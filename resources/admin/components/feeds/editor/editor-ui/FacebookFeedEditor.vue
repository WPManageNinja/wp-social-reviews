<template>
  <div>
    <el-collapse-item name="1">
      <template #title="{ isActive}">
        <EditorCollapsiblePanel :isActive="isActive" iconKey="accounts" :label="$t('Source')"/>
      </template>

      <span class="wpsr_source_settings_fields">
          <FeedEditorGroup
                :fieldsMaps="source_settings"
                :modelValue="feed_config.source_settings"
                @update:modelValue="newVal => feed_config.source_settings = newVal"
          />

          <AccountSelector
              v-if="
                feed_config.source_settings.feed_type !== 'single_album_feed' &&
                feed_config.source_settings.feed_type !== 'event_feed' &&
                feed_config.source_settings.feed_type !== 'video_playlist_feed'
              "
              :accounts="pages"
              :selectedAccounts="feed_config.source_settings.selected_accounts || []"
              :title="$t('Select Pages')"
              :feed_type="feed_config.source_settings.feed_type"
              :loading="accountSelectorLoading"
              @toggle-account="(account) => toggleAccount(account, 'selected_accounts')"
          />

          <AccountSelector
              v-if="feed_config.source_settings.feed_type === 'event_feed'"
              :accounts="pages"
              :selectedAccounts="feed_config.source_settings.selected_accounts_for_events || []"
              :title="$t('Select Event Pages')"
              :feed_type="feed_config.source_settings.feed_type"
              :loading="accountSelectorLoading"
              @toggle-account="(account) => toggleAccount(account, 'selected_accounts_for_events')"
          />

          <div class="wpsr-editor-edit-item-field">
              <el-button class="wpsr_primary_btn wpsr_btn_fullwidth" @click="fetchFeedsButtonClicked">
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
           @update:modelValue="handleLayoutSettingsUpdate"
      />
    </el-collapse-item>
    <el-collapse-item name="3">
      <template #title="{ isActive}">
        <EditorCollapsiblePanel :isActive="isActive" iconKey="filters" :label="$t('Filters')"/>
      </template>
      <FeedEditorGroup v-if="feed_config"
         :fieldsMaps="filters_settings"
         :modelValue="feed_config.filters"
         @update:modelValue="handleFiltersSettingsUpdate"
         @keyup.enter="fetch"
         @on-change="fetch"
      />
    </el-collapse-item>
    <el-collapse-item name="4">
      <template #title="{ isActive}">
        <EditorCollapsiblePanel :isActive="isActive" iconKey="post" :label="$t('Feed')"/>
      </template>
      <div class="wpsr-feed-editor-groups-wrapper">
        <FeedEditorGroup
            :key="feed_config.source_settings.feed_type"
          :fieldsMaps="feed_settings.free"
          :modelValue="feed_config.post_settings"
          @update:modelValue="newVal => feed_config.post_settings = newVal"
        />

        <div :class="!has_pro ? 'wpsr_editor_edit_item-group-pro' : ''">
          <FeedEditorGroup
              v-if="feed_config"
            :fieldsMaps="feed_settings.pro"
            :modelValue="feed_config.post_settings"
            @update:modelValue="newVal => feed_config.post_settings = newVal"
          />
        </div>
      </div>

    </el-collapse-item>
    <el-collapse-item name="5" v-if="feed_config && shouldDisplayHeaderSettings()">
      <template #title="{ isActive}">
        <EditorCollapsiblePanel :isActive="isActive" iconKey="header" :label="$t('Header')"/>
      </template>
      <div class="wpsr-switch-row">
        <span class="wpsr-editor-inside-left wpsr-element-center">{{ $t('Display Header') }}</span>
        <div class="wpsr-editor-inside-right">
          <el-switch
            v-model="feed_config.header_settings.display_header"
            active-value="true"
            inactive-value="false"
          >
          </el-switch>
        </div>
      </div>
      <div class="wpsr-inline-field" v-if="feed_config && feed_config.header_settings.display_header === 'true'">
        <span class="wpsr-editor-inside-left wpsr-element-center">{{ $t('Account to Display ') }}</span>
        <div class="wpsr-editor-inside-right">
          <el-select v-model="feed_config.header_settings.account_to_show" size="small" clearable placeholder="Select" class="wpsr-text-input">
              <el-option
                v-for="(item, index) in getHeaderAccountOptions()"
                :key="index"
                :label="item.name"
                :value="item.page_id"
              >
            </el-option>
          </el-select>
        </div>
      </div>
      <FeedEditorGroup v-if="feed_config && feed_config.header_settings.display_header === 'true'"
        :fieldsMaps="header_settings"
        :modelValue="feed_config.header_settings"
        @update:modelValue="newVal => feed_config.header_settings = newVal"
      />
    </el-collapse-item>
    <el-collapse-item name="6" v-if="feed_config && feed_config.layout_type === 'carousel'">
      <template #title="{ isActive}">
        <EditorCollapsiblePanel :isActive="isActive" iconKey="carousel" :label="$t('Carousel')"/>
      </template>
      <FeedEditorGroup v-if="feed_config"
         :fieldsMaps="carousel_settings"
         :modelValue="feed_config.carousel_settings"
         @update:modelValue="newVal => feed_config.carousel_settings = newVal"
      />
    </el-collapse-item>
    <el-collapse-item name="7" v-if="feed_config && feed_config.post_settings.display_mode === 'popup'">
      <template #title="{ isActive}">
        <EditorCollapsiblePanel :isActive="isActive" iconKey="popup_box" :isPro="!has_pro" :label="$t('Popup')"/>
      </template>
      <FeedEditorGroup v-if="feed_config"
         :fieldsMaps="popup_settings"
         :modelValue="feed_config.popup_settings"
         @update:modelValue="newVal => feed_config.popup_settings = newVal"
      />
    </el-collapse-item>
    <el-collapse-item name="8">
      <template #title="{ isActive}">
        <EditorCollapsiblePanel :isActive="isActive" iconKey="follow_button" :isPro="!has_pro" :label="$t('Like Button')"/>
      </template>
      <UpgradeToProBanner />
      <div class="wpsr-switch-row">
        <span class="wpsr-editor-inside-left wpsr-element-center">{{$t('Display Like Button ')}}</span>
        <div class="wpsr-editor-inside-right">
          <el-switch
            v-model="feed_config.like_button_settings.display_like_button"
            active-value="true"
            inactive-value="false"
            :disabled="!has_pro"
          >
          </el-switch>
        </div>
      </div>
      <FeedEditorGroup v-if="feed_config && feed_config.like_button_settings.display_like_button === 'true'"
           :fieldsMaps="like_button_settings"
           :modelValue="feed_config.like_button_settings"
           :proGroup="true"
           @update:modelValue="newVal => feed_config.like_button_settings = newVal"
      />
    </el-collapse-item>
    <el-collapse-item name="9">
      <template #title="{ isActive}">
        <EditorCollapsiblePanel :isActive="isActive" iconKey="share_button" :isPro="!has_pro" :label="$t('Share Button')"/>
      </template>
      <UpgradeToProBanner />
      <div class="wpsr-switch-row">
        <span class="wpsr-editor-inside-left wpsr-element-center">{{$t('Display Share Button')}}</span>
        <div class="wpsr-editor-inside-right">
          <el-switch
            v-model="feed_config.share_button_settings.display_share_button"
            active-value="true"
            inactive-value="false"
            :disabled="!has_pro"
          >
          </el-switch>
        </div>
      </div>
      <FeedEditorGroup v-if="feed_config && feed_config.share_button_settings.display_share_button === 'true'"
         :fieldsMaps="share_button_settings"
         :modelValue="feed_config.share_button_settings"
         :proGroup="true"
         @update:modelValue="newVal => feed_config.share_button_settings = newVal"
      />
    </el-collapse-item>
    <el-collapse-item name="10" v-if="feed_config && feed_config.layout_type !== 'carousel'">
      <template #title="{ isActive}">
        <EditorCollapsiblePanel :isActive="isActive" iconKey="pagination" :label="$t('Pagination')"/>
      </template>
      <FeedEditorGroup
          :fieldsMaps="pagination_settings"
          :modelValue="feed_config.pagination_settings"
          @update:modelValue="newVal => feed_config.pagination_settings = newVal"
      />
    </el-collapse-item>
  </div>
</template>

<script>
import FeedEditorGroup from './../../../core-ui/editor/EditorGroup';
import UpgradeToProButton from '../../../views/advertise/UpgradeToProButton';
import debounced from "lodash/debounce";
import EditorCollapsiblePanel from '../../../core-ui/editor/EditorCollapsiblePanel.vue';
import AccountSelector from '../../../core-ui/editor/AccountSelector.vue';
import UpgradeToProBanner from '../../../views/advertise/UpgradeToProBanner.vue';

export default {
  name: "FacebookFeedEditor",
  props: {
    feed_config: {
      type: Object,
      required: true
    },
    pages: {
      type: [Array, Object], // Accept both,
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
    AccountSelector,
    UpgradeToProBanner
  },

  data() {
    return {
      isUpdating: false,
      accountSelectorLoading: false,
      filters_settings: [],
      source_settings: [
        {
          fieldKey: 'feed_type',
          type: 'select',
          title: this.$t('Feed Type'),
          options: [
            {
              value: 'timeline_feed',
              label: this.$t('Timeline Feed'),
            },
            {
              value: 'video_feed',
              label: this.$t('All Videos'),
              disabled: !this.has_pro,
            },
            {
              value: 'video_playlist_feed',
              label: this.$t('Specific Video Playlist'),
              disabled: !this.has_pro,
            },
            {
              value: 'photo_feed',
              label: this.$t('Photos'),
              disabled: !this.has_pro,
            },
            {
              value : 'event_feed',
              label : this.$t('Events'),
              disabled: !this.has_pro,
            },
            {
              value : 'album_feed',
              label : this.$t('Albums'),
              disabled: !this.has_pro,
            },
            {
              value : 'single_album_feed',
              label : this.$t('Single Album'),
              disabled: !this.has_pro,
            }

          ]
        },
        {
          fieldKey: 'single_album_id',
          type: 'text',
          title: this.$t('Album ID/ URL'),
          placeholder: this.$t('Enter Album ID/ URL'),
          condition: [
            {
              'key': 'feed_type',
              'selector': 'single_album_feed',
              'operator': '==',
              'data': this.feed_config.source_settings
            }
          ],
          tooltip: true,
          tooltipText: this.$t("If you paste the URL of the album, it will automatically extract the ID."),
        },
        {
          fieldKey: 'video_playlist_id',
          type: 'text',
          title: this.$t('Playlist URL'),
          placeholder: this.$t('Playlist URL'),
          condition: [
            {
              'key': 'feed_type',
              'selector': 'video_playlist_feed',
              'operator': '==',
              'data': this.feed_config.source_settings
            }
          ],
          tooltip: true,
          tooltipText: this.$t("If you paste the URL of the playlist, it will automatically extract the ID."),
        },
        {
          fieldKey: 'selected_account',
          type: 'select',
          title: (this.feed_config.source_settings.feed_type === 'video_playlist_feed') ? this.$t('Choose Page this Playlist belongs to') : this.$t('Choose Page this album belongs to'),
          options: this.getPagesForSelect(this.pages),
          condition:[
            {
              'key': 'feed_type',
              'selector': ['video_playlist_feed', 'single_album_feed'],
              'operator': 'includes',
              'data': this.feed_config.source_settings
            }
          ]
        },
        {
          fieldKey: 'feed_count',
          type: 'number',
          title: this.$t('Total Feed'),
          min: 1,
          max: this.has_pro ? 100 : 50,
          flex: true,
          tooltip: true,
          disabled: !this.has_pro,
          tooltipText: `This input field allows you to set how many posts to retrieve from the Facebook API. <br> Maximum limit is  ${!this.has_pro ? '50. Upgrade to pro to get more feeds' : 100}.`
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
              label: 'Load More',
            }
          ],
          tooltip: true,
          tooltipText: this.$t('The Load More button will only display on your site’s preview/live pages/posts <br> not while you’re in editing mode in the WordPress Editor.')
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
          fieldKey: 'display_cover_photo',
          type: 'switch',
          title: this.$t('Display Cover Photo'),
          active_value: 'true',
          inactive_value: 'false',
        },
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
          title: this.$t('Display Page Name'),
          active_value: 'true',
          inactive_value: 'false',
        },
        {
          fieldKey: 'display_description',
          type: 'switch',
          title: this.$t('Display Description'),
          active_value: 'true',
          inactive_value: 'false',
        },
        {
          fieldKey: 'display_likes_counter',
          type: 'switch',
          title: this.$t('Display Likes Counter'),
          active_value: 'true',
          inactive_value: 'false',
        },
        {
          fieldKey: 'display_followers_count',
          type: 'switch',
          title: this.$t('Display Followers Counter'),
          active_value: 'true',
          inactive_value: 'false',
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
          title: this.$t('Slides to Scroll :'),
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
          fieldKey: 'display_comments_user_picture',
          type: 'switch',
          title: this.$t('Display Comments User Photo'),
          active_value: 'true',
          inactive_value: 'false',
          disabled: this.has_pro
        },
        {
          fieldKey: 'display_likes_count',
          type: 'switch',
          title: this.$t('Display Likes Count'),
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
      ],
      like_button_settings: [
        {
          fieldKey: 'like_button_position',
          type: 'select',
          title: this.$t('Like Button Position'),
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
          fieldKey: 'like_button_text',
          type:'text',
          title: this.$t('Button Text'),
          disabled: !this.has_pro,
        }
      ],
      share_button_settings: [
        {
          fieldKey: 'share_button_position',
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
          fieldKey: 'share_button_text',
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
    toggleAccount(value, fieldKey = 'selected_accounts') {
      this.accountSelectorLoading = true;
      
      const accountIds = [...(this.feed_config.source_settings[fieldKey] || [])];
      const index = accountIds.indexOf(value.page_id);

      if (index === -1) {
        if (!this.has_pro && accountIds.length >= 1) {
          accountIds.splice(0, accountIds.length, value.page_id);
          this.handlePro();
        } else {
          accountIds.push(value.page_id);
        }
      } else {
        accountIds.splice(index, 1);
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
    shouldDisplayHeaderSettings(){
      return this.feed_config.source_settings.feed_type !== 'event_feed';
    },
    fetchFeeds() {
      this.$emit('fetchFeed');
    },
    getPagesBasedOnFeedType(pages) {
      return Object.values(pages).filter(page => 
        page.is_event_enabled === (this.feed_config.source_settings.feed_type === 'event_feed')
        ).map(page => 
        this.feed_config.source_settings.feed_type === 'event_feed' 
          ? { page_id: 'event_feed_' + page.page_id, name: page.name, is_event_enabled: page.is_event_enabled }
          : page
        );
    },    
    getHeaderAccountOptions(){
      return Object.entries(this.pages)
      .filter(([key]) => !key.includes('event_feed_'))
      .map(([key, value]) => ({ ...value, key }));
    },
    fetchFeedsButtonClicked(){
      this.feed_config.edit_mode = false;
      this.fetchFeeds();
    }, 
    fetch : debounced(function () {
      this.$emit('fetchFeed', 'fetching');
    }, 500),
    setAccount(val) {
      
      if (!this.has_pro && val.length > 1) {
        this.feed_config.source_settings.selected_accounts = [];
        this.feed_config.source_settings.selected_accounts.push(val[0]);
        
        this.handlePro();
      }
    },
    getPagesForSelect(pages){
      // pages is an object
      let options = [];
      Object.keys(pages).forEach((key) => {
        if (pages[key].is_event_enabled || key.includes('event_feed_')) {
          return;
        }
        let page = pages[key];
        options.push({
          value: page.page_id,
          label: page.name
        });
      });
      return options;
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
              value: 'facebook',
              label: this.$t('Open on Facebook')
            },
            {
              value: 'popup',
              label: this.$t('Open in Popup Box'),
              disabled: !this.has_pro,
            },
          ]
        },
        {
          fieldKey: 'display_date',
          type: 'switch',
          title: this.$t('Display Date'),
          active_value: 'true',
          inactive_value: 'false',
          condition: {
            'key': 'feed_type',
            'data': this.feed_config.source_settings,
            'operator': 'includes',
            'selector': ['timeline_feed', 'video_feed', 'event_feed'],
          }
        },
        {
          fieldKey: 'display_wp_date_format',
          type: 'switch',
          title: this.$t('Inherit Site Default Date Format'),
          active_value: 'true',
          inactive_value: 'false',
          condition: {
            'key': 'feed_type',
            'data': this.feed_config.source_settings,
            'operator': 'includes',
            'selector': ['timeline_feed', 'video_feed', 'event_feed'],
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
            'selector': 'timeline_feed',
          }
        },
        {
          fieldKey: 'equal_height',
          type: 'switch',
          title: this.$t('Equal Height'),
          active_value: 'true',
          inactive_value: 'false',
          condition: {
            'key': 'feed_type',
            'data': this.feed_config.source_settings,
            'operator': 'includes',
            'selector': ['timeline_feed', 'event_feed'],
          }
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
            'selector': 'timeline_feed',
          }
        },
        {
          fieldKey: 'display_play_icon',
          type: 'switch',
          title: this.$t('Display Play Icon:'),
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
          fieldKey: 'display_author_name',
          type: 'switch',
          title: this.$t('Display Author Name'),
          active_value: 'true',
          inactive_value: 'false',
          condition: {
            'key': 'feed_type',
            'data': this.feed_config.source_settings,
            'operator': '==',
            'selector': 'timeline_feed',
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
            'selector': ['timeline_feed', 'video_feed'],
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
            'selector': 'timeline_feed',
          }
        },
      ];
      this.feed_settings['pro'] = [
        {
          fieldKey: 'display_media',
          type: 'switch',
          title: this.$t('Display Media in Timeline Feed'),
          active_value: 'true',
          inactive_value: 'false',
          disabled : !this.has_pro,
          condition: {
            'key': 'feed_type',
            'data': this.feed_config.source_settings,
            'operator': '==',
            'selector': 'timeline_feed',
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
            'selector': 'timeline_feed',
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
            'selector': 'timeline_feed',
          }
        },
        {
          fieldKey: 'display_event_photo',
          type: 'switch',
          title: this.$t('Display Event Photo'),
          active_value: 'true',
          inactive_value: 'false',
          disabled : this.has_pro,
          condition: {
            'key': 'feed_type',
            'data': this.feed_config.source_settings,
            'operator': '==',
            'selector': 'event_feed',
          }
        },
        {
          fieldKey: 'display_event_name',
          type: 'switch',
          title: this.$t('Display Event Name'),
          active_value: 'true',
          inactive_value: 'false',
          disabled : this.has_pro,
          condition: {
            'key': 'feed_type',
            'data': this.feed_config.source_settings,
            'operator': '==',
            'selector': 'event_feed',
          }
        },
        {
          fieldKey: 'display_event_location',
          type: 'switch',
          title: this.$t('Display Event Location'),
          active_value: 'true',
          inactive_value: 'false',
          disabled : this.has_pro,
          condition: {
            'key': 'feed_type',
            'data': this.feed_config.source_settings,
            'operator': '==',
            'selector': 'event_feed',
          }
        },
        {
          fieldKey: 'display_event_interest',
          type: 'switch',
          title: this.$t('Display Event Meta'),
          active_value: 'true',
          inactive_value: 'false',
          disabled : this.has_pro,
          condition: {
            'key': 'feed_type',
            'data': this.feed_config.source_settings,
            'operator': '==',
            'selector': 'event_feed',
          }
        },
      ];

      if(this.source_settings){
        this.source_settings.forEach((item) => {
          if (['single_album_id', 'video_playlist_id', 'selected_accounts', 'selected_account', 'selected_accounts_for_events'].includes(item.fieldKey)) {
            
            if(item.fieldKey === 'selected_account' && this.feed_config.source_settings.feed_type === 'video_playlist_feed'){
              item.title = this.$t('Choose Page this Playlist belongs to');
            } else if(item.fieldKey === 'selected_account' && this.feed_config.source_settings.feed_type === 'single_album_feed') {
              item.title = this.$t('Choose Page this Album belongs to');
            }

            if (Array.isArray(item.condition)) {
              item.condition.forEach((condition) => {
                if (condition.key === 'feed_type') {
                  condition.data = this.feed_config.source_settings;
                }
              });

            }
          }
        });
      }
      
    },
    setLayouts() {
      this.layout_settings = [
        {
          fieldKey: 'layout_type',
          type: 'image_select',
          title: this.$t('Layout Type'),
          condition: [
            {
              'key': 'feed_type',
              'data': this.feed_config.source_settings,
              'operator': '!=',
              'selector': 'album_feed'
            },
            {
              'key': 'feed_type',
              'data': this.feed_config.source_settings,
              'operator': '!=',
              'selector': 'single_album_feed'
            }
          ],
          options: [
            {
              key: 'timeline',
              title: this.$t('Timeline'),
              img: this.assets_url + '/images/template/global-template/timeline.png',
            },
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
          condition: [
            {
              'key': 'feed_type',
              'data': this.feed_config.source_settings,
              'operator': '!=',
              'selector': 'album_feed'
            },
            {
              'key': 'feed_type',
              'data': this.feed_config.source_settings,
              'operator': '!=',
              'selector': 'single_album_feed'
            },
            {
              'key': 'feed_type',
              'data': this.feed_config.source_settings,
              'operator': '!=',
              'selector': 'event_feed'
            }
          ],
          options: [
            {
              key: 'template1',
              title: 'Template 1',
              img: this.assets_url + '/images/template/facebook-template/template2.png'
            },
            {
              key: 'template2',
              title: 'Template 2',
              pro: !this.has_pro,
              img: this.assets_url + '/images/template/facebook-template/template1.png'
            }
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
    setFilters(){
      this.filters_settings = [
        {
          fieldKey: 'total_posts_number',
          type: 'number',
          title: this.$t('Number of Feeds to Display'),
          min: 1,
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
          fieldKey: 'display_posts',
          type: 'multiple_select',
          title: this.$t('Display posts with'),
          label: 'label',
          value: 'value',
          options: [
            {
              value: 'all',
              label: this.$t('All Posts'),
              disabled: false
            },
            {
              value: 'text',
              label: this.$t('Only Text'),
              disabled: !this.has_pro,
            },
            {
              value: 'video_inline',
              label: this.$t('Video'),
              disabled: !this.has_pro,
            },
            {
              value: 'share',
              label: this.$t('Shared Link'),
              disabled: !this.has_pro,
            },
            {
              value: 'native_templates',
              label: this.$t('Shared Posts'),
              disabled: !this.has_pro,
            },
            {
              value: 'album',
              label: this.$t('Multiple Photos'),
              disabled: !this.has_pro,
            },
            {
              value: 'photo',
              label: this.$t('Single Photo'),
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
          tooltipText: this.$t('You can find the ID of a Post by viewing the post on Facebook and copy/pasting the ID number from the end of the URL </br> ex: https://www.facebook.com/105787145076291/posts/219046507083687/ [ in this URL ID is "219046507083687" ]')
        },
        {
          fieldKey: 'date_range',
          type: 'switch',
          disabled: !this.has_pro,
          title: this.$t('Show Posts Within a Date Range'),
          event: 'on_change',
          activeValue: 'true',
          inactiveValue: 'false',
        },
        {
          fieldKey: 'date_range_type',
          disabled: !this.has_pro,
          type: 'tab_select',
          title: this.$t('Date Range Type'),
          event: 'on_change',
          options: [
            {
              value: 'specific_date',
              label: this.$t('Specific'),
              disabled: !this.has_pro,
            },
            {
              value: 'relative_date',
              label: this.$t('Relative'),
              disabled: !this.has_pro,
            },
          ],
          condition: {
            key: 'date_range',
            operator: 'isTrue',
          }
        },
        {
          fieldKey: 'date_range_start_specific',
          type: 'date_picker',
          disabled: !this.has_pro,
          title: this.$t('From'),
          event: 'on_change',
          condition: [
            {
              'key': 'date_range',
              'operator': 'isTrue',
            },
            {
              'key': 'date_range_type',
              'operator': '==',
              'selector': 'specific_date' ,
            }
          ]
        },
        {
          fieldKey: 'date_range_end_specific',
          type: 'date_picker',
          disabled: !this.has_pro,
          title: this.$t('To'),
          event: 'on_change',
          condition: [
            {
              'key': 'date_range',
              'operator': 'isTrue',
            },
            {
              'key': 'date_range_type',
              'operator': '==',
              'selector': 'specific_date' ,
            }
          ]
        },
        {
          fieldKey: 'date_range_start_relative',
          type: 'text',
          disabled: !this.has_pro,
          title: this.$t('From'),
          placeholder: this.$t('ex: -15 days'),
          event: 'on_change',
          condition: [
            {
              'key': 'date_range',
              'operator': 'isTrue',
            },
            {
              'key': 'date_range_type',
              'operator': '==',
              'selector': 'relative_date' ,
            }
          ]
        },
        {
          fieldKey: 'date_range_end_relative',
          type: 'text',
          disabled: !this.has_pro,
          title: this.$t('To'),
          placeholder: this.$t('ex: now'),
          event: 'on_change',
          condition: [
            {
              'key': 'date_range',
              'operator': 'isTrue',
            },
            {
              'key': 'date_range_type',
              'operator': '==',
              'selector': 'relative_date' ,
            }
          ]
        }
      ]
    },
    updateSourcePagesOptions(){
      this.source_settings.forEach((item) => {
        if (item.fieldKey === 'selected_accounts' || item.fieldKey === 'selected_accounts_for_events') {
          item.options = this.getPagesBasedOnFeedType(this.pages);
        }
      });
    },
    setPagesAfterMount(){
      this.updateSourcePagesOptions();
    },
    updateSourceSettings() {
      this.source_settings.forEach(setting => {
        if (setting.condition) {
          setting.condition.data = this.feed_config.source_settings;
        }
      });
    }
  },
  watch: {
    'feed_config.source_settings.feed_type': {
      handler(new_value) {
        this.feed_config.edit_mode = true;
        
        this.updateSourcePagesOptions();
        
        if(
          !this.feed_config.source_settings.selected_accounts_for_events || 
          this.feed_config.source_settings.selected_accounts_for_events.length === 0 || 
          this.feed_config.source_settings.selected_accounts.length === 0
        ){
          return;
        }
        this.feed_config.header_settings.display_header = false;

        this.fetchFeeds();
      },
    },
    'feed_config': {
      handler(new_value) {
        this.setData();
        this.setLayouts();
        this.setFilters();
      },
      deep: true
    },
    'feed_config.layout_type': {
      handler(newval, oldval) {
        if (newval === 'timeline') {
          if(this.feed_config.responsive_column_number) {
            this.feed_config.responsive_column_number.desktop = '12';
            this.feed_config.responsive_column_number.tablet = '12';
          }
        } else if ((newval === 'grid' || newval === 'masonry' || newval === 'carousel') && (oldval === 'timeline')) {
          if(this.feed_config.responsive_column_number){
            this.feed_config.responsive_column_number.desktop = '4';
            this.feed_config.responsive_column_number.tablet = '4';
          }
        }
      },
      deep: true,
    },
    'feed_config.source_settings': {
      handler(newValue) {
        this.updateSourceSettings();
      },
      deep: true
    }
  },
  mounted() {
    this.feed_settings_backup_free = this.feed_settings.free;
    this.feed_settings_backup_pro = this.feed_settings.pro;
    this.setData();
    this.setLayouts();
    this.setFilters();
    this.setPagesAfterMount();
    this.updateSourceSettings()
  }
}
</script>