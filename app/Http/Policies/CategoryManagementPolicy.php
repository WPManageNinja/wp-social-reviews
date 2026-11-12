<?php

namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Http\Request\Request;

/**
 * Guards global category rename and delete. Categories are shared by reviews and
 * testimonials, and a delete detaches rows on both screens, so both capabilities are
 * required. Read and scoped bulk-assign routes stay behind each screen's own policy.
 */
class CategoryManagementPolicy extends BasePolicy
{
    /**
     * Check user permission for any method
     * @param  \WPSocialReviews\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('wpsn_manage_reviews')
            && $this->currentUserCan('wpsn_manage_testimonials');
    }
}
