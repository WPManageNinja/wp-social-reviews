<template>
  <div>
    <!-- Loading Skeleton -->
    <div v-if="fetching" class="wpsr-general-settings-wrapper">
      <div class="wpsr-settings-container">
        <WpsrSkeleton :rows="15" />
      </div>
    </div>

    <!-- Content -->
    <div v-else-if="!fetching">
      <div class="wpsr-gs-header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
        <h1 class="wpsr-gs-header-title">{{$t('Translation Settings')}}</h1>
        <el-button @click="saveSettings" type="success" class="wpsr_primary_btn" size="default">Save Settings</el-button>
      </div>
      <div class="wpsr-general-settings-wrapper" v-if="hasPermission(['wpsn_translation_settings', 'wpsn_full_access'])">
        <div class="wpsr-settings-container" v-if="Object.keys(translations).length">
        <div class="wpsr-translation-header">
          <h2 class="wpsr-translation-header-title">{{$t('Custom Text/Translate')}}</h2>
          <p class="wpsr-translation-header-description">Enter custom text for the words below, or translate it into the language you would like to use.</p>
        </div>
        
        <div class="wpsr-translation-table">
          <!-- Table Header -->
          <div class="wpsr-translation-table-header">
            <div class="wpsr-translation-original-column">
              <span>Original Text</span>
            </div>
            <div class="wpsr-translation-custom-column">
              <span>Custom Text/Translation</span>
            </div>
          </div>
          
          <!-- Table Body -->
          <div class="wpsr-translation-table-body">
            <div 
              v-for="(field, key) in translations_settings" 
              :key="key" 
              class="wpsr-translation-row"
            >
              <div class="wpsr-translation-original-cell">
                <span class="wpsr-translation-original-text">{{ field.title }}</span>
              </div>
              <div class="wpsr-translation-custom-cell">
                <el-input 
                  v-model="translations[field.fieldKey]"
                  type="text"
                  class="wpsr-text-input wpsr-input-border"
                  placeholder="Placeholder text..."
                  clearable
                />
              </div>
            </div>
          </div>
        </div>

        <!-- <div class="wpsr-translation-actions">
          <el-button @click="saveSettings" type="success" size="default">Save Settings</el-button>
        </div> -->
        </div>
      </div>
      <PermissionsNotification v-if="!hasPermission(['wpsn_translation_settings', 'wpsn_full_access'])" />
    </div>
  </div>
</template>

<script type="text/babel">
import FeedEditorGroup from '../../../core-ui/editor/EditorGroup';
import PermissionsNotification from "../../advertise/PermissionsNotification";
export default {
  name: 'TranslationSettings',
  components: {
    PermissionsNotification,
    FeedEditorGroup
  },
  data() {
    return {
      translations: [],
      fetching: false,
      translations_settings: [
        {
          fieldKey: 'subscribers',
          type: 'text',
          title: 'Subscribers',
        },
        {
          fieldKey: 'following',
          type: 'text',
          title: 'Following',
        },
        {
          fieldKey: 'followers',
          type: 'text',
          title: 'Followers',
        },
        {
          fieldKey: 'videos',
          type: 'text',
          title: 'Videos',
        },
        {
          fieldKey: 'views',
          type: 'text',
          title: 'Views',
        },
        {
          fieldKey: 'tweets',
          type: 'text',
          title: 'Tweets',
        },
        {
          fieldKey: 'people_like_this',
          type: 'text',
          title: 'People like this',
        },
        {
          fieldKey: 'posts',
          type: 'text',
          title: 'Posts',
        },
        {
          fieldKey: 'leave_a_review',
          type: 'text',
          title: 'Where you want to leave a review',
        },
        {
          fieldKey: 'recommends',
          type: 'text',
          title: 'Recommends',
        },
        {
          fieldKey: 'does_not_recommend',
          type: 'text',
          title: 'Does not recommend',
        },
        {
          fieldKey: 'on',
          type: 'text',
          title: 'On',
        },
        {
          fieldKey: 'read_all_reviews',
          type: 'text',
          title: 'Read all reviews',
        },
        {
          fieldKey: 'read_more',
          type: 'text',
          title: 'Read More',
        },
        {
          fieldKey: 'read_less',
          type: 'text',
          title: 'Read Less',
        },
        // {
        //   fieldKey: 'ago',
        //   type: 'text',
        //   title: 'Ago',
        // },
        {
          fieldKey: 'comments',
          type: 'text',
          title: 'Comments',
        },
        {
          fieldKey: 'view_on_fb',
          type: 'text',
          title: 'View on Facebook',
        },
        {
          fieldKey: 'people_responded',
          type: 'text',
          title: 'People Responded',
        },
        {
          fieldKey: 'online_event',
          type: 'text',
          title: 'Online Event',
        },
        {
          fieldKey: 'interested',
          type: 'text',
          title: 'Interested',
        },
        {
          fieldKey: 'going',
          type: 'text',
          title: 'Going',
        },
        {
          fieldKey: 'went',
          type: 'text',
          title: 'Went',
        },
        {
          fieldKey: 'view_on_ig',
          type: 'text',
          title: 'View on Instagram',
        },
        {
          fieldKey: 'likes',
          type: 'text',
          title: 'likes',
        },
        {
          fieldKey: 'view_on_tiktok',
          type: 'text',
          title: 'View on TikTok',
        },
        {
          fieldKey: 'ai_generated_summary',
          type: 'text',
          title: 'AI-Generated Summary',
        }
      ]
    }
  },
  methods: {
    getSettings() {
      this.fetching = true;
      this.$get('settings/translations')
          .then((response) => {
            if(response) {
              this.translations = Object.fromEntries(
                  Object.entries(response.translations_settings).map(([key, value]) => [
                    key.toLowerCase().replace(/[\s\-]+/g, '_'),
                    value
                  ])
              );
            }
          })
          .catch(errors => {
            this.handleError(errors);
          })
          .always(() => {
            this.fetching = false;
          });
    },

    saveSettings() {
      this.fetching = true;
      this.$post('settings/translations', {
        translations_settings: this.translations
      })
          .then(response => {
            this.handleSuccess(response.message)
          })
          .catch(errors => {
            this.handleError(errors);
          }).always(() => {
            this.fetching = false;
          });
    }
  },
  mounted() {
    this.getSettings();
  }
}
</script>
