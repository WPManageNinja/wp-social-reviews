<?php
/** Phase 30 registry-driven family sibling sweep planning, execution, and reconciliation. */
require_once __DIR__ . '/census-lib.php';
require_once __DIR__ . '/test-tree-lib.php';
require_once dirname(__DIR__) . '/skips/skip-lib.php';

const WPSR_SWEEP_PASS = 0;
const WPSR_SWEEP_FAIL = 1;
const WPSR_SWEEP_MISUSE = 2;

function wpsr_sweep_host_metadata($wpsr_root, array $wpsr_plugin)
{
    $wpsr_path = $wpsr_root . '/tests/' . (string) ($wpsr_plugin['test_tree']['metadata'] ?? '');
    $wpsr_metadata = is_file($wpsr_path) ? json_decode(file_get_contents($wpsr_path), true) : null;
    $wpsr_errors = [];
    if (!is_array($wpsr_metadata) || ($wpsr_metadata['schema'] ?? null) !== 1
        || ($wpsr_metadata['plugin']['id'] ?? '') !== ($wpsr_plugin['id'] ?? '')) {
        return [null, ['host test metadata missing, malformed, or plugin-mismatched']];
    }
    $wpsr_files = [];
    $wpsr_suites = [];
    $wpsr_summaries = [];
    $wpsr_metadata_errors = [];
    wpsr_skip_exact_keys($wpsr_metadata, ['schema', 'host_api', 'plugin', 'summary_labels', 'suites'], 'host metadata', $wpsr_metadata_errors);
    wpsr_skip_exact_keys($wpsr_metadata['plugin'] ?? null, ['id', 'slug', 'main_file'], 'host metadata.plugin', $wpsr_metadata_errors);
    if (!is_array($wpsr_metadata['suites'] ?? null) || !wpsr_skip_is_list($wpsr_metadata['suites']) || !$wpsr_metadata['suites']) {
        $wpsr_metadata_errors[] = 'host suites must be nonempty array';
    }
    if (!is_array($wpsr_metadata['summary_labels'] ?? null) || !wpsr_skip_is_list($wpsr_metadata['summary_labels'])) {
        $wpsr_metadata_errors[] = 'host summary_labels must be array';
    }
    foreach ($wpsr_metadata_errors as $wpsr_error) {
        $wpsr_errors[] = $wpsr_error;
    }
    if (!is_array($wpsr_metadata['suites'] ?? null)) {
        return [$wpsr_metadata, array_values(array_unique($wpsr_errors))];
    }
    foreach ($wpsr_metadata['suites'] as $wpsr_suite) {
        if (!is_array($wpsr_suite)) {
            $wpsr_errors[] = 'host suite must be object';
            continue;
        }
        $wpsr_id = $wpsr_suite['id'] ?? '';
        $wpsr_summary = $wpsr_suite['summary'] ?? '';
        $wpsr_suite_errors = [];
        $wpsr_expected_suite_keys = $wpsr_id === 'static' ? ['id', 'summary', 'files', 'commands'] : ['id', 'summary', 'files'];
        wpsr_skip_exact_keys($wpsr_suite, $wpsr_expected_suite_keys, 'host suite ' . (string) $wpsr_id, $wpsr_suite_errors);
        foreach ($wpsr_suite_errors as $wpsr_error) {
            $wpsr_errors[] = $wpsr_error;
        }
        if (!wpsr_family_is_token($wpsr_id) || isset($wpsr_suites[$wpsr_id])
            || !is_string($wpsr_summary) || !preg_match('/^[A-Z][A-Z0-9-]+$/', $wpsr_summary)
            || isset($wpsr_summaries[$wpsr_summary]) || empty($wpsr_suite['files'])) {
            $wpsr_errors[] = 'host suite metadata invalid or duplicate: ' . (string) $wpsr_id;
            continue;
        }
        $wpsr_suites[$wpsr_id] = true;
        $wpsr_summaries[$wpsr_summary] = true;
        foreach ($wpsr_suite['files'] as $wpsr_file) {
            if (!is_string($wpsr_file) || isset($wpsr_files[$wpsr_file]) || !is_file($wpsr_root . '/' . $wpsr_file)) {
                $wpsr_errors[] = 'host registered test file missing/duplicate: ' . (string) $wpsr_file;
                continue;
            }
            $wpsr_files[$wpsr_file] = true;
        }
        if ($wpsr_id === 'static') {
            if (!is_array($wpsr_suite['commands'] ?? null) || !wpsr_skip_is_list($wpsr_suite['commands']) || !$wpsr_suite['commands']) {
                $wpsr_errors[] = 'host static commands must be nonempty array';
            }
            foreach ((array) ($wpsr_suite['commands'] ?? []) as $wpsr_command) {
                $wpsr_command_errors = [];
                wpsr_skip_exact_keys($wpsr_command, ['label', 'command'], 'host static command', $wpsr_command_errors);
                if (!is_string($wpsr_command['label'] ?? null) || $wpsr_command['label'] === ''
                    || !is_array($wpsr_command['command'] ?? null) || !wpsr_skip_is_list($wpsr_command['command']) || !$wpsr_command['command']) {
                    $wpsr_command_errors[] = 'host static command label/argv malformed';
                }
                foreach ($wpsr_command_errors as $wpsr_error) {
                    $wpsr_errors[] = $wpsr_error;
                }
            }
        }
    }
    $wpsr_declared_summaries = array_keys($wpsr_summaries);
    sort($wpsr_declared_summaries, SORT_STRING);
    if (($wpsr_metadata['summary_labels'] ?? null) !== $wpsr_declared_summaries) {
        $wpsr_errors[] = 'host summary_labels disagree with suites';
    }
    $wpsr_static_entries = [
        'tests/infrastructure/lint.php', 'tests/smoke/route-coverage.php', 'tests/permissions/route-coverage.php',
        'tests/round-trips/route-coverage.php', 'tests/validation/route-coverage.php', 'tests/public/handler-coverage.php',
        'tests/security/endpoints/lint.php', 'tests/security/validate-registry.php', 'tests/security/registry-self-test.php',
        'tests/skips/lint.php', 'tests/skips/proof-of-catch.php', 'tests/compatibility/lint.php',
        'tests/compatibility/test-proof-of-catch.php',
    ];
    $wpsr_discovered = array_merge(
        $wpsr_static_entries,
        ['tests/bin/run-family-canary.php', 'tests/bin/run-permissions.php', 'tests/bin/run-smoke.php', 'tests/public/public-handlers.php'],
        array_map(static function ($wpsr_path) use ($wpsr_root) { return substr($wpsr_path, strlen($wpsr_root) + 1); }, glob($wpsr_root . '/tests/integration/*.php') ?: []),
        array_map(static function ($wpsr_path) use ($wpsr_root) { return substr($wpsr_path, strlen($wpsr_root) + 1); }, glob($wpsr_root . '/tests/pure/*.php') ?: []),
        array_map(static function ($wpsr_path) use ($wpsr_root) { return substr($wpsr_path, strlen($wpsr_root) + 1); }, glob($wpsr_root . '/tests/js/specs/*.test.js') ?: [])
    );
    sort($wpsr_discovered, SORT_STRING);
    $wpsr_declared = array_keys($wpsr_files);
    sort($wpsr_declared, SORT_STRING);
    if (!$wpsr_discovered || $wpsr_discovered !== $wpsr_declared) {
        $wpsr_errors[] = 'host registered files disagree with default runtime discovery';
    }
    return [$wpsr_metadata, $wpsr_errors];
}

function wpsr_sweep_plan(array $wpsr_family, $wpsr_root, array $wpsr_options = [])
{
    $wpsr_errors = [];
    $wpsr_plan = [];
    $wpsr_validation = wpsr_family_validate(
        $wpsr_family,
        $wpsr_root,
        $wpsr_options['family_root'] ?? null,
        $wpsr_options['use_env'] ?? true,
        $wpsr_options['registry_dir'] ?? null
    );
    foreach ($wpsr_validation['errors'] as $wpsr_error) {
        $wpsr_errors[] = 'census: ' . $wpsr_error;
    }
    $wpsr_plugins = (array) ($wpsr_family['plugins'] ?? []);
    usort($wpsr_plugins, static function ($wpsr_left, $wpsr_right) {
        return ($wpsr_left['activation_order'] ?? -1) <=> ($wpsr_right['activation_order'] ?? -1);
    });
    $wpsr_seen_order = -1;
    foreach ($wpsr_plugins as $wpsr_plugin) {
        $wpsr_id = (string) ($wpsr_plugin['id'] ?? '');
        $wpsr_override = false;
        $wpsr_path = wpsr_family_resolve_path((array) ($wpsr_plugin['path'] ?? []), $wpsr_root, $wpsr_options['use_env'] ?? true, $wpsr_override);
        $wpsr_available = is_dir($wpsr_path) && count(wpsr_family_plugin_headers($wpsr_path)) === 1;
        $wpsr_execution = (array) ($wpsr_plugin['test_tree']['execution'] ?? []);
        if (($wpsr_plugin['activation_order'] ?? -1) <= $wpsr_seen_order) {
            $wpsr_errors[] = $wpsr_id . ': activation/dependency order is not strictly increasing';
        }
        $wpsr_seen_order = $wpsr_plugin['activation_order'] ?? -1;
        if (!in_array($wpsr_execution['runner'] ?? '', ['host-default', 'addon-wp-cli'], true)
            || ($wpsr_execution['environment'] ?? '') !== ($wpsr_plugin['runtime']['environment'] ?? null)) {
            $wpsr_errors[] = $wpsr_id . ': execution runner/environment metadata invalid';
        }
        $wpsr_item = [
            'id' => $wpsr_id,
            'discovered' => true,
            'role' => $wpsr_plugin['role'] ?? '',
            'slug' => $wpsr_plugin['slug'] ?? '',
            'availability' => $wpsr_plugin['runtime']['availability'] ?? '',
            'available' => $wpsr_available,
            'environment' => $wpsr_execution['environment'] ?? '',
            'runner' => $wpsr_execution['runner'] ?? '',
            'path' => $wpsr_path,
            'activation_order' => $wpsr_plugin['activation_order'] ?? -1,
            'skip_id' => null,
            'suites' => 0,
            'files' => 0,
            'summary_labels' => [],
        ];
        if (!$wpsr_available) {
            if (($wpsr_plugin['runtime']['availability'] ?? '') === 'optional'
                && ($wpsr_plugin['missing_sibling']['policy'] ?? '') === 'skip'
                && !$wpsr_override) {
                $wpsr_item['skip_id'] = $wpsr_plugin['missing_sibling']['skip_id'] ?? '';
            } else {
                $wpsr_errors[] = $wpsr_id . ': required/configured plugin unavailable';
            }
            $wpsr_plan[] = $wpsr_item;
            continue;
        }
        if (($wpsr_plugin['role'] ?? '') === 'host') {
            list($wpsr_metadata, $wpsr_host_errors) = wpsr_sweep_host_metadata($wpsr_root, $wpsr_plugin);
            foreach ($wpsr_host_errors as $wpsr_error) {
                $wpsr_errors[] = $wpsr_id . ': ' . $wpsr_error;
            }
            $wpsr_item['suites'] = count((array) ($wpsr_metadata['suites'] ?? []));
            foreach ((array) ($wpsr_metadata['suites'] ?? []) as $wpsr_suite) {
                $wpsr_item['files'] += count((array) ($wpsr_suite['files'] ?? []));
            }
            $wpsr_item['summary_labels'] = (array) ($wpsr_metadata['summary_labels'] ?? []);
        } else {
            $wpsr_tree = wpsr_test_tree_validate($wpsr_family, $wpsr_root, ['plugin_id' => $wpsr_id]);
            foreach ($wpsr_tree['errors'] as $wpsr_error) {
                $wpsr_errors[] = $wpsr_id . ': available linked test tree broken: ' . $wpsr_error;
            }
            if ($wpsr_tree['skips']) {
                $wpsr_errors[] = $wpsr_id . ': available plugin was incorrectly skipped';
            }
            $wpsr_item['suites'] = (int) ($wpsr_tree['suites'] ?? 0);
            $wpsr_item['files'] = count($wpsr_tree['files']);
            $wpsr_owner_tree = wpsr_family_absolute_path($wpsr_path, (string) ($wpsr_plugin['test_tree']['path'] ?? 'tests'));
            $wpsr_metadata_path = $wpsr_owner_tree . '/' . (string) ($wpsr_plugin['test_tree']['metadata'] ?? 'addon.json');
            $wpsr_metadata = is_file($wpsr_metadata_path) ? json_decode(file_get_contents($wpsr_metadata_path), true) : [];
            $wpsr_summary = (string) ($wpsr_metadata['summary'] ?? '');
            if (!preg_match('/^[A-Z][A-Z0-9-]+$/', $wpsr_summary)) {
                $wpsr_errors[] = $wpsr_id . ': add-on aggregate summary label missing';
            }
            $wpsr_item['summary_labels'] = [$wpsr_summary];
        }
        if ($wpsr_item['suites'] < 1 || $wpsr_item['files'] < 1) {
            $wpsr_errors[] = $wpsr_id . ': zero registered suites or files';
        }
        $wpsr_plan[] = $wpsr_item;
    }
    return ['plan' => $wpsr_plan, 'errors' => array_values(array_unique($wpsr_errors))];
}

function wpsr_sweep_run_command($wpsr_command, $wpsr_root)
{
    $wpsr_started = microtime(true);
    $wpsr_lines = [];
    exec('cd ' . escapeshellarg($wpsr_root) . ' && ' . $wpsr_command . ' 2>&1', $wpsr_lines, $wpsr_code);
    $wpsr_output = implode("\n", $wpsr_lines) . ($wpsr_lines ? "\n" : '');
    echo $wpsr_output;
    return ['exit' => $wpsr_code, 'output' => $wpsr_output, 'runtime' => round(microtime(true) - $wpsr_started, 3)];
}

function wpsr_sweep_parse_output(array $wpsr_item, array $wpsr_result, array $wpsr_skip_rows = [])
{
    $wpsr_summaries = [];
    $wpsr_skip_events = [];
    foreach (explode("\n", $wpsr_result['output']) as $wpsr_line) {
        if (strpos($wpsr_line, 'WPSR-SUITE-JSON ') === 0) {
            $wpsr_summaries[] = json_decode(substr($wpsr_line, 16), true);
        } elseif (strpos($wpsr_line, 'WPSR-SKIP-JSON ') === 0) {
            $wpsr_skip_events[] = json_decode(substr($wpsr_line, 15), true);
        }
    }
    $wpsr_pass = $wpsr_fail = $wpsr_skip = $wpsr_total = 0;
    $wpsr_labels = [];
    $wpsr_errors = [];
    $wpsr_summary_keys = ['suite', 'pass', 'fail', 'skip', 'total', 'assertion_failures', 'runtime', 'skip_ids'];
    foreach ($wpsr_summaries as $wpsr_summary) {
        $wpsr_summary_key_errors = [];
        if (!wpsr_skip_exact_keys($wpsr_summary, $wpsr_summary_keys, 'suite summary', $wpsr_summary_key_errors)
            || !is_string($wpsr_summary['suite'] ?? null)) {
            $wpsr_errors[] = $wpsr_item['id'] . ': malformed suite summary';
            foreach ($wpsr_summary_key_errors as $wpsr_error) {
                $wpsr_errors[] = $wpsr_item['id'] . ': ' . $wpsr_error;
            }
            continue;
        }
        $wpsr_label = $wpsr_summary['suite'];
        if (isset($wpsr_labels[$wpsr_label])) {
            $wpsr_errors[] = $wpsr_item['id'] . ': duplicate suite summary ' . $wpsr_label;
        }
        $wpsr_labels[$wpsr_label] = true;
        foreach (['pass', 'fail', 'skip', 'total'] as $wpsr_field) {
            if (!is_int($wpsr_summary[$wpsr_field] ?? null) || $wpsr_summary[$wpsr_field] < 0) {
                $wpsr_errors[] = $wpsr_item['id'] . ': malformed ' . $wpsr_field . ' count';
            }
        }
        if (($wpsr_summary['pass'] ?? 0) + ($wpsr_summary['fail'] ?? 0) + ($wpsr_summary['skip'] ?? 0) !== ($wpsr_summary['total'] ?? -1)) {
            $wpsr_errors[] = $wpsr_item['id'] . ': suite summary count mismatch ' . $wpsr_label;
        }
        if (!is_int($wpsr_summary['assertion_failures']) || $wpsr_summary['assertion_failures'] < 0
            || ($wpsr_summary['fail'] === 0 && $wpsr_summary['assertion_failures'] !== 0)
            || (!is_int($wpsr_summary['runtime']) && !is_float($wpsr_summary['runtime'])) || $wpsr_summary['runtime'] < 0
            || !is_array($wpsr_summary['skip_ids']) || !wpsr_skip_is_list($wpsr_summary['skip_ids'])
            || count($wpsr_summary['skip_ids']) !== $wpsr_summary['skip']
            || count($wpsr_summary['skip_ids']) !== count(array_unique($wpsr_summary['skip_ids'], SORT_STRING))) {
            $wpsr_errors[] = $wpsr_item['id'] . ': suite summary metadata/skip IDs malformed ' . $wpsr_label;
        }
        $wpsr_pass += (int) ($wpsr_summary['pass'] ?? 0);
        $wpsr_fail += (int) ($wpsr_summary['fail'] ?? 0);
        $wpsr_skip += (int) ($wpsr_summary['skip'] ?? 0);
        $wpsr_total += (int) ($wpsr_summary['total'] ?? 0);
    }
    $wpsr_expected_labels = $wpsr_item['summary_labels'];
    $wpsr_actual_labels = array_keys($wpsr_labels);
    sort($wpsr_actual_labels, SORT_STRING);
    sort($wpsr_expected_labels, SORT_STRING);
    if ($wpsr_actual_labels !== $wpsr_expected_labels) {
        $wpsr_errors[] = $wpsr_item['id'] . ': missing/extra suite execution; expected=' . json_encode($wpsr_expected_labels)
            . ' actual=' . json_encode($wpsr_actual_labels);
    }
    $wpsr_event_ids = [];
    $wpsr_event_keys = ['id', 'owner_plugin', 'suite', 'test', 'reason_category', 'reason', 'evidence', 'expires_on', 'revisit'];
    foreach ($wpsr_skip_events as $wpsr_index => $wpsr_event) {
        $wpsr_event_errors = [];
        if (!wpsr_skip_exact_keys($wpsr_event, $wpsr_event_keys, 'runtime SKIP event', $wpsr_event_errors)) {
            $wpsr_errors[] = $wpsr_item['id'] . ': malformed runtime SKIP event at ' . $wpsr_index;
            continue;
        }
        $wpsr_event_id = $wpsr_event['id'];
        if (!is_string($wpsr_event_id) || isset($wpsr_event_ids[$wpsr_event_id])) {
            $wpsr_errors[] = $wpsr_item['id'] . ': duplicate/malformed runtime SKIP event ' . (string) $wpsr_event_id;
            continue;
        }
        $wpsr_event_ids[$wpsr_event_id] = true;
        $wpsr_registry_row = $wpsr_skip_rows[$wpsr_event_id] ?? null;
        foreach ($wpsr_event_keys as $wpsr_field) {
            if (!is_array($wpsr_registry_row) || $wpsr_event[$wpsr_field] !== ($wpsr_registry_row[$wpsr_field] ?? null)) {
                $wpsr_errors[] = $wpsr_item['id'] . ': runtime SKIP registry metadata mismatch ' . $wpsr_event_id . '/' . $wpsr_field;
            }
        }
    }
    $wpsr_summary_skip_ids = [];
    foreach ($wpsr_summaries as $wpsr_summary) {
        foreach ((array) ($wpsr_summary['skip_ids'] ?? []) as $wpsr_skip_id) {
            $wpsr_summary_skip_ids[] = $wpsr_skip_id;
        }
    }
    sort($wpsr_summary_skip_ids, SORT_STRING);
    $wpsr_emitted_skip_ids = array_keys($wpsr_event_ids);
    sort($wpsr_emitted_skip_ids, SORT_STRING);
    if ($wpsr_skip !== count($wpsr_skip_events) || $wpsr_summary_skip_ids !== $wpsr_emitted_skip_ids) {
        $wpsr_errors[] = $wpsr_item['id'] . ': emitted SKIP count/IDs/events mismatch';
    }
    if ($wpsr_total < 1) {
        $wpsr_errors[] = $wpsr_item['id'] . ': zero executed tests';
    }
    if ($wpsr_result['exit'] !== 0 || $wpsr_fail !== 0) {
        $wpsr_errors[] = $wpsr_item['id'] . ': child execution failed';
    }
    return [
        'plugin' => $wpsr_item['id'],
        'pass' => $wpsr_pass,
        'fail' => $wpsr_fail,
        'skip' => $wpsr_skip,
        'total' => $wpsr_total,
        'runtime' => $wpsr_result['runtime'],
        'suite_summaries' => count($wpsr_summaries),
        'skip_events' => $wpsr_skip_events,
        'errors' => $wpsr_errors,
    ];
}

function wpsr_sweep_validate_artifact(array $wpsr_artifact, array $wpsr_expected_totals, array $wpsr_skip_rows, array $wpsr_expected_plan)
{
    $wpsr_errors = [];
    wpsr_skip_exact_keys($wpsr_artifact, ['schema', 'family', 'plugins', 'family_skip_events', 'aggregate', 'runtime', 'failures'], 'family sweep artifact', $wpsr_errors);
    wpsr_skip_exact_keys($wpsr_artifact['aggregate'] ?? null, ['plugins', 'available', 'executed', 'family_skips', 'suites', 'files', 'pass', 'fail', 'skip', 'total'], 'family sweep aggregate', $wpsr_errors);
    if ($wpsr_errors) {
        return $wpsr_errors;
    }
    if ($wpsr_artifact['schema'] !== 1 || $wpsr_artifact['family'] !== 'wp-social-ninja'
        || !is_array($wpsr_artifact['plugins']) || !wpsr_skip_is_list($wpsr_artifact['plugins'])
        || !is_array($wpsr_artifact['family_skip_events']) || !wpsr_skip_is_list($wpsr_artifact['family_skip_events'])
        || (!is_int($wpsr_artifact['runtime']) && !is_float($wpsr_artifact['runtime'])) || $wpsr_artifact['runtime'] < 0
        || !is_int($wpsr_artifact['failures']) || $wpsr_artifact['failures'] < 0) {
        $wpsr_errors[] = 'family sweep artifact scalar/list metadata malformed';
    }
    if ($wpsr_artifact['aggregate'] !== $wpsr_expected_totals) {
        $wpsr_errors[] = 'family sweep artifact aggregate differs from reconciled totals';
    }
    $wpsr_plugin_keys = ['id', 'discovered', 'role', 'slug', 'availability', 'available', 'environment', 'runner', 'path', 'activation_order', 'skip_id', 'suites', 'files', 'summary_labels', 'status', 'reason', 'code', 'pass', 'fail', 'skip', 'family_skip', 'total', 'runtime', 'failure_count'];
    $wpsr_identity_keys = ['id', 'discovered', 'role', 'slug', 'availability', 'available', 'environment', 'runner', 'path', 'activation_order', 'skip_id', 'suites', 'files', 'summary_labels'];
    $wpsr_seen_plugins = [];
    $wpsr_observed_failures = 0;
    $wpsr_observed_totals = ['plugins' => 0, 'available' => 0, 'executed' => 0, 'family_skips' => 0, 'suites' => 0, 'files' => 0, 'pass' => 0, 'fail' => 0, 'skip' => 0, 'total' => 0];
    foreach ($wpsr_artifact['plugins'] as $wpsr_index => $wpsr_plugin) {
        if (!wpsr_skip_exact_keys($wpsr_plugin, $wpsr_plugin_keys, 'family sweep plugin ' . $wpsr_index, $wpsr_errors)) {
            continue;
        }
        $wpsr_id = $wpsr_plugin['id'] ?? '';
        $wpsr_label = is_string($wpsr_id) ? $wpsr_id : 'plugins[' . $wpsr_index . ']';
        if (!is_string($wpsr_id) || !is_bool($wpsr_plugin['discovered'] ?? null)
            || !is_bool($wpsr_plugin['available'] ?? null) || !in_array($wpsr_plugin['status'] ?? null, ['PASS', 'FAIL', 'SKIP'], true)) {
            $wpsr_errors[] = 'family sweep plugin identity/status malformed';
        } elseif (isset($wpsr_seen_plugins[$wpsr_id])) {
            $wpsr_errors[] = 'family sweep plugin identity/status malformed';
        } else {
            $wpsr_seen_plugins[$wpsr_id] = true;
        }
        $wpsr_expected_plugin = $wpsr_expected_plan[$wpsr_index] ?? null;
        $wpsr_actual_identity = [];
        foreach ($wpsr_identity_keys as $wpsr_field) {
            $wpsr_actual_identity[$wpsr_field] = $wpsr_plugin[$wpsr_field];
        }
        if (!is_array($wpsr_expected_plugin) || $wpsr_actual_identity !== array_intersect_key($wpsr_expected_plugin, array_flip($wpsr_identity_keys))) {
            $wpsr_errors[] = 'family sweep plugin identity differs from reconciled plan: ' . $wpsr_label;
        }
        $wpsr_counts_valid = true;
        foreach (['activation_order', 'suites', 'files', 'pass', 'fail', 'skip', 'family_skip', 'total', 'failure_count'] as $wpsr_field) {
            if (!is_int($wpsr_plugin[$wpsr_field] ?? null) || $wpsr_plugin[$wpsr_field] < 0) {
                $wpsr_errors[] = 'family sweep plugin count malformed: ' . $wpsr_label . '/' . $wpsr_field;
                $wpsr_counts_valid = false;
            }
        }
        if ((!is_int($wpsr_plugin['runtime']) && !is_float($wpsr_plugin['runtime'])) || $wpsr_plugin['runtime'] < 0
            || !is_string($wpsr_plugin['reason']) || $wpsr_plugin['reason'] === ''
            || !is_string($wpsr_plugin['code']) || $wpsr_plugin['code'] === '') {
            $wpsr_errors[] = 'family sweep plugin runtime/reason/code malformed: ' . $wpsr_label;
        }
        if ($wpsr_counts_valid && $wpsr_plugin['pass'] + $wpsr_plugin['fail'] + $wpsr_plugin['skip'] !== $wpsr_plugin['total']) {
            $wpsr_errors[] = 'family sweep plugin counts do not reconcile: ' . $wpsr_label;
        }
        if ($wpsr_plugin['available']) {
            $wpsr_pass_outcome = $wpsr_plugin['status'] === 'PASS' && $wpsr_plugin['reason'] === 'executed'
                && $wpsr_plugin['code'] === 'ok' && $wpsr_plugin['fail'] === 0 && $wpsr_plugin['failure_count'] === 0;
            $wpsr_fail_outcome = $wpsr_plugin['status'] === 'FAIL' && $wpsr_plugin['reason'] === 'child-failure'
                && $wpsr_plugin['code'] === 'failed-add-on-or-host' && $wpsr_plugin['failure_count'] > 0;
            if ($wpsr_plugin['status'] === 'SKIP' || $wpsr_plugin['family_skip'] !== 0 || $wpsr_plugin['total'] < 1
                || (!$wpsr_pass_outcome && !$wpsr_fail_outcome)) {
                $wpsr_errors[] = 'available family sweep plugin outcome malformed: ' . $wpsr_label;
            }
        } elseif ($wpsr_plugin['status'] !== 'SKIP' || $wpsr_plugin['family_skip'] !== 1 || $wpsr_plugin['total'] !== 0
            || $wpsr_plugin['failure_count'] !== 0
            || $wpsr_plugin['reason'] !== 'optional-sibling-missing' || $wpsr_plugin['code'] !== 'optional-sibling-missing') {
            $wpsr_errors[] = 'unavailable family sweep plugin outcome malformed: ' . $wpsr_label;
        }
        if ($wpsr_counts_valid) {
            $wpsr_observed_totals['plugins']++;
            $wpsr_observed_totals['available'] += $wpsr_plugin['available'] ? 1 : 0;
            $wpsr_observed_totals['executed'] += $wpsr_plugin['available'] ? 1 : 0;
            $wpsr_observed_totals['family_skips'] += $wpsr_plugin['family_skip'];
            $wpsr_observed_failures += $wpsr_plugin['failure_count'];
            foreach (['suites', 'files', 'pass', 'fail', 'skip', 'total'] as $wpsr_field) {
                $wpsr_observed_totals[$wpsr_field] += $wpsr_plugin[$wpsr_field];
            }
        }
    }
    if (count($wpsr_artifact['plugins']) !== count($wpsr_expected_plan)
        || count($wpsr_artifact['plugins']) !== ($wpsr_artifact['aggregate']['plugins'] ?? -1)) {
        $wpsr_errors[] = 'family sweep artifact plugin count mismatch';
    }
    if ($wpsr_observed_totals !== $wpsr_artifact['aggregate']) {
        $wpsr_errors[] = 'family sweep artifact plugin rows do not reconcile with aggregate';
    }
    $wpsr_event_keys = ['id', 'owner_plugin', 'suite', 'test', 'reason_category', 'reason', 'evidence', 'expires_on', 'revisit'];
    $wpsr_seen_events = [];
    foreach ($wpsr_artifact['family_skip_events'] as $wpsr_event) {
        if (!wpsr_skip_exact_keys($wpsr_event, $wpsr_event_keys, 'family sweep SKIP event', $wpsr_errors)) {
            continue;
        }
        $wpsr_event_id = $wpsr_event['id'];
        if (!is_string($wpsr_event_id)) {
            $wpsr_errors[] = 'family sweep SKIP event identity duplicated or malformed';
            $wpsr_row = null;
        } elseif (isset($wpsr_seen_events[$wpsr_event_id])) {
            $wpsr_errors[] = 'family sweep SKIP event identity duplicated or malformed';
            $wpsr_row = $wpsr_skip_rows[$wpsr_event_id] ?? null;
        } else {
            $wpsr_seen_events[$wpsr_event_id] = true;
            $wpsr_row = $wpsr_skip_rows[$wpsr_event_id] ?? null;
        }
        foreach ($wpsr_event_keys as $wpsr_field) {
            if (!is_array($wpsr_row) || $wpsr_event[$wpsr_field] !== ($wpsr_row[$wpsr_field] ?? null)) {
                $wpsr_errors[] = 'family sweep SKIP event registry mismatch';
            }
        }
    }
    $wpsr_expected_event_ids = [];
    foreach ($wpsr_expected_plan as $wpsr_expected_plugin) {
        if (empty($wpsr_expected_plugin['available']) && is_string($wpsr_expected_plugin['skip_id'] ?? null)) {
            $wpsr_expected_event_ids[] = $wpsr_expected_plugin['skip_id'];
        }
    }
    if (array_keys($wpsr_seen_events) !== $wpsr_expected_event_ids
        || count($wpsr_artifact['family_skip_events']) !== ($wpsr_artifact['aggregate']['family_skips'] ?? -1)) {
        $wpsr_errors[] = 'family sweep artifact SKIP identities/count mismatch';
    }
    if ($wpsr_artifact['failures'] !== $wpsr_observed_failures
        || (($wpsr_artifact['failures'] === 0) !== !in_array('FAIL', array_column($wpsr_artifact['plugins'], 'status'), true))) {
        $wpsr_errors[] = 'family sweep artifact failure/status mismatch';
    }
    return array_values(array_unique($wpsr_errors));
}

function wpsr_sweep_reconcile(array $wpsr_plan, array $wpsr_executions, array $wpsr_family_skips, array $wpsr_claimed = null)
{
    $wpsr_errors = [];
    $wpsr_by_plugin = [];
    foreach ($wpsr_executions as $wpsr_execution) {
        $wpsr_id = $wpsr_execution['plugin'] ?? '';
        if (isset($wpsr_by_plugin[$wpsr_id])) {
            $wpsr_errors[] = 'duplicate plugin execution: ' . $wpsr_id;
        }
        $wpsr_by_plugin[$wpsr_id] = $wpsr_execution;
        foreach ((array) ($wpsr_execution['errors'] ?? []) as $wpsr_error) {
            $wpsr_errors[] = $wpsr_error;
        }
    }
    $wpsr_skip_by_owner = [];
    foreach ($wpsr_family_skips as $wpsr_skip) {
        $wpsr_owner = $wpsr_skip['owner_plugin'] ?? '';
        if (isset($wpsr_skip_by_owner[$wpsr_owner])) {
            $wpsr_errors[] = 'duplicate family SKIP owner: ' . $wpsr_owner;
        }
        $wpsr_skip_by_owner[$wpsr_owner] = $wpsr_skip;
    }
    $wpsr_totals = ['plugins' => count($wpsr_plan), 'available' => 0, 'executed' => 0, 'family_skips' => 0, 'suites' => 0, 'files' => 0, 'pass' => 0, 'fail' => 0, 'skip' => 0, 'total' => 0];
    foreach ($wpsr_plan as $wpsr_item) {
        $wpsr_id = $wpsr_item['id'];
        if ($wpsr_item['available']) {
            $wpsr_totals['available']++;
            if (!isset($wpsr_by_plugin[$wpsr_id])) {
                $wpsr_errors[] = 'missing available plugin execution: ' . $wpsr_id;
                continue;
            }
            if (isset($wpsr_skip_by_owner[$wpsr_id])) {
                $wpsr_errors[] = 'available plugin cannot be SKIP: ' . $wpsr_id;
            }
            $wpsr_execution = $wpsr_by_plugin[$wpsr_id];
            if (($wpsr_execution['total'] ?? 0) < 1) {
                $wpsr_errors[] = 'zero executed tests: ' . $wpsr_id;
            }
            $wpsr_totals['executed']++;
            $wpsr_totals['suites'] += $wpsr_item['suites'];
            $wpsr_totals['files'] += $wpsr_item['files'];
            foreach (['pass', 'fail', 'skip', 'total'] as $wpsr_field) {
                $wpsr_totals[$wpsr_field] += (int) ($wpsr_execution[$wpsr_field] ?? 0);
            }
        } else {
            if (isset($wpsr_by_plugin[$wpsr_id])) {
                $wpsr_errors[] = 'unavailable plugin unexpectedly executed: ' . $wpsr_id;
            }
            $wpsr_skip = $wpsr_skip_by_owner[$wpsr_id] ?? null;
            if (!$wpsr_skip || ($wpsr_skip['id'] ?? '') !== $wpsr_item['skip_id']) {
                $wpsr_errors[] = 'missing optional explicit SKIP: ' . $wpsr_id;
            } else {
                $wpsr_totals['family_skips']++;
            }
        }
    }
    foreach (array_keys($wpsr_by_plugin) as $wpsr_id) {
        if (!in_array($wpsr_id, array_column($wpsr_plan, 'id'), true)) {
            $wpsr_errors[] = 'unknown/unclassified plugin execution: ' . $wpsr_id;
        }
    }
    if ($wpsr_totals['pass'] + $wpsr_totals['fail'] + $wpsr_totals['skip'] !== $wpsr_totals['total']) {
        $wpsr_errors[] = 'aggregate pass/fail/skip count mismatch';
    }
    if ($wpsr_totals['executed'] + $wpsr_totals['family_skips'] !== $wpsr_totals['plugins']) {
        $wpsr_errors[] = 'plugin execution/SKIP reconciliation mismatch';
    }
    if ($wpsr_claimed !== null && $wpsr_claimed !== $wpsr_totals) {
        $wpsr_errors[] = 'claimed summary count mismatch';
    }
    return ['totals' => $wpsr_totals, 'errors' => array_values(array_unique($wpsr_errors))];
}
