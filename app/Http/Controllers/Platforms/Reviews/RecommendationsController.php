<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Reviews;

use Closure;
use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Models\ReviewCategoryRelationship;
use WPSocialReviews\Framework\Http\Request\Request;
use WPSocialReviews\App\Http\Requests\ReviewRequest;
use WPSocialReviews\Framework\Support\Arr;

class RecommendationsController extends Controller
{
    // Keys inside `fields` that hold a list. WooCommerce and FluentCart store
    // product_thumbnail as the array wp_get_attachment_image_src() returns.
    private const LIST_FIELD_KEYS = ['fields.review_images', 'fields.product_thumbnail'];

    // Columns that map to a single DB value, so a list on one is malformed.
    private const SCALAR_COLUMNS = [
        'rating', 'review_approved', 'review_time', 'reviewer_name',
        'reviewer_text', 'reviewer_email', 'reviewer_img', 'platform_name',
        'review_title', 'reviewer_url', 'category', 'source_id',
        'review_id', 'recommendation_type',
    ];

    // Scalar keys inside `fields` that must be a full http(s) URL when filled.
    private const URL_FIELD_LABELS = [
        'video_url'           => 'video URL',
        'video_poster'        => 'video thumbnail URL',
        'author_website_url'  => 'author website URL',
        'author_website_logo' => 'author website logo URL',
    ];

    // Top-level columns under the same rule. Their sanitizer turns an unusable value into
    // '', so without this a scheme typo saves as "successfully updated" with the field
    // silently emptied — the same input the fields above answer with a 422.
    private const URL_COLUMN_LABELS = [
        'reviewer_url' => 'author URL',
        'reviewer_img' => 'author image URL',
    ];

    /**
     * Memo for getNativeFormCustomSourceIds(). A property, not a function static, so it
     * can be reset: the filter behind it comes from Pro.
     *
     * @var array|null
     */
    protected static $nativeFormCustomSourceIds = null;

    public function index(Request $request)
    {
        return $this->applyIndex($request, 'review');
    }

    public function indexTestimonial(Request $request)
    {
        return $this->applyIndex($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial'
     */
    protected function applyIndex(Request $request, $scope)
    {
        $valid_platforms = apply_filters('wpsocialreviews/available_valid_reviews_platforms', []);
        $customValidPlatforms = get_option('wpsr_available_valid_platforms', []);

        $type = sanitize_text_field(wp_unslash((string) $request->get('type', '')));

        if ($scope === 'testimonial') {
            $type = 'testimonial';
        } elseif ($type !== 'custom_review') {
            $type = 'review';
        }

        $sourceId = sanitize_text_field(wp_unslash((string) $request->get('source_id', '')));

        $search  = sanitize_text_field(wp_unslash((string) $request->get('search', '')));
        $filterRaw = wp_unslash((string) $request->get('filter', ''));
        $filter  = $filterRaw === 'all' ? '' : sanitize_text_field($filterRaw);
        // order_by is a sort direction. orderBy() rejects anything but asc/desc.
        $order_by_raw = strtolower(wp_unslash((string) $request->get('order_by', '')));
        $orderBy = in_array($order_by_raw, ['asc', 'desc'], true) ? $order_by_raw : 'desc';

        $statusFilterRaw = wp_unslash((string) $request->get('status_filter', 'all'));
        $statusFilter = sanitize_text_field($statusFilterRaw);

        if($type === 'testimonial') {
            $filter = $type;
        }

        if($type !== 'custom_review'){
            $reviews = Review::searchBy($search);

            if (!empty($filter)) {
                $reviews = $reviews->where('platform_name', $filter);
            }
        } else {
            $reviews = Review::searchBy($search);
        }

        $hasCustomReview = Review::where('platform_name', 'custom')->count();

        if ($hasCustomReview) {
            $valid_platforms['custom'] = __('Custom', 'wp-social-reviews');
        }

        if ($type !== 'testimonial' && isset($valid_platforms['testimonial'])) {
            unset($valid_platforms['testimonial']);
        }

        if($orderBy) {
            $reviews = $reviews->orderBy('review_time', $orderBy);
        } else {
            $reviews = $reviews->orderBy('id', 'desc');
        }

        $diff1 = array_diff_key($valid_platforms, $customValidPlatforms);
        $diff2 = array_diff_key($customValidPlatforms, $valid_platforms);

        // Skipped when a platform filter is set, so the filter isn't overridden.
        if ($type === 'review' && ($diff1 || $diff2) && empty($filter)) {
            if(in_array('fluent_forms', array_keys($valid_platforms))){
                unset($customValidPlatforms['fluent_forms']);
            }
            $allowedPlatforms = array_keys($valid_platforms);
            // native_form isn't in valid_platforms, but its standalone reviews belong in All Reviews.
            if (!in_array('native_form', $allowedPlatforms, true)) {
                $allowedPlatforms[] = 'native_form';
            }
            $reviews = $reviews->whereIn('platform_name', $allowedPlatforms)
                ->whereNotIn('platform_name', array_keys($customValidPlatforms))
                ->where('platform_name', '!=', 'testimonial');
        } elseif ($type === 'custom_review') {
            if($sourceId !== ''){
                $reviews = $reviews->where('source_id', $sourceId);
            }
            if (empty($filter)) {
                $allowedPlatforms = array_keys($customValidPlatforms);
                // Neither key is guaranteed to be in wpsr_available_valid_platforms.
                // All Fluent Forms sources share "fluent_forms", so one being deleted can drop it.
                if (!in_array('native_form', $allowedPlatforms, true)) {
                    $allowedPlatforms[] = 'native_form';
                }
                if (!in_array('fluent_forms', $allowedPlatforms, true)) {
                    $allowedPlatforms[] = 'fluent_forms';
                }
                $reviews = $reviews->whereIn('platform_name', $allowedPlatforms)
                    ->where('platform_name', '!=', 'testimonial');
            }
        }

        // native_form reviews that belong to a custom source are listed under that source.
        // Reviews from a standalone native form stay visible here.
        if ($type === 'review' && ($filter === '' || $filter === 'native_form')) {
            $nativeFormCustomSourceIds = $this->getNativeFormCustomSourceIds();
            if (!empty($nativeFormCustomSourceIds)) {
                if (empty($filter)) {
                    $reviews = $reviews->where(function ($q) use ($nativeFormCustomSourceIds) {
                        $q->where('platform_name', '!=', 'native_form')
                          ->orWhereNotIn('source_id', $nativeFormCustomSourceIds);
                    });
                } else {
                    $reviews = $reviews->whereNotIn('source_id', $nativeFormCustomSourceIds);
                }
            }
        }
        
        if ($statusFilter !== 'all') {
            switch ($statusFilter) {
                case 'publish':
                    $reviews = $reviews->where('review_approved', '1');
                    break;
                case 'spam':
                    $reviews = $reviews->where('review_approved', '2');
                    break;
                case 'unpublish':
                    $reviews = $reviews->where('review_approved', '0');
                    break;
            }
        }

        // The scope has the last word, so a crafted `filter=testimonial` can't leak rows.
        if ($scope === 'testimonial') {
            $reviews = $reviews->where('platform_name', 'testimonial');
        } else {
            $reviews = $reviews->where('platform_name', '!=', 'testimonial');
        }

        $reviews = $reviews->with('categories')->paginate();

        $totalReviews = $reviews->total();

        return [
            'all_valid_platforms'   => $valid_platforms,
            'items'                 => $reviews,
            'total_items'           => $totalReviews
        ];
    }

	public function create(ReviewRequest $request)
	{
        return $this->applyCreate($request, 'review');
	}

    /**
     * Testimonials screen (wpsn_manage_testimonials).
     */
    public function createTestimonial(ReviewRequest $request)
    {
        return $this->applyCreate($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial'
     */
    protected function applyCreate(ReviewRequest $request, $scope)
    {
        $review_fields = $request->get('review');
		$review_fields = wp_unslash($review_fields);
        $wpsr_errors = $this->validateReviewTypedFields($review_fields);
        if (!empty($wpsr_errors)) {
            return $this->sendError($wpsr_errors, 422);
        }

        // Not wpsr_reviews columns. They go to the pivot once the row has an id.
        $categoryIds = Arr::get($review_fields, 'category_ids');
        $newCategoryNames = Arr::get($review_fields, 'new_category_names');
        unset($review_fields['category_ids'], $review_fields['new_category_names']);

        $review = $this->sanitize($review_fields);

        if ($scope === 'testimonial') {
            $review['platform_name'] = 'testimonial';
        } elseif (Arr::get($review, 'platform_name') === 'testimonial') {
            return $this->sendError([
                'message' => __('A review cannot be created as a testimonial', 'wp-social-reviews')
            ], 422);
        }

		$review['recommendation_type'] = 'positive';
        $review['review_approved'] = 1;
        $platformName = Arr::get($review, 'platform_name', 'custom');
        $sourceId = Arr::get($review, 'source_id', null);
        $dataSource = [
            'source_id'   => $sourceId,
        ];

        $createdReview = Review::create($review);

        if (is_array($categoryIds) || is_array($newCategoryNames)) {
            $createdReview->categories()->sync(
                ReviewCategory::resolveIds($categoryIds, $newCategoryNames)
            );
        }

        $businessInfo = Review::getInternalBusinessInfo($platformName, $dataSource);
        update_option('wpsr_reviews_'.$platformName.'_business_info', $businessInfo);

		do_action('wpsocialreviews/custom_review_created', $createdReview);

		return [
			'message' => __('Review has been successfully created', 'wp-social-reviews'),
			'review'  => $createdReview
		];
	}

    public function duplicate(Request $request)
    {
        return $this->applyDuplicate($request, 'review');
    }

    /**
     * Testimonials screen (wpsn_manage_testimonials).
     */
    public function duplicateTestimonial(Request $request)
    {
        return $this->applyDuplicate($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial'
     */
    protected function applyDuplicate(Request $request, $scope)
    {
        // Deduped, or an id repeated across chunks would be copied twice.
        $ids = array_values(array_unique(array_map('intval', (array) $request->get('ids', []))));

        // A bare string answers 200, and the admin toast reads that as success.
        if (empty($ids)) {
            return $this->sendError([
                'message' => __('No reviews selected', 'wp-social-reviews')
            ], 422);
        }

        // Platform-imported reviews are read-only.
        $duplicatablePlatforms = apply_filters('wpsocialreviews/duplicatable_review_platforms', ['custom', 'testimonial', 'native_form']);

        // Out-of-scope ids are dropped silently, as in scopeReviewIds().
        $reviewsToDuplicate = [];
        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            $query = Review::whereIn('id', $chunk)
                ->whereIn('platform_name', $duplicatablePlatforms);

            if ($scope === 'testimonial') {
                $query = $query->where('platform_name', 'testimonial');
            } else {
                $query = $query->where('platform_name', '!=', 'testimonial');
            }

            foreach ($query->with('categories')->get() as $review) {
                $reviewsToDuplicate[] = $review;
            }
        }

        $createdReviews = empty($reviewsToDuplicate) ? [] : $this->runBulkMutation(function () use ($reviewsToDuplicate) {
            $created = [];
            $links = [];

            foreach ($reviewsToDuplicate as $review) {
                $reviewData = $review->toArray();
                $reviewData['review_title'] = '(Duplicate)' . $reviewData['review_title'] . ' (#' . $reviewData['id'] . ')';
                unset($reviewData['id']);

                $createdReview = Review::create($reviewData);
                $created[] = $createdReview;

                // toArray() skips the pivot rows. A new id has nothing to diff, so attach
                // in bulk rather than paying sync()'s per-review lookup.
                foreach ($review->categories as $category) {
                    $links[] = [
                        'review_id'   => (int) $createdReview->id,
                        'category_id' => (int) $category->id
                    ];
                }
            }

            ReviewCategoryRelationship::bulkAttachPairs($links);

            return $created;
        });

        if ($createdReviews === null) {
            return $this->sendError([
                'message' => __('The reviews could not be duplicated. Nothing was changed, please try again.', 'wp-social-reviews')
            ], 500);
        }

        foreach ($createdReviews as $createdReview) {
            do_action('wpsocialreviews/custom_review_created', $createdReview);
        }

        $duplicatedCount = count($createdReviews);

        return [
            'message' => sprintf(
                // translators: %d is the number of reviews that were duplicated
                _n(
                    '%d review has been successfully duplicated',
                    '%d reviews have been successfully duplicated',
                    $duplicatedCount,
                    'wp-social-reviews'
                ),
                $duplicatedCount
            ),
            'review' => $createdReviews
        ];
    }

	public function update(ReviewRequest $request, $reviewId)
	{
        return $this->applyUpdate($request, $reviewId, 'review');
	}

    /**
     * Testimonials screen (wpsn_manage_testimonials).
     */
    public function updateTestimonial(ReviewRequest $request, $reviewId)
    {
        return $this->applyUpdate($request, $reviewId, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial'
     */
    protected function applyUpdate(ReviewRequest $request, $reviewId, $scope)
    {
        $updateData = $request->get('review');
		$updateData = wp_unslash($updateData);
        $wpsr_errors = $this->validateReviewTypedFields($updateData);

        if (!empty($wpsr_errors)) {
            return $this->sendError($wpsr_errors, 422);
        }

        // Not wpsr_reviews columns, so read them before the allowlist drops them.
        $categoryIds = Arr::get($updateData, 'category_ids');
        $newCategoryNames = Arr::get($updateData, 'new_category_names');

        $updateData = $this->sanitize($updateData);
        $updateData = Arr::only($updateData, [
            'fields',
            'review_title',
            'reviewer_name',
            'reviewer_text',
            'review_time',
            'rating',
            'reviewer_url',
            'reviewer_img',
            'review_approved',
            'category'
        ]);

        // The scope clause stops one capability from editing the other screen's rows.
        $query = Review::where('id', (int) $reviewId);
        if ($scope === 'testimonial') {
            $query = $query->where('platform_name', 'testimonial');
        } else {
            $query = $query->where('platform_name', '!=', 'testimonial');
        }

        $review = $query->first();
        if (!$review) {
            return $this->sendError([
                'message' => __('Review not found', 'wp-social-reviews')
            ], 404);
        }

		$review->fill($updateData);
		$review->save();

        // Either key being present means the client is stating the full category set,
        // so an empty pair clears them. Both absent leaves existing categories alone.
        if (is_array($categoryIds) || is_array($newCategoryNames)) {
            $review->categories()->sync(
                ReviewCategory::resolveIds($categoryIds, $newCategoryNames)
            );
        }

		do_action('wpsocialreviews/custom_review_updated', $review);

		return [
			'message' => __('Review has been successfully updated', 'wp-social-reviews'),
			'review'  => $review
		];
    }

    /**
     * Narrows client-supplied ids to the rows the route's scope may act on. This is the
     * authorisation boundary between the reviews and testimonials capabilities.
     *
     * @param string $scope 'review' or 'testimonial'
     * @return int[]
     */
    private function scopeReviewIds(array $ids, $scope)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $scoped = [];
        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            $query = Review::whereIn('id', $chunk);
            if ($scope === 'testimonial') {
                $query = $query->where('platform_name', 'testimonial');
            } else {
                $query = $query->where('platform_name', '!=', 'testimonial');
            }
            $scoped = array_merge($scoped, array_map('intval', $query->pluck('id')->toArray()));
        }

        return $scoped;
    }

    /**
     * Runs a chunked bulk mutation in one transaction. Returns null on rollback.
     * Callers fire their actions only after it commits, since listeners re-query the table.
     *
     * @return mixed|null
     */
    private function runBulkMutation(Closure $callback)
    {
        try {
            return Review::resolveConnection()->transaction($callback);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function delete(Request $request)
    {
        return $this->applyDelete($request, 'review');
    }

    /**
     * Testimonials screen (wpsn_manage_testimonials).
     */
    public function deleteTestimonial(Request $request)
    {
        return $this->applyDelete($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial'
     */
    protected function applyDelete(Request $request, $scope)
    {
        $ids = $this->scopeReviewIds((array) $request->get('ids', []), $scope);

        if(empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        // Snapshot the rows first. The actions fire after the delete commits.
        $reviewsToDelete = [];
        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            foreach (Review::whereIn('id', $chunk)->get() as $review) {
                $reviewsToDelete[] = $review;
            }
        }

        $deletedCount = $this->runBulkMutation(function () use ($ids) {
            $deleted = 0;
            foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
                $deleted += (int) Review::whereIn('id', $chunk)->delete();
            }

            ReviewCategoryRelationship::deleteForReviews($ids);

            return $deleted;
        });

        if ($deletedCount === null) {
            return $this->sendError([
                'message' => __('The reviews could not be deleted. Nothing was changed, please try again.', 'wp-social-reviews')
            ], 500);
        }

        foreach ($reviewsToDelete as $review) {
            do_action('wpsocialreviews/custom_review_deleted', $review);
        }

        return sprintf(
            // translators: %d is the number of reviews that were deleted
            _n(
                '%d review has been successfully deleted',
                '%d reviews have been successfully deleted',
                $deletedCount,
                'wp-social-reviews'
            ),
            $deletedCount
        );
    }

    public function statusUpdate(Request $request)
    {
        return $this->applyStatusUpdate($request, 'review');
    }

    /**
     * Testimonials screen (wpsn_manage_testimonials).
     */
    public function statusUpdateTestimonial(Request $request)
    {
        return $this->applyStatusUpdate($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial'
     */
    protected function applyStatusUpdate(Request $request, $scope)
    {
        $status = sanitize_text_field(wp_unslash((string) $request->get('status', 'enable')));
        $ids = $this->scopeReviewIds((array) $request->get('ids', []), $scope);

        if (empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        if (!in_array($status, ['enable', 'disable'])) {
            return __('Invalid status value', 'wp-social-reviews');
        }

        // Chunked because "select all" makes $ids as long as the review count, which would
        // blow past max_allowed_packet in one statement.
        $approvedValue = $status === 'enable' ? 1 : 0;
        $updated = $this->runBulkMutation(function () use ($ids, $approvedValue) {
            foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
                Review::whereIn('id', $chunk)->update(['review_approved' => $approvedValue]);
            }

            return true;
        });

        if ($updated === null) {
            return $this->sendError([
                'message' => __('The status could not be updated. Nothing was changed, please try again.', 'wp-social-reviews')
            ], 500);
        }

        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            foreach (Review::whereIn('id', $chunk)->get() as $review) {
                do_action('wpsocialreviews/custom_review_updated', $review);
            }
        }

        $message = $scope === 'testimonial'
            ? __('Testimonials status has been successfully updated', 'wp-social-reviews')
            : __('Reviews status has been successfully updated', 'wp-social-reviews');

        return [
            'message' => $message
        ];
    }

    public function spamReviews(Request $request)
    {
        return $this->applySpam($request, 'review');
    }

    /**
     * Testimonials screen (wpsn_manage_testimonials).
     */
    public function spamTestimonialReviews(Request $request)
    {
        return $this->applySpam($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial'
     */
    protected function applySpam(Request $request, $scope)
    {
        $action = sanitize_text_field(wp_unslash((string) $request->get('action', 'mark-spam')));
        $ids = $this->scopeReviewIds((array) $request->get('ids', []), $scope);

        if (empty($ids)) {
            return [
                'message' => __('No reviews selected', 'wp-social-reviews')
            ];
        }

        if (!in_array($action, ['mark-spam', 'not-spam'])) {
            return __('Invalid action value', 'wp-social-reviews');
        }

        if ($action === 'mark-spam') {
            $approvedValue = 2;

            $message = sprintf(
                _n(
                    '%d review has been marked as spam',
                    '%d reviews have been marked as spam',
                    count($ids),
                    'wp-social-reviews'
                ),
                count($ids)
            );
        } else {
            // Back to pending.
            $approvedValue = 0;

            $message = sprintf(
                _n(
                    '%d review has been unmarked as spam',
                    '%d reviews have been unmarked as spam',
                    count($ids),
                    'wp-social-reviews'
                ),
                count($ids)
            );
        }

        $updated = $this->runBulkMutation(function () use ($ids, $approvedValue) {
            foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
                Review::whereIn('id', $chunk)->update([
                    'review_approved' => $approvedValue
                ]);
            }

            return true;
        });

        if ($updated === null) {
            return $this->sendError([
                'message' => __('The reviews could not be updated. Nothing was changed, please try again.', 'wp-social-reviews')
            ], 500);
        }

        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            foreach (Review::whereIn('id', $chunk)->get() as $review) {
                do_action('wpsocialreviews/custom_review_updated', $review);
            }
        }

        return [
            'message' => $message
        ];
    }

    /**
     * Bulk assign or remove a category on the reviews screen (wpsn_manage_reviews).
     */
    public function bulkCategory(Request $request)
    {
        return $this->applyBulkCategory($request, 'review');
    }

    /**
     * Testimonials screen (wpsn_manage_testimonials).
     */
    public function bulkTestimonialCategory(Request $request)
    {
        return $this->applyBulkCategory($request, 'testimonial');
    }

    /**
     * @param string $scope 'review' or 'testimonial'
     */
    protected function applyBulkCategory(Request $request, $scope)
    {
        $ids = (array) $request->get('ids', []);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $action = sanitize_text_field(wp_unslash((string) $request->get('action', 'add')));
        $categoryId = (int) $request->get('category_id', 0);
        $newCategoryName = sanitize_text_field(wp_unslash((string) $request->get('new_category_name', '')));

        if (empty($ids)) {
            return $this->sendError([
                'message' => __('No reviews selected', 'wp-social-reviews')
            ], 422);
        }

        if (!in_array($action, ['add', 'remove'], true)) {
            return $this->sendError([
                'message' => __('Invalid action value', 'wp-social-reviews')
            ], 422);
        }

        // Removing a category that doesn't exist yet would only leave an empty one behind.
        if ($action === 'remove' && trim($newCategoryName) !== '') {
            return $this->sendError([
                'message' => __('Select an existing category to remove', 'wp-social-reviews')
            ], 422);
        }

        // Out-of-scope ids are dropped silently, as in scopeReviewIds().
        $reviewIds = [];
        foreach (array_chunk($ids, ReviewCategoryRelationship::BATCH_SIZE) as $chunk) {
            $scopedQuery = Review::whereIn('id', $chunk);

            if ($scope === 'testimonial') {
                $scopedQuery = $scopedQuery->where('platform_name', 'testimonial');
            } else {
                $scopedQuery = $scopedQuery->where('platform_name', '!=', 'testimonial');
            }

            $reviewIds = array_merge(
                $reviewIds,
                array_map('intval', $scopedQuery->pluck('id')->toArray())
            );
        }

        if (empty($reviewIds)) {
            return $this->sendError([
                'message' => __('No matching reviews were found for this action', 'wp-social-reviews')
            ], 422);
        }

        // The pivot table has no foreign key, so a stale id would write orphaned rows.
        if ($categoryId && !ReviewCategory::find($categoryId)) {
            return $this->sendError([
                'message' => __('The selected category no longer exists', 'wp-social-reviews')
            ], 422);
        }

        // Created only now, so a request that matches nothing leaves no empty category.
        if (!$categoryId && trim($newCategoryName) !== '') {
            $category = ReviewCategory::findOrCreateByName($newCategoryName);
            $categoryId = $category ? (int) $category->id : 0;
        }

        if (!$categoryId) {
            return $this->sendError([
                'message' => $action === 'remove'
                    ? __('Please select an existing category to remove', 'wp-social-reviews')
                    : __('Please select an existing category or provide a new category name', 'wp-social-reviews')
            ], 422);
        }

        if ($action === 'add') {
            ReviewCategoryRelationship::bulkAttach($reviewIds, $categoryId);
            $message = sprintf(
                // translators: %d is the number of reviews the category was assigned to
                _n(
                    'Category assigned to %d review',
                    'Category assigned to %d reviews',
                    count($reviewIds),
                    'wp-social-reviews'
                ),
                count($reviewIds)
            );
        } else {
            ReviewCategoryRelationship::bulkDetach($reviewIds, $categoryId);
            $message = sprintf(
                // translators: %d is the number of reviews the category was removed from
                _n(
                    'Category removed from %d review',
                    'Category removed from %d reviews',
                    count($reviewIds),
                    'wp-social-reviews'
                ),
                count($reviewIds)
            );
        }

        return [
            'message' => $message
        ];
    }

    /**
     * Source ids of native review forms registered as custom sources. Pro supplies them.
     */
    private function getNativeFormCustomSourceIds(): array
    {
        if (static::$nativeFormCustomSourceIds !== null) {
            return static::$nativeFormCustomSourceIds;
        }

        $raw = (array) apply_filters('wpsocialreviews/native_form_custom_source_ids', []);

        // Normalize as text to match the varchar source_id column.
        static::$nativeFormCustomSourceIds = array_values(array_unique(array_filter(array_map(function ($sourceId) {
            return sanitize_text_field(wp_unslash((string) $sourceId));
        }, $raw), function ($sourceId) {
            return $sourceId !== '';
        })));

        return static::$nativeFormCustomSourceIds;
    }

    public function sanitize($fields)
    {
        // Dot notation is for nested keys.
        $sanitizeRules = [
            'reviewer_name' => 'sanitize_text_field',
            'reviewer_email' => 'sanitize_email',
            'reviewer_url'  => 'sanitize_url',
            'review_title'  => 'sanitize_text_field',
            'reviewer_text' => 'wp_kses_post',
            'category'      => 'sanitize_text_field',
            'review_time'   => 'sanitize_text_field',
            'platform_name' => 'sanitize_text_field',
            'rating'        => 'intval',
            'reviewer_img'  => 'sanitize_url',
            'review_approved' => 'intval',
            'fields.author_company'         => 'sanitize_text_field',
            'fields.author_position'        => 'sanitize_text_field',
            'fields.author_website_logo'    => 'sanitize_url',
            'fields.author_website_url'     => 'sanitize_url',
            'fields.video_url'              => 'sanitize_url',
            'fields.video_poster'           => 'sanitize_url',
            'fields.review_images'          => 'sanitize_url',
            'fields.product_thumbnail'      => 'sanitize_url'
        ];

        $sanitizedReview = [];

        if (empty($fields) || !is_array($fields)) {
            return $sanitizedReview;
        }

        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $dotKey = $key . '.' . $subKey;

                    $sanitizeFunc = Arr::get($sanitizeRules, $dotKey, 'sanitize_text_field');

                    $sanitizedReview[$key][$subKey] = $this->sanitizeFieldValue($sanitizeFunc, $subValue, $dotKey);
                }
            } else {
                $sanitizeFunc = Arr::get($sanitizeRules, $key, 'sanitize_text_field');
                $sanitizedReview[$key] = $this->sanitizeFieldValue($sanitizeFunc, $value, $key);
            }
        }

        return $sanitizedReview;
    }

    /**
     * Sanitize one `fields` value by its per-key rule
     *
     * An array on a scalar key collapses to '', or intval reads [1,2] as 1.
     *
     * @param string $sanitizeFunc
     * @param mixed  $value
     * @param string $fullKey dot-notation key, e.g. `fields.video_url`
     *
     * @return mixed
     */
    private function sanitizeFieldValue($sanitizeFunc, $value, $fullKey = '')
    {
        if (in_array($fullKey, self::LIST_FIELD_KEYS, true)) {
            return $this->sanitizeListField($sanitizeFunc, $value, $fullKey);
        }

        if ($value === null || is_array($value) || is_object($value)) {
            $value = '';
        }

        if ($sanitizeFunc === 'intval') {
            return intval($value);
        }

        $value = (string) $value;

        if ($sanitizeFunc === 'sanitize_url') {
            $value = wpsr_trim_url($value);
        }

        return $sanitizeFunc($value);
    }

    /**
     * Sanitize a `fields` key that holds a list
     *
     * A list keeps its shape, or a re-save would wipe it.
     *
     * @param string $sanitizeFunc
     * @param mixed  $value   the list, or one comma separated string
     * @param string $fullKey dot-notation key, e.g. `fields.review_images`
     *
     * @return array|string an array in, an array out
     */
    private function sanitizeListField($sanitizeFunc, $value, $fullKey)
    {
        if (is_array($value)) {
            // product_thumbnail holds [url, width, height, flag]; sanitize_url would
            // turn 100 into "http://100", so its non-strings are kept as they are.
            $keepsNonStrings = $fullKey === 'fields.product_thumbnail';

            return array_values(array_map(function ($item) use ($sanitizeFunc, $keepsNonStrings) {
                if (is_string($item)) {
                    return $sanitizeFunc($item);
                }

                return ($keepsNonStrings && is_scalar($item)) ? $item : '';
            }, $value));
        }

        if ($value === null || is_object($value)) {
            $value = '';
        }

        // Each part alone: sanitizing the whole string would encode the separator
        // space, or strip the %XX escapes a CDN URL needs.
        return implode(',', array_map(function ($item) use ($sanitizeFunc) {
            return $sanitizeFunc(wpsr_trim_url($item));
        }, explode(',', (string) $value)));
    }

    /**
     * Reject a shape the columns cannot hold
     *
     * An array on a scalar column reaches fill() and stores 0, reporting success.
     *
     * @param array $fields
     *
     * @return array
     */
    private function validateReviewTypedFields($fields)
    {
        $wpsr_errors = [];
        $fields = is_array($fields) ? $fields : [];

        foreach (self::SCALAR_COLUMNS as $wpsr_column) {
            $wpsr_value = Arr::get($fields, $wpsr_column);

            if ($wpsr_value !== null && !is_scalar($wpsr_value)) {
                $wpsr_errors['review.' . $wpsr_column]['type'] = 'The ' . $wpsr_column . ' field must be a single value.';
            }
        }

        $wpsr_review_time = Arr::get($fields, 'review_time');
        if (!$this->isValidReviewTime($wpsr_review_time)) {
            $wpsr_errors['review.review_time']['date_format'] = 'The date field must be in YYYY-MM-DD HH:MM:SS format.';
        }

        foreach (self::URL_COLUMN_LABELS as $wpsr_column => $wpsr_label) {
            $wpsr_value = Arr::get($fields, $wpsr_column);

            if ($wpsr_value === null || !is_scalar($wpsr_value) || (string) $wpsr_value === '') {
                continue;
            }

            if (!wpsr_is_http_url($wpsr_value)) {
                $wpsr_errors['review.' . $wpsr_column]['url'] =
                    'The ' . $wpsr_label . ' must be a full URL starting with http:// or https://.';
            }
        }

        $wpsr_reviewer_email = Arr::get($fields, 'reviewer_email');
        if ($wpsr_reviewer_email !== null && trim((string) $wpsr_reviewer_email) !== '') {
            $wpsr_reviewer_email = (string) $wpsr_reviewer_email;
            if (!is_email($wpsr_reviewer_email)) {
                $wpsr_errors['review.reviewer_email']['email'] = 'The email field must be a valid email address.';
            } elseif (strlen($wpsr_reviewer_email) > 255) {
                $wpsr_errors['review.reviewer_email']['max'] = 'The email field may not be greater than 255 characters.';
            }
        }

        // No destination check on image URLs here: a DNS lookup at save time drops good URLs
        // when the resolver blips. RemoteMediaFetcher checks the destination at fetch time.

        return array_merge($wpsr_errors, $this->validateReviewFieldsShape(Arr::get($fields, 'fields')));
    }

    /**
     * Reject a malformed `fields` payload before it reaches the JSON column
     *
     * Every key outside LIST_FIELD_KEYS is read as a scalar string.
     *
     * @param mixed $wpsr_fields
     *
     * @return array
     */
    private function validateReviewFieldsShape($wpsr_fields)
    {
        $wpsr_errors = [];

        if ($wpsr_fields === null || $wpsr_fields === '') {
            return $wpsr_errors;
        }

        if (!is_array($wpsr_fields)) {
            $wpsr_errors['review.fields']['type'] = 'The fields value must be an object.';

            return $wpsr_errors;
        }

        $wpsr_images = Arr::get($wpsr_fields, 'review_images');
        if ($wpsr_images !== null && !is_scalar($wpsr_images)) {
            if (!$this->isFlatList($wpsr_images)) {
                $wpsr_errors['review.fields.review_images']['list'] = 'The review images field must be a flat list of URLs.';
            } elseif (!$this->everyItemIsHttpUrl($wpsr_images)) {
                $wpsr_errors['review.fields.review_images']['url'] =
                    'Each review image must be a full URL starting with http:// or https://.';
            }
        }

        foreach (self::URL_FIELD_LABELS as $wpsr_key => $wpsr_label) {
            $wpsr_value = Arr::get($wpsr_fields, $wpsr_key);

            if ($wpsr_value === null || $wpsr_value === '') {
                continue;
            }

            if (!wpsr_is_http_url($wpsr_value)) {
                $wpsr_errors['review.fields.' . $wpsr_key]['url'] =
                    'The ' . $wpsr_label . ' must be a full URL starting with http:// or https://.';
            }
        }

        return $wpsr_errors;
    }

    /**
     * @param mixed $wpsr_value
     *
     * @return bool true only for an array whose every item is a scalar
     */
    private function isFlatList($wpsr_value)
    {
        if (!is_array($wpsr_value)) {
            return false;
        }

        foreach ($wpsr_value as $wpsr_item) {
            if (!is_scalar($wpsr_item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $wpsr_list
     *
     * @return bool true when every filled item is a complete http(s) URL
     */
    private function everyItemIsHttpUrl($wpsr_list)
    {
        foreach ($wpsr_list as $wpsr_item) {
            if ($wpsr_item !== '' && !wpsr_is_http_url($wpsr_item)) {
                return false;
            }
        }

        return true;
    }

    private function isValidReviewTime($wpsr_review_time)
    {
        $wpsr_review_time = (string) $wpsr_review_time;
        $wpsr_date = \DateTime::createFromFormat('!Y-m-d H:i:s', $wpsr_review_time);
        $wpsr_errors = \DateTime::getLastErrors();
        $wpsr_has_errors = is_array($wpsr_errors) && (
            !empty($wpsr_errors['warning_count']) || !empty($wpsr_errors['error_count'])
        );

        return $wpsr_date && !$wpsr_has_errors && $wpsr_date->format('Y-m-d H:i:s') === $wpsr_review_time;
    }
}
