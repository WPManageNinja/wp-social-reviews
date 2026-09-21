<?php
/** Phase 24 green-to-red fixture harness. Production source is read/hash-guarded, never mutated. */

class WpsrPhase24SafetyException extends RuntimeException
{
}

$wpsr_phase24_root = dirname(__DIR__, 2);
$wpsr_phase24_pro_root = dirname($wpsr_phase24_root) . '/wp-social-ninja-pro';
$wpsr_phase24_workspaces = [];

function wpsr_phase24_process(array $command, $cwd)
{
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException('could not start command: ' . implode(' ', $command));
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return [
        'exit' => proc_close($process),
        'stdout' => (string) $stdout,
        'stderr' => (string) $stderr,
        'output' => (string) $stdout . (string) $stderr,
    ];
}

function wpsr_phase24_git_status($repo)
{
    $result = wpsr_phase24_process(
        ['git', 'status', '--porcelain=v1', '-z', '--untracked-files=all'],
        $repo
    );
    if ($result['exit'] !== 0) {
        throw new WpsrPhase24SafetyException('could not capture git status for ' . $repo);
    }
    return $result['stdout'];
}

function wpsr_phase24_production_hashes($repo)
{
    $result = wpsr_phase24_process(['git', 'ls-files', '-z'], $repo);
    if ($result['exit'] !== 0) {
        throw new WpsrPhase24SafetyException('could not list tracked production paths for ' . $repo);
    }

    $hashes = [];
    foreach (explode("\0", $result['stdout']) as $relative) {
        if ($relative === '' || preg_match('#^(tests|dev|docs|\.github)/#', $relative)) {
            continue;
        }
        $path = $repo . '/' . $relative;
        if (!is_file($path)) {
            throw new WpsrPhase24SafetyException('tracked production path missing: ' . $path);
        }
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new WpsrPhase24SafetyException('could not hash production path: ' . $path);
        }
        $hashes[$relative] = $hash;
    }
    if (!$hashes) {
        throw new WpsrPhase24SafetyException('production path hash discovery returned zero files: ' . $repo);
    }
    ksort($hashes, SORT_STRING);
    return $hashes;
}

function wpsr_phase24_assert_safety(array $baseline, $label)
{
    foreach (['core', 'pro'] as $repoId) {
        $repo = $baseline[$repoId]['root'];
        $status = wpsr_phase24_git_status($repo);
        if (!hash_equals(hash('sha256', $baseline[$repoId]['status']), hash('sha256', $status))
            || $status !== $baseline[$repoId]['status']) {
            throw new WpsrPhase24SafetyException($label . ': exact ' . $repoId . ' git status drifted');
        }
        $hashes = wpsr_phase24_production_hashes($repo);
        if ($hashes !== $baseline[$repoId]['production']) {
            $paths = array_values(array_unique(array_merge(array_keys($hashes), array_keys($baseline[$repoId]['production']))));
            foreach ($paths as $path) {
                if (($hashes[$path] ?? null) !== ($baseline[$repoId]['production'][$path] ?? null)) {
                    throw new WpsrPhase24SafetyException($label . ': ' . $repoId . ' production hash drifted: ' . $path);
                }
            }
            throw new WpsrPhase24SafetyException($label . ': ' . $repoId . ' production hash map drifted');
        }
    }
}

function wpsr_phase24_remove_tree($path)
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        if (!unlink($path)) {
            throw new RuntimeException('could not remove fixture workspace file: ' . $path);
        }
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        throw new RuntimeException('could not read fixture workspace: ' . $path);
    }
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..') {
            wpsr_phase24_remove_tree($path . DIRECTORY_SEPARATOR . $item);
        }
    }
    if (!rmdir($path)) {
        throw new RuntimeException('could not remove fixture workspace directory: ' . $path);
    }
}

function wpsr_phase24_workspace($label)
{
    global $wpsr_phase24_workspaces;
    $path = sys_get_temp_dir() . '/wpsr-phase24-' . preg_replace('/[^a-z0-9-]/', '-', $label)
        . '-' . bin2hex(random_bytes(6));
    if (!mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('could not create fixture workspace: ' . $path);
    }
    $wpsr_phase24_workspaces[$path] = true;
    return $path;
}

function wpsr_phase24_release_workspace($path)
{
    global $wpsr_phase24_workspaces;
    wpsr_phase24_remove_tree($path);
    unset($wpsr_phase24_workspaces[$path]);
}

function wpsr_phase24_assert_no_residue($path)
{
    if (file_exists($path) || is_link($path)) {
        throw new RuntimeException('fixture residue remains: ' . $path);
    }
}

register_shutdown_function(function () {
    global $wpsr_phase24_workspaces;
    foreach (array_keys($wpsr_phase24_workspaces) as $path) {
        try {
            wpsr_phase24_remove_tree($path);
        } catch (Throwable $throwable) {
            fwrite(STDERR, 'SOURCE-CONTRACT-FIXTURE shutdown cleanup failed: ' . $throwable->getMessage() . "\n");
        }
    }
});

function wpsr_phase24_copy_tree($source, $destination)
{
    if (!is_dir($source)) {
        throw new RuntimeException('fixed fixture root missing: ' . $source);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $target = $destination . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) {
                throw new RuntimeException('could not copy fixture directory: ' . $target);
            }
        } else {
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true) && !is_dir(dirname($target))) {
                throw new RuntimeException('could not create fixture parent: ' . dirname($target));
            }
            if (!copy($item->getPathname(), $target)) {
                throw new RuntimeException('could not copy fixture file: ' . $relative);
            }
        }
    }
}

function wpsr_phase24_fixture_files($root)
{
    $files = [];
    if (!is_dir($root)) {
        return $files;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($root) + 1));
        }
    }
    sort($files, SORT_STRING);
    return $files;
}

function wpsr_phase24_detector($root, array $case, $sourceRoot)
{
    $detector = $root . '/tests/lint/detect_' . str_replace('-', '_', $case['id']) . '.php';
    if (!is_file($detector)) {
        throw new RuntimeException($case['id'] . ': detector missing: ' . $detector);
    }
    return wpsr_phase24_process(
        [PHP_BINARY, $detector, '--source-root=' . $sourceRoot],
        $root
    );
}

function wpsr_phase24_assert_summary(array $case, array $result, $fixed)
{
    $id = preg_quote($case['id'], '~');
    $checks = (int) $case['checks'];
    if ($fixed) {
        if ($result['exit'] !== 0 || !preg_match(
            '~SOURCE-CONTRACT id=' . $id . ' checked=' . $checks . ' failures=0(?: runtime=|\s)~',
            $result['output']
        )) {
            throw new RuntimeException($case['id'] . ': fixed fixture detector was not green with exact nonzero summary: ' . trim($result['output']));
        }
        return;
    }

    if ($result['exit'] === 0) {
        throw new RuntimeException($case['id'] . ': detector unexpectedly green on mutant');
    }
    if ($result['exit'] !== 1) {
        throw new RuntimeException($case['id'] . ': mutant detector exited ' . $result['exit'] . ', expected 1');
    }
    if (strpos($result['output'], 'SOURCE-CONTRACT FAIL ') === false
        || strpos($result['output'], $case['message']) === false
        || !preg_match('~SOURCE-CONTRACT id=' . $id . ' checked=' . $checks . ' failures=[1-9][0-9]*(?: runtime=|\s)~', $result['output'])) {
        throw new RuntimeException($case['id'] . ': mutant red did not name expected contract ID/message: ' . trim($result['output']));
    }
}

function wpsr_phase24_apply_mutation($workspace, array $mutation)
{
    $path = $workspace . '/' . $mutation['file'];
    if (!is_file($path)) {
        throw new RuntimeException('mutation source missing: ' . $mutation['file']);
    }
    if ($mutation['old'] === $mutation['new']) {
        throw new RuntimeException('mutation changes nothing: old and new bytes are identical');
    }
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('mutation source unreadable: ' . $mutation['file']);
    }
    $count = substr_count($source, $mutation['old']);
    if ($count !== (int) $mutation['expected']) {
        throw new RuntimeException('mutation match count drifted for ' . $mutation['file'] . ': expected '
            . (int) $mutation['expected'] . ', found ' . $count);
    }
    $mutated = str_replace($mutation['old'], $mutation['new'], $source, $replaced);
    if ($replaced !== (int) $mutation['expected'] || $mutated === $source) {
        throw new RuntimeException('mutation changes nothing or replacement count drifted: ' . $mutation['file']);
    }
    if (file_put_contents($path, $mutated) === false) {
        throw new RuntimeException('could not write mutation workspace: ' . $mutation['file']);
    }
    return $replaced;
}

function wpsr_phase24_assert_nonzero_cases(array $cases)
{
    if (!$cases) {
        throw new RuntimeException('fixture discovery returned zero cases');
    }
}

function wpsr_phase24_validate_discovery($root, array $cases)
{
    wpsr_phase24_assert_nonzero_cases($cases);
    $registry = json_decode(file_get_contents($root . '/tests/security/test-metadata.json'), true);
    if (!is_array($registry) || empty($registry['targets'])) {
        throw new RuntimeException('security registry unavailable for detector fixture discovery');
    }

    $registered = [];
    foreach ($registry['targets'] as $target) {
        if (preg_match('#^tests/lint/detect_([a-z0-9_]+)\.php$#', $target['path'] ?? '', $match)) {
            $registered[] = str_replace('_', '-', $match[1]);
        }
    }
    sort($registered, SORT_STRING);
    if (!$registered) {
        throw new RuntimeException('registered source-contract detector discovery returned zero targets');
    }

    $manifest = array_column($cases, 'id');
    if (count($manifest) !== count(array_unique($manifest, SORT_STRING))) {
        throw new RuntimeException('fixture manifest contains duplicate detector IDs');
    }
    sort($manifest, SORT_STRING);
    if ($manifest !== $registered) {
        throw new RuntimeException('registered detector/fixture manifest drift: registered=' . json_encode($registered)
            . ' fixtures=' . json_encode($manifest));
    }

    $fixtureIds = [];
    foreach (glob($root . '/tests/lint/fixtures/*/fixed', GLOB_ONLYDIR) ?: [] as $fixed) {
        $fixtureIds[] = basename(dirname($fixed));
    }
    sort($fixtureIds, SORT_STRING);
    if ($fixtureIds !== $registered) {
        throw new RuntimeException('fixture directory discovery drift: registered=' . json_encode($registered)
            . ' directories=' . json_encode($fixtureIds));
    }
}

function wpsr_phase24_validate_case($root, array $case)
{
    foreach (['id', 'checks', 'sources', 'mutation', 'message'] as $field) {
        if (empty($case[$field])) {
            throw new RuntimeException(($case['id'] ?? 'fixture case') . ': missing manifest field ' . $field);
        }
    }
    if ((int) $case['checks'] < 1 || !is_array($case['sources']) || !$case['sources']) {
        throw new RuntimeException($case['id'] . ': zero checks or sources');
    }
    $fixed = $root . '/tests/lint/fixtures/' . $case['id'] . '/fixed';
    $expected = $case['sources'];
    sort($expected, SORT_STRING);
    $actual = wpsr_phase24_fixture_files($fixed);
    if ($actual !== $expected) {
        throw new RuntimeException($case['id'] . ': fixed fixture files incomplete/stale: expected=' . json_encode($expected)
            . ' actual=' . json_encode($actual));
    }
    if (!in_array($case['mutation']['file'] ?? '', $case['sources'], true)) {
        throw new RuntimeException($case['id'] . ': mutation path is not a guarded fixture source');
    }
    foreach ($case['sources'] as $relative) {
        if (!is_file($root . '/' . $relative)) {
            throw new RuntimeException($case['id'] . ': guarded production source missing: ' . $relative);
        }
    }
}

function wpsr_phase24_incomplete_proof($root, array $case, $fixed)
{
    $workspace = wpsr_phase24_workspace($case['id'] . '-incomplete');
    try {
        wpsr_phase24_copy_tree($fixed, $workspace);
        $missing = $workspace . '/' . $case['sources'][0];
        if (!unlink($missing)) {
            throw new RuntimeException($case['id'] . ': could not remove incomplete-proof source');
        }
        $result = wpsr_phase24_detector($root, $case, $workspace);
        if ($result['exit'] !== 1
            || strpos($result['output'], $case['sources'][0]) === false
            || strpos($result['output'], 'source missing or unreadable') === false
            || strpos($result['output'], 'SOURCE-CONTRACT id=' . $case['id']) === false) {
            throw new RuntimeException($case['id'] . ': incomplete fixture did not fail closed without production fallback: '
                . trim($result['output']));
        }
    } finally {
        wpsr_phase24_release_workspace($workspace);
        wpsr_phase24_assert_no_residue($workspace);
    }
}

function wpsr_phase24_mutant_proof($root, array $case, $fixed, array $mutation = null)
{
    $workspace = wpsr_phase24_workspace($case['id'] . '-mutant');
    try {
        wpsr_phase24_copy_tree($fixed, $workspace);
        $count = wpsr_phase24_apply_mutation($workspace, $mutation ?: $case['mutation']);
        $result = wpsr_phase24_detector($root, $case, $workspace);
        wpsr_phase24_assert_summary($case, $result, false);
        return $count;
    } finally {
        wpsr_phase24_release_workspace($workspace);
        wpsr_phase24_assert_no_residue($workspace);
    }
}

function wpsr_phase24_self_tests($root, array $cases, array $baseline)
{
    $tests = [];

    try {
        wpsr_phase24_assert_nonzero_cases([]);
        $tests[] = ['zero fixtures fails', false, 'accepted zero cases'];
    } catch (RuntimeException $exception) {
        $tests[] = ['zero fixtures fails', strpos($exception->getMessage(), 'zero cases') !== false, $exception->getMessage()];
    }
    wpsr_phase24_assert_safety($baseline, 'self-test zero fixtures');

    $fixed = $root . '/tests/lint/fixtures/' . $cases[0]['id'] . '/fixed';
    $workspace = wpsr_phase24_workspace('self-no-op');
    try {
        wpsr_phase24_copy_tree($fixed, $workspace);
        $mutation = $cases[0]['mutation'];
        $mutation['new'] = $mutation['old'];
        try {
            wpsr_phase24_apply_mutation($workspace, $mutation);
            $tests[] = ['mutation that changes nothing fails', false, 'accepted no-op mutation'];
        } catch (RuntimeException $exception) {
            $tests[] = ['mutation that changes nothing fails', strpos($exception->getMessage(), 'changes nothing') !== false, $exception->getMessage()];
        }
    } finally {
        wpsr_phase24_release_workspace($workspace);
        wpsr_phase24_assert_no_residue($workspace);
    }
    wpsr_phase24_assert_safety($baseline, 'self-test no-op mutation');

    $benign = $cases[0]['mutation'];
    $benign['file'] = $cases[0]['sources'][0];
    $benign['old'] = 'Minimal faithful Phase 24 copy';
    $benign['new'] = 'Minimal faithful Phase 24 benign copy';
    $benign['expected'] = 1;
    try {
        wpsr_phase24_mutant_proof($root, $cases[0], $fixed, $benign);
        $tests[] = ['detector unexpectedly green on mutant fails', false, 'accepted green mutant'];
    } catch (RuntimeException $exception) {
        $tests[] = ['detector unexpectedly green on mutant fails', strpos($exception->getMessage(), 'unexpectedly green') !== false, $exception->getMessage()];
    }
    wpsr_phase24_assert_safety($baseline, 'self-test green mutant');

    $residue = wpsr_phase24_workspace('self-residue');
    try {
        try {
            wpsr_phase24_assert_no_residue($residue);
            $tests[] = ['fixture residue fails', false, 'accepted residue'];
        } catch (RuntimeException $exception) {
            $tests[] = ['fixture residue fails', strpos($exception->getMessage(), 'fixture residue remains') !== false, $exception->getMessage()];
        }
    } finally {
        wpsr_phase24_release_workspace($residue);
        wpsr_phase24_assert_no_residue($residue);
    }
    wpsr_phase24_assert_safety($baseline, 'self-test residue');

    $failures = 0;
    foreach ($tests as $test) {
        printf("%s  SELF-TEST %s — %s\n", $test[1] ? 'PASS' : 'FAIL', $test[0], $test[2]);
        if (!$test[1]) {
            $failures++;
        }
    }
    return [count($tests), $failures];
}

$wpsr_phase24_started = microtime(true);
$wpsr_phase24_failures = 0;
$wpsr_phase24_detectors = 0;
$wpsr_phase24_cases = 0;
$wpsr_phase24_mutations = 0;
$wpsr_phase24_self_test_count = 0;
$wpsr_phase24_self_test_failures = 0;

try {
    if (!is_dir($wpsr_phase24_pro_root . '/.git') && !is_file($wpsr_phase24_pro_root . '/.git')) {
        throw new WpsrPhase24SafetyException('Pro checkout missing: ' . $wpsr_phase24_pro_root);
    }
    $wpsr_phase24_baseline = [
        'core' => [
            'root' => $wpsr_phase24_root,
            'status' => wpsr_phase24_git_status($wpsr_phase24_root),
            'production' => wpsr_phase24_production_hashes($wpsr_phase24_root),
        ],
        'pro' => [
            'root' => $wpsr_phase24_pro_root,
            'status' => wpsr_phase24_git_status($wpsr_phase24_pro_root),
            'production' => wpsr_phase24_production_hashes($wpsr_phase24_pro_root),
        ],
    ];
    printf(
        "SOURCE-CONTRACT-FIXTURE SAFETY core-status=%s pro-status=%s production-paths=%d\n",
        substr(hash('sha256', $wpsr_phase24_baseline['core']['status']), 0, 16),
        substr(hash('sha256', $wpsr_phase24_baseline['pro']['status']), 0, 16),
        count($wpsr_phase24_baseline['core']['production']) + count($wpsr_phase24_baseline['pro']['production'])
    );

    $wpsr_phase24_cases_manifest = require $wpsr_phase24_root . '/tests/lint/source-contract-fixtures.php';
    if (!is_array($wpsr_phase24_cases_manifest)) {
        throw new RuntimeException('fixture manifest did not return an array');
    }
    wpsr_phase24_validate_discovery($wpsr_phase24_root, $wpsr_phase24_cases_manifest);
    foreach ($wpsr_phase24_cases_manifest as $case) {
        wpsr_phase24_validate_case($wpsr_phase24_root, $case);
    }
    $wpsr_phase24_detectors = count($wpsr_phase24_cases_manifest);

    list($wpsr_phase24_self_test_count, $wpsr_phase24_self_test_failures) = wpsr_phase24_self_tests(
        $wpsr_phase24_root,
        $wpsr_phase24_cases_manifest,
        $wpsr_phase24_baseline
    );
    $wpsr_phase24_failures += $wpsr_phase24_self_test_failures;

    foreach ($wpsr_phase24_cases_manifest as $case) {
        wpsr_phase24_assert_safety($wpsr_phase24_baseline, $case['id'] . ' before case');
        $fixed = $wpsr_phase24_root . '/tests/lint/fixtures/' . $case['id'] . '/fixed';
        try {
            $fixedResult = wpsr_phase24_detector($wpsr_phase24_root, $case, $fixed);
            wpsr_phase24_assert_summary($case, $fixedResult, true);
            wpsr_phase24_incomplete_proof($wpsr_phase24_root, $case, $fixed);
            $mutationCount = wpsr_phase24_mutant_proof($wpsr_phase24_root, $case, $fixed);
            $wpsr_phase24_mutations += $mutationCount;
            $wpsr_phase24_cases++;
            printf(
                "PASS  FIXTURE id=%s fixed-green=%d incomplete-red=1 mutated-red=1 mutation=%s\n",
                $case['id'],
                $case['checks'],
                $case['mutation']['file']
            );
        } catch (WpsrPhase24SafetyException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            $wpsr_phase24_failures++;
            fwrite(STDERR, 'FAIL  FIXTURE id=' . $case['id'] . ' ' . $throwable->getMessage() . "\n");
        }
        wpsr_phase24_assert_safety($wpsr_phase24_baseline, $case['id'] . ' after case');
    }
} catch (WpsrPhase24SafetyException $exception) {
    fwrite(STDERR, 'SOURCE-CONTRACT-FIXTURE SAFETY ABORT: ' . $exception->getMessage() . "\n");
    printf(
        "SOURCE-CONTRACT-FIXTURES detectors=%d cases=%d mutations=%d self-tests=%d failures=%d runtime=%.4fs\n",
        $wpsr_phase24_detectors,
        $wpsr_phase24_cases,
        $wpsr_phase24_mutations,
        $wpsr_phase24_self_test_count,
        $wpsr_phase24_failures + 1,
        microtime(true) - $wpsr_phase24_started
    );
    exit(1);
} catch (Throwable $throwable) {
    $wpsr_phase24_failures++;
    fwrite(STDERR, 'SOURCE-CONTRACT-FIXTURE FAIL: ' . $throwable->getMessage() . "\n");
}

printf(
    "SOURCE-CONTRACT-FIXTURES detectors=%d cases=%d mutations=%d self-tests=%d failures=%d runtime=%.4fs\n",
    $wpsr_phase24_detectors,
    $wpsr_phase24_cases,
    $wpsr_phase24_mutations,
    $wpsr_phase24_self_test_count,
    $wpsr_phase24_failures,
    microtime(true) - $wpsr_phase24_started
);
if ($wpsr_phase24_detectors === 0 || $wpsr_phase24_cases === 0 || $wpsr_phase24_mutations === 0
    || $wpsr_phase24_self_test_count === 0 || $wpsr_phase24_failures > 0) {
    exit(1);
}
exit(0);
