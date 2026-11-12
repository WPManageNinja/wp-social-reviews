<?php

namespace WPSocialReviews\App\Http\Controllers;

use WPSocialReviews\Framework\Http\Request\Request;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\Framework\Support\Arr;

class ShoppablesController extends Controller
{
    // The only post type storeTemplateSettings() may write to. Downstream writes run
    // no check of their own, and shoppable settings only live on feed templates.
    const ALLOWED_POST_TYPES = ['wp_social_reviews'];

    /**
     * Get all shoppable settings from options table.
     *
     * @param $request
     *
     * @return array
     * @since 3.7.3
     *
     **/
    public function index(Request $request)
    {
        $postType = sanitize_text_field($request->get('postType', ''));
        $default = array(
            'instagram' => [],
            'facebook' => []
        );
        $settings = get_option('wpsr_global_shoppable_settings', $default);

        $has_item = false;
        foreach ($settings as $setting){
            if(count($setting) >= 1) {
                $has_item = true;
                break;
            }
        }

        return [
            'settings' => $settings,
            'has_item' => $has_item,
            'post_types' => GlobalHelper::getPostTypes(),
        ];
    }

    /**
     * Update shoppable settings
     *
     * @param $request
     *
     * @return array
     * @since 3.7.3
     *
     **/
    public function update(Request $request)
    {
        $settingsPayload = $request->get('settings');

        if (is_string($settingsPayload)) {
            $settingsPayload = $this->decodeJsonParam($settingsPayload) ?: [];
        }

        $validate_rules = ['hashtags' => 'required'];
        $settings = $this->recursive_sanitize($settingsPayload, $validate_rules);

        update_option('wpsr_global_shoppable_settings', $settings);
        return [
            'message' => __("Settings Saved Successfully.",
                'wp-social-reviews'),
        ];
    }

    /**
     * Delete shoppable settings
     *
     * @param $request
     *
     * @return array
     * @since 3.7.3
     *
     **/
    public function delete(Request $request)
    {
        $settingsPayload = $request->get('settings');

        if (is_string($settingsPayload)) {
            $settingsPayload = $this->decodeJsonParam($settingsPayload) ?: [];
        }

        // The client sends the whole replacement option, so sanitize it like update().
        // No extra validate rules are passed, since this endpoint only removes entries.
        $settingsPayload = $this->recursive_sanitize($settingsPayload);

        update_option('wpsr_global_shoppable_settings', $settingsPayload);
        return [
            'message' => __("Deleted Successfully.", 'wp-social-reviews'),
        ];
    }

    public function storeTemplateSettings(Request $request, $postId)
    {
        $post = get_post(absint($postId));

        if (!$post || !in_array($post->post_type, self::ALLOWED_POST_TYPES, true)) {
            return $this->sendError([
                'message' => __('Template not found.', 'wp-social-reviews')
            ], 404);
        }

        $platform = sanitize_key($request->get('platform', ''));

        $raw_data = $this->decodeJsonParam($request->get('shoppable_fields'));

        if (null === $raw_data) {
            return $this->invalidJsonPayload();
        }

        $sanitized_data = $this->recursive_sanitize($raw_data);

        $feed = $this->decodeJsonParam($request->get('feed'));

        // Used as array keys below.
        $feed_username = isset($feed['username']) ? sanitize_text_field($feed['username']) : '';
        $feed_id = isset($feed['id']) ? intval($feed['id']) : 0;

        if ('' === $feed_username || $feed_id <= 0) {
            return $this->invalidJsonPayload();
        }

        // Merge into the stored config, never the request body: this capability owns only
        // the shoppable node. The rest of the template belongs to wpsn_manage_templates.
        $settings = json_decode(get_post_meta($post->ID, '_wpsr_template_config', true), true);

        if (!is_array($settings)) {
            return $this->sendError([
                'message' => __('Template not found.', 'wp-social-reviews')
            ], 404);
        }

        $settings['feed_settings']['shoppable_settings']['shoppable_feeds'][$feed_username][$feed_id] = $sanitized_data;

        do_action('wpsocialreviews/update_editor_settings_' . $platform, $settings, $post->ID);
    }

    public function getPosts(Request $request)
    {
        $postType = sanitize_text_field($request->get('postType', ''));
        return [
            'posts' => GlobalHelper::getPostsByPostType($postType),
        ];
    }

    public function get_sanitize_rules()
    {
        return [
            'url'           => 'sanitize_url',
            'text'          => 'sanitize_text_field',
            'hashtags'      => 'sanitize_text_field'
        ];
    }

    public function recursive_sanitize($settings, $validate_rules = array())
    {
        foreach ($settings as $key => &$value) {
            $source_type = Arr::get($settings, 'source_type', '');
            if (is_array($value)) {
                $value = $this->recursive_sanitize($value, $validate_rules);
                if (!empty($source_type) && $source_type === 'custom_url') {
                    $this->validate($settings['url_settings'], [
                        'url' => 'required'
                    ]);
                }

                if (!empty($source_type) && $source_type !== 'custom_url') {
                    $this->validate($settings['url_settings'], [
                        'id' => 'required'
                    ], [
                        'id.required' => 'The link to field is required.'
                    ]);
                }
            } else {
                if(!empty($validate_rules) && array_key_exists($key, $validate_rules)) {
                    $this->validate($settings, [
                        $key => Arr::get($validate_rules, $key, '')
                    ]);
                }

                if($key === 'id' && !empty($value)) {
                    $settings['url_title'] = get_the_title($value);
                    $settings['url'] = get_the_permalink($value);
                }

                $sanitize_rules = $this->get_sanitize_rules();
                $value = $this->sanitize($value, Arr::get($sanitize_rules, $key, ''));
            }
        }

        return $settings;
    }

    public function sanitize($value, $sanitize_method)
    {
        if(!empty($sanitize_method)) {
            return $sanitize_method($value);
        }

        return $value;
    }
}