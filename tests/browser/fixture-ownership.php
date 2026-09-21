<?php
/**
 * Recoverable ownership helpers for browser fixtures.
 */

function wpsr_browser_persist_post_ownership($postId, &$state, $optionKey, $marker)
{
    $postId = (int) $postId;
    $post = $postId ? get_post($postId) : null;
    if (!$post || (string) $post->post_excerpt !== $marker) {
        throw new RuntimeException('Browser post ownership marker missing for ' . $postId);
    }

    $state['post_ids'][] = $postId;
    update_option($optionKey, $state, 'no');
    if (get_option($optionKey, []) !== $state) {
        throw new RuntimeException('Browser post ownership state did not persist for ' . $postId);
    }
}

function wpsr_browser_delete_owned_post($postId, $marker)
{
    $postId = (int) $postId;
    $post = $postId ? get_post($postId) : null;
    if (!$post || (string) $post->post_excerpt !== $marker) {
        return false;
    }

    wp_delete_post($postId, true);
    return true;
}
