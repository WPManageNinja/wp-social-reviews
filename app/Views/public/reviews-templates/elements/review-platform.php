<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Platforms\Reviews\Helper;

$wpsr_platform_icon = Helper::platformIcon($platform_name, 'small');
if ( strlen($wpsr_platform_icon) === 0 ) {
    return;
} ?>
<div class="wpsr-review-platform">
    <img class="wpsr-review-platform-icon" width="20" height="20" src="<?php echo esc_url($wpsr_platform_icon); ?>" alt="<?php echo esc_attr($platform_name); ?>">
</div>
