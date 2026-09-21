<?php
/** Phase 29 add-on test-tree registration, link, discovery, and runtime-state validation. */
require_once __DIR__ . '/census-lib.php';
require_once dirname(__DIR__) . '/security/endpoints/discovery.php';

const WPSR_TEST_TREE_PASS = 0;
const WPSR_TEST_TREE_FAIL = 1;
const WPSR_TEST_TREE_MISUSE = 2;

function wpsr_test_tree_plugin(array $wpsr_family, $wpsr_id)
{
    foreach ((array) ($wpsr_family['plugins'] ?? []) as $wpsr_plugin) {
        if (is_array($wpsr_plugin) && ($wpsr_plugin['id'] ?? '') === $wpsr_id) {
            return $wpsr_plugin;
        }
    }
    return null;
}

function wpsr_test_tree_header_version($wpsr_file)
{
    $wpsr_source = is_file($wpsr_file) ? file_get_contents($wpsr_file, false, null, 0, 16384) : '';
    return preg_match('/^[ \t\/*#@]*Version:\s*(.+?)\s*$/mi', (string) $wpsr_source, $wpsr_match)
        ? trim($wpsr_match[1]) : null;
}

function wpsr_test_tree_normalized_list($wpsr_values)
{
    if (!is_array($wpsr_values) || !$wpsr_values) {
        return false;
    }
    $wpsr_sorted = $wpsr_values;
    sort($wpsr_sorted, SORT_STRING);
    if ($wpsr_sorted !== $wpsr_values || count($wpsr_values) !== count(array_unique($wpsr_values, SORT_STRING))) {
        return false;
    }
    foreach ($wpsr_values as $wpsr_value) {
        if (!wpsr_family_is_token($wpsr_value)) {
            return false;
        }
    }
    return true;
}

function wpsr_test_tree_unique_string_list($wpsr_values)
{
    if (!is_array($wpsr_values) || !wpsr_family_is_list($wpsr_values) || !$wpsr_values) {
        return false;
    }
    $wpsr_seen = [];
    foreach ($wpsr_values as $wpsr_value) {
        if (!is_string($wpsr_value) || $wpsr_value === '' || isset($wpsr_seen[$wpsr_value])) {
            return false;
        }
        $wpsr_seen[$wpsr_value] = true;
    }
    return true;
}

function wpsr_test_tree_load_object($wpsr_path, $wpsr_label, array &$wpsr_errors)
{
    if (!is_file($wpsr_path) || !is_readable($wpsr_path)) {
        $wpsr_errors[] = $wpsr_label . ' missing or unreadable: ' . $wpsr_path;
        return [];
    }
    $wpsr_source = file_get_contents($wpsr_path);
    $wpsr_object = is_string($wpsr_source) ? json_decode($wpsr_source) : null;
    $wpsr_value = is_string($wpsr_source) ? json_decode($wpsr_source, true) : null;
    if (!is_object($wpsr_object) || !is_array($wpsr_value) || wpsr_family_is_list($wpsr_value)
        || json_last_error() !== JSON_ERROR_NONE) {
        $wpsr_errors[] = $wpsr_label . ' malformed or root is not an object';
        return [];
    }
    return $wpsr_value;
}

function wpsr_test_tree_owned_file($wpsr_root, $wpsr_relative)
{
    if (!is_string($wpsr_relative) || $wpsr_relative === '' || $wpsr_relative[0] === '/'
        || strpos($wpsr_relative, '\\') !== false || strpos($wpsr_relative, '//') !== false
        || preg_match('#(^|/)\.\.?(/|$)#', $wpsr_relative) || substr($wpsr_relative, -4) !== '.php') {
        return false;
    }
    $wpsr_root_real = realpath($wpsr_root);
    $wpsr_file = $wpsr_root . '/' . $wpsr_relative;
    $wpsr_file_real = realpath($wpsr_file);
    return $wpsr_root_real !== false && $wpsr_file_real !== false && !is_link($wpsr_file)
        && strpos($wpsr_file_real, $wpsr_root_real . DIRECTORY_SEPARATOR) === 0;
}

function wpsr_test_tree_route_module($wpsr_path)
{
    return preg_match('#^/wpsocialreviews/v2/pro/([^/]+)#', (string) $wpsr_path, $wpsr_match)
        ? $wpsr_match[1] : '';
}

function wpsr_test_tree_source_route_rows($wpsr_host_root, $wpsr_plugin_root, $wpsr_plugin_id, array &$wpsr_errors)
{
    $wpsr_discovery = wpsr_endpoint_discover_plugin($wpsr_plugin_id, $wpsr_plugin_root, $wpsr_host_root);
    foreach ((array) ($wpsr_discovery['errors'] ?? []) as $wpsr_error) {
        $wpsr_errors[] = 'Pro route source discovery: ' . $wpsr_error;
    }
    $wpsr_rows = [];
    foreach ((array) ($wpsr_discovery['rows'] ?? []) as $wpsr_row) {
        if (($wpsr_row['surface'] ?? '') !== 'rest') {
            continue;
        }
        $wpsr_rows[] = [
            'id' => $wpsr_row['id'] ?? '',
            'method' => $wpsr_row['method'] ?? '',
            'path' => $wpsr_row['path'] ?? '',
            'callback' => $wpsr_row['callback'] ?? '',
            'policy' => $wpsr_row['policy'] ?? '',
            'module' => wpsr_test_tree_route_module($wpsr_row['path'] ?? ''),
        ];
    }
    usort($wpsr_rows, static function ($wpsr_left, $wpsr_right) {
        return [$wpsr_left['path'], $wpsr_left['method']] <=> [$wpsr_right['path'], $wpsr_right['method']];
    });
    if (!$wpsr_rows) {
        $wpsr_errors[] = 'Pro route source discovery returned zero REST declarations';
    }
    return $wpsr_rows;
}

function wpsr_test_tree_inventory_rows($wpsr_host_root, $wpsr_plugin_id)
{
    $wpsr_path = $wpsr_host_root . '/tests/security/endpoints/inventory.json';
    $wpsr_inventory = is_file($wpsr_path) ? json_decode(file_get_contents($wpsr_path), true) : null;
    $wpsr_rows = is_array($wpsr_inventory) && isset($wpsr_inventory['rows'])
        ? $wpsr_inventory['rows']
        : (is_array($wpsr_inventory) && isset($wpsr_inventory['endpoints']) ? $wpsr_inventory['endpoints'] : $wpsr_inventory);
    $wpsr_expected = [];
    foreach ((array) $wpsr_rows as $wpsr_row) {
        if (($wpsr_row['plugin'] ?? '') !== $wpsr_plugin_id || ($wpsr_row['surface'] ?? '') !== 'rest') {
            continue;
        }
        $wpsr_expected[] = [
            'id' => $wpsr_row['id'] ?? '',
            'method' => $wpsr_row['method'] ?? '',
            'path' => $wpsr_row['path'] ?? '',
            'callback' => $wpsr_row['callback'] ?? '',
            'policy' => $wpsr_row['policy'] ?? '',
            'module' => $wpsr_row['module'] ?? '',
        ];
    }
    usort($wpsr_expected, static function ($wpsr_left, $wpsr_right) {
        return [$wpsr_left['path'], $wpsr_left['method']] <=> [$wpsr_right['path'], $wpsr_right['method']];
    });
    return $wpsr_expected;
}

function wpsr_test_tree_host_harness_errors(array $wpsr_metadata, array $wpsr_host, $wpsr_host_root, $wpsr_owner_tree, $wpsr_skip_vcs)
{
    $wpsr_errors = [];
    $wpsr_harness = $wpsr_metadata['host_harness'] ?? null;
    wpsr_family_exact_keys($wpsr_harness, [
        'repository', 'pull_request', 'branch', 'minimum_commit', 'runner', 'environment',
        'command', 'capabilities', 'suites', 'expected_tests',
    ], 'add-on metadata.host_harness', $wpsr_errors);
    $wpsr_expected_capabilities = ['addon-link', 'addon-runner', 'compatibility', 'family', 'pro-environment'];
    $wpsr_expected_suites = ['boot', 'database', 'public-surfaces', 'routes'];
    $wpsr_expected_command = 'bash tests/lab/scripts/php-tests.sh --env pro tests/bin/run-addon.php plugin=pro';
    if (!is_array($wpsr_harness)
        || ($wpsr_harness['repository'] ?? null) !== ($wpsr_host['vcs']['canonical_repository'] ?? null)
        || ($wpsr_harness['pull_request'] ?? null) !== 325
        || ($wpsr_harness['branch'] ?? null) !== ($wpsr_host['vcs']['branch'] ?? null)
        || ($wpsr_harness['minimum_commit'] ?? null) !== 'd9cef50cef56711b2dd6e7084ab1ca082470e547'
        || ($wpsr_harness['runner'] ?? null) !== 'tests/bin/run-addon.php'
        || ($wpsr_harness['environment'] ?? null) !== 'pro'
        || ($wpsr_harness['command'] ?? null) !== $wpsr_expected_command
        || ($wpsr_harness['capabilities'] ?? null) !== $wpsr_expected_capabilities
        || ($wpsr_harness['suites'] ?? null) !== $wpsr_expected_suites
        || ($wpsr_harness['expected_tests'] ?? null) !== 41) {
        $wpsr_errors[] = 'add-on required Core PR #325 host harness contract mismatch';
    }

    $wpsr_required_paths = [
        'tests/addons/pro',
        'tests/bin/run-addon.php',
        'tests/bin/run-all.sh',
        'tests/bin/run-compatibility-matrix.sh',
        'tests/family/sweep.php',
        'tests/family/test-tree-lib.php',
        'tests/lab/scripts/php-tests.sh',
    ];
    foreach ($wpsr_required_paths as $wpsr_required_path) {
        if (!file_exists($wpsr_host_root . '/' . $wpsr_required_path)) {
            $wpsr_errors[] = 'required Core host harness path missing: ' . $wpsr_required_path;
        }
    }

    $wpsr_readme_path = $wpsr_owner_tree . '/README.md';
    $wpsr_readme = is_file($wpsr_readme_path) ? file_get_contents($wpsr_readme_path) : false;
    $wpsr_readme_snippets = [
        'Core PR #325',
        'development',
        'd9cef50cef56711b2dd6e7084ab1ca082470e547',
        $wpsr_expected_command,
        'filter=boot',
        'filter=database',
        'filter=public-surfaces',
        'filter=routes',
        'PRO-ADDON: 41/41 passed, 0 failed',
    ];
    foreach ($wpsr_readme_snippets as $wpsr_snippet) {
        if (!is_string($wpsr_readme) || strpos($wpsr_readme, $wpsr_snippet) === false) {
            $wpsr_errors[] = 'Pro README host execution contract missing: ' . $wpsr_snippet;
        }
    }

    if (!$wpsr_skip_vcs && is_array($wpsr_harness)) {
        $wpsr_remote = wpsr_family_git_value($wpsr_host_root, ['remote', 'get-url', (string) ($wpsr_host['vcs']['remote'] ?? '')]);
        $wpsr_branch = wpsr_family_git_value($wpsr_host_root, ['branch', '--show-current']);
        $wpsr_head = wpsr_family_git_value($wpsr_host_root, ['rev-parse', 'HEAD']);
        $wpsr_minimum = (string) ($wpsr_harness['minimum_commit'] ?? '');
        if (wpsr_family_canonical_repository($wpsr_remote) !== ($wpsr_harness['repository'] ?? null)
            || $wpsr_branch === null || $wpsr_branch === ''
            || !wpsr_family_head_tracks_branch($wpsr_host_root, (string) ($wpsr_harness['branch'] ?? ''))
            || !preg_match('/^[0-9a-f]{40}$/', (string) $wpsr_head)
            || !preg_match('/^[0-9a-f]{40}$/', $wpsr_minimum)
            || wpsr_family_git_value($wpsr_host_root, ['cat-file', '-e', $wpsr_minimum . '^{commit}']) !== ''
            || wpsr_family_git_value($wpsr_host_root, ['merge-base', '--is-ancestor', $wpsr_minimum, 'HEAD']) !== '') {
            $wpsr_errors[] = 'resolved Core host is not paired PR #325 branch at minimum harness commit';
        }
        foreach ($wpsr_required_paths as $wpsr_required_path) {
            if (wpsr_family_git_value($wpsr_host_root, ['cat-file', '-e', $wpsr_minimum . ':' . $wpsr_required_path]) !== '') {
                $wpsr_errors[] = 'required Core host harness path absent at minimum commit: ' . $wpsr_required_path;
            }
        }
    }
    return $wpsr_errors;
}

function wpsr_test_tree_validate(array $wpsr_family, $wpsr_host_root, array $wpsr_options = [])
{
    $wpsr_errors = [];
    $wpsr_skips = [];
    $wpsr_files = [];
    $wpsr_cases = 0;
    $wpsr_plugin_id = (string) ($wpsr_options['plugin_id'] ?? '');
    $wpsr_plugin = wpsr_test_tree_plugin($wpsr_family, $wpsr_plugin_id);
    $wpsr_host = $wpsr_plugin ? wpsr_test_tree_plugin($wpsr_family, (string) ($wpsr_plugin['required_host'] ?? '')) : null;
    if (!$wpsr_plugin || ($wpsr_plugin['role'] ?? '') === 'host' || !$wpsr_host) {
        return ['errors' => ['add-on/bridge or required-host registry row missing for ' . $wpsr_plugin_id], 'skips' => [], 'files' => [], 'suites' => 0, 'cases' => 0];
    }

    $wpsr_override_used = false;
    $wpsr_plugin_root = isset($wpsr_options['plugin_root'])
        ? wpsr_family_normalize_path($wpsr_options['plugin_root'])
        : wpsr_family_resolve_path((array) $wpsr_plugin['path'], $wpsr_host_root, true, $wpsr_override_used);
    $wpsr_tree = (array) ($wpsr_plugin['test_tree'] ?? []);
    $wpsr_owner_tree = wpsr_family_absolute_path($wpsr_plugin_root, (string) ($wpsr_tree['path'] ?? ''));
    $wpsr_link = isset($wpsr_options['link'])
        ? wpsr_family_normalize_path($wpsr_options['link'])
        : wpsr_family_absolute_path($wpsr_host_root, (string) ($wpsr_tree['host_link'] ?? ''));

    if (!is_dir($wpsr_plugin_root)) {
        if (($wpsr_plugin['runtime']['availability'] ?? '') === 'optional'
            && ($wpsr_plugin['missing_sibling']['policy'] ?? '') === 'skip') {
            $wpsr_skip_id = (string) ($wpsr_plugin['missing_sibling']['skip_id'] ?? '');
            $wpsr_skips[] = 'ADDON-TEST-TREE SKIP id=' . $wpsr_skip_id . ' owner=' . $wpsr_plugin_id
                . ' reason=optional-sibling-missing path=' . $wpsr_plugin_root;
            return ['errors' => [], 'skips' => $wpsr_skips, 'files' => [], 'suites' => 0, 'cases' => 0];
        }
        return ['errors' => ['registered plugin root missing: ' . $wpsr_plugin_root], 'skips' => [], 'files' => [], 'suites' => 0, 'cases' => 0];
    }

    if (($wpsr_tree['status'] ?? '') !== 'linked'
        || ($wpsr_tree['path'] ?? '') !== 'tests'
        || ($wpsr_tree['host_link'] ?? '') !== 'tests/addons/' . $wpsr_plugin_id
        || ($wpsr_tree['metadata'] ?? '') !== 'addon.json') {
        $wpsr_errors[] = $wpsr_plugin_id . ': registry test_tree ownership path/link/metadata mismatch';
    }
    $wpsr_plugin_real = realpath($wpsr_plugin_root);
    $wpsr_owner_real = realpath($wpsr_owner_tree);
    if ($wpsr_plugin_real === false || $wpsr_owner_real === false
        || strpos($wpsr_owner_real, $wpsr_plugin_real . DIRECTORY_SEPARATOR) !== 0) {
        $wpsr_errors[] = 'registered add-on test tree escapes plugin ownership root';
    }
    if (!is_link($wpsr_link)) {
        $wpsr_errors[] = 'host add-on test-tree link missing or not symlink: ' . $wpsr_link;
    }
    $wpsr_link_real = realpath($wpsr_link);
    if ($wpsr_link_real === false || $wpsr_owner_real === false || $wpsr_link_real !== $wpsr_owner_real) {
        $wpsr_errors[] = 'host link target disagrees with registry owner test_tree path';
    }

    $wpsr_metadata_path = $wpsr_owner_tree . '/' . (string) ($wpsr_tree['metadata'] ?? 'addon.json');
    $wpsr_metadata = wpsr_test_tree_load_object($wpsr_metadata_path, 'add-on metadata', $wpsr_errors);
    wpsr_family_exact_keys($wpsr_metadata, [
        'schema', 'host_api', 'host_harness', 'plugin', 'required_host', 'activation_order', 'suites',
        'manifests', 'surfaces', 'modules', 'fixtures', 'cleanup', 'summary',
    ], 'add-on metadata', $wpsr_errors);
    wpsr_family_exact_keys($wpsr_metadata['plugin'] ?? null, ['id', 'slug', 'main_file', 'version'], 'add-on metadata.plugin', $wpsr_errors);
    wpsr_family_exact_keys($wpsr_metadata['required_host'] ?? null, ['id', 'slug', 'version'], 'add-on metadata.required_host', $wpsr_errors);
    wpsr_family_exact_keys($wpsr_metadata['manifests'] ?? null, ['rest', 'rest_declarations'], 'add-on metadata.manifests', $wpsr_errors);
    wpsr_family_exact_keys($wpsr_metadata['fixtures'] ?? null, ['host_factory', 'needs', 'database', 'outbound'], 'add-on metadata.fixtures', $wpsr_errors);
    wpsr_family_exact_keys($wpsr_metadata['cleanup'] ?? null, ['owner', 'api', 'fingerprints', 'finally_required'], 'add-on metadata.cleanup', $wpsr_errors);
    if (($wpsr_metadata['schema'] ?? null) !== 1 || ($wpsr_metadata['host_api'] ?? null) !== 1) {
        $wpsr_errors[] = 'add-on metadata schema/host_api mismatch';
    }
    foreach (['id', 'slug', 'main_file'] as $wpsr_field) {
        if (($wpsr_metadata['plugin'][$wpsr_field] ?? null) !== ($wpsr_plugin[$wpsr_field] ?? null)) {
            $wpsr_errors[] = 'add-on metadata plugin.' . $wpsr_field . ' mismatch';
        }
    }
    $wpsr_source_version = wpsr_test_tree_header_version($wpsr_plugin_root . '/' . (string) $wpsr_plugin['main_file']);
    if (($wpsr_metadata['plugin']['version'] ?? null) !== ($wpsr_plugin['version']['observed'] ?? null)
        || ($wpsr_metadata['plugin']['version'] ?? null) !== $wpsr_source_version) {
        $wpsr_errors[] = 'add-on metadata plugin.version mismatch';
    }
    $wpsr_required_range = null;
    foreach ((array) ($wpsr_plugin['version_compatibility'] ?? []) as $wpsr_constraint) {
        if (($wpsr_constraint['target'] ?? '') === ($wpsr_host['id'] ?? '')) {
            $wpsr_required_range = $wpsr_constraint['range'] ?? null;
        }
    }
    if (($wpsr_metadata['required_host']['id'] ?? null) !== ($wpsr_host['id'] ?? null)
        || ($wpsr_metadata['required_host']['slug'] ?? null) !== ($wpsr_host['slug'] ?? null)
        || ($wpsr_metadata['required_host']['version'] ?? null) !== $wpsr_required_range) {
        $wpsr_errors[] = 'add-on metadata required_host id/slug/version mismatch';
    }
    foreach (wpsr_test_tree_host_harness_errors(
        $wpsr_metadata,
        $wpsr_host,
        $wpsr_host_root,
        $wpsr_owner_tree,
        !empty($wpsr_options['skip_vcs'])
    ) as $wpsr_harness_error) {
        $wpsr_errors[] = $wpsr_harness_error;
    }
    if (($wpsr_metadata['activation_order'] ?? null) !== ($wpsr_plugin['activation_order'] ?? null)) {
        $wpsr_errors[] = 'add-on metadata activation_order mismatch';
    }
    foreach (['surfaces', 'modules'] as $wpsr_list) {
        if (!wpsr_test_tree_normalized_list($wpsr_metadata[$wpsr_list] ?? null)) {
            $wpsr_errors[] = 'add-on metadata ' . $wpsr_list . ' must be nonzero normalized sorted unique tokens';
        }
    }
    if (($wpsr_metadata['surfaces'] ?? null) !== ['ajax', 'hooks', 'rest', 'shortcode']
        || ($wpsr_metadata['modules'] ?? null) !== ['bootstrap', 'custom-sources', 'public-handlers', 'review-forms', 'settings']) {
        $wpsr_errors[] = 'add-on surfaces/modules inventory drifted';
    }
    $wpsr_needs = $wpsr_metadata['fixtures']['needs'] ?? null;
    $wpsr_fingerprints = $wpsr_metadata['cleanup']['fingerprints'] ?? null;
    $wpsr_needs_valid = wpsr_test_tree_unique_string_list($wpsr_needs);
    $wpsr_fingerprints_valid = wpsr_test_tree_unique_string_list($wpsr_fingerprints);
    if (!$wpsr_needs_valid) {
        $wpsr_errors[] = 'add-on fixture needs must be a nonempty unique string list';
    }
    if (!$wpsr_fingerprints_valid) {
        $wpsr_errors[] = 'add-on cleanup fingerprints must be a nonempty unique string list';
    }
    $wpsr_needs_sorted = $wpsr_needs_valid ? $wpsr_needs : [];
    $wpsr_fingerprints_sorted = $wpsr_fingerprints_valid ? $wpsr_fingerprints : [];
    sort($wpsr_needs_sorted, SORT_STRING);
    sort($wpsr_fingerprints_sorted, SORT_STRING);
    if ($wpsr_needs_sorted !== ['postmeta', 'posts', 'roles', 'users']
        || $wpsr_fingerprints_sorted !== ['all-database-tables', 'cron', 'options', 'postmeta', 'posts', 'protected-plugin-tables']) {
        $wpsr_errors[] = 'add-on fixture needs or cleanup fingerprints drifted';
    }
    if (($wpsr_metadata['fixtures']['host_factory'] ?? '') !== 'WpsrFactory'
        || ($wpsr_metadata['fixtures']['database'] ?? '') !== 'real-mariadb'
        || ($wpsr_metadata['fixtures']['outbound'] ?? '') !== 'host-blocked'
        || ($wpsr_metadata['cleanup']['owner'] ?? '') !== 'host'
        || ($wpsr_metadata['cleanup']['api'] ?? '') !== 'WpsrFactory::cleanup'
        || ($wpsr_metadata['cleanup']['finally_required'] ?? null) !== true
        || !is_string($wpsr_metadata['summary'] ?? null)
        || !preg_match('/^[A-Z][A-Z0-9-]+$/', (string) ($wpsr_metadata['summary'] ?? ''))) {
        $wpsr_errors[] = 'add-on fixture/cleanup/summary contract is malformed';
    }

    $wpsr_declared_files = [];
    $wpsr_suite_ids = [];
    $wpsr_suites = $wpsr_metadata['suites'] ?? null;
    if (!is_array($wpsr_suites) || !wpsr_family_is_list($wpsr_suites) || !$wpsr_suites) {
        $wpsr_errors[] = 'add-on suites must be a nonempty JSON array';
        $wpsr_suites = [];
    }
    foreach ($wpsr_suites as $wpsr_suite) {
        if (!wpsr_family_exact_keys($wpsr_suite, ['id', 'runner', 'files'], 'add-on suite', $wpsr_errors)) {
            continue;
        }
        $wpsr_suite_id = $wpsr_suite['id'] ?? '';
        if (!wpsr_family_is_token($wpsr_suite_id) || isset($wpsr_suite_ids[$wpsr_suite_id])) {
            $wpsr_errors[] = 'add-on suite ID invalid or duplicate: ' . (string) $wpsr_suite_id;
            continue;
        }
        $wpsr_suite_ids[$wpsr_suite_id] = true;
        if (($wpsr_suite['runner'] ?? '') !== 'host-wp-cli' || empty($wpsr_suite['files'])
            || !is_array($wpsr_suite['files']) || !wpsr_family_is_list($wpsr_suite['files'])) {
            $wpsr_errors[] = 'add-on suite ' . $wpsr_suite_id . ' must use host-wp-cli with nonzero files array';
            continue;
        }
        foreach ($wpsr_suite['files'] as $wpsr_file) {
            if (!wpsr_test_tree_owned_file($wpsr_owner_tree, $wpsr_file)) {
                $wpsr_errors[] = 'invalid, missing, symlinked, or outside add-on test file in suite ' . $wpsr_suite_id;
                continue;
            }
            if (isset($wpsr_declared_files[$wpsr_file])) {
                $wpsr_errors[] = 'add-on test file registered more than once: ' . $wpsr_file;
            }
            $wpsr_declared_files[$wpsr_file] = $wpsr_suite_id;
        }
    }
    if (!$wpsr_suite_ids) {
        $wpsr_errors[] = 'add-on metadata discovered zero suites';
    }
    $wpsr_expected_suite_files = [
        'boot' => 'cases/boot.php',
        'database' => 'cases/database.php',
        'public-surfaces' => 'cases/public-surfaces.php',
        'routes' => 'cases/routes.php',
    ];
    ksort($wpsr_declared_files, SORT_STRING);
    $wpsr_actual_suite_files = [];
    foreach ($wpsr_declared_files as $wpsr_file => $wpsr_suite_id) {
        $wpsr_actual_suite_files[$wpsr_suite_id] = $wpsr_file;
    }
    ksort($wpsr_actual_suite_files, SORT_STRING);
    if ($wpsr_actual_suite_files !== $wpsr_expected_suite_files) {
        $wpsr_errors[] = 'add-on suite IDs/files disagree with registered Pro suite contract';
    }
    $wpsr_discovered = [];
    foreach (glob($wpsr_owner_tree . '/cases/*.php') ?: [] as $wpsr_file) {
        $wpsr_discovered[] = 'cases/' . basename($wpsr_file);
    }
    sort($wpsr_discovered, SORT_STRING);
    $wpsr_declared = array_keys($wpsr_declared_files);
    sort($wpsr_declared, SORT_STRING);
    if (!$wpsr_discovered || $wpsr_discovered !== $wpsr_declared) {
        $wpsr_errors[] = 'add-on discovered files disagree with metadata; declared=' . json_encode($wpsr_declared)
            . ' discovered=' . json_encode($wpsr_discovered);
    }
    foreach ($wpsr_declared as $wpsr_file) {
        if (!is_file($wpsr_owner_tree . '/' . $wpsr_file)) {
            $wpsr_errors[] = 'registered add-on test file missing: ' . $wpsr_file;
        }
    }
    $wpsr_files = $wpsr_declared;

    if (empty($wpsr_options['skip_vcs'])) {
        $wpsr_resolved_commit = wpsr_family_git_value($wpsr_plugin_root, ['rev-parse', 'HEAD']);
        $wpsr_resolved_branch = wpsr_family_git_value($wpsr_plugin_root, ['branch', '--show-current']);
        $wpsr_test_status = wpsr_family_git_value($wpsr_plugin_root, ['status', '--porcelain=v1', '--', (string) ($wpsr_tree['path'] ?? 'tests')]);
        if (!preg_match('/^[0-9a-f]{40}$/', (string) $wpsr_resolved_commit)
            || $wpsr_resolved_branch !== ($wpsr_plugin['vcs']['branch'] ?? null)) {
            $wpsr_errors[] = 'resolved linked Pro test commit/branch identity unavailable';
        }
        if ($wpsr_test_status === null || $wpsr_test_status !== '') {
            $wpsr_errors[] = 'stale Pro test tree differs from resolved Pro commit';
        }
        $wpsr_commit_paths = array_merge([
            rtrim((string) ($wpsr_tree['path'] ?? 'tests'), '/') . '/README.md',
            rtrim((string) ($wpsr_tree['path'] ?? 'tests'), '/') . '/' . (string) ($wpsr_tree['metadata'] ?? 'addon.json'),
            rtrim((string) ($wpsr_tree['path'] ?? 'tests'), '/') . '/' . (string) ($wpsr_metadata['manifests']['rest'] ?? 'routes.json'),
        ], array_map(static function ($wpsr_file) use ($wpsr_tree) {
            return rtrim((string) ($wpsr_tree['path'] ?? 'tests'), '/') . '/' . $wpsr_file;
        }, $wpsr_declared));
        foreach ($wpsr_commit_paths as $wpsr_commit_path) {
            if (wpsr_family_git_value($wpsr_plugin_root, ['cat-file', '-e', 'HEAD:' . $wpsr_commit_path]) === null) {
                $wpsr_errors[] = 'linked Pro test path absent at resolved Pro commit: ' . $wpsr_commit_path;
            }
        }
    }

    $wpsr_manifest_relative = $wpsr_metadata['manifests']['rest'] ?? '';
    if ($wpsr_manifest_relative !== 'routes.json') {
        $wpsr_errors[] = 'add-on REST manifest path must be routes.json';
    }
    $wpsr_routes_path = $wpsr_owner_tree . '/' . (string) $wpsr_manifest_relative;
    $wpsr_routes = wpsr_test_tree_load_object($wpsr_routes_path, 'Pro REST manifest', $wpsr_errors);
    wpsr_family_exact_keys($wpsr_routes, ['schema', 'declarations'], 'Pro REST manifest', $wpsr_errors);
    $wpsr_declarations = $wpsr_routes['declarations'] ?? null;
    if (($wpsr_routes['schema'] ?? null) !== 1 || !is_array($wpsr_declarations)
        || !wpsr_family_is_list($wpsr_declarations) || !$wpsr_declarations) {
        $wpsr_errors[] = 'Pro REST manifest schema/declarations malformed or zero-discovery';
        $wpsr_declarations = [];
    }
    $wpsr_expected_count = $wpsr_metadata['manifests']['rest_declarations'] ?? null;
    if (!is_int($wpsr_expected_count) || $wpsr_expected_count < 1 || count($wpsr_declarations) !== $wpsr_expected_count) {
        $wpsr_errors[] = $wpsr_plugin_id . ': REST manifest must contain exact nonzero registered declaration count';
    }
    $wpsr_route_ids = [];
    $wpsr_route_keys = [];
    foreach ($wpsr_declarations as $wpsr_index => $wpsr_declaration) {
        if (!wpsr_family_exact_keys($wpsr_declaration, ['id', 'method', 'path', 'callback', 'policy', 'module'], 'Pro REST declaration ' . $wpsr_index, $wpsr_errors)) {
            continue;
        }
        $wpsr_method = $wpsr_declaration['method'] ?? null;
        $wpsr_path = $wpsr_declaration['path'] ?? null;
        $wpsr_id = $wpsr_declaration['id'] ?? null;
        $wpsr_key = is_string($wpsr_method) && is_string($wpsr_path) ? $wpsr_method . ' ' . $wpsr_path : '';
        if (!in_array($wpsr_method, ['DELETE', 'GET', 'PATCH', 'POST', 'PUT'], true)
            || !is_string($wpsr_path) || !preg_match('#^/wpsocialreviews/v2/pro(?:/[a-z0-9-]+)+(?:/\{id\})?(?:/[a-z0-9-]+)?$#', $wpsr_path)
            || !is_string($wpsr_id) || $wpsr_id !== 'pro:rest:' . $wpsr_method . ':' . $wpsr_path
            || isset($wpsr_route_ids[$wpsr_id]) || isset($wpsr_route_keys[$wpsr_key])) {
            $wpsr_errors[] = 'Pro REST declaration identity/method/path invalid or duplicate at index ' . $wpsr_index;
        } else {
            $wpsr_route_ids[$wpsr_id] = true;
            $wpsr_route_keys[$wpsr_key] = true;
        }
        if (!is_string($wpsr_declaration['callback'] ?? null)
            || !preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]+::[A-Za-z_][A-Za-z0-9_]*$/', $wpsr_declaration['callback'])
            || !is_string($wpsr_declaration['policy'] ?? null)
            || !preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]+Policy$/', $wpsr_declaration['policy'])
            || !wpsr_family_is_token($wpsr_declaration['module'] ?? null)
            || $wpsr_declaration['module'] !== wpsr_test_tree_route_module($wpsr_path)) {
            $wpsr_errors[] = 'Pro REST declaration callback/policy/module malformed at index ' . $wpsr_index;
        }
    }
    $wpsr_actual_routes = $wpsr_declarations;
    usort($wpsr_actual_routes, static function ($wpsr_left, $wpsr_right) {
        return [$wpsr_left['path'] ?? '', $wpsr_left['method'] ?? ''] <=> [$wpsr_right['path'] ?? '', $wpsr_right['method'] ?? ''];
    });
    if ($wpsr_declarations !== $wpsr_actual_routes) {
        $wpsr_errors[] = 'Pro REST declarations must be sorted by path/method';
    }
    $wpsr_source_routes = wpsr_test_tree_source_route_rows($wpsr_host_root, $wpsr_plugin_root, $wpsr_plugin_id, $wpsr_errors);
    if ($wpsr_actual_routes !== $wpsr_source_routes) {
        $wpsr_errors[] = 'Pro REST manifest disagrees with direct production route discovery';
    }
    $wpsr_expected_routes = wpsr_test_tree_inventory_rows($wpsr_host_root, $wpsr_plugin_id);
    if ($wpsr_actual_routes !== $wpsr_expected_routes) {
        $wpsr_errors[] = 'Pro REST manifest disagrees with authoritative family endpoint inventory';
    }
    $wpsr_cases = count($wpsr_declarations);

    if (isset($wpsr_options['activation']) && is_array($wpsr_options['activation'])) {
        $wpsr_active = array_values($wpsr_options['activation']);
        $wpsr_core_entry = ($wpsr_host['slug'] ?? '') . '/' . ($wpsr_host['main_file'] ?? '');
        $wpsr_pro_entry = ($wpsr_plugin['slug'] ?? '') . '/' . ($wpsr_plugin['main_file'] ?? '');
        $wpsr_core_index = array_search($wpsr_core_entry, $wpsr_active, true);
        $wpsr_pro_index = array_search($wpsr_pro_entry, $wpsr_active, true);
        if ($wpsr_core_index === false || $wpsr_pro_index === false) {
            $wpsr_errors[] = 'Pro runtime activation missing core or add-on entry';
        } elseif ($wpsr_core_index >= $wpsr_pro_index) {
            $wpsr_errors[] = 'Pro runtime activation order is wrong; core must precede add-on';
        }
    }

    return [
        'errors' => array_values(array_unique($wpsr_errors)),
        'skips' => $wpsr_skips,
        'files' => $wpsr_files,
        'suites' => count($wpsr_suite_ids),
        'cases' => $wpsr_cases,
    ];
}
