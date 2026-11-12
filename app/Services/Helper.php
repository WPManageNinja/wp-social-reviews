<?php

namespace WPSocialReviews\App\Services;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class Helper
{
    const API_KEY_MASK_PREFIX = '****';

    static $loadedTemplates = [];

    public static function getAccessPermission()
    {
        return apply_filters('wpsr_admin_permission', 'manage_options');
    }

    public static function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function createAuthVerifier()
    {
        try {
            return self::base64UrlEncode(random_bytes(32));
        } catch (\Exception $exception) {
            return wp_generate_password(64, false, false);
        }
    }

    public static function authBaseUrl()
    {
        $baseUrl = WPSN_AUTH_BASE_URL;
        $baseUrl = apply_filters('wpsocialreviews/auth_service_url', $baseUrl);

        return rtrim(esc_url_raw($baseUrl), '/');
    }

    public static function decryptAuthPayload($payload, $resultKey)
    {
        $json = self::authPayloadProtector($resultKey)->decrypt($payload);

        if ($json) {
            return $json;
        }

        throw new \RuntimeException(esc_html__('Invalid auth payload.', 'wp-social-reviews'));
    }

    public static function authPayloadProtector($resultKey)
    {
        return new DataProtector(
            hash('sha256', 'result-encryption:' . $resultKey, true),
            hash('sha256', 'result-authentication:' . $resultKey, true)
        );
    }

    public static function verifyAuthConsumeChallenge($result, $verifier)
    {
        $challenge         = Arr::get($result, 'consume_challenge');
        $expectedChallenge = self::base64UrlEncode(hash('sha256', $verifier, true));

        if (!$challenge || !hash_equals($challenge, $expectedChallenge)) {
            throw new \RuntimeException(esc_html__('Invalid auth verifier.', 'wp-social-reviews'));
        }

        unset($result['consume_challenge']);

        return $result;
    }

    /**
     * One Facebook Graph GET, returning the raw response and its decoded body.
     * Shared by the managed-pages and feed pagination loops.
     *
     * @param string $url      Fully-formed Graph endpoint (including access token / paging cursor).
     * @param string $platform Optional platform slug (e.g. 'facebook', 'facebook_feed'). When given,
     *                         fires wpsocialreviews/{$platform}_api_connect_response with the raw
     *                         response so PlatformData can track API connectivity.
     * @return array{response: array|\WP_Error, body: array} Raw WP response and decoded body
     *               (empty array on transport error or non-array payload).
     */
    public static function requestFacebookGraph($url, $platform = 'facebook')
    {
        $response = wp_remote_get($url, ['timeout' => 60]);

        do_action('wpsocialreviews/' . $platform . '_api_connect_response', $response);

        $body = is_wp_error($response)
            ? []
            : json_decode(wp_remote_retrieve_body($response), true);

        return [
            'response' => $response,
            'body'     => is_array($body) ? $body : [],
        ];
    }

    /**
     * Fetch every Facebook page the user manages for an access token, following pagination.
     * Shared by the Facebook feed (free) and Facebook reviews (Pro).
     *
     * @param string $accessToken Facebook user access token.
     * @param int    $limit       Pages requested per Graph call.
     * @param string $platform    Optional platform slug forwarded to requestFacebookGraph so each
     *                            Graph call fires wpsocialreviews/{$platform}_api_connect_response.
     * @return array{account_id: string|null, pages: array<int, array>} The user/account id
     *               and a flat list of raw page objects (each with id, name, access_token).
     * @throws \Exception When the access token is missing or the Graph request errors.
     */
    public static function fetchFacebookManagedPages($accessToken, $limit = 500, $platform = '')
    {
        if (empty($accessToken)) {
            throw new \Exception(esc_html__('Facebook access token is missing.', 'wp-social-reviews'));
        }

        $limit = absint($limit) ?: 500;
        $url   = 'https://graph.facebook.com/me?fields=accounts.limit(' . $limit . '),id,name&access_token=' . $accessToken;

        $accountId = null;
        $pages     = [];
        $nextUrl   = null;
        $isFirst   = true;

        // Cap so a huge account or a repeating `next` cursor can't hang the request.
        // 20 requests at 500 per page covers ~10k pages.
        $maxRequests = 20;
        $requests    = 0;

        do {
            if (++$requests > $maxRequests) {
                throw new \Exception(esc_html__(
                    'This account manages too many Facebook pages to load automatically. Please reconnect and select pages manually.',
                    'wp-social-reviews'
                ));
            }

            $result   = static::requestFacebookGraph($nextUrl ?: $url, $platform);
            $response = $result['response'];

            if (is_wp_error($response)) {
                throw new \Exception(esc_html($response->get_error_message()));
            }

            $body = $result['body'];

            if (!empty(Arr::get($body, 'error'))) {
                throw new \Exception(esc_html(
                    Arr::get($body, 'error.message', __('Unable to fetch Facebook pages.', 'wp-social-reviews'))
                ));
            }

            // The first call returns { id, name, accounts: { data, paging } };
            // each paging.next URL returns the accounts payload ({ data, paging }) directly.
            if ($isFirst) {
                $accountId = Arr::get($body, 'id');
                $accounts  = Arr::get($body, 'accounts', []);
                $isFirst   = false;
            } else {
                $accounts = is_array($body) ? $body : [];
            }

            $data = Arr::get($accounts, 'data', []);
            if (is_array($data)) {
                foreach ($data as $page) {
                    if (Arr::get($page, 'id') && Arr::get($page, 'name')) {
                        $pages[] = $page;
                    }
                }
            }

            // Only follow a paging cursor that stays on the Graph host.
            $nextUrl = Arr::get($accounts, 'paging.next');
            if ($nextUrl && strtolower((string) wp_parse_url($nextUrl, PHP_URL_HOST)) !== 'graph.facebook.com') {
                $nextUrl = null;
            }
        } while ($nextUrl);

        return [
            'account_id' => $accountId,
            'pages'      => $pages,
        ];
    }

    /**
     * Allowed HTML tags for wp_kses in review content.
     *
     * @return array Allowed HTML tags and their attributes
     */
    public static function allowedHtmlTags()
    {
        $allowed_tags = array(
            'br'     => [],
            'p'      => [
                'class' => [],
            ],
            'strong' => [],
            'em'     => [],
            'b'      => [],
            'i'      => [],
            'ul'     => [],
            'ol'     => [],
            'li'     => [],
            'span'   => [
                'class'      => [],
                'aria-label' => [],
                'tabindex'   => [],
            ],
            'a'      => [
                'href'   => [],
                'target' => [],
                'rel'    => [],
            ],
            // Emoji images.
            'img'    => [
                'src'       => [],
                'alt'       => [],
                'class'     => [],
                'draggable' => [],
                'role'      => [],
            ],
        );

        return apply_filters('wpsocialreviews/allowed_html_tags', $allowed_tags);
    }


    /**
     * Sanitize text for display in templates.
     */
    public static function sanitizeText($text, $allow_html = false)
    {
        if (empty($text) || !is_string($text)) {
            return '';
        }

        if ($allow_html) {
            $allowed_tags = static::allowedHtmlTags();
            $text = wp_kses($text, $allowed_tags);
        } else {
            $text = wp_strip_all_tags($text);
            $text = esc_html($text);
        }

        return $text;
    }

    /**
     * Sanitize API/external content before storage.
     */
    public static function sanitizeForStorage($text)
    {
        if (empty($text) || !is_string($text)) {
            return '';
        }

        $text = static::removeObfuscatedScripts($text);
        $text = static::removeEventHandlers($text);
        $text = static::removeDangerousProtocols($text);

        $allowed_tags = static::allowedHtmlTags();
        $text = wp_kses($text, $allowed_tags);

        return trim($text);
    }

    /**
     * Strip embedding and form tags (iframe, object, style, form, svg, ...).
     */
    public static function removeObfuscatedScripts($text)
    {
        $dangerous_tags = [
            'iframe', 'object', 'embed', 'applet', 'meta',
            'link', 'style', 'form', 'input', 'button',
            'textarea', 'select', 'svg', 'math', 'base'
        ];

        foreach ($dangerous_tags as $tag) {
            $text = preg_replace('/<\/?' . $tag . '[^>]*>/i', '', $text);
        }

        return $text;
    }

    /**
     * Remove inline event handler attributes (onclick, onerror, ...).
     */
    public static function removeEventHandlers($text)
    {
        $text = preg_replace('/\s*on\w+\s*=\s*"[^"]*"/i', '', $text);
        $text = preg_replace('/\s*on\w+\s*=\s*\'[^\']*\'/i', '', $text);
        $text = preg_replace('/\s*on\w+\s*=\s*[^\s>]+/i', '', $text);
        // Spaced variants ("on mouse over = ...").
        $text = preg_replace('/\s*on\s+\w+\s*=\s*"[^"]*"/i', '', $text);
        $text = preg_replace('/\s*on\s+\w+\s*=\s*\'[^\']*\'/i', '', $text);

        return $text;
    }

    /**
     * Remove dangerous protocols (javascript:, data:, vbscript:, file:)
     */
    public static function removeDangerousProtocols($text)
    {
        $text = preg_replace('/javascript\s*:/i', '', $text);
        $text = preg_replace('/data\s*:/i', '', $text);
        $text = preg_replace('/vbscript\s*:/i', '', $text);
        $text = preg_replace('/file\s*:/i', '', $text);

        return $text;
    }

    public static function shortNumberFormat($number)
    {
        if(empty($number)){
            return 0;
        }

        $units = ['', 'K', 'M', 'B', 'T'];
        $last = count($units) - 1;
        for ($i = 0; $number >= 1000 && $i < $last; $i++) {
            $number /= 1000;
        }

        // Rounding can push a value back up to 1000 ("999,999" -> "1,000K"). Carry it.
        if (round($number, 1) >= 1000 && $i < $last) {
            $number /= 1000;
            $i++;
        }

        $formatted = number_format($number, 1);
        // "2.0" -> "2", "1.6" stays.
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted . $units[$i];
    }

    public static function getVideoDuration($duration)
    {
        $di   = new \DateInterval($duration);
        $hour = '';
        if ($di->h > 0) {
            $hour .= $di->h . ':';
            return $hour . sprintf('%02d', $di->i) . ':' . sprintf('%02d', $di->s);
        }

        return $di->i . ':' . sprintf('%02d', $di->s);
    }

    public static function numberWithCommas($number)
    {
        return number_format($number, 0, '.', ',');
    }

    public static function shortcodeAllowedPlatforms(){
        return apply_filters('wpsocialreviews/shortcode_allowed_platforms', [
            'reviews',
            'twitter',
            'youtube',
            'instagram',
            'facebook',
            'tiktok',
            'facebook_feed',
            'testimonial'
        ]);
    }

    /**
     * @return int
     */
    public static function getTemplateCount()
    {
        static $templateCount = null;

        if (null === $templateCount) {
            $templates = get_posts([
                'post_type' => ['wp_social_reviews', 'wpsr_reviews_notify', 'wpsr_social_chats', 'wpsr_custom_source'],
                'post_status' => 'publish',
                'numberposts' => -1
            ]);
            $templateCount = count($templates);
        }

        return $templateCount;
    }

    public static function getPostTypes($formatted = true){
        $post_types = get_post_types(
            [
                'public' => true,
                'show_in_nav_menus' => true
            ],
            'objects'
        );

        $post_types = wp_list_pluck($post_types, 'label', 'name');
        $post_types = array_diff_key($post_types, ['attachment']);

        $post_types_list = [];
        if (!empty($post_types)) {
            foreach ($post_types as $key => $post_type) {
                if($formatted) {
                    $post_types_list[] = array(
                        'name' => $key,
                        'title' => $post_type
                    );
                } else {
                    $post_types_list[$key] = $key;
                }
            }
        }

        return apply_filters('wpsocialreviews/available_post_types', $post_types_list);
    }

    /**
     * @return array
     * @since 1.1.0
     */
    public static function getPagesList()
    {
        $pages = get_posts([
            'post_type' => static::getPostTypes(false),
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $page_list = array(array('id' => '-1', 'title' => __('Everywhere', 'wp-social-reviews')));
        if (!empty($pages)) {
            $page_list = array_merge($page_list, static::formatPostResults($pages));
        }

        return $page_list;
    }

    public static function searchPagesList($search = '', $page = 1, $perPage = 20, $includeIds = [], $postType = '')
    {
        $postTypes = !empty($postType) ? [$postType] : static::getPostTypes(false);
        $results = [];

        // Resolve pre-selected IDs so their labels can be shown.
        if (!empty($includeIds) && empty($search) && $page === 1) {
            $query = new \WP_Query([
                'post_type'      => $postTypes,
                'post_status'    => 'publish',
                'post__in'       => $includeIds,
                'posts_per_page' => count($includeIds),
                'orderby'        => 'post__in',
                'no_found_rows'  => true,
            ]);

            return ['results' => static::formatPostResults($query->posts), 'has_more' => false];
        }

        if ($page === 1) {
            $results[] = ['id' => '-1', 'title' => __('Everywhere', 'wp-social-reviews')];
        }

        $args = [
            'post_type'      => $postTypes,
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new \WP_Query($args);
        $results = array_merge($results, static::formatPostResults($query->posts));
        $hasMore = $page < $query->max_num_pages;

        return ['results' => $results, 'has_more' => $hasMore];
    }

    protected static function formatPostResults($posts)
    {
        $results = [];
        foreach ($posts as $post) {
            $lang = '';
            if (defined('POLYLANG_VERSION')) {
                $lang = pll_get_post_language($post->ID, 'name');
                $lang = $lang ? ' (' . $lang . ')' : '';
            }
            $results[] = [
                'id'    => $post->ID . '',
                'title' => ($post->post_title ? $post->post_title : __('Untitled', 'wp-social-reviews')) . $lang,
            ];
        }
        return $results;
    }

    public static function getPostsByPostType($postType = 'post')
    {
        $posts  = get_posts([
            'post_type' => $postType,
            'post_status' => 'publish',
            'numberposts' => -1
        ]);

        $post_lists = array(array('id' => '-1', 'title' => __('Everywhere', 'wp-social-reviews')));
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $post_lists[] = array(
                    'id'    => $post->ID . '',
                    'title' => $post->post_title ? $post->post_title : __('Untitled',
                        'wp-social-reviews'),
                    'url'   => get_permalink($post->ID)
                );
            }
        }

        return $post_lists;
    }

    public static function getShortCodeIds($content, $tag = 'wp_social_ninja', $selector = 'id')
    {

        if (false === strpos($content, '['.$tag)) {
            return [];
        }

        preg_match_all('/' . get_shortcode_regex() . '/', $content, $matches, PREG_SET_ORDER);
        if (empty($matches)) {
            return [];
        }

        $ids = [];

        foreach ($matches as $shortcode) {
            if (count($shortcode) >= 2 && $tag === $shortcode[2]) {
                $parsedCode = str_replace(['[', ']', '&#91;', '&#93;'], '', $shortcode[0]);

                $result = shortcode_parse_atts($parsedCode);

                if (!empty($result[$selector])) {
                    $ids[$result[$selector]] = $result[$selector];
                }
            }
        }

        return $ids;
    }

    public static function isTemplateMatched($settings)
    {
        global $post;

        $hide_on_desktop = Arr::get($settings, 'hide_on_desktop');
        if(!wp_is_mobile() && $hide_on_desktop === 'true'){
            return false;
        }

        $hide_on_mobile = Arr::get($settings, 'hide_on_mobile');
        if($hide_on_mobile === 'true' && wp_is_mobile()){
            return false;
        }

        $postTypes = Arr::get($settings, 'post_types');
        $pageList = Arr::get($settings, 'page_list', []);
        $chat_lang = Arr::get($settings, 'chat_lang', '');

        if ($chat_lang === 'all') {
            $chat_lang = '';
        }

        // An empty page_list means "everywhere".
        if (empty($pageList)) {
            $pageList = ['-1'];
        }

        if(!empty($post) && !empty($postTypes)) {
            if($postTypes && in_array($post->post_type, $postTypes) && $chat_lang && $chat_lang === get_locale()){
                return true;
            }
            if ($chat_lang === '' && $postTypes && in_array($post->post_type, $postTypes)) {
                return true;
            }
        } else {
            $excludePages = Arr::get($settings, 'exclude_page_list', []);
            if(!empty($post)) {
                if (in_array($post->ID, $excludePages) || in_array('-1', $excludePages)) {
                    return false;
                }

                if(defined('WC_VERSION') && is_shop()){
                    $page_id = wc_get_page_id('shop');
                } else {
                    $page_id = $post->ID;
                }

                if((in_array($page_id, $pageList) || in_array('-1', $pageList)) && $chat_lang && $chat_lang === get_locale()){
                    return true;
                }
                if ((in_array($page_id, $pageList) || in_array('-1', $pageList)) && $chat_lang === '') {
                    return true;
                }
            }
        }

        return false;
    }

    public static function hasColumn( $table_name, $column_name ) {

        global $wpdb;

        $cache_key = 'wpsr_has_column_' . md5( $table_name . '_' . $column_name );

        $cached_result = wp_cache_get( $cache_key, 'wpsr_column_checks' );
        if ( $cached_result !== false ) {
            return $cached_result;
        }

        $db_name = defined( 'DB_NAME' ) ? DB_NAME : '';
        if ( empty( $db_name )) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Schema check requires direct query
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            $db_name,
            $table_name,
            $column_name
        ));

        $result = ! empty( $column_exists );

        wp_cache_set( $cache_key, $result, 'wpsr_column_checks', 3600 );

        return $result;
    }

    /**
     * Print internal content (not user input) without escaping.
     */
    public static function printInternalString( $string ) {
        echo $string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function customEncodeEmoji($content) {
        $emoji = ['&#129655;'];

        foreach ( $emoji as $emojum ) {
            $emoji_char = html_entity_decode( $emojum );
            if ( false !== strpos( $content, $emoji_char ) ) {
                $content = preg_replace( "/$emoji_char/", $emojum, $content );
            }
        }

        return $content;
    }

    public static function getSiteDefaultDateFormat($created_at)
    {
        $date_format = get_option('date_format');
        return date_i18n($date_format, $created_at);
    }

    public static function isLocalUrl($url)
    {
        if(empty($url)){
            return false;
        }
        return strpos($url, "wp-content") !== false ? true : false;
    }

    public static function isCustomFeedForTiktokInstalled()
    {
        $plugins = get_plugins();
        $plugin_path = 'custom-feed-for-tiktok/custom-feed-for-tiktok.php';

        if (isset($plugins[$plugin_path])) {
            return true;
        }
        return false;
    }
    
    public static function initializeUploadDirectory($uploadDir)
    {
        if (!is_dir($uploadDir)) {
            wp_mkdir_p($uploadDir);
        }

        $subDirIndexFile = $uploadDir . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($subDirIndexFile)) {
            @file_put_contents($subDirIndexFile, '');
        }

        $root = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . WPSOCIALREVIEWS_UPLOAD_DIR_NAME;

        $rootIndexFile = $root . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($rootIndexFile)) {
            @file_put_contents($rootIndexFile, '');
        }

        $htaccessFile = $root . DIRECTORY_SEPARATOR . '.htaccess';
        
        // A bare "deny from all" .htaccess is replaced with the stub.
        if (file_exists($htaccessFile)) {
            $content = trim(file_get_contents($htaccessFile));
            if (strtolower($content) === 'deny from all') {
                wp_delete_file($htaccessFile);
            }
        }
    
        if (!file_exists($htaccessFile)) {
            @file_put_contents(
                $root. '/.htaccess',
                file_get_contents(__DIR__ . '/Libs/Stubs/htaccess.stub')
            );
        }
    }

    public static function getOptimizeImageFormat()
    {
        $image_format = get_option('wpsr_global_settings', []);
        $image_format = Arr::get($image_format, 'global_settings.advance_settings.optimize_image_format', 'jpg');

        return in_array($image_format, ['jpg', 'webp'], true) ? $image_format : 'jpg';
    }

    public static function getEncryptionErrorData()
    {
        $site_url      = sprintf( '<a href="%s">%s<a/>', esc_url( 'https://wpsocialninja.com/docs/fixing-access-token-decryption-failed-in-wp-social-ninja/' ), __( 'More Information', 'wp-social-reviews' ) );

        return [
            // translators: %1$s is a link to the documentation for fixing the decryption issue.
            'message' => sprintf(__('Your access token could not be decrypted on this website. Need help? Visit our website for easy steps to prevent this issue in the future. %1$s.', 'wp-social-reviews'), $site_url),
            'code' => 999
        ];
    }

    /**
     * Safely unserialize data.
     *
     * @param string $data The serialized data.
     * @return mixed The unserialized data or the original data if not serialized.
     */
    public static function safeUnserialize($data)
    {
        if (is_serialized($data)) {
            return @unserialize(trim($data), ['allowed_classes' => false]);
        }

        return $data;
    }

    /**
     * Normalize text for matching: NFKD, ASCII transliteration and lowercase, so
     * stylized unicode like 𝕾𝖍𝖚𝖓𝖆𝖗 matches plain text.
     *
     * @param string $text
     * @return string
     */
    public static function normalizeText($text)
    {
        $text = trim((string) $text);

        // Feed processing stores # as HASHTAG and @ as MENTION.
        $text = str_replace(array('#', '@'), array('HASHTAG', 'MENTION'), $text);

        if (class_exists('Normalizer')) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KD);
        }

        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
            if ($transliterated !== null) {
                $text = $transliterated;
            }
        } else {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        if (function_exists('mb_strtolower')) {
            $text = mb_strtolower($text, 'UTF-8');
        } else {
            $text = strtolower($text);
        }

        return $text;
    }

    /**
     * Display form of a stored API key. Only the last four characters survive.
     *
     * @param string $plainKey Decrypted key.
     * @return string Masked key, or an empty string when nothing is stored.
     */
    public static function maskApiKey($plainKey)
    {
        $plainKey = is_string($plainKey) ? $plainKey : '';

        if ($plainKey === '') {
            return '';
        }

        return self::API_KEY_MASK_PREFIX . substr($plainKey, -4);
    }

    /**
     * Whether an incoming value is a mask we handed out, i.e. the key is unchanged.
     *
     * @param mixed $value Value posted back by the admin app.
     * @return bool
     */
    public static function isMaskedApiKey($value)
    {
        return is_string($value) && strpos($value, self::API_KEY_MASK_PREFIX) === 0;
    }

}
