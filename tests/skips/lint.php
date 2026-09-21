<?php
/** Strict stable-ID SKIP registry and active-known-failure enforcement. */
require_once __DIR__ . '/skip-lib.php';

$wpsr_root = dirname(__DIR__, 2);
$wpsr_registry_path = __DIR__ . '/registry.json';
$wpsr_events_path = null;
foreach (array_slice($argv, 1) as $wpsr_argument) {
    if (strpos($wpsr_argument, '--registry=') === 0) {
        $wpsr_registry_path = substr($wpsr_argument, 11);
    } elseif (strpos($wpsr_argument, '--events=') === 0) {
        $wpsr_events_path = substr($wpsr_argument, 9);
    } else {
        fwrite(STDERR, 'SKIP-LINT misuse: ' . $wpsr_argument . "\n");
        exit(WPSR_SKIP_MISUSE);
    }
}

try {
    $wpsr_registry = wpsr_skip_load($wpsr_registry_path);
    $wpsr_events = [];
    if ($wpsr_events_path !== null) {
        $wpsr_events = wpsr_skip_load_runtime_events($wpsr_events_path);
    }
    $wpsr_errors = wpsr_skip_validate($wpsr_registry, $wpsr_root, $wpsr_events);
    $wpsr_declarations = wpsr_skip_discover_declarations($wpsr_root);
} catch (Throwable $wpsr_exception) {
    fwrite(STDERR, 'SKIP-LINT FAIL ' . $wpsr_exception->getMessage() . "\n");
    exit(WPSR_SKIP_FAIL);
}
foreach ($wpsr_errors as $wpsr_error) {
    fwrite(STDERR, 'SKIP-LINT FAIL ' . $wpsr_error . "\n");
}
$wpsr_default = count(array_filter($wpsr_registry['skips'], static function ($wpsr_row) {
    return !empty($wpsr_row['default_gate']);
}));
printf(
    "SKIP-LINT registered=%d declared=%d default=%d family=%d runtime-events=%d failures=%d\n",
    count($wpsr_registry['skips']),
    count($wpsr_declarations),
    $wpsr_default,
    count(array_filter($wpsr_registry['skips'], static function ($wpsr_row) {
        return ($wpsr_row['suite'] ?? '') === 'family';
    })),
    count($wpsr_events),
    count($wpsr_errors)
);
exit($wpsr_errors ? WPSR_SKIP_FAIL : WPSR_SKIP_PASS);
