<?php

use WpsrTests\Mutation\Runner;

require_once __DIR__ . '/Runner.php';

function wpsr_mutation_self_test()
{
    $tmp = sys_get_temp_dir() . '/wpsr-mutation-self-test-' . bin2hex(random_bytes(5));
    if (!mkdir($tmp . '/app', 0777, true) && !is_dir($tmp . '/app')) {
        throw new RuntimeException('Could not create mutation self-test repository.');
    }

    $run = function ($command) use ($tmp) {
        exec('cd ' . escapeshellarg($tmp) . ' && ' . $command . ' 2>&1', $output, $code);
        if ($code !== 0) {
            throw new RuntimeException("Self-test setup command failed: {$command}\n" . implode("\n", $output));
        }
    };

    try {
        file_put_contents($tmp . '/app/Fixture.php', "<?php\nfunction wpsr_mutation_fixture_value() { return 1; }\n");
        file_put_contents($tmp . '/check.php', "<?php\nrequire __DIR__ . '/app/Fixture.php';\nexit(wpsr_mutation_fixture_value() === 1 ? 0 : 1);\n");
        file_put_contents($tmp . '/drift.txt', "clean\n");
        $run('git init -q');
        $run('git config user.email mutation-self-test@example.invalid');
        $run('git config user.name mutation-self-test');
        $run('git add app/Fixture.php check.php drift.txt && git commit -qm baseline');

        $base = [
            'file' => 'app/Fixture.php',
            'operator' => 'self-test',
            'narrow_tier' => 'fixture',
            'source_trace' => 'tests/mutation/self-test.php',
        ];

        $killable = $base + [
            'id' => 'SELF-KILL',
            'description' => 'Intentionally killable fixture.',
            'search' => 'return 1;',
            'replace' => 'return 2;',
            'command' => 'php check.php',
        ];
        $runner = new Runner($tmp, [$tmp], 'git');
        $audit = $runner->run([$killable], 'php check.php', false);
        if (($audit['results'][0]['outcome'] ?? '') !== 'killed') {
            throw new RuntimeException('Intentionally killable fixture mutant was not killed.');
        }
        echo "MUTATION-SELF-TEST killable: killed\n";

        $equivalent = $base + [
            'id' => 'SELF-EQUIVALENT',
            'description' => 'Equivalent arithmetic fixture.',
            'search' => 'return 1;',
            'replace' => 'return 1 + 0;',
            'command' => 'php check.php',
            'survivor_classification' => 'equivalent',
        ];
        $runner = new Runner($tmp, [$tmp], 'git');
        $audit = $runner->run([$equivalent], 'php check.php', false);
        if (($audit['results'][0]['outcome'] ?? '') !== 'equivalent') {
            throw new RuntimeException('Equivalent fixture mutant was not classified equivalent.');
        }
        echo "MUTATION-SELF-TEST equivalent: classified\n";

        $cleanSummary = [
            'total' => 2,
            'killed' => 1,
            'survived' => 0,
            'equivalent' => 1,
            'skipped' => 0,
            'errors' => 0,
            'corrected_outcomes' => 0,
            'runtime_seconds' => 0.01,
            'kill_rate_percent' => 100.0,
            'survivors' => [],
        ];
        if (wpsr_mutation_exit_code($cleanSummary) !== 0) {
            throw new RuntimeException('Zero-survivor mutation summary did not exit successfully.');
        }
        $survivorSummary = $cleanSummary;
        $survivorSummary['total'] = 3;
        $survivorSummary['survived'] = 1;
        $survivorSummary['survivors'] = ['SELF-SURVIVOR'];
        if (wpsr_mutation_exit_code($survivorSummary) !== 1) {
            throw new RuntimeException('Surviving mutation summary did not produce exit 1.');
        }
        $partialFailed = false;
        try {
            wpsr_mutation_exit_code(['errors' => 0, 'survived' => 0]);
        } catch (RuntimeException $exception) {
            $partialFailed = strpos($exception->getMessage(), 'missing valid') !== false;
        }
        if (!$partialFailed) {
            throw new RuntimeException('Partial mutation summary did not fail closed.');
        }
        $zeroSummary = $cleanSummary;
        $zeroSummary['total'] = 0;
        $zeroSummary['killed'] = 0;
        $zeroFailedAtExit = false;
        try {
            wpsr_mutation_exit_code($zeroSummary);
        } catch (RuntimeException $exception) {
            $zeroFailedAtExit = strpos($exception->getMessage(), 'zero outcomes') !== false;
        }
        if (!$zeroFailedAtExit) {
            throw new RuntimeException('Zero-outcome mutation summary did not fail closed.');
        }
        echo "MUTATION-SELF-TEST exit contract: clean=0 survivor=1 partial=rejected zero=rejected\n";

        $zeroFailed = false;
        try {
            $runner = new Runner($tmp, [$tmp], 'git');
            $runner->run([], 'php check.php', false);
        } catch (RuntimeException $exception) {
            $zeroFailed = strpos($exception->getMessage(), 'zero mutants') !== false;
        }
        if (!$zeroFailed) {
            throw new RuntimeException('Zero-mutant self-test did not fail closed.');
        }
        echo "MUTATION-SELF-TEST zero mutants: rejected\n";

        $dirty = $base + [
            'id' => 'SELF-DIRTY',
            'description' => 'Command dirties second tracked file.',
            'search' => 'return 1;',
            'replace' => 'return 2;',
            'command' => "printf 'drift\\n' >> drift.txt; exit 1",
        ];
        $dirtyDetected = false;
        try {
            $runner = new Runner($tmp, [$tmp], 'git');
            $runner->run([$dirty], 'php check.php', false);
        } catch (RuntimeException $exception) {
            $dirtyDetected = strpos($exception->getMessage(), 'Dirty drift detected') !== false;
        }
        $run('git checkout -- drift.txt app/Fixture.php');
        if (!$dirtyDetected) {
            throw new RuntimeException('Dirty-drift restoration guard self-test did not fail.');
        }
        echo "MUTATION-SELF-TEST dirty drift: detected\n";

        return true;
    } finally {
        exec('rm -rf ' . escapeshellarg($tmp));
    }
}
