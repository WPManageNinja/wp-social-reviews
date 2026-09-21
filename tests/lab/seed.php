<?php
/**
 * Idempotent Phase 1 fixtures. Runs only inside disposable Docker lab.
 */
if (!defined('WP_CLI') || !WP_CLI) {
    exit("Lab seed must run via WP-CLI.\n");
}

global $wpdb;
$fixtureIds = get_option('wpsr_test_phase1_fixture_ids', []);
$fixtureContracts = [
    'review_template' => ['title' => 'WPSR Phase 1 Review Template', 'type' => 'wp_social_reviews', 'content' => 'google'],
    'feed_template'   => ['title' => 'WPSR Phase 1 Feed Template', 'type' => 'wp_social_reviews', 'content' => 'youtube'],
    'chat_widget'     => ['title' => 'WPSR Phase 1 Chat Widget', 'type' => 'wpsr_social_chats', 'content' => 'social chats'],
    'page'            => ['title' => 'WPSR Phase 1 Search Page', 'type' => 'page', 'content' => 'Smoke fixture'],
];
$optionExists = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
    'wpsr_test_phase1_fixture_ids'
)) === 1;
if ($wpdb->last_error !== '') {
    WP_CLI::error('Could not verify Phase 1 fixture ownership: ' . $wpdb->last_error);
}
$valid = $optionExists && is_array($fixtureIds) && count($fixtureIds) === count($fixtureContracts);
$validatedIds = [];
foreach ($fixtureContracts as $key => $expected) {
    $id = isset($fixtureIds[$key]) ? (int) $fixtureIds[$key] : 0;
    $post = $id > 0 ? get_post($id) : null;
    if (!$post || $post->post_type !== $expected['type']
        || $post->post_title !== $expected['title']
        || $post->post_content !== $expected['content']
        || $post->post_status !== 'publish'
        || (int) $post->post_author !== 0) {
        $valid = false;
        continue;
    }
    $validatedIds[] = $id;
}
if (count($validatedIds) !== count(array_unique($validatedIds))) {
    $valid = false;
}
if ($valid) {
    WP_CLI::log('Phase 1 fixtures already present.');
    return;
}
if ($optionExists) {
    WP_CLI::error('Phase 1 fixture identity changed; refusing replacement. Reset disposable lab.');
}
$fixtureTitles = array_column($fixtureContracts, 'title');
$placeholders = implode(',', array_fill(0, count($fixtureTitles), '%s'));
$collisions = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title IN ({$placeholders})",
    $fixtureTitles
));
if ($wpdb->last_error !== '' || $collisions !== 0) {
    WP_CLI::error('Pre-existing Phase 1 fixture titles found; refusing replacement. Reset disposable lab.');
}

$insert = function ($title, $type, $content = '') {
    $id = wp_insert_post([
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => $type,
    ], true);
    if (is_wp_error($id) || !$id) {
        WP_CLI::error('Could not create lab fixture: ' . (is_wp_error($id) ? $id->get_error_message() : $title));
    }
    return (int) $id;
};

$postModel = new \WPSocialReviews\App\Models\Post();
$templateModel = new \WPSocialReviews\App\Models\Template();

$reviewId = $insert('WPSR Phase 1 Review Template', 'wp_social_reviews', 'google');
$postModel->updatePostMeta($reviewId, $templateModel->getPlatformDefaultConfig('google'), 'google');

$feedId = $insert('WPSR Phase 1 Feed Template', 'wp_social_reviews', 'youtube');
$postModel->updatePostMeta($feedId, $templateModel->getPlatformDefaultConfig('youtube'), 'youtube');

$chatId = $insert('WPSR Phase 1 Chat Widget', 'wpsr_social_chats', 'social chats');
$chatConfig = \WPSocialReviews\App\Services\Platforms\Chats\Config::formatConfig();
$chatConfig['chat_settings']['created_from_onboarding'] = false;
$postModel->updateConfigMeta($chatId, serialize($chatConfig));

$pageId = $insert('WPSR Phase 1 Search Page', 'page', 'Smoke fixture');

update_option('wpsr_test_phase1_fixture_ids', [
    'review_template' => $reviewId,
    'feed_template'   => $feedId,
    'chat_widget'     => $chatId,
    'page'            => $pageId,
], 'no');

WP_CLI::success('Created Phase 1 fixtures.');
