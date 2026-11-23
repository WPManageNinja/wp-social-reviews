<?php

namespace WPSocialReviews\Framework\Support\Facades;

use WPSocialReviews\Framework\Support\Facade;

/**
 * Static calls to the plugin's configuration.
 *
 * @method static array all()
 * @method static mixed get(string|null $key = null, mixed $default = null)
 * @method static array only(string|array $key)
 * @method static void set(string $key, mixed $value)
 *
 * @see \WPSocialReviews\Framework\Foundation\Config
 */
class Config extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'config';
    }
}
