<?php

namespace WPSocialReviews\App\Services\Platforms\Feeds\Facebook;

use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class Helper
{
    /**
     * Resolve the header layout CSS class from the feed settings.
     * 'classic' keeps the original header, 'minimal' renders the flat header.
     *
     * @param $feed_settings
     *
     * @return string
     **/
    public static function getHeaderLayoutClass($feed_settings = [])
    {
        $layout = Arr::get($feed_settings, 'header_settings.header_layout', 'classic');
        if (!in_array($layout, array('classic', 'minimal'), true)) {
            $layout = 'classic';
        }

        return 'wpsr-fb-feed-header-layout-' . $layout;
    }

    public static function getConncetedSourceList()
    {
        $configs = get_option('wpsr_facebook_feed_connected_sources_config', []);
        $sourceList = Arr::get($configs, 'sources') ? $configs['sources'] : [];
        return $sourceList;
    }

    public static function getTotalFeedReactions($feed = [])
    {
        $sum = 0;
        $sum += Arr::get($feed, 'like.summary.total_count', null);
        $sum += Arr::get($feed, 'love.summary.total_count', null);
        $sum += Arr::get($feed, 'wow.summary.total_count', null);
        $sum += Arr::get($feed, 'haha.summary.total_count', null);
        $sum += Arr::get($feed, 'sad.summary.total_count', null);
        $sum += Arr::get($feed, 'angry.summary.total_count', null);
        return $sum;
    }

    public static function secondsToMinutes($time)
    {
       $hours = floor($time / 3600);
       $minutes = floor(($time - floor($time / 3600) * 3600) / 60);
       $seconds = floor($time - floor($time / 60) * 60);

       $value = "";
       if ($hours > 0) {
          $value .= "" . $hours . ":" . ($hours < 10 ? "0" : "");
       }
       $value .= "" . $minutes . ":" . ($seconds < 10 ? "0" : "");
       $value .= "" . $seconds;

       return $value == '0:00' ? '0:01' : $value;
    }

    /**
     * Facebook only reports this on the /videos edge, so a missing value means
     * the post never carried the flag and the video may still be framed.
     */
    public static function isEmbeddable($feed = [])
    {
        return Arr::get($feed, 'embeddable') !== false;
    }

    /**
     * Build a facebook social-plugin embed URL for a video permalink.
     */
    public static function getVideoEmbedUrl($videoUrl, $args = [])
    {
        if (empty($videoUrl)) {
            return '';
        }

        $args = wp_parse_args($args, ['autoplay' => 1, 'mute' => 0]);
        // add_query_arg() doesn't encode values, and an unencoded query string
        // in the permalink (/watch/?v=123) gets truncated by the plugin endpoint.
        $args = array_merge(['href' => rawurlencode($videoUrl)], $args);

        return add_query_arg($args, 'https://www.facebook.com/plugins/video.php');
    }

    public static function getSiteUrl($attachment = [], $domain = false)
    {
        $url = Arr::get($attachment, 'target.url');
        if($url){
            $query_str = wp_parse_url($url, PHP_URL_QUERY);
            parse_str($query_str, $query_params);
            $site_url = Arr::get($query_params, 'u');
            if($site_url){
                $host = wp_parse_url($site_url);
                return $domain ? $host['host'] : $site_url;
            }
        } else {
            return false;
        }
    }

    public static function generatePhotoAlbumFeedClass($template_meta = [])
    {
        $display_posts = Arr::get($template_meta, 'filters.display_posts', []);

        if(empty($display_posts) || !is_array($display_posts)){
            return '';
        }

        $allowed_combinations = [
            ['photo', 'album'],
            ['photo'],
            ['album']
        ];
        sort($display_posts);

        $class = '';
        foreach ($allowed_combinations as $allowed) {
            sort($allowed);
            if ($display_posts === $allowed) {
                $class = 'wpsr-fb-feed-item-zero-padding';
                break;
            }
        }

        return $class;
    }

    public static function getSourceIDFromCache($cache)
    {
        $cacheValue = Arr::get($cache, 'option_value');
        $firstValue = (count($cacheValue) > 0) ? $cacheValue[0] : '';

        return Arr::get($firstValue, 'from.id', '');
    }

    public static function validateAndRetrieveSingleVideoPlaylistId($singleVideoPlayListURL){

        if (empty($singleVideoPlayListURL)) {
            return false;
        }

        if(is_numeric($singleVideoPlayListURL)){
            return $singleVideoPlayListURL;
        }

        // Expected: https://www.facebook.com/watch/100091416590270/1336242490940769
        // A non-URL parses without a host key.
        $url = wp_parse_url($singleVideoPlayListURL);
        $host = is_array($url) ? ($url['host'] ?? '') : '';
        if ($host === 'www.facebook.com' || $host === 'facebook.com') {
            $pattern = '/\/watch\/(\d+)\/(\d+)(?:\/)?$/';
            if (preg_match($pattern, $singleVideoPlayListURL, $matches)) {
            return $matches[2];
            }
        }
        return false;
    }

    public static function validateAndRetrieveSingleAlbumId($feedType, $singleAlbumId)
    {
        if ($feedType === 'single_album_feed') {
            if (empty($singleAlbumId)) {
                return null;
            }

            if (!is_numeric($singleAlbumId) && !filter_var($singleAlbumId, FILTER_VALIDATE_URL)) {
                return null;
            }

            return static::getAlbumId($singleAlbumId);
        }

        return null;
    }

    public static function getAlbumId($value){

        if(is_numeric($value)){
            return $value;
        }

        // Expected: https://www.facebook.com/media/set/?set=a.145555168501702&type=3

        $url = wp_parse_url($value);
        $host = is_array($url) ? ($url['host'] ?? '') : '';

        if($host === 'www.facebook.com' || $host === 'facebook.com'){
            $pattern = '/set=a\.(\d+)/';
            if (preg_match($pattern, $value, $matches)) {
                return $matches[1];
            }

            return null;
        }
        
    }

    /**
     * Fix common mistakes in relative date input.
     *
     * @param string $dateString The date string to fix.
     * @param string $context The context (start/end date).
     * @return string The fixed date string.
     */
    public static function fixRelativeDateInput($dateString, $context = '')
    {
        if (stripos($dateString, 'now') !== false) {
            return 'now';
        }
        
        // A bare number means days ("50" becomes "-50 days"). It stays positive only
        // for an end date, which may be a future event.
        if (preg_match('/^-?\d+$/', $dateString)) {
            $sign = ($context === 'end' || strpos($dateString, '-') === 0) ? '' : '-';

            return $sign . $dateString . ' days';
        }
        
        if (strpos($dateString, '-') === 0) {
            return $dateString;
        }
        
        // A positive end date may be a future event, so keep it.
        if ($context === 'end' && is_numeric(str_replace(['days', 'weeks', 'months', 'hours'], '', $dateString))) {
            $number = (int)str_replace(['days', 'weeks', 'months', 'hours'], '', $dateString);
            if ($number > 0) {
                return $dateString;
            }
        }
        
        // Anything else unsigned ("5 days", "10 weeks") is meant as the past.
        $patterns = [
            '/(\d+)\s*days?/i' => '-$1 days',
            '/(\d+)\s*weeks?/i' => '-$1 weeks', 
            '/(\d+)\s*months?/i' => '-$1 months',
            '/(\d+)\s*hours?/i' => '-$1 hours',
            '/(\d+)\s*years?/i' => '-$1 years'
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $dateString)) {
                return preg_replace($pattern, $replacement, $dateString);
            }
        }
        
        return $dateString;
    }

    /**
     * Normalize a date for the cache key, so "now" doesn't make a new key every request.
     *
     * @param string $dateString The date string to normalize.
     * @param string $context The context (start/end date).
     * @return int The normalized timestamp for cache key.
     */
    public static function normalizeDateForCache($dateString, $context = '')
    {
        if (stripos($dateString, 'now') !== false) {
            $currentTime = time();
            return (int) (floor($currentTime / 86400) * 86400); // Start of the day
        }
        
        if (strpos($dateString, '-') === 0 || strpos($dateString, '+') === 0) {
            $currentTime = time();
            
            $relativeTimestamp = strtotime($dateString, $currentTime);
            
            if ($relativeTimestamp === false) {
                if (preg_match('/(-?\d+)\s*days?/i', $dateString, $matches)) {
                    $days = (int)$matches[1];
                    $relativeTimestamp = $currentTime + ($days * 86400);
                }
            }
            
            if ($relativeTimestamp !== false) {
                return (int) (floor($relativeTimestamp / 3600) * 3600); // Start of the hour
            }
        }
        
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return (int) (floor($timestamp / 3600) * 3600);
        }
        
        return (int) (floor(time() / 86400) * 86400);
    }
}