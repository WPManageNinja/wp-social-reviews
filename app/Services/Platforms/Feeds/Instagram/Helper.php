<?php

namespace WPSocialReviews\App\Services\Platforms\Feeds\Instagram;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class Helper
{
    /**
     *
     * Resolve the header layout CSS class from the feed settings.
     * 'classic' keeps the original header, 'minimal' renders the flat header.
     *
     * @param $feed_settings
     *
     * @return string
     *
     **/
    public static function getHeaderLayoutClass($feed_settings = [])
    {
        $layout = Arr::get($feed_settings, 'header_settings.header_layout', 'classic');
        if (!in_array($layout, array('classic', 'minimal', 'inline'), true)) {
            $layout = 'classic';
        }

        if ($layout === 'inline') {
            return 'wpsr-ig-header-layout-minimal wpsr-ig-header-layout-inline';
        }

        return 'wpsr-ig-header-layout-' . $layout;
    }

    /**
     * Templates 5 and 6 reuse the views and styles of templates 2 and 1, so they carry that class too.
     *
     * @param string $template
     * @return string
     */
    public static function getTemplateClass($template = '')
    {
        $base_templates = array('template5' => 'template2', 'template6' => 'template1');
        $class = 'wpsr-ig-feed-' . $template;

        return isset($base_templates[$template]) ? 'wpsr-ig-feed-' . $base_templates[$template] . ' ' . $class : $class;
    }

    /**
     * @param string $scheme
     * @return string One of the colour schemes styled in _color-schemes.scss, 'light' otherwise.
     */
    public static function sanitizeColorScheme($scheme = '')
    {
        $schemes = array('light', 'dark', 'shadow', 'soft', 'bordered');

        return in_array($scheme, $schemes, true) ? $scheme : 'light';
    }

    public static function getColorSchemeClass($feed_settings = [])
    {
        return 'wpsr-ig-scheme-' . static::sanitizeColorScheme(Arr::get($feed_settings, 'color_scheme', 'light'));
    }

    public static function getUserAccounts($settings)
    {
        //for header
        $connected_accounts = isset($settings['source_settings']['account_ids']) ? $settings['source_settings']['account_ids'] : array();
        $header_account1    = (count($connected_accounts) && isset($connected_accounts[0])) ? $connected_accounts[0] : '';
        $header_account2    = isset($settings['header_settings']['account_to_show']) ? $settings['header_settings']['account_to_show'] : $header_account1;

        $connected_account = $header_account2;

        if (empty($connected_account)) {
            $connected_account = $header_account1;
        }

        $allAccounts = (new Common())->findConnectedAccounts();

        $headerAccountId = isset($allAccounts[$connected_account]) ? $connected_account : '';

        $actualConnectedIds = array_keys($allAccounts);
        $actualConnectedIds = array_map('strval', $actualConnectedIds);

        if (empty($headerAccountId) && count($actualConnectedIds)) {
            $headerAccountId = $actualConnectedIds[0];
        }

        if (empty($connected_accounts) && count($actualConnectedIds)) {
            $connected_accounts[] = $actualConnectedIds[0];
        }

        return array(
            'account_ids'          => $connected_accounts,
            'connected_account_id' => $headerAccountId,
        );
    }

    public static function getUserAccountInfo($settings = [])
    {
        $configs            = get_option('wpsr_instagram_verification_configs', []);
        $account_to_show    = Arr::get($settings, 'header_settings.account_to_show', null);
        $connected_accounts = Arr::get($configs, 'connected_accounts', []);

        return Arr::get($connected_accounts, $account_to_show, []);
    }
}