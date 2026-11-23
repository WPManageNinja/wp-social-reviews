<?php

namespace WPSocialReviews\App\Modules\SystemTools;

use WPSocialReviews\App\Services\Platforms\PlatformErrorManager;
use WPSocialReviews\App\Services\Platforms\PlatformManager;
use WPSocialReviews\Framework\Support\Arr;

/**
 * Connection state and the last logged error per platform, for the Connected Platforms page.
 */
class PlatformHealth
{
    public function getReport()
    {
        $manager = new PlatformManager();
        $errorManager = new PlatformErrorManager();
        $errors = get_option('wpsr_errors', []);
        $errors = is_array($errors) ? $errors : [];

        $platforms = [];
        $types = array_fill_keys($manager->feedPlatforms(), 'feed') + array_fill_keys($manager->reviewsPlatforms(), 'review');

        foreach ($types as $platform => $type) {
            if (empty($platform)) {
                continue;
            }

            $platformErrors = Arr::get($errors, $platform, []);
            $platformErrors = is_array($platformErrors) ? $platformErrors : [];
            $connected = (bool) $manager->isActivePlatform($platform);
            $connectionErrors = is_array(Arr::get($platformErrors, 'connection')) ? count($platformErrors['connection']) : 0;
            $log = Arr::get($platformErrors, 'error_log', []);
            $lastError = (is_array($log) && $log) ? (string) end($log) : '';

            if (!$connected && !$connectionErrors && $lastError === '') {
                continue;
            }

            $critical = $errorManager->hasCriticalErrors($platform);

            $platforms[] = [
                'platform'          => $platform,
                'name'              => static::platformName($platform),
                'type'              => $type,
                'connected'         => $connected,
                'connection_errors' => $connectionErrors,
                'critical'          => $critical,
                'revoked'           => (string) $errorManager->getRevokedAccounts($platform),
                'last_error'        => wp_strip_all_tags($lastError),
                'status'            => $critical ? 'error' : ($connectionErrors ? 'warning' : 'ok'),
            ];
        }

        return $platforms;
    }

    public static function platformName($platform)
    {
        $names = [
            'facebook_feed' => 'Facebook',
            'youtube'       => 'YouTube',
            'tiktok'        => 'TikTok',
            'twitter'       => 'X (Twitter)',
            'booking.com'   => 'Booking.com',
            'aliexpress'    => 'AliExpress',
            'woocommerce'   => 'WooCommerce',
            'fluent-cart'   => 'FluentCart',
            'tripadvisor'   => 'Tripadvisor',
        ];

        if (isset($names[$platform])) {
            return $names[$platform];
        }

        return ucwords(str_replace(['_', '-'], ' ', $platform));
    }
}
