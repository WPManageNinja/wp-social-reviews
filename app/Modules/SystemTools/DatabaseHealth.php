<?php

namespace WPSocialReviews\App\Modules\SystemTools;

use WPSocialReviews\App\Models\Cache;

/**
 * Whether each plugin table exists, its row count, and the cache totals, for the Database Health page.
 */
class DatabaseHealth
{
    public static function tables()
    {
        return [
            'wpsr_reviews',
            'wpsr_caches',
            'wpsr_optimize_images',
            'wpsr_review_forms',
            'wpsr_review_categories',
            'wpsr_review_category_relationships',
        ];
    }

    protected static function label($table)
    {
        $labels = [
            'wpsr_reviews'                       => __('Reviews', 'wp-social-reviews'),
            'wpsr_caches'                        => __('Feed and Review Caches', 'wp-social-reviews'),
            'wpsr_optimize_images'               => __('Optimized Images', 'wp-social-reviews'),
            'wpsr_review_forms'                  => __('Review Forms', 'wp-social-reviews'),
            'wpsr_review_categories'             => __('Review Categories', 'wp-social-reviews'),
            'wpsr_review_category_relationships' => __('Review Category Assignments', 'wp-social-reviews'),
        ];

        return isset($labels[$table]) ? $labels[$table] : $table;
    }

    public function getReport()
    {
        global $wpdb;

        $tables = [];
        $hasCacheTable = false;
        foreach (static::tables() as $table) {
            $name = $wpdb->prefix . $table;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live check for the status page
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $name)) === $name;
            $rows = null;

            if ($exists) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live check for the status page
                $rows = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $name));
            }

            if ($table === 'wpsr_caches') {
                $hasCacheTable = $exists;
            }

            $tables[] = [
                'name'   => $name,
                'label'  => static::label($table),
                'exists' => $exists,
                'rows'   => $rows,
                'status' => $exists ? 'ok' : 'error',
            ];
        }

        $caches = ['total' => 0, 'expired' => 0];
        if ($hasCacheTable) {
            $caches['total'] = (int) Cache::query()->count();
            $caches['expired'] = (int) Cache::query()->where('expiration', '<', current_time('mysql'))->count();
        }

        return [
            'tables' => $tables,
            'caches' => $caches,
        ];
    }
}
