<?php
/** Phase 28 derived product-family census proof. Production source is never mutated. */
require_once __DIR__ . '/census-lib.php';

$wpsr_root = dirname(__DIR__, 2);
$wpsr_pro_root = dirname($wpsr_root) . '/wp-social-ninja-pro';
$wpsr_lint = __DIR__ . '/census.php';
$wpsr_live_registry = __DIR__ . '/plugins.json';
$wpsr_workspace = sys_get_temp_dir() . '/wpsr-family-census-' . getmypid() . '-' . bin2hex(random_bytes(4));
$wpsr_failures = [];
$wpsr_red = 0;
$wpsr_skip = 0;
$wpsr_cases = 0;
$wpsr_restored = 0;
$wpsr_initial_core_status = shell_exec('git -C ' . escapeshellarg($wpsr_root) . ' status --porcelain=v1 -z --untracked-files=all');
$wpsr_initial_pro_status = shell_exec('git -C ' . escapeshellarg($wpsr_pro_root) . ' status --porcelain=v1 -z --untracked-files=all');

function wpsr_family_proof_write($wpsr_path, $wpsr_content)
{
    $wpsr_directory = dirname($wpsr_path);
    if (!is_dir($wpsr_directory) && !mkdir($wpsr_directory, 0777, true) && !is_dir($wpsr_directory)) {
        throw new RuntimeException('could not create ' . $wpsr_directory);
    }
    if (file_put_contents($wpsr_path, $wpsr_content) === false) {
        throw new RuntimeException('could not write ' . $wpsr_path);
    }
}

function wpsr_family_proof_remove($wpsr_path)
{
    if (!file_exists($wpsr_path) && !is_link($wpsr_path)) {
        return;
    }
    if (is_dir($wpsr_path) && !is_link($wpsr_path)) {
        foreach (scandir($wpsr_path) ?: [] as $wpsr_name) {
            if ($wpsr_name !== '.' && $wpsr_name !== '..') {
                wpsr_family_proof_remove($wpsr_path . '/' . $wpsr_name);
            }
        }
        rmdir($wpsr_path);
        return;
    }
    unlink($wpsr_path);
}

function wpsr_family_proof_git_init($wpsr_path)
{
    $wpsr_is_pro = basename($wpsr_path) === 'wp-social-ninja-pro';
    $wpsr_branch = $wpsr_is_pro ? 'development' : 'development';
    $wpsr_remote = $wpsr_is_pro
        ? 'https://github.com/WPManageNinja/wp-social-ninja-pro.git'
        : 'https://github.com/WPManageNinja/wp-social-ninja.git';
    $wpsr_commands = [
        'git init -q -b ' . escapeshellarg($wpsr_branch),
        'git config user.email wpsr-family@example.test',
        'git config user.name "WPSR Family Fixture"',
        'git remote add origin ' . escapeshellarg($wpsr_remote),
        'git add .',
        'git commit -q -m fixture',
    ];
    foreach ($wpsr_commands as $wpsr_command) {
        exec('cd ' . escapeshellarg($wpsr_path) . ' && ' . $wpsr_command . ' 2>&1', $wpsr_output, $wpsr_code);
        if ($wpsr_code !== 0) {
            throw new RuntimeException('fixture git command failed: ' . $wpsr_command . ' ' . implode("\n", $wpsr_output));
        }
        unset($wpsr_output);
    }
}

function wpsr_family_proof_fixture($wpsr_workspace, $wpsr_live_registry, $wpsr_name)
{
    $wpsr_family_root = $wpsr_workspace . '/' . $wpsr_name;
    $wpsr_core = $wpsr_family_root . '/wp-social-reviews';
    $wpsr_pro = $wpsr_family_root . '/wp-social-ninja-pro';
    mkdir($wpsr_core . '/app/Models', 0777, true);
    mkdir($wpsr_core . '/boot', 0777, true);
    mkdir($wpsr_core . '/tests', 0777, true);
    mkdir($wpsr_pro . '/app', 0777, true);
    mkdir($wpsr_pro . '/tests', 0777, true);

    wpsr_family_proof_write($wpsr_core . '/wp-social-reviews.php', <<<'PHP'
<?php
/*
Plugin Name: WP Social Ninja
Version: 4.4.0
Text Domain: wp-social-reviews
*/
define('WPSOCIALREVIEWS_PRO_MIN_VERSION', '4.4.0');
PHP
    );
    wpsr_family_proof_write($wpsr_core . '/app/Models/Review.php', "<?php\nnamespace WPSocialReviews\\App\\Models;\nclass Review {}\n");
    wpsr_family_proof_write($wpsr_core . '/boot/app.php', "<?php\ndo_action('wp_social_reviews_loaded_v2');\n");
    wpsr_family_proof_write($wpsr_core . '/readme.txt', "Requires at least: 6.2\nRequires PHP: 7.4\n");
    wpsr_family_proof_write($wpsr_core . '/tests/.fixture', "hosted\n");

    wpsr_family_proof_write($wpsr_pro . '/wp-social-ninja-pro.php', <<<'PHP'
<?php
/*
Plugin Name: WP Social Ninja Pro
Version: 4.4.0
Text Domain: wp-social-ninja-pro
*/
define('WPSOCIALREVIEWS_CORE_MIN_VERSION', '4.4.0');
add_action('wp_social_reviews_loaded_v2', function () {});
PHP
    );
    wpsr_family_proof_write($wpsr_pro . '/app/Application.php', "<?php\nnamespace WPSocialReviewsPro\\App;\nclass Application {}\n");
    wpsr_family_proof_write($wpsr_pro . '/readme.txt', "Requires at least: 6.2\nRequires PHP: 7.4\n");
    wpsr_family_proof_write($wpsr_pro . '/tests/.fixture', "linked\n");

    wpsr_family_proof_git_init($wpsr_core);
    wpsr_family_proof_git_init($wpsr_pro);

    $wpsr_registry = json_decode(file_get_contents($wpsr_live_registry), true);
    $wpsr_registry_path = $wpsr_family_root . '/plugins.json';
    wpsr_family_proof_write($wpsr_registry_path, json_encode($wpsr_registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return [$wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry];
}

function wpsr_family_proof_save_registry($wpsr_path, array $wpsr_registry)
{
    file_put_contents($wpsr_path, json_encode($wpsr_registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function wpsr_family_proof_run($wpsr_lint, $wpsr_core, $wpsr_family_root, $wpsr_registry)
{
    $wpsr_command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($wpsr_lint)
        . ' --registry=' . escapeshellarg($wpsr_registry)
        . ' --host-root=' . escapeshellarg($wpsr_core)
        . ' --family-root=' . escapeshellarg($wpsr_family_root)
        . ' --ignore-env 2>&1';
    exec($wpsr_command, $wpsr_output, $wpsr_code);
    return [$wpsr_code, implode("\n", $wpsr_output)];
}

function wpsr_family_proof_red($wpsr_name, callable $wpsr_mutate, $wpsr_expected, $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, array &$wpsr_failures)
{
    global $wpsr_red, $wpsr_cases;
    $wpsr_cases++;
    [$wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry] = wpsr_family_proof_fixture($wpsr_workspace, $wpsr_live_registry, $wpsr_name);
    $wpsr_mutate($wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry);
    [$wpsr_code, $wpsr_output] = wpsr_family_proof_run($wpsr_lint, $wpsr_core, $wpsr_family_root, $wpsr_registry_path);
    $wpsr_ok = $wpsr_code === 1 && strpos($wpsr_output, $wpsr_expected) !== false;
    echo 'PROOF ' . $wpsr_name . ': ' . ($wpsr_ok ? 'RED expected' : 'FAIL') . ' exit=' . $wpsr_code . "\n";
    if ($wpsr_ok) {
        $wpsr_red++;
    } else {
        $wpsr_failures[] = $wpsr_name . ' expected ' . $wpsr_expected . ', got: ' . $wpsr_output;
    }
}

try {
    mkdir($wpsr_workspace, 0700, true);

    foreach (['list' => "[]\n", 'malformed' => "{broken}\n"] as $wpsr_fixture_name => $wpsr_fixture_source) {
        $wpsr_cases++;
        $wpsr_fixture_path = $wpsr_workspace . '/' . $wpsr_fixture_name . '-registry.json';
        wpsr_family_proof_write($wpsr_fixture_path, $wpsr_fixture_source);
        $wpsr_caught = false;
        try {
            wpsr_family_load_json($wpsr_fixture_path);
        } catch (Throwable $wpsr_exception) {
            $wpsr_caught = strpos($wpsr_exception->getMessage(), 'family registry malformed or root is not an object') !== false;
        }
        echo 'PROOF registry-' . $wpsr_fixture_name . '-root: ' . ($wpsr_caught ? 'RED expected' : 'FAIL') . "\n";
        if ($wpsr_caught) {
            $wpsr_red++;
        } else {
            $wpsr_failures[] = $wpsr_fixture_name . ' registry root was accepted';
        }
    }

    $wpsr_live_shape_registry = json_decode(file_get_contents($wpsr_live_registry), true);
    foreach (['empty-object-list' => new stdClass(), 'numeric-object-list' => (object) $wpsr_live_shape_registry['plugins']] as $wpsr_fixture_name => $wpsr_object_list) {
        $wpsr_cases++;
        $wpsr_ambiguous_registry = $wpsr_live_shape_registry;
        if ($wpsr_fixture_name === 'empty-object-list') {
            $wpsr_ambiguous_registry['out_of_family'] = $wpsr_object_list;
        } else {
            $wpsr_ambiguous_registry['plugins'] = $wpsr_object_list;
        }
        $wpsr_ambiguous_path = $wpsr_workspace . '/' . $wpsr_fixture_name . '.json';
        wpsr_family_proof_write($wpsr_ambiguous_path, json_encode($wpsr_ambiguous_registry, JSON_UNESCAPED_SLASHES));
        $wpsr_ambiguous_caught = false;
        try {
            wpsr_family_load_json($wpsr_ambiguous_path);
        } catch (Throwable $wpsr_exception) {
            $wpsr_ambiguous_caught = strpos($wpsr_exception->getMessage(), 'ambiguous JSON object/list shape') !== false;
        }
        echo 'PROOF ' . $wpsr_fixture_name . ': ' . ($wpsr_ambiguous_caught ? 'RED expected' : 'FAIL') . "\n";
        if ($wpsr_ambiguous_caught) {
            $wpsr_red++;
        } else {
            $wpsr_failures[] = $wpsr_fixture_name . ' JSON object masqueraded as list';
        }
    }

    $wpsr_cases++;
    $wpsr_malformed_path_caught = false;
    try {
        wpsr_family_load_json(['not-a-path']);
    } catch (Throwable $wpsr_exception) {
        $wpsr_malformed_path_caught = strpos($wpsr_exception->getMessage(), 'family registry path malformed') !== false;
    }
    $wpsr_scalar_ok = $wpsr_malformed_path_caught
        && wpsr_family_read_constant([], []) === null
        && wpsr_family_read_readme_field([], []) === null
        && wpsr_family_canonical_repository([]) === null;
    echo 'PROOF malformed-helper-scalars: ' . ($wpsr_scalar_ok ? 'RED expected' : 'FAIL') . "\n";
    if ($wpsr_scalar_ok) {
        $wpsr_red++;
    } else {
        $wpsr_failures[] = 'nonscalar helper input was accepted or emitted a diagnostic';
    }

    $wpsr_cases++;
    $wpsr_constant_fixture = $wpsr_workspace . '/constant-source.php';
    wpsr_family_proof_write(
        $wpsr_constant_fixture,
        "<?php\n// define('WPSR_FAMILY_VERSION', '9.9.9');\n\$wpsr_object->define('WPSR_FAMILY_VERSION', '8.8.8');\n\$wpsr_object?->define('WPSR_FAMILY_VERSION', '7.7.7');\nWpsrFixture::define('WPSR_FAMILY_VERSION', '6.6.6');\ndefine('WPSR_FAMILY_VERSION', '5.5.5');\n"
    );
    $wpsr_constant_ok = wpsr_family_read_constant($wpsr_constant_fixture, 'WPSR_FAMILY_VERSION') === '5.5.5';
    echo 'PROOF constant-call-decoys: ' . ($wpsr_constant_ok ? 'RED expected' : 'FAIL') . "\n";
    if ($wpsr_constant_ok) {
        $wpsr_red++;
    } else {
        $wpsr_failures[] = 'method/static/nullsafe constant decoy overrode global define';
    }

    $wpsr_cases++;
    $wpsr_namespaced_fixture = $wpsr_workspace . '/namespaced-constant.php';
    wpsr_family_proof_write(
        $wpsr_namespaced_fixture,
        "<?php\nnamespace WpsrFixture;\nfunction define(\$name, \$value) {}\ndefine('WPSR_FAMILY_VERSION', '5.5.5');\n"
    );
    $wpsr_imported_fixture = $wpsr_workspace . '/imported-constant.php';
    wpsr_family_proof_write(
        $wpsr_imported_fixture,
        "<?php\nuse function WpsrFixture\\define;\ndefine('WPSR_FAMILY_VERSION', '5.5.5');\n"
    );
    $wpsr_non_global_ok = wpsr_family_read_constant($wpsr_namespaced_fixture, 'WPSR_FAMILY_VERSION') === null
        && wpsr_family_read_constant($wpsr_imported_fixture, 'WPSR_FAMILY_VERSION') === null;
    echo 'PROOF non-global-constant-source: ' . ($wpsr_non_global_ok ? 'RED expected' : 'FAIL') . "\n";
    if ($wpsr_non_global_ok) {
        $wpsr_red++;
    } else {
        $wpsr_failures[] = 'namespaced/imported define call was accepted';
    }

    $wpsr_cases++;
    $wpsr_readme_fixture = $wpsr_workspace . '/duplicate-readme.txt';
    wpsr_family_proof_write($wpsr_readme_fixture, "Requires PHP: 7.4\nRequires PHP: 7.4\n");
    $wpsr_readme_ok = wpsr_family_read_readme_field($wpsr_readme_fixture, 'Requires PHP') === null;
    echo 'PROOF duplicate-readme-field: ' . ($wpsr_readme_ok ? 'RED expected' : 'FAIL') . "\n";
    if ($wpsr_readme_ok) {
        $wpsr_red++;
    } else {
        $wpsr_failures[] = 'ambiguous duplicate readme field was accepted';
    }

    $wpsr_cases++;
    $wpsr_repository_ok = wpsr_family_canonical_repository('file://github.com/WPManageNinja/wp-social-ninja.git') === null
        && wpsr_family_canonical_repository('https://github.com/WPManageNinja/wp-social-ninja.git?mirror=foreign') === null;
    echo 'PROOF ambiguous-repository-url: ' . ($wpsr_repository_ok ? 'RED expected' : 'FAIL') . "\n";
    if ($wpsr_repository_ok) {
        $wpsr_red++;
    } else {
        $wpsr_failures[] = 'unsafe or ambiguous repository transport was accepted';
    }

    $wpsr_cases++;
    $wpsr_git_ok = wpsr_family_git_value($wpsr_root, [['rev-parse'], 'HEAD']) === null
        && wpsr_family_git_value($wpsr_workspace . '/missing', ['rev-parse', 'HEAD']) === null;
    echo 'PROOF malformed-git-arguments: ' . ($wpsr_git_ok ? 'RED expected' : 'FAIL') . "\n";
    if ($wpsr_git_ok) {
        $wpsr_red++;
    } else {
        $wpsr_failures[] = 'malformed Git root/arguments were accepted';
    }

    wpsr_family_proof_red('unregistered-sibling', function ($wpsr_family_root) {
        mkdir($wpsr_family_root . '/wp-social-ninja-bridge', 0777, true);
        wpsr_family_proof_write($wpsr_family_root . '/wp-social-ninja-bridge/bridge.php', "<?php\n/* Plugin Name: WP Social Ninja Bridge */\n");
    }, 'unclassified plausible plugin directory:', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('stale-row', function ($wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry) {
        $wpsr_row = $wpsr_registry['plugins'][1];
        $wpsr_row['id'] = 'stale-addon';
        $wpsr_row['slug'] = 'wp-social-ninja-stale';
        $wpsr_row['path']['relative'] = '../wp-social-ninja-stale';
        $wpsr_row['path']['env_override'] = 'WPSR_FAMILY_STALE_PATH';
        $wpsr_row['runtime']['availability'] = 'required';
        $wpsr_row['missing_sibling']['policy'] = 'fail';
        $wpsr_row['activation_order'] = 30;
        $wpsr_registry['plugins'][] = $wpsr_row;
        wpsr_family_proof_save_registry($wpsr_registry_path, $wpsr_registry);
    }, 'stale/missing in-family row', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('duplicate-id', function ($wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry) {
        $wpsr_registry['plugins'][] = $wpsr_registry['plugins'][1];
        wpsr_family_proof_save_registry($wpsr_registry_path, $wpsr_registry);
    }, 'duplicate id', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('duplicate-slug', function ($wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry) {
        $wpsr_row = $wpsr_registry['plugins'][1];
        $wpsr_row['id'] = 'duplicate-slug';
        $wpsr_row['path']['relative'] = '../duplicate-slug';
        $wpsr_row['path']['env_override'] = 'WPSR_FAMILY_DUPLICATE_SLUG_PATH';
        $wpsr_registry['plugins'][] = $wpsr_row;
        wpsr_family_proof_save_registry($wpsr_registry_path, $wpsr_registry);
    }, 'duplicate slug', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('duplicate-path', function ($wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry) {
        $wpsr_row = $wpsr_registry['plugins'][1];
        $wpsr_row['id'] = 'duplicate-path';
        $wpsr_row['slug'] = 'duplicate-path';
        $wpsr_registry['plugins'][] = $wpsr_row;
        wpsr_family_proof_save_registry($wpsr_registry_path, $wpsr_registry);
    }, 'duplicate path', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('second-host', function ($wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry) {
        $wpsr_registry['plugins'][1]['role'] = 'host';
        $wpsr_registry['plugins'][1]['required_host'] = null;
        wpsr_family_proof_save_registry($wpsr_registry_path, $wpsr_registry);
    }, 'host-count !=1', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('dependency-cycle', function ($wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry) {
        $wpsr_registry['plugins'][0]['required_host'] = 'pro';
        wpsr_family_proof_save_registry($wpsr_registry_path, $wpsr_registry);
    }, 'dependency cycle detected', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('incompatible-version', function ($wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry) {
        $wpsr_registry['plugins'][0]['version_compatibility'][2]['range'] = '>=9.0.0';
        wpsr_family_proof_save_registry($wpsr_registry_path, $wpsr_registry);
    }, 'version incompatibility:', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('bad-role-order', function ($wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry) {
        $wpsr_registry['plugins'][1]['role'] = 'sidecar';
        $wpsr_registry['plugins'][1]['activation_order'] = 10;
        wpsr_family_proof_save_registry($wpsr_registry_path, $wpsr_registry);
    }, 'invalid role sidecar', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('missing-required-host', function ($wpsr_family_root, $wpsr_core) {
        unlink($wpsr_core . '/wp-social-reviews.php');
    }, 'stale/missing in-family row', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('foreign-repository', function ($wpsr_family_root, $wpsr_core) {
        exec('git -C ' . escapeshellarg($wpsr_core) . ' remote set-url origin https://github.com/foreign/project.git');
    }, 'VCS repository/branch/ref identity mismatch', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('ambiguous-repository-transport', function ($wpsr_family_root, $wpsr_core) {
        exec('git -C ' . escapeshellarg($wpsr_core) . ' remote set-url origin file://github.com/WPManageNinja/wp-social-ninja.git');
    }, 'VCS repository/branch/ref identity mismatch', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    // A branch cut from the declared branch is legitimate; one that never descends from it is not.
    wpsr_family_proof_red('wrong-branch', function ($wpsr_family_root, $wpsr_core) {
        exec('git -C ' . escapeshellarg($wpsr_core) . ' checkout -q --orphan unrelated-branch');
        exec('git -C ' . escapeshellarg($wpsr_core) . ' commit -q -m unrelated --allow-empty');
    }, 'VCS repository/branch/ref identity mismatch', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('detached-head', function ($wpsr_family_root, $wpsr_core) {
        exec('git -C ' . escapeshellarg($wpsr_core) . ' checkout -q --detach HEAD');
    }, 'VCS repository/branch/ref identity mismatch', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('unknown-registry-key', function ($wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path, $wpsr_registry) {
        $wpsr_registry['plugins'][0]['foreign'] = true;
        wpsr_family_proof_save_registry($wpsr_registry_path, $wpsr_registry);
    }, 'core keys mismatch', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('commented-constant-source', function ($wpsr_family_root, $wpsr_core) {
        $wpsr_path = $wpsr_core . '/wp-social-reviews.php';
        $wpsr_source = file_get_contents($wpsr_path);
        $wpsr_source = str_replace(
            "define('WPSOCIALREVIEWS_PRO_MIN_VERSION', '4.4.0');",
            "// define('WPSOCIALREVIEWS_PRO_MIN_VERSION', '4.4.0');",
            $wpsr_source
        );
        file_put_contents($wpsr_path, $wpsr_source);
    }, 'compatibility source/range drift', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('method-constant-source', function ($wpsr_family_root, $wpsr_core) {
        $wpsr_path = $wpsr_core . '/wp-social-reviews.php';
        $wpsr_source = file_get_contents($wpsr_path);
        $wpsr_source = str_replace(
            "define('WPSOCIALREVIEWS_PRO_MIN_VERSION', '4.4.0');",
            "\$wpsr_decoy->define('WPSOCIALREVIEWS_PRO_MIN_VERSION', '4.4.0');",
            $wpsr_source
        );
        file_put_contents($wpsr_path, $wpsr_source);
    }, 'compatibility source/range drift', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('namespaced-constant-source', function ($wpsr_family_root, $wpsr_core) {
        $wpsr_path = $wpsr_core . '/wp-social-reviews.php';
        $wpsr_source = file_get_contents($wpsr_path);
        $wpsr_source = str_replace(
            "define('WPSOCIALREVIEWS_PRO_MIN_VERSION', '4.4.0');",
            "namespace WpsrFixture;\nfunction define(\$name, \$value) {}\ndefine('WPSOCIALREVIEWS_PRO_MIN_VERSION', '4.4.0');",
            $wpsr_source
        );
        file_put_contents($wpsr_path, $wpsr_source);
    }, 'compatibility source/range drift', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    wpsr_family_proof_red('duplicate-readme-source', function ($wpsr_family_root, $wpsr_core) {
        file_put_contents($wpsr_core . '/readme.txt', "Requires PHP: 7.4\n", FILE_APPEND);
    }, 'compatibility source/range drift', $wpsr_workspace, $wpsr_live_registry, $wpsr_lint, $wpsr_failures);

    $wpsr_cases++;
    [$wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path] = wpsr_family_proof_fixture($wpsr_workspace, $wpsr_live_registry, 'missing-optional-addon');
    wpsr_family_proof_remove($wpsr_pro);
    [$wpsr_code, $wpsr_output] = wpsr_family_proof_run($wpsr_lint, $wpsr_core, $wpsr_family_root, $wpsr_registry_path);
    $wpsr_optional_ok = $wpsr_code === 0
        && strpos($wpsr_output, 'FAMILY-CENSUS SKIP id=family-pro-optional-missing owner=pro slug=wp-social-ninja-pro reason=optional-sibling-missing') !== false
        && strpos($wpsr_output, 'skipped=1 failures=0') !== false;
    echo 'PROOF missing-optional-addon: ' . ($wpsr_optional_ok ? 'SKIP expected' : 'FAIL') . ' exit=' . $wpsr_code . "\n";
    if ($wpsr_optional_ok) {
        $wpsr_skip++;
    } else {
        $wpsr_failures[] = 'missing optional add-on did not emit explicit SKIP metadata: ' . $wpsr_output;
    }

    [$wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path] = wpsr_family_proof_fixture($wpsr_workspace, $wpsr_live_registry, 'restored-fixture');
    [$wpsr_code, $wpsr_output] = wpsr_family_proof_run($wpsr_lint, $wpsr_core, $wpsr_family_root, $wpsr_registry_path);
    if ($wpsr_code === 0 && strpos($wpsr_output, 'candidates=2 classified=2') !== false && strpos($wpsr_output, 'failures=0') !== false) {
        $wpsr_restored++;
    } else {
        $wpsr_failures[] = 'restored derived fixture did not return green: ' . $wpsr_output;
    }

    // A stale local integration branch must not mask an ancestral origin/<branch>.
    $wpsr_cases++;
    [$wpsr_family_root, $wpsr_core, $wpsr_pro, $wpsr_registry_path] = wpsr_family_proof_fixture($wpsr_workspace, $wpsr_live_registry, 'stale-local-branch');
    $wpsr_git = 'git -C ' . escapeshellarg($wpsr_core) . ' ';
    $wpsr_base = trim((string) shell_exec($wpsr_git . 'rev-parse HEAD'));
    exec($wpsr_git . 'update-ref refs/remotes/origin/development ' . escapeshellarg($wpsr_base));
    exec($wpsr_git . 'checkout -q -b feature-work');
    exec($wpsr_git . 'commit -q -m feature --allow-empty');
    exec($wpsr_git . 'checkout -q --detach ' . escapeshellarg($wpsr_base));
    exec($wpsr_git . 'commit -q -m diverged --allow-empty');
    $wpsr_diverged = trim((string) shell_exec($wpsr_git . 'rev-parse HEAD'));
    exec($wpsr_git . 'branch -f development ' . escapeshellarg($wpsr_diverged));
    exec($wpsr_git . 'checkout -q feature-work');
    [$wpsr_code, $wpsr_output] = wpsr_family_proof_run($wpsr_lint, $wpsr_core, $wpsr_family_root, $wpsr_registry_path);
    $wpsr_stale_ok = $wpsr_code === 0 && strpos($wpsr_output, 'failures=0') !== false;
    echo 'PROOF stale-local-branch: ' . ($wpsr_stale_ok ? 'GREEN expected' : 'FAIL') . ' exit=' . $wpsr_code . "\n";
    if ($wpsr_stale_ok) {
        $wpsr_restored++;
    } else {
        $wpsr_failures[] = 'stale local development rejected a branch descending from origin/development: ' . $wpsr_output;
    }

    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($wpsr_lint) . ' 2>&1', $wpsr_live_output, $wpsr_live_code);
    $wpsr_live_text = implode("\n", $wpsr_live_output);
    if ($wpsr_live_code === 0 && strpos($wpsr_live_text, 'candidates=2 classified=2') !== false && strpos($wpsr_live_text, 'failures=0') !== false) {
        $wpsr_restored++;
    } else {
        $wpsr_failures[] = 'live census did not return green: ' . $wpsr_live_text;
    }
} catch (Throwable $wpsr_exception) {
    $wpsr_failures[] = 'proof harness exception: ' . $wpsr_exception->getMessage();
} finally {
    wpsr_family_proof_remove($wpsr_workspace);
    $wpsr_final_core_status = shell_exec('git -C ' . escapeshellarg($wpsr_root) . ' status --porcelain=v1 -z --untracked-files=all');
    $wpsr_final_pro_status = shell_exec('git -C ' . escapeshellarg($wpsr_pro_root) . ' status --porcelain=v1 -z --untracked-files=all');
    if ($wpsr_initial_core_status !== $wpsr_final_core_status) {
        $wpsr_failures[] = 'core status changed during derived proof';
    }
    if ($wpsr_initial_pro_status !== $wpsr_final_pro_status) {
        $wpsr_failures[] = 'Pro status changed during derived proof';
    }
}

foreach ($wpsr_failures as $wpsr_failure) {
    fwrite(STDERR, 'FAMILY-CENSUS-PROOF FAIL ' . $wpsr_failure . "\n");
}
printf(
    "FAMILY-CENSUS-PROOF cases=%d red=%d skipped=%d restored=%d failures=%d\n",
    $wpsr_cases,
    $wpsr_red,
    $wpsr_skip,
    $wpsr_restored,
    count($wpsr_failures)
);
exit($wpsr_failures ? 1 : 0);
