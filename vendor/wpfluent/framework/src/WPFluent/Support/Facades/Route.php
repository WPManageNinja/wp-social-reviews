<?php

namespace WPSocialReviews\Framework\Support\Facades;

use WPSocialReviews\Framework\Support\Facade;

/**
 * Static calls to the router.
 *
 * @method static \WPSocialReviews\Framework\Http\Route get(string $uri, mixed $handler)
 * @method static \WPSocialReviews\Framework\Http\Route post(string $uri, mixed $handler)
 * @method static \WPSocialReviews\Framework\Http\Route put(string $uri, mixed $handler)
 * @method static \WPSocialReviews\Framework\Http\Route patch(string $uri, mixed $handler)
 * @method static \WPSocialReviews\Framework\Http\Route delete(string $uri, mixed $handler)
 * @method static \WPSocialReviews\Framework\Http\Route any(string $uri, mixed $handler)
 * @method static \WPSocialReviews\Framework\Http\Group group(array|\Closure $attributes = [], \Closure|null $callback = null)
 * @method static \WPSocialReviews\Framework\Http\Router prefix(string $prefix)
 * @method static \WPSocialReviews\Framework\Http\Router name(string $name)
 * @method static \WPSocialReviews\Framework\Http\Router namespace(string $ns)
 * @method static \WPSocialReviews\Framework\Http\Router withPolicy(mixed $handler, string|null $method = null)
 * @method static \WPSocialReviews\Framework\Http\Router before(mixed ...$middleware)
 * @method static \WPSocialReviews\Framework\Http\Router after(mixed ...$middleware)
 * @method static \WPSocialReviews\Framework\Http\Route|null getByName(string $name)
 *
 * @see \WPSocialReviews\Framework\Http\Router
 */
class Route extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'router';
    }
}
