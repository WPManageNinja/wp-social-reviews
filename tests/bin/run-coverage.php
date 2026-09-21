<?php
/**
 * Merge independent PCOV captures and emit uncovered-inclusive reports.
 */

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\PcovDriver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Node\File;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlFacade;
use SebastianBergmann\CodeCoverage\Report\Text;
use SebastianBergmann\CodeCoverage\Report\Thresholds;

require '/opt/wpsr-coverage/vendor/autoload.php';

$wpsr_options = [
    'source'   => '/var/www/html/wp-content/plugins/wp-social-ninja',
    'artifact' => '/workspace/wp-social-ninja/tests/.artifacts/coverage/report',
    'roots'    => ['app', 'boot', 'config', 'database'],
    'expect'   => [],
    'self_test'=> false,
];
$wpsr_capture_files = [];

foreach (array_slice($argv, 1) as $wpsr_argument) {
    if (strpos($wpsr_argument, '--source=') === 0) {
        $wpsr_options['source'] = rtrim(substr($wpsr_argument, 9), '/');
    } elseif (strpos($wpsr_argument, '--artifact=') === 0) {
        $wpsr_options['artifact'] = rtrim(substr($wpsr_argument, 11), '/');
    } elseif (strpos($wpsr_argument, '--roots=') === 0) {
        $wpsr_options['roots'] = array_values(array_filter(explode(',', substr($wpsr_argument, 8))));
    } elseif (strpos($wpsr_argument, '--expect=') === 0) {
        $wpsr_options['expect'] = array_values(array_filter(explode(',', substr($wpsr_argument, 9))));
    } elseif ($wpsr_argument === '--self-test') {
        $wpsr_options['self_test'] = true;
    } else {
        $wpsr_capture_files[] = $wpsr_argument;
    }
}

if (!$wpsr_capture_files) {
    fwrite(STDERR, "ZERO COVERAGE CAPTURES — refusing false green.\n");
    exit(2);
}

$wpsr_filter = new Filter();
$wpsr_source_files = [];
$wpsr_excluded_prefixes = $wpsr_options['self_test'] ? [] : [
    'app/Services/Libs/', // Bundled Emogrifier/SimpleDom third-party libraries.
];
$wpsr_direct_files = $wpsr_options['self_test'] ? [] : [
    'wp-social-reviews.php',
    'index.php',
    'language/index.php',
    'resources/index.php',
];

foreach ($wpsr_options['roots'] as $wpsr_root) {
    $wpsr_directory = $wpsr_options['source'] . '/' . trim($wpsr_root, '/');
    if (!is_dir($wpsr_directory)) {
        fwrite(STDERR, 'Coverage source root missing: ' . $wpsr_directory . "\n");
        exit(2);
    }

    $wpsr_iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($wpsr_directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($wpsr_iterator as $wpsr_item) {
        if (!$wpsr_item->isFile() || strtolower($wpsr_item->getExtension()) !== 'php') {
            continue;
        }

        $wpsr_path = str_replace('\\', '/', $wpsr_item->getPathname());
        $wpsr_relative = substr($wpsr_path, strlen($wpsr_options['source']) + 1);
        $wpsr_excluded = false;
        foreach ($wpsr_excluded_prefixes as $wpsr_prefix) {
            if (strpos($wpsr_relative, $wpsr_prefix) === 0) {
                $wpsr_excluded = true;
                break;
            }
        }
        if (!$wpsr_excluded) {
            $wpsr_source_files[$wpsr_relative] = $wpsr_path;
        }
    }
}

foreach ($wpsr_direct_files as $wpsr_relative) {
    $wpsr_path = $wpsr_options['source'] . '/' . $wpsr_relative;
    if (!is_file($wpsr_path)) {
        fwrite(STDERR, 'Coverage direct source file missing: ' . $wpsr_path . "\n");
        exit(2);
    }
    $wpsr_source_files[$wpsr_relative] = $wpsr_path;
}

ksort($wpsr_source_files, SORT_STRING);
if (!$wpsr_source_files) {
    fwrite(STDERR, "ZERO COVERAGE SOURCE FILES — source policy discovered nothing.\n");
    exit(2);
}
$wpsr_filter->includeFiles(array_values($wpsr_source_files));

$wpsr_coverage = new CodeCoverage(new PcovDriver($wpsr_filter), $wpsr_filter);
$wpsr_suites = [];
$wpsr_raw_files_with_data = 0;
$wpsr_raw_executable_lines = 0;

foreach ($wpsr_capture_files as $wpsr_index => $wpsr_capture_file) {
    $wpsr_raw = @file_get_contents($wpsr_capture_file);
    $wpsr_capture = $wpsr_raw === false
        ? false
        : @unserialize($wpsr_raw, ['allowed_classes' => false]);
    if (!is_array($wpsr_capture)
        || ($wpsr_capture['format'] ?? null) !== 1
        || !isset($wpsr_capture['suite'], $wpsr_capture['data'])
        || !is_array($wpsr_capture['data'])) {
        fwrite(STDERR, 'Invalid PCOV capture: ' . $wpsr_capture_file . "\n");
        exit(2);
    }

    $wpsr_suite = (string) $wpsr_capture['suite'];
    $wpsr_suites[$wpsr_suite] = true;
    foreach ($wpsr_capture['data'] as $wpsr_lines) {
        if (!is_array($wpsr_lines) || !$wpsr_lines) {
            continue;
        }
        $wpsr_raw_files_with_data++;
        $wpsr_raw_executable_lines += count($wpsr_lines);
    }

    $wpsr_coverage->append(
        RawCodeCoverageData::fromXdebugWithoutPathCoverage($wpsr_capture['data']),
        $wpsr_suite . '-' . ($wpsr_index + 1)
    );
}

foreach ($wpsr_options['expect'] as $wpsr_expected_suite) {
    if (!isset($wpsr_suites[$wpsr_expected_suite])) {
        fwrite(STDERR, 'Coverage capture missing required tier: ' . $wpsr_expected_suite . "\n");
        exit(2);
    }
}

$wpsr_report = $wpsr_coverage->getReport();
$wpsr_files = [];
$wpsr_total_executable = 0;
$wpsr_total_covered = 0;
$wpsr_covered_files = 0;

foreach ($wpsr_report as $wpsr_node) {
    if (!$wpsr_node instanceof File) {
        continue;
    }

    $wpsr_path = str_replace('\\', '/', $wpsr_node->pathAsString());
    $wpsr_relative = strpos($wpsr_path, $wpsr_options['source'] . '/') === 0
        ? substr($wpsr_path, strlen($wpsr_options['source']) + 1)
        : $wpsr_path;
    $wpsr_executable = $wpsr_node->numberOfExecutableLines();
    $wpsr_covered = $wpsr_node->numberOfExecutedLines();
    $wpsr_percentage = $wpsr_executable > 0
        ? round(($wpsr_covered / $wpsr_executable) * 100, 2)
        : 0.0;
    $wpsr_physical = isset($wpsr_source_files[$wpsr_relative])
        ? count(file($wpsr_source_files[$wpsr_relative], FILE_IGNORE_NEW_LINES))
        : 0;

    $wpsr_files[$wpsr_relative] = [
        'covered'       => $wpsr_covered,
        'executable'    => $wpsr_executable,
        'percentage'    => $wpsr_percentage,
        'physical_lines'=> $wpsr_physical,
    ];
    $wpsr_total_executable += $wpsr_executable;
    $wpsr_total_covered += $wpsr_covered;
    if ($wpsr_covered > 0) {
        $wpsr_covered_files++;
    }
}
ksort($wpsr_files, SORT_STRING);

$wpsr_missing_report_files = array_diff_key($wpsr_source_files, $wpsr_files);
if ($wpsr_missing_report_files) {
    fwrite(STDERR, 'Coverage report omitted filtered first-party files: '
        . implode(', ', array_keys($wpsr_missing_report_files)) . "\n");
    exit(2);
}
if ($wpsr_raw_files_with_data === 0 || $wpsr_raw_executable_lines === 0) {
    fwrite(STDERR, "ZERO PCOV DATA — captures contain no executable line data.\n");
    exit(2);
}
if (!$wpsr_files || $wpsr_total_executable === 0) {
    fwrite(STDERR, "ZERO COVERAGE FILES — source discovery/executable analysis failed.\n");
    exit(2);
}
if ($wpsr_covered_files === 0 || $wpsr_total_covered === 0) {
    fwrite(STDERR, "ZERO COVERED FILES/DATA — refusing false green.\n");
    exit(2);
}

if ($wpsr_options['self_test']) {
    $wpsr_executed = $wpsr_files['fixtures/executed.php'] ?? $wpsr_files['executed.php'] ?? null;
    $wpsr_untouched = $wpsr_files['fixtures/untouched.php'] ?? $wpsr_files['untouched.php'] ?? null;
    if (!$wpsr_executed || $wpsr_executed['covered'] < 1 || !$wpsr_untouched
        || $wpsr_untouched['executable'] < 1 || $wpsr_untouched['covered'] !== 0) {
        fwrite(STDERR, "Coverage self-test failed: executed fixture must be covered and untouched fixture must remain 0%.\n");
        fwrite(STDERR, json_encode($wpsr_files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }

    printf(
        "COVERAGE-SELF-TEST: executed=%d/%d untouched=%d/%d show_uncovered=true\n",
        $wpsr_executed['covered'],
        $wpsr_executed['executable'],
        $wpsr_untouched['covered'],
        $wpsr_untouched['executable']
    );
    exit(0);
}

$wpsr_artifact = $wpsr_options['artifact'];
if (is_dir($wpsr_artifact)) {
    $wpsr_iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($wpsr_artifact, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($wpsr_iterator as $wpsr_item) {
        $wpsr_item->isDir() ? rmdir($wpsr_item->getPathname()) : unlink($wpsr_item->getPathname());
    }
} elseif (!mkdir($wpsr_artifact, 0777, true) && !is_dir($wpsr_artifact)) {
    fwrite(STDERR, 'Could not create coverage artifact directory: ' . $wpsr_artifact . "\n");
    exit(2);
}

$wpsr_text = (new Text(Thresholds::default(), true, false))->process($wpsr_coverage, false);
file_put_contents($wpsr_artifact . '/coverage.txt', $wpsr_text);
(new Clover())->process($wpsr_coverage, $wpsr_artifact . '/clover.xml', 'WP Social Ninja core');
(new HtmlFacade('WP Social Ninja Phase 13'))->process($wpsr_coverage, $wpsr_artifact . '/html');

$wpsr_percentage = round(($wpsr_total_covered / $wpsr_total_executable) * 100, 2);
$wpsr_uncovered_files = array_filter($wpsr_files, static function ($wpsr_summary) {
    return $wpsr_summary['covered'] === 0;
});
$wpsr_executable_zero_files = array_filter($wpsr_files, static function ($wpsr_summary) {
    return $wpsr_summary['executable'] > 0 && $wpsr_summary['covered'] === 0;
});
$wpsr_summary = [
    'generated_at_utc' => gmdate('c'),
    'driver'           => 'PCOV ' . phpversion('pcov'),
    'show_uncovered'   => true,
    'suites'           => array_keys($wpsr_suites),
    'source_policy'    => [
        'included_roots' => $wpsr_options['roots'],
        'included_files' => $wpsr_direct_files,
        'excluded_paths' => [
            'vendor/**',
            'tests/**',
            'dev/**',
            'node_modules/**',
            'assets/** and builds/** generated output',
            'app/Services/Libs/** bundled third-party Emogrifier/SimpleDom',
            'WP Social Ninja Pro checkout',
        ],
    ],
    'totals' => [
        'files'                 => count($wpsr_files),
        'covered_files'         => $wpsr_covered_files,
        'uncovered_files'       => count($wpsr_uncovered_files),
        'covered_lines'         => $wpsr_total_covered,
        'executable_lines'      => $wpsr_total_executable,
        'line_percentage'       => $wpsr_percentage,
        'executable_zero_files' => count($wpsr_executable_zero_files),
    ],
    'files' => $wpsr_files,
];
file_put_contents(
    $wpsr_artifact . '/summary.json',
    json_encode($wpsr_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

fwrite(STDOUT, $wpsr_text);
echo "\nPer-file executable line coverage (show_uncovered=true)\n";
foreach ($wpsr_files as $wpsr_file => $wpsr_file_summary) {
    printf(
        "COVERAGE-FILE covered=%d executable=%d percent=%.2f physical=%d file=%s\n",
        $wpsr_file_summary['covered'],
        $wpsr_file_summary['executable'],
        $wpsr_file_summary['percentage'],
        $wpsr_file_summary['physical_lines'],
        $wpsr_file
    );
}
printf(
    "COVERAGE-TOTAL files=%d covered-files=%d uncovered-files=%d covered=%d executable=%d percent=%.2f executable-zero-files=%d show_uncovered=true\n",
    count($wpsr_files),
    $wpsr_covered_files,
    count($wpsr_uncovered_files),
    $wpsr_total_covered,
    $wpsr_total_executable,
    $wpsr_percentage,
    count($wpsr_executable_zero_files)
);
printf(
    "COVERAGE-ARTIFACTS text=%s/coverage.txt clover=%s/clover.xml html=%s/html/index.html summary=%s/summary.json raw=%s/raw\n",
    $wpsr_artifact,
    $wpsr_artifact,
    $wpsr_artifact,
    $wpsr_artifact,
    dirname($wpsr_artifact)
);
