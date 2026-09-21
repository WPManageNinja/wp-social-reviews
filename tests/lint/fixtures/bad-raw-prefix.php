<?php
/**
 * LINT FIXTURE — INTENTIONALLY BROKEN. NOT PRODUCTION CODE. NEVER LOADED.
 *
 * Includes real regressions from commit 403534031 plus multiline coverage.
 * Detector must report four hits and no correct examples below.
 * Proof: php tests/lint/raw-sql-prefix.php tests/lint/fixtures
 */

// Bug 1 — ListsController::index. Killed GET /lists?with[]=subscribersCount.
$countRows = wpsrDb()->table('wpsr_subscriber_pivot')
    ->leftJoin('wpsr_subscribers', 'wpsr_subscribers.id', '=', 'wpsr_subscriber_pivot.subscriber_id')
    ->select([
        wpsrDb()->raw('COUNT(*) as total_count'),
        wpsrDb()->raw("SUM(CASE WHEN wpsr_subscribers.status = 'subscribed' THEN 1 ELSE 0 END) as subscribed_count")
    ])
    ->get();

// Bug 2 — ReportingController::getAutomationReports. Killed GET /reports/automations.
$topAutomations = Review::join('wpsr_funnels', function ($join) {
})
    ->selectRaw('wpsr_funnel_subscribers.funnel_id, wpsr_funnels.title, COUNT(*) as trigger_count')
    ->get();

// Bug 2b — the conditional aggregate variant.
$globalAggregate = Review::selectRaw("SUM(CASE WHEN wpsr_funnel_subscribers.status = 'completed' THEN 1 ELSE 0 END) as completed")
    ->first();

// Bug 3 — multiline raw calls must not bypass the detector.
$multiline = Review::selectRaw(
    "SUM(CASE WHEN wpsr_reviews.rating = 5 THEN 1 ELSE 0 END) as five_star"
)->first();

// --- The following are CORRECT and must NOT be flagged (false-positive guard) ---

global $wpdb;
$t = $wpdb->prefix . 'wpsr_subscribers';
$ok1 = wpsrDb()->raw("SUM(CASE WHEN `{$t}`.`status` = 'subscribed' THEN 1 ELSE 0 END) as c");

// Bare column names on a single-table query are fine.
$ok2 = wpsrDb()->raw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent");
$ok3 = wpsrDb()->raw('COUNT(*) as total');

/**
 * NOT a violation — the prefix is concatenated immediately before the literal,
 * so the variable sits outside the quotes. Confirmed against WPFluent's
 * Reporting.php, which the lint originally false-positived on. If the lint
 * starts reporting this line, the concatenation check has regressed.
 */
function fixtureConcatenatedPrefixIsFine()
{
    global $wpdb;
    $tablePrefix = $wpdb->prefix;

    return db()->raw('COUNT(' . $tablePrefix . 'wpsr_subscribers.id) AS count');
}
