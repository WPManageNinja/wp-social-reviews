<?php
/** Strict stable-ID SKIP registry validation and runtime lookup. */

const WPSR_SKIP_PASS = 0;
const WPSR_SKIP_FAIL = 1;
const WPSR_SKIP_MISUSE = 2;

function wpsr_skip_is_list(array $wpsr_value)
{
    return $wpsr_value === [] || array_keys($wpsr_value) === range(0, count($wpsr_value) - 1);
}

function wpsr_skip_exact_keys($wpsr_value, array $wpsr_expected, $wpsr_label, array &$wpsr_errors)
{
    if (!is_array($wpsr_value) || wpsr_skip_is_list($wpsr_value)) {
        $wpsr_errors[] = $wpsr_label . ' must be an object';
        return false;
    }
    $wpsr_actual = array_keys($wpsr_value);
    sort($wpsr_actual, SORT_STRING);
    sort($wpsr_expected, SORT_STRING);
    if ($wpsr_actual !== $wpsr_expected) {
        $wpsr_errors[] = $wpsr_label . ' keys mismatch';
        return false;
    }
    return true;
}

function wpsr_skip_load_runtime_events($wpsr_path)
{
    if (!is_file($wpsr_path) || !is_readable($wpsr_path)) {
        throw new RuntimeException('runtime SKIP events missing');
    }
    $wpsr_source = file_get_contents($wpsr_path);
    $wpsr_object = is_string($wpsr_source) ? json_decode($wpsr_source) : null;
    $wpsr_events = is_string($wpsr_source) ? json_decode($wpsr_source, true) : null;
    if (!is_array($wpsr_object) || !is_array($wpsr_events) || !wpsr_skip_is_list($wpsr_events)
        || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('runtime SKIP events must be a JSON array');
    }
    return $wpsr_events;
}

function wpsr_skip_load($wpsr_path)
{
    if (!is_file($wpsr_path)) {
        throw new RuntimeException('SKIP registry missing: ' . $wpsr_path);
    }
    $wpsr_registry = json_decode((string) file_get_contents($wpsr_path), true);
    if (!is_array($wpsr_registry) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('SKIP registry malformed: ' . json_last_error_msg());
    }
    return $wpsr_registry;
}

function wpsr_skip_rows_by_id(array $wpsr_registry)
{
    $wpsr_rows = [];
    foreach ((array) ($wpsr_registry['skips'] ?? []) as $wpsr_row) {
        if (is_array($wpsr_row) && is_string($wpsr_row['id'] ?? null)) {
            $wpsr_rows[$wpsr_row['id']] = $wpsr_row;
        }
    }
    return $wpsr_rows;
}

function wpsr_skip_family($wpsr_root)
{
    $wpsr_path = $wpsr_root . '/tests/family/plugins.json';
    if (!is_file($wpsr_path)) {
        throw new RuntimeException('family registry missing: ' . $wpsr_path);
    }
    $wpsr_family = json_decode((string) file_get_contents($wpsr_path), true);
    if (!is_array($wpsr_family) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('family registry malformed: ' . json_last_error_msg());
    }
    return $wpsr_family;
}

function wpsr_skip_fix_plan_ids($wpsr_path)
{
    $wpsr_ids = [];
    foreach (is_file($wpsr_path) ? (file($wpsr_path, FILE_IGNORE_NEW_LINES) ?: []) : [] as $wpsr_line) {
        if (preg_match('/^- `([a-z0-9]+(?:-[a-z0-9]+)*)` —/', $wpsr_line, $wpsr_match)
            || preg_match('/^### <a id="([a-z0-9]+(?:-[a-z0-9]+)*)"><\/a>`\1`$/', $wpsr_line, $wpsr_match)) {
            $wpsr_ids[$wpsr_match[1]] = true;
        }
    }
    return $wpsr_ids;
}

function wpsr_skip_relative_path($wpsr_root, $wpsr_path)
{
    $wpsr_real_root = rtrim(str_replace('\\', '/', realpath($wpsr_root) ?: $wpsr_root), '/');
    $wpsr_real_path = str_replace('\\', '/', realpath($wpsr_path) ?: $wpsr_path);
    return strpos($wpsr_real_path, $wpsr_real_root . '/') === 0
        ? substr($wpsr_real_path, strlen($wpsr_real_root) + 1)
        : $wpsr_real_path;
}

function wpsr_skip_discover_declarations($wpsr_root)
{
    $wpsr_found = [];
    $wpsr_iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($wpsr_root . '/tests', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($wpsr_iterator as $wpsr_file) {
        $wpsr_path = str_replace('\\', '/', $wpsr_file->getPathname());
        $wpsr_extension = strtolower($wpsr_file->getExtension());
        if (!$wpsr_file->isFile()
            || strpos($wpsr_path, '/node_modules/') !== false
            || strpos($wpsr_path, '/.build/') !== false
            || strpos($wpsr_path, '/.artifacts/') !== false
            || !in_array($wpsr_extension, ['php', 'js'], true)) {
            continue;
        }
        $wpsr_source = file_get_contents($wpsr_file->getPathname());
        if (!is_string($wpsr_source)) {
            continue;
        }
        $wpsr_matches = [];
        if ($wpsr_extension === 'php') {
            $wpsr_clean = '';
            foreach (token_get_all($wpsr_source) as $wpsr_token) {
                if (is_array($wpsr_token) && in_array($wpsr_token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $wpsr_clean .= is_array($wpsr_token) ? $wpsr_token[1] : $wpsr_token;
            }
            preg_match_all("/WpsrTest::(skip|parkKnownFailure)\\s*\\(\\s*'([^']+)'/", $wpsr_clean, $wpsr_calls, PREG_SET_ORDER);
            foreach ($wpsr_calls as $wpsr_call) {
                $wpsr_matches[] = ['method' => $wpsr_call[1], 'id' => $wpsr_call[2]];
            }
        } else {
            preg_match_all("/const\\s+wpsrId\\s*=\\s*'([^']+)'/", $wpsr_source, $wpsr_calls, PREG_SET_ORDER);
            foreach ($wpsr_calls as $wpsr_call) {
                $wpsr_matches[] = ['method' => 'activeKnownFailure', 'id' => $wpsr_call[1]];
            }
        }
        foreach ($wpsr_matches as $wpsr_match) {
            $wpsr_found[] = [
                'id' => $wpsr_match['id'],
                'method' => $wpsr_match['method'],
                'path' => wpsr_skip_relative_path($wpsr_root, $wpsr_file->getPathname()),
            ];
        }
    }

    $wpsr_family = wpsr_skip_family($wpsr_root);
    foreach ((array) ($wpsr_family['plugins'] ?? []) as $wpsr_plugin) {
        $wpsr_skip_id = $wpsr_plugin['missing_sibling']['skip_id'] ?? '';
        if (is_string($wpsr_skip_id) && $wpsr_skip_id !== '') {
            $wpsr_found[] = [
                'id' => $wpsr_skip_id,
                'method' => 'familyOptionalSibling',
                'path' => 'tests/family/plugins.json',
            ];
        }
    }
    usort($wpsr_found, static function ($wpsr_left, $wpsr_right) {
        return [$wpsr_left['id'], $wpsr_left['path']] <=> [$wpsr_right['id'], $wpsr_right['path']];
    });
    return $wpsr_found;
}

function wpsr_skip_validate(array $wpsr_registry, $wpsr_root, array $wpsr_runtime_events = [])
{
    $wpsr_errors = [];
    if (!wpsr_skip_exact_keys($wpsr_registry, ['schema', 'allowed_reason_categories', 'skips'], 'SKIP registry', $wpsr_errors)) {
        return $wpsr_errors;
    }
    if (($wpsr_registry['schema'] ?? null) !== 1 || empty($wpsr_registry['skips']) || !is_array($wpsr_registry['skips'])
        || !wpsr_skip_is_list($wpsr_registry['skips'])) {
        return ['SKIP registry schema must be 1 with nonzero skips array'];
    }
    $wpsr_expected_categories = ['known-failure', 'optional-sibling-missing'];
    if (($wpsr_registry['allowed_reason_categories'] ?? null) !== $wpsr_expected_categories) {
        $wpsr_errors[] = 'allowed reason categories drifted';
    }

    try {
        $wpsr_family = wpsr_skip_family($wpsr_root);
    } catch (Throwable $wpsr_exception) {
        return [$wpsr_exception->getMessage()];
    }
    $wpsr_plugins = [];
    $wpsr_family_skips = [];
    foreach ((array) ($wpsr_family['plugins'] ?? []) as $wpsr_plugin) {
        $wpsr_id = $wpsr_plugin['id'] ?? '';
        if (is_string($wpsr_id) && $wpsr_id !== '') {
            $wpsr_plugins[$wpsr_id] = $wpsr_plugin;
        }
        $wpsr_skip_id = $wpsr_plugin['missing_sibling']['skip_id'] ?? '';
        if (is_string($wpsr_skip_id) && $wpsr_skip_id !== '') {
            $wpsr_family_skips[$wpsr_skip_id] = $wpsr_plugin;
        }
    }

    $wpsr_fix_ids = wpsr_skip_fix_plan_ids($wpsr_root . '/tests/FIX-PLAN.md');
    $wpsr_rows = [];
    $wpsr_default = 0;
    foreach ($wpsr_registry['skips'] as $wpsr_index => $wpsr_row) {
        $wpsr_label = is_array($wpsr_row) && is_string($wpsr_row['id'] ?? null)
            ? $wpsr_row['id'] : 'skips[' . $wpsr_index . ']';
        if (!is_array($wpsr_row)) {
            $wpsr_errors[] = $wpsr_label . ': row must be object';
            continue;
        }
        wpsr_skip_exact_keys($wpsr_row, ['id', 'owner_plugin', 'suite', 'test', 'surface', 'reason_category', 'reason', 'declaration', 'evidence', 'expires_on', 'revisit', 'default_gate'], $wpsr_label, $wpsr_errors);
        $wpsr_id = $wpsr_row['id'] ?? '';
        if (!is_string($wpsr_id) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $wpsr_id)) {
            $wpsr_errors[] = $wpsr_label . ': invalid stable ID';
        } elseif (isset($wpsr_rows[$wpsr_id])) {
            $wpsr_errors[] = $wpsr_label . ': duplicate stable ID';
        } else {
            $wpsr_rows[$wpsr_id] = $wpsr_row;
        }
        if (!isset($wpsr_plugins[$wpsr_row['owner_plugin'] ?? ''])) {
            $wpsr_errors[] = $wpsr_label . ': owner plugin is not registered family member';
        }
        $wpsr_category = $wpsr_row['reason_category'] ?? '';
        if (!in_array($wpsr_category, $wpsr_expected_categories, true)) {
            $wpsr_errors[] = $wpsr_label . ': invalid reason category';
        }
        foreach (['suite', 'test', 'surface', 'reason', 'declaration', 'revisit'] as $wpsr_field) {
            $wpsr_minimum = $wpsr_field === 'suite' ? 2 : 3;
            if (!is_string($wpsr_row[$wpsr_field] ?? null) || strlen(trim($wpsr_row[$wpsr_field])) < $wpsr_minimum) {
                $wpsr_errors[] = $wpsr_label . ': blank ' . $wpsr_field;
            }
        }
        if (!is_bool($wpsr_row['default_gate'] ?? null)) {
            $wpsr_errors[] = $wpsr_label . ': default_gate must be boolean';
        } elseif ($wpsr_row['default_gate']) {
            $wpsr_default++;
        }
        $wpsr_expiry = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($wpsr_row['expires_on'] ?? ''));
        if (!$wpsr_expiry || $wpsr_expiry->format('Y-m-d') !== ($wpsr_row['expires_on'] ?? '')
            || $wpsr_expiry < new DateTimeImmutable('today')) {
            $wpsr_errors[] = $wpsr_label . ': stale or malformed expiry';
        }
        $wpsr_declaration = $wpsr_root . '/' . ltrim((string) ($wpsr_row['declaration'] ?? ''), '/');
        if (!is_file($wpsr_declaration)) {
            $wpsr_errors[] = $wpsr_label . ': declaration missing or stale';
        }

        $wpsr_evidence = $wpsr_row['evidence'] ?? null;
        $wpsr_evidence_keys = $wpsr_category === 'known-failure' ? ['type', 'path', 'item'] : ['type', 'path', 'condition'];
        wpsr_skip_exact_keys($wpsr_evidence, $wpsr_evidence_keys, $wpsr_label . '.evidence', $wpsr_errors);
        if (!is_array($wpsr_evidence) || !is_string($wpsr_evidence['path'] ?? null)
            || !is_file($wpsr_root . '/' . ltrim($wpsr_evidence['path'], '/'))) {
            $wpsr_errors[] = $wpsr_label . ': evidence path missing or stale';
            continue;
        }
        if ($wpsr_category === 'known-failure') {
            if (($wpsr_evidence['type'] ?? '') !== 'fix-plan'
                || ($wpsr_evidence['item'] ?? null) !== $wpsr_id
                || !isset($wpsr_fix_ids[$wpsr_id])) {
                $wpsr_errors[] = $wpsr_label . ': known-failure needs exact stable FIX-PLAN anchor';
            }
        } else {
            $wpsr_plugin = $wpsr_family_skips[$wpsr_id] ?? null;
            if (($wpsr_evidence['type'] ?? '') !== 'source-condition'
                || ($wpsr_evidence['path'] ?? '') !== 'tests/family/plugins.json'
                || ($wpsr_evidence['condition'] ?? '') !== 'runtime.availability=optional and missing_sibling.policy=skip'
                || ($wpsr_row['suite'] ?? '') !== 'family'
                || ($wpsr_row['declaration'] ?? '') !== 'tests/family/plugins.json'
                || !$wpsr_plugin
                || ($wpsr_plugin['id'] ?? '') !== ($wpsr_row['owner_plugin'] ?? '')
                || ($wpsr_plugin['runtime']['availability'] ?? '') !== 'optional'
                || ($wpsr_plugin['missing_sibling']['policy'] ?? '') !== 'skip') {
                $wpsr_errors[] = $wpsr_label . ': optional sibling metadata/source condition mismatch';
            }
        }
    }
    if ($wpsr_default !== 0) {
        $wpsr_errors[] = 'default active known-failure inventory must be exactly 0; found ' . $wpsr_default;
    }

    $wpsr_declarations = wpsr_skip_discover_declarations($wpsr_root);
    $wpsr_declared = [];
    foreach ($wpsr_declarations as $wpsr_declaration) {
        $wpsr_id = $wpsr_declaration['id'];
        if (!isset($wpsr_rows[$wpsr_id])) {
            $wpsr_errors[] = 'undeclared runtime/declarative SKIP ' . $wpsr_id;
            continue;
        }
        if (isset($wpsr_declared[$wpsr_id])) {
            $wpsr_errors[] = 'duplicate SKIP declaration ' . $wpsr_id;
        }
        $wpsr_declared[$wpsr_id] = true;
        $wpsr_row = $wpsr_rows[$wpsr_id];
        $wpsr_expected_method = ($wpsr_row['reason_category'] ?? '') === 'optional-sibling-missing'
            ? 'familyOptionalSibling'
            : (($wpsr_row['suite'] ?? '') === 'js' ? 'activeKnownFailure' : 'parkKnownFailure');
        if ($wpsr_declaration['method'] !== $wpsr_expected_method) {
            $wpsr_errors[] = $wpsr_id . ': bypass SKIP method forbidden; expected ' . $wpsr_expected_method;
        }
        if ($wpsr_declaration['path'] !== ltrim((string) ($wpsr_row['declaration'] ?? ''), '/')) {
            $wpsr_errors[] = $wpsr_id . ': declaration path mismatch';
        }
    }
    foreach (array_keys($wpsr_rows) as $wpsr_id) {
        if (!isset($wpsr_declared[$wpsr_id])) {
            $wpsr_errors[] = 'registered SKIP has no active declaration: ' . $wpsr_id;
        }
    }
    if (count($wpsr_declarations) !== count($wpsr_registry['skips'])) {
        $wpsr_errors[] = 'declared/registered SKIP count mismatch: declared=' . count($wpsr_declarations)
            . ' registered=' . count($wpsr_registry['skips']);
    }

    $wpsr_runtime_seen = [];
    $wpsr_event_keys = ['id', 'owner_plugin', 'suite', 'test', 'reason_category', 'reason', 'evidence', 'expires_on', 'revisit'];
    foreach ($wpsr_runtime_events as $wpsr_index => $wpsr_event) {
        $wpsr_label = 'runtime skip[' . $wpsr_index . ']';
        if (!wpsr_skip_exact_keys($wpsr_event, $wpsr_event_keys, $wpsr_label, $wpsr_errors)) {
            continue;
        }
        $wpsr_id = $wpsr_event['id'];
        if (!is_string($wpsr_id) || !isset($wpsr_rows[$wpsr_id])) {
            $wpsr_errors[] = 'undeclared runtime skip: ' . (is_scalar($wpsr_id) ? (string) $wpsr_id : 'malformed');
            continue;
        }
        if (isset($wpsr_runtime_seen[$wpsr_id])) {
            $wpsr_errors[] = 'duplicate runtime skip: ' . $wpsr_id;
        }
        $wpsr_runtime_seen[$wpsr_id] = true;
        foreach ($wpsr_event_keys as $wpsr_field) {
            if ($wpsr_event[$wpsr_field] !== $wpsr_rows[$wpsr_id][$wpsr_field]) {
                $wpsr_errors[] = $wpsr_id . ': runtime ' . $wpsr_field . ' mismatch';
            }
        }
    }
    return array_values(array_unique($wpsr_errors));
}
