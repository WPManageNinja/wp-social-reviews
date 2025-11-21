<template>
      <el-collapse-item name="2">
          <template #title="{ isActive}">
              <EditorCollapsiblePanel :isActive="isActive" iconKey="template" :label="$t('Template')"/>
          </template>
          <ChatEditorGroup
              :fieldsMaps="layout_fields"
              :modelValue="chat_config"
              @update:modelValue="updateChatConfig"
          />
      </el-collapse-item>

      <el-collapse-item name="3" v-if="chat_config?.layout_type === 'chat_box'">
          <template #title="{ isActive}">
              <EditorCollapsiblePanel :isActive="isActive" iconKey="chatHeader" :label="$t('Chat Header')"/>
          </template>
          <ChatEditorGroup
              v-if="templateConfig?.chat_header"
              :fieldsMaps="header_fields"
              :modelValue="templateConfig.chat_header"
              @update:modelValue="updateChatHeader"
          />
      </el-collapse-item>

      <el-collapse-item name="4" v-if="hasFluentForms()">
        <template #title="{ isActive}">
            <EditorCollapsiblePanel :isActive="isActive" iconKey="FluentForms" :label="$t('Fluent Forms')"/>
        </template>
        <ChatEditorGroup
            v-if="chat_config?.ff_settings"
            :fieldsMaps="ff_fields"
            :modelValue="chat_config.ff_settings"
            @update:modelValue="updateFfSettings"
        />
      </el-collapse-item>

      <el-collapse-item name="5" v-if="chat_config.layout_type === 'chat_box'">
          <template #title="{ isActive}">
              <EditorCollapsiblePanel :isActive="isActive" iconKey="welcomeMessage" :label="$t('Welcome Message')"/>
          </template>
          <ChatEditorGroup
              :fieldsMaps="body_fields"
              :modelValue="templateConfig.chat_body"
              @update:modelValue="updateChatBody"
          />
      </el-collapse-item>

      <el-collapse-item name="6">
          <template #title="{ isActive}">
              <EditorCollapsiblePanel :isActive="isActive" iconKey="channelButtons" :label="$t('Channel Buttons')"/>
          </template>
          <ChatEditorGroup
              :fieldsMaps="button_fields"
              :modelValue="chat_config.chat_button"
              @update:modelValue="updateChatButton"
          />
      </el-collapse-item>

      <el-collapse-item name="7">
          <template #title="{ isActive}">
              <EditorCollapsiblePanel :isActive="isActive" iconKey="channelBubbleButton" :label="$t('Chat Bubble Button')"/>
          </template>
          <div>
            <ChatEditorGroup
                :fieldsMaps="bubble_fields"
                :modelValue="templateConfig.chat_bubble"
                @update:modelValue="updateChatBubble"
            />
          </div>
      </el-collapse-item>

      <el-collapse-item name="8">
          <template #title="{ isActive}">
              <EditorCollapsiblePanel :isActive="isActive" iconKey="settings" :label="$t('Settings')"/>
          </template>
          <ChatEditorGroup
              :fieldsMaps="settings_fields"
              :modelValue="chat_config.settings"
              @update:modelValue="updateSettings"
          />
      </el-collapse-item>

      <!-- <el-collapse-item name="10">
          <template #title="{ isActive}">
              <EditorCollapsiblePanel :isActive="isActive" iconKey="priority" :label="$t('Priority')"/>
          </template>
          <h3 class="demonstration">{{ $t('Priority for this widget.') }}</h3>
          <el-input-number v-model="chat_config.menu_order"></el-input-number>
          <p>{{ $t('The more priority you give, it will match for the condition and show on your appropriate pages') }}</p>
      </el-collapse-item> -->

      <el-collapse-item name="10" >
        <template #title="{ isActive}">
          <EditorCollapsiblePanel :isActive="isActive" iconKey="priority" :label="$t('Priority')"/>
        </template>
        <h3 class="wpsr-demonstration">Priority for this Stream.</h3>
        <!-- <el-input-number v-model=""></el-input-number> -->

        <ChatEditorGroup
            :fieldsMaps="priority_settings"
            :modelValue="chat_config"
            @update:modelValue="updateChatConfig"
        />
        <p>The more priority you give, it will match for the condition and show on your appropriate pages</p>
    </el-collapse-item>
</template>

<script type="text/babel">
import { ElTree } from 'element-plus';
import 'element-plus/dist/index.css';
import ChatEditorGroup from './../../../core-ui/editor/EditorGroup';
import Remove from './../../../core-ui/editor/RemoveConfirm';
import UpgradeToProButton from '../../../views/advertise/UpgradeToProButton';
import PlusIcon from '../../../pieces/icons/PlusIcon.vue';
import MinusIcon from '../../../pieces/icons/MinusIcon.vue';
import ChatEditorAccordionsIconPack from '../../../pieces/icons/ChatEditorAccordionsIconPack.vue';
import EditorCollapsiblePanel from '../../../core-ui/editor/EditorCollapsiblePanel.vue';
export default {
  inheritAttrs: false,
  props: ['chat_config', 'pages', 'languages', 'post_types', 'menu_order', 'pre_selected_channels'],
  components: {
      ChatEditorGroup,
      Remove,
      UpgradeToProButton,
      ElTree,
      ChatEditorAccordionsIconPack,
      PlusIcon,
      MinusIcon,
      EditorCollapsiblePanel
  },
  emits: ['on-update'],
  data() {
      return {
          has_fluent_form: this.appVars.has_fluent_form,
          available_bubble_icons: false,
          layout_fields: [
            {
              fieldKey: 'layout_type',
              type: 'select',
              title: this.$t('Layout Type'),
              options: [
                {
                  value: 'chat_box',
                  label: this.$t('Chat Box')
                },
                {
                  value: 'icons',
                  label: this.$t('Only Icons')
                }
              ]
            },
            {
              fieldKey: 'template',
              type: 'image_select',
              condition: {
                'key': 'layout_type',
                'selector': 'chat_box'
              },
              options: [
                {
                  key: 'template1',
                  title: 'General',
                  img: this.assets_url + '/images/template/chat-template/general.png'
                },
                {
                  key: 'template2',
                  title: 'Support',
                  img: this.assets_url + '/images/template/chat-template/support.png'
                },
                {
                  key: 'template3',
                  title: 'Feedback',
                  pro: !this.has_pro,
                  img: this.assets_url + '/images/template/chat-template/feedback.png'
                },
                {
                  key: 'template4',
                  title: 'Sale',
                  pro: !this.has_pro,
                  img: this.assets_url + '/images/template/chat-template/sales.png'
                }
              ]
            },
          ],
          header_fields: [
              {
                  fieldKey: 'name',
                  type: 'text',
                  title: this.$t('Profile/Page Name'),
              },
              {
                  fieldKey: 'caption',
                  type: 'textarea',
                  title: this.$t('Caption'),
              },
              {
                  fieldKey: 'picture',
                  type: 'image',
                  title: this.$t('Profile Picture'),
              }
          ],
          ff_fields: [
            {
              fieldKey: 'header_title',
              type: 'text',
              title: this.$t('Title'),
            },
          ],
          body_fields: [
              {
                  fieldKey: 'greeting_msg',
                  type: 'wp_editor',
                  title: this.$t('Greeting Message'),
                  value: this.templateConfig?.chat_body?.greeting_msg || '',
                  settings: {
                      media_buttons: false,
                      textarea_name: 'greeting_msg',
                      editor_height: 120,
                      tinymce: {
                          height: 120,
                          plugins: 'lists link image',
                          toolbar: 'bold italic | alignleft aligncenter alignright | bullist numlist | link image | undo redo'
                      }
                  }
              }
          ],
          button_fields: [],
          bubble_fields: [],
          priority_settings:[
            {
              fieldKey: 'menu_order',
              type: 'number',
              title: this.$t('Priority'),
              min: 0,
              flex: true,
              tooltip: true,
              tooltipText: this.$t('The higher the number, the higher the priority.'),
            }
          ],
          settings_fields: [
              {
                  fieldKey: 'chat_bubble_position',
                  type: 'select',
                  title: this.$t('Chat Bubble Position'),
                  options: [
                      {
                          value: 'bottom-right',
                          label: this.$t('Bottom Right')
                      },
                      {
                          value: 'bottom-left',
                          label: this.$t('Bottom Left')
                      },
                      {
                          value: 'top-left',
                          label: this.$t('Top Left')
                      },
                      {
                          value: 'top-right',
                          label: this.$t('Top Right')
                      }
                  ],
              },
            {
              fieldKey: 'chat_lang',
              type: 'select',
              event: 'on_change',
              isFilterable: true,
              title: this.$t('Chat Language'),
              options: this.languages,
              disabled: !this.has_pro,
              tooltip: true,
              tooltipText: 'Target your widget to a specific language. <br/> You can create different widgets for different languages.'
            },
              // {
              //   fieldKey: 'chat_bubble_scroll_position',
              //   type: 'number',
              //   title: this.$t('Chat Bubble Scroll Position(px)'),
              //   disabled : false,//this.has_pro,
              //   coming_soon: true
              // },
              {
                  fieldKey: 'page_list',
                  type: 'multiple_select',
                  title: this.$t('Include Pages to Display Chat'),
                  options: this.pages,
                  label: 'title',
                  value: 'id',
                  disabled: !this.has_pro,
                  isFilterable: true
              },
              {
                fieldKey: 'exclude_page_list',
                type: 'multiple_select',
                title: this.$t('Exclude Pages to Hide Chat'),
                options: this.pages,
                disabled: !this.has_pro,
                label: 'title',
                value: 'id',
                isFilterable: true
              },
              {
                  fieldKey: 'post_types',
                  type: 'multiple_select',
                  title: this.$t('Or Display by Post Type'),
                  options: this.post_types,
                  label: 'title',
                  value: 'name',
                  isFilterable: true
              },
              {
                fieldKey: 'show_label',
                type: 'switch',
                title: this.$t('Show Label (Works on chat box)'),
                active_value: 'true',
                inactive_value: 'false'
              },
              {
                fieldKey: 'hide_on_desktop',
                type: 'switch',
                title: this.$t('Hide Chat on Desktop'),
                active_value: 'true',
                inactive_value: 'false'
              },
              {
                fieldKey: 'hide_on_mobile',
                type: 'switch',
                title: this.$t('Hide Chat on Mobile'),
                active_value: 'true',
                inactive_value: 'false'
              },
              {
                  fieldKey: 'display_greeting',
                  type: 'switch',
                  title: this.$t('Show Chat Popup'),
                  active_value: 'true',
                  inactive_value: 'false'
              },
              {
                fieldKey: 'popup_delay',
                type: 'number',
                title: this.$t('Chat Popup Delay'),
                flex: true,
                condition: {
                  'key': 'display_greeting',
                  'selector': 'true'
                }
              },
              {
                fieldKey: 'popup_target',
                type: 'switch',
                title: this.$t('Chat Open in Popup Window'),
                active_value: 'true',
                inactive_value: 'false',
                flex: true,
                tooltip: true,
                tooltipText: 'Some version of Device don\'t allow popup in the same tab with custom width and height. <br/> This issue only happens on some specific Device. It\'s not a generic issue.'
              },
              {
                  fieldKey: 'day_time_schedule',
                  type: 'switch',
                  title: this.$t('Show Online/Offline'),
                  active_value: 'true',
                  inactive_value: 'false',
                  disabled: !this.has_pro
              },
              {
                fieldKey: 'day_list',
                type: 'checkbox_group',
                display: 'grid',
                title: this.$t('When to Display Online'),
                options: ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                condition: {
                  'key': 'day_time_schedule',
                  'selector': 'true'
                }
              },
              {
                fieldKey: 'time_schedule',
                type: 'switch',
                title: this.$t('Set the Time Schedule'),
                active_value: 'true',
                inactive_value: 'false',
                condition: {
                  key: 'day_time_schedule',
                  selector: 'true'
                }
              },
              {
                fieldKey: 'start_time',
                type: 'time_picker',
                title: this.$t('Start Time'),
                condition: {
                  operator: 'multiple',
                  terms: [
                    {
                      key: 'day_time_schedule',
                      selector: 'true'
                    },
                    {
                      key: 'time_schedule',
                      selector: 'true'
                    }
                  ]
                }
              },
              {
                fieldKey: 'end_time',
                type: 'time_picker',
                title: this.$t('End Time'),
                condition: {
                  operator: 'multiple',
                  terms: [
                    {
                      key: 'day_time_schedule',
                      selector: 'true'
                    },
                    {
                      key: 'time_schedule',
                      selector: 'true'
                    }
                  ]
                }
              },
              {
                fieldKey: 'caption_when_offline',
                type: 'textarea',
                title: this.$t('Caption Text When Offline'),
                rows: 6,
                condition: {
                  'key': 'day_time_schedule',
                  'selector': 'true'
                }
              },
          ],
          channels: [],
          selected_channels: [],
          selected_channel: null,
          phone_number: '',
          channelModal: false,
          channelForm: false,
          edit_channel: false,
          channel_index: null,
          saving: false,
          draggedIndex: null,
      }
  },
  methods: {
      hasFluentForms(){
        let channels = this.chat_config.channels;
        if(channels.some(channel => channel.name === 'fluent_forms')){
          return true;
        } else {
          return false;
        }
      },
      updateChatConfig(newVal) {
        this.$emit('update:chat_config', newVal);
      },
      setBubbleFields() {
        const chatBubble = this.chat_config[this.chat_config.template].chat_bubble;
        const defaultIcon = this.chat_config.channels.length === 1
            ? `${this.assets_url}/images/svg/${this.chat_config.channels[0].name}.svg`
            : `${this.assets_url}/images/icon/chat-icon/icon1.svg`;

        const icons = {};

        if (defaultIcon !== `${this.assets_url}/images/icon/chat-icon/icon1.svg`) {
          for (let i = 1; i <= 6; i++) {
            icons[`icon${i}`] = `Icon ${i}`;
          }
        } else {
          for (let i = 2; i <= 6; i++) {
            icons[`icon${i}`] = `Icon ${i - 1}`;
          }
        }

        this.bubble_fields = [
          {
            fieldKey: 'cb_button_icon',
            title: this.$t("Select Bubble Icon"),
            type: 'image_select',
            class: 'wpsr-element-selection-icons wpsr-inline-element-selection',
            options: Object.entries(icons).map(([key, title]) => ({
              key,
              title,
              pro: !this.has_pro,
              img: `${this.assets_url}/images/icon/chat-icon/${key}.svg`,
            })).concat(defaultIcon ? {
              key: '',
              title: 'Default',
              img: defaultIcon,
            } : []),
          },
          {
            fieldKey:'cb_custom_icon',
            type: 'image',
            title: this.$t('Or Upload Custom Icon'),
            disabled: !this.has_pro,
          },
          {
            fieldKey: 'cb_button_text',
            type: 'text',
            title: this.$t('Bubble Text'),
            disabled: !this.has_pro,
          },
        ];

        if (chatBubble.cb_custom_icon === '' && chatBubble.cb_button_icon === 'custom') {
          chatBubble.cb_button_icon = '';
        }

        if (chatBubble.cb_custom_icon !== '') {
          this.bubble_fields[0].options.push({
            key: 'custom',
            title: 'Custom',
            pro: !this.has_pro,
            img: chatBubble.cb_custom_icon,
          });
        }
      },
      setChatButtons() {
        this.button_fields = [
          {
            fieldKey: 'display_icon',
            type: 'switch',
            title: this.$t('Display Chat Icon'),
            active_value: 'true',
            inactive_value: 'false',
          },
          {
            fieldKey: 'button_text',
            type: 'text',
            title: this.$t('Chat Button Text'),
          },
        ]

        this.button_fields.push(
            {
              fieldKey: 'prefilled_message',
              type: 'switch',
              title: this.$t('Prefilled Message'),
              disabled: !this.has_pro,
              active_value: 'true',
              inactive_value: 'false',
            },
            {
              fieldKey: 'prefilled_placeholder_text',
              type: 'text',
              title: this.$t('Prefilled Input Placeholder Text'),
              condition: {
                'key': 'prefilled_message',
                'selector': 'true'
              }
            }
        );
      },
      resetChatBubbleIcon() {
        this.templateConfig.chat_bubble.cb_button_icon = '';
      },
      shouldShowPrefilledMessage() {
        return (
            this.chat_config.channels.length === 1 &&
            this.chat_config.channels[0].name === 'whatsapp' &&
            this.chat_config.layout_type === 'chat_box'
        );
      },
      updateChatConfig(newVal) {
        this.$emit('update:chat_config', newVal);
      },
      updateChatHeader(newVal) {
        const updatedConfig = {
          ...this.chat_config,
          [this.chat_config.template]: {
            ...this.chat_config[this.chat_config.template],
            chat_header: newVal
          }
        };
        this.$emit('update:chat_config', updatedConfig);
      },
      updateFfSettings(newVal) {
        const updatedConfig = {
          ...this.chat_config,
          ff_settings: newVal
        };
        this.$emit('update:chat_config', updatedConfig);
      },
      updateChatBody(newVal) {
        const updatedConfig = {
          ...this.chat_config,
          [this.chat_config.template]: {
            ...this.chat_config[this.chat_config.template],
            chat_body: newVal
          }
        };
        this.$emit('update:chat_config', updatedConfig);
      },
      updateChatButton(newVal) {
        const updatedConfig = {
          ...this.chat_config,
          chat_button: newVal
        };
        this.$emit('update:chat_config', updatedConfig);
      },
      updateChatBubble(newVal) {
        const updatedConfig = {
          ...this.chat_config,
          [this.chat_config.template]: {
            ...this.chat_config[this.chat_config.template],
            chat_bubble: newVal
          }
        };
        this.$emit('update:chat_config', updatedConfig);
      },
      updateSettings(newVal) {
        const updatedConfig = {
          ...this.chat_config,
          settings: newVal
        };
        this.$emit('update:chat_config', updatedConfig);
      },
      updateStyles(newVal) {
        const updatedConfig = {
          ...this.chat_config,
          styles: newVal
        };
        this.$emit('update:chat_config', updatedConfig);
      },
      onFilterOptionsUpdate(fieldKey, value) {
        if (fieldKey === 'layout_type') {
          const updatedConfig = {
            ...this.chat_config,
            layout_type: value
          };
          this.$emit('update:chat_config', updatedConfig);
        } else if (fieldKey === 'template') {
          const updatedConfig = {
            ...this.chat_config,
            template: value
          };
          this.$emit('update:chat_config', updatedConfig);
        }
      }
  },

  watch: {
      //if layout is icons then always show template1
      'chat_config.layout_type': {
          handler(newVal, oldVal) {
            this.setChatButtons();
            if (newVal === 'icons') {
                  this.chat_config.template = 'template1';
              }
          },
          deep: true
      },
      'chat_config.channels': {
          handler(newVal, oldVal) {
              if (newVal !== oldVal) {
                this.setBubbleFields();
              }
          },
          deep: true
      },
      'templateConfig': {
        handler(newVal, oldVal) {
          this.setBubbleFields();
          this.setChatButtons();
        },
        deep: true
      },
      'templateConfig.chat_bubble.cb_custom_icon': {
        handler(newVal, oldVal) {
          if (newVal !== oldVal && newVal !== '') {
            this.chat_config[this.chat_config.template].chat_bubble.cb_button_icon = 'custom';
          }
        },
        deep: true
      },
  },

  computed: {
      templateConfig() {
          if (!this.chat_config || !this.chat_config.template) {
              return null;
          }
          return this.chat_config[this.chat_config.template] || null;
      }
  },

  mounted() {
      this.checkFullScreen();
      if (this.chat_config) {
          this.setBubbleFields();
          this.setChatButtons();
      }
  }
}
</script>