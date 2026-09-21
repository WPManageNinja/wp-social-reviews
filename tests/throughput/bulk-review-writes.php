<?php
/**
 * Phase 16: review bulk-write chunking and parked unbounded statements.
 */

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Models\ReviewCategoryRelationship;

return function () {
    WpsrTest::case('bulk category attach uses 500-row statements at 3 and 501 reviews', function () {
        $wpsr_run = function ($size) {
            $category = WpsrFactory::category(['name' => 'Throughput bulk ' . $size]);
            $ids = [];
            for ($index = 0; $index < $size; $index++) {
                $review = WpsrFactory::review([
                    'platform_name' => 'custom',
                    'review_title'  => 'Throughput bulk category ' . $size . '-' . $index,
                ]);
                $ids[] = (int) $review->id;
            }

            $measurement = WpsrThroughput::measure('bulk-category N=' . $size, function () use ($ids, $category) {
                return WpsrTest::rest('PUT', '/reviews/bulk-category', [
                    'ids'         => $ids,
                    'action'      => 'add',
                    'category_id' => (int) $category->id,
                ]);
            });
            WpsrTest::assertHealthy($measurement['result'], 'bulk category throughput response', [200]);
            WpsrTest::assertSame(
                $size,
                ReviewCategoryRelationship::whereIn('review_id', $ids)->where('category_id', (int) $category->id)->count(),
                'bulk category writes exact relationship cardinality at N=' . $size
            );
            WpsrThroughput::assertStatementCaps(
                $measurement['queries'],
                ReviewCategoryRelationship::BATCH_SIZE,
                ReviewCategoryRelationship::BATCH_SIZE,
                ReviewCategoryRelationship::BATCH_SIZE,
                'bulk category N=' . $size
            );
            return $measurement;
        };

        try {
            $small = $wpsr_run(3);
            WpsrFactory::cleanup();
            $large = $wpsr_run(501);
            WpsrThroughput::assertCountBudget($small, $large, 4, 6, 2, 'bulk category');
            WpsrTest::assertSame(3, $small['count'], 'bulk category small observed statement count');
            WpsrTest::assertSame(5, $large['count'], 'bulk category large observed statement count');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('bulk status update uses 500-row statements at 3 and 501 reviews', function () {
        $wpsr_run = function ($size) {
            $ids = [];
            for ($index = 0; $index < $size; $index++) {
                $ids[] = (int) WpsrFactory::review([
                    'platform_name'   => 'custom',
                    'review_approved' => 1,
                    'review_title'    => 'Throughput bulk status ' . $size . '-' . $index,
                ])->id;
            }
            $measurement = WpsrThroughput::measure('bulk-status N=' . $size, function () use ($ids) {
                return WpsrTest::rest('PUT', '/reviews/status-update', ['ids' => $ids, 'status' => 'disable']);
            });
            WpsrTest::assertHealthy($measurement['result'], 'bulk status throughput response', [200]);
            WpsrTest::assertSame($size, Review::whereIn('id', $ids)->where('review_approved', 0)->count(), 'bulk status exact cardinality at N=' . $size);
            WpsrThroughput::assertStatementCaps(
                $measurement['queries'],
                ReviewCategoryRelationship::BATCH_SIZE,
                ReviewCategoryRelationship::BATCH_SIZE,
                ReviewCategoryRelationship::BATCH_SIZE,
                'bulk status N=' . $size
            );
            return $measurement;
        };

        try {
            $small = $wpsr_run(3);
            WpsrFactory::cleanup();
            $large = $wpsr_run(501);
            // Three passes (scope, update, read-back) so 501 ids cost one extra statement
            // each, plus the BEGIN and COMMIT wrapping the update pass.
            WpsrThroughput::assertCountBudget($small, $large, 5, 8, 3, 'bulk status');
            WpsrTest::assertSame(5, $small['count'], 'bulk status small observed statement count');
            WpsrTest::assertSame(8, $large['count'], 'bulk status large observed statement count');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('bulk spam update uses 500-row statements at 3 and 501 reviews', function () {
        $wpsr_run = function ($size) {
            $ids = [];
            for ($index = 0; $index < $size; $index++) {
                $ids[] = (int) WpsrFactory::review([
                    'platform_name'   => 'custom',
                    'review_approved' => 1,
                    'review_title'    => 'Throughput bulk spam ' . $size . '-' . $index,
                ])->id;
            }
            $measurement = WpsrThroughput::measure('bulk-spam N=' . $size, function () use ($ids) {
                return WpsrTest::rest('PUT', '/reviews/spam', ['ids' => $ids, 'action' => 'mark-spam']);
            });
            WpsrTest::assertHealthy($measurement['result'], 'bulk spam throughput response', [200]);
            WpsrTest::assertSame($size, Review::whereIn('id', $ids)->where('review_approved', 2)->count(), 'bulk spam exact cardinality at N=' . $size);
            WpsrThroughput::assertStatementCaps(
                $measurement['queries'],
                ReviewCategoryRelationship::BATCH_SIZE,
                ReviewCategoryRelationship::BATCH_SIZE,
                ReviewCategoryRelationship::BATCH_SIZE,
                'bulk spam N=' . $size
            );
            return $measurement;
        };

        try {
            $small = $wpsr_run(3);
            WpsrFactory::cleanup();
            $large = $wpsr_run(501);
            // Same three passes as bulk status, plus the update pass's BEGIN and COMMIT.
            WpsrThroughput::assertCountBudget($small, $large, 5, 8, 3, 'bulk spam');
            WpsrTest::assertSame(5, $small['count'], 'bulk spam small observed statement count');
            WpsrTest::assertSame(8, $large['count'], 'bulk spam large observed statement count');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('bulk review delete uses 500-row statements at 3 and 501 reviews', function () {
        $wpsr_run = function ($size) {
            $ids = [];
            for ($index = 0; $index < $size; $index++) {
                $ids[] = (int) WpsrFactory::review([
                    'platform_name' => 'custom',
                    'review_title'  => 'Throughput bulk delete ' . $size . '-' . $index,
                ])->id;
            }
            $measurement = WpsrThroughput::measure('bulk-delete N=' . $size, function () use ($ids) {
                return WpsrTest::rest('DELETE', '/reviews', ['ids' => $ids]);
            });
            WpsrTest::assertHealthy($measurement['result'], 'bulk delete throughput response', [200]);
            WpsrTest::assertSame(0, Review::whereIn('id', $ids)->count(), 'bulk delete removes exact owned cardinality at N=' . $size);
            // The reported count is summed across chunks, so a dropped chunk shows up here.
            preg_match('/^(\d+)/', (string) $measurement['result']['data'], $reported);
            WpsrTest::assertSame($size, (int) ($reported[1] ?? -1), 'bulk delete reports exact deleted count at N=' . $size);
            WpsrThroughput::assertStatementCaps(
                $measurement['queries'],
                ReviewCategoryRelationship::BATCH_SIZE,
                ReviewCategoryRelationship::BATCH_SIZE,
                ReviewCategoryRelationship::BATCH_SIZE,
                'bulk delete N=' . $size
            );
            return $measurement;
        };

        try {
            $small = $wpsr_run(3);
            WpsrFactory::cleanup();
            $large = $wpsr_run(501);
            // Four passes: scope, snapshot, delete, pivot cleanup. The last two share one
            // transaction, so its BEGIN and COMMIT add two more statements.
            WpsrThroughput::assertCountBudget($small, $large, 6, 10, 4, 'bulk delete');
            WpsrTest::assertSame(6, $small['count'], 'bulk delete small observed statement count');
            WpsrTest::assertSame(10, $large['count'], 'bulk delete large observed statement count');
        } finally {
            WpsrFactory::cleanup();
        }
    });
};
