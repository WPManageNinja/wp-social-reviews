<?php
/** Minimal faithful Phase 24 copy from app/Hooks/Handlers/YoutubeTemplateHandler.php. */
class Phase24Fixture
{
    function renderPopupFeed()
    {
        if (!check_ajax_referer('wpsr-ajax-nonce', 'security', false)) {
            wp_send_json_error(['message' => __('Security validation failed.', 'wp-social-reviews')], 403);
        }

        $feedId     = Arr::get($_REQUEST, 'feedId'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above
        $templateId = absint(Arr::get($_REQUEST, 'templateId')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above

        $app = App::getInstance();
        $shortcodeHandler = new ShortcodeHandler();

        if (!$shortcodeHandler->isPublicRenderableTemplate($templateId)) {
            wp_send_json_error(['message' => __('Invalid template.', 'wp-social-reviews')], 403);
        }

        $template_meta = $shortcodeHandler->templateMeta($templateId, 'youtube');
        $feeds = (new YoutubeFeed())->getTemplateMeta($template_meta, $templateId);
        $settings = $shortcodeHandler->formatFeedSettings($feeds);

        $feeds = Arr::get($settings, 'feeds', []);
        $feed  = YoutubeHelper::getFeedById($feedId, $feeds);

        $gdpr_settings = (new YoutubeFeed())->getGdprSettings('youtube');
        $dp = $gdpr_settings['optimized_images'] === 'true' ? Arr::get($settings, 'header.avatar.local_avatar', '') : Arr::get($settings, 'header.items.0.snippet.thumbnails.high.url', '');
        if($settings['header']){
           $settings['header']['avatar'] = $dp;
        }


        $app->view->render('public.feeds-templates.youtube.popup', array(
            'header'        => $settings['header'],
            'feed'          => $feed,
            'template_meta' => $settings['feed_settings'],
            'image_settings' => $gdpr_settings
        ));
        die();
    }

    function renderFluentPlayer()
    {
        if (!check_ajax_referer('wpsr-ajax-nonce', 'security', false)) {
            wp_send_json_error(['message' => __('Security validation failed.', 'wp-social-reviews')], 403);
        }

        $templateId = absint(Arr::get($_REQUEST, 'templateId')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above
        $videoId    = sanitize_text_field(Arr::get($_REQUEST, 'videoId', '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above
        $duration   = absint(Arr::get($_REQUEST, 'duration', 0)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above

        $shortcodeHandler = new ShortcodeHandler();

        if (!$shortcodeHandler->isPublicRenderableTemplate($templateId)) {
            wp_send_json_error(['message' => __('Invalid template.', 'wp-social-reviews')], 403);
        }

        $template_meta = $shortcodeHandler->templateMeta($templateId, 'youtube');
        $feed_settings = Arr::get($template_meta, 'feed_settings', []);

        $html = FluentPlayerEmbed::render($videoId, $feed_settings, [
            'autoplay' => true,
            // The length comes from the feed the browser already rendered, so no
            // per-click feed lookup is needed. Only re-anchored layers use it, and
            // a spoofed value would only shift that visitor's own layer timing.
            'duration' => Arr::get($feed_settings, 'video_settings.fluent_player_layers_at_end') === 'true'
                ? $duration
                : 0
        ]);

        if ($html === '') {
            wp_send_json_error(['message' => __('Fluent Player is not available for this feed.', 'wp-social-reviews')], 404);
        }

        wp_send_json_success(['html' => $html]);
    }
}
