<?php

namespace WPSocialReviews\Framework\Http\Middleware;

use Throwable;
use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\Framework\Support\DebugLog;

/**
 * Class RateLimiter
 *
 * Handles per-IP and per-endpoint rate limiting for REST requests.
 *
 * Features:
 * - IP + endpoint aware
 * - Retry-After header for 429 responses
 * - X-RateLimit-Limit and X-RateLimit-Remaining headers
 * - No limit for a logged-in user whose request carries the WordPress
 *   REST nonce, as the admin's own screens send it, nor for OPTIONS requests
 * - Transient-based storage, safe for PHP 7.4+
 */
class RateLimiter
{
    /**
     * Maximum requests allowed per interval.
     *
     * @var int
     */
    protected $limit;

    /**
     * Interval in seconds for rate limiting.
     *
     * @var int
     */
    protected $interval;

    /**
     * Which limit it is ('' for a route's own, 'group' for its group's):
     * each counts apart.
     *
     * @var string
     */
    protected $scope = '';

    /**
     * Constructor.
     *
     * @param int $limit
     * @param int $interval
     * @param string $scope
     */
    public function __construct($limit, $interval, $scope = '')
    {
        $this->scope = (string) $scope;

        if (!is_numeric($interval) || (int) $interval <= 0) {
            DebugLog::warn(sprintf(
                'rateLimit(%s, %s): the interval is a number of seconds, so this limit resets on every '
                . 'request. Use rateLimitPerMinute(), rateLimitHourly() or rateLimitDaily(), or seconds such as 60.',
                var_export($limit, true),
                var_export($interval, true)
            ));
        }

        $this->limit = (int) $limit;
        $this->interval = (int) $interval;
    }

    /**
     * Describe the limit for the route map.
     *
     * @return array  ['limit' => requests, 'seconds' => per interval]
     */
    public function describe()
    {
        return ['limit' => $this->limit, 'seconds' => $this->interval];
    }

    /**
     * Handle incoming request.
     *
     * @param \WPSocialReviews\Framework\Http\Request\Request $request
     * @param callable $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        // OPTIONS, and the site's own screens (cookie + REST nonce), are free
        if ($this->shouldAllow($request)) {
            return $next($request);
        }

        // WordPress runs the permission check again for the Allow header,
        // after the request was answered; that run isn't a request.
        if (doing_filter('rest_post_dispatch')) {
            return $next($request);
        }

        $currentTime = time();

        // Read, count and write under a lock, so requests sent at once take
        // turns and can't all slip under the limit.
        $settings = $this->locked($request, function () use ($request, $currentTime) {
            $settings = $this->getSettings($request, $currentTime);

            // Reset interval if expired, otherwise increment
            if ($this->isIntervalExpired($settings, $currentTime)) {
                $settings = $this->resetRateLimit($currentTime);
            } else {
                $settings['count']++;
            }

            // Update transient with correct TTL
            $this->updateSettings($request, $settings, $currentTime);

            return $settings;
        });

        // Check if limit exceeded
        if ($this->isRateLimitExceeded($settings)) {
            $retryAfter = $this->interval - ($currentTime - $settings['firstTime']);
            $response = $request->abort(429, 'Too many requests.');
            $response->header('Retry-After', max(1, $retryAfter));
            $response->header('X-RateLimit-Limit', $this->limit);
            $response->header('X-RateLimit-Remaining', 0);
            return $response;
        }

        // Inject rate limit headers into the actual route response via WP hook,
        // because $next() in a before-middleware returns bool (permission check
        // result), not the WP_REST_Response produced by the route callback.
        $limit = $this->limit;
        $remaining = max(0, $this->limit - $settings['count']);

        add_filter('rest_post_dispatch', function ($response) use ($limit, $remaining) {
            $response->header('X-RateLimit-Limit', $limit);
            $response->header('X-RateLimit-Remaining', $remaining);
            return $response;
        });

        return $next($request);
    }

    /**
     * Run the count's read and write under a MySQL named lock for this
     * visitor and route. Without one (SQLite), or when it isn't had within
     * two seconds, it runs anyway: a request is never stuck on it.
     *
     * @param  \WPSocialReviews\Framework\Http\Request\Request  $request
     * @param  callable  $callback
     * @return mixed
     */
    protected function locked($request, callable $callback)
    {
        global $wpdb;

        $held = false;
        $name = $this->lockName($request);

        if (!(defined('DB_ENGINE') && DB_ENGINE === 'sqlite')) {
            $suppressed = $wpdb->suppress_errors(true);
            $held = (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $name)) === '1';
            $wpdb->suppress_errors($suppressed);
        }

        try {
            return $callback();
        } finally {
            if ($held) {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
            }
        }
    }

    /**
     * The name of the count's lock: MySQL's are server-wide and at most 64
     * characters, so it is the count's key, hashed.
     *
     * @param  \WPSocialReviews\Framework\Http\Request\Request  $request
     * @return string
     */
    protected function lockName($request)
    {
        return 'wpf_rl_' . md5($this->makeTransientKey($request));
    }

    /**
     * Determine if request should bypass rate limiting.
     *
     * @param \WPSocialReviews\Framework\Http\Request\Request $request
     * @return bool
     */
    protected function shouldAllow($request)
    {
        // HEAD isn't free: WordPress answers it with the GET route, whose
        // controller runs. OPTIONS never reaches the controller.
        return $this->isCookieAuthenticated() || $request->method() === 'OPTIONS';
    }

    /**
     * Whether the request comes from the site's own screens: a valid login
     * cookie with the REST nonce.
     *
     * WordPress sets $wp_rest_auth_cookie to true only for a valid cookie;
     * a failed one holds the reason, such as 'malformed' when there is no
     * cookie at all, as for an application password. A valid cookie without
     * the nonce is logged out before the route runs.
     *
     * @return bool
     */
    protected function isCookieAuthenticated()
    {
        // The site's own screens are its editors' and admins': a subscriber
        // who registered themselves is counted like anyone else.
        return is_user_logged_in()
            && current_user_can('edit_posts')
            && ($GLOBALS['wp_rest_auth_cookie'] ?? null) === true;
    }

    /**
     * Get current rate limit settings from transient.
     *
     * @param \WPSocialReviews\Framework\Http\Request\Request $request
     * @param int $currentTime
     * @return array
     */
    protected function getSettings($request, $currentTime)
    {
        $settings = get_transient($this->makeTransientKey($request));
        return $settings ?: ['count' => 0, 'firstTime' => $currentTime];
    }

    /**
     * Check if interval expired.
     *
     * @param array $settings
     * @param int $currentTime
     * @return bool
     */
    protected function isIntervalExpired($settings, $currentTime)
    {
        return ($currentTime - $settings['firstTime']) > $this->interval;
    }

    /**
     * Reset rate limit for a new interval.
     *
     * @param int $currentTime
     * @return array
     */
    protected function resetRateLimit($currentTime)
    {
        return ['count' => 1, 'firstTime' => $currentTime];
    }

    /**
     * Check if limit exceeded.
     *
     * @param array $settings
     * @return bool
     */
    protected function isRateLimitExceeded($settings)
    {
        return $settings['count'] > $this->limit;
    }

    /**
     * Update transient with proper TTL.
     *
     * @param \WPSocialReviews\Framework\Http\Request\Request $request
     * @param array $settings
     * @param int $currentTime
     */
    protected function updateSettings($request, $settings, $currentTime)
    {
        $ttl = $this->interval - ($currentTime - $settings['firstTime']);
        set_transient(
            $this->makeTransientKey($request),
            $settings,
            max(1, $ttl)
        );
    }

    /**
     * The route the request matched, by method and pattern, so all the
     * URLs of a route, such as /orders/1 and /orders/2, share its limit.
     * Outside a route, the request's own path.
     *
     * @param  \WPSocialReviews\Framework\Http\Request\Request  $request
     * @return string
     */
    protected function endpoint($request)
    {
        // The route the permission check bound in the request's own app,
        // not the App facade's, which may be another plugin's.
        try {
            $route = $request->route();
        } catch (Throwable $e) {
            $route = null;
        }

        if (!is_object($route) || !method_exists($route, 'getUrl')) {
            return $request->getRoute() ?: 'unknown';
        }

        // A HEAD request runs the GET route, so it shares its count.
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();

        return $method . ' ' . $route->getUrl();
    }

    /**
     * Who is counted: an IPv4 address (one written as IPv6 too), or an
     * IPv6 address's /64 block, which one visitor usually holds whole.
     *
     * @param \WPSocialReviews\Framework\Http\Request\Request $request
     * @return string
     */
    protected function visitor($request)
    {
        $ip = (string) $request->getIp();
        $packed = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? @inet_pton($ip) : false;

        if ($packed === false) {
            return $ip;
        }

        if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            return inet_ntop(substr($packed, 12));
        }

        return bin2hex(substr($packed, 0, 8)) . '::/64';
    }

    /**
     * Generate a unique transient key per IP + endpoint.
     *
     * @param \WPSocialReviews\Framework\Http\Request\Request $request
     * @return string
     */
    protected function makeTransientKey($request)
    {
        $slug = App::config()->get('app.slug');

        return "{$slug}_rate_limit_" . md5(
            $this->visitor($request) . '|' . $this->endpoint($request) . ($this->scope !== '' ? '|' . $this->scope : '')
        );
    }
}
