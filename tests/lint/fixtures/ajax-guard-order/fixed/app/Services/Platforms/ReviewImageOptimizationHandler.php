<?php
/** Minimal faithful Phase 24 copy from app/Services/Platforms/ReviewImageOptimizationHandler.php. */
class Phase24Fixture
{
    function savePhotos()
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';

        // Try frontend nonce first, then admin nonce
        $nonce_verified = wp_verify_nonce($nonce, 'wpsr-ajax-nonce') || wp_verify_nonce($nonce, 'wp-social-reviews');

        if (!$nonce_verified) {
            wp_send_json_error([
                'message' => __('Security validation failed. Please try again', 'wp-social-reviews')
            ], 423);
        }

        $templateId = absint(Arr::get($_REQUEST, 'id', -1));
        $resize_data = isset($_REQUEST['resize_data']) ? array_map('sanitize_text_field', (array) wp_unslash($_REQUEST['resize_data'])) : [];
        $platforms = isset($_REQUEST['platforms']) ? array_map('sanitize_text_field', (array) wp_unslash($_REQUEST['platforms'])) : [];

        // The nonce is localized for logged-out visitors, so it authenticates nothing.
        // Gate on the template being publicly renderable before reading its meta, otherwise
        // an anonymous request could fetch a draft/private template's reviews and
        // materialize their images into the uploads directory.
        if (!(new \WPSocialReviews\App\Hooks\Handlers\ShortcodeHandler())->isPublicRenderableTemplate($templateId)) {
            wp_send_json_error(['message' => __('Invalid template.', 'wp-social-reviews')], 403);
        }

        if ($templateId > 0) {
            $templateMeta       = get_post_meta($templateId, '_wpsr_template_config', true);
            $formattedMeta = json_decode($templateMeta, true);

            if (is_array($formattedMeta) && isset($formattedMeta['platform'])) {
                $formattedMeta['platform'] = $platforms;
            }

            $formattedMeta      = Helper::formattedTemplateMeta($formattedMeta);
            $reviewsData        = Review::collectReviewsAndBusinessInfo($formattedMeta, $templateId);
            $filtered_reviews   = $reviewsData['filtered_reviews'];

            foreach ($filtered_reviews as $review) {
                if (in_array(Arr::get($review, 'review_id'), $resize_data)) {
                    $this->doneResizing[] = Arr::get($review, 'review_id');
                    continue; // Skip to the next iteration
                }

                if ($this->maxResizingPerUnitTimePeriod()) {
                    continue; // Skip if max resizing is reached
                }

                $platform_name = Arr::get($review, 'platform_name', '');
                if ($this->isMaxRecordsReached($platform_name)) {
                    $this->deleteLeastUsedImages($platform_name);
                }

                $this->processSaveImage($review, $platform_name);
            }

            $reviewIds = OptimizeImage::select('media_id')
                            ->whereIn('platform', $platforms)
                            ->where('aspect_ratio', 1)
                            ->get();
            $mediaIds = array_map(function ($item) {
                return $item['media_id'];
            }, $reviewIds->toArray());

            echo json_encode(['images_data' => $mediaIds]);
            die();
        }
    }
}
