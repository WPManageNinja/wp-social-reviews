<?php
/** Phase 31 derived matrix drift proof. Production checkouts stay byte-identical. */
require_once __DIR__ . '/matrix-lib.php';
require_once __DIR__ . '/runtime-result-lib.php';

$wpsr_root = dirname(__DIR__, 2);
$wpsr_pro_root = dirname($wpsr_root) . '/wp-social-ninja-pro';
$wpsr_temp = sys_get_temp_dir() . '/wpsr-phase31-' . bin2hex(random_bytes(6));
$wpsr_core_status = shell_exec('git -C ' . escapeshellarg($wpsr_root) . ' status --porcelain=v1 -z --untracked-files=all');
$wpsr_pro_status = shell_exec('git -C ' . escapeshellarg($wpsr_pro_root) . ' status --porcelain=v1 -z --untracked-files=all');
$wpsr_core_boot_hash = hash_file('sha256', $wpsr_root . '/wp-social-reviews.php');
$wpsr_pro_boot_hash = hash_file('sha256', $wpsr_pro_root . '/wp-social-ninja-pro.php');
$wpsr_cases = $wpsr_red = $wpsr_restored = $wpsr_failures = 0;

function wpsr_phase31_record($wpsr_name, $wpsr_ok, $wpsr_detail)
{
    global $wpsr_cases, $wpsr_red, $wpsr_failures;
    $wpsr_cases++;
    if ($wpsr_ok) {
        $wpsr_red++;
        echo 'PROOF ' . $wpsr_name . ': RED expected ' . $wpsr_detail . "\n";
    } else {
        $wpsr_failures++;
        echo 'PROOF ' . $wpsr_name . ': FAIL ' . $wpsr_detail . "\n";
    }
}
function wpsr_phase31_has(array $wpsr_errors, $wpsr_fragment)
{
    foreach ($wpsr_errors as $wpsr_error) {
        if (strpos($wpsr_error, $wpsr_fragment) !== false) {
            return true;
        }
    }
    return false;
}
function wpsr_phase31_remove_purpose(array $wpsr_matrix, $wpsr_purpose)
{
    $wpsr_matrix['cells'] = array_values(array_filter($wpsr_matrix['cells'], static function ($wpsr_cell) use ($wpsr_purpose) {
        return ($wpsr_cell['purpose'] ?? '') !== $wpsr_purpose;
    }));
    return $wpsr_matrix;
}
function wpsr_phase31_valid_runtime(array $wpsr_matrix, $wpsr_root)
{
    $wpsr_cell = $wpsr_matrix['cells'][0];
    $wpsr_core = $wpsr_matrix['artifacts'][$wpsr_cell['core']];
    $wpsr_dimension = $wpsr_matrix['dimensions'][$wpsr_cell['dimension']];
    $wpsr_cell['resolved'] = [
        'core_version' => $wpsr_core['version'],
        'pro_version' => '',
        'commits' => ['core' => wpsr_compatibility_git($wpsr_root, ['rev-parse', 'HEAD'], $wpsr_code), 'pro' => null],
        'images' => [
            'wordpress' => 'sha256:' . str_repeat('1', 64),
            'cli' => 'sha256:' . str_repeat('2', 64),
            'database' => 'sha256:' . str_repeat('3', 64),
        ],
        'dimension' => $wpsr_dimension,
    ];
    $wpsr_event = [
        'schema' => 1,
        'cell' => $wpsr_cell['id'],
        'purpose' => $wpsr_cell['purpose'],
        'expected_compatibility' => $wpsr_cell['expected']['compatibility'],
        'actual_compatibility' => $wpsr_cell['expected']['compatibility'],
        'versions' => ['core' => $wpsr_core['version'], 'pro' => null, 'wordpress' => '6.8.1', 'php' => '8.2.20', 'database' => '11.4.5'],
        'commits' => $wpsr_cell['resolved']['commits'],
        'images' => $wpsr_cell['resolved']['images'],
        'boot' => ['core' => true, 'pro' => false],
        'routes' => ['core_paths' => 10, 'pro_paths' => 0, 'pro_declarations_checked' => 0],
        'checks' => ['total' => 8, 'lifecycle' => 6, 'rest' => 2],
        'diagnostic' => '',
        'failures' => [],
    ];
    return [$wpsr_cell, $wpsr_event];
}

function wpsr_phase31_tree_hash($wpsr_path)
{
    $wpsr_rows = [];
    $wpsr_iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($wpsr_path, FilesystemIterator::SKIP_DOTS));
    foreach ($wpsr_iterator as $wpsr_file) {
        if ($wpsr_file->isFile()) {
            $wpsr_relative = substr($wpsr_file->getPathname(), strlen($wpsr_path) + 1);
            $wpsr_rows[] = $wpsr_relative . ':' . hash_file('sha256', $wpsr_file->getPathname());
        }
    }
    sort($wpsr_rows, SORT_STRING);
    return hash('sha256', implode("\n", $wpsr_rows));
}

function wpsr_phase31_summary_gate(array $wpsr_summary, $wpsr_path, $wpsr_matrix_path)
{
    file_put_contents($wpsr_path, json_encode($wpsr_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $wpsr_output = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/validate-summary.php')
        . ' ' . escapeshellarg($wpsr_path) . ' ' . escapeshellarg($wpsr_matrix_path) . ' 2>&1', $wpsr_output, $wpsr_code);
    return [$wpsr_code, implode("\n", $wpsr_output)];
}

try {
    if (!mkdir($wpsr_temp, 0700, true)) {
        throw new RuntimeException('could not create proof workspace');
    }
    $wpsr_matrix = wpsr_compatibility_load(__DIR__ . '/matrix.json');
    $wpsr_green = wpsr_compatibility_validate($wpsr_matrix, $wpsr_root);
    if ($wpsr_green) {
        throw new RuntimeException('live matrix is not green: ' . implode('; ', $wpsr_green));
    }
    $wpsr_restored++;

    $wpsr_shape_errors = [];
    $wpsr_shape_valid = wpsr_compatibility_exact_keys([], ['schema'], 'derived matrix object', $wpsr_shape_errors);
    wpsr_phase31_record('list-is-not-object', !$wpsr_shape_valid && wpsr_phase31_has($wpsr_shape_errors, 'must be an object'), 'list/object confusion rejected');
    wpsr_phase31_record('sparse-array-is-not-list', !wpsr_compatibility_is_list([1 => 'foreign']), 'sparse array rejected as a JSON list');

    foreach (['list' => "[]\n", 'malformed' => "{broken}\n"] as $wpsr_fixture_name => $wpsr_fixture_source) {
        $wpsr_invalid_matrix = $wpsr_temp . '/' . $wpsr_fixture_name . '-matrix.json';
        file_put_contents($wpsr_invalid_matrix, $wpsr_fixture_source);
        $wpsr_invalid_caught = false;
        try {
            wpsr_compatibility_load($wpsr_invalid_matrix);
        } catch (Throwable $wpsr_exception) {
            $wpsr_invalid_caught = strpos($wpsr_exception->getMessage(), 'matrix malformed or root is not an object') !== false;
        }
        wpsr_phase31_record('matrix-' . $wpsr_fixture_name . '-root', $wpsr_invalid_caught, $wpsr_fixture_name . ' JSON root rejected');
    }
    $wpsr_missing_caught = false;
    try {
        wpsr_compatibility_load($wpsr_temp . '/missing-matrix.json');
    } catch (Throwable $wpsr_exception) {
        $wpsr_missing_caught = strpos($wpsr_exception->getMessage(), 'matrix missing') !== false;
    }
    wpsr_phase31_record('matrix-missing', $wpsr_missing_caught, 'missing matrix rejected');
    foreach (['empty-object-list' => new stdClass(), 'numeric-object-list' => (object) $wpsr_matrix['cells']] as $wpsr_fixture_name => $wpsr_object_list) {
        $wpsr_ambiguous_matrix = $wpsr_matrix;
        if ($wpsr_fixture_name === 'empty-object-list') {
            $wpsr_ambiguous_matrix['historical_unavailable'] = $wpsr_object_list;
        } else {
            $wpsr_ambiguous_matrix['cells'] = $wpsr_object_list;
        }
        $wpsr_ambiguous_path = $wpsr_temp . '/' . $wpsr_fixture_name . '.json';
        file_put_contents($wpsr_ambiguous_path, json_encode($wpsr_ambiguous_matrix, JSON_UNESCAPED_SLASHES));
        $wpsr_ambiguous_caught = false;
        try {
            wpsr_compatibility_load($wpsr_ambiguous_path);
        } catch (Throwable $wpsr_exception) {
            $wpsr_ambiguous_caught = strpos($wpsr_exception->getMessage(), 'ambiguous JSON object/list shape') !== false;
        }
        wpsr_phase31_record($wpsr_fixture_name, $wpsr_ambiguous_caught, 'JSON object cannot masquerade as list');
    }
    $wpsr_malformed_path_caught = false;
    try {
        wpsr_compatibility_load(['not-a-path']);
    } catch (Throwable $wpsr_exception) {
        $wpsr_malformed_path_caught = strpos($wpsr_exception->getMessage(), 'matrix path malformed') !== false;
    }
    wpsr_phase31_record('malformed-helper-scalars', $wpsr_malformed_path_caught
        && wpsr_compatibility_constant([], []) === null
        && wpsr_compatibility_readme_field([], []) === null
        && wpsr_compatibility_canonical_repository([]) === null
        && wpsr_compatibility_plugin_root([], 'pro') === null, 'nonscalar helper inputs rejected without diagnostics');

    $wpsr_constant_fixture = $wpsr_temp . '/constant.php';
    file_put_contents($wpsr_constant_fixture, "<?php\n// define('WPSR_DERIVED_VERSION', '9.9.9');\n\$wpsr_decoy = \"define('WPSR_DERIVED_VERSION', '8.8.8')\";\n\$wpsr_object->define('WPSR_DERIVED_VERSION', '7.7.7');\n\$wpsr_object?->define('WPSR_DERIVED_VERSION', '7.6.7');\nWpsrFixture::define('WPSR_DERIVED_VERSION', '6.6.6');\ndefine('WPSR_DERIVED_VERSION', '5.5.5');\n");
    wpsr_phase31_record('constant-source-decoys', wpsr_compatibility_constant($wpsr_constant_fixture, 'WPSR_DERIVED_VERSION') === '5.5.5', 'comment/string/method decoys cannot override global define');
    $wpsr_namespaced_fixture = $wpsr_temp . '/namespaced-constant.php';
    file_put_contents($wpsr_namespaced_fixture, "<?php\nnamespace WpsrFixture;\nfunction define(\$name, \$value) {}\ndefine('WPSR_DERIVED_VERSION', '5.5.5');\n");
    $wpsr_imported_fixture = $wpsr_temp . '/imported-constant.php';
    file_put_contents($wpsr_imported_fixture, "<?php\nuse function WpsrFixture\\define;\ndefine('WPSR_DERIVED_VERSION', '5.5.5');\n");
    wpsr_phase31_record('non-global-constant-source', wpsr_compatibility_constant($wpsr_namespaced_fixture, 'WPSR_DERIVED_VERSION') === null
        && wpsr_compatibility_constant($wpsr_imported_fixture, 'WPSR_DERIVED_VERSION') === null,
        'namespaced/imported define calls rejected');
    wpsr_phase31_record('missing-readme-field', wpsr_compatibility_readme_field($wpsr_constant_fixture, 'Requires PHP') === null, 'missing readme field rejected');
    $wpsr_readme_fixture = $wpsr_temp . '/readme.txt';
    file_put_contents($wpsr_readme_fixture, "Requires PHP: 7.4\nRequires PHP: 7.4\n");
    wpsr_phase31_record('duplicate-readme-field', wpsr_compatibility_readme_field($wpsr_readme_fixture, 'Requires PHP') === null, 'ambiguous duplicate readme field rejected');
    wpsr_phase31_record('malformed-repository-url', wpsr_compatibility_canonical_repository('github.com/foreign/project') === null, 'repository URL without transport rejected');
    $wpsr_unsafe_repositories = [
        'file://github.com/WPManageNinja/wp-social-ninja.git',
        'https://github.com/WPManageNinja/wp-social-ninja.git?mirror=foreign',
        'ssh://foreign@github.com/WPManageNinja/wp-social-ninja.git',
        'https://github.com:8443/WPManageNinja/wp-social-ninja.git',
    ];
    wpsr_phase31_record('ambiguous-repository-url', !array_filter($wpsr_unsafe_repositories, static function ($wpsr_url) {
        return wpsr_compatibility_canonical_repository($wpsr_url) !== null;
    }), 'unsafe or ambiguous repository transports rejected');
    wpsr_phase31_record('unknown-plugin-root', wpsr_compatibility_plugin_root($wpsr_root, 'foreign') === null, 'unknown plugin root rejected');
    wpsr_phase31_record('invalid-git-root', wpsr_compatibility_git($wpsr_temp, ['rev-parse', 'HEAD'], $wpsr_invalid_git_code) === null && $wpsr_invalid_git_code !== 0, 'non-repository Git lookup rejected');
    $wpsr_malformed_git = wpsr_compatibility_git($wpsr_root, [['rev-parse'], 'HEAD'], $wpsr_malformed_git_code);
    wpsr_phase31_record('malformed-git-arguments', $wpsr_malformed_git === null
        && $wpsr_malformed_git_code === WPSR_COMPATIBILITY_MISUSE, 'nonscalar Git arguments rejected without parser crash');

    $wpsr_mutant = $wpsr_matrix;
    $wpsr_mutant['constraints']['wordpress']['sources'][0] = $wpsr_mutant['constraints']['wordpress']['sources'][1];
    wpsr_phase31_record('duplicate-readme-source', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'constraint source mismatch: wordpress'), 'duplicate Core/Pro source identity rejected');

    $wpsr_mutant = wpsr_phase31_remove_purpose($wpsr_matrix, 'current-compatible-pair');
    wpsr_phase31_record('missing-current-pair', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'current-compatible-pair'), 'missing current pair rejected');

    $wpsr_mutant = wpsr_phase31_remove_purpose($wpsr_matrix, 'current-free-alone');
    wpsr_phase31_record('omitted-free-alone', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'current-free-alone'), 'omitted free-alone rejected');

    $wpsr_mutant = $wpsr_matrix;
    $wpsr_mutant['constraints']['pro_requires_core']['range'] = '>=4.3.9';
    wpsr_phase31_record('constraint-source-mismatch', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'constraint source mismatch'), 'constraint drift rejected');

    $wpsr_mutant = $wpsr_matrix;
    $wpsr_mutant['cells'][] = $wpsr_mutant['cells'][0];
    $wpsr_duplicate_errors = wpsr_compatibility_validate($wpsr_mutant, $wpsr_root);
    wpsr_phase31_record('duplicate-cell', wpsr_phase31_has($wpsr_duplicate_errors, 'duplicate or malformed cell id') && wpsr_phase31_has($wpsr_duplicate_errors, 'duplicate cell coordinates'), 'duplicate cell rejected');

    $wpsr_mutant = $wpsr_matrix;
    foreach ($wpsr_mutant['cells'] as &$wpsr_cell) {
        if (($wpsr_cell['purpose'] ?? '') === 'below-minimum-core-for-pro') {
            $wpsr_cell['expected']['pro_boot'] = true;
        }
    }
    unset($wpsr_cell);
    wpsr_phase31_record('expected-outcome-mismatch', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'incompatible expected outcome mismatch'), 'incompatible boot mismatch rejected');

    $wpsr_mutant = $wpsr_matrix;
    $wpsr_mutant['artifacts']['core-below-min']['ref'] = 'phase31-version-does-not-exist';
    wpsr_phase31_record('unavailable-version-silent', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'without explicit SKIP evidence'), 'unavailable ref without evidence rejected');

    $wpsr_mutant = $wpsr_matrix;
    $wpsr_mutant['historical_unavailable'][] = ['id' => 'missing-old-pair', 'version' => '2.0.0', 'evidence' => []];
    wpsr_phase31_record('malformed-unavailable-skip', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'silent/malformed SKIP evidence'), 'silent SKIP rejected');

    $wpsr_mutant = $wpsr_matrix;
    $wpsr_mutant['cells'][0]['expected']['rest_checks_min'] = 0;
    wpsr_phase31_record('zero-route-test-count', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'zero route/test expectation'), 'zero checks rejected');

    $wpsr_mutant = wpsr_phase31_remove_purpose($wpsr_matrix, 'declared-minimum-boundary-pair');
    wpsr_phase31_record('missing-boundary-pair', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'declared-minimum-boundary-pair'), 'minimum boundary omission rejected');

    $wpsr_mutant = wpsr_phase31_remove_purpose($wpsr_matrix, 'below-minimum-pro-for-core');
    wpsr_phase31_record('missing-dependency-direction', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'below-minimum-pro-for-core'), 'one-way incompatibility coverage rejected');

    foreach ([
        'scalar-dimension' => ['dimensions', 'current', '8.2', 'dimension current must be an object'],
        'empty-dimension' => ['dimensions', 'current', [], 'dimension current must be an object'],
        'missing-image-field' => ['dimensions', 'current', null, 'dimension current keys mismatch'],
        'foreign-repository' => ['repositories', 'core', 'github.com/foreign/project', 'repository canonical remote mismatch'],
        'wrong-branch' => ['repositories', 'core', 'wrong-branch', 'repository branch/ref identity mismatch'],
        'stale-pro-test-tree' => ['repositories', 'pro', ['tests/does-not-exist.php'], 'required test linkage path absent'],
    ] as $wpsr_name => $wpsr_case) {
        $wpsr_mutant = $wpsr_matrix;
        if ($wpsr_name === 'scalar-dimension' || $wpsr_name === 'empty-dimension') {
            $wpsr_mutant['dimensions']['current'] = $wpsr_case[2];
        } elseif ($wpsr_name === 'missing-image-field') {
            unset($wpsr_mutant['dimensions']['current']['cli_image']);
        } elseif ($wpsr_name === 'foreign-repository') {
            $wpsr_mutant['repositories']['core']['canonical'] = $wpsr_case[2];
        } elseif ($wpsr_name === 'wrong-branch') {
            $wpsr_mutant['repositories']['core']['current_branch'] = $wpsr_case[2];
        } else {
            $wpsr_mutant['repositories']['pro']['required_current_paths'] = $wpsr_case[2];
        }
        wpsr_phase31_record($wpsr_name, wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), $wpsr_case[3]), $wpsr_case[3]);
    }
    $wpsr_mutant = $wpsr_matrix;
    $wpsr_mutant['artifacts']['core-current']['kind'] = 'foreign-kind';
    wpsr_phase31_record('unknown-artifact-kind', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'artifact core-current keys mismatch'), 'unknown kind rejected');
    $wpsr_mutant = $wpsr_matrix;
    $wpsr_mutant['artifacts']['core-current']['ref'] = 'refs/heads/development';
    wpsr_phase31_record('wrong-current-ref', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'current artifact ref semantics mismatch'), 'wrong current ref rejected');
    $wpsr_mutant = $wpsr_matrix;
    $wpsr_mutant['unknown'] = true;
    wpsr_phase31_record('unknown-matrix-key', wpsr_phase31_has(wpsr_compatibility_validate($wpsr_mutant, $wpsr_root), 'matrix keys mismatch'), 'unknown root key rejected');

    list($wpsr_runtime_cell, $wpsr_runtime_event) = wpsr_phase31_valid_runtime($wpsr_matrix, $wpsr_root);
    $wpsr_runtime_line = wpsr_compatibility_runtime_marker() . json_encode($wpsr_runtime_event, JSON_UNESCAPED_SLASHES) . "\n";
    $wpsr_parsed = wpsr_compatibility_parse_runtime_transcript($wpsr_runtime_line);
    if ($wpsr_parsed['errors'] || wpsr_compatibility_validate_runtime_event($wpsr_parsed['event'], $wpsr_runtime_cell, 0)) {
        throw new RuntimeException('restored runtime parser fixture is not green');
    }
    $wpsr_restored++;
    foreach ([
        'runtime-missing-event' => ['', 'exactly one'],
        'runtime-malformed-json' => [wpsr_compatibility_runtime_marker() . '{broken}' . "\n", 'valid JSON object'],
        'runtime-null-json' => [wpsr_compatibility_runtime_marker() . 'null' . "\n", 'valid JSON object'],
        'runtime-duplicate-event' => [$wpsr_runtime_line . $wpsr_runtime_line, 'exactly one'],
    ] as $wpsr_name => $wpsr_case) {
        $wpsr_runtime_errors = wpsr_compatibility_parse_runtime_transcript($wpsr_case[0])['errors'];
        wpsr_phase31_record($wpsr_name, wpsr_phase31_has($wpsr_runtime_errors, $wpsr_case[1]), $wpsr_case[1]);
    }
    $wpsr_object_event = $wpsr_runtime_event;
    $wpsr_object_event['failures'] = new stdClass();
    $wpsr_object_event_errors = wpsr_compatibility_parse_runtime_transcript(
        wpsr_compatibility_runtime_marker() . json_encode($wpsr_object_event, JSON_UNESCAPED_SLASHES) . "\n"
    )['errors'];
    wpsr_phase31_record('runtime-object-list', wpsr_phase31_has($wpsr_object_event_errors, 'unambiguous nested shapes'),
        'runtime JSON object cannot masquerade as empty failures list');
    foreach ([
        'runtime-wrong-cell' => ['cell', 'foreign-cell', 'cell identity'],
        'runtime-wrong-purpose' => ['purpose', 'foreign-purpose', 'cell identity'],
        'runtime-false-success' => ['actual_compatibility', 'mismatch', 'actual/expected'],
        'runtime-nonempty-failures' => ['failures', ['hidden failure'], 'reported failures'],
    ] as $wpsr_name => $wpsr_case) {
        $wpsr_event_mutant = $wpsr_runtime_event;
        $wpsr_event_mutant[$wpsr_case[0]] = $wpsr_case[1];
        wpsr_phase31_record($wpsr_name, wpsr_phase31_has(wpsr_compatibility_validate_runtime_event($wpsr_event_mutant, $wpsr_runtime_cell, 0), $wpsr_case[2]), $wpsr_case[2]);
    }
    $wpsr_event_mutant = $wpsr_runtime_event;
    $wpsr_event_mutant['commits']['core'] = str_repeat('0', 40);
    wpsr_phase31_record('runtime-wrong-commit', wpsr_phase31_has(wpsr_compatibility_validate_runtime_event($wpsr_event_mutant, $wpsr_runtime_cell, 0), 'commits mismatch'), 'resolved commit mismatch rejected');
    $wpsr_event_mutant = $wpsr_runtime_event;
    $wpsr_event_mutant['images']['cli'] = 'sha256:' . str_repeat('9', 64);
    wpsr_phase31_record('runtime-wrong-image', wpsr_phase31_has(wpsr_compatibility_validate_runtime_event($wpsr_event_mutant, $wpsr_runtime_cell, 0), 'image digests mismatch'), 'resolved image mismatch rejected');
    $wpsr_event_mutant = $wpsr_runtime_event;
    $wpsr_event_mutant['versions']['php'] = '7.4.33';
    wpsr_phase31_record('runtime-wrong-dimension', wpsr_phase31_has(wpsr_compatibility_validate_runtime_event($wpsr_event_mutant, $wpsr_runtime_cell, 0), 'PHP dimension mismatch'), 'runtime dimension mismatch rejected');
    $wpsr_event_mutant = $wpsr_runtime_event;
    $wpsr_event_mutant['checks']['total']++;
    wpsr_phase31_record('runtime-check-reconciliation', wpsr_phase31_has(wpsr_compatibility_validate_runtime_event($wpsr_event_mutant, $wpsr_runtime_cell, 0), 'check counts'), 'runtime check drift rejected');
    wpsr_phase31_record('runtime-child-exit', wpsr_phase31_has(wpsr_compatibility_validate_runtime_event($wpsr_runtime_event, $wpsr_runtime_cell, 1), 'child exit'), 'nonzero child cannot pass');

    $wpsr_gate_log = $wpsr_temp . '/runtime-gate.log';
    $wpsr_gate_result = $wpsr_temp . '/runtime-result.json';
    $wpsr_gate_command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/validate-runtime-result.php')
        . ' --log=' . escapeshellarg($wpsr_gate_log)
        . ' --cell-base64=' . escapeshellarg(base64_encode(json_encode($wpsr_runtime_cell, JSON_UNESCAPED_SLASHES)))
        . ' --child-exit=0 --runtime-seconds=1'
        . ' --core-fixture-hash=' . str_repeat('a', 64)
        . ' --pro-fixture-hash=' . escapeshellarg('')
        . ' --output=' . escapeshellarg($wpsr_gate_result) . ' >/dev/null 2>&1';
    file_put_contents($wpsr_gate_log, $wpsr_runtime_line . $wpsr_runtime_line);
    exec($wpsr_gate_command, $wpsr_gate_output, $wpsr_gate_code);
    wpsr_phase31_record('runtime-gate-no-false-artifact', $wpsr_gate_code === 1 && !is_file($wpsr_gate_result), 'duplicate event fails before artifact write');
    file_put_contents($wpsr_gate_log, $wpsr_runtime_line);
    exec($wpsr_gate_command, $wpsr_gate_output, $wpsr_gate_code);
    if ($wpsr_gate_code !== 0 || !is_file($wpsr_gate_result)) {
        throw new RuntimeException('restored runtime gate did not write validated artifact');
    }
    $wpsr_restored++;

    $wpsr_result_row = json_decode((string) file_get_contents($wpsr_gate_result), true);
    $wpsr_summary_path = $wpsr_temp . '/summary.json';
    $wpsr_matrix_path = __DIR__ . '/matrix.json';
    $wpsr_summary = [
        'schema' => 1,
        'matrix' => [
            'family' => $wpsr_matrix['family'],
            'source_sha256' => hash_file('sha256', $wpsr_matrix_path),
            'repositories' => [
                'core' => wpsr_compatibility_git($wpsr_root, ['rev-parse', 'HEAD'], $wpsr_core_code),
                'pro' => wpsr_compatibility_git($wpsr_pro_root, ['rev-parse', 'HEAD'], $wpsr_pro_code),
            ],
        ],
        'selector' => $wpsr_runtime_cell['id'],
        'selected_cells' => [$wpsr_runtime_cell['id']],
        'historical_skip_ids' => array_values(array_map(static function ($wpsr_skip) {
            return $wpsr_skip['id'];
        }, $wpsr_matrix['historical_unavailable'])),
        'cells' => 1 + count($wpsr_matrix['historical_unavailable']),
        'pass' => 1,
        'expected_incompatible' => 0,
        'fail' => 0,
        'skip' => count($wpsr_matrix['historical_unavailable']),
        'runtime_seconds' => 1.0,
        'results' => [$wpsr_result_row],
    ];
    [$wpsr_summary_code, $wpsr_summary_output] = wpsr_phase31_summary_gate($wpsr_summary, $wpsr_summary_path, $wpsr_matrix_path);
    if ($wpsr_summary_code !== 0) {
        throw new RuntimeException('restored compatibility summary is not green: ' . $wpsr_summary_output);
    }
    $wpsr_restored++;
    foreach ([
        'summary-omitted-selection' => ['selector', 'all', 'selected cell identity'],
        'summary-wrong-incompatible-count' => ['expected_incompatible', 1, 'expected-incompatible count'],
        'summary-foreign-historical-skip' => ['historical_skip_ids', ['foreign-skip'], 'historical SKIP identity'],
        'summary-object-list' => ['historical_skip_ids', new stdClass(), 'unambiguous nested shapes'],
        'summary-scalar-count' => ['pass', ['not-a-count'], 'count malformed: pass'],
    ] as $wpsr_name => $wpsr_case) {
        $wpsr_summary_mutant = $wpsr_summary;
        $wpsr_summary_mutant[$wpsr_case[0]] = $wpsr_case[1];
        [$wpsr_summary_code, $wpsr_summary_output] = wpsr_phase31_summary_gate($wpsr_summary_mutant, $wpsr_summary_path, $wpsr_matrix_path);
        wpsr_phase31_record($wpsr_name, $wpsr_summary_code === 1 && strpos($wpsr_summary_output, $wpsr_case[2]) !== false, $wpsr_case[2] . ' rejected');
    }
    $wpsr_summary_mutant = $wpsr_summary;
    $wpsr_summary_mutant['results'][] = $wpsr_result_row;
    $wpsr_summary_mutant['pass'] = 2;
    $wpsr_summary_mutant['cells'] = 2 + count($wpsr_matrix['historical_unavailable']);
    [$wpsr_summary_code, $wpsr_summary_output] = wpsr_phase31_summary_gate($wpsr_summary_mutant, $wpsr_summary_path, $wpsr_matrix_path);
    wpsr_phase31_record('summary-duplicate-result', $wpsr_summary_code === 1 && strpos($wpsr_summary_output, 'result identity/status malformed') !== false, 'duplicate result identity rejected');

    $wpsr_fixture = $wpsr_temp . '/production-copy';
    mkdir($wpsr_fixture, 0700, true);
    copy($wpsr_root . '/wp-social-reviews.php', $wpsr_fixture . '/wp-social-reviews.php');
    $wpsr_before = wpsr_phase31_tree_hash($wpsr_fixture);
    file_put_contents($wpsr_fixture . '/phase31-residue.tmp', "residue\n");
    $wpsr_after = wpsr_phase31_tree_hash($wpsr_fixture);
    wpsr_phase31_record('production-mutation-residue', $wpsr_before !== $wpsr_after, 'hash/status guard detects derived residue');
    unlink($wpsr_fixture . '/phase31-residue.tmp');
    if (wpsr_phase31_tree_hash($wpsr_fixture) !== $wpsr_before) {
        throw new RuntimeException('derived residue fixture did not restore');
    }
    $wpsr_restored++;
} catch (Throwable $wpsr_exception) {
    $wpsr_failures++;
    fwrite(STDERR, 'COMPATIBILITY-PROOF bootstrap failure: ' . $wpsr_exception->getMessage() . "\n");
} finally {
    exec('rm -rf ' . escapeshellarg($wpsr_temp));
    if (shell_exec('git -C ' . escapeshellarg($wpsr_root) . ' status --porcelain=v1 -z --untracked-files=all') !== $wpsr_core_status
        || shell_exec('git -C ' . escapeshellarg($wpsr_pro_root) . ' status --porcelain=v1 -z --untracked-files=all') !== $wpsr_pro_status
        || hash_file('sha256', $wpsr_root . '/wp-social-reviews.php') !== $wpsr_core_boot_hash
        || hash_file('sha256', $wpsr_pro_root . '/wp-social-ninja-pro.php') !== $wpsr_pro_boot_hash) {
        $wpsr_failures++;
        fwrite(STDERR, "COMPATIBILITY-PROOF production status/hash drifted.\n");
    }
}
printf("COMPATIBILITY-PROOF cases=%d red=%d restored=%d failures=%d\n", $wpsr_cases, $wpsr_red, $wpsr_restored, $wpsr_failures);
exit($wpsr_failures ? WPSR_COMPATIBILITY_FAIL : WPSR_COMPATIBILITY_PASS);
