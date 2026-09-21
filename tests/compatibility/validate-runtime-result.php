<?php
/** Fail-closed compatibility transcript gate and canonical per-cell artifact writer. */
require_once __DIR__ . '/runtime-result-lib.php';

$wpsr_options = [];
foreach (array_slice($argv, 1) as $wpsr_argument) {
    if (!preg_match('/^--([a-z0-9-]+)=(.*)$/s', $wpsr_argument, $wpsr_match)) {
        fwrite(STDERR, 'COMPATIBILITY-RESULT misuse: ' . $wpsr_argument . "\n");
        exit(WPSR_COMPATIBILITY_MISUSE);
    }
    $wpsr_options[$wpsr_match[1]] = $wpsr_match[2];
}
foreach (['log', 'cell-base64', 'child-exit', 'runtime-seconds', 'core-fixture-hash', 'pro-fixture-hash', 'output'] as $wpsr_required) {
    if (!array_key_exists($wpsr_required, $wpsr_options)) {
        fwrite(STDERR, 'COMPATIBILITY-RESULT misuse: missing --' . $wpsr_required . "\n");
        exit(WPSR_COMPATIBILITY_MISUSE);
    }
}
$wpsr_cell_source = base64_decode($wpsr_options['cell-base64'], true);
$wpsr_cell_object = is_string($wpsr_cell_source) ? json_decode($wpsr_cell_source) : null;
$wpsr_cell = is_string($wpsr_cell_source) ? json_decode($wpsr_cell_source, true) : null;
if (!is_object($wpsr_cell_object) || !is_array($wpsr_cell) || json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "COMPATIBILITY-RESULT FAIL expected cell payload malformed.\n");
    exit(WPSR_COMPATIBILITY_FAIL);
}
$wpsr_log = is_file($wpsr_options['log']) ? file_get_contents($wpsr_options['log']) : false;
$wpsr_parsed = wpsr_compatibility_parse_runtime_transcript($wpsr_log);
$wpsr_child_exit = filter_var($wpsr_options['child-exit'], FILTER_VALIDATE_INT);
$wpsr_runtime_seconds = filter_var($wpsr_options['runtime-seconds'], FILTER_VALIDATE_FLOAT);
$wpsr_errors = $wpsr_parsed['errors'];
if ($wpsr_child_exit === false || $wpsr_child_exit < 0 || $wpsr_runtime_seconds === false || $wpsr_runtime_seconds < 0) {
    $wpsr_errors[] = 'parent child-exit/runtime metadata malformed';
}
if (!$wpsr_errors) {
    $wpsr_errors = wpsr_compatibility_validate_runtime_event($wpsr_parsed['event'], $wpsr_cell, (int) $wpsr_child_exit);
}
$wpsr_pro_hash = $wpsr_options['pro-fixture-hash'] === '' ? null : $wpsr_options['pro-fixture-hash'];
$wpsr_result = [
    'schema' => 1,
    'cell' => $wpsr_cell['id'] ?? null,
    'status' => 'PASS',
    'child_exit' => (int) $wpsr_child_exit,
    'runtime_seconds' => (float) $wpsr_runtime_seconds,
    'fixture_hashes' => ['core' => $wpsr_options['core-fixture-hash'], 'pro' => $wpsr_pro_hash],
    'runtime' => $wpsr_parsed['event'],
    'log' => $wpsr_options['log'],
];
if (!$wpsr_errors) {
    $wpsr_errors = wpsr_compatibility_validate_result_artifact($wpsr_result, $wpsr_cell);
}
if ($wpsr_errors) {
    foreach (array_values(array_unique($wpsr_errors)) as $wpsr_error) {
        fwrite(STDERR, 'COMPATIBILITY-RESULT FAIL ' . $wpsr_error . "\n");
    }
    exit(WPSR_COMPATIBILITY_FAIL);
}
$wpsr_directory = dirname($wpsr_options['output']);
if ((!is_dir($wpsr_directory) && !mkdir($wpsr_directory, 0777, true))
    || file_put_contents($wpsr_options['output'], json_encode($wpsr_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false) {
    fwrite(STDERR, "COMPATIBILITY-RESULT FAIL artifact write failed.\n");
    exit(WPSR_COMPATIBILITY_FAIL);
}
echo 'COMPATIBILITY-RESULT PASS cell=' . $wpsr_result['cell'] . ' artifact=' . $wpsr_options['output'] . "\n";
