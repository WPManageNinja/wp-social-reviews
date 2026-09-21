<?php
/**
 * Phase 13 coverage-driven gaps: schema migration, local feed filtering, rendering.
 */

use WPSocialReviews\App\Services\Platforms\Feeds\Common\FeedFilters;
use WPSocialReviews\App\Services\Platforms\Feeds\Instagram\OEmbed;
use WPSocialReviews\Database\Migrations\ReviewsMigrator;

return function () {
    WpsrTest::case('reviews migration creates complete indexed schema and reruns idempotently', function () {
        global $wpdb;

        $wpsr_original_table_name = ReviewsMigrator::$tableName;
        $wpsr_suffix = preg_replace('/[^a-z0-9_]/', '', strtolower(WpsrFactory::marker()));
        $wpsr_table_name = 'wpsr_phase13_reviews_' . substr($wpsr_suffix, 0, 20);
        $wpsr_table = $wpdb->prefix . $wpsr_table_name;

        if (!$wpsr_suffix || strpos($wpsr_table, $wpdb->prefix . 'wpsr_phase13_reviews_') !== 0) {
            throw new RuntimeException('Invalid owned Phase 13 migration table name.');
        }

        $wpsr_created = false;
        $wpsr_drop_table = function () use ($wpdb, $wpsr_table, &$wpsr_created) {
            if (!$wpsr_created) {
                return;
            }
            $wpdb->query("DROP TABLE `{$wpsr_table}`");
            $wpsr_created = false;
        };

        try {
            $wpsr_existing = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpsr_table)));
            WpsrTest::assertNoDatabaseError('reviews migration scratch ownership lookup');
            if ($wpsr_existing !== null) {
                throw new RuntimeException('Refusing to delete pre-existing migration scratch table ' . $wpsr_table . '.');
            }
            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }
            ReviewsMigrator::$tableName = $wpsr_table_name;
            ReviewsMigrator::migrate();
            $wpsr_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpsr_table)));
            $wpsr_created = $wpsr_found === $wpsr_table;
            WpsrTest::assertNoDatabaseError('reviews migration creates scratch schema');
            WpsrTest::assertSame($wpsr_table, $wpsr_found, 'migration creates exact test-owned reviews table');

            $wpsr_columns = $wpdb->get_results("SHOW COLUMNS FROM `{$wpsr_table}`", ARRAY_A);
            $wpsr_columns_by_name = [];
            foreach ($wpsr_columns as $wpsr_column) {
                $wpsr_columns_by_name[$wpsr_column['Field']] = [
                    'type'    => strtolower((string) $wpsr_column['Type']),
                    'null'    => (string) $wpsr_column['Null'],
                    'default' => $wpsr_column['Default'],
                ];
            }

            WpsrTest::assertSame(
                ['category', 'fields', 'review_approved', 'review_id', 'reviewer_email', 'reviewer_img'],
                array_values(array_intersect(
                    ['category', 'fields', 'review_approved', 'review_id', 'reviewer_email', 'reviewer_img'],
                    array_keys($wpsr_columns_by_name)
                )),
                'migration creates customer-critical review columns'
            );
            WpsrTest::assertSame('text', $wpsr_columns_by_name['reviewer_img']['type'], 'reviewer image supports text URLs');
            WpsrTest::assertSame('1', (string) $wpsr_columns_by_name['review_approved']['default'], 'approval default remains approved');
            WpsrTest::assertSame('YES', $wpsr_columns_by_name['reviewer_email']['null'], 'reviewer email remains nullable');

            $wpsr_index_map = function () use ($wpdb, $wpsr_table) {
                $wpsr_rows = $wpdb->get_results("SHOW INDEX FROM `{$wpsr_table}`", ARRAY_A);
                $wpsr_map = [];
                foreach ($wpsr_rows as $wpsr_row) {
                    $wpsr_key = (string) $wpsr_row['Key_name'];
                    if ($wpsr_key === 'PRIMARY') {
                        continue;
                    }
                    $wpsr_map[$wpsr_key][(int) $wpsr_row['Seq_in_index']] = (string) $wpsr_row['Column_name'];
                }
                foreach ($wpsr_map as &$wpsr_columns) {
                    ksort($wpsr_columns, SORT_NUMERIC);
                    $wpsr_columns = array_values($wpsr_columns);
                }
                unset($wpsr_columns);
                ksort($wpsr_map, SORT_STRING);
                return $wpsr_map;
            };

            $wpsr_expected_indexes = [
                'idx_platform_name'   => ['platform_name'],
                'idx_rating_time'     => ['rating', 'review_time', 'id'],
                'idx_review_approved' => ['review_approved'],
                'idx_review_id'       => ['review_id'],
                'idx_review_time'     => ['review_time', 'id'],
                'idx_reviewer_email'  => ['reviewer_email'],
                'idx_source_id'       => ['source_id'],
            ];
            WpsrTest::assertSame($wpsr_expected_indexes, $wpsr_index_map(), 'migration creates exact lookup and sort indexes');

            ReviewsMigrator::migrate();
            WpsrTest::assertNoDatabaseError('reviews migration rerun');
            WpsrTest::assertSame($wpsr_expected_indexes, $wpsr_index_map(), 'migration rerun does not duplicate or drift indexes');
        } finally {
            ReviewsMigrator::$tableName = $wpsr_original_table_name;
            $wpsr_drop_table();
            WpsrTest::assertNoDatabaseError('reviews migration scratch cleanup');
        }
    });

    WpsrTest::case('Twitter feed filtering applies local newest-first ordering and desktop limit', function () {
        $wpsr_filter = new FeedFilters();
        $wpsr_settings = [
            'filters' => [
                'post_order'        => 'ascending',
                'total_posts_number'=> ['desktop' => 2, 'mobile' => 2],
                'includes_inputs'   => '',
                'excludes_inputs'   => '',
                'hide_posts_by_id'  => '',
            ],
        ];
        $wpsr_response = [
            'header' => ['account' => 'owned-twitter-account'],
            'items'  => [
                ['id' => 'old', 'text' => 'Old customer post', 'created_at' => '2026-01-01 10:00:00 UTC'],
                ['id' => 'newest', 'text' => 'Newest customer post', 'created_at' => '2026-01-03 10:00:00 UTC'],
                ['id' => 'middle', 'text' => 'Middle customer post', 'created_at' => '2026-01-02 10:00:00 UTC'],
            ],
        ];
        $wpsr_original = $wpsr_response;

        $wpsr_filtered = $wpsr_filter->filterFeedResponse('twitter', $wpsr_settings, $wpsr_response);
        WpsrTest::assertSame(
            ['newest', 'middle'],
            array_values(array_column($wpsr_filtered['items'], 'id')),
            'ascending feed contract returns newest two posts first'
        );
        WpsrTest::assertSame(['account' => 'owned-twitter-account'], $wpsr_filtered['header'], 'local filtering preserves feed header');
        WpsrTest::assertSame([], $wpsr_filtered['resize_data'], 'Twitter local filtering does not fabricate optimized images');
        WpsrTest::assertSame(true, $wpsr_filtered['has_latest_post'], 'unoptimized local feed reports latest-post availability');
        WpsrTest::assertSame($wpsr_original, $wpsr_response, 'feed filtering does not mutate caller response array');
    });

    WpsrTest::case('Instagram oEmbed rendering wraps only matching embed markup and keeps provider contract', function () {
        $wpsr_oembed = new OEmbed();
        $wpsr_html = '<blockquote class="instagram-media">customer post</blockquote>';
        $wpsr_rendered = $wpsr_oembed->oembedResult(
            $wpsr_html,
            'https://www.instagram.com/reel/owned-post/',
            []
        );

        WpsrTest::assertSame(
            '<div class="wpsr-oembed-wrapper"><blockquote class="instagram-media wpsr-oembed">customer post</blockquote></div>',
            $wpsr_rendered,
            'Instagram embed gets exact plugin wrapper and class'
        );
        WpsrTest::assertSame(
            $wpsr_html,
            $wpsr_oembed->oembedResult($wpsr_html, 'https://example.test/not-instagram', []),
            'non-Instagram markup remains byte-identical'
        );
        WpsrTest::assertSame(
            '<p>Instagram URL without provider markup</p>',
            $wpsr_oembed->oembedResult(
                '<p>Instagram URL without provider markup</p>',
                'https://www.instagram.com/p/owned-post/',
                []
            ),
            'Instagram response without expected provider class remains byte-identical'
        );

        $wpsr_providers = $wpsr_oembed->registerProviders(['existing' => ['https://example.test/oembed', false]]);
        WpsrTest::assertSame(
            ['https://graph.facebook.com/instagram_oembed', true],
            $wpsr_providers['#https?://(www\.)?instagram\.com/(p|reel|tv)/.*#i'],
            'Instagram post/reel/tv provider points to Graph oEmbed endpoint'
        );
        WpsrTest::assertSame(
            ['https://example.test/oembed', false],
            $wpsr_providers['existing'],
            'provider registration preserves existing providers'
        );
        WpsrTest::assertSame(
            14 * DAY_IN_SECONDS,
            $wpsr_oembed->extendOembedCacheTtl(DAY_IN_SECONDS, 'https://instagram.com/tv/owned-post/'),
            'Instagram embeds use exact 14-day cache lifetime'
        );
        WpsrTest::assertSame(
            DAY_IN_SECONDS,
            $wpsr_oembed->extendOembedCacheTtl(DAY_IN_SECONDS, 'https://example.test/video'),
            'non-Instagram cache lifetime remains unchanged'
        );
    });
};
