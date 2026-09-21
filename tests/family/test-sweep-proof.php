<?php
/** Phase 30 derived proof-of-catch for sweep no-silence/count and strict SKIP discipline. */
require_once __DIR__ . '/sweep-lib.php';

$wpsr_root = dirname(__DIR__, 2);
$wpsr_pro_root = dirname($wpsr_root) . '/wp-social-ninja-pro';
$wpsr_temp = sys_get_temp_dir() . '/wpsr-phase30-' . bin2hex(random_bytes(6));
$wpsr_core_status = shell_exec('git -C ' . escapeshellarg($wpsr_root) . ' status --porcelain=v1 --untracked-files=all');
$wpsr_pro_status = shell_exec('git -C ' . escapeshellarg($wpsr_pro_root) . ' status --porcelain=v1 --untracked-files=all');
$wpsr_cases = 0;
$wpsr_red = 0;
$wpsr_restored = 0;
$wpsr_failures = 0;

function wpsr_phase30_record($wpsr_name, $wpsr_ok, $wpsr_detail)
{
    global $wpsr_cases, $wpsr_red, $wpsr_failures;
    $wpsr_cases++;
    if ($wpsr_ok) {
        $wpsr_red++;
        echo 'PROOF ' . $wpsr_name . ': RED expected ' . $wpsr_detail . "\n";
    } else {
        $wpsr_failures++;
        echo 'PROOF ' . $wpsr_name . ': FAIL ' . $wpsr_detail . "\n";
    }
}

function wpsr_phase30_copy($wpsr_source, $wpsr_target)
{
    if (is_dir($wpsr_source)) {
        if (!is_dir($wpsr_target) && !mkdir($wpsr_target, 0700, true)) {
            throw new RuntimeException('could not create derived directory');
        }
        foreach (scandir($wpsr_source) ?: [] as $wpsr_name) {
            if ($wpsr_name !== '.' && $wpsr_name !== '..') {
                wpsr_phase30_copy($wpsr_source . '/' . $wpsr_name, $wpsr_target . '/' . $wpsr_name);
            }
        }
        return;
    }
    if (!copy($wpsr_source, $wpsr_target)) {
        throw new RuntimeException('could not copy derived file');
    }
}

function wpsr_phase30_summary($wpsr_suite, $wpsr_pass = 1, $wpsr_fail = 0, $wpsr_skip = 0, array $wpsr_skip_ids = [])
{
    return [
        'suite' => $wpsr_suite,
        'pass' => $wpsr_pass,
        'fail' => $wpsr_fail,
        'skip' => $wpsr_skip,
        'total' => $wpsr_pass + $wpsr_fail + $wpsr_skip,
        'assertion_failures' => $wpsr_fail,
        'runtime' => 0.001,
        'skip_ids' => $wpsr_skip_ids,
    ];
}

function wpsr_phase30_transcript(array $wpsr_summaries, array $wpsr_events = [])
{
    $wpsr_output = '';
    foreach ($wpsr_events as $wpsr_event) {
        $wpsr_output .= 'WPSR-SKIP-JSON ' . (is_string($wpsr_event) ? $wpsr_event : json_encode($wpsr_event, JSON_UNESCAPED_SLASHES)) . "\n";
    }
    foreach ($wpsr_summaries as $wpsr_summary) {
        $wpsr_output .= 'WPSR-SUITE-JSON ' . (is_string($wpsr_summary) ? $wpsr_summary : json_encode($wpsr_summary, JSON_UNESCAPED_SLASHES)) . "\n";
    }
    return $wpsr_output;
}

function wpsr_phase30_execution($wpsr_plugin, $wpsr_total = 1, array $wpsr_errors = [])
{
    return [
        'plugin' => $wpsr_plugin,
        'pass' => $wpsr_total,
        'fail' => 0,
        'skip' => 0,
        'total' => $wpsr_total,
        'runtime' => 0.001,
        'errors' => $wpsr_errors,
    ];
}

try {
    if (!mkdir($wpsr_temp, 0700, true)) {
        throw new RuntimeException('could not create proof workspace');
    }
    $wpsr_family = wpsr_family_load_json($wpsr_root . '/tests/family/plugins.json');
    $wpsr_planning = wpsr_sweep_plan($wpsr_family, $wpsr_root, ['registry_dir' => $wpsr_root . '/tests/family']);
    if ($wpsr_planning['errors'] || count($wpsr_planning['plan']) !== 2) {
        throw new RuntimeException('live family sweep plan is not green/exact');
    }
    $wpsr_plan = $wpsr_planning['plan'];
    $wpsr_host_plugin = null;
    foreach ($wpsr_family['plugins'] as $wpsr_family_plugin) {
        if (($wpsr_family_plugin['role'] ?? '') === 'host') {
            $wpsr_host_plugin = $wpsr_family_plugin;
            break;
        }
    }
    if (!is_array($wpsr_host_plugin)) {
        throw new RuntimeException('live host registry row missing');
    }
    list($wpsr_host_metadata, $wpsr_host_errors) = wpsr_sweep_host_metadata($wpsr_root, $wpsr_host_plugin);
    if (!is_array($wpsr_host_metadata) || $wpsr_host_errors) {
        throw new RuntimeException('restored host metadata fixture is not green');
    }
    $wpsr_restored++;
    $wpsr_host_mutant = $wpsr_host_plugin;
    $wpsr_host_mutant['id'] = 'foreign-host';
    list(, $wpsr_host_mutant_errors) = wpsr_sweep_host_metadata($wpsr_root, $wpsr_host_mutant);
    wpsr_phase30_record('host-metadata-plugin-mismatch', in_array('host test metadata missing, malformed, or plugin-mismatched', $wpsr_host_mutant_errors, true), 'host metadata/plugin identity drift rejected');
    $wpsr_malformed_host_root = $wpsr_temp . '/malformed-host';
    $wpsr_malformed_host_path = $wpsr_malformed_host_root . '/tests/' . $wpsr_host_plugin['test_tree']['metadata'];
    if (!mkdir(dirname($wpsr_malformed_host_path), 0700, true)) {
        throw new RuntimeException('could not create malformed host fixture');
    }
    $wpsr_malformed_host_metadata = $wpsr_host_metadata;
    $wpsr_malformed_host_metadata['suites'] = 'not-an-array';
    file_put_contents($wpsr_malformed_host_path, json_encode($wpsr_malformed_host_metadata));
    list(, $wpsr_malformed_host_errors) = wpsr_sweep_host_metadata($wpsr_malformed_host_root, $wpsr_host_plugin);
    wpsr_phase30_record('host-metadata-scalar-suites', in_array('host suites must be nonempty array', $wpsr_malformed_host_errors, true), 'scalar suite metadata rejected without parser crash');

    ob_start();
    $wpsr_command_result = wpsr_sweep_run_command("printf 'phase30-command-output\\n'; exit 7", $wpsr_root);
    $wpsr_command_echo = ob_get_clean();
    wpsr_phase30_record('run-command-exit-output', $wpsr_command_result['exit'] === 7
        && $wpsr_command_result['output'] === "phase30-command-output\n"
        && $wpsr_command_echo === $wpsr_command_result['output']
        && is_float($wpsr_command_result['runtime']) && $wpsr_command_result['runtime'] >= 0,
        'command output, exit status, echo, and runtime preserved');

    $wpsr_green_executions = [wpsr_phase30_execution('core'), wpsr_phase30_execution('pro')];
    $wpsr_green = wpsr_sweep_reconcile($wpsr_plan, $wpsr_green_executions, []);
    if ($wpsr_green['errors']) {
        throw new RuntimeException('restored reconciliation fixture is not green');
    }
    $wpsr_restored++;

    $wpsr_result = wpsr_sweep_reconcile($wpsr_plan, [wpsr_phase30_execution('core')], []);
    wpsr_phase30_record('omitted-sibling-iteration', in_array('missing available plugin execution: pro', $wpsr_result['errors'], true), 'missing Pro execution rejected');

    $wpsr_result = wpsr_sweep_reconcile($wpsr_plan, [wpsr_phase30_execution('core'), wpsr_phase30_execution('core'), wpsr_phase30_execution('pro')], []);
    wpsr_phase30_record('duplicate-execution', in_array('duplicate plugin execution: core', $wpsr_result['errors'], true), 'duplicate core execution rejected');

    $wpsr_result = wpsr_sweep_reconcile($wpsr_plan, [wpsr_phase30_execution('core'), wpsr_phase30_execution('pro', 0)], []);
    wpsr_phase30_record('zero-tests', in_array('zero executed tests: pro', $wpsr_result['errors'], true), 'available Pro zero tests rejected');

    $wpsr_artifact_plugins = [];
    foreach ($wpsr_plan as $wpsr_item) {
        $wpsr_artifact_plugins[] = array_merge($wpsr_item, [
            'status' => 'PASS', 'reason' => 'executed', 'code' => 'ok',
            'pass' => 1, 'fail' => 0, 'skip' => 0, 'family_skip' => 0, 'total' => 1, 'runtime' => 0.001,
            'failure_count' => 0,
        ]);
    }
    $wpsr_artifact = [
        'schema' => 1, 'family' => 'wp-social-ninja', 'plugins' => $wpsr_artifact_plugins,
        'family_skip_events' => [], 'aggregate' => $wpsr_green['totals'], 'runtime' => 0.01, 'failures' => 0,
    ];
    if (wpsr_sweep_validate_artifact($wpsr_artifact, $wpsr_green['totals'], [], $wpsr_plan)) {
        throw new RuntimeException('restored family artifact fixture is not green');
    }
    $wpsr_restored++;
    $wpsr_artifact_mutant = $wpsr_artifact;
    $wpsr_artifact_mutant['foreign'] = true;
    wpsr_phase30_record('artifact-unknown-key', (bool) wpsr_sweep_validate_artifact($wpsr_artifact_mutant, $wpsr_green['totals'], [], $wpsr_plan), 'unknown artifact key rejected');
    $wpsr_artifact_mutant = $wpsr_artifact;
    $wpsr_artifact_mutant['aggregate']['pass'] = '2';
    wpsr_phase30_record('artifact-count-type', (bool) wpsr_sweep_validate_artifact($wpsr_artifact_mutant, $wpsr_green['totals'], [], $wpsr_plan), 'artifact scalar count drift rejected');
    $wpsr_artifact_mutant = $wpsr_artifact;
    $wpsr_artifact_mutant['plugins'][0]['id'] = 'foreign';
    wpsr_phase30_record('artifact-plugin-identity', (bool) wpsr_sweep_validate_artifact($wpsr_artifact_mutant, $wpsr_green['totals'], [], $wpsr_plan), 'foreign plugin identity rejected');
    $wpsr_artifact_mutant = $wpsr_artifact;
    $wpsr_artifact_mutant['plugins'][0]['status'] = 'SKIP';
    wpsr_phase30_record('artifact-plugin-outcome', (bool) wpsr_sweep_validate_artifact($wpsr_artifact_mutant, $wpsr_green['totals'], [], $wpsr_plan), 'available plugin SKIP outcome rejected');
    $wpsr_artifact_mutant = $wpsr_artifact;
    $wpsr_artifact_mutant['plugins'][0]['status'] = 'FAIL';
    $wpsr_artifact_mutant['failures'] = 1;
    wpsr_phase30_record('artifact-disguised-failure', (bool) wpsr_sweep_validate_artifact($wpsr_artifact_mutant, $wpsr_green['totals'], [], $wpsr_plan), 'FAIL row with PASS metadata rejected');
    $wpsr_artifact_mutant = $wpsr_artifact;
    $wpsr_artifact_mutant['plugins'][0]['id'] = ['not-an-id'];
    wpsr_phase30_record('artifact-scalar-plugin-id', (bool) wpsr_sweep_validate_artifact($wpsr_artifact_mutant, $wpsr_green['totals'], [], $wpsr_plan), 'nonscalar plugin identity rejected without parser crash');
    $wpsr_artifact_mutant = $wpsr_artifact;
    $wpsr_artifact_mutant['plugins'][0]['pass'] = ['not-a-count'];
    wpsr_phase30_record('artifact-scalar-plugin-count', (bool) wpsr_sweep_validate_artifact($wpsr_artifact_mutant, $wpsr_green['totals'], [], $wpsr_plan), 'nonscalar plugin count rejected without parser crash');

    $wpsr_missing_plan = $wpsr_plan;
    foreach ($wpsr_missing_plan as &$wpsr_item) {
        if ($wpsr_item['id'] === 'pro') {
            $wpsr_item['available'] = false;
            $wpsr_item['skip_id'] = 'family-pro-optional-missing';
            $wpsr_item['suites'] = 0;
            $wpsr_item['files'] = 0;
        }
    }
    unset($wpsr_item);
    $wpsr_result = wpsr_sweep_reconcile($wpsr_missing_plan, [wpsr_phase30_execution('core')], []);
    wpsr_phase30_record('missing-optional-explicit-skip', in_array('missing optional explicit SKIP: pro', $wpsr_result['errors'], true), 'missing optional event rejected');

    $wpsr_derived_pro = $wpsr_temp . '/wp-social-ninja-pro';
    mkdir($wpsr_derived_pro, 0700, true);
    copy($wpsr_pro_root . '/wp-social-ninja-pro.php', $wpsr_derived_pro . '/wp-social-ninja-pro.php');
    wpsr_phase30_copy($wpsr_pro_root . '/tests', $wpsr_derived_pro . '/tests');
    $wpsr_broken_link = $wpsr_temp . '/broken-link';
    symlink($wpsr_derived_pro . '/missing-tests', $wpsr_broken_link);
    $wpsr_tree = wpsr_test_tree_validate($wpsr_family, $wpsr_root, [
        'plugin_id' => 'pro', 'plugin_root' => $wpsr_derived_pro, 'link' => $wpsr_broken_link,
    ]);
    wpsr_phase30_record('broken-available-addon', (bool) array_filter($wpsr_tree['errors'], static function ($wpsr_error) {
        return strpos($wpsr_error, 'link target disagrees') !== false;
    }), 'broken available link rejected');

    $wpsr_skip_registry = wpsr_skip_load($wpsr_root . '/tests/skips/registry.json');
    $wpsr_errors = wpsr_skip_validate($wpsr_skip_registry, $wpsr_root, [[
        'id' => 'phase30-undeclared-runtime',
        'owner_plugin' => 'core',
        'suite' => 'pure',
        'test' => 'derived',
        'reason_category' => 'known-failure',
        'reason' => 'derived proof event',
        'evidence' => ['type' => 'fix-plan', 'path' => 'tests/FIX-PLAN.md', 'item' => 'phase30-undeclared-runtime'],
        'expires_on' => '2099-01-01',
        'revisit' => 'derived proof only',
    ]]);
    wpsr_phase30_record('undeclared-runtime-skip', in_array('undeclared runtime skip: phase30-undeclared-runtime', $wpsr_errors, true), 'undeclared event rejected');

    $wpsr_stale = $wpsr_skip_registry;
    $wpsr_stale['skips'][0]['expires_on'] = '2000-01-01';
    $wpsr_errors = wpsr_skip_validate($wpsr_stale, $wpsr_root);
    wpsr_phase30_record('stale-skip', (bool) array_filter($wpsr_errors, static function ($wpsr_error) {
        return strpos($wpsr_error, 'stale or malformed expiry') !== false;
    }), 'expired registry row rejected');

    $wpsr_mismatch = $wpsr_skip_registry;
    $wpsr_mismatch['skips'][] = [
        'id' => 'phase30-fix-plan-anchor',
        'owner_plugin' => 'core',
        'suite' => 'js',
        'test' => 'derived fix-plan anchor probe',
        'surface' => 'derived proof only',
        'reason_category' => 'known-failure',
        'reason' => 'derived proof event',
        'declaration' => 'tests/FIX-PLAN.md',
        'evidence' => ['type' => 'fix-plan', 'path' => 'tests/FIX-PLAN.md', 'item' => 'not-a-real-fix-plan-anchor'],
        'expires_on' => '2099-01-01',
        'revisit' => 'derived proof only',
        'default_gate' => false,
    ];
    $wpsr_errors = wpsr_skip_validate($wpsr_mismatch, $wpsr_root);
    wpsr_phase30_record('stable-fix-plan-mismatch', (bool) array_filter($wpsr_errors, static function ($wpsr_error) {
        return strpos($wpsr_error, 'exact stable FIX-PLAN anchor') !== false;
    }), 'wrong stable FIX-PLAN anchor rejected');

    $wpsr_claimed = $wpsr_green['totals'];
    $wpsr_claimed['pass']++;
    $wpsr_result = wpsr_sweep_reconcile($wpsr_plan, $wpsr_green_executions, [], $wpsr_claimed);
    wpsr_phase30_record('summary-count-mismatch', in_array('claimed summary count mismatch', $wpsr_result['errors'], true), 'aggregate drift rejected');

    $wpsr_parser_item = ['id' => 'core', 'summary_labels' => ['PUBLIC', 'STATIC']];
    $wpsr_parser_output = wpsr_phase30_transcript([wpsr_phase30_summary('PUBLIC'), wpsr_phase30_summary('STATIC')]);
    $wpsr_parser_green = wpsr_sweep_parse_output($wpsr_parser_item, ['exit' => 0, 'output' => $wpsr_parser_output, 'runtime' => 0.01], $wpsr_skip_rows = wpsr_skip_rows_by_id($wpsr_skip_registry));
    if ($wpsr_parser_green['errors']) {
        throw new RuntimeException('restored sweep parser fixture is not green: ' . implode('; ', $wpsr_parser_green['errors']));
    }
    $wpsr_restored++;
    foreach ([
        'malformed-suite-event' => wpsr_phase30_transcript(['{broken}', wpsr_phase30_summary('STATIC')]),
        'null-suite-event' => wpsr_phase30_transcript(['null', wpsr_phase30_summary('STATIC')]),
        'partial-suite-event' => wpsr_phase30_transcript([['suite' => 'PUBLIC'], wpsr_phase30_summary('STATIC')]),
        'duplicate-suite-event' => wpsr_phase30_transcript([wpsr_phase30_summary('PUBLIC'), wpsr_phase30_summary('PUBLIC'), wpsr_phase30_summary('STATIC')]),
    ] as $wpsr_name => $wpsr_output) {
        $wpsr_parsed = wpsr_sweep_parse_output($wpsr_parser_item, ['exit' => 0, 'output' => $wpsr_output, 'runtime' => 0.01], $wpsr_skip_rows);
        wpsr_phase30_record($wpsr_name, (bool) $wpsr_parsed['errors'], 'production sweep parser rejected event mutation');
    }
    foreach (['STATIC', 'PUBLIC'] as $wpsr_label) {
        $wpsr_other = $wpsr_label === 'STATIC' ? 'PUBLIC' : 'STATIC';
        $wpsr_output = wpsr_phase30_transcript([wpsr_phase30_summary($wpsr_label, 0, 1), wpsr_phase30_summary($wpsr_other)]);
        $wpsr_parsed = wpsr_sweep_parse_output($wpsr_parser_item, ['exit' => 1, 'output' => $wpsr_output, 'runtime' => 0.01], $wpsr_skip_rows);
        wpsr_phase30_record('forced-' . strtolower($wpsr_label) . '-failure', (bool) $wpsr_parsed['errors'], $wpsr_label . ' failure makes family red');
    }
    $wpsr_skip_row = $wpsr_skip_rows['family-pro-optional-missing'];
    $wpsr_skip_event = [];
    foreach (['id', 'owner_plugin', 'suite', 'test', 'reason_category', 'reason', 'evidence', 'expires_on', 'revisit'] as $wpsr_key) {
        $wpsr_skip_event[$wpsr_key] = $wpsr_skip_row[$wpsr_key];
    }
    $wpsr_skip_event['reason'] = 'foreign reason';
    $wpsr_skip_item = ['id' => 'core', 'summary_labels' => ['FAMILY-RESULTS']];
    $wpsr_skip_output = wpsr_phase30_transcript([wpsr_phase30_summary('FAMILY-RESULTS', 0, 0, 1, [$wpsr_skip_event['id']])], [$wpsr_skip_event]);
    $wpsr_parsed = wpsr_sweep_parse_output($wpsr_skip_item, ['exit' => 0, 'output' => $wpsr_skip_output, 'runtime' => 0.01], $wpsr_skip_rows);
    wpsr_phase30_record('skip-exact-registry-metadata', (bool) array_filter($wpsr_parsed['errors'], static function ($wpsr_error) {
        return strpos($wpsr_error, 'runtime SKIP registry metadata mismatch') !== false;
    }), 'runtime SKIP metadata drift rejected by production parser');
    $wpsr_malformed_skip = wpsr_phase30_transcript([wpsr_phase30_summary('PURE', 0, 0, 1, ['x'])], ['null']);
    $wpsr_parsed = wpsr_sweep_parse_output($wpsr_skip_item, ['exit' => 0, 'output' => $wpsr_malformed_skip, 'runtime' => 0.01], $wpsr_skip_rows);
    wpsr_phase30_record('malformed-skip-event', (bool) $wpsr_parsed['errors'], 'malformed runtime SKIP rejected');

    $wpsr_live_skip_errors = wpsr_skip_validate($wpsr_skip_registry, $wpsr_root);
    if ($wpsr_live_skip_errors) {
        throw new RuntimeException('restored live SKIP registry is not green: ' . implode('; ', $wpsr_live_skip_errors));
    }
    $wpsr_restored++;
} catch (Throwable $wpsr_exception) {
    $wpsr_failures++;
    fwrite(STDERR, 'FAMILY-SWEEP-PROOF bootstrap failure: ' . $wpsr_exception->getMessage() . "\n");
} finally {
    exec('rm -rf ' . escapeshellarg($wpsr_temp));
    $wpsr_core_after = shell_exec('git -C ' . escapeshellarg($wpsr_root) . ' status --porcelain=v1 --untracked-files=all');
    $wpsr_pro_after = shell_exec('git -C ' . escapeshellarg($wpsr_pro_root) . ' status --porcelain=v1 --untracked-files=all');
    if ($wpsr_core_after !== $wpsr_core_status || $wpsr_pro_after !== $wpsr_pro_status) {
        $wpsr_failures++;
        fwrite(STDERR, "FAMILY-SWEEP-PROOF repository status drifted.\n");
    }
}
printf(
    "FAMILY-SWEEP-PROOF cases=%d red=%d restored=%d failures=%d\n",
    $wpsr_cases, $wpsr_red, $wpsr_restored, $wpsr_failures
);
exit($wpsr_failures ? WPSR_SWEEP_FAIL : WPSR_SWEEP_PASS);
