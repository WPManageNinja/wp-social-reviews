<?php

namespace WPSocialReviews\App\Services\Platforms\Feeds\Instagram;

use WPSocialReviews\App\Services\DataProtector;
use WPSocialReviews\App\Services\Platforms\Feeds\Instagram\Common;
use WPSocialReviews\App\Services\Platforms\Feeds\CacheHandler;
use WPSocialReviews\App\Services\Platforms\Feeds\Instagram\RefreshToken;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\App\Services\Helper;

if (!defined('ABSPATH')) {
    exit;
}

class AccountFeed
{
    protected $cacheHandler;

    public function __construct()
    {
        $this->cacheHandler = new CacheHandler('instagram');
    }

    /**
     * Get Accounts Feed
     *
     * @param array $accountIds
     *
     * @return array
     * @since 1.3.0
     */
    public function getMultipleAccountResponse($accountIds)
    {
        $response          = array();
        $connectedAccounts = (new Common())->findConnectedAccounts();
        $error_message = '';
        $error_code = null;
        foreach ($accountIds as $index => $accountId) {
            $accountDetails     = Arr::get($connectedAccounts, $accountId) ? $connectedAccounts[$accountId] : '';

            if(empty($accountDetails)) {
                $multipleAccountsConnected = count($accountIds) > 1;
                // translators: %s is the account ID that was deleted
                $base_error_message = __('The account ID (%s) linked to your configuration has been deleted. To continue displaying your feed from this account, please reauthorize and reconnect it in the configuration settings. Then, go to the template editor, navigate to Source → Select a User Account, and choose the account again.', 'wp-social-reviews');
                if ($multipleAccountsConnected) {
                    $error_message .= __('There are multiple accounts being used on this template. ', 'wp-social-reviews') . sprintf($base_error_message, $accountId);
                } else {
                    $error_message .= sprintf($base_error_message, $accountId);
                }
                continue;
            }
            $user_avatar = Arr::get($accountDetails, 'user_avatar', '');
            $userName = Arr::get($accountDetails, 'username', '');
            $has_account_error_code = Arr::get($accountDetails, 'error_code');

            if($has_account_error_code) {
                $error_code = $has_account_error_code;
                if (sizeof($connectedAccounts) > 1) {
                    // translators: Please retain the placeholders (%s, %d, etc.) and ensure they are correctly used in context.
                    $error_message .= sprintf(__('There has been a problem with your account(%s). ', 'wp-social-reviews'), $userName) . Arr::get($connectedAccounts, $accountId . '.error_message', '') . '<br/> <br/>';
                } else {
                    // translators: Please retain the placeholders (%s, %d, etc.) and ensure they are correctly used in context.
                    $error_message .= sprintf(__('There has been a problem with your account(%s). ', 'wp-social-reviews'), $userName) . Arr::get($accountDetails, 'error_message', '') . '<br/> <br/>';
                }
            }

            $resultWithComments = array();
            if ($accountDetails) {
                $feedCacheName   = 'user_account_feed_id_' . $accountId;
                $instagramApiUrl = $this->getApiUrl($accountDetails);

                if ( is_array($instagramApiUrl) && (new Common())->instagramError($instagramApiUrl)) {
                    $error_message .= $instagramApiUrl;
                }
	            $resultWithComments = $this->cacheHandler->getFeedCache($feedCacheName);

                if (!$resultWithComments && empty($has_account_error_code) || !empty($resultWithComments) && !Arr::has($resultWithComments[0], 'user_avatar')) {
                    $resultWithoutComments = (new Common())->expandWithoutComments($instagramApiUrl);
                    if ((new Common())->instagramError($resultWithoutComments)) {
                        // translators: Please retain the placeholders (%s, %d, etc.) and ensure they are correctly used in context.
                        $error_message .= sprintf(__('There has been a problem with your account(%s). ', 'wp-social-reviews'), $userName) . Arr::get($resultWithoutComments, 'error.message');
                        $error_code = Arr::get($resultWithoutComments, 'error.code');
                    }
                    if (!(new Common())->instagramError($resultWithoutComments)) {
                        $resultWithoutComments['data'] = $this->processFeedItems($userName, $accountId, $user_avatar, $resultWithoutComments);

                        $resultWithComments = (new Common())->expandWithComments($accountDetails, $resultWithoutComments);
                        if ((new Common())->instagramError($resultWithComments)) {
                            // translators: Please retain the placeholders (%s, %d, etc.) and ensure they are correctly used in context.
                            $error_message .= sprintf(__('There has been a problem with your account(%s). ', 'wp-social-reviews'), $userName) . Arr::get($resultWithComments, 'error.message');
                            $error_code = Arr::get($resultWithComments, 'error.code');
                        }
                        $this->cacheHandler->createCache($feedCacheName, $resultWithComments);
                    } else {
                        $resultWithComments = false;
                    }
                }
            }
            $response[$index] = $resultWithComments ? $resultWithComments : array();
        }

        $accountFeed = array();
        foreach ($response as $index => $feeds) {
            if(!empty($feeds) && is_array($feeds)) {
                $accountFeed = array_merge($accountFeed, $feeds);
            }
        }

        if($error_code === 999){
            $encryptionError = Helper::getEncryptionErrorData();
            $error_message = $encryptionError['message'];
        }

        return [
            'error_message' => $error_message,
            'feeds'         => $accountFeed
        ];
    }

    /**
     * Follows paging.next until the total, the last page, or a failed page. A failed page keeps
     * the pages before it: a feed is display data.
     */
    public function getNextPageUrlResponse($nextUrl, $response)
    {
        $posts   = $response;
        $limit   = max(1, absint(apply_filters('wpsocialreviews/instagram_feeds_total_limit', 100)));
        $data    = (array) Arr::get($response, 'data', []);
        $visited = [];

        while (!empty($nextUrl) && count($data) < $limit && !isset($visited[$nextUrl])) {
            // The link carries the access token, so it is only ever sent to Graph.
            if (!$this->isGraphUrl($nextUrl)) {
                break;
            }
            $visited[$nextUrl] = true;

            $page = (new Common())->makeRequest($nextUrl);
            $rows = is_array($page) && !Arr::get($page, 'error') ? (array) Arr::get($page, 'data', []) : [];
            if (empty($rows)) {
                break;
            }

            $data    = array_merge($data, $rows);
            $nextUrl = Arr::get($page, 'paging.next');
        }

        $posts['data'] = array_slice($data, 0, $limit);

        return $posts;
    }

    private function isGraphUrl($url)
    {
        return wp_parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(wp_parse_url($url, PHP_URL_HOST), ['graph.facebook.com', 'graph.instagram.com'], true);
    }

    /**
     * Process feed items to add user information and fix media URLs
     *
     * @param string $userName Instagram username
     * @param string $accountId Instagram account ID
     * @param string $user_avatar User avatar URL
     * @param array $resultWithoutComments Raw API response
     * @return array Processed feed items
     */
    public function processFeedItems($userName, $accountId, $user_avatar, $resultWithoutComments)
    {
        if(isset($resultWithoutComments['paging']) && defined('WPSOCIALREVIEWS_PRO')){
            $nextUrl = Arr::get($resultWithoutComments, 'paging.next');
            if($nextUrl){
                $resultWithoutComments = $this->getNextPageUrlResponse($nextUrl, $resultWithoutComments);
            }
        }

        $feeds = Arr::get($resultWithoutComments, 'data', []);

        $feeds = $this->filterFeedsBySharedStatus($feeds);

        if (!empty($feeds) && is_array($feeds)) {
            // A collab post can carry the collaborator's username.
            foreach ($feeds as $key => $feed) {
                $feeds[$key]['username'] = $userName;
                $feeds[$key]['accountId'] = $accountId;
                $feeds[$key]['user_avatar'] = $user_avatar;

                if (isset($feed['caption'])) {
                    $feeds[$key]['caption'] = Helper::sanitizeForStorage($feed['caption']);
                }
            }
        }

        return $feeds;
    }

    /**
     * Drop posts with is_shared_to_feed false: trial reels that only show in the Reels tab.
     *
     * @param array $feeds Array of feed items
     * @return array Filtered feed items
     */
    protected function filterFeedsBySharedStatus($feeds)
    {
        if (empty($feeds) || !is_array($feeds)) {
            return $feeds;
        }

        $feeds = array_values(array_filter($feeds, function ($feedItem) {
            return !isset($feedItem['is_shared_to_feed']) || $feedItem['is_shared_to_feed'] !== false;
        }));

        return $feeds;
    }

    public function getApiUrl($accountDetails)
    {
        $dataProtector = new DataProtector();
        $num    = apply_filters('wpsocialreviews/instagram_feeds_limit', 10);
        $apiUrl = '';
        if ($accountDetails['api_type'] === 'business') {
            $decrypt_access_token = $dataProtector->decrypt($accountDetails['access_token']) ? $dataProtector->decrypt($accountDetails['access_token']) : $accountDetails['access_token'];
            $apiUrl = 'https://graph.facebook.com/' . $accountDetails['user_id'] . '/media?fields=media_url,thumbnail_url,media_product_type,is_shared_to_feed,caption,id,media_type,timestamp,username,comments_count,like_count,permalink,children{media_url,id,media_type,timestamp,permalink,thumbnail_url}&limit=' . $num . '&access_token=' . $decrypt_access_token . '&status_code=PUBLISHED';
        } elseif ($accountDetails['api_type'] === 'business_basic' || $accountDetails['api_type'] === 'personal') {
            $accessToken = (new RefreshToken())->getAccessToken($accountDetails);
            $accessToken = $dataProtector->decrypt($accessToken) ? $dataProtector->decrypt($accessToken) : $accessToken;

            if (is_array($accessToken) && (new Common())->instagramError($accessToken)) {
                return $accessToken;
            }

            $apiUrl = 'https://graph.instagram.com/' . $accountDetails['user_id'] . '/media?fields=media_url,thumbnail_url,caption,id,media_type,media_product_type,is_shared_to_feed,timestamp,username,comments_count,like_count,permalink,children{media_url,id,media_type,timestamp,permalink,thumbnail_url}&limit=' . $num . '&access_token=' . $accessToken . '&status_code=PUBLISHED';
        }

        return $apiUrl;
    }
}
