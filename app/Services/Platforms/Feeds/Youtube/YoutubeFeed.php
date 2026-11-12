<?php

namespace WPSocialReviews\App\Services\Platforms\Feeds\Youtube;

use WPSocialReviews\App\Services\GlobalSettings;
use WPSocialReviews\App\Services\Onboarding\OnboardingHelper;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\Platforms\Feeds\BaseFeed;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\App\Services\Platforms\Feeds\Common\FeedFilters;
use WPSocialReviews\App\Services\Platforms\Feeds\Config;
use WPSocialReviews\App\Services\Platforms\Feeds\Youtube\Config as YoutubeConfig;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Models\Cache;
use WPSocialReviews\App\Services\DataProtector;
use WPSocialReviews\App\Services\Platforms\ImageOptimizationHandler;

if (!defined('ABSPATH')) {
    exit;
}

class YoutubeFeed extends BaseFeed
{
    /**
     * Keys in wpsr_youtube_verification_configs encrypted at rest. Beyond the
     * DataProtector default (access/refresh token), YouTube also stores api_key and access_code.
     */
    const TOKEN_CONFIG_KEYS = ['access_token', 'refresh_token', 'api_key', 'access_code'];

    public $platform = 'youtube';
    protected $authProvider = 'google';
    protected $authFlow = 'youtube';
    protected $postId = null;
    protected $oauth;
    protected $cacheHandler;
    private $remoteFetchUrl = 'https://www.googleapis.com/youtube/v3/';
    private $cronScheduleName = 'wpsr_youtube_feed_update';

    public function __construct()
    {
        parent::__construct($this->platform);
        $this->oauth        = new OAuth();
        $this->cacheHandler = new CacheHandler($this->platform);
         (new ImageOptimizationHandler($this->platform))->registerHooks();
    }

    public function pushValidPlatform($platforms)
    {
        $isActive = get_option('wpsr_'.$this->platform.'_verification_configs');
        if($isActive) {
            $platforms['youtube'] = __('Youtube', 'wp-social-reviews');
        }
        return $platforms;
    }

    /**
     * Save YouTube config on wp options table
     *
     * @return void
     * @since 1.2.5
     */
    public function handleCredential($settings = array())
    {
        try {
            $credentialsType = $settings['credentialsType'];
            $apiKey          = $settings['apiKey'];
            $accessCode      = $settings['accessCode'];
            $accessToken     = $settings['accessToken'];

            $url  = $this->makeConfigAPIRequestUrl($credentialsType, $accessCode, $apiKey, $accessToken);
            $data = $this->getAPIData($url['fetchUrl'], $url['args']);

			if (is_wp_error($data)) {
				$message = $data->get_error_message();
				wp_send_json_error([
					'message' => $message
				], 423);
			}

            if (isset($data['error']) || !empty($data['error'])) {
                $code = isset($data['error']['code']) ? $data['error']['code'] : 401;
                wp_send_json_error([
                    'message' => isset($data['error']['errors'][0]) ? $data['error']['errors'][0]['message'] : __('Sorry, Something went wrong!', 'wp-social-reviews')
                ], $code);
            }

            $configs  = $this->prepareVerificationConfigs($data, $url, $credentialsType, $apiKey, $accessCode);
            $settings = $this->saveVerificationConfigs($configs);

            wp_send_json_success([
                'settings' => $settings,
                'message'  => __('You are Successfully Verified', 'wp-social-reviews')
            ]);
        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 423);
        }
    }

    public function persistOAuthTokenConfig($token = [], $account = [])
    {
        $accessToken = Arr::get($token, 'access_token', '');
        if (!$accessToken) {
            throw new \Exception(esc_html__('YouTube access token is missing.', 'wp-social-reviews'));
        }

        $existing = (new DataProtector())->maybe_decrypt_tokens(get_option('wpsr_youtube_verification_configs', []), self::TOKEN_CONFIG_KEYS);
        $url = [
            'accessToken'   => $accessToken,
            'refresh_token' => Arr::get($token, 'refresh_token', Arr::get($existing, 'refresh_token', '')),
            'expires_in'    => Arr::get($token, 'expires_in', Arr::get($existing, 'expires_in', '')),
        ];

        if (!Arr::get($account, 'channel_id')) {
            $requestUrl = $this->makeConfigAPIRequestUrl('manually_connect', '', '', $accessToken);
            $data       = $this->getAPIData($requestUrl['fetchUrl'], $requestUrl['args']);

            if (is_wp_error($data)) {
                throw new \Exception(esc_html($data->get_error_message()));
            }

            if (isset($data['error']) || empty($data['items'][0])) {
                $message = Arr::get($data, 'error.errors.0.message', __('Sorry, Something went wrong!', 'wp-social-reviews'));
                throw new \Exception(esc_html($message));
            }
        } else {
            $data = $this->formatAccountAsApiResponse($account);
        }

        $configs = $this->prepareVerificationConfigs($data, $url, 'oauth2.0', '', '', Arr::get($token, 'created_at', time()));
        $configs['auth_app'] = 'v2';

        return $this->saveVerificationConfigs($configs);
    }

    /**
     * Make api request url base on credentials type
     *
     * @param string $credentialsType
     * @param string $accessCode
     * @param string $apiKey
     * @param string $accessToken
     *
     * @return array
     * @throws \Exception
     * @since 1.2.5
     */
    private function makeConfigAPIRequestUrl($credentialsType = '', $accessCode = '', $apiKey = '', $accessToken = '')
    {
        $url = [];
        if (($credentialsType === 'oauth2.0' && empty($accessCode)) || ($credentialsType === 'api_key' && empty($apiKey)) || ($credentialsType === 'manually_connect' && empty($accessToken))) {
            wp_send_json_error([
                'message' => __('Field should not be empty!', 'wp-social-reviews')
            ], 423);
        }
        if (($credentialsType === 'oauth2.0' || $credentialsType === 'manually_connect')) {
            if ($accessCode && $credentialsType === 'oauth2.0') {
                // Legacy access-code OAuth is kept for compatibility and will be deprecated soon.
                $result = $this->oauth->generateAccessKey($accessCode);
                if (is_wp_error($result)) {
                    throw new \Exception(esc_html($result->get_error_message()));
                }
                $tokens['credentials_type'] = 'oauth2.0';
                $accessToken                = $result['access_token'];
                $refreshToken               = $result['refresh_token'];
                $expireTime                 = $result['expires_in'];
                $url['expires_in']          = $expireTime;
                $url['refresh_token']       = $refreshToken;
            }
            $args               = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ];
            $url['args']        = $args;
            $url['accessToken'] = $accessToken;
            $part               = 'mine=true&access_token=' . $accessToken;
            $fetchUrl           = 'https://www.googleapis.com/youtube/v3/channels?part=id,snippet&contentDetails&' . $part;
            $url['fetchUrl']    = $fetchUrl;
        }
        if ($credentialsType === 'api_key' && $apiKey) {
            $part            = 'key=' . $apiKey;
            $fetchUrl        = 'https://www.googleapis.com/youtube/v3/search?part=snippet&q=YouTube+Data+API&type=video&' . $part;
            $url['fetchUrl'] = $fetchUrl;
            $url['args']     = [];
        }

        return $url;
    }

    private function prepareVerificationConfigs($data, $url, $credentialsType, $apiKey = '', $accessCode = '', $createdAt = null)
    {
        $configs = [];
        if ((!empty($data) && isset($data['items'][0])) && ($credentialsType === 'oauth2.0' || $credentialsType === 'manually_connect')) {
            $configs['channel_id']  = Arr::get($data, 'items.0.id', '');
            $configs['user_name']   = Arr::get($data, 'items.0.snippet.title', '');
            $configs['user_avatar'] = Arr::get($data, 'items.0.snippet.thumbnails.high.url', '');
        } else {
            $configs['channel_id'] = 'UCiyeXfnGx9e06hXWf0Hz7ow';
        }

        $configs['access_token']     = Arr::get($url, 'accessToken', '');
        $configs['refresh_token']    = Arr::get($url, 'refresh_token', '');
        $configs['expires_in']       = Arr::get($url, 'expires_in', '');
        $configs['version']          = 'latest';
        $configs['api_key']          = $apiKey;
        $configs['access_code']      = $accessCode;
        $configs['credentials_type'] = $credentialsType;
        $configs['created_at']       = $createdAt ?: time();

        return $configs;
    }

    private function formatAccountAsApiResponse($account)
    {
        return [
            'items' => [
                [
                    'id'      => Arr::get($account, 'channel_id', ''),
                    'snippet' => [
                        'title'      => Arr::get($account, 'user_name', ''),
                        'thumbnails' => [
                            'high' => [
                                'url' => Arr::get($account, 'user_avatar', '')
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function saveVerificationConfigs($configs)
    {
        update_option('wpsr_youtube_verification_configs', (new DataProtector())->maybe_encrypt_tokens($configs, self::TOKEN_CONFIG_KEYS), 'no');
        $this->ensureDefaultGlobalSettings();

        return $configs;
    }

    private function ensureDefaultGlobalSettings()
    {
        if (get_option('wpsr_youtube_global_settings')) {
            return;
        }

        update_option('wpsr_youtube_global_settings', [
            'global_settings' => [
                'expiration'   => 259200,
                'caching_type' => 'background'
            ]
        ], 'no');
    }

    protected function persistAuthResult($result)
    {
        if (!Arr::get($result, 'ok')) {
            return $result;
        }

        $platform = Arr::get($result, 'platform', '');
        if ($platform !== $this->platform) {
            throw new \RuntimeException(esc_html__('Invalid auth platform response.', 'wp-social-reviews'));
        }

        $token = Arr::get($result, 'token', []);
        $result['settings'] = $this->persistOAuthTokenConfig($token, Arr::get($result, 'account', []));
        $result['platform'] = $this->platform;

        return $result;
    }

    /**
     * Get the verified YouTube configs
     *
     * @return void
     * @since 1.2.5
     */
    public function getVerificationConfigs()
    {
        $configs = (new DataProtector())->maybe_decrypt_tokens(get_option('wpsr_youtube_verification_configs'), self::TOKEN_CONFIG_KEYS);
        $configs['version'] = Arr::get($configs, 'version', '');
        $configs['needs_reconnect'] = static::usesPreviousAuthApp();

        wp_send_json_success([
            'message'  => __('Youtube Successfully Connected!', 'wp-social-reviews'),
            'settings' => $configs
        ], 200);
    }

    /**
     * Whether the OAuth connection still runs on the previous OAuth app.
     *
     * @return bool
     */
    public static function usesPreviousAuthApp()
    {
        $configs = (new DataProtector())->maybe_decrypt_tokens(get_option('wpsr_youtube_verification_configs', []), self::TOKEN_CONFIG_KEYS);

        return is_array($configs)
            && Arr::get($configs, 'credentials_type') === 'oauth2.0'
            && !empty($configs['refresh_token'])
            && Arr::get($configs, 'auth_app') !== 'v2';
    }

    /**
     * Delete the YouTube configs
     *
     * @return void
     * @since 1.2.5
     */
    public function clearVerificationConfigs()
    {
        $has_wpsr_optimize_images_table = get_option( 'wpsr_optimize_images_table_status', false);
        if($has_wpsr_optimize_images_table){
            $imageOptimizationObj = new ImageOptimizationHandler($this->platform);
            $imageOptimizationObj->resetData($this->platform);
        }

        delete_option('wpsr_youtube_verification_configs');
        $this->cacheHandler->clearCache();
        wp_send_json_success([
            'message' => __('Youtube Successfully Disconnected!', 'wp-social-reviews'),
        ], 200);
    }

    public function getTemplateMeta($settings = [], $postId = null)
    {
        $this->postId = $postId;
        $feed_settings = Arr::get($settings, 'feed_settings', []);
        $apiSettings   = Arr::get($feed_settings, 'source_settings', []);
        $response      = $this->apiConnection($apiSettings, $postId);

        if (isset($response['error_message'])) {
            $filterResponse = $response;
        } else {
            $filterResponse = (new FeedFilters())->filterFeedResponse($this->platform, $feed_settings, $response);
        }

        $settings['dynamic'] = $filterResponse;

        $global_settings = get_option('wpsr_youtube_global_settings');
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');

        $optimized_images = Arr::get($global_settings, 'global_settings.optimized_images', 'false');
        $has_gdpr = Arr::get($advanceSettings, 'has_gdpr', "false");

        $items = $settings['dynamic']['items'] ?? [];
        foreach ($items as $index => $item) {
            $configs = get_option('wpsr_youtube_verification_configs');
            $userAvatar = $configs['user_avatar']  ?? null;
            $accountId = $configs['channel_id'] ?? null;
            $headerMeta = 'avatars';

            $imageOptimizationHandler = new ImageOptimizationHandler($this->platform);
            if (method_exists($imageOptimizationHandler, 'maybeLocalHeader')) {
                $local_avatar = $imageOptimizationHandler->maybeLocalHeader($accountId, $userAvatar, $global_settings, $headerMeta);
                $settings['dynamic']['items'][$index]['user_avatar'] = $local_avatar ?? $userAvatar;
            }
        }

        if ($has_gdpr === "true" && $optimized_images == "false") {
            $settings['dynamic']['items'] = [];
            $settings['dynamic']['header'] = [];
            $settings['dynamic']['error_message'] = [
                'error_message' => __('YouTube feeds are not being displayed due to the "optimize images" option being disabled. If the GDPR settings are set to "Yes," it is necessary to enable the optimize images option.', 'wp-social-reviews')
            ];
        }

        if (Arr::get($settings, 'feed_settings.created_from_onboarding')) {
            OnboardingHelper::applyOnboardingSettings($postId, 'youtube', $settings);
        }

        return $settings;
    }

    /**
     * Format the YouTube template settings together with live feed data
     *
     * @return array
     * @since 1.2.5
     */
    public function getEditorSettings($args = [])
    {
        $postId = Arr::get($args, 'postId');
        $youtubeConfig = new YoutubeConfig();

        $feed_meta       = get_post_meta($postId, '_wpsr_template_config', true);
        $feed_style_meta = get_post_meta($postId, '_wpsr_template_styles_config', true);
        $decodedMeta     = json_decode($feed_meta, true);
        $feed_settings   = Arr::get($decodedMeta, 'feed_settings', []);
        $feed_settings   = Config::formatYoutubeConfig($feed_settings, []);
        $settings        = $this->getTemplateMeta($feed_settings, $postId);
        $settings['styles_config'] = $youtubeConfig->formatStylesConfig(json_decode($feed_style_meta, true), $postId);

        $global_settings = get_option('wpsr_'.$this->platform.'_global_settings');
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');

        $image_settings = [
            'optimized_images' => Arr::get($global_settings, 'global_settings.optimized_images', 'false'),
            'has_gdpr' => Arr::get($advanceSettings, 'has_gdpr', "false")
        ];

        $templateDetails = get_post($postId);
        wp_send_json_success([
            'message'          => __('Success', 'wp-social-reviews'),
            'settings'         => $settings,
            'image_settings'   => $image_settings,
            'template_details' => $templateDetails,
            'elements'         => $youtubeConfig->getStyleElement(),
            'default_styles'   => $youtubeConfig->formatStylesConfig([], $postId)
        ], 200);
    }

    /**
     * Show updated data if new data fetched in editor without update in db
     *
     * @return void
     * @throws \Exception
     * @since 1.2.5
     */
    public function editEditorSettings($configs = [], $postId = null)
    {
        $editMode = $configs['edit_mode'];
        unset($configs['edit_mode']);

        $styles_config = Arr::get($configs, 'styles_config');

        $feed_meta     = Arr::get($configs, 'feed_settings', []);
        $feed_settings = Config::formatYoutubeConfig($feed_meta, []);
        $feedType      = isset($feed_meta['source_settings']['feed_type']) ? $feed_meta['source_settings']['feed_type'] : 'channel_feed';

        if($editMode){
            $errorCacheName = 'yt_'. $feedType .'_' .$postId . '_error';
            $this->cacheHandler->clearCacheByName($errorCacheName);
        }
        if ($feedType === 'single_video' && $editMode === 'fetching') {
            $cacheName = 'single_video_feed_id_' . $postId;
            $this->cacheHandler->clearCacheByName($cacheName);
        }

        $settings = $this->getTemplateMeta($feed_settings, $postId);
        $settings['styles_config'] = $styles_config;

        $global_settings = get_option('wpsr_'.$this->platform.'_global_settings');
        $advanceSettings = (new GlobalSettings())->getGlobalSettings('advance_settings');

        $image_settings = [
            'optimized_images' => Arr::get($global_settings, 'global_settings.optimized_images', 'false'),
            'has_gdpr' => Arr::get($advanceSettings, 'has_gdpr', "false")
        ];
        $settings['image_settings'] = $image_settings;

        wp_send_json_success([
            'message'  => __('Youtube Settings Updated', 'wp-social-reviews'),
            'settings' => $settings,
            'postId'   => $postId
        ], 200);
    }

    /**
     * Save the template settings from the editor
     *
     * @return void
     * @since 1.2.5
     */
    public function updateEditorSettings($settings = [], $postId = null)
    {
        if(defined('WPSOCIALREVIEWS_PRO') && class_exists('\WPSocialReviewsPro\App\Services\TemplateCssHandler')){
            (new \WPSocialReviewsPro\App\Services\TemplateCssHandler())->saveCss($settings, $postId);
        }

        if (Arr::get($settings, 'feed_settings.created_from_onboarding')) {
            OnboardingHelper::removeFromOnboardingSessions($postId);
        }

        // Runtime-only keys, not part of the saved _wpsr_template_config.
        $unsetKeys = ['dynamic', 'styles_config', 'styles', 'responsive_styles'];
        foreach ($unsetKeys as $key){
            if(Arr::get($settings, $key, false)){
                unset($settings[$key]);
            }
        }

        $encodedMeta = json_encode($settings, JSON_UNESCAPED_UNICODE);
        update_post_meta($postId, '_wpsr_template_config', $encodedMeta);
        wp_send_json_success([
            'message' => __('Template Saved Successfully!!', 'wp-social-reviews'),
        ], 200);
    }

    /**
     * Handle Api key or token
     *
     * @return string|array
     * @since 1.2.5
     */
    public function getApiKeyOrToken()
    {
        $apiCredentials       = (new DataProtector())->maybe_decrypt_tokens(get_option('wpsr_youtube_verification_configs', true), self::TOKEN_CONFIG_KEYS);
        $youtubeApiKeyOrToken = '';
        $youtubeAccessToken   = '';
        $youtubeApiKey        = '';

        if (isset($apiCredentials['credentials_type']) && $apiCredentials['credentials_type'] === 'oauth2.0') {
            $youtubeAccessToken = $this->oauth->getAccessToken();
            if (!$youtubeAccessToken) {
                return ['error_message' => __('Error in API key settings,Please check your API key is valid or not.', 'wp-social-reviews')];
            }
        } elseif (isset($apiCredentials['credentials_type']) && $apiCredentials['credentials_type'] === 'api_key') {
            $youtubeApiKey = $apiCredentials['api_key'];
        } elseif (isset($apiCredentials['credentials_type']) && $apiCredentials['credentials_type'] === 'manually_connect') {
            $youtubeAccessToken = $apiCredentials['access_token'];
        }

        if (!empty($youtubeAccessToken) && empty($youtubeApiKey)) {
            $youtubeApiKeyOrToken = 'access_token=' . $youtubeAccessToken . '';
        } else {
            $youtubeApiKeyOrToken = 'key=' . $youtubeApiKey . '';
        }

        if (!$youtubeAccessToken && !$youtubeApiKey) {
            return ['error_message' => __('No credential found. Please configure your YouTube settings correctly.', 'wp-social-reviews')];
        }

        return $youtubeApiKeyOrToken;
    }

    public function getApiDetails($channelId, $playlistId, $feedType, $eventType, $searchTerm, $total)
    {
        if ($feedType === 'channel_feed') {
            return $this->getChannelApiUrlDetails($channelId, $total, $feedType);
        } elseif ($feedType === 'playlist_feed') {
            return apply_filters('wpsocialreviews/youtube_playlist_api_url_details', $playlistId, $total, $this->remoteFetchUrl);
        } elseif ($feedType === 'search_feed') {
            return apply_filters('wpsocialreviews/youtube_search_api_url_details', $searchTerm, $total, $this->remoteFetchUrl);
        } elseif ($feedType === 'live_streams_feed') {
            return apply_filters('wpsocialreviews/youtube_live_streams_api_url_details', $channelId, $eventType, $total, $this->remoteFetchUrl);
        }
    }

    /**
     * Fetch the feed's videos from the YouTube API
     *
     * @param $apiSettings
     * @param $postId
     *
     * @return array
     * @since 1.2.5
     */
    public function apiConnection($apiSettings = [], $postId = null)
    {
        $total    = (int)Arr::get($apiSettings, 'feed_count', '50');
        $feedType = Arr::get($apiSettings, 'feed_type', 'channel_feed');
        $channelId  = Arr::get($apiSettings, 'channel_id', '');
        $playlistId = Arr::get($apiSettings, 'playlist_id', '');
        $searchTerm = Arr::get($apiSettings, 'search_term', '');
        $videoIds   = Arr::get($apiSettings, 'video_id', '');
        $eventType  = Arr::get($apiSettings, 'event_type', '');

        if (empty($channelId) && empty($playlistId) && empty($searchTerm) && empty($videoIds)) {
            $position = strpos($feedType, '_');
            $type     = substr($feedType, 0, $position);
            $type     = (($type === 'live') ? 'channel' : (($type === 'single') ? 'single videos' : $type));
            $suffix   = $feedType === 'search_feed' ? 'term' : 'id';
            // translators: Please retain the placeholders (%s, %d, etc.) and ensure they are correctly used in context.
            return ['error_message' => sprintf(__('Please enter %1$s %2$s to fetch videos!!', 'wp-social-reviews'), $type, $suffix)];
        }

        if (!defined('WPSOCIALREVIEWS_PRO') && $feedType !== 'channel_feed') {
            return ['error_message' => __('Please upgrade to pro version to use this features!! ', 'wp-social-reviews')];
        }

        if ($feedType === 'single_video') {
            $videoIds      = array_map('trim', explode(",", $videoIds));
            $feedCacheName = 'single_video_feed_id_' . $postId . '_num_' . count($videoIds);

            return $this->getSingleVideoFeeds($videoIds, $feedCacheName);
        }

        return $this->getYoutubeFeed($channelId, $playlistId, $feedType, $eventType, $searchTerm, $total, true);
    }

    public function sendApiRequest($youtubeFeedApiUrl, $feedType)
    {
        $youtubeApiKeyOrToken = $this->getApiKeyOrToken();
        if (isset($youtubeApiKeyOrToken['error_message'])) {
            return ['error_message' => $youtubeApiKeyOrToken];
        }

        $args     = array(
            'timeout'   => 60
        );
        $feeds = $this->getAPIData($youtubeFeedApiUrl . $youtubeApiKeyOrToken, $args);
        if (is_wp_error($feeds)) {
            $message = $feeds->get_error_message();
            return ['error_message' => $message];
        }

        if (isset($feeds['error']['message'])) {
            $error = $feeds['error']['message'];
            return ['error_message' => $error];
        }

        $videoIds = $this->getVideoIds($feeds);
        $nextPageToken = Arr::get($feeds, 'nextPageToken', '');

        if (is_array($videoIds)) {
            $videoIds = array_map('strval', array_filter($videoIds, 'is_scalar'));
            $videoIds = implode(',', $videoIds);
        }

        $parts = 'id,snippet,status';
        $parts = apply_filters('wpsocialreviews/youtube_api_parts', $parts, $feedType);

        $youtubeUrl = $this->remoteFetchUrl . 'videos?part=' . $parts . '&id=' . $videoIds . '&';
        $videoLists = [];
        $response   = wp_remote_get($youtubeUrl . $youtubeApiKeyOrToken);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();

            return ['error_message' => $message];
        }

        $videoLists = json_decode(wp_remote_retrieve_body($response), true);

        $errors     = $this->checkYoutubeError($videoLists);
        if (is_array($errors) && (true === $errors[0] || 1 === $errors[0])) {
            return ['error_message' => isset($errors[1]) ? $errors[1] : __('Problem in api key or access token setting!!', 'wp-social-reviews')];
        }

        $videoLists = $this->getPublishVideos($videoLists);

        $videoLists['nextPageToken'] = $nextPageToken;

        return $videoLists;
    }

    /**
     * Keep only public videos
     *
     * @return array
     * @throws \Exception
     * @since 1.2.5
     */
    public function getPublishVideos($videoList)
    {
        $videos = Arr::get($videoList, 'items' , []);
        $publicVideos = [];
        foreach($videos as $video)
        {
            // The feed is fetched with the site owner's OAuth token, so private and
            // unlisted videos come back too and must not reach the front end.
            $videoStatus = Arr::get($video, 'status.privacyStatus', '');
            if($videoStatus === 'public')
            {
                $publicVideos[] = $video;
            }
        }

        $videoList['items'] = $publicVideos;

        return $videoList;
    }

    /**
     * The editor allows up to 200 videos; a site's own youtube_feeds_limit filter has the final
     * word. Each page of 50 costs API quota, and a search page costs 100 units.
     */
    public function resolveFeedLimit($total, $feedType)
    {
        $total = min(absint($total), 200);

        return max(1, absint(apply_filters('wpsocialreviews/youtube_feeds_limit', $total, $feedType)));
    }

    /**
     * Youtube Feeds Without Single Video
     *
     * @return array
     * @throws \Exception
     * @since 1.2.5
     */
    public function getYoutubeFeed($channelId = '', $playlistId = '', $feedType = 'channel_feed', $eventType = '', $searchTerm = '', $total = 50, $hasCache = false)
    {
        // Resolved before getApiDetails(): the count is part of the feed's cache name.
        $total = $this->resolveFeedLimit($total, $feedType);

        $errorCacheName = 'yt_'.$feedType.'_'. $this->postId . '_error';
        // A cached error saves a repeat API call that would fail the same way.
        if($this->postId && $errorCacheName){
            $errorCache = $this->cacheHandler->getFeedCache($errorCacheName);
            if ($errorCache && isset($errorCache['error_message'])) {
                return $errorCache;
            }
        }

        $apiDetails = $this->getApiDetails($channelId, $playlistId, $feedType, $eventType, $searchTerm, $total);

        if (isset($apiDetails['error_message'])) {
            if($this->postId){
                $this->cacheHandler->createCache($errorCacheName, $apiDetails);
            }
            return $apiDetails;
        }

        $youtubeFeedApiUrl = Arr::get($apiDetails, 'api_url');
        $feedCacheName     = Arr::get($apiDetails, 'cache_name');

        if(empty($youtubeFeedApiUrl)) {
            return ['error_message' => __('No videos found. Make sure this is a valid channel ID and that the channel has videos available on YouTube.', 'wp-social-reviews')];
        }

        if ($youtubeFeedApiUrl) {
            $videoLists = [];
            if($hasCache) {
                $videoLists = $this->cacheHandler->getFeedCache($feedCacheName);
            }

            if(isset($videoLists['items']) && empty($videoLists['items'])){
                return ['error_message' => __('No videos found. Please make sure the provided input is correct and the content is publicly available on YouTube.', 'wp-social-reviews')];
            }

            if (empty($videoLists)) {
                $pages = (int)($total/50);
                $pages += (int)(($total%50) > 0);

                $currPage = 1;
                $curTotal = min($total, 50);
                $videoLists = $this->sendApiRequest($youtubeFeedApiUrl.'maxResults='.$curTotal.'&', $feedType);
                $nextPageToken = Arr::get($videoLists, 'nextPageToken');
                while($currPage < $pages && !empty($nextPageToken)) {
                    $curTotal = min($total-(50*$currPage), 50);
                    $currYtFeedApiUrl = $youtubeFeedApiUrl . 'maxResults=' . $curTotal . '&pageToken=' . $nextPageToken . '&';
                    $feeds = $this->sendApiRequest($currYtFeedApiUrl, $feedType);
                    $nextPageToken = Arr::get($feeds, 'nextPageToken');
                    $videoLists['items'] = array_merge(Arr::get($videoLists, 'items', []), Arr::get($feeds, 'items', []));
                    $currPage++;
                }

                $videoLists = $this->sanitizeFeedItems($videoLists);

                $this->cacheHandler->createCache($feedCacheName, $videoLists);
            }

            if ($feedType === 'playlist_feed' && isset($videoLists['items']) && count($videoLists['items'])) {
                foreach ($videoLists['items'] as $video) {
                    if (isset($video['snippet']['channelId'])) {
                        $channelId = $video['snippet']['channelId'];
                        break;
                    }
                }
            }

            $channelCacheName = 'channel_header_' . $channelId;
            $channelInfo = [];
            if($hasCache) {
                $channelInfo = $this->cacheHandler->getFeedCache($channelCacheName);
            }

            if (empty($channelInfo)) {
                $channelInfo = $this->getChannelInfo($feedType, $videoLists, $channelId);
            }

            $videoLists['header'] = $channelInfo;

            return $videoLists;
        }
    }

    /**
     * Youtube Single Video Feeds
     *
     * @param array $videoIds video ids
     *
     * @return array
     * @throws \Exception
     * @since 1.2.5
     */
    public function getSingleVideoFeeds($videoIds, $feedCacheName)
    {
        if (empty($videoIds)) {
            return ['error_message' => __('Please enter a video id!! ', 'wp-social-reviews')];
        }

        $videoLists = [];
        // 'status' lets getPublishVideos() drop private and unlisted videos.
        $parts      = 'id,snippet,status';
        $parts      = apply_filters('wpsocialreviews/youtube_api_parts', $parts, 'single_video');

	    $videoLists = $this->cacheHandler->getFeedCache($feedCacheName);

        if(isset($videoLists['items']) && empty($videoLists['items'])){
            return ['error_message' => __('No videos found. Please make sure the provided input is correct and the content is publicly available on YouTube.', 'wp-social-reviews')];
        }

        if (!$videoLists) {
            $youtubeApiKeyOrToken = $this->getApiKeyOrToken();
            if (isset($youtubeApiKeyOrToken['error_message'])) {
                return ['error_message' => $youtubeApiKeyOrToken];
            }

            $videoIds           = implode(',', $videoIds);
            $channelVideoApiUrl = $this->remoteFetchUrl . 'videos?part=' . $parts . '&id=' . $videoIds . '&';
            $videoLists         = $this->checkYoutubeCache($channelVideoApiUrl, $feedCacheName, 'single_video');

            $videoLists = $this->sanitizeFeedItems($videoLists);
        }

        $videoLists['header'] = '';

        return $videoLists;
    }

    /**
     * Youtube Channel Api Url Details
     *
     * @param string $channelIdentifier Channel ID, username or handle
     * @param integer $total total
     * @param string $feedType feed type
     *
     * @return array
     * @throws \Exception
     * @since 1.2.5
     */
    public function getChannelApiUrlDetails($channelIdentifier, $total, $feedType = '')
    {
        $feedCacheName     = '';
        $youtubeFeedApiUrl = '';
        if (empty($channelIdentifier)) {
            return ['error_message' => __('Please enter Channel ID, Username or Handle to fetch videos!!', 'wp-social-reviews')];
        }

        $channelApiParams = $this->determineChannelIdentifierType($channelIdentifier);

        if (isset($channelApiParams['error_message'])) {
            return $channelApiParams;
        }

        $channelCacheName     = 'channel_header_' . $channelIdentifier;
        $youtubeChannelApiUrl = $this->remoteFetchUrl . 'channels?part=id,snippet,contentDetails,statistics,brandingSettings&maxResults=' . $total . '&' . $channelApiParams . '&';
        $videos               = $this->checkYoutubeCache($youtubeChannelApiUrl, $channelCacheName, $feedType);

        if(Arr::get($videos, 'error_message')){
            return ['error_message' => $videos['error_message']];
        }

        $resultSet = isset($videos['pageInfo']['resultsPerPage']) ? $videos['pageInfo']['resultsPerPage'] : 0;
        if (!$resultSet) {
            return ['error_message' => __('Can\'t find any videos to the channel!!', 'wp-social-reviews')];
        }
        $playlist = isset($videos['items'][0]['contentDetails']['relatedPlaylists']['uploads']) ? $videos['items'][0]['contentDetails']['relatedPlaylists']['uploads'] : '';
        if ($playlist) {
            $feedCacheName     = 'channel_feed_id_' . $channelIdentifier . '_num_' . $total;
            $youtubeFeedApiUrl = $this->remoteFetchUrl . 'playlistItems?part=id,snippet&playlistId=' . $playlist . '&';
        }

        return [
            'cache_name' => $feedCacheName,
            'api_url'    => $youtubeFeedApiUrl
        ];
    }

    /**
     * Map a channel identifier (handle, channel ID or username) to API parameters
     *
     * @param string $identifier
     * @return string|array Array with error message on invalid input
     */
    private function determineChannelIdentifierType($identifier)
    {
        $identifier = trim($identifier);

        if (empty($identifier)) {
            return [
                'error_message' => __('Channel identifier cannot be empty. Please enter a valid Channel ID, Username, or Handle', 'wp-social-reviews')
            ];
        }

        // Handle, e.g. @name
        if (strpos($identifier, '@') === 0) {
            $handle = ltrim($identifier, '@');
            if (!preg_match('/^(?![_\-.·])[a-zA-Z0-9_\-\.·]{3,30}(?<![_\-.·])$/u', $handle)) {
                return [
                    'error_message' => __('Invalid YouTube handle format. Handles should be 3-30 characters and contain only letters, numbers, underscores, and periods.', 'wp-social-reviews')
                ];
            }

            return 'forHandle=' . $handle;
        }

        // Channel ID: UC followed by 22 characters
        if (strpos($identifier, 'UC') === 0) {
            if (!preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $identifier)) {
                return [
                    'error_message' => __('Invalid Channel ID format. YouTube Channel IDs start with "UC" followed by 22 characters.', 'wp-social-reviews')
                ];
            }

            return 'id=' . $identifier;
        }

        // Legacy username
        if (preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $identifier)) {
            return 'forUsername=' . $identifier;
        }

        return [
            'error_message' => __('Invalid channel identifier. Please enter a valid Channel ID, Username, or Handle', 'wp-social-reviews')
        ];
    }

    /**
     * Fetch YouTube API data
     *
     * @param string $fetchUrl
     * @param array $args
     *
     * @return array|\WP_Error
     * @since 1.2.5
     */
    public function getAPIData($fetchUrl = '', $args = [])
    {
        $response = wp_remote_get($fetchUrl, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response = json_decode(wp_remote_retrieve_body($response), true);

        return $response;
    }

    public function getChannelInfo($feedType, $videoLists, $channelId)
    {
        if ($feedType === 'search_feed' || $feedType === 'single_video_feed') {
            return;
        }

        $youtubeApiKeyOrToken = $this->getApiKeyOrToken();
        if (isset($youtubeApiKeyOrToken['error_message'])) {
            return ['error_message' => $youtubeApiKeyOrToken];
        }

        $feedTypes = ['playlist_feed', 'channel_feed', 'live_streams_feed'];
        if (!empty($channelId) && in_array($feedType, $feedTypes)) {
            if($feedType === 'live_streams_feed') {
                $channelId = Arr::get($videoLists, 'items.0.snippet.channelId', '');
            }

            $channelApiParams = $this->determineChannelIdentifierType($channelId);
            if (isset($channelApiParams['error_message'])) {
                return $channelApiParams;
            }

            $channelCacheName = 'channel_header_' . $channelId;
            $youtubeChannelApiUrl = $this->remoteFetchUrl . 'channels?part=id,snippet,contentDetails,statistics,brandingSettings&maxResults=1&' . $channelApiParams . '&';
            $response             = wp_remote_get($youtubeChannelApiUrl . $youtubeApiKeyOrToken);

            if (is_wp_error($response)) {
                $message = $response->get_error_message();
                return ['error_message' => $message];
            }
            $channelInfo = json_decode(wp_remote_retrieve_body($response), true);
            $errors      = $this->checkYoutubeError($channelInfo);

            if (is_array($errors) && (true === $errors[0] || 1 === $errors[0])) {
                return ['error_message' => isset($errors[1]) ? $errors[1] : __('Problem in api key or access token setting!!', 'wp-social-reviews')];
            }

            $this->cacheHandler->createCache($channelCacheName, $channelInfo);
            return $channelInfo;
        }
    }

    public function getVideoIds($feeds = [])
    {
        $videoIds = [];
        if (isset($feeds['items']) && count($feeds['items'])) {
            foreach ($feeds['items'] as $index => $feed) {
                $videoId = Helper::getVideoId($feed);
                if (!empty($videoId)) {
                    $videoIds[] = $videoId;
                }
            }
        }

        return $videoIds;
    }

    /**
     * Check the YouTube API data for an error
     *
     * @param array $feedData feed data
     *
     * @return array|null [true, message] when the payload carries an API error, null otherwise
     * @throws \Exception
     * @since 1.2.5
     */
    public function checkYoutubeError($feedData)
    {
        try {
            if (isset($feedData['error']) && 400 === $feedData['error']['code']) {
                throw new \Exception($feedData['error']['code'] . ' - A VALID access token is required to request this resource.');
            }
            if (isset($feedData['error'])) {
                $output = '';
                if (isset($feedData['error']['message'])) {
                    $output = 'Error: ' . $feedData['error']['message'];
                }
                if (isset($feedData['error']['code'])) {
                    $output .= '<br />Code: ' . $feedData['error']['code'];
                }
                throw new \Exception($output);
            }
        } catch (\Exception $e) {
            return [true, $e->getMessage()];
        }

        return null;
    }

    /**
     * Handle YouTube Cache
     *
     * @param string $youtubeUrl youtube url
     * @param string $cacheName cache name
     *
     * @return array|string|null null when there is neither fresh data nor a cache to fall back on
     * @throws \Exception
     * @since 1.2.5
     */
    public function checkYoutubeCache($youtubeUrl, $cacheName, $feedType = '')
    {
		$feedCache = $this->cacheHandler->getFeedCache($cacheName);

        if ($feedCache) {
            return $feedCache;
        }

        $youtubeApiKeyOrToken = $this->getApiKeyOrToken();
        if (isset($youtubeApiKeyOrToken['error_message'])) {
	        return ['error_message' => $youtubeApiKeyOrToken['error_message']];
        }

        $response = wp_remote_get($youtubeUrl . $youtubeApiKeyOrToken);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();

            return ['error_message' => $message];
        }

        $feedData = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($feedData) && !empty($cacheName)) {
            $wpsr_error_check_complete = $this->checkYoutubeError($feedData);
            if (is_array($wpsr_error_check_complete) && (true === $wpsr_error_check_complete[0] || 1 === $wpsr_error_check_complete[0])) {
	            $feedCache = $this->cacheHandler->getFeedCache($cacheName);
				if ($feedCache) {
                    return $feedCache;
                }

                return ['error_message' => isset($wpsr_error_check_complete[1]) ? $wpsr_error_check_complete[1] : __('Problem in api key or access token setting!!', 'wp-social-reviews')];
            } elseif (!$this->cacheHandler->getFeedCache($cacheName)) {
                if ($feedType === 'single_video') {
                    // Filter before caching: the response includes private and unlisted videos.
                    $feedData = $this->getPublishVideos($feedData);
                }
                $this->cacheHandler->createCache($cacheName, $feedData);
                return $feedData;
            }
        } else {
            if (!empty($cacheName) && empty($feedData)) {
	            $feedCache = $this->cacheHandler->getFeedCache($cacheName);
                if ($feedCache) {
                    return $feedCache;
                }
            }
        }

        return null;
    }

    public function updateCachedFeeds($caches)
    {
        foreach ($caches as $index => $cache) {
            $optionName = $cache['option_name'];
            $event_type = '';
            $searchTerm = '';
            $feed_type  = '';
            $channelId  = '';
            $playlistId = '';
            $num_position = strpos($optionName, '_num_');

            $search_term_position = strpos($optionName, '_search_term_');
            if ($search_term_position) {
                $searchTerm = substr($optionName, $search_term_position + strlen('_search_term_'),
                    $num_position - ($search_term_position + strlen('_search_term_')));
            }

            $total = substr($optionName, $num_position + strlen('_num_'),
                strlen($optionName) - ($num_position + strlen('_num_')));

            $id_position = strpos($optionName, '_id_');
            $id          = substr($optionName, $id_position + strlen('_id_'),
                $num_position - ($id_position + strlen('_id_')));

            $separator        = '_feed';
            $feed_position    = strpos($optionName, $separator) + strlen($separator);
            $initial_position = 0;
            if ($feed_position) {
                $feed_type = substr($optionName, $initial_position, $feed_position - $initial_position);
            }

            if ($feed_type === 'live_streams_feed') {
                $event_type_position = strpos($optionName, '_event_type_');
                $event_type          = substr($optionName, $event_type_position + strlen('_event_type_'),
                    strlen($optionName) - ($event_type_position + strlen('_event_type_')));
                $total               = substr($optionName, $num_position + strlen('_num_'),
                    $event_type_position - ($num_position + strlen('_num_')));
            }

            $total = (int)$total;
            $feedTypes = ['channel_feed', 'playlist_feed', 'single_video_feed', 'search_feed', 'live_streams_feed'];
            if (in_array($feed_type, $feedTypes)) {
                if ($feed_type === 'single_video_feed' && !empty($id)) {
                    $feed_meta     = get_post_meta($id, '_wpsr_template_config', true);
                    $decodedMeta   = json_decode($feed_meta, true);
                    $feed_settings = Arr::get($decodedMeta, 'feed_settings', []);
                    $template_meta = Config::formatYoutubeConfig($feed_settings, []);
                    $videoIds      = $template_meta['feed_settings']['source_settings']['video_id'];
                    $videoIds      = array_map('trim', explode(",", $videoIds));
                    $feedCacheName = 'single_video_feed_id_' . $id . '_num_' . count($videoIds);
                    $this->getSingleVideoFeeds($videoIds, $feedCacheName);
                } else {
                    if ($feed_type === 'channel_feed' || $feed_type == 'live_streams_feed') {
                        $channelId  = $id;
                    } elseif ($feed_type === 'playlist_feed') {
                        $playlistId = $id;
                    }

                    $this->getYoutubeFeed($channelId, $playlistId, $feed_type, $event_type, $searchTerm, $total);
                }
            }
        }
    }

    /**
     * Sanitize user-generated content in YouTube feed items
     *
     * @param array $videoLists Array of video feed data
     * @return array Sanitized video feed data
     */
    public function sanitizeFeedItems($videoLists)
    {
        if (!isset($videoLists['items']) || !is_array($videoLists['items'])) {
            return $videoLists;
        }

        foreach ($videoLists['items'] as &$item) {
            if (isset($item['snippet']['title'])) {
                $item['snippet']['title'] = GlobalHelper::sanitizeForStorage($item['snippet']['title']);
            }

            if (isset($item['snippet']['description'])) {
                $item['snippet']['description'] = GlobalHelper::sanitizeForStorage($item['snippet']['description']);
            }
        }
        unset($item);

        return $videoLists;
    }

    public function clearCache()
    {
        $this->cacheHandler->clearCache();
        wp_send_json_success([
            'message' => __('Cache cleared successfully!', 'wp-social-reviews'),
        ], 200);
    }
}
