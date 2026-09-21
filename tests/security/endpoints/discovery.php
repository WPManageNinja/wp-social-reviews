<?php
/**
 * Phase 27 first-party endpoint source discovery.
 *
 * No WordPress/plugin runtime loads. Route files execute only against inert collector.
 * Hook registrations are tokenized; unresolved registration names/callbacks fail closed.
 */

final class WpsrEndpointRouteCollector
{
    private $wpsr_state;
    private $wpsr_prefix;
    private $wpsr_policy;
    private $wpsr_file;

    public function __construct($wpsr_state = null, $wpsr_prefix = '', $wpsr_policy = '', $wpsr_file = '')
    {
        $this->wpsr_state = $wpsr_state ?: (object) ['routes' => []];
        $this->wpsr_prefix = trim((string) $wpsr_prefix, '/');
        $this->wpsr_policy = (string) $wpsr_policy;
        $this->wpsr_file = (string) $wpsr_file;
    }

    public function prefix($wpsr_prefix)
    {
        return new self(
            $this->wpsr_state,
            trim($this->wpsr_prefix . '/' . trim((string) $wpsr_prefix, '/'), '/'),
            $this->wpsr_policy,
            $this->wpsr_file
        );
    }

    public function withPolicy($wpsr_policy)
    {
        $this->wpsr_policy = is_string($wpsr_policy) ? $wpsr_policy : 'callable';
        return $this;
    }

    public function group($wpsr_options, $wpsr_callback = null)
    {
        $wpsr_router = $this;
        if (is_array($wpsr_options)) {
            $wpsr_callback = $wpsr_callback ?: ($wpsr_options['callback'] ?? null);
            if (isset($wpsr_options['prefix'])) {
                $wpsr_router = $this->prefix($wpsr_options['prefix']);
            }
            if (isset($wpsr_options['policy'])) {
                $wpsr_router = $wpsr_router->withPolicy($wpsr_options['policy']);
            }
        } else {
            $wpsr_callback = $wpsr_options;
        }

        if (!is_callable($wpsr_callback)) {
            throw new RuntimeException('Route group callback is not callable or is ambiguous.');
        }
        $wpsr_callback($wpsr_router);
        return $this;
    }

    public function get($wpsr_path, $wpsr_handler) { return $this->wpsr_add('GET', $wpsr_path, $wpsr_handler); }
    public function post($wpsr_path, $wpsr_handler) { return $this->wpsr_add('POST', $wpsr_path, $wpsr_handler); }
    public function put($wpsr_path, $wpsr_handler) { return $this->wpsr_add('PUT', $wpsr_path, $wpsr_handler); }
    public function delete($wpsr_path, $wpsr_handler) { return $this->wpsr_add('DELETE', $wpsr_path, $wpsr_handler); }
    public function patch($wpsr_path, $wpsr_handler) { return $this->wpsr_add('PATCH', $wpsr_path, $wpsr_handler); }
    public function int($wpsr_identifiers) { return $this; }
    public function routes() { return $this->wpsr_state->routes; }
    public function __call($wpsr_method, $wpsr_arguments)
    {
        throw new RuntimeException('Unsupported route declaration or fluent method: ' . $wpsr_method);
    }

    private function wpsr_add($wpsr_method, $wpsr_path, $wpsr_handler)
    {
        $wpsr_path = '/' . ltrim((string) $wpsr_path, '/');
        $wpsr_line = 0;
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $wpsr_frame) {
            if (($wpsr_frame['file'] ?? '') === $this->wpsr_file) {
                $wpsr_line = (int) ($wpsr_frame['line'] ?? 0);
                break;
            }
        }
        $this->wpsr_state->routes[] = [
            'method' => $wpsr_method,
            'route' => '/' . trim($this->wpsr_prefix . '/' . trim($wpsr_path, '/'), '/'),
            'prefix' => $this->wpsr_prefix,
            'declaration' => $wpsr_method . ' ' . $this->wpsr_prefix . ':' . $wpsr_path,
            'policy' => $this->wpsr_policy,
            'handler_raw' => $wpsr_handler,
            'line' => $wpsr_line,
        ];
        return $this;
    }
}

function wpsr_endpoint_normalize_path($wpsr_path)
{
    return str_replace('\\', '/', (string) $wpsr_path);
}

function wpsr_endpoint_relative($wpsr_root, $wpsr_file)
{
    $wpsr_root = rtrim(wpsr_endpoint_normalize_path($wpsr_root), '/') . '/';
    $wpsr_file = wpsr_endpoint_normalize_path($wpsr_file);
    return strpos($wpsr_file, $wpsr_root) === 0 ? substr($wpsr_file, strlen($wpsr_root)) : $wpsr_file;
}

function wpsr_endpoint_php_files($wpsr_root)
{
    $wpsr_files = [];
    $wpsr_iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($wpsr_root, FilesystemIterator::SKIP_DOTS),
            static function ($wpsr_current) {
                if (!$wpsr_current->isDir()) {
                    return true;
                }
                return !in_array($wpsr_current->getFilename(), [
                    '.git', 'tests', 'vendor', 'node_modules', 'dev', 'dist', 'build', 'builds', 'assets', 'resources',
                ], true);
            }
        )
    );
    foreach ($wpsr_iterator as $wpsr_file) {
        if ($wpsr_file->isFile() && strtolower($wpsr_file->getExtension()) === 'php') {
            $wpsr_rel = wpsr_endpoint_relative($wpsr_root, $wpsr_file->getPathname());
            if (strpos($wpsr_rel, 'app/Services/Libs/') === 0) {
                continue;
            }
            $wpsr_files[] = $wpsr_file->getPathname();
        }
    }
    sort($wpsr_files, SORT_STRING);
    return $wpsr_files;
}

function wpsr_endpoint_source_without_comments($wpsr_source)
{
    $wpsr_clean = '';
    foreach (token_get_all($wpsr_source) as $wpsr_token) {
        if (is_array($wpsr_token) && in_array($wpsr_token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $wpsr_clean .= str_repeat("\n", substr_count($wpsr_token[1], "\n"));
            continue;
        }
        $wpsr_clean .= is_array($wpsr_token) ? $wpsr_token[1] : $wpsr_token;
    }
    return $wpsr_clean;
}

function wpsr_endpoint_unquote($wpsr_value, $wpsr_quote)
{
    if ($wpsr_quote === "'") {
        return str_replace(["\\\\", "\\'"], ["\\", "'"], $wpsr_value);
    }
    return stripcslashes($wpsr_value);
}

function wpsr_endpoint_file_context($wpsr_root, $wpsr_file)
{
    $wpsr_source = file_get_contents($wpsr_file);
    if ($wpsr_source === false) {
        throw new RuntimeException('Could not read source file: ' . $wpsr_file);
    }
    $wpsr_clean = wpsr_endpoint_source_without_comments($wpsr_source);
    preg_match('/\bnamespace\s+([^;]+);/', $wpsr_clean, $wpsr_namespace_match);
    $wpsr_namespace = trim($wpsr_namespace_match[1] ?? '');
    $wpsr_imports = [];
    if (preg_match_all('/^\s*use\s+([^;{]+);/mi', $wpsr_clean, $wpsr_use_matches)) {
        foreach ($wpsr_use_matches[1] as $wpsr_use) {
            $wpsr_use = trim($wpsr_use);
            if (stripos($wpsr_use, 'function ') === 0 || stripos($wpsr_use, 'const ') === 0 || strpos($wpsr_use, ',') !== false) {
                continue;
            }
            $wpsr_parts = preg_split('/\s+as\s+/i', $wpsr_use);
            $wpsr_fqcn = ltrim(trim($wpsr_parts[0]), '\\');
            $wpsr_alias = isset($wpsr_parts[1]) ? trim($wpsr_parts[1]) : basename(str_replace('\\', '/', $wpsr_fqcn));
            $wpsr_imports[$wpsr_alias] = $wpsr_fqcn;
        }
    }
    preg_match('/\b(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/', $wpsr_clean, $wpsr_class_match);
    $wpsr_class = $wpsr_class_match[1] ?? '';
    $wpsr_fqcn = $wpsr_class ? trim($wpsr_namespace . '\\' . $wpsr_class, '\\') : '';
    $wpsr_methods = [];
    if (preg_match_all('/\bfunction\s+&?\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $wpsr_clean, $wpsr_method_matches)) {
        $wpsr_methods = array_values(array_unique($wpsr_method_matches[1]));
    }
    $wpsr_constants = [];
    if (preg_match_all('/\b(?:public\s+|protected\s+|private\s+)?const\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*([\'\"])(.*?)\2\s*;/s', $wpsr_clean, $wpsr_const_matches, PREG_SET_ORDER)) {
        foreach ($wpsr_const_matches as $wpsr_const_match) {
            $wpsr_constants[$wpsr_const_match[1]] = wpsr_endpoint_unquote($wpsr_const_match[3], $wpsr_const_match[2]);
        }
    }
    return [
        'root' => $wpsr_root,
        'file' => $wpsr_file,
        'relative' => wpsr_endpoint_relative($wpsr_root, $wpsr_file),
        'source' => $wpsr_source,
        'clean' => $wpsr_clean,
        'namespace' => $wpsr_namespace,
        'imports' => $wpsr_imports,
        'class' => $wpsr_fqcn,
        'methods' => $wpsr_methods,
        'constants' => $wpsr_constants,
    ];
}

function wpsr_endpoint_class_map($wpsr_root, array $wpsr_files)
{
    $wpsr_map = [];
    $wpsr_contexts = [];
    foreach ($wpsr_files as $wpsr_file) {
        $wpsr_context = wpsr_endpoint_file_context($wpsr_root, $wpsr_file);
        $wpsr_contexts[$wpsr_file] = $wpsr_context;
        if ($wpsr_context['class']) {
            $wpsr_map[$wpsr_context['class']] = [
                'file' => $wpsr_context['relative'],
                'absolute_file' => $wpsr_file,
                'methods' => array_fill_keys($wpsr_context['methods'], true),
                'constants' => $wpsr_context['constants'],
            ];
        }
    }
    return [$wpsr_map, $wpsr_contexts];
}

function wpsr_endpoint_resolve_class($wpsr_name, array $wpsr_context)
{
    $wpsr_name = trim((string) $wpsr_name);
    $wpsr_name = preg_replace('/^(?:new\s+)/i', '', $wpsr_name);
    $wpsr_name = preg_replace('/\s*\(.*$/s', '', $wpsr_name);
    $wpsr_name = ltrim(trim($wpsr_name), '\\');
    if ($wpsr_name === 'self' || $wpsr_name === 'static' || $wpsr_name === '$this') {
        return $wpsr_context['class'];
    }
    $wpsr_first = strtok($wpsr_name, '\\');
    if (isset($wpsr_context['imports'][$wpsr_first])) {
        return $wpsr_context['imports'][$wpsr_first] . substr($wpsr_name, strlen($wpsr_first));
    }
    if (strpos($wpsr_name, '\\') !== false) {
        return $wpsr_name;
    }
    if ($wpsr_context['namespace']) {
        return $wpsr_context['namespace'] . '\\' . $wpsr_name;
    }
    return $wpsr_name;
}

function wpsr_endpoint_split_expression($wpsr_expression)
{
    $wpsr_tokens = token_get_all('<?php ' . $wpsr_expression);
    $wpsr_parts = [];
    $wpsr_current = '';
    $wpsr_depth = 0;
    foreach (array_slice($wpsr_tokens, 1) as $wpsr_token) {
        $wpsr_text = is_array($wpsr_token) ? $wpsr_token[1] : $wpsr_token;
        if (in_array($wpsr_text, ['(', '[', '{'], true)) {
            $wpsr_depth++;
        } elseif (in_array($wpsr_text, [')', ']', '}'], true)) {
            $wpsr_depth--;
        }
        if ($wpsr_text === ',' && $wpsr_depth === 0) {
            $wpsr_parts[] = trim($wpsr_current);
            $wpsr_current = '';
            continue;
        }
        $wpsr_current .= $wpsr_text;
    }
    if (trim($wpsr_current) !== '') {
        $wpsr_parts[] = trim($wpsr_current);
    }
    return $wpsr_parts;
}

function wpsr_endpoint_last_assignment($wpsr_variable, array $wpsr_context, $wpsr_before_offset)
{
    $wpsr_prefix = substr($wpsr_context['clean'], 0, $wpsr_before_offset);
    $wpsr_pattern = '/' . preg_quote($wpsr_variable, '/') . '\s*=\s*(\[[^;]+\]|array\s*\([^;]+\)|new\s+[A-Za-z_\\\\][A-Za-z0-9_\\\\]*\s*\([^;]*\)|[^;]+)\s*;/s';
    if (!preg_match_all($wpsr_pattern, $wpsr_prefix, $wpsr_matches)) {
        return null;
    }
    return trim(end($wpsr_matches[1]));
}

function wpsr_endpoint_literal($wpsr_expression, array $wpsr_context, array $wpsr_class_map, $wpsr_offset, $wpsr_depth = 0)
{
    if ($wpsr_depth > 4) {
        return null;
    }
    $wpsr_expression = trim($wpsr_expression);
    if (preg_match('/^([\'\"])(.*)\1$/s', $wpsr_expression, $wpsr_match)) {
        return wpsr_endpoint_unquote($wpsr_match[2], $wpsr_match[1]);
    }
    if (preg_match('/^(\$[A-Za-z_][A-Za-z0-9_]*)$/', $wpsr_expression, $wpsr_match)) {
        $wpsr_assignment = wpsr_endpoint_last_assignment($wpsr_match[1], $wpsr_context, $wpsr_offset);
        return $wpsr_assignment === null ? null : wpsr_endpoint_literal($wpsr_assignment, $wpsr_context, $wpsr_class_map, $wpsr_offset, $wpsr_depth + 1);
    }
    if (preg_match('/^([A-Za-z_\\\\][A-Za-z0-9_\\\\]*|self|static)::([A-Za-z_][A-Za-z0-9_]*)$/', $wpsr_expression, $wpsr_match)) {
        $wpsr_class = wpsr_endpoint_resolve_class($wpsr_match[1], $wpsr_context);
        return $wpsr_class_map[$wpsr_class]['constants'][$wpsr_match[2]] ?? null;
    }
    $wpsr_concat = wpsr_endpoint_split_expression(str_replace('.', ',', $wpsr_expression));
    if (strpos($wpsr_expression, '.') !== false && count($wpsr_concat) > 1) {
        $wpsr_value = '';
        foreach ($wpsr_concat as $wpsr_part) {
            $wpsr_resolved = wpsr_endpoint_literal($wpsr_part, $wpsr_context, $wpsr_class_map, $wpsr_offset, $wpsr_depth + 1);
            if ($wpsr_resolved === null) {
                return null;
            }
            $wpsr_value .= $wpsr_resolved;
        }
        return $wpsr_value;
    }
    return null;
}

function wpsr_endpoint_callback($wpsr_expression, array $wpsr_context, array $wpsr_class_map, $wpsr_offset, &$wpsr_error = null, $wpsr_depth = 0)
{
    if ($wpsr_depth > 4) {
        $wpsr_error = 'callback indirection depth exceeded';
        return null;
    }
    $wpsr_expression = trim($wpsr_expression);
    if (preg_match('/^(?:static\s+)?function\b|^fn\b/', $wpsr_expression)) {
        $wpsr_line = 1 + substr_count(substr($wpsr_context['clean'], 0, $wpsr_offset), "\n");
        return 'closure@' . $wpsr_context['relative'] . ':' . $wpsr_line;
    }
    if (preg_match('/^(\$[A-Za-z_][A-Za-z0-9_]*)$/', $wpsr_expression, $wpsr_match)) {
        $wpsr_assignment = wpsr_endpoint_last_assignment($wpsr_match[1], $wpsr_context, $wpsr_offset);
        if ($wpsr_assignment === null) {
            $wpsr_error = 'unresolved callback variable ' . $wpsr_match[1];
            return null;
        }
        return wpsr_endpoint_callback($wpsr_assignment, $wpsr_context, $wpsr_class_map, $wpsr_offset, $wpsr_error, $wpsr_depth + 1);
    }
    if (preg_match('/^([\'\"])(.*?)\1$/s', $wpsr_expression, $wpsr_match)) {
        $wpsr_value = wpsr_endpoint_unquote($wpsr_match[2], $wpsr_match[1]);
        if (strpos($wpsr_value, '@') === false) {
            if (function_exists($wpsr_value)) {
                return 'function:' . $wpsr_value;
            }
            $wpsr_error = 'callback string has no Class@method shape: ' . $wpsr_value;
            return null;
        }
        [$wpsr_class_name, $wpsr_method] = explode('@', $wpsr_value, 2);
        $wpsr_class = wpsr_endpoint_resolve_class($wpsr_class_name, $wpsr_context);
        if (strpos($wpsr_class_name, '\\') === false) {
            $wpsr_candidates = [];
            foreach ($wpsr_class_map as $wpsr_candidate => $wpsr_definition) {
                if (basename(str_replace('\\', '/', $wpsr_candidate)) === $wpsr_class_name
                    && isset($wpsr_definition['methods'][$wpsr_method])) {
                    $wpsr_candidates[] = $wpsr_candidate;
                }
            }
            if (count($wpsr_candidates) !== 1) {
                $wpsr_error = 'missing/ambiguous callback class ' . $wpsr_class_name . '@' . $wpsr_method;
                return null;
            }
            $wpsr_class = $wpsr_candidates[0];
        }
        if (!isset($wpsr_class_map[$wpsr_class]['methods'][$wpsr_method])) {
            $wpsr_error = 'missing callback method ' . $wpsr_class . '::' . $wpsr_method;
            return null;
        }
        return $wpsr_class . '::' . $wpsr_method;
    }

    $wpsr_inner = null;
    if (preg_match('/^\[(.*)\]$/s', $wpsr_expression, $wpsr_match)) {
        $wpsr_inner = $wpsr_match[1];
    } elseif (preg_match('/^array\s*\((.*)\)$/s', $wpsr_expression, $wpsr_match)) {
        $wpsr_inner = $wpsr_match[1];
    }
    if ($wpsr_inner !== null) {
        $wpsr_parts = wpsr_endpoint_split_expression($wpsr_inner);
        if (count($wpsr_parts) !== 2) {
            $wpsr_error = 'callback array must contain exactly class/object and method';
            return null;
        }
        $wpsr_target = trim($wpsr_parts[0]);
        if (preg_match('/^(\$[A-Za-z_][A-Za-z0-9_]*)$/', $wpsr_target, $wpsr_var_match) && $wpsr_target !== '$this') {
            $wpsr_assignment = wpsr_endpoint_last_assignment($wpsr_var_match[1], $wpsr_context, $wpsr_offset);
            if ($wpsr_assignment === null || !preg_match('/^new\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)/', trim($wpsr_assignment), $wpsr_new_match)) {
                $wpsr_error = 'unresolved callback object variable ' . $wpsr_target;
                return null;
            }
            $wpsr_class = wpsr_endpoint_resolve_class($wpsr_new_match[1], $wpsr_context);
        } elseif ($wpsr_target === '$this') {
            $wpsr_class = $wpsr_context['class'];
        } elseif (preg_match('/^(?:new\s+)?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)(?:::class)?(?:\s*\(.*\))?$/s', $wpsr_target, $wpsr_class_match)) {
            $wpsr_class = wpsr_endpoint_resolve_class($wpsr_class_match[1], $wpsr_context);
        } else {
            $wpsr_error = 'unresolved callback target ' . $wpsr_target;
            return null;
        }
        $wpsr_method = wpsr_endpoint_literal($wpsr_parts[1], $wpsr_context, $wpsr_class_map, $wpsr_offset);
        if (!$wpsr_class || !$wpsr_method) {
            $wpsr_error = 'callback class or method is empty';
            return null;
        }
        if (!isset($wpsr_class_map[$wpsr_class]['methods'][$wpsr_method])) {
            $wpsr_error = 'missing callback ' . $wpsr_class . '::' . $wpsr_method;
            return null;
        }
        return $wpsr_class . '::' . $wpsr_method;
    }
    $wpsr_error = 'unsupported callback expression ' . preg_replace('/\s+/', ' ', $wpsr_expression);
    return null;
}

function wpsr_endpoint_calls(array $wpsr_context)
{
    $wpsr_source = $wpsr_context['clean'];
    $wpsr_tokens = token_get_all($wpsr_source);
    $wpsr_offsets = [];
    $wpsr_offset = 0;
    foreach ($wpsr_tokens as $wpsr_index => $wpsr_token) {
        $wpsr_offsets[$wpsr_index] = $wpsr_offset;
        $wpsr_offset += strlen(is_array($wpsr_token) ? $wpsr_token[1] : $wpsr_token);
    }
    $wpsr_names = ['add_action', 'addAction', 'add_shortcode', 'add_filter', 'addFilter', 'register_rest_route', 'add_rewrite_rule', 'add_rewrite_tag'];
    $wpsr_calls = [];
    $wpsr_count = count($wpsr_tokens);
    for ($wpsr_i = 0; $wpsr_i < $wpsr_count; $wpsr_i++) {
        $wpsr_token = $wpsr_tokens[$wpsr_i];
        if (!is_array($wpsr_token) || $wpsr_token[0] !== T_STRING || !in_array($wpsr_token[1], $wpsr_names, true)) {
            continue;
        }
        $wpsr_j = $wpsr_i + 1;
        while ($wpsr_j < $wpsr_count && is_array($wpsr_tokens[$wpsr_j]) && in_array($wpsr_tokens[$wpsr_j][0], [T_WHITESPACE], true)) {
            $wpsr_j++;
        }
        if (($wpsr_tokens[$wpsr_j] ?? null) !== '(') {
            continue;
        }
        $wpsr_depth = 0;
        $wpsr_argument_tokens = [];
        $wpsr_end = $wpsr_j;
        for (; $wpsr_end < $wpsr_count; $wpsr_end++) {
            $wpsr_part = $wpsr_tokens[$wpsr_end];
            $wpsr_text = is_array($wpsr_part) ? $wpsr_part[1] : $wpsr_part;
            if ($wpsr_text === '(') {
                $wpsr_depth++;
                if ($wpsr_depth === 1) {
                    continue;
                }
            } elseif ($wpsr_text === ')') {
                $wpsr_depth--;
                if ($wpsr_depth === 0) {
                    break;
                }
            }
            if ($wpsr_depth >= 1) {
                $wpsr_argument_tokens[] = $wpsr_text;
            }
        }
        if ($wpsr_depth !== 0) {
            $wpsr_calls[] = ['name' => $wpsr_token[1], 'line' => $wpsr_token[2], 'offset' => $wpsr_offsets[$wpsr_i], 'args' => [], 'parse_error' => 'unbalanced call'];
            continue;
        }
        $wpsr_calls[] = [
            'name' => $wpsr_token[1],
            'line' => $wpsr_token[2],
            'offset' => $wpsr_offsets[$wpsr_i],
            'args' => wpsr_endpoint_split_expression(implode('', $wpsr_argument_tokens)),
            'parse_error' => '',
        ];
        $wpsr_i = $wpsr_end;
    }
    return $wpsr_calls;
}

function wpsr_endpoint_route_callback($wpsr_raw, array $wpsr_context, array $wpsr_class_map, &$wpsr_error)
{
    if (is_array($wpsr_raw) && count($wpsr_raw) === 2) {
        $wpsr_class = ltrim((string) $wpsr_raw[0], '\\');
        $wpsr_method = (string) $wpsr_raw[1];
        if (!isset($wpsr_class_map[$wpsr_class]['methods'][$wpsr_method])) {
            $wpsr_error = 'missing REST callback ' . $wpsr_class . '::' . $wpsr_method;
            return null;
        }
        return $wpsr_class . '::' . $wpsr_method;
    }
    if (is_string($wpsr_raw)) {
        return wpsr_endpoint_callback(var_export($wpsr_raw, true), $wpsr_context, $wpsr_class_map, 0, $wpsr_error);
    }
    $wpsr_error = 'unsupported REST callback shape';
    return null;
}

function wpsr_endpoint_callable_slice($wpsr_source, $wpsr_method = null, $wpsr_line = null)
{
    $wpsr_tokens = token_get_all($wpsr_source);
    $wpsr_matches = [];
    $wpsr_count = count($wpsr_tokens);
    for ($wpsr_index = 0; $wpsr_index < $wpsr_count; $wpsr_index++) {
        $wpsr_token = $wpsr_tokens[$wpsr_index];
        if (!is_array($wpsr_token) || $wpsr_token[0] !== T_FUNCTION) continue;
        $wpsr_start_line = $wpsr_token[2];
        $wpsr_name = null;
        $wpsr_cursor = $wpsr_index + 1;
        while ($wpsr_cursor < $wpsr_count) {
            $wpsr_part = $wpsr_tokens[$wpsr_cursor];
            if (is_array($wpsr_part) && $wpsr_part[0] === T_STRING) {
                $wpsr_name = $wpsr_part[1];
                break;
            }
            $wpsr_text = is_array($wpsr_part) ? $wpsr_part[1] : $wpsr_part;
            if ($wpsr_text === '(') break;
            $wpsr_cursor++;
        }
        if ($wpsr_method !== null && $wpsr_name !== $wpsr_method) continue;
        if ($wpsr_line !== null && $wpsr_start_line !== (int) $wpsr_line) continue;
        while ($wpsr_cursor < $wpsr_count && (is_array($wpsr_tokens[$wpsr_cursor]) || $wpsr_tokens[$wpsr_cursor] !== '{')) $wpsr_cursor++;
        if ($wpsr_cursor >= $wpsr_count) continue;
        $wpsr_depth = 0;
        $wpsr_text = '';
        for (; $wpsr_cursor < $wpsr_count; $wpsr_cursor++) {
            $wpsr_part = $wpsr_tokens[$wpsr_cursor];
            $wpsr_piece = is_array($wpsr_part) ? $wpsr_part[1] : $wpsr_part;
            $wpsr_text .= $wpsr_piece;
            if ($wpsr_piece === '{') $wpsr_depth++;
            if ($wpsr_piece === '}' && --$wpsr_depth === 0) break;
        }
        $wpsr_matches[] = ['line' => $wpsr_start_line, 'source' => $wpsr_text];
    }
    return $wpsr_matches;
}

function wpsr_endpoint_contract_calls($wpsr_source)
{
    preg_match_all('/(?:(?:->|::)\s*)?([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $wpsr_source, $wpsr_matches);
    $wpsr_calls = array_values(array_unique($wpsr_matches[1] ?? []));
    sort($wpsr_calls, SORT_STRING);
    return $wpsr_calls;
}

function wpsr_endpoint_contract_signals(array $wpsr_calls, array $wpsr_exact, $wpsr_prefix_pattern = '')
{
    $wpsr_signals = [];
    foreach ($wpsr_calls as $wpsr_call) {
        if (in_array($wpsr_call, $wpsr_exact, true) || ($wpsr_prefix_pattern && preg_match($wpsr_prefix_pattern, $wpsr_call))) {
            $wpsr_signals[] = $wpsr_call;
        }
    }
    return $wpsr_signals;
}

function wpsr_endpoint_contract_callable(array $wpsr_row, array $wpsr_class_map, array $wpsr_contexts, &$wpsr_error)
{
    $wpsr_callback = $wpsr_row['callback'];
    if (preg_match('/^closure@(.+):(\d+)$/', $wpsr_callback, $wpsr_match)) {
        $wpsr_candidates = array_values(array_filter($wpsr_contexts, static function ($wpsr_context) use ($wpsr_match) {
            return $wpsr_context['relative'] === $wpsr_match[1];
        }));
        if (count($wpsr_candidates) !== 1) {
            $wpsr_error = 'missing/ambiguous closure contract source for ' . $wpsr_callback;
            return null;
        }
        $wpsr_slices = wpsr_endpoint_callable_slice($wpsr_candidates[0]['clean'], null, (int) $wpsr_match[2]);
        if (count($wpsr_slices) !== 1) {
            $wpsr_error = 'missing/ambiguous closure body for ' . $wpsr_callback;
            return null;
        }
        return ['file' => $wpsr_match[1], 'line' => $wpsr_slices[0]['line'], 'source' => $wpsr_slices[0]['source']];
    }
    if (!preg_match('/^(.+)::([A-Za-z_][A-Za-z0-9_]*)$/', $wpsr_callback, $wpsr_match)) {
        $wpsr_error = 'unsupported security-contract callback ' . $wpsr_callback;
        return null;
    }
    $wpsr_definition = $wpsr_class_map[$wpsr_match[1]] ?? null;
    if (!$wpsr_definition || empty($wpsr_definition['absolute_file'])) {
        $wpsr_error = 'missing security-contract class source ' . $wpsr_match[1];
        return null;
    }
    $wpsr_source = file_get_contents($wpsr_definition['absolute_file']);
    $wpsr_slices = wpsr_endpoint_callable_slice(wpsr_endpoint_source_without_comments((string) $wpsr_source), $wpsr_match[2]);
    if (count($wpsr_slices) !== 1) {
        $wpsr_error = 'missing/ambiguous security-contract method ' . $wpsr_callback;
        return null;
    }
    return ['file' => $wpsr_definition['file'], 'line' => $wpsr_slices[0]['line'], 'source' => $wpsr_slices[0]['source']];
}

function wpsr_endpoint_contract_policy(array $wpsr_row, array $wpsr_class_map, &$wpsr_error)
{
    $wpsr_policy = ltrim((string) $wpsr_row['policy'], '\\');
    $wpsr_candidates = [];
    foreach ($wpsr_class_map as $wpsr_class => $wpsr_definition) {
        if ($wpsr_class === $wpsr_policy || basename(str_replace('\\', '/', $wpsr_class)) === basename(str_replace('\\', '/', $wpsr_policy))) {
            if (isset($wpsr_definition['methods']['verifyRequest'])) $wpsr_candidates[$wpsr_class] = $wpsr_definition;
        }
    }
    if (count($wpsr_candidates) !== 1) {
        $wpsr_error = 'missing/ambiguous policy security contract ' . $wpsr_row['policy'];
        return null;
    }
    $wpsr_class = array_key_first($wpsr_candidates);
    $wpsr_definition = $wpsr_candidates[$wpsr_class];
    $wpsr_source = wpsr_endpoint_source_without_comments((string) file_get_contents($wpsr_definition['absolute_file']));
    $wpsr_slices = wpsr_endpoint_callable_slice($wpsr_source, 'verifyRequest');
    if (count($wpsr_slices) !== 1) {
        $wpsr_error = 'missing/ambiguous verifyRequest contract ' . $wpsr_class;
        return null;
    }
    preg_match_all('/currentUserCan\s*\(\s*([\'\"])([^\'\"]+)\1\s*\)/', $wpsr_slices[0]['source'], $wpsr_caps);
    $wpsr_capabilities = array_values(array_unique($wpsr_caps[2] ?? []));
    sort($wpsr_capabilities, SORT_STRING);
    $wpsr_any_plugin_capability = strpos($wpsr_slices[0]['source'], 'currentUserCanAny(') !== false;
    if (!$wpsr_capabilities && !$wpsr_any_plugin_capability) {
        $wpsr_error = 'policy authorization contract has no capability signal ' . $wpsr_class;
        return null;
    }
    $wpsr_mode = $wpsr_any_plugin_capability ? 'any-plugin-capability' : (strpos($wpsr_slices[0]['source'], '&&') !== false ? 'all' : 'any');
    return [
        'file' => $wpsr_definition['file'],
        'line' => $wpsr_slices[0]['line'],
        'policy' => $wpsr_class,
        'source_fingerprint' => hash('sha256', $wpsr_slices[0]['source']),
        'capabilities' => $wpsr_capabilities,
        'mode' => $wpsr_mode,
    ];
}

function wpsr_endpoint_security_metadata(array $wpsr_row, array $wpsr_class_map, array $wpsr_contexts, &$wpsr_error)
{
    $wpsr_callable = wpsr_endpoint_contract_callable($wpsr_row, $wpsr_class_map, $wpsr_contexts, $wpsr_error);
    if (!$wpsr_callable) return null;
    $wpsr_calls = wpsr_endpoint_contract_calls($wpsr_callable['source']);
    $wpsr_auth_signals = wpsr_endpoint_contract_signals($wpsr_calls, [
        'check_ajax_referer', 'check_admin_referer', 'wp_verify_nonce', 'current_user_can', 'currentUserCan',
        'currentUserCanAny', 'is_user_logged_in', 'isPublicTemplate', 'isTemplatePublic',
    ]);
    $wpsr_object_signals = wpsr_endpoint_contract_signals($wpsr_calls, [
        'find', 'findOrFail', 'first', 'get', 'get_option', 'get_post', 'get_post_meta', 'get_post_status',
        'get_post_type', 'get_query_var', 'isPublicRenderableTemplate', 'isPublicTemplate', 'isTemplatePublic', 'where', 'whereIn', 'whereType',
    ]);
    $wpsr_effect_signals = wpsr_endpoint_contract_signals($wpsr_calls, [
        'add_post_meta', 'delete_option', 'delete_post_meta', 'file_put_contents', 'set_post_thumbnail', 'unlink',
        'update_option', 'update_post_meta', 'wp_delete_file', 'wp_insert_post', 'wp_redirect', 'wp_remote_post',
        'wp_safe_redirect', 'wp_update_post',
    ], '/^(?:create|delete|destroy|insert|remove|save|store|sync|update|upload|write)/i');

    $wpsr_contract = [
        'handler' => [
            'file' => $wpsr_callable['file'],
            'line' => $wpsr_callable['line'],
            'source_fingerprint' => hash('sha256', $wpsr_callable['source']),
        ],
        'authorization' => ['signals' => $wpsr_auth_signals],
        'object_scope' => ['signals' => $wpsr_object_signals],
        'side_effect' => ['signals' => $wpsr_effect_signals],
        'coverage' => ['disposition' => 'THIN', 'runtime' => 'source-only', 'reference' => 'tests/security/endpoints/lint.php::source-contract-v1'],
    ];
    if ($wpsr_row['surface'] === 'rest') {
        $wpsr_policy = wpsr_endpoint_contract_policy($wpsr_row, $wpsr_class_map, $wpsr_error);
        if (!$wpsr_policy) return null;
        $wpsr_contract['authorization']['policy'] = $wpsr_policy;
        $wpsr_auth = 'policy=' . basename(str_replace('\\', '/', $wpsr_policy['policy']))
            . '; mode=' . $wpsr_policy['mode'] . '; capabilities=' . ($wpsr_policy['capabilities'] ? implode(',', $wpsr_policy['capabilities']) : '(plugin-capability-set)');
    } else {
        $wpsr_auth = 'dispatch=' . ($wpsr_row['public'] ? 'public' : 'authenticated')
            . '; handler-guards=' . ($wpsr_auth_signals ? implode(',', $wpsr_auth_signals) : 'none-observed');
    }
    $wpsr_contract['authorization']['fingerprint'] = hash('sha256', json_encode($wpsr_contract['authorization']));
    $wpsr_contract['object_scope']['fingerprint'] = hash('sha256', json_encode($wpsr_contract['object_scope']));
    $wpsr_contract['side_effect']['fingerprint'] = hash('sha256', json_encode($wpsr_contract['side_effect']));

    return [
        'auth_mechanism' => $wpsr_auth,
        'object_scope' => 'handler-object-signals=' . ($wpsr_object_signals ? implode(',', $wpsr_object_signals) : 'none-observed'),
        'side_effect' => 'handler-effect-signals=' . ($wpsr_effect_signals ? implode(',', $wpsr_effect_signals) : 'none-observed'),
        'test_disposition' => 'THIN',
        'existing_test' => 'Exact policy/handler source contract: tests/security/endpoints/lint.php::source-contract-v1',
        'runtime' => $wpsr_row['plugin'] === 'pro'
            ? 'source-only; Pro not activated or executed'
            : 'source-only contract; no endpoint runtime behavior claimed',
        'security_contract' => $wpsr_contract,
    ];
}

function wpsr_endpoint_read_db_version($wpsr_core_root)
{
    foreach (['wp-social-reviews.php', 'boot/app.php'] as $wpsr_relative) {
        $wpsr_file = $wpsr_core_root . '/' . $wpsr_relative;
        if (!is_file($wpsr_file)) {
            continue;
        }
        $wpsr_source = file_get_contents($wpsr_file);
        if (preg_match('/[\'\"]WPSOCIALREVIEWS_DB_VERSION[\'\"]\s*,\s*([0-9]+)/', (string) $wpsr_source, $wpsr_match)) {
            return (int) $wpsr_match[1];
        }
    }
    return null;
}

function wpsr_endpoint_discover_plugin($wpsr_plugin, $wpsr_root, $wpsr_core_root)
{
    $wpsr_errors = [];
    if (!is_dir($wpsr_root)) {
        return ['rows' => [], 'errors' => ['plugin root missing: ' . $wpsr_root], 'stats' => ['files' => 0, 'calls' => 0], 'absence' => []];
    }
    $wpsr_files = wpsr_endpoint_php_files($wpsr_root);
    if (!$wpsr_files) {
        return ['rows' => [], 'errors' => ['zero first-party PHP files discovered for ' . $wpsr_plugin], 'stats' => ['files' => 0, 'calls' => 0], 'absence' => []];
    }
    [$wpsr_class_map, $wpsr_contexts] = wpsr_endpoint_class_map($wpsr_root, $wpsr_files);
    if ($wpsr_plugin === 'pro' && realpath($wpsr_root) !== realpath($wpsr_core_root)) {
        $wpsr_core_files = wpsr_endpoint_php_files($wpsr_core_root);
        [$wpsr_core_class_map] = wpsr_endpoint_class_map($wpsr_core_root, $wpsr_core_files);
        $wpsr_class_map += $wpsr_core_class_map;
    }
    $wpsr_route_file = $wpsr_root . '/app/Http/Routes/api.php';
    if (!is_file($wpsr_route_file)) {
        $wpsr_errors[] = 'REST route source missing for ' . $wpsr_plugin;
    }

    $wpsr_rows = [];
    if (is_file($wpsr_route_file)) {
        if (!defined('ABSPATH')) {
            define('ABSPATH', rtrim($wpsr_core_root, '/') . '/');
        }
        if (!defined('WPSOCIALREVIEWS_DB_VERSION')) {
            $wpsr_db_version = wpsr_endpoint_read_db_version($wpsr_core_root);
            if (!$wpsr_db_version) {
                $wpsr_errors[] = 'could not resolve WPSOCIALREVIEWS_DB_VERSION used by route conditions';
            } else {
                define('WPSOCIALREVIEWS_DB_VERSION', $wpsr_db_version);
            }
        }
        $router = new WpsrEndpointRouteCollector(null, '', '', $wpsr_route_file);
        try {
            require $wpsr_route_file;
            $wpsr_routes = $router->routes();
        } catch (Throwable $wpsr_exception) {
            $wpsr_routes = [];
            $wpsr_errors[] = 'route parser failure for ' . $wpsr_plugin . ': ' . $wpsr_exception->getMessage();
        }
        unset($router);
        $wpsr_route_context = $wpsr_contexts[$wpsr_route_file] ?? wpsr_endpoint_file_context($wpsr_root, $wpsr_route_file);
        foreach ($wpsr_routes as $wpsr_route) {
            $wpsr_callback_error = '';
            $wpsr_callback = wpsr_endpoint_route_callback($wpsr_route['handler_raw'], $wpsr_route_context, $wpsr_class_map, $wpsr_callback_error);
            if (!$wpsr_callback) {
                $wpsr_errors[] = $wpsr_callback_error . ' at ' . wpsr_endpoint_relative($wpsr_root, $wpsr_route_file) . ':' . $wpsr_route['line'];
                continue;
            }
            if ($wpsr_route['policy'] === '') {
                $wpsr_errors[] = 'REST route has no mounted policy: ' . $wpsr_route['method'] . ' ' . $wpsr_route['route'];
            }
            $wpsr_full_path = '/wpsocialreviews/v2' . ($wpsr_route['route'] === '/' ? '' : $wpsr_route['route']);
            $wpsr_rows[] = [
                'id' => $wpsr_plugin . ':rest:' . $wpsr_route['method'] . ':' . $wpsr_full_path,
                'plugin' => $wpsr_plugin,
                'surface' => 'rest',
                'registration' => 'wpfluent-router',
                'method' => $wpsr_route['method'],
                'path' => $wpsr_full_path,
                'callback' => $wpsr_callback,
                'policy' => $wpsr_route['policy'],
                'public' => false,
                'source_key' => $wpsr_route['declaration'],
                'evidence' => ['file' => 'app/Http/Routes/api.php', 'line' => $wpsr_route['line']],
            ];
        }
        if (!$wpsr_routes) {
            $wpsr_errors[] = 'zero REST declarations discovered for ' . $wpsr_plugin;
        }
    }

    $wpsr_call_count = 0;
    $wpsr_raw_body = [];
    $wpsr_ipn = [];
    $wpsr_webhook = [];
    foreach ($wpsr_contexts as $wpsr_file => $wpsr_context) {
        if (strpos($wpsr_context['clean'], 'php://input') !== false) {
            $wpsr_raw_body[] = $wpsr_context['relative'];
        }
        if (preg_match('/\bipn\b/i', $wpsr_context['clean'])) {
            $wpsr_ipn[] = $wpsr_context['relative'];
        }
        if (preg_match('/\bwebhook\b/i', $wpsr_context['clean'])) {
            $wpsr_webhook[] = $wpsr_context['relative'];
        }
        foreach (wpsr_endpoint_calls($wpsr_context) as $wpsr_call) {
            $wpsr_call_count++;
            if ($wpsr_call['parse_error']) {
                $wpsr_errors[] = 'parser ambiguity ' . $wpsr_context['relative'] . ':' . $wpsr_call['line'] . ' ' . $wpsr_call['parse_error'];
                continue;
            }
            if ($wpsr_call['name'] === 'register_rest_route') {
                $wpsr_errors[] = 'direct register_rest_route requires explicit parser support at ' . $wpsr_context['relative'] . ':' . $wpsr_call['line'];
                continue;
            }
            if (in_array($wpsr_call['name'], ['add_rewrite_rule', 'add_rewrite_tag'], true)) {
                $wpsr_errors[] = 'rewrite listener requires inventory parser classification at ' . $wpsr_context['relative'] . ':' . $wpsr_call['line'];
                continue;
            }
            $wpsr_is_action = in_array($wpsr_call['name'], ['add_action', 'addAction'], true);
            $wpsr_is_filter = in_array($wpsr_call['name'], ['add_filter', 'addFilter'], true);
            $wpsr_is_shortcode = $wpsr_call['name'] === 'add_shortcode';
            if (!$wpsr_is_action && !$wpsr_is_filter && !$wpsr_is_shortcode) {
                continue;
            }
            if (count($wpsr_call['args']) < 2) {
                $wpsr_errors[] = 'registration missing hook/tag or callback at ' . $wpsr_context['relative'] . ':' . $wpsr_call['line'];
                continue;
            }
            $wpsr_name = wpsr_endpoint_literal($wpsr_call['args'][0], $wpsr_context, $wpsr_class_map, $wpsr_call['offset']);
            if ($wpsr_name === null) {
                $wpsr_expression = trim($wpsr_call['args'][0]);
                $wpsr_proven_internal_prefix = preg_match('/^[\'\"](wpsocialreviews\\/|fluentform\\/)/', $wpsr_expression);
                $wpsr_known_dynamic = $wpsr_plugin === 'pro'
                    && $wpsr_context['relative'] === 'app/Services/TemplateCssHandler.php'
                    && $wpsr_expression === '$action';
                if (!$wpsr_known_dynamic && !$wpsr_proven_internal_prefix) {
                    $wpsr_errors[] = 'parser ambiguity: unresolved registration name ' . preg_replace('/\s+/', ' ', $wpsr_expression)
                        . ' at ' . $wpsr_context['relative'] . ':' . $wpsr_call['line'];
                }
                continue;
            }
            $wpsr_surface = '';
            $wpsr_registration = '';
            $wpsr_public = false;
            $wpsr_locator = [];
            if ($wpsr_is_action && strpos($wpsr_name, 'wp_ajax_') === 0) {
                $wpsr_surface = 'ajax';
                $wpsr_registration = strpos($wpsr_name, 'wp_ajax_nopriv_') === 0 ? 'wp_ajax_nopriv' : 'wp_ajax';
                $wpsr_public = $wpsr_registration === 'wp_ajax_nopriv';
                $wpsr_locator = ['hook' => $wpsr_name, 'action' => preg_replace('/^wp_ajax_(?:nopriv_)?/', '', $wpsr_name)];
            } elseif ($wpsr_is_shortcode) {
                $wpsr_surface = 'shortcode';
                $wpsr_registration = 'add_shortcode';
                $wpsr_public = true;
                $wpsr_locator = ['tag' => $wpsr_name];
            } elseif (($wpsr_is_action && $wpsr_name === 'template_redirect') || ($wpsr_is_filter && $wpsr_name === 'query_vars')) {
                $wpsr_surface = 'listener';
                $wpsr_registration = $wpsr_name;
                $wpsr_public = true;
                $wpsr_locator = ['hook' => $wpsr_name];
            } else {
                continue;
            }
            $wpsr_callback_error = '';
            $wpsr_callback = wpsr_endpoint_callback($wpsr_call['args'][1], $wpsr_context, $wpsr_class_map, $wpsr_call['offset'], $wpsr_callback_error);
            if (!$wpsr_callback) {
                $wpsr_errors[] = $wpsr_callback_error . ' at ' . $wpsr_context['relative'] . ':' . $wpsr_call['line'];
                continue;
            }
            if ($wpsr_surface === 'ajax') {
                $wpsr_id = $wpsr_plugin . ':ajax:' . $wpsr_name;
            } elseif ($wpsr_surface === 'shortcode') {
                $wpsr_id = $wpsr_plugin . ':shortcode:' . $wpsr_name;
            } else {
                $wpsr_id = $wpsr_plugin . ':listener:' . $wpsr_name . ':' . $wpsr_callback;
            }
            $wpsr_rows[] = array_merge([
                'id' => $wpsr_id,
                'plugin' => $wpsr_plugin,
                'surface' => $wpsr_surface,
                'registration' => $wpsr_registration,
                'callback' => $wpsr_callback,
                'policy' => '',
                'public' => $wpsr_public,
                'evidence' => ['file' => $wpsr_context['relative'], 'line' => $wpsr_call['line']],
            ], $wpsr_locator);
        }
    }

    if ($wpsr_raw_body) {
        $wpsr_errors[] = 'direct raw-body listener candidate(s) require classification: ' . implode(', ', array_unique($wpsr_raw_body));
    }
    foreach ($wpsr_rows as &$wpsr_row) {
        $wpsr_contract_error = '';
        $wpsr_metadata = wpsr_endpoint_security_metadata($wpsr_row, $wpsr_class_map, $wpsr_contexts, $wpsr_contract_error);
        if (!$wpsr_metadata) {
            $wpsr_errors[] = $wpsr_contract_error . ' for ' . $wpsr_row['id'];
            continue;
        }
        $wpsr_row['security_metadata'] = $wpsr_metadata;
    }
    unset($wpsr_row);
    $wpsr_seen = [];
    foreach ($wpsr_rows as $wpsr_row) {
        if (isset($wpsr_seen[$wpsr_row['id']])) {
            $wpsr_errors[] = 'duplicate source registration identity ' . $wpsr_row['id'];
        }
        $wpsr_seen[$wpsr_row['id']] = true;
    }
    return [
        'rows' => $wpsr_rows,
        'errors' => array_values(array_unique($wpsr_errors)),
        'stats' => ['files' => count($wpsr_files), 'calls' => $wpsr_call_count, 'classes' => count($wpsr_class_map)],
        'absence' => [
            'raw_body_direct' => count(array_unique($wpsr_raw_body)),
            'ipn_candidates' => count(array_unique($wpsr_ipn)),
            'webhook_candidates' => count(array_unique($wpsr_webhook)),
            'rewrite_registrations' => 0,
        ],
    ];
}

function wpsr_endpoint_discover_family($wpsr_core_root, $wpsr_pro_root)
{
    $wpsr_core = wpsr_endpoint_discover_plugin('core', $wpsr_core_root, $wpsr_core_root);
    $wpsr_pro = wpsr_endpoint_discover_plugin('pro', $wpsr_pro_root, $wpsr_core_root);
    $wpsr_rows = array_merge($wpsr_core['rows'], $wpsr_pro['rows']);
    usort($wpsr_rows, static function ($wpsr_left, $wpsr_right) {
        $wpsr_plugin_order = ['core' => 0, 'pro' => 1];
        $wpsr_surface_order = ['rest' => 0, 'ajax' => 1, 'shortcode' => 2, 'listener' => 3];
        $wpsr_left_key = sprintf('%d:%d:%s', $wpsr_plugin_order[$wpsr_left['plugin']] ?? 9, $wpsr_surface_order[$wpsr_left['surface']] ?? 9, $wpsr_left['id']);
        $wpsr_right_key = sprintf('%d:%d:%s', $wpsr_plugin_order[$wpsr_right['plugin']] ?? 9, $wpsr_surface_order[$wpsr_right['surface']] ?? 9, $wpsr_right['id']);
        return strcmp($wpsr_left_key, $wpsr_right_key);
    });
    return [
        'rows' => $wpsr_rows,
        'errors' => array_merge($wpsr_core['errors'], $wpsr_pro['errors']),
        'plugins' => ['core' => $wpsr_core, 'pro' => $wpsr_pro],
    ];
}
