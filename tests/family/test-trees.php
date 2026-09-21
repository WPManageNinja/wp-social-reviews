<?php
/** Phase 30 host-side all-linked-add-on test-tree/link/metadata/discovery drift gate. */
require_once __DIR__ . '/test-tree-lib.php';

$wpsr_options = [
    'family' => __DIR__ . '/plugins.json',
    'host_root' => dirname(__DIR__, 2),
    'plugin' => null,
    'plugin_root' => null,
    'link' => null,
    'activation' => null,
];
foreach (array_slice($argv, 1) as $wpsr_argument) {
    if (strpos($wpsr_argument, '--family=') === 0) {
        $wpsr_options['family'] = substr($wpsr_argument, 9);
    } elseif (strpos($wpsr_argument, '--host-root=') === 0) {
        $wpsr_options['host_root'] = substr($wpsr_argument, 12);
    } elseif (strpos($wpsr_argument, '--plugin=') === 0) {
        $wpsr_options['plugin'] = substr($wpsr_argument, 9);
    } elseif (strpos($wpsr_argument, '--plugin-root=') === 0) {
        $wpsr_options['plugin_root'] = substr($wpsr_argument, 14);
    } elseif (strpos($wpsr_argument, '--link=') === 0) {
        $wpsr_options['link'] = substr($wpsr_argument, 7);
    } elseif (strpos($wpsr_argument, '--activation-json=') === 0) {
        $wpsr_options['activation'] = json_decode(substr($wpsr_argument, 18), true);
        if (!is_array($wpsr_options['activation']) || json_last_error() !== JSON_ERROR_NONE) {
            fwrite(STDERR, "ADDON-TEST-TREE misuse: --activation-json must be a JSON array.\n");
            exit(WPSR_TEST_TREE_MISUSE);
        }
    } else {
        fwrite(STDERR, 'ADDON-TEST-TREE misuse: ' . $wpsr_argument . "\n");
        exit(WPSR_TEST_TREE_MISUSE);
    }
}
try {
    $wpsr_family = wpsr_family_load_json($wpsr_options['family']);
    $wpsr_plugins = array_values(array_filter((array) ($wpsr_family['plugins'] ?? []), static function ($wpsr_plugin) use ($wpsr_options) {
        return is_array($wpsr_plugin)
            && ($wpsr_plugin['role'] ?? '') !== 'host'
            && ($wpsr_plugin['test_tree']['status'] ?? '') === 'linked'
            && ($wpsr_options['plugin'] === null || ($wpsr_plugin['id'] ?? '') === $wpsr_options['plugin']);
    }));
    usort($wpsr_plugins, static function ($wpsr_left, $wpsr_right) {
        return ($wpsr_left['activation_order'] ?? 0) <=> ($wpsr_right['activation_order'] ?? 0);
    });
    if (!$wpsr_plugins) {
        throw new RuntimeException('linked add-on/bridge discovery returned zero plugins');
    }
} catch (Throwable $wpsr_exception) {
    fwrite(STDERR, 'ADDON-TEST-TREE FAIL ' . $wpsr_exception->getMessage() . "\n");
    exit(WPSR_TEST_TREE_FAIL);
}
$wpsr_totals = ['linked' => 0, 'suites' => 0, 'files' => 0, 'rest' => 0, 'skipped' => 0];
$wpsr_errors = [];
foreach ($wpsr_plugins as $wpsr_plugin) {
    $wpsr_validate_options = ['plugin_id' => $wpsr_plugin['id']];
    foreach (['plugin_root', 'link', 'activation'] as $wpsr_key) {
        if ($wpsr_options[$wpsr_key] !== null) {
            $wpsr_validate_options[$wpsr_key] = $wpsr_options[$wpsr_key];
        }
    }
    $wpsr_result = wpsr_test_tree_validate(
        $wpsr_family,
        wpsr_family_normalize_path($wpsr_options['host_root']),
        $wpsr_validate_options
    );
    foreach ($wpsr_result['skips'] as $wpsr_skip) {
        echo getenv('WPSR_FAMILY_SWEEP_CHILD') === '1'
            ? str_replace('ADDON-TEST-TREE SKIP', 'ADDON-TEST-TREE UNAVAILABLE', $wpsr_skip) . "\n"
            : $wpsr_skip . "\n";
    }
    foreach ($wpsr_result['errors'] as $wpsr_error) {
        $wpsr_errors[] = $wpsr_plugin['id'] . ': ' . $wpsr_error;
    }
    $wpsr_skipped = count($wpsr_result['skips']);
    $wpsr_totals['linked'] += $wpsr_skipped ? 0 : 1;
    $wpsr_totals['suites'] += (int) ($wpsr_result['suites'] ?? 0);
    $wpsr_totals['files'] += count($wpsr_result['files']);
    $wpsr_totals['rest'] += (int) $wpsr_result['cases'];
    $wpsr_totals['skipped'] += $wpsr_skipped;
    printf(
        "ADDON-TEST-TREE PLUGIN id=%s available=%s suites=%d files=%d rest=%d skipped=%d failures=%d\n",
        $wpsr_plugin['id'],
        $wpsr_skipped ? 'no' : 'yes',
        $wpsr_result['suites'] ?? 0,
        count($wpsr_result['files']),
        $wpsr_result['cases'],
        $wpsr_skipped,
        count($wpsr_result['errors'])
    );
}
foreach ($wpsr_errors as $wpsr_error) {
    fwrite(STDERR, 'ADDON-TEST-TREE FAIL ' . $wpsr_error . "\n");
}
printf(
    "ADDON-TEST-TREE plugins=%d linked=%d suites=%d files=%d rest=%d skipped=%d failures=%d\n",
    count($wpsr_plugins),
    $wpsr_totals['linked'],
    $wpsr_totals['suites'],
    $wpsr_totals['files'],
    $wpsr_totals['rest'],
    $wpsr_totals['skipped'],
    count($wpsr_errors)
);
exit($wpsr_errors ? WPSR_TEST_TREE_FAIL : WPSR_TEST_TREE_PASS);
