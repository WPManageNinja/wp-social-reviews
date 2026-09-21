<?php
/**
 * Phase 3: anonymous + explicit low-privilege denial for every core mutating route.
 */
require_once __DIR__ . '/../lib/harness.php';
require_once __DIR__ . '/../lib/factory.php';
require_once __DIR__ . '/../lib/runner.php';

WpsrTest::boot();
$wpsr_manifest = require __DIR__ . '/../permissions/routes.manifest.php';
if (!is_array($wpsr_manifest) || !$wpsr_manifest) {
    WP_CLI::error('Permission manifest discovered zero routes; suite wiring is broken.');
}

$wpsr_declarations = array_unique(array_column($wpsr_manifest, 'declaration'));
if (!$wpsr_declarations) {
    WP_CLI::error('Permission manifest discovered zero declaration mappings; suite wiring is broken.');
}

$wpsr_filter = WpsrRunner::filter((array) $args, 'Permission');
$wpsr_selected_manifest = array_values(array_filter($wpsr_manifest, function ($wpsr_entry) use ($wpsr_filter) {
    return $wpsr_filter === null
        || stripos($wpsr_entry['label'] . ' ' . $wpsr_entry['method'] . ' ' . $wpsr_entry['route'], $wpsr_filter) !== false;
}));
if (!$wpsr_selected_manifest) {
    WP_CLI::error('Permission filter selected zero routes: ' . $wpsr_filter);
}

$wpsr_method_counts = ['POST' => 0, 'PUT' => 0, 'DELETE' => 0, 'PATCH' => 0];
foreach ($wpsr_manifest as $wpsr_index => $wpsr_entry) {
    if (!is_array($wpsr_entry)
        || empty($wpsr_entry['declaration'])
        || empty($wpsr_entry['label'])
        || empty($wpsr_entry['method'])
        || empty($wpsr_entry['route'])
        || empty($wpsr_entry['policy'])
        || !isset($wpsr_entry['params'])
        || !is_array($wpsr_entry['params'])) {
        WP_CLI::error('Invalid permission-manifest entry at index ' . $wpsr_index . '.');
    }

    $wpsr_method = strtoupper($wpsr_entry['method']);
    if (!array_key_exists($wpsr_method, $wpsr_method_counts)) {
        WP_CLI::error('Unsupported permission-manifest method: ' . $wpsr_method . '.');
    }
    $wpsr_method_counts[$wpsr_method]++;
}

$wpsr_admin_id = get_current_user_id();
$wpsr_user_id = 0;
$wpsr_cleanup_complete = false;

$wpsr_cleanup = function () use ($wpsr_admin_id, &$wpsr_cleanup_complete) {
    if ($wpsr_cleanup_complete) {
        return;
    }
    wp_set_current_user($wpsr_admin_id);
    \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);
    try {
        WpsrFactory::cleanup();
    } catch (Throwable $wpsr_cleanup_error) {
        WpsrTest::fail('permission fixture cleanup failed: ' . $wpsr_cleanup_error->getMessage());
    }
    $wpsr_cleanup_complete = true;
};
register_shutdown_function($wpsr_cleanup);

$wpsr_create_post = function ($wpsr_title, $wpsr_type, $wpsr_content = '') {
    $wpsr_post = WpsrFactory::post($wpsr_type, [
        'post_title' => $wpsr_title,
        'post_content' => $wpsr_content,
        'post_status' => 'publish',
    ]);
    return (int) $wpsr_post->ID;
};

$wpsr_controller_fuse_hits = [];
$wpsr_rejection_counts = ['anonymous' => 0, 'low-privilege' => 0];
$wpsr_controller_fuse = function ($wpsr_dispatch_result, $wpsr_request) use (&$wpsr_controller_fuse_hits) {
    if (strpos($wpsr_request->get_route(), '/wpsocialreviews/v2/') === 0) {
        $wpsr_controller_fuse_hits[] = $wpsr_request->get_method() . ' ' . $wpsr_request->get_route();
        return new WP_Error(
            'wpsr_permission_gate_bypassed',
            'Permission callback allowed unauthorized controller dispatch.',
            ['status' => 418]
        );
    }

    return $wpsr_dispatch_result;
};

$wpsr_replace_tokens = function ($wpsr_value, array $wpsr_tokens) use (&$wpsr_replace_tokens) {
    if (is_array($wpsr_value)) {
        foreach ($wpsr_value as $wpsr_key => $wpsr_item) {
            $wpsr_value[$wpsr_key] = $wpsr_replace_tokens($wpsr_item, $wpsr_tokens);
        }
        return $wpsr_value;
    }
    if (!is_string($wpsr_value)) {
        return $wpsr_value;
    }

    foreach ($wpsr_tokens as $wpsr_token => $wpsr_replacement) {
        if ($wpsr_value === '{' . $wpsr_token . '}') {
            return $wpsr_replacement;
        }
        $wpsr_value = str_replace('{' . $wpsr_token . '}', (string) $wpsr_replacement, $wpsr_value);
    }
    return $wpsr_value;
};

$wpsr_assert_denied = function (
    array $wpsr_entry,
    $wpsr_route,
    array $wpsr_params,
    $wpsr_identity,
    $wpsr_identity_user_id,
    $wpsr_expected_status
) use (&$wpsr_rejection_counts) {
    wp_set_current_user($wpsr_identity_user_id);
    // PermissionManager caches process-wide without user key. Refresh after every switch.
    \WPSocialReviews\App\Services\PermissionManager::currentUserPermissions(false);

    if (get_current_user_id() !== $wpsr_identity_user_id) {
        WpsrTest::fail(sprintf(
            '%s identity setup failed: expected user %d, got %d',
            $wpsr_identity,
            $wpsr_identity_user_id,
            get_current_user_id()
        ));
        return;
    }

    $wpsr_result = WpsrTest::rest($wpsr_entry['method'], $wpsr_route, $wpsr_params);
    $wpsr_code = is_array($wpsr_result['data']) && isset($wpsr_result['data']['code'])
        ? (string) $wpsr_result['data']['code']
        : '';
    $wpsr_envelope_status = is_array($wpsr_result['data']) && isset($wpsr_result['data']['data']['status'])
        ? (int) $wpsr_result['data']['data']['status']
        : 0;

    if ($wpsr_result['db_error'] !== '') {
        WpsrTest::fail('DATABASE ERROR during permission check: ' . $wpsr_result['db_error']);
        return;
    }
    if ($wpsr_result['is_exception']) {
        WpsrTest::fail('PLUGIN EXCEPTION during permission check: ' . $wpsr_result['message']);
        return;
    }

    $wpsr_denial_is_exact = $wpsr_result['status'] === $wpsr_expected_status
        && $wpsr_code === 'rest_forbidden'
        && $wpsr_envelope_status === $wpsr_expected_status
        && $wpsr_result['message'] !== '';

    if (!$wpsr_denial_is_exact) {
        $wpsr_security = $wpsr_code === 'wpsr_permission_gate_bypassed'
            || ($wpsr_result['status'] >= 200 && $wpsr_result['status'] < 300)
            ? 'SECURITY: unauthorized access detected — '
            : '';
        WpsrTest::fail(sprintf(
            '%sexpected %s denial %d/rest_forbidden with matching envelope for %s %s; got %d/%s envelope=%d%s',
            $wpsr_security,
            $wpsr_identity,
            $wpsr_expected_status,
            $wpsr_entry['method'],
            $wpsr_route,
            $wpsr_result['status'],
            $wpsr_code !== '' ? $wpsr_code : '(no code)',
            $wpsr_envelope_status,
            $wpsr_result['message'] !== '' ? "\n  message: " . $wpsr_result['message'] : ''
        ));
    } else {
        $wpsr_rejection_counts[$wpsr_identity]++;
    }

    if (get_current_user_id() !== $wpsr_identity_user_id) {
        WpsrTest::fail(sprintf(
            '%s identity changed during dispatch: expected user %d, got %d',
            $wpsr_identity,
            $wpsr_identity_user_id,
            get_current_user_id()
        ));
    }
};

$wpsr_database_before = [];
$wpsr_database_after = [];
try {
    $wpsr_marker = WpsrFactory::marker();
    $wpsr_low_user = WpsrFactory::limitedUser([]);
    $wpsr_user_id = (int) $wpsr_low_user->ID;
    $wpsr_plugin_caps = \WPSocialReviews\App\Services\PermissionManager::pluginPermissions();
    $wpsr_forbidden_caps = array_merge($wpsr_plugin_caps, ['manage_options', 'install_plugins', 'activate_plugins', 'edit_posts']);
    $wpsr_accidental_caps = array_values(array_filter($wpsr_forbidden_caps, function ($wpsr_cap) use ($wpsr_low_user) {
        return user_can($wpsr_low_user, $wpsr_cap);
    }));
    if (!$wpsr_low_user || !user_can($wpsr_low_user, 'read') || $wpsr_accidental_caps) {
        throw new RuntimeException('Low-privilege role capability contract failed; accidental caps: '
            . ($wpsr_accidental_caps ? implode(', ', $wpsr_accidental_caps) : '(role/user invalid)'));
    }

    $wpsr_review_template_id = $wpsr_create_post('WPSR Permission Review Template', 'wp_social_reviews', 'google');
    $wpsr_feed_template_id = $wpsr_create_post('WPSR Permission Feed Template', 'wp_social_reviews', 'youtube');
    $wpsr_chat_widget_id = $wpsr_create_post('WPSR Permission Chat Widget', 'wpsr_social_chats', 'social chats');
    $wpsr_notification_id = $wpsr_create_post('WPSR Permission Notification', 'wpsr_reviews_notify', 'google');

    $wpsr_review = WpsrFactory::review([
        'platform_name'      => 'custom',
        'source_id'          => $wpsr_marker . '-permission-source',
        'review_title'       => 'Permission Review Fixture',
        'reviewer_name'      => 'Permission Reviewer',
        'reviewer_email'     => 'reviewer@example.test',
        'reviewer_text'      => 'Permission fixture body',
        'review_time'        => '2026-01-02 03:04:05',
        'rating'             => 5,
        'review_approved'    => 1,
        'recommendation_type'=> 'positive',
    ]);
    $wpsr_testimonial = WpsrFactory::review([
        'platform_name'      => 'testimonial',
        'source_id'          => $wpsr_marker . '-permission-source',
        'review_title'       => 'Permission Testimonial Fixture',
        'reviewer_name'      => 'Permission Author',
        'reviewer_email'     => 'author@example.test',
        'reviewer_text'      => 'Permission testimonial fixture body',
        'review_time'        => '2026-01-02 03:04:05',
        'rating'             => 5,
        'review_approved'    => 1,
        'recommendation_type'=> 'positive',
    ]);
    if (!$wpsr_review || !$wpsr_review->id || !$wpsr_testimonial || !$wpsr_testimonial->id) {
        throw new RuntimeException('Could not create permission review fixtures.');
    }
    $wpsr_category = WpsrFactory::category(['name' => 'Permission Category']);
    if (!$wpsr_category || !$wpsr_category->id) {
        throw new RuntimeException('Could not create permission category fixture.');
    }
    $wpsr_category_id = (int) $wpsr_category->id;

    $wpsr_tokens = [
        'review_id'          => (int) $wpsr_review->id,
        'testimonial_id'     => (int) $wpsr_testimonial->id,
        'category_id'        => $wpsr_category_id,
        'review_template_id' => $wpsr_review_template_id,
        'feed_template_id'   => $wpsr_feed_template_id,
        'chat_widget_id'     => $wpsr_chat_widget_id,
        'notification_id'    => $wpsr_notification_id,
    ];

    WpsrTest::assertNoDiagnostics('permission fixture setup');
    $wpsr_database_before = WpsrTest::databaseFingerprint();
    add_filter('rest_dispatch_request', $wpsr_controller_fuse, 10, 4);

    WP_CLI::log('WP Social Ninja core permissions — ' . count($wpsr_manifest) . ' routes / '
        . (count($wpsr_manifest) * 2) . ' anonymous+low-privilege cases ('
        . 'POST ' . $wpsr_method_counts['POST'] . ', PUT ' . $wpsr_method_counts['PUT']
        . ', DELETE ' . $wpsr_method_counts['DELETE'] . ', PATCH ' . $wpsr_method_counts['PATCH'] . ')'
        . ($wpsr_filter !== null ? ' — selected ' . count($wpsr_selected_manifest) . ' route(s), filter: ' . $wpsr_filter : '')
        . ' — low role caps: read only' . "\n");

    foreach ($wpsr_selected_manifest as $wpsr_entry) {
        $wpsr_route = $wpsr_replace_tokens($wpsr_entry['route'], $wpsr_tokens);
        $wpsr_params = $wpsr_replace_tokens($wpsr_entry['params'], $wpsr_tokens);
        if (preg_match('/\{[^}]+\}/', $wpsr_route) || preg_match('/\{[a-z_]+\}/i', json_encode($wpsr_params))) {
            throw new RuntimeException('Unresolved permission fixture token for ' . $wpsr_entry['declaration']);
        }

        foreach ([
            ['anonymous', 0, 401],
            ['low-privilege', $wpsr_user_id, 403],
        ] as $wpsr_identity) {
            WpsrTest::case($wpsr_entry['label'] . ': ' . $wpsr_identity[0], function () use (
                $wpsr_entry,
                $wpsr_route,
                $wpsr_params,
                $wpsr_identity,
                $wpsr_assert_denied
            ) {
                $wpsr_assert_denied(
                    $wpsr_entry,
                    $wpsr_route,
                    $wpsr_params,
                    $wpsr_identity[0],
                    $wpsr_identity[1],
                    $wpsr_identity[2]
                );
            });
        }
    }

    remove_filter('rest_dispatch_request', $wpsr_controller_fuse, 10);
    $wpsr_database_after = WpsrTest::databaseFingerprint();
    if ($wpsr_database_after !== $wpsr_database_before) {
        $wpsr_changed_tables = [];
        foreach ($wpsr_database_before as $wpsr_table => $wpsr_before_value) {
            if (!isset($wpsr_database_after[$wpsr_table]) || $wpsr_database_after[$wpsr_table] !== $wpsr_before_value) {
                $wpsr_changed_tables[] = $wpsr_table;
            }
        }
        foreach (array_diff_key($wpsr_database_after, $wpsr_database_before) as $wpsr_table => $wpsr_value) {
            $wpsr_changed_tables[] = $wpsr_table;
        }
        WpsrTest::fail('permission requests changed database state in: ' . implode(', ', array_unique($wpsr_changed_tables)));
    }
    if ($wpsr_controller_fuse_hits) {
        WpsrTest::fail('SECURITY: controller fuse observed unauthorized dispatch: '
            . implode(', ', $wpsr_controller_fuse_hits));
    }
} catch (Throwable $wpsr_exception) {
    remove_filter('rest_dispatch_request', $wpsr_controller_fuse, 10);
    WpsrTest::fail('permission runner failed: ' . $wpsr_exception->getMessage()
        . ' (' . basename($wpsr_exception->getFile()) . ':' . $wpsr_exception->getLine() . ')');
} finally {
    remove_filter('rest_dispatch_request', $wpsr_controller_fuse, 10);
    $wpsr_cleanup();
    WpsrTest::assertNoDiagnostics('permission fixture cleanup');
}

WP_CLI::log('Rejections observed: anonymous ' . $wpsr_rejection_counts['anonymous']
    . ' x 401/rest_forbidden; low-privilege ' . $wpsr_rejection_counts['low-privilege']
    . ' x 403/rest_forbidden');
WP_CLI::log('Controller fuse hits: ' . count($wpsr_controller_fuse_hits)
    . '; database state: ' . (($wpsr_database_before && $wpsr_database_before === $wpsr_database_after) ? 'unchanged' : 'FAILED'));
WpsrTest::finish('PERMISSIONS');
