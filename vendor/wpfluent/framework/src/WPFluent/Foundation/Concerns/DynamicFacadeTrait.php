<?php

namespace WPSocialReviews\Framework\Foundation\Concerns;

use WPSocialReviews\Framework\Support\Facade;
use RuntimeException;

/**
 * Facades made on the fly: a class in the plugin's Facade namespace that
 * doesn't exist, such as MyPlugin\Facade\DB, is written to the uploads
 * folder and loaded, calling the binding named after it.
 *
 * @deprecated 2.19.0 A facade must be a real class: one of the framework's
 *             own in \WPSocialReviews\Framework\Support\Facades (DB, View, Route, Mail,
 *             Config), or one that extends \WPSocialReviews\Framework\Support\Facade and
 *             returns its binding from getFacadeAccessor(). Facades made
 *             on the fly will be removed in a release after 2026-11-05.
 */
trait DynamicFacadeTrait
{
    /**
     * Register the dynamic facade resolver.
     * 
     * Registers an SPL autoloader to intercept facade class requests within
     * the current namespace's Facade sub-namespace. Dynamically
     * creates facade classes on-demand.
     *
     * @param  \WPSocialReviews\Framework\Foundation\Application $app
     * @return void
     */
    protected function registerFacadeResolver($app)
    {
        Facade::setFacadeApplication($app);

        spl_autoload_register(function($class) use ($app) {
            $fqn = __NAMESPACE__;

            $ns = substr($fqn, 0, strpos($fqn, '\\'));

            if (str_contains($class, $facade = $ns . '\Facade')) {
                $this->createFacadeFor($facade, $class, $app);
            }
        }, true, true);
    }

    /**
     * Resolve the binding name for the facade.
     *
     * Transforms the fully qualified facade class name to a service container
     * binding key (accessor) by stripping the facade namespace
     * and normalizing names.
     *
     * Special cases (e.g. 'route' to 'router') are handled here. The name is
     * not checked against the container: the facade resolves it when it is
     * called, so a facade loaded before its binding works once it is bound.
     *
     * @param  string $facade Facade namespace prefix (e.g., WPSocialReviews\Framework\Facade)
     * @param  string $class  Fully qualified facade class name
     * @param  \WPSocialReviews\Framework\Foundation\Application $app
     * @return string The accessor name.
     */
    protected function resolveFacadeAccessor($facade, $class, $app)
    {
        $name = strtolower(trim(str_replace($facade, '', $class), '\\'));

        if ($name === 'route') {
            $name = 'router';
        }

        return $name;
    }

    /**
     * Create a facade resolver class dynamically.
     *
     * Generates a PHP facade class file under the uploads directory and loads it.
     * This enables real-time facade creation for any requested class.
     *
     * @param string $facade Facade namespace prefix
     * @param string $class  Fully qualified class name of the facade to generate
     * @param \WPSocialReviews\Framework\Foundation\Application $app
     * @return void
     * @throws \RuntimeException If the facade stub file is missing
     */
    protected function createFacadeFor($facade, $class, $app)
    {
        $accessor = $this->resolveFacadeAccessor($facade, $class, $app);
        [$namespace, $baseClass] = $this->extractNamespaceAndClass($class);
        $slug = $app->config->get('slug');

        $facadesDir = $this->getFacadeDirectory($slug);
        $this->ensureDirectoryExists($facadesDir);

        $facadeFile = $this->getFacadeFilePath($facadesDir, $class);

        $facadeCode = $this->populateFacadeStub(
            $namespace, $baseClass, $accessor
        );

        // Rewritten when it differs, so a file from an older stub, or with
        // another accessor, never outlives the code that wrote it.
        $written = !file_exists($facadeFile)
            || file_get_contents($facadeFile) !== $facadeCode;

        if ($written) {
            file_put_contents($facadeFile, $facadeCode);
        }

        $this->loadFacadeClass($class, $facadeFile);

        $this->deprecatedFacade($class, $accessor, $written);
    }

    /**
     * Write a facade made on the fly to the debug log, once: when its file
     * is written.
     *
     * No PHP notice and no deprecated_class_run: a plugin's error handler
     * may turn a notice into an exception, and WordPress's test case fails
     * a test on the action, while the facade must keep working until it
     * is removed.
     *
     * @param  string $class
     * @param  string $accessor
     * @param  bool   $written
     * @return void
     */
    protected function deprecatedFacade($class, $accessor, $written)
    {
        if (!$written || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $builtIn = Facade::class . 's\\' . $this->extractNamespaceAndClass($class)[1];

        $instead = class_exists($builtIn)
            ? "Use {$builtIn} instead"
            : sprintf(
                "Write a class of its own that extends %s and returns '%s' "
                . 'from getFacadeAccessor()',
                Facade::class,
                $accessor
            );

        error_log(sprintf(
            '[WPFluent] The facade %s is made on the fly, which is deprecated '
            . 'and will be removed in a release after 2026-11-05. %s.',
            $class,
            $instead
        ));
    }

    /**
     * Extract the namespace and base class name from a
     * fully qualified class name (FQCN).
     *
     * @param  string $fqcn Fully qualified class name
     * @return array{string, string} Tuple of namespace and base class name
     */
    protected function extractNamespaceAndClass($fqcn)
    {
        $parts = explode('\\', $fqcn);
        $baseClass = array_pop($parts);
        $namespace = implode('\\', $parts);
        return [$namespace, $baseClass];
    }

    /**
     * Get the directory path where facade classes are stored based on app.slug.
     *
     * @param  string $slug Plugin slug used for folder naming inside uploads
     * @return string The absolute directory path where facade classes are saved
     */
    protected function getFacadeDirectory($slug)
    {
        $uploadDir = wp_upload_dir();
        return "{$uploadDir['basedir']}/{$slug}/facades";
    }

    /**
     * Ensure that the given directory exists; create it recursively if not.
     *
     * @param  string $dir Directory path
     * @return void
     */
    protected function ensureDirectoryExists($dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Get the full path for the generated facade PHP file based on class name.
     *
     * Converts namespace separators to underscores for safe file names.
     *
     * @param  string $facadesDir Directory where facades are stored
     * @param  string $class      Fully qualified class name
     * @return string Full file path for the facade class file
     */
    protected function getFacadeFilePath($facadesDir, $class)
    {
        $safeName = str_replace(['\\', '/'], '_', $class);
        return "{$facadesDir}/{$safeName}.php";
    }

    /**
     * Populate the facade stub template placeholders with real values.
     *
     * Replaces `{{ namespace }}`, `{{ $stub }}`, `{{ class }}` and
     * `{{ accessor }}` placeholders with corresponding values.
     *
     * @param  string $namespace The namespace for the facade class
     * @param  string $class     The base class name for the facade class
     * @param  string $accessor  The accessor name returned by getFacadeAccessor()
     * @return string The populated PHP class code
     */
    protected function populateFacadeStub($namespace, $class, $accessor)
    {
        return str_replace(
            ['{{ namespace }}', '{{ use }}', '{{ class }}', '{{ accessor }}'],
            [$namespace, Facade::class, $class, $accessor],
            $this->getFacadeStub(__DIR__ . '/facade.stub')
        );
    }

    /**
     * Retrieve the content of the facade stub file.
     *
     * Throws a RuntimeException if the stub file is missing.
     *
     * @param  string $stubPath Absolute path to the stub file
     * @return string The stub file content
     * @throws \RuntimeException If stub file does not exist
     */
    protected function getFacadeStub($stubPath)
    {
        if (!file_exists($stubPath)) {
            throw new RuntimeException(
                "Facade stub not found at: {$stubPath}"
            );
        }

        return file_get_contents($stubPath);
    }

    /**
     * Load the generated facade class file if the class is not already loaded.
     *
     * @param  string $class      Fully qualified class name
     * @param  string $facadeFile Absolute path to the generated facade PHP file
     * @return void
     */
    protected function loadFacadeClass($class, $facadeFile)
    {
        if (!class_exists($class, false)) {
            require_once $facadeFile;
        }
    }
}
