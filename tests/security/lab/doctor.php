<?php
/** Phase 25 Docker security-lab doctor. Validation core also accepts derived self-test models. */

const WPSR_DAST_TARGET = 'http://127.0.0.1:80';

$root = dirname(__DIR__, 3);
$lab = $root . '/tests/lab';
$configOnly = false;
$modelPath = null;
$writeModel = null;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--config-only') {
        $configOnly = true;
    } elseif (strpos($argument, '--model=') === 0) {
        $modelPath = substr($argument, 8);
    } elseif (strpos($argument, '--write-model=') === 0) {
        $writeModel = substr($argument, 14);
    } else {
        fwrite(STDERR, "SECURITY-DOCTOR misuse: {$argument}\n");
        exit(2);
    }
}

function wpsr_doctor_command($command, $cwd, $allowFailure = false)
{
    $lines = [];
    exec('cd ' . escapeshellarg($cwd) . ' && ' . $command . ' 2>&1', $lines, $code);
    if (!$allowFailure && $code !== 0) {
        throw new RuntimeException("command exited {$code}: {$command}\n" . implode("\n", $lines));
    }
    return [$code, trim(implode("\n", $lines))];
}

function wpsr_doctor_compose_command($lab, $arguments)
{
    return 'docker compose --project-name wp-social-ninja-test-lab'
        . ' --project-directory ' . escapeshellarg($lab)
        . ' --file ' . escapeshellarg($lab . '/compose.yaml')
        . ' --profile tools --profile security ' . $arguments;
}

function wpsr_doctor_mounts(array $service)
{
    $mounts = [];
    foreach ((array) ($service['volumes'] ?? []) as $mount) {
        $mounts[] = [
            'source' => (string) ($mount['source'] ?? ''),
            'target' => (string) ($mount['target'] ?? ''),
            'read_only' => !empty($mount['read_only']),
            'type' => (string) ($mount['type'] ?? ''),
        ];
    }
    return $mounts;
}

function wpsr_doctor_build_model($root, $lab, $runtime)
{
    list(, $json) = wpsr_doctor_command(
        wpsr_doctor_compose_command($lab, 'config --format json'),
        $root
    );
    $compose = json_decode($json, true);
    if (!is_array($compose) || empty($compose['services'])) {
        throw new RuntimeException('Docker Compose returned malformed/empty config JSON.');
    }

    $services = $compose['services'];
    $networkInternal = false;
    foreach ((array) ($compose['networks'] ?? []) as $network) {
        if (!empty($network['internal'])) {
            $networkInternal = true;
        }
    }

    $model = [
        'root' => realpath($root) ?: $root,
        'services' => [],
        'network_internal' => $networkInternal,
        'reset_supported' => false,
        'tools' => [],
        'target_probes' => [],
        'runtime' => ['checked' => false],
    ];

    foreach ($services as $name => $service) {
        $model['services'][$name] = [
            'image' => (string) ($service['image'] ?? ''),
            'environment' => (array) ($service['environment'] ?? []),
            'ports' => array_values((array) ($service['ports'] ?? [])),
            'mounts' => wpsr_doctor_mounts($service),
            'healthcheck' => !empty($service['healthcheck']['test']),
            'network_mode' => (string) ($service['network_mode'] ?? ''),
        ];
    }

    $reset = (string) @file_get_contents($lab . '/scripts/reset.sh');
    $model['reset_supported'] = strpos($reset, "--yes") !== false
        && strpos($reset, 'down --volumes --remove-orphans') !== false;
    foreach (['docker', 'curl', 'php'] as $tool) {
        list($code) = wpsr_doctor_command('command -v ' . escapeshellarg($tool), $root, true);
        $model['tools'][$tool] = $code === 0;
    }
    list($composeCode) = wpsr_doctor_command('docker compose version', $root, true);
    $model['tools']['compose'] = $composeCode === 0;
    foreach (['zap', 'nuclei'] as $scanner) {
        $image = $model['services'][$scanner]['image'] ?? '';
        list($code) = wpsr_doctor_command('docker image inspect ' . escapeshellarg($image), $root, true);
        $model['tools'][$scanner . '_image'] = $code === 0;
        $model['tools'][$scanner . '_script'] = is_file($root . '/tests/security/dast/run-' . $scanner . '.sh');
    }
    $semgrepScript = (string) @file_get_contents($root . '/tests/bin/run-semgrep.sh')
        . (string) @file_get_contents($root . '/tests/security/semgrep/test-fixtures.sh');
    $model['tools']['semgrep_script'] = $semgrepScript !== '';
    $model['tools']['semgrep_offline'] = strpos($semgrepScript, 'semgrep/semgrep:1.90.0') !== false
        && substr_count($semgrepScript, '--network none') >= 2
        && strpos($semgrepScript, 'SEMGREP_SEND_METRICS=off') !== false;
    list($semgrepCode) = wpsr_doctor_command('docker image inspect ' . escapeshellarg('semgrep/semgrep:1.90.0'), $root, true);
    $model['tools']['semgrep_image'] = $semgrepCode === 0;
    list($safeCode) = wpsr_doctor_command(
        'php tests/security/dast/validate-target.php ' . escapeshellarg(WPSR_DAST_TARGET),
        $root,
        true
    );
    list($unsafeCode) = wpsr_doctor_command(
        'php tests/security/dast/validate-target.php ' . escapeshellarg('https://example.com'),
        $root,
        true
    );
    $model['target_probes'] = ['safe_accepts' => $safeCode === 0, 'unsafe_refuses' => $unsafeCode !== 0];

    if (!$runtime) {
        return $model;
    }

    $runtimeModel = ['checked' => true, 'containers' => [], 'mounts' => [], 'db_ports' => [], 'web_bindings' => []];
    foreach (['db', 'wordpress'] as $service) {
        list($code, $id) = wpsr_doctor_command(wpsr_doctor_compose_command($lab, 'ps -q ' . $service), $root, true);
        if ($code !== 0 || $id === '') {
            $runtimeModel['containers'][$service] = ['running' => false, 'healthy' => false];
            continue;
        }
        list(, $inspectJson) = wpsr_doctor_command('docker inspect ' . escapeshellarg(trim($id)), $root);
        $inspect = json_decode($inspectJson, true)[0] ?? [];
        $runtimeModel['containers'][$service] = [
            'running' => !empty($inspect['State']['Running']),
            'healthy' => ($inspect['State']['Health']['Status'] ?? '') === 'healthy',
        ];
        if ($service === 'db') {
            $runtimeModel['db_ports'] = array_values(array_filter(
                (array) ($inspect['NetworkSettings']['Ports']['3306/tcp'] ?? []),
                'is_array'
            ));
        } else {
            $runtimeModel['web_bindings'] = array_values((array) ($inspect['NetworkSettings']['Ports']['80/tcp'] ?? []));
            foreach ((array) ($inspect['Mounts'] ?? []) as $mount) {
                $runtimeModel['mounts'][] = [
                    'source' => (string) ($mount['Source'] ?? ''),
                    'target' => (string) ($mount['Destination'] ?? ''),
                    'read_only' => empty($mount['RW']),
                ];
            }
        }
    }

    $webPort = (string) (($model['services']['wordpress']['ports'][0]['published'] ?? ''));
    $runtimeModel['lab_url'] = $webPort !== '' ? 'http://127.0.0.1:' . $webPort : '';
    list($curlCode) = wpsr_doctor_command(
        'curl --fail --silent --show-error --max-time 10 --output /dev/null ' . escapeshellarg($runtimeModel['lab_url']),
        $root,
        true
    );
    $runtimeModel['url_reachable'] = $curlCode === 0;

    $php = <<<'PHP'
require '/var/www/html/wp-load.php';
global $wpdb;
echo json_encode([
    'db' => DB_NAME,
    'prefix' => $wpdb->prefix,
    'core_active' => is_plugin_active('wp-social-ninja/wp-social-reviews.php'),
    'pro_active' => is_plugin_active('wp-social-ninja-pro/wp-social-ninja-pro.php'),
]);
PHP;
    list($wpCode, $wpOutput) = wpsr_doctor_command(
        wpsr_doctor_compose_command($lab, 'exec -T wordpress php -r ' . escapeshellarg($php)),
        $root,
        true
    );
    $runtimeModel['wordpress'] = $wpCode === 0 ? (json_decode($wpOutput, true) ?: []) : [];
    $model['runtime'] = $runtimeModel;
    return $model;
}

function wpsr_doctor_has_mount(array $mounts, $source, $target, $readOnly = true)
{
    foreach ($mounts as $mount) {
        $actualSource = realpath((string) ($mount['source'] ?? '')) ?: (string) ($mount['source'] ?? '');
        if (strpos($actualSource, '/host_mnt/') === 0) {
            $actualSource = substr($actualSource, strlen('/host_mnt'));
        }
        if ($actualSource === $source
            && ($mount['target'] ?? '') === $target
            && (!$readOnly || !empty($mount['read_only']))) {
            return true;
        }
    }
    return false;
}

function wpsr_doctor_has_writable_mount(array $mounts, $target)
{
    foreach ($mounts as $mount) {
        if (($mount['target'] ?? '') === $target && empty($mount['read_only'])) {
            return true;
        }
    }
    return false;
}

function wpsr_doctor_validate(array $model, $configOnly)
{
    $checks = [];
    $check = static function ($name, $ok, $detail = '') use (&$checks) {
        $checks[] = ['name' => $name, 'ok' => (bool) $ok, 'detail' => $detail];
    };
    $services = (array) ($model['services'] ?? []);
    $db = (array) ($services['db'] ?? []);
    $wordpress = (array) ($services['wordpress'] ?? []);
    $phpTests = (array) ($services['php-tests'] ?? []);
    $root = (string) ($model['root'] ?? '');

    $check('required compose services exist', isset($services['db'], $services['wordpress'], $services['php-tests'], $services['zap'], $services['nuclei']));
    $check('compose default network blocks external egress', !empty($model['network_internal']));
    $check('DB has no configured host-published port', empty($db['ports']));
    $webPorts = (array) ($wordpress['ports'] ?? []);
    $webSafe = count($webPorts) === 1 && ($webPorts[0]['host_ip'] ?? '') === '127.0.0.1'
        && (int) ($webPorts[0]['target'] ?? 0) === 80 && (int) ($webPorts[0]['published'] ?? 0) === 8892;
    $check('WordPress publishes only 127.0.0.1:8892', $webSafe);
    foreach (['wordpress' => $wordpress, 'php-tests' => $phpTests] as $serviceName => $service) {
        $mounts = (array) ($service['mounts'] ?? []);
        $check($serviceName . ' workspace core mount is read-only', wpsr_doctor_has_mount($mounts, $root, '/workspace/wp-social-ninja'));
        $check($serviceName . ' plugin-path core mount is read-only', wpsr_doctor_has_mount($mounts, $root, '/var/www/html/wp-content/plugins/wp-social-ninja'));
    }

    $dbName = (string) ($wordpress['environment']['WORDPRESS_DB_NAME'] ?? '');
    $prefix = (string) ($wordpress['environment']['WORDPRESS_TABLE_PREFIX'] ?? '');
    $phpDb = (string) ($phpTests['environment']['WORDPRESS_DB_NAME'] ?? '');
    $phpPrefix = (string) ($phpTests['environment']['WORDPRESS_TABLE_PREFIX'] ?? '');
    $wpsr_test_db = static function ($wpsr_name) {
        return (bool) preg_match('/(?:^|[_-])test(?:[_-]|$)/i', $wpsr_name);
    };
    $check('configured DB names are matching explicit test databases', $dbName === $phpDb && $wpsr_test_db($dbName));
    $check('configured prefixes are matching and non-default', $prefix !== '' && $prefix !== 'wp_' && $phpPrefix === $prefix);
    $check('DB and WordPress declare health checks', !empty($db['healthcheck']) && !empty($wordpress['healthcheck']));
    $check('throwaway reset supports explicit down -v', !empty($model['reset_supported']));

    foreach (['docker', 'compose', 'curl', 'php'] as $tool) {
        $check('host tool present: ' . $tool, !empty($model['tools'][$tool]));
    }
    $scannerImages = ['zap' => 'ghcr.io/zaproxy/zaproxy:2.17.0', 'nuclei' => 'projectdiscovery/nuclei:v3.11.0'];
    foreach (['zap', 'nuclei'] as $scanner) {
        $service = (array) ($services[$scanner] ?? []);
        $scannerMounts = (array) ($service['mounts'] ?? []);
        $check($scanner . ' image is exactly pinned', ($service['image'] ?? '') === $scannerImages[$scanner]);
        $check($scanner . ' target is fixed loopback lab URL', ($service['environment']['WPSR_DAST_TARGET'] ?? '') === WPSR_DAST_TARGET);
        $check($scanner . ' shares WordPress network namespace', ($service['network_mode'] ?? '') === 'service:wordpress');
        $check($scanner . ' publishes no ports', empty($service['ports']));
        $check($scanner . ' scanner config mount is read-only', wpsr_doctor_has_mount($scannerMounts, $root . '/tests/security/dast', '/scanner'));
        $check($scanner . ' artifact mount is writable', wpsr_doctor_has_writable_mount($scannerMounts, '/zap/wrk'));
        $check($scanner . ' launcher exists', !empty($model['tools'][$scanner . '_script']));
        if (!$configOnly) {
            $check($scanner . ' image/tool is locally available', !empty($model['tools'][$scanner . '_image']));
        }
    }
    $check('target validator accepts exact loopback target', !empty($model['target_probes']['safe_accepts']));
    $check('target validator refuses external target', !empty($model['target_probes']['unsafe_refuses']));
    $check('Semgrep Docker launcher exists', !empty($model['tools']['semgrep_script']));
    $check('Semgrep image is pinned and network/metrics disabled', !empty($model['tools']['semgrep_offline']));
    if (!$configOnly) {
        $check('Semgrep pinned image/tool is locally available', !empty($model['tools']['semgrep_image']));
        $runtime = (array) ($model['runtime'] ?? []);
        $check('runtime inspection completed', !empty($runtime['checked']));
        foreach (['db', 'wordpress'] as $service) {
            $check($service . ' container running and healthy', !empty($runtime['containers'][$service]['running']) && !empty($runtime['containers'][$service]['healthy']));
        }
        $check('runtime DB has no published port', empty($runtime['db_ports']));
        $runtimeWeb = (array) ($runtime['web_bindings'] ?? []);
        $check('runtime web bind is loopback-only', count($runtimeWeb) === 1 && ($runtimeWeb[0]['HostIp'] ?? '') === '127.0.0.1');
        $check('runtime workspace core mount is read-only', wpsr_doctor_has_mount((array) ($runtime['mounts'] ?? []), $root, '/workspace/wp-social-ninja'));
        $check('runtime plugin-path core mount is read-only', wpsr_doctor_has_mount((array) ($runtime['mounts'] ?? []), $root, '/var/www/html/wp-content/plugins/wp-social-ninja'));
        $url = (string) ($runtime['lab_url'] ?? '');
        $urlParts = parse_url($url);
        $check('lab URL is reachable and loopback-only', !empty($runtime['url_reachable']) && ($urlParts['host'] ?? '') === '127.0.0.1');
        $actualDb = (string) ($runtime['wordpress']['db'] ?? '');
        $actualPrefix = (string) ($runtime['wordpress']['prefix'] ?? '');
        $check('runtime DB/prefix match disposable config', $actualDb === $dbName && $actualPrefix === $prefix
            && $wpsr_test_db($actualDb) && $actualPrefix !== '' && $actualPrefix !== 'wp_');
        $check('core active and Pro inactive', !empty($runtime['wordpress']['core_active']) && empty($runtime['wordpress']['pro_active']));
    }
    return $checks;
}

try {
    if ($modelPath !== null) {
        $model = json_decode((string) file_get_contents($modelPath), true);
        if (!is_array($model)) {
            throw new RuntimeException('Derived doctor model is malformed: ' . $modelPath);
        }
    } else {
        $model = wpsr_doctor_build_model($root, $lab, !$configOnly);
    }
    if ($writeModel !== null) {
        file_put_contents($writeModel, json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
    $checks = wpsr_doctor_validate($model, $configOnly);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'SECURITY-DOCTOR bootstrap failure: ' . $throwable->getMessage() . "\n");
    echo 'SECURITY-DOCTOR checks=0 failures=1 mode=' . ($configOnly ? 'config' : 'full') . "\n";
    exit(1);
}

$failures = 0;
foreach ($checks as $check) {
    echo ($check['ok'] ? 'PASS  ' : 'FAIL  ') . $check['name'] . ($check['detail'] !== '' ? ' — ' . $check['detail'] : '') . "\n";
    if (!$check['ok']) {
        $failures++;
    }
}
printf("SECURITY-DOCTOR checks=%d failures=%d mode=%s\n", count($checks), $failures, $configOnly ? 'config' : 'full');
exit($failures ? 1 : 0);
