<?php

namespace WPSocialReviews\Framework\Http\Concerns;

use Closure;
use Throwable;
use ReflectionFunction;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\Framework\Support\Str;
use WPSocialReviews\Framework\Http\Middleware\RateLimiter;

trait DescribesRouteTrait
{
    /**
     * Describe the route for the endpoint map: the controller, the action
     * and its URI, methods and policy. A closure route has no controller,
     * so the admin page's map leaves it out.
     *
     * With details, for developer tools, the route also gets its middleware,
     * whether it is signed, its rate limit and its name, and a closure route
     * is listed under "Closure" as "_{METHODS} {uri}".
     *
     * @param  bool  $resolvePolicy  Name the policy method that will run.
     * @param  bool  $withDetails  Add middleware, signing, rate limit and name.
     * @return array|null  [controller, action, details]
     */
    public function describe($resolvePolicy = false, $withDetails = false)
    {
        if ($this->handler instanceof Closure) {
            if (!$withDetails) {
                return null;
            }

            $controller = 'Closure';
            $action = "_{$this->method} {$this->uri}";
        } else {
            [$controller, $cb] = Str::parseCallback($this->parseAction($this->handler));

            $controller = str_replace('\\', '.', $controller);
            $action = "_{$cb}";
        }

        $details = [
            'uri' => $this->uri,
            'methods' => explode(',', $this->method),
            'policy' => $resolvePolicy
                ? $this->getResolvedPolicyName()
                : $this->getPolicyName(),
        ];

        if ($withDetails) {
            if ($this->policyHandler instanceof Closure) {
                $details['policy'] = $this->describeHandler($this->policyHandler);
            }

            $details += [
                'middleware' => [
                    'before' => $this->describeMiddleware('before'),
                    'after' => $this->describeMiddleware('after'),
                ],
                'signed' => $this->signed,
                'rate_limit' => $this->describeRateLimit(),
                'name' => $this->name,
            ];
        }

        return [$controller, $action, $details];
    }

    /**
     * Name the route's own middleware for one phase, as written: a class by
     * its name, an alias with what it points to, a closure with where it is
     * written.
     * The rate limit is described on its own.
     *
     * @param  string  $type  "before" or "after".
     * @return string[]
     */
    protected function describeMiddleware($type)
    {
        $names = [];

        foreach ($this->middleware[$type] as $middleware) {
            if ($middleware instanceof RateLimiter) {
                continue;
            }

            if (!is_string($middleware) || class_exists($middleware)) {
                $names[] = $this->describeHandler($middleware);
                continue;
            }

            $handler = Arr::get($this->middlewareAliases($type), explode(':', $middleware)[0]);

            $names[] = $middleware . ' (' . (is_null($handler)
                ? 'no such alias in app/Http/middleware.php'
                : $this->describeHandler($handler)
            ) . ')';
        }

        return $names;
    }

    /**
     * Name a middleware or policy handler: a class name, Class@method, or
     * a closure with the file and line it is written at, from the plugin's
     * folder, so a reader can open it.
     *
     * @param  mixed  $handler
     * @return string
     */
    protected function describeHandler($handler)
    {
        if ($handler instanceof Closure) {
            $closure = new ReflectionFunction($handler);

            $file = (string) $closure->getFileName();
            $root = (string) $this->app['path'];

            if ($root !== '' && strpos($file, $root) === 0) {
                $file = substr($file, strlen($root));
            }

            return $file === '' ? 'Closure' : "Closure ({$file}:{$closure->getStartLine()})";
        }

        if (is_object($handler)) {
            return get_class($handler);
        }

        if (is_array($handler)) {
            return implode('@', array_map(function ($part) {
                return is_object($part) ? get_class($part) : (string) $part;
            }, $handler));
        }

        return (string) $handler;
    }

    /**
     * Describe the route's rate limit, the first one when it has several.
     *
     * @return array|null  ['limit' => requests, 'seconds' => per interval]
     */
    protected function describeRateLimit()
    {
        foreach ($this->middleware['before'] as $middleware) {
            if ($middleware instanceof RateLimiter) {
                return $middleware->describe();
            }
        }

        return null;
    }

    /**
     * Get a display name for the route's policy handler.
     *
     * @return string|null
     */
    protected function getPolicyName()
    {
        if (!$this->policyHandler) {
            return null;
        }

        if ($this->policyHandler instanceof Closure) {
            return 'Closure';
        }

        $name = $this->policyHandler;

        if (is_string($name) && !$this->app->hasNamespace($name)) {
            $name = $this->app->__namespace__ . '\\App\\Http\\Policies\\' . $name;
        }

        return $name;
    }

    /**
     * Parse the action from the handler.
     * 
     * @param  mixed $handler
     * @return string
     */
    protected function parseAction($handler)
    {
        $action = $this->app->parseRestHandler($handler, $this->namespace);
        $action = trim($action, '\\');

        if (!str_contains($action, '@')) {
            $action .= '@__invoke';
        }

        return $action;
    }

    /**
     * Get the policy as Class@method, the callable the route will run, or
     * its display name when it doesn't resolve to one.
     *
     * @return string|null
     */
    protected function getResolvedPolicyName()
    {
        if (!$this->policyHandler || $this->policyHandler instanceof Closure) {
            return $this->getPolicyName();
        }

        try {
            $policy = $this->describePolicy($this->callablePolicy());
        } catch (Throwable $e) {
            return $this->getPolicyName();
        }

        return is_array($policy) ? implode('@', $policy) : $this->getPolicyName();
    }
}
