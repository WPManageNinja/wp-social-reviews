<template>
    <div class="wpsr-support-page">
        <!-- Page Header -->
        <div class="wpsr-support-header">
            <h1 class="wpsr-page-title">Support & Documentation</h1>
            <p class="wpsr-page-subtitle">Get help, find resources, and connect with our support team</p>
        </div>

        <!-- Quick Support Cards -->
        <div class="wpsr-support-section">
            <h2 class="wpsr-section-title">Quick Support</h2>
            <div class="wpsr-support-cards">
                <div v-for="(item, i) in items" :key="i" class="wpsr-support-card">
                    <div class="wpsr-support-card-icon">
                        <img :src="assetsUrl(item.img)" :alt="item.title" />
                    </div>
                    <div class="wpsr-support-card-content">
                        <h3 class="wpsr-support-card-title">{{ $t(item.title) }}</h3>
                        <p class="wpsr-support-card-description">{{ $t(item.des) }}</p>
                        <el-button
                            type="primary"
                            size="default"
                            @click="openUrl(item.url)"
                            class="wpsr-support-card-button wpsr_primary_btn"
                        >
                            {{ item.btn }}
                            <el-icon class="el-icon--right"><ArrowRight /></el-icon>
                        </el-button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documentation Section -->
        <div class="wpsr-support-section">
            <h2 class="wpsr-section-title">Documentation & Guides</h2>
            <div class="wpsr-docs-grid">
                <div class="wpsr-docs-main">
                    <div class="wpsr-docs-card">
                        <div class="wpsr-docs-card-header">
                            <el-icon class="wpsr-docs-icon"><Document /></el-icon>
                            <h3>Complete Documentation</h3>
                        </div>
                        <div class="wpsr-docs-card-body wpsr-scrollbar">
                            <div class="wpsr-docs-categories">
                                <div v-for="(category, index) in docs" :key="index" class="wpsr-docs-category">
                                    <h4 class="wpsr-docs-category-title">{{ category.title }}</h4>
                                    <ul class="wpsr-docs-list">
                                        <li v-for="(doc, key) in category.docs" :key="key" class="wpsr-docs-item">
                                            <a
                                                :href="doc.url"
                                                target="_blank"
                                                rel="noopener"
                                                class="wpsr-docs-link"
                                            >
                                                <el-icon class="wpsr-docs-link-icon"><Link /></el-icon>
                                                {{ doc.title }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Cards -->
                <div class="wpsr-docs-sidebar">
                    <!-- Terms & Policies -->
                    <div class="wpsr-sidebar-card">
                        <div class="wpsr-sidebar-card-header">
                            <el-icon class="wpsr-sidebar-icon"><Files /></el-icon>
                            <h3>Legal & Policies</h3>
                        </div>
                        <div class="wpsr-sidebar-card-body">
                            <ul class="wpsr-sidebar-list">
                                <li>
                                    <a href="https://wpsocialninja.com/terms-conditions/" target="_blank" class="wpsr-sidebar-link">
                                        <el-icon><Link /></el-icon>
                                        Terms and Conditions
                                    </a>
                                </li>
                                <li>
                                    <a href="https://wpsocialninja.com/privacy-policy/" target="_blank" class="wpsr-sidebar-link">
                                        <el-icon><Link /></el-icon>
                                        Privacy Policy
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Community & Feedback -->
                    <div class="wpsr-sidebar-card">
                        <div class="wpsr-sidebar-card-header">
                            <el-icon class="wpsr-sidebar-icon"><Star /></el-icon>
                            <h3>Community & Feedback</h3>
                        </div>
                        <div class="wpsr-sidebar-card-body">
                            <p class="wpsr-sidebar-description">We'd love your help, feedback and support.</p>
                            <ul class="wpsr-sidebar-list">
                                <li>
                                    <a href="https://wordpress.org/support/plugin/wp-social-reviews/reviews/#new-post" target="_blank" class="wpsr-sidebar-link">
                                        <el-icon><Star /></el-icon>
                                        Rate our plugin
                                    </a>
                                </li>
                                <li>
                                    <a href="https://wpmanageninja.com/support-tickets/" target="_blank" class="wpsr-sidebar-link">
                                        <el-icon><ChatDotRound /></el-icon>
                                        Open Support Ticket
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import { ArrowRight, Document, Link, Files, Star, ChatDotRound } from '@element-plus/icons-vue';

    export default {
        name: 'SupportDocumentation',
        components: {
            ArrowRight,
            Document,
            Link,
            Files,
            Star,
            ChatDotRound
        },
        data() {
            return {
              items: {
                support1: {
                  title: 'Need And Expert Support?',
                  des: 'Our EXPERTS would like to assist you for your query and any customization.',
                  url: 'https://wpmanageninja.com/support-tickets/',
                  img: '/images/supports/support.png',
                  btn: 'Contact Support'
                },
                support2: {
                  title: 'Love this Plugin?',
                  des: 'Please write a review in wp.org plugin repository. We appreciate it!',
                  url: 'https://wordpress.org/support/plugin/wp-social-reviews/reviews/#new-post',
                  img: '/images/supports/like.png',
                  btn: 'Write a Review'
                },
                support3: {
                  title: 'Found a Bug?',
                  des: 'Please report us and we promise we will fix that as soon as humanly possible',
                  url: 'https://wpmanageninja.com/support-tickets/',
                  img: '/images/supports/bug.png',
                  btn: 'Let us know'
                }
              },
                docs: {
                    getting_started: {
                        title: 'Getting Started with WP Social Ninja',
                        docs: [
                            {
                              title: 'WP Social Ninja Introduction',
                              url: 'https://wpsocialninja.com/docs/getting-started-with-wp-social-ninja/'
                            },
                            {
                                title: 'Prerequisites of Installation',
                                url: 'https://wpsocialninja.com/docs/prerequisites-of-wp-social-ninja-installation/'
                            },
                            {
                                title: 'How to Install, Upgrade and Activate License',
                                url: 'https://wpsocialninja.com/docs/how-to-install-upgrade-and-activate-license/'
                            }
                        ]
                    },
                    getting_started_user_interface: {
                      title: 'Getting Started with the User Interface',
                      docs: [
                        {
                          title: 'What WP Social Ninja Offers',
                          url: 'https://wpsocialninja.com/docs/what-wp-social-review-offers/'
                        },
                        {
                          title: 'User Interface',
                          url: 'https://wpsocialninja.com/docs/getting-started-with-the-user-interface-of-wp-social-ninja/'
                        },
                        {
                          title: 'Platforms',
                          url: 'https://wpsocialninja.com/docs/all-platforms-of-wp-social-ninja/'
                        },
                        {
                          title: 'Reviews',
                          url: 'https://wpsocialninja.com/docs/all-reviews/'
                        },
                        {
                          title: 'Templates',
                          url: 'https://wpsocialninja.com/docs/all-templates/'
                        },
                        {
                          title: 'Settings',
                          url: 'https://wpsocialninja.com/docs/settings-of-wp-social-ninja/'
                        },
                        {
                          title: 'Advanced Settings',
                          url: 'https://wpsocialninja.com/docs/advanced-settings-social-feeds-wp-social-ninja/'
                        }
                      ]
                    },
                    social_reviews_configuration: {
                        title: 'Business Reviews',
                        docs: [
                            {
                              title: 'Business Reviews',
                              url: 'https://wpsocialninja.com/docs/social-media-reviews-wp-social-ninja/'
                            },
                            {
                                title: 'Google Reviews',
                                url: 'https://wpsocialninja.com/docs/google-configuration-social-reviews-wp-social-ninja/'
                            },
                            {
                                title: 'Airbnb Reviews',
                                url: 'https://wpsocialninja.com/docs/airbnb-configuration-social-reviews-wp-social-ninja/'
                            },
                            {
                                title: 'Yelp Reviews',
                                url: 'https://wpsocialninja.com/docs/yelp-configuration-social-reviews-wp-social-ninja/'
                            },
                            {
                                title: 'Tripadvisor Reviews',
                                url: 'https://wpsocialninja.com/docs/tripadvisor-configuration-social-reviews-wp-social-ninja/'
                            },
                            {
                                title: 'Amazon Reviews',
                                url: 'https://wpsocialninja.com/docs/amazon-configuration-social-reviews-wp-social-ninja/'
                            },
                            {
                                title: 'AliExpress Reviews',
                                url: 'https://wpsocialninja.com/docs/aliexpress-configuration-social-reviews-wp-social-ninja/'
                            },
                            {
                                title: 'Booking.com Reviews',
                                url: 'https://wpsocialninja.com/docs/booking-com-configuration-social-reviews-wp-social-ninja/'
                            },
                            {
                              title: 'Facebook Reviews',
                              url: 'https://wpsocialninja.com/docs/facebook-configuration-social-reviews-wp-social-ninja/'
                            },
                            {
                              title: 'WooCommerce Reviews',
                              url: 'https://wpsocialninja.com/docs/woocommerce-reviews-social-reviews-wp-social-ninja/'
                            },
                            {
                              title: 'How to Add Fluent Forms Reviews',
                              url: 'https://wpsocialninja.com/docs/fluent-forms-review/'
                            },
                            {
                              title: 'How to Add Custom Reviews',
                              url: 'https://wpsocialninja.com/docs/how-to-add-custom-reviews-with-wp-social-ninja/'
                            },
                            {
                                title: 'How to create a review template',
                                url: 'https://wpsocialninja.com/docs/create-a-template-social-reviews-wp-social-ninja/'
                            },
                            {
                                title: 'Template Layouts',
                                url: 'https://wpsocialninja.com/docs/template-layouts-social-reviews-wp-social-ninja/'
                            },
                            {
                                title: 'Layout Settings',
                                url: 'https://wpsocialninja.com/docs/layout-settings-social-reviews-wp-social-ninja/'
                            },
                            {
                              title: 'How to Configure Schema Snippet',
                              url: 'https://wpsocialninja.com/docs/how-to-configure-schema-snippet-with-wp-social-ninja/'
                            },
                          {
                            title: 'Get Reviews via QR Code',
                            url: 'https://wpsocialninja.com/docs/generate-qr-code-for-reviews/'
                          }
                        ]
                    },
                    testimonials_configuration: {
                      title: 'Testimonials',
                      docs: [
                        {
                          title: 'How to Add Testimonials',
                          url: 'https://wpsocialninja.com/docs/how-to-add-testimonials-with-wp-social-ninja/'
                        },
                        {
                          title: 'How to Collect Testimonials with Fluent Forms',
                          url: 'https://wpsocialninja.com/docs/how-to-collect-testimonials-with-fluent-forms/'
                        }
                      ]
                    },
                    notification_popup_configuration: {
                      title: 'Notification Popup',
                      docs: [
                        {
                          title: 'Notification Popup',
                          url: 'https://wpsocialninja.com/docs/notification-popup/'
                        }
                      ]
                    },
                    social_feeds: {
                      title: 'Social Feeds',
                      docs: [
                        {
                          title: 'Social Feeds',
                          url: 'https://wpsocialninja.com/docs/social-feeds-social-ninja/'
                        }
                      ]
                    },
                    twitter_configuration: {
                        title: 'X (Twitter) Feed',
                        docs: [
                            {
                                title: 'X (Twitter) Configuration',
                                url: 'https://wpsocialninja.com/docs/twitter-configuration-twitter-feeds-wp-social-ninja/'
                            },
                            {
                                title: 'Create your own X (Twitter) App',
                                url: 'https://wpsocialninja.com/docs/create-your-twitter-app-wp-social-ninja/'
                            },
                            {
                                title: 'X (Twitter) Feed Layout',
                                url: 'https://wpsocialninja.com/docs/twitter-feed-layout-i-twitter-feeds-i-wp-social-ninja/'
                            },
                            {
                              title: 'X (Twitter) Feed Settings',
                              url: 'https://wpsocialninja.com/docs/twitter-settings-twitter-feeds-wp-social-ninja/'
                            },
                        ]
                    },
                    youtube_configuration: {
                        title: 'YouTube Feed',
                        docs: [
                          {
                            title: 'YouTube Configuration',
                            url: 'https://wpsocialninja.com/docs/youtube-configuration-youtube-feeds-wp-social-ninja/'
                          },
                          {
                            title: 'YouTube Feed Type',
                            url: 'https://wpsocialninja.com/docs/youtube-feed-type-social-feeds-wp-social-ninja-2/'
                          },
                          {
                            title: 'YouTube Template Layout',
                            url: 'https://wpsocialninja.com/docs/youtube-feed-layout-social-feeds-wp-social-ninja/'
                          },
                          {
                            title: 'YouTube Settings',
                            url: 'https://wpsocialninja.com/docs/youtube-settings-social-feeds-wp-social-ninja/'
                          },
                          {
                            title: 'YouTube Feed Settings',
                            url: 'https://wpsocialninja.com/docs/youtube-feed-settings-social-feeds-wp-social-ninja/'
                          },
                        ]
                    },
                    instagram_configuration: {
                      title: 'Instagram Feed',
                      docs: [
                        {
                          title: 'Instagram Configuration',
                          url: 'https://wpsocialninja.com/docs/instagram-configuration-social-feeds-wp-social-ninja/'
                        },
                        {
                          title: 'Instagram Access Token',
                          url: 'https://wpsocialninja.com/docs/instagram-access-token-instagram-feeds-wp-social-ninja/'
                        },
                        {
                          title: 'Instagram Feed Layout',
                          url: 'https://wpsocialninja.com/docs/instagram-feed-layout-i-social-feeds-i-wp-social-ninja/'
                        },
                        {
                          title: 'Instagram Settings',
                          url: 'https://wpsocialninja.com/docs/instagram-set-up-social-feeds-wp-social-ninja/'
                        },
                        {
                          title: 'Instagram Shoppable Feed',
                          url: 'https://wpsocialninja.com/docs/instagram-shoppable-feed/'
                        },
                        {
                          title: 'Optimize Images for Instagram',
                          url: 'https://wpsocialninja.com/docs/optimize-images-for-instagram-social-feeds-wp-social-ninja/'
                        },
                        {
                          title: 'Instagram Feed GDPR Compliance',
                          url: 'https://wpsocialninja.com/docs/instagram-feed-gdpr-compliance-social-feeds-wp-social-ninja/'
                        },
                        {
                          title: 'Instagram Feed Issue Email Report',
                          url: 'https://wpsocialninja.com/docs/instagram-feed-issue-email-report-social-feeds-wp-social-ninja/'
                        },
                        {
                          title: 'Instagram API Error Message Reference',
                          url: 'https://wpsocialninja.com/docs/instagram-api-error-message-reference-social-feeds-wp-social-ninja/'
                        },
                        {
                          title: 'How to Convert an Instagram Personal Account to a Business Account',
                          url: 'https://wpsocialninja.com/docs/how-to-convert-an-instagram-personal-account-to-a-business-account/'
                        },
                        {
                          title: 'How to Switch Your Instagram Account to a Professional or Creator Account',
                          url: 'https://wpsocialninja.com/docs/instagram-business-basic-social-feeds-wp-social-ninja/'
                        },
                      ]
                    },
                    facebook_configuration: {
                      title: 'Facebook Feed',
                      docs: [
                        {
                          title: 'Facebook Configuration',
                          url: 'https://wpsocialninja.com/docs/facebook-feed-integration-with-wp-social-ninja/'
                        },
                        {
                          title: 'Facebook Feed Layout Settings',
                          url: 'https://wpsocialninja.com/docs/facebook-feed-layout-styling-with-wp-social-ninja/'
                        },
                        {
                          title: 'Facebook Feed Settings',
                          url: 'https://wpsocialninja.com/docs/how-to-customize-facebook-feed-with-wp-social-ninja/'
                        },
                        {
                          title: 'Facebook Events Access Token',
                          url: 'https://wpsocialninja.com/docs/facebook-events-access-token/'
                        },
                        {
                          title: 'How to Display Facebook Events Feed with WP Social Ninja',
                          url: 'https://wpsocialninja.com/docs/how-to-display-facebook-events/'
                        },
                        {
                          title: 'How to Display Facebook Events Feed with WP Social Ninja',
                          url: 'https://wpsocialninja.com/docs/facebook-feed-date-range-social-feeds-wp-social-ninja/'
                        },
                        {
                          title: 'WP Social Ninja’s Instagram/Facebook Reauthorize App',
                          url: 'https://wpsocialninja.com/docs/how-to-reauthorize-the-wp-social-ninja-instagram-facebook-app/'
                        },
                      ]
                    },
                    tiktok_configuration: {
                      title: 'TikTok Feed',
                      docs: [
                        {
                          title: 'TikTok Feed Activation',
                          url: 'https://wpsocialninja.com/docs/tiktok-feed-activation/'
                        },
                        {
                          title: 'TikTok Feed Configuration',
                          url: 'https://wpsocialninja.com/docs/tiktok-feed-configuration/'
                        },
                        {
                          title: 'TikTok Feed Template',
                          url: 'https://wpsocialninja.com/docs/tiktok-feed-template/'
                        },
                        {
                          title: 'TikTok Feed Settings',
                          url: 'https://wpsocialninja.com/docs/tiktok-feed-settings/'
                        },
                      ]
                    },
                    social_chat_configuration: {
                        title: 'Social Chats',
                        docs: [
                            {
                                title: 'Social Chats',
                                url: 'https://wpsocialninja.com/docs/social-chat-wp-social-ninja/'
                            },
                            {
                                title: 'All-in-One Configuration',
                                url: 'https://wpsocialninja.com/docs/social-chat-widget-configuration-wp-social-ninja/'
                            },
                            {
                                title: 'Template Layout',
                                url: 'https://wpsocialninja.com/docs/template-layout-social-chat-wp-social-ninja/'
                            },
                            {
                                title: 'Settings',
                                url: 'https://wpsocialninja.com/docs/settings-social-chat-wp-social-ninja/'
                            },
                            {
                                title: 'Style',
                                url: 'https://wpsocialninja.com/docs/style-social-chat-wp-social-ninja/'
                            },
                            {
                              title: 'Add Custom Button Chat Widget',
                              url: 'https://wpsocialninja.com/docs/how-to-add-custom-button-chat-widget-on-your-website/'
                            },
                        ]
                    },
                    global_settings: {
                    title: 'Global Settings',
                    docs: [
                      {
                        title: 'How to use a Shortcode',
                        url: 'https://wpsocialninja.com/docs/how-to-use-a-shortcode-social-reviews-wp-social-ninja/'
                      },
                      {
                        title: 'How to Sync Reviews Manually',
                        url: 'https://wpsocialninja.com/docs/how-to-sync-reviews-manually/'
                      },
                      {
                        title: 'Permission Manager',
                        url: 'https://wpsocialninja.com/docs/manager-wp-social-ninja/'
                      },
                      {
                        title: 'Translate Option',
                        url: 'https://wpsocialninja.com/docs/translate-option-wp-social-ninja/'
                      },
                      {
                        title: 'Shoppable by Hashtags',
                        url: 'https://wpsocialninja.com/docs/shoppable-by-hashtags-social-feeds-wp-social-ninja/'
                      }
                    ]
                  },
                    design: {
                      title: 'Design',
                      docs: [
                        {
                          title: 'Style',
                          url: 'https://wpsocialninja.com/docs/website-styling-for-feeds-and-reviews/'
                        }
                      ]
                    },
                    export_import: {
                      title: 'Export/Import',
                      docs: [
                        {
                          title: 'How to Export/Import Custom Reviews',
                          url: 'https://wpsocialninja.com/docs/how-to-export-import-custom-reviews/'
                        }
                      ]
                    },
                    page_builders: {
                    title: 'Page Builders',
                    docs: [
                      {
                        title: 'How to Embed WP Social Ninja Template with Elementor Page Builder',
                        url: 'https://wpsocialninja.com/docs/how-to-embed-wp-social-ninja-template-with-elementor-page-builder/'
                      },
                      {
                        title: 'How to Embed WP Social Ninja Template with Oxygen Page Builder',
                        url: 'https://wpsocialninja.com/docs/how-to-embed-wp-social-ninja-template-with-oxygen-page-builder/'
                      },
                      {
                        title: 'How to Embed WP Social Ninja Template with Beaver Page Builder',
                        url: 'https://wpsocialninja.com/docs/beaver-page-builder/'
                      }
                    ]
                  },
                    changelog: {
                      title: 'Changelog',
                      docs: [
                        {
                          title: 'Changelog',
                          url: 'https://wpsocialninja.com/docs/change-log/'
                        }
                      ]
                    },
                }
            }
        },
        methods: {
            assetsUrl(path) {
              return  this.assets_url + path;
            },
            openUrl(url) {
                window.open(url, '_blank');
            }
        }
    }
</script>
