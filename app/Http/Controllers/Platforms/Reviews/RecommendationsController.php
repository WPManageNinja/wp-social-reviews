<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Reviews;

use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\Framework\Request\Request;
use WPSocialReviews\App\Http\Requests\ReviewRequest;
use WPSocialReviews\Framework\Support\Arr;

class RecommendationsController extends Controller
{
    public function index(Request $request)
    {
        //find all available platforms for templating
        $valid_platforms = apply_filters('wpsocialreviews/available_valid_reviews_platforms', []);
        $customValidPlatforms = get_option('wpsr_available_valid_platforms', []);

        $type = $request->get('type');
        $sourceId = $request->get('source_id');

        $search  = $request->get('search');
        $filter  = $request->get('filter') === 'all' ? '' : $request->get('filter');
        $orderBy = $request->get('order_by') ? $request->get('order_by') : '';

        if($type === 'testimonial') {
            $filter = $type;
        }

        if($type !== 'custom_review'){
            $reviews = Review::searchBy($search)->where('platform_name', 'like', '%'.$filter.'%');
        } else {
            $reviews = Review::searchBy($search);
        }

        $hasCustomReview = Review::where('platform_name', 'custom')->count();

        if ($hasCustomReview) {
            $valid_platforms['custom'] = __('Custom', 'wp-social-reviews');
        }

        // Remove testimonial from valid_platforms when type is not testimonial
        if ($type !== 'testimonial' && isset($valid_platforms['testimonial'])) {
            unset($valid_platforms['testimonial']);
        }

        if($orderBy) {
            $reviews = $reviews->orderBy('review_time', $orderBy);
        } else {
            $reviews = $reviews->orderBy('id', 'desc');
        }

        // Detect any mismatch between the two arrays
        $diff1 = array_diff_key($valid_platforms, $customValidPlatforms);
        $diff2 = array_diff_key($customValidPlatforms, $valid_platforms);

        if ($type === 'review' && ($diff1 || $diff2)) {
            if(in_array('fluent_forms', array_keys($valid_platforms))){
                unset($customValidPlatforms['fluent_forms']);
            }
            // Include only $valid_platforms, exclude $customValidPlatforms
            $reviews = $reviews->whereIn('platform_name', array_keys($valid_platforms))
                ->whereNotIn('platform_name', array_keys($customValidPlatforms))
                ->where('platform_name', '!=', 'testimonial');
        } elseif ($type === 'custom_review') {
            if($sourceId){
                $reviews = $reviews->where('source_id', $sourceId);
            }
            // Include only $customValidPlatforms, exclude $valid_platforms
            $reviews = $reviews->whereIn('platform_name', array_keys($customValidPlatforms))
                ->where('platform_name', '!=', 'testimonial');
        }

        $reviews = $reviews->paginate();

        $totalReviews = $type === 'testimonial' ? Review::where('platform_name', $filter)->get() : Review::all();

        $totalReviews = count($totalReviews);

        return [
            'all_valid_platforms'   => $valid_platforms,
            'items'                 => $reviews,
            'total_items'           => $totalReviews
        ];
    }

	public function create(ReviewRequest $request)
	{
        $review_fields = $request->get('review');
		$review_fields = wp_unslash($review_fields);
        $review = $this->sanitize($review_fields);

		$review['recommendation_type'] = 'positive';
        $review['review_approved'] = 1;
        $platformName = Arr::get($review, 'platform_name', 'custom');
        $sourceId = Arr::get($review, 'source_id', null);
        $dataSource = [
            'source_id'   => $sourceId,
        ];

        $createdReview = Review::create($review);

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
        $ids = $request->get('ids', []);

        if(empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        $duplicatedCount = 0;
        $createdReviews = [];
        
        foreach ( $ids as $id) {
            $review = Review::find($id)->toArray();

            $review['review_title'] = '(Duplicate)' . $review['review_title'] . ' (#' . $review['id'] . ')';

            $createdReview = Review::create($review);
            $createdReviews[] = $createdReview;
            $duplicatedCount++;
            do_action('wpsocialreviews/custom_review_created', $createdReview);
        }
        
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
        $updateData = $request->get('review');
		$updateData = wp_unslash($updateData);
        $updateData = $this->sanitize($updateData);

        $review = Review::findOrFail($reviewId);

		$review->fill($updateData);
		$review->save();

		do_action('wpsocialreviews/custom_review_updated', $review);

		return [
			'message' => __('Review has been successfully updated', 'wp-social-reviews'),
			'review'  => $review
		];
	}

    public function delete(Request $request)
    {
        $ids = $request->get('ids', []);

        if(empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        $deletedCount = 0;
        foreach ($ids as $id) {
            $review = Review::find($id);
            if ($review && $review->delete()) {
                do_action('wpsocialreviews/custom_review_deleted', $review);
                $deletedCount++;
            }
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
        $ids = $request->get('ids', []);
        $status = $request->get('status', 'enable');
        $type = $request->get('type', 'review');

        if (empty($ids)) {
            return __('No reviews selected', 'wp-social-reviews');
        }

        foreach ($ids as $id) {
            $review = Review::find($id);
            $review->review_approved = $status === 'enable' ? 1 : 0;
            $review->save();
        }

        // Dynamic message based on type
        $message = $type === 'testimonial' 
            ? __('Testimonials status has been successfully updated', 'wp-social-reviews')
            : __('Reviews status has been successfully updated', 'wp-social-reviews');

        return [
            'message' => $message
        ];
    }

    public function sanitize($fields)
    {
        $sanitizeRules = [
            'reviewer_name' => 'sanitize_text_field',
            'reviewer_url'  => 'sanitize_url',
            'review_title'  => 'sanitize_text_field',
            'reviewer_text' => 'wp_kses_post',
            'category'      => 'sanitize_text_field',
            'review_time'   => 'sanitize_text_field',
            'platform_name' => 'sanitize_text_field',
            'reviewer_img'  => 'sanitize_url',
            'fields.author_company'         => 'sanitize_text_field',
            'fields.author_position'        => 'sanitize_text_field',
            'fields.author_website_logo'    => 'sanitize_url',
            'fields.author_website_url'     => 'sanitize_url'
        ];

        $review = [];
        if($fields && is_array($fields)) {
            foreach ($fields as $dataKey => $dataItem) {
                if(is_array($fields[$dataKey]) && count($fields[$dataKey]) > 1) {
                    foreach ($fields[$dataKey] as $subKey => $subItem) {
                        $key = $dataKey.'.'.$subKey;
                        $sanitizeFunc = Arr::get($sanitizeRules, $key, 'sanitize_text_field');
                        $review[$dataKey][$subKey] = $sanitizeFunc($subItem);
                    }
                } else {
                    $sanitizeFunc = Arr::get($sanitizeRules, $dataKey, 'sanitize_text_field');
                    $review[$dataKey] = $sanitizeFunc($dataItem);
                }
            }
        }

        return $review;
    }
}