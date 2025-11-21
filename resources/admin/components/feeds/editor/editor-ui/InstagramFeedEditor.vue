<template>
    <div v-if="feed_config">
        <el-collapse-item name="1">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="accounts" :label="$t('Accounts')"/>
            </template>
            <span class="wpsr_source_settings_fields">
                <FeedEditorGroup
                    :fieldsMaps="source_settings"
                    :modelValue="feed_config.source_settings"
                    @update:modelValue="handleSourceSettingsUpdate"
                    @change="handleSourceChange"
                />

                <div 
                    class="wpsn-accounts-list" 
                    v-if="connected_ids && connected_ids.length > 0"
                    v-loading="accountSelectorLoading" 
                >
                    <div class="wpsn-accounts-wrapper">
                        <p class="wpsn-accounts-header-title wpsr-font-weight-500">{{ $t('Select Accounts') }}</p>
                        <div class="wpsn-accounts-card-wrapper">
                        <div
                            v-for="item in connected_ids"
                            :key="item.user_id"
                            class="wpsn-accounts-card"
                            :class="{ 'wpsn-accounts-card--selected': feed_config.source_settings.account_ids.includes(item.user_id) }"
                            @click="toggleAccount(item)"
                        >
                            <div class="wpsn-accounts-label-row">
                            <img :src="getAccountIcon(item)" class="wpsn-accounts-icon wpsn-circle-avatar" :alt="item.username" />
                            <div class="wpsr-accounts-info">
                                <span class="wpsr-accounts-label">
                                    {{ item.username }}
                                </span>
                                <span class="wpsr-accounts-secondary-label wpsr-secondary-label wpsr-body-regular-color">
                                    {{ item.api_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                                </span>
                            </div>
                            <span
                                class="wpsn-accounts-checkbox"
                                :class="{
                                'checked': feed_config.source_settings.account_ids.includes(item.user_id)
                                }"
                                style="cursor: pointer;"
                            >
                                <InputCheckIcon v-if="feed_config.source_settings.account_ids && feed_config.source_settings.account_ids.includes(item.user_id)" />
                                <InputUncheckIcon v-else />
                            </span>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>

                <div class="wpsr-editor-edit-item-field">
                    <el-button 
                        class="wpsr_primary_btn wpsr_btn_fullwidth" 
                        @click="fetchFeeds"
                        :disabled="!connected_ids || connected_ids.length === 0"
                    >
                        {{ $t('Fetch Feeds') }}
                    </el-button>
                </div>
            </span>
        </el-collapse-item>

        <el-collapse-item name="2">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="layout" :label="$t('Layout')"/>
            </template>

            <!-- <transition name="fade">
              <ImageSelect v-model="template_meta.templateType" :options="templateTypes" :headerTitle="'Layout Type'"/>
            </transition> -->

            <FeedEditorGroup
                 :fieldsMaps="layout_settings"
                 :modelValue="feed_config"
                 :sectionGap="12"
                 @update:modelValue="handleLayoutSettingsUpdate"
            />
        </el-collapse-item>
        <el-collapse-item name="3">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="filters" :label="$t('Filters')"/>
            </template>
            <FeedEditorGroup
                :fieldsMaps="filters_settings"
                :modelValue="feed_config.filters"
                @update:modelValue="handleFiltersSettingsUpdate"
                @keyup.enter="fetch"
                @on-change="fetch"
            />
        </el-collapse-item>
        <el-collapse-item name="4">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="post" :label="$t('Post')"/>
            </template>
            <FeedEditorGroup
                :fieldsMaps="post_settings"
                :modelValue="feed_config.post_settings"
                @update:modelValue="handlePostSettingsUpdate"
            />
        </el-collapse-item>
        <el-collapse-item name="5">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" :isPro="!has_pro" iconKey="shopable_feed" :label="$t('Shoppable Feed')"/>
            </template>

            <div class="wpsr_editor_edit_item-group wpsr_editor_inside_gap_16">
                <UpgradeToProBanner />
                <div class="wpsr-settings-switch">
                    <span class="wpsr-editor-inside-left wpsr-input-field-label">{{$t('Enable Shoppable Feed') }}</span>
                    <div class="wpsr-editor-inside-right">
                        <el-switch
                            :modelValue="feed_config.shoppable_settings.enable_shoppable"
                            @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, shoppable_settings: { ...feed_config.shoppable_settings, enable_shoppable: val }})"
                            active-value="true"
                            inactive-value="false"
                            :disabled="!has_pro"
                            @change="fetchFeeds"
                        >
                        </el-switch>
                    </div>

                </div>
                <div v-if="feed_config.shoppable_settings.enable_shoppable === 'true'">
                    <div class="wpsr-settings-instruction">
                    <img :src="assets_url+ '/images/icon/shoppable-info.png'" alt="">
                    <p>
                        Note: Tap "Add" or "Update" button on an image to add/update each post URL.
                    </p>
                    </div>
                </div>
                <div class="wpsr-settings-switch">
                    <span class="wpsr-editor-inside-left wpsr-input-field-label">{{$t('Include Shoppable by Hashtags') }}</span>
                    <div class="wpsr-editor-inside-right">
                        <el-switch
                            :modelValue="feed_config.shoppable_settings.include_shoppable_by_hashtags"
                            @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, shoppable_settings: { ...feed_config.shoppable_settings, include_shoppable_by_hashtags: val }})"
                            active-value="true"
                            inactive-value="false"
                            :disabled="!has_pro"
                            @change="fetchFeeds"
                        >
                        </el-switch>
                    </div>
                </div>
                <div class="wpsr-settings-switch">
                    <span class="wpsr-editor-inside-left wpsr-input-field-label">{{$t('Configure Shoppable by Hashtags') }}</span>
                    <div class="wpsr-editor-inside-right">
                        <router-link 
                            :to="{ name: 'shoppable-by-hashtags' }"
                            class="wpsr-link-button"
                            target="_blank"
                        >
                            {{ $t('Shoppable Settings') }}
                        </router-link>
                    </div>
                </div>
                <div class="wpsr-settings-switch">
                    <span class="wpsr-editor-inside-left wpsr-input-field-label">{{$t('Display Shoppable Icon') }}</span>
                    <div class="wpsr-editor-inside-right">
                        <el-switch
                            :modelValue="feed_config.shoppable_settings.display_shoppable_icon"
                            @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, shoppable_settings: { ...feed_config.shoppable_settings, display_shoppable_icon: val }})"
                            active-value="true"
                            inactive-value="false"
                            :disabled="!has_pro"
                        >
                        </el-switch>
                    </div>

                </div>
            </div>
        </el-collapse-item>
        <el-collapse-item name="6" v-if="feed_config.layout_type ==='carousel'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="carousel" :label="$t('Carousel')"/>
            </template>
            <FeedEditorGroup
                :fieldsMaps="carousel_settings"
                :modelValue="feed_config.carousel_settings"
                @update:modelValue="handleCarouselSettingsUpdate"
            />
        </el-collapse-item>
        <el-collapse-item name="7" v-if="feed_config.post_settings.display_mode ==='popup'">

            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="popup_box" :isPro="!has_pro" :label="$t('Popup Box')"/>
            </template>

            <FeedEditorGroup
                :fieldsMaps="popup_settings"
                :modelValue="feed_config.popup_settings"
                @update:modelValue="handlePopupSettingsUpdate"
            />
        </el-collapse-item>
        <el-collapse-item name="8" v-if="feed_config.source_settings.feed_type !== 'search_feed'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="header" :label="$t('Header')"/>
            </template>
            <div class="wpsr_editor_edit_item-group wpsr_editor_inside_gap_16">
                <div class="wpsr-settings-switch">
                    <span class="wpsr-editor-inside-left wpsr-input-field-label">{{ $t('Display Header') }}</span>
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
                <div class="wpsr-inline-field" v-if="feed_config.header_settings.display_header === 'true'">
                    <span class="wpsr-editor-inside-left wpsr-input-field-label">{{ $t('Account to Display') }}</span>
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
                                v-for="item in header_connected_ids"
                                :key="item.user_id"
                                :label="item.username"
                                :value="item.user_id"
                            >
                            </el-option>
                        </el-select>
                    </div>

                </div>
            </div>
            <FeedEditorGroup v-if="feed_config.header_settings.display_header === 'true'"
                :fieldsMaps="header_settings"
                :modelValue="feed_config.header_settings"
                @update:modelValue="handleHeaderSettingsUpdate"
            />
        </el-collapse-item>
        <el-collapse-item name="9">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="follow_button" :label="$t('Follow Button')"/>
            </template>
            <span class="wpsr_source_settings_fields">
                <div class="wpsr-settings-switch">
                    <span class="wpsr-editor-inside-left wpsr-input-field-label">{{$t('Display Follow Button') }}</span>
                    <div class="wpsr-editor-inside-right">
                        <el-switch
                            :modelValue="feed_config.follow_button_settings.display_follow_button"
                            @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, follow_button_settings: { ...feed_config.follow_button_settings, display_follow_button: val }})"
                            active-value="true"
                            inactive-value="false"
                            :disabled="!has_pro"
                        >
                        </el-switch>
                    </div>
                </div>
                <transition>
                <FeedEditorGroup v-if="feed_config.follow_button_settings.display_follow_button === 'true'"
                    :fieldsMaps="follow_button_settings"
                    :modelValue="feed_config.follow_button_settings"
                    @update:modelValue="handleFollowButtonSettingsUpdate"
                />
                </transition>
            </span>
        </el-collapse-item>
        <el-collapse-item name="10" v-if="feed_config.layout_type !== 'carousel'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="pagination" :label="$t('Pagination')"/>
            </template>
            <FeedEditorGroup
                :fieldsMaps="pagination_settings"
                :modelValue="feed_config.pagination_settings"
                @update:modelValue="handlePaginationSettingsUpdate"
            />
        </el-collapse-item>
    </div>
</template>

<script type="text/babel">
import debounced from 'lodash/debounce';
import FeedEditorGroup from './../../../core-ui/editor/EditorGroup';
import UpgradeToProButton from '../../../views/advertise/UpgradeToProButton';
import Instagram from '../../../views/platforms/feeds/Instagram.vue';
import EditorCollapsiblePanel from '../../../core-ui/editor/EditorCollapsiblePanel.vue';
import ImageSelect from '../../../core-ui/editor/ImageSelect.vue'
import InputCheckIcon from '../../../pieces/icons/InputCheckIcon.vue';
import InputUncheckIcon from '../../../pieces/icons/InputUncheckIcon.vue';
import UpgradeToProBanner from '../../../views/advertise/UpgradeToProBanner.vue';
export default {
    name: "InstagramFeedEditor",
    components: {
        FeedEditorGroup,
        UpgradeToProButton,
        Instagram,
        EditorCollapsiblePanel,
        ImageSelect,
        InputCheckIcon,
        InputUncheckIcon,
        UpgradeToProBanner
    },
    props: {
        feed_config: {
            type: Object,
            required: true
        },
        image_settings: {
            type: Object,
            default: () => ({})
        },
        connected_ids: {
            type: [Array, Object], // Accept both,
            default: () => []
        },
        header_connected_ids: {
            type: [Array, Object], // Accept both,
            default: () => []
        }
    },
    emits: ['update:feed_config', 'fetchFeed'],

    data() {
        return {
            localFeedConfig: null,
            isUpdating: false,
            accountSelectorLoading: false,
            filters_settings: [
                {
                    fieldKey: 'total_posts_number',
                    type: 'number',
                    title: this.$t('Number of Posts to Display'),
                    min: 1,
                    max: this.has_pro ? 1000 : 10,
                    tooltip: this.has_pro ? false : true,
                    tooltipText: this.$t('Upgrade to pro to display unlimited Instagram Post.'),
                    hide_tablet: true,
                    responsive: true,
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
                            value: 'most_popular',
                            label: this.$t('Most Popular'),
                            disabled: !this.has_pro,
                        },
                        {
                            value: 'least_popular',
                            label: this.$t('Least Popular'),
                            disabled: !this.has_pro,
                        },
                        {
                            value: 'random',
                            label: this.$t('Random'),
                            disabled: !this.has_pro,
                        }
                    ],
                },
                {
                    fieldKey: 'post_type',
                    type: 'select',
                    title: this.$t('Types of Posts'),
                    event: 'on_change',
                    options: [
                        {
                            value: 'all',
                            label: this.$t('All Posts')
                        },
                        {
                            value: 'videos',
                            label: this.$t('Videos Only')
                        },
                        {
                            value: 'images',
                            label: this.$t('Images Only')
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
                    tooltip: true,
                    autosize: {minRows: 2, maxRows: 4},
                    tooltipText: this.$t('You can find the ID of a Post by viewing the post on Instagram and copy/pasting the ID number from the end of the URL </br> ex: https://www.instagram.com/p/CIdOs_JrF6M/ [ in this URL ID is "CIdOs_JrF6M" ]')
                }
            ],
            source_settings: [
                {
                    fieldKey: 'feed_type',
                    type: 'select',
                    title: this.$t('Feed Type'),

                    options: [
                        {
                            value: 'user_account_feed',
                            label: this.$t('User Account'),
                        },
                        {
                            value: 'hashtag_feed',
                            label: this.$t('Hashtag'),
                            disabled: !this.has_pro,
                        },
                    ]
                },
                {
                    fieldKey: 'hash_tags',
                    type: 'textarea',
                    title: this.$t('Hashtags'),
                    placeholder: this.$t('Enter hashtags using comma separator'),
                    tooltip: true,
                    tooltipText: this.$t('Enter any hashtag to display posts from a hashtag. <br> Separate multiple hashtag with comma ex: #wp, #wpsocialninja'),
                    condition: {
                        'key': 'feed_type',
                        'selector': 'hashtag_feed'
                    }
                },
                {
                    fieldKey: 'hashtag_type',
                    type: 'select',
                    title: this.$t('Hashtag Types'),
                    options: [
                        {
                            value: 'top_media',
                            label: this.$t('Most Popular')
                        },
                        {
                            value: 'recent_media',
                            label: this.$t('Most Recent')
                        }
                    ],
                    tooltip: true,
                    tooltipText: this.$t(
                        '<strong>Limitations</strong><br/><br/>' +
                        'Popular and Recent Hashtag: <br/>' +
                        '- Only returns public photos and videos. <br/>' +
                        '- Will not return promoted/boosted/ads media. <br/>' +
                        '- You can query a maximum of 30 unique hashtags within a 7 day period. <br/><br/>' +
                        'Recent Hashtag: <br/>' +
                        '- Only returns media objects published within 24 hours of query execution.'
                    ),
                    condition: {
                        'key': 'feed_type',
                        'selector': 'hashtag_feed'
                    }
                },
                // {
                //     fieldKey: 'account_ids',
                //     type: 'multiple_select',
                //     title: this.$t('Select an User Account'),
                //     label: 'username',
                //     value: 'user_id',
                //     pro_handler: !(true && this.has_pro), // in free it will true else false
                //     options: this.connected_ids
                // },
            ],
            layout_settings: [
                {
                    fieldKey: 'layout_type',
                    type: 'image_select',
                    title: this.$t('Layout Type'),
                    options: [
                        {
                            key: 'grid',
                            title: this.$t('Grid'),
                            img: this.assets_url + '/images/template/global-template/grid.png',
                        },
                        {
                            key: 'carousel',
                            title: this.$t('Carousel'),
                            pro: !this.has_pro,
                            img: this.assets_url + '/images/template/global-template/slider.png',
                        },
                        {
                            key: 'masonry',
                            title: this.$t('Masonry'),
                            pro: !this.has_pro,
                            img: this.assets_url + '/images/template/global-template/masonry.png',
                        }
                    ]
                },
                {
                    fieldKey: 'template',
                    shouldShowSeeMoreButton: false,
                    type: 'image_select',
                    options: [
                        {
                            key: 'template1',
                            title: 'Template 1',
                            img: this.assets_url + '/images/template/instagram-template/template1.png'
                        },
                        {
                            key: 'template2',
                            title: 'Template 2',
                            img: this.assets_url + '/images/template/instagram-template/template2.png'
                        }
                    ]
                },
                {
                    type: 'divider'
                },
                {
                    title: this.$t('Layout Styling'),
                    type: 'section_title',
                    condition: {
                      'key': 'layout_type',
                      'operator': '!=',
                      'selector': 'carousel'
                    },
                },
                {
                    fieldKey: 'responsive_column_number',
                    type: 'select',
                    title: this.$t('Number of Columns'),
                    responsive: true,
                    condition: {
                        'key': 'layout_type',
                        'operator': '!=',
                        'selector': 'carousel'
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
                        'operator': '!=',
                        'selector': 'carousel'
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
            ],
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
                            label: 'Load More',
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
            column_gap_options: [
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
            ],
            carousel_settings: [
                {
                    fieldKey: 'autoplay',
                    type: 'switch',
                    title: this.$t('Autoplay :'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'autoplay_speed',
                    type: 'number',
                    title: this.$t('Autoplay Speed :'),
                    min: 1,
                    max: 10000,
                    flex: true
                },
                {
                    fieldKey: 'responsive_slides_to_show',
                    type: 'number',
                    title: this.$t('Slides to Show :'),
                    min: 1,
                    max: 10,
                    flex: true,
                    responsive: true
                },
                {
                    fieldKey: 'responsive_slides_to_scroll',
                    type: 'number',
                    title: this.$t('Slides to Scroll :'),
                    min: 1,
                    max: 10,
                    flex: true,
                    responsive: true
                },
                {
                    fieldKey: 'spaceBetween',
                    type: 'number',
                    title: this.$t('Space between slide:'),
                    min: 0,
                    max: 200,
                    flex: true
                },
                {
                    fieldKey: 'navigation',
                    type: 'select',
                    title: this.$t('Navigation'),
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
            post_settings: [
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
                    fieldKey: 'aspect_ratio',
                    type: 'select',
                    title: this.$t('Image Aspect Ratio'),
                    options: [
                        {
                            value: 'classic',
                            label: this.$t('1:1(Classic)')
                        },
                        {
                            value: 'frame34',
                            label: this.$t('3:4(Portrait)')
                        },
                        {
                            value: 'frame45',
                            label: this.$t('4:5(Tall)')
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
                            label: this.$t('None')
                        },
                        {
                            value: 'inline',
                            label: this.$t('Inline')
                        },
                        {
                            value: 'instagram',
                            label: this.$t('Open on Instagram')
                        },
                        {
                            value: 'popup',
                            label: this.$t('Open in Popup Box'),
                            disabled: !this.has_pro,
                        }
                    ]
                },
                {
                    fieldKey: 'display_caption',
                    type: 'switch',
                    title: this.$t('Display Caption'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'trim_caption_words',
                    type: 'number',
                    title: this.$t('Trim Caption Words'),
                    flex: true,
                    disabled : !this.has_pro,
                },
                {
                    fieldKey: 'display_likes_counter',
                    type: 'switch',
                    title: this.$t('Display Likes Count'),
                    disabled: !this.has_pro,
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_comments_counter',
                    type: 'switch',
                    title: this.$t('Display Comments Count'),
                    disabled: !this.has_pro,
                    active_value: 'true',
                    inactive_value: 'false',
                }
            ],
            header_settings: [
                {
                    fieldKey: 'display_avatar',
                    type: 'switch',
                    title: this.$t('Display Profile Photo'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_username',
                    type: 'switch',
                    title: this.$t('Display Username'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_name',
                    type: 'switch',
                    title: this.$t('Display Full Name'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_description',
                    type: 'switch',
                    title: this.$t('Display Bio Text'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_posts_counter',
                    type: 'switch',
                    title: this.$t('Display Posts Count'),
                    disabled: !this.has_pro,
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_followers_counter',
                    type: 'switch',
                    title: this.$t('Display Followers Count'),
                    disabled: !this.has_pro,
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey:'custom_profile_photo',
                    type: 'image',
                    title: this.$t('Custom Profile Photo'),
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'custom_profile_bio_text',
                    type: 'textarea',
                    title: this.$t('Custom Bio Text'),
                    disabled: !this.has_pro,
                }
            ],
            follow_button_settings: [
                {
                    fieldKey: 'follow_button_position',
                    type: 'select',
                    title: this.$t('Follow Button Position'),
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
            popup_settings: [
                {
                    fieldKey: 'display_sidebar',
                    type: 'switch',
                    title: this.$t('Display Sidebar'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: this.has_pro
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
                    disabled: this.has_pro
                },
                {
                    fieldKey: 'display_caption',
                    type: 'switch',
                    title: this.$t('Display Caption'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: this.has_pro
                },
                {
                    fieldKey: 'display_date',
                    type: 'switch',
                    title: this.$t('Display Date'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: this.has_pro
                },
                {
                    fieldKey: 'display_comments',
                    type: 'switch',
                    title: this.$t('Display Comments'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: this.has_pro
                },
                {
                    fieldKey: 'display_cta_btn',
                    type: 'switch',
                    title: this.$t('Display Call to Action Button'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: this.has_pro
                },
                {
                    fieldKey: 'display_next_prev_arrows',
                    type: 'switch',
                    title: this.$t('Display Next Prev Arrows'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: this.has_pro
                }
            ]
        }
    },

    created() {
        this.initializeFeedConfig();
    },

    mounted() {
        let configuration = null;
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        configuration = urlParams.get('configuration');

        if (!configuration) {
            const hash = window.location.hash;
            const hashQuery = hash.split('?')[1];
            if (hashQuery) {
                const hashParams = new URLSearchParams(hashQuery);
                configuration = hashParams.get('configuration');
            }
        }
    },

    methods: {
        initializeFeedConfig() {
            if (this.feed_config) {
                this.localFeedConfig = JSON.parse(JSON.stringify(this.feed_config));
            }
        },

        fetchFeeds() {
            this.$emit('fetchFeed');
        },

        fetch: debounced(function () {
            this.$emit('fetchFeed', 'fetching');
        }, 500),

        setAccount(val) {
            if (!this.has_pro && val.length > 1) {
                this.feed_config.source_settings.account_ids = [];
                this.feed_config.source_settings.account_ids.push(val[0]);
                this.handlePro();
            }

            if (this.feed_config.source_settings.feed_type === 'hashtag_feed') {
                this.disableMultiselect();
            }
        },

        disableMultiselect() {
            let accounts = this.feed_config.source_settings.account_ids;
            if (accounts.length > 1) {
                this.feed_config.source_settings.account_ids = [];
                this.feed_config.source_settings.account_ids.push(accounts[accounts.length-1]);
            }
        },

        handleSourceSettingsUpdate(val) {
          const updatedValue = val;
            if (this.isUpdating) return;
            this.isUpdating = true;

            const updatedConfig = {
                ...this.feed_config,
                source_settings: updatedValue
            };

            this.$emit('update:feed_config', updatedConfig);
            this.$nextTick(() => {
                this.isUpdating = false;
            });
        },
        handleSourceChange() {
            this.fetchFeeds();
        },
        toggleAccount(value) {
            this.accountSelectorLoading = true;
            
            const accountIds = [...this.feed_config.source_settings.account_ids];
            const index = accountIds.indexOf(value.user_id);

            if (index === -1) {
                if (!this.has_pro && accountIds.length >= 1) {
                    accountIds.splice(0, accountIds.length, value.user_id);
                    this.handlePro();
                } else {
                    accountIds.push(value.user_id);
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
                    account_ids: accountIds
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
        getAccountIcon(account) {
            return account?.user_avatar;
        },
        handleLayoutSettingsUpdate(val) {
            if (this.isUpdating) return;
            this.isUpdating = true;

            const updatedConfig = {
                ...this.feed_config,
                ...val
            };

            this.$emit('update:feed_config', updatedConfig);
            
            // If layout type changed, fetch feeds to reinitialize the layout
            if (val.layout_type && val.layout_type !== this.feed_config.layout_type) {
                this.$nextTick(() => {
                    this.fetchFeeds();
                });
            }
            
            this.$nextTick(() => {
                this.isUpdating = false;
            });
        },
        handleFiltersSettingsUpdate(val) {
            if (this.isUpdating) return;
            this.isUpdating = true;

            const updatedConfig = {
                ...this.feed_config,
                filters: val
            };

            this.$emit('update:feed_config', updatedConfig);
            this.$nextTick(() => {
                this.isUpdating = false;
            });
        },
        handlePostSettingsUpdate(val) {
            if (this.isUpdating) return;
            this.isUpdating = true;

            const updatedConfig = {
                ...this.feed_config,
                post_settings: val
            };

            this.$emit('update:feed_config', updatedConfig);
            this.$nextTick(() => {
                this.isUpdating = false;
            });
        },
        handleCarouselSettingsUpdate(val) {
            if (this.isUpdating) return;
            this.isUpdating = true;

            const updatedConfig = {
                ...this.feed_config,
                carousel_settings: val
            };

            this.$emit('update:feed_config', updatedConfig);
            this.$nextTick(() => {
                this.isUpdating = false;
            });
        },
        handlePopupSettingsUpdate(val) {
            if (this.isUpdating) return;
            this.isUpdating = true;

            const updatedConfig = {
                ...this.feed_config,
                popup_settings: val
            };

            this.$emit('update:feed_config', updatedConfig);
            this.$nextTick(() => {
                this.isUpdating = false;
            });
        },
        handleHeaderSettingsUpdate(val) {
            if (this.isUpdating) return;
            this.isUpdating = true;

            const updatedConfig = {
                ...this.feed_config,
                header_settings: val
            };

            this.$emit('update:feed_config', updatedConfig);
            this.$nextTick(() => {
                this.isUpdating = false;
            });
        },
        handleFollowButtonSettingsUpdate(val) {
            if (this.isUpdating) return;
            this.isUpdating = true;

            const updatedConfig = {
                ...this.feed_config,
                follow_button_settings: val
            };

            this.$emit('update:feed_config', updatedConfig);
            this.$nextTick(() => {
                this.isUpdating = false;
            });
        },
        handlePaginationSettingsUpdate(val) {
            if (this.isUpdating) return;
            this.isUpdating = true;

            const updatedConfig = {
                ...this.feed_config,
                pagination_settings: val
            };

            this.$emit('update:feed_config', updatedConfig);
            this.$nextTick(() => {
                this.isUpdating = false;
            });
        },
    },

    watch: {
        feed_config: {
            handler(newVal) {
                if (newVal && !this.isUpdating) {
                    this.initializeFeedConfig();
                }
            },
            deep: true,
            immediate: true
        }
    }
}
</script>