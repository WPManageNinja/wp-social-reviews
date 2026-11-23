<?php

namespace WPSocialReviews\Framework\Http\Concerns;

use Closure;
use Throwable;
use WP_REST_Response;
use WPSocialReviews\Framework\Support\Str;
use WPSocialReviews\Framework\Support\Pipeline;
use WPSocialReviews\Framework\Validator\ValidationException;
use WPSocialReviews\Framework\Database\Orm\ModelNotFoundException;
use WPSocialReviews\Framework\Foundation\Exceptions\HttpExceptionInterface;
use WPSocialReviews\Framework\Foundation\Exceptions\ExceptionHandler;
use WPSocialReviews\Framework\Http\Response\FileResponse;
use WPSocialReviews\Framework\Http\Response\Response as WPFluentResponse;

trait DispatchesActionTrait
{
    /**
     * Route handler
     *
     * Throws nothing: every exception from the controller or an
     * after-middleware is answered with an error response.
     *
     * @return \WP_REST_Response
     */
    public function callback()
    {
        try {
            return $this->response = $this->handleAfterMiddleware(
                $this->dispatchRouteAction()
            );
        } catch (Throwable $e) {
            return $this->renderException($e);
        }
    }

    /**
     * Answer an exception. Every exception is handled the same way:
     *
     * 1. The plugin's exception handler, when it has a renderer for it.
     * 2. A failed validation: 422 with the errors.
     * 3. A missing model: 404.
     * 4. An exception that says how it is answered (HttpException).
     * 5. Anything else: a server error, its message hidden outside debug.
     *
     * @param  \Throwable $e
     * @return \WP_REST_Response
     */
    protected function renderException(Throwable $e)
    {
        if ($response = $this->mapToHandlerResponse($e)) {
            return $response;
        }

        if ($e instanceof ValidationException) {
            return $this->app->response->sendError(
                $e->errors(), $e->getCode()
            );
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->app->response->sendError([
                'message' => $this->modelNotFoundMessage($e)
            ], 404);
        }

        if ($e instanceof HttpExceptionInterface) {
            return $this->renderHttpException($e);
        }

        // $this->response is only set after a call succeeds: here it is an
        // earlier call's, whose headers aren't this error's.
        return $this->handleUnknownException($e);
    }

    /**
     * Run the bound `ExceptionHandler` over `$e` and convert its result
     * to a `WP_REST_Response`, or `null` if the handler has nothing for
     * this exception (in which case the caller falls through to the
     * sanitizer).
     *
     * An `HttpException` result is answered in the same `{code, message,
     * data}` shape, with its headers, as one the plugin threw itself.
     * A `WP_REST_Response` is returned verbatim — the renderer claimed
     * full control over the response shape. Either way `fluent_exception`
     * fires with the original exception, so observability listeners see
     * the real error and not the message written for the client.
     *
     * @param  \Throwable $e
     * @return \WP_REST_Response|null
     */
    protected function mapToHandlerResponse(Throwable $e)
    {
        $result = $this->app->make(ExceptionHandler::class)->render($e, $this->app);

        if ($result instanceof HttpExceptionInterface) {
            $this->fireExceptionEvent($e, $result);

            return $this->httpExceptionResponse($result);
        }

        if ($result instanceof WP_REST_Response) {
            $this->fireExceptionEvent($e);
            return $result;
        }

        return null;
    }

    /**
     * Handle exception and send error response.
     * 
     * @param  Throwable $e
     * @return \WP_REST_Response
     */
    protected function handleUnknownException(Throwable $e, $headers = [])
    {
        $data = [];

        $this->fireExceptionEvent($e);

        // Production sanitization: client-facing message must not leak
        // PDO / HTTP-client / file-system internals. The real message
        // ships to fluent_exception listeners (Night Watcher / bridge)
        // via fireExceptionEvent above, so observability is preserved.
        if ($this->app->shouldShowErrorDetails()) {
            $data = [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ];

            $message = $e->getMessage();
        } else {
            $message = 'An internal error occurred.';
        }

        return $this->app->response->sendError([
            'code'    => 'plugin_exception',
            'data'    => $data,
            'message' => $message,
        ], $this->httpStatusOf($e, 500), $headers);
    }

    /**
     * The HTTP status an exception's code asks for.
     *
     * Only an error status, 400 to 599, can be one. A code of 600 or more,
     * such as a database error's, is a server error, and so is any code
     * that isn't an integer.
     *
     * @param  \Throwable  $e
     * @param  int  $default  For a code below 400, or none.
     * @return int
     */
    protected function httpStatusOf(Throwable $e, $default)
    {
        $code = $e->getCode();

        if (!is_int($code) || $code >= 600) {
            return 500;
        }

        return $code >= 400 ? $code : $default;
    }

    /**
     * The message for a model that wasn't found.
     *
     * The framework's own message names the model class, so outside debug
     * it is only shown when the plugin wrote the message itself.
     *
     * @param  \WPSocialReviews\Framework\Database\Orm\ModelNotFoundException  $e
     * @return string
     */
    protected function modelNotFoundMessage(ModelNotFoundException $e)
    {
        if (!$e->getModel() || $this->app->shouldShowErrorDetails()) {
            return $e->getMessage();
        }

        return 'Not found.';
    }

    /**
     * Render an HttpException to a sanitization-free response.
     *
     * HttpExceptionInterface is the opt-in contract for "I authored this
     * message, it is safe to ship to the client". Bypasses
     * handleUnknownException's production sanitization but still fires
     * fluent_exception for observability so listeners see every one.
     *
     * @param  HttpExceptionInterface $e
     * @return \WP_REST_Response
     */
    protected function renderHttpException(HttpExceptionInterface $e)
    {
        $this->fireExceptionEvent($e);

        return $this->httpExceptionResponse($e);
    }

    /**
     * Build the error response an HttpException describes.
     *
     * @param  HttpExceptionInterface $e
     * @return \WP_REST_Response
     */
    protected function httpExceptionResponse(HttpExceptionInterface $e)
    {
        return $this->app->response->sendError([
            'code'    => $e->getErrorCode(),
            'message' => $e->getMessage(),
            'data'    => $e->getData(),
        ], $e->getStatusCode(), $e->getHeaders());
    }

    /**
     * Dispatch the route action.
     *
     * @return \WP_REST_Response
     */
    protected function dispatchRouteAction()
    {
        $this->download = null;
        $this->dispatching = true;

        try {
            $response = $this->app->call(
                $this->action, $this->getControllerParameters()
            );
        } finally {
            $this->dispatching = false;
        }

        // A controller that calls download() without returning it still
        // sends the file.
        if (is_null($response) && $this->download) {
            $response = $this->download;
        }

        $this->download = null;

        if ($response instanceof WPFluentResponse) {
            $response = $response->toRestResponse();
        } elseif (!($response instanceof WP_REST_Response)) {
            $response = !is_wp_error($response) ?
                $this->app->response->sendSuccess($response) :
                $this->app->response->wpErrorToResponse($response);
        }

        // Headers and cookies queued on the app's response reach the
        // client whatever response object the controller returned.
        return $this->app->response->mergeQueuedInto($response);
    }

    /**
     * Whether the route's action is running.
     *
     * @return bool
     */
    public function isDispatching()
    {
        return $this->dispatching;
    }

    /**
     * Remember the file the action asked to download.
     *
     * @param  \WPSocialReviews\Framework\Http\Response\FileResponse $file
     * @return \WPSocialReviews\Framework\Http\Response\FileResponse
     */
    public function keepDownload(FileResponse $file)
    {
        return $this->download = $file;
    }

    /**
     * Handle after middleware if any.
     *
     * @param mixed $response
     * @return mixed
     */
    protected function handleAfterMiddleware($response)
    {
        if (!$this->skipMiddleware) {
            $response = $this->app->make(Pipeline::class)
                ->send(new WPFluentResponse($response))
                ->through($this->collectMiddleware('after'))
                ->then(function($response) {
                    return $this->normalize($response);
                });

            if (!$response) {
                $response = $this->app->request->abort();
            }
        }

        return $response;
    }

    /**
     * Normalize the response.
     * 
     * @param  mixed $response
     * @return mixed
     */
    protected function normalize($response)
    {
        if ($response instanceof WPFluentResponse) {
            $response = $response->toRestResponse();
        }

        if (!$response instanceof WP_REST_Response) {
            return new WP_REST_Response($response);
        }

        return $response;
    }

    /**
     * Report an exception through the plugin's exception handler.
     *
     * An exception the handler rendered is reported as itself; the
     * HttpException it was rendered as only decides whether it is a
     * refusal or an error.
     *
     * @param  Exception $exception
     * @param  HttpExceptionInterface|null $renderedAs
     * @return void
     */
    protected function fireExceptionEvent($exception, $renderedAs = null)
    {
        if (!$this->reportExceptions) {
            return;
        }

        $this->app->make(ExceptionHandler::class)->report(
            $exception, $this->app, $renderedAs
        );
    }

    /**
     * Gether route params after substituted the params
     *
     * @return array
     */
    protected function getControllerParameters()
    {
        // Models are looked up on first use, after the before-middleware,
        // and once for both the policy and the controller.
        if (is_null($this->substitutedParameters)) {
            $routeParameters = $this->getParameter();

            $this->substitutedParameters = $routeParameters
                ? $this->substituteParameters($routeParameters)
                : [];
        }

        return $this->substitutedParameters;
    }

    /**
     * Parse the rest and permission/policy handlers
     *
     * @param \WP_REST_Request $request
     * @return null
     * @throws \BadMethodCallException
     */
    public function prepareCallbacks($request)
    {
        $handler = $this->app->parseRestHandler($this->handler, $this->namespace);

        [$action, $controller] = $this->resolveHandlerDetails($handler);

        $policyHandler = $this->resolvePolicyHandler();

        $this->actionInfo = [
            'handler'             => is_object($handler) ? $action : $handler,
            'controller'          => $controller,
            'method'              => $this->getMethodName($action, $handler),
            'path'                => $this->uri,
            'http_method'         => $request->get_method(),
            'full_uri'            => $request->get_route(),
            'permission_callback' => $policyHandler,
            'compiled_url'        => $this->compiled
        ];

        $this->action = $handler;

        return $this->action;
    }

    /**
     * Get the method name to build action info.
     * 
     * @param  mixed $action
     * @param  mixed $handler
     * @return string|null
     */
    protected function getMethodName($action, $handler)
    {
        $method = is_array($action) ? $action[1] ?? '__invoke' : null;

        if (is_null($method) && is_object($handler)) {
            $method = '__invoke';
        }

        return $method;
    }

    /**
     * Resolve the handler details.
     * 
     * @param  mixed $handler
     * @return array
     */
    protected function resolveHandlerDetails($handler)
    {
        if ($handler instanceof Closure) {
            return ['Closure', null];
        }

        if (is_object($handler)) {
            $class = get_class($handler);
            return [$class, $class];
        }

        $handler = trim($handler, '\\');
        [$controller, $method] = Str::parseCallback($handler, '__invoke');
        $controllerName = $this->extractControllerName($controller);

        return [[$controller, $method], $controllerName];
    }

    /**
     * Extract the controller name from the FQCN.
     * 
     * @param  string $fqcn
     * @return string      
     */
    protected function extractControllerName($fqcn)
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
