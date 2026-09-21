<?php
/** Strict parser and artifact validator for compatibility child runtime events. */
require_once __DIR__ . '/matrix-lib.php';

function wpsr_compatibility_runtime_marker()
{
    return 'COMPATIBILITY-CELL-JSON ';
}

function wpsr_compatibility_parse_runtime_transcript($wpsr_source)
{
    if (!is_string($wpsr_source)) {
        return ['event' => null, 'errors' => ['runtime transcript is unreadable']];
    }
    $wpsr_payloads = [];
    foreach (preg_split('/\r?\n/', $wpsr_source) as $wpsr_line) {
        if (strpos($wpsr_line, wpsr_compatibility_runtime_marker()) === 0) {
            $wpsr_payloads[] = substr($wpsr_line, strlen(wpsr_compatibility_runtime_marker()));
        }
    }
    if (count($wpsr_payloads) !== 1) {
        return ['event' => null, 'errors' => ['expected exactly one compatibility runtime event; found ' . count($wpsr_payloads)]];
    }
    $wpsr_object = json_decode($wpsr_payloads[0]);
    $wpsr_event = json_decode($wpsr_payloads[0], true);
    if (!is_object($wpsr_object) || !is_array($wpsr_event) || json_last_error() !== JSON_ERROR_NONE
        || wpsr_compatibility_json_has_ambiguous_objects($wpsr_object)) {
        return ['event' => null, 'errors' => ['compatibility runtime event must be valid JSON object with unambiguous nested shapes']];
    }
    return ['event' => $wpsr_event, 'errors' => []];
}

function wpsr_compatibility_validate_runtime_event(array $wpsr_event, array $wpsr_cell, $wpsr_child_exit)
{
    $wpsr_errors = [];
    wpsr_compatibility_exact_keys($wpsr_event, [
        'schema', 'cell', 'purpose', 'expected_compatibility', 'actual_compatibility', 'versions',
        'commits', 'images', 'boot', 'routes', 'checks', 'diagnostic', 'failures',
    ], 'runtime event', $wpsr_errors);
    foreach ([
        'versions' => ['core', 'pro', 'wordpress', 'php', 'database'],
        'commits' => ['core', 'pro'],
        'images' => ['wordpress', 'cli', 'database'],
        'boot' => ['core', 'pro'],
        'routes' => ['core_paths', 'pro_paths', 'pro_declarations_checked'],
        'checks' => ['total', 'lifecycle', 'rest'],
    ] as $wpsr_field => $wpsr_keys) {
        wpsr_compatibility_exact_keys($wpsr_event[$wpsr_field] ?? null, $wpsr_keys, 'runtime event.' . $wpsr_field, $wpsr_errors);
    }
    if ($wpsr_errors) {
        return array_values(array_unique($wpsr_errors));
    }
    $wpsr_expected = $wpsr_cell['expected'] ?? [];
    $wpsr_resolved = $wpsr_cell['resolved'] ?? [];
    if (($wpsr_event['schema'] ?? null) !== 1
        || ($wpsr_event['cell'] ?? null) !== ($wpsr_cell['id'] ?? null)
        || ($wpsr_event['purpose'] ?? null) !== ($wpsr_cell['purpose'] ?? null)
        || ($wpsr_event['expected_compatibility'] ?? null) !== ($wpsr_expected['compatibility'] ?? null)) {
        $wpsr_errors[] = 'runtime event cell identity/purpose/expectation mismatch';
    }
    if (!is_int($wpsr_child_exit) || $wpsr_child_exit !== 0) {
        $wpsr_errors[] = 'compatibility child exit was nonzero';
    }
    if (($wpsr_event['actual_compatibility'] ?? null) !== ($wpsr_expected['compatibility'] ?? null)) {
        $wpsr_errors[] = 'runtime actual/expected compatibility mismatch';
    }
    if (($wpsr_event['versions']['core'] ?? null) !== ($wpsr_resolved['core_version'] ?? null)
        || ($wpsr_event['versions']['pro'] ?? null) !== (($wpsr_resolved['pro_version'] ?? '') !== '' ? $wpsr_resolved['pro_version'] : null)) {
        $wpsr_errors[] = 'runtime resolved plugin versions mismatch';
    }
    foreach (['wordpress', 'php', 'database'] as $wpsr_field) {
        if (!is_string($wpsr_event['versions'][$wpsr_field] ?? null) || $wpsr_event['versions'][$wpsr_field] === '') {
            $wpsr_errors[] = 'runtime version field malformed: ' . $wpsr_field;
        }
    }
    $wpsr_dimension = $wpsr_resolved['dimension'] ?? [];
    $wpsr_php = (string) ($wpsr_dimension['php'] ?? '');
    $wpsr_wordpress = (string) ($wpsr_dimension['wordpress'] ?? '');
    if ($wpsr_php === '' || strpos((string) $wpsr_event['versions']['php'], $wpsr_php . '.') !== 0) {
        $wpsr_errors[] = 'runtime PHP dimension mismatch';
    }
    if ($wpsr_wordpress === '') {
        $wpsr_errors[] = 'runtime WordPress dimension missing';
    } elseif ($wpsr_wordpress !== 'current-image'
        && (string) $wpsr_event['versions']['wordpress'] !== $wpsr_wordpress
        && strpos((string) $wpsr_event['versions']['wordpress'], $wpsr_wordpress . '.') !== 0) {
        $wpsr_errors[] = 'runtime WordPress dimension mismatch';
    }
    if ($wpsr_event['commits'] !== ($wpsr_resolved['commits'] ?? null)) {
        $wpsr_errors[] = 'runtime resolved commits mismatch';
    }
    if ($wpsr_event['images'] !== ($wpsr_resolved['images'] ?? null)) {
        $wpsr_errors[] = 'runtime resolved image digests mismatch';
    }
    foreach ($wpsr_event['images'] as $wpsr_image) {
        if (!is_string($wpsr_image) || !preg_match('/^sha256:[0-9a-f]{64}$/', $wpsr_image)) {
            $wpsr_errors[] = 'runtime image digest malformed';
        }
    }
    foreach (['core', 'pro'] as $wpsr_plugin) {
        if (!is_bool($wpsr_event['boot'][$wpsr_plugin] ?? null)
            || $wpsr_event['boot'][$wpsr_plugin] !== (bool) ($wpsr_expected[$wpsr_plugin . '_boot'] ?? null)) {
            $wpsr_errors[] = 'runtime boot outcome mismatch: ' . $wpsr_plugin;
        }
    }
    foreach ($wpsr_event['routes'] as $wpsr_field => $wpsr_count) {
        if (!is_int($wpsr_count) || $wpsr_count < 0) {
            $wpsr_errors[] = 'runtime route count malformed: ' . $wpsr_field;
        }
    }
    if (($wpsr_event['routes']['core_paths'] ?? -1) < ($wpsr_expected['core_routes_min'] ?? PHP_INT_MAX)
        || ($wpsr_event['routes']['pro_declarations_checked'] ?? -1) !== ($wpsr_expected['pro_route_declarations'] ?? null)
        || (($wpsr_expected['pro_route_declarations'] ?? 0) === 0 && ($wpsr_event['routes']['pro_paths'] ?? -1) !== 0)
        || (($wpsr_expected['pro_route_declarations'] ?? 0) > 0 && ($wpsr_event['routes']['pro_paths'] ?? 0) < 1)) {
        $wpsr_errors[] = 'runtime route outcomes do not reconcile with cell expectations';
    }
    foreach ($wpsr_event['checks'] as $wpsr_field => $wpsr_count) {
        if (!is_int($wpsr_count) || $wpsr_count < 0) {
            $wpsr_errors[] = 'runtime check count malformed: ' . $wpsr_field;
        }
    }
    if (($wpsr_event['checks']['total'] ?? -1) !== ($wpsr_event['checks']['lifecycle'] ?? -2) + ($wpsr_event['checks']['rest'] ?? -2)
        || ($wpsr_event['checks']['lifecycle'] ?? -1) < ($wpsr_expected['lifecycle_checks_min'] ?? PHP_INT_MAX)
        || ($wpsr_event['checks']['rest'] ?? -1) < ($wpsr_expected['rest_checks_min'] ?? PHP_INT_MAX)) {
        $wpsr_errors[] = 'runtime check counts do not reconcile';
    }
    if (!is_string($wpsr_event['diagnostic'] ?? null)) {
        $wpsr_errors[] = 'runtime diagnostic must be string';
    } elseif (($wpsr_expected['diagnostic'] ?? null) !== null
        && strpos($wpsr_event['diagnostic'], $wpsr_expected['diagnostic']) === false) {
        $wpsr_errors[] = 'runtime dependency diagnostic mismatch';
    } elseif (($wpsr_expected['diagnostic'] ?? null) === null && $wpsr_event['diagnostic'] !== '') {
        $wpsr_errors[] = 'runtime emitted unexpected dependency diagnostic';
    }
    if (!is_array($wpsr_event['failures'] ?? null) || !wpsr_compatibility_is_list($wpsr_event['failures'])) {
        $wpsr_errors[] = 'runtime failures must be an array';
    } elseif ($wpsr_event['failures'] !== []) {
        $wpsr_errors[] = 'successful compatibility child reported failures';
    }
    return array_values(array_unique($wpsr_errors));
}

function wpsr_compatibility_validate_result_artifact(array $wpsr_result, array $wpsr_cell)
{
    $wpsr_errors = [];
    wpsr_compatibility_exact_keys($wpsr_result, ['schema', 'cell', 'status', 'child_exit', 'runtime_seconds', 'fixture_hashes', 'runtime', 'log'], 'result artifact', $wpsr_errors);
    wpsr_compatibility_exact_keys($wpsr_result['fixture_hashes'] ?? null, ['core', 'pro'], 'result artifact.fixture_hashes', $wpsr_errors);
    if ($wpsr_errors) {
        return $wpsr_errors;
    }
    if ($wpsr_result['schema'] !== 1 || $wpsr_result['cell'] !== ($wpsr_cell['id'] ?? null)
        || $wpsr_result['status'] !== 'PASS' || $wpsr_result['child_exit'] !== 0
        || (!is_int($wpsr_result['runtime_seconds']) && !is_float($wpsr_result['runtime_seconds']))
        || $wpsr_result['runtime_seconds'] < 0 || !is_string($wpsr_result['log']) || $wpsr_result['log'] === '') {
        $wpsr_errors[] = 'result artifact scalar metadata malformed';
    }
    foreach ($wpsr_result['fixture_hashes'] as $wpsr_plugin => $wpsr_hash) {
        $wpsr_required = $wpsr_plugin === 'core' || ($wpsr_cell['pro'] ?? null) !== null;
        if ($wpsr_required && (!is_string($wpsr_hash) || !preg_match('/^[0-9a-f]{64}$/', $wpsr_hash))) {
            $wpsr_errors[] = 'result artifact fixture hash malformed: ' . $wpsr_plugin;
        }
        if (!$wpsr_required && $wpsr_hash !== null) {
            $wpsr_errors[] = 'free-alone result artifact has Pro fixture hash';
        }
    }
    if (!is_array($wpsr_result['runtime'])) {
        $wpsr_errors[] = 'result artifact runtime event missing';
    } else {
        $wpsr_errors = array_merge($wpsr_errors, wpsr_compatibility_validate_runtime_event($wpsr_result['runtime'], $wpsr_cell, $wpsr_result['child_exit']));
    }
    return array_values(array_unique($wpsr_errors));
}
