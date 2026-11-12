<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Reviews;

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Services\Platforms\PlatformData;
use WPSocialReviews\Framework\Foundation\Application;
use WPSocialReviews\Framework\Http\Request\Request;
use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\App\Services\Platforms\ReviewImageOptimizationHandler;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper as ReviewsHelper;
use WPSocialReviews\Framework\Support\Arr;

class ConfigsController extends Controller
{
    protected $app = null;

    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function index(Request $request)
    {
        $platformName   = sanitize_text_field($request->get('platform', ''));
        $credential     = $this->app->applyCustomFilters('api_credential_' . $platformName, []);
        $additionalInfo = $this->app->applyCustomFilters('additional_info_' . $platformName, []);

        $response = [
            'credential'      => $credential,
            'additional_info' => $additionalInfo
        ];

        // Opt-in pagination for long lists (e.g. connected products). Page/per_page go to
        // the business_info_* filter so platforms can slice before the costly enrichment.
        if ($request->has('per_page')) {
            $perPage = max(1, intval($request->get('per_page', 10)));
            $page    = max(1, intval($request->get('page', 1)));

            // Count from the raw option; enrichment only runs for the current page.
            $rawBusinessInfo = get_option('wpsr_reviews_' . $platformName . '_business_info', []);
            $total = is_array($rawBusinessInfo) ? count($rawBusinessInfo) : 0;

            $response['business_info'] = $this->app->applyCustomFilters('business_info_' . $platformName, [], $page, $perPage);
            $response['pagination']    = [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ];
        } else {
            $response['business_info'] = $this->app->applyCustomFilters('business_info_' . $platformName, []);
        }

        return $response;
    }

    public function store(Request $request)
    {
        $platform = sanitize_text_field($request->get('platform', ''));
        $configs  = $request->get('verificationData');
        $configs = sanitize_text_field((string) $configs);
        $this->app->doCustomAction('save_configs' . $platform, $configs);
    }

    public function prepare(Request $request)
    {
        $wpsr_sanitize_map = [
            'platform'   => 'sanitize_key',
            'provider'   => 'sanitize_key',
            'flow'       => 'sanitize_key',
            'return_url' => 'esc_url_raw',
            'source'     => 'sanitize_text_field'
        ];
        $wpsr_request_data = $request->all();
        $wpsr_settings = wpsr_backend_sanitizer($wpsr_request_data, $wpsr_sanitize_map);
        $wpsr_platform = Arr::get($wpsr_settings, 'platform', '');

        if (!$wpsr_platform) {
            return $this->sendError([
                'message' => __('Missing platform data.', 'wp-social-reviews')
            ], 423);
        }

        // Handlers come from BaseReview::registerHooks. Pro-only platforms have none
        // while Pro is inactive; their UI is gated behind has_pro.
        return $this->dispatchAction(
            'wpsocialreviews/prepare_review_auth_' . $wpsr_platform,
            [$wpsr_settings],
            __('No authentication handler is registered for this platform.', 'wp-social-reviews')
        );
    }

    public function consume(Request $request)
    {
        $wpsr_sanitize_map = [
            'platform'  => 'sanitize_key',
            'result_id' => 'wpsr_sanitize_opaque_token',
            'flow_id'   => 'wpsr_sanitize_opaque_token',
            'payload'   => 'wpsr_sanitize_opaque_token'
        ];
        $wpsr_request_data = $request->all();
        $wpsr_settings = wpsr_backend_sanitizer($wpsr_request_data, $wpsr_sanitize_map);
        $wpsr_platform = Arr::get($wpsr_settings, 'platform', '');

        if (!$wpsr_platform) {
            return $this->sendError([
                'message' => __('Missing platform data.', 'wp-social-reviews')
            ], 423);
        }

        return $this->dispatchAction(
            'wpsocialreviews/consume_review_auth_' . $wpsr_platform,
            [$wpsr_settings],
            __('No authentication handler is registered for this platform.', 'wp-social-reviews')
        );
    }

    public function saveReviews(Request $request)
    {
        $settings = $request->get('settings');

        $sanitizeMap = [
            'api_key'      => 'sanitize_text_field',
            'source_id'    => 'sanitize_text_field', // IDs can exceed int range
            'product_name' => 'sanitize_text_field',
            'count'        => 'intval',
            'platform'     => 'sanitize_text_field',
            'url_value'    => 'esc_url_raw',
            'credentialsType' => 'sanitize_text_field',
            'language'     => 'sanitize_text_field',
            // booking.com: picked from the hotel search (see searchBusiness)
            'hotel_id'       => 'intval',
            'ufi'            => 'intval',
            'hotel_name'     => 'sanitize_text_field',
            'hotel_country'  => 'sanitize_text_field',
            'hotel_location' => 'sanitize_text_field',
            // Which saved property this hotel replaces, or "new" for none of them.
            'legacy_choice'  => 'sanitize_text_field'
        ];

        $settings = wpsr_backend_sanitizer($settings, $sanitizeMap);

        $platformForAction = '';
        if (isset($settings['platform'])) {
            $platformForAction = sanitize_text_field($settings['platform']);
        }

        $this->app->doCustomAction('verify_review_credential_' . $platformForAction, $settings);
    }

    /**
     * Business search for platforms connected by name rather than URL/key (booking.com today).
     * Handlers respond with JSON: { results: [...] }.
     */
    public function searchBusiness(Request $request)
    {
        $wpsr_sanitize_map = [
            'platform' => 'sanitize_text_field',
            'query'    => 'sanitize_text_field',
            'language' => 'sanitize_text_field'
        ];
        $wpsr_request_data = $request->all();
        $wpsr_settings = wpsr_backend_sanitizer($wpsr_request_data, $wpsr_sanitize_map);
        $wpsr_platform = Arr::get($wpsr_settings, 'platform', '');

        if (!$wpsr_platform) {
            return $this->sendError([
                'message' => __('Missing platform data.', 'wp-social-reviews')
            ], 423);
        }

        return $this->dispatchAction(
            'wpsocialreviews/search_review_business_' . $wpsr_platform,
            [$wpsr_settings],
            __('Business search is not available for this platform.', 'wp-social-reviews')
        );
    }

    public function manuallySyncReviews(Request $request)
    {
        $platform = sanitize_text_field($request->get('platform', ''));
        $credentials = $request->get('credentials');

        $sanitizeMap = [
            'place_id'        => 'sanitize_text_field',
            'name'            => 'sanitize_text_field',
            'url'             => 'esc_url_raw',
            'address'         => 'sanitize_text_field',
            'average_rating'  => 'floatval',
            'total_rating'    => 'intval',
            'phone'           => 'sanitize_text_field',
            'platform_name'   => 'sanitize_text_field',
            'status'          => 'sanitize_text_field',
            'error_message'   => 'sanitize_text_field',
            'has_app_permission_error' => 'rest_sanitize_boolean',
            'has_critical_error'       => 'rest_sanitize_boolean',
            'error_code'      => 'intval',
            'encryption_error' => 'rest_sanitize_boolean',
            'connection_type'  => 'sanitize_text_field'
        ];

        $credentials = wpsr_backend_sanitizer($credentials, $sanitizeMap);

        $this->app->doCustomAction($platform . '_manually_sync_reviews', $credentials);
    }

    public function delete(Request $request)
    {
        $platform           = sanitize_text_field($request->get('platform', ''));
        $sourceId           = sanitize_text_field($request->get('sourceId', ''));
        $settings_option_name = 'wpsr_reviews_' . $platform . '_settings';
        $business_info_option_name = 'wpsr_reviews_' . $platform . '_business_info';
        $settings           = get_option($settings_option_name, []);
        $businessInfo       = get_option($business_info_option_name, []);
        $settings           = is_array($settings) ? $settings : [];
        $businessInfo       = is_array($businessInfo) ? $businessInfo : [];

        do_action('wpsocialreviews/clear_reviews_verification_configs_' . $platform, $sourceId);

        if($sourceId && $sourceId !== 'clear-locations'){
            unset($settings[$sourceId]);
            unset($businessInfo[$sourceId]);
        }
        update_option($settings_option_name, $settings, 'no');
        update_option($business_info_option_name, $businessInfo, 'no');

	    (new CacheHandler($platform))->clearCacheByName($business_info_option_name.'_' . $sourceId);
        (new PlatformData($platform))->deleteLastUsedTime($sourceId);

        if((is_array($settings) && count($settings) === 0) || (is_array($businessInfo) && count($businessInfo) === 0) || $sourceId === 'clear-locations') {
            delete_option($settings_option_name);
            delete_option('last_fetched_tripadvisor_review_id');
            if ($platform === 'google') {
                delete_option('wpsr_reviews_google_connected_accounts');
                if($sourceId === 'clear-locations'){
                    delete_option('wpsr_reviews_google_locations_list');
                }
            }

            if ($platform === 'facebook' && $sourceId === 'clear-locations') {
                delete_option('wpsr_reviews_facebook_pages_list');
            }

            if($sourceId === 'clear-locations'){
                Review::where('platform_name', $platform)
                    ->delete();
            }
        }

        if ($sourceId === 'clear-locations') {
            (new ReviewImageOptimizationHandler([$platform]))->deleteBusinessMediaByUserName($platform, $sourceId);
        } elseif ($platform !== 'fluent-cart') {
            // FluentCart reviews come only from our forms and can't be re-fetched, so disconnect keeps them for a reconnect.
            ReviewsHelper::deleteBusinessReviews($platform, $sourceId);
        }

        return [
            'message' => __('Clear Configurations', 'wp-social-reviews')
        ];
    }
}
