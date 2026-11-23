<?php

namespace WPSocialReviews\Framework\Http\Request;

use Closure;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\Framework\Support\Helper;
use WPSocialReviews\Framework\Support\Concerns\MacroableTrait;
use WPSocialReviews\Framework\Foundation\Application;
use WPSocialReviews\Framework\Validator\ValidationException;

/**
 * @method mixed route(string|null $key = null) The current route, or one of
 *     its values: route('id') is the {id} in the URL, as text; null when
 *     the route has no such name.
 */
class Request
{
    use InteractsWithCleaningTrait,
        InteractsWithHeadersTrait,
        InputHelperMethodsTrait,
        InteractsWithFilesTrait,
        InteractsWithIPTrait,
        MacroableTrait {
            __call as macroCall;
        }

    /**
     * The application instance
     * @var \WPSocialReviews\Framework\Foundation\Application
     */
    protected $app = null;

    /**
     * Validator instance.
     * 
     * @var \WPSocialReviews\Framework\Validator\Validator
     */
    protected $validator = null;

    /**
     * PHP header variables
     * @var array
     */
    protected $headers = [];

    /**
     * PHP server variables
     * @var array
     */
    protected $server = [];

    /**
     * PHP cookie variables
     * @var array
     */
    protected $cookie = [];

    /**
     * The content of the request
     * @var mixed
     */
    protected $content = null;

    /**
     * The JSON payload of the request
     * @var array
     */
    protected $json = [];

    /**
     * PHP $_GET Superglobal
     * @var array
     */
    protected $get = [];


    /**
     * PHP $_POST Superglobal
     * @var array
     */
    protected $post = [];

    /**
     * PHP $_GET and $_POST Superglobals
     * @var array
     */
    protected $request = [];

    /**
     * WP_REST_Request instance
     * @var \WP_REST_Request|false
     */
    protected $wpRestRequest = false;

    /**
     * The query and the form input PHP gave the request, before any REST
     * request's input was merged in: each REST request starts from them.
     *
     * @var array{get: array, post: array}
     */
    protected $phpInput = ['get' => [], 'post' => []];

    /**
     * Validated data after validation has been passed
     * @var array
     */
    protected $validated = [];

    /**
     * $safe Determines the input source when data retrieval methods get called.
     * If true, the data will be returned from the $validated array.
     * If false, the data will be returned from the $request array.
     * 
     * @var boolean
     */
    protected $safe = false;

    /**
     * Construct the request instance
     * @param \WPSocialReviews\Framework\Foundation\Application $app
     * @param array $get
     * @param array $post
     */
    public function __construct(Application $app, $get, $post)
    {
        $this->app = $app;
        $this->server = $_SERVER;
        $this->cookie = $_COOKIE;
        
        $this->request = array_merge(
            $this->get = $this->clean($get),
            $this->post = $this->clean($post)
        );

        $this->phpInput = ['get' => $this->get, 'post' => $this->post];
    }

    /**
     * Variable exists
     * @param  string $key
     * @return bool
     */
    public function exists($key)
    {
        return Arr::has($this->inputs(), $key);
    }

    /**
     * Variable exists and has truthy value
     * @param  string $key
     * @return bool
     */
    public function has($key)
    {
        $inputs = $this->inputs();
        
        return isset($inputs[$key]) && !empty($inputs[$key]);
    }

    /**
     * Any variable exists and has truthy value
     * @param  array|string $keys
     * @return bool
     */
    public function hasAny($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        if ($data = $this->only($keys)) {
            return (bool) count(array_filter($data));
        }

        return false;
    }

    /**
     * Calls a callback if has value, otherwise
     *  calls another/second callback if given.
     * 
     * @param  string $key
     * @param  \Closure $has
     * @param  \Closure|null $hasnot
     * @return mixed
     */
    public function whenHas($key, Closure $has, ?Closure $hasnot = null)
    {
        if ($this->has($key)) {
            return $has($key, $this->get($key));
        }

        return ($hasnot ? $hasnot($key) : null);
    }

    /**
     * Checks if a key is missing in the request.
     * 
     * @param  string $key
     * @return bool
     */
    public function missing($key)
    {
        return !$this->has($key);
    }

    /**
     * Calls the given callback if the provided key is missing.
     * 
     * @param  string $key
     * @param  \Closure $callback
     * @return mixed
     */
    public function whenMissing($key, Closure $callback)
    {
        if ($this->missing($key)) {
            return $callback($key, $this);
        }

        return $this;
    }

    /**
     * Set an item into the request inputs
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function set($key, $value)
    {
        Arr::set($this->request, $key, $value);

        return $this;
    }

    /**
     * Retrive all the items from the request inputs
     * @return array
     */
    public function all()
    {
        return $this->get();
    }

    /**
     * Retrieve an item from the request inputs
     * @param  string|null $key
     * @param  mixed $default
     * @return mixed
     */
    public function get($key = null, $default = null)
    {
        return Helper::dataGet($this->inputs(), $key, $default);
    }

    /**
     * Check the content-type for JSON
     * 
     * @return boolean
     */
    public function isJson()
    {
        if (!($isJson = $this->is_json_content_type())) {
            if (!$isJson) {
                if ($body = $this->get_body()) {
                    json_decode($body);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $isJson = true;
                    }
                } elseif ($this->isRest()) {
                    $isJson = true;
                }
            }
        }

        if (
            !$isJson &&
            isset($_SERVER['CONTENT_TYPE']) &&
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
        ) {
            $requestBody = file_get_contents('php://input');

            if (!empty($requestBody)) {
                $this->json = json_decode($requestBody, true);
                $isJson = json_last_error() === JSON_ERROR_NONE;
            }
        }

        if (!$isJson) {
            $requestBody = file_get_contents('php://input');
            if (!empty($requestBody)) {
                json_decode($requestBody);
                $isJson = json_last_error() === JSON_ERROR_NONE;
            }
        }

        return $isJson;
    }

    /**
     * Check if current request wants JSON response.
     * 
     * @return boolean
     */
    public function wantsJson()
    {
        $wants = $this->header('accept');

        if ($wants === '*/*') {
            return $this->isJson();
        }

        return $wants === 'application/json';
    }

    /**
     * Check if current request is a Rest request
     *
     * A REST request WordPress serves (REST_REQUEST, which
     * wp_is_serving_rest_request() reads), or one dispatched inside another
     * request with rest_do_request(): a batch item, a preload, a call from
     * an admin-ajax handler. WordPress 6.5 and later tell that with
     * wp_is_rest_endpoint() (other plugins may answer through its filter);
     * on every supported version, the plugin's own route running counts.
     *
     * WordPress defines REST_REQUEST only once it routes the request, on
     * parse_request, after plugins have loaded; until then, the request is a
     * REST request when it has ?rest_route= or its path is under the REST
     * prefix, below the site's own path: /wp-json/..., or /blog/wp-json/...
     * for WordPress in a subfolder. After it, the constant decides.
     *
     * @return boolean
     */
    public function isRest()
    {
        if ($this->isForEndpoints()) {
            return false;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if ($this->isOwnRouteRunning()) {
            return true;
        }

        if (function_exists('wp_is_rest_endpoint') && wp_is_rest_endpoint()) {
            return true;
        }

        // Once WordPress has parsed the request, REST_REQUEST is its answer:
        // /wp-json/... with plain permalinks, say, is a page, not REST. It is
        // defined during parse_request (rest_api_loaded, priority 10), so the
        // URL still decides until that action is over.
        if (did_action('parse_request') && !doing_action('parse_request')) {
            return false;
        }

        return $this->restRoute() !== '';
    }

    /**
     * Whether one of the plugin's routes is running its controller now,
     * dispatched by WordPress or with rest_do_request().
     *
     * @return bool
     */
    protected function isOwnRouteRunning()
    {
        if (!$this->app->bound('route')) {
            return false;
        }

        $route = $this->app->make('route');

        // The plugin's REST route only: a package's routes (web pages)
        // dispatch too, but aren't REST.
        return $route instanceof \WPSocialReviews\Framework\Http\Route && $route->isDispatching();
    }

    /**
     * Check if the request asks for the plugin's endpoint map.
     *
     * The admin app asks {rest_namespace}/{version}/__endpoints. Plugins
     * made from the skeleton before 2026-02-25 ask for the namespace twice,
     * {rest_namespace}/{version}/{rest_namespace}/__endpoints, so any route
     * under the plugin's namespace that ends in /__endpoints counts.
     *
     * @param  string|null $route The REST route, e.g. /myplugin/v2/__endpoints;
     *                            the current request's when null.
     * @return bool
     */
    public function isForEndpoints($route = null)
    {
        $route = '/' . trim($route ?? $this->restRoute(), '/');

        $namespace = '/' . trim(
            (string) $this->app->config->get('app.rest_namespace'), '/'
        ) . '/';

        return str_starts_with($route, $namespace)
            && str_ends_with($route, '/__endpoints');
    }

    /**
     * Get the REST route a URL, or the request, asks for.
     *
     * @param  string|null $url A full URL; the current request's when null.
     * @return string The route, e.g. /myplugin/v2/posts; '' when it is not
     *                a REST request.
     */
    public function restRoute($url = null)
    {
        if ($url === null) {
            $route = $this->query('rest_route');
        } else {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $route = $query['rest_route'] ?? null;
        }

        if ($route && is_string($route)) {
            return '/' . trim($route, '/');
        }

        // Where the REST API is, as get_rest_url() puts it: with plain
        // permalinks only at ?rest_route= (/wp-json/... is a page then);
        // with index permalinks (/index.php/%postname%/) at
        // /index.php/wp-json/; otherwise at /wp-json/, unless a plugin
        // changed the prefix.
        $structure = (string) get_option('permalink_structure');

        if ($structure === '') {
            return '';
        }

        $prefix = '/' . trim(rest_get_url_prefix(), '/') . '/';

        if (preg_match('#^/*index\.php#', $structure)) {
            $prefix = '/index.php' . $prefix;
        }

        $path = $this->sitePath($url) . '/';

        if (!str_starts_with($path, $prefix)) {
            return '';
        }

        return '/' . trim(substr($path, strlen($prefix)), '/');
    }

    /**
     * Get the path of a URL, or of the request, below the site's home:
     * /shop/cart for http://example.com/blog/shop/cart when WordPress lives
     * in /blog.
     *
     * @param  string|null $url A full URL; the current request's when null.
     * @return string
     */
    public function sitePath($url = null)
    {
        $path = (string) parse_url(
            $url ?? $this->server('REQUEST_URI', ''), PHP_URL_PATH
        );

        $home = rtrim((string) parse_url(home_url(), PHP_URL_PATH), '/');

        if ($home !== '' && ($path === $home || str_starts_with($path, $home . '/'))) {
            $path = substr($path, strlen($home));
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * Whether a REST request is being dispatched now: one WordPress serves,
     * or one run inside another request with rest_do_request(). The
     * plugin's own route running counts on every supported WordPress;
     * WP_REST_Server::is_dispatching() only exists from 6.5. False without
     * a REST server.
     *
     * @return bool
     */
    public function isInternal()
    {
        if ($this->isOwnRouteRunning()) {
            return true;
        }

        $server = $GLOBALS['wp_rest_server'] ?? null;

        return is_object($server) && method_exists($server, 'is_dispatching') && $server->is_dispatching();
    }

    /**
     * Retrieve an item from the json payload of the request.
     * 
     * @param  string $key
     * @param  string $default
     * @return mixed
     */
    public function json($key = null, $default = null)
    {
        if (!$this->isJson()) {
            return [];
        }

        if (empty($this->json)) {
            $json = $this->get_json_params() ?: $this->getContent();
            
            if (!is_array($json)) {
                $decoded = json_decode($json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->json = [];
                } else {
                    $this->json = (array) $decoded;
                }
            } else {
                $this->json = $json;
            }
        }

        if (is_null($key)) {
            return $this->json;
        }

        return Helper::dataGet($this->json, $key, $default);
    }

    /**
     * Retrieve an item from the PHP $_SERVER array
     * @param  string $key
     * @param  string $default
     * @return mixed
     */
    public function server($key = null, $default = null)
    {
        return $key ? Arr::get($this->server, $key, $default) : $this->server;
    }

    /**
     * Retrieve an item from the cookie jar.
     *
     * Returns the raw cookie value, with one exception: if the value is a
     * valid strict-base64 string AND the decoded bytes parse as JSON, the
     * decoded value is returned instead. This keeps backwards compatibility
     * with callers that stored base64(json(...)) payloads, while normal
     * cookies (utm_*, UUIDs, hex hashes, JWTs, plain strings) pass through
     * unchanged because they fail one of the two gates.
     *
     * @param  string|null $key      Cookie name, or null for the full array
     * @param  mixed       $default  Returned when $key is set but absent
     * @return mixed
     */
    public function cookie($key = null, $default = null)
    {
        if ($key === null) {
            return $this->cookie;
        }

        $raw = Arr::get($this->cookie, $key, $default);

        // Pass through defaults, non-strings, and empty strings unchanged
        if ($raw === $default || !is_string($raw) || $raw === '') {
            return $raw;
        }

        // Auto-detect base64(json(...)) payloads. Both gates must pass;
        // otherwise return the raw cookie. Strict mode rejects values
        // containing characters outside the base64 alphabet (e.g. '-'),
        // and most plain values fail the JSON parse on the decoded bytes.
        $decoded = base64_decode($raw, true);
        if ($decoded === false) {
            return $raw;
        }

        $parsed = json_decode($decoded, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $raw;
        }

        return $parsed;
    }

    /**
     * Get an item from the PHP $_GET array
     * @param  string $key
     * @param  mixed $default
     * @return mixed
     */
    public function query($key = null, $default = null)
    {
        return $key ? Arr::get($this->get, $key, $default) : $this->get;
    }

    /**
     * Get an item from the PHP $_POST array
     * @param  string $key
     * @param  mixed $default
     * @return mixed
     */
    public function post($key = null, $default = null)
    {
        return $key ? Arr::get($this->post, $key, $default) : $this->post;
    }

    /**
     * Return the only items given in the args
     * @param  array $keys
     * @return array
     */
    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return Arr::only($this->inputs(), $keys);
    }

    /**
     * Return a subset of the request inputs except the given keys
     * @param  array $keys
     * @return array
     */
    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        
        return Arr::except($this->inputs(), $keys);
    }

    /**
     * Merge array with the request inputs
     * @param  array  $data
     * @return self
     */
    public function merge(array $data = [])
    {
        $this->request = array_replace($this->inputs(), $data);

        return $this;
    }

    /**
     * Merge array with the request inputs if the
     * key(s) is missing from the request.
     * 
     * @param  array  $data
     * @return self
     */
    public function mergeIfMissing(array $data = [])
    {
        $all = $this->inputs();

        $this->merge(Arr::mergeMissing($data, $all));

        return $this;
    }

    /**
     * Merge new input into the request's input, but only when
     * that key is present in the request but value is missing.
     *
     * @param  array  $input
     * @return $this
     */
    public function mergeMissing(array $input)
    {
        return $this->merge(Helper::collect($input)
            ->filter(function($value, $key) {
                return $this->missing($key);
            })->toArray()
        );
    }

    /**
     * Returns the request body content.
     *
     * @return mixed
     */
    public function getContent()
    {
        // A REST request's body is its own, even when empty: in a batch,
        // php://input is the batch's body.
        if (is_null($this->content)) {
            $this->content = file_get_contents('php://input');
        }

        return $this->content;
    }

    /**
     * Take the input of a WP_REST_Request.
     *
     * The request is one shared object, and several REST requests can run
     * in one PHP process (the batch API, rest_do_request(), WP-CLI), so
     * each starts from the input PHP gave and adds only its own: nothing
     * from the REST request before it (input, JSON, body, headers, what
     * was validated) is left over.
     *
     * @param  \WP_REST_Request $wpRestRequest
     * @return void
     */
    public function mergeInputsFromRestRequest($wpRestRequest)
    {
        $this->get = array_merge(
            $this->phpInput['get'], $wpRestRequest->get_query_params()
        );

        $this->post = array_merge(
            $this->phpInput['post'], $wpRestRequest->get_body_params()
        );

        $this->request = array_merge(
            $this->phpInput['get'],
            $this->phpInput['post'],
            $wpRestRequest->get_params()
        );

        $this->json = [];
        $this->content = $wpRestRequest->get_body();
        $this->validated = [];
        $this->safe = false;

        $this->headers = [];
        $this->setHeaders();
        $this->mergerHeaders($wpRestRequest);

        $this->wpRestRequest = true;
    }

    /**
     * Merge the headers from the WP_REST_Request.
     * 
     * @param  \WP_REST_Request $wpRestRequest
     * @return void
     */
    protected function mergerHeaders($wpRestRequest)
    {
        $headers = [];

        foreach ($wpRestRequest->get_headers() as $key => $header) {
            $headers[strtoupper($key)] = reset($header);
        }

        $this->headers = array_merge($this->headers, $headers);

        if (
            !isset($this->headers['CONTENT_TYPE']) || (
                isset($this->headers['CONTENT_TYPE']) && 
                $this->headers['CONTENT_TYPE'] !== true
        )) {
            if ($this->isJson()) {
                $this->headers['CONTENT_TYPE'] = 'application/json';
            }
        }
    }

    /**
     * Retrieve an input item from the request.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function input($key = null, $default = null)
    {
        return Arr::get($this->inputs(), $key, $default);
    }

    /**
     * Remove a key(s) from the $request array
     * @param  mixed $key
     * @return self
     */
    public function forget($key)
    {
        Arr::forget($this->request, $key);

        return $this;
    }

    /**
     * Remove query-string values from the request. A body field of the
     * same name stays among the inputs.
     *
     * @param  string|string[] $keys
     * @return self
     */
    public function forgetQuery($keys)
    {
        foreach ((array) $keys as $key) {
            Arr::forget($this->get, $key);

            if (!array_key_exists($key, $this->post)) {
                Arr::forget($this->request, $key);
            }
        }

        return $this;
    }

    /**
     * Get all inputs
     * 
     * @return array
     */
    protected function inputs()
    {
        if (!$this->wpRestRequest) {
            if ($this->app->bound('wprestrequest')) {
                // @phpstan-ignore-next-line
                $this->mergeInputsFromRestRequest($this->app->wprestrequest);
            }
        }

        if (empty($this->json) && $json = $this->json()) {
            $this->post = array_merge($this->post, $json);
            $this->request = array_merge($this->request, $json);
        }

        return $this->safe === true ? $this->validated : $this->request;
    }

    /**
     * To get item(s) from validated inputs
     *
     * @return self
     */
    public function safe()
    {
        $clone = clone $this;

        $clone->safe = true;

        return $clone;
    }

    /**
     * Get the request method.
     * 
     * @return string
     */
    public function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Get the URL (no query string) for the request.
     *
     * @return string
     */
    public function url()
    {
        // Cut off the query string, everything from the first '?', then a
        // trailing slash, as in Laravel: '.../posts/?page=2' -> '.../posts'.
        return rtrim(preg_replace('/\?.*/', '', $this->getFullUrl()), '/');
    }

    /**
     * Get the full URL for the request: the site's scheme, host and port,
     * and the path and query the browser asked for. REQUEST_URI already
     * holds the folder WordPress lives in, so the site URL's own path is
     * not added. Without a request (WP-CLI, cron), the site's origin.
     *
     * @return string
     */
    public function getFullUrl()
    {
        $site = wp_parse_url(get_site_url());

        $origin = ($site['scheme'] ?? 'http') . '://' . ($site['host'] ?? '')
            . (isset($site['port']) ? ':' . $site['port'] : '');

        return $origin . rtrim($_SERVER['REQUEST_URI'] ?? '', '/');
    }

    /**
     * Validate the request.
     *
     * @param  array $rules
     * @param  array $messages
     * @param  array $attributes The names the messages give the fields.
     * @return mixed
     * @throws \WPSocialReviews\Framework\Validator\ValidationException
     */
    public function validate(array $rules, array $messages = [], array $attributes = [])
    {
        $this->validator = $this->app->make('validator')->make(
            $this->withUploadsFor($rules), $rules, $messages, $attributes
        );

        $data = $this->all();

        if ($this->validator->validate()->fails()) {
            throw new ValidationException(
                'Unprocessable Entity!', 422, null, $this->validator->errors()
            );
        }

        $this->validated = $this->validator->validated();

        return $data;
    }

    /**
     * Get the inputs plus the uploads a rule names, so file rules can check
     * them. Uploads are turned into File objects only when a rule asks.
     *
     * @param  array $rules
     * @return array
     */
    protected function withUploadsFor(array $rules)
    {
        $data = $this->all();

        foreach (array_keys($rules) as $key) {
            $input = explode('.', $key)[0];

            if (!isset($_FILES[$input]) && !isset($this->files[$input])) {
                continue;
            }

            if (!is_null($upload = $this->files($input))) {
                $data[$input] = $upload;
            }
        }

        return $data;
    }

    /**
     * Get the validator instance.
     * 
     * @return \WPSocialReviews\Framework\Validator\Validator
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * Get the valid data after validation has been passed.
     *
     * @return array
     */
    public function validated($data = [])
    {
        if ($data) {
            return $this->validated = $data;
        }

        return (array) $this->validated;
    }

    /**
     * Abort the request.
     * 
     * @param  integer $status
     * @param  string  $message
     * @return \WP_REST_Response
     */
    public function abort($status = 403, $message = null)
    {
        $this->maybeThrowValidationException($status);

        if (!$message && !is_numeric($status) && is_string($status)) {
            $message = $status;
            $status = 403;
        }

        $message = $message ?: "{$status} Request has been aborted.";

        return new \WP_REST_Response(
            is_array($message) ? $message : [
                'message' => (string) $message
            ], $status
        );
    }

    /**
     * Terminate the request.
     * 
     * @param  integer $status
     * @param  string  $message
     * @return \WP_REST_Response
     */
    public function terminate($status = 200, $message = null)
    {
        $this->maybeThrowValidationException($status);

        if (!$message && !is_numeric($status) && is_string($status)) {
            $message = $status;
            $status = 403;
        }

        $message = $message
            ?: "Request has been terminated with status {$status}.";

        return wp_send_json(
            is_array($message) ? $message : ['message' => (string) $message], $status
        );
    }

    /**
     * Throw a validation exception if status is validation exception.
     * @param  mixed $status
     * @return void
     * @throws \WPSocialReviews\Framework\Validator\ValidationException
     */
    protected function maybeThrowValidationException($status)
    {
        if (is_object($status)) {
            if (method_exists($status, 'errors')) {
                throw new ValidationException(
                    'Unprocessable Entity!', 422, null, $status->errors()
                );
            }
        }
    }

    /**
     * Get an input element from the request.
     *
     * @param  string $key
     * @return mixed
     */
    public function __get($key)
    {
        if ($value = $this->get($key)) {
            return $value;
        }

        return $this->file($key);
    }

    /**
     * WordPress's current user with a way through to the plugin's User model,
     * the same object as $app->user(). Use exists() to tell a guest.
     *
     * @return \WPSocialReviews\Framework\Http\Request\CurrentUser
     */
    public function user()
    {
        return $this->app->user();
    }

    /**
     * Dynamyc method calls (specially for WP_rest_request)
     * @param  string $method
     * @param  array $params
     * @return mixed
     */
    public function __call($method, $params = [])
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $params);
        }

        if ($method == 'route') {
            if ($params) {
                // @phpstan-ignore-next-line
                return $this->app->route->{$params[0]};
            }
            // @phpstan-ignore-next-line
            return $this->app->route;
        }
        
        if ($this->app->bound('wprestrequest')) {
            // @phpstan-ignore-next-line
            if (!method_exists($this->app->wprestrequest, $method)) {
                // Turn camelCase into snake_case: 'getURLParams' ->
                // 'get_url_params'. The first pattern puts '_' between a
                // lowercase letter or digit and a capital
                // ('get_URLParams'), the second before a capital that is
                // followed by a lowercase letter, when no '_' is there yet
                // ('get_URL_Params'); strtolower() does the rest.
                $method = strtolower(
                    preg_replace([
                        '/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'
                    ], '$1_$2', $method)
                );
            }

            return call_user_func_array([
                // @phpstan-ignore-next-line
                $this->app->wprestrequest, $method], $params
            );
        }
    }
}
