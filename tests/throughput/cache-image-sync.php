<?php
/**
 * Phase 16: cache/image cleanup caps and parked per-item review sync.
 */

use WPSocialReviews\App\Models\Cache;
use WPSocialReviews\App\Models\OptimizeImage;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\App\Services\Platforms\ReviewImageOptimizationHandler;
use WPSocialReviews\App\Services\Platforms\Reviews\BaseReview;

return function () {
    WpsrTest::case('platform cache cleanup remains one query at 3 and 20 rows', function () {
        $wpsr_run = function ($size) {
            $platform = sanitize_key(WpsrFactory::marker() . '-cache-platform-' . $size);
            $ids = [];
            for ($index = 0; $index < $size; $index++) {
                $ids[] = (int) WpsrFactory::cache($platform, 'throughput-' . $size . '-' . $index, ['index' => $index])->id;
            }
            $foreign = WpsrFactory::cache('youtube', 'throughput-foreign-' . $size, ['owner' => 'foreign']);

            $measurement = WpsrThroughput::measure('cache-clear N=' . $size, function () use ($platform) {
                (new CacheHandler($platform))->clearCache();
                return true;
            });
            WpsrTest::assertSame(0, Cache::whereIn('id', $ids)->count(), 'cache clear removes exact target cardinality at N=' . $size);
            WpsrTest::assertTrue((bool) Cache::find((int) $foreign->id), 'cache clear preserves foreign platform at N=' . $size);
            return $measurement;
        };

        try {
            $small = $wpsr_run(3);
            WpsrFactory::cleanup();
            $large = $wpsr_run(20);
            WpsrThroughput::assertCountBudget($small, $large, 1, 1, 0, 'platform cache clear');
            WpsrTest::assertSame(1, $small['count'], 'cache clear small observed statement count');
            WpsrTest::assertSame(1, $large['count'], 'cache clear large observed statement count');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('image eviction caps selection and deletes at 2 across 3 and 12 rows', function () {
        $wpsr_run = function ($size) {
            $platform = sanitize_key(WpsrFactory::marker() . '-image-platform-' . $size);
            $ids = [];
            for ($index = 0; $index < $size; $index++) {
                $ids[] = (int) WpsrFactory::optimizeImage($platform, [
                    '_identity'     => 'throughput-' . $size . '-' . $index,
                    'last_requested' => sprintf('2026-01-%02d 00:00:00', $index + 1),
                ])->id;
            }
            $foreign = WpsrFactory::optimizeImage('youtube', [
                '_identity'      => 'throughput-foreign-' . $size,
                'last_requested' => '2025-01-01 00:00:00',
            ]);
            $handler = new class($platform) extends ReviewImageOptimizationHandler {
                public function getUploadDir($platform)
                {
                    return sys_get_temp_dir() . '/wpsr-phase16-no-files-' . sanitize_key($platform);
                }
            };
            $handler->availableRecords = 2;

            $measurement = WpsrThroughput::measure('image-eviction N=' . $size, function () use ($handler, $platform) {
                $handler->deleteLeastUsedImages($platform);
                return true;
            });
            $remaining = array_map('intval', OptimizeImage::whereIn('id', $ids)->orderBy('id', 'asc')->pluck('id')->toArray());
            WpsrTest::assertSame(array_slice($ids, 2), $remaining, 'image eviction removes exact two oldest target rows at N=' . $size);
            WpsrTest::assertTrue((bool) OptimizeImage::find((int) $foreign->id), 'image eviction preserves foreign platform at N=' . $size);
            WpsrThroughput::assertStatementCaps($measurement['queries'], 2, 2, 2, 'image eviction N=' . $size);
            return $measurement;
        };

        try {
            $small = $wpsr_run(3);
            WpsrFactory::cleanup();
            $large = $wpsr_run(12);
            WpsrThroughput::assertCountBudget($small, $large, 3, 3, 0, 'image eviction');
            WpsrTest::assertSame(3, $small['count'], 'image eviction small observed statement count');
            WpsrTest::assertSame(3, $large['count'], 'image eviction large observed statement count');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('review sync upserts are bounded at 2 and 8 remote items', function () {
        $wpsr_run = function ($size) {
            $marker = WpsrFactory::marker();
            $place = $marker . '-throughput-sync-place-' . $size;
            WpsrFactory::ownedOption('last_fetched_reviews_id', ['_wpsr_test_marker' => $marker]);
            $service = new class('yelp', $marker . '-settings', $marker . '-cron') extends BaseReview {
                public function formatData($review, $index)
                {
                    return [
                        'platform_name'   => 'yelp',
                        'source_id'       => (string) ($review['source_id'] ?? ''),
                        'review_id'       => (string) ($review['id'] ?? ''),
                        'review_title'    => 'Throughput sync ' . (int) $index,
                        'reviewer_name'   => 'Throughput reviewer ' . (int) $index,
                        'reviewer_text'   => 'Throughput body ' . (int) $index,
                        'review_time'     => '2026-09-01 00:00:00',
                        'rating'          => 5,
                        'review_approved' => 1,
                    ];
                }
                public function getApiSettings()
                {
                    return [];
                }
            };
            $remote = [];
            for ($index = 0; $index < $size; $index++) {
                $remote[] = [
                    'id'        => $marker . '-throughput-sync-' . $size . '-' . $index,
                    'source_id' => $place,
                ];
            }

            $measurement = WpsrThroughput::measure('review-sync N=' . $size, function () use ($service, $remote, $place) {
                $service->syncRemoteReviews($remote, $place);
                return true;
            });
            $rows = Review::where('platform_name', 'yelp')->where('source_id', $place)->get();
            foreach ($rows as $row) {
                WpsrFactory::trackReview($row);
            }
            WpsrTest::assertSame($size, count($rows), 'review sync exact upsert cardinality at N=' . $size);
            WpsrThroughput::assertStatementCaps(
                $measurement['queries'],
                BaseReview::SYNC_BATCH_SIZE,
                BaseReview::SYNC_BATCH_SIZE,
                BaseReview::SYNC_BATCH_SIZE,
                'review sync N=' . $size
            );
            // Total count carries option-cache noise that depends on run order, so the
            // size-invariance claim is made against the review-table statements alone.
            WpsrTest::assertSame(
                2,
                count(WpsrThroughput::queriesForTable($measurement, 'wpsr_reviews')),
                'review sync emits one lookup and one insert at N=' . $size
            );
            return $measurement;
        };

        try {
            $small = $wpsr_run(2);
            WpsrFactory::cleanup();
            $large = $wpsr_run(8);
            WpsrThroughput::assertCountBudget($small, $large, 6, 6, 0, 'review sync');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('review sync re-run matches existing rows instead of duplicating them', function () {
        $marker = WpsrFactory::marker();
        $place = $marker . '-resync-place';
        WpsrFactory::ownedOption('last_fetched_reviews_id', ['_wpsr_test_marker' => $marker]);
        $service = new class('yelp', $marker . '-settings', $marker . '-cron') extends BaseReview {
            public function formatData($review, $index)
            {
                return [
                    'platform_name'   => 'yelp',
                    'source_id'       => (string) ($review['source_id'] ?? ''),
                    'review_id'       => (string) ($review['id'] ?? ''),
                    'review_title'    => 'Resync ' . (int) $index,
                    'reviewer_name'   => 'Resync reviewer ' . (int) $index,
                    'reviewer_text'   => 'Resync body ' . (int) $index,
                    'review_time'     => '2026-09-01 00:00:00',
                    'rating'          => 5,
                    'review_approved' => 1,
                ];
            }
            public function getApiSettings()
            {
                return [];
            }
        };

        $remote = [];
        for ($index = 0; $index < 4; $index++) {
            $remote[] = ['id' => $marker . '-resync-' . $index, 'source_id' => $place];
        }

        $wpsr_owned = function () use ($place) {
            $rows = Review::where('platform_name', 'yelp')->where('source_id', $place)->get();
            foreach ($rows as $row) {
                WpsrFactory::trackReview($row);
            }
            return $rows;
        };

        try {
            $service->syncRemoteReviews($remote, $place);
            WpsrTest::assertSame(4, count($wpsr_owned()), 'first sync inserts every remote item');

            $second = WpsrThroughput::measure('review-resync', function () use ($service, $remote, $place) {
                $service->syncRemoteReviews($remote, $place);
                return true;
            });

            $rows = $wpsr_owned();
            WpsrTest::assertSame(4, count($rows), 're-sync updates in place instead of duplicating');
            WpsrTest::assertSame(0, count(array_filter(
                WpsrThroughput::queriesForTable($second, 'wpsr_reviews'),
                function ($query) {
                    return stripos(trim($query), 'insert') === 0;
                }
            )), 're-sync inserts nothing');
            WpsrTest::assertSame(1, count(array_filter(
                WpsrThroughput::queriesForTable($second, 'wpsr_reviews'),
                function ($query) {
                    return stripos(trim($query), 'select') === 0;
                }
            )), 're-sync resolves every existing row in one lookup');
        } finally {
            WpsrFactory::cleanup();
        }
    });
};
