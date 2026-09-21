<?php
/** Core-only public-surface, mounted-handler, and runtime-test drift gate. */
class WpsrPublicRouteCollector
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
    public function prefix($prefix) { return new self($this->state, trim($this->prefix . '/' . trim((string) $prefix, '/'), '/'), $this->policy); }
    public function withPolicy($policy) { $this->policy = (string) $policy; return $this; }
    public function group($callback) { if (!is_callable($callback)) { throw new RuntimeException('Route group callback is not callable.'); } $callback($this); return $this; }
    private function addRoute($method, $path) { $this->state->routes[] = ['method' => strtoupper($method), 'route' => '/' . trim($this->prefix . '/' . trim((string) $path, '/'), '/'), 'policy' => $this->policy]; return $this; }
    public function get($path, $handler) { return $this->addRoute('GET', $path); }
    public function post($path, $handler) { return $this->addRoute('POST', $path); }
    public function put($path, $handler) { return $this->addRoute('PUT', $path); }
    public function delete($path, $handler) { return $this->addRoute('DELETE', $path); }
    public function patch($path, $handler) { return $this->addRoute('PATCH', $path); }
    public function int($identifiers) { return $this; }
    public function routes() { return $this->state->routes; }
    public function __call($method, $arguments)
    {
        throw new RuntimeException('Unsupported route declaration or fluent method: ' . $method);
    }
}

function wpsr_public_php_without_comments($file)
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

function wpsr_public_manifest_handler($handler)
{
    if (preg_match('#([^/]+)\.php::([A-Za-z0-9_]+)$#', (string) $handler, $match)) {
        return $match[1] . '::' . $match[2];
    }
    return '';
}

function wpsr_public_scan_php($root)
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files, SORT_STRING);

    $ajax = [];
    $shortcodes = [];
    $redirects = [];
    $rewriteHits = [];
    foreach ($files as $file) {
        $source = wpsr_public_php_without_comments($file);
        if (preg_match_all('/addAction\s*\(\s*[\'\"]wp_ajax_nopriv_([A-Za-z0-9_\-]+)[\'\"]\s*,\s*[\'\"]([A-Za-z0-9_\\\\]+)@([A-Za-z0-9_]+)[\'\"]/', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $class = preg_replace('/^.*\\\\/', '', $match[2]);
                $ajax[$match[1]][] = $class . '::' . $match[3];
            }
        }
        if (preg_match_all('/add_action\s*\(\s*[\'\"]wp_ajax_nopriv_([A-Za-z0-9_\-]+)[\'\"]\s*,\s*array\s*\(\s*\$this\s*,\s*[\'\"]([A-Za-z0-9_]+)[\'\"]/', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $ajax[$match[1]][] = basename($file, '.php') . '::' . $match[2];
            }
        }
        if (preg_match_all('/add_shortcode\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*,\s*array\s*\(\s*\$this\s*,\s*[\'\"]([A-Za-z0-9_]+)[\'\"]/', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $shortcodes[$match[1]][] = basename($file, '.php') . '::' . $match[2];
            }
        }
        if (preg_match_all('/add_action\s*\(\s*[\'\"]template_redirect[\'\"]\s*,\s*array\s*\(\s*\$this\s*,\s*[\'\"]([A-Za-z0-9_]+)[\'\"]/', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $redirects[] = basename($file, '.php') . '::' . $match[1];
            }
        }
        if (preg_match('/add_rewrite_(?:rule|tag)|add_filter\s*\(\s*[\'\"]query_vars[\'\"]|parse_request/', $source)) {
            $rewriteHits[] = str_replace($root . '/', '', $file);
        }
    }
    foreach ($ajax as &$handlers) {
        sort($handlers, SORT_STRING);
    }
    unset($handlers);
    foreach ($shortcodes as &$handlers) {
        sort($handlers, SORT_STRING);
    }
    unset($handlers);
    ksort($ajax, SORT_STRING);
    ksort($shortcodes, SORT_STRING);
    sort($redirects, SORT_STRING);
    sort($rewriteHits, SORT_STRING);
    return compact('ajax', 'shortcodes', 'redirects', 'rewriteHits');
}

$root = dirname(__DIR__, 2);
$manifestFile = __DIR__ . '/handlers.manifest.php';
$runtimeFile = __DIR__ . '/public-handlers.php';
$errors = [];
if (!is_file($manifestFile) || !is_file($runtimeFile)) {
    fwrite(STDERR, "public-handler-coverage: manifest/runtime file missing.\n");
    exit(1);
}
$manifest = require $manifestFile;
if (!is_array($manifest)) {
    fwrite(STDERR, "public-handler-coverage: manifest must return array.\n");
    exit(1);
}
foreach (['ajax', 'shortcodes', 'template_redirect', 'public_rest', 'rewrite_query_vars', 'frontend_flows'] as $key) {
    if (!isset($manifest[$key]) || !is_array($manifest[$key])) {
        $errors[] = 'missing array section ' . $key;
    }
}
if (isset($manifest['pro_addon_only'])) {
    $errors[] = 'foundation manifest must not contain sibling/family inventory';
}
if (empty($manifest['ajax']) || empty($manifest['shortcodes']) || empty($manifest['template_redirect'])) {
    $errors[] = 'zero material public entry points; inventory wiring is broken';
}

$scan = wpsr_public_scan_php($root);
$runtimeSource = wpsr_public_php_without_comments($runtimeFile);
$manifestAjax = [];
$manifestShortcodes = [];
$manifestRedirects = [];
$runtimeTests = [];
$validateRow = function ($row, $kind, $index) use (&$errors, &$runtimeTests, $root, $runtimeSource) {
    foreach (['handler', 'guard', 'runtime_test'] as $key) {
        if (empty($row[$key]) || !is_string($row[$key])) {
            $errors[] = $kind . ' row ' . ($index + 1) . ' missing ' . $key;
        }
    }
    $normalized = wpsr_public_manifest_handler($row['handler'] ?? '');
    $file = $root . '/' . strstr((string) ($row['handler'] ?? ''), '::', true);
    $method = strpos((string) ($row['handler'] ?? ''), '::') !== false
        ? substr((string) $row['handler'], strpos((string) $row['handler'], '::') + 2)
        : '';
    if ($normalized === '' || !is_file($file)) {
        $errors[] = $kind . ' row ' . ($index + 1) . ' has stale handler path';
    } elseif (strpos(wpsr_public_php_without_comments($file), 'function ' . $method . '(') === false
        && strpos(wpsr_public_php_without_comments($file), 'function ' . $method . ' (') === false) {
        $errors[] = $kind . ' row ' . ($index + 1) . ' handler method is missing';
    }
    $test = (string) ($row['runtime_test'] ?? '');
    if ($test !== '') {
        $runtimeTests[] = $test;
        if (substr_count($runtimeSource, "WpsrTest::case('" . $test . "'") !== 1) {
            $errors[] = $kind . ' runtime test is missing or duplicated: ' . $test;
        }
    }
    return $normalized;
};

foreach ((array) ($manifest['ajax'] ?? []) as $index => $row) {
    if (empty($row['hook']) || !is_string($row['hook'])) {
        $errors[] = 'ajax row ' . ($index + 1) . ' missing hook';
        continue;
    }
    $manifestAjax[$row['hook']][] = $validateRow($row, 'ajax', $index);
}
foreach ($manifestAjax as &$handlers) {
    sort($handlers, SORT_STRING);
}
unset($handlers);
ksort($manifestAjax, SORT_STRING);
if ($manifestAjax !== $scan['ajax']) {
    $errors[] = 'nopriv AJAX handler drift: source=' . json_encode($scan['ajax']) . ' manifest=' . json_encode($manifestAjax);
}

foreach ((array) ($manifest['shortcodes'] ?? []) as $index => $row) {
    if (empty($row['tag']) || !is_string($row['tag'])) {
        $errors[] = 'shortcode row ' . ($index + 1) . ' missing tag';
        continue;
    }
    $manifestShortcodes[$row['tag']][] = $validateRow($row, 'shortcode', $index);
}
foreach ($manifestShortcodes as &$handlers) {
    sort($handlers, SORT_STRING);
}
unset($handlers);
ksort($manifestShortcodes, SORT_STRING);
if ($manifestShortcodes !== $scan['shortcodes']) {
    $errors[] = 'shortcode handler drift: source=' . json_encode($scan['shortcodes']) . ' manifest=' . json_encode($manifestShortcodes);
}

foreach ((array) ($manifest['template_redirect'] ?? []) as $index => $row) {
    $manifestRedirects[] = $validateRow($row, 'template_redirect', $index);
}
sort($manifestRedirects, SORT_STRING);
if ($manifestRedirects !== $scan['redirects']) {
    $errors[] = 'template_redirect handler drift: source=' . json_encode($scan['redirects']) . ' manifest=' . json_encode($manifestRedirects);
}
if (count($runtimeTests) !== count(array_unique($runtimeTests))) {
    $errors[] = 'runtime_test names must be unique across public entry points';
}
if ($scan['rewriteHits'] !== [] || !empty($manifest['rewrite_query_vars'])) {
    $errors[] = 'core rewrite/query-var classification drift: source=' . json_encode($scan['rewriteHits']);
}

$router = new WpsrPublicRouteCollector();
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}
try {
    require $root . '/app/Http/Routes/api.php';
} catch (Throwable $exception) {
    $errors[] = 'REST route discovery failed: ' . $exception->getMessage();
}
$routes = $router->routes();
$publicRoutes = array_values(array_filter($routes, function ($route) { return $route['policy'] === ''; }));
$smoke = require $root . '/tests/smoke/routes.manifest.php';
$permissions = require $root . '/tests/permissions/routes.manifest.php';
$getDeclarations = [];
foreach ((array) $smoke as $row) {
    $getDeclarations[(string) ($row['declaration'] ?? '')] = true;
}
$expectedRestCount = count(array_filter(array_keys($getDeclarations), 'strlen')) + count((array) $permissions);
if (!$routes || count($routes) !== $expectedRestCount) {
    $errors[] = 'REST discovery/manifests differ: source=' . count($routes) . ' independent=' . $expectedRestCount;
}
if ($publicRoutes !== [] || !empty($manifest['public_rest'])) {
    $errors[] = 'public REST classification drift: ' . json_encode($publicRoutes);
}

foreach ((array) ($manifest['frontend_flows'] ?? []) as $index => $row) {
    foreach (['source', 'entry', 'classification'] as $key) {
        if (empty($row[$key]) || !is_string($row[$key])) {
            $errors[] = 'frontend flow row ' . ($index + 1) . ' missing ' . $key;
        }
    }
    foreach (preg_split('/\s+\+\s+/', (string) ($row['source'] ?? '')) as $sourceFile) {
        if ($sourceFile !== '' && !is_file($root . '/' . $sourceFile)) {
            $errors[] = 'frontend flow source is stale: ' . $sourceFile;
        }
    }
}

printf("public-handler-coverage: core nopriv=%d shortcode=%d template_redirect=%d public_REST=%d rewrite/query-var=%d.\n",
    count($scan['ajax']), count($scan['shortcodes']), count($scan['redirects']), count($publicRoutes), count($scan['rewriteHits']));
if ($errors) {
    fwrite(STDERR, "public-handler-coverage: FAIL\n  - " . implode("\n  - ", array_unique($errors)) . "\n");
    exit(1);
}
echo "OK — every core public registration retains exact handler and active runtime case; REST remains policy-guarded.\n";
