<?php

namespace WPSocialReviews\Framework\View;

use Exception;
use LogicException;

class View
{
	/**
     * Application Instance
     * @var \WPSocialReviews\Framework\Foundation\Application $app
     */
	protected $app;

	/**
	 * View file path
	 * 
	 * @var string
	 */
	protected $path;

	/**
	 * View data
	 * @var array
	 */
	protected $data = [];
	
	/**
	 * Shared data inall the views
	 * @var array
	 */
	protected static $sharedData = [];

	/**
	 * The folder this view's names resolve in, when setViewPath() set one;
	 * else the app's path.views.
	 *
	 * @var string|null
	 */
	protected $basePath = null;

	/**
	 * Construct the view instamce
	 * @param \WPSocialReviews\Framework\Foundation\Application $app
	 */
	public function __construct($app)
	{
		$this->app = $app;
	}

	/**
	 * Generate and echo/print a view file
	 * @param  string $path
	 * @param  array  $data
	 * @return void
	 */
	public function render($path, $data = [])
	{
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->make($path, $data);
	}

	/**
	 * Generate a view file
	 * @param  string $path
	 * @param  array  $data
	 * @return string [generated html]
	 * @throws Exception
	 */
	public function make($path, $data = [])
	{
		$path = str_replace('.php', '', $path);

		// A full path is used as given; a name never resolves against the
		// working folder (the WordPress root, or wp-admin), where make('index')
		// would load WordPress's own index.php.
		if ($this->isAllowedPath($path) && file_exists($this->path = $path . '.php')) {
			return $this->prepareData($data);
		}

        if (strpos($path, ':')) {
            return $this->loadViewFrom($path, $data);
        }

		if (file_exists($this->resolveFilePath($path))) {
			return $this->prepareData($data);
		}

		$this->handleViewNotFoundException($this->path);
	}

	/**
	 * Load view from specified location.
	 * 
	 * @param  string $path
	 * @param  array $data
	 * @return string
	 * @throws Exception
	 */
	public function loadViewFrom($path, $data)
	{
		$pieces = explode(':', $path);
		
		$pluginRoot = reset($pieces);

		// A plugin's folder name: never a path out of the plugins folder.
		$isFolder = preg_match('/^[A-Za-z0-9._-]+$/', $pluginRoot) && trim($pluginRoot, '.') !== '';

		if ($isFolder && is_dir($root = WP_PLUGIN_DIR . '/' . $pluginRoot)) {
            
            if (is_dir($views = $root . '/app/Views')) {

                // Dots become folders in the view's name only: the install
                // path may have dots of its own (/var/www/example.com/).
                $path = $views . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, end($pieces));

                if (file_exists($this->path = ($path . '.php'))) {
                    return $this->prepareData($data);
                }       
            }
        }
		
        $this->handleViewNotFoundException($path);
	}

    /**
     * Throw view not found exception.
     * 
     * @return void
     * @throws \Exception
     */
    protected function handleViewNotFoundException($path)
    {
        throw new Exception("The view file [{$path}] doesn't exists!");
    }

    /**
     * Prepare data for view.
     * 
     * @param  array $data
     * @return self
     */
    protected function prepareData($data)
    {
        $this->data = array_merge(
            $this->data, static::$sharedData, $data
        );

        return $this;
    }

	/**
	 * Resolve the view file path
	 * @param  string $path
	 * @return string
	 */
	protected function resolveFilePath($path)
	{
        $path = str_replace('.', DIRECTORY_SEPARATOR, $path);

        return $this->path = ($this->basePath !== null ? $this->basePath : $this->app['path.views']) . $path .'.php';
	}

	/**
	 * Evaluate the view file
	 * @param  \WPSocialReviews\Framework\Foundation\Application $app
	 * @return $this
	 */
	protected function renderContent($app)
	{
		ob_start();
		extract($this->data, EXTR_SKIP);
		require $this->getNormalizedPath();
		return ltrim(ob_get_clean());
	}

    /**
     * Get normalized path.
     * 
     * @return string
     */
    protected function getNormalizedPath()
    {
        $ds = DIRECTORY_SEPARATOR;

        $path = str_replace($ds.$ds, $ds, $this->path);

        return $path;
    }

	/**
	 * Share global data for any view
	 * @param  string $key
	 * @param  mixed $value
	 * @return void
	 */
	public function share($key, $value)
	{
		static::$sharedData[$key] = $value;
	}

	/**
	 * Reset all shared view data (useful for test isolation).
	 * 
	 * @return void
	 */
	public static function resetSharedData()
	{
		static::$sharedData = [];
	}

	/**
	 * Provides a fluent interface to set data
	 * @param  array|string $name
	 * @param  mixed $data
	 * @return $this
	 */
	public function with($name, $data = [])
	{
		if (is_array($name)) {
			foreach ($name as $key => $value) {
				$this->__set($key, $value);
			}
		} else {
			$this->__set($name, $data);
		}
		
		return $this;
	}

	/**
	 * Whether a full path may be a view: one inside the plugin's own
	 * folder, or WordPress's content, plugins or themes folders, so a name
	 * taken from the request can't load any PHP file on the server.
	 *
	 * @param  string $path
	 * @return bool
	 */
	protected function isAllowedPath($path)
	{
		if (!path_is_absolute($path) || ($real = realpath($path . '.php')) === false) {
			return false;
		}

		// The plugin's own folder too: a plugin symlinked into the plugins
		// folder (common while developing) resolves to a path outside it.
		$roots = array_filter([
			$this->app['path'] ?? null,
			defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : null,
			defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : null,
			defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : null,
			function_exists('get_theme_root') ? get_theme_root() : null,
		]);

		foreach ($roots as $root) {
			$root = realpath($root);

			if ($root && strpos($real, rtrim($root, '/\\') . DIRECTORY_SEPARATOR) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Set the folder this view's names resolve in (an add-on's own views).
	 * It changes this view only: the app's path.views, and every other
	 * view of the plugin or its add-ons, stay as they are.
	 * 
	 * @param string $path
	 * @return $this
	 */
	public function setViewPath($path)
	{
		$this->basePath = rtrim((string) $path, '/\\') . DIRECTORY_SEPARATOR;

		return $this;
	}

	/**
	 * Get the resolved view path.
	 * 
	 * @return string
	 */
	public function getResolvedPath()
	{
		return $this->path;
	}

	/**
	 * Getter for the view.
	 * 
	 * @param string $key
	 */
	public function __get($key)
	{
		if (isset($this->data[$key])) {
			return $this->data[$key];
		}
	}

	/**
	 * Setter for the view
	 * @param string $key
	 * @param mixed $value
	 */
	public function __set($key, $value)
	{
		$this->data[$key] = $value;
	}

	/**
	 * Return the view data.
	 * 
	 * @return array
	 */
	public function toArray()
	{
		return $this->data;
	}

	/**
	 * Dump the view result.
	 * 
	 * @return string
	 */
	public function __toString()
	{
		return $this->renderContent($this->app);
	}

    /**
     * Prevent unserialization (legacy PHP <7.4)
     */
    public function __wakeup()
    {
        $this->handleUnserializeNotAllowedException();
    }

    /**
     * Prevent unserialization (PHP >=7.4)
     */
    public function __unserialize(array $data)
    {
        $this->handleUnserializeNotAllowedException();
    }

    /**
     * Handle unserialization error.
     * 
     * @return void
     * @throws \LogicException
     */
    protected function handleUnserializeNotAllowedException()
    {
        throw new LogicException(static::class . ' cannot be unserialized.');
    }
}
