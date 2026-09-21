<?php
/**
 * Phase 28 family-census registry validation and filesystem discovery helpers.
 */

const WPSR_FAMILY_PASS = 0;
const WPSR_FAMILY_FAIL = 1;
const WPSR_FAMILY_MISUSE = 2;
const WPSR_FAMILY_ROLES = ['host', 'add-on', 'bridge'];

function wpsr_family_load_json($wpsr_path)
{
    if (!is_string($wpsr_path) || $wpsr_path === '') {
        throw new RuntimeException('family registry path malformed');
    }
    if (!is_file($wpsr_path)) {
        throw new RuntimeException('family registry missing: ' . $wpsr_path);
    }
    $wpsr_source = file_get_contents($wpsr_path);
    if (!is_string($wpsr_source)) {
        throw new RuntimeException('family registry unreadable: ' . $wpsr_path);
    }
    $wpsr_object = json_decode($wpsr_source);
    if (!is_object($wpsr_object) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('family registry malformed or root is not an object: ' . json_last_error_msg());
    }
    $wpsr_ambiguous_object = null;
    $wpsr_ambiguous_object = static function ($wpsr_value) use (&$wpsr_ambiguous_object) {
        if (is_object($wpsr_value)) {
            $wpsr_properties = get_object_vars($wpsr_value);
            $wpsr_keys = array_map('strval', array_keys($wpsr_properties));
            if ($wpsr_keys === [] || $wpsr_keys === array_map('strval', range(0, count($wpsr_keys) - 1))) {
                return true;
            }
            foreach ($wpsr_properties as $wpsr_property) {
                if ($wpsr_ambiguous_object($wpsr_property)) {
                    return true;
                }
            }
        } elseif (is_array($wpsr_value)) {
            foreach ($wpsr_value as $wpsr_item) {
                if ($wpsr_ambiguous_object($wpsr_item)) {
                    return true;
                }
            }
        }
        return false;
    };
    if ($wpsr_ambiguous_object($wpsr_object)) {
        throw new RuntimeException('family registry contains ambiguous JSON object/list shape');
    }
    return json_decode($wpsr_source, true);
}

function wpsr_family_is_token($wpsr_value)
{
    return is_string($wpsr_value) && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $wpsr_value);
}

function wpsr_family_is_list(array $wpsr_value)
{
    return $wpsr_value === [] || array_keys($wpsr_value) === range(0, count($wpsr_value) - 1);
}

function wpsr_family_exact_keys($wpsr_value, array $wpsr_expected, $wpsr_label, array &$wpsr_errors)
{
    if (!is_array($wpsr_value) || wpsr_family_is_list($wpsr_value)) {
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

function wpsr_family_validate_source_shape($wpsr_source, $wpsr_label, $wpsr_with_value, array &$wpsr_errors)
{
    if (!is_array($wpsr_source)) {
        $wpsr_errors[] = $wpsr_label . ' must be an object';
        return;
    }
    $wpsr_type = $wpsr_source['type'] ?? null;
    $wpsr_keys = ['type', 'file'];
    if (in_array($wpsr_type, ['plugin-header', 'readme-field'], true)) {
        $wpsr_keys[] = 'field';
    } elseif (in_array($wpsr_type, ['constant', 'hook'], true)) {
        $wpsr_keys[] = 'name';
    } elseif ($wpsr_type !== 'namespace') {
        $wpsr_errors[] = $wpsr_label . ' source type invalid';
    }
    if ($wpsr_with_value) {
        $wpsr_keys[] = 'value';
    }
    wpsr_family_exact_keys($wpsr_source, $wpsr_keys, $wpsr_label, $wpsr_errors);
    foreach ($wpsr_keys as $wpsr_key) {
        if (!is_string($wpsr_source[$wpsr_key] ?? null) || $wpsr_source[$wpsr_key] === '') {
            $wpsr_errors[] = $wpsr_label . '.' . $wpsr_key . ' must be nonempty string';
        }
    }
}

function wpsr_family_canonical_repository($wpsr_url)
{
    if (!is_string($wpsr_url) || trim($wpsr_url) === '') {
        return null;
    }
    $wpsr_url = preg_replace('#^git@([^:]+):#i', 'https://$1/', trim($wpsr_url));
    $wpsr_parts = strpos($wpsr_url, '://') !== false ? parse_url($wpsr_url) : null;
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

function wpsr_family_normalize_path($wpsr_path)
{
    $wpsr_path = str_replace('\\', '/', (string) $wpsr_path);
    $wpsr_prefix = '';
    if (preg_match('#^[A-Za-z]:/#', $wpsr_path, $wpsr_match)) {
        $wpsr_prefix = substr($wpsr_path, 0, 3);
        $wpsr_path = substr($wpsr_path, 3);
    } elseif (strpos($wpsr_path, '/') === 0) {
        $wpsr_prefix = '/';
        $wpsr_path = ltrim($wpsr_path, '/');
    }
    $wpsr_parts = [];
    foreach (explode('/', $wpsr_path) as $wpsr_part) {
        if ($wpsr_part === '' || $wpsr_part === '.') {
            continue;
        }
        if ($wpsr_part === '..' && $wpsr_parts && end($wpsr_parts) !== '..') {
            array_pop($wpsr_parts);
            continue;
        }
        $wpsr_parts[] = $wpsr_part;
    }
    $wpsr_result = $wpsr_prefix . implode('/', $wpsr_parts);
    return $wpsr_result === '' ? '.' : rtrim($wpsr_result, '/');
}

function wpsr_family_absolute_path($wpsr_base, $wpsr_relative)
{
    if (preg_match('#^(?:/|[A-Za-z]:[\\/])#', (string) $wpsr_relative)) {
        return wpsr_family_normalize_path($wpsr_relative);
    }
    return wpsr_family_normalize_path(rtrim($wpsr_base, '/\\') . '/' . $wpsr_relative);
}

function wpsr_family_resolve_path(array $wpsr_path, $wpsr_host_root, $wpsr_use_env, &$wpsr_override_used = false)
{
    $wpsr_override_used = false;
    $wpsr_env = (string) ($wpsr_path['env_override'] ?? '');
    if ($wpsr_use_env && $wpsr_env !== '') {
        $wpsr_value = getenv($wpsr_env);
        if ($wpsr_value !== false && trim($wpsr_value) !== '') {
            $wpsr_override_used = true;
            return wpsr_family_absolute_path($wpsr_host_root, trim($wpsr_value));
        }
    }
    return wpsr_family_absolute_path($wpsr_host_root, (string) ($wpsr_path['relative'] ?? ''));
}

/**
 * True when HEAD is the declared integration branch or was cut from it.
 * Feature branches are normal work; a wrong repository or a checkout that
 * predates the branch still fails.
 */
function wpsr_family_head_tracks_branch($wpsr_root, $wpsr_branch)
{
    if (!is_string($wpsr_branch) || $wpsr_branch === '') {
        return false;
    }
    // Every candidate is tried: a stale local branch must not mask an ancestral origin ref.
    foreach ([$wpsr_branch, 'refs/remotes/origin/' . $wpsr_branch] as $wpsr_ref) {
        if (wpsr_family_git_value($wpsr_root, ['rev-parse', '--verify', '--quiet', $wpsr_ref . '^{commit}']) === null) {
            continue;
        }
        if (wpsr_family_git_value($wpsr_root, ['merge-base', '--is-ancestor', $wpsr_ref, 'HEAD']) === '') {
            return true;
        }
    }
    return false;
}

function wpsr_family_plugin_headers($wpsr_root)
{
    $wpsr_headers = [];
    foreach (glob(rtrim($wpsr_root, '/\\') . '/*.php') ?: [] as $wpsr_file) {
        $wpsr_source = file_get_contents($wpsr_file, false, null, 0, 16384);
        if (!is_string($wpsr_source) || !preg_match('/^[ \t\/*#@]*Plugin Name:\s*(.+?)\s*$/mi', $wpsr_source, $wpsr_name)) {
            continue;
        }
        $wpsr_fields = [];
        foreach (['Plugin Name', 'Version', 'Requires Plugins', 'Text Domain'] as $wpsr_field) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($wpsr_field, '/') . ':\s*(.+?)\s*$/mi', $wpsr_source, $wpsr_match)) {
                $wpsr_fields[$wpsr_field] = trim($wpsr_match[1]);
            }
        }
        $wpsr_headers[] = [
            'file' => basename($wpsr_file),
            'fields' => $wpsr_fields,
        ];
    }
    return $wpsr_headers;
}

function wpsr_family_headers_carry_family_name(array $wpsr_headers, $wpsr_prefix)
{
    if (!is_string($wpsr_prefix) || $wpsr_prefix === '') {
        return true;
    }
    foreach ($wpsr_headers as $wpsr_header) {
        if (stripos((string) ($wpsr_header['fields']['Plugin Name'] ?? ''), $wpsr_prefix) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Family-named neighbours to leave unclassified, one directory name per line.
 * Read from beside the registry, so a fixture registry never inherits this list.
 */
function wpsr_family_ignored_neighbours($wpsr_registry_dir)
{
    if (!is_string($wpsr_registry_dir) || $wpsr_registry_dir === '') {
        return [];
    }
    $wpsr_file = rtrim($wpsr_registry_dir, '/\\') . '/ignored-neighbours.txt';
    if (!is_file($wpsr_file)) {
        return [];
    }
    $wpsr_names = [];
    foreach (preg_split('/\R/', (string) file_get_contents($wpsr_file)) ?: [] as $wpsr_line) {
        $wpsr_line = trim($wpsr_line);
        if ($wpsr_line === '' || strpos($wpsr_line, '#') === 0) {
            continue;
        }
        if (wpsr_family_is_token($wpsr_line)) {
            $wpsr_names[$wpsr_line] = true;
        }
    }
    return array_keys($wpsr_names);
}

function wpsr_family_discover_candidates($wpsr_family_root, array $wpsr_explicit_paths, $wpsr_name_prefix = '', array $wpsr_ignore = [], array &$wpsr_ignored = [])
{
    $wpsr_paths = [];
    if (is_dir($wpsr_family_root)) {
        foreach (scandir($wpsr_family_root) ?: [] as $wpsr_name) {
            if ($wpsr_name === '.' || $wpsr_name === '..') {
                continue;
            }
            $wpsr_path = wpsr_family_normalize_path($wpsr_family_root . '/' . $wpsr_name);
            if (is_dir($wpsr_path)) {
                $wpsr_paths[$wpsr_path] = false;
            }
        }
    }
    foreach ($wpsr_explicit_paths as $wpsr_path) {
        if (is_dir($wpsr_path)) {
            $wpsr_paths[wpsr_family_normalize_path($wpsr_path)] = true;
        }
    }

    $wpsr_candidates = [];
    foreach ($wpsr_paths as $wpsr_path => $wpsr_is_explicit) {
        $wpsr_headers = wpsr_family_plugin_headers($wpsr_path);
        if (!$wpsr_headers) {
            continue;
        }
        // Registry paths always count; neighbours only when they claim the family name.
        if (!$wpsr_is_explicit && !wpsr_family_headers_carry_family_name($wpsr_headers, $wpsr_name_prefix)) {
            continue;
        }
        if (!$wpsr_is_explicit && in_array(basename($wpsr_path), $wpsr_ignore, true)) {
            $wpsr_ignored[] = $wpsr_path;
            continue;
        }
        $wpsr_candidates[$wpsr_path] = [
            'path' => $wpsr_path,
            'slug' => basename($wpsr_path),
            'headers' => $wpsr_headers,
        ];
    }
    ksort($wpsr_candidates, SORT_STRING);
    return $wpsr_candidates;
}

function wpsr_family_read_constant($wpsr_file, $wpsr_name)
{
    if (!is_string($wpsr_file) || $wpsr_file === '' || !is_string($wpsr_name) || $wpsr_name === '') {
        return null;
    }
    $wpsr_source = is_file($wpsr_file) ? file_get_contents($wpsr_file) : false;
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

function wpsr_family_read_readme_field($wpsr_file, $wpsr_field)
{
    if (!is_string($wpsr_file) || $wpsr_file === '' || !is_string($wpsr_field) || $wpsr_field === ''
        || !is_file($wpsr_file)) {
        return null;
    }
    $wpsr_source = file_get_contents($wpsr_file, false, null, 0, 16384);
    if (!is_string($wpsr_source)
        || preg_match_all('/^' . preg_quote($wpsr_field, '/') . ':\s*(.+?)\s*$/mi', $wpsr_source, $wpsr_matches) !== 1) {
        return null;
    }
    return trim($wpsr_matches[1][0]);
}

function wpsr_family_source_value(array $wpsr_source, $wpsr_plugin_root)
{
    $wpsr_file = wpsr_family_absolute_path($wpsr_plugin_root, (string) ($wpsr_source['file'] ?? ''));
    switch ($wpsr_source['type'] ?? '') {
        case 'plugin-header':
            foreach (wpsr_family_plugin_headers($wpsr_plugin_root) as $wpsr_header) {
                if ($wpsr_header['file'] === ($wpsr_source['file'] ?? '')) {
                    return $wpsr_header['fields'][$wpsr_source['field'] ?? ''] ?? null;
                }
            }
            return null;
        case 'constant':
            return wpsr_family_read_constant($wpsr_file, (string) ($wpsr_source['name'] ?? ''));
        case 'readme-field':
            return wpsr_family_read_readme_field($wpsr_file, (string) ($wpsr_source['field'] ?? ''));
        case 'hook':
            if (!is_file($wpsr_file)) {
                return null;
            }
            $wpsr_source_text = file_get_contents($wpsr_file);
            return strpos((string) $wpsr_source_text, (string) ($wpsr_source['name'] ?? '')) !== false
                ? (string) $wpsr_source['name'] : null;
        case 'namespace':
            if (!is_file($wpsr_file)) {
                return null;
            }
            $wpsr_source_text = file_get_contents($wpsr_file);
            if (preg_match('/\bnamespace\s+([^;]+);/', (string) $wpsr_source_text, $wpsr_match)) {
                return trim($wpsr_match[1]);
            }
            return null;
    }
    return null;
}

function wpsr_family_version_satisfies($wpsr_version, $wpsr_range)
{
    $wpsr_range = trim((string) $wpsr_range);
    if (!preg_match('/^(>=|<=|>|<|=)?\s*([0-9]+(?:\.[0-9A-Za-z-]+)*)$/', $wpsr_range, $wpsr_match)) {
        return null;
    }
    $wpsr_operator = $wpsr_match[1] ?: '=';
    return version_compare((string) $wpsr_version, $wpsr_match[2], $wpsr_operator);
}

function wpsr_family_git_value($wpsr_root, array $wpsr_arguments)
{
    if (!is_string($wpsr_root) || $wpsr_root === '' || !is_dir($wpsr_root) || !$wpsr_arguments) {
        return null;
    }
    $wpsr_command = ['git', '-C', $wpsr_root];
    foreach ($wpsr_arguments as $wpsr_argument) {
        if (!is_string($wpsr_argument) || $wpsr_argument === '') {
            return null;
        }
        $wpsr_command[] = $wpsr_argument;
    }
    $wpsr_descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $wpsr_process = proc_open($wpsr_command, $wpsr_descriptor, $wpsr_pipes);
    if (!is_resource($wpsr_process)) {
        return null;
    }
    $wpsr_output = stream_get_contents($wpsr_pipes[1]);
    fclose($wpsr_pipes[1]);
    fclose($wpsr_pipes[2]);
    $wpsr_code = proc_close($wpsr_process);
    return $wpsr_code === 0 ? trim((string) $wpsr_output) : null;
}

function wpsr_family_cycle_errors(array $wpsr_plugins)
{
    $wpsr_errors = [];
    $wpsr_by_id = [];
    foreach ($wpsr_plugins as $wpsr_plugin) {
        if (is_array($wpsr_plugin) && !empty($wpsr_plugin['id'])) {
            $wpsr_by_id[$wpsr_plugin['id']] = $wpsr_plugin;
        }
    }
    foreach ($wpsr_by_id as $wpsr_start => $wpsr_plugin) {
        $wpsr_seen = [];
        $wpsr_current = $wpsr_start;
        while (isset($wpsr_by_id[$wpsr_current])) {
            if (isset($wpsr_seen[$wpsr_current])) {
                $wpsr_errors[] = 'dependency cycle detected from ' . $wpsr_start . ' through ' . $wpsr_current;
                break;
            }
            $wpsr_seen[$wpsr_current] = true;
            $wpsr_next = $wpsr_by_id[$wpsr_current]['required_host'] ?? null;
            if (!$wpsr_next) {
                break;
            }
            $wpsr_current = $wpsr_next;
        }
    }
    return array_values(array_unique($wpsr_errors));
}

function wpsr_family_validate(array $wpsr_registry, $wpsr_host_root, $wpsr_family_root_override = null, $wpsr_use_env = true, $wpsr_registry_dir = null)
{
    $wpsr_errors = [];
    $wpsr_skips = [];
    $wpsr_metadata = [];

    if (!wpsr_family_exact_keys($wpsr_registry, ['schema_version', 'family', 'host', 'discovery', 'plugins', 'out_of_family'], 'family registry', $wpsr_errors)) {
        return ['errors' => $wpsr_errors, 'skips' => [], 'metadata' => [], 'stats' => []];
    }
    if (($wpsr_registry['schema_version'] ?? null) !== 2) {
        return ['errors' => ['family registry schema_version must be 2'], 'skips' => [], 'metadata' => [], 'stats' => []];
    }
    if (!wpsr_family_is_token($wpsr_registry['family'] ?? null)) {
        $wpsr_errors[] = 'family must be normalized kebab-case';
    }
    wpsr_family_exact_keys($wpsr_registry['discovery'] ?? null, ['candidate_rule', 'family_name_prefix', 'family_root'], 'discovery', $wpsr_errors);
    if (!is_string($wpsr_registry['discovery']['family_name_prefix'] ?? null) || trim((string) $wpsr_registry['discovery']['family_name_prefix']) === '') {
        $wpsr_errors[] = 'discovery.family_name_prefix must be a non-empty string';
    }
    wpsr_family_exact_keys($wpsr_registry['discovery']['family_root'] ?? null, ['relative_to', 'relative', 'env_override'], 'discovery.family_root', $wpsr_errors);
    if (!isset($wpsr_registry['discovery']['family_root']) || !is_array($wpsr_registry['discovery']['family_root'])) {
        $wpsr_errors[] = 'discovery.family_root path strategy missing';
        $wpsr_family_root = wpsr_family_normalize_path(dirname($wpsr_host_root));
    } else {
        $wpsr_root_override = false;
        $wpsr_family_root = $wpsr_family_root_override !== null
            ? wpsr_family_absolute_path($wpsr_host_root, $wpsr_family_root_override)
            : wpsr_family_resolve_path($wpsr_registry['discovery']['family_root'], $wpsr_host_root, $wpsr_use_env, $wpsr_root_override);
        if ($wpsr_root_override && !is_dir($wpsr_family_root)) {
            $wpsr_errors[] = 'configured family-root override unavailable: ' . $wpsr_family_root;
        }
    }
    if (!is_dir($wpsr_family_root)) {
        $wpsr_errors[] = 'family discovery root unavailable: ' . $wpsr_family_root;
    }

    $wpsr_plugins = $wpsr_registry['plugins'] ?? null;
    $wpsr_outside = $wpsr_registry['out_of_family'] ?? null;
    if (!is_array($wpsr_plugins) || !$wpsr_plugins) {
        $wpsr_errors[] = 'plugins must be a nonempty array';
        $wpsr_plugins = [];
    }
    if (!is_array($wpsr_outside)) {
        $wpsr_errors[] = 'out_of_family must be an array';
        $wpsr_outside = [];
    }

    $wpsr_ids = [];
    $wpsr_slugs = [];
    $wpsr_paths = [];
    $wpsr_activation_orders = [];
    $wpsr_resolved = [];
    $wpsr_host_count = 0;
    $wpsr_host_order = null;
    $wpsr_explicit_paths = [];

    foreach ($wpsr_plugins as $wpsr_index => $wpsr_plugin) {
        $wpsr_label = is_array($wpsr_plugin) && !empty($wpsr_plugin['id']) ? $wpsr_plugin['id'] : 'plugins[' . $wpsr_index . ']';
        if (!is_array($wpsr_plugin)) {
            $wpsr_errors[] = $wpsr_label . ': row must be an object';
            continue;
        }
        $wpsr_plugin_keys = ['id', 'role', 'slug', 'plugin_slug', 'main_file', 'path', 'required_host', 'runtime', 'activation_order', 'version', 'version_compatibility', 'test_tree', 'missing_sibling', 'classification_evidence', 'vcs'];
        wpsr_family_exact_keys($wpsr_plugin, $wpsr_plugin_keys, $wpsr_label, $wpsr_errors);
        wpsr_family_exact_keys($wpsr_plugin['path'] ?? null, ['relative_to', 'relative', 'env_override'], $wpsr_label . '.path', $wpsr_errors);
        wpsr_family_exact_keys($wpsr_plugin['runtime'] ?? null, ['availability', 'phase_28_status', 'selectable', 'environment'], $wpsr_label . '.runtime', $wpsr_errors);
        wpsr_family_exact_keys($wpsr_plugin['version'] ?? null, ['source', 'observed'], $wpsr_label . '.version', $wpsr_errors);
        wpsr_family_validate_source_shape($wpsr_plugin['version']['source'] ?? null, $wpsr_label . '.version.source', false, $wpsr_errors);
        if (!is_string($wpsr_plugin['version']['observed'] ?? null) || !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $wpsr_plugin['version']['observed'])) {
            $wpsr_errors[] = $wpsr_label . '.version.observed malformed';
        }
        $wpsr_tree_keys = ($wpsr_plugin['role'] ?? '') === 'host'
            ? ['status', 'path', 'planned_phase', 'metadata', 'execution']
            : ['status', 'path', 'host_link', 'metadata', 'planned_phase', 'execution'];
        wpsr_family_exact_keys($wpsr_plugin['test_tree'] ?? null, $wpsr_tree_keys, $wpsr_label . '.test_tree', $wpsr_errors);
        wpsr_family_exact_keys($wpsr_plugin['test_tree']['execution'] ?? null, ['runner', 'environment'], $wpsr_label . '.test_tree.execution', $wpsr_errors);
        $wpsr_missing_keys = ($wpsr_plugin['runtime']['availability'] ?? '') === 'optional'
            ? ['policy', 'code', 'reason', 'skip_id'] : ['policy', 'code', 'reason'];
        wpsr_family_exact_keys($wpsr_plugin['missing_sibling'] ?? null, $wpsr_missing_keys, $wpsr_label . '.missing_sibling', $wpsr_errors);
        wpsr_family_exact_keys($wpsr_plugin['vcs'] ?? null, ['required', 'remote', 'canonical_repository', 'branch', 'ref_semantics'], $wpsr_label . '.vcs', $wpsr_errors);
        foreach (['version_compatibility', 'classification_evidence'] as $wpsr_list_field) {
            if (!is_array($wpsr_plugin[$wpsr_list_field] ?? null) || !wpsr_family_is_list($wpsr_plugin[$wpsr_list_field])) {
                $wpsr_errors[] = $wpsr_label . '.' . $wpsr_list_field . ' must be an array';
            }
        }
        foreach ((array) ($wpsr_plugin['version_compatibility'] ?? []) as $wpsr_constraint_index => $wpsr_constraint) {
            wpsr_family_exact_keys($wpsr_constraint, ['target', 'range', 'source'], $wpsr_label . '.version_compatibility[' . $wpsr_constraint_index . ']', $wpsr_errors);
            if (!wpsr_family_is_token($wpsr_constraint['target'] ?? null)
                || !is_string($wpsr_constraint['range'] ?? null) || $wpsr_constraint['range'] === '') {
                $wpsr_errors[] = $wpsr_label . ': compatibility target/range malformed';
            }
            wpsr_family_validate_source_shape($wpsr_constraint['source'] ?? null, $wpsr_label . '.version_compatibility.source', false, $wpsr_errors);
        }
        foreach ((array) ($wpsr_plugin['classification_evidence'] ?? []) as $wpsr_evidence_index => $wpsr_evidence) {
            wpsr_family_validate_source_shape($wpsr_evidence, $wpsr_label . '.classification_evidence[' . $wpsr_evidence_index . ']', true, $wpsr_errors);
        }
        $wpsr_id = $wpsr_plugin['id'] ?? '';
        $wpsr_slug = $wpsr_plugin['slug'] ?? '';
        if (!wpsr_family_is_token($wpsr_id)) {
            $wpsr_errors[] = $wpsr_label . ': invalid id';
        } elseif (isset($wpsr_ids[$wpsr_id])) {
            $wpsr_errors[] = $wpsr_label . ': duplicate id';
        } else {
            $wpsr_ids[$wpsr_id] = true;
        }
        if (!wpsr_family_is_token($wpsr_slug)) {
            $wpsr_errors[] = $wpsr_label . ': invalid slug';
        } elseif (isset($wpsr_slugs[$wpsr_slug])) {
            $wpsr_errors[] = $wpsr_label . ': duplicate slug';
        } else {
            $wpsr_slugs[$wpsr_slug] = true;
        }
        // plugin_slug is the plugin's own directory (what ships, and what is on disk);
        // slug is the repository identity the lab mounts under and Pro pins against.
        if (!wpsr_family_is_token($wpsr_plugin['plugin_slug'] ?? null)) {
            $wpsr_errors[] = $wpsr_label . ': invalid plugin_slug';
        }
        $wpsr_role = $wpsr_plugin['role'] ?? '';
        if (!in_array($wpsr_role, WPSR_FAMILY_ROLES, true)) {
            $wpsr_errors[] = $wpsr_label . ': invalid role ' . $wpsr_role;
        }
        if ($wpsr_role === 'host') {
            $wpsr_host_count++;
            $wpsr_host_order = $wpsr_plugin['activation_order'] ?? null;
            if (($wpsr_plugin['required_host'] ?? null) !== null) {
                $wpsr_errors[] = $wpsr_label . ': host required_host must be null';
            }
        } elseif (empty($wpsr_plugin['required_host'])) {
            $wpsr_errors[] = $wpsr_label . ': add-on/bridge must declare required_host';
        }
        if (!is_int($wpsr_plugin['activation_order'] ?? null) || $wpsr_plugin['activation_order'] < 0) {
            $wpsr_errors[] = $wpsr_label . ': invalid activation order';
        } elseif (isset($wpsr_activation_orders[$wpsr_plugin['activation_order']])) {
            $wpsr_errors[] = $wpsr_label . ': duplicate activation order with ' . $wpsr_activation_orders[$wpsr_plugin['activation_order']];
        } else {
            $wpsr_activation_orders[$wpsr_plugin['activation_order']] = $wpsr_label;
        }

        if (!is_array($wpsr_plugin['path'] ?? null)
            || ($wpsr_plugin['path']['relative_to'] ?? null) !== 'host'
            || !is_string($wpsr_plugin['path']['relative'] ?? null)
            || !preg_match('/^WPSR_FAMILY_[A-Z0-9_]+_PATH$/', (string) ($wpsr_plugin['path']['env_override'] ?? ''))) {
            $wpsr_errors[] = $wpsr_label . ': invalid relative/env path strategy';
            continue;
        }
        $wpsr_override_used = false;
        $wpsr_path = wpsr_family_resolve_path($wpsr_plugin['path'], $wpsr_host_root, $wpsr_use_env, $wpsr_override_used);
        $wpsr_explicit_paths[] = $wpsr_path;
        if (isset($wpsr_paths[$wpsr_path])) {
            $wpsr_errors[] = $wpsr_label . ': duplicate path with ' . $wpsr_paths[$wpsr_path];
        } else {
            $wpsr_paths[$wpsr_path] = $wpsr_label;
        }
        $wpsr_resolved[$wpsr_label] = [
            'row' => $wpsr_plugin,
            'path' => $wpsr_path,
            'override' => $wpsr_override_used,
        ];
    }

    if ($wpsr_host_count !== 1) {
        $wpsr_errors[] = 'host-count !=1: ' . $wpsr_host_count;
    }
    if (($wpsr_registry['host'] ?? null) === null || !isset($wpsr_ids[$wpsr_registry['host']])) {
        $wpsr_errors[] = 'registry host id is missing';
    } elseif (($wpsr_resolved[$wpsr_registry['host']]['row']['role'] ?? '') !== 'host') {
        $wpsr_errors[] = 'registry host id does not identify host role';
    }

    foreach ($wpsr_plugins as $wpsr_plugin) {
        if (!is_array($wpsr_plugin) || empty($wpsr_plugin['id'])) {
            continue;
        }
        $wpsr_id = $wpsr_plugin['id'];
        $wpsr_required = $wpsr_plugin['required_host'] ?? null;
        if ($wpsr_required !== null) {
            if (!isset($wpsr_ids[$wpsr_required])) {
                $wpsr_errors[] = $wpsr_id . ': required host does not exist: ' . $wpsr_required;
            } elseif (($wpsr_resolved[$wpsr_required]['row']['role'] ?? '') !== 'host') {
                $wpsr_errors[] = $wpsr_id . ': required_host does not identify host role: ' . $wpsr_required;
            }
            if ($wpsr_required === $wpsr_id) {
                $wpsr_errors[] = $wpsr_id . ': required host cannot be self';
            }
            if (isset($wpsr_resolved[$wpsr_required]) && ($wpsr_plugin['activation_order'] ?? -1) <= ($wpsr_resolved[$wpsr_required]['row']['activation_order'] ?? -1)) {
                $wpsr_errors[] = $wpsr_id . ': invalid activation order; dependency must activate first';
            }
        }
        if ($wpsr_host_order !== null && ($wpsr_plugin['role'] ?? '') !== 'host' && ($wpsr_plugin['activation_order'] ?? -1) <= $wpsr_host_order) {
            $wpsr_errors[] = $wpsr_id . ': invalid activation order relative to host';
        }
    }
    $wpsr_errors = array_merge($wpsr_errors, wpsr_family_cycle_errors($wpsr_plugins));

    foreach ($wpsr_outside as $wpsr_index => $wpsr_row) {
        $wpsr_label = 'out_of_family[' . $wpsr_index . ']';
        if (!is_array($wpsr_row)) {
            $wpsr_errors[] = $wpsr_label . ': row must be an object';
            continue;
        }
        foreach (['slug', 'main_file', 'path', 'reason', 'classification_evidence', 'vcs'] as $wpsr_field) {
            if (empty($wpsr_row[$wpsr_field]) && $wpsr_field !== 'classification_evidence') {
                $wpsr_errors[] = $wpsr_label . ': missing ' . $wpsr_field;
            }
        }
        if (!is_array($wpsr_row['path'] ?? null)) {
            $wpsr_errors[] = $wpsr_label . ': invalid path strategy';
            continue;
        }
        $wpsr_override_used = false;
        $wpsr_path = wpsr_family_resolve_path($wpsr_row['path'], $wpsr_host_root, $wpsr_use_env, $wpsr_override_used);
        $wpsr_explicit_paths[] = $wpsr_path;
        if (isset($wpsr_paths[$wpsr_path])) {
            $wpsr_errors[] = $wpsr_label . ': duplicate path with ' . $wpsr_paths[$wpsr_path];
        } else {
            $wpsr_paths[$wpsr_path] = $wpsr_label;
        }
        if (isset($wpsr_slugs[$wpsr_row['slug'] ?? ''])) {
            $wpsr_errors[] = $wpsr_label . ': duplicate slug';
        } else {
            $wpsr_slugs[$wpsr_row['slug'] ?? ''] = true;
        }
        $wpsr_resolved[$wpsr_label] = ['row' => $wpsr_row, 'path' => $wpsr_path, 'override' => $wpsr_override_used, 'outside' => true];
    }

    $wpsr_ignored = [];
    $wpsr_candidates = wpsr_family_discover_candidates(
        $wpsr_family_root,
        $wpsr_explicit_paths,
        (string) ($wpsr_registry['discovery']['family_name_prefix'] ?? ''),
        wpsr_family_ignored_neighbours($wpsr_registry_dir),
        $wpsr_ignored
    );
    foreach ($wpsr_candidates as $wpsr_path => $wpsr_candidate) {
        if (!isset($wpsr_paths[$wpsr_path])) {
            $wpsr_errors[] = 'unclassified plausible plugin directory: ' . $wpsr_path;
        }
        if (count($wpsr_candidate['headers']) !== 1) {
            $wpsr_errors[] = 'ambiguous plugin headers at ' . $wpsr_path;
        }
    }

    $wpsr_versions = [];
    foreach ($wpsr_resolved as $wpsr_label => $wpsr_item) {
        $wpsr_row = $wpsr_item['row'];
        $wpsr_path = $wpsr_item['path'];
        $wpsr_is_outside = !empty($wpsr_item['outside']);
        $wpsr_exists = is_dir($wpsr_path);
        $wpsr_candidate = $wpsr_candidates[$wpsr_path] ?? null;
        if (!$wpsr_exists || !$wpsr_candidate) {
            if ($wpsr_is_outside) {
                $wpsr_errors[] = $wpsr_label . ': stale out-of-family classification path ' . $wpsr_path;
                continue;
            }
            $wpsr_availability = $wpsr_row['runtime']['availability'] ?? '';
            $wpsr_policy = $wpsr_row['missing_sibling']['policy'] ?? '';
            if ($wpsr_item['override']) {
                $wpsr_errors[] = $wpsr_label . ': configured override path unavailable or not a plugin: ' . $wpsr_path;
            } elseif ($wpsr_availability === 'optional' && $wpsr_policy === 'skip') {
                $wpsr_skip_id = (string) ($wpsr_row['missing_sibling']['skip_id'] ?? '');
                if (!wpsr_family_is_token($wpsr_skip_id)) {
                    $wpsr_errors[] = $wpsr_label . ': optional sibling missing policy needs stable skip_id';
                }
                $wpsr_skips[] = [
                    'id' => $wpsr_skip_id,
                    'owner' => $wpsr_row['id'],
                    'slug' => $wpsr_row['slug'],
                    'code' => $wpsr_row['missing_sibling']['code'] ?? 'optional-sibling-missing',
                    'path' => $wpsr_path,
                ];
            } else {
                $wpsr_errors[] = $wpsr_label . ': stale/missing in-family row; required host or sibling unavailable at ' . $wpsr_path;
            }
            continue;
        }
        $wpsr_expected_dir = $wpsr_row['plugin_slug'] ?? $wpsr_row['slug'] ?? '';
        if (($wpsr_candidate['slug'] ?? '') !== $wpsr_expected_dir) {
            $wpsr_errors[] = $wpsr_label . ': plugin slug/path mismatch; found ' . ($wpsr_candidate['slug'] ?? '');
        }
        $wpsr_header = $wpsr_candidate['headers'][0];
        if (($wpsr_header['file'] ?? '') !== ($wpsr_row['main_file'] ?? '')) {
            $wpsr_errors[] = $wpsr_label . ': main plugin file mismatch; found ' . ($wpsr_header['file'] ?? '');
        }

        if (!$wpsr_is_outside) {
            $wpsr_runtime = $wpsr_row['runtime'] ?? [];
            if (!in_array($wpsr_runtime['availability'] ?? '', ['required', 'optional'], true)
                || !is_bool($wpsr_runtime['selectable'] ?? null)
                || !in_array($wpsr_runtime['phase_28_status'] ?? '', ['host-runtime', 'runtime-only', 'addon-runtime'], true)) {
                $wpsr_errors[] = $wpsr_label . ': invalid runtime availability/status';
            }
            $wpsr_missing = $wpsr_row['missing_sibling'] ?? [];
            if (!in_array($wpsr_missing['policy'] ?? '', ['fail', 'skip'], true)
                || ($wpsr_runtime['availability'] ?? '') === 'required' && ($wpsr_missing['policy'] ?? '') !== 'fail'
                || ($wpsr_runtime['availability'] ?? '') === 'optional' && ($wpsr_missing['policy'] ?? '') !== 'skip') {
                $wpsr_errors[] = $wpsr_label . ': invalid missing-sibling policy';
            }
            $wpsr_tree = $wpsr_row['test_tree'] ?? [];
            if (!in_array($wpsr_tree['status'] ?? '', ['hosted', 'linked', 'not-created'], true)) {
                $wpsr_errors[] = $wpsr_label . ': invalid test-tree status';
            } elseif (in_array($wpsr_tree['status'] ?? '', ['hosted', 'linked'], true)) {
                if (empty($wpsr_tree['path']) || !is_dir(wpsr_family_absolute_path($wpsr_path, $wpsr_tree['path']))) {
                    $wpsr_errors[] = $wpsr_label . ': registered test tree missing';
                }
            } elseif (!empty($wpsr_runtime['selectable'])) {
                $wpsr_errors[] = $wpsr_label . ': plugin without test tree cannot be selectable';
            }

            $wpsr_version = wpsr_family_source_value((array) ($wpsr_row['version']['source'] ?? []), $wpsr_path);
            if ($wpsr_version === null) {
                $wpsr_errors[] = $wpsr_label . ': version source unavailable';
            } else {
                $wpsr_versions[$wpsr_row['id']] = $wpsr_version;
                if ($wpsr_version !== (string) ($wpsr_row['version']['observed'] ?? '')) {
                    $wpsr_errors[] = $wpsr_label . ': observed version is stale; source=' . $wpsr_version;
                }
            }
            if (!is_array($wpsr_row['version_compatibility'] ?? null) || !$wpsr_row['version_compatibility']) {
                $wpsr_errors[] = $wpsr_label . ': version compatibility must be nonempty';
            } else {
                foreach ($wpsr_row['version_compatibility'] as $wpsr_constraint) {
                    $wpsr_range = $wpsr_constraint['range'] ?? '';
                    $wpsr_source_value = wpsr_family_source_value((array) ($wpsr_constraint['source'] ?? []), $wpsr_path);
                    $wpsr_expected_source = preg_replace('/^(>=|<=|>|<|=)\s*/', '', (string) $wpsr_range);
                    if ($wpsr_source_value === null || $wpsr_source_value !== $wpsr_expected_source) {
                        $wpsr_errors[] = $wpsr_label . ': compatibility source/range drift for ' . ($wpsr_constraint['target'] ?? 'unknown');
                    }
                    if (wpsr_family_version_satisfies('1.0.0', $wpsr_range) === null) {
                        $wpsr_errors[] = $wpsr_label . ': invalid compatibility range ' . $wpsr_range;
                    }
                }
            }
        }

        foreach ((array) ($wpsr_row['classification_evidence'] ?? []) as $wpsr_evidence) {
            $wpsr_actual = wpsr_family_source_value((array) $wpsr_evidence, $wpsr_path);
            if ($wpsr_actual === null || isset($wpsr_evidence['value']) && $wpsr_actual !== (string) $wpsr_evidence['value']) {
                $wpsr_errors[] = $wpsr_label . ': classification evidence drift in ' . ($wpsr_evidence['file'] ?? 'unknown');
            }
        }
        $wpsr_vcs = $wpsr_row['vcs'] ?? null;
        if (!is_array($wpsr_vcs) || ($wpsr_vcs['required'] ?? null) !== true
            || ($wpsr_vcs['remote'] ?? null) !== 'origin'
            || ($wpsr_vcs['ref_semantics'] ?? null) !== 'current-branch-head'
            || !is_string($wpsr_vcs['canonical_repository'] ?? null)
            || !is_string($wpsr_vcs['branch'] ?? null) || $wpsr_vcs['branch'] === '') {
            $wpsr_errors[] = $wpsr_label . ': VCS identity metadata malformed';
            $wpsr_branch = null;
            $wpsr_commit = null;
        } else {
            $wpsr_branch = wpsr_family_git_value($wpsr_path, ['branch', '--show-current']);
            $wpsr_commit = wpsr_family_git_value($wpsr_path, ['rev-parse', 'HEAD']);
            $wpsr_remote = wpsr_family_git_value($wpsr_path, ['remote', 'get-url', $wpsr_vcs['remote']]);
            if ($wpsr_branch === null || $wpsr_branch === ''
                || !wpsr_family_head_tracks_branch($wpsr_path, $wpsr_vcs['branch'])
                || !preg_match('/^[0-9a-f]{40}$/', (string) $wpsr_commit)
                || wpsr_family_canonical_repository($wpsr_remote) !== strtolower($wpsr_vcs['canonical_repository'])) {
                $wpsr_errors[] = $wpsr_label . ': VCS repository/branch/ref identity mismatch';
            }
        }
        $wpsr_metadata[] = [
            'id' => $wpsr_is_outside ? 'out-of-family' : $wpsr_row['id'],
            'role' => $wpsr_is_outside ? 'out-of-family' : $wpsr_row['role'],
            'slug' => $wpsr_row['slug'],
            'version' => $wpsr_is_outside ? ($wpsr_header['fields']['Version'] ?? 'unknown') : ($wpsr_versions[$wpsr_row['id']] ?? 'unknown'),
            'runtime' => $wpsr_is_outside ? 'not-selectable' : 'available',
            'selector' => !$wpsr_is_outside && !empty($wpsr_row['runtime']['selectable']) ? 'selectable' : 'runtime-only',
            'branch' => $wpsr_branch ?: 'unknown',
            'commit' => $wpsr_commit ?: 'unknown',
            'path' => $wpsr_path,
        ];
    }

    foreach ($wpsr_plugins as $wpsr_plugin) {
        if (!is_array($wpsr_plugin) || empty($wpsr_plugin['id'])) {
            continue;
        }
        foreach ((array) ($wpsr_plugin['version_compatibility'] ?? []) as $wpsr_constraint) {
            $wpsr_target = $wpsr_constraint['target'] ?? '';
            if (!isset($wpsr_versions[$wpsr_plugin['id']], $wpsr_versions[$wpsr_target])) {
                continue;
            }
            if (wpsr_family_version_satisfies($wpsr_versions[$wpsr_target], $wpsr_constraint['range']) !== true) {
                $wpsr_errors[] = 'version incompatibility: ' . $wpsr_plugin['id'] . ' requires ' . $wpsr_target . ' ' . $wpsr_constraint['range']
                    . ', found ' . $wpsr_versions[$wpsr_target];
            }
        }
    }

    return [
        'errors' => array_values(array_unique($wpsr_errors)),
        'skips' => $wpsr_skips,
        'ignored' => $wpsr_ignored,
        'metadata' => $wpsr_metadata,
        'stats' => [
            'candidates' => count($wpsr_candidates),
            'classified' => count(array_intersect(array_keys($wpsr_candidates), array_keys($wpsr_paths))),
            'in_family' => count($wpsr_plugins),
            'out_of_family' => count($wpsr_outside),
            'available' => count($wpsr_metadata) - count(array_filter($wpsr_metadata, static function ($wpsr_row) {
                return $wpsr_row['role'] === 'out-of-family';
            })),
            'skipped' => count($wpsr_skips),
        ],
    ];
}
