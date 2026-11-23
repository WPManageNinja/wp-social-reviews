<?php

namespace WPSocialReviews\Framework\Foundation\Exceptions;

use Throwable;
use WP_REST_Response;
use InvalidArgumentException;
use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\Framework\Support\DebugLog;
use WPSocialReviews\Framework\Validator\ValidationException;
use WPSocialReviews\Framework\Database\Orm\ModelNotFoundException;

/**
 * The plugin's exception handler: how an exception is rendered for the
 * client, and how it is reported.
 *
 * Plugins extend this class, override `register()`, and map exceptions
 * to `HttpException` instances (or directly to a `WP_REST_Response`).
 * A route asks the bound handler FIRST, for every exception from the
 * controller, a policy or a before-middleware. When no renderer answers,
 * the route falls back to its own answer: 422 for a failed validation,
 * 404 for a missing model, what an `HttpExceptionInterface` exception
 * says about itself, and a scrubbed server error for anything else.
 *
 * Plugin example:
 *
 *     namespace Acme\App\Hooks\Handlers;
 *
 *     use WPSocialReviews\Framework\Foundation\Exceptions\ExceptionHandler as BaseHandler;
 *     use WPSocialReviews\Framework\Foundation\Exceptions\ServiceUnavailableHttpException;
 *     use WPSocialReviews\Framework\Foundation\Exceptions\TooManyRequestsHttpException;
 *
 *     class ExceptionHandler extends BaseHandler
 *     {
 *         public function register()
 *         {
 *             $this->renderable(\PDOException::class, function ($e, $app) {
 *                 return new ServiceUnavailableHttpException(
 *                     'Database temporarily unavailable.',
 *                     'db_unavailable'
 *                 );
 *             });
 *
 *             $this->renderable(\Acme\Domain\RateLimitException::class,
 *                 function ($e, $app) {
 *                     return new TooManyRequestsHttpException(
 *                         $e->getMessage(),
 *                         'rate_limited',
 *                         [],
 *                         ['Retry-After' => (string) $e->retryAfter()]
 *                     );
 *                 });
 *         }
 *     }
 *
 * Plugins bind their subclass over the default during boot in
 * `boot/bindings.php`:
 *
 *     $app->singleton(
 *         \WPSocialReviews\Framework\Foundation\Exceptions\ExceptionHandler::class,
 *         \Acme\App\Hooks\Handlers\ExceptionHandler::class
 *     );
 *
 * Renderer return contract — closures may return any of:
 *   - `HttpExceptionInterface` (answered with its status, code, data, headers)
 *   - `WP_REST_Response`      (returned to the client as-is)
 *   - `null`                  (skip this entry; try the next match)
 *   - any other value         (treated as `null` — invalid return ignored)
 *
 * A renderer that throws is caught internally and treated as `null` —
 * a buggy renderer never crashes the request.
 *
 * Matching — `render()` walks the registry in **registration order** and
 * returns the first `instanceof` match. The conventional registration
 * order is therefore "specific classes first, broader classes last". The
 * framework default registers nothing; plugins fully control the
 * registry. Re-registering an existing class name overwrites the closure
 * but keeps its registration position.
 *
 * Reporting — `report()` runs the callbacks given to `reportable()`,
 * writes the debug log and fires `fluent_exception`. A route reports
 * through it, and plugin code can call it from its own `try/catch`:
 *
 *     $this->reportable(\Acme\Domain\PaymentException::class, function ($e, $app) {
 *         Tracker::send($e);
 *
 *         return false; // the tracker has it; keep it out of the debug log
 *     });
 *
 *     $this->dontReport(\Acme\Domain\OutOfStockException::class);
 */
class ExceptionHandler
{
    /**
     * Registered renderers, keyed by exception class name. Walked in
     * insertion order; PHP associative arrays preserve insertion order
     * and `$arr[$key] = $value` to an existing key updates in place.
     *
     * @var array<string, callable>
     */
    protected $renderables = [];

    /**
     * Exception classes that are not reported: no log entry and no
     * `fluent_exception`. A subclass may list its own here, or call
     * `dontReport()` from `register()`.
     *
     * @var string[]
     */
    protected $dontReport = [];

    /**
     * Classes the framework never reports. A refusal isn't an error, as
     * a policy that returns false isn't; a failed validation and a
     * missing record are the client's mistake.
     *
     * @var string[]
     */
    protected $internalDontReport = [
        AuthorizationException::class,
        ValidationException::class,
        ModelNotFoundException::class,
    ];

    /**
     * Registered reporters, as `[class, callable]` pairs in the order
     * they were added. A class may have more than one.
     *
     * @var array<int, array{0: string, 1: callable}>
     */
    protected $reportables = [];

    /**
     * Whether an exception is being reported right now. Shared by every
     * handler instance, so a listener or a reporter that reports again
     * doesn't loop.
     *
     * @var bool
     */
    private static $reporting = false;

    /**
     * Construct the handler. Calls `register()` so subclasses can declare
     * their renderables and reportables in one place without remembering
     * to call it.
     */
    public function __construct()
    {
        $this->register();
    }

    /**
     * Override in a subclass to declare renderables, reportables and the
     * classes that are not reported. The base
     * implementation is a no-op so the framework default acts as a
     * bare registry plugins can add to.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Map an exception class to a renderer closure.
     *
     * @param  string   $class
     * @param  callable $renderer  signature: function ($exception, $app)
     * @return $this
     *
     * @throws \InvalidArgumentException when `$class` is not a non-empty string
     */
    public function renderable($class, callable $renderer)
    {
        $this->renderables[$this->className($class, 'renderable')] = $renderer;

        return $this;
    }

    /**
     * Run a callback when an exception of the given class is reported.
     *
     * Every matching reporter runs, in the order it was added. One that
     * returns `false` stops the reporters after it and keeps the
     * exception out of the debug log; `fluent_exception` still fires.
     *
     * @param  string   $class
     * @param  callable $reporter  signature: function ($exception, $app)
     * @return $this
     *
     * @throws \InvalidArgumentException when `$class` is not a non-empty string
     */
    public function reportable($class, callable $reporter)
    {
        $this->reportables[] = [$this->className($class, 'reportable'), $reporter];

        return $this;
    }

    /**
     * Make sure a class name given to a registry method is usable.
     *
     * @param  mixed  $class
     * @param  string $method
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function className($class, $method)
    {
        if (!is_string($class) || $class === '') {
            throw new InvalidArgumentException(
                "ExceptionHandler::{$method}() expects a non-empty class name string "
                . 'as the first argument.'
            );
        }

        $this->warnIfUnknown($class, $method);

        return $class;
    }

    /**
     * Name a class that doesn't exist in the debug log: it never matches,
     * most often because its use line is missing and ::class resolved in
     * the wrong namespace.
     *
     * @param  string $class
     * @param  string $method
     * @return void
     */
    protected function warnIfUnknown($class, $method)
    {
        if (!class_exists($class) && !interface_exists($class)) {
            DebugLog::warn(
                "ExceptionHandler::{$method}(): there is no class or interface {$class}, "
                . 'so it never matches; check its use line.'
            );
        }
    }

    /**
     * Remove a previously-registered renderer. No-op if the class
     * wasn't registered. Useful for plugins that want to opt out of a
     * default registered by a parent handler.
     *
     * @param  string $class
     * @return $this
     */
    public function forget($class)
    {
        unset($this->renderables[$class]);

        return $this;
    }

    /**
     * Stop reporting the given exception classes, and their children.
     *
     * The response to the client is not changed; only the log entry and
     * the `fluent_exception` action are skipped.
     *
     * @param  string|string[] $classes
     * @return $this
     */
    public function dontReport($classes)
    {
        foreach ((array) $classes as $class) {
            $this->warnIfUnknown($class, 'dontReport');
        }

        $this->dontReport = array_values(array_unique(
            array_merge($this->dontReport, (array) $classes)
        ));

        return $this;
    }

    /**
     * Whether an exception is reported. It isn't when it is an instance
     * of a class given to `dontReport()`, a refusal, a failed validation
     * or a missing record.
     *
     * @param  \Throwable $e
     * @return bool
     */
    public function shouldReport(Throwable $e)
    {
        $classes = array_merge($this->dontReport, $this->internalDontReport);

        foreach ($classes as $class) {
            if ($e instanceof $class) {
                return false;
            }
        }

        return true;
    }

    /**
     * Report an exception: run its reporters, write it to the log and
     * fire `fluent_exception`.
     *
     * An exception rendered as an `HttpException` is reported as itself,
     * so the reporters, the log and the listeners get the real error;
     * the `HttpException` only decides whether it is a refusal or an
     * error.
     *
     * @param  \Throwable $e
     * @param  mixed      $app  the framework App instance; the plugin's own when null
     * @param  \WPSocialReviews\Framework\Foundation\Exceptions\HttpExceptionInterface|null $renderedAs
     * @return void
     */
    public function report(Throwable $e, $app = null, $renderedAs = null)
    {
        $shown = $renderedAs ?: $e;

        if (!$this->shouldReport($e) || !$this->shouldReport($shown)) {
            return;
        }

        // Reentrancy guard: a reporter or a fluent_exception listener that
        // reports an exception itself must not recurse. Reset in finally
        // so the next report proceeds normally.
        if (self::$reporting) {
            return;
        }

        self::$reporting = true;

        try {
            if ($this->runReporters($e, $app ?: App::getInstance()) && $this->shouldLog($shown)) {
                error_log(sprintf(
                    "%s in %s:%d\nStack trace:\n%s\n",
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString()
                ));
            }

            $this->fireExceptionAction($e);
        } finally {
            self::$reporting = false;
        }
    }

    /**
     * Run the reporters registered for an exception.
     *
     * A reporter that throws is caught and logged, so a tracker that is
     * down never crashes the request.
     *
     * @param  \Throwable $e
     * @param  mixed      $app
     * @return bool  false when a reporter asked to keep it out of the log
     */
    protected function runReporters(Throwable $e, $app)
    {
        foreach ($this->reportables as [$class, $reporter]) {
            if (!($e instanceof $class)) {
                continue;
            }

            try {
                if ($reporter($e, $app) === false) {
                    return false;
                }
            } catch (Throwable $inner) {
                error_log(sprintf(
                    '[WPFluent] ExceptionHandler reporter for %s threw: %s in %s:%d',
                    $class,
                    $inner->getMessage(),
                    $inner->getFile(),
                    $inner->getLine()
                ));
            }
        }

        return true;
    }

    /**
     * Fire `fluent_exception` for the listeners.
     *
     * @param  \Throwable $e
     * @return void
     */
    protected function fireExceptionAction(Throwable $e)
    {
        try {
            do_action('fluent_exception', $e);
        } catch (Throwable $listenerError) {
            // Listener-throw isolation: a buggy fluent_exception listener
            // (DB down, disk full) must not escape and crash the response.
            // Never re-fire fluent_exception here — that would be the
            // cascade we are protecting against.
            if ($this->writesLog()) {
                error_log(
                    'fluent_exception listener failed: ' . $listenerError->getMessage()
                );
            }
        }
    }

    /**
     * Whether an exception is written to the log with its trace.
     *
     * An HttpException below 500 is the plugin refusing a request, not
     * an error, so it stays out of the log.
     *
     * @param  \Throwable $shown
     * @return bool
     */
    protected function shouldLog(Throwable $shown)
    {
        if (!$this->writesLog()) {
            return false;
        }

        return !$shown instanceof HttpExceptionInterface
            || $shown->getStatusCode() >= 500;
    }

    /**
     * Whether reported exceptions are written to the log at all.
     *
     * @return bool
     */
    protected function writesLog()
    {
        return (defined('WP_DEBUG') && WP_DEBUG) || defined('FLUENT_BRIDGE_SECRET');
    }

    /**
     * Consult the registry for a thrown exception.
     *
     * Walks `$renderables` in registration order. The first entry whose
     * key the exception is `instanceof` invokes its renderer. The
     * renderer may return an `HttpException`, a `WP_REST_Response`, or
     * `null` (to fall through to the next match). Any thrown exception
     * from the renderer is caught and treated as `null` so a buggy
     * renderer never crashes the request.
     *
     * @param  \Throwable $e
     * @param  mixed      $app  the framework App instance (or null in tests)
     * @return \WPSocialReviews\Framework\Foundation\Exceptions\HttpExceptionInterface|\WP_REST_Response|null
     */
    public function render(Throwable $e, $app = null)
    {
        foreach ($this->renderables as $class => $renderer) {
            if (!($e instanceof $class)) {
                continue;
            }

            try {
                $result = $renderer($e, $app);
            } catch (Throwable $inner) {
                // Renderer crashed. Write it to the error log, in production
                // too, as nothing else reports it; never let it kill the
                // request.
                if (function_exists('error_log')) {
                    error_log(sprintf(
                        '[WPFluent] ExceptionHandler renderer for %s threw while rendering %s: %s in %s:%d',
                        $class,
                        get_class($e),
                        $inner->getMessage(),
                        $inner->getFile(),
                        $inner->getLine()
                    ));
                }
                return null;
            }

            if ($result instanceof HttpExceptionInterface) {
                return $result;
            }

            if ($result instanceof WP_REST_Response) {
                return $result;
            }

            if ($result === null) {
                continue;
            }

            // Invalid return type (string, int, array, etc.) — log it, in
            // production too, and fall through to the sanitizer. Strict by design:
            // the contract is HttpException | WP_REST_Response | null.
            if (function_exists('error_log')) {
                error_log(sprintf(
                    '[WPFluent] ExceptionHandler renderer for %s returned %s while rendering %s; '
                    . 'expected HttpException | WP_REST_Response | null. Ignoring.',
                    $class,
                    is_object($result) ? get_class($result) : gettype($result),
                    get_class($e)
                ));
            }
            return null;
        }

        return null;
    }

    /**
     * List the class names this handler has renderables registered for.
     * Useful for introspection, debugging, and AI tooling that wants to
     * know which exception types are mapped to safe responses.
     *
     * @return string[]
     */
    public function registeredFor()
    {
        return array_keys($this->renderables);
    }

    /**
     * Whether the handler has a renderer registered for `$class`. Does
     * NOT walk the inheritance chain — exact key match only. Use this
     * for "is THIS specific class registered" checks; use `render()`
     * for "does any registration handle this exception".
     *
     * @param  string $class
     * @return bool
     */
    public function hasRenderable($class)
    {
        return is_string($class) && array_key_exists($class, $this->renderables);
    }
}
