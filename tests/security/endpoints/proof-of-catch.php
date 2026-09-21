<?php
/** Phase 27 proof-of-catch. Mutates only system-temp source/inventory copies. */

$wpsr_core_root = dirname(__DIR__, 3);
$wpsr_pro_root = realpath($wpsr_core_root . '/../wp-social-ninja-pro');
$wpsr_lint = __DIR__ . '/lint.php';
$wpsr_inventory_file = __DIR__ . '/inventory.json';
$wpsr_failures = [];
$wpsr_workspace = sys_get_temp_dir() . '/wpsr-phase27-' . getmypid() . '-' . bin2hex(random_bytes(4));
$wpsr_fixture_core = $wpsr_workspace . '/wp-social-ninja';
$wpsr_fixture_pro = $wpsr_workspace . '/wp-social-ninja-pro';
$wpsr_fixture_inventory = $wpsr_workspace . '/inventory.json';

if (!$wpsr_pro_root || !is_dir($wpsr_pro_root . '/app')
    || (!is_dir($wpsr_pro_root . '/.git') && !is_file($wpsr_pro_root . '/.git'))) {
    fwrite(STDERR, "ENDPOINT-INVENTORY-PROOF Pro sibling checkout unavailable.\n");
    exit(2);
}

function wpsr_endpoint_proof_remove($wpsr_path)
{
    if (!file_exists($wpsr_path)) return;
    if (is_dir($wpsr_path) && !is_link($wpsr_path)) {
        foreach (new FilesystemIterator($wpsr_path, FilesystemIterator::SKIP_DOTS) as $wpsr_item) {
            wpsr_endpoint_proof_remove($wpsr_item->getPathname());
        }
        rmdir($wpsr_path);
        return;
    }
    unlink($wpsr_path);
}

function wpsr_endpoint_proof_git_status($wpsr_root)
{
    $wpsr_lines = [];
    $wpsr_exit = 0;
    exec('git -C ' . escapeshellarg($wpsr_root) . ' status --porcelain=v1 --untracked-files=all 2>&1', $wpsr_lines, $wpsr_exit);
    if ($wpsr_exit !== 0) {
        throw new RuntimeException('could not capture git status for ' . $wpsr_root . ': ' . implode("\n", $wpsr_lines));
    }
    return implode("\n", $wpsr_lines);
}

function wpsr_endpoint_proof_copy_php_tree($wpsr_source_root, $wpsr_target_root)
{
    $wpsr_iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($wpsr_source_root . '/app', FilesystemIterator::SKIP_DOTS),
            static function ($wpsr_current) {
                if (!$wpsr_current->isDir()) return true;
                return $wpsr_current->getFilename() !== 'Libs';
            }
        )
    );
    foreach ($wpsr_iterator as $wpsr_file) {
        if (!$wpsr_file->isFile() || strtolower($wpsr_file->getExtension()) !== 'php') continue;
        $wpsr_relative = substr($wpsr_file->getPathname(), strlen($wpsr_source_root) + 1);
        $wpsr_target = $wpsr_target_root . '/' . $wpsr_relative;
        if (!is_dir(dirname($wpsr_target))) mkdir(dirname($wpsr_target), 0777, true);
        copy($wpsr_file->getPathname(), $wpsr_target);
    }
}

function wpsr_endpoint_proof_run($wpsr_lint, $wpsr_core, $wpsr_pro, $wpsr_inventory)
{
    $wpsr_command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($wpsr_lint)
        . ' --core-root=' . escapeshellarg($wpsr_core)
        . ' --pro-root=' . escapeshellarg($wpsr_pro)
        . ' --inventory=' . escapeshellarg($wpsr_inventory) . ' 2>&1';
    $wpsr_lines = [];
    $wpsr_exit = 0;
    exec($wpsr_command, $wpsr_lines, $wpsr_exit);
    return [$wpsr_exit, implode("\n", $wpsr_lines)];
}

function wpsr_endpoint_proof_expect_red($wpsr_name, callable $wpsr_mutate, $wpsr_expected, array &$wpsr_failures, $wpsr_lint, $wpsr_core, $wpsr_pro, $wpsr_inventory)
{
    $wpsr_mutate();
    [$wpsr_exit, $wpsr_output] = wpsr_endpoint_proof_run($wpsr_lint, $wpsr_core, $wpsr_pro, $wpsr_inventory);
    $wpsr_ok = $wpsr_exit === 1 && strpos($wpsr_output, $wpsr_expected) !== false;
    echo 'PROOF ' . $wpsr_name . ': ' . ($wpsr_ok ? 'RED expected' : 'FAIL') . ' exit=' . $wpsr_exit . "\n";
    if (!$wpsr_ok) {
        $wpsr_failures[] = $wpsr_name . ' expected exit 1 containing ' . $wpsr_expected . '; output=' . $wpsr_output;
    }
}

register_shutdown_function(static function () use ($wpsr_workspace) {
    wpsr_endpoint_proof_remove($wpsr_workspace);
});

try {
    $wpsr_initial_core_status = wpsr_endpoint_proof_git_status($wpsr_core_root);
    $wpsr_initial_pro_status = wpsr_endpoint_proof_git_status($wpsr_pro_root);
    mkdir($wpsr_fixture_core, 0777, true);
    mkdir($wpsr_fixture_pro, 0777, true);
    wpsr_endpoint_proof_copy_php_tree($wpsr_core_root, $wpsr_fixture_core);
    wpsr_endpoint_proof_copy_php_tree($wpsr_pro_root, $wpsr_fixture_pro);
    copy($wpsr_core_root . '/wp-social-reviews.php', $wpsr_fixture_core . '/wp-social-reviews.php');
    copy($wpsr_inventory_file, $wpsr_fixture_inventory);

    $wpsr_core_routes = $wpsr_fixture_core . '/app/Http/Routes/api.php';
    $wpsr_core_actions = $wpsr_fixture_core . '/app/Hooks/actions.php';
    $wpsr_core_shortcode = $wpsr_fixture_core . '/app/Hooks/Handlers/ShortcodeHandler.php';
    $wpsr_widgets_policy = $wpsr_fixture_core . '/app/Http/Policies/WidgetsPolicy.php';
    $wpsr_baseline_files = [
        $wpsr_core_routes => file_get_contents($wpsr_core_routes),
        $wpsr_core_actions => file_get_contents($wpsr_core_actions),
        $wpsr_core_shortcode => file_get_contents($wpsr_core_shortcode),
        $wpsr_widgets_policy => file_get_contents($wpsr_widgets_policy),
        $wpsr_fixture_inventory => file_get_contents($wpsr_fixture_inventory),
    ];
    $wpsr_reset = static function () use ($wpsr_baseline_files) {
        foreach ($wpsr_baseline_files as $wpsr_file => $wpsr_bytes) file_put_contents($wpsr_file, $wpsr_bytes);
    };

    wpsr_endpoint_proof_expect_red('undisclosed-rest', static function () use ($wpsr_reset, $wpsr_core_routes) {
        $wpsr_reset();
        file_put_contents($wpsr_core_routes, "\n\$router->prefix('phase27-proof')->withPolicy('AdminPolicy')->group(function (\$router) {\n    \$router->get('/', [PlatformController::class, 'index']);\n});\n", FILE_APPEND);
    }, 'orphan source declaration missing inventory row: core:rest:GET:/wpsocialreviews/v2/phase27-proof', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('undisclosed-ajax-nopriv', static function () use ($wpsr_reset, $wpsr_core_actions) {
        $wpsr_reset();
        file_put_contents($wpsr_core_actions, "\n\$app->addAction('wp_ajax_nopriv_wpsr_phase27_proof', 'ShortcodeHandler@handleLoadMoreAjax');\n", FILE_APPEND);
    }, 'orphan source declaration missing inventory row: core:ajax:wp_ajax_nopriv_wpsr_phase27_proof', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('undisclosed-shortcode', static function () use ($wpsr_reset, $wpsr_core_shortcode) {
        $wpsr_reset();
        file_put_contents($wpsr_core_shortcode, "\nadd_shortcode('wpsr_phase27_proof', [new \\WPSocialReviews\\App\\Hooks\\Handlers\\ShortcodeHandler(), 'makeShortcode']);\n", FILE_APPEND);
    }, 'orphan source declaration missing inventory row: core:shortcode:wpsr_phase27_proof', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('undisclosed-listener', static function () use ($wpsr_reset, $wpsr_core_actions) {
        $wpsr_reset();
        file_put_contents($wpsr_core_actions, "\n\$app->addAction('template_redirect', 'ShortcodeHandler@makeShortcode');\n", FILE_APPEND);
    }, 'orphan source declaration missing inventory row: core:listener:template_redirect:', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('removed-row', static function () use ($wpsr_reset, $wpsr_fixture_inventory) {
        $wpsr_reset();
        $wpsr_json = json_decode(file_get_contents($wpsr_fixture_inventory), true);
        array_shift($wpsr_json['rows']);
        file_put_contents($wpsr_fixture_inventory, json_encode($wpsr_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }, 'orphan source declaration missing inventory row:', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('stale-row', static function () use ($wpsr_reset, $wpsr_fixture_inventory) {
        $wpsr_reset();
        $wpsr_json = json_decode(file_get_contents($wpsr_fixture_inventory), true);
        $wpsr_stale = $wpsr_json['rows'][0];
        $wpsr_stale['id'] .= ':stale-proof';
        $wpsr_json['rows'][] = $wpsr_stale;
        file_put_contents($wpsr_fixture_inventory, json_encode($wpsr_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }, 'stale inventory row missing source declaration:', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('duplicate-row', static function () use ($wpsr_reset, $wpsr_fixture_inventory) {
        $wpsr_reset();
        $wpsr_json = json_decode(file_get_contents($wpsr_fixture_inventory), true);
        $wpsr_json['rows'][] = $wpsr_json['rows'][0];
        file_put_contents($wpsr_fixture_inventory, json_encode($wpsr_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }, 'duplicate inventory row', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('missing-auth-metadata', static function () use ($wpsr_reset, $wpsr_fixture_inventory) {
        $wpsr_reset();
        $wpsr_json = json_decode(file_get_contents($wpsr_fixture_inventory), true);
        $wpsr_json['rows'][0]['auth_mechanism'] = '';
        file_put_contents($wpsr_fixture_inventory, json_encode($wpsr_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }, 'missing auth/metadata field auth_mechanism', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('policy-capability-drift', static function () use ($wpsr_reset, $wpsr_widgets_policy) {
        $wpsr_reset();
        $wpsr_source = file_get_contents($wpsr_widgets_policy);
        $wpsr_source = str_replace("currentUserCan('wpsn_manage_chat_widgets')", "currentUserCan('wpsn_full_access')", $wpsr_source, $wpsr_count);
        if ($wpsr_count !== 1) throw new RuntimeException('policy capability proof mutation target count=' . $wpsr_count);
        file_put_contents($wpsr_widgets_policy, $wpsr_source);
    }, 'authorization contract drift core:rest:DELETE:/wpsocialreviews/v2/chat-widgets', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('removed-handler-object-guard', static function () use ($wpsr_reset, $wpsr_core_shortcode) {
        $wpsr_reset();
        $wpsr_source = file_get_contents($wpsr_core_shortcode);
        $wpsr_source = str_replace('isPublicRenderableTemplate($templateId, $wpsr_extra_post_types)', 'removedObjectGuard($templateId, $wpsr_extra_post_types)', $wpsr_source, $wpsr_count);
        if ($wpsr_count !== 1) throw new RuntimeException('object guard proof mutation target count=' . $wpsr_count);
        file_put_contents($wpsr_core_shortcode, $wpsr_source);
    }, 'object-scope contract drift core:ajax:wp_ajax_nopriv_wpsr_get_more_feeds', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('introduced-handler-write', static function () use ($wpsr_reset, $wpsr_core_shortcode) {
        $wpsr_reset();
        $wpsr_source = file_get_contents($wpsr_core_shortcode);
        $wpsr_needle = "    public function handleLoadMoreAjax()\n    {";
        $wpsr_replacement = $wpsr_needle . "\n        update_option('wpsr_phase27_proof', 'written', 'no');";
        $wpsr_source = str_replace($wpsr_needle, $wpsr_replacement, $wpsr_source, $wpsr_count);
        if ($wpsr_count !== 1) throw new RuntimeException('side-effect proof mutation target count=' . $wpsr_count);
        file_put_contents($wpsr_core_shortcode, $wpsr_source);
    }, 'side-effect contract drift core:ajax:wp_ajax_nopriv_wpsr_get_more_feeds', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('parser-ambiguity', static function () use ($wpsr_reset, $wpsr_core_actions) {
        $wpsr_reset();
        file_put_contents($wpsr_core_actions, "\nadd_action(getenv('WPSR_PHASE27_HOOK'), [new \\WPSocialReviews\\App\\Hooks\\Handlers\\ShortcodeHandler(), 'makeShortcode']);\n", FILE_APPEND);
    }, 'parser ambiguity: unresolved registration name', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    wpsr_endpoint_proof_expect_red('unsupported-route-method', static function () use ($wpsr_reset, $wpsr_core_routes) {
        $wpsr_reset();
        file_put_contents($wpsr_core_routes, "\n\$router->options('/phase27-unsupported', [PlatformController::class, 'index']);\n", FILE_APPEND);
    }, 'Unsupported route declaration or fluent method: options', $wpsr_failures, $wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);

    $wpsr_reset();
    [$wpsr_fixture_exit, $wpsr_fixture_output] = wpsr_endpoint_proof_run($wpsr_lint, $wpsr_fixture_core, $wpsr_fixture_pro, $wpsr_fixture_inventory);
    if ($wpsr_fixture_exit !== 0 || strpos($wpsr_fixture_output, 'failures=0') === false) {
        $wpsr_failures[] = 'restored derived fixture did not return green: ' . $wpsr_fixture_output;
    }
    [$wpsr_live_exit, $wpsr_live_output] = wpsr_endpoint_proof_run($wpsr_lint, $wpsr_core_root, $wpsr_pro_root, $wpsr_inventory_file);
    if ($wpsr_live_exit !== 0 || strpos($wpsr_live_output, 'failures=0') === false) {
        $wpsr_failures[] = 'live restored inventory did not return green: ' . $wpsr_live_output;
    }

    $wpsr_final_core_status = wpsr_endpoint_proof_git_status($wpsr_core_root);
    $wpsr_final_pro_status = wpsr_endpoint_proof_git_status($wpsr_pro_root);
    if ($wpsr_initial_core_status !== $wpsr_final_core_status) $wpsr_failures[] = 'core status changed during derived proof';
    if ($wpsr_initial_pro_status !== $wpsr_final_pro_status) $wpsr_failures[] = 'Pro status changed during derived proof';
} catch (Throwable $wpsr_exception) {
    $wpsr_failures[] = $wpsr_exception->getMessage();
} finally {
    wpsr_endpoint_proof_remove($wpsr_workspace);
}

if ($wpsr_failures) {
    fwrite(STDERR, "ENDPOINT-INVENTORY-PROOF FAIL\n  - " . implode("\n  - ", $wpsr_failures) . "\n");
    exit(1);
}
echo "ENDPOINT-INVENTORY-PROOF cases=13 red=13 restored=2 failures=0\n";
