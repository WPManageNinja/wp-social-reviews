<?php

namespace WPSocialReviews\Framework\Foundation\Concerns;

use WPSocialReviews\Framework\Support\Str;

trait HooksRemovalTrait
{
    /**
     * Remove a callback added with addFilter() or addAction().
     *
     * The handler is named the way it was added: a function name, a static
     * 'Class::method', a class and a method (as two arguments or as
     * 'Class@method'), a class alone, or an object and a method. A class
     * handler is matched by its class name, so the instance the container
     * built when the hook was added is found without building another.
     *
     * @param  string  $action
     * @param  string|object  $class
     * @param  string|int  $method  The method, or the priority when numeric.
     * @param  int  $priority
     * @return bool  Whether a hook was removed.
     */
    public function removeFilter($action, $class = '', $method = '', $priority = 10)
    {
        if (is_numeric($method)) {
            [$priority, $method] = [(int) $method, ''];
        }

        if (is_string($class) && $this->isPlainCallbackString($class)) {
            return remove_filter($action, $class, $priority);
        }

        if (is_object($class)) {
            $className = get_class($class);
        } else {
            [$className, $namedMethod] = Str::parseCallback($class);

            $className = $this->handlerClassName($className);

            $method = $namedMethod ?? $method;
        }

        return $this->removeHook($action, $className, $method ?: null, $priority);
    }

    /**
	 * Remove filters/Actions
	 * 
	 * @param  string  $action
	 * @param  string  $class
	 * @param  string  $method
	 * @param  integer $priority
	 * @return bool
	 */
    public function removeAction($action, $class = '', $method = '', $priority = 10)
    {
        return $this->removeFilter($action, $class, $method, $priority);
    }

    /**
	 * Remove filters/Actions
	 * 
	 * @param  string  $action
	 * @param  string  $class
	 * @param  string  $method
	 * @param  integer $priority
	 * @return bool
	 */
    public function removeCustomFilter($action, $class = '', $method = '', $priority = 10)
    {
        $prefix = $this->config->get('app.hook_prefix');

        return $this->removeFilter(
            $this->hook($prefix, $action), $class, $method, $priority
        );
    }

    /**
	 * Remove filters/Actions
	 * 
	 * @param  string  $action
	 * @param  string  $class
	 * @param  string  $method
	 * @param  integer $priority
	 * @return bool
	 */
    public function removeCustomAction($action, $class = '', $method = '', $priority = 10)
    {
        $prefix = $this->config->get('app.hook_prefix');

        return $this->removeAction(
            $this->hook($prefix, $action), $class, $method, $priority
        );
    }

    /**
     * Determine whether WordPress stored the string itself as the callback:
     * a function name or a static 'Class::method'.
     *
     * @param  string  $handler
     * @return bool
     */
    protected function isPlainCallbackString($handler)
    {
        return function_exists($handler) || strpos($handler, '::') !== false;
    }

    /**
     * Get the class a handler name refers to, as makeInstance() builds it: a
     * short name is a class in the plugin's hook handlers namespace.
     *
     * @param  string  $class
     * @return string
     */
    protected function handlerClassName($class)
    {
        $class = ltrim($class, '\\');

        if (str_contains($class, '@anonymous') || $this->hasNamespace($class)) {
            return $class;
        }

        return $this->handlerNamespace . '\\' . $class;
    }

    /**
     * Remove every callback on the hook that belongs to the class.
     *
     * Without a method, the callback addFilter() registers for the class
     * alone is removed: the object itself when the class is invokable,
     * otherwise its handle() method.
     *
     * @param  string  $action
     * @param  string  $class
     * @param  string|null  $method
     * @param  int  $priority
     * @return bool  Whether a hook was removed.
     */
    private function removeHook($action, $class, $method, $priority)
    {
        global $wp_filter;

        if (empty($wp_filter[$action]->callbacks[$priority])) {
            return false;
        }

        $method = $this->registeredMethod($class, $method);

        $isHookRemoved = false;

        foreach ($wp_filter[$action]->callbacks[$priority] as $registered) {
            $callback = $registered['function'];

            if ($this->isCallbackOf($callback, $class, $method)) {
                $wp_filter[$action]->remove_filter($action, $callback, $priority);

                $isHookRemoved = true;
            }
        }

        return $isHookRemoved;
    }

    /**
     * Find a handler named the way it was added: 'Class@method', a class
     * alone, or [class, method]. addFilter() registered the object the
     * container built, which WordPress's has_filter() cannot match with a
     * name, so the hook's callbacks are searched by class and method.
     * Returns null for any other handler, which has_filter() matches.
     *
     * @param  string  $action
     * @param  mixed  $handler
     * @return int|false|null  The priority, false when absent, or null
     *                         when the handler is not a named class one.
     */
    protected function findNamedHandler($action, $handler)
    {
        if (is_string($handler) && !$this->isPlainCallbackString($handler)) {
            [$class, $method] = Str::parseCallback($handler);
        } elseif (is_array($handler) && $this->isHookHandlerArray($handler) && is_string($handler[0])) {
            [$class, $method] = $handler;
        } else {
            return null;
        }

        global $wp_filter;

        $class = $this->handlerClassName($class);
        $method = $this->registeredMethod($class, $method);

        if (empty($wp_filter[$action])) {
            return false;
        }

        foreach ($wp_filter[$action]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $registered) {
                if ($this->isCallbackOf($registered['function'], $class, $method)) {
                    return $priority;
                }
            }
        }

        return false;
    }

    /**
     * The method addFilter() registers for a class: the one named, or for
     * the class alone the object itself (null) when it is invokable,
     * otherwise its handle() method.
     *
     * @param  string  $class
     * @param  string|null  $method
     * @return string|null
     */
    private function registeredMethod($class, $method)
    {
        if (!is_null($method)) {
            return $method;
        }

        return method_exists($class, '__invoke') ? null : 'handle';
    }

    /**
     * Whether a registered callback is the class's method (or, with a
     * null method, the invokable object itself).
     *
     * @param  mixed  $callback
     * @param  string  $class
     * @param  string|null  $method
     * @return bool
     */
    private function isCallbackOf($callback, $class, $method)
    {
        if (is_null($method)) {
            return is_object($callback) && get_class($callback) === $class;
        }

        return is_array($callback)
            && is_object($callback[0])
            && get_class($callback[0]) === $class
            && $callback[1] === $method;
    }
}
