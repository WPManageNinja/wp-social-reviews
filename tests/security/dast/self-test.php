<?php
/** Proof-of-catch for DAST target/result policy; no scanner or network access. */

$root = dirname(__DIR__, 3);
$policy = $root . '/tests/security/dast/policy.php';
$temp = sys_get_temp_dir() . '/wpsr-dast-policy-' . bin2hex(random_bytes(6));
mkdir($temp, 0700, true);
$pass = 0;
$fail = 0;

function wpsr_dast_self_run($scanner, $input, $expected, $diagnostic, $message, $temp, $policy, $root)
{
    global $pass, $fail;
    $source = $temp . '/' . $message . ($scanner === 'zap' ? '.json' : '.jsonl');
    $output = $temp . '/' . $message . '-policy.json';
    file_put_contents($source, $input);
    $lines = [];
    exec('cd ' . escapeshellarg($root) . ' && php ' . escapeshellarg($policy) . ' '
        . escapeshellarg($scanner) . ' ' . escapeshellarg($source) . ' ' . escapeshellarg($output) . ' 2>&1', $lines, $code);
    $combined = implode("\n", $lines);
    $ok = $code === $expected && is_file($output) && strpos($combined, $diagnostic) !== false;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $message . " — exit {$code}\n";
    $ok ? $pass++ : $fail++;
}

$zap = static function ($site, $id, $risk, $instance = null) {
    return json_encode(['site' => [[
        '@name' => $site,
        'alerts' => [[
            'pluginid' => $id,
            'riskdesc' => $risk . ' (High)',
            'alert' => 'fixture',
            'instances' => [['uri' => $instance ?: $site]],
        ]],
    ]]], JSON_UNESCAPED_SLASHES) . "\n";
};
$nuclei = static function ($target, $id, $severity) {
    return json_encode([
        'template-id' => $id,
        'matched-at' => $target,
        'info' => ['name' => 'fixture', 'severity' => $severity],
    ], JSON_UNESCAPED_SLASHES) . "\n";
};

try {
    wpsr_dast_self_run('zap', $zap('http://127.0.0.1', '10020', 'Low'), 0, 'failures=0', 'allowlisted ZAP baseline passes', $temp, $policy, $root);
    wpsr_dast_self_run('zap', $zap('http://127.0.0.1', '99999', 'High'), 1, 'HIGH 99999 finding is not permitted', 'unexplained ZAP high fails', $temp, $policy, $root);
    wpsr_dast_self_run('zap', $zap('https://example.com', '10020', 'Low'), 1, 'ZAP reported off-target site', 'off-target ZAP result fails', $temp, $policy, $root);
    wpsr_dast_self_run('zap', $zap('http://127.0.0.1', '10020', 'Low', 'https://example.com/leak'), 1, 'ZAP reported off-target finding instance', 'off-target ZAP instance fails', $temp, $policy, $root);
    wpsr_dast_self_run('zap', $zap('http://127.0.0.1', '99998', 'Medium'), 1, 'unallowlisted MEDIUM finding requires review', 'unallowlisted ZAP medium fails', $temp, $policy, $root);
    wpsr_dast_self_run('nuclei', $nuclei('http://127.0.0.1:80/readme.html', 'wpsr-lab-wordpress-readme', 'low'), 0, 'failures=0', 'allowlisted nuclei baseline passes', $temp, $policy, $root);
    wpsr_dast_self_run('nuclei', $nuclei('http://127.0.0.1:80/leak', 'fixture-critical', 'critical'), 1, 'CRITICAL fixture-critical finding is not permitted', 'nuclei critical fails', $temp, $policy, $root);
    wpsr_dast_self_run('nuclei', '', 1, 'scanner result artifact missing or empty', 'zero nuclei result fails', $temp, $policy, $root);
} finally {
    exec('rm -rf ' . escapeshellarg($temp));
}

printf("DAST-POLICY-SELF-TEST pass=%d fail=%d\n", $pass, $fail);
exit($fail ? 1 : ($pass > 0 ? 0 : 2));
