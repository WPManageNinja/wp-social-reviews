<?php
/**
 * Phase 2 static lint: every mounted core GET route must map to smoke manifest.
 *
 * Route declarations are loaded against this collector only. WordPress, plugin
 * bootstrap, controllers, policies, and database are never loaded.
 */

class WpsrRouteCoverageRouter
{
    private $wpsr_prefix;
    private $wpsr_state;

    public function __construct($wpsr_state = null, $wpsr_prefix = '')
    {
        $this->wpsr_state = $wpsr_state ?: (object) ['routes' => []];
        $this->wpsr_prefix = trim((string) $wpsr_prefix, '/');
    }

    public function prefix($wpsr_prefix)
    {
        $wpsr_combined = trim($this->wpsr_prefix . '/' . trim((string) $wpsr_prefix, '/'), '/');

        return new self($this->wpsr_state, $wpsr_combined);
    }

    public function withPolicy($wpsr_policy)
    {
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
        $wpsr_path = '/' . ltrim((string) $wpsr_path, '/');
        $wpsr_trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $wpsr_full_path = '/' . trim($this->wpsr_prefix . '/' . trim($wpsr_path, '/'), '/');

        $this->wpsr_state->routes[] = [
            'declaration' => $this->wpsr_prefix . ':' . $wpsr_path,
            'route'       => $wpsr_full_path,
            'line'        => isset($wpsr_trace[0]['line']) ? (int) $wpsr_trace[0]['line'] : 0,
        ];

        return $this;
    }

    public function post($wpsr_path, $wpsr_handler)
    {
        return $this;
    }

    public function put($wpsr_path, $wpsr_handler)
    {
        return $this;
    }

    public function delete($wpsr_path, $wpsr_handler)
    {
        return $this;
    }

    public function patch($wpsr_path, $wpsr_handler)
    {
        return $this;
    }

    public function int($wpsr_identifiers)
    {
        return $this;
    }

    public function routes()
    {
        return $this->wpsr_state->routes;
    }

    public function __call($wpsr_method, $wpsr_arguments)
    {
        throw new RuntimeException('Unsupported route declaration or fluent method: ' . $wpsr_method);
    }
}

function wpsr_route_coverage_pattern($wpsr_source_route)
{
    $wpsr_parts = preg_split('/(\{[^}\/]+\})/', $wpsr_source_route, -1, PREG_SPLIT_DELIM_CAPTURE);
    $wpsr_pattern = '';

    foreach ($wpsr_parts as $wpsr_part) {
        if (preg_match('/^\{[^}\/]+\}$/', $wpsr_part)) {
            $wpsr_pattern .= '(?:' . preg_quote($wpsr_part, '#') . '|[^\/{}]+)';
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

if (!is_file($wpsr_routes_file)) {
    fwrite(STDERR, "route-coverage: source route file missing: {$wpsr_routes_file}\n");
    exit(1);
}

if (!is_file($wpsr_manifest_file)) {
    fwrite(STDERR, "route-coverage: smoke manifest missing: {$wpsr_manifest_file}\n");
    exit(1);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $wpsr_root . '/');
}

$router = new WpsrRouteCoverageRouter();
try {
    require $wpsr_routes_file;
} catch (Throwable $wpsr_exception) {
    fwrite(STDERR, 'route-coverage: failed to read route declarations: ' . $wpsr_exception->getMessage() . "\n");
    exit(1);
}

$wpsr_source_routes = $router->routes();
unset($router);
$wpsr_manifest = require $wpsr_manifest_file;

if (!is_array($wpsr_manifest)) {
    fwrite(STDERR, "route-coverage: smoke manifest must return an array.\n");
    exit(1);
}

if (!$wpsr_source_routes) {
    fwrite(STDERR, "route-coverage: FAIL — discovered zero core GET routes; source discovery is broken.\n");
    exit(1);
}

if (!$wpsr_manifest) {
    fwrite(STDERR, "route-coverage: FAIL — discovered zero smoke cases; manifest discovery is broken.\n");
    exit(1);
}

$wpsr_source_by_declaration = [];
foreach ($wpsr_source_routes as $wpsr_source) {
    $wpsr_declaration = $wpsr_source['declaration'];
    if (isset($wpsr_source_by_declaration[$wpsr_declaration])) {
        $wpsr_errors[] = 'duplicate source declaration cannot be mapped uniquely: ' . $wpsr_declaration;
        continue;
    }
    $wpsr_source_by_declaration[$wpsr_declaration] = $wpsr_source;
}

$wpsr_manifest_by_declaration = [];
foreach ($wpsr_manifest as $wpsr_index => $wpsr_case) {
    $wpsr_case_number = $wpsr_index + 1;
    if (!is_array($wpsr_case)) {
        $wpsr_errors[] = "manifest case {$wpsr_case_number} is not an array";
        continue;
    }

    foreach (['declaration', 'label', 'route'] as $wpsr_required_key) {
        if (!isset($wpsr_case[$wpsr_required_key]) || !is_string($wpsr_case[$wpsr_required_key]) || $wpsr_case[$wpsr_required_key] === '') {
            $wpsr_errors[] = "manifest case {$wpsr_case_number} has empty or missing {$wpsr_required_key}";
        }
    }

    if (empty($wpsr_case['declaration']) || !is_string($wpsr_case['declaration'])) {
        continue;
    }

    $wpsr_manifest_by_declaration[$wpsr_case['declaration']][] = $wpsr_case_number;

    if (isset($wpsr_source_by_declaration[$wpsr_case['declaration']]) && !empty($wpsr_case['route'])) {
        $wpsr_source_route = $wpsr_source_by_declaration[$wpsr_case['declaration']]['route'];
        if (!preg_match(wpsr_route_coverage_pattern($wpsr_source_route), $wpsr_case['route'])) {
            $wpsr_errors[] = "manifest case {$wpsr_case_number} route {$wpsr_case['route']} does not represent {$wpsr_source_route}";
        }
    }
}

$wpsr_missing = array_diff_key($wpsr_source_by_declaration, $wpsr_manifest_by_declaration);
$wpsr_phantom = array_diff_key($wpsr_manifest_by_declaration, $wpsr_source_by_declaration);
$wpsr_source_count = count($wpsr_source_routes);
$wpsr_case_count = count($wpsr_manifest);
$wpsr_mapping_count = count($wpsr_manifest_by_declaration);

echo "route-coverage: discovered {$wpsr_source_count} core GET routes; {$wpsr_case_count} smoke cases; {$wpsr_mapping_count} manifest mappings.\n";

if ($wpsr_missing) {
    $wpsr_errors[] = count($wpsr_missing) . ' source GET route(s) have no manifest representation';
    echo "\nMissing manifest mappings:\n";
    foreach ($wpsr_missing as $wpsr_declaration => $wpsr_source) {
        echo "  - {$wpsr_declaration} [GET {$wpsr_source['route']}] app/Http/Routes/api.php:{$wpsr_source['line']}\n";
    }
}

if ($wpsr_phantom) {
    $wpsr_errors[] = count($wpsr_phantom) . ' stale/phantom manifest mapping(s) have no source GET route';
    echo "\nStale/phantom manifest mappings:\n";
    foreach ($wpsr_phantom as $wpsr_declaration => $wpsr_case_numbers) {
        echo '  - ' . $wpsr_declaration . ' [manifest case(s) ' . implode(', ', $wpsr_case_numbers) . "]\n";
    }
}

if ($wpsr_errors) {
    echo "\nFAIL — route coverage drift detected:\n";
    foreach (array_unique($wpsr_errors) as $wpsr_error) {
        echo "  - {$wpsr_error}\n";
    }
    exit(1);
}

echo "OK — {$wpsr_source_count}/{$wpsr_source_count} core GET routes represented by manifest; no stale mappings.\n";
exit(0);
