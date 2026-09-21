<?php
/** Canonical non-recursive host static gates shared by default and family selectors. */
$wpsr_root = dirname(__DIR__, 2);
$wpsr_metadata = json_decode((string) file_get_contents($wpsr_root . '/tests/family/host.json'), true);
$wpsr_suite = null;
foreach ((array) ($wpsr_metadata['suites'] ?? []) as $wpsr_row) {
    if (($wpsr_row['id'] ?? '') === 'static') {
        $wpsr_suite = $wpsr_row;
        break;
    }
}
if (!is_array($wpsr_suite) || ($wpsr_suite['summary'] ?? '') !== 'STATIC' || empty($wpsr_suite['commands'])) {
    fwrite(STDERR, "HOST-STATIC FAIL canonical metadata missing.\n");
    exit(1);
}
$wpsr_pass = 0;
$wpsr_fail = 0;
$wpsr_started = microtime(true);
foreach ($wpsr_suite['commands'] as $wpsr_gate) {
    $wpsr_label = $wpsr_gate['label'] ?? '';
    $wpsr_command = $wpsr_gate['command'] ?? null;
    if (!is_string($wpsr_label) || $wpsr_label === '' || !is_array($wpsr_command) || !$wpsr_command) {
        fwrite(STDERR, "HOST-STATIC FAIL malformed command metadata.\n");
        $wpsr_fail++;
        continue;
    }
    echo "\n== " . $wpsr_label . " ==\n";
    $wpsr_escaped = array_map('escapeshellarg', $wpsr_command);
    passthru('cd ' . escapeshellarg($wpsr_root) . ' && ' . implode(' ', $wpsr_escaped) . ' 2>&1', $wpsr_code);
    if ($wpsr_code === 0) {
        echo 'PASS  ' . $wpsr_label . "\n";
        $wpsr_pass++;
    } else {
        fwrite(STDERR, 'FAIL  ' . $wpsr_label . "\n");
        $wpsr_fail++;
    }
}
$wpsr_total = $wpsr_pass + $wpsr_fail;
$wpsr_result = [
    'suite' => 'STATIC',
    'pass' => $wpsr_pass,
    'fail' => $wpsr_fail,
    'skip' => 0,
    'total' => $wpsr_total,
    'assertion_failures' => $wpsr_fail,
    'runtime' => round(microtime(true) - $wpsr_started, 3),
    'skip_ids' => [],
];
echo 'WPSR-SUITE-JSON ' . json_encode($wpsr_result, JSON_UNESCAPED_SLASHES) . "\n";
exit($wpsr_fail || $wpsr_total < 1 ? 1 : 0);
