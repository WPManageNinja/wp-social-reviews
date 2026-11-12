<?php

namespace WPSocialReviews\App\Models;

use WPSocialReviews\Database\Migrations\ReviewCategoriesMigrator;

/**
 * Pivot behind Review::categories() / ReviewCategory::reviews(). Per-review work goes
 * through the relation; this holds set-based operations across many reviews.
 */
class ReviewCategoryRelationship extends Model
{
    protected $table = 'wpsr_review_category_relationships';

    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'category_id'
    ];

    protected $casts = [
        'review_id'   => 'integer',
        'category_id' => 'integer'
    ];

    /**
     * Review ids per statement. "Select all" makes these lists as long as the
     * site's review count, so they are chunked to stay under max_allowed_packet.
     */
    const BATCH_SIZE = 500;

    /**
     * Drops every category link for the given reviews. Call it wherever reviews are
     * deleted: those are mass deletes, so no model event clears these rows.
     */
    public static function deleteForReviews(array $reviewIds)
    {
        $reviewIds = array_values(array_unique(array_map('intval', $reviewIds)));
        if (empty($reviewIds)) {
            return;
        }

        // Reachable from cron, but the migration only runs on the admin page,
        // so the table may not exist yet.
        if (!ReviewCategoriesMigrator::tablesExist()) {
            return;
        }

        foreach (array_chunk($reviewIds, static::BATCH_SIZE) as $chunk) {
            static::whereIn('review_id', $chunk)->delete();
        }
    }

    /**
     * Attaches one category to many reviews (bulk-assign action).
     */
    public static function bulkAttach(array $reviewIds, $categoryId)
    {
        $reviewIds = array_values(array_unique(array_map('intval', $reviewIds)));
        $categoryId = (int) $categoryId;

        if (empty($reviewIds) || !$categoryId) {
            return;
        }

        foreach (array_chunk($reviewIds, static::BATCH_SIZE) as $chunk) {
            $rows = [];
            foreach ($chunk as $reviewId) {
                $rows[] = ['review_id' => $reviewId, 'category_id' => $categoryId];
            }

            // The unique (review_id, category_id) key makes re-attaching a no-op.
            static::query()->insertOrIgnore($rows);
        }
    }

    /**
     * Attaches arbitrary (review, category) pairs, for the duplicate action.
     * Callers must scope review_id and confirm category_id exists first.
     *
     * @param array $pairs Rows of ['review_id' => int, 'category_id' => int].
     * @return int Links actually created.
     */
    public static function bulkAttachPairs(array $pairs)
    {
        $rows = [];
        foreach ($pairs as $pair) {
            $reviewId = isset($pair['review_id']) ? (int) $pair['review_id'] : 0;
            $categoryId = isset($pair['category_id']) ? (int) $pair['category_id'] : 0;

            if (!$reviewId || !$categoryId) {
                continue;
            }

            // Keyed so a pair repeated in the input costs no statement space.
            $rows[$reviewId . ':' . $categoryId] = [
                'review_id'   => $reviewId,
                'category_id' => $categoryId
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        $attached = 0;
        foreach (array_chunk(array_values($rows), static::BATCH_SIZE) as $chunk) {
            // Same unique-key dedupe as bulkAttach(): an existing link is a no-op.
            $attached += (int) static::query()->insertOrIgnore($chunk);
        }

        return $attached;
    }

    /**
     * Detaches one category from many reviews (bulk "Remove category" action).
     */
    public static function bulkDetach(array $reviewIds, $categoryId)
    {
        $reviewIds = array_values(array_unique(array_map('intval', $reviewIds)));
        $categoryId = (int) $categoryId;

        if (empty($reviewIds) || !$categoryId) {
            return;
        }

        foreach (array_chunk($reviewIds, static::BATCH_SIZE) as $chunk) {
            static::whereIn('review_id', $chunk)
                ->where('category_id', $categoryId)
                ->delete();
        }
    }
}
