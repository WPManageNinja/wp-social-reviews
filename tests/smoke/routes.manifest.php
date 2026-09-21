<?php
/**
 * Core 4.4.0 GET manifest: all 27 declarations in app/Http/Routes/api.php.
 *
 * Query variants mirror resources/admin calls. No core GET declaration was
 * identified as destructive. Review-meta GET may canonicalize category IDs;
 * it runs only against a test-owned lab fixture and protected-table guards
 * still apply.
 */
return [
    ['declaration' => 'platforms:/', 'label' => 'platform catalog', 'route' => '/platforms'],
    ['declaration' => 'platforms:/enabled', 'label' => 'enabled platforms', 'route' => '/platforms/enabled'],
    ['declaration' => 'platforms:/get-statuses', 'label' => 'platform statuses', 'route' => '/platforms/get-statuses'],

    ['declaration' => 'platforms/reviews:/configs', 'label' => 'review config — google', 'route' => '/platforms/reviews/configs', 'params' => ['platform' => 'google']],
    ['declaration' => 'platforms/reviews:/configs', 'label' => 'review config — airbnb', 'route' => '/platforms/reviews/configs', 'params' => ['platform' => 'airbnb']],
    ['declaration' => 'platforms/reviews:/configs', 'label' => 'review config — connected-products pagination', 'route' => '/platforms/reviews/configs', 'params' => ['platform' => 'woocommerce', 'page' => 1, 'per_page' => 10]],

    ['declaration' => 'platforms/feeds:/configs', 'label' => 'feed config — youtube', 'route' => '/platforms/feeds/configs', 'params' => ['platform' => 'youtube']],
    ['declaration' => 'platforms/feeds:/configs', 'label' => 'feed config — twitter', 'route' => '/platforms/feeds/configs', 'params' => ['platform' => 'twitter']],
    ['declaration' => 'platforms/feeds:/configs', 'label' => 'feed config — instagram', 'route' => '/platforms/feeds/configs', 'params' => ['platform' => 'instagram']],
    ['declaration' => 'platforms/feeds:/configs', 'label' => 'feed config — facebook', 'route' => '/platforms/feeds/configs', 'params' => ['platform' => 'facebook_feed']],
    ['declaration' => 'platforms/feeds:/configs', 'label' => 'feed config — TikTok client variation', 'route' => '/platforms/feeds/configs', 'params' => ['platform' => 'tiktok']],

    ['declaration' => 'platforms:/dashboard-notices', 'label' => 'dashboard notices', 'route' => '/platforms/dashboard-notices'],

    ['declaration' => 'reviews:/', 'label' => 'reviews — default list', 'route' => '/reviews', 'params' => ['per_page' => 10, 'page' => 1, 'search' => '', 'filter' => '', 'type' => 'review', 'source_id' => '', 'order_by' => 'desc', 'status_filter' => 'all']],
    ['declaration' => 'reviews:/', 'label' => 'reviews — search/platform/published', 'route' => '/reviews', 'params' => ['per_page' => 10, 'page' => 1, 'search' => 'phase', 'filter' => 'google', 'type' => 'review', 'source_id' => '', 'order_by' => 'asc', 'status_filter' => 'publish']],
    ['declaration' => 'reviews:/', 'label' => 'reviews — custom source/spam', 'route' => '/reviews', 'params' => ['per_page' => 10, 'page' => 1, 'search' => '', 'filter' => '', 'type' => 'custom_review', 'source_id' => 'fixture-source', 'order_by' => 'desc', 'status_filter' => 'spam']],
    ['declaration' => 'testimonials:/', 'label' => 'testimonials — table query', 'route' => '/testimonials', 'params' => ['per_page' => 10, 'page' => 1, 'search' => '', 'filter' => '', 'type' => 'testimonial', 'source_id' => '', 'order_by' => 'desc', 'status_filter' => 'all']],
    ['declaration' => 'reviews/categories:/', 'label' => 'review categories', 'route' => '/reviews/categories'],
    ['declaration' => 'testimonials/categories:/', 'label' => 'testimonial categories', 'route' => '/testimonials/categories'],

    ['declaration' => 'settings:/', 'label' => 'review settings — google', 'route' => '/settings', 'params' => ['platform' => 'google']],
    ['declaration' => 'settings:/', 'label' => 'feed settings — youtube', 'route' => '/settings', 'params' => ['platform' => 'youtube']],
    [
        'declaration' => 'settings:/license',
        'label' => 'license — core-alone expected unavailable',
        'route' => '/settings/license',
        'params' => ['verify' => true],
        'ok' => defined('WPSOCIALREVIEWS_PRO') ? [200] : [422],
    ],
    ['declaration' => 'settings:/translations', 'label' => 'translations', 'route' => '/settings/translations'],
    ['declaration' => 'settings:/advance-settings', 'label' => 'advanced settings', 'route' => '/settings/advance-settings'],

    ['declaration' => 'chat-widgets:/', 'label' => 'chat widgets — table query', 'route' => '/chat-widgets', 'params' => ['per_page' => 10, 'page' => 1, 'search' => '']],
    ['declaration' => 'chat-widgets/meta/chats:/{id}', 'label' => 'chat widget editor', 'route' => '/chat-widgets/meta/chats/{id}', 'needs' => 'chat_widget'],

    ['declaration' => 'shoppable:/posts', 'label' => 'shoppable post choices', 'route' => '/shoppable/posts', 'params' => ['postType' => 'post']],
    ['declaration' => 'shoppable:/', 'label' => 'shoppable settings', 'route' => '/shoppable', 'params' => ['postType' => 'post']],

    ['declaration' => 'notifications:/', 'label' => 'notifications — table query', 'route' => '/notifications', 'params' => ['per_page' => 10, 'page' => 1, 'search' => '', 'filter' => '', 'order_by' => 'desc']],

    ['declaration' => 'pages:/search', 'label' => 'pages — search/pagination', 'route' => '/pages/search', 'params' => ['search' => 'WPSR Phase', 'page' => 1, 'per_page' => 20, 'post_type' => 'page']],
    ['declaration' => 'pages:/search', 'label' => 'pages — resolve selected IDs', 'route' => '/pages/search', 'params' => ['include_ids' => ['{id}'], 'page' => 1, 'per_page' => 20, 'post_type' => 'page'], 'needs' => 'page'],

    ['declaration' => 'onboarding:/', 'label' => 'onboarding data', 'route' => '/onboarding'],
    ['declaration' => 'onboarding:/config', 'label' => 'onboarding config', 'route' => '/onboarding/config'],

    ['declaration' => 'templates:/', 'label' => 'templates — table query', 'route' => '/templates', 'params' => ['per_page' => 10, 'page' => 1, 'search' => '', 'filter' => '']],
    ['declaration' => 'templates:/', 'label' => 'templates — export templates', 'route' => '/templates', 'params' => ['search' => '', 'filter' => '', 'exclude_woocommerce' => true, 'per_page' => 500, 'templateType' => 'template']],
    ['declaration' => 'templates:/', 'label' => 'templates — export notifications', 'route' => '/templates', 'params' => ['search' => '', 'filter' => '', 'exclude_woocommerce' => true, 'per_page' => 500, 'templateType' => 'notifications']],
    ['declaration' => 'templates:/', 'label' => 'templates — export chat widgets', 'route' => '/templates', 'params' => ['search' => '', 'filter' => '', 'exclude_woocommerce' => true, 'per_page' => 500, 'templateType' => 'chat-widget']],

    ['declaration' => 'templates/meta/reviews:/{id}', 'label' => 'review template editor', 'route' => '/templates/meta/reviews/{id}', 'needs' => 'review_template'],
    ['declaration' => 'templates/meta/reviews:/{id}/first-round/{isFirstRound}', 'label' => 'review template editor — first round', 'route' => '/templates/meta/reviews/{id}/first-round/true', 'needs' => 'review_template'],
    ['declaration' => 'templates/meta/reviews:/{id}/can-enable-ai-summary', 'label' => 'AI summary availability — core alone', 'route' => '/templates/meta/reviews/{id}/can-enable-ai-summary', 'needs' => 'review_template', 'ok' => [422]],
    ['declaration' => 'templates/meta/feeds:/{id}', 'label' => 'feed template editor — youtube', 'route' => '/templates/meta/feeds/{id}', 'params' => ['platform' => 'youtube', 'postType' => 'post'], 'needs' => 'feed_template'],
];
