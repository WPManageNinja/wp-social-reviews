<?php
/** Phase 24 fixture/mutation manifest for every registered Phase 23 detector. */
return [
    [
        'id' => 'ajax-guard-order',
        'checks' => 7,
        'sources' => [
            'app/Hooks/Handlers/ShortcodeHandler.php',
            'app/Hooks/Handlers/YoutubeTemplateHandler.php',
            'app/Services/Platforms/ImageOptimizationHandler.php',
            'app/Services/Platforms/ReviewImageOptimizationHandler.php',
        ],
        'mutation' => [
            'file' => 'app/Hooks/Handlers/ShortcodeHandler.php',
            'old' => "check_ajax_referer('wpsr-ajax-nonce', 'security', false)",
            'new' => "check_ajax_referer('wpsr-broken-nonce', 'security', false)",
            'expected' => 1,
        ],
        'message' => 'handleLoadMoreAjax nonce verification must precede public object authorization',
    ],
    [
        'id' => 'category-destructive-policy',
        'checks' => 5,
        'sources' => [
            'app/Http/Policies/CategoryManagementPolicy.php',
            'app/Http/Routes/api.php',
        ],
        'mutation' => [
            'file' => 'app/Http/Policies/CategoryManagementPolicy.php',
            'old' => "&& \$this->currentUserCan('wpsn_manage_testimonials')",
            'new' => "|| \$this->currentUserCan('wpsn_manage_testimonials')",
            'expected' => 1,
        ],
        'message' => 'category policy must conjunct both capabilities',
    ],
    [
        'id' => 'facebook-paging-host',
        'checks' => 3,
        'sources' => [
            'app/Services/Helper.php',
        ],
        'mutation' => [
            'file' => 'app/Services/Helper.php',
            'old' => "wp_parse_url(\$nextUrl, PHP_URL_HOST)) !== 'graph.facebook.com'",
            'new' => "wp_parse_url(\$nextUrl, PHP_URL_HOST)) !== 'attacker.example'",
            'expected' => 1,
        ],
        'message' => 'paging URL host must equal Graph host',
    ],
    [
        'id' => 'meta-write-ownership',
        'checks' => 8,
        'sources' => [
            'app/Http/Controllers/Platforms/Chats/MetaController.php',
            'app/Http/Controllers/Platforms/Reviews/MetaController.php',
            'app/Services/Platforms/Chats/SocialChat.php',
        ],
        'mutation' => [
            'file' => 'app/Http/Controllers/Platforms/Reviews/MetaController.php',
            'old' => "                    ->whereIn('post_type', self::ALLOWED_POST_TYPES)\n",
            'new' => '',
            'expected' => 1,
        ],
        'message' => 'raw review menu-order update must reassert allowed post types',
    ],
    [
        'id' => 'no-shipped-oauth-secrets',
        'checks' => 8,
        'sources' => [
            'app/Services/Platforms/Feeds/Youtube/OAuth.php',
            'app/Services/Platforms/Reviews/GoogleMyBusiness.php',
        ],
        'mutation' => [
            'file' => 'app/Services/Platforms/Feeds/Youtube/OAuth.php',
            'old' => "private \$clientSecret = '';",
            'new' => "private \$clientSecret = 'GOCSPX-phase24-regression';",
            'expected' => 1,
        ],
        'message' => 'youtube shipped client secret must remain blank',
    ],
    [
        'id' => 'public-template-visibility',
        'checks' => 9,
        'sources' => [
            'app/Hooks/Handlers/ShortcodeHandler.php',
            'app/Hooks/Handlers/YoutubeTemplateHandler.php',
        ],
        'mutation' => [
            'file' => 'app/Hooks/Handlers/ShortcodeHandler.php',
            'old' => "\$wpsr_template_post->post_status === 'publish' && !post_password_required(\$wpsr_template_post)",
            'new' => "\$wpsr_template_post->post_status === 'publish'",
            'expected' => 1,
        ],
        'message' => 'published templates must still pass password challenge',
    ],
    [
        'id' => 'remote-auth-hmac',
        'checks' => 6,
        'sources' => [
            'app/Services/Platforms/RemoteAuth.php',
        ],
        'mutation' => [
            'file' => 'app/Services/Platforms/RemoteAuth.php',
            'old' => "'body'    => \$canonical,",
            'new' => "'body'    => wp_json_encode(\$body),",
            'expected' => 1,
        ],
        'message' => 'transport body must reuse signed canonical bytes',
    ],
    [
        'id' => 'remote-auth-return-host',
        'checks' => 5,
        'sources' => [
            'app/Services/Platforms/RemoteAuth.php',
        ],
        'mutation' => [
            'file' => 'app/Services/Platforms/RemoteAuth.php',
            'old' => "strtolower(\$returnHost) !== strtolower(\$siteHost)",
            'new' => "strtolower(\$returnHost) === strtolower(\$siteHost)",
            'expected' => 1,
        ],
        'message' => 'missing or cross-host return URL must fail closed',
    ],
    [
        'id' => 'remote-auth-secret-storage',
        'checks' => 4,
        'sources' => [
            'app/Services/Platforms/RemoteAuth.php',
        ],
        'mutation' => [
            'file' => 'app/Services/Platforms/RemoteAuth.php',
            'old' => "        ], 'no');",
            'new' => "        ], 'yes');",
            'expected' => 1,
        ],
        'message' => 'bridge identity option must disable autoload',
    ],
    [
        'id' => 'twitter-card-dom-sinks',
        'checks' => 7,
        'sources' => [
            'resources/public/twitter-card.js',
        ],
        'mutation' => [
            'file' => 'resources/public/twitter-card.js',
            'old' => ".text(twitter_card['twitter:title'].substr(0, 48) + titleIndicator)",
            'new' => ".html(twitter_card['twitter:title'].substr(0, 48) + titleIndicator)",
            'expected' => 1,
        ],
        'message' => 'title, description, and domain must use text sinks',
    ],
];
