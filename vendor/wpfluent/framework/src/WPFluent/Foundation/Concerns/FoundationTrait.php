<?php

namespace WPSocialReviews\Framework\Foundation\Concerns;

use RuntimeException;
use InvalidArgumentException;
use WPSocialReviews\Framework\Support\Str;
use WPSocialReviews\Framework\Http\Request\CurrentUser;

trait FoundationTrait
{
    use AjaxTrait, HooksRemovalTrait;

    /**
     * Check if wp debug mode is on.
     * 
     * @return boolean
     */
    public function isDebugOn()
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Whether error responses carry the error's message, file and line:
     * WP_DEBUG on and WP_DEBUG_DISPLAY not turned off, as WordPress shows
     * errors. A site that only logs (WP_DEBUG_DISPLAY false or null) still
     * writes them to the debug log, which follows WP_DEBUG alone.
     *
     * @return bool
     */
    public function shouldShowErrorDetails()
    {
        return $this->isDebugOn()
            && (!defined('WP_DEBUG_DISPLAY') || WP_DEBUG_DISPLAY);
    }

    /**
     * Check whether multi-site or not.
     * 
     * @return boolean
     */
    public function isMultiSite()
    {
        return function_exists('is_multisite') && is_multisite();
    }

    /**
     * The environment: config/app.php's env (dev, testing, production),
     * production when none is set. On the command line WPF_ENV wins, as
     * the test runner sets it. WP_DEBUG plays no part.
     *
     * @return string
     */
    public function env()
    {
        if (php_sapi_name() === 'cli') {
            $env = $_ENV['WPF_ENV'] ?? getenv('WPF_ENV');

            if (is_string($env) && $env !== '') {
                return $env;
            }
        }

        return $this->config->get('app.env') ?: 'production';
    }

    /**
     * Get current namespace for the plugin.
     * 
     * @return string
     */
    public function getCurrentNamespace()
    {
        return $this->getComposer('extra.wpfluent.namespace.current');
    }

    /**
     * Make the custom hook name for the plugin.
     * @param  string $prefix
     * @param  string $hook
     * @return string
     */
    public function hook($prefix, $hook)
    {
        return $prefix . $hook;
    }

    /**
     * Parse the handler for the rest request
     * @param  string|\Closure $handler
     * @param  string $ns
     * @return mixed
     */
    public function parseRestHandler($handler, $ns = '')
    {
        if (is_object($handler) && is_callable($handler)) {
            return $handler;
        }

        if (is_array($handler)) {

            if (is_object($handler[0])) {
                return $handler;
            }

            if (is_string($handler[0])) {
                // [Controller::class, 'method'] names the class in full, in
                // any group; a short name is in the group's namespace.
                if ($this->hasNamespace($handler[0])) {
                    return '\\' . ltrim($handler[0], '\\') . '@' . $handler[1];
                }

                $handler = $handler[0] . '@' . $handler[1];
            }
        }

        // A leading backslash: the class as written, outside any namespace.
        if (is_string($handler) && strpos($handler, '\\') === 0) {
            return '\\' . ltrim($handler, '\\');
        }

        if ($ns && str_contains($handler, $ns)) {
            if (strpos($handler, $ns) !== false) {
                $handler = trim(str_replace($ns, '', $handler), '\\');
            }
        }
        
        $handler = $ns ? $ns . '\\' . $handler : $handler;

        return $this->getControllerNamespace($handler) . '\\' . $handler;
    }

    /**
     * Parse the policy handler
     * @param  string|array $handler
     * @return mixed
     */
    public function parsePolicyHandler($handler)
    {
        if (is_string($handler)) {

            if (function_exists($handler)) return $handler;

            $handler = ltrim($handler, '\\');

            $handler = $this->getPolicyNamespace($handler) . '\\' . $handler;

            if (is_string($handler) && strpos($handler, '@') !== false) {

                list($class, $method) = explode('@', $handler);

                if (!method_exists($class, $method)) {
                    $method = 'verifyRequest';
                }

                $instance = $this->make($class);

                $handler = [$instance, $method];
            }

        } else if (is_array($handler)) {
            [$class, $method] = $handler;

            // A policy method is an instance method, so the class is built,
            // as for 'Policy@method'; a short name is looked up in the
            // plugin's policy namespace.
            if (is_string($class)) {
                $class = ltrim($class, '\\');

                $namespace = $this->getPolicyNamespace($class);

                $class = $this->make($namespace ? $namespace . '\\' . $class : $class);
            }

            $handler = [$class, $method];
        }

        return $handler;
    }

    /**
     * Register an action hook
     * @param string $action
     * @param mixed $handler
     * @param integer $priority
     * @param integer $numOfArgs
     */
    public function addAction($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        return add_action(
            $action,
            $this->parseHookHandler($handler),
            $priority,
            $numOfArgs
        );
    }

    /**
     * Register a custom action hook
     * @param string $action
     * @param mixed $handler
     * @param integer $priority
     * @param integer $numOfArgs
     */
    public function addCustomAction($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        $prefix = $this->config->get('app.hook_prefix');

        return $this->addAction(
            $this->hook($prefix, $action), $handler, $priority, $numOfArgs
        );
    }

    /**
     * Dispatch an action
     * @return null
     */
    public function doAction()
    {
        return call_user_func_array('do_action', func_get_args());
    }

    /**
     * Dispatch a custom action
     * @return null
     */
    public function doCustomAction()
    {
        $args = func_get_args();

        $prefix = $this->config->get('app.hook_prefix');

        $args[0] = $this->hook($prefix, $args[0]);

        return call_user_func_array('do_action', $args);
    }

    /**
     * Register a filter hook
     * @param string $action
     * @param mixed $handler
     * @param integer $priority
     * @param integer $numOfArgs
     */
    public function addFilter($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        return add_filter(
            $action,
            $this->parseHookHandler($handler),
            $priority,
            $numOfArgs
        );
    }

    /**
     * Register a custom filter hook
     * @param string $action
     * @param mixed $handler
     * @param integer $priority
     * @param integer $numOfArgs
     */
    public function addCustomFilter($action, $handler, $priority = 10, $numOfArgs = 1)
    {
        $prefix = $this->config->get('app.hook_prefix');

        return $this->addFilter(
            $this->hook($prefix, $action), $handler, $priority, $numOfArgs
        );
    }

    /**
     * Dispatc a filter
     * @return mixed
     */
    public function applyFilters()
    {
        return call_user_func_array('apply_filters', func_get_args());
    }

    /**
     * Dispatch a custom filter
     * @return mixed
     */
    public function applyCustomFilters()
    {
        $args = func_get_args();
        $prefix = $this->config->get('app.hook_prefix');
        $args[0] = $this->hook($prefix, $args[0]);

        return call_user_func_array('apply_filters', $args);
    }

    /**
     * Checks if any action has been fired.
     * 
     * @param  string $action
     * @return int Number of times the action was fired
     */
    public function didAction($action)
    {
        return did_action($action);
    }

    /**
     * Check whether an action has callbacks, or has the given one.
     *
     * The callback is named as it was added: a handler class by
     * 'Class@method', by the class alone or by [class, method] is found
     * though the container built it. Like has_filter(), the answer is the
     * callback's priority, or false; without a callback, whether the hook
     * has any.
     *
     * @param  string $action
     * @param  mixed $callback
     * @return bool|int
     */
    public function hasAction($action, $callback = false)
    {
        return $this->hasFilter($action, $callback);
    }

    /**
     * Checks if any custom action has been fired.
     * 
     * @param  string $action
     * @return int Number of times the action was fired
     */
    public function didCustomAction($action)
    {
        return did_action(
            $this->config->get('app.hook_prefix') . $action
        );
    }

    /**
     * Check whether a custom (prefixed) action has callbacks, or has the given one.
     *
     * The callback is named as it was added: a handler class by
     * 'Class@method', by the class alone or by [class, method] is found
     * though the container built it. Like has_filter(), the answer is the
     * callback's priority, or false; without a callback, whether the hook
     * has any.
     *
     * @param  string $action
     * @param  mixed $callback
     * @return bool|int
     */
    public function hasCustomAction($action, $callback = false)
    {
        $prefix = $this->config->get('app.hook_prefix');

        return $this->hasFilter($this->hook($prefix, $action), $callback);
    }

    /**
     * Checks if any action has been fired.
     * 
     * @param  string $action
     * @return int Number of times the action was fired
     */
    public function didFilter($action)
    {
        $this->requireDidFilter(__FUNCTION__);

        return did_filter($action);
    }

    /**
     * Check whether a filter has callbacks, or has the given one.
     *
     * The callback is named as it was added: a handler class by
     * 'Class@method', by the class alone or by [class, method] is found
     * though the container built it. Like has_filter(), the answer is the
     * callback's priority, or false; without a callback, whether the hook
     * has any.
     *
     * @param  string $action
     * @param  mixed $callback
     * @return bool|int
     */
    public function hasFilter($action, $callback = false)
    {
        $priority = $callback === false
            ? null
            : $this->findNamedHandler($action, $callback);

        return $priority ?? has_filter($action, $callback);
    }

    /**
     * Checks if any custom action has been fired.
     * 
     * @param  string $action
     * @return int Number of times the action was fired
     */
    public function didCustomFilter($action)
    {
        $this->requireDidFilter(__FUNCTION__);

        return did_filter(
            $this->config->get('app.hook_prefix') . $action
        );
    }

    /**
     * Stop with a clear message on a WordPress without did_filter().
     *
     * WordPress added did_filter() in 6.1; before it, filters were not
     * counted at all, so there is no number to give instead.
     *
     * @param  string  $method  The method that needs it.
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function requireDidFilter($method)
    {
        if (!$this->hasDidFilter()) {
            throw new RuntimeException(
                "{$method}() needs WordPress 6.1 or newer, which added did_filter()."
            );
        }
    }

    /**
     * Determine whether this WordPress has did_filter().
     *
     * @return bool
     */
    protected function hasDidFilter()
    {
        return function_exists('did_filter');
    }

    /**
     * Check whether a custom (prefixed) filter has callbacks, or has the given one.
     *
     * The callback is named as it was added: a handler class by
     * 'Class@method', by the class alone or by [class, method] is found
     * though the container built it. Like has_filter(), the answer is the
     * callback's priority, or false; without a callback, whether the hook
     * has any.
     *
     * @param  string $action
     * @param  mixed $callback
     * @return bool|int
     */
    public function hasCustomFilter($action, $callback = false)
    {
        $prefix = $this->config->get('app.hook_prefix');

        return $this->hasFilter($this->hook($prefix, $action), $callback);
    }

    /**
     * Register a short code
     * @param string $action
     * @param string|\Closure $handler
     * @return void
     */
    public function addShortcode($action, $handler)
    {
        add_shortcode($action, $this->parseHookHandler($handler));
    }

    /**
     * Execute a shortcode
     * @param  mixed $content
     * @param  boolean $ignore_html
     * @return mixed
     */
    public function doShortcode($content, $ignore_html = false)
    {
        return do_shortcode($content, $ignore_html);
    }

    /**
     * Parse a hook handler.
     *
     * @param mixed $handler
     * @return mixed
     */
    public function parseHookHandler($handler)
    {
        if (is_string($handler)) {
            if (function_exists($handler)) {
                return $handler;
            }

            // 'Class::method' is PHP's static callable, so WordPress calls
            // it as written, without building the class.
            if (strpos($handler, '::') !== false) {
                return $handler;
            }

            // 'Handler@boot' builds Handler and calls boot(). Without a
            // method, an invokable class is called itself, any other class
            // gets handle().
            [$class, $method] = Str::parseCallback($handler);

            $class = $this->makeInstance($class);

            if (!is_null($method)) {
                return [$class, $method];
            }

            return is_callable($class) ? $class : [$class, 'handle'];
        }

        if (is_array($handler)) {
            // Rejected here, while the hook is registered: WordPress would
            // only fail when the hook fires, far from the mistake.
            if (!$this->isHookHandlerArray($handler)) {
                throw new InvalidArgumentException(
                    'A hook handler array must be [class or object, method].'
                );
            }

            [$class, $method] = $handler;

            if (is_string($class)) {
                $class = $this->makeInstance($class);
            }

            return [$class, $method];
        }

        return $handler;
    }

    /**
     * Determine whether the value is a valid [class or object, method] handler.
     *
     * @param array $handler
     * @return bool
     */
    protected function isHookHandlerArray(array $handler)
    {
        return array_keys($handler) === [0, 1]
            && (is_string($handler[0]) || is_object($handler[0]))
            && is_string($handler[1])
            && $handler[1] !== '';
    }

    /**
     * Chdeck if handler has FQCN.
     * 
     * @param  string|\Closure $handler
     * @return boolean
     */
    public function hasNamespace($handler)
    {
        if ($handler instanceof \Closure) {
            return false;
        };

        if (is_object($handler)) {
            return false;
        }

        $parts = array_filter(explode('\\', $handler));
        
        return count($parts) > 1;
    }

    /**
     * Resolve the namespace for a controller
     * @param  string $handler
     * @return mixed
     */
    public function getControllerNamespace($handler)
    {
        if ($this->hasNamespace($handler)) {
            return '';
        }

        return $this->controllerNamespace;
    }

    /**
     * Resolve the namespace for a policy
     * @param  string $handler
     * @return mixed
     */
    public function getPolicyNamespace($handler)
    {
        if ($this->hasNamespace($handler)) {
            return '';
        }

        return $this->policyNamespace;
    }

    /**
     * Make an instance by the container
     * @param  string $class
     * @return mixed
     */
    public function makeInstance($class)
    {
        // Container bindings are keyed without a leading backslash.
        $class = ltrim($class, '\\');

        if ($this->hasNamespace($class)) {
            $instance = $this->make($class);
        } else {
            $instance = $this->make($this->handlerNamespace . '\\' . $class);
        }

        return $instance;
    }

    /**
     * Retrieve the base url
     * @param  string $url
     * @return string
     */
    public function url($url = '')
    {
        return $this->baseUrl.ltrim($url, '/');
    }

    /**
     * WordPress's current user with a way through to the plugin's User model.
     * The same object on every call. It's a guest before init, and a call
     * that early is logged in debug mode.
     *
     * @return \WPSocialReviews\Framework\Http\Request\CurrentUser
     */
    public function user()
    {
        if (!did_action('init') && $this->isDebugOn()) {
            error_log("User not available—'init' action hasn't fired.");
        }

        return $this->currentUser ??= new CurrentUser(
            $this->__namespace__ . '\App\Models\User'
        );
    }

    public function enableApplicationPassword()
    {
        add_filter(
            'wp_is_application_passwords_available',
            fn() => !str_starts_with($this->env(), 'prod')
        );
    }
}
