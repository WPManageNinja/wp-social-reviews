<?php
class UnsafeAlbumHandler {
    public function handleAlbumPhoto() {
        // ruleid: wpsr.php.public-album-handler-without-guard
        do_action('wpsocialreviews/facebook_feed_handle_album_photo');
    }
}

class SafeAlbumHandler {
    public function handleAlbumPhoto() {
        check_ajax_referer('wpsr-ajax-nonce', 'security');
        // ok: wpsr.php.public-album-handler-without-guard
        do_action('wpsocialreviews/facebook_feed_handle_album_photo');
    }
}

class SafeGuardedAlbumHandler {
    public function handleAlbumPhoto() {
        if (!check_ajax_referer('wpsr-ajax-nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Security validation failed.'], 403);
        }

        if (!(new ShortcodeHandler())->isPublicRenderableTemplate(absint($_REQUEST['template_id']))) {
            wp_send_json_error(['message' => 'Invalid template.'], 403);
        }

        // ok: wpsr.php.public-album-handler-without-guard
        do_action('wpsocialreviews/facebook_feed_handle_album_photo');
    }
}
