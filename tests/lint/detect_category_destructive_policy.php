<?php
/** WHAT: global category rename/delete routes require both review and testimonial capabilities. Production: api.php category routes + CategoryManagementPolicy::verifyRequest. */
require_once __DIR__ . '/source-contract-lib.php';

class WpsrPhase23RouteCollector
{
    private $state;
    private $prefix;
    private $policy;
    public function __construct($state = null, $prefix = '', $policy = '')
    {
        $this->state = $state ?: (object) ['routes' => []];
        $this->prefix = trim($prefix, '/');
        $this->policy = $policy;
    }
    public function prefix($prefix) { return new self($this->state, trim($this->prefix . '/' . trim($prefix, '/'), '/'), $this->policy); }
    public function withPolicy($policy) { $this->policy = (string) $policy; return $this; }
    public function group($callback) { $callback($this); return $this; }
    public function get($path, $handler) { return $this->add('GET', $path); }
    public function post($path, $handler) { return $this->add('POST', $path); }
    public function put($path, $handler) { return $this->add('PUT', $path); }
    public function delete($path, $handler) { return $this->add('DELETE', $path); }
    public function patch($path, $handler) { return $this->add('PATCH', $path); }
    public function int($identifiers) { return $this; }
    public function __call($method, $arguments)
    {
        throw new RuntimeException('Unsupported route declaration or fluent method: ' . $method);
    }
    public function routes() { return $this->state->routes; }
    private function add($method, $path)
    {
        $this->state->routes[] = [
            'method' => $method,
            'route' => '/' . trim($this->prefix . '/' . trim($path, '/'), '/'),
            'policy' => $this->policy,
        ];
        return $this;
    }
}

$wpsr = wpsr_source_contract_start('category-destructive-policy', $argv);
try {
    $policy = $wpsr->phpMethod('app/Http/Policies/CategoryManagementPolicy.php', 'verifyRequest');
    $wpsr->requirePattern($policy, '/currentUserCan\s*\(\s*[\'\"]wpsn_manage_reviews[\'\"]\s*\)\s*&&\s*\$this->currentUserCan\s*\(\s*[\'\"]wpsn_manage_testimonials[\'\"]\s*\)/', 'category policy must conjunct both capabilities');

    $routeFile = $wpsr->source('app/Http/Routes/api.php');
    if (!defined('ABSPATH')) define('ABSPATH', dirname($routeFile['path'], 4) . '/');
    $router = new WpsrPhase23RouteCollector();
    require $routeFile['path'];
    $routes = $router->routes();
    if (!$routes) throw new RuntimeException('app/Http/Routes/api.php:1 route parser discovered zero routes');

    foreach (['PUT', 'DELETE'] as $method) {
        foreach (['/reviews/categories/{id}', '/testimonials/categories/{id}'] as $route) {
            $matches = array_values(array_filter($routes, function ($row) use ($method, $route) {
                return $row['method'] === $method && $row['route'] === $route;
            }));
            $wpsr->check(count($matches) === 1 && $matches[0]['policy'] === 'CategoryManagementPolicy', $routeFile,
                $method . ' ' . $route . ' must mount exactly once under CategoryManagementPolicy');
        }
    }
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();
