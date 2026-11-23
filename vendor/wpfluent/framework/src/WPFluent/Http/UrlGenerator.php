<?php

namespace WPSocialReviews\Framework\Http;

use InvalidArgumentException;
use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\Framework\Support\DateTime;

class UrlGenerator
{
    /**
     * The application instance.
     *
     * @var \WPSocialReviews\Framework\Foundation\App|null
     */
    protected $app = null;
    

    /**
     * The encrypter instance.
     * @var \WPSocialReviews\Framework\Encryption\Encrypter|null
     */
    protected $encrypter = null;

    /**
     * Create a new URL Generator instance.
     *
     * @param  \WPSocialReviews\Framework\Foundation\App|null  $app
     * @param  \WPSocialReviews\Framework\Encryption\Encrypter|null  $encrypter
     */
    public function __construct($app = null, $encrypter = null)
    {
        $this->app = $app ?: App::getInstance();
        $this->encrypter = $encrypter;
    }

    /**
     * Sign a URL
     * 
     * @param  string $url
     * @param  array $params
     * @return string
     */
    public function sign($url, $params = [])
    {
        $encrypter = $this->resolveEncrypter();

        $url = $this->normalizeUrl($url);

        [$baseUrl, $query] = $this->extractUrlParts($url);

        $params = $this->validateExpiryTime(array_merge($query, $params));

        $payload = $encrypter->encrypt(http_build_query($params));

        $signature = hash_hmac('sha256', $this->signedTarget($url) . '|' . $payload, $encrypter->getKey());

        // URL-encode via http_build_query so payload/signature stay safe
        // even if their alphabet ever includes '+', '/', or '='. Old-style
        // URLs (raw values) keep verifying because parse_str on the verify
        // side decodes both forms identically.
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query([
            '_data' => $payload,
            '_signature' => $signature,
        ]);
    }

    /**
     * Normalize URL — handle relative routes, slugs, and REST routes.
     * 
     * @param  string $url
     * @return string
     */
    protected function normalizeUrl($url)
    {
        // Already a full URL: it starts with 'http://' or 'https://', in
        // any case (i), so 'HTTPS://example.com' counts too.
        if (preg_match('#^(http|https)://#i', $url)) {
            return $url;
        }

        return $this->buildRestBaseUrl()
            . $this->buildNamespaceSegment()
            . '/' . ltrim($url, '/');
    }

    /**
     * Extract base URL and query array from a full URL.
     *
     * @param  string $url
     * @return array
     */
    protected function extractUrlParts($url)
    {
        $parts = parse_url($url);

        $base = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '');

        parse_str($parts['query'] ?? '', $query);

        // Without pretty permalinks the route is in ?rest_route=: it stays
        // in the URL, where WordPress reads it, instead of being signed.
        if (isset($query['rest_route'])) {
            $base .= '?rest_route=' . $query['rest_route'];
            unset($query['rest_route']);
        }

        return [$base, $query];
    }

    /**
     * Normalize the expiry time.
     *
     * @param  array $params
     * @return array
     * @throws InvalidArgumentException
     *
     * @phpstan-ignore-next-line
     */
    public function validateExpiryTime(array $params): array
    {
        if (!isset($params['expires_at'])) {
            return $params; // Nothing to validate
        }

        $expiresAt = $params['expires_at'];

        // Convert string date/time to timestamp
        if (is_string($expiresAt)) {
            $expiresAt = strtotime($expiresAt);
            if ($expiresAt === false) {
                throw new InvalidArgumentException(
                    'The expiry time string is invalid.'
                );
            }

        // Convert DateTime object to timestamp
        } elseif ($expiresAt instanceof DateTime) {
            $expiresAt = $expiresAt->getTimestamp();

        // Numeric values are treated as absolute timestamps
        } elseif (is_numeric($expiresAt)) {
            $expiresAt = (int) $expiresAt;

        // Anything else is invalid
        } else {
            throw new InvalidArgumentException(
                'The expiry time must be a string, DateTime, or numeric timestamp.'
            );
        }

        // Check if the expiry time is in the past
        if ($expiresAt <= time()) {
            throw new InvalidArgumentException('The expiry time has already passed.');
        }

        $params['expires_at'] = $expiresAt;

        return $params;
    }

    /**
     * Validate a URL
     * 
     * @param  string $url
     * @return mixed (false or array)
     */
    public function validate($url)
    {
        if (!$query = $this->parseUrlAndGetQuery($url)) {
            return false;
        }

        if (!isset($query['_data']) || !isset($query['_signature'])) {
            return false;
        }

        return $this->verifySignature($query['_data'], $query['_signature'], $this->signedTarget($url));
    }

    /**
     * Parse query string from the url.
     * 
     * @param  string $url
     * @return mixed (bool or array)
     */
    public function parseUrlAndGetQuery($url)
    {
        $parts = parse_url($url);

        if (!isset($parts['query'])) {
            return false;
        }

        parse_str($parts['query'], $query);

        return $query;
    }

    /**
     * What a signature is made for besides the values: the URL's path and,
     * with plain permalinks, its ?rest_route=, as in Laravel, so a link
     * signed for one route is refused on another. Scheme, host and a
     * trailing slash are left out, so a proxy or a www redirect doesn't
     * break a link.
     *
     * @param  string $url
     * @return string
     */
    protected function signedTarget($url)
    {
        $parts = parse_url($url);

        $target = rtrim(rawurldecode($parts['path'] ?? ''), '/');

        parse_str($parts['query'] ?? '', $query);

        if (isset($query['rest_route']) && is_string($query['rest_route'])) {
            $target .= '?rest_route=/' . trim($query['rest_route'], '/');
        }

        return $target;
    }

    /**
     * Verify the signature, made for the target (signedTarget()) the URL
     * is on.
     * 
     * @param  string $data
     * @param  string $signature
     * @param  string $target
     * @return mixed (bool or array)
     */
    public function verifySignature($data, $signature, $target = '')
    {
        $encrypter = $this->resolveEncrypter();

        $expected = hash_hmac('sha256', $target . '|' . $data, $encrypter->getKey());

        if (!hash_equals($expected, $signature)) return false;

        parse_str($encrypter->decrypt($data), $params);

        $expiresAt = $params['expires_at'] ?? null;

        if (is_numeric($expiresAt) && time() > (int) $expiresAt) {
            return false;
        }

        return empty($params) ? true : $params;
    }

    /**
     * Generate a full REST URL from a named route.
     *
     * @param  string  $nameOrPath   The name of the route or path.
     * @param  array   $params Optional parameters to fill in the placeholders.
     * @param  array   $query  Optional query parameters to append to the URL.
     * @return string|null     The full REST URL or null if the route doesn't exist.
     */
    public function route($nameOrPath, $params = [], $query = [])
    {
        // @phpstan-ignore-next-line
        $route = $this->app->router->getByName($nameOrPath);

        if (!$route) {
            if (!str_contains($nameOrPath, '/')) {
                return;
            }
            $path = '/' . trim($nameOrPath, '/');
        } else {
            $path = $this->buildPath($route->uri(), $params);
        }

        $fullUrl = $this->buildFullUrl($path);

        return $this->appendQueryString($fullUrl, $query);
    }

    /**
     * Resolve the encrypter.
     * 
     * @return \WPSocialReviews\Framework\Encryption\Encrypter
     */
    protected function resolveEncrypter()
    {
        if (!$this->encrypter) {
            $this->encrypter = App::make('encrypter');
        }

        return $this->encrypter;
    }

    /**
     * Build the full URL including base REST path,
     * namespace, version, and route path.
     *
     * @param  string  $path
     * @return string
     */
    protected function buildFullUrl($path)
    {
        $restUrl = $this->buildRestBaseUrl();

        $namespaceSegment = $this->buildNamespaceSegment();

        return $restUrl . $namespaceSegment . $path;
    }

    /**
     * Get the base REST URL as WordPress gives it: https://example.com/wp-json
     * with pretty permalinks, https://example.com/index.php?rest_route= without
     * them, where /wp-json/ is a 404.
     *
     * @return string
     */
    protected function buildRestBaseUrl()
    {
        return rtrim(rest_url(), '/');
    }

    /**
     * Build the namespace and version segment of the REST URL.
     *
     * @return string
     */
    protected function buildNamespaceSegment()
    {
        // @phpstan-ignore-next-line
        $ns  = trim($this->app->config->get('app.rest_namespace'), '/');

        // @phpstan-ignore-next-line
        $ver = trim($this->app->config->get('app.rest_version'), '/');

        return "/{$ns}/{$ver}";
    }

    /**
     * Replace route placeholders in the URI with the provided parameters.
     *
     * @param  string  $template
     * @param  array   $params
     * @return string
     */
    protected function buildPath($template, &$params)
    {
        // Fill each {name} or {name?} placeholder with $params['name'], or
        // with '' when it's missing: 'users/{id}/posts/{slug?}' with id 5
        // -> 'users/5/posts/'. The name is everything up to the '}', so an
        // optional name keeps its '?', which rtrim() then removes.
        $replaced = preg_replace_callback(
            '/\{([^}]+)\??\}/',
            function ($m) use (&$params) {
                $key = rtrim($m[1], '?');
                return isset($params[$key]) ? $params[$key] : '';
            },
            $template
        );

        return '/' . trim($replaced, '/');
    }

    /**
     * Append query parameters to a URL.
     *
     * @param  string  $url
     * @param  array   $query
     * @return string
     */
    protected function appendQueryString($url, $query)
    {
        if (empty($query)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($query);
    }
}
