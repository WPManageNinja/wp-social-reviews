<?php
/** Phase 10 static inventory drift and zero-discovery gate. */

class WpsrValidationRouteRouter
{
    private $prefix;
    private $policy;
    private $state;

    public function __construct($state = null, $prefix = '', $policy = '')
    {
        $this->state = $state ?: (object) ['routes' => []];
        $this->prefix = trim((string) $prefix, '/');
        $this->policy = (string) $policy;
    }

    public function prefix($prefix)
    {
        return new self($this->state, trim($this->prefix . '/' . trim((string) $prefix, '/'), '/'), $this->policy);
    }

    public function withPolicy($policy)
    {
        $this->policy = (string) $policy;
        return $this;
    }

    public function group($callback)
    {
        if (!is_callable($callback)) {
            throw new RuntimeException('Route group callback is not callable.');
        }
        $callback($this);
        return $this;
    }

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
        $path = '/' . ltrim((string) $path, '/');
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $declaration = $method . ' ' . $this->prefix . ':' . $path;
        $this->state->routes[] = [
            'declaration' => $declaration,
            'method' => $method,
            'route' => '/' . trim($this->prefix . '/' . trim($path, '/'), '/'),
            'policy' => $this->policy,
            'line' => isset($trace[1]['line']) ? (int) $trace[1]['line'] : 0,
        ];
        return $this;
    }
}

$wpsr_root = dirname(__DIR__, 2);
$wpsr_errors = [];
$wpsr_allowed_inputs = [
    'none', 'order-sort', 'search-like', 'pagination-bounds', 'ids-object-scope',
    'text-markup', 'urls-emails-colors-styles', 'nested-settings',
    'filenames-uploads', 'action-status-enum',
];
$wpsr_allowed_dispositions = ['phase10', 'prior', 'deferred', 'excluded'];

if (!defined('ABSPATH')) {
    define('ABSPATH', $wpsr_root . '/');
}
$router = new WpsrValidationRouteRouter();
try {
    require $wpsr_root . '/app/Http/Routes/api.php';
} catch (Throwable $exception) {
    fwrite(STDERR, 'validation-route-coverage: route discovery failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
$wpsr_source = $router->routes();
unset($router);
$wpsr_manifest = require __DIR__ . '/routes.manifest.php';
$wpsr_cases = require __DIR__ . '/cases.manifest.php';
$wpsr_non_rest = require __DIR__ . '/non-rest.manifest.php';

if (!$wpsr_source) {
    $wpsr_errors[] = 'discovered zero core REST routes';
}
if (!is_array($wpsr_manifest) || !$wpsr_manifest) {
    $wpsr_errors[] = 'discovered zero validation inventory rows';
}
if (!is_array($wpsr_cases) || !$wpsr_cases) {
    $wpsr_errors[] = 'discovered zero Phase 10 runtime cases';
}
if (!is_array($wpsr_non_rest) || !$wpsr_non_rest) {
    $wpsr_errors[] = 'discovered zero non-REST input surfaces';
}

$wpsr_source_by_key = [];
foreach ($wpsr_source as $row) {
    if (isset($wpsr_source_by_key[$row['declaration']])) {
        $wpsr_errors[] = 'duplicate source route ' . $row['declaration'];
    }
    $wpsr_source_by_key[$row['declaration']] = $row;
}
$wpsr_route_pattern = function ($sourceRoute) {
    $parts = preg_split('/(\{[^}\/]+\})/', $sourceRoute, -1, PREG_SPLIT_DELIM_CAPTURE);
    $pattern = '';
    foreach ($parts as $part) {
        $pattern .= preg_match('/^\{[^}\/]+\}$/', $part)
            ? '(?:\{[^}\/]+\}|[^\/{}]+)'
            : preg_quote($part, '#');
    }
    return '#^' . $pattern . '$#';
};
$wpsr_manifest_by_key = [];
$wpsr_family_counts = array_fill_keys($wpsr_allowed_inputs, 0);
$wpsr_disposition_counts = array_fill_keys($wpsr_allowed_dispositions, 0);
foreach ((array) $wpsr_manifest as $index => $row) {
    $number = $index + 1;
    foreach (['declaration', 'method', 'route', 'disposition', 'reason'] as $required) {
        if (!isset($row[$required]) || !is_string($row[$required]) || $row[$required] === '') {
            $wpsr_errors[] = "inventory row {$number} has empty/missing {$required}";
        }
    }
    if (!isset($row['inputs']) || !is_array($row['inputs']) || !$row['inputs']) {
        $wpsr_errors[] = "inventory row {$number} has zero input families";
    }
    if (!isset($row['coverage']) || !is_array($row['coverage']) || !$row['coverage']) {
        $wpsr_errors[] = "inventory row {$number} has zero coverage/deferment references";
    }
    $key = $row['declaration'] ?? '';
    if ($key === '') {
        continue;
    }
    if (isset($wpsr_manifest_by_key[$key])) {
        $wpsr_errors[] = 'duplicate inventory route ' . $key;
    }
    $wpsr_manifest_by_key[$key] = $row;

    if (isset($wpsr_source_by_key[$key])) {
        $source = $wpsr_source_by_key[$key];
        if (($row['method'] ?? '') !== $source['method']
            || !preg_match($wpsr_route_pattern($source['route']), (string) ($row['route'] ?? ''))) {
            $wpsr_errors[] = 'method/route drift for ' . $key;
        }
    }
    if (!in_array($row['disposition'] ?? '', $wpsr_allowed_dispositions, true)) {
        $wpsr_errors[] = 'unknown disposition for ' . $key;
    } else {
        $wpsr_disposition_counts[$row['disposition']]++;
    }
    foreach ((array) ($row['inputs'] ?? []) as $family) {
        if (!in_array($family, $wpsr_allowed_inputs, true)) {
            $wpsr_errors[] = 'unknown input family ' . $family . ' for ' . $key;
            continue;
        }
        $wpsr_family_counts[$family]++;
    }
    if (($row['disposition'] ?? '') === 'phase10'
        && !array_intersect(array_keys($wpsr_cases), (array) ($row['coverage'] ?? []))) {
        $wpsr_errors[] = 'Phase 10 row references no registered runtime case: ' . $key;
    }
}

$wpsr_missing = array_diff_key($wpsr_source_by_key, $wpsr_manifest_by_key);
$wpsr_stale = array_diff_key($wpsr_manifest_by_key, $wpsr_source_by_key);
foreach ($wpsr_missing as $key => $row) {
    $wpsr_errors[] = 'missing REST inventory row: ' . $key . ' at app/Http/Routes/api.php:' . $row['line'];
}
foreach ($wpsr_stale as $key => $row) {
    $wpsr_errors[] = 'stale REST inventory row: ' . $key;
}

$wpsr_material_families = [
    'order-sort', 'search-like', 'pagination-bounds', 'ids-object-scope', 'text-markup',
    'urls-emails-colors-styles', 'nested-settings', 'action-status-enum',
];
foreach ($wpsr_material_families as $family) {
    if (($wpsr_family_counts[$family] ?? 0) === 0) {
        $wpsr_errors[] = 'material input family has zero REST inventory rows: ' . $family;
    }
}
$wpsr_non_rest_filename_rows = array_filter((array) $wpsr_non_rest, function ($row) {
    return in_array('filenames-uploads', (array) ($row['inputs'] ?? []), true);
});
if (!$wpsr_non_rest_filename_rows) {
    $wpsr_errors[] = 'filename/upload discovery has zero non-REST rows';
}
foreach ((array) $wpsr_non_rest as $index => $row) {
    foreach (['surface', 'handler', 'disposition', 'reason'] as $required) {
        if (!isset($row[$required]) || !is_string($row[$required]) || $row[$required] === '') {
            $wpsr_errors[] = 'non-REST row ' . ($index + 1) . ' has empty/missing ' . $required;
        }
    }
    if (!isset($row['inputs']) || !is_array($row['inputs']) || !$row['inputs']) {
        $wpsr_errors[] = 'non-REST row ' . ($index + 1) . ' has zero input families';
    }
}

$wpsr_format = function (array $counts) {
    $parts = [];
    foreach ($counts as $key => $count) {
        if ($count) {
            $parts[] = $key . ' ' . $count;
        }
    }
    return implode(', ', $parts);
};
echo 'validation-route-coverage: discovered ' . count($wpsr_source) . ' core REST routes; '
    . count((array) $wpsr_manifest) . ' inventory rows; ' . count((array) $wpsr_cases)
    . ' runtime cases; ' . count((array) $wpsr_non_rest) . " non-REST rows.\n";
echo 'dispositions: ' . $wpsr_format($wpsr_disposition_counts) . "\n";
echo 'input families: ' . $wpsr_format($wpsr_family_counts) . "\n";

if ($wpsr_errors) {
    echo "FAIL — Phase 10 inventory drift:\n";
    foreach (array_unique($wpsr_errors) as $error) {
        echo '  - ' . $error . "\n";
    }
    exit(1);
}

echo 'OK — every core REST declaration classified; material families covered or explicitly deferred; zero-discovery guards pass.' . "\n";
