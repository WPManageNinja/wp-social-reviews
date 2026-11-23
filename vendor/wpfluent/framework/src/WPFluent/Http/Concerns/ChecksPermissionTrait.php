<?php

namespace WPSocialReviews\Framework\Http\Concerns;

use Exception;
use Throwable;
use WP_Error;
use WP_User;
use WP_REST_Response;
use InvalidArgumentException;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\Framework\Support\Pipeline;
use WPSocialReviews\Framework\Http\PolicyVerdict;
use WPSocialReviews\Framework\Http\Request\Request;
use WPSocialReviews\Framework\Http\Request\WPUserProxy;
use WPSocialReviews\Framework\Database\Orm\ModelNotFoundException;
use WPSocialReviews\Framework\Foundation\Exceptions\HttpExceptionInterface;
use WPSocialReviews\Framework\Container\Contracts\BindingResolutionException;

trait ChecksPermissionTrait
{
    /**
     * Permission callback for route.
     *
     * @param  \WP_REST_Request  $wpRestRequest
     * @return mixed
     */
    public function permissionCallback($wpRestRequest)
    {
        // WordPress runs this again while it builds the Allow header
        // (rest_send_allow_header, on rest_post_dispatch); a failure then
        // was already reported when the request itself was checked.
        $this->reportExceptions = !doing_filter('rest_post_dispatch');

        try {
            $this->prepareRequest($wpRestRequest);

            $this->ensureValidSignature();

            return $this->resolvePermission(
                $this->sendThroughBeforeMiddleware()
            );
        } catch (Throwable $e) {
            return $this->permissionError($e);
        }
    }

    /**
     * Bind the request state the middleware and the policy read.
     *
     * @param  \WP_REST_Request  $wpRestRequest
     * @return void
     */
    protected function prepareRequest($wpRestRequest)
    {
        // The route outlives a request (a batch, rest_do_request(), tests):
        // a new request looks its models up again, while WordPress's second
        // run of the check, for the Allow header, keeps this request's.
        if (!doing_filter('rest_post_dispatch')) {
            $this->boundModels = [];
        }

        $this->parameters = null;
        $this->substitutedParameters = null;
        $this->app->instance('route', $this);
        $this->app->instance('wprestrequest', $wpRestRequest);
        $this->app->request->mergeInputsFromRestRequest($wpRestRequest);
        $this->prepareCallbacks($this->app->request);
    }

    /**
     * Stop the request when the route is signed and its signature is
     * missing, expired or wrong.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function ensureValidSignature()
    {
        if (!$this->isThisValidSignedRoute()) {
            throw new Exception('Invalid Signature', 403);
        }
    }

    /**
     * Send the request through the before-middleware to the policy.
     *
     * @return mixed
     */
    protected function sendThroughBeforeMiddleware()
    {
        return $this->app->make(Pipeline::class)
            ->send($this->app->request)
            ->through($this->collectMiddleware('before'))
            ->then(fn ($request) => $this->checkPolicy($request));
    }

    /**
     * Ask the route's policy whether the request may pass.
     *
     * @param  mixed  $request
     * @return \WPSocialReviews\Framework\Http\PolicyVerdict|null
     */
    protected function checkPolicy($request)
    {
        if ($request instanceof Request) {
            return new PolicyVerdict($this->dispatchPermissionHandler());
        }
    }

    /**
     * Turn what came out of the middleware into WordPress's answer.
     *
     * A before-middleware may only stop the request or hand it on with
     * $next(); only the policy's verdict can let it through. Anything else
     * means a middleware cut the chain short, so the request is denied.
     *
     * @param  mixed  $result
     * @return mixed
     */
    protected function resolvePermission($result)
    {
        if (is_wp_error($result)) {
            return $this->deny(
                $result->get_error_message(), $this->wpErrorStatus($result)
            );
        }

        if ($result instanceof WP_REST_Response) {
            $data = $result->get_data();

            return $this->deny(
                $data['message'] ?? $result->get_status(),
                $result->get_status(),
                is_array($data) ? Arr::except($data, 'message') : [],
                $result->get_headers()
            );
        }

        return $result instanceof PolicyVerdict ? $result->result() : false;
    }

    /**
     * Find the status a WP_Error asks for.
     *
     * WordPress reads it from the error's data, so that comes first; an
     * integer error code is still honoured, and anything else is a 403.
     *
     * @param  \WP_Error  $error
     * @return int
     */
    protected function wpErrorStatus(WP_Error $error)
    {
        $data = $error->get_error_data();

        if (is_array($data) && is_int($data['status'] ?? null)) {
            return $data['status'];
        }

        return is_int($code = $error->get_error_code()) ? $code : 403;
    }

    /**
     * Build the error WordPress returns when the permission check fails.
     *
     * Handled like an exception from the controller: the plugin's
     * exception handler first, then an HttpException as it is. Any other
     * exception shows its message, file and line only when debug is on,
     * so an SQL error never reaches the client.
     *
     * @param  \Throwable  $e
     * @return \WP_Error
     */
    protected function permissionError(Throwable $e)
    {
        if ($response = $this->mapToHandlerResponse($e)) {
            return $this->denyWith($response);
        }

        if ($e instanceof HttpExceptionInterface) {
            $this->fireExceptionEvent($e);

            return $this->deny(
                $e->getMessage(),
                $e->getStatusCode(),
                $e->getData(),
                $e->getHeaders()
            );
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->deny($this->modelNotFoundMessage($e), 404);
        }

        $this->fireExceptionEvent($e);

        // A denied request is never a success, so a low code means 403.
        $status = $this->httpStatusOf($e, 403);

        if (!$this->app->shouldShowErrorDetails()) {
            return $this->deny('An internal error occurred.', $status);
        }

        return $this->deny($e->getMessage(), $status, [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    /**
     * Deny the request with WordPress's permission error.
     *
     * @param  string  $message
     * @param  int  $status
     * @param  array  $data
     * @param  array  $headers
     * @return \WP_Error
     */
    protected function deny($message, $status, $data = [], $headers = [])
    {
        $error = new WP_Error('Permission Callback Error', $message, array_merge(
            (array) $data, ['status' => $status]
        ));

        if (!$headers) {
            return $error;
        }

        $response = rest_convert_error_to_response($error);

        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $this->denyWith($response, $error);
    }

    /**
     * Deny the request and answer with the given response.
     *
     * A permission callback can only return a WP_Error, which carries no
     * headers, so the response replaces it right after WordPress stops the
     * request, unless another filter has replaced it already.
     *
     * @param  \WP_REST_Response  $response
     * @param  \WP_Error|null  $error
     * @return \WP_Error
     */
    protected function denyWith(WP_REST_Response $response, $error = null)
    {
        $error = $error ?: new WP_Error(
            'Permission Callback Error',
            $response->get_data()['message'] ?? '',
            ['status' => $response->get_status()]
        );

        $request = $this->app->make('wprestrequest');

        $answer = function ($result, $handler, $current) use (
            &$answer, $error, $response, $request
        ) {
            if ($current !== $request) {
                return $result;
            }

            remove_filter('rest_request_after_callbacks', $answer, PHP_INT_MAX);

            return $result === $error ? $response : $result;
        };

        add_filter('rest_request_after_callbacks', $answer, PHP_INT_MAX, 3);

        return $error;
    }

    /**
     * Checks if the route is signed and needs validation.
     * 
     * @return boolean [description]
     */
    protected function isThisValidSignedRoute()
    {
        if (!$this->signed) return true;

        $request = $this->app->make('request');

        if ($this->app->make('url')->validate($request->getFullUrl())) {
            parse_str($this->app->make('encrypter')->decrypt(
                $this->app->request->get('_data')
            ), $query);

            $this->dropUnsignedQueryValues($request->getFullUrl(), array_keys($query));

            $this->app->request->merge(
                Arr::except($query, ['expires_at'])
            );

            $this->app->request->forget('_data');

            return true;
        }
    }

    /**
     * Remove the query-string values a signed link didn't sign, so one
     * added to the link can't stand in for a value it never carried. Body
     * fields and WordPress's own parameters stay.
     *
     * @param  string  $url  The URL the request came to.
     * @param  string[]  $signed  The names of the signed values.
     * @return void
     */
    protected function dropUnsignedQueryValues($url, array $signed)
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $sent);

        $unsigned = array_values(array_diff(array_keys($sent), $signed, [
            '_data', '_signature', 'rest_route', '_wpnonce', '_locale',
            '_fields', '_embed', '_envelope', '_method', '_jsonp',
        ]));

        if (!$unsigned) {
            return;
        }

        $this->app->request->forgetQuery($unsigned);

        if ($this->app->bound('wprestrequest')) {
            $wpRestRequest = $this->app->wprestrequest;

            $wpRestRequest->set_query_params(
                Arr::except($wpRestRequest->get_query_params(), $unsigned)
            );
        }
    }

    /**
     * Dispatches the permission handler.
     *
     * @return bool|null
     */
    protected function dispatchPermissionHandler()
    {
        if (!$this->permissionHandler) {
            return true;
        }

        try {
            $parameters = $this->policyParameters();
        } catch (ModelNotFoundException $e) {
            return $this->allowsWithoutTheModel($e);
        }

        return $this->callPermissionHandler($parameters);
    }

    /**
     * The policy's arguments, from its own signature as the controller's
     * come from the controller's: a policy names its parameters as it
     * likes, and a model both bind is looked up once.
     *
     * @return array
     */
    protected function policyParameters()
    {
        $routeParameters = $this->getParameter();

        return $routeParameters
            ? $this->substituteParameters($routeParameters, $this->signatureOf($this->permissionHandler))
            : [];
    }

    /**
     * Ask the policy about a route whose model does not exist, with the
     * route's own values, so a caller it refuses gets the usual refusal
     * instead of learning which records exist. A missing record is only
     * reported to a caller the policy lets in, or when the policy can't
     * decide without the model.
     *
     * @param  \WPSocialReviews\Framework\Database\Orm\ModelNotFoundException  $e
     * @return bool
     * @throws \WPSocialReviews\Framework\Database\Orm\ModelNotFoundException
     */
    protected function allowsWithoutTheModel(ModelNotFoundException $e)
    {
        try {
            $allowed = $this->callPermissionHandler($this->getParameter());
        } catch (HttpExceptionInterface $refusal) {
            throw $refusal;
        } catch (Throwable $cannotDecide) {
            throw $e;
        }

        if (!$allowed) {
            return false;
        }

        throw $e;
    }

    /**
     * Call the policy and read its answer as a yes or a no.
     *
     * @param  array  $parameters
     * @return bool
     */
    protected function callPermissionHandler($parameters)
    {
        try {
            $isValid = $this->app->call(
                $this->permissionHandler, $parameters
            );
        } catch (BindingResolutionException $e) {
            // The policy asks for a value the route doesn't have: the route
            // is broken, so a 500, not the 403 of a refusal.
            throw new BindingResolutionException($e->getMessage(), 500, $e);
        }

        if (is_object($isValid)) {
            if (($userId = $this->userId($isValid)) === null) {
                $this->throwInvalidPolicy();
            }

            // Someone else's user is a no: returning a user means the one
            // making the request may go on.
            return $userId > 0 && $userId === get_current_user_id();
        }

        if (!is_bool($isValid) && !is_int($isValid) && !is_null($isValid)) {
            $this->throwInvalidPolicy();
        }

        return (bool) $isValid;
    }

    /**
     * The ID of a user in any form the plugin has for one: WPUserProxy,
     * WP_User, or the plugin's App\Models\User (what $request->user()
     * gives when the plugin has that model). Null for anything else.
     *
     * @param  object  $user
     * @return int|null
     */
    protected function userId($user)
    {
        if ($user instanceof WPUserProxy) {
            return (int) $user->id();
        }

        if ($user instanceof WP_User) {
            return (int) $user->ID;
        }

        $model = $this->app->__namespace__ . '\App\Models\User';

        return class_exists($model) && $user instanceof $model ? (int) $user->getKey() : null;
    }

    /**
     * Throw invalid policy handling exception.
     * 
     * @return InvalidArgumentException
     */
    protected function throwInvalidPolicy()
    {
        throw new InvalidArgumentException(
            'The policy must return a boolean, integer, null, or a WPUserProxy instance.', 500
        );
    }
}
