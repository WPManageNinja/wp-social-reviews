<?php

namespace WPSocialReviews\App\Modules\SystemTools;

use WPSocialReviews\Framework\Support\Arr;

/**
 * Versions, PHP limits and server features, for the copied system report and the Cron Job Monitor.
 */
class Environment
{
    public function getReport()
    {
        global $wpdb;

        $server = $this->getServerInfo();
        $proVersion = defined('WPSOCIALREVIEWS_PRO_VERSION') ? WPSOCIALREVIEWS_PRO_VERSION : '';
        $hasImageEditor = wp_image_editor_supports(['mime_type' => 'image/jpeg']);
        $hasWebp = $hasImageEditor && wp_image_editor_supports(['mime_type' => 'image/webp']);

        return [
            $this->item(__('WP Social Ninja version', 'wp-social-reviews'), WPSOCIALREVIEWS_VERSION),
            $this->item(__('WP Social Ninja Pro version', 'wp-social-reviews'), $proVersion ?: __('Not installed', 'wp-social-reviews')),
            $this->item(__('WordPress version', 'wp-social-reviews'), get_bloginfo('version')),
            $this->item(__('PHP version', 'wp-social-reviews'), PHP_VERSION),
            $this->item(__('MySQL version', 'wp-social-reviews'), (string) $wpdb->db_version()),
            $this->item(__('PHP memory limit', 'wp-social-reviews'), $server['memory_limit']),
            $this->item(__('PHP max execution time', 'wp-social-reviews'), $server['max_execution_time']),
            $this->item(__('cURL', 'wp-social-reviews'), function_exists('curl_version') ? __('Enabled', 'wp-social-reviews') : __('Disabled', 'wp-social-reviews')),
            $this->item(__('Image editor (GD or Imagick)', 'wp-social-reviews'), $hasImageEditor ? __('Available', 'wp-social-reviews') : __('Not available', 'wp-social-reviews')),
            $this->item(__('WebP support', 'wp-social-reviews'), $hasWebp ? __('Available', 'wp-social-reviews') : __('Not available', 'wp-social-reviews')),
            $this->item(__('Upload folder writable', 'wp-social-reviews'), $this->isUploadDirWritable() ? __('Yes', 'wp-social-reviews') : __('No', 'wp-social-reviews')),
        ];
    }

    /**
     * The PHP limits the Cron Job Monitor shows beside the task list.
     *
     * @return array memory_limit and max_execution_time, as display text.
     */
    public function getServerInfo()
    {
        $memoryLimit = (string) ini_get('memory_limit');
        $maxExecution = (int) ini_get('max_execution_time');

        return [
            'memory_limit'       => wp_convert_hr_to_bytes($memoryLimit) < 0 ? __('Unlimited', 'wp-social-reviews') : $memoryLimit,
            'max_execution_time' => $maxExecution > 0 ? $maxExecution . 's' : __('No limit', 'wp-social-reviews'),
        ];
    }

    protected function item($label, $value)
    {
        return [
            'label' => $label,
            'value' => (string) $value,
        ];
    }

    protected function isUploadDirWritable()
    {
        $upload = wp_upload_dir(null, false);
        $base = Arr::get($upload, 'basedir', '');
        $path = trailingslashit($base) . WPSOCIALREVIEWS_UPLOAD_DIR_NAME;

        // The plugin creates its folder on first use, so a missing folder only needs a writable parent.
        $target = is_dir($path) ? $path : $base;

        return $target !== '' && wp_is_writable($target);
    }
}
