<?php
/**
 * Phase 20 exact lab-state capture/configure/restore/verify utility.
 * Runs only through WP-CLI in disposable Docker lab.
 */
if (!defined('WP_CLI') || !WP_CLI) {
    exit("Compatibility state utility requires WP-CLI.\n");
}

$wpsr_action = '';
$wpsr_path = '';
foreach ((array) $args as $wpsr_arg) {
    if (strpos($wpsr_arg, 'action=') === 0) {
        $wpsr_action = substr($wpsr_arg, 7);
    } elseif (strpos($wpsr_arg, 'path=') === 0) {
        $wpsr_path = substr($wpsr_arg, 5);
    }
}
if ($wpsr_action === '') {
    WP_CLI::error('Compatibility state action is required.');
}
if (stripos((string) DB_NAME, 'test') === false) {
    WP_CLI::error('Compatibility state utility refuses non-test DB: ' . DB_NAME);
}

global $wpdb;
if ($wpdb->prefix === 'wp_') {
    WP_CLI::error('Compatibility state utility refuses default wp_ prefix.');
}

$wpsr_config = require dirname(__DIR__) . '/suite.config.php';
$wpsr_option_names = ['timezone_string', 'gmt_offset', 'active_plugins', 'active_sitewide_plugins', 'cron'];

$wpsr_option_rows = function () use ($wpdb, $wpsr_option_names) {
    $wpsr_rows = [];
    foreach ($wpsr_option_names as $wpsr_name) {
        $wpsr_row = $wpdb->get_row($wpdb->prepare(
            "SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",
            $wpsr_name
        ), ARRAY_A);
        $wpsr_rows[$wpsr_name] = is_array($wpsr_row) ? $wpsr_row : null;
    }
    return $wpsr_rows;
};

$wpsr_table_fingerprints = function () use ($wpdb, $wpsr_config) {
    $wpsr_suffixes = array_merge($wpsr_config['protected_tables'], $wpsr_config['protected_wordpress_tables']);
    $wpsr_fingerprints = [];
    foreach ($wpsr_suffixes as $wpsr_suffix) {
        $wpsr_table = $wpdb->prefix . $wpsr_suffix;
        $wpsr_safe = str_replace('`', '``', $wpsr_table);
        $wpsr_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpsr_table)));
        if ($wpsr_found !== $wpsr_table) {
            throw new RuntimeException('Compatibility protected table missing: ' . $wpsr_table);
        }
        $wpsr_checksum = $wpdb->get_row("CHECKSUM TABLE `{$wpsr_safe}`", ARRAY_A);
        $wpsr_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpsr_safe}`");
        if (!is_array($wpsr_checksum) || $wpdb->last_error !== '') {
            throw new RuntimeException('Could not fingerprint ' . $wpsr_table . ': ' . $wpdb->last_error);
        }
        $wpsr_fingerprints[$wpsr_suffix] = [
            'count' => (int) $wpsr_count,
            'checksum' => isset($wpsr_checksum['Checksum']) ? (string) $wpsr_checksum['Checksum'] : '',
        ];
    }
    return $wpsr_fingerprints;
};

$wpsr_capture = function () use ($wpdb, $wpsr_option_rows, $wpsr_table_fingerprints) {
    $wpsr_sql = $wpdb->get_row('SELECT @@GLOBAL.sql_mode AS global_mode', ARRAY_A);
    if (!is_array($wpsr_sql) || $wpdb->last_error !== '') {
        throw new RuntimeException('Could not capture global SQL mode: ' . $wpdb->last_error);
    }
    return [
        'schema' => 1,
        'db_name' => DB_NAME,
        'table_prefix' => $wpdb->prefix,
        'global_sql_mode' => (string) $wpsr_sql['global_mode'],
        'options' => $wpsr_option_rows(),
        'tables' => $wpsr_table_fingerprints(),
    ];
};

$wpsr_read_snapshot = function () use (&$wpsr_path) {
    if ($wpsr_path === '' || !is_file($wpsr_path)) {
        throw new RuntimeException('Compatibility snapshot missing: ' . ($wpsr_path ?: '(empty path)'));
    }
    $wpsr_snapshot = json_decode(file_get_contents($wpsr_path), true);
    if (!is_array($wpsr_snapshot) || ($wpsr_snapshot['schema'] ?? 0) !== 1) {
        throw new RuntimeException('Compatibility snapshot is malformed: ' . $wpsr_path);
    }
    return $wpsr_snapshot;
};

try {
    if ($wpsr_action === 'capture') {
        if ($wpsr_path === '') {
            throw new RuntimeException('Capture path is required.');
        }
        $wpsr_snapshot = $wpsr_capture();
        $wpsr_json = wp_json_encode($wpsr_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($wpsr_json === false || file_put_contents($wpsr_path, $wpsr_json . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Could not write compatibility snapshot: ' . $wpsr_path);
        }
        WP_CLI::success('Compatibility baseline captured: ' . $wpsr_path);
        return;
    }

    if ($wpsr_action === 'configure-non-utc') {
        $wpsr_axes = require __DIR__ . '/axes.php';
        $wpsr_axis = $wpsr_axes['non-utc'];
        update_option('timezone_string', $wpsr_axis['timezone_string'], 'no');
        update_option('gmt_offset', $wpsr_axis['gmt_offset'], 'no');
        wp_cache_delete('timezone_string', 'options');
        wp_cache_delete('gmt_offset', 'options');
        if ((string) get_option('timezone_string') !== $wpsr_axis['timezone_string']
            || (float) get_option('gmt_offset') !== (float) $wpsr_axis['gmt_offset']) {
            throw new RuntimeException('Could not configure exact non-UTC site options.');
        }
        WP_CLI::success('Configured timezone_string=Asia/Kolkata and gmt_offset=5.5.');
        return;
    }

    if ($wpsr_action === 'restore') {
        $wpsr_snapshot = $wpsr_read_snapshot();
        foreach (['timezone_string', 'gmt_offset'] as $wpsr_name) {
            $wpsr_row = $wpsr_snapshot['options'][$wpsr_name] ?? null;
            if ($wpsr_row === null) {
                $wpdb->delete($wpdb->options, ['option_name' => $wpsr_name], ['%s']);
            } else {
                $wpsr_existing_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s",
                    $wpsr_name
                ));
                if ($wpsr_existing_id === null) {
                    $wpsr_inserted = $wpdb->insert($wpdb->options, [
                        'option_id' => (int) $wpsr_row['option_id'],
                        'option_name' => $wpsr_name,
                        'option_value' => $wpsr_row['option_value'],
                        'autoload' => $wpsr_row['autoload'],
                    ], ['%d', '%s', '%s', '%s']);
                    if ($wpsr_inserted === false) {
                        throw new RuntimeException('Could not restore absent option ' . $wpsr_name . ': ' . $wpdb->last_error);
                    }
                } else {
                    $wpsr_updated = $wpdb->update($wpdb->options, [
                        'option_value' => $wpsr_row['option_value'],
                        'autoload' => $wpsr_row['autoload'],
                    ], ['option_name' => $wpsr_name], ['%s', '%s'], ['%s']);
                    if ($wpsr_updated === false) {
                        throw new RuntimeException('Could not restore option ' . $wpsr_name . ': ' . $wpdb->last_error);
                    }
                }
            }
            wp_cache_delete($wpsr_name, 'options');
        }
        wp_cache_flush();
        WP_CLI::success('Compatibility site options restored exactly.');
        return;
    }

    if ($wpsr_action === 'verify') {
        $wpsr_snapshot = $wpsr_read_snapshot();
        $wpsr_current = $wpsr_capture();
        $wpsr_failures = [];
        foreach (['db_name', 'table_prefix', 'global_sql_mode', 'options', 'tables'] as $wpsr_key) {
            if (($wpsr_snapshot[$wpsr_key] ?? null) !== ($wpsr_current[$wpsr_key] ?? null)) {
                $wpsr_failures[] = $wpsr_key;
            }
        }
        if ($wpsr_failures) {
            throw new RuntimeException('Compatibility restoration mismatch: ' . implode(', ', $wpsr_failures));
        }
        WP_CLI::success('Compatibility restoration verified: tables/options/cron/plugin state/SQL mode exact.');
        return;
    }

    throw new RuntimeException('Unknown compatibility state action: ' . $wpsr_action);
} catch (Throwable $wpsr_exception) {
    WP_CLI::error($wpsr_exception->getMessage());
}
