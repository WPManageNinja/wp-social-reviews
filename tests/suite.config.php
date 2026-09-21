<?php
/**
 * WP Social Ninja family suite configuration.
 */
return [
    'plugin_slug'      => 'wp-social-ninja',
    'plugin_dir_hint'  => 'plugins/wp-social-ninja',
    'rest_namespace'   => 'wpsocialreviews/v2',
    'routes_file'      => 'app/Http/Routes/api.php',
    'table_prefix'     => 'wpsr_',
    'app_bootstrap'    => 'wpsrSocialReviews',
    'request_class'    => 'WPSocialReviews\\Framework\\Http\\Request\\Request',
    'sentinel_class'   => 'WPSocialReviews\\App\\Models\\Review',
    'cache_groups'     => ['wpsr_column_checks'],
    'loopback_filter'  => '',
    'protected_tables' => [
        'wpsr_reviews',
        'wpsr_caches',
        'wpsr_review_forms',
        'wpsr_optimize_images',
        'wpsr_review_categories',
        'wpsr_review_category_relationships',
    ],
    'protected_wordpress_tables' => [
        'posts',
        'postmeta',
        'users',
        'options',
    ],
];
