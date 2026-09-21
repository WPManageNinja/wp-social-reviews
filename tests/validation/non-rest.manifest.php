<?php
/** Phase 10 non-REST input-family inventory; behavior remains outside Phase 10 where noted. */
return [
    [
        'surface' => 'shortcode [wp_social_ninja]',
        'handler' => 'app/Hooks/Handlers/ShortcodeHandler.php::makeShortcode',
        'inputs' => ['ids-object-scope', 'action-status-enum'],
        'disposition' => 'deferred',
        'reason' => 'Public shortcode visibility/object handling belongs Phase 18; source uses absint, platform allowlist, and public-renderable template guard.',
    ],
    [
        'surface' => 'wp_ajax[_nopriv]_wpsr_get_more_feeds',
        'handler' => 'app/Hooks/Handlers/ShortcodeHandler.php::handleLoadMoreAjax',
        'inputs' => ['ids-object-scope', 'pagination-bounds', 'order-sort', 'search-like', 'action-status-enum'],
        'disposition' => 'deferred',
        'reason' => 'Public nonce/rate-limit/template visibility behavior is Phase 18. Phase 10 covers reused sort map, wildcard escaping, pagination model, and rendered review output without firing public AJAX.',
    ],
    [
        'surface' => 'wp_ajax[_nopriv]_wpsr_youtube_popup_feed',
        'handler' => 'app/Hooks/Handlers/YoutubeTemplateHandler.php::renderPopupFeed',
        'inputs' => ['ids-object-scope'],
        'disposition' => 'deferred',
        'reason' => 'Public template visibility and remote cached feed rendering belong Phase 18; no real HTTP is allowed.',
    ],
    [
        'surface' => 'wp_ajax[_nopriv]_wpsr_youtube_fluent_player',
        'handler' => 'app/Hooks/Handlers/YoutubeTemplateHandler.php::renderFluentPlayer',
        'inputs' => ['ids-object-scope', 'pagination-bounds', 'text-markup'],
        'disposition' => 'deferred',
        'reason' => 'Public nonce/template visibility and optional sibling-player rendering belong Phase 18; Pro/sibling behavior excluded.',
    ],
    [
        'surface' => 'wp_ajax[_nopriv]_wpsr_facebook_album_photo',
        'handler' => 'app/Hooks/Handlers/FacebookFeedTemplateHandler.php::handleAlbumPhoto',
        'inputs' => ['ids-object-scope'],
        'disposition' => 'deferred',
        'reason' => 'Core delegates implementation through hook; public behavior and remote feed data belong Phase 18 and Pro is excluded.',
    ],
    [
        'surface' => 'wp_ajax[_nopriv]_wpsr_resize_images',
        'handler' => 'app/Services/Platforms/ImageOptimizationHandler.php::savePhotos',
        'inputs' => ['ids-object-scope', 'filenames-uploads', 'nested-settings'],
        'disposition' => 'deferred',
        'reason' => 'Remote image URLs and generated media/user filenames can write files only after nonce plus public-template guard. Full resize/download/filesystem workflow belongs Phase 14/18; outbound HTTP and file creation are prohibited here.',
    ],
    [
        'surface' => 'wp_ajax[_nopriv]_wpsr_review_resize_images',
        'handler' => 'app/Services/Platforms/ReviewImageOptimizationHandler.php::savePhotos',
        'inputs' => ['ids-object-scope', 'filenames-uploads', 'nested-settings'],
        'disposition' => 'deferred',
        'reason' => 'Review media IDs/source names derive generated paths after public-template guard. Full resize/download/filesystem workflow belongs Phase 14/18; existing Phase 8 covers local DB eviction only.',
    ],
    [
        'surface' => 'admin localized action=wpsr_upload_image',
        'handler' => 'no core wp_ajax_wpsr_upload_image registration',
        'inputs' => ['filenames-uploads'],
        'disposition' => 'excluded',
        'reason' => 'Core exposes URL for add-on UI but registers no multipart/$_FILES upload handler. Pro tests are out of scope; no core upload payload can be behavior-probed in Phase 10.',
    ],
];
