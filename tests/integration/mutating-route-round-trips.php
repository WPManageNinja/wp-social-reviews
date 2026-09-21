<?php
/**
 * Phase 9: authorized mutating-route round trips and full unchanged-field snapshots.
 *
 * Volatile normalization:
 * - WP posts: post_modified and post_modified_gmt (WordPress updates both on writes).
 * - WPFluent rows: updated_at (ORM timestamp updated by save()).
 * Semantic normalization:
 * - _wpsr_template_config is decoded from JSON/PHP serialization before comparison;
 *   serialization bytes are transport format, while every decoded persisted field remains compared.
 */

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Models\ReviewCategoryRelationship;
use WPSocialReviews\App\Services\GlobalSettings;

return function () {
    $wpsr_marker = WpsrFactory::marker();

    $wpsr_response_value = function ($wpsr_value, $wpsr_key) {
        if (is_object($wpsr_value)) {
            return $wpsr_value->{$wpsr_key} ?? null;
        }
        return is_array($wpsr_value) ? ($wpsr_value[$wpsr_key] ?? null) : null;
    };

    $wpsr_review_snapshot = function ($wpsr_id) {
        global $wpdb;
        $wpsr_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpsr_reviews WHERE id = %d",
            (int) $wpsr_id
        ), ARRAY_A);
        WpsrTest::assertNoDatabaseError('review full snapshot');
        if (!is_array($wpsr_row)) {
            return null;
        }
        unset($wpsr_row['updated_at']);
        $wpsr_categories = array_map('intval', ReviewCategoryRelationship::where('review_id', (int) $wpsr_id)
            ->orderBy('category_id', 'asc')->pluck('category_id')->toArray());
        WpsrTest::assertNoDatabaseError('review category snapshot');
        return ['row' => $wpsr_row, 'category_ids' => $wpsr_categories];
    };

    $wpsr_decode_config = function ($wpsr_value) {
        // WordPress may double-serialize a string that is already serialized.
        for ($wpsr_depth = 0; $wpsr_depth < 3 && is_string($wpsr_value); $wpsr_depth++) {
            $wpsr_json = json_decode($wpsr_value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $wpsr_json;
            }
            $wpsr_unserialized = maybe_unserialize($wpsr_value);
            if ($wpsr_unserialized === $wpsr_value) {
                break;
            }
            $wpsr_value = $wpsr_unserialized;
        }
        return $wpsr_value;
    };

    $wpsr_post_snapshot = function ($wpsr_id) use ($wpsr_decode_config) {
        global $wpdb;
        $wpsr_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->posts} WHERE ID = %d",
            (int) $wpsr_id
        ), ARRAY_A);
        WpsrTest::assertNoDatabaseError('post full snapshot');
        if (!is_array($wpsr_row)) {
            return null;
        }
        unset($wpsr_row['post_modified'], $wpsr_row['post_modified_gmt']);

        $wpsr_meta_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_key ASC, meta_id ASC",
            (int) $wpsr_id
        ), ARRAY_A);
        WpsrTest::assertNoDatabaseError('postmeta full snapshot');
        $wpsr_meta = [];
        foreach ($wpsr_meta_rows as $wpsr_meta_row) {
            $wpsr_key = (string) $wpsr_meta_row['meta_key'];
            $wpsr_value = $wpsr_meta_row['meta_value'];
            if ($wpsr_key === '_wpsr_template_config') {
                $wpsr_value = $wpsr_decode_config($wpsr_value);
            }
            if (array_key_exists($wpsr_key, $wpsr_meta)) {
                $wpsr_meta[$wpsr_key][] = $wpsr_value;
            } else {
                $wpsr_meta[$wpsr_key] = $wpsr_value;
            }
        }
        ksort($wpsr_meta, SORT_STRING);
        return ['row' => $wpsr_row, 'postmeta' => $wpsr_meta];
    };

    $wpsr_category_snapshot = function ($wpsr_id) {
        global $wpdb;
        $wpsr_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpsr_review_categories WHERE id = %d",
            (int) $wpsr_id
        ), ARRAY_A);
        WpsrTest::assertNoDatabaseError('category full snapshot');
        if (is_array($wpsr_row)) {
            unset($wpsr_row['updated_at']);
        }
        $wpsr_review_ids = array_map('intval', ReviewCategoryRelationship::where('category_id', (int) $wpsr_id)
            ->orderBy('review_id', 'asc')->pluck('review_id')->toArray());
        WpsrTest::assertNoDatabaseError('category relationship snapshot');
        return ['row' => $wpsr_row, 'review_ids' => $wpsr_review_ids];
    };

    $wpsr_rest_items = function (array $wpsr_result) {
        $wpsr_items = is_array($wpsr_result['data']) ? ($wpsr_result['data']['items'] ?? []) : [];
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
        return is_array($wpsr_items) ? array_values($wpsr_items) : [];
    };

    $wpsr_item_ids = function (array $wpsr_result, $wpsr_key) use ($wpsr_rest_items, $wpsr_response_value) {
        return array_values(array_map('intval', array_map(function ($wpsr_item) use ($wpsr_key, $wpsr_response_value) {
            return $wpsr_response_value($wpsr_item, $wpsr_key);
        }, $wpsr_rest_items($wpsr_result))));
    };

    $wpsr_assert_post_gone = function ($wpsr_id, $wpsr_label) {
        global $wpdb;
        WpsrTest::assertSame(null, get_post((int) $wpsr_id), $wpsr_label . ' post is gone');
        $wpsr_meta_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d",
            (int) $wpsr_id
        ));
        WpsrTest::assertNoDatabaseError($wpsr_label . ' postmeta gone query');
        WpsrTest::assertSame(0, $wpsr_meta_count, $wpsr_label . ' postmeta is gone');
    };

    $wpsr_track_created_post = function ($wpsr_id, $wpsr_type, $wpsr_title, $wpsr_content) {
        return WpsrFactory::trackPost($wpsr_id, $wpsr_type, $wpsr_title, $wpsr_content);
    };

    $wpsr_create_review = function ($wpsr_route, $wpsr_platform, $wpsr_label) use ($wpsr_marker, $wpsr_response_value) {
        $wpsr_review_id = $wpsr_marker . '-' . $wpsr_label . '-remote-id';
        $wpsr_payload = [
            'platform_name'   => $wpsr_platform,
            'source_id'       => $wpsr_marker . '-' . $wpsr_label . '-source',
            'review_id'       => $wpsr_review_id,
            'review_title'    => $wpsr_marker . ' ' . $wpsr_label . ' title',
            'reviewer_name'   => $wpsr_marker . ' ' . $wpsr_label . ' author',
            'reviewer_email'  => $wpsr_label . '-' . getmypid() . '@example.test',
            'reviewer_url'    => 'https://example.test/' . $wpsr_label,
            'reviewer_img'    => 'https://example.test/' . $wpsr_label . '.jpg',
            'reviewer_text'   => $wpsr_marker . ' ' . $wpsr_label . ' body',
            'review_time'     => '2026-08-01 02:03:04',
            'rating'          => 5,
            'category'        => $wpsr_marker . ' legacy category',
            'fields'          => ['author_company' => $wpsr_marker . ' Company'],
        ];
        $wpsr_result = WpsrTest::rest('POST', $wpsr_route, ['review' => $wpsr_payload]);
        WpsrTest::assertHealthy($wpsr_result, $wpsr_label . ' create response', [200]);
        $wpsr_created = is_array($wpsr_result['data']) ? ($wpsr_result['data']['review'] ?? null) : null;
        $wpsr_id = (int) $wpsr_response_value($wpsr_created, 'id');
        WpsrTest::assertTrue($wpsr_id > 0, $wpsr_label . ' create response includes persisted ID');
        $wpsr_review = Review::find($wpsr_id);
        WpsrTest::assertTrue((bool) $wpsr_review, $wpsr_label . ' create persisted row reloads');
        if ($wpsr_review) {
            WpsrFactory::trackReview($wpsr_review);
            WpsrTest::assertSame($wpsr_review_id, (string) $wpsr_review->review_id, $wpsr_label . ' response ID reload value');
            WpsrTest::assertSame($wpsr_payload['reviewer_email'], (string) $wpsr_review->reviewer_email, $wpsr_label . ' persisted email');
            WpsrTest::assertSame($wpsr_payload['fields'], $wpsr_review->fields, $wpsr_label . ' persisted nested fields');
        }
        return [$wpsr_id, $wpsr_payload];
    };

    $wpsr_review_update_payload = function (Review $wpsr_review, $wpsr_title) {
        return [
            'fields'          => $wpsr_review->fields,
            'review_title'    => $wpsr_title,
            'reviewer_name'   => $wpsr_review->reviewer_name,
            'reviewer_email'  => $wpsr_review->reviewer_email,
            'reviewer_text'   => $wpsr_review->reviewer_text,
            'review_time'     => $wpsr_review->review_time,
            'rating'          => (int) $wpsr_review->rating,
            'reviewer_url'    => $wpsr_review->reviewer_url,
            'reviewer_img'    => $wpsr_review->reviewer_img,
            'review_approved' => (int) $wpsr_review->review_approved,
            'category'        => $wpsr_review->category,
        ];
    };

    $wpsr_implementations = [];

    $wpsr_implementations['review-crud'] = ['review create/read/update-one-field/full-snapshot/delete', function () use (
        $wpsr_marker, $wpsr_create_review, $wpsr_review_snapshot, $wpsr_review_update_payload, $wpsr_item_ids
    ) {
        WpsrFactory::trackAbsentFixedOption('wpsr_reviews_custom_business_info');
        try {
            [$wpsr_id, $wpsr_payload] = $wpsr_create_review('/reviews', 'custom', 'review');
            $wpsr_read = WpsrTest::rest('GET', '/reviews', ['search' => $wpsr_marker, 'filter' => 'custom']);
            WpsrTest::assertHealthy($wpsr_read, 'review read response', [200]);
            WpsrTest::assertSame([$wpsr_id], $wpsr_item_ids($wpsr_read, 'id'), 'review read returns exact created ID');

            $wpsr_before = $wpsr_review_snapshot($wpsr_id);
            $wpsr_new_title = $wpsr_payload['review_title'] . ' updated';
            $wpsr_review = Review::find($wpsr_id);
            $wpsr_update = WpsrTest::rest('PUT', '/reviews/' . $wpsr_id, [
                'review' => $wpsr_review_update_payload($wpsr_review, $wpsr_new_title),
            ]);
            WpsrTest::assertHealthy($wpsr_update, 'review update response', [200]);
            WpsrTest::assertSame($wpsr_new_title, (string) Review::find($wpsr_id)->review_title, 'review update persisted requested title');
            $wpsr_after = $wpsr_review_snapshot($wpsr_id);
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'row.review_title' => ['before' => $wpsr_payload['review_title'], 'after' => $wpsr_new_title],
            ], 'review update preserves every other persisted field and pivot');

            $wpsr_delete = WpsrTest::rest('DELETE', '/reviews', ['ids' => [$wpsr_id]]);
            WpsrTest::assertHealthy($wpsr_delete, 'review delete response', [200]);
            WpsrTest::assertTrue(strpos((string) $wpsr_delete['data'], '1 review') !== false, 'review delete response reports one deletion');
            WpsrTest::assertSame(null, Review::find($wpsr_id), 'review delete row is gone');
            WpsrTest::assertSame(0, ReviewCategoryRelationship::where('review_id', $wpsr_id)->count(), 'review delete pivots are gone');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['testimonial-crud'] = ['testimonial create/read/update-one-field/full-snapshot/delete', function () use (
        $wpsr_marker, $wpsr_create_review, $wpsr_review_snapshot, $wpsr_review_update_payload, $wpsr_item_ids
    ) {
        WpsrFactory::trackAbsentFixedOption('wpsr_reviews_testimonial_business_info');
        try {
            [$wpsr_id, $wpsr_payload] = $wpsr_create_review('/testimonials', 'custom', 'testimonial');
            $wpsr_read = WpsrTest::rest('GET', '/testimonials', ['search' => $wpsr_marker]);
            WpsrTest::assertHealthy($wpsr_read, 'testimonial read response', [200]);
            WpsrTest::assertSame([$wpsr_id], $wpsr_item_ids($wpsr_read, 'id'), 'testimonial read returns exact created ID');
            WpsrTest::assertSame('testimonial', (string) Review::find($wpsr_id)->platform_name, 'testimonial route persists route-owned discriminator');

            $wpsr_before = $wpsr_review_snapshot($wpsr_id);
            $wpsr_new_title = $wpsr_payload['review_title'] . ' updated';
            $wpsr_update = WpsrTest::rest('PUT', '/testimonials/' . $wpsr_id, [
                'review' => $wpsr_review_update_payload(Review::find($wpsr_id), $wpsr_new_title),
            ]);
            WpsrTest::assertHealthy($wpsr_update, 'testimonial update response', [200]);
            WpsrTest::assertSame($wpsr_new_title, (string) Review::find($wpsr_id)->review_title, 'testimonial update persisted requested title');
            $wpsr_after = $wpsr_review_snapshot($wpsr_id);
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'row.review_title' => ['before' => $wpsr_payload['review_title'], 'after' => $wpsr_new_title],
            ], 'testimonial update preserves every other persisted field and pivot');

            $wpsr_delete = WpsrTest::rest('DELETE', '/testimonials', ['ids' => [$wpsr_id]]);
            WpsrTest::assertHealthy($wpsr_delete, 'testimonial delete response', [200]);
            WpsrTest::assertTrue(strpos((string) $wpsr_delete['data'], '1 review') !== false, 'testimonial delete response reports one deletion');
            WpsrTest::assertSame(null, Review::find($wpsr_id), 'testimonial delete row is gone');
            WpsrTest::assertSame(0, ReviewCategoryRelationship::where('review_id', $wpsr_id)->count(), 'testimonial delete pivots are gone');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['category-crud'] = ['category attach-create/read/rename-one-field/full-snapshot/delete', function () use (
        $wpsr_marker, $wpsr_category_snapshot
    ) {
        try {
            $wpsr_review = WpsrFactory::review(['platform_name' => 'custom', 'review_title' => 'Category lifecycle owner']);
            $wpsr_name = $wpsr_marker . ' Category Original';
            $wpsr_create = WpsrTest::rest('PUT', '/reviews/bulk-category', [
                'ids' => [(int) $wpsr_review->id], 'action' => 'add', 'new_category_name' => $wpsr_name,
            ]);
            WpsrTest::assertHealthy($wpsr_create, 'category attach-create response', [200]);
            $wpsr_category = ReviewCategory::findBySlug(ReviewCategory::makeSlug($wpsr_name));
            WpsrTest::assertTrue((bool) $wpsr_category, 'category attach-create persists category');
            WpsrFactory::trackCategory($wpsr_category);
            WpsrFactory::relationship((int) $wpsr_review->id, (int) $wpsr_category->id);
            $wpsr_category_id = (int) $wpsr_category->id;

            $wpsr_read = WpsrTest::rest('GET', '/reviews/categories');
            WpsrTest::assertHealthy($wpsr_read, 'category read response', [200]);
            $wpsr_category_rows = $wpsr_read['data']['categories'] ?? [];
            if (is_object($wpsr_category_rows) && method_exists($wpsr_category_rows, 'toArray')) {
                $wpsr_category_rows = $wpsr_category_rows->toArray();
            }
            $wpsr_read_ids = array_map('intval', array_map(function ($wpsr_row) {
                return is_object($wpsr_row) ? $wpsr_row->id : ($wpsr_row['id'] ?? 0);
            }, is_array($wpsr_category_rows) ? $wpsr_category_rows : []));
            WpsrTest::assertTrue(in_array($wpsr_category_id, $wpsr_read_ids, true), 'category read contains created category ID');

            $wpsr_before = $wpsr_category_snapshot($wpsr_category_id);
            $wpsr_new_name = $wpsr_marker . ' Category Renamed';
            $wpsr_new_slug = ReviewCategory::makeSlug($wpsr_new_name);
            $wpsr_update = WpsrTest::rest('PUT', '/reviews/categories/' . $wpsr_category_id, ['name' => $wpsr_new_name]);
            WpsrTest::assertHealthy($wpsr_update, 'category rename response', [200]);
            $wpsr_reloaded = WpsrFactory::refreshTrackedCategory($wpsr_category_id);
            WpsrTest::assertSame($wpsr_new_name, (string) $wpsr_reloaded->name, 'category rename persisted requested name');
            $wpsr_after = $wpsr_category_snapshot($wpsr_category_id);
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'row.name' => ['before' => $wpsr_name, 'after' => $wpsr_new_name],
                'row.slug' => ['before' => ReviewCategory::makeSlug($wpsr_name), 'after' => $wpsr_new_slug],
            ], 'category rename preserves every other persisted field and relationship; slug is documented derived field');

            $wpsr_delete = WpsrTest::rest('DELETE', '/reviews/categories/' . $wpsr_category_id);
            WpsrTest::assertHealthy($wpsr_delete, 'category delete response', [200]);
            WpsrTest::assertSame(null, ReviewCategory::find($wpsr_category_id), 'category delete row is gone');
            WpsrTest::assertSame(0, ReviewCategoryRelationship::where('category_id', $wpsr_category_id)->count(), 'category delete relationships are gone');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['template-crud'] = ['template create/read/update-one-title/full-snapshot/delete', function () use (
        $wpsr_marker, $wpsr_response_value, $wpsr_track_created_post, $wpsr_item_ids, $wpsr_post_snapshot, $wpsr_assert_post_gone
    ) {
        try {
            $wpsr_platform = $wpsr_marker . '-template-platform';
            $wpsr_title = ucfirst($wpsr_platform) . ' Template';
            $wpsr_create = WpsrTest::rest('POST', '/templates', ['platform' => $wpsr_platform, 'onboarding' => false]);
            WpsrTest::assertHealthy($wpsr_create, 'template create response', [200]);
            $wpsr_id = (int) $wpsr_response_value($wpsr_create['data'], 'template_id');
            $wpsr_track_created_post($wpsr_id, 'wp_social_reviews', $wpsr_title, $wpsr_platform);
            WpsrTest::assertTrue($wpsr_id > 0, 'template create response includes ID');

            $wpsr_read = WpsrTest::rest('GET', '/templates', ['search' => $wpsr_marker, 'templateType' => 'template']);
            WpsrTest::assertHealthy($wpsr_read, 'template read response', [200]);
            WpsrTest::assertSame([$wpsr_id], $wpsr_item_ids($wpsr_read, 'ID'), 'template read returns exact created ID');

            $wpsr_before = $wpsr_post_snapshot($wpsr_id);
            $wpsr_new_title = $wpsr_marker . ' Renamed Template';
            $wpsr_update = WpsrTest::rest('PUT', '/templates/title/' . $wpsr_id, ['template_title' => $wpsr_new_title]);
            WpsrTest::assertHealthy($wpsr_update, 'template title update response', [200]);
            WpsrFactory::refreshTrackedPost($wpsr_id);
            WpsrTest::assertSame($wpsr_new_title, get_post($wpsr_id)->post_title, 'template title update persisted requested title');
            $wpsr_after = $wpsr_post_snapshot($wpsr_id);
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'row.post_title' => ['before' => $wpsr_title, 'after' => $wpsr_new_title],
            ], 'template title update preserves every other post and meta field');

            $wpsr_delete = WpsrTest::rest('DELETE', '/templates', ['ids' => [$wpsr_id]]);
            WpsrTest::assertHealthy($wpsr_delete, 'template delete response', [200]);
            WpsrTest::assertSame(1, (int) preg_match('/1 template/', (string) ($wpsr_delete['data']['message'] ?? '')), 'template delete response reports one deletion');
            $wpsr_assert_post_gone($wpsr_id, 'template delete');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_post_status_crud = function ($wpsr_family, $wpsr_create_route, $wpsr_list_route, $wpsr_delete_route, $wpsr_type, $wpsr_create_params, $wpsr_title, $wpsr_content) use (
        $wpsr_response_value, $wpsr_track_created_post, $wpsr_item_ids, $wpsr_post_snapshot, $wpsr_assert_post_gone
    ) {
        $wpsr_create = WpsrTest::rest('POST', $wpsr_create_route, $wpsr_create_params);
        WpsrTest::assertHealthy($wpsr_create, $wpsr_family . ' create response', [200]);
        $wpsr_id = (int) $wpsr_response_value($wpsr_create['data'], 'template_id');
        $wpsr_track_created_post($wpsr_id, $wpsr_type, $wpsr_title, $wpsr_content);
        WpsrTest::assertTrue($wpsr_id > 0, $wpsr_family . ' create response includes ID');

        $wpsr_read = WpsrTest::rest('GET', $wpsr_list_route, []);
        WpsrTest::assertHealthy($wpsr_read, $wpsr_family . ' read response', [200]);
        WpsrTest::assertTrue(in_array($wpsr_id, $wpsr_item_ids($wpsr_read, 'ID'), true), $wpsr_family . ' read contains created ID');

        $wpsr_before = $wpsr_post_snapshot($wpsr_id);
        $wpsr_update = WpsrTest::rest('PUT', $wpsr_list_route, ['ids' => [$wpsr_id], 'status' => 'draft']);
        WpsrTest::assertHealthy($wpsr_update, $wpsr_family . ' status update response', [200]);
        WpsrFactory::refreshTrackedPost($wpsr_id);
        WpsrTest::assertSame('draft', get_post($wpsr_id)->post_status, $wpsr_family . ' status persisted');
        $wpsr_after = $wpsr_post_snapshot($wpsr_id);
        WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
            'row.post_status' => ['before' => 'publish', 'after' => 'draft'],
        ], $wpsr_family . ' status update preserves every other post and meta field');

        $wpsr_delete = WpsrTest::rest('DELETE', $wpsr_delete_route, ['ids' => [$wpsr_id]]);
        WpsrTest::assertHealthy($wpsr_delete, $wpsr_family . ' delete response', [200]);
        WpsrTest::assertTrue(strpos(wp_json_encode($wpsr_delete['data']), '1 ' . $wpsr_family) !== false, $wpsr_family . ' delete response reports one deletion');
        $wpsr_assert_post_gone($wpsr_id, $wpsr_family . ' delete');
    };

    $wpsr_implementations['widget-crud'] = ['widget create/read/update-one-status/full-snapshot/delete', function () use ($wpsr_post_status_crud) {
        try {
            $wpsr_post_status_crud('widget', '/chat-widgets', '/chat-widgets', '/chat-widgets', 'wpsr_social_chats', ['onboarding' => false], 'Chat Widget', 'social chats');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['notification-crud'] = ['notification create/read/update-one-status/full-snapshot/delete', function () use ($wpsr_marker, $wpsr_post_status_crud) {
        try {
            $wpsr_platform = $wpsr_marker . '-notification';
            $wpsr_post_status_crud('notification', '/notifications', '/notifications', '/notifications', 'wpsr_reviews_notify', ['platform' => $wpsr_platform, 'onboarding' => false], ucfirst($wpsr_platform) . ' Notification Popup', $wpsr_platform);
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['translation-settings'] = ['translation settings create/read/update exactly one field/full snapshot', function () use ($wpsr_marker) {
        $wpsr_frontend_payload = function ($wpsr_strings) {
            $wpsr_payload = [];
            foreach ((array) $wpsr_strings as $wpsr_key => $wpsr_value) {
                $wpsr_payload[strtolower(preg_replace('/[\s\-]+/', '_', (string) $wpsr_key))] = $wpsr_value;
            }
            return $wpsr_payload;
        };
        WpsrFactory::trackAbsentFixedOption('wpsr_global_settings');
        try {
            $wpsr_initial_read = WpsrTest::rest('GET', '/settings/translations');
            WpsrTest::assertHealthy($wpsr_initial_read, 'translation initial read', [200]);
            $wpsr_translations = $wpsr_frontend_payload($wpsr_initial_read['data']['translations_settings'] ?? []);
            $wpsr_translations['read_more'] = $wpsr_marker . ' Read more';
            $wpsr_translations['read_less'] = $wpsr_marker . ' Read less';
            $wpsr_create = WpsrTest::rest('POST', '/settings/translations', ['translations_settings' => $wpsr_translations]);
            WpsrTest::assertHealthy($wpsr_create, 'translation baseline save', [200]);
            $wpsr_read = WpsrTest::rest('GET', '/settings/translations');
            WpsrTest::assertHealthy($wpsr_read, 'translation persisted read', [200]);
            $wpsr_next = $wpsr_frontend_payload($wpsr_read['data']['translations_settings'] ?? []);
            WpsrTest::assertSame($wpsr_translations['read_less'], $wpsr_next['read_less'] ?? null, 'translation read returns persisted sibling');

            $wpsr_before = get_option('wpsr_global_settings');
            $wpsr_old = $wpsr_next['read_more'];
            $wpsr_next['read_more'] = $wpsr_marker . ' Continue reading';
            $wpsr_update = WpsrTest::rest('POST', '/settings/translations', ['translations_settings' => $wpsr_next]);
            WpsrTest::assertHealthy($wpsr_update, 'translation one-field update', [200]);
            $wpsr_after = get_option('wpsr_global_settings');
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'global_settings.translations.read_more' => ['before' => $wpsr_old, 'after' => $wpsr_next['read_more']],
            ], 'translation update preserves complete global settings option');
            $wpsr_reload = WpsrTest::rest('GET', '/settings/translations');
            $wpsr_reloaded_payload = $wpsr_frontend_payload($wpsr_reload['data']['translations_settings'] ?? []);
            WpsrTest::assertSame($wpsr_next['read_more'], $wpsr_reloaded_payload['read_more'] ?? null, 'translation reload returns updated field');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['advanced-settings'] = ['advanced settings read/update exactly one field/full snapshot', function () use ($wpsr_marker) {
        $wpsr_seed = (new GlobalSettings())->formatGlobalSettings([
            'global_settings' => [
                'translations' => ['read_more' => $wpsr_marker . ' preserved translation'],
                'advance_settings' => [
                    'has_gdpr' => 'false',
                    'optimize_image_format' => 'jpg',
                    'review_optimized_images' => 'false',
                    'preserve_plugin_data' => 'true',
                    'ai_review_summarizer_enabled' => 'false',
                    'ai_platform' => 'OpenRouter',
                    'ai_api_key' => '',
                    'selected_model' => $wpsr_marker . '-model',
                    'email_report' => ['status' => 'false', 'sending_day' => 'Mon', 'recipients' => 'admin@example.test'],
                ],
            ],
        ]);
        WpsrFactory::ownedOption('wpsr_global_settings', $wpsr_seed);
        try {
            $wpsr_read = WpsrTest::rest('GET', '/settings/advance-settings');
            WpsrTest::assertHealthy($wpsr_read, 'advanced settings read', [200]);
            $wpsr_advance = (array) ($wpsr_read['data']['advance_settings'] ?? []);
            WpsrTest::assertSame($wpsr_marker . '-model', $wpsr_advance['selected_model'] ?? null, 'advanced settings read returns owned marker model');
            // One canonical save normalizes nested numeric request values to their
            // production persisted scalar types before one-field diff begins.
            $wpsr_canonical = WpsrTest::rest('POST', '/settings/advance-settings', ['advance_settings' => $wpsr_advance]);
            WpsrTest::assertHealthy($wpsr_canonical, 'advanced settings canonical save', [200]);
            $wpsr_advance = (array) WpsrTest::rest('GET', '/settings/advance-settings')['data']['advance_settings'];
            $wpsr_before = get_option('wpsr_global_settings');
            $wpsr_advance['has_gdpr'] = 'true';
            $wpsr_update = WpsrTest::rest('POST', '/settings/advance-settings', ['advance_settings' => $wpsr_advance]);
            WpsrTest::assertHealthy($wpsr_update, 'advanced settings one-field update', [200]);
            $wpsr_after = get_option('wpsr_global_settings');
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'global_settings.advance_settings.has_gdpr' => ['before' => 'false', 'after' => 'true'],
            ], 'advanced settings update preserves complete global settings option');
            $wpsr_reload = WpsrTest::rest('GET', '/settings/advance-settings');
            WpsrTest::assertSame('true', $wpsr_reload['data']['advance_settings']['has_gdpr'] ?? null, 'advanced settings reload returns changed field');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['platform-settings'] = ['platform settings create/read/update exactly one field/full snapshot', function () {
        WpsrFactory::trackAbsentFixedOption('wpsr_youtube_global_settings');
        try {
            $wpsr_settings = ['global_settings' => [
                'caching_type' => 'background', 'expiration' => 43200, 'oembed' => 'false',
                'optimized_images' => 'false', 'is_enabled_platform' => true,
            ]];
            $wpsr_create = WpsrTest::rest('PUT', '/settings', ['platform' => 'youtube', 'settings' => $wpsr_settings]);
            WpsrTest::assertHealthy($wpsr_create, 'YouTube settings baseline save', [200]);
            $wpsr_read = WpsrTest::rest('GET', '/settings', ['platform' => 'youtube']);
            WpsrTest::assertHealthy($wpsr_read, 'YouTube settings read', [200]);
            $wpsr_persisted = get_option('wpsr_youtube_global_settings');
            WpsrTest::assertSame(43200, (int) ($wpsr_persisted['global_settings']['expiration'] ?? 0), 'YouTube settings persisted expiration');

            $wpsr_before = $wpsr_persisted;
            $wpsr_next = $wpsr_before;
            $wpsr_next['global_settings']['expiration'] = 86400;
            $wpsr_update = WpsrTest::rest('PUT', '/settings', ['platform' => 'youtube', 'settings' => $wpsr_next]);
            WpsrTest::assertHealthy($wpsr_update, 'YouTube settings one-field update', [200]);
            $wpsr_after = get_option('wpsr_youtube_global_settings');
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'global_settings.expiration' => [
                    'before' => $wpsr_before['global_settings']['expiration'],
                    'after' => $wpsr_after['global_settings']['expiration'],
                ],
            ], 'platform settings update preserves complete option');
            $wpsr_reload = WpsrTest::rest('GET', '/settings', ['platform' => 'youtube']);
            WpsrTest::assertHealthy($wpsr_reload, 'YouTube settings reload', [200]);
            WpsrTest::assertSame(86400, (int) get_option('wpsr_youtube_global_settings')['global_settings']['expiration'], 'YouTube settings reload persisted changed field');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['shoppable-settings'] = ['shoppable settings create/read/update-one-field/delete-one-item/full snapshots', function () use ($wpsr_marker) {
        WpsrFactory::trackAbsentFixedOption('wpsr_global_shoppable_settings');
        try {
            $wpsr_settings = [
                'instagram' => [
                    ['hashtags' => $wpsr_marker . '-one', 'source_type' => 'custom_url', 'url_settings' => ['url' => 'https://example.test/one']],
                    ['hashtags' => $wpsr_marker . '-two', 'source_type' => 'custom_url', 'url_settings' => ['url' => 'https://example.test/two']],
                ],
                'facebook' => [
                    ['hashtags' => $wpsr_marker . '-facebook-sibling', 'source_type' => 'custom_url', 'url_settings' => ['url' => 'https://example.test/facebook']],
                ],
            ];
            $wpsr_create = WpsrTest::rest('PUT', '/shoppable', ['settings' => $wpsr_settings]);
            WpsrTest::assertHealthy($wpsr_create, 'shoppable baseline save', [200]);
            $wpsr_read = WpsrTest::rest('GET', '/shoppable');
            WpsrTest::assertHealthy($wpsr_read, 'shoppable settings read', [200]);
            WpsrTest::assertSame($wpsr_marker . '-two', $wpsr_read['data']['settings']['instagram'][1]['hashtags'] ?? null, 'shoppable read returns second owned item');

            $wpsr_before = get_option('wpsr_global_shoppable_settings');
            $wpsr_next = $wpsr_before;
            $wpsr_next['instagram'][0]['url_settings']['url'] = 'https://example.test/one-updated';
            $wpsr_update = WpsrTest::rest('PUT', '/shoppable', ['settings' => $wpsr_next]);
            WpsrTest::assertHealthy($wpsr_update, 'shoppable one-field update', [200]);
            $wpsr_after = get_option('wpsr_global_shoppable_settings');
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'instagram.0.url_settings.url' => ['before' => 'https://example.test/one', 'after' => 'https://example.test/one-updated'],
            ], 'shoppable update preserves every other persisted setting');

            $wpsr_before_delete = $wpsr_after;
            $wpsr_remaining = $wpsr_before_delete;
            $wpsr_removed = $wpsr_remaining['instagram'][1];
            array_pop($wpsr_remaining['instagram']);
            $wpsr_delete = WpsrTest::rest('DELETE', '/shoppable', ['settings' => $wpsr_remaining]);
            WpsrTest::assertHealthy($wpsr_delete, 'shoppable semantic delete', [200]);
            $wpsr_after_delete = get_option('wpsr_global_shoppable_settings');
            WpsrTest::assertOnlyChanges($wpsr_before_delete, $wpsr_after_delete, [
                'instagram.1' => ['before' => $wpsr_removed, 'after' => '__WPSR_MISSING__'],
            ], 'shoppable delete removes only requested item and preserves siblings');
            WpsrTest::assertSame(1, count($wpsr_after_delete['instagram']), 'shoppable deleted item is gone');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['widget-config'] = ['widget config read/update exactly one nested field/full snapshot', function () use (
        $wpsr_response_value, $wpsr_track_created_post, $wpsr_post_snapshot, $wpsr_assert_post_gone
    ) {
        try {
            $wpsr_create = WpsrTest::rest('POST', '/chat-widgets', ['onboarding' => false]);
            WpsrTest::assertHealthy($wpsr_create, 'widget config owner create', [200]);
            $wpsr_id = (int) $wpsr_response_value($wpsr_create['data'], 'template_id');
            $wpsr_track_created_post($wpsr_id, 'wpsr_social_chats', 'Chat Widget', 'social chats');

            $wpsr_read = WpsrTest::rest('GET', '/chat-widgets/meta/chats/' . $wpsr_id);
            WpsrTest::assertHealthy($wpsr_read, 'widget config read', [200]);
            $wpsr_config = $wpsr_read['data']['data']['settings']['chat_settings'] ?? null;
            WpsrTest::assertTrue(is_array($wpsr_config), 'widget config read returns full chat settings');
            $wpsr_canonical = WpsrTest::rest('PUT', '/chat-widgets/meta/chats/' . $wpsr_id, ['args' => wp_json_encode($wpsr_config)]);
            WpsrTest::assertHealthy($wpsr_canonical, 'widget config canonical save', [200]);

            $wpsr_before = $wpsr_post_snapshot($wpsr_id);
            $wpsr_current = $wpsr_before['postmeta']['_wpsr_template_config']['chat_settings'];
            $wpsr_old = $wpsr_current['chat_button']['button_text'];
            $wpsr_current['chat_button']['button_text'] = WpsrFactory::marker() . ' Chat now';
            $wpsr_update = WpsrTest::rest('PUT', '/chat-widgets/meta/chats/' . $wpsr_id, ['args' => wp_json_encode($wpsr_current)]);
            WpsrTest::assertHealthy($wpsr_update, 'widget config one-field update', [200]);
            $wpsr_after = $wpsr_post_snapshot($wpsr_id);
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'postmeta._wpsr_template_config.chat_settings.chat_button.button_text' => ['before' => $wpsr_old, 'after' => $wpsr_current['chat_button']['button_text']],
            ], 'widget config update preserves every other post and decoded config field');

            $wpsr_delete = WpsrTest::rest('DELETE', '/chat-widgets', ['ids' => [$wpsr_id]]);
            WpsrTest::assertHealthy($wpsr_delete, 'widget config owner delete', [200]);
            $wpsr_assert_post_gone($wpsr_id, 'widget config owner delete');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['review-template-config'] = ['review template config read/update exactly one nested field/full snapshot', function () use (
        $wpsr_response_value, $wpsr_track_created_post, $wpsr_post_snapshot, $wpsr_assert_post_gone
    ) {
        try {
            $wpsr_create = WpsrTest::rest('POST', '/templates', ['platform' => 'google', 'onboarding' => false]);
            WpsrTest::assertHealthy($wpsr_create, 'review template config owner create', [200]);
            $wpsr_id = (int) $wpsr_response_value($wpsr_create['data'], 'template_id');
            $wpsr_track_created_post($wpsr_id, 'wp_social_reviews', 'Google Business Profile Template', 'google');
            $wpsr_initial = $wpsr_post_snapshot($wpsr_id)['postmeta']['_wpsr_template_config'];
            $wpsr_canonical = WpsrTest::rest('PUT', '/templates/meta/reviews/' . $wpsr_id, ['template_meta' => wp_json_encode($wpsr_initial)]);
            WpsrTest::assertHealthy($wpsr_canonical, 'review template canonical save', [200]);
            // Second pass reaches formatter fixed point (false/null request values
            // become persisted string/zero forms on first round trip).
            $wpsr_canonical_meta = $wpsr_post_snapshot($wpsr_id)['postmeta']['_wpsr_template_config'];
            $wpsr_canonical_again = WpsrTest::rest('PUT', '/templates/meta/reviews/' . $wpsr_id, ['template_meta' => wp_json_encode($wpsr_canonical_meta)]);
            WpsrTest::assertHealthy($wpsr_canonical_again, 'review template canonical fixed-point save', [200]);
            $wpsr_read = WpsrTest::rest('GET', '/templates/meta/reviews/' . $wpsr_id);
            WpsrTest::assertHealthy($wpsr_read, 'review template config read', [200]);
            WpsrTest::assertSame($wpsr_id, (int) ($wpsr_read['data']['template_id'] ?? 0), 'review template config read returns owned ID');

            $wpsr_before = $wpsr_post_snapshot($wpsr_id);
            $wpsr_meta = $wpsr_before['postmeta']['_wpsr_template_config'];
            $wpsr_old = $wpsr_meta['custom_title_text'];
            $wpsr_meta['custom_title_text'] = WpsrFactory::marker() . ' Customer voices';
            $wpsr_update = WpsrTest::rest('PUT', '/templates/meta/reviews/' . $wpsr_id, ['template_meta' => wp_json_encode($wpsr_meta)]);
            WpsrTest::assertHealthy($wpsr_update, 'review template config one-field update', [200]);
            $wpsr_after = $wpsr_post_snapshot($wpsr_id);
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'postmeta._wpsr_template_config.custom_title_text' => ['before' => $wpsr_old, 'after' => $wpsr_meta['custom_title_text']],
            ], 'review template config update preserves every other post and decoded config field');

            $wpsr_delete = WpsrTest::rest('DELETE', '/templates', ['ids' => [$wpsr_id]]);
            WpsrTest::assertHealthy($wpsr_delete, 'review template config owner delete', [200]);
            $wpsr_assert_post_gone($wpsr_id, 'review template config owner delete');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_implementations['feed-template-config'] = ['feed template config update-one-field plus shoppable field/full snapshots/delete', function () use (
        $wpsr_marker, $wpsr_response_value, $wpsr_track_created_post, $wpsr_post_snapshot, $wpsr_assert_post_gone
    ) {
        try {
            $wpsr_create = WpsrTest::rest('POST', '/templates', ['platform' => 'youtube', 'onboarding' => false]);
            WpsrTest::assertHealthy($wpsr_create, 'feed template config owner create', [200]);
            $wpsr_id = (int) $wpsr_response_value($wpsr_create['data'], 'template_id');
            $wpsr_track_created_post($wpsr_id, 'wp_social_reviews', 'Youtube Template', 'youtube');
            $wpsr_meta = $wpsr_post_snapshot($wpsr_id)['postmeta']['_wpsr_template_config'];
            $wpsr_meta['feed_settings']['template'] = 'template1';
            $wpsr_meta['feed_settings']['shoppable_settings'] = ['shoppable_feeds' => []];
            $wpsr_canonical = WpsrTest::rest('PUT', '/templates/meta/feeds/' . $wpsr_id, [
                'platform' => 'youtube', 'settings' => wp_json_encode($wpsr_meta),
            ]);
            WpsrTest::assertHealthy($wpsr_canonical, 'feed template canonical save', [200]);

            $wpsr_before = $wpsr_post_snapshot($wpsr_id);
            $wpsr_next = $wpsr_before['postmeta']['_wpsr_template_config'];
            $wpsr_next['feed_settings']['template'] = 'template2';
            $wpsr_update = WpsrTest::rest('PUT', '/templates/meta/feeds/' . $wpsr_id, [
                'platform' => 'youtube', 'settings' => wp_json_encode($wpsr_next),
            ]);
            WpsrTest::assertHealthy($wpsr_update, 'feed template one-field update', [200]);
            $wpsr_after = $wpsr_post_snapshot($wpsr_id);
            WpsrTest::assertOnlyChanges($wpsr_before, $wpsr_after, [
                'postmeta._wpsr_template_config.feed_settings.template' => ['before' => 'template1', 'after' => 'template2'],
            ], 'feed template update preserves every other post and decoded config field');

            $wpsr_before_shoppable = $wpsr_after;
            $wpsr_username = $wpsr_marker . '-feed';
            $wpsr_fields = [['hashtags' => $wpsr_marker . '-product', 'text' => 'Owned product', 'url' => 'https://example.test/product']];
            $wpsr_shoppable = WpsrTest::rest('PUT', '/shoppable/template-settings/' . $wpsr_id, [
                'platform' => 'youtube',
                'shoppable_fields' => wp_json_encode($wpsr_fields),
                'settings' => wp_json_encode($wpsr_before_shoppable['postmeta']['_wpsr_template_config']),
                'feed' => wp_json_encode(['username' => $wpsr_username, 'id' => 17]),
            ]);
            WpsrTest::assertHealthy($wpsr_shoppable, 'feed template shoppable field save', [200]);
            $wpsr_after_shoppable = $wpsr_post_snapshot($wpsr_id);
            WpsrTest::assertOnlyChanges($wpsr_before_shoppable, $wpsr_after_shoppable, [
                'postmeta._wpsr_template_config.feed_settings.shoppable_settings.shoppable_feeds.' . $wpsr_username => [
                    'before' => '__WPSR_MISSING__', 'after' => [17 => $wpsr_fields],
                ],
            ], 'template shoppable save adds one owned feed field and preserves every other field');

            $wpsr_delete = WpsrTest::rest('DELETE', '/templates', ['ids' => [$wpsr_id]]);
            WpsrTest::assertHealthy($wpsr_delete, 'feed template config owner delete', [200]);
            $wpsr_assert_post_gone($wpsr_id, 'feed template config owner delete');
        } finally {
            WpsrFactory::cleanup();
        }
    }];

    $wpsr_case_manifest = require dirname(__DIR__) . '/round-trips/cases.manifest.php';
    $wpsr_registered = array_keys($wpsr_implementations);
    $wpsr_expected = array_keys($wpsr_case_manifest);
    sort($wpsr_registered, SORT_STRING);
    sort($wpsr_expected, SORT_STRING);
    if ($wpsr_registered !== $wpsr_expected) {
        throw new RuntimeException('Phase 9 runtime case registry drift: expected ' . wp_json_encode($wpsr_expected)
            . '; implemented ' . wp_json_encode($wpsr_registered));
    }

    foreach ($wpsr_implementations as $wpsr_id => $wpsr_case) {
        WpsrTest::case('Phase 9 [' . $wpsr_id . '] ' . $wpsr_case[0], $wpsr_case[1]);
    }
};
