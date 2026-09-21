<?php
/** Disposable WordPress runtime probe for one Phase 31 matrix cell. */
if (!defined('WP_CLI') || !WP_CLI) {
    exit("Compatibility runtime requires WP-CLI.\n");
}

$wpsr_payload = '';
foreach ((array) $args as $wpsr_argument) {
    if (strpos($wpsr_argument, 'payload=') === 0) {
        $wpsr_payload = substr($wpsr_argument, 8);
    }
}
$wpsr_cell = json_decode((string) base64_decode($wpsr_payload, true), true);
if (!is_array($wpsr_cell) || empty($wpsr_cell['id']) || empty($wpsr_cell['expected'])) {
    WP_CLI::error('COMPATIBILITY-RUNTIME malformed cell payload.');
}

$wpsr_failures = [];
$wpsr_checks = 0;
$wpsr_lifecycle_checks = 0;
$wpsr_rest_checks = 0;
$wpsr_assert = static function ($wpsr_condition, $wpsr_message, $wpsr_kind = 'lifecycle') use (&$wpsr_failures, &$wpsr_checks, &$wpsr_lifecycle_checks, &$wpsr_rest_checks) {
    $wpsr_checks++;
    if ($wpsr_kind === 'rest') {
        $wpsr_rest_checks++;
    } else {
        $wpsr_lifecycle_checks++;
    }
    if (!$wpsr_condition) {
        $wpsr_failures[] = $wpsr_message;
    }
};

if (stripos((string) DB_NAME, 'test') === false) {
    $wpsr_failures[] = 'database name lacks test';
}
global $wpdb, $wp_version;
if ($wpdb->prefix === 'wp_') {
    $wpsr_failures[] = 'default wp_ prefix is forbidden';
}
$wpsr_expected = $wpsr_cell['expected'];
$wpsr_dimension = (array) ($wpsr_cell['resolved']['dimension'] ?? []);
$wpsr_expected_php = (string) ($wpsr_dimension['php'] ?? '');
$wpsr_expected_wp = (string) ($wpsr_dimension['wordpress'] ?? '');
$wpsr_assert($wpsr_expected_php !== '' && strpos(PHP_VERSION, $wpsr_expected_php . '.') === 0, 'PHP dimension mismatch; expected ' . $wpsr_expected_php . ', got ' . PHP_VERSION);
if ($wpsr_expected_wp !== '' && $wpsr_expected_wp !== 'current-image') {
    $wpsr_assert(strpos((string) $wp_version, $wpsr_expected_wp . '.') === 0 || (string) $wp_version === $wpsr_expected_wp, 'WordPress dimension mismatch; expected ' . $wpsr_expected_wp . ', got ' . $wp_version);
}
$wpsr_active = array_values((array) get_option('active_plugins', []));
$wpsr_core_index = array_search('wp-social-ninja/wp-social-reviews.php', $wpsr_active, true);
$wpsr_pro_index = array_search('wp-social-ninja-pro/wp-social-ninja-pro.php', $wpsr_active, true);
$wpsr_assert($wpsr_core_index !== false, 'core activation entry absent');
$wpsr_assert(defined('WPSOCIALREVIEWS_VERSION'), 'core version constant absent');
$wpsr_assert(did_action('wp_social_reviews_loaded_v2') > 0, 'core lifecycle did not fire');
$wpsr_assert(class_exists('WPSocialReviews\\App\\Models\\Review'), 'core application sentinel absent');
$wpsr_assert((string) WPSOCIALREVIEWS_VERSION === (string) $wpsr_cell['resolved']['core_version'], 'core runtime version mismatch');

$wpsr_pro_boot = did_action('wp_social_ninja_pro_loaded') > 0;
$wpsr_has_pro_artifact = !empty($wpsr_cell['pro']);
if ($wpsr_has_pro_artifact) {
    $wpsr_assert($wpsr_pro_index !== false, 'Pro activation entry absent');
    // WordPress may sort active_plugins. Registry order plus both fired lifecycle
    // actions proves Core activated first and Pro booted from Core's loaded hook.
    $wpsr_assert(did_action('wp_social_reviews_loaded_v2') > 0 && ($wpsr_pro_boot || empty($wpsr_expected['pro_boot'])), 'Core lifecycle was unavailable to Pro dependency callback');
    $wpsr_assert(defined('WPSOCIALREVIEWS_PRO_VERSION') && (string) WPSOCIALREVIEWS_PRO_VERSION === (string) $wpsr_cell['resolved']['pro_version'], 'Pro runtime version mismatch');
} else {
    $wpsr_assert($wpsr_pro_index === false, 'free-alone activation leaked Pro');
    foreach (['WPSOCIALREVIEWS_PRO_MAIN_FILE', 'WPSOCIALREVIEWS_PRO_VERSION', 'WPSOCIALREVIEWS_PRO', 'WPSOCIALREVIEWS_PRO_URL', 'WPSOCIALREVIEWS_PRO_DIR'] as $wpsr_constant) {
        $wpsr_assert(!defined($wpsr_constant), 'free-alone leaked ' . $wpsr_constant);
    }
    foreach (['wp_ajax_nopriv_wpsr_submit_review_form', 'wp_ajax_nopriv_wpsr_review_form_upload', 'wp_ajax_nopriv_wpsr_twitter_cards'] as $wpsr_hook) {
        $wpsr_assert(has_action($wpsr_hook) === false, 'free-alone leaked hook ' . $wpsr_hook);
    }
}

$wpsr_assert($wpsr_pro_boot === (bool) $wpsr_expected['pro_boot'], 'Pro boot outcome mismatch; expected=' . ((int) $wpsr_expected['pro_boot']) . ' actual=' . ((int) $wpsr_pro_boot));

$wpsr_outbound_http = [];
$wpsr_outbound_mail = [];
add_filter('pre_http_request', static function ($wpsr_preempt, $wpsr_args, $wpsr_url) use (&$wpsr_outbound_http) {
    $wpsr_outbound_http[] = (string) $wpsr_url;
    return new WP_Error('wpsr_compatibility_remote_blocked', 'Outbound HTTP blocked by compatibility matrix.');
}, PHP_INT_MAX, 3);
add_filter('pre_wp_mail', static function ($wpsr_preempt, $wpsr_attributes) use (&$wpsr_outbound_mail) {
    $wpsr_outbound_mail[] = is_array($wpsr_attributes) ? $wpsr_attributes : [];
    return true;
}, PHP_INT_MAX, 2);
$wpsr_db_fingerprint = static function () use ($wpdb) {
    $wpsr_tables = $wpdb->get_col('SHOW TABLES');
    sort($wpsr_tables, SORT_STRING);
    $wpsr_rows = [];
    foreach ($wpsr_tables as $wpsr_table) {
        $wpsr_safe = str_replace('`', '``', $wpsr_table);
        $wpsr_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$wpsr_safe}`");
        $wpsr_checksum = $wpdb->get_row("CHECKSUM TABLE `{$wpsr_safe}`", ARRAY_A);
        $wpsr_rows[$wpsr_table] = [$wpsr_count, (string) ($wpsr_checksum['Checksum'] ?? '')];
    }
    return hash('sha256', serialize($wpsr_rows));
};
$wpsr_database_before = $wpsr_db_fingerprint();

$wpsr_server = rest_get_server();
do_action('rest_api_init', $wpsr_server);
$wpsr_routes = $wpsr_server->get_routes();
$wpsr_core_routes = array_values(array_filter(array_keys($wpsr_routes), static function ($wpsr_route) {
    return strpos($wpsr_route, '/wpsocialreviews/v2/') === 0 && strpos($wpsr_route, '/wpsocialreviews/v2/pro') !== 0;
}));
$wpsr_pro_routes = array_values(array_filter(array_keys($wpsr_routes), static function ($wpsr_route) {
    return strpos($wpsr_route, '/wpsocialreviews/v2/pro') === 0;
}));
$wpsr_assert(count($wpsr_core_routes) >= (int) $wpsr_expected['core_routes_min'], 'core route count below expected minimum', 'rest');

$wpsr_admins = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID']);
$wpsr_admin_id = $wpsr_admins ? (int) $wpsr_admins[0]->ID : 0;
$wpsr_assert($wpsr_admin_id > 0, 'administrator fixture absent');
if ($wpsr_admin_id > 0) {
    wp_set_current_user($wpsr_admin_id);
    if (class_exists('WPSocialReviews\\App\\Services\\PermissionManager')) {
        \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
    }
    $wpsr_core_request = new WP_REST_Request('GET', '/wpsocialreviews/v2/platforms');
    $wpsr_core_response = rest_do_request($wpsr_core_request);
    $wpsr_assert($wpsr_core_response->get_status() === 200, 'representative core GET smoke did not return 200; got ' . $wpsr_core_response->get_status(), 'rest');
}

$wpsr_pro_declarations_checked = 0;
$wpsr_manifest_path = '/suite-pro-tests/routes.json';
$wpsr_manifest = is_file($wpsr_manifest_path) ? json_decode((string) file_get_contents($wpsr_manifest_path), true) : null;
$wpsr_declarations = is_array($wpsr_manifest) ? (array) ($wpsr_manifest['declarations'] ?? []) : [];
if ((int) $wpsr_expected['pro_route_declarations'] === 33) {
    $wpsr_assert(count($wpsr_declarations) === 33, 'authoritative Pro REST manifest is not 33 declarations', 'rest');
    wp_set_current_user(0);
    if (class_exists('WPSocialReviews\\App\\Services\\PermissionManager')) {
        \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
    }
    foreach ($wpsr_declarations as $wpsr_declaration) {
        $wpsr_method = (string) ($wpsr_declaration['method'] ?? '');
        $wpsr_path = str_replace('{id}', '731337', (string) ($wpsr_declaration['path'] ?? ''));
        $wpsr_request = new WP_REST_Request($wpsr_method, $wpsr_path);
        $wpsr_response = rest_do_request($wpsr_request);
        $wpsr_data = $wpsr_response->get_data();
        $wpsr_code = is_array($wpsr_data) ? (string) ($wpsr_data['code'] ?? '') : '';
        $wpsr_assert($wpsr_response->get_status() === 401 && $wpsr_code === 'rest_forbidden', 'Pro route denial mismatch ' . $wpsr_method . ' ' . $wpsr_path . '; got ' . $wpsr_response->get_status() . '/' . ($wpsr_code ?: '(none)'), 'rest');
        $wpsr_pro_declarations_checked++;
    }
} else {
    $wpsr_assert(count($wpsr_pro_routes) === 0, 'incompatible/free cell registered Pro routes: ' . count($wpsr_pro_routes), 'rest');
    $wpsr_request = new WP_REST_Request('GET', '/wpsocialreviews/v2/pro/custom-sources');
    $wpsr_response = rest_do_request($wpsr_request);
    $wpsr_data = $wpsr_response->get_data();
    $wpsr_code = is_array($wpsr_data) ? (string) ($wpsr_data['code'] ?? '') : '';
    $wpsr_assert($wpsr_response->get_status() === 404 && $wpsr_code === 'rest_no_route', 'Pro endpoint not exactly absent; got ' . $wpsr_response->get_status() . '/' . ($wpsr_code ?: '(none)'), 'rest');
}

$wpsr_diagnostic = '';
if (!empty($wpsr_expected['diagnostic'])) {
    if (($wpsr_cell['purpose'] ?? '') === 'below-minimum-pro-for-core') {
        if (class_exists('WPSocialReviews\\App\\Services\\DashboardNotices')) {
            $wpsr_notice = new \WPSocialReviews\App\Services\DashboardNotices();
            if ($wpsr_notice->maybeDisplayProUpdateNotice()) {
                $wpsr_diagnostic = 'displayProUpdateNotice=true';
            }
        }
    } else {
        wp_set_current_user($wpsr_admin_id);
        ob_start();
        do_action('admin_notices');
        $wpsr_diagnostic = trim((string) ob_get_clean());
    }
    $wpsr_assert(strpos($wpsr_diagnostic, (string) $wpsr_expected['diagnostic']) !== false, 'dependency diagnostic mismatch');
}

$wpsr_assert($wpsr_db_fingerprint() === $wpsr_database_before, 'REST smoke changed database fingerprint', 'rest');
$wpsr_assert($wpsr_outbound_http === [], 'REST smoke attempted outbound HTTP: ' . implode(', ', $wpsr_outbound_http), 'rest');
$wpsr_assert($wpsr_outbound_mail === [], 'REST smoke attempted outbound mail', 'rest');
$wpsr_assert($wpsr_lifecycle_checks >= (int) $wpsr_expected['lifecycle_checks_min'], 'zero/low lifecycle checks');
$wpsr_assert($wpsr_rest_checks >= (int) $wpsr_expected['rest_checks_min'], 'zero/low REST checks', 'rest');
$wpsr_db_version = (string) $wpdb->get_var('SELECT VERSION()');
$wpsr_result = [
    'schema' => 1,
    'cell' => $wpsr_cell['id'],
    'purpose' => $wpsr_cell['purpose'],
    'expected_compatibility' => $wpsr_expected['compatibility'],
    'actual_compatibility' => $wpsr_failures ? 'mismatch' : $wpsr_expected['compatibility'],
    'versions' => [
        'core' => defined('WPSOCIALREVIEWS_VERSION') ? WPSOCIALREVIEWS_VERSION : null,
        'pro' => defined('WPSOCIALREVIEWS_PRO_VERSION') ? WPSOCIALREVIEWS_PRO_VERSION : null,
        'wordpress' => (string) $wp_version,
        'php' => PHP_VERSION,
        'database' => $wpsr_db_version,
    ],
    'commits' => $wpsr_cell['resolved']['commits'],
    'images' => $wpsr_cell['resolved']['images'],
    'boot' => ['core' => did_action('wp_social_reviews_loaded_v2') > 0, 'pro' => $wpsr_pro_boot],
    'routes' => ['core_paths' => count($wpsr_core_routes), 'pro_paths' => count($wpsr_pro_routes), 'pro_declarations_checked' => $wpsr_pro_declarations_checked],
    'checks' => ['total' => $wpsr_checks, 'lifecycle' => $wpsr_lifecycle_checks, 'rest' => $wpsr_rest_checks],
    'diagnostic' => wp_strip_all_tags($wpsr_diagnostic),
    'failures' => $wpsr_failures,
];
echo 'COMPATIBILITY-CELL-JSON ' . wp_json_encode($wpsr_result, JSON_UNESCAPED_SLASHES) . "\n";
printf(
    "COMPATIBILITY-CELL id=%s compatibility=%s core-boot=%s pro-boot=%s core-routes=%d pro-paths=%d pro-declarations=%d lifecycle-checks=%d rest-checks=%d failures=%d\n",
    $wpsr_cell['id'], $wpsr_expected['compatibility'], $wpsr_result['boot']['core'] ? 'yes' : 'no', $wpsr_pro_boot ? 'yes' : 'no',
    count($wpsr_core_routes), count($wpsr_pro_routes), $wpsr_pro_declarations_checked, $wpsr_lifecycle_checks, $wpsr_rest_checks, count($wpsr_failures)
);
if ($wpsr_failures) {
    foreach ($wpsr_failures as $wpsr_failure) {
        WP_CLI::warning('COMPATIBILITY-RUNTIME ' . $wpsr_failure);
    }
    WP_CLI::error('Compatibility cell failed expected/actual reconciliation.');
}
