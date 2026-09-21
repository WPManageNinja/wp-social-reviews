<?php
/** One-time Phase 27 inventory builder. Lint never regenerates inventory. */
require __DIR__ . '/discovery.php';

$wpsr_core_root = dirname(__DIR__, 3);
$wpsr_pro_root = realpath($wpsr_core_root . '/../wp-social-ninja-pro');
$wpsr_output = __DIR__ . '/inventory.json';
if (!$wpsr_pro_root) {
    fwrite(STDERR, "Pro sibling missing.\n");
    exit(1);
}
$wpsr_discovery = wpsr_endpoint_discover_family($wpsr_core_root, $wpsr_pro_root);
if ($wpsr_discovery['errors']) {
    fwrite(STDERR, implode("\n", $wpsr_discovery['errors']) . "\n");
    exit(1);
}

function wpsr_endpoint_module(array $wpsr_row)
{
    if ($wpsr_row['surface'] === 'rest') {
        $wpsr_path = $wpsr_row['path'];
        foreach ([
            '/pro/review-forms' => 'review-forms', '/pro/custom-sources' => 'custom-sources',
            '/pro/settings' => 'settings', '/platforms' => 'platforms', '/reviews' => 'reviews',
            '/testimonials' => 'testimonials', '/settings' => 'settings', '/chat-widgets' => 'chat-widgets',
            '/shoppable' => 'shoppable', '/notifications' => 'notifications', '/pages' => 'pages',
            '/onboarding' => 'onboarding', '/templates' => 'templates',
        ] as $wpsr_prefix => $wpsr_module) {
            if (strpos($wpsr_path, '/wpsocialreviews/v2' . $wpsr_prefix) === 0) return $wpsr_module;
        }
        return 'rest-api';
    }
    $wpsr_name = $wpsr_row['action'] ?? ($wpsr_row['tag'] ?? ($wpsr_row['callback'] ?? ''));
    if (strpos($wpsr_name, 'review_form') !== false || strpos($wpsr_name, 'ReviewForm') !== false || strpos($wpsr_name, 'TempFile') !== false) return 'review-forms';
    if (strpos($wpsr_name, 'twitter') !== false || strpos($wpsr_name, 'Twitter') !== false) return 'twitter-cards';
    if (strpos($wpsr_name, 'resize') !== false || strpos($wpsr_name, 'Optimization') !== false) return 'image-optimization';
    if (strpos($wpsr_name, 'youtube') !== false || strpos($wpsr_name, 'Youtube') !== false) return 'youtube';
    if (strpos($wpsr_name, 'facebook') !== false || strpos($wpsr_name, 'Facebook') !== false) return 'facebook-feeds';
    if (strpos($wpsr_name, 'ImportExport') !== false) return 'import-export';
    if (strpos($wpsr_name, 'ChatHandler') !== false) return 'chat-widgets';
    if (strpos($wpsr_name, 'NotificationHandler') !== false) return 'notifications';
    if (strpos($wpsr_name, 'QRCode') !== false || ($wpsr_row['registration'] ?? '') === 'query_vars') return 'qr-codes';
    return $wpsr_row['surface'] === 'shortcode' ? 'templates' : 'public-handlers';
}

function wpsr_endpoint_metadata(array $wpsr_row)
{
    $wpsr_plugin = $wpsr_row['plugin'];
    $wpsr_surface = $wpsr_row['surface'];
    if ($wpsr_surface === 'rest') {
        $wpsr_entry_condition = $wpsr_row['method'] . ' request matching ' . $wpsr_row['path'] . ' after WP REST/WPFluent route dispatch.';
    } elseif ($wpsr_surface === 'ajax') {
        $wpsr_entry_condition = 'admin-ajax.php action=' . $wpsr_row['action'] . ' through ' . $wpsr_row['registration'] . ' dispatch.';
    } elseif ($wpsr_surface === 'shortcode') {
        $wpsr_entry_condition = '[' . $wpsr_row['tag'] . '] encountered while rendering WordPress content.';
    } elseif ($wpsr_plugin === 'core' && strpos($wpsr_row['callback'], 'ChatHandler::') !== false) {
        $wpsr_entry_condition = 'template_redirect outside excluded builders; at least one published matching chat template.';
    } elseif ($wpsr_plugin === 'core') {
        $wpsr_entry_condition = 'template_redirect outside excluded builders; at least one published matching notification template.';
    } elseif ($wpsr_row['registration'] === 'query_vars') {
        $wpsr_entry_condition = 'WordPress query_vars filter execution; registers wpsr_qr_code for later listener dispatch.';
    } elseif (strpos($wpsr_row['callback'], 'QRCodeHandler::') !== false) {
        $wpsr_entry_condition = 'template_redirect with get_query_var(wpsr_qr_code) as nonempty string matching stored QR key.';
    } else {
        $wpsr_entry_condition = 'template_redirect with WooCommerce or FluentCart active; integration performs product-context checks.';
    }
    return [
        'module' => wpsr_endpoint_module($wpsr_row),
        'reachability' => $wpsr_row['public'] ? 'public-unauthenticated' : 'authenticated',
        'entry_condition' => $wpsr_entry_condition,
    ];
}

$wpsr_rows = [];
foreach ($wpsr_discovery['rows'] as $wpsr_row) {
    $wpsr_source_row = $wpsr_row;
    unset($wpsr_source_row['security_metadata']);
    $wpsr_rows[] = array_merge($wpsr_source_row, wpsr_endpoint_metadata($wpsr_row), $wpsr_row['security_metadata']);
}
$wpsr_inventory = [
    'schema_version' => 1,
    'family' => 'wp-social-ninja',
    'scope' => 'Authoritative four-surface family source-contract inventory; no Pro runtime claim',
    'generated_from_current_source_on' => gmdate('Y-m-d'),
    'verified_absent' => [
        'core' => [
            'webhook_ipn' => 0,
            'raw_body_direct_listener' => 0,
            'rewrite_rule_or_tag' => 0,
            'public_rest_without_policy' => 0,
            'evidence' => 'Token scan of all first-party PHP source; route collector; raw-body/IPN/webhook/rewrite candidate scans.',
        ],
        'pro' => [
            'webhook_ipn' => 0,
            'raw_body_direct_listener' => 0,
            'rewrite_rule_or_tag' => 0,
            'public_rest_without_policy' => 0,
            'evidence' => 'Token scan of all first-party PHP source; route collector; raw-body/IPN/webhook/rewrite candidate scans.',
        ],
    ],
    'rows' => $wpsr_rows,
];
file_put_contents($wpsr_output, json_encode($wpsr_inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo 'Wrote ' . count($wpsr_rows) . ' rows to ' . $wpsr_output . "\n";
