<template>
      <span>
        <el-collapse-item name="1">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="accounts" :label="$t('Source')"/>
            </template>
            <FeedEditorGroup
              v-if="localFeedConfig && localFeedConfig.additional_settings"
              :fieldsMaps="source_settings"
              :modelValue="localFeedConfig.additional_settings"
              @update:modelValue="val => $emit('update:feed_config', { ...localFeedConfig, additional_settings: val })"
            />
            <div class="wpsr-editor-edit-item-field wpsr-mt-16">
                <el-button class="wpsr_primary_btn wpsr_btn_fullwidth" @click="fetchTweets">
                    {{ $t('Fetch Feeds') }}
                </el-button>
            </div>
        </el-collapse-item>
        <el-collapse-item name="2">
          <template #title="{ isActive}">
            <EditorCollapsiblePanel :isActive="isActive" iconKey="layout" :label="$t('Template')"/>
          </template>
          <FeedEditorGroup
              :fieldsMaps="layout_settings"
              :modelValue="localFeedConfig"
              :sectionGap="12"
              @update:modelValue="$emit('update:feed_config', $event)"
          />
        </el-collapse-item>
        <el-collapse-item name="3">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="filters" :label="$t('Filters')"/>
            </template>
            <FeedEditorGroup
                :fieldsMaps="filters_settings"
                :modelValue="localFeedConfig.filters"
                @update:modelValue="handleFiltersSettingsUpdate"
                @keyup.enter.native="fetch"
                @on-change="fetch"
                @change.native="fetch"
            />
        </el-collapse-item>
        <el-collapse-item name="4">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="post" :label="$t('Feed Settings')"/>
            </template>
            <FeedEditorGroup
               :fieldsMaps="advance_settings"
               :modelValue="localFeedConfig.advance_settings"
               @input="(val) => $emit('update:feed_config', { ...localFeedConfig, advance_settings: val })"
            />
            <div class="wpsr-switch-row" v-if="localFeedConfig.layout_type !== 'standard'">
                <span class="wpsr-editor-inside-left wpsr-element-center">{{ $t('Equal Height') }}</span>
                <div class="wpsr-editor-inside-right">
                    <el-switch
                        :modelValue="localFeedConfig.advance_settings.equal_height"
                        @change="(val) => $emit('update:feed_config', { ...localFeedConfig, advance_settings: { ...localFeedConfig.advance_settings, equal_height: val }})"
                        active-value="true"
                        inactive-value="false"
                    >
                    </el-switch>
                </div>
            </div>
        </el-collapse-item>
        <el-collapse-item name="5" v-if="localFeedConfig && localFeedConfig.additional_settings.feed_type !== 'hashtag'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="header" :isPro="!has_pro" :label="$t('Header')"/>
            </template>
            <UpgradeToProBanner />
            <div class="wpsr-settings-switch">
                <span class="wpsr-editor-inside-left wpsr-element-center">{{ $t('Display Header') }}</span>
                <div class="wpsr-editor-inside-right">
                    <el-switch
                        :modelValue="localFeedConfig.header_settings.show_header"
                        @change="(val) => $emit('update:feed_config', { ...localFeedConfig, header_settings: { ...localFeedConfig.header_settings, show_header: val }})"
                        active-value="true"
                        inactive-value="false"
                        :disabled="!!has_pro === false  ? true : false"
                    >
                    </el-switch>
                </div>
            </div>
            <FeedEditorGroup v-if="localFeedConfig && localFeedConfig.header_settings.show_header === 'true'"
               :fieldsMaps="header_settings"
               :modelValue="localFeedConfig.header_settings"
               :proGroup="true"
               @update:modelValue="val => $emit('update:feed_config', { ...localFeedConfig, header_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="6" v-if="localFeedConfig && localFeedConfig.layout_type ==='carousel'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="carousel" :label="$t('Carousel')"/>
            </template>
            <FeedEditorGroup v-if="localFeedConfig"
               :fieldsMaps="carousel_settings"
               :modelValue="localFeedConfig.carousel_settings"
               @input="(val) => $emit('update:feed_config', { ...localFeedConfig, carousel_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="7" v-if="localFeedConfig && localFeedConfig.advance_settings.tweet_action_target === 'popup'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="popup_box" :isPro="!has_pro" :label="$t('Popup')"/>

            </template>
            <FeedEditorGroup v-if="localFeedConfig"
               :fieldsMaps="popup_settings"
               :modelValue="localFeedConfig.popup_settings"
               @input="(val) => $emit('update:feed_config', { ...localFeedConfig, popup_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="8" v-if="localFeedConfig && localFeedConfig.additional_settings.feed_type !== 'hashtag'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="follow_button" :isPro="!has_pro" :label="$t('Follow Button')"/>
            </template>
            <UpgradeToProBanner />
            <div class="wpsr-switch-row">
                <span class="wpsr-element-label wpsr-editor-inside-left wpsr-element-center">
                    <span>
                        {{ $t('Display Follow Button') }}
                    </span>
                </span>
                <div class="wpsr-editor-inside-right">
                    <el-switch
                        :modelValue="localFeedConfig.follow_button_settings.display_follow_button"
                        @change="(val) => $emit('update:feed_config', { ...localFeedConfig, follow_button_settings: { ...localFeedConfig.follow_button_settings, display_follow_button: val }})"
                        active-value="true"
                        inactive-value="false"
                        :disabled="has_pro?false:true"
                    >
                    </el-switch>
                </div>
            </div>
            <FeedEditorGroup v-if="localFeedConfig && localFeedConfig.follow_button_settings.display_follow_button === 'true'"
               :fieldsMaps="follow_button_settings"
               :modelValue="localFeedConfig.follow_button_settings"
               :proGroup="true"
               @input="(val) => $emit('update:feed_config', { ...localFeedConfig, follow_button_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="9" v-if="localFeedConfig && localFeedConfig.layout_type !== 'carousel'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="pagination" :label="$t('Pagination')"/>
            </template>
            <div class="wpsr_editor_edit_item-group wpsr_editor_inside_gap_16">
                <div class="wpsr_editor_edit_item">
                    <div class="wpsr-filters-row">
                        <div class="wpsr-editor-inside-left wpsr-input-field-label wpsr-element-center">
                            <span>{{ $t('Pagination Type') }}</span>
                            <el-tooltip 
                                :raw-content="true"
                                effect="dark"
                                placement="right-start"
                                :content="this.$t('The Load More button will only display on your site\'s preview/live pages/posts <br> not while you\'re in editing mode in the WordPress Editor.')"
                            >
                                <el-icon ><InfoFilled /></el-icon>
                            </el-tooltip>
                        </div>
                        <div class="wpsr-editor-inside-right">
                            <el-select 
                                :modelValue="localFeedConfig.pagination_settings.pagination_type"
                                @change="(val) => $emit('update:feed_config', { ...localFeedConfig, pagination_settings: { ...localFeedConfig.pagination_settings, pagination_type: val }})"
                                placeholder="Select" 
                                size="small"
                                class="wpsr-text-input"
                            >
                                <el-option
                                    v-for="item in paginationType"
                                    :key="item.value"
                                    :label="(item.disabled===true)? item.label+' (Pro)':item.label"
                                    :value="item.value"
                                    :disabled="item.disabled"
                                >
                                </el-option>
                            </el-select>
                        </div>
                    </div>
                </div>
                <div class="wpsr_editor_edit_item" v-if="localFeedConfig.pagination_settings.pagination_type !== 'none'">
                    <div class="wpsr-filters-row">
                        <span class="wpsr-editor-inside-left wpsr-element-center">{{$t('Load More Button Text')}}</span>
                        <div class="wpsr-editor-inside-right">
                            <el-input 
                                type="text" 
                                placeholder="Load More" 
                                :value="localFeedConfig.pagination_settings.load_more_button_text"
                                @change="(val) => $emit('update:feed_config', { ...localFeedConfig, pagination_settings: { ...localFeedConfig.pagination_settings, load_more_button_text: val }})"
                                size="small"
                                class="wpsr-text-input wpsr-editor-text-input">
                            </el-input>
                        </div>
                    </div>
                </div>
              
                <div class="wpsr_editor_edit_item" v-if="localFeedConfig.pagination_settings.pagination_type !== 'none'">
                    <div class="wpsr-settings-switch">
                        <span class="wpsr-editor-inside-left wpsr-input-field-label wpsr-element-center">{{ $t('Posts Per Page') }}</span>
                        <div class="wpsr-reviews-number wpsr-editor-inside-right">
                            <el-input-number
                                :min="1"
                                :max="20"
                                controls-position="right"
                                :modelValue="localFeedConfig.pagination_settings.paginate"
                                @update:modelValue="(val) => $emit('update:feed_config', { ...localFeedConfig, pagination_settings: { ...localFeedConfig.pagination_settings, paginate: val }})"
                                size="large"
                                class="wpsr-text-input"
                            >
                            </el-input-number>
                            <div class="wpsr-group-input">
                                <el-button
                                    class="wpsr-editor-minus-btn"
                                    @click="decreasePaginate"
                                > <MinusIcon width="16" height="16" />
                                </el-button>
                                <el-button
                                    class="wpsr-editor-plus-btn"
                                    @click="increasePaginate"
                                ><PlusIcon width="16" height="16"/>
                                </el-button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </el-collapse-item>
      </span>
</template>

<script type="text/babel">
import ImageSelect from './../../../core-ui/editor/ImageSelect' ;
import FeedEditorGroup from './../../../core-ui/editor/EditorGroup';
import UpgradeToProButton from '../../../views/advertise/UpgradeToProButton';
import EditorCollapsiblePanel from '../../../core-ui/editor/EditorCollapsiblePanel.vue';
import debounced from 'lodash/debounce';
import PlusIcon from '../../../pieces/icons/PlusIcon';
import MinusIcon from '../../../pieces/icons/MinusIcon';
import UpgradeToProBanner from '../../../views/advertise/UpgradeToProBanner.vue';
import ProCrownIcon from '../../../pieces/icons/ProCrownIcon.vue';
export default {
    props:['feed_config', 'current_api_version'],
    components: {
        ImageSelect,
        FeedEditorGroup,
        UpgradeToProButton,
        EditorCollapsiblePanel,
        PlusIcon,
        MinusIcon,
        UpgradeToProBanner,
        ProCrownIcon
    },
    data() {
        return {
            api_version: this.current_api_version,
            localFeedConfig: null,
            isUpdating: false,
            filters_settings: [
            {
              fieldKey: 'total_posts_number',
              type: 'number',
              title: this.$t('Number of Tweets to Display'),
              min: 1,
              hide_tablet: true,
              responsive: true,
              tooltip: true,
              tooltipText: "The responsive number of posts will only work on your site's preview/live pages/posts."
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
              tooltipText: this.$t('You can find the ID of a Post by viewing the post on X (Twitter) and copy/pasting the ID number from the end of the URL </br> ex: https://twitter.com/wpsocialninja/status/1361763913801494530 [ in this URL ID is 1361763913801494530 ]')
            }
          ],
            layout_settings: [
            {
              fieldKey: 'layout_type',
              type: 'image_select',
              title: this.$t('Layout Type'),
              options: [
                {
                  key: 'standard',
                  title: this.$t('Standard'),
                  img: this.assets_url + '/images/template/global-template/timeline.png',
                },
                {
                  key: 'masonry',
                  title: this.$t('Masonry'),
                  img: this.assets_url + '/images/template/global-template/masonry.png',
                  pro : !this.has_pro
                },
                {
                  key: 'carousel',
                  title: this.$t('Carousel'),
                  img: this.assets_url + '/images/template/global-template/slider.png',
                  pro : !this.has_pro
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
                  pro: false,
                  img: this.assets_url + '/images/template/twitter-template/template1.png'
                }
              ]
            },
            {
              title: this.$t('Layout Styling'),
              type: 'section_title',
              condition: {
                'key': 'layout_type',
                'operator': 'Arr',
                'selector': ['masonry']
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
                'selector': ['masonry']
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
              ],
            },
            {
              fieldKey: 'column_gaps',
              type: 'select',
              title: this.$t('Columns Gap'),
              condition: {
                'key': 'layout_type',
                'operator': 'Arr',
                'selector': ['masonry']
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
                            disabled: !this.has_pro
                        },
                        {
                            value: 'footer',
                            label: this.$t('Footer'),
                            disabled: !this.has_pro
                        },
                        {
                            value: 'both',
                            label: this.$t('Both'),
                            disabled: !this.has_pro
                        }
                    ]
                },
                {
                    fieldKey: 'follow_button_text',
                    type: 'text',
                    title: this.$t('Button Text'),
                    disabled: !this.has_pro
                }
            ],
            postsOrder:[
                {
                    value: 'ascending',
                    label: this.$t('Ascending')
                },
                {
                    value: 'descending',
                    label: this.$t('Descending')
                },
                {
                    value: 'most_popular',
                    label: this.$t('Most Popular'),
                    disabled: !this.has_pro
                },
                {
                    value: 'least_popular',
                    label: this.$t('Least Popular'),
                    disabled: !this.has_pro
                },
                {
                    value: 'random',
                    label: this.$t('Random'),
                    disabled: !this.has_pro
                }
            ],
            feedTypes: [
                {
                    value: 'user_timeline',
                    label: this.$t('User Timeline')
                },
                {
                    value: 'home_timeline',
                    label: this.$t('Home Timeline'),
                    disabled: !this.has_pro,
                    api_version: '1.1'
                },
                {
                    value: 'hashtag',
                    label: this.$t('Hashtag'),
                    disabled: !this.has_pro,
                    api_version: '1.1'
                },
                {
                    value: 'user_mentions',
                    label: this.$t('Mentions'),
                    disabled: !this.has_pro,
                    api_version: '1.1'
                }
            ],
            popup_settings: [
                {
                    fieldKey: 'autoplay',
                    type: 'switch',
                    title: this.$t('Autoplay'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_sidebar',
                    type: 'switch',
                    title: this.$t('Display Sidebar'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'avatar_image',
                    type: 'switch',
                    title: this.$t('Display Avatar Image'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'author_name',
                    type: 'switch',
                    title: this.$t('Display Author Name'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'user_name',
                    type: 'switch',
                    title: this.$t('Display Username'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'display_date',
                    type: 'switch',
                    title: this.$t('Display Date'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'twitter_logo',
                    type: 'switch',
                    title: this.$t('Display X (Twitter) Logo'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'tweet_text',
                    type: 'switch',
                    title: this.$t('Display Tweet Text'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_reply_action',
                    type: 'switch',
                    title: this.$t('Display Reply Action'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_retweet_action',
                    type: 'switch',
                    title: this.$t('Display Retweet Action'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_like_action',
                    type: 'switch',
                    title: this.$t('Display Like Action'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                  fieldKey: 'display_next_prev_arrows',
                  type: 'switch',
                  title: this.$t('Display Next Prev Arrows'),
                  active_value: 'true',
                  inactive_value: 'false',
                  disabled: !this.has_pro
                },
            ],
            paginationType: [
                {
                    value: 'none',
                    label: this.$t('None'),
                },
                {
                    value: 'load_more',
                    label: this.$t('Load More'),
                },
                {
                    value: 'infinite',
                    label: this.$t('Infinite Scroll'),
                    disabled: !this.has_pro
                }
            ],
            header_settings: [
                {
                    fieldKey: 'show_banner_image',
                    type: 'switch',
                    title: this.$t('Display Banner Image'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_avatar',
                    type: 'switch',
                    title: this.$t('Display Avatar Image'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_name',
                    type: 'switch',
                    title: this.$t('Display Name'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_user_name',
                    type: 'switch',
                    title: this.$t('Display Username'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_location',
                    type: 'switch',
                    title: this.$t('Display Location'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_description',
                    type: 'switch',
                    title: this.$t('Display Description'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_total_tweets',
                    type: 'switch',
                    title: this.$t('Display Tweets'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_following',
                    type: 'switch',
                    title: this.$t('Display Following'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                },
                {
                    fieldKey: 'show_followers',
                    type: 'switch',
                    title: this.$t('Display Followers'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro
                }
            ],
            advance_settings:[
                {
                    fieldKey: 'avatar_image',
                    type: 'switch',
                    title: this.$t('Display Avatar Image'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'author_name',
                    type: 'switch',
                    title: this.$t('Display Author Name'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'user_name',
                    type: 'switch',
                    title: this.$t('Display Username'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'date',
                    type: 'switch',
                    title: this.$t('Display Date'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'twitter_logo',
                    type: 'switch',
                    title: this.$t('Display X (Twitter) Logo'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'tweet_text',
                    type: 'switch',
                    title: this.$t('Display Tweet Text'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                  fieldKey: 'tweet_action_target',
                  type: 'select',
                  title: this.$t('Open Tweet Actions in'),
                  options: [
                    {
                      value: 'popup',
                      label: this.$t('Popup Window'),
                      disabled: !this.has_pro,
                    },
                    {
                      value: '_blank',
                      label: this.$t('Open in new tab')
                    },
                  ]
                },
                {
                    fieldKey: 'show_reply_action',
                    type: 'switch',
                    title: this.$t('Display Reply Action'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'show_retweet_action',
                    type: 'switch',
                    title: this.$t('Display Retweet Action'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'show_like_action',
                    type: 'switch',
                    title: this.$t('Display Like Action'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                     fieldKey: 'show_retweeted_tweet',
                     type: 'switch',
                     title: this.$t('Display Retweeted Tweet'),
                     active_value: 'true',
                     inactive_value: 'false',
                },
                {
                  fieldKey: 'show_quoted_tweet',
                  type: 'switch',
                  title: this.$t('Display Quoted Tweet:'),
                  active_value: 'true',
                  inactive_value: 'false',
                },
                {
                    fieldKey: 'show_tweet_image',
                    type: 'switch',
                    title: this.$t(' Display Images on Tweet'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'show_tweet_video',
                    type: 'switch',
                    title: this.$t(' Display Videos on Tweet'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'show_tweet_gif',
                    type: 'switch',
                    title: this.$t(' Display Gifs on Tweet'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                // {
                //     fieldKey: 'show_image_video_popup',
                //     type: 'switch',
                //     title: this.$t('Display Photos & Videos in Popup:'),
                //     active_value: 'true',
                //     inactive_value: 'false',
                //     disabled : !this.has_pro
                // },
                {
                    fieldKey: 'show_twitter_card',
                    type: 'switch',
                    title: this.$t('Display X (Twitter) Card'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro,
                    tooltip: true,
                    tooltipText: this.$t("The X (Twitter) cards will only display on your site's preview/live pages/posts not while you're in editing mode in the WordPress Editor.")
                },
                {
                    fieldKey: 'show_card_for_third_party_url',
                    type: 'switch',
                    title: this.$t('Display X (Twitter) Player Card'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled : !this.has_pro,
                    tooltip: true,
                    tooltipText: this.$t("The X (Twitter) player cards will only display on your site's preview/live pages/posts not while you're in editing mode in the WordPress Editor.")
                },
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
                    fieldKey:'autoplay_speed',
                    type:'number',
                    title:this.$t('Autoplay Speed'),
                    min:1,
                    max:10000,
                    flex: true
                },
                {
                    fieldKey:'responsive_slides_to_show',
                    type:'number',
                    title:this.$t('Slides to Show'),
                    min:1,
                    max:10,
                    flex: true,
                    responsive: true
                },
                {
                    fieldKey:'responsive_slides_to_scroll',
                    type:'number',
                    title:this.$t('Slides to Scroll'),
                    min:1,
                    max:10,
                    flex: true,
                    responsive: true
                },
                {
                  fieldKey: 'spaceBetween',
                  type: 'number',
                  title: this.$t('Space between slide'),
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
                }
            ]
        }
    },
    computed: {
        feedTypeLabel() {
            const found = this.feedTypes.find(opt => opt.value === this.localFeedConfig?.additional_settings?.feed_type);
            return found ? found.label : this.localFeedConfig?.additional_settings?.feed_type;
        },
        source_settings() {
            return [
                {
                    fieldKey: 'feed_type',
                    type: 'select',
                    title: this.$t('Feed Type'),
                    label: 'label',
                    value: 'value',
                    options: this.feedTypes
                },
                {
                    fieldKey: 'screen_name',
                    type: 'text',
                    title: this.$t('User Name'),
                    placeholder: 'ex: user_name',
                    condition: {
                        key: 'feed_type',
                        selector: 'user_timeline',
                        operator: '=='
                    }
                },
                {
                    fieldKey: 'hashtag',
                    type: 'text',
                    title: this.$t('Hashtag'),
                    placeholder: 'ex: #baseball',
                    condition: {
                        key: 'feed_type',
                        selector: 'hashtag',
                        operator: '=='
                    }
                },
                {
                    fieldKey: 'feed_count',
                    type: 'number',
                    title: this.$t('Total Feed'),
                    min: 1,
                    max: this.has_pro ? 200 : 10,
                    tooltip: true,
                    tooltipText: "This input field allows you to set how many tweets to retrieve from the X (Twitter) API. Max: 200"
                }
            ]
        }
    },
    created() {
        this.initializeFeedConfig();
    },
    methods: {
        initializeFeedConfig() {
            if (this.feed_config) {
                this.localFeedConfig = JSON.parse(JSON.stringify(this.feed_config));
            }
        },
        fetch: debounced(function () {
            this.$emit('fetchFeed', 'fetching');
        }, 500),
        fetchTweets(){
            this.$emit('fetchFeed', 'fetching');
        },
        handleFiltersSettingsUpdate(val) {
            if (this.isUpdating) return;
            this.isUpdating = true;
            const safeVal = val && typeof val === 'object' ? val : {};
            this.localFeedConfig = {
                ...this.localFeedConfig,
                filters: JSON.parse(JSON.stringify(safeVal))
            };
            this.$emit('update:feed_config', { ...this.localFeedConfig, filters: JSON.parse(JSON.stringify(safeVal)) });
            this.$nextTick(() => {
                this.isUpdating = false;
            });
        },
        decreasePaginate() {
            const currentValue = this.localFeedConfig.pagination_settings.paginate || 1;
            const newValue = Math.max(1, currentValue - 1);
            this.$emit('update:feed_config', { 
                ...this.localFeedConfig, 
                pagination_settings: { 
                    ...this.localFeedConfig.pagination_settings, 
                    paginate: newValue 
                } 
            });
        },
        increasePaginate() {
            const currentValue = this.localFeedConfig.pagination_settings.paginate || 1;
            const newValue = Math.min(20, currentValue + 1);
            this.$emit('update:feed_config', { 
                ...this.localFeedConfig, 
                pagination_settings: { 
                    ...this.localFeedConfig.pagination_settings, 
                    paginate: newValue 
                } 
            });
        }
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
        },
        'localFeedConfig.additional_settings.feed_type': function(newVal) {
            if (!newVal) return;
            const found = this.feedTypes.find(opt => opt.label === newVal);
            if (found && found.value !== newVal) {
                this.localFeedConfig.additional_settings.feed_type = found.value;
                this.$emit('update:feed_config', { ...this.localFeedConfig, additional_settings: { ...this.localFeedConfig.additional_settings, feed_type: found.value } });
            }
        },
    },
}
</script>