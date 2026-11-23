<?php

namespace WPSocialReviews\Framework\Http;

use Closure;
use ReflectionMethod;
use ReflectionFunction;
use InvalidArgumentException;
use WPSocialReviews\Framework\Container\Util;
use WPSocialReviews\Framework\Support\Reflector;
use WPSocialReviews\Framework\Support\Str;
use WPSocialReviews\Framework\Support\Contracts\UrlRoutable;

trait SubstituteParameters
{
    /**
     * The models the route's values were bound to, by class, placeholder
     * and value, so the policy and the controller share one lookup.
     *
     * @var array
     */
    protected $boundModels = [];

    /**
     * The arguments a callable gets from the route's values: a model
     * parameter binds by its name or snake_case name, and the other values
     * go to the parameters that have no class or interface type, in
     * order. The controller's signature unless another is given.
     *
     * @param  array  $routeParameters  The route's values, by placeholder.
     * @param  \ReflectionParameter[]|null  $signatureParameters
     * @return array  The arguments, by parameter name.
     */
    protected function substituteParameters($routeParameters, $signatureParameters = null)
    {
        $resolved = [];

        $signatureParameters = $signatureParameters ?? $this->getParametersFromRouteAction();

        foreach ($signatureParameters as $signatureParam) {
            $name = $signatureParam->getName();

            $routeName = $this->routeParameterName($name, $routeParameters);

            if ($routeName && $this->boundModel($signatureParam)) {
                $resolved[$name] = $this->bindModel(
                    Util::getParameterClassName($signatureParam),
                    $routeName,
                    $routeParameters[$routeName]
                );

                unset($routeParameters[$routeName]);
            }
        }

        $remainingParams = [];

        // A parameter typed as a class or an interface is the container's
        // to make; the route's values go to the others, in order.
        $signatureParameters = array_filter($signatureParameters, function($param) {
            return is_null(Reflector::getParameterClassName($param));
        });

        foreach ($routeParameters as $param) {
            $dep = reset($signatureParameters);

            if (!$dep) {
                break;
            }

            // A variadic parameter takes this value and every one after it,
            // unnamed, so the call spreads them into it.
            if ($dep->isVariadic()) {
                $remainingParams[] = $param;

                continue;
            }

            array_shift($signatureParameters);

            $remainingParams[$dep->getName()] = $param;
        }

        return $resolved + $remainingParams;
    }

    /**
     * The model of the class a route value binds to, looked up once.
     *
     * @param  string  $class
     * @param  string  $routeName
     * @param  mixed  $value
     * @return mixed
     */
    protected function bindModel($class, $routeName, $value)
    {
        $key = $class . '|' . $routeName . '|' . $value;

        if (!array_key_exists($key, $this->boundModels)) {
            $this->boundModels[$key] = $this->app->make($class)->resolveRouteBinding($value);
        }

        return $this->boundModels[$key];
    }

    /**
     * The parameters of any callable: a closure, a function's name, an
     * invokable object, [object or class, method] or "Class::method".
     *
     * @param  callable|string|array  $callable
     * @return \ReflectionParameter[]
     */
    protected function signatureOf($callable)
    {
        if (is_string($callable) && strpos($callable, '::') !== false) {
            $callable = explode('::', $callable, 2);
        }

        if (is_array($callable)) {
            return (new ReflectionMethod($callable[0], $callable[1]))->getParameters();
        }

        if (is_object($callable) && !$callable instanceof Closure) {
            return (new ReflectionMethod($callable, '__invoke'))->getParameters();
        }

        return (new ReflectionFunction($callable))->getParameters();
    }

    /**
     * The route's placeholder for a parameter: its own name ({blogPost}),
     * or its snake_case form ({blog_post}).
     *
     * @param  string  $name
     * @param  array  $routeParameters
     * @return string|null
     */
    protected function routeParameterName($name, $routeParameters)
    {
        if (array_key_exists($name, $routeParameters)) {
            return $name;
        }

        if (array_key_exists($snake = Str::snake($name), $routeParameters)) {
            return $snake;
        }

        return null;
    }

    /**
     * Whether the parameter is a model the route binds.
     *
     * @param  \ReflectionParameter  $parameter
     * @return bool
     */
    protected function boundModel($parameter)
    {
        return Reflector::isParameterSubclassOf($parameter, UrlRoutable::class);
    }

    protected function getParametersFromRouteAction()
    {
        if ($this->action instanceof Closure) {
            return (new ReflectionFunction($this->action))->getParameters();
        }

        list($class, $method) = explode('@', $this->action);

        return (new ReflectionMethod($class, $method))->getParameters();
    }
}
