<?php
/**
 * Phase 7: exact-value report and aggregate contracts from core production paths.
 */

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper as ReviewsHelper;

return function () {
    $wpsr_rows = function ($wpsr_value) {
        if (is_object($wpsr_value) && method_exists($wpsr_value, 'items')) {
            $wpsr_value = $wpsr_value->items();
        } elseif (is_object($wpsr_value) && method_exists($wpsr_value, 'toArray')) {
            $wpsr_value = $wpsr_value->toArray();
        }

        if (is_array($wpsr_value) && isset($wpsr_value['data']) && is_array($wpsr_value['data'])) {
            $wpsr_value = $wpsr_value['data'];
        }
        if ($wpsr_value instanceof Traversable) {
            $wpsr_value = iterator_to_array($wpsr_value, false);
        }

        return is_array($wpsr_value) ? array_values($wpsr_value) : [];
    };

    $wpsr_ids = function ($wpsr_value, $wpsr_key = 'id') use ($wpsr_rows) {
        $wpsr_ids = [];
        foreach ($wpsr_rows($wpsr_value) as $wpsr_row) {
            $wpsr_id = is_array($wpsr_row) ? ($wpsr_row[$wpsr_key] ?? 0) : ($wpsr_row->{$wpsr_key} ?? 0);
            if ($wpsr_id) {
                $wpsr_ids[] = (int) $wpsr_id;
            }
        }
        sort($wpsr_ids, SORT_NUMERIC);
        return $wpsr_ids;
    };

    $wpsr_breakdown = function ($wpsr_value) {
        $wpsr_normalized = [];
        foreach ((array) $wpsr_value as $wpsr_bucket) {
            $wpsr_bucket = (array) $wpsr_bucket;
            $wpsr_normalized[] = [
                'type'       => (string) ($wpsr_bucket['type'] ?? ''),
                'star'       => (int) ($wpsr_bucket['star'] ?? -1),
                'count'      => (int) ($wpsr_bucket['count'] ?? -1),
                'percentage' => (string) ($wpsr_bucket['percentage'] ?? ''),
            ];
        }
        return $wpsr_normalized;
    };

    $wpsr_expected_breakdown = [
        ['type' => 'star_rating', 'star' => 5, 'count' => 2, 'percentage' => '50%'],
        ['type' => 'star_rating', 'star' => 4, 'count' => 0, 'percentage' => '0%'],
        ['type' => 'star_rating', 'star' => 3, 'count' => 1, 'percentage' => '25%'],
        ['type' => 'star_rating', 'star' => 2, 'count' => 0, 'percentage' => '0%'],
        ['type' => 'star_rating', 'star' => 1, 'count' => 0, 'percentage' => '0%'],
        ['type' => 'star_rating', 'star' => 0, 'count' => 1, 'percentage' => '25%'],
    ];
    $wpsr_zero_breakdown = array_map(function ($wpsr_star) {
        return ['type' => 'star_rating', 'star' => $wpsr_star, 'count' => 0, 'percentage' => '0%'];
    }, [5, 4, 3, 2, 1, 0]);

    WpsrTest::case('review and testimonial endpoints report exact status-scoped totals', function () use ($wpsr_ids) {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_platform = $wpsr_marker . '-admin-platform';

        try {
            $wpsr_approved_a = WpsrFactory::review([
                'platform_name'   => $wpsr_platform,
                'review_title'    => $wpsr_marker . ' approved A',
                'review_time'     => '2026-04-01 01:00:00',
                'review_approved' => 1,
            ]);
            $wpsr_approved_b = WpsrFactory::review([
                'platform_name'   => $wpsr_platform,
                'review_title'    => $wpsr_marker . ' approved B',
                'review_time'     => '2026-04-01 02:00:00',
                'review_approved' => 1,
            ]);
            $wpsr_pending = WpsrFactory::review([
                'platform_name'   => $wpsr_platform,
                'review_title'    => $wpsr_marker . ' pending',
                'review_time'     => '2026-04-01 03:00:00',
                'review_approved' => 0,
            ]);
            $wpsr_spam = WpsrFactory::review([
                'platform_name'   => $wpsr_platform,
                'review_title'    => $wpsr_marker . ' spam',
                'review_time'     => '2026-04-01 04:00:00',
                'review_approved' => 2,
            ]);
            $wpsr_testimonial = WpsrFactory::review([
                'platform_name'   => 'testimonial',
                'review_title'    => $wpsr_marker . ' testimonial',
                'review_time'     => '2026-04-01 05:00:00',
                'review_approved' => 1,
            ]);
            WpsrFactory::review([
                'platform_name'   => $wpsr_marker . '-other-platform',
                'review_title'    => 'unrelated title',
                'review_approved' => 1,
            ]);

            $wpsr_cases = [
                'all' => [
                    'expected_ids' => [(int) $wpsr_approved_a->id, (int) $wpsr_approved_b->id, (int) $wpsr_pending->id, (int) $wpsr_spam->id],
                    'expected_total' => 4,
                ],
                'publish' => [
                    'expected_ids' => [(int) $wpsr_approved_a->id, (int) $wpsr_approved_b->id],
                    'expected_total' => 2,
                ],
                'unpublish' => [
                    'expected_ids' => [(int) $wpsr_pending->id],
                    'expected_total' => 1,
                ],
                'spam' => [
                    'expected_ids' => [(int) $wpsr_spam->id],
                    'expected_total' => 1,
                ],
            ];

            foreach ($wpsr_cases as $wpsr_status => $wpsr_expected) {
                sort($wpsr_expected['expected_ids'], SORT_NUMERIC);
                $wpsr_result = WpsrTest::rest('GET', '/reviews', [
                    'search'        => $wpsr_marker,
                    'filter'        => $wpsr_platform,
                    'status_filter' => $wpsr_status,
                    'order_by'      => 'asc',
                    'per_page'      => 20,
                    'page'          => 1,
                ]);
                WpsrTest::assertHealthy($wpsr_result, 'review total endpoint for status ' . $wpsr_status, [200]);
                WpsrTest::assertSame($wpsr_expected['expected_total'], (int) ($wpsr_result['data']['total_items'] ?? -1), 'review total_items for status ' . $wpsr_status);
                WpsrTest::assertSame($wpsr_expected['expected_ids'], $wpsr_ids($wpsr_result['data']['items'] ?? []), 'review IDs for status ' . $wpsr_status);
            }

            $wpsr_testimonial_result = WpsrTest::rest('GET', '/testimonials', [
                'search'        => $wpsr_marker,
                'status_filter' => 'all',
                'per_page'      => 20,
                'page'          => 1,
            ]);
            WpsrTest::assertHealthy($wpsr_testimonial_result, 'testimonial total endpoint', [200]);
            WpsrTest::assertSame(1, (int) ($wpsr_testimonial_result['data']['total_items'] ?? -1), 'testimonial total excludes every ordinary review status');
            WpsrTest::assertSame([(int) $wpsr_testimonial->id], $wpsr_ids($wpsr_testimonial_result['data']['items'] ?? []), 'testimonial listing returns exact scoped ID');

            $wpsr_zero = WpsrTest::rest('GET', '/reviews', [
                'search'        => $wpsr_marker . '-no-match',
                'filter'        => $wpsr_platform,
                'status_filter' => 'publish',
                'per_page'      => 20,
                'page'          => 1,
            ]);
            WpsrTest::assertHealthy($wpsr_zero, 'zero review total endpoint', [200]);
            WpsrTest::assertSame(0, (int) ($wpsr_zero['data']['total_items'] ?? -1), 'zero-match review total_items boundary');
            WpsrTest::assertSame([], $wpsr_ids($wpsr_zero['data']['items'] ?? []), 'zero-match review items boundary');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('template widget and notification endpoints report exact shared-post totals', function () {
        $wpsr_marker = WpsrFactory::marker();

        try {
            WpsrFactory::post('wp_social_reviews', ['post_title' => 'aggregate template A']);
            WpsrFactory::post('wp_social_reviews', ['post_title' => 'aggregate template B']);
            WpsrFactory::post('wp_social_reviews', ['post_title' => 'aggregate template draft', 'post_status' => 'draft']);
            WpsrFactory::post('wpsr_social_chats', ['post_title' => 'aggregate widget publish']);
            WpsrFactory::post('wpsr_social_chats', ['post_title' => 'aggregate widget draft', 'post_status' => 'draft']);
            WpsrFactory::post('wpsr_reviews_notify', ['post_title' => 'aggregate notification publish']);
            WpsrFactory::post('wpsr_reviews_notify', ['post_title' => 'aggregate notification draft', 'post_status' => 'draft']);
            WpsrFactory::post('post', ['post_title' => 'aggregate ordinary post']);

            $wpsr_requests = [
                ['/templates', ['search' => $wpsr_marker, 'templateType' => 'template', 'per_page' => 20, 'page' => 1], 2],
                ['/chat-widgets', ['search' => $wpsr_marker, 'per_page' => 20, 'page' => 1], 2],
                ['/notifications', ['search' => $wpsr_marker, 'per_page' => 20, 'page' => 1], 2],
            ];

            foreach ($wpsr_requests as $wpsr_request) {
                $wpsr_result = WpsrTest::rest('GET', $wpsr_request[0], $wpsr_request[1]);
                WpsrTest::assertHealthy($wpsr_result, $wpsr_request[0] . ' aggregate endpoint', [200]);
                WpsrTest::assertSame($wpsr_request[2], (int) ($wpsr_result['data']['total_items'] ?? -1), $wpsr_request[0] . ' exact total_items');
            }

            $wpsr_zero = WpsrTest::rest('GET', '/templates', [
                'search'       => $wpsr_marker . '-no-match',
                'templateType' => 'template',
                'per_page'     => 20,
                'page'         => 1,
            ]);
            WpsrTest::assertHealthy($wpsr_zero, 'zero template aggregate endpoint', [200]);
            WpsrTest::assertSame(0, (int) ($wpsr_zero['data']['total_items'] ?? -1), 'zero-match template total boundary');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('category endpoint reports exact relationship counts including zero', function () use ($wpsr_rows) {
        try {
            $wpsr_category_a = WpsrFactory::category(['name' => 'Aggregate A']);
            $wpsr_category_b = WpsrFactory::category(['name' => 'Aggregate B']);
            $wpsr_category_zero = WpsrFactory::category(['name' => 'Aggregate Zero']);
            $wpsr_review = WpsrFactory::review(['platform_name' => 'custom', 'review_approved' => 1]);
            $wpsr_pending = WpsrFactory::review(['platform_name' => 'custom', 'review_approved' => 0]);
            $wpsr_testimonial = WpsrFactory::review(['platform_name' => 'testimonial', 'review_approved' => 2]);

            WpsrFactory::relationship((int) $wpsr_review->id, (int) $wpsr_category_a->id);
            WpsrFactory::relationship((int) $wpsr_pending->id, (int) $wpsr_category_a->id);
            WpsrFactory::relationship((int) $wpsr_testimonial->id, (int) $wpsr_category_a->id);
            WpsrFactory::relationship((int) $wpsr_testimonial->id, (int) $wpsr_category_b->id);

            $wpsr_result = WpsrTest::rest('GET', '/reviews/categories');
            WpsrTest::assertHealthy($wpsr_result, 'category count endpoint', [200]);
            $wpsr_counts = [];
            foreach ($wpsr_rows($wpsr_result['data']['categories'] ?? []) as $wpsr_category) {
                $wpsr_category = (array) $wpsr_category;
                if (strpos((string) ($wpsr_category['name'] ?? ''), WpsrFactory::marker()) !== 0) {
                    continue;
                }
                $wpsr_counts[(string) $wpsr_category['name']] = (int) ($wpsr_category['reviews_count'] ?? -1);
            }
            ksort($wpsr_counts, SORT_STRING);

            $wpsr_expected = [
                (string) $wpsr_category_a->name => 3,
                (string) $wpsr_category_b->name => 1,
                (string) $wpsr_category_zero->name => 0,
            ];
            ksort($wpsr_expected, SORT_STRING);
            WpsrTest::assertSame($wpsr_expected, $wpsr_counts, 'category counts span statuses/platforms and preserve zero bucket');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('business summaries and rating buckets use exact platform source status and aggregate values', function () use ($wpsr_breakdown, $wpsr_expected_breakdown, $wpsr_zero_breakdown) {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_platform_a = $wpsr_marker . '-alpha';
        $wpsr_platform_b = $wpsr_marker . '-beta';
        $wpsr_other_platform = $wpsr_marker . '-other';
        $wpsr_source_a1 = $wpsr_marker . '-alpha-one';
        $wpsr_source_a2 = $wpsr_marker . '-alpha-two';
        $wpsr_source_b1 = $wpsr_marker . '-beta-one';
        $wpsr_registry_filter = function ($wpsr_platforms) use ($wpsr_platform_a, $wpsr_platform_b) {
            $wpsr_platforms = is_array($wpsr_platforms) ? $wpsr_platforms : [];
            $wpsr_platforms[$wpsr_platform_a] = 'Phase 7 Alpha';
            $wpsr_platforms[$wpsr_platform_b] = 'Phase 7 Beta';
            return $wpsr_platforms;
        };

        add_filter('wpsocialreviews/available_valid_reviews_platforms', $wpsr_registry_filter, PHP_INT_MAX);
        try {
            WpsrFactory::option('wpsr_reviews_' . $wpsr_platform_a . '_business_info', [
                $wpsr_source_a1 => [
                    'place_id' => $wpsr_source_a1, 'platform_name' => $wpsr_platform_a, 'name' => 'Alpha One',
                    'url' => 'https://example.test/alpha-one', 'logo' => '', 'average_rating' => 5, 'total_rating' => 2,
                ],
                $wpsr_source_a2 => [
                    'place_id' => $wpsr_source_a2, 'platform_name' => $wpsr_platform_a, 'name' => 'Alpha Two',
                    'url' => 'https://example.test/alpha-two', 'logo' => '', 'average_rating' => 1, 'total_rating' => 4,
                ],
            ]);
            WpsrFactory::option('wpsr_reviews_' . $wpsr_platform_b . '_business_info', [
                $wpsr_source_b1 => [
                    'place_id' => $wpsr_source_b1, 'platform_name' => $wpsr_platform_b, 'name' => 'Beta One',
                    'url' => 'https://example.test/beta-one', 'logo' => '', 'average_rating' => 3, 'total_rating' => 7,
                ],
            ]);

            WpsrFactory::review(['platform_name' => $wpsr_platform_a, 'source_id' => $wpsr_source_a1, 'rating' => 5, 'review_approved' => 1]);
            WpsrFactory::review(['platform_name' => $wpsr_platform_a, 'source_id' => $wpsr_source_a1, 'rating' => 5, 'review_approved' => 1]);
            WpsrFactory::review(['platform_name' => $wpsr_platform_a, 'source_id' => $wpsr_source_a2, 'rating' => 3, 'review_approved' => 1]);
            WpsrFactory::review(['platform_name' => $wpsr_platform_b, 'source_id' => $wpsr_source_b1, 'rating' => 0, 'review_approved' => 1]);
            WpsrFactory::review(['platform_name' => $wpsr_platform_a, 'source_id' => $wpsr_source_a1, 'rating' => 4, 'review_approved' => 0]);
            WpsrFactory::review(['platform_name' => $wpsr_platform_a, 'source_id' => $wpsr_marker . '-unselected', 'rating' => 1, 'review_approved' => 1]);
            WpsrFactory::review(['platform_name' => $wpsr_other_platform, 'source_id' => $wpsr_source_b1, 'rating' => 2, 'review_approved' => 1]);

            $wpsr_summary = ReviewsHelper::getBusinessInfoByPlatforms([$wpsr_platform_a, $wpsr_platform_b]);
            WpsrTest::assertNoDatabaseError('business aggregate helper');
            WpsrTest::assertSame(3, (int) ($wpsr_summary['total_business'] ?? -1), 'business aggregate counts three connected businesses');
            WpsrTest::assertSame(2, (int) ($wpsr_summary['total_platforms'] ?? -1), 'business aggregate counts two distinct platforms');
            WpsrTest::assertSame(13, (int) ($wpsr_summary['total_rating'] ?? -1), 'business aggregate sums review totals instead of counting businesses');
            WpsrTest::assertSame(3.0, (float) ($wpsr_summary['average_rating'] ?? -1), 'business aggregate averages three business rating values');
            WpsrTest::assertSame([$wpsr_source_a1, $wpsr_source_a2, $wpsr_source_b1], array_keys($wpsr_summary['platforms'] ?? []), 'business aggregate preserves deterministic platform/business map');
            WpsrTest::assertSame($wpsr_expected_breakdown, $wpsr_breakdown($wpsr_summary['rating_breakdown'] ?? []), 'rating GROUP BY returns exact six buckets and percentages');

            $wpsr_internal = Review::getInternalBusinessInfo($wpsr_platform_a, ['source_id' => $wpsr_source_a1, 'handle' => 'Alpha One']);
            WpsrTest::assertNoDatabaseError('internal business count and average');
            WpsrTest::assertSame(2, (int) ($wpsr_internal[$wpsr_source_a1]['total_rating'] ?? -1), 'internal business count includes approved matching source rows only');
            WpsrTest::assertSame(5.0, (float) ($wpsr_internal[$wpsr_source_a1]['average_rating'] ?? -1), 'internal business average uses approved matching source rows only');

            $wpsr_empty_source = $wpsr_marker . '-empty-source';
            $wpsr_internal_zero = Review::getInternalBusinessInfo($wpsr_platform_a, ['source_id' => $wpsr_empty_source]);
            WpsrTest::assertSame(0, (int) ($wpsr_internal_zero[$wpsr_empty_source]['total_rating'] ?? -1), 'internal business zero-count boundary');
            WpsrTest::assertSame(0.0, (float) ($wpsr_internal_zero[$wpsr_empty_source]['average_rating'] ?? -1), 'internal business zero-average boundary');
            WpsrTest::assertSame(
                $wpsr_zero_breakdown,
                $wpsr_breakdown(ReviewsHelper::calculateStarRatingBreakdown([$wpsr_platform_a], [
                    $wpsr_empty_source => ['platform_name' => $wpsr_platform_a],
                ])),
                'rating breakdown zero-match boundary contains every zero bucket'
            );

            $wpsr_template = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Phase 7 aggregate meta']);
            update_post_meta((int) $wpsr_template->ID, '_wpsr_template_config', wp_json_encode([
                'platform'           => [$wpsr_platform_a, $wpsr_platform_b],
                'selectedBusinesses' => [],
                'starFilterVal'      => -1,
                'order'              => 'oldest',
                'templateType'       => 'grid',
                'pagination_type'    => 'load_more',
                'paginate'           => 2,
                'totalReviewsNumber' => ['desktop' => 20, 'mobile' => 20],
            ]));
            $wpsr_meta = WpsrTest::rest('GET', '/templates/meta/reviews/' . (int) $wpsr_template->ID);
            WpsrTest::assertHealthy($wpsr_meta, 'review template aggregate meta endpoint', [200]);
            $wpsr_meta_summary = $wpsr_meta['data']['business_info'] ?? [];
            WpsrTest::assertSame(3, (int) ($wpsr_meta_summary['total_business'] ?? -1), 'meta endpoint exposes exact business count');
            WpsrTest::assertSame(2, (int) ($wpsr_meta_summary['total_platforms'] ?? -1), 'meta endpoint exposes exact platform count');
            WpsrTest::assertSame(13, (int) ($wpsr_meta_summary['total_rating'] ?? -1), 'meta endpoint exposes exact summed rating total');
            WpsrTest::assertSame(3.0, (float) ($wpsr_meta_summary['average_rating'] ?? -1), 'meta endpoint exposes exact business average');
            WpsrTest::assertSame($wpsr_expected_breakdown, $wpsr_breakdown($wpsr_meta_summary['rating_breakdown'] ?? []), 'meta endpoint exposes exact server rating buckets');
        } finally {
            remove_filter('wpsocialreviews/available_valid_reviews_platforms', $wpsr_registry_filter, PHP_INT_MAX);
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('KNOWN-FAILURE selected-business summary reports selected count only', function () {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_platform = $wpsr_marker . '-selected';
        $wpsr_source_a = $wpsr_marker . '-selected-a';
        $wpsr_source_b = $wpsr_marker . '-selected-b';

        try {
            WpsrFactory::option('wpsr_reviews_' . $wpsr_platform . '_business_info', [
                $wpsr_source_a => [
                    'place_id' => $wpsr_source_a, 'platform_name' => $wpsr_platform, 'name' => 'Selected A',
                    'url' => 'https://example.test/selected-a', 'average_rating' => 5, 'total_rating' => 2,
                ],
                $wpsr_source_b => [
                    'place_id' => $wpsr_source_b, 'platform_name' => $wpsr_platform, 'name' => 'Selected B',
                    'url' => 'https://example.test/selected-b', 'average_rating' => 1, 'total_rating' => 7,
                ],
            ]);

            // The count started from the all-business total and then incremented per
            // selected business, so one selection out of two reported three.
            $wpsr_one = ReviewsHelper::getSelectedBusinessInfoByPlatforms([$wpsr_platform], [$wpsr_source_a]);

            WpsrTest::assertSame(1, (int) ($wpsr_one['total_business'] ?? -1),
                'one selected business out of two counts as one');
            WpsrTest::assertSame([$wpsr_source_a], array_keys($wpsr_one['platforms'] ?? []),
                'only the selected business survives the filter');
            WpsrTest::assertSame(2, (int) ($wpsr_one['total_rating'] ?? -1),
                'total rating comes from the selected business only');
            WpsrTest::assertSame(5.0, (float) ($wpsr_one['average_rating'] ?? -1),
                'average rating comes from the selected business only');

            // Positive control: selecting both counts both and aggregates both.
            $wpsr_both = ReviewsHelper::getSelectedBusinessInfoByPlatforms(
                [$wpsr_platform], [$wpsr_source_a, $wpsr_source_b]
            );

            WpsrTest::assertSame(2, (int) ($wpsr_both['total_business'] ?? -1),
                'two selected businesses count as two');
            WpsrTest::assertSame(9, (int) ($wpsr_both['total_rating'] ?? -1),
                'total rating sums both selected businesses');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('public model and editor load-more paths report exact filtered totals', function () use ($wpsr_ids) {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_platform = $wpsr_marker . '-feed';
        $wpsr_source = $wpsr_marker . '-feed-source';
        $wpsr_registry_filter = function ($wpsr_platforms) use ($wpsr_platform) {
            $wpsr_platforms = is_array($wpsr_platforms) ? $wpsr_platforms : [];
            $wpsr_platforms[$wpsr_platform] = 'Phase 7 Feed';
            return $wpsr_platforms;
        };
        add_filter('wpsocialreviews/available_valid_reviews_platforms', $wpsr_registry_filter, PHP_INT_MAX);

        try {
            $wpsr_first = WpsrFactory::review([
                'platform_name' => $wpsr_platform, 'source_id' => $wpsr_source,
                'review_time' => '2026-05-01 01:00:00', 'rating' => 5, 'review_approved' => 1,
            ]);
            $wpsr_second = WpsrFactory::review([
                'platform_name' => $wpsr_platform, 'source_id' => $wpsr_source,
                'review_time' => '2026-05-01 02:00:00', 'rating' => 4, 'review_approved' => 1,
            ]);
            $wpsr_third = WpsrFactory::review([
                'platform_name' => $wpsr_platform, 'source_id' => $wpsr_source,
                'review_time' => '2026-05-01 03:00:00', 'rating' => 3, 'review_approved' => 1,
            ]);
            WpsrFactory::review([
                'platform_name' => $wpsr_platform, 'source_id' => $wpsr_source,
                'review_time' => '2026-05-01 04:00:00', 'rating' => 5, 'review_approved' => 0,
            ]);
            WpsrFactory::review([
                'platform_name' => $wpsr_platform, 'source_id' => $wpsr_marker . '-other-source',
                'review_time' => '2026-05-01 05:00:00', 'rating' => 5, 'review_approved' => 1,
            ]);
            WpsrFactory::review([
                'platform_name' => $wpsr_marker . '-other-feed', 'source_id' => $wpsr_source,
                'review_time' => '2026-05-01 06:00:00', 'rating' => 5, 'review_approved' => 1,
            ]);

            $wpsr_meta = [
                'platform'           => [$wpsr_platform],
                'selectedBusinesses' => [$wpsr_source],
                'starFilterVal'      => -1,
                'order'              => 'oldest',
                'templateType'       => 'grid',
                'pagination_type'    => 'load_more',
                'paginate'           => 2,
                'paginate_number'    => ['desktop' => 2, 'mobile' => 2],
                'totalReviewsNumber' => ['desktop' => 0, 'mobile' => 0],
            ];

            $wpsr_page = Review::paginatedReviews([$wpsr_platform], $wpsr_meta, 1);
            WpsrTest::assertNoDatabaseError('public paginated review total');
            WpsrTest::assertSame(3, (int) ($wpsr_page['total_reviews'] ?? -1), 'public model count applies platform business and approval scope');
            WpsrTest::assertSame(
                [(int) $wpsr_first->id, (int) $wpsr_second->id],
                array_map('intval', $wpsr_page['reviews']->pluck('id')->toArray()),
                'public first page contains exact ordered IDs while total remains three'
            );

            $wpsr_zero = Review::paginatedReviews([$wpsr_marker . '-missing-feed'], $wpsr_meta, 1);
            WpsrTest::assertSame(0, (int) ($wpsr_zero['total_reviews'] ?? -1), 'public model zero-total boundary');
            WpsrTest::assertSame([], array_values($wpsr_zero['reviews']->toArray()), 'public model zero-total returns no rows');

            $wpsr_template = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Phase 7 load-more total']);
            $wpsr_result = WpsrTest::rest('POST', '/templates/meta/reviews/' . (int) $wpsr_template->ID . '/load-more', [
                'page'          => 2,
                'template_meta' => wp_json_encode($wpsr_meta),
            ]);
            WpsrTest::assertHealthy($wpsr_result, 'editor load-more aggregate endpoint', [200]);
            WpsrTest::assertSame(3, (int) ($wpsr_result['data']['total_reviews'] ?? -1), 'editor load-more total applies exact filtered query');
            WpsrTest::assertSame(false, (bool) ($wpsr_result['data']['has_more'] ?? true), 'editor load-more has_more boundary after final row');
            WpsrTest::assertSame([(int) $wpsr_third->id], $wpsr_ids($wpsr_result['data']['reviews'] ?? []), 'editor load-more returns exact final-page ID');
        } finally {
            remove_filter('wpsocialreviews/available_valid_reviews_platforms', $wpsr_registry_filter, PHP_INT_MAX);
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('review config endpoint reports exact option-backed business pagination totals', function () {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_platform = $wpsr_marker . '-connections';

        try {
            WpsrFactory::option('wpsr_reviews_' . $wpsr_platform . '_business_info', [
                'one' => ['name' => 'One'],
                'two' => ['name' => 'Two'],
                'three' => ['name' => 'Three'],
                'four' => ['name' => 'Four'],
                'five' => ['name' => 'Five'],
            ]);

            $wpsr_result = WpsrTest::rest('GET', '/platforms/reviews/configs', [
                'platform' => $wpsr_platform,
                'page'     => 2,
                'per_page' => 2,
            ]);
            WpsrTest::assertHealthy($wpsr_result, 'connected-business pagination aggregate endpoint', [200]);
            WpsrTest::assertSame([
                'page' => 2,
                'per_page' => 2,
                'total' => 5,
                'total_pages' => 3,
            ], $wpsr_result['data']['pagination'] ?? [], 'connected-business pagination computes exact count and ceiling');

            $wpsr_zero = WpsrTest::rest('GET', '/platforms/reviews/configs', [
                'platform' => $wpsr_marker . '-empty-connections',
                'page'     => 1,
                'per_page' => 4,
            ]);
            WpsrTest::assertHealthy($wpsr_zero, 'zero connected-business pagination endpoint', [200]);
            WpsrTest::assertSame([
                'page' => 1,
                'per_page' => 4,
                'total' => 0,
                'total_pages' => 0,
            ], $wpsr_zero['data']['pagination'] ?? [], 'connected-business zero pagination boundary');
        } finally {
            WpsrFactory::cleanup();
        }
    });
};
