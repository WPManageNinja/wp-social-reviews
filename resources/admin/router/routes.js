import GlobalView from '../components/global/Index';

import Platforms from '../components/views/Platforms';
import ChatWidgets from '../components/views/ChatWidgets';
import CustomSources from '../components/views/CustomSources';
import Settings from '../components/views/Settings';
import SupportAndDocs from '../components/views/SupportAndDocs';

import ReviewSettings from '../components/views/settings/reviews/ReviewSettings';
import GlobalReviewSettings from '../components/views/settings/reviews/GlobalReviewSettings';
import GetReviewsViaQRCode from '../components/views/settings/reviews/GetReviewsViaQRCode';
import FeedSettings from "../components/views/settings/feeds/FeedSettings";
import GlobalFeedSettings from "../components/views/settings/feeds/GlobalFeedSettings";
import AllTemplates from '../components/views/AllTemplates';
import TikTokFeedLandingPage from '../components/views/advertise/TikTokFeedLandingPage';
import Shoppable from '../components/views/settings/shoppable/Shoppable';

import AllNotifications from '../components/views/AllNotifications';
import AllTestimonials from "../components/views/AllTestimonials";
import EditTemplateView from '../components/reviews/editor/ReviewsView';

//chat view
import EditChatView from '../components/chats/editor/ChatView';

//feed view
import EditTwitterFeedView from '../components/feeds/editor/TwitterView';
import EditYoutubeFeedView from '../components/feeds/editor/YoutubeView';
import EditInstagramFeedView from '../components/feeds/editor/InstagramView';
import EditFacebookFeedView from '../components/feeds/editor/FacebookView';
import EditTiktokFeedView from '../components/feeds/editor/TiktokView';
import EditSocialWallView from '../components/feeds/editor/SocialWallView';
import EditCustomSourceView from '../components/reviews/editor/CustomSourceView';

//all review
import allReviews from '../components/views/AllReviews'

import LicenseManagement from "../components/views/settings/others/LicenseManagement";
import Translation from "../components/views/settings/others/Translation";
import ManagementSettings from "../components/views/settings/ManagementSettings";

//tools
import ExportView from "../components/views/tools/ExportReviews.vue";
import ImportView from "../components/views/tools/ImportReviews.vue";
import Tools from '../components/views/Tools';

//Advance settings
import AdvanceSettings from "../components/views/settings/advance/Advance";

const  globalSettingsViewChildrenRoutes =[
    {
        path: 'get-reviews',
        name: 'get-reviews',
        component: GetReviewsViaQRCode,

        meta: { title: 'Get Reviews Via QR Code' }
    },
    {
        path: 'global-feeds-settings',
        name: 'global-feeds-settings',
        component: GlobalFeedSettings,

        meta: { title: 'Global Feeds Settings' }
    },
    {
        path: 'global-reviews-settings',
        name: 'global-reviews-settings',
        component: GlobalReviewSettings,

        meta: { title: 'Global Reviews Settings' }
    },
    {
        path: 'google-settings',
        name: 'google-settings',
        component: ReviewSettings,

        meta: { title: 'Google Settings' }
    },
    {
        path: 'twitter-settings',
        name: 'twitter-settings',
        component: FeedSettings,

        meta: { title: 'X (Twitter) Settings' }
    },
    {
        path: 'youtube-settings',
        name: 'youtube-settings',
        component: FeedSettings,

        meta: { title: 'YouTube Settings' }
    },
    {
        path: 'instagram-settings',
        name: 'instagram-settings',
        component: FeedSettings,
        meta: { title: 'Instagram Settings' }
    },
    {
        path: 'facebook-feed-settings',
        name: 'facebook-feed-settings',
        component: FeedSettings,
        meta: { title: 'Facebook Settings' }
    },
    {
        path: 'tiktok-settings',
        name: 'tiktok-settings',
        component: FeedSettings,
        meta: { title: 'TikTok Settings' }
    },
    {
        path: 'global-review-settings',
        name: 'global-review-settings',
        component: GlobalReviewSettings,

        meta: { title: 'Global Review Settings' }
    },
    {
        path: 'airbnb-settings',
        name: 'airbnb-settings',
        component: ReviewSettings,

        meta: { title: 'Airbnb Settings' }
    },
    {
        path: 'tripadvisor-settings',
        name: 'tripadvisor-settings',
        component: ReviewSettings,

        meta: { title: 'Tripadvisor Settings' }
    },
    {
        path: 'tp-settings',
        name: 'tp-settings',
        component: ReviewSettings,
        meta: { title: 'Settings' }
    },
    {
        path: 'amazon-settings',
        name: 'amazon-settings',
        component: ReviewSettings,

        meta: { title: 'Amazon Settings' }
    },
    {
        path: 'aliexpress-settings',
        name: 'aliexpress-settings',
        component: ReviewSettings,

        meta: { title: 'AliExpress Settings' }
    },
    {
        path: 'booking.com-settings',
        name: 'booking.com-settings',
        component: ReviewSettings,

        meta: { title: 'Booking.com Settings' }
    },
    {
        path: 'zomato-settings',
        name: 'zomato-settings',
        component: ReviewSettings,

        meta: { title: 'Zomato Settings' }
    },
    {
        path: 'yelp-settings',
        name: 'yelp-settings',
        component: ReviewSettings,

        meta: { title: 'Yelp Settings' }
    },
    {
        path: 'facebook-settings',
        name: 'facebook-settings',
        component: ReviewSettings,

        meta: { title: 'Facebook Settings' }
    },
    {
        path: 'woocommerce-settings',
        name: 'woocommerce-settings',
        component: ReviewSettings,

        meta: { title: 'WooCommerce Settings' }
    },
    {
        path: 'fluent-forms-settings',
        name: 'fluent-forms-settings',
        component: ReviewSettings,

        meta: { title: 'Fluent Forms Settings' }
    },
    {
        path: 'shoppable-by-hashtags',
        name: 'shoppable-by-hashtags',
        component: Shoppable,

        meta: { title: 'Shoppable by Hashtags' }
    },
    {
        path: 'advance-settings',
        name: 'advance-settings',
        component: AdvanceSettings,

        meta: { title: 'Advance Settings' }
    },
    {
        path: 'license-management',
        name: 'license-management',
        component: LicenseManagement,

        meta: { title: 'License Management' }
    },
    {
        path: 'management-settings',
        name: 'management-settings',
        component: ManagementSettings,

        meta: { title: 'Managers' }
    },
    {
        path: 'translation-management',
        name: 'translation-management',
        component: Translation,

        meta: { title: 'Translation Management' }
    },
];

const  toolsViewChildrenRoutes =[
    {
        path: 'export',
        name: 'export',
        component: ExportView,

        meta: { title: 'Export' }
    },
    {
        path: 'import',
        name: 'import',
        component: ImportView,

        meta: { title: 'Import' }
    }
];

export const routes = [
    {
        path: '/',
        component: GlobalView,
        props: true,
        children: [
            {
                path: '/',
                name: 'platforms',
                component: Platforms,

                meta: { title: 'Platforms' }
            },
            {
                path: 'templates',
                name: 'templates',
                component: AllTemplates,

                meta: { title: 'Templates' }
            },
            {
                path: 'testimonials',
                name: 'testimonials',
                component: AllTestimonials,
                meta: { title: 'Testimonials'}
            },
            {
                path: 'notifications',
                name: 'notifications',
                component: AllNotifications,
                meta: { title: 'Notifications'}
            },
            {
                path: '/reviews',
                name: 'reviews',
                component: allReviews,

                meta: { title: 'Reviews' }
            },
            {
                path: '/chat-widgets',
                name: 'chat-widgets',
                component: ChatWidgets,

                meta: { title: 'Chat Widgets' }
            },
            {
                path: '/custom-sources',
                name: 'custom-sources',
                component: CustomSources,

                meta: { title: 'Custom Sources' }
            },
            {
                path: '/settings/',
                component: Settings,
                children: globalSettingsViewChildrenRoutes,

                meta: { title: 'Settings' }
            },
            {
                path: '/tools/',
                component: Tools,
                children: toolsViewChildrenRoutes,

                meta: { title: 'Tools' }
            },
            {
                path: 'tiktok_feed',
                name: 'tiktok_feed',
                component: TikTokFeedLandingPage,
                meta: { title: 'TikTok Feed'}
            },
            {
                path: '/support/',
                name: 'support',
                component: SupportAndDocs,

                meta: { title: 'Support' }
            }
        ]
    },
    {
        path: '/edit-template/:template_id',
        name:'edit-template',
        component: EditTemplateView,

        meta: { title: 'Edit Review Template' }
    },
    {
        path: '/edit-chat-widget/:widget_id',
        name:'edit-chat-widget',
        component: EditChatView,

        meta: { title: 'Edit Chat Widget' }
    },
    {
        path: '/edit-twitter-template/:template_id',
        name:'edit-twitter-template',
        component: EditTwitterFeedView,

        meta: { title: 'Edit X (Twitter) Template' }
    },
    {
        path: '/edit-youtube-template/:template_id',
        name:'edit-youtube-template',
        component: EditYoutubeFeedView,

        meta: { title: 'Edit Youtube Template' }
    },
    {
        path: '/edit-instagram-template/:template_id',
        name:'edit-instagram-template',
        component: EditInstagramFeedView,

        meta: { title: 'Edit Instagram Template' }
    },
    {
        path: '/edit-facebook-feed-template/:template_id',
        name:'edit-facebook-feed-template',
        component: EditFacebookFeedView,

        meta: { title: 'Edit Facebook Template' }
    },
    {
        path: '/edit-tiktok-template/:template_id',
        name:'edit-tiktok-template',
        component: EditTiktokFeedView,

        meta: { title: 'Edit TikTok Template' }
    },
    {
        path: '/edit-social-wall-template/:template_id',
        name:'edit-social-wall-template',
        component: EditSocialWallView,

        meta: { title: 'Edit Social Wall Template' }
    },
    {
        path: '/edit-custom-source/:template_id',
        name:'edit-custom-source',
        component: EditCustomSourceView,

        meta: { title: 'Edit Custom Source' }
    },
];