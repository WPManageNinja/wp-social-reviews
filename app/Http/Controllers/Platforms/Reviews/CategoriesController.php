<?php

namespace WPSocialReviews\App\Http\Controllers\Platforms\Reviews;

use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\App\Models\ReviewCategory;
use WPSocialReviews\App\Services\PermissionManager;
use WPSocialReviews\Framework\Http\Request\Request;

/**
 * Browse, rename and delete review categories. There is no store(): categories are
 * only created attached to something (review select, bulk assign, CSV import).
 */
class CategoriesController extends Controller
{
    /**
     * Serves the assign dropdowns and the management modal. Counts span reviews and
     * testimonials, so only a caller with both capabilities (as CategoryManagementPolicy)
     * gets them. Others get names only and can't infer the other screen's totals.
     */
    public function index(Request $request)
    {
        $canSeeGlobalCounts = PermissionManager::currentUserCan('wpsn_manage_reviews')
            && PermissionManager::currentUserCan('wpsn_manage_testimonials');

        return [
            'categories' => $canSeeGlobalCounts
                ? ReviewCategory::allOrderedWithCounts()
                : ReviewCategory::allOrdered()
        ];
    }

    public function update(Request $request, $id)
    {
        $name = sanitize_text_field(wp_unslash((string) $request->get('name', '')));

        if (trim($name) === '') {
            return $this->sendError([
                'message' => __('Category name is required', 'wp-social-reviews')
            ], 422);
        }

        $category = ReviewCategory::findOrFail((int) $id);

        $slug = ReviewCategory::makeSlug($name);
        if ($slug === '') {
            return $this->sendError([
                'message' => __('Please provide a category name with at least one letter or number', 'wp-social-reviews')
            ], 422);
        }

        $duplicate = ReviewCategory::findBySlug($slug);
        if ($duplicate && (int) $duplicate->id !== (int) $category->id) {
            return $this->sendError([
                'message' => __('A category with this name already exists', 'wp-social-reviews')
            ], 422);
        }

        $category->fill([
            'name' => $name,
            'slug' => $slug
        ]);
        $category->save();

        return [
            'message'  => __('Category has been successfully renamed', 'wp-social-reviews'),
            'category' => $category
        ];
    }

    public function delete(Request $request, $id)
    {
        $category = ReviewCategory::findOrFail((int) $id);

        $category->delete();

        return [
            'message' => __('Category has been successfully deleted', 'wp-social-reviews')
        ];
    }
}
