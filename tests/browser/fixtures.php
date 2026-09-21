<?php
/**
 * Lab-only browser identity/fixture setup and exact cleanup.
 */

if (!defined('WP_CLI') || !WP_CLI) {
    exit("Browser fixtures must run through WP-CLI.\n");
}

use WPSocialReviews\App\Models\Post as WpsrPost;
use WPSocialReviews\App\Models\ReviewForm;
use WPSocialReviews\App\Models\Template;
use WPSocialReviews\App\Services\Platforms\Chats\Config as ChatConfig;
use WPSocialReviews\App\Services\Platforms\Feeds\Config as FeedConfig;

require_once __DIR__ . '/fixture-ownership.php';

$args = isset($args) && is_array($args) ? $args : [];
$mode = isset($args[0]) ? (string) $args[0] : '';
$runId = isset($args[1]) ? (string) $args[1] : '';
if (!in_array($mode, ['setup', 'cleanup'], true) || !preg_match('/^[a-z0-9]{8,40}$/', $runId)) {
    WP_CLI::error('Usage: fixtures.php setup|cleanup <safe-run-id>');
}

global $wpdb;
if (stripos(DB_NAME, 'test') === false || $wpdb->prefix === 'wp_' || !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
    WP_CLI::error('Browser fixtures refused outside disposable non-default-prefix cron-disabled lab.');
}

$optionKey = 'wpsr_test_browser_' . $runId;
$marker = 'wpsr-browser-' . $runId;
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
];

$fingerprint = static function () use ($wpdb, $protectedSuffixes) {
    $result = [];
    foreach ($protectedSuffixes as $suffix) {
        $table = $wpdb->prefix . $suffix;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($found !== $table) {
            WP_CLI::error('Browser fingerprint table missing: ' . $table);
        }
        $safe = str_replace('`', '``', $table);
        $checksum = $wpdb->get_row("CHECKSUM TABLE `{$safe}`", ARRAY_A);
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$safe}`");
        if ($wpdb->last_error !== '' || !is_array($checksum)) {
            WP_CLI::error('Browser fingerprint failed for ' . $table . ': ' . $wpdb->last_error);
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
    if (!is_array($state) || empty($state['marker']) || $state['marker'] !== $marker) {
        if ($strict) {
            WP_CLI::error('Browser fixture ownership record missing: ' . $optionKey);
        }
        return ['clean' => true, 'fingerprint' => $fingerprint()];
    }

    foreach (array_reverse((array) ($state['post_ids'] ?? [])) as $postId) {
        $postId = (int) $postId;
        if (!$postId) {
            continue;
        }
        if (!wpsr_browser_delete_owned_post($postId, $marker)) {
            WP_CLI::error('Refusing to delete unowned browser post ' . $postId);
        }
    }

    foreach ((array) ($state['review_form_ids'] ?? []) as $formId) {
        $formId = (int) $formId;
        if (!$formId) {
            continue;
        }
        $form = ReviewForm::find($formId);
        if ($form && strpos((string) $form->slug, $marker) !== 0) {
            WP_CLI::error('Refusing to delete unowned browser review form ' . $formId);
        }
        if ($form) {
            $form->delete();
        }
    }

    $userId = (int) ($state['user_id'] ?? 0);
    if ($userId) {
        if (get_user_meta($userId, '_wpsr_browser_fixture', true) !== $marker) {
            WP_CLI::error('Refusing to delete unowned browser user ' . $userId);
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }

    delete_option($optionKey);

    $postLeaks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wpsr_browser_fixture' AND meta_value = %s",
        $marker
    ));
    $formTable = $wpdb->prefix . 'wpsr_review_forms';
    $formLeaks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$formTable} WHERE slug LIKE %s",
        $wpdb->esc_like($marker) . '%'
    ));
    $userLeaks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '_wpsr_browser_fixture' AND meta_value = %s",
        $marker
    ));
    if ($postLeaks || $formLeaks || $userLeaks) {
        WP_CLI::error("Browser cleanup leaked post={$postLeaks} form={$formLeaks} user={$userLeaks}");
    }

    $after = $fingerprint();
    $before = isset($state['fingerprint']) && is_array($state['fingerprint']) ? $state['fingerprint'] : [];
    if ($before !== $after) {
        WP_CLI::error('Browser cleanup fingerprint mismatch. Before=' . wp_json_encode($before) . ' After=' . wp_json_encode($after));
    }

    return ['clean' => true, 'fingerprint' => $after];
};

if ($mode === 'cleanup') {
    $result = $cleanup(true);
    WP_CLI::log('WPSR_BROWSER_CLEANUP=' . wp_json_encode($result));
    return;
}

if (get_option($optionKey, null) !== null) {
    $cleanup(true);
}

$state = [
    'marker' => $marker,
    'fingerprint' => $fingerprint(),
    'post_ids' => [],
    'review_form_ids' => [],
    'user_id' => 0,
];
update_option($optionKey, $state, 'no');
$complete = false;
register_shutdown_function(static function () use (&$complete, $cleanup) {
    if (!$complete) {
        $cleanup(false);
    }
});

$userLogin = 'wpsrb_' . substr($runId, 0, 20);
$userPassword = wp_generate_password(32, true, true);
$userId = wp_insert_user([
    'user_login' => $userLogin,
    'user_pass' => $userPassword,
    'user_email' => $userLogin . '@example.test',
    'display_name' => 'WPSR Browser Admin',
    'role' => 'administrator',
    'meta_input' => [
        '_wpsr_browser_fixture' => $marker,
    ],
]);
if (is_wp_error($userId) || !$userId) {
    WP_CLI::error('Could not create browser admin: ' . (is_wp_error($userId) ? $userId->get_error_message() : 'unknown'));
}
$userId = (int) $userId;
$state['user_id'] = $userId;
update_option($optionKey, $state, 'no');

wp_set_current_user($userId);
$postModel = new WpsrPost();
$templateModel = new Template();
$tokens = [];
$titles = [];

$createPost = static function ($key, $title, $postType, $content, $config, $platform) use (&$state, $optionKey, $marker, $postModel, &$tokens, &$titles) {
    $postId = wp_insert_post([
        'post_author' => get_current_user_id(),
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => $postType,
        // Atomic insertion field: cleanup can verify ownership before config exists.
        'post_excerpt' => $marker,
    ], true);
    if (is_wp_error($postId) || !$postId) {
        WP_CLI::error('Could not create browser post ' . $key);
    }
    $postId = (int) $postId;
    wpsr_browser_persist_post_ownership($postId, $state, $optionKey, $marker);
    update_post_meta($postId, '_wpsr_browser_fixture', $marker);
    $postModel->updatePostMeta($postId, $config, $platform);
    $tokens[$key] = $postId;
    $titles[$key . '_title'] = $title;
};

$createPost('review_template', 'WPSR Browser Review ' . $runId, 'wp_social_reviews', 'google', $templateModel->getPlatformDefaultConfig('google'), 'google');
$createPost('chat_widget', 'WPSR Browser Chat ' . $runId, 'wpsr_social_chats', 'social chats', ChatConfig::formatConfig(), 'chat');

$feedPlatforms = [
    'twitter_template' => 'twitter',
    'youtube_template' => 'youtube',
    'instagram_template' => 'instagram',
    'facebook_feed_template' => 'facebook_feed',
    'tiktok_template' => 'tiktok',
];
foreach ($feedPlatforms as $key => $platform) {
    $createPost($key, 'WPSR Browser ' . ucwords(str_replace('_', ' ', $platform)) . ' ' . $runId, 'wp_social_reviews', $platform, $templateModel->getPlatformDefaultConfig($platform), $platform);
}
$createPost('social_wall_template', 'WPSR Browser Social Wall ' . $runId, 'wp_social_reviews', 'social_wall', FeedConfig::formatSocialWallConfig([], []), 'social_wall');
$createPost('custom_source', 'WPSR Browser Custom Source ' . $runId, 'wpsr_custom_source', 'custom', [
    'source_settings' => [
        'name' => $marker,
        'label' => 'WPSR Browser Custom Source',
        'type' => 'custom',
    ],
], 'custom');

$form = ReviewForm::create([
    'title' => 'WPSR Browser Review Form ' . $runId,
    'slug' => $marker . '-form',
    'schema' => [],
    'settings' => [],
    'status' => 'active',
    'created_by' => $userId,
]);
if (!$form || empty($form->id)) {
    WP_CLI::error('Could not create browser review-form fixture.');
}
$tokens['review_form'] = (int) $form->id;
$titles['review_form_title'] = (string) $form->title;
$state['review_form_ids'][] = (int) $form->id;
update_option($optionKey, $state, 'no');

$expiry = time() + HOUR_IN_SECONDS;
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$context = [
    'run_id' => $runId,
    'marker' => $marker,
    'base_url' => untrailingslashit(home_url()),
    'admin_url' => admin_url('admin.php?page=wpsocialninja.php'),
    'host' => $host,
    'user_id' => $userId,
    'tokens' => $tokens,
    'titles' => $titles,
    'cookies' => [
        ['name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($userId, $expiry, 'logged_in'), 'secure' => false],
        ['name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie($userId, $expiry, 'auth'), 'secure' => false],
        ['name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie($userId, $expiry, 'secure_auth'), 'secure' => true],
    ],
];

$complete = true;
WP_CLI::log('WPSR_BROWSER_CONTEXT=' . wp_json_encode($context));
