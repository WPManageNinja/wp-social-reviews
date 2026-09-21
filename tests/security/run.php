<?php
/** Phase 22 unified registry selector and execution engine. */

require_once __DIR__ . '/lib/registry.php';

$root = wpsr_security_root();
list($selectors, $flags, $parseError) = wpsr_security_parse_arguments(
    array_slice($argv, 1),
    ['registry']
);
if ($parseError !== null) {
    fwrite(STDERR, "ERROR: {$parseError}\n");
    exit(WPSR_SECURITY_MISUSE);
}
if (!empty($flags['help'])) {
    echo "Usage: tests/bin/security [--list] [--plugin ID[,ID]] [--module ID[,ID]] [--category ID[,ID]]\n";
    echo "Repeat selectors or use comma-separated values for OR within one dimension. Dimensions combine with AND.\n";
    exit(WPSR_SECURITY_PASS);
}

$registryPath = $flags['registry'] ?? $root . '/tests/security/test-metadata.json';
try {
    $registry = wpsr_security_load_json($registryPath, 'security registry');
    $family = wpsr_security_family();
} catch (RuntimeException $exception) {
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . "\n");
    exit(WPSR_SECURITY_MISUSE);
}

$errors = wpsr_security_validate($registry, $family, $root, true);
if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'REGISTRY ERROR: ' . $error . "\n");
    }
    exit(WPSR_SECURITY_MISUSE);
}

list($selected, $selectorError) = wpsr_security_select($registry['targets'], $selectors, $family);
if ($selectorError !== null) {
    fwrite(STDERR, 'ERROR: ' . $selectorError . "\n");
    exit(WPSR_SECURITY_MISUSE);
}

if (!empty($flags['list'])) {
    foreach ($selected as $target) {
        echo wpsr_security_format_target($target) . "\n";
    }
    exit(WPSR_SECURITY_PASS);
}

function wpsr_security_command(array $target, $root, array $family)
{
    $path = $root . '/' . $target['path'];
    switch ($target['runner']) {
        case 'php':
            $parts = ['php', escapeshellarg($path)];
            foreach ((array) ($target['args'] ?? []) as $argument) {
                $parts[] = escapeshellarg($argument);
            }
            return [implode(' ', $parts), 0];

        case 'raw-sql-fixture':
            return [
                'php ' . escapeshellarg($root . '/tests/lint/raw-sql-prefix.php') . ' ' . escapeshellarg(dirname($path)),
                1,
            ];

        case 'wp-cli':
            $base = 'bash ' . escapeshellarg($root . '/tests/lab/scripts/php-tests.sh') . ' ';
            if ($target['suite'] === 'permissions') {
                return [$base . 'tests/bin/run-permissions.php', 0];
            }
            if ($target['suite'] === 'integration') {
                return [$base . 'tests/bin/run-integration.php ' . escapeshellarg('filter=' . $target['filter']), 0];
            }
            return [$base . 'tests/bin/run-public.php ' . escapeshellarg('filter=' . $target['filter']), 0];

        case 'security-doctor':
            return ['php ' . escapeshellarg($root . '/tests/security/lab/doctor.php') . ' --config-only', 0];

        case 'security-self-test':
            return ['php ' . escapeshellarg($root . '/tests/security/lab/self-test.php'), 0];

        case 'fuzz-self-test':
            return ['bash ' . escapeshellarg($root . '/tests/bin/run-fuzz.sh') . ' self-test', 0];

        case 'release-secrets-self-test':
            return ['bash ' . escapeshellarg($root . '/tests/bin/run-release-secrets.sh') . ' self-test', 0];

        case 'semgrep-fixtures':
            return ['bash ' . escapeshellarg($root . '/tests/bin/run-semgrep.sh') . ' fixtures', 0];

        case 'fuzz':
            return ['bash ' . escapeshellarg($root . '/tests/bin/run-fuzz.sh') . ' audit', 0];

        case 'release-secrets':
            return ['bash ' . escapeshellarg($root . '/tests/bin/run-release-secrets.sh') . ' audit', 0];

        case 'semgrep-security':
            return ['bash ' . escapeshellarg($root . '/tests/bin/run-semgrep.sh') . ' security', 0];

        case 'semgrep-quality':
            return ['bash ' . escapeshellarg($root . '/tests/bin/run-semgrep.sh') . ' quality', 0];

        case 'mutation-self-test':
            return ['php ' . escapeshellarg($root . '/tests/bin/run-mutation.php') . ' --self-test-only', 0];

        case 'mutation-audit':
            return ['php ' . escapeshellarg($root . '/tests/bin/run-mutation.php'), 0];

        case 'dast-zap':
            return ['bash ' . escapeshellarg($root . '/tests/bin/run-zap-baseline.sh'), 0];

        case 'dast-nuclei':
            return ['bash ' . escapeshellarg($root . '/tests/bin/run-nuclei-baseline.sh'), 0];
    }
    throw new RuntimeException('Runner implementation missing: ' . $target['runner']);
}

function wpsr_security_run_command($command, $root)
{
    $started = microtime(true);
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(
        ['/usr/bin/env', 'bash', '-c', $command . ' 2>&1'],
        $descriptor,
        $pipes,
        $root
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start target command.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return [
        'exit' => $code,
        'output' => (string) $output . (string) $stderr,
        'runtime' => round(microtime(true) - $started, 3),
    ];
}

function wpsr_security_executed_count(array $target, $output)
{
    $clean = preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/', '', (string) $output);
    if (($target['count_mode'] ?? 'capture') === 'matches') {
        $count = preg_match_all($target['count_pattern'], $clean, $matches);
        return $count === false ? 0 : $count;
    }
    if (!preg_match($target['count_pattern'], $clean, $matches)) {
        return 0;
    }
    return (int) ($matches[(int) $target['count_group']] ?? 0);
}

$failures = 0;
$executedTargets = 0;
$executedTests = 0;
$targetResults = [];
$started = microtime(true);
foreach ($selected as $target) {
    echo "\n== {$target['id']} ==\n";
    try {
        list($command, $expectedExit) = wpsr_security_command($target, $root, $family);
        $result = wpsr_security_run_command($command, $root);
        echo $result['output'];
        if ($result['output'] !== '' && substr($result['output'], -1) !== "\n") {
            echo "\n";
        }
        $count = wpsr_security_executed_count($target, $result['output']);
        $passed = $result['exit'] === $expectedExit && $count > 0;
        printf(
            "SECURITY-TARGET id=%s executed=%d child-exit=%d expected-exit=%d runtime=%.3fs result=%s\n",
            $target['id'],
            $count,
            $result['exit'],
            $expectedExit,
            $result['runtime'],
            $passed ? 'PASS' : 'FAIL'
        );
        $targetResults[$target['id']] = ['passed' => $passed, 'tests' => $count];
        if (!$passed) {
            if ($count === 0) {
                fwrite(STDERR, "SECURITY-TARGET {$target['id']} reported zero executed targets/tests.\n");
            }
            $failures++;
        } else {
            $executedTargets++;
            $executedTests += $count;
        }
    } catch (Throwable $throwable) {
        fwrite(STDERR, 'SECURITY-TARGET ' . $target['id'] . ' runner error: ' . $throwable->getMessage() . "\n");
        $targetResults[$target['id']] = ['passed' => false, 'tests' => 0];
        $failures++;
    }
}

$summaryPlugins = $selectors['plugin'];
if (!$summaryPlugins) {
    foreach ($selected as $target) {
        foreach ($target['plugins'] as $pluginId) {
            $summaryPlugins[] = $pluginId;
        }
    }
    $summaryPlugins = array_values(array_unique($summaryPlugins));
}
sort($summaryPlugins, SORT_STRING);
foreach ($summaryPlugins as $pluginId) {
    $pluginTargets = array_values(array_filter($selected, static function ($target) use ($pluginId) {
        return in_array($pluginId, $target['plugins'], true);
    }));
    $pluginExecuted = 0;
    $pluginTests = 0;
    $pluginFailures = 0;
    foreach ($pluginTargets as $target) {
        $result = $targetResults[$target['id']] ?? ['passed' => false, 'tests' => 0];
        if ($result['passed']) {
            $pluginExecuted++;
            $pluginTests += $result['tests'];
        } else {
            $pluginFailures++;
        }
    }
    if (!$pluginTargets || $pluginExecuted + $pluginFailures !== count($pluginTargets)) {
        $failures++;
        $pluginFailures++;
    }
    printf(
        "SECURITY-PLUGIN plugin=%s selected=%d executed-targets=%d executed-tests=%d failures=%d\n",
        $pluginId,
        count($pluginTargets),
        $pluginExecuted,
        $pluginTests,
        $pluginFailures
    );
}
if (count($targetResults) !== count($selected) || $executedTargets + $failures < count($selected)) {
    fwrite(STDERR, "SECURITY-REGRESSION selected/executed target reconciliation drift.\n");
    $failures++;
}
printf(
    "\nSECURITY-REGRESSION registered=%d selected=%d executed-targets=%d executed-tests=%d failures=%d runtime=%.3fs\n",
    count($registry['targets']),
    count($selected),
    $executedTargets,
    $executedTests,
    $failures,
    microtime(true) - $started
);
if ($executedTargets === 0 || $executedTests === 0) {
    fwrite(STDERR, "SECURITY-REGRESSION zero execution is a failure.\n");
    exit(WPSR_SECURITY_FAIL);
}
exit($failures > 0 ? WPSR_SECURITY_FAIL : WPSR_SECURITY_PASS);
