<?php
/**
 * Phase 5: Review approval states and scoped production transition handlers.
 */

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Services\ReviewApprovalService;

return function () {
    $wpsr_reload_state = function ($wpsr_review_id, $wpsr_label) {
        $wpsr_review = Review::find((int) $wpsr_review_id);
        WpsrTest::assertNoDatabaseError($wpsr_label . ' reload');
        WpsrTest::assertTrue((bool) $wpsr_review, $wpsr_label . ' must still exist');

        return $wpsr_review ? (int) $wpsr_review->review_approved : null;
    };

    $wpsr_assert_states = function (array $wpsr_expected, $wpsr_label) use ($wpsr_reload_state) {
        $wpsr_actual = [];
        foreach ($wpsr_expected as $wpsr_review_id => $wpsr_state) {
            $wpsr_actual[(int) $wpsr_review_id] = $wpsr_reload_state($wpsr_review_id, $wpsr_label);
        }

        WpsrTest::assertSame($wpsr_expected, $wpsr_actual, $wpsr_label . ' persisted states');
    };

    $wpsr_transition = function ($wpsr_route, array $wpsr_params, array $wpsr_expected, $wpsr_label) use ($wpsr_assert_states) {
        $wpsr_result = WpsrTest::rest('PUT', $wpsr_route, $wpsr_params);
        WpsrTest::assertHealthy($wpsr_result, $wpsr_label . ' request', [200]);
        $wpsr_assert_states($wpsr_expected, $wpsr_label);

        return $wpsr_result;
    };

    WpsrTest::case('approval policy initializes approved, pending, and spam states and model reload preserves values', function () {
        $wpsr_review_data = [
            'rating'        => 5,
            'review_title'  => 'Policy state fixture',
            'reviewer_text' => 'Allowed review body',
        ];
        $wpsr_contracts = [
            'auto' => [
                'actual'   => ReviewApprovalService::determineReviewApproval($wpsr_review_data, 'auto', []),
                'expected' => 1,
            ],
            'manual' => [
                'actual'   => ReviewApprovalService::determineReviewApproval($wpsr_review_data, 'manually', []),
                'expected' => 0,
            ],
            'blocked-keyword' => [
                'actual' => ReviewApprovalService::determineReviewApproval(
                    array_merge($wpsr_review_data, ['reviewer_text' => 'Contains blocked-token marker']),
                    'conditional',
                    ['min_rating' => 3, 'blocked_keywords' => 'blocked-token', 'min_review_length' => 0]
                ),
                'expected' => 2,
            ],
        ];

        try {
            $wpsr_ids = [];
            foreach ($wpsr_contracts as $wpsr_name => $wpsr_contract) {
                WpsrTest::assertSame(
                    $wpsr_contract['expected'],
                    $wpsr_contract['actual'],
                    $wpsr_name . ' approval policy state value'
                );

                $wpsr_review = WpsrFactory::review([
                    'review_title'    => 'Initial ' . $wpsr_name . ' state',
                    'review_approved' => $wpsr_contract['actual'],
                    'fields'          => ['state_contract' => $wpsr_name],
                ]);
                $wpsr_ids[(int) $wpsr_review->id] = $wpsr_contract['expected'];
            }

            $wpsr_persisted = Review::whereIn('id', array_keys($wpsr_ids))
                ->orderBy('id', 'asc')
                ->get();
            WpsrTest::assertNoDatabaseError('initial approval state ordered reload');
            WpsrTest::assertSame(
                array_values($wpsr_ids),
                array_map('intval', $wpsr_persisted->pluck('review_approved')->toArray()),
                'initial approval states persist in deterministic creation order'
            );
            WpsrTest::assertSame(
                ['auto', 'manual', 'blocked-keyword'],
                array_values($wpsr_persisted->map(function ($wpsr_review) {
                    return $wpsr_review->fields['state_contract'];
                })->toArray()),
                'ordered reload contains only case-owned state fixtures'
            );
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('Phase 5 [phase5-review-status] review routes persist legitimate transitions without crossing scope', function () use ($wpsr_transition) {
        try {
            $wpsr_review = WpsrFactory::review(['platform_name' => 'custom', 'review_approved' => 1]);
            $wpsr_testimonial = WpsrFactory::review(['platform_name' => 'testimonial', 'review_approved' => 1]);
            $wpsr_review_id = (int) $wpsr_review->id;
            $wpsr_testimonial_id = (int) $wpsr_testimonial->id;
            $wpsr_mixed_ids = [$wpsr_review_id, $wpsr_testimonial_id];

            foreach ([
                ['/reviews/status-update', ['ids' => $wpsr_mixed_ids, 'status' => 'disable'], 0, 'review approved to pending'],
                ['/reviews/status-update', ['ids' => $wpsr_mixed_ids, 'status' => 'enable'], 1, 'review pending to approved'],
                ['/reviews/spam', ['ids' => $wpsr_mixed_ids, 'action' => 'mark-spam'], 2, 'review approved to spam'],
                ['/reviews/spam', ['ids' => $wpsr_mixed_ids, 'action' => 'not-spam'], 0, 'review spam to pending'],
            ] as $wpsr_case) {
                $wpsr_transition($wpsr_case[0], $wpsr_case[1], [
                    $wpsr_review_id => $wpsr_case[2],
                    $wpsr_testimonial_id => 1,
                ], $wpsr_case[3] . '; testimonial excluded');
            }
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('Phase 5 [phase5-testimonial-status] testimonial routes persist legitimate transitions without crossing scope', function () use ($wpsr_transition) {
        try {
            $wpsr_review = WpsrFactory::review(['platform_name' => 'custom', 'review_approved' => 1]);
            $wpsr_testimonial = WpsrFactory::review(['platform_name' => 'testimonial', 'review_approved' => 1]);
            $wpsr_review_id = (int) $wpsr_review->id;
            $wpsr_testimonial_id = (int) $wpsr_testimonial->id;
            $wpsr_mixed_ids = [$wpsr_review_id, $wpsr_testimonial_id];

            foreach ([
                ['/testimonials/status-update', ['ids' => $wpsr_mixed_ids, 'status' => 'disable'], 0, 'testimonial approved to pending'],
                ['/testimonials/status-update', ['ids' => $wpsr_mixed_ids, 'status' => 'enable'], 1, 'testimonial pending to approved'],
                ['/testimonials/spam', ['ids' => $wpsr_mixed_ids, 'action' => 'mark-spam'], 2, 'testimonial approved to spam'],
                ['/testimonials/spam', ['ids' => $wpsr_mixed_ids, 'action' => 'not-spam'], 0, 'testimonial spam to pending'],
            ] as $wpsr_case) {
                $wpsr_transition($wpsr_case[0], $wpsr_case[1], [
                    $wpsr_review_id => 1,
                    $wpsr_testimonial_id => $wpsr_case[2],
                ], $wpsr_case[3] . '; review excluded');
            }
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('invalid status and spam commands are persisted no-ops', function () use ($wpsr_transition) {
        try {
            $wpsr_review = WpsrFactory::review([
                'platform_name'   => 'custom',
                'review_title'    => 'Invalid transition fixture',
                'review_approved' => 2,
            ]);
            $wpsr_review_id = (int) $wpsr_review->id;

            $wpsr_status_result = $wpsr_transition('/reviews/status-update', [
                'ids' => [$wpsr_review_id],
                'status' => 'archived',
            ], [
                $wpsr_review_id => 2,
            ], 'invalid status command leaves spam state unchanged');
            WpsrTest::assertSame(
                'Invalid status value',
                $wpsr_status_result['data'],
                'invalid status command returns production boundary value'
            );

            $wpsr_spam_result = $wpsr_transition('/reviews/spam', [
                'ids' => [$wpsr_review_id],
                'action' => 'quarantine',
            ], [
                $wpsr_review_id => 2,
            ], 'invalid spam command leaves spam state unchanged');
            WpsrTest::assertSame(
                'Invalid action value',
                $wpsr_spam_result['data'],
                'invalid spam command returns production boundary value'
            );
        } finally {
            WpsrFactory::cleanup();
        }
    });
};
