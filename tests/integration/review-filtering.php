<?php
/**
 * Foundation behavior: real Review model persistence and owner-side feed filters.
 */

use WPSocialReviews\App\Models\Review;

return function () {
    WpsrTest::case('review fixtures persist values and filtered query returns only approved matching rows', function () {
        $sourceId = WpsrFactory::marker() . '-selected-source';
        $otherSourceId = WpsrFactory::marker() . '-other-source';

        try {
            $matching = WpsrFactory::review([
                'source_id'       => $sourceId,
                'review_title'    => 'Selected five-star fixture',
                'reviewer_text'   => 'Persisted selected body',
                'review_time'     => '2026-01-04 04:00:00',
                'rating'          => 5,
                'review_approved' => 1,
                'fields'          => ['contract' => 'selected'],
            ]);
            WpsrFactory::review([
                'source_id'       => $sourceId,
                'review_title'    => 'Below rating floor fixture',
                'review_time'     => '2026-01-03 03:00:00',
                'rating'          => 3,
                'review_approved' => 1,
            ]);
            WpsrFactory::review([
                'source_id'       => $sourceId,
                'review_title'    => 'Pending fixture',
                'review_time'     => '2026-01-02 02:00:00',
                'rating'          => 5,
                'review_approved' => 0,
            ]);
            WpsrFactory::review([
                'source_id'       => $otherSourceId,
                'review_title'    => 'Different business fixture',
                'review_time'     => '2026-01-01 01:00:00',
                'rating'          => 5,
                'review_approved' => 1,
            ]);

            WpsrTest::assertNoDatabaseError('review fixture setup');

            $persisted = Review::find((int) $matching->id);
            WpsrTest::assertNoDatabaseError('review fixture persisted-value lookup');
            WpsrTest::assertTrue((bool) $persisted, 'matching review must exist after model create');
            if ($persisted) {
                WpsrTest::assertSame($sourceId, $persisted->source_id, 'source_id persists through real model');
                WpsrTest::assertSame('Persisted selected body', $persisted->reviewer_text, 'review text persists through real model');
                WpsrTest::assertSame(5, $persisted->rating, 'rating cast returns persisted integer');
                WpsrTest::assertSame('selected', $persisted->fields['contract'], 'JSON fields cast returns persisted value');
            }

            $sourceTitles = Review::where('platform_name', 'custom')
                ->where('source_id', $sourceId)
                ->orderBy('review_time', 'asc')
                ->pluck('review_title')
                ->toArray();
            WpsrTest::assertNoDatabaseError('source-scoped fixture query');
            WpsrTest::assertSame([
                'Pending fixture',
                'Below rating floor fixture',
                'Selected five-star fixture',
            ], array_values($sourceTitles), 'source query sees exact fixture values in deterministic order');

            $filtered = Review::filteredReviewsQuery(['custom'], [
                'selectedBusinesses' => [$sourceId],
                'starFilterVal'      => 4,
                'order'              => 'oldest',
                'totalReviewsNumber' => ['desktop' => 20, 'mobile' => 20],
            ])->get();
            WpsrTest::assertNoDatabaseError('filtered review query');

            $filteredIds = array_map('intval', $filtered->pluck('id')->toArray());
            WpsrTest::assertSame(
                [(int) $matching->id],
                $filteredIds,
                'platform, business, approval, and minimum-rating filters return only matching review'
            );
            WpsrTest::assertSame(
                ['Selected five-star fixture'],
                array_values($filtered->pluck('review_title')->toArray()),
                'filtered query returns expected persisted title value'
            );
        } finally {
            WpsrFactory::cleanup();
        }
    });
};
