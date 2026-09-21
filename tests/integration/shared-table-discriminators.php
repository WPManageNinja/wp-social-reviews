<?php
/**
 * Phase 6: adjacent-row contamination checks for every material core shared table.
 */

use WPSocialReviews\App\Models\Cache;
use WPSocialReviews\App\Models\Notification;
use WPSocialReviews\App\Models\OptimizeImage;
use WPSocialReviews\App\Models\Post;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Models\ReviewCategoryRelationship;
use WPSocialReviews\App\Models\Template;
use WPSocialReviews\App\Models\Widget;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;

return function () {
    $wpsr_model_ids = function ($wpsr_rows, $wpsr_key = 'id') {
        $wpsr_ids = [];
        foreach ($wpsr_rows as $wpsr_row) {
            if (is_array($wpsr_row)) {
                $wpsr_ids[] = (int) $wpsr_row[$wpsr_key];
            } else {
                $wpsr_ids[] = (int) $wpsr_row->{$wpsr_key};
            }
        }
        return $wpsr_ids;
    };

    $wpsr_rest_items = function (array $wpsr_result) {
        $wpsr_data = $wpsr_result['data'];
        if (!is_array($wpsr_data) || !array_key_exists('items', $wpsr_data)) {
            throw new RuntimeException('REST listing response has no items field.');
        }

        $wpsr_items = $wpsr_data['items'];
        if (is_object($wpsr_items) && method_exists($wpsr_items, 'items')) {
            $wpsr_items = $wpsr_items->items();
        } elseif (is_object($wpsr_items) && method_exists($wpsr_items, 'toArray')) {
            $wpsr_items = $wpsr_items->toArray();
        }

        if (is_array($wpsr_items) && isset($wpsr_items['data']) && is_array($wpsr_items['data'])) {
            $wpsr_items = $wpsr_items['data'];
        }
        if ($wpsr_items instanceof Traversable) {
            $wpsr_items = iterator_to_array($wpsr_items, false);
        }
        if (!is_array($wpsr_items)) {
            throw new RuntimeException('REST listing items are not iterable.');
        }

        return array_values($wpsr_items);
    };

    $wpsr_rest_ids = function (array $wpsr_result, $wpsr_key = 'id') use ($wpsr_rest_items, $wpsr_model_ids) {
        return $wpsr_model_ids($wpsr_rest_items($wpsr_result), $wpsr_key);
    };

    $wpsr_paginator_ids = function ($wpsr_paginator, $wpsr_key = 'ID') use ($wpsr_model_ids) {
        return $wpsr_model_ids($wpsr_paginator, $wpsr_key);
    };

    WpsrTest::case('wp_posts models and listing query isolate template, widget, and notification post types', function () use ($wpsr_paginator_ids, $wpsr_rest_ids) {
        try {
            $wpsr_template = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Template discriminator']);
            $wpsr_draft_template = WpsrFactory::post('wp_social_reviews', [
                'post_title'  => 'Draft template status discriminator',
                'post_status' => 'draft',
            ]);
            $wpsr_widget = WpsrFactory::post('wpsr_social_chats', ['post_title' => 'Widget discriminator']);
            $wpsr_notification = WpsrFactory::post('wpsr_reviews_notify', ['post_title' => 'Notification discriminator']);
            $wpsr_custom_source = WpsrFactory::post('wpsr_custom_source', ['post_title' => 'Custom source discriminator']);
            $wpsr_foreign = WpsrFactory::post('post', ['post_title' => 'Foreign WordPress post']);
            $wpsr_marker = WpsrFactory::marker();

            $wpsr_template_ids = array_map('intval', Template::where('post_title', 'like', $wpsr_marker . '%')
                ->orderBy('ID', 'asc')->pluck('ID')->toArray());
            $wpsr_widget_ids = array_map('intval', Widget::where('post_title', 'like', $wpsr_marker . '%')
                ->orderBy('ID', 'asc')->pluck('ID')->toArray());
            $wpsr_notification_ids = array_map('intval', Notification::where('post_title', 'like', $wpsr_marker . '%')
                ->orderBy('ID', 'asc')->pluck('ID')->toArray());
            WpsrTest::assertNoDatabaseError('post-type model scope queries');

            WpsrTest::assertSame([(int) $wpsr_template->ID], $wpsr_template_ids, 'Template scope includes published template and excludes every adjacent post type and draft');
            WpsrTest::assertSame([(int) $wpsr_widget->ID], $wpsr_widget_ids, 'Widget scope includes widget and excludes template, notification, and foreign post');
            WpsrTest::assertSame([(int) $wpsr_notification->ID], $wpsr_notification_ids, 'Notification scope includes notification and excludes template, widget, and foreign post');
            WpsrTest::assertSame(null, Template::find((int) $wpsr_widget->ID), 'Template find excludes adjacent widget ID in same physical table');
            WpsrTest::assertSame(null, Widget::find((int) $wpsr_notification->ID), 'Widget find excludes adjacent notification ID in same physical table');
            WpsrTest::assertSame(null, Notification::find((int) $wpsr_foreign->ID), 'Notification find excludes ordinary WordPress post ID');

            $wpsr_post = new Post();
            WpsrTest::assertSame(
                [(int) $wpsr_template->ID],
                $wpsr_paginator_ids($wpsr_post->getPosts('wp_social_reviews', $wpsr_marker, ''), 'ID'),
                'dynamic Post template listing returns exact template ID only'
            );
            WpsrTest::assertSame(
                [(int) $wpsr_widget->ID],
                $wpsr_paginator_ids($wpsr_post->getPosts('wpsr_social_chats', $wpsr_marker, ''), 'ID'),
                'dynamic Post widget listing returns exact widget ID only'
            );
            WpsrTest::assertSame(
                [(int) $wpsr_notification->ID],
                $wpsr_paginator_ids($wpsr_post->getPosts('wpsr_reviews_notify', $wpsr_marker, ''), 'ID'),
                'dynamic Post notification listing returns exact notification ID only'
            );

            $wpsr_template_result = WpsrTest::rest('GET', '/templates', [
                'search'       => $wpsr_marker,
                'templateType' => 'template',
            ]);
            WpsrTest::assertHealthy($wpsr_template_result, 'template controller listing', [200]);
            WpsrTest::assertSame([(int) $wpsr_template->ID], $wpsr_rest_ids($wpsr_template_result, 'ID'), 'template REST controller returns exact published template ID');
            $wpsr_widget_result = WpsrTest::rest('GET', '/chat-widgets', ['search' => $wpsr_marker]);
            WpsrTest::assertHealthy($wpsr_widget_result, 'widget controller listing', [200]);
            WpsrTest::assertSame([(int) $wpsr_widget->ID], $wpsr_rest_ids($wpsr_widget_result, 'ID'), 'widget REST controller returns exact widget ID');
            $wpsr_notification_result = WpsrTest::rest('GET', '/notifications', ['search' => $wpsr_marker]);
            WpsrTest::assertHealthy($wpsr_notification_result, 'notification controller listing', [200]);
            WpsrTest::assertSame([(int) $wpsr_notification->ID], $wpsr_rest_ids($wpsr_notification_result, 'ID'), 'notification REST controller returns exact notification ID');

            WpsrTest::assertSame(6, count([
                $wpsr_template->ID,
                $wpsr_draft_template->ID,
                $wpsr_widget->ID,
                $wpsr_notification->ID,
                $wpsr_custom_source->ID,
                $wpsr_foreign->ID,
            ]), 'six adjacent posts exist for contamination checks');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('wpsr_reviews read paths isolate ordinary platforms from testimonials', function () use ($wpsr_rest_ids) {
        try {
            $wpsr_marker = WpsrFactory::marker();
            $wpsr_custom = WpsrFactory::review([
                'platform_name' => 'custom',
                'review_title'  => $wpsr_marker . ' custom platform row',
                'review_time'   => '2026-02-03 00:00:00',
            ]);
            $wpsr_google = WpsrFactory::review([
                'platform_name' => 'google',
                'review_title'  => $wpsr_marker . ' google platform row',
                'review_time'   => '2026-02-02 00:00:00',
            ]);
            $wpsr_testimonial = WpsrFactory::review([
                'platform_name' => 'testimonial',
                'review_title'  => $wpsr_marker . ' testimonial row',
                'review_time'   => '2026-02-01 00:00:00',
            ]);

            $wpsr_custom_query = Review::filteredReviewsQuery(['custom'], [
                'order'              => 'oldest',
                'totalReviewsNumber' => ['desktop' => 20, 'mobile' => 20],
            ])->get();
            $wpsr_google_query = Review::filteredReviewsQuery(['google'], [
                'order'              => 'oldest',
                'totalReviewsNumber' => ['desktop' => 20, 'mobile' => 20],
            ])->get();
            WpsrTest::assertNoDatabaseError('platform-scoped review model queries');
            WpsrTest::assertSame([(int) $wpsr_custom->id], array_map('intval', $wpsr_custom_query->pluck('id')->toArray()), 'custom platform query includes custom row and excludes google/testimonial rows');
            WpsrTest::assertSame([(int) $wpsr_google->id], array_map('intval', $wpsr_google_query->pluck('id')->toArray()), 'google platform query includes google row and excludes custom/testimonial rows');

            $wpsr_review_result = WpsrTest::rest('GET', '/reviews', [
                'search' => $wpsr_marker,
                'filter' => 'custom',
            ]);
            WpsrTest::assertHealthy($wpsr_review_result, 'ordinary review listing', [200]);
            WpsrTest::assertSame([(int) $wpsr_custom->id], $wpsr_rest_ids($wpsr_review_result), 'review controller returns exact ordinary custom ID');

            $wpsr_crafted_result = WpsrTest::rest('GET', '/reviews', [
                'search' => $wpsr_marker,
                'filter' => 'testimonial',
            ]);
            WpsrTest::assertHealthy($wpsr_crafted_result, 'crafted testimonial filter on review route', [200]);
            WpsrTest::assertSame([], $wpsr_rest_ids($wpsr_crafted_result), 'review route final discriminator excludes testimonial even when client requests it');

            $wpsr_testimonial_result = WpsrTest::rest('GET', '/testimonials', [
                'search'   => $wpsr_marker,
                'filter'   => 'custom',
                'type'     => 'custom_review',
                'order_by' => 'asc',
            ]);
            WpsrTest::assertHealthy($wpsr_testimonial_result, 'testimonial listing', [200]);
            WpsrTest::assertSame([(int) $wpsr_testimonial->id], $wpsr_rest_ids($wpsr_testimonial_result), 'testimonial controller includes exact testimonial ID and excludes ordinary platforms');
            WpsrTest::assertSame(3, Review::whereIn('id', [(int) $wpsr_custom->id, (int) $wpsr_google->id, (int) $wpsr_testimonial->id])->count(), 'three adjacent review rows remain present during read assertions');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('Phase 6 [phase6-category-scope] category pivot relations and bulk handlers isolate both review and category ownership keys', function () use ($wpsr_model_ids) {
        try {
            $wpsr_review = WpsrFactory::review(['platform_name' => 'custom', 'review_title' => 'Pivot ordinary row']);
            $wpsr_testimonial = WpsrFactory::review(['platform_name' => 'testimonial', 'review_title' => 'Pivot testimonial row']);
            $wpsr_category_a = WpsrFactory::category(['name' => 'Pivot A']);
            $wpsr_category_b = WpsrFactory::category(['name' => 'Pivot B']);
            $wpsr_review_id = (int) $wpsr_review->id;
            $wpsr_testimonial_id = (int) $wpsr_testimonial->id;
            $wpsr_category_a_id = (int) $wpsr_category_a->id;
            $wpsr_category_b_id = (int) $wpsr_category_b->id;

            $wpsr_review_attach = WpsrTest::rest('PUT', '/reviews/bulk-category', [
                'ids'         => [$wpsr_review_id, $wpsr_testimonial_id],
                'action'      => 'add',
                'category_id' => $wpsr_category_a_id,
            ]);
            WpsrTest::assertHealthy($wpsr_review_attach, 'review bulk category attach', [200]);
            $wpsr_testimonial_attach = WpsrTest::rest('PUT', '/testimonials/bulk-category', [
                'ids'         => [$wpsr_review_id, $wpsr_testimonial_id],
                'action'      => 'add',
                'category_id' => $wpsr_category_b_id,
            ]);
            WpsrTest::assertHealthy($wpsr_testimonial_attach, 'testimonial bulk category attach', [200]);

            $wpsr_pairs = ReviewCategoryRelationship::whereIn('review_id', [$wpsr_review_id, $wpsr_testimonial_id])
                ->orderBy('review_id', 'asc')->orderBy('category_id', 'asc')->get();
            $wpsr_pair_values = array_values($wpsr_pairs->map(function ($wpsr_pair) {
                return [(int) $wpsr_pair->review_id, (int) $wpsr_pair->category_id];
            })->toArray());
            WpsrTest::assertSame([
                [$wpsr_review_id, $wpsr_category_a_id],
                [$wpsr_testimonial_id, $wpsr_category_b_id],
            ], $wpsr_pair_values, 'route-scoped bulk attach writes exact review/category pairs and excludes cross-type IDs');

            WpsrFactory::relationship($wpsr_review_id, $wpsr_category_b_id);
            $wpsr_review_categories = $wpsr_review->categories()->orderBy('wpsr_review_categories.id', 'asc')->get();
            $wpsr_testimonial_categories = $wpsr_testimonial->categories()->orderBy('wpsr_review_categories.id', 'asc')->get();
            $wpsr_category_a_reviews = $wpsr_category_a->reviews()->orderBy('wpsr_reviews.id', 'asc')->get();
            $wpsr_category_b_reviews = $wpsr_category_b->reviews()->orderBy('wpsr_reviews.id', 'asc')->get();
            WpsrTest::assertNoDatabaseError('category pivot relation reads');
            WpsrTest::assertSame([$wpsr_category_a_id, $wpsr_category_b_id], $wpsr_model_ids($wpsr_review_categories), 'Review categories relation includes its two categories only');
            WpsrTest::assertSame([$wpsr_category_b_id], $wpsr_model_ids($wpsr_testimonial_categories), 'adjacent testimonial categories relation excludes review-only category');
            WpsrTest::assertSame([$wpsr_review_id], $wpsr_model_ids($wpsr_category_a_reviews), 'Category reviews relation includes its ordinary review only');
            WpsrTest::assertSame([$wpsr_review_id, $wpsr_testimonial_id], $wpsr_model_ids($wpsr_category_b_reviews), 'shared category relation returns exact linked review IDs in deterministic order');

            ReviewCategoryRelationship::bulkDetach([$wpsr_review_id], $wpsr_category_b_id);
            WpsrTest::assertNoDatabaseError('composite-key bulk detach');
            $wpsr_remaining = ReviewCategoryRelationship::whereIn('review_id', [$wpsr_review_id, $wpsr_testimonial_id])
                ->orderBy('review_id', 'asc')->orderBy('category_id', 'asc')->get();
            $wpsr_remaining_pairs = array_values($wpsr_remaining->map(function ($wpsr_pair) {
                return [(int) $wpsr_pair->review_id, (int) $wpsr_pair->category_id];
            })->toArray());
            WpsrTest::assertSame([
                [$wpsr_review_id, $wpsr_category_a_id],
                [$wpsr_testimonial_id, $wpsr_category_b_id],
            ], $wpsr_remaining_pairs, 'bulk detach removes only requested review/category pair and preserves both adjacent-key rows');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('cache service isolates same-name rows by platform discriminator', function () {
        try {
            $wpsr_youtube = WpsrFactory::cache('youtube', 'shared-name', ['owner' => 'youtube', 'version' => 1]);
            $wpsr_google = WpsrFactory::cache('google', 'shared-name', ['owner' => 'google', 'version' => 1]);
            $wpsr_name = (string) $wpsr_google->name;
            $wpsr_handler = new CacheHandler('google');

            WpsrTest::assertSame(
                ['owner' => 'google', 'version' => 1],
                $wpsr_handler->getFeedCache($wpsr_name),
                'same-name cache read returns selected platform value'
            );
            $wpsr_fetched_ids = array_map('intval', $wpsr_handler->fetchCachesByName($wpsr_name)->pluck('id')->toArray());
            WpsrTest::assertSame([(int) $wpsr_google->id], $wpsr_fetched_ids, 'cache prefix query includes google row and excludes same-name youtube row');

            $wpsr_expected_expiration = gmdate('Y-m-d H:i:s', current_time('timestamp') + 3600);
            $wpsr_handler->createOrUpdateCache($wpsr_name, ['owner' => 'google', 'version' => 2], 3600);
            WpsrTest::assertNoDatabaseError('platform-scoped cache update');
            $wpsr_google_after = Cache::find((int) $wpsr_google->id);
            WpsrTest::assertTrue(
                $wpsr_google_after && abs(strtotime($wpsr_google_after->expiration) - strtotime($wpsr_expected_expiration)) <= 2,
                'cache expiration uses site-local current_time contract rather than UTC-equivalent clock'
            );
            $wpsr_rows = Cache::whereIn('id', [(int) $wpsr_youtube->id, (int) $wpsr_google->id])
                ->orderBy('id', 'asc')->get();
            $wpsr_values = array_values($wpsr_rows->map(function ($wpsr_row) {
                $wpsr_value = json_decode($wpsr_row->value, true);
                return [(int) $wpsr_row->id, $wpsr_row->platform, $wpsr_value['owner'], (int) $wpsr_value['version']];
            })->toArray());
            WpsrTest::assertSame([
                [(int) $wpsr_youtube->id, 'youtube', 'youtube', 1],
                [(int) $wpsr_google->id, 'google', 'google', 2],
            ], $wpsr_values, 'cache update changes selected platform row only and preserves adjacent same-name value');
            WpsrTest::assertSame(2, count($wpsr_values), 'both same-name physical cache rows remain after scoped update');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('optimize-image platform operations isolate adjacent platform rows', function () {
        try {
            $wpsr_youtube = WpsrFactory::optimizeImage('youtube', [
                'last_requested' => '2026-03-01 00:00:00',
            ]);
            $wpsr_instagram = WpsrFactory::optimizeImage('instagram', [
                'last_requested' => '2026-03-02 00:00:00',
            ]);
            $wpsr_optimizer = new OptimizeImage();

            $wpsr_instagram_ids = array_map('intval', $wpsr_optimizer->getOldPosts(10, 'instagram')->pluck('id')->toArray());
            WpsrTest::assertSame([(int) $wpsr_instagram->id], $wpsr_instagram_ids, 'old-post query includes selected platform and excludes adjacent platform row');

            $wpsr_optimizer->deleteMediaByPlatform('instagram');
            WpsrTest::assertNoDatabaseError('optimize-image platform delete');
            $wpsr_remaining = OptimizeImage::whereIn('id', [(int) $wpsr_youtube->id, (int) $wpsr_instagram->id])
                ->orderBy('id', 'asc')->get();
            WpsrTest::assertSame([(int) $wpsr_youtube->id], array_map('intval', $wpsr_remaining->pluck('id')->toArray()), 'platform delete preserves adjacent platform row exactly');
            WpsrTest::assertSame(['youtube'], array_values($wpsr_remaining->pluck('platform')->toArray()), 'remaining optimize-image row retains exact platform value');
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('optimize-image identity writes include platform ownership', function () {
        try {
            $wpsr_youtube = WpsrFactory::optimizeImage('youtube', ['_identity' => 'platform-owner']);
            // Identity without the platform let the Instagram write claim the YouTube row.
            $wpsr_platforms = (function () use ($wpsr_youtube) {
                $wpsr_optimizer = new OptimizeImage();
                $wpsr_optimizer->updateData(
                    (string) $wpsr_youtube->media_id,
                    (string) $wpsr_youtube->user_name,
                    [
                        'platform'       => 'instagram',
                        'user_id'        => WpsrFactory::marker() . '-instagram-owner',
                        'json_data'      => wp_json_encode(['_wpsr_test_marker' => WpsrFactory::marker()]),
                        'images_resized' => 1,
                        'last_requested' => '2026-03-03 00:00:00',
                    ]
                );
                WpsrTest::assertNoDatabaseError('optimize-image ownership collision write');
                $wpsr_rows = OptimizeImage::where('media_id', (string) $wpsr_youtube->media_id)
                    ->where('user_name', (string) $wpsr_youtube->user_name)
                    ->orderBy('id', 'asc')->get();
                foreach ($wpsr_rows as $wpsr_row) {
                    WpsrFactory::trackOptimizeImage($wpsr_row);
                }
                return array_values($wpsr_rows->pluck('platform')->toArray());
            })();

            WpsrTest::assertSame(['youtube', 'instagram'], $wpsr_platforms,
                'each platform owns its own row for the same media id and user name');
        } finally {
            WpsrFactory::cleanup();
        }
    });
};
