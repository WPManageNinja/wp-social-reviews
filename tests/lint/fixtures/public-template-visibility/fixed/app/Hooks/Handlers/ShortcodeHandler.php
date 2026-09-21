<?php
/** Minimal faithful Phase 24 copy from app/Hooks/Handlers/ShortcodeHandler.php. */
class Phase24Fixture
{
    function isPublicRenderableTemplate($wpsr_template_id, $wpsr_extra_post_types = [])
    {
        $wpsr_template_id = absint($wpsr_template_id);

        if (empty($wpsr_template_id)) {
            return false;
        }

        $wpsr_template_post = get_post($wpsr_template_id);

        if (empty($wpsr_template_post)) {
            return false;
        }

        $wpsr_allowed_post_types = apply_filters(
            'wpsocialreviews/public_renderable_template_post_types',
            array_merge(['wp_social_reviews'], (array) $wpsr_extra_post_types)
        );

        if (!in_array($wpsr_template_post->post_type, $wpsr_allowed_post_types, true)) {
            return false;
        }

        // A published but password-protected template must still satisfy the post
        // password challenge; anonymous AJAX callers never do, so fall through to the
        // edit_post capability check (admin previews) rather than exposing its feed.
        if ($wpsr_template_post->post_status === 'publish' && !post_password_required($wpsr_template_post)) {
            return true;
        }

        return current_user_can('edit_post', $wpsr_template_id);
    }

    function makeShortcode($args = [], $content = null, $tag = '')
    {
        // Skip processing during Elementor save/editor to prevent memory exhaustion
        if (defined('ELEMENTOR_VERSION')) {
            // skip during REST API requests when Elementor is active (block editor compatibility)
            if (defined('REST_REQUEST') && REST_REQUEST) {
                return '';
            }
        }

        $args = shortcode_atts(array(
            'id' => '',
            'platform' => '',
            'product_id' => '',
        ), $args);

        if (!$args['id']) {
            return '';
        }
        $platform = sanitize_text_field(Arr::get($args, 'platform', ''));
        $product_id = absint(Arr::get($args, 'product_id', 0));

        if (empty($platform) || empty($args['id'])) {
            return __('Please, set a template platform name or template id on your shortcode', 'wp-social-reviews');
        }

        $templateId = absint($args['id']);

        if (!in_array($platform, GlobalHelper::shortcodeAllowedPlatforms())) {
            return __('Provided platform name is not valid.', 'wp-social-reviews');
        }

        // Same visibility policy as the AJAX renderers: never render a draft, private,
        // password-protected, or wrong-post-type template on a public page. Users who
        // can edit the template still get admin previews via the edit_post fallback.
        if (!$this->isPublicRenderableTemplate($templateId)) {
            return '';
        }

        $this->platform = $platform;
        $this->productId = $product_id;

        if (!did_action('wp_enqueue_scripts')) {
            $this->registerStyles();
        }

        $this->enqueueStyles([$this->platform]);

        $platformName = $platform === 'facebook_feed' ? 'Facebook' : $platform;
        $methodName = str_replace('_', ucfirst($platformName), 'render_Template');
        $optimize_platform = $platform == 'testimonial' ? 'reviews' : $platform;
        $this->imageSettings = Helper::getImageSettings($optimize_platform);

        if ($platformName === 'tiktok') {
            return apply_filters('wpsocialreviews/render_tiktok_template', $templateId, $platform);
        } else {
            return $this->{$methodName}($templateId, $platform);
        }

    }

    function handleLoadMoreAjax()
    {
        if (!check_ajax_referer('wpsr-ajax-nonce', 'security', false)) {
            wp_send_json_error([
                'message' => __('Invalid nonce.', 'wp-social-reviews')
            ], 403);
        }

        $templateId = absint(Arr::get($_REQUEST, 'template_id'));

        // Read before the check below, which uses it to pick the allowed post types.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $platform = sanitize_text_field(Arr::get($_REQUEST, 'platform'));

        // Notification popups render a reviews feed from their own post type, so allow it here only.
        $wpsr_extra_post_types = $platform === 'reviews' ? ['wpsr_reviews_notify'] : [];

        if (!$this->isPublicRenderableTemplate($templateId, $wpsr_extra_post_types)) {
            wp_send_json_error([
                'message' => __('Invalid template.', 'wp-social-reviews')
            ], 403);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $page = absint(Arr::get($_REQUEST, 'page', 2));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $feed_type = sanitize_text_field(Arr::get($_REQUEST, 'feed_type' , ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $feed_id = sanitize_text_field(Arr::get($_REQUEST, 'feed_id' , null));

        list($order, $exactRating, $wpsr_search) = $this->readReviewSortParams();

        // Reviews only, since the other feeds share this endpoint; within reviews it covers every request.
        if ($platform === 'reviews' && !Helper::allowVisitorFeedRequest($wpsr_search !== '')) {
            wp_send_json_error([
                'message' => $wpsr_search !== ''
                    ? __('Too many searches. Please wait a moment and try again.', 'wp-social-reviews')
                    : __('Too many requests. Please wait a moment and try again.', 'wp-social-reviews')
            ], 429);
        }

        // Append pages may reuse the first page's total to skip the COUNT; it never sizes the page.
        $knownTotal = null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $requestedTotal = Arr::get($_REQUEST, 'total_reviews');
        if ($page > 1 && is_numeric($requestedTotal) && absint($requestedTotal) > 0) {
            $knownTotal = absint($requestedTotal);
        }

        // Product scope captured at render time, so a re-fetch stays on that product.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $wpsr_product_id = absint(Arr::get($_REQUEST, 'product_id', 0));

        // Shuffle seed echoed back so a re-fetch repeats the order; absint() because it reaches ORDER BY.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading AJAX parameters for pagination, not processing sensitive form data
        $wpsr_random_seed = absint(Arr::get($_REQUEST, 'seed', 0));

        $responseExtras = [];

        if ($platform == 'youtube') {
            $content = (new YoutubeTemplateHandler())->getPaginatedFeedHtml($templateId, $page);
        } else if ($platform == 'twitter') {
            $content = (new TwitterTemplateHandler())->getPaginatedFeedHtml($templateId, $page);
        } else if ($platform == 'facebook_feed') {
            $content = (new FacebookFeedTemplateHandler())->getPaginatedFeedHtml($templateId, $page, $feed_id, $feed_type);
        } else if ($platform === 'tiktok') {
            $content = apply_filters('wpsocialreviews/get_paginated_feed_html', $templateId, $page);
        }else if ($platform == 'reviews') {
            $this->platform = 'reviews';
            $this->imageSettings = Helper::getImageSettings($platform);
            $feedData = (new ReviewsTemplateHandler())->getPaginatedFeedData($templateId, $page, $this->imageSettings, $order, $exactRating, $knownTotal, $wpsr_product_id, $wpsr_random_seed, $wpsr_search);

            // Reviews also answer with the total and applied sort/rating/search; other platforms stay content-only.
            $content = Arr::get($feedData, 'content', '');
            $wpsr_has_more = Arr::get($feedData, 'has_more');

            $responseExtras = [
                'total_reviews' => (int) Arr::get($feedData, 'total_reviews', 0),
                'order'         => Arr::get($feedData, 'order', ''),
                'exactRating'   => (int) Arr::get($feedData, 'exactRating', -1),
                'search'        => (string) Arr::get($feedData, 'search', ''),
                // Only a searched query answers this; null everywhere else, where total_reviews is a real count.
                'has_more'      => is_null($wpsr_has_more) ? null : (bool) $wpsr_has_more,
            ];
        } else {
            $content = apply_filters('wpsr_feed_items_by_page_' . $platform, '', $templateId, $page);
        }

        wp_send_json(array_merge([
            'content' => $content
        ], $responseExtras));
        die();
    }
}
