<?php
/**
 * Phase 16: bounded review/category list preparation and known template-list N+1.
 */

use WPSocialReviews\App\Models\Post;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper as ReviewsHelper;

return function () {
    $wpsr_rows = function ($items) {
        if (is_object($items) && method_exists($items, 'items')) {
            return $items->items();
        }
        if (is_object($items) && method_exists($items, 'toArray')) {
            return $items->toArray();
        }
        return (array) $items;
    };

    WpsrTest::case('review listing keeps eager category preparation bounded at 2 and 8 rows', function () use ($wpsr_rows) {
        $wpsr_run = function ($size) use ($wpsr_rows) {
            $token = WpsrFactory::marker() . '-throughput-list-' . $size;
            $category = WpsrFactory::category(['name' => 'Throughput list ' . $size]);
            $expectedIds = [];
            for ($index = 0; $index < $size; $index++) {
                $review = WpsrFactory::review([
                    'platform_name' => 'custom',
                    'review_title'  => $token . ' row ' . $index,
                    'review_time'   => sprintf('2026-09-01 00:00:%02d', $index),
                ]);
                WpsrFactory::relationship((int) $review->id, (int) $category->id);
                $expectedIds[] = (int) $review->id;
            }

            // Prime route/options caches outside measured operation so first/second size order cannot change SQL counts.
            WpsrTest::rest('GET', '/reviews', [
                'search' => $token . '-warmup-no-match',
                'filter' => 'custom',
            ]);

            $measurement = WpsrThroughput::measure('review-list N=' . $size, function () use ($token, $size, $wpsr_rows) {
                $result = WpsrTest::rest('GET', '/reviews', [
                    'search'        => $token,
                    'filter'        => 'custom',
                    'status_filter' => 'publish',
                    'order_by'      => 'asc',
                    'page'          => 1,
                    'per_page'      => $size + 2,
                ]);
                WpsrTest::assertHealthy($result, 'throughput review listing response', [200]);
                $rows = $wpsr_rows($result['data']['items'] ?? []);
                $categoryCounts = [];
                foreach ($rows as $row) {
                    $categoryCounts[] = is_object($row) ? count($row->categories) : count((array) ($row['categories'] ?? []));
                }
                return ['rows' => $rows, 'category_counts' => $categoryCounts];
            });

            $actualIds = [];
            foreach ($measurement['result']['rows'] as $row) {
                $actualIds[] = (int) (is_object($row) ? $row->id : ($row['id'] ?? 0));
            }
            WpsrTest::assertSame($expectedIds, $actualIds, 'review listing returns exact owned rows at N=' . $size);
            WpsrTest::assertSame(array_fill(0, $size, 1), $measurement['result']['category_counts'], 'review listing serializes one eager category per row at N=' . $size);
            return $measurement;
        };

        try {
            $small = $wpsr_run(2);
            WpsrFactory::cleanup();
            $large = $wpsr_run(8);
            WpsrThroughput::assertCountBudget($small, $large, 5, 5, 0, 'review listing');
            WpsrTest::assertSame($small['count'], $large['count'], 'review listing exact query count is size-invariant');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('rating aggregate stays one grouped query at 2 and 20 reviews', function () {
        $wpsr_run = function ($size) {
            $businessInfo = [];
            for ($index = 0; $index < $size; $index++) {
                $source = WpsrFactory::marker() . '-throughput-rating-' . $size . '-' . $index;
                WpsrFactory::review([
                    'platform_name'   => 'google',
                    'source_id'       => $source,
                    'rating'          => 5,
                    'review_approved' => 1,
                    'review_title'    => 'Throughput rating ' . $size . '-' . $index,
                ]);
                $businessInfo[$source] = ['platform_name' => 'google'];
            }
            $measurement = WpsrThroughput::measure('rating-aggregate N=' . $size, function () use ($businessInfo) {
                return ReviewsHelper::calculateStarRatingBreakdown(['google'], $businessInfo);
            });
            $counts = [];
            foreach ($measurement['result'] as $row) {
                $counts[(int) $row['star']] = (int) $row['count'];
            }
            WpsrTest::assertSame($size, $counts[5] ?? -1, 'rating aggregate returns exact five-star cardinality at N=' . $size);
            WpsrTest::assertSame($size, array_sum($counts), 'rating aggregate total cardinality at N=' . $size);
            return $measurement;
        };

        try {
            $small = $wpsr_run(2);
            WpsrFactory::cleanup();
            $large = $wpsr_run(20);
            WpsrThroughput::assertCountBudget($small, $large, 2, 2, 0, 'rating aggregate');
            WpsrTest::assertSame(1, $small['count'], 'rating aggregate small observed statement count');
            WpsrTest::assertSame(1, $large['count'], 'rating aggregate large observed statement count');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('category aggregate listing stays one statement at 2 and 12 categories', function () {
        $wpsr_run = function ($size) {
            $token = WpsrFactory::marker() . '-throughput-aggregate-' . $size;
            $expected = [];
            for ($index = 0; $index < $size; $index++) {
                $review = WpsrFactory::review(['review_title' => $token . ' row ' . $index]);
                $category = WpsrFactory::category(['name' => 'Throughput aggregate ' . $size . '-' . $index]);
                WpsrFactory::relationship((int) $review->id, (int) $category->id);
                $expected[(int) $category->id] = 1;
            }

            $measurement = WpsrThroughput::measure('category-aggregate N=' . $size, function () {
                return ReviewCategory::allOrderedWithCounts();
            });
            $actual = [];
            foreach ($measurement['result'] as $category) {
                $id = (int) $category->id;
                if (isset($expected[$id])) {
                    $actual[$id] = (int) $category->reviews_count;
                }
            }
            ksort($actual, SORT_NUMERIC);
            ksort($expected, SORT_NUMERIC);
            WpsrTest::assertSame($expected, $actual, 'category aggregate returns exact owned cardinality/count map at N=' . $size);
            return $measurement;
        };

        try {
            $small = $wpsr_run(2);
            WpsrFactory::cleanup();
            $large = $wpsr_run(12);
            WpsrThroughput::assertCountBudget($small, $large, 2, 2, 0, 'category aggregate');
            WpsrTest::assertSame(1, $small['count'], 'category aggregate small observed statement count');
            WpsrTest::assertSame(1, $large['count'], 'category aggregate large observed statement count');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('template list metadata serialization is query-bounded at 2 and 8 rows', function () {
        $wpsr_run = function ($size) {
            $token = WpsrFactory::marker() . '-throughput-template-' . $size;
            $ids = [];
            for ($index = 0; $index < $size; $index++) {
                $post = WpsrFactory::post('wp_social_reviews', [
                    'post_title'   => $token . ' row ' . $index,
                    'post_content' => 'google',
                ]);
                update_post_meta((int) $post->ID, '_wpsr_template_config', wp_json_encode(['platform' => ['google']]));
                clean_post_cache((int) $post->ID);
                $ids[] = (int) $post->ID;
            }

            $measurement = WpsrThroughput::measure('template-list N=' . $size, function () use ($token) {
                return (new Post())->getPosts('wp_social_reviews', $token, '', false);
            });
            $actual = array_map('intval', $measurement['result']->pluck('ID')->toArray());
            sort($ids, SORT_NUMERIC);
            sort($actual, SORT_NUMERIC);
            WpsrTest::assertSame($ids, $actual, 'template list returns exact owned rows at N=' . $size);

            // Serialization reads platform names from post meta. One primed read covers the
            // page, so nothing may ask for more rows than the page itself returned.
            $pageLimit = 0;
            foreach ($measurement['queries'] as $query) {
                foreach (WpsrThroughput::limitValues($query) as $limit) {
                    $pageLimit = max($pageLimit, $limit);
                }
            }
            WpsrTest::assertTrue($pageLimit > 0, 'template list paginates at N=' . $size);
            WpsrThroughput::assertStatementCaps($measurement['queries'], $pageLimit, $pageLimit, $pageLimit, 'template list N=' . $size);
            return $measurement;
        };

        try {
            $small = $wpsr_run(2);
            WpsrFactory::cleanup();
            $large = $wpsr_run(8);
            WpsrThroughput::assertCountBudget($small, $large, 3, 3, 0, 'template list');
            WpsrTest::assertSame($small['count'], $large['count'], 'template list exact query count is size-invariant');
            WpsrTest::assertSame(3, $small['count'], 'template list small observed statement count');
            WpsrTest::assertSame(3, $large['count'], 'template list large observed statement count');
        } finally {
            WpsrFactory::cleanup();
        }
    });
};
