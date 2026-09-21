<?php
/** Phase 26 disposable-lab fixtures and exact database fingerprint guard. */

$args = isset($args) && is_array($args) ? $args : [];
$mode = isset($args[0]) ? (string) $args[0] : '';
$runId = isset($args[1]) ? (string) $args[1] : '';
if (!in_array($mode, ['setup', 'fingerprint', 'cleanup'], true) || !preg_match('/^[a-z0-9]{8,40}$/', $runId)) {
    WP_CLI::error('Usage: fixtures.php setup|fingerprint|cleanup <safe-run-id>');
}

global $wpdb;
if (stripos(DB_NAME, 'test') === false || $wpdb->prefix === 'wp_' || !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
    WP_CLI::error('Public-input fixtures refused outside disposable non-default-prefix cron-disabled lab.');
}
if (defined('WPSOCIALREVIEWS_PRO')) {
    WP_CLI::error('Public-input fixtures require core-alone lab; Pro is active.');
}

$stateKey = 'wpsr_fuzz_' . $runId;
$marker = 'wpsr-fuzz-' . $runId;

$fingerprint = static function () use ($wpdb) {
    $tables = $wpdb->get_col("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    if ($wpdb->last_error !== '' || empty($tables)) {
        WP_CLI::error('Public-input fingerprint could not discover database tables: ' . $wpdb->last_error);
    }
    sort($tables, SORT_STRING);
    $result = [];
    foreach ($tables as $table) {
        $safe = str_replace('`', '``', $table);
        $checksum = $wpdb->get_row("CHECKSUM TABLE `{$safe}`", ARRAY_A);
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$safe}`");
        if ($wpdb->last_error !== '' || !is_array($checksum)) {
            WP_CLI::error('Public-input fingerprint failed for ' . $table . ': ' . $wpdb->last_error);
        }
        $result[$table] = [
            'count' => (int) $count,
            'checksum' => isset($checksum['Checksum']) ? (string) $checksum['Checksum'] : '',
        ];
    }
    return $result;
};

$cleanup = static function ($strict = true) use ($stateKey, $marker, $fingerprint, $wpdb) {
    $state = get_option($stateKey, null);
    if (!is_array($state) || ($state['marker'] ?? '') !== $marker) {
        if ($strict) {
            WP_CLI::error('Public-input fixture ownership record missing: ' . $stateKey);
        }
        return ['clean' => true, 'fingerprint' => $fingerprint()];
    }
    foreach ((array) ($state['post_ids'] ?? []) as $postId) {
        $post = get_post((int) $postId);
        if ($post && ($post->post_type !== 'wp_social_reviews' || strpos((string) $post->post_title, $marker) !== 0)) {
            WP_CLI::error('Public-input cleanup refused unowned post ' . (int) $postId);
        }
        if ($post) {
            wp_delete_post((int) $postId, true);
        }
    }
    $delayedIds = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s",
        'wp_social_reviews',
        $wpdb->esc_like($marker) . '%'
    ));
    foreach ($delayedIds as $postId) {
        wp_delete_post((int) $postId, true);
    }
    if (!empty($state['owned_youtube_option'])) {
        $value = get_option('wpsr_youtube_verification_configs', null);
        if ($value !== null && $value !== []) {
            WP_CLI::error('Public-input cleanup refused changed YouTube option.');
        }
        if ($value === []) {
            delete_option('wpsr_youtube_verification_configs');
        }
    }
    delete_option($stateKey);
    $leaks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE %s",
        $wpdb->esc_like($marker) . '%'
    ));
    if ($leaks !== 0 || get_option($stateKey, null) !== null) {
        WP_CLI::error('Public-input cleanup leaked owned fixtures.');
    }
    $after = $fingerprint();
    if (($state['baseline'] ?? []) !== $after) {
        WP_CLI::error('Public-input cleanup fingerprint mismatch. Before=' . wp_json_encode($state['baseline'] ?? []) . ' After=' . wp_json_encode($after));
    }
    return ['clean' => true, 'fingerprint' => $after];
};

if ($mode === 'fingerprint') {
    WP_CLI::log('WPSR_FUZZ_FINGERPRINT=' . wp_json_encode($fingerprint()));
    return;
}
if ($mode === 'cleanup') {
    WP_CLI::log('WPSR_FUZZ_CLEANUP=' . wp_json_encode($cleanup(true)));
    return;
}

if (get_option($stateKey, null) !== null) {
    $cleanup(true);
}
$state = [
    'marker' => $marker,
    'baseline' => $fingerprint(),
    'post_ids' => [],
    'owned_youtube_option' => false,
];
add_option($stateKey, $state, '', 'no');
$complete = false;
register_shutdown_function(static function () use (&$complete, $cleanup) {
    if (!$complete) {
        $cleanup(false);
    }
});

$createdPosts = [];
foreach ([
    'review' => ['status' => 'publish', 'suffix' => 'reviews'],
    'youtube' => ['status' => 'publish', 'suffix' => 'youtube'],
    'draft' => ['status' => 'draft', 'suffix' => 'draft'],
] as $name => $spec) {
    $postId = wp_insert_post([
        'post_type' => 'wp_social_reviews',
        'post_status' => $spec['status'],
        'post_title' => $marker . '-' . $spec['suffix'],
    ], true);
    if (is_wp_error($postId) || empty($postId)) {
        WP_CLI::error('Public-input fixture post creation failed: ' . $name);
    }
    $createdPosts[$name] = (int) $postId;
    $state['post_ids'][] = (int) $postId;
    update_option($stateKey, $state, 'no');
}
$reviewId = $createdPosts['review'];
$youtubeId = $createdPosts['youtube'];
$draftId = $createdPosts['draft'];

$reviewMeta = [
    'platform' => ['custom'],
    'selectedBusinesses' => [$marker . '-source'],
    'template' => 'grid1',
    'templateType' => 'grid',
    'pagination_type' => 'load_more',
    'paginate' => 1,
    'paginate_number' => ['desktop' => 1, 'mobile' => 1],
    'totalReviewsNumber' => ['desktop' => 1, 'mobile' => 1],
];
$youtubeMeta = [
    'feed_settings' => [
        'platform' => 'youtube',
        'source_settings' => ['feed_type' => 'channel_feed', 'channel_id' => ''],
        'video_settings' => ['use_fluent_player' => 'true', 'fluent_player_layers_at_end' => 'false'],
    ],
];
update_post_meta((int) $reviewId, '_wpsr_template_config', wp_json_encode($reviewMeta));
update_post_meta((int) $youtubeId, '_wpsr_template_config', wp_json_encode($youtubeMeta));
update_post_meta((int) $draftId, '_wpsr_template_config', wp_json_encode($reviewMeta));
if (get_option('wpsr_youtube_verification_configs', null) === null) {
    $state['owned_youtube_option'] = true;
    update_option($stateKey, $state, 'no');
    if (!add_option('wpsr_youtube_verification_configs', [], '', 'no')) {
        WP_CLI::error('Public-input fixture could not create owned YouTube option.');
    }
}
wp_set_current_user(0);
$nonce = wp_create_nonce('wpsr-ajax-nonce');
$complete = true;
WP_CLI::log('WPSR_FUZZ_SETUP=' . wp_json_encode([
    'run_id' => $runId,
    'nonce' => $nonce,
    'review_id' => (int) $reviewId,
    'youtube_id' => (int) $youtubeId,
    'draft_id' => (int) $draftId,
    'fixture_fingerprint' => $fingerprint(),
]));
