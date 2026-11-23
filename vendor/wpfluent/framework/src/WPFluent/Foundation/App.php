<?php

namespace WPSocialReviews\Framework\Foundation;

use WPSocialReviews\Framework\Container\Contracts\BindingResolutionException;

/**
 * @method static \WPSocialReviews\Framework\Database\DatabaseManager db()
 * @method static \WPSocialReviews\Framework\View\View view()
 * @method static \WPSocialReviews\Framework\Events\Dispatcher events()
 * @method static \WPSocialReviews\Framework\Foundation\Config config()
 * @method static \WPSocialReviews\Framework\Http\Request\Request request()
 * @method static \WPSocialReviews\Framework\Http\Response\Response response()
 * @method static \WPSocialReviews\Framework\Encryption\Encrypter encrypter()
 * @method static \WPSocialReviews\Framework\Validator\Validator validator()
 */
class App
{
    /**
     * Application instance
     * 
     * @var \WPSocialReviews\Framework\Foundation\Application
     */
    protected static $instance = null;

    /**
     * Set the application instance
     * 
     * @param \WPSocialReviews\Framework\Foundation\Application $app
     */
    public static function setInstance($app)
    {
        static::$instance = $app;
    }

    /**
     * Get the application instance
     * 
     * @param  string $module The binding/key name for a component.
     * @param  array $parameters constructor dependencies if any.
     * @return \WPSocialReviews\Framework\Foundation\Application|mixed
     */
    public static function getInstance($module = null, $parameters = [])
    {
        if ($module) {
            return static::$instance->make($module, $parameters);
        }

        return static::$instance;
    }

    /**
     * Retrive a component from the container
     * 
     * @param  string $module The binding/key name for a component.
     * @param  array $parameters constructor dependencies if any.
     * @return \WPSocialReviews\Framework\Foundation\Application|mixed
     */
    public static function make($module = null, $parameters = [])
    {
        return static::getInstance($module, $parameters);
    }

    /**
     * Handle dynamic method calls
     * 
     * @param  string $method
     * @param  array $params
     * @return mixed
     */
    public static function __callStatic($method, $params)
    {
        if ($method === 'support') {
            $param = array_splice($params, 0, 1);
            return static::getInstance(
                'Support.' . implode('.', $param), ...$params
            );
        }

        if (method_exists(static::$instance, $method)) {
            return static::$instance->{$method}(...$params);
        }

        return static::getInstance($method, ...$params);
    }
}
