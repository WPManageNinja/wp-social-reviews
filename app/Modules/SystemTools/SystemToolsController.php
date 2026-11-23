<?php

namespace WPSocialReviews\App\Modules\SystemTools;

use WPSocialReviews\App\Hooks\Handlers\ActivateCronEvent;
use WPSocialReviews\App\Http\Controllers\Controller;
use WPSocialReviews\Framework\Http\Request\Request;

/**
 * Settings → System Admin Tools: one endpoint per page, plus the full report for "Copy System Report".
 */
class SystemToolsController extends Controller
{
    const RUN_CRON_LOCK = 'wpsr_system_tools_cron_lock';

    const RUN_CRON_LOCK_TTL = 300;

    public function index()
    {
        return [
            'report' => [
                'environment' => (new Environment())->getReport(),
                'cron'        => (new CronMonitor())->getReport(),
                'platforms'   => (new PlatformHealth())->getReport(),
                'database'    => (new DatabaseHealth())->getReport(),
            ]
        ];
    }

    /**
     * Answers GET, POST, PUT and DELETE alike, so the page can tell which methods the host lets through.
     */
    public function restCheck()
    {
        return [
            'ok' => true
        ];
    }

    public function getCron()
    {
        return [
            'cron'       => (new CronMonitor())->getReport(),
            'server'     => (new Environment())->getServerInfo(),
            'can_manage' => current_user_can('manage_options'),
        ];
    }

    public function getPlatforms()
    {
        return [
            'platforms' => (new PlatformHealth())->getReport()
        ];
    }

    public function getDatabase()
    {
        return [
            'database' => (new DatabaseHealth())->getReport()
        ];
    }

    public function runCron(Request $request)
    {
        $hook = sanitize_text_field($request->get('hook', ''));
        $jobs = CronMonitor::coreCronHooks();

        if (empty($hook) || empty($jobs[$hook]['can_run'])) {
            return $this->sendError([
                'message' => __('This scheduled task cannot be run from here.', 'wp-social-reviews')
            ], 422);
        }

        if (!$this->acquireRunLock()) {
            return $this->sendError([
                'message' => __('Another scheduled task is still running. Please wait a moment and try again.', 'wp-social-reviews')
            ], 409);
        }

        try {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- $hook is a can_run key of coreCronHooks(), all wpsr_ prefixed
            do_action($hook);
        } finally {
            delete_option(static::RUN_CRON_LOCK);
        }

        return [
            /* translators: %s: scheduled task name, for example "Refresh feeds and reviews" */
            'message' => sprintf(__('"%s" ran successfully.', 'wp-social-reviews'), $jobs[$hook]['label']),
            'cron'    => (new CronMonitor())->getReport()
        ];
    }

    /**
     * Takes the lock in one query each, so two requests cannot both get it.
     * add_option() reads before it writes, so it cannot do this.
     */
    protected function acquireRunLock()
    {
        global $wpdb;

        $now = time();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock, must skip the option cache
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            static::RUN_CRON_LOCK,
            (string) $now
        ));

        if ($inserted) {
            return true;
        }

        // A lock older than the limit belongs to a run that died, so take it over.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock, must skip the option cache
        return (bool) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d",
            (string) $now,
            static::RUN_CRON_LOCK,
            $now - static::RUN_CRON_LOCK_TTL
        ));
    }

    public function rescheduleCrons()
    {
        (new ActivateCronEvent())->activate();

        return [
            'message' => __('The scheduled tasks are scheduled again.', 'wp-social-reviews'),
            'cron'    => (new CronMonitor())->getReport()
        ];
    }
}
