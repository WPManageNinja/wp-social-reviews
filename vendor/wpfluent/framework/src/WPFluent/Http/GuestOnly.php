<?php

namespace WPSocialReviews\Framework\Http;

use Closure;
use InvalidArgumentException;
use WPSocialReviews\Framework\Http\Request\Request;
use WPSocialReviews\Framework\Foundation\Application;

/**
 * The policy guestOnly() gives a route or group: a visitor who isn't logged
 * in passes; a logged-in user is refused, with a 403, or on a web route sent
 * to the path or URL guestOnly() was given.
 */
class GuestOnly
{
    /**
     * Make the policy.
     *
     * @param  string|\Closure|null  $redirectTo  A path under the web routes,
     *     or a URL on the site; a closure, run for a logged-in visitor only,
     *     whose answer is the policy's (it gets what it type-hints); null
     *     refuses instead.
     * @return \Closure
     *
     * @throws \InvalidArgumentException For anything else.
     */
    public static function policy($redirectTo = null)
    {
        if ($redirectTo !== null && !$redirectTo instanceof Closure && (!is_string($redirectTo) || $redirectTo === '')) {
            throw new InvalidArgumentException(
                'guestOnly() takes the path or URL to send a logged-in visitor to, a closure that answers for them, or nothing to refuse them.'
            );
        }

        return function (Request $request, Application $app) use ($redirectTo) {
            if (!$request->user()->exists()) {
                return true;
            }

            if ($redirectTo instanceof Closure) {
                return $app->call($redirectTo);
            }

            // A REST route answers with a refusal; only a web route redirects.
            if ($redirectTo === null || $request->isRest() || !$app->bound('redirect')) {
                return false;
            }

            // A full URL stays on the site, as every redirect of a policy should.
            $to = preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $redirectTo)
                ? wp_validate_redirect($redirectTo, home_url('/'))
                : $redirectTo;

            return $app->make('redirect')->to($to);
        };
    }
}
