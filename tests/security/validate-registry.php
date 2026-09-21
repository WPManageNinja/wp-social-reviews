<?php
/** Phase 22 registry validator: structure, discovery ownership, and selector semantics. */

require_once __DIR__ . '/lib/registry.php';

$root = wpsr_security_root();
$options = ['registry' => $root . '/tests/security/test-metadata.json', 'skip-list' => false];
foreach (array_slice($argv, 1) as $argument) {
    if (strpos($argument, '--registry=') === 0) {
        $options['registry'] = substr($argument, 11);
    } elseif ($argument === '--skip-list') {
        $options['skip-list'] = true;
    } else {
        fwrite(STDERR, 'REGISTRY-VALIDATOR misuse: ' . $argument . "\n");
        exit(WPSR_SECURITY_MISUSE);
    }
}

try {
    $registry = wpsr_security_load_json($options['registry'], 'security registry');
    $family = wpsr_security_family();
} catch (RuntimeException $exception) {
    fwrite(STDERR, 'REGISTRY-VALIDATOR FAIL ' . $exception->getMessage() . "\n");
    exit(WPSR_SECURITY_FAIL);
}

$failures = wpsr_security_validate($registry, $family, $root, true);
$checks = 1;

if (!$options['skip-list'] && !$failures) {
    $expectations = require __DIR__ . '/list-expectations.php';
    if (!is_array($expectations) || !$expectations) {
        $failures[] = 'selector expectations discovered zero cases';
    } else {
        $wpsr_expected_selector_values = ['plugin' => [], 'module' => [], 'category' => []];
        foreach ($expectations as $wpsr_expectation) {
            $wpsr_args = array_values((array) ($wpsr_expectation['args'] ?? []));
            for ($wpsr_index = 0; $wpsr_index < count($wpsr_args); $wpsr_index++) {
                if (!preg_match('/^--(plugin|module|category)(?:=(.*))?$/', $wpsr_args[$wpsr_index], $wpsr_match)) {
                    continue;
                }
                $wpsr_raw = isset($wpsr_match[2]) && $wpsr_match[2] !== ''
                    ? $wpsr_match[2]
                    : ($wpsr_args[++$wpsr_index] ?? '');
                foreach (explode(',', $wpsr_raw) as $wpsr_value) {
                    if ($wpsr_value !== '') $wpsr_expected_selector_values[$wpsr_match[1]][$wpsr_value] = true;
                }
            }
        }
        $wpsr_registry_selector_values = ['plugin' => [], 'module' => [], 'category' => []];
        foreach ($registry['targets'] as $wpsr_target) {
            foreach (['plugin' => 'plugins', 'module' => 'modules', 'category' => 'categories'] as $wpsr_dimension => $wpsr_field) {
                foreach ($wpsr_target[$wpsr_field] as $wpsr_value) $wpsr_registry_selector_values[$wpsr_dimension][$wpsr_value] = true;
            }
        }
        foreach ($wpsr_registry_selector_values as $wpsr_dimension => $wpsr_values) {
            ksort($wpsr_values, SORT_STRING);
            ksort($wpsr_expected_selector_values[$wpsr_dimension], SORT_STRING);
            if (array_keys($wpsr_values) !== array_keys($wpsr_expected_selector_values[$wpsr_dimension])) {
                $failures[] = $wpsr_dimension . ' selector expectation coverage drift; registry=' . json_encode(array_keys($wpsr_values))
                    . ' expectations=' . json_encode(array_keys($wpsr_expected_selector_values[$wpsr_dimension]));
            }
        }
        foreach ($expectations as $case) {
            $command = 'php ' . escapeshellarg($root . '/tests/security/run.php') . ' --list';
            foreach ($case['args'] as $argument) {
                $command .= ' ' . escapeshellarg($argument);
            }
            $command .= ' --registry=' . escapeshellarg($options['registry']);
            exec('cd ' . escapeshellarg($root) . ' && ' . $command . ' 2>&1', $lines, $code);
            $ids = [];
            foreach ($lines as $line) {
                if ($line === '') {
                    continue;
                }
                $columns = explode("\t", $line);
                $ids[] = $columns[0];
            }
            $checks++;
            if ($code !== 0) {
                $failures[] = $case['name'] . ': representative --list exited ' . $code;
            } elseif ($ids !== $case['ids']) {
                $failures[] = $case['name'] . ': exact ordered IDs drifted; expected '
                    . json_encode($case['ids']) . ', got ' . json_encode($ids);
            }
            unset($lines);
        }
    }
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'REGISTRY-VALIDATOR FAIL ' . $failure . "\n");
    }
    printf(
        "REGISTRY-VALIDATOR checks=%d targets=%d discovered=%d failures=%d\n",
        $checks,
        count((array) ($registry['targets'] ?? [])),
        count(wpsr_security_discovered_targets($root)),
        count($failures)
    );
    exit(WPSR_SECURITY_FAIL);
}

printf(
    "REGISTRY-VALIDATOR checks=%d targets=%d discovered=%d failures=0\n",
    $checks,
    count($registry['targets']),
    count(wpsr_security_discovered_targets($root))
);
exit(WPSR_SECURITY_PASS);
