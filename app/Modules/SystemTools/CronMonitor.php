<?php

namespace WPSocialReviews\App\Modules\SystemTools;

use WPSocialReviews\Framework\Support\Arr;

/**
 * The plugin's scheduled tasks, for the Cron Job Monitor page.
 */
class CronMonitor
{
    const OVERDUE_GRACE = 900;

    /**
     * Every core cron hook ActivateCronEvent schedules, so a missing one shows as an error.
     * A can_run hook may be run by hand and needs a listener in app/Hooks/actions.php.
     *
     * @return array<string, array{label: string, can_run: bool}> Keyed by hook name.
     */
    public static function coreCronHooks()
    {
        return [
            'wpsr_cron_job'                        => [
                'label'   => __('Refresh feeds and reviews', 'wp-social-reviews'),
                'can_run' => true,
            ],
            'wpsr_do_email_report_scheduled_tasks' => [
                'label'   => __('Send email reports', 'wp-social-reviews'),
                'can_run' => true,
            ],
            'wpsr_scheduled_weekly'                => [
                'label'   => __('Weekly cleanup', 'wp-social-reviews'),
                'can_run' => true,
            ],
            'wpsr_scheduled_twicedaily'            => [
                'label'   => __('Twice-daily tasks', 'wp-social-reviews'),
                'can_run' => false,
            ],
        ];
    }

    public function getReport()
    {
        $now = time();
        $core = static::coreCronHooks();
        $events = [];

        foreach ($this->getScheduledEvents() as $hook => $event) {
            $events[$hook] = $this->cronEvent($hook, $event, $core, $now);
        }

        foreach (array_keys($core) as $hook) {
            if (!isset($events[$hook])) {
                $events[$hook] = $this->cronEvent($hook, null, $core, $now);
            }
        }

        // Core hooks first, then the per-platform sync hooks by name.
        uasort($events, function ($a, $b) {
            if ($a['is_core'] !== $b['is_core']) {
                return $a['is_core'] ? -1 : 1;
            }
            return strcmp($a['hook'], $b['hook']);
        });

        return [
            'has_server_cron' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'events'          => array_values($events),
        ];
    }

    /**
     * Every scheduled event whose hook starts with "wpsr_", keyed by hook. Keeps the earliest run.
     */
    protected function getScheduledEvents()
    {
        $crons = function_exists('_get_cron_array') ? _get_cron_array() : [];
        $events = [];

        if (!is_array($crons)) {
            return $events;
        }

        foreach ($crons as $timestamp => $hooks) {
            if (!is_array($hooks)) {
                continue;
            }

            foreach ($hooks as $hook => $instances) {
                if (strpos((string) $hook, 'wpsr_') !== 0 || isset($events[$hook]) || !is_array($instances)) {
                    continue;
                }

                $instance = reset($instances);
                $events[$hook] = [
                    'timestamp' => (int) $timestamp,
                    'schedule'  => (string) Arr::get($instance, 'schedule', ''),
                    'interval'  => (int) Arr::get($instance, 'interval', 0),
                ];
            }
        }

        return $events;
    }

    protected function cronEvent($hook, $event, array $core, $now)
    {
        $isCore = isset($core[$hook]);
        $timestamp = $event ? (int) $event['timestamp'] : 0;
        $isPast = $event && $timestamp < $now;
        $isOverdue = $event && ($now - $timestamp) > max(static::OVERDUE_GRACE, (int) $event['interval']);

        $status = 'ok';
        if (!$event) {
            $status = 'error';
        } elseif ($isOverdue) {
            $status = 'warning';
        }

        return [
            'hook'         => $hook,
            'label'        => $isCore ? $core[$hook]['label'] : $this->cronLabel($hook),
            'is_core'      => $isCore,
            'is_scheduled' => (bool) $event,
            'schedule'     => $event ? $this->scheduleLabel($event['schedule'], $event['interval']) : '',
            'next_run_in'  => $event ? $this->relativeTime($timestamp, $now) : '',
            'is_past'      => (bool) $isPast,
            'is_overdue'   => (bool) $isOverdue,
            'can_run'      => $isCore && $core[$hook]['can_run'],
            'status'       => $status,
        ];
    }

    protected function cronLabel($hook)
    {
        $labels = [
            'wpsr_cleanup_orphan_uploads' => __('Clean up unused review form uploads', 'wp-social-reviews'),
        ];

        if (isset($labels[$hook])) {
            return $labels[$hook];
        }

        if (preg_match('/^wpsr_(.+)_(reviews|feed)_update$/', $hook, $match)) {
            /* translators: %s: platform name, for example "Google" */
            return sprintf(__('Sync %s', 'wp-social-reviews'), PlatformHealth::platformName($match[1]));
        }

        return $hook;
    }

    protected function scheduleLabel($schedule, $interval)
    {
        if ($schedule === '') {
            return __('Once', 'wp-social-reviews');
        }

        $schedules = wp_get_schedules();
        if (isset($schedules[$schedule]['display'])) {
            return (string) $schedules[$schedule]['display'];
        }

        return $interval ? human_time_diff(0, $interval) : $schedule;
    }

    protected function relativeTime($timestamp, $now)
    {
        if ($timestamp >= $now) {
            /* translators: %s: time span, for example "5 mins" */
            return sprintf(__('in %s', 'wp-social-reviews'), human_time_diff($now, $timestamp));
        }

        /* translators: %s: time span, for example "5 mins" */
        return sprintf(__('%s ago', 'wp-social-reviews'), human_time_diff($timestamp, $now));
    }
}
