<?php

defined('ABSPATH') or die;

use WPSocialReviews\Framework\Foundation\Application;
use WPSocialReviews\App\Hooks\Handlers\ActivationHandler;
use WPSocialReviews\App\Hooks\Handlers\DeactivationHandler;
use WPSocialReviews\Database\Migrations\ReviewCategoriesMigrator;

return function ($file) {

    $app = new Application($file);

    register_activation_hook($file, function ($network_wide) use ($app) {
        (new ActivationHandler)->handle($network_wide);
    });

    register_deactivation_hook($file, function () {
        (new DeactivationHandler)->handle();
    });

    add_action('plugins_loaded', function () use ($app) {
        if (substr(phpversion(), 0, 3) == '7.0') {
            add_action('admin_notices', function () {
                $class = 'notice notice-error fc_message';
                $message = 'Looks like you are using PHP 7.0 which is not supported by WP Social Ninja. Please upgrade your PHP Version greater than to 7.2';
                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
            });
        } else {
            // Category migration work runs on admin, cron and WP-CLI requests only.
            // Other requests read through the `category` column fallback until it has run.
            if (ReviewCategoriesMigrator::isMaintenanceRequest()) {
                // isBackfilled() also checks the tables, so a flag that outlived a dropped
                // table routes back to migrate(), which recreates the schema.
                if (!ReviewCategoriesMigrator::isBackfilled()) {
                    ReviewCategoriesMigrator::migrate();
                } else {
                    // Resumes a sweep cut short by its per-request cap. One option read when idle.
                    ReviewCategoriesMigrator::maybeContinueOrphanSweep();
                }
            }

            do_action('wp_social_reviews_loaded', $app);
            do_action('wp_social_reviews_loaded_v2', $app);
        }
    });
};
