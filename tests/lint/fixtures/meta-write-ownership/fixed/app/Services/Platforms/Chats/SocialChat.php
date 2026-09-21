<?php
/** Minimal faithful Phase 24 copy from app/Services/Platforms/Chats/SocialChat.php. */
class Phase24Fixture
{
    function updateSettings($postId = null, $args = [])
    {
        global $wpdb;
        $charset = $wpdb->get_col_charset( $wpdb->posts, 'post_content' );
        if('utf8' === $charset || 'utf8mb3' === $charset) {
            $args[$args['template']]['chat_body']['greeting_msg'] = wp_encode_emoji($args[$args['template']]['chat_body']['greeting_msg']);
        }

        $args['settings']['start_chat_time'] = chatHelper::formatedLocalTimeToUTCTime($args['settings']['start_time']);
        $args['settings']['end_chat_time'] = chatHelper::formatedLocalTimeToUTCTime($args['settings']['end_time']);

        if(isset($args['menu_order'])) {
            $menuOrder = $args['menu_order'];
            unset($args['menu_order']);
            $db = App::getInstance('db');
            // post_type is re-asserted here: this is a raw UPDATE that bypasses the
            // WordPress post API, so it must not rely on the caller's guard alone.
            $db->table('posts')->where('ID', $postId)
                ->where('post_type', 'wpsr_social_chats')
                ->update([
                    'menu_order' => absint($menuOrder)
                ]);
        }

        $settings = array(
            'chat_settings' => $args
        );

        // Remove template from onboarding sessions since it's now been edited
        if (Arr::get($settings, 'chat_settings.created_from_onboarding')) {
            OnboardingHelper::removeFromOnboardingSessions($postId);
        }

        $settings = 'utf8mb3' === $charset ? json_encode($settings, JSON_UNESCAPED_UNICODE) : serialize($settings);
        update_post_meta($postId, '_wpsr_template_config', $settings);

        // Clear LiteSpeed cache if plugin is active
        if(defined('LSCWP_V')) {
            do_action( 'litespeed_purge_post', $postId ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        }

        wp_send_json_success([
            'message'   => __('Successfully Updated', 'wp-social-reviews'),
        ], 200);
    }
}
