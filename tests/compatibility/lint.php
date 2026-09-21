<?php
/** Fast Phase 31 authoritative compatibility matrix drift lint. */
require_once __DIR__ . '/matrix-lib.php';

$wpsr_root = dirname(__DIR__, 2);
$wpsr_matrix_path = __DIR__ . '/matrix.json';
foreach (array_slice($argv, 1) as $wpsr_argument) {
    if (strpos($wpsr_argument, '--matrix=') === 0) {
        $wpsr_matrix_path = substr($wpsr_argument, 9);
    } else {
        fwrite(STDERR, 'COMPATIBILITY-LINT misuse: ' . $wpsr_argument . "\n");
        exit(WPSR_COMPATIBILITY_MISUSE);
    }
}
try {
    $wpsr_matrix = wpsr_compatibility_load($wpsr_matrix_path);
    $wpsr_errors = wpsr_compatibility_validate($wpsr_matrix, $wpsr_root);
} catch (Throwable $wpsr_exception) {
    $wpsr_errors = [$wpsr_exception->getMessage()];
    $wpsr_matrix = ['cells' => [], 'artifacts' => [], 'historical_unavailable' => []];
}
foreach ($wpsr_errors as $wpsr_error) {
    fwrite(STDERR, 'COMPATIBILITY-LINT FAIL ' . $wpsr_error . "\n");
}
printf(
    "COMPATIBILITY-LINT cells=%d artifacts=%d dimensions=%d explicit-skips=%d checks=%d failures=%d\n",
    count((array) ($wpsr_matrix['cells'] ?? [])),
    count((array) ($wpsr_matrix['artifacts'] ?? [])),
    count((array) ($wpsr_matrix['dimensions'] ?? [])),
    count((array) ($wpsr_matrix['historical_unavailable'] ?? [])),
    10,
    count($wpsr_errors)
);
exit($wpsr_errors ? WPSR_COMPATIBILITY_FAIL : WPSR_COMPATIBILITY_PASS);
