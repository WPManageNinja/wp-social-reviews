<?php

namespace WPSocialReviews\Framework\Support;

/**
 * Tells the developer about a likely mistake the framework lets pass, in
 * the debug log, without changing what the code does.
 *
 * Only with WP_DEBUG on, and each message once per request, so a mistake
 * inside a loop writes one line. It is never a PHP notice: a plugin's
 * error handler can turn notices into exceptions, and that would change
 * behaviour instead of describing it.
 */
class DebugLog
{
    /**
     * The messages written in this request.
     *
     * @var array<string, bool>
     */
    protected static $written = [];

    /**
     * Write a warning to the debug log.
     *
     * @param  string  $message
     * @return void
     */
    public static function warn($message)
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG || isset(static::$written[$message])) {
            return;
        }

        static::$written[$message] = true;

        error_log('[WPFluent] ' . $message);
    }
}
