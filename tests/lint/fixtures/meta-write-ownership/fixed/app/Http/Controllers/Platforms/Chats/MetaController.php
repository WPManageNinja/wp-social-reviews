<?php
/** Minimal faithful Phase 24 copy from app/Http/Controllers/Platforms/Chats/MetaController.php. */
class Phase24Fixture
{
    function isChatWidget($postId)
    {
        return $postId && get_post_type($postId) === self::CHAT_POST_TYPE;
    }

    function update(Request $request, $postId)
    {
        $postId = absint($postId);

        if (!$this->isChatWidget($postId)) {
            return $this->sendError([
                'message' => __('Chat widget not found.', 'wp-social-reviews')
            ], 404);
        }

        $settings = json_decode($request->get('args'), true);
        $settings = wp_unslash($settings);
        $settings = $this->sanitizeChatSettings($settings);
        do_action('wpsocialreviews/update_chat_settings', $postId, $settings);
    }
}
