<?php

namespace WPSocialReviews\App\Services;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class ReviewApprovalService
{
    /**
     * Approval status for a new review: 1 approved, 0 pending, 2 spam.
     */
    public static function determineReviewApproval($reviewData, $reviewPublishMode = null, $conditionalRules = null)
    {
        if ($reviewPublishMode === null || $conditionalRules === null) {
            $globalReviewSettings = get_option('wpsr_global_settings', []);
            $globalSettingsService = new GlobalSettings();
            $formattedGlobalSettings = $globalSettingsService->formatGlobalSettings($globalReviewSettings);

            $reviewPublishMode = Arr::get($formattedGlobalSettings, 'global_settings.advance_settings.review_publish_mode', 'auto');
            $conditionalRules = Arr::get($formattedGlobalSettings, 'global_settings.advance_settings.conditional_rules', []);
        }

        switch ($reviewPublishMode) {
            case 'auto':
                return 1;

            case 'manually':
                return 0;

            case 'conditional':
                return static::evaluateConditionalRules($reviewData, $conditionalRules);

            default:
                return 1;
        }
    }

    /**
     * Same return values as determineReviewApproval().
     */
    public static function evaluateConditionalRules($reviewData, $conditionalRules)
    {
        $rating = intval(Arr::get($reviewData, 'rating', 0));
        $reviewText = Arr::get($reviewData, 'reviewer_text', '');
        $reviewTitle = Arr::get($reviewData, 'review_title', '');
        $fullReviewText = trim($reviewText . ' ' . $reviewTitle);

        $minRating = Arr::get($conditionalRules, 'min_rating', 3);
        if ($rating < $minRating) {
            return 0;
        }

        $blockedKeywords = Arr::get($conditionalRules, 'blocked_keywords', '');
        if (!empty($blockedKeywords)) {
            $keywords = array_map('trim', explode(',', $blockedKeywords));
            $lowerReviewText = strtolower($fullReviewText);

            foreach ($keywords as $keyword) {
                if (!empty($keyword) && strpos($lowerReviewText, strtolower($keyword)) !== false) {
                    return 2; // Blocked keyword: spam.
                }
            }
        }

        $minLength = Arr::get($conditionalRules, 'min_review_length', 0);
        if ($minLength && strlen($fullReviewText) < $minLength) {
            return 0;
        }

        return 1;
    }

    /**
     * determineReviewApproval() with the global settings.
     */
    public static function getReviewApprovalStatus($reviewData)
    {
        return static::determineReviewApproval($reviewData);
    }
}