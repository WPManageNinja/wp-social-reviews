<?php
/** S0 lint: raw SQL must not contain unprefixed table-qualified identifiers.
 * Usage: php tests/lint/raw-sql-prefix.php [SCAN_PATH...]; exit 0 clean, 1 violations.
 */

// Resolve the plugin root from the current working directory when this script
// lives outside the plugin (e.g. run from a shared skill/assets copy), and from
// the script's own location when it has been installed into tests/lint/.
$root = is_dir(__DIR__ . '/../../app') ? dirname(__DIR__, 2) : getcwd();

// Default scan targets. An explicit path argument overrides them, which is what
// the self-test uses to prove this rule actually fires:
//   php tests/lint/raw-sql-prefix.php tests/lint/fixtures   ->  must exit 1
$scanDirs = ['app', 'boot', 'database'];
if (isset($argv[1]) && $argv[1] !== '') {
    $scanDirs = array_map(function ($dir) { return rtrim($dir, '/'); }, array_slice($argv, 1));
}

$rawCallPattern = '/\b(?:selectRaw|whereRaw|havingRaw|orderByRaw|groupByRaw|orWhereRaw|raw)\s*\(/i';

// A table-qualified WP Social Ninja identifier: wpsr_something.
$qualifiedPattern = '/\bwpsr_[a-z0-9_]+\s*\./i';

// Evidence that the prefix was resolved: an interpolated variable or a call.
$prefixEvidence = '/\$wpdb->prefix|getTablePrefix\s*\(|\{\$[a-zA-Z_][a-zA-Z0-9_]*\}|\$[a-zA-Z_][a-zA-Z0-9_]*\s*\./';

$violations = [];
$scanned = 0;

$iterate = function ($dir) use (&$iterate) {
    $out = [];
    if (!is_dir($dir)) {
        return $out;
    }
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $path = $file->getPathname();
            // Never lint vendored or scoped-vendor trees.
            if (strpos($path, '/vendor/') !== false || strpos($path, 'scoped-vendor') !== false) {
                continue;
            }
            $out[] = $path;
        }
    }
    return $out;
};

foreach ($scanDirs as $dir) {
    // Accept absolute paths (self-test from a shared copy) as well as paths
    // relative to the plugin root.
    $target = ($dir !== '' && $dir[0] === '/') ? $dir : $root . '/' . $dir;
    foreach ($iterate($target) as $path) {
        $scanned++;
        $source = file_get_contents($path);
        if ($source === false) {
            $violations[] = ['file' => str_replace($root . '/', '', $path), 'line' => 1, 'ident' => '(unreadable)', 'code' => 'Could not read PHP source.'];
            continue;
        }
        if (!preg_match_all($rawCallPattern, $source, $rawCalls, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($rawCalls[0] as $rawCall) {
            $openParen = strpos($source, '(', $rawCall[1]);
            if ($openParen === false) continue;
            $depth = 0;
            $quote = '';
            $closeParen = null;
            for ($p = $openParen, $len = strlen($source); $p < $len; $p++) {
                $char = $source[$p];
                if ($quote !== '') {
                    if ($char === '\\') { $p++; continue; }
                    if ($char === $quote) $quote = '';
                    continue;
                }
                if ($char === "'" || $char === '"') { $quote = $char; continue; }
                if ($char === '(') $depth++;
                elseif ($char === ')' && --$depth === 0) { $closeParen = $p; break; }
            }
            if ($closeParen === null) {
                $violations[] = [
                    'file' => str_replace($root . '/', '', $path),
                    'line' => 1 + substr_count(substr($source, 0, $rawCall[1]), "\n"),
                    'ident' => '(parse-error)',
                    'code' => 'Unbalanced raw SQL call.',
                ];
                continue;
            }
            $rawSpan = substr($source, $openParen, $closeParen - $openParen + 1);
            if (!preg_match_all(
                '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/s',
                $rawSpan,
                $matches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            )) continue;

            foreach ($matches as $match) {
                $doubleQuoted = isset($match[1]) ? $match[1][0] : '';
                $singleQuoted = isset($match[2]) ? $match[2][0] : '';
                $literal = $singleQuoted !== '' ? $singleQuoted : $doubleQuoted;
                $literalOffset = $match[0][1];
                if (!preg_match($qualifiedPattern, $literal, $hit)) continue;
                if (preg_match($prefixEvidence, $literal)) continue;
                $before = substr($rawSpan, 0, $literalOffset);
                if (preg_match('/(?:\$wpdb->prefix|getTablePrefix\s*\(\s*\)|\$[a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*$/', $before)) continue;

                $violations[] = [
                    'file' => str_replace($root . '/', '', $path),
                    'line' => 1 + substr_count(substr($source, 0, $openParen + $literalOffset), "\n"),
                    'ident' => trim($hit[0]),
                    'code' => trim(preg_replace('/\s+/', ' ', $rawSpan)),
                ];
            }
        }
    }
}

echo "raw-sql-prefix: scanned {$scanned} PHP files\n";
if ($scanned === 0) {
    fwrite(STDERR, "FAIL — scan resolved zero PHP files.\n");
    exit(1);
}

if (!$violations) {
    echo "OK — no unprefixed table-qualified identifiers inside raw SQL.\n";
    exit(0);
}

echo "\nFAIL — " . count($violations) . " violation(s):\n\n";
foreach ($violations as $v) {
    echo "  {$v['file']}:{$v['line']}\n";
    echo "    unprefixed identifier: {$v['ident']}\n";
    echo "    " . (strlen($v['code']) > 140 ? substr($v['code'], 0, 137) . '...' : $v['code']) . "\n\n";
}
echo "Raw SQL bypasses the query grammar. Resolve the prefix explicitly:\n";
echo "  global \$wpdb; \$t = \$wpdb->prefix . 'wpsr_subscribers';\n";
echo "  ->selectRaw(\"... `{\$t}`.`status` ...\")\n";
exit(1);
