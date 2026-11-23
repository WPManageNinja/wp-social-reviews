<?php

namespace WPSocialReviews\Framework\Foundation;

use WPSocialReviews\Framework\Http\Request\Request;

abstract class Policy
{
    /**
     * Answer for a policy that does not decide itself: deny. A policy
     * grants access only by overriding this method or the route's method.
     *
     * @param  \WPSocialReviews\Framework\Http\Request\Request $request
     * @return bool
     */
    public function verifyRequest(Request $request)
    {
        return false;
    }
}
