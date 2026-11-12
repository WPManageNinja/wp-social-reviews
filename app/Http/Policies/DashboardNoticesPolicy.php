<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Http\Request\Request;
use WPSocialReviews\App\Services\PermissionManager;

/**
 * Dashboard notices render on every plugin screen, so any plugin capability may use
 * them. PlatformPolicy would 403 users who hold only one other capability.
 */
class DashboardNoticesPolicy extends BasePolicy
{
    /**
     * Check user permission for any method
     * @param  \WPSocialReviews\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return PermissionManager::currentUserCanAny();
    }
}
