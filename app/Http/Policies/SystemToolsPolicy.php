<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Http\Request\Request;

/**
 * Full access reads every System Admin Tools page. Actions also need a site administrator,
 * because full access can be granted to any role. The router calls an action's method
 * instead of verifyRequest(), so each one checks full access again.
 */
class SystemToolsPolicy extends BasePolicy
{
    /**
     * @param  \WPSocialReviews\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('wpsn_full_access');
    }

    /**
     * @param  \WPSocialReviews\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function runCron(Request $request)
    {
        return $this->currentUserCan('wpsn_full_access') && current_user_can('manage_options');
    }

    /**
     * @param  \WPSocialReviews\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function rescheduleCrons(Request $request)
    {
        return $this->currentUserCan('wpsn_full_access') && current_user_can('manage_options');
    }
}
