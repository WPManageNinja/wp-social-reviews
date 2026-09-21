<?php
/** Green-to-red proof for Phase 22 registry validator and selector misuse contract. */

$root = dirname(__DIR__, 2);
$sourcePath = $root . '/tests/security/test-metadata.json';
$validator = $root . '/tests/security/validate-registry.php';
$runner = $root . '/tests/security/run.php';
$source = json_decode(file_get_contents($sourcePath), true);
if (!is_array($source) || empty($source['targets'])) {
    fwrite(STDERR, "SECURITY-REGISTRY-SELF-TEST source registry unavailable.\n");
    exit(2);
}

$temp = sys_get_temp_dir() . '/wpsr-security-registry-' . bin2hex(random_bytes(6));
if (!mkdir($temp, 0700, true) && !is_dir($temp)) {
    fwrite(STDERR, "SECURITY-REGISTRY-SELF-TEST temporary directory unavailable.\n");
    exit(2);
}
$pass = 0;
$fail = 0;

function wpsr_registry_self_command($command, $root)
{
    exec('cd ' . escapeshellarg($root) . ' && ' . $command . ' 2>&1', $output, $code);
    return [$code, implode("\n", $output)];
}

function wpsr_registry_self_record($name, $ok, $detail = '')
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo 'PASS  ' . $name . ($detail !== '' ? ' — ' . $detail : '') . "\n";
    } else {
        $fail++;
        echo 'FAIL  ' . $name . ($detail !== '' ? ' — ' . $detail : '') . "\n";
    }
}

function wpsr_registry_self_fixture($temp, $name, array $registry)
{
    $path = $temp . '/' . $name . '.json';
    file_put_contents($path, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return $path;
}

try {
    list($code, $output) = wpsr_registry_self_command('php ' . escapeshellarg($validator), $root);
    wpsr_registry_self_record(
        'live validator green',
        $code === 0 && preg_match('/REGISTRY-VALIDATOR checks=[1-9][0-9]* targets=[1-9][0-9]* discovered=[1-9][0-9]* failures=0/', $output),
        'exit ' . $code
    );

    $mutations = [];

    $orphan = $source;
    array_shift($orphan['targets']);
    $mutations[] = ['orphan', $orphan, '/orphan security target file:/'];

    $phantom = $source;
    $row = $phantom['targets'][0];
    $row['id'] = 'phantom-security-target';
    $row['path'] = 'tests/security/targets/does-not-exist.php';
    $phantom['targets'][] = $row;
    $mutations[] = ['phantom', $phantom, '/phantom registry entry:/'];

    $duplicate = $source;
    $row = $duplicate['targets'][0];
    $row['path'] = $duplicate['targets'][1]['path'];
    $duplicate['targets'][] = $row;
    $mutations[] = ['duplicate', $duplicate, '/duplicate id/'];

    $badPlugin = $source;
    $badPlugin['targets'][0]['plugins'] = ['outside-family'];
    $mutations[] = ['bad-plugin', $badPlugin, '/bad plugin not found in PR #324 security family/'];

    $badTag = $source;
    $badTag['targets'][0]['modules'] = ['rest_api'];
    $mutations[] = ['bad-tag', $badTag, '/bad normalized modules tag/'];

    foreach ($mutations as $mutation) {
        list($name, $registry, $pattern) = $mutation;
        $path = wpsr_registry_self_fixture($temp, $name, $registry);
        list($code, $output) = wpsr_registry_self_command(
            'php ' . escapeshellarg($validator) . ' --skip-list --registry=' . escapeshellarg($path),
            $root
        );
        wpsr_registry_self_record(
            'validator rejects ' . $name,
            $code === 1 && preg_match($pattern, $output),
            'exit ' . $code
        );
    }

    $tagDrift = $source;
    $tagDrift['targets'][0]['modules'][] = 'misleading-drift';
    sort($tagDrift['targets'][0]['modules'], SORT_STRING);
    $tagDriftPath = wpsr_registry_self_fixture($temp, 'tag-drift', $tagDrift);
    list($code, $output) = wpsr_registry_self_command(
        'php ' . escapeshellarg($validator) . ' --registry=' . escapeshellarg($tagDriftPath),
        $root
    );
    wpsr_registry_self_record(
        'validator rejects unclaimed valid tag drift',
        $code === 1 && strpos($output, 'selector expectation coverage drift') !== false,
        'exit ' . $code
    );

    list($code, $output) = wpsr_registry_self_command(
        'php ' . escapeshellarg($runner) . ' --list --plugin core',
        $root
    );
    wpsr_registry_self_record(
        'selectable core plugin resolves registered targets',
        $code === 0 && strpos($output, 'raw-sql-prefix') !== false
            && strpos($output, 'endpoint-inventory-drift') !== false
            && strpos($output, 'semgrep-security-audit') !== false,
        'exit ' . $code
    );

    $selectorCases = [
        ['unknown plugin selector exits 2', '--list --plugin absent-plugin', '/unknown plugin selector/'],
        ['unknown module selector exits 2', '--list --module absent-module', '/unknown module selector/'],
        ['unknown category selector exits 2', '--list --category absent-category', '/unknown category selector/'],
        ['empty intersection exits 2', '--list --module database --category authorization', '/empty intersection/'],
    ];
    foreach ($selectorCases as $case) {
        list($name, $arguments, $pattern) = $case;
        list($code, $output) = wpsr_registry_self_command(
            'php ' . escapeshellarg($runner) . ' ' . $arguments,
            $root
        );
        wpsr_registry_self_record($name, $code === 2 && preg_match($pattern, $output), 'exit ' . $code);
    }

    $zeroCount = $source;
    foreach ($zeroCount['targets'] as $index => $target) {
        if ($target['id'] === 'raw-sql-prefix') {
            $zeroCount['targets'][$index]['count_pattern'] = '~THIS-SUMMARY-CANNOT-EXIST-([0-9]+)~';
            break;
        }
    }
    $zeroCountPath = wpsr_registry_self_fixture($temp, 'zero-count', $zeroCount);
    list($code, $output) = wpsr_registry_self_command(
        'php ' . escapeshellarg($runner)
            . ' --module database --category table-prefix-safety --registry=' . escapeshellarg($zeroCountPath),
        $root
    );
    wpsr_registry_self_record(
        'zero executed summary is target failure exit 1',
        $code === 1 && strpos($output, 'reported zero executed targets/tests') !== false,
        'exit ' . $code
    );

    list($code, $output) = wpsr_registry_self_command('php ' . escapeshellarg($runner) . ' --list', $root);
    $lines = array_values(array_filter(explode("\n", trim($output)), 'strlen'));
    wpsr_registry_self_record('no-selector list resolves nonzero exact registry', $code === 0 && count($lines) === count($source['targets']), 'targets ' . count($lines));
} finally {
    exec('rm -rf ' . escapeshellarg($temp));
}

echo "SECURITY-REGISTRY-SELF-TEST pass={$pass} fail={$fail}\n";
if ($fail > 0) {
    exit(1);
}
if ($pass === 0) {
    exit(2);
}
exit(0);
