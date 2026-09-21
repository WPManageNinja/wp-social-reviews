<?php
/**
 * Phase 8: foundational ordering, cursor, idempotency, and scheduling invariants.
 */

use WPSocialReviews\App\Hooks\Handlers\ActivateCronEvent;
use WPSocialReviews\App\Models\OptimizeImage;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Models\ReviewCategoryRelationship;
use WPSocialReviews\App\Services\Platforms\ReviewImageOptimizationHandler;
use WPSocialReviews\App\Services\Platforms\Reviews\BaseReview;
use WPSocialReviews\Database\Migrations\ReviewCategoriesMigrator;

return function () {
    $wpsr_cron_events = function (array $wpsr_hooks) {
        $wpsr_events = array_fill_keys($wpsr_hooks, []);
        foreach ((array) _get_cron_array() as $wpsr_timestamp => $wpsr_timestamp_hooks) {
            if ($wpsr_timestamp === 'version' || !is_array($wpsr_timestamp_hooks)) {
                continue;
            }
            foreach ($wpsr_hooks as $wpsr_hook) {
                foreach ((array) ($wpsr_timestamp_hooks[$wpsr_hook] ?? []) as $wpsr_event) {
                    $wpsr_events[$wpsr_hook][] = [
                        'timestamp' => (int) $wpsr_timestamp,
                        'schedule'  => $wpsr_event['schedule'] ?? false,
                        'interval'  => isset($wpsr_event['interval']) ? (int) $wpsr_event['interval'] : 0,
                        'args'      => array_values((array) ($wpsr_event['args'] ?? [])),
                    ];
                }
            }
        }
        return $wpsr_events;
    };

    $wpsr_relationship_ids = function (array $wpsr_ids) {
        return array_map('intval', ReviewCategoryRelationship::whereIn('id', $wpsr_ids)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->toArray());
    };

    $wpsr_migrator_state = function () {
        return [
            'backfill_option' => ReviewCategoriesMigrator::$backfillOption,
            'cursor_option'   => ReviewCategoriesMigrator::$backfillCursorOption,
            'sweep_option'    => ReviewCategoriesMigrator::$orphanSweepPendingOption,
            'chunk_size'      => ReviewCategoriesMigrator::$backfillChunkSize,
            'max_chunks'      => ReviewCategoriesMigrator::$backfillMaxChunksPerRequest,
        ];
    };

    $wpsr_restore_migrator = function (array $wpsr_state) {
        ReviewCategoriesMigrator::$backfillOption = $wpsr_state['backfill_option'];
        ReviewCategoriesMigrator::$backfillCursorOption = $wpsr_state['cursor_option'];
        ReviewCategoriesMigrator::$orphanSweepPendingOption = $wpsr_state['sweep_option'];
        ReviewCategoriesMigrator::$backfillChunkSize = $wpsr_state['chunk_size'];
        ReviewCategoriesMigrator::$backfillMaxChunksPerRequest = $wpsr_state['max_chunks'];
    };

    WpsrTest::case('global and per-platform cron registration stays single and replaces recurrence deterministically', function () use ($wpsr_cron_events) {
        $wpsr_global_hooks = [
            'wpsr_cron_job',
            'wpsr_do_email_report_scheduled_tasks',
            'wpsr_scheduled_twicedaily',
            'wpsr_scheduled_weekly',
        ];
        $wpsr_expected_schedules = [
            'wpsr_cron_job'                        => ['hourly', 3600],
            'wpsr_do_email_report_scheduled_tasks' => ['daily', 86400],
            'wpsr_scheduled_twicedaily'             => ['twicedaily', 43200],
            'wpsr_scheduled_weekly'                 => ['1week', 604800],
        ];

        $wpsr_before = $wpsr_cron_events($wpsr_global_hooks);
        foreach ($wpsr_expected_schedules as $wpsr_hook => $wpsr_expected) {
            WpsrTest::assertSame(1, count($wpsr_before[$wpsr_hook]), $wpsr_hook . ' has exactly one global event before repeat registration');
            WpsrTest::assertSame($wpsr_expected[0], $wpsr_before[$wpsr_hook][0]['schedule'] ?? null, $wpsr_hook . ' recurrence value');
            WpsrTest::assertSame($wpsr_expected[1], $wpsr_before[$wpsr_hook][0]['interval'] ?? null, $wpsr_hook . ' interval value');
        }

        (new ActivateCronEvent())->activate();
        (new ActivateCronEvent())->activate();
        WpsrTest::assertSame($wpsr_before, $wpsr_cron_events($wpsr_global_hooks), 'repeat global registration preserves exact event set and timestamps');

        $wpsr_platform = WpsrFactory::marker() . '-schedule-platform';
        $wpsr_hook = WpsrFactory::trackCronHook(WpsrFactory::marker() . '-reviews-update');
        $wpsr_option = WpsrFactory::option('wpsr_' . $wpsr_platform . '_global_settings', [
            'global_settings' => [
                'auto_syncing'           => 'true',
                'fetch_review_recurrence' => '2days',
            ],
        ]);
        $wpsr_service = new class($wpsr_platform, WpsrFactory::marker() . '-settings', $wpsr_hook) extends BaseReview {
            public function formatData($review, $index)
            {
                return (array) $review;
            }

            public function getApiSettings()
            {
                return [];
            }
        };

        try {
            $wpsr_service->activateCronEvent();
            $wpsr_first = $wpsr_cron_events([$wpsr_hook]);
            WpsrTest::assertSame(1, count($wpsr_first[$wpsr_hook]), 'enabled platform owns exactly one scheduled refresh');
            WpsrTest::assertSame('2days', $wpsr_first[$wpsr_hook][0]['schedule'] ?? null, 'initial platform recurrence is persisted setting');
            WpsrTest::assertSame(172800, $wpsr_first[$wpsr_hook][0]['interval'] ?? null, 'initial platform interval is exact');

            update_option($wpsr_option, [
                'global_settings' => [
                    'auto_syncing'           => 'true',
                    'fetch_review_recurrence' => '3days',
                ],
            ], 'no');
            $wpsr_service->activateCronEvent();
            $wpsr_replaced = $wpsr_cron_events([$wpsr_hook]);
            WpsrTest::assertSame(1, count($wpsr_replaced[$wpsr_hook]), 'recurrence replacement does not double-schedule platform refresh');
            WpsrTest::assertSame('3days', $wpsr_replaced[$wpsr_hook][0]['schedule'] ?? null, 'recurrence replacement removes old event before scheduling new');
            WpsrTest::assertSame(259200, $wpsr_replaced[$wpsr_hook][0]['interval'] ?? null, 'replacement interval is exact');

            update_option($wpsr_option, ['global_settings' => ['auto_syncing' => 'false']], 'no');
            $wpsr_service->activateCronEvent();
            WpsrTest::assertSame([], $wpsr_cron_events([$wpsr_hook])[$wpsr_hook], 'disabling auto sync clears owned platform schedule');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('category backfill advances in bounded ordered chunks, resumes, and repeats without duplicate writes', function () use ($wpsr_migrator_state, $wpsr_restore_migrator) {
        $wpsr_original = $wpsr_migrator_state();
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_backfill_option = $wpsr_marker . '-category-backfilled';
        $wpsr_cursor_option = $wpsr_marker . '-category-cursor';
        $wpsr_sweep_option = $wpsr_marker . '-category-sweep';
        $wpsr_missing = $wpsr_marker . '-missing';

        WpsrFactory::trackOptionName($wpsr_backfill_option);
        WpsrFactory::trackOptionName($wpsr_cursor_option);
        WpsrFactory::trackOptionName($wpsr_sweep_option);

        ReviewCategoriesMigrator::$backfillOption = $wpsr_backfill_option;
        ReviewCategoriesMigrator::$backfillCursorOption = $wpsr_cursor_option;
        ReviewCategoriesMigrator::$orphanSweepPendingOption = $wpsr_sweep_option;
        ReviewCategoriesMigrator::$backfillChunkSize = 2;
        ReviewCategoriesMigrator::$backfillMaxChunksPerRequest = 1;

        try {
            $wpsr_reviews = [];
            foreach (['Alpha', 'Beta', 'Alpha', 'Gamma', 'Beta'] as $wpsr_index => $wpsr_category) {
                $wpsr_reviews[] = WpsrFactory::review([
                    'review_title' => 'Bounded migration row ' . $wpsr_index,
                    'review_time'  => sprintf('2026-06-01 00:00:%02d', $wpsr_index),
                    'category'     => $wpsr_marker . ' Category ' . $wpsr_category,
                ]);
            }
            $wpsr_review_ids = array_map(function ($wpsr_review) {
                return (int) $wpsr_review->id;
            }, $wpsr_reviews);

            ReviewCategoriesMigrator::migrate();
            WpsrTest::assertSame($wpsr_review_ids[1], (int) get_option($wpsr_cursor_option), 'first bounded pass cursor is second ordered review ID');
            WpsrTest::assertSame(2, ReviewCategoryRelationship::whereIn('review_id', $wpsr_review_ids)->count(), 'first pass writes at most one two-row chunk');
            WpsrTest::assertSame(false, (bool) get_option($wpsr_backfill_option), 'partial first pass does not claim completion');

            ReviewCategoriesMigrator::migrate();
            WpsrTest::assertSame($wpsr_review_ids[3], (int) get_option($wpsr_cursor_option), 'second pass resumes strictly after first cursor');
            WpsrTest::assertSame(4, ReviewCategoryRelationship::whereIn('review_id', $wpsr_review_ids)->count(), 'second pass adds only next bounded chunk');

            ReviewCategoriesMigrator::migrate();
            WpsrTest::assertSame($wpsr_review_ids[4], (int) get_option($wpsr_cursor_option), 'third pass advances cursor through final nonempty row');
            WpsrTest::assertSame(5, ReviewCategoryRelationship::whereIn('review_id', $wpsr_review_ids)->count(), 'third pass adds final relationship once');

            ReviewCategoriesMigrator::migrate();
            WpsrTest::assertSame($wpsr_missing, get_option($wpsr_cursor_option, $wpsr_missing), 'empty continuation clears completed backfill cursor');
            WpsrTest::assertSame(true, (bool) get_option($wpsr_backfill_option), 'completion marker is written only after exhausted cursor');

            $wpsr_categories = ReviewCategory::where('slug', 'like', sanitize_title($wpsr_marker) . '-category-%')
                ->orderBy('slug', 'asc')
                ->get();
            WpsrTest::assertSame([
                sanitize_title($wpsr_marker . ' Category Alpha'),
                sanitize_title($wpsr_marker . ' Category Beta'),
                sanitize_title($wpsr_marker . ' Category Gamma'),
            ], array_values($wpsr_categories->pluck('slug')->toArray()), 'chunk-local category resolution deduplicates exact slugs deterministically');

            $wpsr_pairs_before = ReviewCategoryRelationship::whereIn('review_id', $wpsr_review_ids)
                ->orderBy('review_id', 'asc')
                ->get()
                ->map(function ($wpsr_relationship) {
                    return [(int) $wpsr_relationship->review_id, (int) $wpsr_relationship->category_id];
                })
                ->toArray();

            ReviewCategoriesMigrator::maybeContinueOrphanSweep();
            ReviewCategoriesMigrator::maybeContinueOrphanSweep();
            WpsrTest::assertSame($wpsr_missing, get_option($wpsr_sweep_option, $wpsr_missing), 'bounded completion sweep drains its persisted cursor');

            ReviewCategoriesMigrator::migrate();
            ReviewCategoriesMigrator::maybeContinueOrphanSweep();
            ReviewCategoriesMigrator::maybeContinueOrphanSweep();
            $wpsr_pairs_after = ReviewCategoryRelationship::whereIn('review_id', $wpsr_review_ids)
                ->orderBy('review_id', 'asc')
                ->get()
                ->map(function ($wpsr_relationship) {
                    return [(int) $wpsr_relationship->review_id, (int) $wpsr_relationship->category_id];
                })
                ->toArray();
            WpsrTest::assertSame($wpsr_pairs_before, $wpsr_pairs_after, 'repeat completed migration and sweep are value-idempotent with no duplicate links');
            WpsrTest::assertSame(3, ReviewCategory::whereIn('id', array_map('intval', $wpsr_categories->pluck('id')->toArray()))->count(), 'repeat migration does not duplicate category rows');
        } finally {
            $wpsr_restore_migrator($wpsr_original);
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('orphan sweep cursor advances over clean and orphan rows and deletes only lost ownership', function () use ($wpsr_relationship_ids, $wpsr_migrator_state, $wpsr_restore_migrator) {
        $wpsr_original = $wpsr_migrator_state();
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_backfill_option = $wpsr_marker . '-orphan-backfilled';
        $wpsr_sweep_option = $wpsr_marker . '-orphan-sweep';
        WpsrFactory::option($wpsr_backfill_option, true);
        WpsrFactory::option($wpsr_sweep_option, 0);

        ReviewCategoriesMigrator::$backfillOption = $wpsr_backfill_option;
        ReviewCategoriesMigrator::$orphanSweepPendingOption = $wpsr_sweep_option;
        ReviewCategoriesMigrator::$backfillChunkSize = 1;
        ReviewCategoriesMigrator::$backfillMaxChunksPerRequest = 1;

        try {
            $wpsr_valid_review = WpsrFactory::review(['review_title' => 'Sweep valid review']);
            $wpsr_orphan_review = WpsrFactory::review(['review_title' => 'Sweep deleted review']);
            $wpsr_orphan_category_review = WpsrFactory::review(['review_title' => 'Sweep deleted category review']);
            $wpsr_valid_category = WpsrFactory::category(['name' => 'Sweep valid category']);
            $wpsr_orphan_review_category = WpsrFactory::category(['name' => 'Sweep orphan review category']);
            $wpsr_deleted_category = WpsrFactory::category(['name' => 'Sweep deleted category']);

            $wpsr_valid = WpsrFactory::relationship((int) $wpsr_valid_review->id, (int) $wpsr_valid_category->id);
            $wpsr_lost_review = WpsrFactory::relationship((int) $wpsr_orphan_review->id, (int) $wpsr_orphan_review_category->id);
            $wpsr_lost_category = WpsrFactory::relationship((int) $wpsr_orphan_category_review->id, (int) $wpsr_deleted_category->id);
            $wpsr_ids = [(int) $wpsr_valid->id, (int) $wpsr_lost_review->id, (int) $wpsr_lost_category->id];

            Review::where('id', (int) $wpsr_orphan_review->id)->delete();
            ReviewCategory::where('id', (int) $wpsr_deleted_category->id)->delete();
            WpsrTest::assertNoDatabaseError('orphan sweep parent deletion setup');

            ReviewCategoriesMigrator::maybeContinueOrphanSweep();
            WpsrTest::assertSame((int) $wpsr_valid->id, (int) get_option($wpsr_sweep_option), 'clean first row still advances sweep high-water cursor');
            WpsrTest::assertSame($wpsr_ids, $wpsr_relationship_ids($wpsr_ids), 'clean first bounded page changes no relationship rows');

            ReviewCategoriesMigrator::maybeContinueOrphanSweep();
            WpsrTest::assertSame((int) $wpsr_lost_review->id, (int) get_option($wpsr_sweep_option), 'second pass persists cursor after deleted-review orphan');
            WpsrTest::assertSame([(int) $wpsr_valid->id, (int) $wpsr_lost_category->id], $wpsr_relationship_ids($wpsr_ids), 'second pass deletes only relationship whose review is gone');

            ReviewCategoriesMigrator::maybeContinueOrphanSweep();
            WpsrTest::assertSame((int) $wpsr_lost_category->id, (int) get_option($wpsr_sweep_option), 'third full-size pass persists final inspected relationship ID');
            WpsrTest::assertSame([(int) $wpsr_valid->id], $wpsr_relationship_ids($wpsr_ids), 'third pass deletes only relationship whose category is gone');

            ReviewCategoriesMigrator::maybeContinueOrphanSweep();
            WpsrTest::assertSame($wpsr_marker . '-missing', get_option($wpsr_sweep_option, $wpsr_marker . '-missing'), 'empty retry proves completion and clears pending cursor');
            WpsrTest::assertSame([(int) $wpsr_valid->id], $wpsr_relationship_ids($wpsr_ids), 'completed repeat preserves valid owned relationship');
        } finally {
            $wpsr_restore_migrator($wpsr_original);
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('image eviction selects bounded oldest platform rows and preserves newer and foreign ownership', function () {
        try {
            $wpsr_foreign = WpsrFactory::optimizeImage('youtube', ['last_requested' => '2026-01-01 00:00:00']);
            $wpsr_oldest = WpsrFactory::optimizeImage('instagram', ['last_requested' => '2026-01-02 00:00:00']);
            $wpsr_second = WpsrFactory::optimizeImage('instagram', ['last_requested' => '2026-01-03 00:00:00']);
            $wpsr_newest = WpsrFactory::optimizeImage('instagram', ['last_requested' => '2026-01-04 00:00:00']);

            $wpsr_handler = new class('instagram') extends ReviewImageOptimizationHandler {
                public function getUploadDir($platform)
                {
                    return sys_get_temp_dir() . '/wpsr-phase8-no-files-' . sanitize_key($platform);
                }
            };
            $wpsr_handler->availableRecords = 2;
            $wpsr_handler->deleteLeastUsedImages('instagram');
            WpsrTest::assertNoDatabaseError('bounded image eviction');

            $wpsr_remaining = OptimizeImage::whereIn('id', [
                (int) $wpsr_foreign->id,
                (int) $wpsr_oldest->id,
                (int) $wpsr_second->id,
                (int) $wpsr_newest->id,
            ])->orderBy('id', 'asc')->get();
            WpsrTest::assertSame(
                [(int) $wpsr_foreign->id, (int) $wpsr_newest->id],
                array_map('intval', $wpsr_remaining->pluck('id')->toArray()),
                'eviction removes exactly two oldest selected-platform rows'
            );
            WpsrTest::assertSame(
                ['youtube', 'instagram'],
                array_values($wpsr_remaining->pluck('platform')->toArray()),
                'eviction preserves foreign platform owner and newest selected row'
            );
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('review sync cursor is idempotent, resumes after marker, and isolates source ownership', function () {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_cursor_option = 'last_fetched_reviews_id';
        WpsrFactory::ownedOption($wpsr_cursor_option, ['_wpsr_test_marker' => $wpsr_marker]);
        $wpsr_service = new class('yelp', $wpsr_marker . '-settings', $wpsr_marker . '-cron') extends BaseReview {
            public function formatData($review, $index)
            {
                return [
                    'platform_name'   => 'yelp',
                    'source_id'       => (string) ($review['source_id'] ?? ''),
                    'review_id'       => (string) ($review['id'] ?? ''),
                    'review_title'    => 'Cursor review ' . (int) $index,
                    'reviewer_name'   => (string) ($review['name'] ?? ''),
                    'reviewer_text'   => (string) ($review['text'] ?? ''),
                    'review_time'     => '2026-07-01 00:00:00',
                    'rating'          => 5,
                    'review_approved' => 1,
                ];
            }

            public function getApiSettings()
            {
                return [];
            }
        };
        $wpsr_remote = function ($wpsr_number, $wpsr_source) use ($wpsr_marker) {
            return [
                'id'        => $wpsr_marker . '-review-cursor-' . $wpsr_number,
                'source_id' => $wpsr_source,
                'name'      => 'Cursor reviewer ' . $wpsr_number,
                'text'      => 'Cursor body ' . $wpsr_number,
            ];
        };

        try {
            $wpsr_place_a = $wpsr_marker . '-place-a';
            $wpsr_place_b = $wpsr_marker . '-place-b';
            $wpsr_first_batch = [
                $wpsr_remote(1, $wpsr_place_a),
                $wpsr_remote(2, $wpsr_place_a),
                $wpsr_remote(3, $wpsr_place_a),
            ];
            $wpsr_service->syncRemoteReviews($wpsr_first_batch, $wpsr_place_a);
            $wpsr_first_ids = Review::where('platform_name', 'yelp')
                ->where('review_id', 'like', $wpsr_marker . '-review-cursor-%')
                ->orderBy('id', 'asc')
                ->pluck('review_id')
                ->toArray();
            WpsrTest::assertSame(array_column($wpsr_first_batch, 'id'), array_values($wpsr_first_ids), 'first sync persists exact remote order once');
            WpsrTest::assertSame($wpsr_first_batch[2]['id'], get_option($wpsr_cursor_option)['yelp'][$wpsr_place_a] ?? null, 'first sync stores last fetched ID under platform and source');

            $wpsr_service->syncRemoteReviews($wpsr_first_batch, $wpsr_place_a);
            WpsrTest::assertSame(3, Review::where('platform_name', 'yelp')->where('review_id', 'like', $wpsr_marker . '-review-cursor-%')->count(), 'repeat batch is row-idempotent');
            WpsrTest::assertSame($wpsr_first_batch[2]['id'], get_option($wpsr_cursor_option)['yelp'][$wpsr_place_a] ?? null, 'repeat batch preserves source cursor');

            $wpsr_fourth = $wpsr_remote(4, $wpsr_place_a);
            $wpsr_service->syncRemoteReviews([$wpsr_first_batch[2], $wpsr_fourth], $wpsr_place_a);
            WpsrTest::assertSame(4, Review::where('platform_name', 'yelp')->where('review_id', 'like', $wpsr_marker . '-review-cursor-%')->count(), 'resume batch skips marker and appends only unseen row');
            WpsrTest::assertSame($wpsr_fourth['id'], get_option($wpsr_cursor_option)['yelp'][$wpsr_place_a] ?? null, 'resume advances cursor to newest processed ID');

            $wpsr_service->syncRemoteReviews([], $wpsr_place_a);
            WpsrTest::assertSame($wpsr_fourth['id'], get_option($wpsr_cursor_option)['yelp'][$wpsr_place_a] ?? null, 'empty retry preserves last successful source cursor');
            WpsrTest::assertSame(4, Review::where('platform_name', 'yelp')->where('review_id', 'like', $wpsr_marker . '-review-cursor-%')->count(), 'empty retry is a persisted no-op');

            $wpsr_fifth = $wpsr_remote(5, $wpsr_place_b);
            $wpsr_service->syncRemoteReviews([$wpsr_fifth], $wpsr_place_b);
            $wpsr_cursors = get_option($wpsr_cursor_option);
            WpsrTest::assertSame($wpsr_fourth['id'], $wpsr_cursors['yelp'][$wpsr_place_a] ?? null, 'second source leaves first source cursor unchanged');
            WpsrTest::assertSame($wpsr_fifth['id'], $wpsr_cursors['yelp'][$wpsr_place_b] ?? null, 'second source receives independent cursor');
            WpsrTest::assertSame($wpsr_marker, $wpsr_cursors['_wpsr_test_marker'] ?? null, 'fixed-name cursor option retains test ownership marker');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('review sync matches a NULL identity column instead of inserting a duplicate every run', function () {
        $wpsr_marker = WpsrFactory::marker();
        // zomato identifies on reviewer_url and reads that identity out of the payload, so an
        // item can carry a real one or none at all -- the null case is what strval() folds to ''.
        $wpsr_service = new class('zomato', $wpsr_marker . '-settings', $wpsr_marker . '-cron') extends BaseReview {
            public function formatData($review, $index)
            {
                return [
                    'platform_name'   => 'zomato',
                    'source_id'       => (string) ($review['source_id'] ?? ''),
                    'review_id'       => (string) ($review['review_id'] ?? ''),
                    'reviewer_url'    => $review['review']['user']['profile_url'] ?? null,
                    'review_title'    => (string) ($review['title'] ?? ''),
                    'reviewer_name'   => 'Null identity reviewer ' . (int) $index,
                    'reviewer_text'   => 'Null identity body',
                    'review_time'     => '2026-07-01 00:00:00',
                    'rating'          => 5,
                    'review_approved' => 1,
                ];
            }

            public function getApiSettings()
            {
                return [];
            }
        };

        $wpsr_owned = function () use ($wpsr_marker) {
            $wpsr_rows = Review::where('platform_name', 'zomato')
                ->where('review_id', 'like', $wpsr_marker . '-null-identity-%')
                ->orderBy('id', 'asc')
                ->get();
            foreach ($wpsr_rows as $wpsr_row) {
                WpsrFactory::trackReview($wpsr_row);
            }
            return $wpsr_rows;
        };
        $wpsr_row = function ($wpsr_review_id) {
            return Review::where('platform_name', 'zomato')->where('review_id', $wpsr_review_id)->first();
        };

        try {
            $wpsr_place = $wpsr_marker . '-null-identity-place';
            $wpsr_null_id = $wpsr_marker . '-null-identity-1';
            $wpsr_real_id = $wpsr_marker . '-null-identity-2';
            $wpsr_url = 'https://example.test/' . $wpsr_marker;
            $wpsr_null_item = [
                'review_id' => $wpsr_null_id,
                'source_id' => $wpsr_place,
                'title'     => 'First pass',
            ];
            $wpsr_real_item = [
                'review_id' => $wpsr_real_id,
                'source_id' => $wpsr_place,
                'title'     => 'Real identity',
                'review'    => ['user' => ['profile_url' => $wpsr_url]],
            ];

            $wpsr_service->syncRemoteReviews([$wpsr_null_item], $wpsr_place);
            WpsrTest::assertSame(1, count($wpsr_owned()), 'first sync inserts the null-identity row once');
            WpsrTest::assertSame(null, $wpsr_row($wpsr_null_id)->reviewer_url, 'the identity column is stored as a real NULL');

            $wpsr_null_item['title'] = 'Second pass';
            $wpsr_service->syncRemoteReviews([$wpsr_null_item], $wpsr_place);
            WpsrTest::assertSame(1, count($wpsr_owned()), 're-sync resolves the NULL row rather than duplicating it');
            WpsrTest::assertSame('Second pass', (string) $wpsr_row($wpsr_null_id)->review_title, 're-sync updates that same row in place');

            // Control: a chunk with no empty identity must not sweep the NULL row into its match.
            $wpsr_service->syncRemoteReviews([$wpsr_real_item], $wpsr_place);
            WpsrTest::assertSame(2, count($wpsr_owned()), 'a real identity inserts its own row beside the NULL one');
            WpsrTest::assertSame('Second pass', (string) $wpsr_row($wpsr_null_id)->review_title, 'the NULL row is left untouched by a real identity');
            WpsrTest::assertSame(null, $wpsr_row($wpsr_null_id)->reviewer_url, 'the NULL row keeps its NULL identity');

            // A mixed chunk resolves both shapes in the one widened lookup.
            $wpsr_null_item['title'] = 'Third pass';
            $wpsr_real_item['title'] = 'Real second pass';
            $wpsr_service->syncRemoteReviews([$wpsr_null_item, $wpsr_real_item], $wpsr_place);
            WpsrTest::assertSame(2, count($wpsr_owned()), 'a mixed chunk duplicates neither row');
            WpsrTest::assertSame('Third pass', (string) $wpsr_row($wpsr_null_id)->review_title, 'the NULL row updates from a mixed chunk');
            WpsrTest::assertSame('Real second pass', (string) $wpsr_row($wpsr_real_id)->review_title, 'the real row updates from a mixed chunk');
            WpsrTest::assertSame($wpsr_url, (string) $wpsr_row($wpsr_real_id)->reviewer_url, 'the real row keeps its own identity');
        } finally {
            WpsrFactory::cleanup();
        }
    });
};
