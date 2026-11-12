<?php

namespace WPSocialReviews\App\Hooks\Handlers;

use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Platforms\ImageOptimizationHandler;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\Platforms\Feeds\Instagram\Helper as InstagramHelper;
class InstagramTemplateHandler
{
    /**
     * Instagram templates the installed Pro release can render. Pro builds the view
     * path from the key, and released Pro ships none of these, hence the empty default.
     *
     * @return array
     **/
    public static function getProTemplates()
    {
        return apply_filters('wpsocialreviews/instagram_pro_templates', array());
    }

    /**
     * Also needs a renderer, or the caller prints the unfiltered array as "Array".
     *
     * @param string $template
     * @return bool
     **/
    public static function canRenderProTemplate($template)
    {
        return defined('WPSOCIALREVIEWS_PRO')
            && in_array($template, self::getProTemplates(), true)
            && has_filter('wpsocialreviews/add_instagram_feed_template');
    }

    /**
     * @param $template_meta
     * @since 3.7.0
     **/
    public function renderTemplateItemWrapper($template_meta = []){
        $app = App::getInstance();

        $desktop_column = Arr::get($template_meta, 'responsive_column_number.desktop');
        $tablet_column = Arr::get($template_meta, 'responsive_column_number.tablet');
        $mobile_column = Arr::get($template_meta, 'responsive_column_number.mobile');
        $template = Arr::get($template_meta, 'template') === 'template2' ? 'wpsr-mb-30 ' : '';

        $classes = esc_attr($template) . 'wpsr-col-' . esc_attr($desktop_column) . ' wpsr-col-sm-' . esc_attr($tablet_column) . ' wpsr-col-xs-' . esc_attr($mobile_column);
        $app->view->render('public.feeds-templates.instagram.elements.item-parent-wrapper', array(
            'classes' => $classes,
        ));
    }

    /**
     * @param $feed
     * @since 1.3.0
     **/
    public function renderPostMedia($feed = [], $template_meta = [], $index = null, $display_optimize_image = null)
    {
        $media_type = Arr::get($feed, 'media_type', '');
        $thumbnail_url = ($media_type === 'VIDEO') ? Arr::get($feed, 'thumbnail_url', '') : '';
        $media_url = Arr::get($feed, 'media_url', '');
        $layout_type = Arr::get($template_meta, 'layout_type', '');
        $default_media = Arr::get($feed, 'default_media', '');
        $oembed_image_failed = Arr::get($feed, 'oembed_image_failed', false);

        $app = App::getInstance();
        $app->view->render('public.feeds-templates.instagram.elements.media', [
            'feed'          => $feed,
            'template_meta' => $template_meta,
            'media_type'    => $media_type,
            'media_url'     => $media_url,
            'thumbnail_url' => $thumbnail_url,
            'default_media' => $default_media,
            'media_name'    => Arr::get($feed, 'media_name', ''),
            'placeholder_img_class' => $this->getPlaceholderImgClass($display_optimize_image, $media_url),
            'animation_img_class' => $this->getAnimationImgClass($display_optimize_image, $media_url, $layout_type),
            'index'         => $index,
            'display_optimize_image' => $display_optimize_image
        ]);
    }

    /**
     * @param $feed
     * @param $template_meta
     * @since 1.3.0
     **/
    public function renderPostCaption($feed = [], $template_meta = [])
    {
        if (Arr::get($template_meta, 'post_settings.display_caption') === 'false') {
            return false;
        }

        $trim_words_count = isset($template_meta['post_settings']['trim_caption_words']) && $template_meta['post_settings']['trim_caption_words'] > 0 ? $template_meta['post_settings']['trim_caption_words'] : 0;
        if (isset($feed['caption']) && $trim_words_count) {
            $caption = $this->trimCaptionWords($feed['caption'], $trim_words_count);
        } else {
            $caption = isset($feed['caption']) ? $feed['caption'] : '';
        }

        $app = App::getInstance();
        $app->view->render('public.feeds-templates.instagram.elements.caption', array(
            'feed'          => $feed,
            'template_meta' => $template_meta,
            'caption'       => $caption
        ));
    }

    public function renderIcon()
    {
        $app = App::getInstance();
        $app->view->render('public.feeds-templates.instagram.elements.icon');
    }

    /**
     * Avatar, username and post time row. Only the author card templates (5 and 6) show it;
     * they reuse the views of templates 2 and 1.
     *
     * @param array $feed
     * @param array $template_meta
     */
    public function renderPostAuthor($feed = [], $template_meta = [])
    {
        $username = Arr::get($feed, 'username', '');
        if (empty($username) || !in_array(Arr::get($template_meta, 'template'), array('template5', 'template6'), true)) {
            return;
        }

        $avatar_url = Arr::get($template_meta, '_header_data.avatar_url', '');
        ?>
        <div class="wpsr-ig-post-author">
            <?php if (!empty($avatar_url)) { ?>
                <img class="wpsr-ig-post-author-avatar" src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($username); ?>" loading="lazy" />
            <?php } ?>
            <div class="wpsr-ig-post-author-details">
                <span class="wpsr-ig-post-author-name"><?php echo esc_html($username); ?></span>
                <?php $this->renderPostTime($feed, $template_meta, 'wpsr-ig-post-author-time'); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Free Follow button with the default text. Pro renders its own, with the custom text.
     *
     * @param array $settings
     */
    public function renderFollowButtonHtml($settings = [])
    {
        if (defined('WPSOCIALREVIEWS_PRO') || Arr::get($settings, 'follow_button_settings.display_follow_button') !== 'true') {
            return;
        }

        $username = Arr::get(InstagramHelper::getUserAccountInfo($settings), 'username', '');
        if (empty($username)) {
            return;
        }

        $text = __('Follow on Instagram', 'wp-social-reviews');
        ?>
        <div class="wpsr-ig-follow-btn">
            <a href="<?php echo esc_url('https://www.instagram.com/' . $username); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr($text); ?>">
                <?php echo esc_html($text); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Free header posts and followers counts. Pro renders its own.
     *
     * @param array $user
     * @param array $settings Header settings.
     * @param array $translations
     */
    public function renderHeaderStatisticsHtml($user = [], $settings = [], $translations = [])
    {
        if (defined('WPSOCIALREVIEWS_PRO')) {
            return;
        }

        $show_posts     = Arr::get($user, 'media_count') && Arr::get($settings, 'display_posts_counter') === 'true';
        $show_followers = Arr::get($user, 'followers_count') && Arr::get($settings, 'display_followers_counter') === 'true';
        if (!$show_posts && !$show_followers) {
            return;
        }
        ?>
        <div class="wpsr-ig-header-statistics">
            <?php if ($show_posts) { ?>
                <div class="wpsr-ig-header-statistic-item" dir="auto">
                    <strong><?php echo esc_html(GlobalHelper::shortNumberFormat($user['media_count'])); ?> </strong>
                    <?php echo esc_html(Arr::get($translations, 'posts') ?: __('Posts', 'wp-social-reviews')); ?>
                </div>
            <?php } ?>
            <?php if ($show_followers) { ?>
                <div class="wpsr-ig-header-statistic-item" dir="auto">
                    <strong><?php echo esc_html(GlobalHelper::shortNumberFormat($user['followers_count'])); ?></strong>
                    <?php echo esc_html(Arr::get($translations, 'followers') ?: __('Followers', 'wp-social-reviews')); ?>
                </div>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * Post time ("3 days ago") on Classic Grid and Caption Card. Author Card and Author Bar
     * reuse their views but already show the time in the author row.
     *
     * @param array $feed
     * @param array $template_meta
     */
    public function renderPostDate($feed = [], $template_meta = [])
    {
        if (in_array(Arr::get($template_meta, 'template'), array('template5', 'template6'), true)) {
            return;
        }

        $this->renderPostTime($feed, $template_meta);
    }

    /**
     * The one place a post's time ("3 days ago") is rendered, honouring the Display Date switch.
     *
     * @param array $feed
     * @param array $template_meta
     * @param string $class
     */
    public function renderPostTime($feed = [], $template_meta = [], $class = 'wpsr-ig-post-date')
    {
        $timestamp = strtotime(Arr::get($feed, 'timestamp', ''));
        if (empty($timestamp) || Arr::get($template_meta, 'post_settings.display_date', 'true') !== 'true') {
            return;
        }

        /* translators: %s: Human-readable time difference, e.g. "3 days". */
        $time_ago = sprintf(__('%s ago', 'wp-social-reviews'), human_time_diff($timestamp));
        ?>
        <span class="<?php echo esc_attr($class); ?>"><?php echo esc_html($time_ago); ?></span>
        <?php
    }

    public function renderUserAvatar($header = [], $header_settings = [])
    {
        $userName = Arr::get($header, 'username', '');
        if (Arr::get($header_settings, 'display_avatar') !== 'true' || empty($userName)) {
            return;
        }

        $profileUrl = 'https://www.instagram.com/' . $userName;
        $avatarUrl = $this->getUserAvatar($header, $header_settings);

        $app = App::getInstance();
        $app->view->render('public.feeds-templates.instagram.elements.avatar', [
            'profile_url' => $profileUrl,
            'avatar_url'  => $avatarUrl,
            'user_name'  => $userName,
            'account_id'  => Arr::get($header, 'account_id')
        ]);
    }

    public function getUserAvatar($header, $header_settings)
    {
        $customAvatar = Arr::get($header_settings, 'custom_profile_photo');
        if (!empty($customAvatar)) {
            return $customAvatar;
        }

        $userAvatar = Arr::get($header, 'user_avatar');
        $globalSettings = get_option('wpsr_instagram_global_settings');
        if (Arr::get($globalSettings, 'global_settings.optimized_images') === 'false' && !empty($userAvatar)) {
            return $userAvatar;
        }

        $localAvatar = Arr::get($header, 'user_avatar');
        if(!empty($localAvatar) && Arr::get($globalSettings, 'global_settings.optimized_images') === 'true') {
            return $localAvatar;
        }

        if (empty($localAvatar) && Arr::get($globalSettings, 'global_settings.optimized_images') === 'true') {
            $avatar = Arr::get($header, 'user_avatar');
            $accountId = Arr::get($header, 'account_id');
            $isLocalUrl = GlobalHelper::isLocalUrl($avatar);

            if(!empty($avatar) && !$isLocalUrl) {
                $imageOptimizationObj =  new ImageOptimizationHandler('instagram');
                $created = $imageOptimizationObj->createLocalHeader($accountId, $avatar, '');
                $imageOptimizationObj->updateLocalHeaderStatus($accountId, $created);

                if ($created) {
                    return $imageOptimizationObj->getLocalHeaderUrl($accountId);
                }
            }
        }

        return WPSOCIALREVIEWS_URL . 'assets/images/template/review-template/placeholder-image.png';
    }

    /**
     * @param bool $display_optimize_image
     * @param string $media_url
     * @return string
     */
    private function getPlaceholderImgClass($display_optimize_image, $media_url)
    {
        if (!$display_optimize_image) {
            return 'wpsr-show';
        }

        if ($media_url && !str_contains($media_url, 'placeholder')) {
            return 'wpsr-show';
        }

        return 'wpsr-hide';
    }

    /**
     * @param bool $display_optimize_image
     * @param string $media_url
     * @param string $layout_type
     * @return string
     */
    private function getAnimationImgClass($display_optimize_image, $media_url, $layout_type)
    {
        if (!$display_optimize_image) {
            return '';
        }

        if (($media_url && str_contains($media_url, 'placeholder')) || $layout_type === 'carousel') {
            return 'wpsr-animated-background';
        }

        return '';
    }

    /**
     * Cut a caption after a word count without splitting the HTML tags it may contain.
     *
     * @param string $caption
     * @param int $limit
     * @return string
     */
    private function trimCaptionWords($caption, $limit)
    {
        $parts = preg_split('/(\s+)/u', (string) $caption, -1, PREG_SPLIT_DELIM_CAPTURE);
        $words = 0;
        $output = '';
        foreach ((array) $parts as $part) {
            $output .= $part;
            if (trim($part) === '' || preg_match('/^<[^>]+>$/', $part)) {
                continue;
            }
            if (++$words >= $limit) {
                break;
            }
        }

        return rtrim($output) === rtrim((string) $caption) ? $caption : rtrim($output) . '...';
    }
}
