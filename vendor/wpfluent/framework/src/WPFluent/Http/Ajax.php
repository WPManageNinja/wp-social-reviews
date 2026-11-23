<?php

namespace WPSocialReviews\Framework\Http;

use Throwable;
use WP_HTTP_Response;
use BadMethodCallException;
use InvalidArgumentException;
use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\Framework\Validator\ValidationException;
use WPSocialReviews\Framework\Database\Orm\ModelNotFoundException;
use WPSocialReviews\Framework\Foundation\Exceptions\HttpException;
use WPSocialReviews\Framework\Foundation\Exceptions\ExceptionHandler;
use WPSocialReviews\Framework\Foundation\Exceptions\HttpExceptionInterface;

/**
 * Register AJAX handlers using HTTP-like method names.
 *
 * $action should start with config.app.hook_prefix,
 * e.g. `wpfluent_my_action`.
 * 
 * The $handler can be a callable or "Class@method" string.
 * These dynamic methods delegate to the register() method.
 * 
 * Each is static (`Ajax::post(...)`) and can be called on an instance too
 * (`$ajax->post(...)`); `@method static void` says both to PHPStan. They
 * register the handler and return nothing, so they do not chain.
 *
 * @method static void get(string $action, callable|string $handler, int|string|array $priority = 10, string $scope = "both")
 * @method static void post(string $action, callable|string $handler, int|string|array $priority = 10, string $scope = "both")
 * @method static void put(string $action, callable|string $handler, int|string|array $priority = 10, string $scope = "both")
 * @method static void patch(string $action, callable|string $handler, int|string|array $priority = 10, string $scope = "both")
 * @method static void delete(string $action, callable|string $handler, int|string|array $priority = 10, string $scope = "both")
 *
 * Usage Examples:
 *
 * Ajax::post('action', 'MyController@handle'); // priority 10, admin & public
 * Ajax::post('action', 'MyController@handle', 5); // priority 5, admin & public
 * Ajax::post('action', 'MyController@handle', 'admin'); // priority 10, admin
 * Ajax::post('action', 'MyController@handle', 5, 'public'); // priority 5, public
 *
 * Ajax::post('action', 'MyController@handle', [
 *  'priority' => 5,
 *  'scope' => 'public'
 * ]);
 */

class Ajax
{
	/**
	 * @var \WPSocialReviews\Framework\Foundation\Application
	 */
	protected $app = null;
	
	/**
	 * $passthru allowed methods
	 * @var array
	 */
	protected $passthru = [
		'get',
		'post',
		'put',
		'patch',
		'delete',
	];

	/**
	 * Consruct the Instance.
	 * 
	 * @param \WPSocialReviews\Framework\Foundation\Application $app
	 */
	public function __construct($app = null)
	{
		$this->app = $app ?? App::getInstance();
	}

	/**
	 * Alternative constructor.
	 * 
	 * @return $this
	 */
	public static function getInstance()
	{
		return new static;
	}

	/**
	 * Register the ajax hook using appropriate method.
	 * 
	 * @param  string  			$method
	 * @param  string  			$action
	 * @param  callable|string  $handler
	 * @param  integer 			$priority
	 * @param  string  			$scope
	 * @return void
	 */
	public function register(
	    $method,
	    $action,
	    $handler,
	    $priority = 10,
	    $scope = 'both'
	) {
	    if (!in_array($method, $this->passthru)) {
	        throw new BadMethodCallException(
	            "Ajax::{$method}() is not supported."
	        );
	    }

	    // If 3rd argument is an array
	    if (is_array($priority)) {
	        $scope = $priority['scope'] ?? 'both';
	        $priority = $priority['priority'] ?? 10;
	    }
	    // If scope is passed as the 3rd argument
	    elseif (is_string($priority) && !is_numeric($priority)) {
	        [$scope, $priority] = [$priority, $scope];
	    }

	    if (!in_array($scope, ['admin', 'public', 'both'], true)) {
	        throw new InvalidArgumentException(
	            "Invalid scope '{$scope}' provided."
	        );
	    }

	    $callback = function () use ($method, $handler) {
	        try {
	            $data = $this->toAjaxData($this->handle($method, $handler));
	        } catch (Throwable $e) {
	            $this->fail($e);

	            return;
	        }

	        wp_send_json_success($data);
	    };

	    if ($scope === 'both') {
	    	$registrationMethod = 'addAjaxActions';
	    } elseif ($scope === 'admin') {
	    	$registrationMethod = 'addAdminAjaxAction';
	    } elseif ($scope === 'public') {
	    	$registrationMethod = 'addPublicAjaxAction';
	    }

	    $this->app->$registrationMethod(
        	$action, $callback, $priority
        );
	}

	/**
	 * Report an exception through the plugin's exception handler.
	 *
	 * @param  \Throwable $e
	 * @param  \WPSocialReviews\Framework\Foundation\Exceptions\HttpExceptionInterface|null $renderedAs
	 * @return void
	 */
	protected function report(Throwable $e, $renderedAs = null)
	{
		$this->app->make(ExceptionHandler::class)->report(
			$e, $this->app, $renderedAs
		);
	}

	/**
	 * Report an exception and answer the request with an error.
	 *
	 * The plugin's exception handler is asked first how to answer it, as
	 * a route asks it.
	 *
	 * @param  \Throwable $e
	 * @return void
	 */
	protected function fail(Throwable $e)
	{
		$rendered = $this->app->make(ExceptionHandler::class)->render($e, $this->app);

		$this->report(
			$e, $rendered instanceof HttpExceptionInterface ? $rendered : null
		);

		[$data, $status] = $this->errorResponse($rendered ?: $e);

		wp_send_json_error($data, $status);
	}

	/**
	 * The error an exception is answered with, as [data, status]:
	 *
	 * 1. A response the plugin's handler made: its data and status.
	 * 2. A failed validation: 422 with the message and the errors.
	 * 3. A missing model: 404.
	 * 4. An exception that says how it is answered (HttpException).
	 * 5. Anything else: a server error, its message hidden outside debug.
	 *
	 * @param  \Throwable|\WP_HTTP_Response $error
	 * @return array
	 */
	public function errorResponse($error)
	{
		if ($error instanceof WP_HTTP_Response) {
			return [$error->get_data(), $error->get_status()];
		}

		if ($error instanceof ValidationException) {
			return [[
				'message' => $error->getMessage(),
				'errors'  => $error->errors(),
			], 422];
		}

		if ($error instanceof ModelNotFoundException) {
			$named = $error->getModel() && !$this->app->shouldShowErrorDetails();

			return [$named ? 'Not found.' : $error->getMessage(), 404];
		}

		if ($error instanceof HttpExceptionInterface) {
			return [$error->getMessage(), $error->getStatusCode()];
		}

		$code = $error->getCode();

		return [
			$this->app->shouldShowErrorDetails() ? $error->getMessage() : 'An internal error occurred.',
			is_int($code) && $code >= 400 && $code < 600 ? $code : 500,
		];
	}

	/**
	 * Handle an AJAX request.
	 * 
	 * @param  string $method
	 * @param  mixed $handler
	 * @return mixed
	 */
	public function handle($method, $handler)
	{
		if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
			throw new HttpException(405, 'Invalid request method.');
		}

		check_ajax_referer($this->app->config->get('hook_prefix'));

		return $this->app->call(
			$this->app->parseHookHandler($handler)
		);
	}

	/**
	 * Turn a handler's redirect into where the page should go, since an
	 * ajax call can't redirect the page itself: the script reads
	 * data.redirect_to and navigates. Anything else is sent as it is.
	 *
	 * @param  mixed $result
	 * @return mixed
	 */
	protected function toAjaxData($result)
	{
		if (!$result instanceof WP_HTTP_Response) {
			return $result;
		}

		$status = $result->get_status();

		$headers = array_change_key_case($result->get_headers());

		if ($status >= 300 && $status < 400 && isset($headers['location'])) {
			return ['redirect_to' => $headers['location']];
		}

		return $result;
	}

	/**
	 * Overload magic method.
	 * 
	 * @param  string $method
	 * @param  array $args
	 * @return void
	 */
	public function __call($method, $args)
	{
		$this->register($method, ...$args);
	}

	/**
	 * Overload static magic method.
	 * 
	 * @param  string $method
	 * @param  array $args
	 * @return void
	 */
	public static function __callStatic($method, $args)
	{
		return (new static)->$method(...$args);
	}
}
