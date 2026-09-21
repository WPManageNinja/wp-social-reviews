<?php
/** Derived-model proof that Phase 25 doctor fails closed without editing live compose. */

$root = dirname(__DIR__, 3);
$doctor = $root . '/tests/security/lab/doctor.php';
$temp = sys_get_temp_dir() . '/wpsr-security-doctor-' . bin2hex(random_bytes(6));
if (!mkdir($temp, 0700, true) && !is_dir($temp)) {
    fwrite(STDERR, "SECURITY-DOCTOR-SELF-TEST temp unavailable.\n");
    exit(2);
}

function wpsr_doctor_self_run($command, $root)
{
    $lines = [];
    exec('cd ' . escapeshellarg($root) . ' && ' . $command . ' 2>&1', $lines, $code);
    return [$code, implode("\n", $lines)];
}

function wpsr_doctor_self_write($temp, $name, array $model)
{
    $path = $temp . '/' . $name . '.json';
    file_put_contents($path, json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return $path;
}

$pass = 0;
$fail = 0;
try {
    $safePath = $temp . '/safe.json';
    list($code, $output) = wpsr_doctor_self_run(
        'php ' . escapeshellarg($doctor) . ' --config-only --write-model=' . escapeshellarg($safePath),
        $root
    );
    $safe = is_file($safePath) ? json_decode((string) file_get_contents($safePath), true) : null;
    $ok = $code === 0 && is_array($safe) && preg_match('/SECURITY-DOCTOR checks=[1-9][0-9]* failures=0 mode=config/', $output);
    echo ($ok ? 'PASS  ' : 'FAIL  ') . "safe live compose config is green — exit {$code}\n";
    $ok ? $pass++ : $fail++;
    if (!is_array($safe)) {
        throw new RuntimeException('could not derive safe model from live compose');
    }

    $cases = [];
    $mutant = $safe;
    $mutant['network_internal'] = false;
    $cases[] = ['external-compose-network', $mutant, 'compose default network blocks external egress'];

    $mutant = $safe;
    $mutant['services']['db']['ports'][] = ['host_ip' => '127.0.0.1', 'target' => 3306, 'published' => '3306'];
    $cases[] = ['db-port-exposure', $mutant, 'DB has no configured host-published port'];

    $mutant = $safe;
    $mutant['services']['wordpress']['ports'][0]['host_ip'] = '0.0.0.0';
    $cases[] = ['wildcard-web-bind', $mutant, 'WordPress publishes only 127.0.0.1:8892'];

    $mutant = $safe;
    foreach ($mutant['services']['wordpress']['mounts'] as &$mount) {
        if (($mount['target'] ?? '') === '/workspace/wp-social-ninja') {
            $mount['read_only'] = false;
        }
    }
    unset($mount);
    $cases[] = ['writable-core-mount', $mutant, 'wordpress workspace core mount is read-only'];

    $mutant = $safe;
    $mutant['services']['php-tests']['mounts'] = array_values(array_filter(
        $mutant['services']['php-tests']['mounts'],
        static function ($mount) {
            return ($mount['target'] ?? '') !== '/var/www/html/wp-content/plugins/wp-social-ninja';
        }
    ));
    $cases[] = ['single-core-mount', $mutant, 'php-tests plugin-path core mount is read-only'];

    $mutant = $safe;
    $mutant['services']['wordpress']['environment']['WORDPRESS_DB_NAME'] = 'production';
    $mutant['services']['php-tests']['environment']['WORDPRESS_DB_NAME'] = 'production';
    $mutant['services']['wordpress']['environment']['WORDPRESS_TABLE_PREFIX'] = 'wp_';
    $mutant['services']['php-tests']['environment']['WORDPRESS_TABLE_PREFIX'] = 'wp_';
    $cases[] = ['unsafe-db-and-prefix', $mutant, ['configured DB names are matching explicit test databases', 'configured prefixes are matching and non-default']];

    $mutant = $safe;
    $mutant['services']['zap']['environment']['WPSR_DAST_TARGET'] = 'https://example.com';
    $mutant['target_probes']['unsafe_refuses'] = false;
    $cases[] = ['non-loopback-scanner-target', $mutant, 'zap target is fixed loopback lab URL'];

    $mutant = $safe;
    $mutant['services']['nuclei']['image'] = 'projectdiscovery/nuclei:latest';
    $cases[] = ['unpinned-scanner-image', $mutant, 'nuclei image is exactly pinned'];

    $mutant = $safe;
    foreach ($mutant['services']['zap']['mounts'] as &$mount) {
        if (($mount['target'] ?? '') === '/scanner') {
            $mount['read_only'] = false;
        }
    }
    unset($mount);
    $cases[] = ['writable-scanner-config', $mutant, 'zap scanner config mount is read-only'];

    $mutant = $safe;
    $mutant['services']['db']['healthcheck'] = false;
    $cases[] = ['missing-healthcheck', $mutant, 'DB and WordPress declare health checks'];

    $mutant = $safe;
    $mutant['tools']['docker'] = false;
    $cases[] = ['missing-tool', $mutant, 'host tool present: docker'];

    $mutant = $safe;
    $mutant['tools']['semgrep_offline'] = false;
    $cases[] = ['unsafe-semgrep-tooling', $mutant, 'Semgrep image is pinned and network/metrics disabled'];

    foreach ($cases as $case) {
        list($name, $model, $messages) = $case;
        $path = wpsr_doctor_self_write($temp, $name, $model);
        list($code, $output) = wpsr_doctor_self_run(
            'php ' . escapeshellarg($doctor) . ' --config-only --model=' . escapeshellarg($path),
            $root
        );
        $diagnosticsMatch = true;
        foreach ((array) $messages as $message) {
            $diagnosticsMatch = $diagnosticsMatch && strpos($output, 'FAIL  ' . $message) !== false;
        }
        $ok = $code === 1 && $diagnosticsMatch && preg_match('/SECURITY-DOCTOR checks=[1-9][0-9]* failures=[1-9][0-9]* mode=config/', $output);
        echo ($ok ? 'PASS  ' : 'FAIL  ') . "doctor rejects {$name} — exit {$code}\n";
        $ok ? $pass++ : $fail++;
    }
} catch (Throwable $throwable) {
    $fail++;
    fwrite(STDERR, 'SECURITY-DOCTOR-SELF-TEST bootstrap failure: ' . $throwable->getMessage() . "\n");
} finally {
    exec('rm -rf ' . escapeshellarg($temp));
}

printf("SECURITY-DOCTOR-SELF-TEST pass=%d fail=%d mutations=12\n", $pass, $fail);
exit($fail ? 1 : ($pass > 0 ? 0 : 2));
