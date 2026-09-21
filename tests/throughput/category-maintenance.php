<?php
/**
 * Phase 16: category backfill and orphan-sweep chunk throughput.
 */

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Models\ReviewCategoryRelationship;
use WPSocialReviews\Database\Migrations\ReviewCategoriesMigrator;

return function () {
    $wpsr_state = function () {
        return [
            'backfill_option' => ReviewCategoriesMigrator::$backfillOption,
            'cursor_option'   => ReviewCategoriesMigrator::$backfillCursorOption,
            'sweep_option'    => ReviewCategoriesMigrator::$orphanSweepPendingOption,
            'chunk_size'      => ReviewCategoriesMigrator::$backfillChunkSize,
            'max_chunks'      => ReviewCategoriesMigrator::$backfillMaxChunksPerRequest,
        ];
    };
    $wpsr_restore = function ($state) {
        ReviewCategoriesMigrator::$backfillOption = $state['backfill_option'];
        ReviewCategoriesMigrator::$backfillCursorOption = $state['cursor_option'];
        ReviewCategoriesMigrator::$orphanSweepPendingOption = $state['sweep_option'];
        ReviewCategoriesMigrator::$backfillChunkSize = $state['chunk_size'];
        ReviewCategoriesMigrator::$backfillMaxChunksPerRequest = $state['max_chunks'];
    };

    WpsrTest::case('category backfill limits every query/write chunk to 4 at 3 and 8 reviews', function () use ($wpsr_state, $wpsr_restore) {
        global $wpdb;
        $original = $wpsr_state();
        $method = new ReflectionMethod(ReviewCategoriesMigrator::class, 'backfillFromCategoryColumn');
        $method->setAccessible(true);

        $wpsr_run = function ($size) use ($wpdb, $method) {
            $token = WpsrFactory::marker() . '-throughput-backfill-' . $size;
            $cursor = $token . '-cursor';
            WpsrFactory::option($cursor, 0);
            ReviewCategoriesMigrator::$backfillCursorOption = $cursor;
            ReviewCategoriesMigrator::$backfillChunkSize = 4;
            ReviewCategoriesMigrator::$backfillMaxChunksPerRequest = 2;

            $ids = [];
            for ($index = 0; $index < $size; $index++) {
                $ids[] = (int) WpsrFactory::review([
                    'review_title' => $token . ' row ' . $index,
                    'category'     => $token . ' Shared',
                ])->id;
            }

            // Start immediately before owned IDs: no empty-table assumption and no pre-existing row is touched.
            $startCursor = max(0, min($ids) - 1);
            // Prime table-existence and option-schema caches outside measured operation.
            ReviewCategoriesMigrator::tablesExist();
            update_option($cursor, $startCursor + 1, 'no');
            update_option($cursor, $startCursor, 'no');

            $measurement = WpsrThroughput::measure('category-backfill N=' . $size, function () use ($method, $wpdb) {
                return $method->invoke(
                    null,
                    $wpdb->prefix . ReviewCategoriesMigrator::$categoriesTable,
                    $wpdb->prefix . ReviewCategoriesMigrator::$relationshipsTable
                );
            });
            WpsrTest::assertSame(
                $size,
                ReviewCategoryRelationship::whereIn('review_id', $ids)->count(),
                'backfill writes exact owned relationship cardinality at N=' . $size
            );
            $category = ReviewCategory::where('slug', sanitize_title($token . ' Shared'))->first();
            WpsrTest::assertTrue((bool) $category, 'backfill creates owned shared category at N=' . $size);
            if ($category) {
                WpsrFactory::trackCategory($category);
            }
            WpsrThroughput::assertStatementCaps($measurement['queries'], 4, 4, 4, 'category backfill N=' . $size);
            return $measurement;
        };

        try {
            $small = $wpsr_run(3);
            WpsrFactory::cleanup();
            $large = $wpsr_run(8);
            WpsrThroughput::assertCountBudget($small, $large, 12, 12, 0, 'category backfill');
            WpsrTest::assertSame(11, $small['count'], 'category backfill small observed statement count');
            WpsrTest::assertSame(11, $large['count'], 'category backfill large observed statement count');
        } finally {
            $wpsr_restore($original);
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('orphan sweep inspects/deletes at most 4 rows per chunk at 3 and 8 relationships', function () use ($wpsr_state, $wpsr_restore) {
        global $wpdb;
        $original = $wpsr_state();
        $method = new ReflectionMethod(ReviewCategoriesMigrator::class, 'sweepOrphanedRelationships');
        $method->setAccessible(true);

        $wpsr_run = function ($size) use ($wpdb, $method) {
            $token = WpsrFactory::marker() . '-throughput-sweep-' . $size;
            $pending = $token . '-pending';
            WpsrFactory::option($pending, 0);
            ReviewCategoriesMigrator::$orphanSweepPendingOption = $pending;
            ReviewCategoriesMigrator::$backfillChunkSize = 4;
            ReviewCategoriesMigrator::$backfillMaxChunksPerRequest = 2;

            $category = WpsrFactory::category(['name' => 'Throughput sweep ' . $size]);
            $reviewIds = [];
            $relationshipIds = [];
            for ($index = 0; $index < $size; $index++) {
                $review = WpsrFactory::review(['review_title' => $token . ' row ' . $index]);
                $relationship = WpsrFactory::relationship((int) $review->id, (int) $category->id);
                $reviewIds[] = (int) $review->id;
                $relationshipIds[] = (int) $relationship->id;
            }
            Review::whereIn('id', $reviewIds)->delete();
            WpsrTest::assertNoDatabaseError('orphan sweep throughput parent setup');
            // Start immediately before owned relationships so foreign lab rows are never inspected/deleted.
            update_option($pending, max(0, min($relationshipIds) - 1), 'no');

            $measurement = WpsrThroughput::measure('orphan-sweep N=' . $size, function () use ($method, $wpdb) {
                $method->invoke(
                    null,
                    $wpdb->prefix . ReviewCategoriesMigrator::$categoriesTable,
                    $wpdb->prefix . ReviewCategoriesMigrator::$relationshipsTable
                );
                return true;
            });
            WpsrTest::assertSame(0, ReviewCategoryRelationship::whereIn('id', $relationshipIds)->count(), 'orphan sweep removes exact owned cardinality at N=' . $size);
            WpsrThroughput::assertStatementCaps($measurement['queries'], 4, 4, 4, 'orphan sweep N=' . $size);
            return $measurement;
        };

        try {
            $small = $wpsr_run(3);
            WpsrFactory::cleanup();
            $large = $wpsr_run(8);
            WpsrThroughput::assertCountBudget($small, $large, 5, 6, 1, 'orphan sweep');
            WpsrTest::assertSame(4, $small['count'], 'orphan sweep small observed statement count');
            WpsrTest::assertSame(5, $large['count'], 'orphan sweep large observed statement count');
        } finally {
            $wpsr_restore($original);
            WpsrFactory::cleanup();
        }
    });
};
