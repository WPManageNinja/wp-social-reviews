<?php
/**
 * Phase 10: input-validation and limited-capability authorization regressions.
 */

use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Models\ReviewCategoryRelationship;
use WPSocialReviews\App\Services\PermissionManager;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper as ReviewsHelper;

return function () {
    $wpsr_admin_id = get_current_user_id();
    $wpsr_marker = WpsrFactory::marker();

    $wpsr_items = function (array $wpsr_result) {
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

    $wpsr_ids = function ($wpsr_rows, $wpsr_key = 'id') {
        $wpsr_ids = [];
        foreach ($wpsr_rows as $wpsr_row) {
            $wpsr_ids[] = (int) (is_object($wpsr_row) ? $wpsr_row->{$wpsr_key} : ($wpsr_row[$wpsr_key] ?? 0));
        }
        return $wpsr_ids;
    };

    $wpsr_decode_config = function ($wpsr_value) {
        for ($wpsr_depth = 0; $wpsr_depth < 3 && is_string($wpsr_value); $wpsr_depth++) {
            $wpsr_json = json_decode($wpsr_value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $wpsr_json;
            }
            $wpsr_next = maybe_unserialize($wpsr_value);
            if ($wpsr_next === $wpsr_value) {
                break;
            }
            $wpsr_value = $wpsr_next;
        }
        return $wpsr_value;
    };

    $wpsr_as_user = function ($wpsr_user, callable $wpsr_callback) use ($wpsr_admin_id) {
        wp_set_current_user((int) $wpsr_user->ID);
        PermissionManager::currentUserPermissions(false);
        try {
            return $wpsr_callback();
        } finally {
            wp_set_current_user($wpsr_admin_id);
            PermissionManager::currentUserPermissions(false);
        }
    };

    $wpsr_implementations = [];

    $wpsr_implementations['query-shaping'] = function () use ($wpsr_marker, $wpsr_items, $wpsr_ids) {
        try {
            $wpsr_old = WpsrFactory::review([
                'platform_name' => 'custom', 'review_title' => $wpsr_marker . ' SELECT old',
                'review_time' => '2026-01-01 00:00:00',
            ]);
            $wpsr_new = WpsrFactory::review([
                'platform_name' => 'custom', 'review_title' => $wpsr_marker . ' SELECT new',
                'review_time' => '2026-01-02 00:00:00',
            ]);

            $wpsr_result = WpsrTest::rest('GET', '/reviews', [
                'search' => $wpsr_marker . ' SELECT', 'filter' => 'custom',
                'order_by' => 'DESC; SELECT review_id FROM wpsr_reviews', 'per_page' => 20, 'page' => 1,
            ]);
            WpsrTest::assertHealthy($wpsr_result, 'SELECT-shaped review order probe', [200]);
            WpsrTest::assertSame(
                [(int) $wpsr_new->id, (int) $wpsr_old->id],
                $wpsr_ids($wpsr_items($wpsr_result)),
                'invalid admin sort direction uses exact descending fallback'
            );

            $wpsr_model_rows = Review::filteredReviewsQuery(['custom'], [
                'order' => 'rating DESC; SELECT id',
                'selectedBusinesses' => [$wpsr_marker . '-source'],
                'totalReviewsNumber' => ['desktop' => 20, 'mobile' => 20],
            ])->get();
            WpsrTest::assertNoDatabaseError('SELECT-shaped template order probe');
            WpsrTest::assertSame(
                [(int) $wpsr_new->id, (int) $wpsr_old->id],
                $wpsr_ids($wpsr_model_rows),
                'invalid template order key resolves through literal newest sort map'
            );
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['like-wildcards'] = function () use ($wpsr_marker, $wpsr_ids) {
        try {
            $wpsr_literal = WpsrFactory::review([
                'platform_name' => 'custom',
                'review_title' => $wpsr_marker . ' literal 100%_match',
            ]);
            WpsrFactory::review([
                'platform_name' => 'custom',
                'review_title' => $wpsr_marker . ' ordinary match',
            ]);

            $wpsr_rows = Review::filteredReviewsQuery(['custom'], [
                'visitorSearch' => '%_',
                'display_review_title' => 'true',
                'isReviewerText' => 'false',
                'reviewer_name' => 'false',
                'order' => 'newest',
                'totalReviewsNumber' => ['desktop' => 20, 'mobile' => 20],
            ])->get();
            WpsrTest::assertNoDatabaseError('literal visitor LIKE wildcard query');
            WpsrTest::assertSame(
                [(int) $wpsr_literal->id],
                $wpsr_ids($wpsr_rows),
                'visitor LIKE percent and underscore match literal characters only'
            );
            global $wpdb;
            WpsrTest::assertSame('\\%\\_', $wpdb->esc_like('%_'), 'WordPress LIKE escape boundary output');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['pagination-bounds'] = function () use ($wpsr_marker) {
        $wpsr_platform = $wpsr_marker . '-bounds';
        try {
            WpsrFactory::option('wpsr_reviews_' . $wpsr_platform . '_business_info', [
                'one' => ['name' => 'One'],
                'two' => ['name' => 'Two'],
            ]);
            $wpsr_result = WpsrTest::rest('GET', '/platforms/reviews/configs', [
                'platform' => $wpsr_platform, 'page' => -99, 'per_page' => -1,
            ]);
            WpsrTest::assertHealthy($wpsr_result, 'review config pagination lower bounds', [200]);
            WpsrTest::assertSame([
                'page' => 1, 'per_page' => 1, 'total' => 2, 'total_pages' => 2,
            ], $wpsr_result['data']['pagination'] ?? [], 'review config page and per-page clamp to one');

            $wpsr_page = WpsrFactory::post('page', ['post_title' => 'Pagination boundary page']);
            $wpsr_pages = WpsrTest::rest('GET', '/pages/search', [
                'include_ids' => ['0', (string) $wpsr_page->ID, 'SELECT'],
                'page' => 0, 'per_page' => 0, 'post_type' => 'page',
            ]);
            WpsrTest::assertHealthy($wpsr_pages, 'page selector integer boundary probe', [200]);
            $wpsr_results = $wpsr_pages['data']['data']['results'] ?? ($wpsr_pages['data']['results'] ?? []);
            WpsrTest::assertSame([(string) $wpsr_page->ID], array_values(array_map(function ($wpsr_row) {
                return (string) ($wpsr_row['id'] ?? '');
            }, $wpsr_results)), 'page selector drops zero/non-ID tokens and resolves owned ID only');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['known-negative-id-gap'] = function () {
        try {
            $wpsr_page = WpsrFactory::post('page', ['post_title' => 'Negative ID boundary page']);
            $wpsr_search = function ($wpsr_includeIds) {
                $wpsr_result = WpsrTest::rest('GET', '/pages/search', [
                    'include_ids' => $wpsr_includeIds,
                    'page' => 1,
                    'per_page' => 20,
                    'post_type' => 'page',
                ]);
                WpsrTest::assertSame(200, $wpsr_result['status'], 'page search answers without error');
                $wpsr_rows = $wpsr_result['data']['data']['results'] ?? ($wpsr_result['data']['results'] ?? []);
                return array_map(function ($wpsr_row) {
                    return (int) ($wpsr_row['id'] ?? 0);
                }, (array) $wpsr_rows);
            };

            // absint() mapped "-12" onto post 12, so a negative id resolved to a real object.
            WpsrTest::assertSame([], $wpsr_search(['-' . (int) $wpsr_page->ID]),
                'a negative include id never resolves to its positive object');

            // Positive control: the real id still resolves, and mixed input keeps only it.
            WpsrTest::assertSame([(int) $wpsr_page->ID], $wpsr_search([(int) $wpsr_page->ID]),
                'a positive include id still resolves');
            WpsrTest::assertSame([(int) $wpsr_page->ID],
                $wpsr_search(['0', '-' . (int) $wpsr_page->ID, 'SELECT', (int) $wpsr_page->ID]),
                'zero, negative and non-numeric tokens are dropped while the real id survives');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['review-input-output'] = function () use ($wpsr_marker) {
        WpsrFactory::trackAbsentFixedOption('wpsr_reviews_custom_business_info');
        try {
            $wpsr_markup = '<strong>Allowed</strong><img src="x" onerror="SELECT()"><script>SELECT()</script>';
            $wpsr_payload = [
                'platform_name' => 'custom',
                'source_id' => $wpsr_marker . '-source',
                'review_id' => $wpsr_marker . '-input-review',
                'review_title' => '<b>SELECT title</b> & proof',
                'reviewer_name' => '<i>SELECT reviewer</i>',
                'reviewer_email' => 'valid@example.test',
                'reviewer_url' => 'javascript:SELECT(1)',
                'reviewer_img' => 'data:text/html,SELECT',
                'reviewer_text' => $wpsr_markup,
                'review_time' => '2026-06-07 08:09:10',
                'rating' => 4,
                'fields' => [
                    'author_company' => '<b>SELECT company</b>',
                    'author_website_url' => 'javascript:SELECT(2)',
                ],
            ];
            $wpsr_result = WpsrTest::rest('POST', '/reviews', ['review' => $wpsr_payload]);
            WpsrTest::assertHealthy($wpsr_result, 'review sanitizer persistence route', [200]);
            $wpsr_created = $wpsr_result['data']['review'] ?? null;
            $wpsr_id = (int) (is_object($wpsr_created) ? $wpsr_created->id : ($wpsr_created['id'] ?? 0));
            $wpsr_review = Review::find($wpsr_id);
            WpsrTest::assertTrue((bool) $wpsr_review, 'sanitized review exists before value assertions');
            if ($wpsr_review) {
                WpsrFactory::trackReview($wpsr_review);
                WpsrTest::assertSame('SELECT title & proof', (string) $wpsr_review->review_title, 'review title exact sanitize_text_field output');
                WpsrTest::assertSame('SELECT reviewer', (string) $wpsr_review->reviewer_name, 'reviewer name exact sanitize_text_field output');
                WpsrTest::assertSame('valid@example.test', (string) $wpsr_review->reviewer_email, 'reviewer email exact sanitize_email output');
                WpsrTest::assertSame('', (string) $wpsr_review->reviewer_url, 'unsafe reviewer URL is empty');
                WpsrTest::assertSame('', (string) $wpsr_review->reviewer_img, 'unsafe reviewer image URL is empty');
                WpsrTest::assertSame(4, (int) $wpsr_review->rating, 'rating exact integer conversion');
                WpsrTest::assertSame(wp_kses_post($wpsr_markup), (string) $wpsr_review->reviewer_text, 'review body exact wp_kses_post storage output');
                WpsrTest::assertSame('SELECT company', $wpsr_review->fields['author_company'] ?? null, 'nested company exact text sanitizer output');
                WpsrTest::assertSame('', $wpsr_review->fields['author_website_url'] ?? null, 'nested unsafe website URL is empty');

                $wpsr_app = \WPSocialReviews\App\App::getInstance();
                $wpsr_content_html = (string) $wpsr_app->view->make('public.reviews-templates.elements.review-content', [
                    'contentType' => 'full', 'platform_name' => 'custom',
                    'enable_text_typing_animation' => false, 'enable_ai_readmore' => false,
                    'content_length' => 0, 'reviewer_text' => $wpsr_review->reviewer_text,
                ]);
                WpsrTest::assertTrue(strpos($wpsr_content_html, '<strong>Allowed</strong>') !== false, 'rendered review keeps allowed strong markup');
                WpsrTest::assertTrue(stripos($wpsr_content_html, '<script') === false, 'rendered review contains no script element');
                WpsrTest::assertTrue(stripos($wpsr_content_html, 'onerror') === false, 'rendered review contains no event-handler attribute');

                $wpsr_title_html = (string) $wpsr_app->view->make('public.reviews-templates.elements.review-title', [
                    'htmlTag' => 'h3', 'review_title' => $wpsr_review->review_title,
                ]);
                WpsrTest::assertTrue(strpos($wpsr_title_html, 'SELECT title &amp; proof') !== false, 'rendered title escapes ampersand in text context');
            }

            $wpsr_before_count = Review::where('review_id', $wpsr_marker . '-invalid-review')->count();
            $wpsr_invalid = $wpsr_payload;
            $wpsr_invalid['review_id'] = $wpsr_marker . '-invalid-review';
            $wpsr_invalid['reviewer_email'] = 'not-an-email';
            $wpsr_invalid['review_time'] = '2026-02-31 25:61:00';
            $wpsr_rejected = WpsrTest::rest('POST', '/reviews', ['review' => $wpsr_invalid]);
            WpsrTest::assertSame(422, $wpsr_rejected['status'], 'invalid email/date review returns validation status');
            WpsrTest::assertSame($wpsr_before_count, Review::where('review_id', $wpsr_marker . '-invalid-review')->count(), 'invalid typed review persists no row');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['nested-sanitizers'] = function () use ($wpsr_marker, $wpsr_decode_config) {
        try {
            $wpsr_create = WpsrTest::rest('POST', '/chat-widgets', ['onboarding' => false]);
            WpsrTest::assertHealthy($wpsr_create, 'nested sanitizer chat owner create', [200]);
            $wpsr_id = (int) ($wpsr_create['data']['template_id'] ?? 0);
            WpsrFactory::trackPost($wpsr_id, 'wpsr_social_chats', 'Chat Widget', 'social chats');

            $wpsr_read = WpsrTest::rest('GET', '/chat-widgets/meta/chats/' . $wpsr_id);
            WpsrTest::assertHealthy($wpsr_read, 'nested sanitizer chat baseline read', [200]);
            $wpsr_payload = $wpsr_read['data']['data']['settings']['chat_settings'] ?? [];
            WpsrTest::assertTrue(is_array($wpsr_payload) && !empty($wpsr_payload), 'chat baseline exists before sanitizer probe');
            $wpsr_payload['template'] = '<b>template1</b>';
            $wpsr_payload['layout_type'] = 'floating<script>';
            $wpsr_payload['menu_order'] = '17 SELECT';
            $wpsr_payload['chat_button']['button_text'] = '<i>SELECT Chat</i>';
            $wpsr_payload['styles']['widget_icon_bg_color'] = 'url(javascript:SELECT)';
            $wpsr_payload['styles']['header_color'] = '#a1B2c3';
            $wpsr_payload['template1']['chat_header']['picture'] = 'javascript:SELECT(1)';
            $wpsr_payload['template1']['chat_body']['greeting_msg'] = '<strong>Hello</strong><img src="x" onerror="SELECT()">';
            $wpsr_payload['channels'] = [[
                'name' => '<b>whatsapp</b>',
                'credential' => '<b>12345</b>',
                'webUrl' => 'javascript:SELECT(3)',
                'icon' => 'data:text/html,SELECT',
            ]];
            $wpsr_update = WpsrTest::rest('PUT', '/chat-widgets/meta/chats/' . $wpsr_id, [
                'args' => wp_json_encode($wpsr_payload),
            ]);
            WpsrTest::assertHealthy($wpsr_update, 'nested sanitizer chat save', [200]);
            $wpsr_stored = $wpsr_decode_config(get_post_meta($wpsr_id, '_wpsr_template_config', true));
            $wpsr_settings = $wpsr_stored['chat_settings'] ?? $wpsr_stored;
            WpsrTest::assertSame('template1', $wpsr_settings['template'] ?? null, 'chat template exact text sanitizer output');
            WpsrTest::assertSame('floating', $wpsr_settings['layout_type'] ?? null, 'chat layout strips markup');
            global $wpdb;
            $wpsr_menu_order = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT menu_order FROM {$wpdb->posts} WHERE ID = %d",
                $wpsr_id
            ));
            WpsrTest::assertNoDatabaseError('chat menu order persisted query');
            WpsrTest::assertSame(17, $wpsr_menu_order, 'chat menu order exact integer conversion');
            WpsrTest::assertSame('SELECT Chat', $wpsr_settings['chat_button']['button_text'] ?? null, 'nested chat button strips markup');
            WpsrTest::assertSame('', $wpsr_settings['styles']['widget_icon_bg_color'] ?? null, 'invalid CSS color is empty');
            WpsrTest::assertSame('#a1B2c3', $wpsr_settings['styles']['header_color'] ?? null, 'valid hex color preserved exactly');
            WpsrTest::assertSame('', $wpsr_settings['template1']['chat_header']['picture'] ?? null, 'nested unsafe image URL is empty');
            WpsrTest::assertSame(
                wp_kses_post($wpsr_payload['template1']['chat_body']['greeting_msg']),
                $wpsr_settings['template1']['chat_body']['greeting_msg'] ?? null,
                'nested greeting exact wp_kses_post output'
            );
            WpsrTest::assertSame('whatsapp', $wpsr_settings['channels'][0]['name'] ?? null, 'channel name exact text sanitizer output');
            WpsrTest::assertSame('', $wpsr_settings['channels'][0]['webUrl'] ?? null, 'channel unsafe URL is empty');
            WpsrTest::assertSame('', $wpsr_settings['channels'][0]['icon'] ?? null, 'channel unsafe icon URL is empty');

            WpsrTest::assertSame([
                'styles' => [
                    'card' => [
                        'selector' => '.wpsr-cardscript>body',
                        'color' => ['text_color' => '', 'background_color' => '#abc'],
                        'spacing' => ['top' => '12px', 'left' => ''],
                    ],
                ],
            ], wpsr_sanitize_styles_config([
                'styles' => [
                    'card' => [
                        'selector' => '.wpsr-card<script>body',
                        'color' => ['text_color' => 'expression(SELECT)', 'background_color' => '#abc'],
                        'spacing' => ['top' => '12px', 'left' => 'SELECT'],
                    ],
                ],
            ]), 'style config exact selector/color/unit sanitizer output');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['enum-boundaries'] = function () {
        try {
            $wpsr_widget = WpsrFactory::post('wpsr_social_chats', ['post_title' => 'Enum widget']);
            $wpsr_result = WpsrTest::rest('PUT', '/chat-widgets', [
                'ids' => [(int) $wpsr_widget->ID], 'status' => 'publish; SELECT',
            ]);
            WpsrTest::assertHealthy($wpsr_result, 'widget invalid status fallback', [200]);
            WpsrFactory::refreshTrackedPost((int) $wpsr_widget->ID);
            WpsrTest::assertSame('draft', get_post((int) $wpsr_widget->ID)->post_status, 'invalid widget status falls back to exact draft enum');

            $wpsr_review = WpsrFactory::review(['platform_name' => 'custom']);
            $wpsr_category = WpsrFactory::category(['name' => 'Enum category']);
            $wpsr_before = ReviewCategoryRelationship::where('review_id', (int) $wpsr_review->id)->count();
            $wpsr_invalid = WpsrTest::rest('PUT', '/reviews/bulk-category', [
                'ids' => [(int) $wpsr_review->id], 'action' => 'attach; SELECT',
                'category_id' => (int) $wpsr_category->id,
            ]);
            WpsrTest::assertSame(422, $wpsr_invalid['status'], 'invalid bulk-category action returns validation status');
            WpsrTest::assertSame($wpsr_before, ReviewCategoryRelationship::where('review_id', (int) $wpsr_review->id)->count(), 'invalid action persists no relationship');

            $wpsr_stale = WpsrTest::rest('PUT', '/reviews/bulk-category', [
                'ids' => [(int) $wpsr_review->id], 'action' => 'add', 'category_id' => PHP_INT_MAX,
            ]);
            WpsrTest::assertSame(422, $wpsr_stale['status'], 'stale category ID returns validation status');
            WpsrTest::assertSame(0, ReviewCategoryRelationship::where('category_id', PHP_INT_MAX)->count(), 'stale category ID persists no orphan pivot');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['limited-capability-scope'] = function () use ($wpsr_as_user) {
        try {
            $wpsr_user = WpsrFactory::limitedUser(['wpsn_manage_reviews']);
            $wpsr_review = WpsrFactory::review(['platform_name' => 'custom', 'review_approved' => 1]);
            $wpsr_testimonial = WpsrFactory::review(['platform_name' => 'testimonial', 'review_approved' => 1]);
            $wpsr_category = WpsrFactory::category(['name' => 'Limited capability category']);

            $wpsr_as_user($wpsr_user, function () use ($wpsr_review, $wpsr_testimonial, $wpsr_category) {
                $wpsr_status = WpsrTest::rest('PUT', '/reviews/status-update', [
                    'ids' => [(int) $wpsr_review->id, (int) $wpsr_testimonial->id], 'status' => 'disable',
                ]);
                WpsrTest::assertHealthy($wpsr_status, 'review-only user scoped status route', [200]);
                WpsrTest::assertSame(0, (int) Review::find((int) $wpsr_review->id)->review_approved, 'review-only user mutates owned screen row');
                WpsrTest::assertSame(1, (int) Review::find((int) $wpsr_testimonial->id)->review_approved, 'review-only user cannot mutate cross-screen testimonial ID');

                $wpsr_delete_cross = WpsrTest::rest('DELETE', '/reviews', ['ids' => [(int) $wpsr_testimonial->id]]);
                WpsrTest::assertHealthy($wpsr_delete_cross, 'review-only cross-screen delete request', [200]);
                WpsrTest::assertTrue((bool) Review::find((int) $wpsr_testimonial->id), 'review-only destructive route preserves cross-screen testimonial');

                $wpsr_category_delete = WpsrTest::rest('DELETE', '/reviews/categories/' . (int) $wpsr_category->id);
                WpsrTest::assertSame(403, $wpsr_category_delete['status'], 'single-capability user denied global category deletion requiring both caps');
                WpsrTest::assertTrue((bool) ReviewCategory::find((int) $wpsr_category->id), 'denied category deletion persists no change');

                $wpsr_categories = WpsrTest::rest('GET', '/reviews/categories');
                WpsrTest::assertHealthy($wpsr_categories, 'review-only category vocabulary read', [200]);
                $wpsr_rows = $wpsr_categories['data']['categories'] ?? [];
                if (is_object($wpsr_rows) && method_exists($wpsr_rows, 'toArray')) {
                    $wpsr_rows = $wpsr_rows->toArray();
                }
                foreach ((array) $wpsr_rows as $wpsr_row) {
                    $wpsr_array = is_object($wpsr_row) ? $wpsr_row->toArray() : $wpsr_row;
                    WpsrTest::assertTrue(!array_key_exists('reviews_count', (array) $wpsr_array), 'single-capability category read hides cross-screen relationship count');
                }
            });
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['cross-object-scope'] = function () use ($wpsr_as_user) {
        try {
            $wpsr_foreign = WpsrFactory::post('post', ['post_title' => 'Cross object foreign post']);
            $wpsr_before_post = get_post((int) $wpsr_foreign->ID, ARRAY_A);
            $wpsr_before_meta = get_post_meta((int) $wpsr_foreign->ID);

            $wpsr_widget_user = WpsrFactory::limitedUser(['wpsn_manage_chat_widgets']);
            $wpsr_as_user($wpsr_widget_user, function () use ($wpsr_foreign) {
                $wpsr_result = WpsrTest::rest('PUT', '/chat-widgets/meta/chats/' . (int) $wpsr_foreign->ID, [
                    'args' => wp_json_encode(['menu_order' => 999, 'chat_button' => ['button_text' => 'SELECT changed']]),
                ]);
                WpsrTest::assertSame(404, $wpsr_result['status'], 'chat editor rejects ordinary WordPress post ID');
            });

            $wpsr_template_user = WpsrFactory::limitedUser(['wpsn_manage_templates']);
            $wpsr_as_user($wpsr_template_user, function () use ($wpsr_foreign) {
                $wpsr_result = WpsrTest::rest('PUT', '/templates/meta/reviews/' . (int) $wpsr_foreign->ID, [
                    'template_meta' => wp_json_encode(['platform' => ['custom'], 'templateType' => 'grid']),
                ]);
                WpsrTest::assertSame(404, $wpsr_result['status'], 'review-template editor rejects ordinary WordPress post ID');
            });

            WpsrTest::assertSame($wpsr_before_post, get_post((int) $wpsr_foreign->ID, ARRAY_A), 'cross-object editor attempts preserve complete foreign post');
            WpsrTest::assertSame($wpsr_before_meta, get_post_meta((int) $wpsr_foreign->ID), 'cross-object editor attempts preserve complete foreign postmeta');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['known-admin-like-gap'] = function () use ($wpsr_marker, $wpsr_items, $wpsr_ids) {
        try {
            $wpsr_literal = WpsrFactory::review(['platform_name' => 'custom', 'review_title' => $wpsr_marker . ' 100% literal']);
            $wpsr_plain = WpsrFactory::review(['platform_name' => 'custom', 'review_title' => $wpsr_marker . ' plain']);
            $wpsr_search = function ($wpsr_term) use ($wpsr_items, $wpsr_ids) {
                $wpsr_result = WpsrTest::rest('GET', '/reviews', [
                    'search' => $wpsr_term, 'filter' => 'custom', 'per_page' => 20,
                ]);
                WpsrTest::assertSame(200, $wpsr_result['status'], 'admin review search answers without error');
                return $wpsr_ids($wpsr_items($wpsr_result));
            };

            // A bare wildcard is a literal character, so it matches only the row containing it.
            $wpsr_wildcard = $wpsr_search('%');
            WpsrTest::assertSame([(int) $wpsr_literal->id], $wpsr_wildcard,
                'admin percent search matches the literal percent row only');

            $wpsr_underscore = $wpsr_search('_');
            WpsrTest::assertSame([], $wpsr_underscore,
                'admin underscore search matches no row when no title contains one');

            // Positive control: ordinary searching is unaffected by the escaping.
            $wpsr_plain_hit = $wpsr_search($wpsr_marker . ' plain');
            WpsrTest::assertSame([(int) $wpsr_plain->id], $wpsr_plain_hit,
                'admin plain-text search still resolves its exact row');

            $wpsr_both = $wpsr_search($wpsr_marker);
            WpsrTest::assertSame(2, count($wpsr_both),
                'admin shared-prefix search still returns both rows');

            // The sibling `filter` parameter reaches the same LIKE predicate.
            $wpsr_widget = WpsrFactory::post('wp_social_reviews', ['post_title' => $wpsr_marker . ' filter probe']);
            $wpsr_filtered = WpsrTest::rest('GET', '/templates', ['filter' => '%', 'per_page' => 20]);
            WpsrTest::assertSame(200, $wpsr_filtered['status'], 'template filter answers without error');
            WpsrTest::assertSame(false,
                in_array((int) $wpsr_widget->ID, $wpsr_ids($wpsr_items($wpsr_filtered), 'ID'), true),
                'template filter percent is literal and does not match every post');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['known-settings-policy-gap'] = function () use ($wpsr_marker, $wpsr_as_user) {
        WpsrFactory::trackAbsentFixedOption('wpsr_global_settings');
        try {
            $wpsr_post_model = function ($wpsr_value) {
                return ['advance_settings' => ['has_gdpr' => 'false', 'selected_model' => $wpsr_value]];
            };
            $wpsr_stored_model = function () {
                $wpsr_option = get_option('wpsr_global_settings', false);
                return is_array($wpsr_option)
                    ? ($wpsr_option['global_settings']['advance_settings']['selected_model'] ?? null)
                    : null;
            };

            // Another screen's capability must not reach the advanced settings screen.
            $wpsr_other = WpsrFactory::limitedUser(['wpsn_translation_settings']);
            $wpsr_denied = $wpsr_as_user($wpsr_other, function () use ($wpsr_marker, $wpsr_post_model) {
                return WpsrTest::rest('POST', '/settings/advance-settings',
                    $wpsr_post_model($wpsr_marker . '-cross-screen-model'));
            });

            WpsrTest::assertSame(403, $wpsr_denied['status'], 'translation-only user is refused advanced settings');
            WpsrTest::assertSame(false, get_option('wpsr_global_settings', false),
                'refused advanced-settings write creates no global settings option');

            // That user's own screen still works, so the guard scopes rather than blocks.
            $wpsr_own = $wpsr_as_user($wpsr_other, function () {
                return WpsrTest::rest('GET', '/settings/translations', []);
            });
            WpsrTest::assertSame(200, $wpsr_own['status'], 'translation-only user still reaches translations');

            // Positive control: the capability owning the advanced settings screen.
            $wpsr_owner = WpsrFactory::limitedUser(['wpsn_feeds_advance_settings']);
            $wpsr_allowed = $wpsr_as_user($wpsr_owner, function () use ($wpsr_marker, $wpsr_post_model) {
                return WpsrTest::rest('POST', '/settings/advance-settings',
                    $wpsr_post_model($wpsr_marker . '-owned-model'));
            });

            WpsrTest::assertSame(200, $wpsr_allowed['status'], 'advanced-settings capability may save advanced settings');
            WpsrTest::assertSame($wpsr_marker . '-owned-model', $wpsr_stored_model(),
                'advanced-settings capability persists its own value');

            // Global Reviews Settings posts the same route, so its capability keeps access too.
            $wpsr_reviews = WpsrFactory::limitedUser(['wpsn_reviews_platforms_settings']);
            $wpsr_shared = $wpsr_as_user($wpsr_reviews, function () use ($wpsr_marker, $wpsr_post_model) {
                return WpsrTest::rest('POST', '/settings/advance-settings',
                    $wpsr_post_model($wpsr_marker . '-reviews-model'));
            });

            WpsrTest::assertSame(200, $wpsr_shared['status'], 'reviews-settings capability may save advanced settings');
            WpsrTest::assertSame($wpsr_marker . '-reviews-model', $wpsr_stored_model(),
                'reviews-settings capability persists its own value');

            // Generic platform routes pick their target from the request, so the
            // capability must follow the platform rather than the route.
            $wpsr_feeds = WpsrFactory::limitedUser(['wpsn_feeds_platforms_settings']);

            $wpsr_cross = $wpsr_as_user($wpsr_feeds, function () {
                return WpsrTest::rest('DELETE', '/settings', ['platform' => 'google', 'cacheType' => 'all']);
            });
            WpsrTest::assertSame(403, $wpsr_cross['status'], 'feeds-only user cannot clear a review platform cache');

            $wpsr_own = $wpsr_as_user($wpsr_feeds, function () {
                return WpsrTest::rest('DELETE', '/settings', ['platform' => 'instagram', 'cacheType' => 'all']);
            });
            WpsrTest::assertSame(false, $wpsr_own['status'] === 403, 'feeds-only user may still clear a feed platform cache');

            $wpsr_reverse = $wpsr_as_user($wpsr_reviews, function () {
                return WpsrTest::rest('DELETE', '/settings', ['platform' => 'instagram', 'cacheType' => 'all']);
            });
            WpsrTest::assertSame(403, $wpsr_reverse['status'], 'reviews-only user cannot clear a feed platform cache');

            $wpsr_unknown = $wpsr_as_user($wpsr_feeds, function () {
                return WpsrTest::rest('DELETE', '/settings', ['platform' => 'not-a-platform', 'cacheType' => 'all']);
            });
            WpsrTest::assertSame(403, $wpsr_unknown['status'], 'unknown platform is refused before a hook name is built');

            // 'reviews' is the aggregate over review platforms, so only that capability owns it.
            $wpsr_aggregate = $wpsr_as_user($wpsr_feeds, function () {
                return WpsrTest::rest('DELETE', '/settings/reset-images', ['platform' => 'reviews']);
            });
            WpsrTest::assertSame(403, $wpsr_aggregate['status'], 'feeds-only user cannot reset every review platform');

            $wpsr_aggregate_owner = $wpsr_as_user($wpsr_reviews, function () {
                return WpsrTest::rest('DELETE', '/settings/reset-images', ['platform' => 'reviews']);
            });
            WpsrTest::assertSame(false, $wpsr_aggregate_owner['status'] === 403,
                'reviews-only user may still reset every review platform');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['known-template-object-gap'] = function () use ($wpsr_as_user) {
        try {
            $wpsr_widget = WpsrFactory::post('wpsr_social_chats', ['post_title' => 'Cross-screen destructive widget']);
            $wpsr_before_post = get_post((int) $wpsr_widget->ID, ARRAY_A);
            $wpsr_before_meta = get_post_meta((int) $wpsr_widget->ID);
            $wpsr_user = WpsrFactory::limitedUser(['wpsn_manage_templates']);

            $wpsr_denied = $wpsr_as_user($wpsr_user, function () use ($wpsr_widget) {
                return WpsrTest::rest('DELETE', '/templates', ['ids' => [(int) $wpsr_widget->ID]]);
            });

            WpsrTest::assertSame(200, $wpsr_denied['status'], 'templates delete answers without server error');
            WpsrTest::assertSame(0, preg_match('/1 template/', wp_json_encode($wpsr_denied['data'])),
                'templates-only user is never told a chat widget was deleted');
            WpsrTest::assertSame($wpsr_before_post, get_post((int) $wpsr_widget->ID, ARRAY_A),
                'chat widget post survives a templates-only delete intact');
            WpsrTest::assertSame($wpsr_before_meta, get_post_meta((int) $wpsr_widget->ID),
                'chat widget postmeta survives a templates-only delete intact');

            // Positive control: the guard must not block the screen's own objects.
            $wpsr_template = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Owned template']);
            $wpsr_owned = $wpsr_as_user($wpsr_user, function () use ($wpsr_template) {
                return WpsrTest::rest('DELETE', '/templates', ['ids' => [(int) $wpsr_template->ID]]);
            });

            WpsrTest::assertSame(200, $wpsr_owned['status'], 'templates-only user may still delete a template');
            WpsrTest::assertSame(1, preg_match('/1 template/', wp_json_encode($wpsr_owned['data'])),
                'templates-only user is told exactly one template was deleted');
            WpsrTest::assertSame(null, get_post((int) $wpsr_template->ID),
                'owned template is gone after its own capability deletes it');

            // Reaching a chat widget through this screen needs both the route and object capability.
            $wpsr_both_user = WpsrFactory::limitedUser(['wpsn_manage_templates', 'wpsn_manage_chat_widgets']);
            $wpsr_allowed = $wpsr_as_user($wpsr_both_user, function () use ($wpsr_widget) {
                return WpsrTest::rest('DELETE', '/templates', ['ids' => [(int) $wpsr_widget->ID]]);
            });

            WpsrTest::assertSame(200, $wpsr_allowed['status'], 'holding both capabilities deletes the chat widget');
            WpsrTest::assertSame(null, get_post((int) $wpsr_widget->ID),
                'chat widget is gone once its owning capability is held too');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_implementations['known-shoppable-delete-gap'] = function () use ($wpsr_marker) {
        try {
            WpsrFactory::ownedOption('wpsr_global_shoppable_settings', [
                'instagram' => [['hashtags' => $wpsr_marker, 'url_settings' => ['url' => 'https://example.test/original']]],
                'facebook' => [],
            ]);
            // DELETE replaces the whole option from a client payload, so it needs the
            // same recursive sanitizer PUT applies.
            $wpsr_result = WpsrTest::rest('DELETE', '/shoppable', ['settings' => [
                'instagram' => [['hashtags' => '<b>' . $wpsr_marker . '</b>', 'url_settings' => ['url' => 'javascript:SELECT(1)']]],
                'facebook' => [],
            ]]);
            $wpsr_stored = get_option('wpsr_global_shoppable_settings');

            WpsrTest::assertSame(200, $wpsr_result['status'], 'shoppable delete answers without error');
            WpsrTest::assertSame($wpsr_marker,
                (string) ($wpsr_stored['instagram'][0]['hashtags'] ?? ''),
                'delete strips markup from stored hashtags');
            WpsrTest::assertSame('',
                (string) ($wpsr_stored['instagram'][0]['url_settings']['url'] ?? ''),
                'delete rejects a javascript: URL rather than persisting it');

            // Positive control: a legitimate replacement still round-trips intact.
            $wpsr_clean = WpsrTest::rest('DELETE', '/shoppable', ['settings' => [
                'instagram' => [['hashtags' => $wpsr_marker, 'url_settings' => ['url' => 'https://example.test/kept']]],
                'facebook' => [],
            ]]);
            $wpsr_stored = get_option('wpsr_global_shoppable_settings');

            WpsrTest::assertSame(200, $wpsr_clean['status'], 'clean shoppable delete answers without error');
            WpsrTest::assertSame($wpsr_marker, (string) ($wpsr_stored['instagram'][0]['hashtags'] ?? ''),
                'clean hashtags survive the sanitizer unchanged');
            WpsrTest::assertSame('https://example.test/kept',
                (string) ($wpsr_stored['instagram'][0]['url_settings']['url'] ?? ''),
                'a valid URL survives the sanitizer unchanged');
            WpsrTest::assertSame([], $wpsr_stored['facebook'] ?? null, 'emptied platform stays emptied');
        } finally {
            WpsrFactory::cleanup();
        }
    };

    $wpsr_registry = require dirname(__DIR__) . '/validation/cases.manifest.php';
    WpsrTest::assertSame(array_keys($wpsr_registry), array_keys($wpsr_implementations), 'Phase 10 runtime registry has exact implementation coverage');
    if (!$wpsr_implementations) {
        WpsrTest::case('Phase 10 runtime discovery', function () {
            WpsrTest::fail('Phase 10 discovered zero behavior cases.');
        });
        return;
    }

    foreach ($wpsr_implementations as $wpsr_case_id => $wpsr_callback) {
        WpsrTest::case('Phase 10 ' . $wpsr_case_id, $wpsr_callback);
    }
};
