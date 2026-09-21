<?php
/** Mirrored core/pro runtime canary for Phase 29 environment matrix. */
require_once __DIR__ . '/../lib/harness.php';

WpsrTest::boot();
$wpsr_environment = trim((string) getenv('WPSR_TEST_ENV'));
$wpsr_environment = $wpsr_environment === 'pro' ? 'pro' : 'core';

WpsrTest::case('family activation and runtime match selected ' . $wpsr_environment . ' environment', function () use ($wpsr_environment) {
    $wpsr_active = array_values((array) get_option('active_plugins', []));
    $wpsr_core = array_search('wp-social-ninja/wp-social-reviews.php', $wpsr_active, true);
    $wpsr_pro = array_search('wp-social-ninja-pro/wp-social-ninja-pro.php', $wpsr_active, true);
    WpsrTest::assertTrue($wpsr_core !== false, 'Core activation entry must exist.');

    $wpsr_server = rest_get_server();
    do_action('rest_api_init', $wpsr_server);
    $wpsr_pro_routes = array_values(array_filter(array_keys($wpsr_server->get_routes()), static function ($wpsr_route) {
        return strpos($wpsr_route, '/wpsocialreviews/v2/pro') === 0;
    }));
    $wpsr_admin_id = get_current_user_id();
    wp_set_current_user(0);
    \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
    try {
        $wpsr_result = WpsrTest::rest('GET', 'pro/custom-sources', []);
        $wpsr_code = is_array($wpsr_result['data']) ? ($wpsr_result['data']['code'] ?? '') : '';
    } finally {
        wp_set_current_user($wpsr_admin_id);
        \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
    }

    if ($wpsr_environment === 'pro') {
        WpsrTest::assertTrue($wpsr_pro !== false, 'Pro activation entry must exist.');
        WpsrTest::assertTrue(defined('WPSOCIALREVIEWS_VERSION'), 'Core runtime constant must exist.');
        WpsrTest::assertTrue(did_action('wp_social_reviews_loaded_v2') > 0, 'Core lifecycle must fire before Pro runtime is usable.');
        WpsrTest::assertTrue(defined('WPSOCIALREVIEWS_PRO') && defined('WPSOCIALREVIEWS_PRO_VERSION'), 'Pro constants must exist.');
        WpsrTest::assertTrue(did_action('wp_social_ninja_pro_loaded') > 0, 'Pro lifecycle hook must fire.');
        WpsrTest::assertSame(22, count($wpsr_pro_routes), 'distinct Pro runtime REST path count including router root alias');
        WpsrTest::assertSame(401, $wpsr_result['status'], 'Pro endpoint mounted denial status');
        WpsrTest::assertSame('rest_forbidden', $wpsr_code, 'Pro endpoint mounted denial code');
        return;
    }

    WpsrTest::assertSame(false, $wpsr_pro, 'Pro activation entry must be absent.');
    foreach (['WPSOCIALREVIEWS_PRO_MAIN_FILE', 'WPSOCIALREVIEWS_PRO_VERSION', 'WPSOCIALREVIEWS_PRO', 'WPSOCIALREVIEWS_PRO_URL', 'WPSOCIALREVIEWS_PRO_DIR'] as $wpsr_constant) {
        WpsrTest::assertTrue(!defined($wpsr_constant), 'Core environment leaked Pro constant ' . $wpsr_constant . '.');
    }
    foreach (['wp_ajax_nopriv_wpsr_submit_review_form', 'wp_ajax_nopriv_wpsr_review_form_upload', 'wp_ajax_nopriv_wpsr_twitter_cards'] as $wpsr_hook) {
        WpsrTest::assertSame(false, has_action($wpsr_hook), 'Core environment leaked Pro hook ' . $wpsr_hook);
    }
    WpsrTest::assertSame(0, did_action('wp_social_ninja_pro_loaded'), 'Core environment Pro lifecycle count');
    WpsrTest::assertSame([], $wpsr_pro_routes, 'Core environment Pro routes');
    WpsrTest::assertSame(404, $wpsr_result['status'], 'Core environment Pro endpoint absence status');
    WpsrTest::assertSame('rest_no_route', $wpsr_code, 'Core environment Pro endpoint absence code');
});

WpsrTest::finish('FAMILY-' . strtoupper($wpsr_environment));
