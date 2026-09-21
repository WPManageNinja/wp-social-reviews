<?php
/** Phase 31 compatibility-matrix schema, repository identity, source, and outcome contracts. */

const WPSR_COMPATIBILITY_PASS = 0;
const WPSR_COMPATIBILITY_FAIL = 1;
const WPSR_COMPATIBILITY_MISUSE = 2;

function wpsr_compatibility_is_list(array $wpsr_value)
{
    return array_keys($wpsr_value) === range(0, count($wpsr_value) - 1) || $wpsr_value === [];
}

function wpsr_compatibility_exact_keys($wpsr_value, array $wpsr_keys, $wpsr_label, array &$wpsr_errors)
{
    if (!is_array($wpsr_value) || wpsr_compatibility_is_list($wpsr_value)) {
        $wpsr_errors[] = $wpsr_label . ' must be an object';
        return false;
    }
    $wpsr_actual = array_keys($wpsr_value);
    sort($wpsr_actual, SORT_STRING);
    sort($wpsr_keys, SORT_STRING);
    if ($wpsr_actual !== $wpsr_keys) {
        $wpsr_errors[] = $wpsr_label . ' keys mismatch; expected=' . json_encode($wpsr_keys) . ' actual=' . json_encode($wpsr_actual);
        return false;
    }
    return true;
}

function wpsr_compatibility_json_has_ambiguous_objects($wpsr_value)
{
    if (is_object($wpsr_value)) {
        $wpsr_properties = get_object_vars($wpsr_value);
        $wpsr_keys = array_map('strval', array_keys($wpsr_properties));
        if ($wpsr_keys === [] || $wpsr_keys === array_map('strval', range(0, count($wpsr_keys) - 1))) {
            return true;
        }
        foreach ($wpsr_properties as $wpsr_property) {
            if (wpsr_compatibility_json_has_ambiguous_objects($wpsr_property)) {
                return true;
            }
        }
    } elseif (is_array($wpsr_value)) {
        foreach ($wpsr_value as $wpsr_item) {
            if (wpsr_compatibility_json_has_ambiguous_objects($wpsr_item)) {
                return true;
            }
        }
    }
    return false;
}

function wpsr_compatibility_load($wpsr_path)
{
    if (!is_string($wpsr_path) || $wpsr_path === '') {
        throw new RuntimeException('matrix path malformed');
    }
    if (!is_file($wpsr_path)) {
        throw new RuntimeException('matrix missing: ' . $wpsr_path);
    }
    $wpsr_source = file_get_contents($wpsr_path);
    $wpsr_object = is_string($wpsr_source) ? json_decode($wpsr_source) : null;
    if (!is_object($wpsr_object) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('matrix malformed or root is not an object: ' . json_last_error_msg());
    }
    if (wpsr_compatibility_json_has_ambiguous_objects($wpsr_object)) {
        throw new RuntimeException('matrix contains ambiguous JSON object/list shape');
    }
    return json_decode($wpsr_source, true);
}

function wpsr_compatibility_constant($wpsr_path, $wpsr_name)
{
    if (!is_string($wpsr_path) || $wpsr_path === '' || !is_string($wpsr_name) || $wpsr_name === '') {
        return null;
    }
    $wpsr_source = is_file($wpsr_path) ? file_get_contents($wpsr_path) : false;
    if (!is_string($wpsr_source)) {
        return null;
    }
    $wpsr_tokens = token_get_all($wpsr_source);
    $wpsr_count = count($wpsr_tokens);
    foreach ($wpsr_tokens as $wpsr_token_index => $wpsr_source_token) {
        if (is_array($wpsr_source_token) && $wpsr_source_token[0] === T_NAMESPACE) {
            return null;
        }
        if (!is_array($wpsr_source_token) || $wpsr_source_token[0] !== T_USE) {
            continue;
        }
        do {
            $wpsr_token_index++;
        } while ($wpsr_token_index < $wpsr_count && is_array($wpsr_tokens[$wpsr_token_index])
            && in_array($wpsr_tokens[$wpsr_token_index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true));
        if (is_array($wpsr_tokens[$wpsr_token_index] ?? null) && $wpsr_tokens[$wpsr_token_index][0] === T_FUNCTION) {
            return null;
        }
    }
    $wpsr_next = static function (&$wpsr_index) use ($wpsr_tokens, $wpsr_count) {
        do {
            $wpsr_index++;
        } while ($wpsr_index < $wpsr_count && is_array($wpsr_tokens[$wpsr_index])
            && in_array($wpsr_tokens[$wpsr_index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true));
        return $wpsr_tokens[$wpsr_index] ?? null;
    };
    $wpsr_previous = static function ($wpsr_index) use ($wpsr_tokens) {
        do {
            $wpsr_index--;
        } while ($wpsr_index >= 0 && is_array($wpsr_tokens[$wpsr_index])
            && in_array($wpsr_tokens[$wpsr_index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true));
        return $wpsr_tokens[$wpsr_index] ?? null;
    };
    for ($wpsr_index = 0; $wpsr_index < $wpsr_count; $wpsr_index++) {
        $wpsr_token = $wpsr_tokens[$wpsr_index];
        if (!is_array($wpsr_token) || $wpsr_token[0] !== T_STRING || strcasecmp($wpsr_token[1], 'define') !== 0) {
            continue;
        }
        $wpsr_previous_token = $wpsr_previous($wpsr_index);
        $wpsr_previous_id = is_array($wpsr_previous_token) ? $wpsr_previous_token[0] : null;
        if (in_array($wpsr_previous_id, [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)
            || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $wpsr_previous_id === constant('T_NULLSAFE_OBJECT_OPERATOR'))) {
            continue;
        }
        if ($wpsr_next($wpsr_index) !== '(') {
            continue;
        }
        $wpsr_name_token = $wpsr_next($wpsr_index);
        if (!is_array($wpsr_name_token) || $wpsr_name_token[0] !== T_CONSTANT_ENCAPSED_STRING
            || stripcslashes(substr($wpsr_name_token[1], 1, -1)) !== $wpsr_name
            || $wpsr_next($wpsr_index) !== ',') {
            continue;
        }
        $wpsr_value_token = $wpsr_next($wpsr_index);
        if (is_array($wpsr_value_token) && $wpsr_value_token[0] === T_CONSTANT_ENCAPSED_STRING
            && $wpsr_next($wpsr_index) === ')') {
            return stripcslashes(substr($wpsr_value_token[1], 1, -1));
        }
    }
    return null;
}

function wpsr_compatibility_readme_field($wpsr_path, $wpsr_field)
{
    if (!is_string($wpsr_path) || $wpsr_path === '' || !is_string($wpsr_field) || $wpsr_field === '') {
        return null;
    }
    $wpsr_source = is_file($wpsr_path) ? file_get_contents($wpsr_path, false, null, 0, 16384) : false;
    if (!is_string($wpsr_source)
        || preg_match_all('/^' . preg_quote($wpsr_field, '/') . ':\s*(.+?)\s*$/mi', $wpsr_source, $wpsr_matches) !== 1) {
        return null;
    }
    return trim($wpsr_matches[1][0]);
}

function wpsr_compatibility_git($wpsr_repo, array $wpsr_args, &$wpsr_code = null)
{
    $wpsr_code = WPSR_COMPATIBILITY_MISUSE;
    if (!is_string($wpsr_repo) || $wpsr_repo === '' || !is_dir($wpsr_repo) || !$wpsr_args) {
        return null;
    }
    $wpsr_command = 'git -C ' . escapeshellarg($wpsr_repo);
    foreach ($wpsr_args as $wpsr_arg) {
        if (!is_string($wpsr_arg) || $wpsr_arg === '') {
            return null;
        }
        $wpsr_command .= ' ' . escapeshellarg($wpsr_arg);
    }
    $wpsr_lines = [];
    exec($wpsr_command . ' 2>/dev/null', $wpsr_lines, $wpsr_code);
    return $wpsr_code === 0 ? trim(implode("\n", $wpsr_lines)) : null;
}

/**
 * True when HEAD is the declared integration branch or was cut from it.
 * Mirrors wpsr_family_head_tracks_branch() in tests/family/census-lib.php.
 */
function wpsr_compatibility_head_tracks_branch($wpsr_repo, $wpsr_branch)
{
    if (!is_string($wpsr_branch) || $wpsr_branch === '') {
        return false;
    }
    // Every candidate is tried: a stale local branch must not mask an ancestral origin ref.
    foreach ([$wpsr_branch, 'refs/remotes/origin/' . $wpsr_branch] as $wpsr_ref) {
        if (wpsr_compatibility_git($wpsr_repo, ['rev-parse', '--verify', '--quiet', $wpsr_ref . '^{commit}']) === null) {
            continue;
        }
        wpsr_compatibility_git($wpsr_repo, ['merge-base', '--is-ancestor', $wpsr_ref, 'HEAD'], $wpsr_code);
        if ($wpsr_code === 0) {
            return true;
        }
    }
    return false;
}

function wpsr_compatibility_canonical_repository($wpsr_url)
{
    if (!is_string($wpsr_url) || trim($wpsr_url) === '') {
        return null;
    }
    $wpsr_url = trim($wpsr_url);
    $wpsr_url = preg_replace('#^git@([^:]+):#i', 'https://$1/', $wpsr_url);
    if (strpos($wpsr_url, '://') === false) {
        return null;
    }
    $wpsr_parts = parse_url($wpsr_url);
    $wpsr_scheme = is_array($wpsr_parts) ? strtolower((string) ($wpsr_parts['scheme'] ?? '')) : '';
    if (!is_array($wpsr_parts) || !in_array($wpsr_scheme, ['https', 'ssh', 'git'], true)
        || empty($wpsr_parts['host']) || empty($wpsr_parts['path'])
        || isset($wpsr_parts['pass']) || isset($wpsr_parts['port'])
        || isset($wpsr_parts['query']) || isset($wpsr_parts['fragment'])
        || (isset($wpsr_parts['user']) && strtolower($wpsr_parts['user']) !== 'git')) {
        return null;
    }
    return strtolower($wpsr_parts['host'] . '/' . preg_replace('/\.git$/i', '', trim($wpsr_parts['path'], '/')));
}

function wpsr_compatibility_plugin_root($wpsr_root, $wpsr_plugin)
{
    if (!is_string($wpsr_root) || $wpsr_root === '' || !is_string($wpsr_plugin)) {
        return null;
    }
    if ($wpsr_plugin === 'core') {
        return $wpsr_root;
    }
    return $wpsr_plugin === 'pro' ? dirname($wpsr_root) . '/wp-social-ninja-pro' : null;
}

function wpsr_compatibility_validate(array $wpsr_matrix, $wpsr_root, array $wpsr_options = [])
{
    $wpsr_errors = [];
    if (!wpsr_compatibility_exact_keys($wpsr_matrix, ['schema', 'family', 'repositories', 'constraints', 'artifacts', 'dimensions', 'cells', 'historical_unavailable'], 'matrix', $wpsr_errors)) {
        return $wpsr_errors;
    }
    if (($wpsr_matrix['schema'] ?? null) !== 1 || ($wpsr_matrix['family'] ?? null) !== 'wp-social-ninja') {
        $wpsr_errors[] = 'matrix schema/family mismatch';
    }
    foreach (['repositories', 'constraints', 'artifacts', 'dimensions'] as $wpsr_field) {
        if (!is_array($wpsr_matrix[$wpsr_field] ?? null) || wpsr_compatibility_is_list($wpsr_matrix[$wpsr_field]) || !$wpsr_matrix[$wpsr_field]) {
            $wpsr_errors[] = $wpsr_field . ' must be a nonempty object';
        }
    }
    foreach (['cells', 'historical_unavailable'] as $wpsr_field) {
        if (!is_array($wpsr_matrix[$wpsr_field] ?? null) || !wpsr_compatibility_is_list($wpsr_matrix[$wpsr_field])) {
            $wpsr_errors[] = $wpsr_field . ' must be an array';
        }
    }
    if ($wpsr_errors) {
        return array_values(array_unique($wpsr_errors));
    }
    if (!$wpsr_matrix['cells']) {
        return ['matrix declares zero cells'];
    }

    foreach (['core', 'pro'] as $wpsr_plugin) {
        $wpsr_row = $wpsr_matrix['repositories'][$wpsr_plugin] ?? null;
        if (!wpsr_compatibility_exact_keys($wpsr_row, ['path', 'remote', 'canonical', 'current_branch', 'current_ref', 'required_current_paths'], 'repository ' . $wpsr_plugin, $wpsr_errors)) {
            continue;
        }
        $wpsr_expected_path = $wpsr_plugin === 'core' ? '.' : '../wp-social-ninja-pro';
        if (($wpsr_row['path'] ?? null) !== $wpsr_expected_path || ($wpsr_row['remote'] ?? null) !== 'origin'
            || !is_string($wpsr_row['canonical'] ?? null) || !preg_match('#^github\.com/[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $wpsr_row['canonical'])
            || !is_string($wpsr_row['current_branch'] ?? null) || $wpsr_row['current_branch'] === ''
            || ($wpsr_row['current_ref'] ?? null) !== 'HEAD'
            || !is_array($wpsr_row['required_current_paths'] ?? null) || !wpsr_compatibility_is_list($wpsr_row['required_current_paths'])
            || !$wpsr_row['required_current_paths']) {
            $wpsr_errors[] = 'repository metadata malformed: ' . $wpsr_plugin;
            continue;
        }
        $wpsr_repo = wpsr_compatibility_plugin_root($wpsr_root, $wpsr_plugin);
        $wpsr_remote_url = wpsr_compatibility_git($wpsr_repo, ['remote', 'get-url', $wpsr_row['remote']], $wpsr_remote_code);
        $wpsr_branch = wpsr_compatibility_git($wpsr_repo, ['branch', '--show-current'], $wpsr_branch_code);
        $wpsr_head = wpsr_compatibility_git($wpsr_repo, ['rev-parse', 'HEAD'], $wpsr_head_code);
        if ($wpsr_remote_url === null || wpsr_compatibility_canonical_repository($wpsr_remote_url) !== strtolower($wpsr_row['canonical'])) {
            $wpsr_errors[] = 'repository canonical remote mismatch: ' . $wpsr_plugin;
        }
        if ($wpsr_branch === null || $wpsr_branch === ''
            || !wpsr_compatibility_head_tracks_branch($wpsr_repo, $wpsr_row['current_branch'])
            || !preg_match('/^[0-9a-f]{40}$/', (string) $wpsr_head)) {
            $wpsr_errors[] = 'repository branch/ref identity mismatch: ' . $wpsr_plugin;
        }
        $wpsr_seen_paths = [];
        foreach ($wpsr_row['required_current_paths'] as $wpsr_path) {
            if (!is_string($wpsr_path) || $wpsr_path === '' || strpos($wpsr_path, '..') !== false || isset($wpsr_seen_paths[$wpsr_path])) {
                $wpsr_errors[] = 'repository required path malformed/duplicate: ' . $wpsr_plugin;
                continue;
            }
            $wpsr_seen_paths[$wpsr_path] = true;
            if (wpsr_compatibility_git($wpsr_repo, ['cat-file', '-e', 'HEAD:' . $wpsr_path], $wpsr_path_code) === null) {
                $wpsr_errors[] = 'required test linkage path absent at resolved current commit: ' . $wpsr_plugin . '/' . $wpsr_path;
            }
        }
        if ($wpsr_plugin === 'pro' && wpsr_compatibility_git($wpsr_repo, ['status', '--porcelain=v1', '--', 'tests'], $wpsr_status_code) !== '') {
            $wpsr_errors[] = 'stale Pro test tree differs from resolved Pro commit';
        }
    }
    foreach (array_keys($wpsr_matrix['repositories']) as $wpsr_plugin) {
        if (!in_array($wpsr_plugin, ['core', 'pro'], true)) {
            $wpsr_errors[] = 'unknown repository: ' . $wpsr_plugin;
        }
    }

    $wpsr_expected_constraint_ids = ['core_requires_pro', 'php', 'pro_requires_core', 'wordpress'];
    $wpsr_constraint_ids = array_keys($wpsr_matrix['constraints']);
    sort($wpsr_constraint_ids, SORT_STRING);
    if ($wpsr_constraint_ids !== $wpsr_expected_constraint_ids) {
        $wpsr_errors[] = 'constraint keys mismatch';
    }
    $wpsr_constraint_sources = [
        'core_requires_pro' => ['core', 'wp-social-reviews.php', 'WPSOCIALREVIEWS_PRO_MIN_VERSION'],
        'pro_requires_core' => ['pro', 'wp-social-ninja-pro.php', 'WPSOCIALREVIEWS_CORE_MIN_VERSION'],
    ];
    foreach ($wpsr_constraint_sources as $wpsr_id => $wpsr_source) {
        $wpsr_row = $wpsr_matrix['constraints'][$wpsr_id] ?? [];
        wpsr_compatibility_exact_keys($wpsr_row, ['range', 'source'], 'constraint ' . $wpsr_id, $wpsr_errors);
        wpsr_compatibility_exact_keys($wpsr_row['source'] ?? null, ['plugin', 'file', 'constant'], 'constraint source ' . $wpsr_id, $wpsr_errors);
        $wpsr_root_path = wpsr_compatibility_plugin_root($wpsr_root, $wpsr_source[0]);
        $wpsr_actual = wpsr_compatibility_constant($wpsr_root_path . '/' . $wpsr_source[1], $wpsr_source[2]);
        $wpsr_expected = preg_replace('/^>=/', '', (string) ($wpsr_row['range'] ?? ''));
        if ($wpsr_actual === null || $wpsr_actual !== $wpsr_expected
            || ($wpsr_row['source']['plugin'] ?? '') !== $wpsr_source[0]
            || ($wpsr_row['source']['file'] ?? '') !== $wpsr_source[1]
            || ($wpsr_row['source']['constant'] ?? '') !== $wpsr_source[2]) {
            $wpsr_errors[] = 'constraint source mismatch: ' . $wpsr_id;
        }
    }
    foreach (['wordpress' => 'Requires at least', 'php' => 'Requires PHP'] as $wpsr_id => $wpsr_field) {
        $wpsr_row = $wpsr_matrix['constraints'][$wpsr_id] ?? [];
        wpsr_compatibility_exact_keys($wpsr_row, ['range', 'sources'], 'constraint ' . $wpsr_id, $wpsr_errors);
        if (!is_array($wpsr_row['sources'] ?? null) || !wpsr_compatibility_is_list($wpsr_row['sources']) || count($wpsr_row['sources']) !== 2) {
            $wpsr_errors[] = 'constraint sources malformed: ' . $wpsr_id;
            continue;
        }
        $wpsr_expected = preg_replace('/^>=/', '', (string) ($wpsr_row['range'] ?? ''));
        $wpsr_expected_sources = [
            ['plugin' => 'core', 'file' => 'readme.txt', 'field' => $wpsr_field],
            ['plugin' => 'pro', 'file' => 'readme.txt', 'field' => $wpsr_field],
        ];
        foreach ($wpsr_row['sources'] as $wpsr_index => $wpsr_source) {
            wpsr_compatibility_exact_keys($wpsr_source, ['plugin', 'file', 'field'], 'constraint source ' . $wpsr_id, $wpsr_errors);
            $wpsr_plugin_root = wpsr_compatibility_plugin_root($wpsr_root, $wpsr_source['plugin'] ?? '');
            if (($wpsr_expected_sources[$wpsr_index] ?? null) !== $wpsr_source
                || !is_string($wpsr_plugin_root)
                || wpsr_compatibility_readme_field($wpsr_plugin_root . '/' . ($wpsr_source['file'] ?? ''), $wpsr_field) !== $wpsr_expected) {
                $wpsr_errors[] = 'constraint source mismatch: ' . $wpsr_id . '/' . ($wpsr_source['plugin'] ?? 'unknown');
            }
        }
    }

    $wpsr_explicit_unavailable = [];
    $wpsr_skip_ids = [];
    foreach ($wpsr_matrix['historical_unavailable'] as $wpsr_index => $wpsr_skip) {
        $wpsr_label = 'historical_unavailable[' . $wpsr_index . ']';
        wpsr_compatibility_exact_keys($wpsr_skip, ['id', 'artifact', 'version', 'evidence'], $wpsr_label, $wpsr_errors);
        wpsr_compatibility_exact_keys($wpsr_skip['evidence'] ?? null, ['command', 'result'], $wpsr_label . '.evidence', $wpsr_errors);
        $wpsr_id = $wpsr_skip['id'] ?? '';
        if (!is_string($wpsr_id) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $wpsr_id) || isset($wpsr_skip_ids[$wpsr_id])
            || !is_string($wpsr_skip['artifact'] ?? null) || $wpsr_skip['artifact'] === ''
            || !is_string($wpsr_skip['version'] ?? null) || $wpsr_skip['version'] === ''
            || !is_string($wpsr_skip['evidence']['command'] ?? null) || $wpsr_skip['evidence']['command'] === ''
            || !is_string($wpsr_skip['evidence']['result'] ?? null) || $wpsr_skip['evidence']['result'] === '') {
            $wpsr_errors[] = 'unavailable historical version has silent/malformed SKIP evidence';
        }
        $wpsr_skip_ids[$wpsr_id] = true;
        $wpsr_explicit_unavailable[(string) ($wpsr_skip['artifact'] ?? '')] = true;
    }

    $wpsr_artifacts = [];
    foreach ($wpsr_matrix['artifacts'] as $wpsr_id => $wpsr_artifact) {
        $wpsr_kind = is_array($wpsr_artifact) ? ($wpsr_artifact['kind'] ?? null) : null;
        $wpsr_expected_keys = $wpsr_kind === 'current-checkout'
            ? ['plugin', 'repository', 'ref', 'version', 'kind']
            : ['plugin', 'repository', 'ref', 'version', 'kind', 'expected_commit'];
        if (!wpsr_compatibility_exact_keys($wpsr_artifact, $wpsr_expected_keys, 'artifact ' . $wpsr_id, $wpsr_errors)) {
            continue;
        }
        $wpsr_plugin = $wpsr_artifact['plugin'] ?? '';
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $wpsr_id)
            || !in_array($wpsr_plugin, ['core', 'pro'], true)
            || !in_array($wpsr_kind, ['current-checkout', 'historical-tag'], true)
            || !is_string($wpsr_artifact['ref'] ?? null) || $wpsr_artifact['ref'] === ''
            || !is_string($wpsr_artifact['version'] ?? null) || !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $wpsr_artifact['version'])
            || ($wpsr_artifact['repository'] ?? null) !== ($wpsr_matrix['repositories'][$wpsr_plugin]['path'] ?? null)) {
            $wpsr_errors[] = 'malformed artifact: ' . $wpsr_id;
            continue;
        }
        if ($wpsr_kind === 'current-checkout' && $wpsr_artifact['ref'] !== ($wpsr_matrix['repositories'][$wpsr_plugin]['current_ref'] ?? null)) {
            $wpsr_errors[] = 'current artifact ref semantics mismatch: ' . $wpsr_id;
        }
        if ($wpsr_kind === 'historical-tag' && ($wpsr_artifact['ref'] !== $wpsr_artifact['version']
            || !preg_match('/^[0-9a-f]{40}$/', (string) ($wpsr_artifact['expected_commit'] ?? '')))) {
            $wpsr_errors[] = 'historical artifact ref/commit malformed: ' . $wpsr_id;
        }
        $wpsr_repo = wpsr_compatibility_plugin_root($wpsr_root, $wpsr_plugin);
        $wpsr_commit = wpsr_compatibility_git($wpsr_repo, ['rev-parse', $wpsr_artifact['ref'] . '^{commit}'], $wpsr_code);
        $wpsr_main_file = $wpsr_plugin === 'core' ? 'wp-social-reviews.php' : 'wp-social-ninja-pro.php';
        $wpsr_main_source = wpsr_compatibility_git($wpsr_repo, ['show', $wpsr_artifact['ref'] . ':' . $wpsr_main_file], $wpsr_show_code);
        $wpsr_source_version = null;
        if (is_string($wpsr_main_source) && preg_match('/^[ \t\/*#@]*Version:\s*(.+?)\s*$/mi', $wpsr_main_source, $wpsr_version_match)) {
            $wpsr_source_version = trim($wpsr_version_match[1]);
        }
        if ($wpsr_commit === null && !isset($wpsr_explicit_unavailable[$wpsr_id])) {
            $wpsr_errors[] = 'artifact ref unavailable without explicit SKIP evidence: ' . $wpsr_id;
        } elseif ($wpsr_kind === 'current-checkout' && $wpsr_commit !== wpsr_compatibility_git($wpsr_repo, ['rev-parse', 'HEAD'], $wpsr_head_code)) {
            $wpsr_errors[] = 'current artifact does not resolve checkout HEAD: ' . $wpsr_id;
        } elseif ($wpsr_kind === 'historical-tag' && $wpsr_commit !== $wpsr_artifact['expected_commit']) {
            $wpsr_errors[] = 'historical artifact commit drift: ' . $wpsr_id;
        }
        if ($wpsr_commit !== null && $wpsr_source_version !== $wpsr_artifact['version']) {
            $wpsr_errors[] = 'artifact version/source drift: ' . $wpsr_id;
        }
        $wpsr_artifacts[$wpsr_id] = $wpsr_artifact;
    }

    foreach ($wpsr_matrix['dimensions'] as $wpsr_id => $wpsr_dimension) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $wpsr_id)
            || !wpsr_compatibility_exact_keys($wpsr_dimension, ['wordpress_image', 'cli_image', 'php', 'wordpress', 'database_image'], 'dimension ' . $wpsr_id, $wpsr_errors)) {
            continue;
        }
        foreach ($wpsr_dimension as $wpsr_field => $wpsr_value) {
            if (!is_string($wpsr_value) || trim($wpsr_value) === '') {
                $wpsr_errors[] = 'dimension ' . $wpsr_id . ' field ' . $wpsr_field . ' must be nonempty string';
            }
        }
    }

    $wpsr_ids = [];
    $wpsr_coordinates = [];
    $wpsr_purposes = [];
    foreach ($wpsr_matrix['cells'] as $wpsr_index => $wpsr_cell) {
        $wpsr_label = 'cells[' . $wpsr_index . ']';
        if (!wpsr_compatibility_exact_keys($wpsr_cell, ['id', 'purpose', 'core', 'pro', 'dimension', 'expected'], $wpsr_label, $wpsr_errors)) {
            continue;
        }
        $wpsr_id = $wpsr_cell['id'];
        if (!is_string($wpsr_id) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $wpsr_id)) {
            $wpsr_errors[] = 'duplicate or malformed cell id: ' . (is_scalar($wpsr_id) ? (string) $wpsr_id : $wpsr_label);
            continue;
        }
        if (isset($wpsr_ids[$wpsr_id])) {
            $wpsr_errors[] = 'duplicate or malformed cell id: ' . $wpsr_id;
        } else {
            $wpsr_ids[$wpsr_id] = true;
        }
        $wpsr_purpose = $wpsr_cell['purpose'] ?? null;
        if (!is_string($wpsr_purpose) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $wpsr_purpose)) {
            $wpsr_errors[] = $wpsr_id . ': malformed purpose';
        } else {
            $wpsr_purposes[$wpsr_purpose] = ($wpsr_purposes[$wpsr_purpose] ?? 0) + 1;
        }
        if (!is_string($wpsr_cell['core'] ?? null) || !isset($wpsr_artifacts[$wpsr_cell['core']])) {
            $wpsr_errors[] = $wpsr_id . ': core artifact missing';
        }
        if (($wpsr_cell['pro'] ?? null) !== null && (!is_string($wpsr_cell['pro']) || !isset($wpsr_artifacts[$wpsr_cell['pro']]))) {
            $wpsr_errors[] = $wpsr_id . ': Pro artifact missing';
        }
        if (!is_string($wpsr_cell['dimension'] ?? null) || !isset($wpsr_matrix['dimensions'][$wpsr_cell['dimension']])) {
            $wpsr_errors[] = $wpsr_id . ': dimension missing';
        }
        $wpsr_coordinate = implode('|', [$wpsr_cell['core'] ?? '', $wpsr_cell['pro'] ?? 'alone', $wpsr_cell['dimension'] ?? '']);
        if (isset($wpsr_coordinates[$wpsr_coordinate])) {
            $wpsr_errors[] = 'duplicate cell coordinates: ' . $wpsr_id . '/' . $wpsr_coordinates[$wpsr_coordinate];
        }
        $wpsr_coordinates[$wpsr_coordinate] = $wpsr_id;

        $wpsr_expected = $wpsr_cell['expected'] ?? null;
        if (!wpsr_compatibility_exact_keys($wpsr_expected, ['compatibility', 'core_boot', 'pro_boot', 'diagnostic', 'core_routes_min', 'pro_route_declarations', 'lifecycle_checks_min', 'rest_checks_min'], $wpsr_id . '.expected', $wpsr_errors)) {
            continue;
        }
        foreach (['core_boot', 'pro_boot'] as $wpsr_boolean) {
            if (!is_bool($wpsr_expected[$wpsr_boolean])) {
                $wpsr_errors[] = $wpsr_id . ': expected ' . $wpsr_boolean . ' must be boolean';
            }
        }
        if (!is_null($wpsr_expected['diagnostic']) && (!is_string($wpsr_expected['diagnostic']) || $wpsr_expected['diagnostic'] === '')) {
            $wpsr_errors[] = $wpsr_id . ': diagnostic must be null or nonempty string';
        }
        foreach (['core_routes_min', 'pro_route_declarations', 'lifecycle_checks_min', 'rest_checks_min'] as $wpsr_count) {
            if (!is_int($wpsr_expected[$wpsr_count]) || $wpsr_expected[$wpsr_count] < 0) {
                $wpsr_errors[] = $wpsr_id . ': invalid expected count ' . $wpsr_count;
            }
        }
        if (($wpsr_expected['core_routes_min'] ?? 0) < 1 || ($wpsr_expected['lifecycle_checks_min'] ?? 0) < 1 || ($wpsr_expected['rest_checks_min'] ?? 0) < 1) {
            $wpsr_errors[] = $wpsr_id . ': zero route/test expectation';
        }
        if ($wpsr_expected['compatibility'] === 'compatible') {
            if (($wpsr_cell['pro'] !== null && !$wpsr_expected['pro_boot']) || ($wpsr_cell['pro'] === null && $wpsr_expected['pro_boot'])) {
                $wpsr_errors[] = $wpsr_id . ': compatible pair expected outcome mismatch';
            }
        } elseif ($wpsr_expected['compatibility'] === 'incompatible') {
            if ($wpsr_expected['pro_boot'] || !$wpsr_expected['diagnostic'] || $wpsr_expected['pro_route_declarations'] !== 0) {
                $wpsr_errors[] = $wpsr_id . ': incompatible expected outcome mismatch';
            }
        } else {
            $wpsr_errors[] = $wpsr_id . ': compatibility must be compatible/incompatible';
        }
    }
    foreach (['current-free-alone', 'current-compatible-pair', 'declared-minimum-boundary-pair', 'below-minimum-core-for-pro', 'below-minimum-pro-for-core'] as $wpsr_required) {
        if (($wpsr_purposes[$wpsr_required] ?? 0) !== 1) {
            $wpsr_errors[] = 'required matrix purpose missing or duplicated: ' . $wpsr_required;
        }
    }
    if (!isset($wpsr_matrix['dimensions']['current'], $wpsr_matrix['dimensions']['supported-minimum'])) {
        $wpsr_errors[] = 'supported/current WordPress/PHP dimensions missing';
    }
    return array_values(array_unique($wpsr_errors));
}
