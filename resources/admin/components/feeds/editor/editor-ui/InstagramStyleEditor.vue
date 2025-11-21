<template>
    <span>
        <div :style="style" >
          <template v-for="(options, optionsKey) in elements" :key="optionsKey">
              <el-collapse-item
                  :name="optionsKey"
                  v-if="feed_config.styles && elements && show_section(optionsKey, options)"
                >

                <template #title="{ isActive}">
                  <EditorCollapsiblePanel :isActive="isActive" :iconKey="options.title.toLowerCase().replace(/ /g, '_')" :label="options.title"/>
                </template>

                <div class="wpsr-styling-components-wrapper">
                    <UpgradeToProBanner />
                    <div v-for="(option, index) in options" :key="index">
                      <h3 v-if="show_section(optionsKey, option) && option.title" class="wpsr-style-title">{{ option.title }}</h3>
                        <div v-if="option.styles && option.styles.length && hasVisibleElements(option, optionsKey)" class="wpsr-styling-components" :class="!has_pro ? 'wpsr-style-upgrade':''">
                          <span class="wpsr-style-typography-wrapper">
                            <FeedEditorGroup
                                v-if="feed_config.styles[option.key] && feed_config.styles[option.key].color && show_section(optionsKey, option)"
                                :fieldsMaps="option.styles"
                                :proGroup="true"
                                :modelValue="feed_config.styles[option.key].color"
                                @update:modelValue="feed_config.styles[option.key].color = $event"
                            />
                            <TypographyElementWrapper
                                v-if="option.typography && feed_config.styles[option.key] && feed_config.styles[option.key].typography && show_section(optionsKey, option)"
                                :typography="feed_config.styles[option.key].typography"
                                :identifier="option.key"
                                @resetMe="resetMe"
                                @update:typography="feed_config.styles[option.key].typography = $event"
                            />
                          </span>
                          <SpacingElement
                              v-if="option.padding && feed_config.styles[option.key] && show_section(optionsKey, option)"
                              :label="'Padding'"
                              :spacing="feed_config.styles[option.key].padding"
                              @update:spacing="feed_config.styles[option.key].spacing = $event"
                          />
                          <BorderElement
                              :label="'Border'"
                              v-if="option.border && feed_config.styles[option.key] && show_section(optionsKey, option)"
                              :border="feed_config.styles[option.key].border"
                              @update:border="feed_config.styles[option.key].border = $event"
                          />
                          <SliderElement
                              v-if="option.slider && feed_config.styles[option.key] && show_section(optionsKey, option)"
                              :spacing="feed_config.styles[option.key].slider"
                              :title="option.slider.title"
                              :slider="option.slider"
                              :max="200"
                              :min="0"
                              @update:spacing="feed_config.styles[option.key].slider = $event"
                          />
                          <div v-if="option.divider" class="wpsr-divider"/>
                      </div>
                    </div>
                </div>
             </el-collapse-item>
          </template>
          </div>
      </span>
</template>

<script>
import FeedEditorGroup from './../../../core-ui/editor/EditorGroup';
import SpacingElement from './../../../core-ui/editor/SpacingElement';
import TypographyElementWrapper from "../../../core-ui/editor/TypographyElementWrapper";
import BorderElement from "../../../core-ui/editor/BorderElement";
import {helperStyle} from "../../../../mixins/helperStyle";
import {StyleEditorMixin} from "../../../../mixins/StyleEditorMixin";
import SliderElement from "../../../core-ui/editor/SliderElement";
import EditorCollapsiblePanel from '../../../core-ui/editor/EditorCollapsiblePanel.vue';
import UpgradeToProBanner from '../../../views/advertise/UpgradeToProBanner.vue';
export default {
  props: {
    feed_config: {
      type: [Array, Object], // Accept both,
      required: true
    },
    elements: {
      type: Object,
      default: () => ({})
    },
    feed_settings: {
      type: Object,
      default: () => ({})
    }
  },
  mixins: [helperStyle, StyleEditorMixin],
  components: {
    TypographyElementWrapper,
    FeedEditorGroup,
    SpacingElement,
    BorderElement,
    SliderElement,
    EditorCollapsiblePanel,
    UpgradeToProBanner
  },
  data() {
    return {
      // define object's are for resetHandler
      temp_style_user_name: {},
      temp_style_account_statistics: {},
      temp_style_full_name: {},
      temp_style_account_description: {},
      temp_style_follow_button: {},
      temp_style_content: {},
      temp_style_statistics: {},
      temp_style_popup_user_info_name: {},
      temp_style_popup_post_description: {},
      temp_style_popup_post_hashtag: {},
      temp_style_popup_post_time: {},
      temp_style_popup_call_to_action: {},
      temp_style_pagination: {},
      temp_style_user_name_responsive: {},
      temp_style_account_statistics_responsive: {},
      temp_style_full_name_responsive: {},
      temp_style_account_description_responsive: {},
      temp_style_follow_button_responsive: {},
      temp_style_content_responsive: {},
      temp_style_statistics_responsive: {},
      temp_style_popup_user_info_name_responsive: {},
      temp_style_popup_post_description_responsive: {},
      temp_style_popup_post_hashtag_responsive: {},
      temp_style_popup_post_time_responsive: {},
      temp_style_popup_call_to_action_responsive: {},
      temp_style_pagination_responsive: {}
    }
  },

  methods: {
    getResetSettings(){
      if (!this.feed_config || !this.feed_config.styles) {
        return;
      }

      let identifierKey = [
        'user_name',
        'account_statistics',
        'full_name',
        'account_description',
        'follow_button',
        'content',
        'statistics',
        'popup_user_info_name',
        'popup_post_description',
        'popup_post_hashtag',
        'popup_post_time',
        'popup_call_to_action',
        'pagination',
      ]

      identifierKey.forEach(identifier => {
        if (this.feed_config.styles[identifier]) {
          this.resetVariableHandler(this.feed_config, identifier);
        }
      })
    },

    resetMe (val) {
      let styleVal = 'temp_style_'+val;
      let responsiveVal = 'temp_style_'+val+'_responsive';
      if (val && this[styleVal] && this[responsiveVal]){
        this.resetHandler(val, this[styleVal], this[responsiveVal]);
      }
    },
  },

  computed: {
    style() {
      if (!this.feed_config || !this.feed_config.styles) {
        return {};
      }

      let style_container = [
        this.feed_config.styles.user_name,
        this.feed_config.styles.account_statistics_number,
        this.feed_config.styles.account_statistics,
        this.feed_config.styles.full_name,
        this.feed_config.styles.account_description,
        this.feed_config.styles.follow_button,
        this.feed_config.styles.account_info_wrapper,
        this.feed_config.styles.content,
        this.feed_config.styles.hashtag,
        this.feed_config.styles.statistics,
        this.feed_config.styles.statistics_icon,
        this.feed_config.styles.statistics_wrapper,
        this.feed_config.styles.popup_user_info_name,
        this.feed_config.styles.popup_post_description,
        this.feed_config.styles.popup_post_hashtag,
        this.feed_config.styles.popup_post_time,
        this.feed_config.styles.popup_call_to_action,
        this.feed_config.styles.pagination,
        this.feed_config.styles.content_box,
      ].filter(Boolean); // Filter out any undefined values

      this.handleStyles(style_container);
      return {};
    },
  },
  mounted(){
    this.getResetSettings();
  },

  watch: {
    feed_config: {
      handler(){
        this.getResetSettings();
      },
      deep: true
    }
  }
}
</script>