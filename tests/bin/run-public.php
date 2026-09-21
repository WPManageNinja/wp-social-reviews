<?php
/** Phase 18 public/unauthenticated runtime runner. */
require_once __DIR__ . '/../lib/harness.php';
require_once __DIR__ . '/../lib/factory.php';
require_once __DIR__ . '/../lib/runner.php';

WpsrTest::boot();
$wpsr_admin_id = get_current_user_id();
$wpsr_cleanup_complete = false;
$wpsr_runner_reached_finish = false;
$wpsr_fallback_cleanup = function () use ($wpsr_admin_id, &$wpsr_cleanup_complete, &$wpsr_runner_reached_finish) {
    if ($wpsr_cleanup_complete) {
        return;
    }
    wp_set_current_user($wpsr_admin_id);
    \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
    try {
        WpsrFactory::cleanup();
        $wpsr_cleanup_complete = true;
    } catch (Throwable $exception) {
        WP_CLI::warning('Public fixture shutdown cleanup failed: ' . $exception->getMessage());
    }
    if (!$wpsr_runner_reached_finish) {
        fwrite(STDERR, "PUBLIC RUNNER ABORTED BEFORE SUMMARY: unexpected hard termination.\n");
        exit(90);
    }
};
register_shutdown_function($wpsr_fallback_cleanup);

$wpsr_files = WpsrRunner::files('public', __DIR__ . '/../public', (array) $args);

WP_CLI::log('WP Social Ninja public handlers — ' . count($wpsr_files) . ' file(s) — marker '
    . WpsrFactory::marker() . ' — core-alone anonymous requests' . "\n");
$wpsr_cleanup_failure = null;
try {
    foreach ($wpsr_files as $wpsr_file) {
        $before = WpsrTest::$passed + WpsrTest::$skipped + WpsrTest::$failedCases;
        try {
            $suite = require $wpsr_file;
            if (!is_callable($suite)) {
                throw new RuntimeException(basename($wpsr_file) . ' must return callable.');
            }
            wp_set_current_user($wpsr_admin_id);
            $suite();
        } catch (Throwable $exception) {
            WpsrTest::case('public file loads: ' . basename($wpsr_file), function () use ($exception) {
                throw $exception;
            });
        }
        $after = WpsrTest::$passed + WpsrTest::$skipped + WpsrTest::$failedCases;
        if ($after === $before) {
            WpsrTest::case('public file registers tests: ' . basename($wpsr_file), function () {
                WpsrTest::fail('Public file registered zero test cases.');
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
    } catch (Throwable $exception) {
        $wpsr_cleanup_failure = $exception;
    }
}
if ($wpsr_cleanup_failure) {
    WpsrTest::case('public fixture cleanup is exact and idempotent', function () use ($wpsr_cleanup_failure) {
        throw $wpsr_cleanup_failure;
    });
}
if (!(WpsrTest::$passed + WpsrTest::$skipped + WpsrTest::$failedCases)) {
    WpsrTest::case('public runner executes tests', function () {
        WpsrTest::fail('Public layer executed zero tests.');
    });
}
$wpsr_runner_reached_finish = true;
WpsrTest::finish('PUBLIC');
