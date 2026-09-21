<?php
/** Phase 29 derived proof-of-catch: link/metadata/files/activation/runtime assertion. */
require_once __DIR__ . '/test-tree-lib.php';
$wpsr_root = dirname(__DIR__, 2);
$wpsr_pro_root = dirname($wpsr_root) . '/wp-social-ninja-pro';
$wpsr_validator = $wpsr_root . '/tests/family/test-trees.php';
$wpsr_temp = sys_get_temp_dir() . '/wpsr-addon-tree-' . bin2hex(random_bytes(6));
$wpsr_artifact = $wpsr_root . '/tests/.artifacts/phase29-proof-' . bin2hex(random_bytes(6));
$wpsr_core_status = shell_exec('git -C ' . escapeshellarg($wpsr_root) . ' status --porcelain=v1 --untracked-files=all');
$wpsr_pro_status = shell_exec('git -C ' . escapeshellarg($wpsr_pro_root) . ' status --porcelain=v1 --untracked-files=all');
$wpsr_cases = 0;
$wpsr_red = 0;
$wpsr_restored = 0;
$wpsr_failures = 0;

function wpsr_tree_proof_copy($wpsr_source, $wpsr_target)
{
    if (is_dir($wpsr_source)) {
        if (!is_dir($wpsr_target) && !mkdir($wpsr_target, 0755, true)) {
            throw new RuntimeException('Could not create derived directory ' . $wpsr_target);
        }
        foreach (scandir($wpsr_source) ?: [] as $wpsr_name) {
            if ($wpsr_name === '.' || $wpsr_name === '..') {
                continue;
            }
            wpsr_tree_proof_copy($wpsr_source . '/' . $wpsr_name, $wpsr_target . '/' . $wpsr_name);
        }
        return;
    }
    if (!copy($wpsr_source, $wpsr_target)) {
        throw new RuntimeException('Could not copy derived file ' . $wpsr_source);
    }
}

function wpsr_tree_proof_run($wpsr_command, $wpsr_cwd)
{
    $wpsr_lines = [];
    exec('cd ' . escapeshellarg($wpsr_cwd) . ' && ' . $wpsr_command . ' 2>&1', $wpsr_lines, $wpsr_code);
    return [$wpsr_code, implode("\n", $wpsr_lines)];
}

function wpsr_tree_proof_record($wpsr_name, $wpsr_ok, $wpsr_detail)
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

function wpsr_tree_proof_fixture($wpsr_temp, $wpsr_name, $wpsr_pro_root)
{
    $wpsr_base = $wpsr_temp . '/' . $wpsr_name;
    $wpsr_plugin = $wpsr_base . '/wp-social-ninja-pro';
    mkdir($wpsr_plugin, 0700, true);
    copy($wpsr_pro_root . '/wp-social-ninja-pro.php', $wpsr_plugin . '/wp-social-ninja-pro.php');
    wpsr_tree_proof_copy($wpsr_pro_root . '/app', $wpsr_plugin . '/app');
    wpsr_tree_proof_copy($wpsr_pro_root . '/tests', $wpsr_plugin . '/tests');
    foreach ([
        'git init -q -b development',
        'git config user.email wpsr-tree@example.test',
        'git config user.name "WPSR Tree Fixture"',
        'git remote add origin https://github.com/WPManageNinja/wp-social-ninja-pro.git',
        'git add .',
        'git commit -q -m fixture',
    ] as $wpsr_command) {
        exec('cd ' . escapeshellarg($wpsr_plugin) . ' && ' . $wpsr_command, $wpsr_output, $wpsr_code);
        if ($wpsr_code !== 0) {
            throw new RuntimeException('Could not initialize derived Pro commit fixture.');
        }
        $wpsr_output = [];
    }
    symlink($wpsr_plugin . '/tests', $wpsr_base . '/link');
    return [$wpsr_plugin, $wpsr_base . '/link'];
}

function wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link, $wpsr_extra = '')
{
    return 'php ' . escapeshellarg($wpsr_validator)
        . ' --plugin-root=' . escapeshellarg($wpsr_plugin)
        . ' --link=' . escapeshellarg($wpsr_link)
        . ($wpsr_extra !== '' ? ' ' . $wpsr_extra : '');
}

try {
    if (!mkdir($wpsr_temp, 0700, true) || !mkdir($wpsr_artifact, 0777, true)) {
        throw new RuntimeException('Could not create Phase 29 proof workspaces.');
    }
    wpsr_tree_proof_record('string-list-object-member', !wpsr_test_tree_unique_string_list(['valid', ['not-a-string']]), 'nonscalar list member rejected directly');
    $wpsr_list_root = $wpsr_temp . '/list-root.json';
    file_put_contents($wpsr_list_root, "[]\n");
    $wpsr_load_errors = [];
    wpsr_test_tree_load_object($wpsr_list_root, 'derived list root', $wpsr_load_errors);
    wpsr_tree_proof_record('direct-list-root', in_array('derived list root malformed or root is not an object', $wpsr_load_errors, true), 'JSON list root rejected directly');
    wpsr_tree_proof_record('direct-owned-path-traversal', !wpsr_test_tree_owned_file($wpsr_pro_root . '/tests', '../wp-social-ninja-pro.php'), 'path traversal rejected directly');
    wpsr_tree_proof_record('direct-route-module-invalid', wpsr_test_tree_route_module('/foreign/v1/pro/settings') === '', 'foreign namespace rejected directly');
    $wpsr_direct_source_errors = [];
    $wpsr_direct_source_rows = wpsr_test_tree_source_route_rows($wpsr_root, $wpsr_pro_root, 'pro', $wpsr_direct_source_errors);
    $wpsr_direct_inventory_rows = wpsr_test_tree_inventory_rows($wpsr_root, 'pro');
    if ($wpsr_direct_source_errors || count($wpsr_direct_source_rows) !== 33 || $wpsr_direct_source_rows !== $wpsr_direct_inventory_rows) {
        throw new RuntimeException('Direct source/inventory helper baseline is not exact.');
    }
    $wpsr_restored++;

    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run('php ' . escapeshellarg($wpsr_validator), $wpsr_root);
    if ($wpsr_code !== 0 || strpos($wpsr_output, 'failures=0') === false) {
        throw new RuntimeException('Live add-on test-tree baseline is not green.');
    }
    $wpsr_restored++;

    list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, 'broken-link', $wpsr_pro_root);
    unlink($wpsr_link);
    symlink($wpsr_plugin . '/missing-tests', $wpsr_link);
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
    wpsr_tree_proof_record('broken-link', $wpsr_code === 1 && strpos($wpsr_output, 'link target disagrees') !== false, 'exit=' . $wpsr_code);

    list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, 'wrong-tree-ownership', $wpsr_pro_root);
    $wpsr_family_path = $wpsr_temp . '/wrong-tree-ownership-family.json';
    $wpsr_family = json_decode(file_get_contents($wpsr_root . '/tests/family/plugins.json'), true);
    foreach ($wpsr_family['plugins'] as &$wpsr_family_plugin) {
        if (($wpsr_family_plugin['id'] ?? '') === 'pro') {
            $wpsr_family_plugin['test_tree']['host_link'] = 'tests/addons/foreign';
        }
    }
    unset($wpsr_family_plugin);
    file_put_contents($wpsr_family_path, json_encode($wpsr_family, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $wpsr_extra = '--family=' . escapeshellarg($wpsr_family_path);
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link, $wpsr_extra), $wpsr_root);
    wpsr_tree_proof_record('wrong-tree-ownership', $wpsr_code === 1 && strpos($wpsr_output, 'ownership path/link/metadata mismatch') !== false, 'registry ownership drift rejected exit=' . $wpsr_code);

    foreach ([
        'metadata-id' => ['plugin', 'id', 'wrong-pro', 'plugin.id mismatch'],
        'metadata-version' => ['plugin', 'version', '9.9.9', 'plugin.version mismatch'],
        'metadata-order' => [null, 'activation_order', 10, 'activation_order mismatch'],
    ] as $wpsr_name => $wpsr_mutation) {
        list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, $wpsr_name, $wpsr_pro_root);
        $wpsr_path = $wpsr_plugin . '/tests/addon.json';
        $wpsr_json = json_decode(file_get_contents($wpsr_path), true);
        if ($wpsr_mutation[0] === null) {
            $wpsr_json[$wpsr_mutation[1]] = $wpsr_mutation[2];
        } else {
            $wpsr_json[$wpsr_mutation[0]][$wpsr_mutation[1]] = $wpsr_mutation[2];
        }
        file_put_contents($wpsr_path, json_encode($wpsr_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
        wpsr_tree_proof_record($wpsr_name, $wpsr_code === 1 && strpos($wpsr_output, $wpsr_mutation[3]) !== false, 'exit=' . $wpsr_code);
    }

    foreach ([
        'metadata-unknown-key' => static function (&$wpsr_json) { $wpsr_json['foreign'] = true; },
        'metadata-suites-object' => static function (&$wpsr_json) { $wpsr_json['suites'] = ['boot' => $wpsr_json['suites'][0]]; },
        'metadata-duplicate-suite-id' => static function (&$wpsr_json) { $wpsr_json['suites'][1]['id'] = $wpsr_json['suites'][0]['id']; },
        'metadata-duplicate-file' => static function (&$wpsr_json) { $wpsr_json['suites'][1]['files'][0] = $wpsr_json['suites'][0]['files'][0]; },
        'host-harness-missing' => static function (&$wpsr_json) { unset($wpsr_json['host_harness']); },
        'host-harness-wrong-pr' => static function (&$wpsr_json) { $wpsr_json['host_harness']['pull_request'] = 999; },
        'host-harness-wrong-branch' => static function (&$wpsr_json) { $wpsr_json['host_harness']['branch'] = 'development'; },
        'host-harness-wrong-minimum' => static function (&$wpsr_json) { $wpsr_json['host_harness']['minimum_commit'] = str_repeat('f', 40); },
        'host-harness-capability-drift' => static function (&$wpsr_json) { array_pop($wpsr_json['host_harness']['capabilities']); },
    ] as $wpsr_name => $wpsr_mutate) {
        list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, $wpsr_name, $wpsr_pro_root);
        $wpsr_path = $wpsr_plugin . '/tests/addon.json';
        $wpsr_json = json_decode(file_get_contents($wpsr_path), true);
        $wpsr_mutate($wpsr_json);
        file_put_contents($wpsr_path, json_encode($wpsr_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
        wpsr_tree_proof_record($wpsr_name, $wpsr_code === 1, 'strict add-on metadata mutation exit=' . $wpsr_code);
    }

    foreach ([
        'route-unknown-key' => static function (&$wpsr_json) { $wpsr_json['declarations'][0]['foreign'] = true; },
        'route-missing-id' => static function (&$wpsr_json) { unset($wpsr_json['declarations'][0]['id']); },
        'route-duplicate-id' => static function (&$wpsr_json) { $wpsr_json['declarations'][1]['id'] = $wpsr_json['declarations'][0]['id']; },
        'route-module-drift' => static function (&$wpsr_json) { $wpsr_json['declarations'][0]['module'] = 'settings'; },
        'route-declarations-object' => static function (&$wpsr_json) { $wpsr_json['declarations'] = ['row' => $wpsr_json['declarations'][0]]; },
    ] as $wpsr_name => $wpsr_mutate) {
        list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, $wpsr_name, $wpsr_pro_root);
        $wpsr_path = $wpsr_plugin . '/tests/routes.json';
        $wpsr_json = json_decode(file_get_contents($wpsr_path), true);
        $wpsr_mutate($wpsr_json);
        file_put_contents($wpsr_path, json_encode($wpsr_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
        wpsr_tree_proof_record($wpsr_name, $wpsr_code === 1, 'strict REST manifest mutation exit=' . $wpsr_code);
    }

    list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, 'route-list-root', $wpsr_pro_root);
    file_put_contents($wpsr_plugin . '/tests/routes.json', "[]\n");
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
    wpsr_tree_proof_record('route-list-root', $wpsr_code === 1 && strpos($wpsr_output, 'root is not an object') !== false, 'JSON list root rejected exit=' . $wpsr_code);

    list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, 'readme-host-path-drift', $wpsr_pro_root);
    $wpsr_path = $wpsr_plugin . '/tests/README.md';
    $wpsr_source = str_replace('Core PR #325', 'Core PR #999', file_get_contents($wpsr_path), $wpsr_replacements);
    if ($wpsr_replacements !== 1) {
        throw new RuntimeException('README host-path mutation did not replace exactly one contract reference.');
    }
    file_put_contents($wpsr_path, $wpsr_source);
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
    wpsr_tree_proof_record('readme-host-path-drift', $wpsr_code === 1 && strpos($wpsr_output, 'README host execution contract') !== false, 'documented host path drift rejected exit=' . $wpsr_code);

    foreach ([
        'source-callback-drift' => ["[ManagersController::class, 'getManagers']", "[ManagersController::class, 'updateManagers']"],
        'source-policy-drift' => ["withPolicy('WPSocialReviews\\App\\Http\\Policies\\SettingsPolicy')", "withPolicy('WPSocialReviews\\App\\Http\\Policies\\ReviewsPlatformsSettingsPolicy')"],
    ] as $wpsr_name => $wpsr_replacement) {
        list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, $wpsr_name, $wpsr_pro_root);
        $wpsr_path = $wpsr_plugin . '/app/Http/Routes/api.php';
        $wpsr_source = file_get_contents($wpsr_path);
        $wpsr_source = str_replace($wpsr_replacement[0], $wpsr_replacement[1], $wpsr_source, $wpsr_replacements);
        if ($wpsr_replacements !== 1) {
            throw new RuntimeException($wpsr_name . ' did not mutate exactly one route source expression.');
        }
        file_put_contents($wpsr_path, $wpsr_source);
        list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
        wpsr_tree_proof_record($wpsr_name, $wpsr_code === 1 && strpos($wpsr_output, 'direct production route discovery') !== false, 'source mapping drift rejected exit=' . $wpsr_code);
    }

    list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, 'source-unmanifested-route', $wpsr_pro_root);
    file_put_contents($wpsr_plugin . '/app/Http/Routes/api.php', "\n\$router->prefix('pro/settings')->withPolicy('WPSocialReviews\\App\\Http\\Policies\\SettingsPolicy')->group(function (\$router) {\n    \$router->get('/phase29-proof', [ManagersController::class, 'getManagers']);\n});\n", FILE_APPEND);
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
    wpsr_tree_proof_record('source-unmanifested-route', $wpsr_code === 1 && strpos($wpsr_output, 'direct production route discovery') !== false, 'unmanifested source route rejected exit=' . $wpsr_code);

    list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, 'source-route-decoys', $wpsr_pro_root);
    file_put_contents($wpsr_plugin . '/app/Http/Routes/api.php', "\n// \$router->get('/comment-decoy', [ManagersController::class, 'getManagers']);\n\$wpsr_route_decoy = \"\$router->post('/string-decoy', [ManagersController::class, 'addManagers']);\";\n", FILE_APPEND);
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
    if ($wpsr_code !== 0 || strpos($wpsr_output, 'failures=0') === false) {
        throw new RuntimeException('comment/string route decoys changed direct source census: ' . $wpsr_output);
    }
    $wpsr_restored++;

    list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, 'outside-file-symlink', $wpsr_pro_root);
    unlink($wpsr_plugin . '/tests/cases/database.php');
    symlink($wpsr_plugin . '/app/Http/Routes/api.php', $wpsr_plugin . '/tests/cases/database.php');
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
    wpsr_tree_proof_record('outside-file-symlink', $wpsr_code === 1 && strpos($wpsr_output, 'symlinked, or outside') !== false, 'outside symlink rejected exit=' . $wpsr_code);

    list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, 'stale-commit-tree', $wpsr_pro_root);
    file_put_contents($wpsr_plugin . '/tests/cases/boot.php', "\n// stale derived change\n", FILE_APPEND);
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
    wpsr_tree_proof_record('stale-pro-test-tree', $wpsr_code === 1 && strpos($wpsr_output, 'stale Pro test tree') !== false, 'exit=' . $wpsr_code);

    list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, 'omitted-file', $wpsr_pro_root);
    unlink($wpsr_plugin . '/tests/cases/database.php');
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link), $wpsr_root);
    wpsr_tree_proof_record('omitted-pro-test-file', $wpsr_code === 1 && strpos($wpsr_output, 'invalid, missing, symlinked, or outside') !== false, 'exit=' . $wpsr_code);

    foreach ([
        'activation-absent' => ['wp-social-ninja/wp-social-reviews.php'],
        'activation-order' => ['wp-social-ninja-pro/wp-social-ninja-pro.php', 'wp-social-ninja/wp-social-reviews.php'],
    ] as $wpsr_name => $wpsr_activation) {
        list($wpsr_plugin, $wpsr_link) = wpsr_tree_proof_fixture($wpsr_temp, $wpsr_name, $wpsr_pro_root);
        $wpsr_extra = '--activation-json=' . escapeshellarg(json_encode($wpsr_activation));
        list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run(wpsr_tree_proof_validator_command($wpsr_validator, $wpsr_plugin, $wpsr_link, $wpsr_extra), $wpsr_root);
        $wpsr_expected = $wpsr_name === 'activation-absent' ? 'activation missing' : 'activation order is wrong';
        wpsr_tree_proof_record($wpsr_name, $wpsr_code === 1 && strpos($wpsr_output, $wpsr_expected) !== false, 'exit=' . $wpsr_code);
    }

    wpsr_tree_proof_copy($wpsr_pro_root . '/tests', $wpsr_artifact . '/tests');
    $wpsr_runtime_mutations = [
        'boot.php' => [
            "WpsrTest::assertSame('4.4.0', WPSOCIALREVIEWS_PRO_VERSION, 'Pro runtime version');",
            "WpsrTest::assertSame('9.9.9', WPSOCIALREVIEWS_PRO_VERSION, 'Pro runtime version');",
        ],
        'public-surfaces.php' => [
            "WpsrTest::assertSame('', \$wpsr_invalid, 'missing form ID output');",
            "WpsrTest::assertSame('unexpected', \$wpsr_invalid, 'missing form ID output');",
        ],
        'routes.php' => [
            "WpsrTest::assertSame(401, \$wpsr_result['status'], 'anonymous denial status');",
            "WpsrTest::assertSame(418, \$wpsr_result['status'], 'anonymous denial status');",
        ],
        'database.php' => [
            '$wpsr_expected[] = (string) $wpsr_marker_id;',
            '$wpsr_expected[] = (string) ($wpsr_marker_id + 1);',
        ],
    ];
    foreach ($wpsr_runtime_mutations as $wpsr_file => $wpsr_replacement) {
        $wpsr_mutant = $wpsr_artifact . '/tests/cases/' . $wpsr_file;
        $wpsr_source = file_get_contents($wpsr_mutant);
        $wpsr_changed = str_replace($wpsr_replacement[0], $wpsr_replacement[1], $wpsr_source, $wpsr_replacements);
        if ($wpsr_replacements !== 1) {
            throw new RuntimeException('Meaningful Pro ' . $wpsr_file . ' mutant did not change exactly one derived line.');
        }
        file_put_contents($wpsr_mutant, $wpsr_changed);
    }
    $wpsr_container_tree = '/workspace/wp-social-ninja/tests/.artifacts/' . basename($wpsr_artifact) . '/tests';
    $wpsr_runtime_command = 'WPSR_PHASE29_PROOF=1 bash tests/lab/scripts/php-tests.sh --env pro tests/bin/run-addon.php plugin=pro tree='
        . escapeshellarg($wpsr_container_tree);
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run($wpsr_runtime_command, $wpsr_root);
    foreach ([
        'meaningful-pro-boot-assertion' => 'Pro runtime version',
        'meaningful-pro-public-assertion' => 'missing form ID output',
        'meaningful-pro-route-assertion' => 'anonymous denial status',
        'meaningful-pro-db-assertion' => 'native-form IDs from real post/postmeta query',
    ] as $wpsr_name => $wpsr_expected_output) {
        wpsr_tree_proof_record(
            $wpsr_name,
            $wpsr_code === 1 && strpos($wpsr_output, $wpsr_expected_output) !== false,
            'combined runtime mutant exit=' . $wpsr_code
        );
    }
    if ($wpsr_code !== 1) {
        fwrite(STDERR, "ADDON-TEST-TREE-PROOF mutant output:\n" . $wpsr_output . "\n");
    }

    exec('rm -rf ' . escapeshellarg($wpsr_artifact . '/tests'));
    wpsr_tree_proof_copy($wpsr_pro_root . '/tests', $wpsr_artifact . '/tests');
    $wpsr_runtime_metadata_path = $wpsr_artifact . '/tests/addon.json';
    $wpsr_runtime_metadata = json_decode(file_get_contents($wpsr_runtime_metadata_path), true);
    $wpsr_runtime_metadata['host_harness']['expected_tests'] = 40;
    file_put_contents($wpsr_runtime_metadata_path, json_encode($wpsr_runtime_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run($wpsr_runtime_command, $wpsr_root);
    wpsr_tree_proof_record(
        'runtime-full-suite-count-contract',
        $wpsr_code === 1 && strpos($wpsr_output, 'full-suite count mismatch: expected 40, executed 41') !== false,
        'runtime metadata mutant exit=' . $wpsr_code
    );

    copy($wpsr_pro_root . '/tests/addon.json', $wpsr_runtime_metadata_path);
    list($wpsr_code, $wpsr_output) = wpsr_tree_proof_run($wpsr_runtime_command, $wpsr_root);
    if ($wpsr_code !== 0 || strpos($wpsr_output, 'PRO-ADDON: 41/41 passed, 0 failed') === false) {
        throw new RuntimeException("Restored derived Pro add-on assertions did not return exact 41/41 green. Output:\n" . $wpsr_output);
    }
    $wpsr_restored++;
} catch (Throwable $wpsr_exception) {
    $wpsr_failures++;
    fwrite(STDERR, 'ADDON-TEST-TREE-PROOF bootstrap failure: ' . $wpsr_exception->getMessage() . "\n");
} finally {
    exec('rm -rf ' . escapeshellarg($wpsr_temp));
    exec('rm -rf ' . escapeshellarg($wpsr_artifact));
    $wpsr_core_after = shell_exec('git -C ' . escapeshellarg($wpsr_root) . ' status --porcelain=v1 --untracked-files=all');
    $wpsr_pro_after = shell_exec('git -C ' . escapeshellarg($wpsr_pro_root) . ' status --porcelain=v1 --untracked-files=all');
    if ($wpsr_core_after !== $wpsr_core_status || $wpsr_pro_after !== $wpsr_pro_status) {
        $wpsr_failures++;
        fwrite(STDERR, "ADDON-TEST-TREE-PROOF repository status drifted.\n");
    }
}
printf(
    "ADDON-TEST-TREE-PROOF cases=%d red=%d restored=%d failures=%d\n",
    $wpsr_cases,
    $wpsr_red,
    $wpsr_restored,
    $wpsr_failures
);
exit($wpsr_failures ? 1 : 0);
