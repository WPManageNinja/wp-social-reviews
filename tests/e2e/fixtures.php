<?php
/**
 * Phase 17 + 21 lab-only E2E identity, local fixtures, and exact teardown.
 */

if (!defined('WP_CLI') || !WP_CLI) {
    exit("E2E fixtures must run through WP-CLI.\n");
}

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\ReviewCategory;

$args = isset($args) && is_array($args) ? $args : [];
$mode = isset($args[0]) ? (string) $args[0] : '';
$runId = isset($args[1]) ? (string) $args[1] : '';
if (!in_array($mode, ['setup', 'cleanup'], true) || !preg_match('/^[a-z0-9]{8,40}$/', $runId)) {
    WP_CLI::error('Usage: fixtures.php setup|cleanup <safe-run-id>');
}

global $wpdb;
if (stripos(DB_NAME, 'test') === false || $wpdb->prefix === 'wp_' || !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
    WP_CLI::error('E2E fixtures refused outside disposable non-default-prefix cron-disabled lab.');
}

$optionKey = 'wpsr_test_e2e_' . $runId;
$marker = 'wpsr-e2e-' . $runId;
$protectedSuffixes = [
    'wpsr_reviews',
    'wpsr_caches',
    'wpsr_review_forms',
    'wpsr_optimize_images',
    'wpsr_review_categories',
    'wpsr_review_category_relationships',
    'posts',
    'postmeta',
    'users',
    'usermeta',
    'options',
];

$fingerprint = static function () use ($wpdb, $protectedSuffixes) {
    $result = [];
    foreach ($protectedSuffixes as $suffix) {
        $table = $wpdb->prefix . $suffix;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($found !== $table) {
            WP_CLI::error('E2E fingerprint table missing: ' . $table);
        }
        $safe = str_replace('`', '``', $table);
        $checksum = $wpdb->get_row("CHECKSUM TABLE `{$safe}`", ARRAY_A);
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$safe}`");
        if ($wpdb->last_error !== '' || !is_array($checksum)) {
            WP_CLI::error('E2E fingerprint failed for ' . $table . ': ' . $wpdb->last_error);
        }
        $result[$suffix] = [
            'count' => (int) $count,
            'checksum' => isset($checksum['Checksum']) ? (string) $checksum['Checksum'] : '',
        ];
    }
    $result['cron'] = hash('sha256', serialize(get_option('cron', [])));
    return $result;
};

$cleanup = static function ($strict = true) use ($optionKey, $marker, $wpdb, $fingerprint) {
    $state = get_option($optionKey, []);
    if (!is_array($state) || ($state['marker'] ?? '') !== $marker) {
        if ($strict) {
            WP_CLI::error('E2E fixture ownership record missing: ' . $optionKey);
        }
        return ['clean' => true, 'fingerprint' => $fingerprint()];
    }

    $userId = (int) ($state['user_id'] ?? 0);
    if ($userId) {
        $postIds = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_author = %d AND post_type IN ('wp_social_reviews','wpsr_social_chats','wpsr_reviews_notify','wpsr_custom_source')",
            $userId
        ));
        foreach (array_reverse(array_map('intval', (array) $postIds)) as $postId) {
            if (!$postId) {
                continue;
            }
            $post = get_post($postId);
            if (!$post || (int) $post->post_author !== $userId) {
                WP_CLI::error('Refusing to delete unowned E2E post ' . $postId);
            }
            wp_delete_post($postId, true);
        }
    }

    foreach ((array) ($state['review_ids'] ?? []) as $reviewId) {
        $reviewId = (int) $reviewId;
        if (!$reviewId) {
            continue;
        }
        $review = Review::find($reviewId);
        if ($review && strpos((string) $review->review_id, $marker) !== 0) {
            WP_CLI::error('Refusing to delete unowned E2E review ' . $reviewId);
        }
        $wpdb->delete($wpdb->prefix . 'wpsr_review_category_relationships', ['review_id' => $reviewId], ['%d']);
        if ($review) {
            $review->delete();
        }
    }

    $categoryRows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}wpsr_review_categories WHERE name LIKE %s ORDER BY id DESC",
        $wpdb->esc_like($marker) . '%'
    ), ARRAY_A);
    foreach ((array) $categoryRows as $categoryRow) {
        $categoryId = (int) ($categoryRow['id'] ?? 0);
        $categoryName = (string) ($categoryRow['name'] ?? '');
        if (!$categoryId) {
            continue;
        }
        if (strpos($categoryName, $marker) !== 0) {
            WP_CLI::error('Refusing to delete unowned E2E category ' . $categoryId);
        }
        $category = ReviewCategory::find($categoryId);
        if ($category) {
            $category->delete();
        }
    }

    foreach ((array) ($state['owned_options'] ?? []) as $ownedOption) {
        $ownedValue = get_option($ownedOption, null);
        if ($ownedValue !== null && strpos(wp_json_encode($ownedValue), $marker) === false) {
            WP_CLI::error('Refusing to delete unowned E2E option ' . $ownedOption);
        }
        delete_option($ownedOption);
    }

    if ($userId) {
        if (get_user_meta($userId, '_wpsr_e2e_fixture', true) !== $marker) {
            WP_CLI::error('Refusing to delete unowned E2E user ' . $userId);
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }

    delete_option($optionKey);

    $postLeaks = $userId ? (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type IN ('wp_social_reviews','wpsr_social_chats','wpsr_reviews_notify','wpsr_custom_source')",
        $userId
    )) : 0;
    $reviewLeaks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpsr_reviews WHERE review_id LIKE %s",
        $wpdb->esc_like($marker) . '%'
    ));
    $userLeaks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '_wpsr_e2e_fixture' AND meta_value = %s",
        $marker
    ));
    $optionLeaks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s OR option_value LIKE %s",
        $optionKey,
        '%' . $wpdb->esc_like($marker) . '%'
    ));
    $categoryLeaks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpsr_review_categories WHERE name LIKE %s",
        $wpdb->esc_like($marker) . '%'
    ));
    if ($postLeaks || $reviewLeaks || $userLeaks || $optionLeaks || $categoryLeaks) {
        WP_CLI::error("E2E cleanup leaked post={$postLeaks} review={$reviewLeaks} category={$categoryLeaks} user={$userLeaks} option={$optionLeaks}");
    }

    $after = $fingerprint();
    $before = isset($state['fingerprint']) && is_array($state['fingerprint']) ? $state['fingerprint'] : [];
    if ($before !== $after) {
        WP_CLI::error('E2E cleanup fingerprint mismatch. Before=' . wp_json_encode($before) . ' After=' . wp_json_encode($after));
    }

    return ['clean' => true, 'fingerprint' => $after];
};

if ($mode === 'cleanup') {
    $result = $cleanup(true);
    WP_CLI::log('WPSR_E2E_CLEANUP=' . wp_json_encode($result));
    return;
}

if (get_option($optionKey, null) !== null) {
    $cleanup(true);
}

$state = [
    'marker' => $marker,
    'token' => wp_generate_password(40, false, false),
    'fingerprint' => $fingerprint(),
    'review_ids' => [],
    'owned_options' => [],
    'user_id' => 0,
];
update_option($optionKey, $state, 'no');
$complete = false;
register_shutdown_function(static function () use (&$complete, $cleanup) {
    if (!$complete) {
        $cleanup(false);
    }
});

$googleSettingsKey = 'wpsr_reviews_google_settings';
if (get_option($googleSettingsKey, null) !== null) {
    WP_CLI::error('E2E requires absent local Google settings option; refusing to replace shared state.');
}
// Persist recoverable ownership before creating fixed-name option.
$state['owned_options'][] = $googleSettingsKey;
update_option($optionKey, $state, 'no');
update_option($googleSettingsKey, [
    $marker => ['place_id' => $marker . '-place', 'api_key' => ''],
], 'no');

$globalSettingsKey = 'wpsr_global_settings';
if (get_option($globalSettingsKey, null) !== null) {
    WP_CLI::error('E2E requires absent global settings option; refusing to replace shared state.');
}
// Translation flow creates this through real UI. Register ownership now so every
// failure path may delete it, but do not pre-create it or replace product state.
$state['owned_options'][] = $globalSettingsKey;
update_option($optionKey, $state, 'no');

$userLogin = 'wpsre2e_' . substr($runId, 0, 18);
$userPassword = wp_generate_password(32, true, true);
$userId = wp_insert_user([
    'user_login' => $userLogin,
    'user_pass' => $userPassword,
    'user_email' => $userLogin . '@example.test',
    'display_name' => 'WPSR E2E Admin',
    'role' => 'administrator',
    'meta_input' => [
        '_wpsr_e2e_fixture' => $marker,
    ],
]);
if (is_wp_error($userId) || !$userId) {
    WP_CLI::error('Could not create E2E admin: ' . (is_wp_error($userId) ? $userId->get_error_message() : 'unknown'));
}
$userId = (int) $userId;
$state['user_id'] = $userId;
update_option($optionKey, $state, 'no');
wp_set_current_user($userId);

$reviewSpecs = [
    'moderation_target' => ['status' => 0, 'title' => $marker . ' Pending Moderation', 'name' => $marker . ' Pending Author'],
    'moderation_control' => ['status' => 1, 'title' => $marker . ' Approved Control', 'name' => $marker . ' Control Author'],
    'template_review' => ['status' => 1, 'title' => $marker . ' Template Render Review', 'name' => $marker . ' Template Author', 'platform' => 'google'],
    'filter_custom' => ['status' => 1, 'title' => $marker . ' Custom Filter Control', 'name' => $marker . ' Custom Filter Author', 'platform' => 'custom'],
];
$reviewTokens = [];
$titles = [];
foreach ($reviewSpecs as $key => $spec) {
    $review = Review::create([
        'platform_name' => $spec['platform'] ?? 'google',
        'source_id' => $marker . '-' . $key . '-source',
        'review_id' => $marker . '-' . $key,
        'review_title' => $spec['title'],
        'reviewer_name' => $spec['name'],
        'reviewer_email' => $key . '@example.test',
        'reviewer_url' => 'https://example.test/' . $key,
        'reviewer_img' => '',
        'reviewer_text' => $marker . ' exact body for ' . $key,
        'review_time' => '2026-08-17 12:34:56',
        'rating' => $key === 'moderation_target' ? 4 : 5,
        'review_approved' => $spec['status'],
        'category' => '',
        'fields' => ['author_company' => $marker . ' Company'],
    ]);
    if (!$review || empty($review->id)) {
        WP_CLI::error('Could not create E2E review fixture ' . $key);
    }
    $reviewTokens[$key] = (int) $review->id;
    $titles[$key] = $spec['title'];
    $state['review_ids'][] = (int) $review->id;
    update_option($optionKey, $state, 'no');
}

$expiry = time() + HOUR_IN_SECONDS;
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$context = [
    'run_id' => $runId,
    'marker' => $marker,
    'lab_token' => $state['token'],
    'base_url' => untrailingslashit(home_url()),
    'admin_url' => admin_url('admin.php?page=wpsocialninja.php'),
    'state_url' => rest_url('wpsr-test-lab/v1/e2e-state/' . $runId),
    'cleanup_url' => rest_url('wpsr-test-lab/v1/e2e-cleanup/' . $runId),
    'host' => $host,
    'user_id' => $userId,
    'reviews' => $reviewTokens,
    'titles' => $titles,
    'cookies' => [
        ['name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($userId, $expiry, 'logged_in'), 'secure' => false],
        ['name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie($userId, $expiry, 'auth'), 'secure' => false],
        ['name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie($userId, $expiry, 'secure_auth'), 'secure' => true],
    ],
];

$complete = true;
WP_CLI::log('WPSR_E2E_CONTEXT=' . wp_json_encode($context));
