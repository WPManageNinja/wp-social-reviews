<?php
/** Foundation infrastructure contracts independent from runtime discovery. */
$root = dirname(__DIR__, 2);
$errors = [];

$runtimeManifest = require $root . '/tests/runtime-files.manifest.php';
foreach (['integration', 'pure', 'public'] as $tier) {
    $expected = isset($runtimeManifest[$tier]) && is_array($runtimeManifest[$tier])
        ? array_values($runtimeManifest[$tier])
        : [];
    $support = isset($runtimeManifest[$tier . '_support']) && is_array($runtimeManifest[$tier . '_support'])
        ? array_values($runtimeManifest[$tier . '_support'])
        : [];
    $declared = array_merge($expected, $support);
    foreach ($declared as $name) {
        if (!is_string($name) || $name === '' || basename($name) !== $name || substr($name, -4) !== '.php') {
            $errors[] = $tier . ' runtime manifest contains unsafe entry: ' . (string) $name;
        }
    }
    if (count($declared) !== count(array_unique($declared))) {
        $errors[] = $tier . ' runtime manifest contains duplicate entries';
    }
    sort($declared, SORT_STRING);
    $directory = $root . '/tests/' . $tier;
    $actual = [];
    $tierIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($tierIterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $actual[] = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
        }
    }
    sort($actual, SORT_STRING);
    if (!$expected || $actual !== $declared) {
        $errors[] = $tier . ' runtime census drift: expected=' . json_encode($declared) . ' actual=' . json_encode($actual);
    }
}

$required = [
    'tests/bin/run-all.sh',
    'tests/bin/run-smoke.php',
    'tests/bin/run-permissions.php',
    'tests/bin/run-pure.php',
    'tests/bin/run-integration.php',
    'tests/bin/run-public.php',
    'tests/lib/harness.php',
    'tests/lib/factory.php',
    'tests/lib/runner.php',
    'tests/lib/validate-suite-output.php',
    'tests/lab/scripts/verify-compose.php',
    'tests/smoke/route-coverage.php',
    'tests/permissions/route-coverage.php',
    'tests/round-trips/route-coverage.php',
    'tests/validation/route-coverage.php',
    'tests/public/handler-coverage.php',
    'tests/skips/lint.php',
    'tests/skips/skip-lib.php',
    'tests/family/census.php',
    'tests/family/test-trees.php',
    'tests/family/sweep.php',
    'tests/compatibility/lint.php',
    'tests/bin/run-addon.php',
    'tests/bin/run-family-canary.php',
    'tests/bin/run-compatibility-matrix.sh',
    'tests/bin/run-host-static.php',
    'tests/compatibility/runtime-result-lib.php',
    'tests/compatibility/validate-runtime-result.php',
    'tests/compatibility/validate-summary.php',
    'tests/skips/proof-of-catch.php',
];
foreach ($required as $path) {
    if (!is_file($root . '/' . $path)) {
        $errors[] = 'required foundation file missing: ' . $path;
    }
}
$documentationPaths = [];
$documentationIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/tests', FilesystemIterator::SKIP_DOTS)
);
foreach ($documentationIterator as $documentationFile) {
    $documentationFilePath = str_replace('\\', '/', $documentationFile->getPathname());
    if ($documentationFile->isFile() && strtolower($documentationFile->getExtension()) === 'md'
        && strpos($documentationFilePath, '/node_modules/') === false
        && strpos($documentationFilePath, '/.artifacts/') === false
        && strpos($documentationFilePath, '/artifacts/') === false
        && strpos($documentationFilePath, '/.build/') === false
        && strpos($documentationFilePath, '/__pycache__/') === false) {
        $documentationPaths[] = $documentationFile->getPathname();
    }
}
sort($documentationPaths, SORT_STRING);
if (!$documentationPaths) {
    $errors[] = 'test-suite documentation missing or unreadable';
}
$supportedSuites = ['all', 'static', 'php', 'family', 'canary', 'addon', 'pro', 'compatibility', 'pure', 'smoke', 'permissions', 'integration', 'js', 'browser', 'e2e', 'throughput', 'public', 'coverage', 'mutation', 'security', 'dast', 'fuzz', 'secrets', 'semgrep', 'audit'];
foreach ($documentationPaths as $documentationPath) {
    $documentationSource = file_get_contents($documentationPath);
    $documentationName = ltrim(str_replace($root . '/tests', '', $documentationPath), '/');
    if (!is_string($documentationSource)) {
        $errors[] = $documentationName . ': documentation unreadable';
        continue;
    }
    foreach (['--env family', 'run-all.sh environment', 'run-environment-axis.sh'] as $forbiddenDocSelector) {
        if (strpos($documentationSource, $forbiddenDocSelector) !== false) {
            $errors[] = $documentationName . ': unsupported selector/command: ' . $forbiddenDocSelector;
        }
    }
    if (preg_match_all('/bash tests\/bin\/run-all\.sh(?:[ \t]+([^\s#]+))?/', $documentationSource, $documentedCommands, PREG_SET_ORDER)) {
        foreach ($documentedCommands as $documentedCommand) {
            $documentedSuite = trim($documentedCommand[1] ?? '', "`.,;:()[]{}\"");
            if ($documentedSuite !== '' && strpos($documentedSuite, '--') !== 0
                && !in_array($documentedSuite, $supportedSuites, true)) {
                $errors[] = $documentationName . ': run-all selector is unsupported: ' . $documentedSuite;
            }
        }
    }
    if (preg_match_all('/\btests\/bin\/[A-Za-z0-9._\/-]+/', $documentationSource, $documentedPaths)) {
        foreach (array_unique($documentedPaths[0]) as $documentedPath) {
            if (!is_file($root . '/' . $documentedPath)) {
                $errors[] = $documentationName . ': command path does not exist: ' . $documentedPath;
            }
        }
    }
}

$routeFile = $root . '/app/Http/Routes/api.php';
$source = is_file($routeFile) ? file_get_contents($routeFile) : false;
if ($source === false) {
    $errors[] = 'production route source missing or unreadable';
} else {
    $tokens = token_get_all($source);
    $methods = ['GET' => 0, 'POST' => 0, 'PUT' => 0, 'DELETE' => 0, 'PATCH' => 0];
    $allowedRouterMethods = ['get', 'post', 'put', 'delete', 'patch', 'prefix', 'withPolicy', 'group'];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_VARIABLE || $tokens[$i][1] !== '$router') {
            continue;
        }
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }
        if ($j >= $count || (!is_array($tokens[$j]) || $tokens[$j][0] !== T_OBJECT_OPERATOR)) {
            continue;
        }
        $j++;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if ($j >= $count || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
            $errors[] = 'route source has an unparseable direct $router call';
            continue;
        }
        $method = $tokens[$j][1];
        if (!in_array($method, $allowedRouterMethods, true)) {
            $errors[] = 'route source uses collector-unsupported direct router method: ' . $method;
            continue;
        }
        $upper = strtoupper($method);
        if (isset($methods[$upper])) {
            $methods[$upper]++;
        }
    }

    // A route-closure declaration contributes once to lexical counts but can be
    // mounted repeatedly. Account for every named closure mount independently.
    $cleanSource = '';
    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $cleanSource .= is_array($token) ? $token[1] : $token;
    }
    if (preg_match_all('/(\$wpsr_[A-Za-z0-9_]+)\s*=\s*function\s*\([^)]*\)[^{]*\{(.*?)\n\};/s', $cleanSource, $closures, PREG_SET_ORDER)) {
        foreach ($closures as $closure) {
            $variable = preg_quote($closure[1], '/');
            preg_match_all('/->group\s*\(\s*' . $variable . '(?:\s*\([^)]*\))?\s*\)/', $cleanSource, $mounts);
            $mountCount = count($mounts[0]);
            if ($mountCount < 1) {
                continue;
            }
            foreach (['get', 'post', 'put', 'delete', 'patch'] as $method) {
                preg_match_all('/\$router\s*->\s*' . $method . '\s*\(/', $closure[2], $declarations);
                $methods[strtoupper($method)] += count($declarations[0]) * ($mountCount - 1);
            }
        }
    }

    $smoke = require $root . '/tests/smoke/routes.manifest.php';
    $permission = require $root . '/tests/permissions/routes.manifest.php';
    $manifestCounts = ['GET' => 0, 'POST' => 0, 'PUT' => 0, 'DELETE' => 0, 'PATCH' => 0];
    $getDeclarations = [];
    foreach ((array) $smoke as $row) {
        if (!empty($row['declaration'])) {
            $getDeclarations[(string) $row['declaration']] = true;
        }
    }
    $manifestCounts['GET'] = count($getDeclarations);
    foreach ((array) $permission as $row) {
        $method = strtoupper((string) ($row['method'] ?? ''));
        if (isset($manifestCounts[$method])) {
            $manifestCounts[$method]++;
        }
    }
    if ($methods !== $manifestCounts) {
        $errors[] = 'token/source route census differs from independent manifests: source=' . json_encode($methods)
            . ' manifests=' . json_encode($manifestCounts);
    }
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests', FilesystemIterator::SKIP_DOTS));
$phpFiles = [];
$trackedLikeFiles = 0;
foreach ($iterator as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    if (!$file->isFile() || strpos($path, '/.artifacts/') !== false || strpos($path, '/artifacts/') !== false
        || strpos($path, '/node_modules/') !== false || strpos($path, '/.build/') !== false
        || strpos($path, '/__pycache__/') !== false) {
        continue;
    }
    $relative = str_replace($root . '/', '', $path);
    $trackedLikeFiles++;
    if (strtolower($file->getExtension()) === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}
if (!$phpFiles) {
    $errors[] = 'PHP infrastructure discovery found zero files';
}
foreach ($phpFiles as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    exec($command, $output, $status);
    if ($status !== 0) {
        $errors[] = 'PHP syntax failure in ' . str_replace($root . '/', '', $file) . ': ' . implode(' ', $output);
    }
    $output = [];
}

$validator = $root . '/tests/lib/validate-suite-output.php';
$temp = tempnam(sys_get_temp_dir(), 'wpsr-output-contract-');
if ($temp === false) {
    $errors[] = 'could not create summary-contract fixture';
} else {
    $summary = 'WPSR-SUITE-JSON {"suite":"SELF","pass":1,"fail":0,"skip":0,"total":1,"assertion_failures":0,"runtime":0.01,"skip_ids":[]}' . "\n";
    $valid = $summary . "WPSR-SUITE-END SELF PASS\n";
    $mutants = [
        'zero total' => str_replace('"pass":1', '"pass":0', str_replace('"total":1', '"total":0', $valid)),
        'malformed JSON' => "WPSR-SUITE-JSON {broken}\nWPSR-SUITE-END SELF PASS\n",
        'child-reported failure' => str_replace('"fail":0', '"fail":1', $valid),
        'missing terminal' => $summary,
        'stale trailing output' => $valid . "stale\n",
    ];
    file_put_contents($temp, $valid);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator) . ' ' . escapeshellarg($temp) . ' >/dev/null 2>&1', $unused, $status);
    if ($status !== 0) {
        $errors[] = 'suite-output validator rejects valid contract fixture';
    }
    foreach ($mutants as $name => $fixture) {
        file_put_contents($temp, $fixture);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator) . ' ' . escapeshellarg($temp) . ' >/dev/null 2>&1', $unused, $status);
        if ($status === 0) {
            $errors[] = 'suite-output validator accepted mutant: ' . $name;
        }
    }
    unlink($temp);
}

$runtimeCount = count($runtimeManifest['integration']) + count($runtimeManifest['pure']) + count($runtimeManifest['public']);
printf("infrastructure-lint: %d runtime files, %d PHP files, independent route census checked.\n",
    $runtimeCount, count($phpFiles));
if ($errors) {
    fwrite(STDERR, "infrastructure-lint: FAIL\n  - " . implode("\n  - ", array_unique($errors)) . "\n");
    exit(1);
}
echo "OK — runtime discovery, syntax, summary parser, scope, and independent route census fail closed.\n";
