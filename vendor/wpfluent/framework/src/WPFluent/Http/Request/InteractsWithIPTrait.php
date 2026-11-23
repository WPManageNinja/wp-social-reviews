<?php

namespace WPSocialReviews\Framework\Http\Request;

/**
 * Trait InteractsWithIPTrait
 *
 * Resolves the real client IP address securely.
 *
 * SECURITY:
 * - By default, NO proxies are trusted.
 * - Forwarded headers are only trusted if REMOTE_ADDR belongs to a trusted proxy.
 * - Safe for normal hosting, Cloudflare (configured), and explicit proxy setups.
 *
 * EXTENSIBILITY:
 * - Developers can add trusted proxies using the 'trusted_proxies' filter.
 */
trait InteractsWithIPTrait
{
    /**
     * Cached resolved IP for the current request.
     *
     * Instance property (not static) so each Request instance gets its own
     * cache. This is equivalent in production (one instance per HTTP request)
     * but avoids cross-request contamination in tests where multiple dispatches
     * share the same PHP process.
     *
     * @var string|null
     */
    protected $resolvedIp = null;

    /**
     * Get client IP.
     *
     * @param bool $anonymize Return anonymized IP if true.
     * @return string
     */
    public function getIp($anonymize = false)
    {
        if ($this->resolvedIp === null) {
            $this->resolvedIp = $this->resolveIp();
        }

        $ip = $anonymize
            ? wp_privacy_anonymize_ip($this->resolvedIp)
            : $this->resolvedIp;

        return $this->app->applyCustomFilters('user_ip', $ip, $anonymize);
    }

    /**
     * Resolve the real client IP.
     *
     * @return string
     */
    protected function resolveIp()
    {
        if (empty($_SERVER['REMOTE_ADDR'])) {
            return '127.0.0.1'; // CLI or unusual environment
        }

        $remoteAddr = $this->sanitize($_SERVER['REMOTE_ADDR']);

        // Only trust forwarded headers if REMOTE_ADDR is a trusted proxy
        if (!$this->isTrustedProxy($remoteAddr)) {
            return $this->isValidIp($remoteAddr) ? $remoteAddr : '127.0.0.1';
        }

        // Behind trusted proxies, X-Forwarded-For is "client, proxy1, ...":
        // each proxy appends the address it saw, and whatever is to the
        // left of what the first trusted proxy appended is the client's own
        // text. So the visitor is the rightmost address that isn't a
        // trusted proxy.
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $chain = array_map('trim', explode(',', $this->sanitize($_SERVER['HTTP_X_FORWARDED_FOR'])));

            foreach (array_reverse($chain) as $ip) {
                if (!$this->isValidIp($ip)) {
                    break;
                }

                if (!$this->isTrustedProxy($ip)) {
                    return $ip;
                }
            }
        }

        // Without X-Forwarded-For, the single-address headers some proxies
        // set (Cloudflare's, nginx's) instead.
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $header) {
            if (empty($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER[$header])) {
                $ip = $this->sanitize($_SERVER[$header]);

                if ($this->isValidIp($ip)) {
                    return $ip;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Determine if REMOTE_ADDR belongs to a trusted proxy.
     *
     * @param string $ip
     * @return bool
     */
    protected function isTrustedProxy($ip)
    {
        if (!$this->isValidIp($ip)) {
            return false;
        }

        foreach ($this->getTrustedProxies() as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get an array of trusted proxy CIDR ranges.
     *
     * Reads from config/trustedproxy.php (proxies key) by default.
     * Can be overridden or extended using the filter:
     * add_filter('trusted_proxies', fn($proxies) => [...$proxies, '10.0.0.1']);
     *
     * @return array
     */
    protected function getTrustedProxies()
    {
        $configured = $this->app->config->get('trustedproxy.proxies', []);
        return $this->app->applyCustomFilters('trusted_proxies', $configured);
    }

    /**
     * Validate IPv4 or IPv6 address.
     *
     * @param string $ip
     * @return bool
     */
    protected function isValidIp($ip)
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP);
    }

    /**
     * Sanitize server values (WordPress compatible).
     *
     * @param string $value
     * @return string
     */
    protected function sanitize($value)
    {
        return sanitize_text_field(wp_unslash($value));
    }

    /**
     * Whether an address is a given one, or within a CIDR range: IPv4
     * ("173.245.48.0/20") or IPv6 ("2001:db8::/32").
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    protected function ipInRange($ip, $cidr)
    {
        if (strpos($cidr, '/') === false) {
            return @inet_pton($ip) !== false && @inet_pton($ip) === @inet_pton($cidr);
        }

        list($subnet, $bits) = explode('/', $cidr, 2);

        $ipBytes = @inet_pton($ip);
        $subnetBytes = @inet_pton($subnet);

        // Both addresses of one family, and a mask that fits it.
        if ($ipBytes === false || $subnetBytes === false || strlen($ipBytes) !== strlen($subnetBytes)) {
            return false;
        }

        $bits = (int) $bits;

        if ($bits < 0 || $bits > strlen($ipBytes) * 8) {
            return false;
        }

        $bytes = intdiv($bits, 8);

        if (substr($ipBytes, 0, $bytes) !== substr($subnetBytes, 0, $bytes)) {
            return false;
        }

        if ($bits % 8 === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $bits % 8)) & 0xFF;

        return (ord($ipBytes[$bytes]) & $mask) === (ord($subnetBytes[$bytes]) & $mask);
    }
}
