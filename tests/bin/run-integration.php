<?php
/**
 * Phase 4 integration runner. Loads sorted callable test files against Docker WP.
 */
require_once __DIR__ . '/../lib/harness.php';
require_once __DIR__ . '/../lib/factory.php';
require_once __DIR__ . '/../lib/runner.php';

WpsrTest::boot();
$wpsr_admin_id = get_current_user_id();
$wpsr_cleanup_complete = false;

$wpsr_fallback_cleanup = function () use ($wpsr_admin_id, &$wpsr_cleanup_complete) {
    if ($wpsr_cleanup_complete) {
        return;
    }

    wp_set_current_user($wpsr_admin_id);
    \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
    try {
        WpsrFactory::cleanup();
        $wpsr_cleanup_complete = true;
    } catch (Throwable $wpsr_exception) {
        WP_CLI::warning('Integration fixture shutdown cleanup failed: ' . $wpsr_exception->getMessage());
    }
};
register_shutdown_function($wpsr_fallback_cleanup);

$wpsr_filter = WpsrRunner::filter((array) $args, 'Integration');
$wpsr_files = WpsrRunner::files('integration', __DIR__ . '/../integration', (array) $args);

WP_CLI::log('WP Social Ninja integration — ' . count($wpsr_files) . ' file(s)'
    . ($wpsr_filter !== null ? ' (filtered)' : '')
    . ' — marker ' . WpsrFactory::marker()
    . " — persistent cache baseline verified clean\n");

$wpsr_cleanup_failure = null;
try {
    foreach ($wpsr_files as $wpsr_file) {
        $wpsr_cases_before = WpsrTest::$passed + WpsrTest::$skipped + WpsrTest::$failedCases;

        try {
            $wpsr_suite = require $wpsr_file;
            if (!is_callable($wpsr_suite)) {
                throw new RuntimeException(basename($wpsr_file) . ' must return a callable.');
            }

            wp_set_current_user($wpsr_admin_id);
            \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
            $wpsr_suite();
        } catch (Throwable $wpsr_exception) {
            WpsrTest::case('integration file loads: ' . basename($wpsr_file), function () use ($wpsr_exception) {
                throw $wpsr_exception;
            });
        }

        $wpsr_cases_after = WpsrTest::$passed + WpsrTest::$skipped + WpsrTest::$failedCases;
        if ($wpsr_cases_after === $wpsr_cases_before) {
            WpsrTest::case('integration file registers tests: ' . basename($wpsr_file), function () {
                WpsrTest::fail('Integration file registered zero test cases.');
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
    WpsrTest::case('integration fixture cleanup is exact and idempotent', function () use ($wpsr_cleanup_failure) {
        throw $wpsr_cleanup_failure;
    });
}

$wpsr_total = WpsrTest::$passed + WpsrTest::$skipped + WpsrTest::$failedCases;
if (!$wpsr_total) {
    WpsrTest::case('integration runner executes tests', function () {
        WpsrTest::fail('Integration layer executed zero tests.');
    });
}

WpsrTest::finish('INTEGRATION');
