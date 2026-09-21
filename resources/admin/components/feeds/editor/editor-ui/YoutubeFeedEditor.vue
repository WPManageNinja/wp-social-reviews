<template>
    <div v-if="feed_config">
        <el-collapse-item name="1">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="accounts" :label="$t('Source')"/>
            </template>
            <div class="wpsr_editor_layout">
              <EditorGroup
                   :fieldsMaps="source_settings"
                   :modelValue="feed_config.source_settings"
                   @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, source_settings: val })"
              />
            </div>
            <div class="wpsr-editor-edit-item-field">
                <el-button class="wpsr_primary_btn wpsr_btn_fullwidth" @click="fetchVideos">
                    {{ $t('Fetch Videos') }}
                </el-button>
            </div>
        </el-collapse-item>
        <el-collapse-item name="2">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="layout" :label="$t('Template')"/>
            </template>
            <div class="wpsr_editor_layout">
              <EditorGroup
                   :fieldsMaps="layout_settings"
                   :modelValue="feed_config"
                   @update:modelValue="handleLayoutSettingsUpdate"
              />
            </div>
        </el-collapse-item>
        <el-collapse-item name="3">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="filters" :label="$t('Filters')"/>
            </template>
            <EditorGroup
                :fieldsMaps="filters_settings"
                :modelValue="feed_config.filters"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, filters: val })"
                @keyup.enter="fetch"
                @on-change="fetch"
                @change="$emit('fetchFeed')"
            />
        </el-collapse-item>
        <el-collapse-item name="4">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="post" :label="$t('Video')"/>
            </template>
            <EditorGroup
                :fieldsMaps="video_settings"
                :modelValue="feed_config.video_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, video_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="5" v-if="(feed_config.source_settings && feed_config.source_settings.feed_type !== 'search_feed' && feed_config.source_settings.feed_type !== 'single_video' )">
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
            <EditorGroup v-if="feed_config.header_settings.display_header === 'true'"
                :fieldsMaps="header_settings"
                :modelValue="feed_config.header_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, header_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="6" v-if="feed_config.layout_type === 'carousel'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="carousel" :label="$t('Carousel')"/>
            </template>
            <EditorGroup v-if="feed_config"
                :fieldsMaps="carousel_settings"
                :modelValue="feed_config.carousel_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, carousel_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="7" v-if="feed_config.video_settings && feed_config.video_settings.play_mode === 'popup'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="popup_box" :label="$t('Popup')"/>
            </template>
            <EditorGroup v-if="feed_config"
                :fieldsMaps="popup_settings"
                :modelValue="feed_config.popup_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, popup_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="8" v-if="(feed_config.source_settings && feed_config.source_settings.feed_type !== 'search_feed' && feed_config.source_settings.feed_type !== 'single_video')">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" :isPro="!has_pro" iconKey="follow_button" :label="$t('Subscribe Button')"/>
            </template>
            <div class="wpsr-switch-row">
              <span class="wpsr-editor-inside-left wpsr-element-center">{{$t('Display Subscribe Button')}}</span>
              <div class="wpsr-editor-inside-right">
                <el-switch
                    :modelValue="feed_config.subscribe_button_settings.display_subscribe_button"
                    @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, subscribe_button_settings: { ...feed_config.subscribe_button_settings, display_subscribe_button: val }})"
                    active-value="true"
                    inactive-value="false"
                    :disabled="!has_pro"
                >
                </el-switch>
              </div>
            </div>
            <EditorGroup v-if="feed_config && feed_config.subscribe_button_settings.display_subscribe_button === 'true'"
                :fieldsMaps="subscribe_button_settings"
                :modelValue="feed_config.subscribe_button_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, subscribe_button_settings: val })"
            />
        </el-collapse-item>
        <el-collapse-item name="9" v-if="feed_config.layout_type !== 'carousel'">
            <template #title="{ isActive}">
                <EditorCollapsiblePanel :isActive="isActive" iconKey="pagination" :label="$t('Pagination')"/>
            </template>
            <EditorGroup
                :fieldsMaps="pagination_settings"
                :modelValue="feed_config.pagination_settings"
                @update:modelValue="(val) => $emit('update:feed_config', { ...feed_config, pagination_settings: val })"
            />
        </el-collapse-item>
    </div>
</template>

<script>
import { debounce, wpsrFetchFluentPlayerMedia } from '../../../../utils';
import EditorGroup from './../../../core-ui/editor/EditorGroup';
import UpgradeToProButton from '../../../views/advertise/UpgradeToProButton';
import EditorCollapsiblePanel from '../../../core-ui/editor/EditorCollapsiblePanel.vue';

export default {
    name: "YoutubeFeedEditor",
    components: {
        EditorGroup,
        UpgradeToProButton,
        EditorCollapsiblePanel
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
    },
    emits: ['update:feed_config', 'fetchFeed'],
    data() {
        return {
          filters_settings: [
            {
              fieldKey: 'total_posts_number',
              type: 'number',
              title: this.$t('Number of Videos to Display'),
              min: -1,
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
                  value: 'none',
                  label: this.$t('None')
                },
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
              ]
            },
            {
              fieldKey: 'includes_inputs',
              type: 'textarea',
              title: this.$t('Show posts containing these words or hashtags'),
              placeholder: this.$t('Type something and press enter....'),
              disabled: !this.has_pro,
              tooltip: true,
              tooltipText: this.$t('You can use this setting to show posts that contain certain specific words or hashtags in the caption.') + '<br>' + this.$t('Separate multiple words or hashtags using commas. ex: #hashtag1,word1,word2,#hashtag2'),
            },
            {
              fieldKey: 'excludes_inputs',
              type: 'textarea',
              title: this.$t('Hide posts containing these words or hashtags'),
              placeholder: this.$t('Type something and press enter....'),
              disabled: !this.has_pro,
              tooltip: true,
              tooltipText: this.$t('You can use this setting to hide posts that contain certain specific words or hashtags in the caption.') + '<br>' + this.$t('Separate multiple words or hashtags using commas. ex: #hashtag1,word1,#hashtag2,word2')
            },
            {
              fieldKey: 'hide_posts_by_id',
              type: 'textarea',
              title: this.$t('Hide Specific Videos'),
              placeholder: this.$t('Type video id and press enter..'),
              disabled: !this.has_pro,
              tooltip: true,
              autosize: {minRows: 2, maxRows: 4},
              tooltipText: this.$t('You can find the video ID by viewing the video on YouTube and copying the ID number from the end of the URL.') + '<br>ex: https://www.youtube.com/watch?v=azeHzcW5OgY'
            }
          ],

          layout_settings: [],

          source_settings: [
            {
              fieldKey: 'feed_type',
              type: 'select',
              title: this.$t('Feed Type'),
              options: [
                {
                  value: 'channel_feed',
                  label: this.$t('Channel'),
                },
                {
                  value: 'playlist_feed',
                  label: this.$t('Playlist'),
                  disabled: !this.has_pro,
                },
                {
                  value: 'search_feed',
                  label: this.$t('Search'),
                  disabled: !this.has_pro,
                },
                {
                  value: 'single_video',
                  label: this.$t('Specific Videos'),
                  disabled: !this.has_pro,
                },
                {
                  value: 'live_streams_feed',
                  label: this.$t('Live Streams'),
                  disabled: !this.has_pro,
                }
              ]
            },
            {
              fieldKey: 'channel_id',
              type: 'text',
              title: this.$t('Channel ID, Handle or Username'),
              placeholder: this.$t('Enter Channel ID, Handle or Username. For example: @yourchannel or UCibcXdeKy8e04hYWf0Gz7ow'),
              condition: {
                'key': 'feed_type',
                'operator': 'Arr',
                'selector': ['channel_feed', 'live_streams_feed']
              },
              tooltip: true,
              tooltipText: this.$t('<p>'+'Enter a Channel ID, Handle or Username to display the latest videos from any YouTube account,</br> ' +
                  'starting with the most recently published. </br> </br>' +
                  'You can find the required Channel ID, Handle or Username from the YouTube URL: </br> </br>' +
                  '📌 Channel Handle: <strong>https://www.youtube.com/@yourchannel</strong> → Use @yourchannel </br> ' +
                  '📌 Channel ID: <strong>https://www.youtube.com/channel/UCibcXdeKy8e04hYWf0Gz7ow</strong> → Use UCibcXdeKy8e04hYWf0Gz7ow </br> ' +
                  '📌 Username: <strong>https://www.youtube.com/user/your_user_name → Use your_user_name</strong> </br> </br>' +
                  'Simply copy and paste it here to show the latest videos.'+'</p>')
            },
            {
              fieldKey: 'playlist_id',
              type: 'text',
              title: this.$t('Playlist ID'),
              placeholder: this.$t('Enter your playlist id'),
              condition: {
                'key': 'feed_type',
                'selector': 'playlist_feed'
              },
              tooltip: true,
              tooltipText: this.$t('Enter any Playlist ID to display YouTube videos from a Playlist. You can find the ID of your YouTube Playlist from the URL.') + '<br>ex: https://www.youtube.com/playlist?list=PLXpD0vT4thWHBqJ5fvNRZmnzaATJ2WzY8 — copy the ID after "list="'
            },
            {
              fieldKey: 'video_id',
              type: 'text',
              title: this.$t('Video ID'),
              placeholder: this.$t('Enter your video id using comma separator'),
              condition: {
                'key': 'feed_type',
                'selector': 'single_video'
              },
              tooltip: true,
              tooltipText: this.$t('Enter any video ID to display specific YouTube videos. Separate multiple video IDs with commas. You can find the ID from the URL.') + '<br>ex: https://www.youtube.com/watch?v=QSVpos-WJFg — copy the ID after "watch?v="'
            },
            {
              fieldKey: 'search_term',
              type: 'text',
              title: this.$t('Search Term'),
              placeholder: this.$t('Enter your search term'),
              condition: {
                'key': 'feed_type',
                'selector': 'search_feed'
              },
              tooltip: true,
              tooltipText: this.$t('Enter any search term or phrase. Separate multiple terms with commas.')
            },
            {
              fieldKey: 'event_type',
              type: 'select',
              title: this.$t('Event Type'),
              options: [
                {
                  value: 'none',
                  label: this.$t('None')
                },
                {
                  value: 'completed',
                  label: this.$t('Completed')
                },
                {
                  value: 'upcoming',
                  label: this.$t('Upcoming')
                },
                {
                  value: 'live',
                  label: this.$t('Live')
                },
              ],
              condition: {
                'key': 'feed_type',
                'selector': 'live_streams_feed'
              }
            },
            {
              fieldKey: 'feed_count',
              type: 'number',
              title: this.$t('Total Feed'),
              min: 1,
              max: 200,
              flex: true,
              condition: {
                'key': 'feed_type',
                'operator': '!=',
                'selector': 'single_video'
              },
              tooltip: true,
              tooltipText:  this.$t('This input field allows you to set how many videos to retrieve from the YouTube API. Max: 200')
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
                  label: this.$t('None')
                },
                {
                  value: 'load_more',
                  label: this.$t('Load More')
                },
                {
                  value: 'prev_next',
                  label: this.$t('Prev Next'),
                  disabled: !this.has_pro,
                }
              ],
              tooltip: true,
              tooltipText: this.$t("The Load More button will only display on your site's preview/live pages/posts.") + "<br>" + this.$t("It will not display while you are in editing mode in the WordPress Editor.")
            },
            {
              fieldKey: 'load_more_button_text',
              type: 'text',
              title: this.$t('Load More Button Text'),
              placeholder: this.$t('Load More'),
              condition: {
                key: 'pagination_type',
                selector: 'load_more'
              }
            },
            {
              fieldKey: 'paginate_number',
              type: 'number',
              title: this.$t('Feeds Per Page'),
              min: 1,
              flex: true,
              responsive: true,
              hide_tablet: true,
              condition: {
                key: 'pagination_type',
                selector: 'load_more',
                operator: 'includes'
              },
              tooltip: true,
              tooltipText: this.$t("The Feeds per Page is only visible on preview and live pages/posts, not in the WordPress Editor's editing mode."),
            },
          ],
          video_settings: [],
          header_settings: [
                {
                    fieldKey: 'header_layout',
                    type: 'select',
                    title: this.$t('Header Layout'),
                    options: [
                        {
                            value: 'classic',
                            label: this.$t('Classic')
                        },
                        {
                            value: 'minimal',
                            label: this.$t('Minimal')
                        }
                    ]
                },
                {
                    fieldKey: 'display_banner',
                    type: 'switch',
                    title: this.$t('Display Banner'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_logo',
                    type: 'switch',
                    title: this.$t('Display Logo'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_name',
                    type: 'switch',
                    title: this.$t('Display Name'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_subscriber_counter',
                    type: 'switch',
                    title: this.$t('Display Subscriber Counter'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_videos_counter',
                    type: 'switch',
                    title: this.$t('Display Videos Counter '),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_views_counter',
                    type: 'switch',
                    title: this.$t('Display Views Counter '),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
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
                  fieldKey:'custom_banner',
                  type: 'image',
                  title: this.$t('Upload Custom Banner'),
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
                // {
                //     fieldKey:'rows',
                //     type:'number',
                //     title:this.$t('Number of Rows :'),
                //     min:1,
                //     max:10,
                //     flex: true
                // },
                {
                    fieldKey:'responsive_slides_to_show',
                    type:'number',
                    title: this.$t('Slides to Show'),
                    min: 1,
                    max: 10,
                    flex: true,
                    responsive: true
                },
                {
                    fieldKey:'responsive_slides_to_scroll',
                    type:'number',
                    title: this.$t('Slides to Scroll'),
                    min: 1,
                    max: 10,
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
                // {
                //     fieldKey: 'vertical',
                //     type: 'switch',
                //     title: this.$t('Vertical Mode? :'),
                //     active_value: 'true',
                //     inactive_value: 'false',
                // },
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
                    fieldKey: 'autoplay',
                    type: 'switch',
                    title: this.$t('Autoplay'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'video_loop',
                    type: 'switch',
                    title: this.$t('Video Loop'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_title',
                    type: 'switch',
                    title: this.$t('Title'),
                    active_value: 'true',
                    inactive_value: 'false',
                },
                {
                    fieldKey: 'display_views_counter',
                    type: 'switch',
                    title: this.$t('Views Counter'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_date',
                    type: 'switch',
                    title: this.$t('Date:'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_likes_counter',
                    type: 'switch',
                    title: this.$t('Likes Counter'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_dislikes_counter',
                    type: 'switch',
                    title: this.$t('Dislikes Counter'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_channel_logo',
                    type: 'switch',
                    title: this.$t('Channel Logo'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_channel_name',
                    type: 'switch',
                    title: this.$t('Channel Name'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_subscribers_counter',
                    type: 'switch',
                    title: this.$t('Subscribers Counter'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
                {
                    fieldKey: 'display_subscribe_button',
                    type: 'switch',
                    title: this.$t('Subscribe Button'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
//                    {
//                        fieldKey: 'display_likes_ratio',
//                        type: 'switch',
//                        title: this.$t('Likes Ratio:'),
//                        active_value: 'true',
//                        inactive_value: 'false',
//                    },
//                    {
//                        fieldKey: 'display_share',
//                        type: 'switch',
//                        title: this.$t('Share:'),
//                        active_value: 'true',
//                        inactive_value: 'false',
//                    },
                {
                    fieldKey: 'display_description',
                    type: 'switch',
                    title: this.$t('Description'),
                    active_value: 'true',
                    inactive_value: 'false',
                    disabled: !this.has_pro,
                },
//                    {
//                        fieldKey: 'display_description_more',
//                        type: 'switch',
//                        title: this.$t('Description More Button:'),
//                        active_value: 'true',
//                        inactive_value: 'false',
//                    },
//                 {
//                     fieldKey: 'display_comments',
//                     type: 'switch',
//                     title: this.$t('Comments:'),
//                     active_value: 'true',
//                     inactive_value: 'false',
//                     disabled: !this.has_pro,
//                     tooltip: true,
//                     tooltipText: this.$t('Comments is not available for oAuth2.0 credentials type due to restrictions set by YouTube')
//                 },
            ],
            subscribe_button_settings: [
                {
                    fieldKey: 'subscribe_button_position',
                    type: 'select',
                    title: this.$t('Subscribe Button Position'),
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
                    fieldKey: 'subscribe_button_text',
                    type: 'text',
                    title: this.$t('Button Text'),
                    disabled: !this.has_pro,
                }
            ],
            videoSettingsOptions: [
              {
                value: 'popup',
                label: this.$t('Popup')
              },
              {
                value: 'youtube',
                label: this.$t('Redirect to YouTube')
              }
            ],
            // Filled from Fluent Player's media list, see fetchFluentPlayerMedia().
            fluentPlayerMediaOptions: [
              {
                value: 0,
                label: this.$t('Default player design')
              }
            ]
        }
    },
    methods: {
      fetchVideos() {
        this.$emit('fetchFeed');
      },
      handleLayoutSettingsUpdate(val) {
        if (this.isUpdating) return;
        this.isUpdating = true;

        const updatedConfig = {
          ...this.feed_config,
          ...val
        };

        this.$emit('update:feed_config', updatedConfig);
        this.$nextTick(() => {
          this.isUpdating = false;
        });
      },
      setVideoSettings() {
        if (this.image_settings.has_gdpr === 'false' && !this.videoSettingsOptions.some((option) => option.value === 'gallery')) {
          this.videoSettingsOptions.push(
            {
              value: 'inline',
              label: this.$t('Inline')
            },
            {
              value: 'gallery',
              label: this.$t('Gallery'),
              disabled: !this.has_pro
            }
          );
        }

        this.video_settings= [
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
            fieldKey: 'play_mode',
            type: 'select',
            title: this.$t('Play Mode'),
            options: this.videoSettingsOptions,
            condition: {
              data: this.feed_config,
              key: 'template',
              operator: '!=',
              selector: 'template5'
            }
          },
          ...this.fluentPlayerSettings(),
          // {
          //     fieldKey: 'image_size',
          //     type: 'select',
          //     title: this.$t('Images Resolution'),
          //     options: [
          //         {
          //             value: 'high',
          //             label: this.$t('High')
          //         },
          //         {
          //             value: 'standard',
          //             label: this.$t('Standard')
          //         }
          //     ]
          // },
          {
            fieldKey: 'display_title',
            type: 'switch',
            title: this.$t('Display Title'),
            active_value: 'true',
            inactive_value: 'false',
          },
          {
            fieldKey: 'trim_title_words',
            type: 'number',
            title: this.$t('Trim Title Words'),
            flex: true,
            disabled: !this.has_pro,
          },
          {
            fieldKey: 'display_play_icon',
            type: 'switch',
            title: this.$t('Display Play Icon'),
            active_value: 'true',
            inactive_value: 'false',
            disabled: !this.has_pro,
          },
          {
            fieldKey: 'display_duration',
            type: 'switch',
            title: this.$t('Display Duration'),
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
            fieldKey: 'display_views_counter',
            type: 'switch',
            title: this.$t('Display Views Counter'),
            active_value: 'true',
            inactive_value: 'false',
            disabled: !this.has_pro,
          },
          {
            fieldKey: 'display_likes_counter',
            type: 'switch',
            title: this.$t('Display Likes Counter'),
            active_value: 'true',
            inactive_value: 'false',
            disabled: !this.has_pro,
          },
          {
            fieldKey: 'display_comments_counter',
            type: 'switch',
            title: this.$t('Display Comments Counter'),
            active_value: 'true',
            inactive_value: 'false',
            disabled: !this.has_pro,
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
            fieldKey: 'display_channel_name',
            type: 'switch',
            title: this.$t('Display Channel Name'),
            active_value: 'true',
            inactive_value: 'false',
            disabled: !this.has_pro,
          },
        ]
      },
      /**
       * Fluent Player fields, only when that plugin is installed
       *
       * @return {Array}
       */
      fluentPlayerSettings() {
        if (!this.appVars || !this.appVars.has_fluent_player) {
          return [];
        }

        return [
          {
            fieldKey: 'use_fluent_player',
            type: 'switch',
            title: this.$t('Play with Fluent Player'),
            active_value: 'true',
            inactive_value: 'false',
            tooltip: true,
            tooltipText: this.$t('Play the videos in Fluent Player instead of the YouTube player. The videos still come from YouTube. This preview always shows the YouTube player.')
          },
          {
            fieldKey: 'fluent_player_media',
            type: 'select',
            title: this.$t('Player Design'),
            condition: {key: 'use_fluent_player', selector: 'true'},
            options: this.fluentPlayerMediaOptions,
            tooltip: true,
            tooltipText: this.$t('Reuse the design and the layers (call to action, email capture) of one of your Fluent Player videos for this feed.')
          },
          {
            fieldKey: 'fluent_player_layers_at_end',
            type: 'switch',
            title: this.$t('Show Layers at Video End'),
            active_value: 'true',
            inactive_value: 'false',
            condition: {key: 'use_fluent_player', selector: 'true'},
            tooltip: true,
            tooltipText: this.$t('Layers are set to appear at a fixed time, so a late one never shows on a short video. Turn this on to move them to the end of whichever video is playing.')
          }
        ];
      },
      /**
       * Load the player design options from Fluent Player's REST route
       *
       * The saved id is seeded first so the select never renders empty while
       * the list loads, or when that video is no longer published.
       */
      /**
       * Load the player designs, seeding the saved id so the select is never empty
       *
       * Spliced in place: the field config already holds this array.
       */
      fetchFluentPlayerMedia() {
        const savedId = this.savedFluentPlayerMedia();

        const show = (medias) => {
          const options = [{value: 0, label: this.$t('Default player design')}, ...medias];

          if (savedId && !options.some((option) => option.value === savedId)) {
            options.push({value: savedId, label: '#' + savedId});
          }

          this.fluentPlayerMediaOptions.splice(0, this.fluentPlayerMediaOptions.length, ...options);
        };

        show([]);
        wpsrFetchFluentPlayerMedia(this.appVars).then(show);
      },
      savedFluentPlayerMedia() {
        const videoSettings = this.feed_config && this.feed_config.video_settings;
        return videoSettings ? parseInt(videoSettings.fluent_player_media, 10) || 0 : 0;
      },
      setLayoutSettings() {
        // rebuilt so the layout_type options re-read feed_config.template, which is replaced wholesale on update,
        // leaving a value baked in at data() time stale
        const isPlaylistPlayer = this.feed_config && this.feed_config.template === 'template5';

        const layoutTypeOptions = [
          {
            key: 'grid',
            title: this.$t('Grid'),
            img: this.assets_url + '/images/template/global-template/grid.png',
          },
        ];

        // Template 5 (Playlist Player) has a fixed two-column player + list layout, so carousel mode doesn't apply
        if (!isPlaylistPlayer) {
          layoutTypeOptions.push({
            key: 'carousel',
            title: this.$t('Carousel'),
            pro: !this.has_pro,
            img: this.assets_url + '/images/template/global-template/slider.png',
          });
        }

        this.layout_settings = [
          {
            fieldKey: 'layout_type',
            type: 'image_select',
            title: this.$t('Layout Type'),
            options: layoutTypeOptions
          },
          {
            fieldKey: 'template',
            type: 'image_select',
            options: [
              {
                key: 'template1',
                title: this.$t('Video Grid'),
                img: this.assets_url + '/images/template/youtube-template/template1.png'
              },
              {
                key: 'template2',
                title: this.$t('Hover Overlay'),
                pro: !this.has_pro,
                img: this.assets_url + '/images/template/youtube-template/template2.png'
              },
              {
                key: 'template3',
                title: this.$t('List View'),
                pro: !this.has_pro,
                img: this.assets_url + '/images/template/youtube-template/template3.png'
              },
              {
                key: 'template4',
                title: this.$t('Text-Top Card'),
                pro: !this.has_pro,
                img: this.assets_url + '/images/template/youtube-template/template4.png'
              },
              {
                key: 'template5',
                title: this.$t('Player & Playlist'),
                pro: !this.has_pro,
                img: this.assets_url + '/images/template/youtube-template/template5.png'
              },
            ]
          },
          {
            title: this.$t('Layout Styling'),
            type: 'section_title',
            condition: [
              {
                'key': 'layout_type',
                'operator': 'Arr',
                'selector': ['grid']
              },
              {
                'key': 'template',
                'operator': '!=',
                'selector': 'template5'
              }
            ],
          },
          {

            fieldKey: 'responsive_column_number',
            type: 'select',
            title: this.$t('Number of Columns'),
            responsive: true,
            condition: [
              {
                'key': 'layout_type',
                'selector': 'grid'
              },
              {
                'key': 'template',
                'operator': '!=',
                'selector': 'template5'
              }
            ],
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
            condition: [
              {
                'key': 'layout_type',
                'selector': 'grid'
              },
              {
                'key': 'template',
                'operator': '!=',
                'selector': 'template5'
              }
            ],
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
        ];
      },
      fetch: debounce(function () {
        this.$emit('fetchFeed');
      }, 500)
    },
    watch: {
      'feed_config.template': function () {
        // rebuilt so play_mode's condition.data re-reads feed_config, which is replaced wholesale on update rather than mutated
        this.setVideoSettings();
        this.setLayoutSettings();
      },
      'feed_config.header_settings.header_layout': function (newval, oldval) {
        // Guard against hydration: without oldval a saved minimal feed would
        // lose a banner the user had deliberately switched back on.
        if (!oldval || newval === oldval) {
          return;
        }
        // Deferred a macrotask: EditorGroup holds isUpdating for the change that
        // opened this watcher and drops any modelValue sync arriving first, so
        // the Display Banner switch would keep rendering the stale value.
        setTimeout(() => {
          this.$emit('update:feed_config', {
            ...this.feed_config,
            header_settings: {
              ...this.feed_config.header_settings,
              display_banner: newval === 'minimal' ? 'false' : 'true'
            }
          });
        }, 0);
      }
    },
    mounted() {
      this.setVideoSettings();
      this.setLayoutSettings();

      if (this.appVars && this.appVars.has_fluent_player) {
        this.fetchFluentPlayerMedia();
      }
    }
}
</script>