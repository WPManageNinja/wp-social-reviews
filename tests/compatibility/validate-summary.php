<?php
/** Strict compatibility matrix aggregate artifact validator. */
require_once __DIR__ . '/runtime-result-lib.php';

$wpsr_summary_path = $argv[1] ?? '';
$wpsr_matrix_path = $argv[2] ?? '';
$wpsr_root = dirname(__DIR__, 2);
$wpsr_summary_source = is_file($wpsr_summary_path) ? file_get_contents($wpsr_summary_path) : false;
$wpsr_summary_object = is_string($wpsr_summary_source) ? json_decode($wpsr_summary_source) : null;
$wpsr_summary = is_string($wpsr_summary_source) ? json_decode($wpsr_summary_source, true) : null;
$wpsr_errors = [];
if (!is_object($wpsr_summary_object) || !is_array($wpsr_summary) || json_last_error() !== JSON_ERROR_NONE
    || wpsr_compatibility_json_has_ambiguous_objects($wpsr_summary_object)) {
    $wpsr_errors[] = 'summary must be valid JSON object with unambiguous nested shapes';
} else {
    wpsr_compatibility_exact_keys($wpsr_summary, [
        'schema', 'matrix', 'selector', 'selected_cells', 'historical_skip_ids', 'cells', 'pass',
        'expected_incompatible', 'fail', 'skip', 'runtime_seconds', 'results',
    ], 'summary', $wpsr_errors);
    wpsr_compatibility_exact_keys($wpsr_summary['matrix'] ?? null, ['family', 'source_sha256', 'repositories'], 'summary.matrix', $wpsr_errors);
    wpsr_compatibility_exact_keys($wpsr_summary['matrix']['repositories'] ?? null, ['core', 'pro'], 'summary.matrix.repositories', $wpsr_errors);
}
try {
    $wpsr_matrix = wpsr_compatibility_load($wpsr_matrix_path);
    foreach (wpsr_compatibility_validate($wpsr_matrix, $wpsr_root) as $wpsr_matrix_error) {
        $wpsr_errors[] = 'matrix invalid: ' . $wpsr_matrix_error;
    }
} catch (Throwable $wpsr_exception) {
    $wpsr_errors[] = $wpsr_exception->getMessage();
    $wpsr_matrix = [];
}
if (!$wpsr_errors) {
    if ($wpsr_summary['schema'] !== 1 || $wpsr_summary['matrix']['family'] !== ($wpsr_matrix['family'] ?? null)
        || $wpsr_summary['matrix']['source_sha256'] !== hash_file('sha256', $wpsr_matrix_path)) {
        $wpsr_errors[] = 'summary matrix identity mismatch';
    }
    foreach (['core', 'pro'] as $wpsr_plugin) {
        $wpsr_commit = wpsr_compatibility_git(wpsr_compatibility_plugin_root($wpsr_root, $wpsr_plugin), ['rev-parse', 'HEAD'], $wpsr_code);
        if (!is_string($wpsr_summary['matrix']['repositories'][$wpsr_plugin] ?? null)
            || $wpsr_summary['matrix']['repositories'][$wpsr_plugin] !== $wpsr_commit) {
            $wpsr_errors[] = 'summary repository commit mismatch: ' . $wpsr_plugin;
        }
    }

    $wpsr_matrix_cells = [];
    $wpsr_incompatible_cells = [];
    foreach ($wpsr_matrix['cells'] as $wpsr_cell) {
        $wpsr_cell_id = $wpsr_cell['id'];
        $wpsr_matrix_cells[$wpsr_cell_id] = $wpsr_cell;
        if (($wpsr_cell['expected']['compatibility'] ?? null) === 'incompatible') {
            $wpsr_incompatible_cells[$wpsr_cell_id] = true;
        }
    }
    $wpsr_selector = $wpsr_summary['selector'];
    if ($wpsr_selector === 'all') {
        $wpsr_expected_selected = array_keys($wpsr_matrix_cells);
    } elseif (is_string($wpsr_selector) && isset($wpsr_matrix_cells[$wpsr_selector])) {
        $wpsr_expected_selected = [$wpsr_selector];
    } else {
        $wpsr_expected_selected = [];
        $wpsr_errors[] = 'summary selector is unknown or malformed';
    }
    if (!is_array($wpsr_summary['selected_cells']) || !wpsr_compatibility_is_list($wpsr_summary['selected_cells'])
        || $wpsr_summary['selected_cells'] !== $wpsr_expected_selected) {
        $wpsr_errors[] = 'summary selected cell identity mismatch';
    }
    $wpsr_expected_historical_skips = array_values(array_map(static function ($wpsr_skip) {
        return $wpsr_skip['id'];
    }, $wpsr_matrix['historical_unavailable']));
    if (!is_array($wpsr_summary['historical_skip_ids']) || !wpsr_compatibility_is_list($wpsr_summary['historical_skip_ids'])
        || $wpsr_summary['historical_skip_ids'] !== $wpsr_expected_historical_skips) {
        $wpsr_errors[] = 'summary historical SKIP identity mismatch';
    }

    $wpsr_counts_valid = true;
    foreach (['cells', 'pass', 'expected_incompatible', 'fail', 'skip'] as $wpsr_field) {
        if (!is_int($wpsr_summary[$wpsr_field]) || $wpsr_summary[$wpsr_field] < 0) {
            $wpsr_errors[] = 'summary count malformed: ' . $wpsr_field;
            $wpsr_counts_valid = false;
        }
    }
    if ((!is_int($wpsr_summary['runtime_seconds']) && !is_float($wpsr_summary['runtime_seconds'])) || $wpsr_summary['runtime_seconds'] < 0) {
        $wpsr_errors[] = 'summary runtime malformed';
    }
    if ($wpsr_counts_valid) {
        $wpsr_expected_incompatible = count(array_intersect($wpsr_expected_selected, array_keys($wpsr_incompatible_cells)));
        if ($wpsr_summary['expected_incompatible'] !== $wpsr_expected_incompatible) {
            $wpsr_errors[] = 'summary expected-incompatible count mismatch';
        }
        if ($wpsr_summary['cells'] !== count($wpsr_expected_selected) + count($wpsr_expected_historical_skips)
            || $wpsr_summary['pass'] + $wpsr_summary['fail'] !== count($wpsr_expected_selected)
            || $wpsr_summary['skip'] !== count($wpsr_expected_historical_skips)
            || $wpsr_summary['cells'] !== $wpsr_summary['pass'] + $wpsr_summary['fail'] + $wpsr_summary['skip']) {
            $wpsr_errors[] = 'summary aggregate counts do not reconcile with selected matrix census';
        }
    }

    if (!is_array($wpsr_summary['results']) || !wpsr_compatibility_is_list($wpsr_summary['results'])) {
        $wpsr_errors[] = 'summary results must be array';
    } else {
        $wpsr_seen = [];
        foreach ($wpsr_summary['results'] as $wpsr_index => $wpsr_result) {
            wpsr_compatibility_exact_keys($wpsr_result, ['schema', 'cell', 'status', 'child_exit', 'runtime_seconds', 'fixture_hashes', 'runtime', 'log'], 'summary result ' . $wpsr_index, $wpsr_errors);
            $wpsr_cell_id = $wpsr_result['cell'] ?? '';
            if (!is_string($wpsr_cell_id) || isset($wpsr_seen[$wpsr_cell_id]) || !in_array($wpsr_cell_id, $wpsr_expected_selected, true)
                || ($wpsr_result['schema'] ?? null) !== 1 || ($wpsr_result['status'] ?? null) !== 'PASS'
                || ($wpsr_result['child_exit'] ?? null) !== 0) {
                $wpsr_errors[] = 'summary result identity/status malformed';
            }
            $wpsr_seen[$wpsr_cell_id] = true;
            $wpsr_cell = $wpsr_matrix_cells[$wpsr_cell_id] ?? null;
            if (!is_array($wpsr_cell) || !is_array($wpsr_result['runtime'] ?? null)) {
                $wpsr_errors[] = 'summary result references unknown/malformed cell';
                continue;
            }
            $wpsr_core_artifact = $wpsr_matrix['artifacts'][$wpsr_cell['core']] ?? [];
            $wpsr_pro_artifact = $wpsr_cell['pro'] === null ? null : ($wpsr_matrix['artifacts'][$wpsr_cell['pro']] ?? []);
            $wpsr_cell['resolved'] = [
                'core_version' => $wpsr_core_artifact['version'] ?? null,
                'pro_version' => is_array($wpsr_pro_artifact) ? ($wpsr_pro_artifact['version'] ?? null) : '',
                'commits' => [
                    'core' => wpsr_compatibility_git($wpsr_root, ['rev-parse', ($wpsr_core_artifact['ref'] ?? '') . '^{commit}'], $wpsr_core_code),
                    'pro' => is_array($wpsr_pro_artifact) ? wpsr_compatibility_git(wpsr_compatibility_plugin_root($wpsr_root, 'pro'), ['rev-parse', ($wpsr_pro_artifact['ref'] ?? '') . '^{commit}'], $wpsr_pro_code) : null,
                ],
                'images' => $wpsr_result['runtime']['images'] ?? null,
                'dimension' => $wpsr_matrix['dimensions'][$wpsr_cell['dimension']] ?? null,
            ];
            foreach (wpsr_compatibility_validate_result_artifact($wpsr_result, $wpsr_cell) as $wpsr_result_error) {
                $wpsr_errors[] = 'summary result ' . $wpsr_cell_id . ': ' . $wpsr_result_error;
            }
        }
        if (count($wpsr_summary['results']) !== $wpsr_summary['pass']) {
            $wpsr_errors[] = 'summary result/pass count mismatch';
        }
        $wpsr_result_ids = array_keys($wpsr_seen);
        if ($wpsr_summary['fail'] === 0 && $wpsr_result_ids !== $wpsr_expected_selected) {
            $wpsr_errors[] = 'successful summary result identities do not match selected cells';
        }
    }
}
foreach (array_values(array_unique($wpsr_errors)) as $wpsr_error) {
    fwrite(STDERR, 'COMPATIBILITY-SUMMARY FAIL ' . $wpsr_error . "\n");
}
if ($wpsr_errors) {
    exit(WPSR_COMPATIBILITY_FAIL);
}
echo 'COMPATIBILITY-SUMMARY PASS artifact=' . $wpsr_summary_path . "\n";
