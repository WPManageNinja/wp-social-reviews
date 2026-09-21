<?php
/**
 * Phase 16 throughput runner. Query counts and statement batch shapes only.
 */
require_once __DIR__ . '/../lib/harness.php';
require_once __DIR__ . '/../lib/factory.php';
require_once __DIR__ . '/../lib/throughput.php';

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
        WP_CLI::warning('Throughput fixture shutdown cleanup failed: ' . $wpsr_exception->getMessage());
    }
};
register_shutdown_function($wpsr_fallback_cleanup);

$wpsr_files = glob(__DIR__ . '/../throughput/*.php');
$wpsr_files = $wpsr_files === false ? [] : $wpsr_files;
sort($wpsr_files, SORT_STRING);
$wpsr_requested = [];
foreach ((array) $args as $wpsr_arg) {
    if (strpos($wpsr_arg, '--filter=') === 0) {
        $wpsr_filter_value = substr($wpsr_arg, 9);
    } elseif (strpos($wpsr_arg, 'filter=') === 0) {
        $wpsr_filter_value = substr($wpsr_arg, 7);
    } else {
        continue;
    }
    $wpsr_requested = array_values(array_filter(array_map('trim', explode(',', $wpsr_filter_value))));
}

if ($wpsr_requested) {
    $wpsr_normalized = array_map(function ($wpsr_name) {
        $wpsr_name = basename($wpsr_name);
        return substr($wpsr_name, -4) === '.php' ? $wpsr_name : $wpsr_name . '.php';
    }, $wpsr_requested);
    $wpsr_files = array_values(array_filter($wpsr_files, function ($wpsr_file) use ($wpsr_normalized) {
        return in_array(basename($wpsr_file), $wpsr_normalized, true);
    }));
}

if (!$wpsr_files) {
    $wpsr_detail = $wpsr_requested
        ? 'Throughput filter selected zero test files: ' . implode(',', $wpsr_requested)
        : 'Throughput runner discovered zero tests/throughput/*.php files.';
    WP_CLI::error($wpsr_detail . ' Suite wiring is broken.');
}

WP_CLI::log('WP Social Ninja throughput — ' . count($wpsr_files) . ' file(s)'
    . ($wpsr_requested ? ' (filtered)' : '')
    . ' — marker ' . WpsrFactory::marker() . "\n");

$wpsr_cleanup_failure = null;
try {
    foreach ($wpsr_files as $wpsr_file) {
        $wpsr_cases_before = WpsrTest::$passed + WpsrTest::$skipped + count(WpsrTest::$failures);
        try {
            $wpsr_suite = require $wpsr_file;
            if (!is_callable($wpsr_suite)) {
                throw new RuntimeException(basename($wpsr_file) . ' must return a callable.');
            }
            wp_set_current_user($wpsr_admin_id);
            \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
            $wpsr_suite();
        } catch (Throwable $wpsr_exception) {
            WpsrTest::case('throughput file loads: ' . basename($wpsr_file), function () use ($wpsr_exception) {
                throw $wpsr_exception;
            });
        }

        $wpsr_cases_after = WpsrTest::$passed + WpsrTest::$skipped + count(WpsrTest::$failures);
        if ($wpsr_cases_after === $wpsr_cases_before) {
            WpsrTest::case('throughput file registers tests: ' . basename($wpsr_file), function () {
                WpsrTest::fail('Throughput file registered zero test cases.');
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
    WpsrTest::case('throughput fixture cleanup is exact and idempotent', function () use ($wpsr_cleanup_failure) {
        throw $wpsr_cleanup_failure;
    });
}

$wpsr_total = WpsrTest::$passed + WpsrTest::$skipped + count(WpsrTest::$failures);
if (!$wpsr_total) {
    WpsrTest::case('throughput runner executes tests', function () {
        WpsrTest::fail('Throughput layer executed zero tests.');
    });
}

WpsrTest::finish('THROUGHPUT');
