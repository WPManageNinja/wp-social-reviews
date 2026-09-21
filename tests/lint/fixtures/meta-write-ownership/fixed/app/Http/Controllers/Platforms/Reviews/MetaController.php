<?php
/** Minimal faithful Phase 24 copy from app/Http/Controllers/Platforms/Reviews/MetaController.php. */
class Phase24Fixture
{
    function getTemplatePost($templateId)
    {
        if (!$templateId) {
            return null;
        }

        $templateDetails = get_post($templateId);

        if (!$templateDetails || !in_array($templateDetails->post_type, self::ALLOWED_POST_TYPES, true)) {
            return null;
        }

        return $templateDetails;
    }

    function update(Request $request, $templateId)
    {
        $templateId = absint($templateId);

        if (!$this->getTemplatePost($templateId)) {
            return $this->sendError([
                'message' => __('Template not found.', 'wp-social-reviews')
            ], 404);
        }

        $templateMeta = wp_unslash($request->get('template_meta'));
        $templateMeta = json_decode($templateMeta, true);
        $templateMeta = $this->sanitizeTemplateMeta($templateMeta);

        $templateMeta = $this->stripClientCategoryIds($templateMeta);

        if ($this->hasBlankCustomWriteReviewUrl($templateMeta)) {
            return $this->sendError([
                'message' => __('Please enter a custom URL for the Write a Review button.', 'wp-social-reviews')
            ], 422);
        }

        if(
            defined('WPSOCIALREVIEWS_PRO') &&
            class_exists('\WPSocialReviewsPro\App\Services\TemplateCssHandler')){
            (new \WPSocialReviewsPro\App\Services\TemplateCssHandler())->saveCss($templateMeta, $templateId);
        }

        do_action('wpsocialreviews/template_meta_data', $templateId, $templateMeta);

        if(Arr::get($templateMeta, 'templateType') === 'badge' && !empty(Arr::get($templateMeta, 'badge_settings'))) {
            $url = $this->getUrl($templateMeta['badge_settings']);
            $templateMeta['badge_settings']['url'] = $url;
        }

        if(Arr::get($templateMeta, 'templateType') === 'notification' && !empty(Arr::get($templateMeta, 'notification_settings'))) {
            $url = $this->getUrl($templateMeta['notification_settings']);
            $templateMeta['notification_settings']['url'] = $url;
        }

        // Remove template from onboarding sessions since it's now been edited
        if (Arr::get($templateMeta, 'feed_settings.created_from_onboarding')) {
            OnboardingHelper::removeFromOnboardingSessions($templateId);
        }

        $formattedMeta      = Helper::formattedTemplateMeta($templateMeta);

        if($formattedMeta['templateType'] === 'notification') {
            unset($formattedMeta['badge_settings']);
            if (isset($formattedMeta['notification_settings'])) {
                $menuOrder = $formattedMeta['notification_settings']['notification_priority'];
                $db = App::getInstance('db');

                // post_type is re-asserted here: this bypasses wp_update_post(), so it
                // must not rely on the caller's guard alone.
                $db->table('posts')->where('ID', $templateId)
                    ->whereIn('post_type', self::ALLOWED_POST_TYPES)
                    ->update([
                        'menu_order' => absint($menuOrder)
                    ]);
            }
        } else {
            unset($formattedMeta['notification_settings']);
        }

        $unsetKeys = ['styles_config', 'styles', 'responsive_styles'];
        foreach ($unsetKeys as $key){
            if(Arr::get($templateMeta, $key, false)){
                unset($templateMeta[$key]);
            }
        }

        $encodedMeta = json_encode($formattedMeta, JSON_UNESCAPED_UNICODE);
        update_post_meta($templateId, '_wpsr_template_config', $encodedMeta);

        $platforms = Arr::get($formattedMeta, 'platform', []);
        $platforms = implode(',', $platforms);

        $postData = [
            'ID'            => $templateId,
            'post_content'  => $platforms
        ];

        wp_update_post($postData);
        $updatedMeta = get_post_meta($templateId, '_wpsr_template_config', true);
        $decodedMeta = json_decode($updatedMeta);

        return [
            'message'       => __("Template saved successfully!!", 'wp-social-reviews'),
            'template_id'   => $templateId,
            'template_meta' => $decodedMeta,
        ];
    }
}
