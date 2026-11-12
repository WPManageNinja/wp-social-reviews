<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms;

use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\Framework\Http\Request\Request;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper;
use WPSocialReviews\App\Services\DashboardNotices;
use WPSocialReviews\App\Services\Platforms\PlatformErrorManager;
use WPSocialReviews\Framework\Support\Arr;

class PlatformController extends Controller
{
    public function index()
    {
        $platforms = Helper::validPlatforms();

        return [
            'message'   => 'success',
            'platforms' => $platforms
        ];
    }

    public function getDashboardNotices(Request $request, DashboardNotices $notices)
    {
        $hasAdminErrors = (new PlatformErrorManager())->getAdminErrors();
        $displayOptInNotice = $notices->maybeDisplayOptIn();
        $displayProUpdateNotice = $notices->maybeDisplayProUpdateNotice();


        $current_user = wp_get_current_user();
        if (!empty($current_user->user_email)) {
            $email = $current_user->user_email;
        } else {
            $email = get_option('admin_email');
        }

        $userData = [
            'name'  => $current_user->first_name . ' ' .$current_user->last_name,
            'email' => $email,
        ];

        // Add-on notices: a list of ['type', 'title', 'message', 'dismissible', 'actions' =>
        // [['label', 'url', 'target']]]. Title and message render as plain text, so add-ons
        // must not put HTML in them. Unsafe URL schemes are dropped.
        $customNotices = apply_filters('wpsocialreviews/dashboard_notices', []);
        if (!is_array($customNotices)) {
            $customNotices = [];
        }

        if ($notices->isReconnectSnoozed()) {
            $customNotices = array_filter($customNotices, function ($notice) {
                return !is_array($notice) || !isset($notice['group']) || $notice['group'] !== 'reconnect';
            });
        }

        wp_send_json_success([
            'displayNotice' => empty($hasAdminErrors) && $notices->getNoticesStatus(),
            'displayOptInNotice'  => empty($hasAdminErrors) && $displayOptInNotice,
            'displayProUpdateNotice' => $displayProUpdateNotice,
            'userData' => $notices->maybeDisplayNewsletter() ? $userData : [],
            'displayNewsletter' => empty($hasAdminErrors) && $notices->maybeDisplayNewsletter(),
            'customNotices' => array_values($customNotices),
            'googleReconnectPlatforms' => $notices->getGoogleReconnectPlatforms()
        ], 200);
    }

    public function updateDashboardNotices(Request $request, DashboardNotices $notices)
    {
        $args = $request->get('args');

        $sanitizeMap = [
            'notice_type' => 'sanitize_text_field',
            'value' => 'rest_sanitize_boolean',
        ];

        $args = wpsr_backend_sanitizer($args, $sanitizeMap);

        $value = sanitize_text_field(Arr::get($args, 'value', ''));
        $notices->updateNotices($args);

        wp_send_json_success([
            'displayNotice' => $notices->getNoticesStatus(),
            'displayOptInNotice' => $notices->maybeDisplayOptIn(),
            'displayNewsletter' => !($value === '1'),
            'googleReconnectPlatforms' => $notices->getGoogleReconnectPlatforms()
        ], 200);
    }

    public function processSubscribeQuery(Request $request, DashboardNotices $notices)
    {
        $args = $request->get('args');
        $sanitizeMap = [
            'email' => 'sanitize_email',
            'name' => 'sanitize_text_field',
        ];
        $args = wpsr_backend_sanitizer($args, $sanitizeMap);
        $status = $notices->updateNewsletter($args);

        return [
            'status'  => 'success',
            'message' => $status,
            'displayNewsletter' => $notices->maybeDisplayNewsletter(),
        ];
    }

    public function enabledPlatforms(Request $request)
    {
        $reviewsPlatforms   = apply_filters('wpsocialreviews/available_valid_reviews_platforms', []);
        $feedPlatforms      = apply_filters('wpsocialreviews/available_valid_feed_platforms', []);
        $platforms = $reviewsPlatforms + $feedPlatforms;
        if(!empty($feedPlatforms)){
            $platforms['social_wall'] = __('Social Wall', 'wp-social-reviews');
        }
        
        do_action('wpsocialreviews/active_platforms', $platforms);

        $hasAdminErrors = (new PlatformErrorManager())->getAdminErrors();
        return [
            'notices' => $hasAdminErrors,
            'platforms'   => $platforms
        ];
    }

    public function getStatuses()
    {
        $statuses = get_option('wpsr_statuses', []);

        // The Vue component supplies the defaults.
        if (!isset($statuses['platforms_statuses'])) {
            $statuses['platforms_statuses'] = [
                'feeds' => [],
                'reviews' => [],
                'chats' => []
            ];
        }

        return [
            'statuses'  => $statuses,
        ];
    }

    public function updateStatuses(Request $request)
    {
        $statuses = $request->get('statuses');
        if (!is_array($statuses)) {
            return $this->sendError(['message' => 'Invalid statuses']);
        }

        $existingStatuses = get_option('wpsr_statuses', []);

        $platformsStatuses = [
            'platforms_statuses' => [
                'feeds' => [],
                'reviews' => [],
                'chats' => []
            ]
        ];

        $types = ['feeds', 'reviews', 'chats'];

        foreach ($types as $type) {
            if (isset($statuses[$type]) && is_array($statuses[$type])) {
                foreach ($statuses[$type] as $platform => $value) {
                    $clean = rest_sanitize_boolean($value);
                    $platformsStatuses['platforms_statuses'][$type][$platform] = $clean;
                }
            }
        }

        $finalStatuses = array_merge($existingStatuses, $platformsStatuses);

        update_option('wpsr_statuses', $finalStatuses);

        return [
            'message' => __('Statuses updated successfully!', 'wp-social-reviews'),
        ];
    }
}
