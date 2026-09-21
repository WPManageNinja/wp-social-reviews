<?php
/**
 * Exact-ownership factories for WP Social Ninja integration tests.
 *
 * Every fixture embeds one collision-resistant marker. Recorded primary keys are
 * verified before deletion; marker sweeps cover rows created before tracking.
 */

use WPSocialReviews\App\Models\Cache;
use WPSocialReviews\App\Models\OptimizeImage;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Models\ReviewCategoryRelationship;

class WpsrFactory
{
    private static $marker = null;
    private static $counters = [];
    private static $reviewIds = [];
    private static $postIds = [];
    private static $categoryIds = [];
    private static $relationshipIds = [];
    private static $cacheIds = [];
    private static $optimizeImageIds = [];
    private static $optionNames = [];
    private static $cronHooks = [];
    private static $userIds = [];
    private static $roleNames = [];

    public static function marker()
    {
        if (self::$marker === null) {
            try {
                $random = bin2hex(random_bytes(8));
            } catch (Throwable $exception) {
                $random = str_replace('.', '', uniqid('', true));
            }

            self::$marker = 'wpsr-it-' . getmypid() . '-' . $random;
        }

        return self::$marker;
    }

    public static function review(array $attributes = [])
    {
        $suffix = self::nextSuffix('review');
        $marker = self::marker();
        $reviewId = $marker . '-review-' . $suffix;
        $fields = isset($attributes['fields']) && is_array($attributes['fields'])
            ? $attributes['fields']
            : [];
        $fields['_wpsr_test_marker'] = $marker;

        $defaults = [
            'platform_name'       => 'custom',
            'source_id'           => $marker . '-source',
            'review_id'           => $reviewId,
            'review_title'        => 'Integration review ' . $suffix,
            'reviewer_name'       => 'Integration Reviewer ' . $suffix,
            'reviewer_email'      => $marker . '-' . $suffix . '@example.test',
            'reviewer_text'       => 'Integration fixture body ' . $suffix,
            'review_time'         => '2026-01-01 00:00:00',
            'rating'              => 5,
            'review_approved'     => 1,
            'recommendation_type' => 'positive',
            'fields'              => $fields,
        ];
        $data = array_merge($defaults, $attributes);

        // Ownership identifiers cannot be replaced by a test's business payload.
        $data['review_id'] = $reviewId;
        $data['fields'] = $fields;

        global $wpdb;
        $wpdb->last_error = '';
        $review = Review::create($data);
        WpsrTest::assertNoDatabaseError('review fixture insert');

        if (!$review || empty($review->id)) {
            throw new RuntimeException('Review factory did not persist a primary key.');
        }

        self::$reviewIds[(int) $review->id] = [
            'review_id' => (string) $review->review_id,
            'platform_name' => (string) $review->platform_name,
            'source_id' => (string) $review->source_id,
            'review_type' => (string) $review->review_type,
        ];
        return $review;
    }

    public static function trackReview($review)
    {
        if (!$review || empty($review->id) || empty($review->review_id)) {
            throw new RuntimeException('Cannot track route-created review without primary and review IDs.');
        }
        if (strpos((string) $review->review_id, self::marker()) !== 0) {
            throw new RuntimeException('Route-created review ID does not carry factory ownership marker.');
        }

        self::$reviewIds[(int) $review->id] = [
            'review_id' => (string) $review->review_id,
            'platform_name' => (string) $review->platform_name,
            'source_id' => (string) $review->source_id,
            'review_type' => (string) $review->review_type,
        ];
        return $review;
    }

    public static function post($postType, array $attributes = [])
    {
        if (!$postType) {
            throw new InvalidArgumentException('Post fixture requires a post type.');
        }

        $suffix = self::nextSuffix('post');
        $marker = self::marker();
        $titleLabel = isset($attributes['post_title']) ? (string) $attributes['post_title'] : 'shared-table';
        $contentLabel = isset($attributes['post_content']) ? (string) $attributes['post_content'] : 'shared-table';
        $data = array_merge([
            'post_author'  => get_current_user_id(),
            'post_status'  => 'publish',
            'post_type'    => $postType,
            'post_title'   => $marker . ' post ' . $suffix . ' ' . $titleLabel,
            'post_content' => $marker . ' content ' . $suffix . ' ' . $contentLabel,
        ], $attributes);

        // Search marker and discriminator are fixture ownership, not business payload.
        $data['post_type'] = $postType;
        $data['post_title'] = $marker . ' post ' . $suffix . ' ' . $titleLabel;
        $data['post_content'] = $marker . ' content ' . $suffix . ' ' . $contentLabel;

        $postId = wp_insert_post($data, true);
        if (is_wp_error($postId) || !$postId) {
            $message = is_wp_error($postId) ? $postId->get_error_message() : 'missing primary key';
            throw new RuntimeException('Post factory failed: ' . $message);
        }

        $postId = (int) $postId;
        self::$postIds[$postId] = [
            'post_type'    => $postType,
            'post_title'   => $data['post_title'],
            'post_content' => $data['post_content'],
            'post_author'  => (int) $data['post_author'],
        ];
        update_post_meta($postId, '_wpsr_test_marker', $marker);

        return get_post($postId);
    }

    public static function trackPost($postId, $expectedType, $expectedTitle, $expectedContent)
    {
        $postId = (int) $postId;
        $post = $postId ? get_post($postId) : null;
        if (!$post) {
            throw new RuntimeException('Cannot track missing route-created post.');
        }
        if ($post->post_type !== $expectedType
            || $post->post_title !== $expectedTitle
            || $post->post_content !== $expectedContent
            || (int) $post->post_author !== (int) get_current_user_id()) {
            throw new RuntimeException('Route-created post does not match exact expected ownership fields.');
        }

        $existingMarker = get_post_meta($postId, '_wpsr_test_marker', true);
        if ($existingMarker !== '' && $existingMarker !== self::marker()) {
            throw new RuntimeException('Route-created post has a foreign ownership marker.');
        }
        update_post_meta($postId, '_wpsr_test_marker', self::marker());
        self::$postIds[$postId] = [
            'post_type'    => $expectedType,
            'post_title'   => $expectedTitle,
            'post_content' => $expectedContent,
            'post_author'  => (int) $post->post_author,
        ];

        return get_post($postId);
    }

    public static function refreshTrackedPost($postId)
    {
        $postId = (int) $postId;
        if (!isset(self::$postIds[$postId])) {
            throw new RuntimeException('Cannot refresh untracked post ownership.');
        }
        $post = get_post($postId);
        if (!$post || $post->post_type !== self::$postIds[$postId]['post_type']
            || (int) $post->post_author !== self::$postIds[$postId]['post_author']
            || get_post_meta($postId, '_wpsr_test_marker', true) !== self::marker()) {
            throw new RuntimeException('Tracked post ownership changed before refresh.');
        }

        self::$postIds[$postId]['post_title'] = $post->post_title;
        self::$postIds[$postId]['post_content'] = $post->post_content;
        return $post;
    }

    public static function category(array $attributes = [])
    {
        $suffix = self::nextSuffix('category');
        $marker = self::marker();
        $nameLabel = isset($attributes['name']) ? (string) $attributes['name'] : 'Category ' . $suffix;
        $name = $marker . ' ' . $nameLabel;
        $slug = sanitize_title($marker . '-category-' . $suffix);

        $category = ReviewCategory::create([
            'name' => $name,
            'slug' => $slug,
        ]);
        WpsrTest::assertNoDatabaseError('category fixture insert');

        if (!$category || empty($category->id)) {
            throw new RuntimeException('Category factory did not persist a primary key.');
        }

        self::$categoryIds[(int) $category->id] = ['slug' => $slug, 'name' => $name];
        return $category;
    }

    public static function trackCategory($category)
    {
        if (!$category || empty($category->id) || empty($category->slug)) {
            throw new RuntimeException('Cannot track route-created category without primary key and slug.');
        }
        if (strpos((string) $category->name, self::marker()) !== 0
            || strpos((string) $category->slug, sanitize_title(self::marker())) !== 0) {
            throw new RuntimeException('Route-created category does not carry factory ownership marker.');
        }

        self::$categoryIds[(int) $category->id] = [
            'slug' => (string) $category->slug,
            'name' => (string) $category->name,
        ];
        return $category;
    }

    public static function refreshTrackedCategory($categoryId)
    {
        $categoryId = (int) $categoryId;
        if (!isset(self::$categoryIds[$categoryId])) {
            throw new RuntimeException('Cannot refresh untracked category ownership.');
        }
        $category = ReviewCategory::find($categoryId);
        if (!$category || strpos((string) $category->name, self::marker()) !== 0
            || strpos((string) $category->slug, sanitize_title(self::marker())) !== 0) {
            throw new RuntimeException('Tracked category ownership changed before refresh.');
        }

        self::$categoryIds[$categoryId] = [
            'slug' => (string) $category->slug,
            'name' => (string) $category->name,
        ];
        return $category;
    }

    public static function relationship($reviewId, $categoryId)
    {
        $reviewId = (int) $reviewId;
        $categoryId = (int) $categoryId;
        if (!$reviewId || !$categoryId) {
            throw new InvalidArgumentException('Relationship fixture requires review and category IDs.');
        }
        if (!isset(self::$reviewIds[$reviewId]) || !isset(self::$categoryIds[$categoryId])) {
            throw new InvalidArgumentException('Relationship fixture requires factory-owned review and category IDs.');
        }

        ReviewCategoryRelationship::query()->insertOrIgnore([
            ['review_id' => $reviewId, 'category_id' => $categoryId],
        ]);
        WpsrTest::assertNoDatabaseError('relationship fixture insert');

        $relationship = ReviewCategoryRelationship::where('review_id', $reviewId)
            ->where('category_id', $categoryId)
            ->first();
        WpsrTest::assertNoDatabaseError('relationship fixture ownership lookup');
        if (!$relationship || empty($relationship->id)) {
            throw new RuntimeException('Relationship factory did not persist a primary key.');
        }

        self::$relationshipIds[(int) $relationship->id] = [
            'review_id'   => $reviewId,
            'category_id' => $categoryId,
        ];

        return $relationship;
    }

    public static function cache($platform, $nameKey, array $value, array $attributes = [])
    {
        if (!$platform || !$nameKey) {
            throw new InvalidArgumentException('Cache fixture requires platform and name key.');
        }

        $name = self::marker() . '-cache-' . sanitize_key($nameKey);
        $cache = Cache::create(array_merge([
            'platform'   => $platform,
            'name'       => $name,
            'value'      => wp_json_encode($value),
            'expiration' => '2025-01-01 00:00:00',
        ], $attributes, [
            'platform' => $platform,
            'name'     => $name,
        ]));
        WpsrTest::assertNoDatabaseError('cache fixture insert');

        if (!$cache || empty($cache->id)) {
            throw new RuntimeException('Cache factory did not persist a primary key.');
        }

        self::$cacheIds[(int) $cache->id] = ['platform' => $platform, 'name' => $name];
        return $cache;
    }

    public static function cacheExact($platform, $name, $value, array $attributes = [])
    {
        if (!$platform || !$name || strpos((string) $name, self::marker()) === false) {
            throw new InvalidArgumentException('Exact cache fixture requires platform and marker-owned name.');
        }

        $cache = Cache::create(array_merge([
            'platform'   => $platform,
            'name'       => (string) $name,
            'value'      => wp_json_encode($value),
            'expiration' => '2025-01-01 00:00:00',
        ], $attributes, [
            'platform' => $platform,
            'name'     => (string) $name,
        ]));
        WpsrTest::assertNoDatabaseError('exact cache fixture insert');

        if (!$cache || empty($cache->id)) {
            throw new RuntimeException('Exact cache factory did not persist a primary key.');
        }

        self::$cacheIds[(int) $cache->id] = ['platform' => $platform, 'name' => (string) $name];
        return $cache;
    }

    public static function optimizeImage($platform, array $attributes = [])
    {
        if (!$platform) {
            throw new InvalidArgumentException('Optimize-image fixture requires a platform.');
        }

        $suffix = self::nextSuffix('optimize-image');
        $identity = isset($attributes['_identity']) ? sanitize_key($attributes['_identity']) : (string) $suffix;
        unset($attributes['_identity']);
        $mediaId = self::marker() . '-media-' . $identity;
        $userName = self::marker() . '-user-' . $identity;
        $data = array_merge([
            'platform'       => $platform,
            'user_id'        => self::marker() . '-uid-' . $identity,
            'user_name'      => $userName,
            'media_id'       => $mediaId,
            'json_data'      => wp_json_encode(['_wpsr_test_marker' => self::marker()]),
            'images_resized' => 1,
            'last_requested' => '2026-01-01 00:00:00',
        ], $attributes, [
            'platform'  => $platform,
            'user_name' => $userName,
            'media_id'  => $mediaId,
        ]);

        $image = OptimizeImage::create($data);
        WpsrTest::assertNoDatabaseError('optimize-image fixture insert');
        self::trackOptimizeImage($image);

        return $image;
    }

    public static function trackOptimizeImage($image)
    {
        if (!$image || empty($image->id) || empty($image->media_id)
            || empty($image->user_name) || empty($image->platform)) {
            throw new RuntimeException('Cannot track optimize-image row without platform and ownership identifiers.');
        }
        if (strpos((string) $image->media_id, self::marker()) !== 0
            || strpos((string) $image->user_name, self::marker()) !== 0) {
            throw new RuntimeException('Cannot track optimize-image row without marker-owned identity.');
        }

        self::$optimizeImageIds[(int) $image->id] = [
            'platform'  => (string) $image->platform,
            'media_id'  => (string) $image->media_id,
            'user_name' => (string) $image->user_name,
        ];
    }

    public static function option($name, $value)
    {
        $name = (string) $name;
        if ($name === '' || strpos($name, self::marker()) === false) {
            throw new InvalidArgumentException('Option fixture name must contain the integration marker.');
        }

        global $wpdb;
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
            $name
        ));
        WpsrTest::assertNoDatabaseError('option fixture ownership lookup');
        if ($exists !== 0) {
            throw new RuntimeException('Refusing to replace pre-existing option fixture ' . $name . '.');
        }

        if (!add_option($name, $value, '', 'no')) {
            throw new RuntimeException('Option factory could not create ' . $name . '.');
        }

        self::$optionNames[$name] = ['ownership' => 'name'];
        return $name;
    }

    public static function trackOptionName($name)
    {
        $name = (string) $name;
        if ($name === '' || strpos($name, self::marker()) === false) {
            throw new InvalidArgumentException('Tracked option name must contain the integration marker.');
        }
        if (get_option($name, null) !== null) {
            throw new RuntimeException('Refusing to track pre-existing option ' . $name . '.');
        }

        self::$optionNames[$name] = ['ownership' => 'name'];
        return $name;
    }

    /**
     * Own a fixed production option only when absent. Route under test creates it;
     * cleanup may then delete that exact name even when production formatting drops
     * arbitrary marker keys from the value.
     */
    public static function trackAbsentFixedOption($name)
    {
        $name = (string) $name;
        if ($name === '') {
            throw new InvalidArgumentException('Fixed option name cannot be empty.');
        }

        global $wpdb;
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
            $name
        ));
        WpsrTest::assertNoDatabaseError('fixed option absence ownership lookup');
        if ($exists !== 0) {
            throw new RuntimeException('Refusing to own pre-existing fixed option ' . $name . '.');
        }

        self::$optionNames[$name] = ['ownership' => 'absent-fixed'];
        return $name;
    }

    public static function ownedOption($name, $value)
    {
        $name = (string) $name;
        $encoded = wp_json_encode($value);
        if ($name === '' || $encoded === false || strpos($encoded, self::marker()) === false) {
            throw new InvalidArgumentException('Fixed-name option value must contain the integration marker.');
        }

        global $wpdb;
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
            $name
        ));
        WpsrTest::assertNoDatabaseError('fixed-name option ownership lookup');
        if ($exists !== 0) {
            throw new RuntimeException('Refusing to replace pre-existing fixed-name option ' . $name . '.');
        }
        if (!add_option($name, $value, '', 'no')) {
            throw new RuntimeException('Fixed-name option factory could not create ' . $name . '.');
        }

        self::$optionNames[$name] = ['ownership' => 'value'];
        return $name;
    }

    public static function trackCronHook($hook)
    {
        $hook = (string) $hook;
        if ($hook === '' || strpos($hook, self::marker()) === false) {
            throw new InvalidArgumentException('Cron fixture hook must contain the integration marker.');
        }
        if (wp_next_scheduled($hook) !== false) {
            throw new RuntimeException('Refusing to own pre-existing cron hook ' . $hook . '.');
        }

        self::$cronHooks[$hook] = true;
        return $hook;
    }

    /**
     * Create a non-administrator user with only read plus requested plugin caps.
     */
    public static function limitedUser(array $capabilities)
    {
        $suffix = self::nextSuffix('user');
        $roleName = sanitize_key(self::marker() . '-role-' . $suffix);
        $username = sanitize_user(self::marker() . '-user-' . $suffix, true);
        $email = $username . '@example.test';
        $roleCaps = ['read' => true];

        foreach ($capabilities as $capability) {
            if (!is_string($capability) || strpos($capability, 'wpsn_') !== 0) {
                throw new InvalidArgumentException('Limited user capabilities must be explicit wpsn_* strings.');
            }
            $roleCaps[$capability] = true;
        }

        if (get_role($roleName)) {
            throw new RuntimeException('Refusing to replace pre-existing role ' . $roleName . '.');
        }
        $role = add_role($roleName, 'WPSR integration limited role', $roleCaps);
        if (!$role) {
            throw new RuntimeException('Could not create limited integration role.');
        }
        self::$roleNames[$roleName] = true;

        $userId = wp_create_user($username, wp_generate_password(24, true, true), $email);
        if (is_wp_error($userId) || !$userId) {
            throw new RuntimeException('Could not create limited integration user.');
        }
        $user = get_user_by('ID', (int) $userId);
        $user->set_role($roleName);
        self::$userIds[(int) $userId] = [
            'username' => $username,
            'email' => $email,
            'role' => $roleName,
        ];

        return $user;
    }

    public static function cleanup()
    {
        $errors = [];

        self::cleanupUsersAndRoles($errors);
        self::cleanupRelationships($errors);
        self::cleanupReviews($errors);
        self::cleanupPosts($errors);
        self::cleanupCategories($errors);
        self::cleanupCaches($errors);
        self::cleanupOptimizeImages($errors);
        self::cleanupOptions($errors);
        self::cleanupCronHooks($errors);
        WpsrTest::restoreOwnedPostCronSideEffect();

        if ($errors) {
            throw new RuntimeException(implode(' ', array_unique($errors)));
        }
    }

    public static function trackedReviewIds()
    {
        return array_map('intval', array_keys(self::$reviewIds));
    }

    private static function cleanupRelationships(array &$errors)
    {
        try {
            foreach (self::$relationshipIds as $relationshipId => $expected) {
                $relationship = ReviewCategoryRelationship::find((int) $relationshipId);
                if (!$relationship) {
                    unset(self::$relationshipIds[$relationshipId]);
                    continue;
                }
                if ((int) $relationship->review_id !== $expected['review_id']
                    || (int) $relationship->category_id !== $expected['category_id']) {
                    $errors[] = 'Refusing to delete unowned relationship ID ' . (int) $relationshipId . '.';
                    continue;
                }
                $relationship->delete();
                WpsrTest::assertNoDatabaseError('relationship fixture cleanup');
                unset(self::$relationshipIds[$relationshipId]);
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    private static function cleanupReviews(array &$errors)
    {
        $refused = [];
        try {
            foreach (self::$reviewIds as $id => $expected) {
                $review = Review::find((int) $id);
                WpsrTest::assertNoDatabaseError('review fixture ownership lookup');
                if (!$review) {
                    unset(self::$reviewIds[$id]);
                    continue;
                }
                if ($expected['review_id'] === ''
                    || (string) $review->review_id !== $expected['review_id']
                    || (string) $review->platform_name !== $expected['platform_name']
                    || (string) $review->source_id !== $expected['source_id']
                    || (string) $review->review_type !== $expected['review_type']) {
                    $refused[(int) $id] = true;
                    $errors[] = 'Refusing to delete unowned review ID ' . (int) $id . '.';
                    continue;
                }
                ReviewCategoryRelationship::deleteForReviews([(int) $id]);
                Review::where('id', (int) $id)->delete();
                WpsrTest::assertNoDatabaseError('review fixture cleanup');
                unset(self::$reviewIds[$id]);
            }

            $orphans = Review::where('review_id', 'like', self::marker() . '-review-%')->get();
            WpsrTest::assertNoDatabaseError('review fixture fallback lookup');
            foreach ($orphans as $review) {
                $id = (int) $review->id;
                if (isset($refused[$id]) || isset(self::$reviewIds[$id])
                    || strpos((string) $review->review_id, self::marker() . '-review-') !== 0) {
                    continue;
                }
                ReviewCategoryRelationship::deleteForReviews([$id]);
                Review::where('id', $id)->delete();
                WpsrTest::assertNoDatabaseError('review fixture fallback cleanup');
            }

            $remaining = Review::where('review_id', 'like', self::marker() . '-review-%')->count();
            WpsrTest::assertNoDatabaseError('review fixture leak check');
            if ((int) $remaining !== 0) {
                $errors[] = 'Review fixture cleanup left ' . (int) $remaining . ' marked/refused row(s).';
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    private static function cleanupPosts(array &$errors)
    {
        global $wpdb;
        $refused = [];
        try {
            foreach (self::$postIds as $postId => $expected) {
                $post = get_post($postId);
                if (!$post) {
                    unset(self::$postIds[$postId]);
                    continue;
                }
                $owned = $post->post_type === $expected['post_type']
                    && $post->post_title === $expected['post_title']
                    && $post->post_content === $expected['post_content']
                    && (int) $post->post_author === (int) $expected['post_author']
                    && get_post_meta($postId, '_wpsr_test_marker', true) === self::marker();
                if (!$owned) {
                    $refused[(int) $postId] = true;
                    $errors[] = 'Refusing to delete unowned post ID ' . (int) $postId . '.';
                    continue;
                }
                if (!wp_delete_post($postId, true)) {
                    throw new RuntimeException('Owned post deletion failed for ID ' . (int) $postId . '.');
                }
                unset(self::$postIds[$postId]);
            }

            $pattern = $wpdb->esc_like(self::marker() . ' post ') . '%';
            $markedIds = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s ORDER BY ID ASC",
                $pattern
            ));
            WpsrTest::assertNoDatabaseError('post fixture fallback lookup');
            foreach ($markedIds as $postId) {
                $postId = (int) $postId;
                $post = get_post($postId);
                if (isset($refused[$postId]) || isset(self::$postIds[$postId]) || !$post
                    || strpos((string) $post->post_title, self::marker() . ' post ') !== 0
                    || strpos((string) $post->post_content, self::marker() . ' content ') !== 0
                    || get_post_meta($postId, '_wpsr_test_marker', true) !== self::marker()) {
                    continue;
                }
                if (!wp_delete_post($postId, true)) {
                    throw new RuntimeException('Fallback owned post deletion failed for ID ' . $postId . '.');
                }
            }

            $remaining = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE %s",
                $pattern
            ));
            WpsrTest::assertNoDatabaseError('post fixture leak check');
            if ($remaining !== 0) {
                $errors[] = 'Post fixture cleanup left ' . $remaining . ' marked/refused row(s).';
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    private static function cleanupCategories(array &$errors)
    {
        $refused = [];
        try {
            foreach (self::$categoryIds as $categoryId => $expected) {
                $category = ReviewCategory::find((int) $categoryId);
                if (!$category) {
                    unset(self::$categoryIds[$categoryId]);
                    continue;
                }
                if ($category->slug !== $expected['slug'] || $category->name !== $expected['name']) {
                    $refused[(int) $categoryId] = true;
                    $errors[] = 'Refusing to delete unowned category ID ' . (int) $categoryId . '.';
                    continue;
                }
                $category->delete();
                WpsrTest::assertNoDatabaseError('category fixture cleanup');
                unset(self::$categoryIds[$categoryId]);
            }

            $orphans = ReviewCategory::where('slug', 'like', sanitize_title(self::marker()) . '-category-%')->get();
            WpsrTest::assertNoDatabaseError('category fixture fallback lookup');
            foreach ($orphans as $category) {
                $id = (int) $category->id;
                if (isset($refused[$id]) || isset(self::$categoryIds[$id])
                    || strpos((string) $category->slug, sanitize_title(self::marker()) . '-category-') !== 0
                    || strpos((string) $category->name, self::marker() . ' ') !== 0) {
                    continue;
                }
                $category->delete();
                WpsrTest::assertNoDatabaseError('category fixture fallback cleanup');
            }

            $remaining = ReviewCategory::where('slug', 'like', sanitize_title(self::marker()) . '-category-%')->count();
            WpsrTest::assertNoDatabaseError('category fixture leak check');
            if ((int) $remaining !== 0) {
                $errors[] = 'Category fixture cleanup left ' . (int) $remaining . ' marked/refused row(s).';
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    private static function cleanupCaches(array &$errors)
    {
        $refused = [];
        try {
            foreach (self::$cacheIds as $cacheId => $expected) {
                $cache = Cache::find((int) $cacheId);
                if (!$cache) {
                    unset(self::$cacheIds[$cacheId]);
                    continue;
                }
                if ($cache->platform !== $expected['platform'] || $cache->name !== $expected['name']) {
                    $refused[(int) $cacheId] = true;
                    $errors[] = 'Refusing to delete unowned cache ID ' . (int) $cacheId . '.';
                    continue;
                }
                $cache->delete();
                WpsrTest::assertNoDatabaseError('cache fixture cleanup');
                unset(self::$cacheIds[$cacheId]);
            }

            $orphans = Cache::where('name', 'like', self::marker() . '-cache-%')->get();
            WpsrTest::assertNoDatabaseError('cache fixture fallback lookup');
            foreach ($orphans as $cache) {
                $id = (int) $cache->id;
                if (isset($refused[$id]) || isset(self::$cacheIds[$id])
                    || strpos((string) $cache->name, self::marker() . '-cache-') !== 0) {
                    continue;
                }
                $cache->delete();
                WpsrTest::assertNoDatabaseError('cache fixture fallback cleanup');
            }
            $remaining = Cache::where('name', 'like', self::marker() . '-cache-%')->count();
            if ((int) $remaining !== 0) {
                $errors[] = 'Cache fixture cleanup left ' . (int) $remaining . ' marked/refused row(s).';
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    private static function cleanupOptimizeImages(array &$errors)
    {
        $refused = [];
        try {
            foreach (self::$optimizeImageIds as $imageId => $expected) {
                $image = OptimizeImage::find((int) $imageId);
                if (!$image) {
                    unset(self::$optimizeImageIds[$imageId]);
                    continue;
                }
                if ($image->platform !== $expected['platform']
                    || $image->media_id !== $expected['media_id']
                    || $image->user_name !== $expected['user_name']) {
                    $refused[(int) $imageId] = true;
                    $errors[] = 'Refusing to delete unowned optimize-image ID ' . (int) $imageId . '.';
                    continue;
                }
                $image->delete();
                WpsrTest::assertNoDatabaseError('optimize-image fixture cleanup');
                unset(self::$optimizeImageIds[$imageId]);
            }

            $orphans = OptimizeImage::where('media_id', 'like', self::marker() . '-media-%')->get();
            WpsrTest::assertNoDatabaseError('optimize-image fixture fallback lookup');
            foreach ($orphans as $image) {
                $id = (int) $image->id;
                if (isset($refused[$id]) || isset(self::$optimizeImageIds[$id])
                    || strpos((string) $image->media_id, self::marker() . '-media-') !== 0
                    || strpos((string) $image->user_name, self::marker() . '-user-') !== 0) {
                    continue;
                }
                $image->delete();
                WpsrTest::assertNoDatabaseError('optimize-image fixture fallback cleanup');
            }
            $remaining = OptimizeImage::where('media_id', 'like', self::marker() . '-media-%')->count();
            if ((int) $remaining !== 0) {
                $errors[] = 'Optimize-image fixture cleanup left ' . (int) $remaining . ' marked/refused row(s).';
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    private static function cleanupOptions(array &$errors)
    {
        global $wpdb;

        try {
            foreach (self::$optionNames as $name => $metadata) {
                $ownership = isset($metadata['ownership']) ? $metadata['ownership'] : '';
                if ($ownership === 'name' && strpos($name, self::marker()) === false) {
                    $errors[] = 'Refusing to delete unowned option ' . $name . '.';
                    continue;
                }
                if ($ownership === 'value') {
                    $current = wp_json_encode(get_option($name, null));
                    if ($current === false || strpos($current, self::marker()) === false) {
                        $errors[] = 'Refusing to delete fixed-name option whose marker ownership changed: ' . $name . '.';
                        continue;
                    }
                }
                if (!in_array($ownership, ['name', 'value', 'absent-fixed'], true)) {
                    $errors[] = 'Refusing option deletion with invalid ownership mode: ' . $name . '.';
                    continue;
                }
                delete_option($name);
                unset(self::$optionNames[$name]);
            }

            $pattern = '%' . $wpdb->esc_like(self::marker()) . '%';
            $remaining = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_value LIKE %s",
                $pattern,
                $pattern
            ));
            WpsrTest::assertNoDatabaseError('option fixture leak check');
            if ($remaining !== 0) {
                $errors[] = 'Option fixture cleanup left ' . $remaining . ' marked row(s).';
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    private static function cleanupCronHooks(array &$errors)
    {
        try {
            foreach (array_keys(self::$cronHooks) as $hook) {
                if (strpos($hook, self::marker()) === false) {
                    $errors[] = 'Refusing to clear unowned cron hook ' . $hook . '.';
                    continue;
                }
                wp_clear_scheduled_hook($hook);
                unset(self::$cronHooks[$hook]);
            }

            $remaining = 0;
            foreach ((array) _get_cron_array() as $timestamp => $hooks) {
                if ($timestamp === 'version' || !is_array($hooks)) {
                    continue;
                }
                foreach (array_keys($hooks) as $hook) {
                    if (strpos((string) $hook, self::marker()) !== false) {
                        $remaining++;
                    }
                }
            }
            if ($remaining !== 0) {
                $errors[] = 'Cron fixture cleanup left ' . $remaining . ' marked hook(s).';
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    private static function cleanupUsersAndRoles(array &$errors)
    {
        try {
            foreach (self::$userIds as $userId => $expected) {
                $user = get_user_by('ID', (int) $userId);
                if (!$user) {
                    unset(self::$userIds[$userId]);
                    continue;
                }
                if ($user->user_login !== $expected['username']
                    || $user->user_email !== $expected['email']
                    || !in_array($expected['role'], (array) $user->roles, true)
                    || strpos($expected['username'], sanitize_user(self::marker(), true)) !== 0) {
                    $errors[] = 'Refusing to delete unowned user ID ' . (int) $userId . '.';
                    continue;
                }
                if (!function_exists('wp_delete_user')) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                }
                if (!wp_delete_user((int) $userId)) {
                    throw new RuntimeException('Owned user deletion failed for ID ' . (int) $userId . '.');
                }
                unset(self::$userIds[$userId]);
            }

            foreach (array_keys(self::$roleNames) as $roleName) {
                if (strpos($roleName, sanitize_key(self::marker())) !== 0) {
                    $errors[] = 'Refusing to remove unowned role ' . $roleName . '.';
                    continue;
                }
                remove_role($roleName);
                unset(self::$roleNames[$roleName]);
            }
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    private static function nextSuffix($type)
    {
        if (!isset(self::$counters[$type])) {
            self::$counters[$type] = 0;
        }

        self::$counters[$type]++;
        return self::$counters[$type];
    }
}
