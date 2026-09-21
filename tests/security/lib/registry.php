<?php
/** Phase 22 registry loading, validation, selector parsing, and deterministic ordering. */

const WPSR_SECURITY_PASS = 0;
const WPSR_SECURITY_FAIL = 1;
const WPSR_SECURITY_MISUSE = 2;
const WPSR_SECURITY_RUNNERS = [
    'php',
    'raw-sql-fixture',
    'wp-cli',
    'security-doctor',
    'security-self-test',
    'fuzz-self-test',
    'release-secrets-self-test',
    'semgrep-fixtures',
    'fuzz',
    'release-secrets',
    'semgrep-security',
    'semgrep-quality',
    'mutation-self-test',
    'mutation-audit',
    'dast-zap',
    'dast-nuclei',
];

function wpsr_security_root()
{
    return dirname(__DIR__, 3);
}

function wpsr_security_load_json($path, $label)
{
    if (!is_file($path)) {
        throw new RuntimeException($label . ' missing: ' . $path);
    }
    $value = json_decode(file_get_contents($path), true);
    if (!is_array($value) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException($label . ' malformed: ' . json_last_error_msg());
    }
    return $value;
}

function wpsr_security_family()
{
    return [
        'schema' => 1,
        'plugins' => [[
            'id' => 'core',
            'role' => 'host',
            'runtime_only' => false,
        ]],
    ];
}

function wpsr_security_plugins(array $family)
{
    $plugins = [];
    foreach ((array) ($family['plugins'] ?? []) as $plugin) {
        if (!is_array($plugin) || empty($plugin['id'])) {
            continue;
        }
        $plugins[$plugin['id']] = $plugin;
    }
    return $plugins;
}

function wpsr_security_is_normalized_token($value)
{
    return is_string($value) && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
}

function wpsr_security_runtime_only(array $plugin)
{
    if (isset($plugin['runtime']) && is_array($plugin['runtime'])) {
        return empty($plugin['runtime']['selectable'])
            || !in_array(($plugin['test_tree']['status'] ?? ''), ['hosted', 'linked'], true);
    }
    return !empty($plugin['runtime_only']) || array_key_exists('phase_1', $plugin) && !$plugin['phase_1'];
}

function wpsr_security_discovered_targets($root)
{
    $paths = [];
    foreach (array_merge(
        glob($root . '/tests/security/detect_*.php') ?: [],
        glob($root . '/tests/security/targets/*.php') ?: [],
        glob($root . '/tests/lint/detect_*.php') ?: [],
        glob($root . '/tests/lint/*sql*.php') ?: []
    ) as $path) {
        if (is_file($path)) {
            $paths[] = substr($path, strlen($root) + 1);
        }
    }

    foreach ([
        'tests/smoke/route-coverage.php',
        'tests/permissions/route-coverage.php',
        'tests/validation/route-coverage.php',
        'tests/public/handler-coverage.php',
        'tests/bin/run-permissions.php',
        'tests/integration/input-validation-authorization.php',
        'tests/public/public-handlers.php',
        'tests/lint/fixtures/bad-raw-prefix.php',
        'tests/mutation/manifest.php',
        'tests/mutation/self-test.php',
        'tests/security/registry-self-test.php',
        'tests/bin/run-source-contract-fixtures.php',
        'tests/security/lab/doctor.php',
        'tests/security/lab/self-test.php',
        'tests/security/dast/self-test.php',
        'tests/security/endpoints/lint.php',
        'tests/security/endpoints/proof-of-catch.php',
        'tests/bin/run-zap-baseline.sh',
        'tests/bin/run-nuclei-baseline.sh',
        'tests/bin/run-fuzz.sh',
        'tests/bin/run-release-secrets.sh',
        'tests/bin/run-semgrep.sh',
    ] as $relative) {
        if (is_file($root . '/' . $relative)) {
            $paths[] = $relative;
        }
    }

    $paths = array_values(array_unique($paths));
    sort($paths, SORT_STRING);
    return $paths;
}

function wpsr_security_validate(array $registry, array $family, $root, $discover = true)
{
    $errors = [];
    if (($registry['schema'] ?? null) !== 1 || !isset($registry['targets']) || !is_array($registry['targets'])) {
        return ['registry schema must be 1 with a targets array'];
    }
    if (!$registry['targets']) {
        $errors[] = 'registry declares zero targets';
    }

    $plugins = wpsr_security_plugins($family);
    $ids = [];
    $paths = [];
    foreach ($registry['targets'] as $index => $target) {
        $label = is_array($target) && isset($target['id']) ? $target['id'] : 'targets[' . $index . ']';
        if (!is_array($target)) {
            $errors[] = $label . ': target must be an object';
            continue;
        }
        foreach (['id', 'plugins', 'modules', 'categories', 'runner', 'path', 'count_pattern', 'claim'] as $field) {
            if (!array_key_exists($field, $target)) {
                $errors[] = $label . ': missing ' . $field;
            }
        }
        $id = $target['id'] ?? '';
        if (!wpsr_security_is_normalized_token($id)) {
            $errors[] = $label . ': id must be unique kebab-case';
        } elseif (isset($ids[$id])) {
            $errors[] = $label . ': duplicate id';
        } else {
            $ids[$id] = true;
        }

        foreach (['plugins', 'modules', 'categories'] as $dimension) {
            $values = $target[$dimension] ?? null;
            if (!is_array($values) || !$values) {
                $errors[] = $label . ': ' . $dimension . ' must be a nonempty array';
                continue;
            }
            if (count($values) !== count(array_unique($values, SORT_STRING))) {
                $errors[] = $label . ': duplicate ' . $dimension . ' tag';
            }
            $sorted = $values;
            sort($sorted, SORT_STRING);
            if ($sorted !== $values) {
                $errors[] = $label . ': ' . $dimension . ' tags must be sorted';
            }
            foreach ($values as $value) {
                if (!wpsr_security_is_normalized_token($value)) {
                    $errors[] = $label . ': bad normalized ' . $dimension . ' tag ' . var_export($value, true);
                }
            }
        }

        foreach ((array) ($target['plugins'] ?? []) as $pluginId) {
            if (!isset($plugins[$pluginId])) {
                $errors[] = $label . ': bad plugin not found in PR #324 security family: ' . $pluginId;
            } elseif (wpsr_security_runtime_only($plugins[$pluginId])) {
                $errors[] = $label . ': runtime-only plugin cannot own selectable security targets: ' . $pluginId;
            }
        }

        if (!in_array($target['runner'] ?? '', WPSR_SECURITY_RUNNERS, true)) {
            $errors[] = $label . ': runner does not exist: ' . ($target['runner'] ?? '');
        }
        $path = $target['path'] ?? '';
        if (!is_string($path) || $path === '' || $path[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $path)) {
            $errors[] = $label . ': path must stay relative to repository root';
        } elseif (!is_file($root . '/' . $path)) {
            $errors[] = $label . ': phantom registry entry: ' . $path;
        } else {
            $paths[$path] = true;
        }
        if (!is_string($target['count_pattern'] ?? null) || @preg_match($target['count_pattern'], '') === false) {
            $errors[] = $label . ': count_pattern must be valid PCRE';
        }
        if (($target['count_mode'] ?? 'capture') === 'capture' && (int) ($target['count_group'] ?? 0) < 1) {
            $errors[] = $label . ': count_group must identify nonzero summary capture';
        }
        if (!in_array($target['count_mode'] ?? 'capture', ['capture', 'matches'], true)) {
            $errors[] = $label . ': unsupported count_mode';
        }
        if (!is_string($target['claim'] ?? null) || strlen(trim($target['claim'])) < 40) {
            $errors[] = $label . ': tags require substantive claim text';
        }
        if (($target['runner'] ?? '') === 'wp-cli') {
            if (!in_array($target['suite'] ?? '', ['permissions', 'integration', 'public'], true)) {
                $errors[] = $label . ': wp-cli suite runner does not exist';
            }
            if (in_array($target['suite'] ?? '', ['integration', 'public'], true) && empty($target['filter'])) {
                $errors[] = $label . ': focused wp-cli wrapper needs exact filter';
            }
        }
    }

    if ($discover) {
        $discovered = wpsr_security_discovered_targets($root);
        foreach ($discovered as $path) {
            if (!isset($paths[$path])) {
                $errors[] = 'orphan security target file: ' . $path;
            }
        }
        foreach (array_keys($paths) as $path) {
            if (!in_array($path, $discovered, true)) {
                $errors[] = 'phantom/non-discoverable security target registration: ' . $path;
            }
        }
    }

    return array_values(array_unique($errors));
}

function wpsr_security_parse_arguments(array $args, array $extraFlags = [])
{
    $selectors = ['plugin' => [], 'module' => [], 'category' => []];
    $flags = [];
    for ($index = 0; $index < count($args); $index++) {
        $argument = $args[$index];
        if (preg_match('/^--(plugin|module|category)(?:=(.*))?$/', $argument, $match)) {
            $raw = isset($match[2]) && $match[2] !== '' ? $match[2] : ($args[++$index] ?? '');
            if ($raw === '') {
                return [null, null, '--' . $match[1] . ' requires a value'];
            }
            foreach (explode(',', $raw) as $value) {
                $value = trim($value);
                if (!wpsr_security_is_normalized_token($value)) {
                    return [null, null, 'invalid normalized selector value: ' . $value];
                }
                $selectors[$match[1]][] = $value;
            }
            continue;
        }
        if ($argument === '--list') {
            $flags['list'] = true;
            continue;
        }
        if ($argument === '-h' || $argument === '--help') {
            $flags['help'] = true;
            continue;
        }
        if (preg_match('/^--([a-z-]+)=(.*)$/', $argument, $match) && in_array($match[1], $extraFlags, true)) {
            if ($match[2] === '') {
                return [null, null, '--' . $match[1] . ' requires a value'];
            }
            $flags[$match[1]] = $match[2];
            continue;
        }
        return [null, null, 'unknown option: ' . $argument];
    }
    foreach ($selectors as $dimension => $values) {
        $selectors[$dimension] = array_values(array_unique($values));
    }
    return [$selectors, $flags, null];
}

function wpsr_security_select(array $targets, array $selectors, array $family)
{
    $plugins = wpsr_security_plugins($family);
    $known = ['module' => [], 'category' => []];
    foreach ($targets as $target) {
        foreach ($target['modules'] as $value) {
            $known['module'][$value] = true;
        }
        foreach ($target['categories'] as $value) {
            $known['category'][$value] = true;
        }
    }
    foreach ($selectors['plugin'] as $value) {
        if (!isset($plugins[$value])) {
            return [[], 'unknown plugin selector: ' . $value];
        }
        if (wpsr_security_runtime_only($plugins[$value])) {
            return [[], 'runtime-only plugin is not selectable until its test tree is hosted: ' . $value];
        }
        $available = array_filter($targets, function ($target) use ($value) {
            return in_array($value, $target['plugins'], true);
        });
        if (!$available) {
            return [[], 'plugin has no selectable security targets: ' . $value];
        }
    }
    foreach (['module', 'category'] as $dimension) {
        foreach ($selectors[$dimension] as $value) {
            if (!isset($known[$dimension][$value])) {
                return [[], 'unknown ' . $dimension . ' selector: ' . $value];
            }
        }
    }

    $selected = array_values(array_filter($targets, function ($target) use ($selectors) {
        foreach (['plugin' => 'plugins', 'module' => 'modules', 'category' => 'categories'] as $selector => $field) {
            if ($selectors[$selector] && !array_intersect($selectors[$selector], $target[$field])) {
                return false;
            }
        }
        return true;
    }));
    if (!$selected) {
        return [[], 'selector matched zero targets: empty intersection'];
    }

    $order = array_flip(WPSR_SECURITY_RUNNERS);
    usort($selected, function ($left, $right) use ($order) {
        $runner = $order[$left['runner']] <=> $order[$right['runner']];
        return $runner !== 0 ? $runner : strcmp($left['id'], $right['id']);
    });
    return [$selected, null];
}

function wpsr_security_format_target(array $target)
{
    return sprintf(
        "%s\trunner=%s\tplugins=%s\tmodules=%s\tcategories=%s\tpath=%s",
        $target['id'],
        $target['runner'],
        implode(',', $target['plugins']),
        implode(',', $target['modules']),
        implode(',', $target['categories']),
        $target['path']
    );
}
