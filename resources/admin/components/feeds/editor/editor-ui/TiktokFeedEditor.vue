<template>
    <div v-if="feed_config">
        <el-collapse-item name="1">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="accounts" :label="$t('Source')"/>
            </template>

            <span class="wpsr_source_settings_fields">
                <FeedEditorGroup
                    :fieldsMaps="source_settings"
                    :modelValue="feed_config.source_settings"
                    @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, source_settings: val })"
                />

                <div 
                    class="wpsn-accounts-list" 
                    v-if="pages.length > 0"
                    v-loading="accountSelectorLoading" 
                >
                    <div class="wpsn-accounts-wrapper">
                        <p class="wpsn-accounts-header-title wpsr-font-weight-500">{{ $t('Select Accounts') }}</p>
                        <div class="wpsn-accounts-card-wrapper">
                        <div
                            v-for="item in pages"
                            :key="item.user_id"
                            class="wpsn-accounts-card"
                            :class="{ 'wpsn-accounts-card--selected': feed_config.source_settings.selected_accounts.includes(item.open_id) }"
                            @click="toggleAccount(item)"
                        >
                            <div class="wpsn-accounts-label-row">
                            <img :src="getAccountIcon(item)" class="wpsn-accounts-icon wpsn-circle-avatar" :alt="item.username" />
                            <div class="wpsr-accounts-info">
                                <span class="wpsr-accounts-label">
                                    {{ item.display_name || item.username }}
                                </span>
                            </div>
                            <span
                                class="wpsn-accounts-checkbox"
                                :class="{
                                'checked': feed_config.source_settings.selected_accounts.includes(item.open_id)
                                }"
                                style="cursor: pointer;"
                            >
                                <InputCheckIcon v-if="feed_config.source_settings.selected_accounts && feed_config.source_settings.selected_accounts.includes(item.open_id)" />
                                <InputUncheckIcon v-else />
                            </span>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
                
                <div class="wpsr-editor-edit-item-field">
                    <el-button :disabled="pages.length <= 0" class="wpsr_primary_btn wpsr_btn_fullwidth" @click="fetchFeeds">
                        {{ $t('Fetch Feeds') }}
                    </el-button>
                </div>
            </span>
        </el-collapse-item>
        <el-collapse-item name="2">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="layout" :label="$t('Template')"/>
            </template>
            <FeedEditorGroup
                :fieldsMaps="layout_settings"
                :modelValue="feed_config"
                @update:modelValue="(val) => $emit('update:feed_config', val)"
            />
        </el-collapse-item>
        <el-collapse-item name="3">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="filters" :label="$t('Filters')"/>
            </template>
            <FeedEditorGroup v-if="feed_config"
                :fieldsMaps="filters_settings"
                :modelValue="feed_config.filters"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, filters: val })"
                @keyup.enter="fetch"
                @on-change="fetch"
            />
        </el-collapse-item>
        <el-collapse-item name="4">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="post" :label="$t('Feed')"/>
            </template>
            <FeedEditorGroup
                :key="feed_config.source_settings.feed_type"
                :fieldsMaps="feed_settings.free"
                :modelValue="feed_config.post_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, post_settings: val })"
            />
            <div :class="!has_pro ? 'wpsr_editor_edit_item-group-pro' : ''">
                <FeedEditorGroup
                    v-if="feed_config"
                    :fieldsMaps="feed_settings.pro"
                    :modelValue="feed_config.post_settings"
                    @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, post_settings: val })"
                />
            </div>
        </el-collapse-item>
        <el-collapse-item name="5" v-if="feed_config">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="header" :label="$t('Header')"/>
            </template>
            <div class="wpsr-switch-row">
                <span class="wpsr-editor-inside-left wpsr-element-center">{{ $t('Display Header') }}</span>
                <div class="wpsr-editor-inside-right">
                    <el-switch
                        :modelValue="feed_config.header_settings.display_header"
                        @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, header_settings: { ...feed_config.header_settings, display_header: val }})"
                        active-value="true"
                        inactive-value="false"
                    >
                    </el-switch>
                </div>
            </div>
            <div class="wpsr-inline-field" v-if="feed_config && feed_config.header_settings.display_header === 'true'">
                <span class="wpsr-editor-inside-left wpsr-element-center">{{ $t('Account to Display') }}</span>
                <div class="wpsr-editor-inside-right">
                    <el-select
                        :modelValue="feed_config.header_settings.account_to_show"
                        @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, header_settings: { ...feed_config.header_settings, account_to_show: val }})"
                        size="default"
                        clearable
                        placeholder="Select"
                        class="wpsr-text-input"
                    >
                        <el-option
                            v-for="item in pages"
                            :key="item.open_id"
                            :label="item.display_name"
                            :value="item.open_id"
                        >
                        </el-option>
                    </el-select>
                </div>

            </div>
            <FeedEditorGroup v-if="feed_config && feed_config.header_settings.display_header === 'true'"
                :fieldsMaps="header_settings"
                :modelValue="feed_config.header_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, header_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="6" v-if="feed_config">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="follow_button" :label="$t('Follow Button')"/>
            </template>
            <UpgradeToProBanner />
            <FeedEditorGroup v-if="feed_config"
                :fieldsMaps="follow_button_settings"
                :modelValue="feed_config.follow_button_settings"
                :proGroup="true"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, follow_button_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="7" v-if="feed_config && feed_config.layout_type === 'carousel'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="carousel" :label="$t('Carousel')"/>
            </template>
            <FeedEditorGroup v-if="feed_config"
                :fieldsMaps="carousel_settings"
                :modelValue="feed_config.carousel_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, carousel_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="8" v-if="feed_config && feed_config.post_settings.display_mode === 'popup'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="popup_box" :isPro="!has_pro" :label="$t('Popup')"/>
            </template>
            <FeedEditorGroup v-if="feed_config"
                :fieldsMaps="popup_settings"
                :modelValue="feed_config.popup_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, popup_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="9" v-if="feed_config && feed_config.layout_type !== 'carousel'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="pagination" :label="$t('Pagination')"/>
            </template>
            <FeedEditorGroup
                :fieldsMaps="pagination_settings"
                :modelValue="feed_config.pagination_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, pagination_settings: val })"
            />
        </el-collapse-item>
    </div>
</template>

<script>
import FeedEditorGroup from './../../../core-ui/editor/EditorGroup';
import UpgradeToProButton from '../../../views/advertise/UpgradeToProButton';
import EditorCollapsiblePanel from '../../../core-ui/editor/EditorCollapsiblePanel.vue';
import InputCheckIcon from '../../../pieces/icons/InputCheckIcon.vue';
import InputUncheckIcon from '../../../pieces/icons/InputUncheckIcon.vue';
import debounced from "lodash/debounce";
import UpgradeToProBanner from '../../../views/advertise/UpgradeToProBanner.vue';

export default {
    name: "TiktokFeedEditor",
    props: {
        feed_config: {
            type: Object,
            required: true
        },
        pages: {
            type: Array,
            default: () => []
        },
        image_settings: {
            type: Object,
            default: () => ({})
        }
    },
    emits: ['update:feed_config', 'fetchFeed'],
    components: {
        FeedEditorGroup,
        UpgradeToProButton,
        EditorCollapsiblePanel,
        InputCheckIcon,
        InputUncheckIcon,
        UpgradeToProBanner
    },
    data() {
        return {
            accountSelectorLoading: false,
            filters_settings: [
                {
                    fieldKey: 'total_posts_number',
                    type: 'number',
                    title: this.$t('Number of Feeds to Display'),
                    min: 1,
                    hide_tablet: true,
                    responsive: true,
                    max: this.has_pro ? 200 : 10,
                    tooltip: this.has_pro ? false : true,
                    tooltipText: this.$t('Upgrade to pro to display more TikTok videos.')
                },
                {
                    fieldKey: 'post_order',
                    type: 'select',
                    title: this.$t('Posts Order'),
                    event: 'on_change',
                    options: [
                        {
                            value: 'ascending',
                            label: this.$t('Newest')
                        },
                        {
                            value: 'descending',
                            label: this.$t('Oldest')
                        },
                        {
                            value: 'most_viewed',
                            label: this.$t('Most Viewed'),
                            disabled: !this.has_pro
                        },
                        {
                            value: 'most_liked',
                            label: this.$t('Most Liked'),
                            disabled: !this.has_pro
                        },
                        {
                            value: 'random',
                            label: this.$t('Random'),
                            disabled: !this.has_pro
                        }
                    ]
                },
                {
                    fieldKey: 'includes_inputs',
                    type: 'textarea',
                    title: this.$t('Show posts containing these words or hashtags'),
                    placeholder: this.$t('Type something and press enter....'),
                    disabled: !this.has_pro,
                    tooltip: true,
                    tooltipText: this.$t("You can use this setting to show posts that contain certain specific words or hashtags in the caption. <br/> Separate multiple words or hashtags using commas. ex: #hashtag1,word1,word2,#hashtag2"),
                },
                {
                    fieldKey: 'excludes_inputs',
                    type: 'textarea',
                    title: this.$t('Hide posts containing these words or hashtags'),
                    placeholder: this.$t('Type something and press enter....'),
                    disabled: !this.has_pro,
                    tooltip: true,
                    tooltipText: this.$t('You can use this setting to hiding posts that contain certain specific words or hashtags in the caption. <br/> Separate multiple words or hashtags using commas. ex: #hashtag1,word1,#hashtag2,word2')
                },
                {
                    fieldKey: 'hide_posts_by_id',
                    type: 'textarea',
                    title: this.$t('Hide Specific Posts'),
                    placeholder: this.$t('Type post id and press enter..'),
                    disabled: !this.has_pro,
                    autosize: {minRows: 2, maxRows: 4},
                    tooltip: true,
                    tooltipText: this.$t('You can find the ID of a Post by viewing the post on TikTok and copy/pasting the ID number from the end of the URL </br> ex: https://www.tiktok.com/@exampleuser/video/7293160505676533024 [ in this URL ID is "7293160505676533024" ]')
                }
            ],
            source_settings: [
                {
                    fieldKey: 'feed_type',
                    type: 'select',
                    title: this.$t('Feed Type'),
                    options: [
                        {
                            value: 'user_feed',
                            label: this.$t('User Account'),
                        },
                      // {
                      //     value: 'single_video_feed',
                      //     label: this.$t('Specific Videos'),
                      //     disabled: !this.has_pro,
                      // }
                    ]
                },
                // {
                //     fieldKey: 'selected_accounts',
                //     type: 'multiple_select',
                //     title: this.$t('Select Account'),
                //     label: 'display_name',
                //     value: 'open_id',
                //     pro_handler: !(true && this.has_pro),
                //     options: this.pages
                // },
              // {
              //   fieldKey: 'single_video_feed_ids',
              //   type: 'text',
              //   title: this.$t('Video ID '),
              //   placeholder: this.$t('Enter your video id using comma separator'),
              //   tooltip: true,
              //   tooltipText: this.$t('Enter any video ID to display specific TikTok videos. Separate multiple video ID\'s with comma. <br> You can find the ID of your TikTok video from the URL. <br> ex: "https://www.tiktok.com/@yourusername/video/7231522355498044673" copy the ID after "video/" and <br> the ID will look like "7231522355498044673"'),
              //   condition: {
              //     'key': 'feed_type',
              //     'selector': 'single_video_feed'
              //   }
              // },
                {
                    fieldKey: 'feed_count',
                    type: 'number',
                    title: this.$t('Total Feed'),
                    min: 1,
                    max: this.has_pro ? 100 : 10,
                    flex: true,
                    tooltip: true,
                    tooltipText: `This input field allows you to set how many posts to retrieve from the TikTok API. <br> Maximum limit is  ${!this.has_pro ? '10. Upgrade to pro to get more feeds' : 100}.`
                },
            ],
            layout_settings: [],
            pagination_settings: [
                {
                    fieldKey: 'pagination_type',
                    type: 'select',
                    title: this.$t('Pagination Type'),
                    options: [
                        {
                            value: 'none',
                            label: 'None'
                        },
                        {
                            value: 'load_more',
                            label: 'Load More'
                        }
                    ],
                    tooltip: true,
                    tooltipText: this.$t('The Load More button will only display on your site\'s preview/live pages/posts <br> not while you\'re in editing mode in the WordPress Editor.')
                },
                {
                    fieldKey: 'load_more_button_text',
                    type: 'text',
                    title: this.$t('Load More Button Text'),
                    placeholder: this.$t('Load More'),
                    condition: {
                        'key': 'pagination_type',
                        'selector': 'load_more'
                    }
                },
                {
                    fieldKey: 'paginate',
                    type: 'number',
                    title: this.$t('Feeds Per Page'),
                    min: 1,
                    max: 20,
                    flex: true,
                    condition: {
                        'key': 'pagination_type',
                        'selector': 'load_more'
                    }
                },
            ],
            feed_settings: {
                free: [],
                pro: []
            },
            header_settings: [
                {
                    fieldKey: 'display_profile_photo',
                    type: 'switch',
                    title: this.$t('Display Profile Photo'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_page_name',
                    type: 'switch',
                    title: this.$t('Display Account Name'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_description',
                    type: 'switch',
                    title: this.$t('Display Description'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_likes_counter',
                    type: 'switch',
                    title: this.$t('Display Like Counter'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,

                },
                {
                    fieldKey: 'display_followers_counter',
                    type: 'switch',
                    title: this.$t('Display Follower Counter'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_following_counter',
                    type: 'switch',
                    title: this.$t('Display Follow Counter'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                }
            ],
            carousel_settings: [
                {
                    fieldKey: 'autoplay',
                    type: 'switch',
                    title: this.$t('Autoplay'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'autoplay_speed',
                    type: 'number',
                    title: this.$t('Autoplay Speed'),
                    min: 1,
                    max: 50000,
                    flex: true
                },
                {
                    fieldKey: 'responsive_slides_to_show',
                    type: 'number',
                    title: this.$t('Slides to Show'),
                    min: 1,
                    max: 10,
                    flex: true,
                    responsive: true
                },
                {
                    fieldKey: 'responsive_slides_to_scroll',
                    type: 'number',
                    title: this.$t('Slides to Scroll'),
                    min: 1,
                    max: 10,
                    flex: true,
                    responsive: true
                },
                {
                    fieldKey: 'navigation',
                    type: 'select',
                    title: this.$t('Navigation Type'),
                    options: [
                        {
                            value: 'both',
                            label: this.$t('Arrows and Dots')
                        },
                        {
                            value: 'arrow',
                            label: this.$t('Arrows')
                        },
                        {
                            value: 'dot',
                            label: this.$t('Dots')
                        },
                        {
                            value: 'none',
                            label: this.$t('None')
                        }
                    ]
                },
            ],
            popup_settings: [
                {
                    fieldKey: 'display_sidebar',
                    type: 'switch',
                    title: this.$t('Display Sidebar'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_video',
                    type: 'switch',
                    title: this.$t('Display Video'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_profile_photo',
                    type: 'switch',
                    title: this.$t('Display Profile Photo'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_username',
                    type: 'switch',
                    title: this.$t('Display Username'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_caption',
                    type: 'switch',
                    title: this.$t('Display Caption'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_date',
                    type: 'switch',
                    title: this.$t('Display Date'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                  fieldKey: 'display_cta_btn',
                  type: 'switch',
                  title: this.$t('Display Call to Action Button'),
                  active_value: 'true',
                  inactive_value: 'false',
                  disabled: !this.has_pro,
                }
            ],
            follow_button_settings: [
                {
                  fieldKey: 'display_follow_button',
                  type: 'switch',
                  title: this.$t('Display Follow Button'),
                  active_value: 'true',
                  inactive_value: 'false',
                  disabled: !this.has_pro,
                },
                {
                    fieldKey: 'follow_button_position',
                    type: 'select',
                    title: this.$t('Share Button Position'),
                    disabled: !this.has_pro,
                    options: [
                        {
                            value: 'header',
                            label: this.$t('Header'),
                            disabled: !this.has_pro,
                        },
                        {
                            value: 'footer',
                            label: this.$t('Footer'),
                            disabled: !this.has_pro,
                        },
                        {
                            value: 'both',
                            label: this.$t('Both'),
                            disabled: !this.has_pro,
                        }
                    ]
                },
                {
                  fieldKey: 'follow_button_text',
                  type:'text',
                  title: this.$t('Button Text'),
                  disabled: !this.has_pro,
                }
            ],
            feed_settings_backup: [],
            feed_settings_backup_free: [],
            feed_settings_backup_pro: []
        }
    },
    methods: {
        fetchFeeds() {
            this.$emit('fetchFeed');
        },
        toggleAccount(value, fieldKey = 'selected_accounts') {
            this.accountSelectorLoading = true;
            
            const accountIds = [...(this.feed_config.source_settings[fieldKey] || [])];
            const index = accountIds.indexOf(value.open_id);

            if (index === -1) {
                if (!this.has_pro && accountIds.length >= 1) {
                    accountIds.splice(0, accountIds.length, value.open_id);
                    this.handlePro();
                } else {
                    accountIds.push(value.open_id);
                }
            } else {
                if (!this.has_pro && accountIds.length === 1) {
                    this.handlePro();
                } else {
                    accountIds.splice(index, 1);
                }
            }

            const updatedConfig = {
                    ...this.feed_config,
                    source_settings: {
                    ...this.feed_config.source_settings,
                    [fieldKey]: accountIds
                }
            };
            
            this.$emit('update:feed_config', updatedConfig);
            
            // Wait for DOM update and add a small delay to ensure loading is visible
            this.$nextTick(() => {
                setTimeout(() => {
                    this.accountSelectorLoading = false;
                }, 300);
            });
        },
        getAccountIcon(account){
            return account.avatar_url || ''; 
        },
        fetch : debounced(function () {
            this.$emit('fetchFeed');
        }, 500),

        setAccount(val) {
            if (!this.has_pro && val.length > 1) {
                this.feed_config.source_settings.selected_accounts = [];
                this.feed_config.source_settings.selected_accounts.push(val[0]);
                this.handlePro();
            }
        },
        setData() {
            this.feed_settings['free'] = [
                {
                  fieldKey: 'resolution',
                  type: 'select',
                  title: this.$t('Images Resolution'),
                  condition: {
                    data: this.image_settings,
                    key: 'optimized_images',
                    selector: "true"
                  },
                  options: [
                    {
                      value: 'thumb',
                      label: this.$t('Thumbnail (150*150)')
                    },
                    {
                      value: 'low',
                      label: this.$t('Medium (320*320)')
                    },
                    {
                      value: 'full',
                      label: this.$t('Full Size (640*640)')
                    }
                  ]
                },
                {
                    fieldKey: 'display_mode',
                    type: 'select',
                    title: this.$t('Open Post In'),
                    options: [
                        {
                            value: 'none',
                            label: this.$t('None'),
                        },
                        {
                            value: 'tiktok',
                            label: this.$t('Open on TikTok')
                        },
                        {
                            value: 'popup',
                            label: this.$t('Open in Popup Box'),
                            disabled: !this.has_pro,
                        },
                    ]
                },
                {
                    fieldKey: 'display_author_photo',
                    type: 'switch',
                    title: this.$t('Display Author Photo'),
                    active_value: 'true',
                    inactive_value: 'false',
                    condition: {
                        'key': 'feed_type',
                        'data': this.feed_config.source_settings,
                        'operator': '==',
                        'selector': 'user_feed',
                    }
                },
                {
                    fieldKey: 'display_play_icon',
                    type: 'switch',
                    title: this.$t('Display Play Icon'),
                    active_value: 'true',
                    inactive_value: 'false',
                    condition: {
                        'key': 'feed_type',
                        'data': this.feed_config.source_settings,
                        'operator': '==',
                        'selector': 'video_feed',
                    }
                },
                {
                  fieldKey: 'display_platform_icon',
                  type: 'switch',
                  title: this.$t('Display Platform Icon'),
                  active_value: 'true',
                  inactive_value: 'false',
                  condition: {
                    'key': 'feed_type',
                    'data': this.feed_config.source_settings,
                    'operator': '==',
                    'selector': 'user_feed',
                  }
                },
                {
                    fieldKey: 'display_author_name',
                    type: 'switch',
                    title: this.$t('Display Author Name'),
                    active_value: 'true',
                    inactive_value: 'false',
                    condition: {
                        'key': 'feed_type',
                        'data': this.feed_config.source_settings,
                        'operator': '==',
                        'selector': 'user_feed',
                    }
                },
                {
                    fieldKey: 'display_description',
                    type: 'switch',
                    title: this.$t('Display Description'),
                    active_value: 'true',
                    inactive_value: 'false',
                    condition: {
                        'key': 'feed_type',
                        'data': this.feed_config.source_settings,
                        'operator': 'includes',
                        'selector': ['user_feed', 'video_feed'],
                    }
                },
                {
                    fieldKey: 'content_length',
                    type: 'number',
                    min: 0,
                    title: this.$t('Trim Description Words'),
                    flex: true,
                    disabled : !this.has_pro,
                    condition: {
                        'key': 'feed_type',
                        'data': this.feed_config.source_settings,
                        'operator': '==',
                        'selector': 'user_feed',
                    }
                },
                {
                    fieldKey: 'display_date',
                    type: 'switch',
                    title: this.$t('Display Date'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                    condition: {
                          'key': 'feed_type',
                          'data': this.feed_config.source_settings,
                          'operator': 'includes',
                          'selector': ['user_feed'],
                    }
                },
            ];
            this.feed_settings['pro'] = [
                {
                    fieldKey: 'display_views_count',
                    type: 'switch',
                    title: this.$t('Display Views Count'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro,
                    condition: {
                        'key': 'feed_type',
                        'data': this.feed_config.source_settings,
                        'operator': '==',
                        'selector': 'user_feed',
                    }
                },
                {
                    fieldKey: 'display_likes_count',
                    type: 'switch',
                    title: this.$t('Display Likes Count'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro,
                    condition: {
                        'key': 'feed_type',
                        'data': this.feed_config.source_settings,
                        'operator': '==',
                        'selector': 'user_feed',
                    }
                },
                {
                    fieldKey: 'display_comments_count',
                    type: 'switch',
                    title: this.$t('Display Comments Count'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro,
                    condition: {
                        'key': 'feed_type',
                        'data': this.feed_config.source_settings,
                        'operator': '==',
                        'selector': 'user_feed',
                    }
                }
            ]
        },
        setLayouts() {
            this.layout_settings = [
                {
                    fieldKey: 'layout_type',
                    type: 'image_select',
                    title: this.$t('Layout Type'),
                    condition: {
                        'key': 'feed_type',
                        'data': this.feed_config.source_settings,
                        'operator': '!=',
                        'selector': 'album_feed'
                    },
                    options: [
                        {
                            key: 'grid',
                            title: this.$t('Grid'),
                            img: this.assets_url + '/images/template/global-template/grid.png',
                        },
                        {
                          key: 'masonry',
                          title: this.$t('Masonry'),
                          pro: !this.has_pro,
                          img: this.assets_url + '/images/template/global-template/masonry.png',
                        },
                        {
                            key: 'carousel',
                            title: this.$t('Carousel'),
                            pro: !this.has_pro,
                            img: this.assets_url + '/images/template/global-template/slider.png',
                        },
                    ]
                },
                {
                    fieldKey: 'template',
                    type: 'image_select',
                    options: [
                        {
                            key: 'template1',
                            title: 'Template 1',
                            img: this.assets_url + '/images/template/tiktok-template/template1.png'
                        },
                        {
                            key: 'template2',
                            title: 'Template 2',
                            img: this.assets_url + '/images/template/tiktok-template/template1.png',
                            pro: !this.has_pro,
                        },
                    ]
                },
                {
                  title: this.$t('Layout Styling'),
                  type: 'section_title',
                  condition: {
                    'key': 'layout_type',
                    'operator': 'Arr',
                    'selector': ['grid', 'masonry']
                  },
                },
                {
                    fieldKey: 'responsive_column_number',
                    type: 'select',
                    title: this.$t('Number of Columns'),
                    responsive: true,
                    condition: {
                        'key': 'layout_type',
                        'operator': 'Arr',
                        'selector': ['grid', 'masonry']
                    },
                    options: [
                        {
                            value: '12',
                            label: this.$t('1 Column')
                        },
                        {
                            value: '6',
                            label: this.$t('2 Column')
                        },
                        {
                            value: '4',
                            label: this.$t('3 Column')
                        },
                        {
                            value: '3',
                            label: this.$t('4 Column')
                        },
                        {
                            value: '5',
                            label: this.$t('5 Column')
                        },
                        {
                            value: '2',
                            label: this.$t('6 Column')
                        },
                        {
                            value: '1',
                            label: this.$t('10 Column')
                        },
                    ]
                },
                {
                    fieldKey: 'column_gaps',
                    type: 'select',
                    title: this.$t('Columns Gap'),
                    condition: {
                        'key': 'layout_type',
                        'operator': 'Arr',
                        'selector': ['grid', 'masonry']
                    },
                    options: [
                        {
                            value: 'default',
                            label: this.$t('Default')
                        },
                        {
                            value: 'no_gap',
                            label: this.$t('No Gap')
                        },
                        {
                            value: 'narrow',
                            label: this.$t('Narrow')
                        },
                        {
                            value: 'small',
                            label: this.$t('Small')
                        },
                        {
                            value: 'wide',
                            label: this.$t('Wide')
                        },
                        {
                            value: 'wider',
                            label: this.$t('Wider')
                        }
                    ]
                },
            ]
        },
    },
    watch: {
        'feed_config': {
            handler(new_value) {
                this.setData();
                this.setLayouts();
            },
            deep: true
        }
    },
    mounted() {
        this.feed_settings_backup_free = this.feed_settings.free;
        this.feed_settings_backup_pro = this.feed_settings.pro;
        this.setData();
        this.setLayouts();
    }
}
</script>