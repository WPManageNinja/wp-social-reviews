<?php
/**
 * Phase 3 static drift gate: every core mutating route must map to permission smoke.
 *
 * Route declarations load against this collector only. WordPress, plugin bootstrap,
 * controllers, policies, and database never load.
 */

class WpsrPermissionRouteRouter
{
    private $wpsr_prefix;
    private $wpsr_policy;
    private $wpsr_state;

    public function __construct($wpsr_state = null, $wpsr_prefix = '', $wpsr_policy = '')
    {
        $this->wpsr_state = $wpsr_state ?: (object) ['routes' => []];
        $this->wpsr_prefix = trim((string) $wpsr_prefix, '/');
        $this->wpsr_policy = (string) $wpsr_policy;
    }

    public function prefix($wpsr_prefix)
    {
        $wpsr_combined = trim($this->wpsr_prefix . '/' . trim((string) $wpsr_prefix, '/'), '/');

        return new self($this->wpsr_state, $wpsr_combined, $this->wpsr_policy);
    }

    public function withPolicy($wpsr_policy)
    {
        $this->wpsr_policy = is_string($wpsr_policy) ? $wpsr_policy : 'callable';

        return $this;
    }

    public function group($wpsr_callback)
    {
        if (!is_callable($wpsr_callback)) {
            throw new RuntimeException('Route group callback is not callable.');
        }

        $wpsr_callback($this);

        return $this;
    }

    public function get($wpsr_path, $wpsr_handler)
    {
        return $this;
    }

    public function post($wpsr_path, $wpsr_handler)
    {
        return $this->wpsr_add_route('POST', $wpsr_path, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1));
    }

    public function put($wpsr_path, $wpsr_handler)
    {
        return $this->wpsr_add_route('PUT', $wpsr_path, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1));
    }

    public function delete($wpsr_path, $wpsr_handler)
    {
        return $this->wpsr_add_route('DELETE', $wpsr_path, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1));
    }

    public function patch($wpsr_path, $wpsr_handler)
    {
        return $this->wpsr_add_route('PATCH', $wpsr_path, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1));
    }

    public function routes()
    {
        return $this->wpsr_state->routes;
    }

    public function int($wpsr_identifiers)
    {
        return $this;
    }

    public function __call($wpsr_method, $wpsr_arguments)
    {
        throw new RuntimeException('Unsupported route declaration or fluent method: ' . $wpsr_method);
    }

    private function wpsr_add_route($wpsr_method, $wpsr_path, array $wpsr_trace)
    {
        $wpsr_path = '/' . ltrim((string) $wpsr_path, '/');
        $wpsr_full_path = '/' . trim($this->wpsr_prefix . '/' . trim($wpsr_path, '/'), '/');

        $this->wpsr_state->routes[] = [
            'declaration' => $wpsr_method . ' ' . $this->wpsr_prefix . ':' . $wpsr_path,
            'method'      => $wpsr_method,
            'route'       => $wpsr_full_path,
            'policy'      => $this->wpsr_policy,
            'line'        => isset($wpsr_trace[0]['line']) ? (int) $wpsr_trace[0]['line'] : 0,
        ];

        return $this;
    }
}

function wpsr_permission_route_collector_fails_closed()
{
    $wpsr_router = new WpsrPermissionRouteRouter();

    try {
        $wpsr_router->any('/__wpsr_any_probe', ['WpsrRouteProbe', 'handle']);
    } catch (RuntimeException $wpsr_exception) {
        return $wpsr_exception->getMessage() === 'Unsupported route declaration or fluent method: any';
    }

    return false;
}

function wpsr_permission_route_pattern($wpsr_source_route)
{
    $wpsr_parts = preg_split('/(\{[^}\/]+\})/', $wpsr_source_route, -1, PREG_SPLIT_DELIM_CAPTURE);
    $wpsr_pattern = '';

    foreach ($wpsr_parts as $wpsr_part) {
        if (preg_match('/^\{[^}\/]+\}$/', $wpsr_part)) {
            // Manifest may retain source token, use a named fixture token, or use a concrete segment.
            $wpsr_pattern .= '(?:\{[^}\/]+\}|[^\/{}]+)';
        } else {
            $wpsr_pattern .= preg_quote($wpsr_part, '#');
        }
    }

    return '#^' . $wpsr_pattern . '$#';
}

$wpsr_root = dirname(__DIR__, 2);
$wpsr_routes_file = $wpsr_root . '/app/Http/Routes/api.php';
$wpsr_manifest_file = __DIR__ . '/routes.manifest.php';
$wpsr_errors = [];
$wpsr_supported_methods = ['POST', 'PUT', 'DELETE', 'PATCH'];

if (!wpsr_permission_route_collector_fails_closed()) {
    fwrite(STDERR, "permission-route-coverage: FAIL — collector accepted unsupported any() declaration.\n");
    exit(1);
}

if (!is_file($wpsr_routes_file)) {
    fwrite(STDERR, "permission-route-coverage: source route file missing: {$wpsr_routes_file}\n");
    exit(1);
}

if (!is_file($wpsr_manifest_file)) {
    fwrite(STDERR, "permission-route-coverage: manifest missing: {$wpsr_manifest_file}\n");
    exit(1);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $wpsr_root . '/');
}

$router = new WpsrPermissionRouteRouter();
try {
    require $wpsr_routes_file;
} catch (Throwable $wpsr_exception) {
    fwrite(STDERR, 'permission-route-coverage: failed to read route declarations: ' . $wpsr_exception->getMessage() . "\n");
    exit(1);
}

$wpsr_source_routes = $router->routes();
unset($router);
$wpsr_manifest = require $wpsr_manifest_file;

if (!is_array($wpsr_manifest)) {
    fwrite(STDERR, "permission-route-coverage: manifest must return an array.\n");
    exit(1);
}
if (!$wpsr_source_routes) {
    fwrite(STDERR, "permission-route-coverage: FAIL — discovered zero core mutating routes; source discovery is broken.\n");
    exit(1);
}
if (!$wpsr_manifest) {
    fwrite(STDERR, "permission-route-coverage: FAIL — discovered zero permission cases; manifest discovery is broken.\n");
    exit(1);
}

$wpsr_source_by_declaration = [];
$wpsr_source_method_counts = array_fill_keys($wpsr_supported_methods, 0);
foreach ($wpsr_source_routes as $wpsr_source) {
    $wpsr_declaration = $wpsr_source['declaration'];
    $wpsr_source_method_counts[$wpsr_source['method']]++;
    if (isset($wpsr_source_by_declaration[$wpsr_declaration])) {
        $wpsr_errors[] = 'duplicate source declaration cannot be mapped uniquely: ' . $wpsr_declaration;
        continue;
    }
    if ($wpsr_source['policy'] === '') {
        $wpsr_errors[] = 'source mutating route has no explicit mounted policy: ' . $wpsr_declaration;
    }
    $wpsr_source_by_declaration[$wpsr_declaration] = $wpsr_source;
}

$wpsr_manifest_by_declaration = [];
$wpsr_manifest_method_counts = array_fill_keys($wpsr_supported_methods, 0);
foreach ($wpsr_manifest as $wpsr_index => $wpsr_case) {
    $wpsr_case_number = $wpsr_index + 1;
    if (!is_array($wpsr_case)) {
        $wpsr_errors[] = "manifest case {$wpsr_case_number} is not an array";
        continue;
    }

    foreach (['declaration', 'label', 'method', 'route', 'policy'] as $wpsr_required_key) {
        if (!isset($wpsr_case[$wpsr_required_key]) || !is_string($wpsr_case[$wpsr_required_key]) || $wpsr_case[$wpsr_required_key] === '') {
            $wpsr_errors[] = "manifest case {$wpsr_case_number} has empty or missing {$wpsr_required_key}";
        }
    }
    if (!isset($wpsr_case['params']) || !is_array($wpsr_case['params'])) {
        $wpsr_errors[] = "manifest case {$wpsr_case_number} has missing or non-array params";
    }

    $wpsr_method = isset($wpsr_case['method']) ? strtoupper((string) $wpsr_case['method']) : '';
    if (!in_array($wpsr_method, $wpsr_supported_methods, true)) {
        $wpsr_errors[] = "manifest case {$wpsr_case_number} has unsupported method {$wpsr_method}";
    } else {
        $wpsr_manifest_method_counts[$wpsr_method]++;
    }

    if (empty($wpsr_case['declaration']) || !is_string($wpsr_case['declaration'])) {
        continue;
    }

    $wpsr_declaration = $wpsr_case['declaration'];
    $wpsr_manifest_by_declaration[$wpsr_declaration][] = $wpsr_case_number;
    if (count($wpsr_manifest_by_declaration[$wpsr_declaration]) > 1) {
        $wpsr_errors[] = 'duplicate manifest mapping: ' . $wpsr_declaration;
    }

    if (isset($wpsr_source_by_declaration[$wpsr_declaration])) {
        $wpsr_source = $wpsr_source_by_declaration[$wpsr_declaration];
        if ($wpsr_method !== $wpsr_source['method']) {
            $wpsr_errors[] = "manifest case {$wpsr_case_number} method {$wpsr_method} does not match {$wpsr_source['method']}";
        }
        if (!empty($wpsr_case['route']) && !preg_match(wpsr_permission_route_pattern($wpsr_source['route']), $wpsr_case['route'])) {
            $wpsr_errors[] = "manifest case {$wpsr_case_number} route {$wpsr_case['route']} does not represent {$wpsr_source['route']}";
        }
        if (isset($wpsr_case['policy']) && $wpsr_case['policy'] !== $wpsr_source['policy']) {
            $wpsr_errors[] = "manifest case {$wpsr_case_number} policy {$wpsr_case['policy']} does not match {$wpsr_source['policy']}";
        }
    }
}

$wpsr_missing = array_diff_key($wpsr_source_by_declaration, $wpsr_manifest_by_declaration);
$wpsr_phantom = array_diff_key($wpsr_manifest_by_declaration, $wpsr_source_by_declaration);
$wpsr_source_count = count($wpsr_source_routes);
$wpsr_case_count = count($wpsr_manifest);
$wpsr_mapping_count = count($wpsr_manifest_by_declaration);
$wpsr_format_counts = function (array $wpsr_counts) {
    $wpsr_parts = [];
    foreach ($wpsr_counts as $wpsr_method => $wpsr_count) {
        $wpsr_parts[] = $wpsr_method . ' ' . $wpsr_count;
    }
    return implode(', ', $wpsr_parts);
};

echo "permission-route-coverage: discovered {$wpsr_source_count} core mutating routes ("
    . $wpsr_format_counts($wpsr_source_method_counts) . "); {$wpsr_case_count} manifest cases; {$wpsr_mapping_count} mappings.\n";

if ($wpsr_missing) {
    $wpsr_errors[] = count($wpsr_missing) . ' source mutating route(s) have no manifest representation';
    echo "\nMissing manifest mappings:\n";
    foreach ($wpsr_missing as $wpsr_declaration => $wpsr_source) {
        echo "  - {$wpsr_declaration} [{$wpsr_source['method']} {$wpsr_source['route']}] app/Http/Routes/api.php:{$wpsr_source['line']}\n";
    }
}
if ($wpsr_phantom) {
    $wpsr_errors[] = count($wpsr_phantom) . ' stale/phantom manifest mapping(s) have no source mutating route';
    echo "\nStale/phantom manifest mappings:\n";
    foreach ($wpsr_phantom as $wpsr_declaration => $wpsr_case_numbers) {
        echo '  - ' . $wpsr_declaration . ' [manifest case(s) ' . implode(', ', $wpsr_case_numbers) . "]\n";
    }
}
if ($wpsr_source_method_counts !== $wpsr_manifest_method_counts) {
    $wpsr_errors[] = 'source/manifest method counts differ: source '
        . $wpsr_format_counts($wpsr_source_method_counts) . '; manifest '
        . $wpsr_format_counts($wpsr_manifest_method_counts);
}

if ($wpsr_errors) {
    echo "\nFAIL — permission route coverage drift detected:\n";
    foreach (array_unique($wpsr_errors) as $wpsr_error) {
        echo "  - {$wpsr_error}\n";
    }
    exit(1);
}

echo "OK — {$wpsr_source_count}/{$wpsr_source_count} core mutating routes represented exactly once; policies and method census match.\n";
exit(0);
