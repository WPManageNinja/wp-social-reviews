<?php

use WpsrTests\Mutation\Runner;

$root = dirname(__DIR__, 2);
$pro = dirname($root) . '/wp-social-ninja-pro';

require_once $root . '/tests/mutation/Runner.php';
require_once $root . '/tests/mutation/self-test.php';

$options = [
    'filter' => null,
    'max' => null,
    'self_test_only' => false,
    'exit_contract' => null,
    'write_committed' => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--self-test-only') {
        $options['self_test_only'] = true;
    } elseif ($arg === '--write-committed') {
        $options['write_committed'] = true;
    } elseif (strpos($arg, '--exit-contract=') === 0) {
        $options['exit_contract'] = substr($arg, 16);
    } elseif (strpos($arg, '--filter=') === 0) {
        $options['filter'] = substr($arg, 9);
    } elseif (strpos($arg, '--max=') === 0) {
        $options['max'] = (int) substr($arg, 6);
    } else {
        fwrite(STDERR, "Unknown mutation option: {$arg}\n");
        exit(2);
    }
}

function wpsr_mutation_exit_code(array $summary)
{
    $countKeys = ['total', 'killed', 'survived', 'equivalent', 'skipped', 'errors', 'corrected_outcomes'];
    foreach ($countKeys as $key) {
        if (!array_key_exists($key, $summary) || !is_int($summary[$key]) || $summary[$key] < 0) {
            throw new RuntimeException('Mutation summary missing valid non-negative integer: ' . $key);
        }
    }
    if (!isset($summary['survivors']) || !is_array($summary['survivors'])) {
        throw new RuntimeException('Mutation summary missing survivor IDs.');
    }
    if (!array_key_exists('kill_rate_percent', $summary) || !is_numeric($summary['kill_rate_percent'])
        || !array_key_exists('runtime_seconds', $summary) || !is_numeric($summary['runtime_seconds'])
        || $summary['runtime_seconds'] < 0
    ) {
        throw new RuntimeException('Mutation summary missing valid rate/runtime metadata.');
    }
    if ($summary['total'] < 1) {
        throw new RuntimeException('Mutation summary contains zero outcomes.');
    }
    $classified = $summary['killed'] + $summary['survived'] + $summary['equivalent']
        + $summary['skipped'] + $summary['errors'];
    if ($summary['total'] !== $classified) {
        throw new RuntimeException('Mutation summary total does not match classified outcomes.');
    }
    if (count($summary['survivors']) !== $summary['survived']) {
        throw new RuntimeException('Mutation summary survivor IDs do not match survived count.');
    }
    foreach ($summary['survivors'] as $survivorId) {
        if (!is_string($survivorId) || $survivorId === '') {
            throw new RuntimeException('Mutation summary contains malformed survivor ID.');
        }
    }
    if ($summary['corrected_outcomes'] > $summary['killed']) {
        throw new RuntimeException('Mutation summary corrected outcomes exceed killed mutants.');
    }

    return $summary['errors'] === 0 && $summary['survived'] === 0 ? 0 : 1;
}

function wpsr_mutation_write_json($path, array $value)
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Could not create {$directory}");
    }
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    $temporary = $path . '.tmp-' . getmypid();
    if (file_put_contents($temporary, $json) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException("Could not write {$path}");
    }
}

try {
    if ($options['exit_contract'] !== null) {
        $summary = [
            'total' => 1,
            'killed' => 1,
            'survived' => 0,
            'equivalent' => 0,
            'skipped' => 0,
            'errors' => 0,
            'corrected_outcomes' => 0,
            'runtime_seconds' => 0.0,
            'kill_rate_percent' => 100.0,
            'survivors' => [],
        ];
        if ($options['exit_contract'] === 'survivor') {
            $summary['killed'] = 0;
            $summary['survived'] = 1;
            $summary['kill_rate_percent'] = 0.0;
            $summary['survivors'] = ['DERIVED-SURVIVOR'];
        } elseif ($options['exit_contract'] !== 'clean') {
            throw new RuntimeException('Mutation exit-contract fixture must be clean or survivor.');
        }
        printf("MUTATION-EXIT-CONTRACT: mode=%s survived=%d errors=%d\n", $options['exit_contract'], $summary['survived'], $summary['errors']);
        exit(wpsr_mutation_exit_code($summary));
    }

    echo "WP Social Ninja Phase 19 mutation audit\n";
    wpsr_mutation_self_test();
    if ($options['self_test_only']) {
        echo "MUTATION-SELF-TEST: PASS\n";
        exit(0);
    }

    $mutants = require $root . '/tests/mutation/manifest.php';
    if ($options['filter'] !== null) {
        $wanted = array_fill_keys(array_filter(explode(',', $options['filter']), 'strlen'), true);
        $mutants = array_values(array_filter($mutants, function ($mutant) use ($wanted) {
            return isset($wanted[$mutant['id']]);
        }));
    }
    if ($options['max'] !== null) {
        if ($options['max'] < 0) {
            throw new RuntimeException('Mutation --max must be zero or greater.');
        }
        $mutants = array_slice($mutants, 0, $options['max']);
    }

    printf("Selected mutants: %d\n", count($mutants));
    $guards = [$root];
    if (is_dir($pro . '/.git')) {
        $guards[] = $pro;
    }
    $runner = new Runner($root, $guards, 'git');
    // Foundation run-all includes foundation-only tree guards. Mutation audit runs
    // complete behavioral gate directly so later stacked tiers do not poison baseline.
    $fullCommand = implode(' && ', [
        'bash tests/lab/scripts/php-tests.sh tests/bin/run-smoke.php',
        'bash tests/lab/scripts/php-tests.sh tests/bin/run-permissions.php',
        'bash tests/lab/scripts/php-tests.sh tests/bin/run-pure.php',
        'bash tests/lab/scripts/php-tests.sh tests/bin/run-integration.php',
        'bash tests/lab/scripts/php-tests.sh tests/bin/run-public.php',
    ]);
    $audit = $runner->run($mutants, $fullCommand, true);
    $audit['manifest'] = [
        'path' => 'tests/mutation/manifest.php',
        'selected_ids' => array_column($mutants, 'id'),
        'selection' => [
            'filter' => $options['filter'],
            'max' => $options['max'],
            'deterministic_order' => true,
        ],
        'scope' => 'covered/high-risk core PHP only; one syntactically valid mutant at a time',
    ];
    $audit['baseline']['parent_verified'] = [
        'default' => 'green ~14s',
        'public' => '17/20',
        'e2e' => '3/3',
        'browser' => '42/48',
        'throughput' => '8/12',
        'merged_php_coverage_percent' => 26.64,
        'pre_phase_core_status_sha256' => '808417a7dcd290a5994998b87548e04691fef9063f4cdae4adfd9ea89c67eae1',
        'pre_phase_core_status_bytes' => 4670,
        'pre_phase_pro_status_sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        'pre_phase_core_production_tree_sha256' => '322a22a5b801d5c157b2ad483eac7676f61db22357ae78199ec69ddc49b5ed61',
        'pre_phase_pro_tracked_tree_sha256' => 'fefefd8c47fbbd43a55a1821db5c35526c3d4e63c6230fbf7d0bf50e02bf61d5',
    ];

    $artifactRoot = $root . '/tests/.artifacts/mutation';
    wpsr_mutation_write_json($artifactRoot . '/baseline.json', $audit['baseline']);
    wpsr_mutation_write_json($artifactRoot . '/results.json', [
        'manifest' => $audit['manifest'],
        'summary' => $audit['summary'],
        'results' => $audit['results'],
    ]);

    if ($options['write_committed']) {
        wpsr_mutation_write_json($root . '/tests/mutation/baseline.json', $audit['baseline']);
        wpsr_mutation_write_json($root . '/tests/mutation/results.json', [
            'manifest' => $audit['manifest'],
            'summary' => $audit['summary'],
            'results' => $audit['results'],
        ]);
    }

    $summary = $audit['summary'];
    printf(
        "MUTATION: total=%d killed=%d survived=%d equivalent=%d skipped=%d errors=%d corrected=%d kill-rate=%.2f%% runtime=%.3fs\n",
        $summary['total'], $summary['killed'], $summary['survived'], $summary['equivalent'],
        $summary['skipped'], $summary['errors'], $summary['corrected_outcomes'],
        $summary['kill_rate_percent'], $summary['runtime_seconds']
    );
    echo 'SURVIVORS: ' . (empty($summary['survivors']) ? 'none' : implode(', ', $summary['survivors'])) . "\n";

    exit(wpsr_mutation_exit_code($summary));
} catch (Throwable $throwable) {
    fwrite(STDERR, 'MUTATION-AUDIT: FAIL — ' . $throwable->getMessage() . "\n");
    exit(2);
}
