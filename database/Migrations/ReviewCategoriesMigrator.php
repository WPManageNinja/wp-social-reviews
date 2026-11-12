<?php

namespace WPSocialReviews\Database\Migrations;

class ReviewCategoriesMigrator
{
    static $categoriesTable = 'wpsr_review_categories';
    static $relationshipsTable = 'wpsr_review_category_relationships';

    // Set only once the backfill finishes. Table existence can't be the guard: tables
    // are created first, so a backfill that died partway would never be retried.
    static $backfillOption = 'wpsr_review_categories_backfilled';

    // Reviews per pass: one SELECT plus one multi-row INSERT each.
    static $backfillChunkSize = 1000;

    // Last review id backfilled, persisted so a large table resumes across requests.
    static $backfillCursorOption = 'wpsr_review_categories_backfill_cursor';

    // Chunks per migrate(), so one request can't time out or run out of memory.
    // 20 * 1000 = 20k reviews per request.
    static $backfillMaxChunksPerRequest = 20;

    // Orphan sweep resume point: the last relationship id processed, or 0 for a deferred
    // full pass. Its presence, not its value, is the "pending" flag. Cleared on a full scan.
    static $orphanSweepPendingOption = 'wpsr_review_categories_orphan_sweep_pending';

    // Memo for tableExists(), keyed by prefixed name so it survives switch_to_blog().
    protected static $tableExistsCache = [];

    /**
     * Read paths must check this before touching the relation. The tables may not
     * exist yet on a request that hasn't run the migration.
     */
    public static function tablesExist()
    {
        global $wpdb;

        return static::tableExists($wpdb->prefix . static::$categoriesTable)
            && static::tableExists($wpdb->prefix . static::$relationshipsTable);
    }

    /**
     * True once the legacy `category` column is fully copied into the taxonomy, which
     * makes the taxonomy authoritative. Re-checks the tables in case the flag outlived them.
     */
    public static function isBackfilled()
    {
        return (bool) get_option(static::$backfillOption) && static::tablesExist();
    }

    /**
     * Requests that may run the backfill/sweep: admin page loads, cron and WP-CLI.
     * Not admin-ajax, so heartbeat and autosave never inherit ~20k row deletions.
     */
    public static function isMaintenanceRequest()
    {
        $isMaintenance = wp_doing_cron()
            || (defined('WP_CLI') && WP_CLI)
            || (is_admin() && !wp_doing_ajax());

        return (bool) apply_filters('wpsocialreviews/is_maintenance_request', $isMaintenance);
    }

    public static function migrate()
    {
        global $wpdb;

        // Callable from any request context, but dbDelta() lives in an admin-only include.
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        $charsetCollate = $wpdb->get_charset_collate();
        $categoriesTable = $wpdb->prefix . static::$categoriesTable;
        $relationshipsTable = $wpdb->prefix . static::$relationshipsTable;

        $categoriesTableExisted = static::tableExists($categoriesTable);
        $relationshipsTableExisted = static::tableExists($relationshipsTable);

        if (!$categoriesTableExisted) {
            // 191, not 255: see ReviewCategory::MAX_LENGTH.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to create table
            $sql = "CREATE TABLE $categoriesTable (
                `id`         bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name`       varchar(191) NOT NULL,
                `slug`       varchar(191) NOT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                UNIQUE KEY `idx_slug` (`slug`)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        if (!$relationshipsTableExisted) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for migration to create table
            // `review_id` matches wpsr_reviews.id's int(11) so the join can use the index.
            // idx_review_category's leftmost column already covers review_id lookups.
            $sql = "CREATE TABLE $relationshipsTable (
                `id`          bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `review_id`   int(11) NOT NULL,
                `category_id` bigint(20) NOT NULL,
                UNIQUE KEY `idx_review_category` (`review_id`, `category_id`),
                INDEX `idx_category_id` (`category_id`)
            ) $charsetCollate;";
            dbDelta($sql);
        }

        // The memo predates the CREATE TABLEs above.
        static::$tableExistsCache = [];

        // A just-created table can't have been backfilled, so clear any stale state.
        // This is how a site recovers from the tables being dropped.
        if (!$categoriesTableExisted || !$relationshipsTableExisted) {
            delete_option(static::$backfillOption);
            delete_option(static::$backfillCursorOption);
            delete_option(static::$orphanSweepPendingOption);
        }

        // Table creation is cheap and runs anywhere. The backfill and sweep are real work,
        // so they only run on maintenance requests.
        if (static::isMaintenanceRequest()) {
            static::maybeBackfillFromCategoryColumn($categoriesTable, $relationshipsTable);

            // Nothing can be orphaned until the backfill is done.
            if (static::isBackfilled()) {
                static::sweepOrphanedRelationships($categoriesTable, $relationshipsTable);
            }
        } elseif (static::isBackfilled()) {
            // Defer the sweep to the next maintenance request.
            static::markOrphanSweepPending();
        }
    }

    /**
     * Flags a full sweep as owed without resetting one already in flight.
     */
    protected static function markOrphanSweepPending()
    {
        if (get_option(static::$orphanSweepPendingOption, null) === null) {
            update_option(static::$orphanSweepPendingOption, 0, 'no');
        }
    }

    /**
     * Runs any owed sweep (deferred by migrate() or cut short by the per-request cap)
     * so cleanup finishes across admin requests. One option read when nothing is pending.
     */
    public static function maybeContinueOrphanSweep()
    {
        global $wpdb;

        // Pending means the option exists; cursor 0 is falsy but still pending.
        if (get_option(static::$orphanSweepPendingOption, null) === null) {
            return;
        }

        if (!static::isBackfilled()) {
            return;
        }

        static::sweepOrphanedRelationships(
            $wpdb->prefix . static::$categoriesTable,
            $wpdb->prefix . static::$relationshipsTable
        );
    }

    /**
     * Deletes pivot rows whose category or review is gone (races the models' cleanup
     * can't catch), in bounded id batches. On the cap or a query error the cursor is
     * persisted for maybeContinueOrphanSweep().
     */
    protected static function sweepOrphanedRelationships($categoriesTable, $relationshipsTable)
    {
        global $wpdb;

        $reviewsTable = $wpdb->prefix . 'wpsr_reviews';

        $lastId = (int) get_option(static::$orphanSweepPendingOption, 0);

        for ($batch = 0; $batch < static::$backfillMaxChunksPerRequest; $batch++) {
            // The inner LIMIT caps rows inspected, not orphans found, so a clean table
            // can't be scanned end to end in one batch.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded orphan cleanup; no FK to cascade
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT r.id AS rel_id, c.id AS category_match, rv.id AS review_match
                 FROM (
                     SELECT id, review_id, category_id FROM `$relationshipsTable`
                     WHERE id > %d
                     ORDER BY id ASC
                     LIMIT %d
                 ) r
                 LEFT JOIN `$categoriesTable` c ON c.id = r.category_id
                 LEFT JOIN `$reviewsTable` rv ON rv.id = r.review_id
                 ORDER BY r.id ASC",
                $lastId,
                static::$backfillChunkSize
            ));

            // get_results returns [] on error too, so check the error before "empty".
            if ($wpdb->last_error) {
                update_option(static::$orphanSweepPendingOption, $lastId, 'no');
                return;
            }

            if (empty($rows)) {
                delete_option(static::$orphanSweepPendingOption);
                return;
            }

            $orphanIds = [];
            foreach ($rows as $row) {
                if ($row->category_match === null || $row->review_match === null) {
                    $orphanIds[] = (int) $row->rel_id;
                }
            }

            if (!empty($orphanIds)) {
                $placeholders = implode(', ', array_fill(0, count($orphanIds), '%d'));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded orphan cleanup; no FK to cascade
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM `$relationshipsTable` WHERE id IN ($placeholders)",
                    $orphanIds
                ));

                if ($wpdb->last_error) {
                    // $lastId still points before this page, so this page is retried.
                    update_option(static::$orphanSweepPendingOption, $lastId, 'no');
                    return;
                }
            }

            $lastRow = end($rows);
            $lastId = (int) $lastRow->rel_id;

            // A short page means the scan reached the end of the table.
            if (count($rows) < static::$backfillChunkSize) {
                delete_option(static::$orphanSweepPendingOption);
                return;
            }
        }

        // Cap reached mid-table.
        update_option(static::$orphanSweepPendingOption, $lastId, 'no');
    }

    /**
     * Runs the backfill once per site, flagging it done only on success. Retrying a
     * partial run is safe: lookups are by slug and pivot writes are INSERT IGNORE.
     */
    protected static function maybeBackfillFromCategoryColumn($categoriesTable, $relationshipsTable)
    {
        // dbDelta fails silently, and a site with nothing to migrate reports success,
        // so without this the flag could claim a taxonomy that doesn't exist.
        if (!static::tablesExist()) {
            return;
        }

        if (get_option(static::$backfillOption)) {
            return;
        }

        if (static::backfillFromCategoryColumn($categoriesTable, $relationshipsTable)) {
            update_option(static::$backfillOption, true, 'no');
        }
    }

    protected static function tableExists($table)
    {
        global $wpdb;

        // Memoised: tablesExist() runs on every front-end render that filters by category.
        if (!isset(static::$tableExistsCache[$table])) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for migration to check table existence
            static::$tableExistsCache[$table] = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
        }

        return static::$tableExistsCache[$table];
    }

    /**
     * Copies the legacy `wpsr_reviews`.`category` column into the taxonomy in bounded
     * id-cursor chunks, resolving only each chunk's slugs. Resumable via the cursor.
     *
     * @return bool True when complete (including nothing to migrate); false if it aborted
     *              partway, so the caller leaves the flag unset and retries.
     */
    protected static function backfillFromCategoryColumn($categoriesTable, $relationshipsTable)
    {
        global $wpdb;
        $reviewsTable = $wpdb->prefix . 'wpsr_reviews';

        // Nothing to migrate from, so done rather than retrying forever.
        if (!static::tableExists($reviewsTable)) {
            return true;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for one-time migration backfill
        $existingColumns = $wpdb->get_col($wpdb->prepare("DESC %i", $reviewsTable), 0);
        // get_col returns [] on error too; a failed DESC must not flag the backfill complete.
        if ($wpdb->last_error) {
            return false;
        }
        if (!in_array('category', $existingColumns, true)) {
            return true;
        }

        $lastId = (int) get_option(static::$backfillCursorOption, 0);

        for ($chunk = 0; $chunk < static::$backfillMaxChunksPerRequest; $chunk++) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for one-time migration backfill
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, category FROM %i WHERE category IS NOT NULL AND category != '' AND id > %d ORDER BY id ASC LIMIT %d",
                $reviewsTable,
                $lastId,
                static::$backfillChunkSize
            ));

            // get_results returns [] on error too; don't mistake it for an exhausted cursor.
            if ($wpdb->last_error) {
                return false;
            }

            if (empty($rows)) {
                delete_option(static::$backfillCursorOption);
                return true;
            }

            $slugByReview = [];
            $nameBySlug = [];
            foreach ($rows as $row) {
                // Advance before any skip, or a skipped row is re-selected forever.
                $lastId = (int) $row->id;

                $slug = static::categorySlug($row->category);
                if ($slug === '') {
                    continue;
                }
                $slugByReview[(int) $row->id] = $slug;
                if (!isset($nameBySlug[$slug])) {
                    $nameBySlug[$slug] = static::categoryName($row->category);
                }
            }

            if (!empty($nameBySlug)) {
                $idBySlug = static::resolveSlugIds($categoriesTable, $nameBySlug);
                if ($idBySlug === false) {
                    return false;
                }

                $pairs = [];
                foreach ($slugByReview as $reviewId => $slug) {
                    if (isset($idBySlug[$slug])) {
                        $pairs[] = [$reviewId, $idBySlug[$slug]];
                    }
                }

                if ($pairs && !static::insertRelationships($relationshipsTable, $pairs)) {
                    return false;
                }
            }

            // Commit per chunk so a failure or the cap resumes from here, via the
            // plugins_loaded gate in boot/app.php.
            update_option(static::$backfillCursorOption, $lastId, 'no');
        }

        return false;
    }

    /**
     * Mirrors ReviewCategory::makeSlug(), inlined so the migration doesn't depend on
     * the app models.
     */
    protected static function categorySlug($value)
    {
        return substr(sanitize_title(trim((string) $value)), 0, 191);
    }

    /**
     * The display name a legacy value stores under, capped to the column width.
     */
    protected static function categoryName($value)
    {
        $name = trim((string) $value);

        return function_exists('mb_substr')
            ? mb_substr($name, 0, 191, 'UTF-8')
            : substr($name, 0, 191);
    }

    /**
     * Resolves a chunk's slugs to ids: SELECT the existing ones, INSERT IGNORE the
     * rest, then read them back.
     *
     * @param array $nameBySlug [slug => display name].
     * @return array|false [slug => id] covering every input slug, or false on a query error.
     */
    protected static function resolveSlugIds($categoriesTable, array $nameBySlug)
    {
        $idBySlug = static::selectSlugIds($categoriesTable, array_keys($nameBySlug));
        if ($idBySlug === false) {
            return false;
        }

        $missing = array_diff_key($nameBySlug, $idBySlug);
        if (!empty($missing)) {
            if (!static::insertCategories($categoriesTable, $missing)) {
                return false;
            }

            $created = static::selectSlugIds($categoriesTable, array_keys($missing));
            if ($created === false) {
                return false;
            }
            $idBySlug += $created;
        }

        // An unresolved slug would skip its reviews and advance the cursor past them.
        // Abort so the next run retries.
        if (count($idBySlug) < count($nameBySlug)) {
            return false;
        }

        return $idBySlug;
    }

    /**
     * @param array $slugs Slugs to look up.
     * @return array|false [slug => id] for the ones that exist, or false on a query error.
     */
    protected static function selectSlugIds($categoriesTable, array $slugs)
    {
        global $wpdb;

        if (empty($slugs)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($slugs), '%s'));
        $args = array_merge([$categoriesTable], array_values($slugs));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for one-time migration backfill
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, slug FROM %i WHERE slug IN ($placeholders)",
            $args
        ));
        // get_results returns [] on error too; fail so the caller retries.
        if ($wpdb->last_error) {
            return false;
        }

        $map = [];
        foreach ($results as $result) {
            $map[$result->slug] = (int) $result->id;
        }

        return $map;
    }

    /**
     * Bulk-inserts categories in one statement. INSERT IGNORE so a slug that already
     * exists doesn't abort the batch.
     *
     * @param array $nameBySlug [slug => display name] to create.
     * @return bool False only on an outright query error.
     */
    protected static function insertCategories($categoriesTable, array $nameBySlug)
    {
        global $wpdb;

        $now = current_time('mysql');
        $placeholders = [];
        $values = [$categoriesTable];
        foreach ($nameBySlug as $slug => $name) {
            $placeholders[] = '(%s, %s, %s, %s)';
            array_push($values, $name, $slug, $now, $now);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for one-time migration backfill
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO %i (name, slug, created_at, updated_at) VALUES " . implode(', ', $placeholders),
            $values
        ));

        return $inserted !== false;
    }

    /**
     * Writes a chunk's review/category links in one statement.
     *
     * @param array $pairs List of [reviewId, categoryId].
     *
     * @return bool False only on an outright query error.
     */
    protected static function insertRelationships($relationshipsTable, array $pairs)
    {
        global $wpdb;

        $values = [$relationshipsTable];
        foreach ($pairs as $pair) {
            $values[] = $pair[0];
            $values[] = $pair[1];
        }

        $placeholders = implode(', ', array_fill(0, count($pairs), '(%d, %d)'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for one-time migration backfill
        $linked = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO %i (review_id, category_id) VALUES $placeholders",
            $values
        ));

        // Ignored duplicates report 0 affected rows, which is success here.
        return $linked !== false;
    }
}
