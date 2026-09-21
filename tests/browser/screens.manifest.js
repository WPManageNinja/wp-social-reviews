'use strict';

const screen = (name, path, component, componentFile, selector, options = {}) => ({
    name, path, component, componentFile, selector, ...options,
});

const knownFailure = (id, reason, expectedError) => ({
    id,
    reason,
    expectedError,
    remediation: `tests/FIX-PLAN.md#${id}`,
    revisit: `Remove ${id} after screen passes its active browser assertion.`,
});

module.exports = [
    screen('platforms', '/', 'Platforms', 'resources/admin/components/views/Platforms.vue', '.wpsr-platforms-wrapper', { text: 'All Platforms' }),
    screen('templates', '/templates', 'AllTemplates', 'resources/admin/components/views/AllTemplates.vue', '.wpsr-page-container.wpsr-all-templates-page', { text: 'All Templates' }),
    screen('testimonials', '/testimonials', 'AllTestimonials', 'resources/admin/components/views/AllTestimonials.vue', '.wpsr-promote-testimonial-lp', { text: 'Display Powerful Testimonials' }),
    screen('notifications', '/notifications', 'AllNotifications', 'resources/admin/components/views/AllNotifications.vue', '.wpsr-promote-lp', { text: 'Notification Popup' }),
    screen('reviews', '/reviews', 'allReviews', 'resources/admin/components/views/AllReviews.vue', '.wpsr-page-container.wpsr-all-templates-page', { text: 'All Reviews' }),
    screen('chat-widgets', '/chat-widgets', 'ChatWidgets', 'resources/admin/components/views/ChatWidgets.vue', '.wpsr-page-container.wpsr-all-templates-page', { text: 'Chat Widgets' }),
    screen('custom-sources', '/custom-sources', 'CustomSources', 'resources/admin/components/views/CustomSources.vue', '.wpsr-managers-upgrade-to-pro', { text: 'Add Reviews from Any Platform You Want' }),
    screen('review-forms', '/review-forms', 'ReviewForms', 'resources/admin/components/views/review-forms/ReviewsFormView.vue', '.wpsr-managers-upgrade-to-pro', { text: 'Collect Reviews Directly on Your Website' }),

    screen('get-reviews', '/settings/get-reviews', 'GetReviewsViaQRCode', 'resources/admin/components/views/settings/reviews/GetReviewsViaQRCode.vue', '.wpsr-managers-upgrade-to-pro', { text: 'turn every scan into a 5-star opportunity' }),
    screen('global-feeds-settings', '/settings/global-feeds-settings', 'GlobalFeedSettings', 'resources/admin/components/views/settings/feeds/GlobalFeedSettings.vue', '.wpsr-gs-header', { text: 'Global Feed Settings' }),
    screen('global-reviews-settings', '/settings/global-reviews-settings', 'GlobalReviewSettings', 'resources/admin/components/views/settings/reviews/GlobalReviewSettings.vue', '.wpsr-gs-header', { text: 'Global Review Settings' }),
    screen('google-settings', '/settings/google-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner'),
    screen('twitter-settings', '/settings/twitter-settings', 'FeedSettings', 'resources/admin/components/views/settings/feeds/FeedSettings.vue', '.wpsr-general-settings-wrapper', { text: 'Configure Connection' }),
    screen('youtube-settings', '/settings/youtube-settings', 'FeedSettings', 'resources/admin/components/views/settings/feeds/FeedSettings.vue', '.wpsr-general-settings-wrapper', { text: 'Configure Connection' }),
    screen('instagram-settings', '/settings/instagram-settings', 'FeedSettings', 'resources/admin/components/views/settings/feeds/FeedSettings.vue', '.wpsr-general-settings-wrapper', { text: 'Configure Connection' }),
    screen('facebook-feed-settings', '/settings/facebook-feed-settings', 'FeedSettings', 'resources/admin/components/views/settings/feeds/FeedSettings.vue', '.wpsr-general-settings-wrapper', { text: 'Configure Connection' }),
    screen('tiktok-settings', '/settings/tiktok-settings', 'FeedSettings', 'resources/admin/components/views/settings/feeds/FeedSettings.vue', '.wpsr-general-settings-wrapper', { text: 'Configure Connection' }),
    screen('global-review-settings', '/settings/global-review-settings', 'GlobalReviewSettings', 'resources/admin/components/views/settings/reviews/GlobalReviewSettings.vue', '.wpsr-gs-header', { text: 'Global Review Settings' }),
    screen('airbnb-settings', '/settings/airbnb-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner'),
    screen('tripadvisor-settings', '/settings/tripadvisor-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner', { text: 'Upgrade to WP Social Ninja Pro' }),
    screen('tp-settings', '/settings/tp-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner', { text: 'Upgrade to WP Social Ninja Pro' }),
    screen('amazon-settings', '/settings/amazon-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner', { text: 'Upgrade to WP Social Ninja Pro' }),
    screen('aliexpress-settings', '/settings/aliexpress-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner', { text: 'Upgrade to WP Social Ninja Pro' }),
    screen('booking.com-settings', '/settings/booking.com-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner', { text: 'Upgrade to WP Social Ninja Pro' }),
    screen('zomato-settings', '/settings/zomato-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner', { text: 'Upgrade to WP Social Ninja Pro' }),
    screen('yelp-settings', '/settings/yelp-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner', { text: 'Upgrade to WP Social Ninja Pro' }),
    screen('facebook-settings', '/settings/facebook-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner', { text: 'Upgrade to WP Social Ninja Pro' }),
    screen('woocommerce-settings', '/settings/woocommerce-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner', { text: 'Upgrade to WP Social Ninja Pro' }),
    screen('fluent-cart-settings', '/settings/fluent-cart-settings', 'ReviewSettings', 'resources/admin/components/views/settings/reviews/ReviewSettings.vue', '.wpsr-advance-settings-main .wpsr-advance-settings-inner', { text: 'Upgrade to WP Social Ninja Pro' }),
    screen('shoppable-by-hashtags', '/settings/shoppable-by-hashtags', 'Shoppable', 'resources/admin/components/views/settings/shoppable/Shoppable.vue', '.wpsr-shoppable-upgrade-to-pro', { text: 'Maximize profit & conversions from your Instagram' }),
    screen('advance-settings', '/settings/advance-settings', 'AdvanceSettings', 'resources/admin/components/views/settings/advance/Advance.vue', '.wpsr-gs-header', { text: 'Advanced Settings' }),
    screen('license-management', '/settings/license-management', 'LicenseManagement', 'resources/admin/components/views/settings/others/LicenseManagement.vue', '.wpsr-license-management', { text: 'License Management' }),
    screen('management-settings', '/settings/management-settings', 'ManagementSettings', 'resources/admin/components/views/settings/ManagementSettings.vue', '.wpsr-advance-settings-main', {
        text: 'WPSN Managers - Roles and Permissions',
    }),
    screen('translation-management', '/settings/translation-management', 'Translation', 'resources/admin/components/views/settings/others/Translation.vue', '.wpsr-gs-header-title', { text: 'Translation Settings' }),

    screen('export', '/tools/export', 'ExportView', 'resources/admin/components/views/tools/ExportReviews.vue', '.wpsr-tools-export-import', { text: 'Export Data' }),
    screen('import', '/tools/import', 'ImportView', 'resources/admin/components/views/tools/ImportReviews.vue', '.wpsr-tools-export-import', { text: 'Import Data' }),
    screen('tiktok_feed', '/tiktok_feed', 'TikTokFeedLandingPage', 'resources/admin/components/views/advertise/TikTokFeedLandingPage.vue', '.wpsr-promote-lp-tiktok', { text: 'Custom Feed for TikTok' }),
    screen('support', '/support', 'SupportAndDocs', 'resources/admin/components/views/SupportAndDocs.vue', '.wpsr-support-page', { text: 'Support & Documentation' }),

    screen('edit-template', '/edit-template/:template_id', 'EditTemplateView', 'resources/admin/components/reviews/editor/ReviewsView.vue', '.wpsr_editor', { token: 'review_template', fixtureText: 'review_template_title' }),
    screen('edit-chat-widget', '/edit-chat-widget/:widget_id', 'EditChatView', 'resources/admin/components/chats/editor/ChatView.vue', '.wpsr_editor', { token: 'chat_widget', fixtureText: 'chat_widget_title' }),
    screen('edit-twitter-template', '/edit-twitter-template/:template_id', 'EditTwitterFeedView', 'resources/admin/components/feeds/editor/TwitterView.vue', '.wpsr_editor', { token: 'twitter_template', fixtureText: 'twitter_template_title' }),
    screen('edit-youtube-template', '/edit-youtube-template/:template_id', 'EditYoutubeFeedView', 'resources/admin/components/feeds/editor/YoutubeView.vue', '.wpsr_editor', { token: 'youtube_template', fixtureText: 'youtube_template_title' }),
    screen('edit-instagram-template', '/edit-instagram-template/:template_id', 'EditInstagramFeedView', 'resources/admin/components/feeds/editor/InstagramView.vue', '.wpsr_editor', { token: 'instagram_template', fixtureText: 'instagram_template_title' }),
    screen('edit-facebook-feed-template', '/edit-facebook-feed-template/:template_id', 'EditFacebookFeedView', 'resources/admin/components/feeds/editor/FacebookView.vue', '.wpsr_editor', { token: 'facebook_feed_template', fixtureText: 'facebook_feed_template_title' }),
    screen('edit-tiktok-template', '/edit-tiktok-template/:template_id', 'EditTiktokFeedView', 'resources/admin/components/feeds/editor/TiktokView.vue', '.wpsr-tiktok-addon-required', { token: 'tiktok_template', text: 'Custom Feed for TikTok' }),
    screen('edit-social-wall-template', '/edit-social-wall-template/:template_id', 'EditSocialWallView', 'resources/admin/components/feeds/editor/SocialWallView.vue', '.wpsr-managers-upgrade-to-pro', { token: 'social_wall_template', text: 'Blend Every Social Feed into One Wall' }),
    screen('edit-custom-source', '/edit-custom-source/:template_id', 'EditCustomSourceView', 'resources/admin/components/reviews/editor/CustomSourceView.vue', '.wpsr-page-container.wpsr-all-templates-page', { token: 'custom_source', text: 'Add Reviews from Any Platform You Want' }),
    screen('review-form-edit', '/review-forms/:id/edit', 'ReviewFormEditor', 'resources/admin/components/views/review-forms/ReviewFormEditor.vue', '.wpsr-managers-upgrade-to-pro', { token: 'review_form', text: 'Collect Reviews Directly on Your Website' }),
];
