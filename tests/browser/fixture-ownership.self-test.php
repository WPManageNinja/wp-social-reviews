<?php
/**
 * Focused fault-injection proof for browser fixture post ownership.
 */

$wpsrOptions = [];
$wpsrPosts = [];
$wpsrDeletedPosts = [];

function get_post($postId)
{
    global $wpsrPosts;
    return $wpsrPosts[$postId] ?? null;
}

function update_option($key, $value, $autoload = null)
{
    global $wpsrOptions;
    $wpsrOptions[$key] = $value;
    return true;
}

function get_option($key, $default = false)
{
    global $wpsrOptions;
    return $wpsrOptions[$key] ?? $default;
}

function wp_delete_post($postId, $forceDelete)
{
    global $wpsrDeletedPosts;
    $wpsrDeletedPosts[] = $postId;
    return true;
}

require __DIR__ . '/fixture-ownership.php';

$fail = static function ($message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$marker = 'wpsr-browser-fault-proof';
$optionKey = 'wpsr_test_browser_faultproof';
$state = ['marker' => $marker, 'post_ids' => []];
update_option($optionKey, $state, 'no');

// Simulate wp_insert_post(post_excerpt => marker), then fail first config write.
$postId = 41;
$wpsrPosts[$postId] = (object) ['post_excerpt' => $marker];
try {
    wpsr_browser_persist_post_ownership($postId, $state, $optionKey, $marker);
    throw new RuntimeException('injected first post-config failure');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'injected first post-config failure') {
        throw $exception;
    }
}
foreach (array_reverse(get_option($optionKey)['post_ids']) as $ownedPostId) {
    if (!wpsr_browser_delete_owned_post($ownedPostId, $marker)) {
        $fail('owned partial post refused cleanup');
    }
}
if ($wpsrDeletedPosts !== [$postId]) {
    $fail('faulted partial post was not deleted');
}

$mismatchId = 42;
$wpsrPosts[$mismatchId] = (object) ['post_excerpt' => 'different-owner'];
if (wpsr_browser_delete_owned_post($mismatchId, $marker) !== false) {
    $fail('ownership mismatch was accepted');
}
if ($wpsrDeletedPosts !== [$postId]) {
    $fail('ownership mismatch deleted unrelated post');
}

$source = file_get_contents(__DIR__ . '/fixtures.php');
$persistAt = strpos($source, 'wpsr_browser_persist_post_ownership($postId');
$configAt = strpos($source, '$postModel->updatePostMeta($postId');
if ($persistAt === false || $configAt === false || $persistAt > $configAt) {
    $fail('fixture does not persist ownership before post config');
}
if (strpos($source, "'post_excerpt' => \$marker") === false) {
    $fail('post insertion does not establish atomic ownership marker');
}

echo "Browser fixture ownership fault proof passed.\n";
