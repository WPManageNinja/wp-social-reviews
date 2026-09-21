<?php
/** Phase 9 source-backed composition, prior-case, and alias-handler drift gate. */
class WpsrRoundTripRouteRouter
{
    private $state;
    private $prefix;
    private $policy;

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
        $this->policy = is_string($policy) ? $policy : 'callable';
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

    public function get($path, $handler) { return $this; }
    public function post($path, $handler) { return $this->add('POST', $path, $handler); }
    public function put($path, $handler) { return $this->add('PUT', $path, $handler); }
    public function delete($path, $handler) { return $this->add('DELETE', $path, $handler); }
    public function patch($path, $handler) { return $this->add('PATCH', $path, $handler); }
    public function int($identifiers) { return $this; }
    public function __call($method, $arguments)
    {
        throw new RuntimeException('Unsupported route declaration or fluent method: ' . $method);
    }
    public function routes() { return $this->state->routes; }

    private function add($method, $path, $handler)
    {
        $path = '/' . ltrim((string) $path, '/');
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $this->state->routes[] = [
            'declaration' => $method . ' ' . $this->prefix . ':' . $path,
            'method' => $method,
            'route' => '/' . trim($this->prefix . '/' . trim($path, '/'), '/'),
            'policy' => $this->policy,
            'handler' => $this->normalizeHandler($handler),
            'line' => isset($trace[1]['line']) ? (int) $trace[1]['line'] : 0,
        ];
        return $this;
    }

    private function normalizeHandler($handler)
    {
        if (is_array($handler) && count($handler) === 2) {
            $class = is_object($handler[0]) ? get_class($handler[0]) : (string) $handler[0];
            return ltrim($class, '\\') . '@' . (string) $handler[1];
        }
        if (is_string($handler) && $handler !== '') {
            return ltrim(str_replace('::', '@', $handler), '\\');
        }
        return is_callable($handler) ? 'callable' : '';
    }
}

function wpsr_round_trip_route_collector_fails_closed()
{
    $wpsr_router = new WpsrRoundTripRouteRouter();

    try {
        $wpsr_router->any('/__wpsr_any_probe', ['WpsrRouteProbe', 'handle']);
    } catch (RuntimeException $wpsr_exception) {
        return $wpsr_exception->getMessage() === 'Unsupported route declaration or fluent method: any';
    }

    return false;
}

function wpsr_round_trip_source_without_comments($file)
{
    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $file);
    }
    $clean = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $clean .= is_array($token) ? $token[1] : $token;
    }
    return $clean;
}

function wpsr_round_trip_case_body($file, $testName)
{
    $tokens = token_get_all(file_get_contents($file));
    $count = count($tokens);
    $next = function ($index) use ($tokens, $count) {
        while ($index < $count && is_array($tokens[$index])
            && in_array($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $index++;
        }
        return $index;
    };
    $bodies = [];
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || $tokens[$i][1] !== 'WpsrTest') {
            continue;
        }
        $j = $next($i + 1);
        if (!is_array($tokens[$j] ?? null) || $tokens[$j][0] !== T_DOUBLE_COLON) {
            continue;
        }
        $j = $next($j + 1);
        if (!is_array($tokens[$j] ?? null) || $tokens[$j][1] !== 'case') {
            continue;
        }
        $j = $next($j + 1);
        if (($tokens[$j] ?? null) !== '(') {
            continue;
        }
        $j = $next($j + 1);
        if (!is_array($tokens[$j] ?? null) || $tokens[$j][0] !== T_CONSTANT_ENCAPSED_STRING
            || trim($tokens[$j][1], "'\"") !== $testName) {
            continue;
        }
        while ($j < $count && (!is_array($tokens[$j]) || $tokens[$j][0] !== T_FUNCTION)) {
            $j++;
        }
        while ($j < $count && $tokens[$j] !== '{') {
            $j++;
        }
        if ($j >= $count) {
            continue;
        }
        $depth = 1;
        $body = '';
        for ($j++; $j < $count && $depth > 0; $j++) {
            if ($tokens[$j] === '{') {
                $depth++;
            } elseif ($tokens[$j] === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
            if ($depth > 0 && !(is_array($tokens[$j])
                && in_array($tokens[$j][0], [T_COMMENT, T_DOC_COMMENT], true))) {
                $body .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            }
        }
        $bodies[] = $body;
    }
    return count($bodies) === 1 ? $bodies[0] : null;
}

$wpsr_root = dirname(__DIR__, 2);
$wpsr_inventory = require __DIR__ . '/routes.manifest.php';
$wpsr_cases = require __DIR__ . '/cases.manifest.php';
$wpsr_prior_cases = require __DIR__ . '/prior-cases.manifest.php';
$wpsr_permissions = require dirname(__DIR__) . '/permissions/routes.manifest.php';
$wpsr_runtime_files = require dirname(__DIR__) . '/runtime-files.manifest.php';
$wpsr_errors = [];

if (!wpsr_round_trip_route_collector_fails_closed()) {
    fwrite(STDERR, "round-trip-route-coverage: FAIL — collector accepted unsupported any() declaration.\n");
    exit(1);
}

foreach ([
    'route dispositions' => $wpsr_inventory,
    'Phase 9 runtime cases' => $wpsr_cases,
    'prior behavior cases' => $wpsr_prior_cases,
    'permission routes' => $wpsr_permissions,
] as $wpsr_label => $wpsr_rows) {
    if (!is_array($wpsr_rows) || !$wpsr_rows) {
        fwrite(STDERR, 'round-trip-route-coverage: FAIL — discovered zero ' . $wpsr_label . ".\n");
        exit(1);
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $wpsr_root . '/');
}
$router = new WpsrRoundTripRouteRouter();
try {
    require $wpsr_root . '/app/Http/Routes/api.php';
} catch (Throwable $exception) {
    fwrite(STDERR, 'round-trip-route-coverage: source discovery failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
$wpsr_source_routes = $router->routes();
unset($router);
if (!$wpsr_source_routes) {
    fwrite(STDERR, "round-trip-route-coverage: FAIL — discovered zero source mutating routes.\n");
    exit(1);
}

$wpsr_source_by_declaration = [];
foreach ($wpsr_source_routes as $row) {
    $declaration = $row['declaration'];
    if (isset($wpsr_source_by_declaration[$declaration])) {
        $wpsr_errors[] = 'duplicate source mutating declaration: ' . $declaration;
        continue;
    }
    if ($row['policy'] === '' || $row['handler'] === '') {
        $wpsr_errors[] = 'source route lacks explicit policy/handler: ' . $declaration;
    }
    $wpsr_source_by_declaration[$declaration] = $row;
}

$wpsr_permission_by_declaration = [];
foreach ($wpsr_permissions as $index => $route) {
    $declaration = is_array($route) ? ($route['declaration'] ?? '') : '';
    if (!is_string($declaration) || $declaration === '') {
        $wpsr_errors[] = 'permission manifest row ' . ($index + 1) . ' has no declaration';
        continue;
    }
    if (isset($wpsr_permission_by_declaration[$declaration])) {
        $wpsr_errors[] = 'duplicate permission declaration: ' . $declaration;
    }
    $wpsr_permission_by_declaration[$declaration] = $route;
    if (isset($wpsr_source_by_declaration[$declaration])) {
        $source = $wpsr_source_by_declaration[$declaration];
        if (($route['method'] ?? '') !== $source['method'] || ($route['policy'] ?? '') !== $source['policy']) {
            $wpsr_errors[] = 'permission method/policy drift for ' . $declaration;
        }
    }
}
foreach (array_keys(array_diff_key($wpsr_source_by_declaration, $wpsr_permission_by_declaration)) as $declaration) {
    $wpsr_errors[] = 'source mutating declaration missing permission row: ' . $declaration;
}
foreach (array_keys(array_diff_key($wpsr_permission_by_declaration, $wpsr_source_by_declaration)) as $declaration) {
    $wpsr_errors[] = 'stale permission declaration absent from source: ' . $declaration;
}

$wpsr_prior_references = [];
foreach ($wpsr_prior_cases as $id => $row) {
    if (!is_string($id) || $id === '' || !is_array($row)) {
        $wpsr_errors[] = 'malformed prior behavior registry row';
        continue;
    }
    foreach (['file', 'test', 'routes'] as $key) {
        if (empty($row[$key]) || ($key === 'routes' ? !is_array($row[$key]) : !is_string($row[$key]))) {
            $wpsr_errors[] = $id . ' has malformed prior-case ' . $key;
        }
    }
    $file = $wpsr_root . '/' . ltrim((string) ($row['file'] ?? ''), '/');
    if (!is_file($file)) {
        $wpsr_errors[] = $id . ' prior-case file is missing';
        continue;
    }
    if (!in_array(basename($file), (array) ($wpsr_runtime_files['integration'] ?? []), true)) {
        $wpsr_errors[] = $id . ' prior-case file is not in exact integration runtime census';
    }
    try {
        $source = wpsr_round_trip_source_without_comments($file);
        $caseLiteral = "WpsrTest::case('" . $row['test'] . "'";
        $caseBody = wpsr_round_trip_case_body($file, $row['test']);
        if (substr_count($source, $caseLiteral) !== 1 || $caseBody === null) {
            $wpsr_errors[] = $id . ' prior test case is missing, duplicated, or unparseable: ' . $row['test'];
            continue;
        }
        foreach ($row['routes'] as $route) {
            if (!is_string($route) || $route === '' || strpos($caseBody, "'" . $route . "'") === false) {
                $wpsr_errors[] = $id . ' prior test case no longer executes route ' . (string) $route;
            }
        }
    } catch (Throwable $exception) {
        $wpsr_errors[] = $id . ' prior-case source read failed: ' . $exception->getMessage();
    }
}

$wpsr_allowed = ['round-trip', 'reversible', 'covered-prior', 'alias', 'deferred'];
$wpsr_active_cases = [];
$wpsr_disposition_counts = array_fill_keys($wpsr_allowed, 0);
foreach ($wpsr_inventory as $declaration => $row) {
    if (!is_string($declaration) || $declaration === '' || !is_array($row)) {
        $wpsr_errors[] = 'malformed route inventory row';
        continue;
    }
    foreach (['family', 'disposition', 'case', 'reason'] as $key) {
        if (!array_key_exists($key, $row) || !is_string($row[$key])) {
            $wpsr_errors[] = $declaration . ' has malformed ' . $key;
        }
    }
    $disposition = $row['disposition'] ?? '';
    if (!in_array($disposition, $wpsr_allowed, true)) {
        $wpsr_errors[] = $declaration . ' has unknown disposition ' . $disposition;
        continue;
    }
    $wpsr_disposition_counts[$disposition]++;
    if (trim((string) ($row['reason'] ?? '')) === '') {
        $wpsr_errors[] = $declaration . ' has no classification reason';
    }
    $case = (string) ($row['case'] ?? '');
    if (in_array($disposition, ['round-trip', 'reversible'], true)) {
        if ($case === '' || !isset($wpsr_cases[$case])) {
            $wpsr_errors[] = $declaration . ' references missing runtime case ' . ($case ?: '(empty)');
        } else {
            $wpsr_active_cases[$case] = true;
        }
    } elseif ($disposition === 'covered-prior') {
        if ($case === '' || !isset($wpsr_prior_cases[$case])) {
            $wpsr_errors[] = $declaration . ' references missing prior behavior case ' . ($case ?: '(empty)');
        } else {
            $wpsr_prior_references[$case] = true;
        }
    } elseif ($disposition === 'alias') {
        $canonical = (string) ($row['canonical'] ?? '');
        if ($case === '' || !isset($wpsr_cases[$case])) {
            $wpsr_errors[] = $declaration . ' alias references missing runtime case ' . ($case ?: '(empty)');
        } else {
            $wpsr_active_cases[$case] = true;
        }
        if ($canonical === '' || !isset($wpsr_source_by_declaration[$canonical]) || !isset($wpsr_inventory[$canonical])) {
            $wpsr_errors[] = $declaration . ' alias has missing/stale canonical declaration ' . ($canonical ?: '(empty)');
        } elseif (isset($wpsr_source_by_declaration[$declaration])) {
            $aliasSource = $wpsr_source_by_declaration[$declaration];
            $canonicalSource = $wpsr_source_by_declaration[$canonical];
            if ($aliasSource['handler'] !== $canonicalSource['handler'] || $aliasSource['policy'] !== $canonicalSource['policy']) {
                $wpsr_errors[] = $declaration . ' alias handler/policy differs from ' . $canonical
                    . ': alias=' . $aliasSource['handler'] . '/' . $aliasSource['policy']
                    . ' canonical=' . $canonicalSource['handler'] . '/' . $canonicalSource['policy'];
            }
            if (($wpsr_inventory[$canonical]['case'] ?? '') !== $case
                || !in_array($wpsr_inventory[$canonical]['disposition'] ?? '', ['round-trip', 'reversible'], true)) {
                $wpsr_errors[] = $declaration . ' alias canonical route does not own same active runtime case';
            }
        }
    } elseif ($case !== '') {
        $wpsr_errors[] = $declaration . ' deferred row must not claim runtime case ' . $case;
    }
}

foreach (array_keys(array_diff_key($wpsr_source_by_declaration, $wpsr_inventory)) as $declaration) {
    $wpsr_errors[] = 'mutating declaration has no Phase 9 disposition: ' . $declaration;
}
foreach (array_keys(array_diff_key($wpsr_inventory, $wpsr_source_by_declaration)) as $declaration) {
    $wpsr_errors[] = 'stale Phase 9 disposition has no source declaration: ' . $declaration;
}
foreach (array_keys(array_diff_key($wpsr_cases, $wpsr_active_cases)) as $case) {
    $wpsr_errors[] = 'runtime case has no applicable route mapping: ' . $case;
}
foreach (array_keys(array_diff_key($wpsr_prior_cases, $wpsr_prior_references)) as $case) {
    $wpsr_errors[] = 'prior behavior registry case has no covered-prior route mapping: ' . $case;
}

foreach (['review', 'testimonial', 'category', 'template', 'widget', 'notification'] as $family) {
    $found = false;
    foreach ($wpsr_inventory as $row) {
        if (($row['family'] ?? '') === $family && ($row['disposition'] ?? '') === 'round-trip') {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $wpsr_errors[] = 'material CRUD family silently disappeared: ' . $family;
    }
}

printf("round-trip-route-coverage: %d source mutating declarations; %d dispositions; %d runtime cases; %d prior cases.\n",
    count($wpsr_source_by_declaration), count($wpsr_inventory), count($wpsr_cases), count($wpsr_prior_cases));
echo 'dispositions: ' . implode(', ', array_map(function ($name) use ($wpsr_disposition_counts) {
    return $name . ' ' . $wpsr_disposition_counts[$name];
}, $wpsr_allowed)) . "\n";
if ($wpsr_errors) {
    echo "FAIL — Phase 9 mutating-route composition drift:\n";
    foreach (array_unique($wpsr_errors) as $error) {
        echo '  - ' . $error . "\n";
    }
    exit(1);
}
echo "OK — every source mutation classified; active/prior cases exist; aliases retain canonical handler and policy.\n";
