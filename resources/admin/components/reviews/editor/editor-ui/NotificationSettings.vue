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
  props: ['pages', 'post_types', 'template_meta'],
  components: {
    EditorGroup
  },
  data() {
    return {
      notification_settings: [
        {
          fieldKey: 'custom_notification_text',
          type: 'text',
          title: this.$t('Custom Notification Title'),
          placeholder: this.$t('Just left us a {review_rating} star review'),
          tooltip: true,
          tooltipText: this.$t('You can use this {review_rating} shortcode to show dynamic value in a text. Ex: Just left us a {review_rating} star review.')
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
          type: 'dynamic_select',
          title: this.$t('Select a redirect page'),
          options: JSON.parse(JSON.stringify(this.pages)).splice(1),
          placeholder: this.$t('Select a redirect page'),
          label: 'title',
          value: 'id',
          multiple: false,
          event: 'on_change',
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
          type: 'multiple_select',
          title: this.$t('Include Pages to Display Notification'),
          options: this.pages,
          label: 'title',
          value: 'id'
        },
        {
          fieldKey: 'exclude_page_list',
          type: 'multiple_select',
          title: this.$t('Exclude Pages to Hide Notification'),
          options: this.pages,
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
          inactive_value: 'false'
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