<?php
/**
 * Phase 18 core public/unauthenticated surface inventory.
 *
 * Entry points are source-owned core registrations. Sibling/add-on inventory is
 * intentionally absent from foundation.
 */
return [
    'ajax' => [
        [
            'hook' => 'wpsr_get_more_feeds',
            'handler' => 'app/Hooks/Handlers/ShortcodeHandler.php::handleLoadMoreAjax',
            'guard' => 'nonce + public template post type/status/password + review request budgets',
            'runtime_test' => 'Phase 18 load-more valid anonymous read returns exact approved owned scope and cleans rate state',
        ],
        [
            'hook' => 'wpsr_youtube_popup_feed',
            'handler' => 'app/Hooks/Handlers/YoutubeTemplateHandler.php::renderPopupFeed',
            'guard' => 'nonce + public template post type/status/password',
            'runtime_test' => 'Phase 18 YouTube popup valid token rejects hidden object with no leakage',
        ],
        [
            'hook' => 'wpsr_youtube_fluent_player',
            'handler' => 'app/Hooks/Handlers/YoutubeTemplateHandler.php::renderFluentPlayer',
            'guard' => 'nonce + public template post type/status/password',
            'runtime_test' => 'Phase 18 YouTube player valid token stays template-scoped and returns exact unavailable response',
        ],
        [
            'hook' => 'wpsr_facebook_album_photo',
            'handler' => 'app/Hooks/Handlers/FacebookFeedTemplateHandler.php::handleAlbumPhoto',
            'guard' => 'nonce + public template post type/status/password before the extension hook, then hard die',
            'runtime_test' => 'Phase 18 Facebook album core dispatch requires nonce and public template',
        ],
        [
            'hook' => 'wpsr_resize_images',
            'handler' => 'app/Services/Platforms/ImageOptimizationHandler.php::savePhotos',
            'guard' => 'frontend/admin nonce + public template post type/status/password + handler platform match',
            'runtime_test' => 'Phase 18 feed image valid nonce is object/platform scoped with harmless empty fixture',
        ],
        [
            'hook' => 'wpsr_review_resize_images',
            'handler' => 'app/Services/Platforms/ReviewImageOptimizationHandler.php::savePhotos',
            'guard' => 'frontend/admin nonce + public template post type/status/password',
            'runtime_test' => 'Phase 18 review image valid nonce rejects hidden object as full no-op',
        ],
    ],
    'shortcodes' => [
        [
            'tag' => 'wp_social_ninja',
            'handler' => 'app/Hooks/Handlers/ShortcodeHandler.php::makeShortcode',
            'guard' => 'platform allowlist + public template post type/status/password',
            'runtime_test' => 'Phase 18 public shortcode renders exact scoped escaped output and safe empty invalid IDs',
        ],
    ],
    'template_redirect' => [
        [
            'handler' => 'app/Hooks/Handlers/ChatHandler.php::maybeHasChats',
            'guard' => 'published wpsr_social_chats query + page/device/language match',
            'runtime_test' => 'Phase 18 template_redirect renders exact published chat only with escaped owned data',
        ],
        [
            'handler' => 'app/Hooks/Handlers/NotificationHandler.php::maybeHasNotification',
            'guard' => 'published wpsr_reviews_notify query + page/device/language match; core has no renderer',
            'runtime_test' => 'Phase 18 notification template_redirect is exact core-alone no-output read',
        ],
    ],
    'public_rest' => [],
    'rewrite_query_vars' => [],
    'frontend_flows' => [
        [
            'source' => 'resources/public/wp-social-review.js + resources/public/reviews-sort-toolbar.js',
            'entry' => 'wpsr_get_more_feeds',
            'classification' => 'core load-more; unavailable visitor filters are ignored in core-alone mode',
        ],
        [
            'source' => 'resources/public/youtube-popup.js',
            'entry' => 'wpsr_youtube_popup_feed + wpsr_youtube_fluent_player',
            'classification' => 'core public cached-feed/player reads',
        ],
        [
            'source' => 'resources/public/image_resizer.js + resources/public/reviews-image-resizer.js',
            'entry' => 'wpsr_resize_images + wpsr_review_resize_images',
            'classification' => 'core remote-cache image materialization; no multipart upload input',
        ],
        [
            'source' => 'resources/public/wpsr-fb-album.js',
            'entry' => 'wpsr_facebook_album_photo',
            'classification' => 'core dispatch shell delegates to an extension hook',
        ],
    ],
];
