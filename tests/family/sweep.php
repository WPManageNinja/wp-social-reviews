<?php
/** Phase 30 family sibling sweep: every registered member, activation order, exact summaries. */
require_once __DIR__ . '/sweep-lib.php';

$wpsr_root = dirname(__DIR__, 2);
$wpsr_options = [
    'registry' => __DIR__ . '/plugins.json',
    'lint' => false,
    'artifact' => $wpsr_root . '/tests/.artifacts/family/sweep-summary.json',
    'family_root' => null,
    'use_env' => true,
];
foreach (array_slice($argv, 1) as $wpsr_argument) {
    if ($wpsr_argument === '--lint') {
        $wpsr_options['lint'] = true;
    } elseif ($wpsr_argument === '--ignore-env') {
        $wpsr_options['use_env'] = false;
    } elseif (strpos($wpsr_argument, '--registry=') === 0) {
        $wpsr_options['registry'] = substr($wpsr_argument, 11);
    } elseif (strpos($wpsr_argument, '--artifact=') === 0) {
        $wpsr_options['artifact'] = substr($wpsr_argument, 11);
    } elseif (strpos($wpsr_argument, '--family-root=') === 0) {
        $wpsr_options['family_root'] = substr($wpsr_argument, 14);
    } else {
        fwrite(STDERR, 'FAMILY-SWEEP misuse: ' . $wpsr_argument . "\n");
        exit(WPSR_SWEEP_MISUSE);
    }
}
try {
    $wpsr_family = wpsr_family_load_json($wpsr_options['registry']);
    $wpsr_planning = wpsr_sweep_plan($wpsr_family, $wpsr_root, [
        'use_env' => $wpsr_options['use_env'],
        'family_root' => $wpsr_options['family_root'],
        'registry_dir' => dirname($wpsr_options['registry']),
    ]);
} catch (Throwable $wpsr_exception) {
    fwrite(STDERR, 'FAMILY-SWEEP FAIL ' . $wpsr_exception->getMessage() . "\n");
    exit(WPSR_SWEEP_FAIL);
}
foreach ($wpsr_planning['errors'] as $wpsr_error) {
    fwrite(STDERR, 'FAMILY-SWEEP FAIL ' . $wpsr_error . "\n");
}
foreach ($wpsr_planning['plan'] as $wpsr_item) {
    printf(
        "FAMILY-SWEEP DISCOVERY id=%s role=%s discovered=yes availability=%s available=%s environment=%s order=%d suites=%d files=%d\n",
        $wpsr_item['id'],
        $wpsr_item['role'],
        $wpsr_item['availability'],
        $wpsr_item['available'] ? 'yes' : 'no',
        $wpsr_item['environment'],
        $wpsr_item['activation_order'],
        $wpsr_item['suites'],
        $wpsr_item['files']
    );
}
if ($wpsr_planning['errors']) {
    printf("FAMILY-SWEEP-LINT plugins=%d failures=%d\n", count($wpsr_planning['plan']), count($wpsr_planning['errors']));
    exit(WPSR_SWEEP_FAIL);
}
if ($wpsr_options['lint']) {
    printf("FAMILY-SWEEP-LINT plugins=%d available=%d skipped=%d suites=%d files=%d failures=0\n",
        count($wpsr_planning['plan']),
        count(array_filter($wpsr_planning['plan'], static function ($wpsr_item) { return $wpsr_item['available']; })),
        count(array_filter($wpsr_planning['plan'], static function ($wpsr_item) { return !$wpsr_item['available']; })),
        array_sum(array_column($wpsr_planning['plan'], 'suites')),
        array_sum(array_column($wpsr_planning['plan'], 'files'))
    );
    exit(WPSR_SWEEP_PASS);
}

$wpsr_skip_registry = wpsr_skip_load($wpsr_root . '/tests/skips/registry.json');
$wpsr_skip_rows = wpsr_skip_rows_by_id($wpsr_skip_registry);
$wpsr_executions = [];
$wpsr_family_skips = [];
$wpsr_plugin_summaries = [];
$wpsr_started = microtime(true);
foreach ($wpsr_planning['plan'] as $wpsr_item) {
    echo "\n" . str_repeat('=', 72) . "\n";
    echo 'FAMILY-SWEEP PLUGIN ' . $wpsr_item['id'] . ' role=' . $wpsr_item['role'] . ' env=' . $wpsr_item['environment'] . "\n";
    if (!$wpsr_item['available']) {
        $wpsr_skip_row = $wpsr_skip_rows[$wpsr_item['skip_id']] ?? null;
        if (!$wpsr_skip_row) {
            $wpsr_family_skips[] = ['id' => $wpsr_item['skip_id'], 'owner_plugin' => $wpsr_item['id'], 'invalid' => true];
            continue;
        }
        $wpsr_event = [
            'id' => $wpsr_skip_row['id'],
            'owner_plugin' => $wpsr_skip_row['owner_plugin'],
            'suite' => $wpsr_skip_row['suite'],
            'test' => $wpsr_skip_row['test'],
            'reason_category' => $wpsr_skip_row['reason_category'],
            'reason' => $wpsr_skip_row['reason'],
            'evidence' => $wpsr_skip_row['evidence'],
            'expires_on' => $wpsr_skip_row['expires_on'],
            'revisit' => $wpsr_skip_row['revisit'],
        ];
        $wpsr_family_skips[] = $wpsr_event;
        echo 'FAMILY-SWEEP SKIP id=' . $wpsr_event['id'] . ' owner=' . $wpsr_event['owner_plugin']
            . ' test=' . $wpsr_event['test'] . ' reason=' . $wpsr_event['reason_category']
            . ' code=optional-sibling-missing evidence=' . $wpsr_event['evidence']['path']
            . ' expires=' . $wpsr_event['expires_on'] . ' revisit=' . $wpsr_event['revisit'] . "\n";
        $wpsr_plugin_summaries[] = array_merge($wpsr_item, [
            'status' => 'SKIP', 'reason' => $wpsr_event['reason_category'], 'code' => 'optional-sibling-missing',
            'pass' => 0, 'fail' => 0, 'skip' => 0, 'family_skip' => 1, 'total' => 0, 'runtime' => 0,
            'failure_count' => 0,
        ]);
        continue;
    }
    if ($wpsr_item['runner'] === 'host-default') {
        $wpsr_command = 'WPSR_FAMILY_SWEEP_CHILD=1 bash tests/bin/run-all.sh family-host --env '
            . escapeshellarg($wpsr_item['environment']);
    } else {
        $wpsr_command = 'bash tests/lab/scripts/php-tests.sh --env ' . escapeshellarg($wpsr_item['environment'])
            . ' tests/bin/run-addon.php ' . escapeshellarg('plugin=' . $wpsr_item['id']);
    }
    $wpsr_result = wpsr_sweep_run_command($wpsr_command, $wpsr_root);
    $wpsr_execution = wpsr_sweep_parse_output($wpsr_item, $wpsr_result, $wpsr_skip_rows);
    $wpsr_executions[] = $wpsr_execution;
    $wpsr_status = !$wpsr_execution['errors'] ? 'PASS' : 'FAIL';
    $wpsr_plugin_summaries[] = array_merge($wpsr_item, [
        'status' => $wpsr_status,
        'reason' => $wpsr_status === 'PASS' ? 'executed' : 'child-failure',
        'code' => $wpsr_status === 'PASS' ? 'ok' : 'failed-add-on-or-host',
        'pass' => $wpsr_execution['pass'],
        'fail' => $wpsr_execution['fail'],
        'skip' => $wpsr_execution['skip'],
        'family_skip' => 0,
        'total' => $wpsr_execution['total'],
        'runtime' => $wpsr_execution['runtime'],
        'failure_count' => count($wpsr_execution['errors']),
    ]);
}
$wpsr_reconciled = wpsr_sweep_reconcile($wpsr_planning['plan'], $wpsr_executions, $wpsr_family_skips);
foreach ($wpsr_reconciled['errors'] as $wpsr_error) {
    fwrite(STDERR, 'FAMILY-SWEEP FAIL ' . $wpsr_error . "\n");
}
echo "\n" . str_repeat('=', 72) . "\nFAMILY-SWEEP HUMAN SUMMARY\n";
foreach ($wpsr_plugin_summaries as $wpsr_summary) {
    printf(
        "FAMILY-SWEEP PLUGIN-SUMMARY id=%s discovered=yes availability=%s role=%s environment=%s status=%s suites=%d files=%d pass=%d fail=%d skip=%d family-skip=%d total=%d reason=%s code=%s runtime=%.3fs\n",
        $wpsr_summary['id'], $wpsr_summary['availability'], $wpsr_summary['role'], $wpsr_summary['environment'],
        $wpsr_summary['status'], $wpsr_summary['suites'], $wpsr_summary['files'], $wpsr_summary['pass'],
        $wpsr_summary['fail'], $wpsr_summary['skip'], $wpsr_summary['family_skip'], $wpsr_summary['total'], $wpsr_summary['reason'],
        $wpsr_summary['code'], $wpsr_summary['runtime']
    );
}
$wpsr_machine = [
    'schema' => 1,
    'family' => $wpsr_family['family'] ?? '',
    'plugins' => $wpsr_plugin_summaries,
    'family_skip_events' => $wpsr_family_skips,
    'aggregate' => $wpsr_reconciled['totals'],
    'runtime' => round(microtime(true) - $wpsr_started, 3),
    'failures' => count($wpsr_reconciled['errors']),
];
$wpsr_artifact_errors = wpsr_sweep_validate_artifact($wpsr_machine, $wpsr_reconciled['totals'], $wpsr_skip_rows, $wpsr_planning['plan']);
foreach ($wpsr_artifact_errors as $wpsr_error) {
    fwrite(STDERR, 'FAMILY-SWEEP FAIL ' . $wpsr_error . "\n");
}
$wpsr_machine['failures'] += count($wpsr_artifact_errors);
$wpsr_artifact_dir = dirname($wpsr_options['artifact']);
if (!is_dir($wpsr_artifact_dir) && !mkdir($wpsr_artifact_dir, 0777, true)) {
    fwrite(STDERR, 'FAMILY-SWEEP FAIL could not create artifact directory\n');
    $wpsr_machine['failures']++;
}
if (!file_put_contents($wpsr_options['artifact'], json_encode($wpsr_machine, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n")) {
    fwrite(STDERR, 'FAMILY-SWEEP FAIL could not write machine summary\n');
    $wpsr_machine['failures']++;
}
echo 'FAMILY-SWEEP-JSON ' . json_encode($wpsr_machine, JSON_UNESCAPED_SLASHES) . "\n";
$wpsr_totals = $wpsr_reconciled['totals'];
printf(
    "FAMILY-SWEEP plugins=%d available=%d executed=%d family-skips=%d suites=%d files=%d pass=%d fail=%d skip=%d total=%d failures=%d runtime=%.3fs artifact=%s\n",
    $wpsr_totals['plugins'], $wpsr_totals['available'], $wpsr_totals['executed'], $wpsr_totals['family_skips'],
    $wpsr_totals['suites'], $wpsr_totals['files'], $wpsr_totals['pass'], $wpsr_totals['fail'],
    $wpsr_totals['skip'], $wpsr_totals['total'], $wpsr_machine['failures'], $wpsr_machine['runtime'], $wpsr_options['artifact']
);
exit($wpsr_machine['failures'] ? WPSR_SWEEP_FAIL : WPSR_SWEEP_PASS);
