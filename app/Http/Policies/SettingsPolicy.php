<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Http\Request\Request;

/**
 * Method names here are matched against SettingsController actions: one named after an
 * action gates that route alone and verifyRequest never runs for it. Only add one when
 * a route's owning capability really differs from the group's.
 */
class SettingsPolicy extends BasePolicy
{
    /**
     * Check user permission for any method
     * @param  \WPSocialReviews\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('wpsn_feeds_platforms_settings')
            || $this->currentUserCan('wpsn_reviews_platforms_settings')
            || $this->currentUserCan('wpsn_shoppable_settings')
            || $this->currentUserCan('wpsn_translation_settings')
            || $this->currentUserCan('wpsn_license_settings')
            || $this->currentUserCan('wpsn_feeds_advance_settings')
            || $this->currentUserCan('wpsn_manage_qr_codes');
    }

    /**
     * Gates SettingsController::resetErrorLog. The error log lives on the Platforms
     * screen, so it uses that screen's capability.
     */
    public function resetErrorLog(Request $request)
    {
        return $this->currentUserCan('wpsn_manage_platforms');
    }
}
