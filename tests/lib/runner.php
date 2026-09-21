<?php
/** Shared fail-closed runtime discovery and filter parsing. */
class WpsrRunner
{
    public static function files($tier, $directory, array $arguments)
    {
        $manifestFile = dirname(__DIR__) . '/runtime-files.manifest.php';
        $manifest = is_file($manifestFile) ? require $manifestFile : null;
        if (!is_array($manifest) || empty($manifest[$tier]) || !is_array($manifest[$tier])) {
            WP_CLI::error('Runtime file manifest has zero or malformed ' . $tier . ' rows.');
        }

        $expected = array_values($manifest[$tier]);
        $supportKey = $tier . '_support';
        $support = isset($manifest[$supportKey]) && is_array($manifest[$supportKey])
            ? array_values($manifest[$supportKey])
            : [];
        foreach (array_merge($expected, $support) as $name) {
            if (!is_string($name) || $name === '' || basename($name) !== $name || substr($name, -4) !== '.php') {
                WP_CLI::error('Runtime file manifest has unsafe ' . $tier . ' entry: ' . (string) $name);
            }
        }
        $declared = array_merge($expected, $support);
        if (count($declared) !== count(array_unique($declared))) {
            WP_CLI::error('Runtime file manifest has duplicate ' . $tier . ' entries.');
        }
        sort($expected, SORT_STRING);
        sort($declared, SORT_STRING);

        $directory = rtrim($directory, '/');
        $allPaths = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $allPaths[] = $file->getPathname();
            }
        }
        sort($allPaths, SORT_STRING);
        $actual = array_map(function ($path) use ($directory) {
            return str_replace('\\', '/', substr($path, strlen($directory) + 1));
        }, $allPaths);
        sort($actual, SORT_STRING);
        if ($actual !== $declared) {
            WP_CLI::error(
                ucfirst($tier) . ' file drift. Expected ' . wp_json_encode($declared)
                . '; discovered ' . wp_json_encode($actual) . '.'
            );
        }
        $paths = array_values(array_filter($allPaths, function ($path) use ($expected, $directory) {
            $relative = str_replace('\\', '/', substr($path, strlen($directory) + 1));
            return in_array($relative, $expected, true);
        }));
        if (!$paths) {
            WP_CLI::error(ucfirst($tier) . ' runtime discovery selected zero files after support-file exclusion.');
        }

        $filter = self::filter($arguments, ucfirst($tier));
        if ($filter === null) {
            return $paths;
        }

        $requested = array_map('trim', explode(',', $filter));
        if (!$requested || in_array('', $requested, true)) {
            WP_CLI::error(ucfirst($tier) . ' filter contains an empty file selector.');
        }
        $requested = array_map(function ($name) use ($tier) {
            if (basename($name) !== $name) {
                WP_CLI::error(ucfirst($tier) . ' filter contains an unsafe path selector: ' . $name);
            }
            return substr($name, -4) === '.php' ? $name : $name . '.php';
        }, $requested);
        if (count($requested) !== count(array_unique($requested))) {
            WP_CLI::error(ucfirst($tier) . ' filter must select unique test files.');
        }

        $unknown = array_values(array_diff($requested, $expected));
        if ($unknown) {
            WP_CLI::error(ucfirst($tier) . ' filter references unknown test files: ' . implode(',', $unknown));
        }

        $selected = array_values(array_filter($paths, function ($path) use ($requested) {
            return in_array(basename($path), $requested, true);
        }));
        if (!$selected) {
            WP_CLI::error(ucfirst($tier) . ' filter selected zero test files: ' . $filter);
        }

        return $selected;
    }

    public static function filter(array $arguments, $label)
    {
        $seen = false;
        $value = null;
        foreach ($arguments as $argument) {
            if (strpos($argument, '--filter=') === 0) {
                $candidate = substr($argument, 9);
            } elseif (strpos($argument, 'filter=') === 0) {
                $candidate = substr($argument, 7);
            } else {
                WP_CLI::error($label . ' runner received unsupported argument: ' . (string) $argument);
            }

            if ($seen) {
                WP_CLI::error($label . ' runner received duplicate filter arguments.');
            }
            $seen = true;
            $value = trim((string) $candidate);
        }

        if ($seen && $value === '') {
            WP_CLI::error($label . ' runner refuses an empty filter.');
        }

        return $seen ? $value : null;
    }
}
