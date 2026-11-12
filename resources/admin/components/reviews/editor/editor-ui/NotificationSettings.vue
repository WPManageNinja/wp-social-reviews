<template>
  <div>
    <EditorGroup
        :fieldsMaps="notification_settings"
        v-model="template_meta.notification_settings"
        @on-change="$emit('on-change')"
    />
  </div>
</template>

<script>
import EditorGroup from '../../../core-ui/editor/EditorGroup';

export default {
  name: "NotificationSettings",
  props: ['post_types', 'template_meta'],
  components: {
    EditorGroup
  },
  watch: {
    // Picking Review Spotlight snapshots source_display/display_custom_text and switches them to
    // its look; switching back to Rotating Card restores the snapshot. Fires only on an actual
    // selection, so opening an existing saved config never overwrites what the user chose.
    'template_meta.notification_settings.template'(newVal, oldVal) {
      if (newVal === oldVal) {
        return;
      }
      if (newVal === 'notification2') {
        this.previousSourceDisplay = this.template_meta.notification_settings.source_display;
        this.previousDisplayCustomText = this.template_meta.notification_settings.display_custom_text;
        this.template_meta.notification_settings.source_display = 'logo_text';
        this.template_meta.notification_settings.display_custom_text = 'false';
        this.$emit('on-change');
      } else if (oldVal === 'notification2') {
        this.template_meta.notification_settings.source_display = this.previousSourceDisplay || 'logo';
        this.template_meta.notification_settings.display_custom_text = this.previousDisplayCustomText || 'true';
        this.$emit('on-change');
      }
    }
  },
  data() {
    return {
      // Set by the watcher above; null until the first switch into Spotlight.
      previousSourceDisplay: null,
      previousDisplayCustomText: null,
      notification_settings: [
        {
          fieldKey: 'template',
          type: 'select',
          title: this.$t('Notification Style'),
          options: [
            {
              value: 'notification1',
              label: this.$t('Rotating Card'),
            },
            {
              value: 'notification2',
              label: this.$t('Review Spotlight'),
            },
            {
              value: 'notification3',
              label: this.$t('Rating Pulse'),
            }
          ]
        },
        {
          fieldKey: 'source_display',
          type: 'select',
          title: this.$t('Source Display'),
          tooltip: true,
          tooltipText: this.$t('How the review source is shown in Review Spotlight: a bare logo, matching Rotating Card, or the logo plus the platform name.'),
          options: [
            {
              value: 'logo',
              label: this.$t('Logo only'),
            },
            {
              value: 'logo_text',
              label: this.$t('Logo + platform name'),
            }
          ],
          condition: { key: 'template', selector: 'notification2' }
        },
        {
          fieldKey: 'display_custom_text',
          type: 'switch',
          title: this.$t('Display Custom Notification Title'),
          active_value: 'true',
          inactive_value: 'false',
          // Rating Pulse shows the aggregate score, not a per-review custom line.
          condition: { key: 'template', operator: 'includes', selector: ['notification1', 'notification2'] }
        },
        {
          fieldKey: 'custom_notification_text',
          type: 'text',
          title: this.$t('Custom Notification Title'),
          placeholder: this.$t('Just left us a {review_rating} star review'),
          tooltip: true,
          tooltipText: this.$t('You can use this {review_rating} shortcode to show dynamic value in a text. Ex: Just left us a {review_rating} star review.'),
          condition: [
            { key: 'display_custom_text', selector: 'true' },
            { key: 'template', operator: 'includes', selector: ['notification1', 'notification2'] }
          ]
        },
        {
          fieldKey: 'notification_position',
          type: 'select',
          title: this.$t('Notification Position'),
          options: [
            {
              value: 'float_left_bottom',
              label: this.$t('Float Left Bottom'),
            },
            {
              value: 'float_right_bottom',
              label: this.$t('Float Right Bottom'),
            }
          ]
        },
        {
          fieldKey: 'display_mode',
          type: 'select',
          title: this.$t('Display Reviews in'),
          options: [
            {
              value: 'none',
              label: 'None'
            },
            {
              value: 'popup',
              label: 'Popup',
            },
            {
              value: 'page',
              label: 'Page',
            },
            {
              value: 'custom_url',
              label: 'Custom Url',
            }
          ],
        },
        {
          fieldKey: 'custom_url',
          type: 'text',
          title: this.$t('Custom Url'),
          placeholder: this.$t('Enter Custom Url'),
          condition: {
            'key': 'display_mode',
            'selector': 'custom_url'
          }
        },
        {
          fieldKey: 'id',
          type: 'async_multiple_select',
          title: this.$t('Select a redirect page'),
          searchRoute: 'pages/search',
          multiple: false,
          includeEverywhere: false,
          condition: {
            'key': 'display_mode',
            'selector': 'page'
          }
        },
        {
          fieldKey: 'display_read_all_reviews_btn',
          type: 'switch',
          title: this.$t('Display \'Read all reviews\' Button'),
          active_value: 'true',
          inactive_value: 'false',
          condition: {
            'key': 'display_mode',
            'selector': 'popup'
          },
        },
        {
          fieldKey: 'read_all_reviews_btn_url',
          type: 'text',
          title: this.$t('\'Read all reviews\' Button URL'),
          placeholder: this.$t(''),
          tooltip: true,
          tooltipText: this.$t('Please enter your business all reviews URL. You can set only single business URL.'),
          condition: {
            operator: 'multiple',
            terms: [
              {
                'key': 'display_read_all_reviews_btn',
                'selector': 'true'
              },
              {
                'key': 'display_mode',
                'selector': 'popup'
              },
            ]
          },
        },
        {
          fieldKey: 'page_list',
          type: 'async_multiple_select',
          title: this.$t('Include Pages to Display Notification'),
          searchRoute: 'pages/search',
          label: 'title',
          value: 'id'
        },
        {
          fieldKey: 'exclude_page_list',
          type: 'async_multiple_select',
          title: this.$t('Exclude Pages to Hide Notification'),
          searchRoute: 'pages/search',
          disabled: !this.has_pro,
          label: 'title',
          value: 'id'
        },
        {
          fieldKey: 'post_types',
          type: 'multiple_select',
          title: this.$t('Or Display by Post Type'),
          options: this.post_types,
          label: 'title',
          value: 'name'
        },
        {
          fieldKey: 'hide_on_desktop',
          type: 'switch',
          title: this.$t('Hide Notification on Desktop'),
          active_value: 'true',
          inactive_value: 'false'
        },
        {
          fieldKey: 'hide_on_mobile',
          type: 'switch',
          title: this.$t('Hide Notification on Mobile'),
          active_value: 'true',
          inactive_value: 'false'
        },
        {
          fieldKey: 'display_close_button',
          type: 'switch',
          title: this.$t('Display Close Button'),
          active_value: 'true',
          inactive_value: 'false'
        },
        {
          fieldKey: 'display_date',
          type: 'switch',
          title: this.$t('Display Date'),
          active_value: 'true',
          inactive_value: 'false',
          // Rating Pulse has no per-review timestamp to show.
          condition: { key: 'template', operator: 'includes', selector: ['notification1', 'notification2'] }
        },
        {
          fieldKey: 'initial_delay',
          type: 'number',
          title: this.$t('Initial Delay'),
          min: 1000,
          flex: true,
          tooltip: true,
          tooltipText: this.$t('Delay before first notification. (Time should be in Millisecond.)'),
          step: 100
        },
        {
          fieldKey: 'notification_delay',
          type: 'number',
          title: this.$t('Delay Between'),
          min: 1000,
          flex: true,
          tooltip: true,
          tooltipText: this.$t('Delay between each notification. (Time should be in Millisecond.)'),
          step: 100
        },
        {
          fieldKey: 'delay_for',
          type: 'number',
          title: this.$t('Display For'),
          min: 1000,
          flex: true,
          tooltip: true,
          tooltipText: this.$t('Display each notification for * seconds. (Time should be in Millisecond.)'),
          step: 100
        }
      ],
    }
  }
}
</script>