<?php
/** Minimal faithful Phase 24 copy from app/Services/Platforms/ImageOptimizationHandler.php. */
class Phase24Fixture
{
    function savePhotos()
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';

        // Try frontend nonce first, then admin nonce
        $nonce_verified = wp_verify_nonce($nonce, 'wpsr-ajax-nonce') || wp_verify_nonce($nonce, 'wp-social-reviews');

        if (!$nonce_verified) {
            wp_send_json_error([
                'message' => __('Security validation failed. Please try again', 'wp-social-reviews')
            ], 423);
        }

        $id = absint(Arr::get($_REQUEST, 'id', -1));
        $platform = isset($_REQUEST['platform']) ? sanitize_text_field(wp_unslash($_REQUEST['platform'])) : '';
        $feed_type = isset($_REQUEST['feed_type']) ? sanitize_text_field(wp_unslash($_REQUEST['feed_type'])) : '';

        // The nonce is localized for logged-out visitors, so it authenticates nothing.
        // Gate on the template being publicly renderable before reading its meta, otherwise
        // an anonymous request could fetch a draft/private feed with the site's token and
        // materialize its private images into the uploads directory.
        if (!(new \WPSocialReviews\App\Hooks\Handlers\ShortcodeHandler())->isPublicRenderableTemplate($id)) {
            wp_send_json_error(['message' => __('Invalid template.', 'wp-social-reviews')], 403);
        }

        if($id > 0 && $this->platform == $platform) {
            $encodedMeta   = get_post_meta($id, '_wpsr_template_config', true);
            $decodedMeta   = json_decode($encodedMeta, true);
            $feed_settings = Arr::get($decodedMeta, 'feed_settings', []);

            $feedConfigs = null;
            if($this->platform == 'instagram'){
                $formattedMeta = Config::formatInstagramConfig($feed_settings, array());
                $feedConfigs = (new InstagramFeed())->getTemplateMeta($formattedMeta);
            }else if($this->platform == 'youtube'){
                $formattedMeta = Config::formatYoutubeConfig($feed_settings, array());
                $feedConfigs = (new YoutubeFeed())->getTemplateMeta($formattedMeta);
            }else if($this->platform == 'facebook_feed'){
                $formattedMeta = Config::formatFacebookConfig($feed_settings, array());
                $feedConfigs = (new FacebookFeed())->getTemplateMeta($formattedMeta, null, $feed_type);
            }else if($this->platform == 'twitter'){
                $formattedMeta = Config::formatTwitterConfig($feed_settings, array());
                $feedConfigs = (new TwitterFeed())->getTemplateMeta($formattedMeta);
            } else if($this->platform == 'tiktok'){
                $formattedMeta =  apply_filters('wpsocialreviews/format_tiktok_config', $feed_settings, []);
                $feedConfigs = apply_filters('wpsocialreviews/get_template_meta', $formattedMeta, []);
            }

            $feeds = Arr::get($feedConfigs, 'dynamic.items', []);
            if($this->platform == 'instagram'){
                $feeds = array_map(function($feed) {
                    $oembed_image_failed = Arr::get($feed, 'oembed_image_failed', false);
                    if(!$oembed_image_failed){
                        return $feed;
                    }
                }, $feeds);
            }

            $resizedImages = Arr::get($feedConfigs, 'dynamic.resize_data', []);

            $photo_type = $feed_type ? $feed_type : Arr::get($feed_settings, 'source_settings.feed_type', '');
            $feedIds = [];
            foreach ($feeds as $index => $feed) {
                $max_records = $this->maxRecordsCount($this->platform);
                if($index > $max_records){
                    continue;
                }

                if($photo_type == 'album_feed'){
                    $photo_feeds = Arr::get($feed, 'photos.data', []);
                    $feedIds = array_merge($feedIds,array_column($photo_feeds , 'id'));
                    foreach ($photo_feeds as $itemFeed) {
                        $itemFeed['page_id'] = Arr::get($feed, 'page_id', '');
                        $itemFeed['media_type'] = 'IMAGE';
                        $itemFeed['default_media'] = Arr::get($feed, 'source', '');
                        $itemFeed['media_url'] = Arr::get($feed, 'source', '');
                        if (in_array(Arr::get($itemFeed, 'id'), $resizedImages)) {
                            $this->doneResizing[] = Arr::get($itemFeed, 'id');
                        } else {
                            if(!$this->maxResizingPerUnitTimePeriod()) {
                                if ($this->isMaxRecordsReached($this->platform)) {
                                    $this->deleteLeastUsedImages($this->platform);
                                }
                                $this->processSaveImage($itemFeed, $this->platform);
                            }
                        }
                    }
                } else {
                    $feedIds = array_column($feeds , 'id');
                    $feedId = Arr::get($feed, 'id');
                    if (in_array($feedId, $resizedImages)) {
                        $this->doneResizing[] = Arr::get($feed, 'id');
                    } else {
                        if (!$this->maxResizingPerUnitTimePeriod()) {
                            if ($this->isMaxRecordsReached($this->platform)) {
                                $this->deleteLeastUsedImages($this->platform);
                            }
                            $this->processSaveImage($feed, $this->platform);
                        }
                    }
                }
            }

            $header = Arr::get($feedConfigs, 'dynamic.header');
            $accountId = Arr::get($feedConfigs, 'feed_settings.header_settings.account_to_show');

            if ($platform !== 'tiktok' && empty(Arr::get($header, 'user_avatar'))) {
                $accountId = null;
            }

            if ($platform === 'tiktok' && empty(Arr::get($header, 'data.user.avatar_url'))) {
                $accountId = null;
            }
            //get all connected ids

            $connected_ids = [];
            $account_ids = [];
            if ($this->platform == 'facebook_feed') {
                $connected_ids            = (new FacebookHelper())->getConncetedSourceList();
                $account_ids = Arr::get($feed_settings, 'source_settings.selected_accounts', []);
            } else if ($this->platform == 'instagram') {
                $connected_ids            = (new Common())->findConnectedAccounts();
                $account_ids = Arr::get($feed_settings, 'source_settings.account_ids', []);
            } else if ($this->platform == 'tiktok') {
                $connected_ids =  apply_filters('wpsocialreviews/get_connected_source_list',[]);
                $account_ids = Arr::get($feed_settings, 'open_id', []);
            } else if ($this->platform == 'youtube') {
                $connected_ids = get_option('wpsr_youtube_verification_configs');
                $account_ids[] = Arr::get($feed_settings, 'source_settings.channel_id', []);
            }

            $connected_account_list = array_intersect_key($connected_ids, array_flip($account_ids));

            $account_id = null;
            foreach ($connected_account_list as $item) {
                if ($this->platform == 'facebook_feed') {
                    $account_id = $this->platformHeaderLogo($item, $item['page_id']);
                    $this->platformCoverPhoto($item, $item['page_id']);
                } else if ($this->platform == 'instagram') {
                    $account_id = $this->platformHeaderLogo($item, $item['user_id']);
                } else if ($this->platform == 'tiktok') {
                    $account_id = $this->platformHeaderLogo($item, $item['open_id']);
                }else if($this->platform == 'youtube'){
                    $account_id = $this->platformHeaderLogo($item, $item['channel_id']);
                    $this->platformCoverPhoto($item, $item['channel_id']);
                }
            }

            $resizedImages = [
                'images_data' => $feedIds,
                'account_id'  => $account_id
            ];

            echo wp_json_encode($resizedImages);
        }
    }
}
