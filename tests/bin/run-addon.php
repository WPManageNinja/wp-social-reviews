<?php
/** Phase 29 host-owned runner for registered add-on callable test files. */
require_once __DIR__ . '/../family/test-tree-lib.php';
require_once __DIR__ . '/../lib/harness.php';
require_once __DIR__ . '/../lib/factory.php';

$wpsr_plugin_id = '';
$wpsr_filters = [];
$wpsr_tree_override = null;
foreach ((array) $args as $wpsr_argument) {
    if (strpos($wpsr_argument, 'plugin=') === 0) {
        $wpsr_plugin_id = substr($wpsr_argument, 7);
    } elseif (strpos($wpsr_argument, 'filter=') === 0) {
        $wpsr_filters = array_values(array_filter(array_map('trim', explode(',', substr($wpsr_argument, 7)))));
    } elseif (strpos($wpsr_argument, 'tree=') === 0) {
        $wpsr_tree_override = substr($wpsr_argument, 5);
    } else {
        WP_CLI::error('ADDON-RUNNER misuse: ' . $wpsr_argument);
    }
}
if ($wpsr_plugin_id === '') {
    WP_CLI::error('ADDON-RUNNER requires plugin=<registered-id>.');
}
$wpsr_host_root = dirname(__DIR__, 2);
$wpsr_family = wpsr_family_load_json($wpsr_host_root . '/tests/family/plugins.json');
$wpsr_plugin = wpsr_test_tree_plugin($wpsr_family, $wpsr_plugin_id);
if (!$wpsr_plugin || ($wpsr_plugin['role'] ?? '') === 'host' || ($wpsr_plugin['test_tree']['status'] ?? '') !== 'linked') {
    WP_CLI::error('ADDON-RUNNER unknown or unlinked add-on/bridge plugin: ' . $wpsr_plugin_id);
}
$wpsr_required_environment = (string) ($wpsr_plugin['test_tree']['execution']['environment'] ?? '');
if ($wpsr_required_environment === '' || (string) getenv('WPSR_TEST_ENV') !== $wpsr_required_environment) {
    WP_CLI::error('ADDON-RUNNER plugin ' . $wpsr_plugin_id . ' requires WPSR_TEST_ENV=' . $wpsr_required_environment . '.');
}
// Container mounts do not expose a usable host Git worktree; host static lint owns VCS/ref checks.
$wpsr_tree_contract = wpsr_test_tree_validate($wpsr_family, $wpsr_host_root, [
    'plugin_id' => $wpsr_plugin_id,
    'skip_vcs' => true,
]);
if ($wpsr_tree_contract['errors'] || $wpsr_tree_contract['skips']) {
    WP_CLI::error('ADDON-RUNNER linked test-tree contract failed: '
        . implode('; ', array_merge($wpsr_tree_contract['errors'], $wpsr_tree_contract['skips'])));
}
$wpsr_tree = $wpsr_host_root . '/' . (string) ($wpsr_plugin['test_tree']['host_link'] ?? '');
if ($wpsr_tree_override !== null) {
    $wpsr_real_override = realpath($wpsr_tree_override);
    $wpsr_allowed_roots = array_values(array_filter([
        realpath($wpsr_host_root . '/tests/.artifacts'),
        realpath('/workspace/wp-social-ninja/tests/.artifacts'),
    ]));
    $wpsr_allowed = false;
    foreach ($wpsr_allowed_roots as $wpsr_allowed_root) {
        if ($wpsr_real_override && strpos($wpsr_real_override, $wpsr_allowed_root . DIRECTORY_SEPARATOR) === 0) {
            $wpsr_allowed = true;
        }
    }
    if (getenv('WPSR_PHASE29_PROOF') !== '1' || !$wpsr_real_override || !$wpsr_allowed) {
        WP_CLI::error('ADDON-RUNNER tree override is restricted to Phase 29 derived proof artifacts.');
    }
    $wpsr_tree = $wpsr_real_override;
}

$wpsr_metadata_path = $wpsr_tree . '/addon.json';
$wpsr_metadata = is_file($wpsr_metadata_path) ? json_decode(file_get_contents($wpsr_metadata_path), true) : null;
if (!is_array($wpsr_metadata) || ($wpsr_metadata['plugin']['id'] ?? '') !== $wpsr_plugin_id) {
    WP_CLI::error('ADDON-RUNNER metadata missing or plugin ID mismatch.');
}
$wpsr_suite_files = [];
$wpsr_known_filters = [];
foreach ((array) ($wpsr_metadata['suites'] ?? []) as $wpsr_suite) {
    $wpsr_suite_id = (string) ($wpsr_suite['id'] ?? '');
    if ($wpsr_suite_id === '') {
        continue;
    }
    $wpsr_known_filters[] = $wpsr_suite_id;
    foreach ((array) ($wpsr_suite['files'] ?? []) as $wpsr_file) {
        $wpsr_name = basename($wpsr_file, '.php');
        $wpsr_known_filters[] = $wpsr_name;
        if (!$wpsr_filters || in_array($wpsr_suite_id, $wpsr_filters, true) || in_array($wpsr_name, $wpsr_filters, true)) {
            $wpsr_suite_files[] = $wpsr_tree . '/' . $wpsr_file;
        }
    }
}
$wpsr_suite_files = array_values(array_unique($wpsr_suite_files));
sort($wpsr_suite_files, SORT_STRING);
if ($wpsr_filters) {
    foreach ($wpsr_filters as $wpsr_filter) {
        if (!in_array($wpsr_filter, $wpsr_known_filters, true)) {
            WP_CLI::error('ADDON-RUNNER unknown filter: ' . $wpsr_filter);
        }
    }
}
if (!$wpsr_suite_files) {
    WP_CLI::error('ADDON-RUNNER selected zero registered test files.');
}

WpsrTest::boot();
$wpsr_admin_id = get_current_user_id();
$wpsr_cleanup_complete = false;
register_shutdown_function(function () use ($wpsr_admin_id, &$wpsr_cleanup_complete) {
    if ($wpsr_cleanup_complete) {
        return;
    }
    wp_set_current_user($wpsr_admin_id);
    \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
    try {
        WpsrFactory::cleanup();
        $wpsr_cleanup_complete = true;
    } catch (Throwable $wpsr_exception) {
        WP_CLI::warning('Add-on fixture shutdown cleanup failed: ' . $wpsr_exception->getMessage());
    }
});

WP_CLI::log('WP Social Ninja family add-on — plugin=' . $wpsr_plugin_id . ' files=' . count($wpsr_suite_files)
    . ($wpsr_filters ? ' filter=' . implode(',', $wpsr_filters) : '')
    . ' marker=' . WpsrFactory::marker());
$wpsr_cleanup_failure = null;
try {
    foreach ($wpsr_suite_files as $wpsr_file) {
        if (!is_file($wpsr_file)) {
            WpsrTest::case('registered add-on file exists: ' . basename($wpsr_file), function () use ($wpsr_file) {
                WpsrTest::fail('Registered add-on test file missing: ' . $wpsr_file);
            });
            continue;
        }
        $wpsr_before = WpsrTest::$passed + WpsrTest::$skipped + count(WpsrTest::$failures);
        try {
            $wpsr_callable = require $wpsr_file;
            if (!is_callable($wpsr_callable)) {
                throw new RuntimeException(basename($wpsr_file) . ' must return a callable.');
            }
            wp_set_current_user($wpsr_admin_id);
            \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
            $wpsr_callable();
        } catch (Throwable $wpsr_exception) {
            WpsrTest::case('add-on file loads: ' . basename($wpsr_file), function () use ($wpsr_exception) {
                throw $wpsr_exception;
            });
        }
        $wpsr_after = WpsrTest::$passed + WpsrTest::$skipped + count(WpsrTest::$failures);
        if ($wpsr_after === $wpsr_before) {
            WpsrTest::case('add-on file registers tests: ' . basename($wpsr_file), function () {
                WpsrTest::fail('Registered add-on file executed zero cases.');
            });
        }
    }
} finally {
    wp_set_current_user($wpsr_admin_id);
    \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
    try {
        WpsrFactory::cleanup();
        WpsrFactory::cleanup();
        $wpsr_cleanup_complete = true;
    } catch (Throwable $wpsr_exception) {
        $wpsr_cleanup_failure = $wpsr_exception;
    }
}
if ($wpsr_cleanup_failure) {
    WpsrTest::case('add-on fixture cleanup is exact and idempotent', function () use ($wpsr_cleanup_failure) {
        throw $wpsr_cleanup_failure;
    });
}
$wpsr_total = WpsrTest::$passed + WpsrTest::$skipped + count(WpsrTest::$failures);
if (!$wpsr_total) {
    WpsrTest::case('add-on runner executes nonzero tests', function () {
        WpsrTest::fail('Add-on layer executed zero tests.');
    });
}
$wpsr_expected_tests = $wpsr_metadata['host_harness']['expected_tests'] ?? null;
if (!$wpsr_filters && (!is_int($wpsr_expected_tests) || $wpsr_total !== $wpsr_expected_tests)) {
    WP_CLI::error('ADDON-RUNNER full-suite count mismatch: expected '
        . var_export($wpsr_expected_tests, true) . ', executed ' . $wpsr_total . '.');
}
$wpsr_summary = (string) ($wpsr_metadata['summary'] ?? '');
if ($wpsr_summary === '' || !preg_match('/^[A-Z][A-Z0-9-]+$/', $wpsr_summary)) {
    WP_CLI::error('ADDON-RUNNER metadata summary label missing or malformed.');
}
WpsrTest::finish($wpsr_summary);
