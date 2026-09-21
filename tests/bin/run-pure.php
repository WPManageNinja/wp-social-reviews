<?php
/**
 * Phase 12 pure-function runner. WordPress supplies production autoloading and
 * sanitizer APIs; cases do not use plugin models, query builders, or fixtures.
 */
require_once __DIR__ . '/../lib/harness.php';
require_once __DIR__ . '/../lib/runner.php';

WpsrTest::bootPure();

$wpsr_filter = WpsrRunner::filter((array) $args, 'Pure');
$wpsr_files = WpsrRunner::files('pure', __DIR__ . '/../pure', (array) $args);

WP_CLI::log('WP Social Ninja pure functions — ' . count($wpsr_files) . ' file(s)'
    . ($wpsr_filter !== null ? ' (filtered)' : '') . "\n");

foreach ($wpsr_files as $wpsr_file) {
    $wpsr_cases_before = WpsrTest::$passed + WpsrTest::$skipped + WpsrTest::$failedCases;

    try {
        $wpsr_suite = require $wpsr_file;
        if (!is_callable($wpsr_suite)) {
            throw new RuntimeException(basename($wpsr_file) . ' must return a callable.');
        }
        $wpsr_suite();
    } catch (Throwable $wpsr_exception) {
        WpsrTest::case('pure file loads: ' . basename($wpsr_file), function () use ($wpsr_exception) {
            throw $wpsr_exception;
        });
    }

    $wpsr_cases_after = WpsrTest::$passed + WpsrTest::$skipped + WpsrTest::$failedCases;
    if ($wpsr_cases_after === $wpsr_cases_before) {
        WpsrTest::case('pure file registers tests: ' . basename($wpsr_file), function () {
            WpsrTest::fail('Pure file registered zero test cases.');
        });
    }
}

$wpsr_total = WpsrTest::$passed + WpsrTest::$skipped + WpsrTest::$failedCases;
if (!$wpsr_total) {
    WpsrTest::case('pure runner executes tests', function () {
        WpsrTest::fail('Pure layer executed zero tests.');
    });
}

WpsrTest::finish('PURE');
