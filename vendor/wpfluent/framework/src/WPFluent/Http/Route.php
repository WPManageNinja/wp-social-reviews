<?php

namespace WPSocialReviews\Framework\Http;

use InvalidArgumentException;
use WPSocialReviews\Framework\Http\SubstituteParameters;
use WPSocialReviews\Framework\Http\Middleware\RateLimiter;
use WPSocialReviews\Framework\Http\Concerns\ChecksPermissionTrait;
use WPSocialReviews\Framework\Http\Concerns\DescribesRouteTrait;
use WPSocialReviews\Framework\Http\Concerns\DispatchesActionTrait;
use WPSocialReviews\Framework\Http\Concerns\RegistersRouteTrait;
use WPSocialReviews\Framework\Http\Concerns\ResolvesPolicyTrait;
use WPSocialReviews\Framework\Http\Concerns\ResolvesMiddlewareTrait;

class Route
{
    use SubstituteParameters,
        RegistersRouteTrait,
        DescribesRouteTrait,
        ChecksPermissionTrait,
        ResolvesPolicyTrait,
        ResolvesMiddlewareTrait,
        DispatchesActionTrait;

    /**
     * Application Instance
     * @var \WPSocialReviews\Framework\Foundation\Application
     */
    protected $app = null;

    /**
     * Route name
     * @var string
     */
    protected $name = null;

    /**
     * Rest namespace from config
     * @var string
     */
    protected $restNamespace = null;

    /**
     * Whether this route should override existing routes at the same URI.
     * @var bool
     */
    protected $shouldOverride = false;

    /**
     * Full URI
     * @var string
     */
    protected $uri = null;

    /**
     * Compiled rest endpoint
     * @var string
     */
    protected $compiled = null;

    /**
     * Route meta data
     * @var array
     */
    protected $meta = [];

    /**
     * Rest Handler/Callback before parsing
     * @var string
     */
    protected $handler = null;

    /**
     * Rest Handler/Callback after parsing
     * @var callable|string
     */
    protected $action = null;

    /**
     * Rest route action info after parsing
     * @var array
     */
    protected $actionInfo = [];

    /**
     * Policy Handler/Callback after parsing
     * @var string
     */
    protected $permissionHandler = [];

    /**
     * HTTP Methods
     * @var string
     */
    protected $method = null;

    /**
     * Rest options
     * @var array
     */
    protected $options = [];

    /**
     * Route where constraints
     * @var array
     */
    protected $wheres = [];

    /**
     * Rest namespace
     * @var string
     */
    protected $namespace = null;

    /**
     * Policy Handler/Callback after parsing
     * @var callable|string
     */
    protected $policyHandler = null;

    /**
     * Route Middleware
     * @var array
     */
    protected $middleware = [
        'before' => [],
        'after' => []
    ];

    /**
     * Skips middlewar if true
     *
     * @var boolean
     */
    protected $skipMiddleware = false;

    /**
     * Predefined Regex foe where constraints
     * @var array
     */
    protected $predefinedNamedRegx = [
        // Each value is one or more of: int, the digits 0-9 ('42'); alpha,
        // the letters a-z and A-Z ('abc'); alpha_num, both ('a1');
        // alpha_num_dash, both plus '-' and '_' ('my-slug_1').
        'int' => '[0-9]+',
        'alpha' => '[a-zA-Z]+',
        'alpha_num' => '[a-zA-Z0-9]+',
        'alpha_num_dash' => '[a-zA-Z0-9-_]+'
    ];

    /**
     * Route parameters
     * @var null|array
     */
    protected $parameters = null;

    /**
     * Route substituted parameters
     *
     * @var null|array
     */
    protected $substitutedParameters = null;

    /**
     * Whether the route's action is running.
     *
     * @var bool
     */
    protected $dispatching = false;

    /**
     * Whether an exception is reported through fluent_exception. Off while
     * WordPress checks the route again to build the Allow header.
     *
     * @var bool
     */
    protected $reportExceptions = true;

    /**
     * The file the action asked to download, sent if it returns nothing.
     *
     * @var \WPSocialReviews\Framework\Http\Response\FileResponse|null
     */
    protected $download = null;

    /**
     * Is route signed
     * 
     * @var boolean
     */
    protected $signed = false;

    /**
     * Response instance
     * 
     * @var \WP_REST_Response
     */
    protected $response = null;

    /**
     * Construct the route instance
     *
     * @param \WPSocialReviews\Framework\Foundation\Application $app
     * @param string $restNamespace
     * @param string $uri
     * @param string $handler
     * @param string $method
     */
    public function __construct($app, $restNamespace, $uri, $handler, $method)
    {
        $this->app = $app;
        $this->restNamespace = $restNamespace;
        $this->uri = $uri;
        $this->handler = $handler;
        $this->method = $method;
    }

    /**
     * Alternative constructor
     *
     * @param \WPSocialReviews\Framework\Foundation\Application $app
     * @param string $namespace
     * @param string $uri
     * @param string $handler
     * @param string $method
     * @return self
     */
    public static function create($app, $namespace, $uri, $handler, $method)
    {
        return new static($app, $namespace, $uri, $handler, $method);
    }

    /**
     * Set route meta
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function meta($key, $value = null)
    {
        $meta = is_array($key) ? $key : [$key => $value];

        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Get route meta: one value, null for a key the route doesn't have,
     * or all of it without a key.
     *
     * @param string $key
     * @return mixed
     */
    public function getMeta($key = '')
    {
        if ($key === '' || $key === null) {
            return $this->meta;
        }

        return $this->meta[$key] ?? null;
    }

    /**
     * Get route options
     *
     * @return mixed
     */
    public function getOptions()
    {
        return $this->getOption();
    }

    /**
     * Get route options
     *
     * @param string $key
     * @return mixed
     */
    public function getOption($key = null)
    {
        return $key ? $this->options[$key] : $this->options;
    }

    /**
     * Get route action information: one value, null for a key it doesn't
     * have, or all of it without a key.
     *
     * @param string $key
     * @return mixed
     */
    public function getAction($key = '')
    {
        if ($key === '' || $key === null) {
            return $this->actionInfo;
        }

        return $this->actionInfo[$key] ?? null;
    }

    /**
     * Set a where constrain into the route
     *
     * @param string $identifier
     * @param string $value
     * @return self
     */
    public function where($identifier, $value = null)
    {
        if (!is_null($value)) {
            $this->wheres[$identifier] = $this->getValue($value);
        } else {
            foreach ($identifier as $key => $value) {
                $this->wheres[$key] = $this->getValue($value);
            }
        }

        return $this;
    }

    /**
     * Add an integer type route constraint
     *
     * @param string $identifiers
     * @return self
     */
    public function int($identifiers)
    {
        $identifiers = is_array($identifiers) ? $identifiers : func_get_args();

        foreach ($identifiers as $identifier) {
            // One or more digits: '42' passes, '4a' doesn't.
            $this->wheres[$identifier] = '[0-9]+';
        }

        return $this;
    }

    /**
     * Add an alpha type route constraint
     *
     * @param string $identifiers
     * @return self
     */
    public function alpha($identifiers)
    {
        $identifiers = is_array($identifiers) ? $identifiers : func_get_args();

        foreach ($identifiers as $identifier) {
            // One or more ASCII letters: 'abc' passes, 'a1' doesn't.
            $this->wheres[$identifier] = '[a-zA-Z]+';
        }

        return $this;
    }

    /**
     * Add an alphanum type route constraint
     *
     * @param string $identifiers
     * @return self
     */
    public function alphaNum($identifiers)
    {
        $identifiers = is_array($identifiers) ? $identifiers : func_get_args();

        foreach ($identifiers as $identifier) {
            // One or more ASCII letters or digits: 'a1' passes, 'a-1' doesn't.
            $this->wheres[$identifier] = '[a-zA-Z0-9]+';
        }

        return $this;
    }

    /**
     * Add an alphanumdash type route constraint
     *
     * @param string $identifiers
     * @return self
     */
    public function alphaNumDash($identifiers)
    {
        $identifiers = is_array($identifiers) ? $identifiers : func_get_args();

        foreach ($identifiers as $identifier) {
            // One or more ASCII letters, digits, '-' or '_' ('my-slug_1').
            $this->wheres[$identifier] = '[a-zA-Z0-9-_]+';
        }

        return $this;
    }

    /**
     * Set the route before middleware
     *
     * @param array|string $middleware
     * @return self
     */
    public function before(...$middleware)
    {
        return $this->middleware('before', ...$middleware);
    }

    /**
     * Set the route after middleware
     *
     * @param array|string $middleware
     * @return self
     */
    public function after(...$middleware)
    {
        return $this->middleware('after', ...$middleware);
    }

    /**
     * Set the route middleware
     * @param array $middleware
     * @return self
     */
    public function middleware($type = 'before', ...$middleware)
    {
        // An unknown type would drop the middleware without a word.
        if (!in_array($type, ['before', 'after'], true)) {
            throw new InvalidArgumentException(
                "Middleware type must be 'before' or 'after'; use before(...) or after(...) to add middleware."
            );
        }

        if ($middleware && is_array($middleware[0])) {
            $middleware = reset($middleware);
        }

        $this->middleware[$type] = array_merge(
            $this->middleware[$type], $middleware
        );

        return $this;
    }

    /**
     * Set the default route policy.
     * 
     * @return self
     */
    public function withDefaultPolicy()
    {
        return $this->withPolicy(
            // @phpstan-ignore-next-line
            $this->app->__namespace__.'\\App\\Http\\Policies\\Policy'
        );
    }

    /**
     * Keep the route for visitors who aren't logged in, such as a login or
     * registration page. A logged-in user is refused with a 403; on a web
     * route, given a path or URL, they are sent there instead. It sets the
     * route's policy, so withPolicy() after it replaces it.
     *
     * A closure decides for a logged-in visitor instead: its answer is the
     * policy's, such as its own redirect, or an HttpException it throws.
     *
     * @param  string|\Closure|null  $redirectTo
     * @return $this
     */
    public function guestOnly($redirectTo = null)
    {
        return $this->withPolicy(GuestOnly::policy($redirectTo));
    }

    /**
     * Set the route policy
     *
     * @param mixed $handler
     * @param string|null $method
     * @return self
     */
    public function withPolicy($handler, $method = null)
    {
        $handler = $method ? func_get_args() : $handler;

        // [Policy::class, 'method'] is kept as 'Policy@method', which the
        // route resolves in the plugin's policy namespace; an object in
        // the array is already a callable and stays one.
        if (is_array($handler) && is_string($handler[0] ?? null)) {
            $handler = implode('@', $handler);
        }

        $this->policyHandler = $handler;

        if (is_string($handler) && !$this->app->hasNamespace($handler)) {
            $this->setPolicyHandlerWithNamespace(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4)
            );
        }

        return $this;
    }

    /**
     * Resolve and set policy with namespace for add-ons
     *
     * @param array $backTrace
     * @return void
     */
    protected function setPolicyHandlerWithNamespace($backTrace)
    {
        $last = end($backTrace);

        if (!isset($last['class'])) return;

        $class = $last['class'];

        $namespace = substr(__NAMESPACE__, 0, strpos(__NAMESPACE__, '\\'));

        $calledClassNamespace = substr($class, 0, strpos($class, '\\'));

        if ($namespace != $calledClassNamespace) {
            $ns = $calledClassNamespace . '\\App\\Http\\Policies\\';
            $this->policyHandler = $ns . $this->policyHandler;
        }
    }

    /**
     * Set the name for the route.
     * 
     * @param string $name
     * @return self
     */
    public function name($name)
    {
        if (!$this->name) {
            $this->name = $name;
        } else {
            $this->name .= $name;
        }

        // @phpstan-ignore-next-line
        return $this->app->router->setNamedRoute($this->name, $this);
    }

    /**
     * Set the name for the route.
     * 
     * @param string $name
     * @return null
     */
    public function withName($name)
    {
        $this->name = implode('', $name);
    }

    /**
     * Set the namespace for controller/action.
     * 
     * @param string $ns
     * @return null
     */
    public function withNamespace($ns)
    {
        if (is_array($ns)) {
            $this->namespace = implode('\\', $ns);
        } else {
            $this->namespace = trim($ns, '\\');
        }
    }

    /**
     *  Sign the route.
     *  
     * @return $this
     */
    public function signed()
    {
        $this->signed = true;

        return $this;
    }

    /**
     *  Apply rate limit to the route.
     *  
     *  @param int $limit Number of allowed requests.
     *  @param int $interval Time interval in seconds.
     *  
     * @return $this
     */
    public function rateLimit($limit, $interval)
    {
        $rateLimiter = new RateLimiter($limit, $interval);

        $this->middleware('before', $rateLimiter);

        return $this;
    }

    /**
     * Apply a rate limit to the route for per minute.
     *
     * @param int $limit The maximum number of requests allowed per minute.
     * @return $this
     */
    public function rateLimitPerMinute($limit)
    {
        return $this->rateLimit($limit, MINUTE_IN_SECONDS);
    }

    /**
     * Apply an hourly rate limit to the route.
     *
     * @param int $limit The maximum number of requests allowed per hour.
     * @return $this
     */
    public function rateLimitHourly($limit)
    {
        return $this->rateLimit($limit, HOUR_IN_SECONDS);
    }

    /**
     * Apply a daily basis (24 hours) rate limit to the route.
     *
     * @param int $limit The maximum number of requests allowed per day.
     * @return $this
     */
    public function rateLimitDaily($limit)
    {
        return $this->rateLimit($limit, DAY_IN_SECONDS);
    }

    /**
     * Mark this route to override any existing route at the same URI.
     *
     * @return $this
     */
    public function override()
    {
        $this->shouldOverride = true;

        return $this;
    }

    /**
     * Generate and return the schema for the route.
     * 
     * @return self
     * @see https://developer.wordpress.org/rest-api/extending-the-rest-api/schema
     */
    public function schema($schema)
    {
        $this->options['schema'] = fn() => $schema;

        return $this;
    }

    /**
     * Get item from predefined regex
     * @param string $value
     * @return string
     */
    protected function getValue($value)
    {
        if (array_key_exists($value, $this->predefinedNamedRegx)) {
            return $this->predefinedNamedRegx[$value];
        }

        return $value;
    }

    /**
     * Get one route parameter, or all of them.
     *
     * A name the route does not have is null, as in Laravel, so a request
     * class shared by POST /things and PUT /things/{id} can read the id.
     *
     * @param  string|null  $key
     * @return mixed
     */
    public function getParameter($key = null)
    {
        if (is_null($this->parameters)) {
            $this->parameters = $this->app->request->get_url_params();
        }

        return $key ? ($this->parameters[$key] ?? null) : $this->parameters;
    }

    /**
     * Get the name of the route.
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the url of the route.
     * 
     * @return string
     */
    public function getUrl()
    {
        return $this->uri;
    }

    /**
     * Get the url of the route.
     * 
     * @return string
     */
    public function uri()
    {
        return $this->getUrl();
    }

    /**
     * Dynamically access a route parameter.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getParameter($key);
    }
}
