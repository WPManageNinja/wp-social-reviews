<?php

namespace WPSocialReviews\Framework\Support\Concerns;

use WPSocialReviews\Framework\Support\Helper;

trait Tappable
{
    /**
     * Call the given Closure with this instance then return the instance.
     *
     * @param  callable|null  $callback
     * @return $this|\WPSocialReviews\Framework\Support\Proxies\HigherOrderTapProxy
     */
    public function tap($callback = null)
    {
        return Helper::tap($this, $callback);
    }
}
