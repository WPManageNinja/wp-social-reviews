<?php
/** Phase 27 authoritative four-surface endpoint inventory drift lint. */
require __DIR__ . '/discovery.php';

function wpsr_endpoint_lint_args(array $wpsr_argv)
{
    $wpsr_options = [];
    foreach (array_slice($wpsr_argv, 1) as $wpsr_arg) {
        if (!preg_match('/^--(core-root|pro-root|inventory)=(.+)$/', $wpsr_arg, $wpsr_match)) {
            throw new InvalidArgumentException('Unknown argument: ' . $wpsr_arg);
        }
        $wpsr_options[$wpsr_match[1]] = $wpsr_match[2];
    }
    return $wpsr_options;
}

function wpsr_endpoint_set(array $wpsr_values)
{
    $wpsr_values = array_values(array_unique(array_map('strval', $wpsr_values)));
    sort($wpsr_values, SORT_STRING);
    return $wpsr_values;
}

function wpsr_endpoint_compare_set($wpsr_label, array $wpsr_expected, array $wpsr_actual, array &$wpsr_errors)
{
    $wpsr_expected = wpsr_endpoint_set($wpsr_expected);
    $wpsr_actual = wpsr_endpoint_set($wpsr_actual);
    if ($wpsr_expected !== $wpsr_actual) {
        $wpsr_missing = array_values(array_diff($wpsr_expected, $wpsr_actual));
        $wpsr_stale = array_values(array_diff($wpsr_actual, $wpsr_expected));
        $wpsr_errors[] = $wpsr_label . ' mismatch; missing=' . json_encode($wpsr_missing) . ' stale=' . json_encode($wpsr_stale);
    }
}

function wpsr_endpoint_old_consistency($wpsr_core_root, array $wpsr_source_rows, array &$wpsr_errors)
{
    $wpsr_core_rest = array_values(array_filter($wpsr_source_rows, static function ($wpsr_row) {
        return $wpsr_row['plugin'] === 'core' && $wpsr_row['surface'] === 'rest';
    }));
    $wpsr_all_rest_keys = array_column($wpsr_core_rest, 'source_key');
    $wpsr_get_keys = [];
    $wpsr_mutating_keys = [];
    foreach ($wpsr_core_rest as $wpsr_row) {
        if ($wpsr_row['method'] === 'GET') {
            $wpsr_get_keys[] = substr($wpsr_row['source_key'], 4);
        } else {
            $wpsr_mutating_keys[] = $wpsr_row['source_key'];
        }
    }

    $wpsr_validation = require $wpsr_core_root . '/tests/validation/routes.manifest.php';
    $wpsr_smoke = require $wpsr_core_root . '/tests/smoke/routes.manifest.php';
    $wpsr_permissions = require $wpsr_core_root . '/tests/permissions/routes.manifest.php';
    wpsr_endpoint_compare_set('Phase 10 REST manifest', $wpsr_all_rest_keys, array_column((array) $wpsr_validation, 'declaration'), $wpsr_errors);
    wpsr_endpoint_compare_set('Phase 1/2 GET manifest', $wpsr_get_keys, array_column((array) $wpsr_smoke, 'declaration'), $wpsr_errors);
    wpsr_endpoint_compare_set('Phase 3 mutating manifest', $wpsr_mutating_keys, array_column((array) $wpsr_permissions, 'declaration'), $wpsr_errors);

    $wpsr_public_manifest = require $wpsr_core_root . '/tests/public/handlers.manifest.php';
    $wpsr_core_public_ajax = [];
    $wpsr_core_shortcodes = [];
    $wpsr_core_redirects = [];
    foreach ($wpsr_source_rows as $wpsr_row) {
        if ($wpsr_row['plugin'] !== 'core') continue;
        if ($wpsr_row['surface'] === 'ajax' && $wpsr_row['public']) $wpsr_core_public_ajax[] = $wpsr_row['action'];
        if ($wpsr_row['surface'] === 'shortcode') $wpsr_core_shortcodes[] = $wpsr_row['tag'];
        if ($wpsr_row['surface'] === 'listener' && $wpsr_row['registration'] === 'template_redirect') {
            $wpsr_parts = explode('\\', $wpsr_row['callback']);
            $wpsr_core_redirects[] = end($wpsr_parts);
        }
    }
    wpsr_endpoint_compare_set('Phase 18 nopriv AJAX manifest', $wpsr_core_public_ajax, array_column((array) ($wpsr_public_manifest['ajax'] ?? []), 'hook'), $wpsr_errors);
    wpsr_endpoint_compare_set('Phase 18 shortcode manifest', $wpsr_core_shortcodes, array_column((array) ($wpsr_public_manifest['shortcodes'] ?? []), 'tag'), $wpsr_errors);
    $wpsr_old_redirects = [];
    foreach ((array) ($wpsr_public_manifest['template_redirect'] ?? []) as $wpsr_row) {
        if (preg_match('/([^\/]+)\.php::(.+)$/', (string) ($wpsr_row['handler'] ?? ''), $wpsr_match)) {
            $wpsr_old_redirects[] = $wpsr_match[1] . '::' . $wpsr_match[2];
        }
    }
    wpsr_endpoint_compare_set('Phase 18 template_redirect manifest', $wpsr_core_redirects, $wpsr_old_redirects, $wpsr_errors);
    if (!empty($wpsr_public_manifest['public_rest'])) {
        $wpsr_errors[] = 'Phase 18 public_rest must remain empty because authoritative inventory has no policy-free REST row';
    }
    if (!empty($wpsr_public_manifest['rewrite_query_vars'])) {
        $wpsr_errors[] = 'Phase 18 core rewrite/query-var section disagrees with authoritative zero';
    }

    $wpsr_non_rest = require $wpsr_core_root . '/tests/validation/non-rest.manifest.php';
    $wpsr_expected_non_rest = array_merge(['shortcode:wp_social_ninja'], array_map(static function ($wpsr_action) {
        return 'ajax:' . $wpsr_action;
    }, $wpsr_core_public_ajax), ['absent:wpsr_upload_image']);
    $wpsr_actual_non_rest = [];
    foreach ((array) $wpsr_non_rest as $wpsr_row) {
        $wpsr_surface = (string) ($wpsr_row['surface'] ?? '');
        if (preg_match('/shortcode \[([^\]]+)\]/', $wpsr_surface, $wpsr_match)) {
            $wpsr_actual_non_rest[] = 'shortcode:' . $wpsr_match[1];
        } elseif (preg_match('/wp_ajax\[_nopriv\]_([A-Za-z0-9_-]+)/', $wpsr_surface, $wpsr_match)) {
            $wpsr_actual_non_rest[] = 'ajax:' . $wpsr_match[1];
        } elseif (strpos($wpsr_surface, 'wpsr_upload_image') !== false) {
            $wpsr_actual_non_rest[] = 'absent:wpsr_upload_image';
        } else {
            $wpsr_errors[] = 'Phase 10 non-REST row cannot map to authoritative inventory: ' . $wpsr_surface;
        }
    }
    wpsr_endpoint_compare_set('Phase 10 non-REST manifest', $wpsr_expected_non_rest, $wpsr_actual_non_rest, $wpsr_errors);

    $wpsr_pro_public = array_values(array_filter($wpsr_source_rows, static function ($wpsr_row) {
        return $wpsr_row['plugin'] === 'pro' && $wpsr_row['public'];
    }));
    $wpsr_pro_ids = array_fill_keys(array_column($wpsr_pro_public, 'id'), true);
    $wpsr_pro_actions_source = file_get_contents(dirname($wpsr_core_root) . '/wp-social-ninja-pro/app/Hooks/actions.php');
    foreach ((array) ($wpsr_public_manifest['pro_addon_only'] ?? []) as $wpsr_row) {
        $wpsr_surface = (string) ($wpsr_row['surface'] ?? '');
        if (preg_match('/wp_ajax_nopriv_([A-Za-z0-9_-]+)/', $wpsr_surface, $wpsr_match)) {
            if (!isset($wpsr_pro_ids['pro:ajax:wp_ajax_nopriv_' . $wpsr_match[1]])) {
                $wpsr_errors[] = 'Phase 18 stale Pro nopriv classification: ' . $wpsr_surface;
            }
        } elseif (preg_match('/shortcode \[([^\]]+)\]/', $wpsr_surface, $wpsr_match)) {
            if (!isset($wpsr_pro_ids['pro:shortcode:' . $wpsr_match[1]])) {
                $wpsr_errors[] = 'Phase 18 stale Pro shortcode classification: ' . $wpsr_surface;
            }
        } elseif (strpos($wpsr_surface, 'wpsr_qr_code') !== false) {
            $wpsr_qr_rows = array_filter($wpsr_pro_public, static function ($wpsr_item) {
                return $wpsr_item['surface'] === 'listener'
                    && ($wpsr_item['registration'] === 'query_vars' || strpos($wpsr_item['callback'], 'QRCodeHandler::templateRedirect') !== false);
            });
            if (count($wpsr_qr_rows) !== 2) $wpsr_errors[] = 'Phase 18 QR listener classification no longer maps to query_vars + template_redirect';
        } elseif (strpos($wpsr_surface, 'WooCommerce/FluentCart') !== false) {
            $wpsr_commerce = array_filter($wpsr_pro_public, static function ($wpsr_item) {
                return $wpsr_item['surface'] === 'listener' && strpos($wpsr_item['callback'], 'app/Hooks/actions.php') !== false;
            });
            if (count($wpsr_commerce) !== 1) $wpsr_errors[] = 'Phase 18 commerce listener classification no longer maps to Pro template_redirect';
        } elseif (strpos($wpsr_surface, 'Facebook album-photo') !== false) {
            if (strpos((string) $wpsr_pro_actions_source, "wpsocialreviews/facebook_feed_handle_album_photo") === false) {
                $wpsr_errors[] = 'Phase 18 Facebook material-listener classification is stale';
            }
        } else {
            $wpsr_errors[] = 'Phase 18 Pro/add-on classification cannot map to authoritative source: ' . $wpsr_surface;
        }
    }
}

try {
    $wpsr_options = wpsr_endpoint_lint_args($argv);
} catch (Throwable $wpsr_exception) {
    fwrite(STDERR, 'endpoint-inventory: ' . $wpsr_exception->getMessage() . "\n");
    exit(2);
}
$wpsr_core_root = realpath($wpsr_options['core-root'] ?? dirname(__DIR__, 3));
$wpsr_pro_root = realpath($wpsr_options['pro-root'] ?? (dirname(__DIR__, 3) . '/../wp-social-ninja-pro'));
$wpsr_inventory_file = $wpsr_options['inventory'] ?? (__DIR__ . '/inventory.json');
$wpsr_errors = [];
if (!$wpsr_core_root || !$wpsr_pro_root) {
    fwrite(STDERR, "endpoint-inventory: core or Pro source root missing.\n");
    exit(1);
}
$wpsr_inventory = json_decode((string) file_get_contents($wpsr_inventory_file), true);
if (!is_array($wpsr_inventory) || ($wpsr_inventory['schema_version'] ?? null) !== 1 || !isset($wpsr_inventory['rows']) || !is_array($wpsr_inventory['rows'])) {
    fwrite(STDERR, "endpoint-inventory: malformed inventory JSON/schema.\n");
    exit(1);
}
$wpsr_discovery = wpsr_endpoint_discover_family($wpsr_core_root, $wpsr_pro_root);
$wpsr_errors = array_merge($wpsr_errors, $wpsr_discovery['errors']);
$wpsr_source_rows = $wpsr_discovery['rows'];
$wpsr_inventory_rows = $wpsr_inventory['rows'];
if (!$wpsr_source_rows) $wpsr_errors[] = 'zero source endpoints discovered; parser wiring failed';
if (!$wpsr_inventory_rows) $wpsr_errors[] = 'zero authoritative inventory rows';

$wpsr_required = ['id', 'plugin', 'surface', 'registration', 'callback', 'public', 'module', 'reachability', 'entry_condition', 'auth_mechanism', 'object_scope', 'side_effect', 'test_disposition', 'existing_test', 'runtime', 'security_contract', 'evidence'];
$wpsr_allowed_dispositions = ['DONE', 'THIN', 'PINNED', 'LEFT'];
$wpsr_inventory_by_id = [];
foreach ($wpsr_inventory_rows as $wpsr_index => $wpsr_row) {
    $wpsr_number = $wpsr_index + 1;
    foreach ($wpsr_required as $wpsr_key) {
        if (!array_key_exists($wpsr_key, $wpsr_row)
            || (!in_array($wpsr_key, ['public', 'evidence', 'security_contract'], true) && (!is_string($wpsr_row[$wpsr_key]) || trim($wpsr_row[$wpsr_key]) === ''))) {
            $wpsr_errors[] = 'inventory row ' . $wpsr_number . ' missing auth/metadata field ' . $wpsr_key;
        }
    }
    if (!is_bool($wpsr_row['public'] ?? null)) $wpsr_errors[] = 'inventory row ' . $wpsr_number . ' public must be boolean';
    if (!is_array($wpsr_row['evidence'] ?? null) || empty($wpsr_row['evidence']['file']) || empty($wpsr_row['evidence']['line'])) {
        $wpsr_errors[] = 'inventory row ' . $wpsr_number . ' missing file:line evidence';
    }
    if (!in_array($wpsr_row['test_disposition'] ?? '', $wpsr_allowed_dispositions, true)) {
        $wpsr_errors[] = 'inventory row ' . $wpsr_number . ' invalid DONE/THIN/PINNED/LEFT disposition';
    }
    $wpsr_contract = $wpsr_row['security_contract'] ?? null;
    if (!is_array($wpsr_contract)
        || empty($wpsr_contract['handler']['file']) || empty($wpsr_contract['handler']['line'])
        || !is_array($wpsr_contract['authorization'] ?? null)
        || !is_array($wpsr_contract['object_scope']['signals'] ?? null)
        || !is_array($wpsr_contract['side_effect']['signals'] ?? null)
        || ($wpsr_contract['coverage']['reference'] ?? '') !== 'tests/security/endpoints/lint.php::source-contract-v1') {
        $wpsr_errors[] = 'inventory row ' . $wpsr_number . ' missing/ambiguous exact security contract';
    }
    if (isset($wpsr_inventory_by_id[$wpsr_row['id'] ?? ''])) $wpsr_errors[] = 'duplicate inventory row ' . ($wpsr_row['id'] ?? '(empty)');
    $wpsr_inventory_by_id[$wpsr_row['id'] ?? ''] = $wpsr_row;
}
$wpsr_source_by_id = [];
foreach ($wpsr_source_rows as $wpsr_row) {
    if (isset($wpsr_source_by_id[$wpsr_row['id']])) $wpsr_errors[] = 'duplicate discovered row ' . $wpsr_row['id'];
    $wpsr_source_by_id[$wpsr_row['id']] = $wpsr_row;
}
foreach (array_diff_key($wpsr_source_by_id, $wpsr_inventory_by_id) as $wpsr_id => $wpsr_row) {
    $wpsr_errors[] = 'orphan source declaration missing inventory row: ' . $wpsr_id . ' at ' . $wpsr_row['evidence']['file'] . ':' . $wpsr_row['evidence']['line'];
}
foreach (array_diff_key($wpsr_inventory_by_id, $wpsr_source_by_id) as $wpsr_id => $wpsr_row) {
    $wpsr_errors[] = 'stale inventory row missing source declaration: ' . $wpsr_id;
}
$wpsr_source_fields = ['plugin', 'surface', 'registration', 'method', 'path', 'hook', 'action', 'tag', 'callback', 'policy', 'public', 'source_key', 'evidence'];
foreach (array_intersect_key($wpsr_source_by_id, $wpsr_inventory_by_id) as $wpsr_id => $wpsr_source_row) {
    $wpsr_inventory_row = $wpsr_inventory_by_id[$wpsr_id];
    foreach ($wpsr_source_fields as $wpsr_field) {
        $wpsr_source_value = $wpsr_source_row[$wpsr_field] ?? null;
        $wpsr_inventory_value = $wpsr_inventory_row[$wpsr_field] ?? null;
        if ($wpsr_source_value !== $wpsr_inventory_value) {
            $wpsr_errors[] = 'source/inventory field drift ' . $wpsr_id . ' field=' . $wpsr_field
                . ' source=' . json_encode($wpsr_source_value) . ' inventory=' . json_encode($wpsr_inventory_value);
        }
    }
    $wpsr_derived = $wpsr_source_row['security_metadata'] ?? [];
    $wpsr_contract_fields = [
        'auth_mechanism' => 'authorization contract drift',
        'object_scope' => 'object-scope contract drift',
        'side_effect' => 'side-effect contract drift',
        'test_disposition' => 'disposition contract drift',
        'existing_test' => 'test-reference contract drift',
        'runtime' => 'runtime contract drift',
        'security_contract' => 'security contract evidence drift',
    ];
    foreach ($wpsr_contract_fields as $wpsr_field => $wpsr_reason) {
        if (($wpsr_derived[$wpsr_field] ?? null) !== ($wpsr_inventory_row[$wpsr_field] ?? null)) {
            $wpsr_errors[] = $wpsr_reason . ' ' . $wpsr_id . ' field=' . $wpsr_field
                . ' source=' . json_encode($wpsr_derived[$wpsr_field] ?? null)
                . ' inventory=' . json_encode($wpsr_inventory_row[$wpsr_field] ?? null);
        }
    }
}

foreach (['core', 'pro'] as $wpsr_plugin) {
    $wpsr_plugin_rows = array_filter($wpsr_source_rows, static function ($wpsr_row) use ($wpsr_plugin) { return $wpsr_row['plugin'] === $wpsr_plugin; });
    foreach (['rest', 'ajax', 'shortcode', 'listener'] as $wpsr_surface) {
        if (!array_filter($wpsr_plugin_rows, static function ($wpsr_row) use ($wpsr_surface) { return $wpsr_row['surface'] === $wpsr_surface; })) {
            $wpsr_errors[] = 'zero ' . $wpsr_plugin . ' ' . $wpsr_surface . ' discovery; parser failure or undeclared absence';
        }
    }
    $wpsr_absence = $wpsr_discovery['plugins'][$wpsr_plugin]['absence'];
    $wpsr_declared_absence = $wpsr_inventory['verified_absent'][$wpsr_plugin] ?? [];
    foreach (['webhook_ipn', 'raw_body_direct_listener', 'rewrite_rule_or_tag', 'public_rest_without_policy'] as $wpsr_absent_class) {
        if (($wpsr_declared_absence[$wpsr_absent_class] ?? null) !== 0) {
            $wpsr_errors[] = 'verified absence missing/nonzero for ' . $wpsr_plugin . ' ' . $wpsr_absent_class;
        }
    }
    if (($wpsr_absence['raw_body_direct'] ?? -1) !== 0 || ($wpsr_absence['ipn_candidates'] ?? -1) !== 0
        || ($wpsr_absence['webhook_candidates'] ?? -1) !== 0 || ($wpsr_absence['rewrite_registrations'] ?? -1) !== 0) {
        $wpsr_errors[] = 'declared zero listener class disagrees with source candidates for ' . $wpsr_plugin;
    }
}

if ($wpsr_core_root === realpath(dirname(__DIR__, 3)) && $wpsr_inventory_file === __DIR__ . '/inventory.json') {
    wpsr_endpoint_old_consistency($wpsr_core_root, $wpsr_source_rows, $wpsr_errors);
}

$wpsr_counts = [];
$wpsr_dispositions = array_fill_keys($wpsr_allowed_dispositions, 0);
foreach ($wpsr_inventory_rows as $wpsr_row) {
    $wpsr_key = $wpsr_row['plugin'] . '/' . $wpsr_row['surface'] . '/' . ($wpsr_row['public'] ? 'public' : 'authenticated');
    $wpsr_counts[$wpsr_key] = ($wpsr_counts[$wpsr_key] ?? 0) + 1;
    if (isset($wpsr_dispositions[$wpsr_row['test_disposition'] ?? ''])) $wpsr_dispositions[$wpsr_row['test_disposition']]++;
}
ksort($wpsr_counts, SORT_STRING);
$wpsr_summary = [];
foreach ($wpsr_counts as $wpsr_key => $wpsr_count) $wpsr_summary[] = $wpsr_key . '=' . $wpsr_count;
echo 'endpoint-inventory: discovered=' . count($wpsr_source_rows) . ' inventory=' . count($wpsr_inventory_rows)
    . ' files=' . ($wpsr_discovery['plugins']['core']['stats']['files'] + $wpsr_discovery['plugins']['pro']['stats']['files'])
    . ' registrations=' . ($wpsr_discovery['plugins']['core']['stats']['calls'] + $wpsr_discovery['plugins']['pro']['stats']['calls']) . "\n";
echo 'endpoint-inventory: ' . implode(' ', $wpsr_summary) . "\n";
echo 'endpoint-inventory: dispositions DONE=' . $wpsr_dispositions['DONE'] . ' THIN=' . $wpsr_dispositions['THIN']
    . ' PINNED=' . $wpsr_dispositions['PINNED'] . ' LEFT=' . $wpsr_dispositions['LEFT'] . "\n";
if ($wpsr_errors) {
    fwrite(STDERR, "ENDPOINT-INVENTORY FAIL\n  - " . implode("\n  - ", array_values(array_unique($wpsr_errors))) . "\n");
    exit(1);
}
echo 'ENDPOINT-INVENTORY rows=' . count($wpsr_inventory_rows) . ' discovered=' . count($wpsr_source_rows) . ' surfaces=4 plugins=2 failures=0' . "\n";
