<template>
    <span>
        <div :style="style" >
           <template v-for="(options, optionsKey) in elements" :key="optionsKey">
            <el-collapse-item
             :name="optionsKey"
             v-if="feed_config.styles && elements && show_section(optionsKey, options)"
           >
               <template #title="{ isActive }">
                  <EditorCollapsiblePanel :isActive="isActive" :iconKey="options.title.toLowerCase().replace(/ /g, '_')" :label="options.title"/>
               </template>
               <div class="wpsr-styling-components-wrapper">
                  <UpgradeToProBanner />
                  <div v-for="(option, index) in options" :key="index" >
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
                      <div v-if="option.divider" class="wpsr-divider"></div>
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
import SliderElement from "../../../core-ui/editor/SliderElement";
import {helperStyle} from "../../../../mixins/helperStyle";
import {StyleEditorMixin} from "../../../../mixins/StyleEditorMixin";
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
      temp_style_channel_name: {},
      temp_style_statistics: {},
      temp_style_header_description: {},
      temp_style_subscribe_button: {},
      temp_style_video_title: {},
      temp_style_video_statistics: {},
      temp_style_video_description: {},
      temp_style_youtube_pagination: {},
      temp_style_channel_name_responsive: {},
      temp_style_statistics_responsive: {},
      temp_style_header_description_responsive: {},
      temp_style_subscribe_button_responsive: {},
      temp_style_video_title_responsive: {},
      temp_style_video_statistics_responsive: {},
      temp_style_video_description_responsive: {},
      temp_style_youtube_pagination_responsive: {},
    }
  },

  methods: {
    getResetSettings(){
      if (!this.feed_config || !this.feed_config.styles) {
        return;
      }

      let identifierKey = [
        'channel_name',
        'statistics',
        'header_description',
        'subscribe_button',
        'video_title',
        'video_statistics',
        'video_description',
        'youtube_pagination',
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
        this.feed_config.styles.channel_name,
        this.feed_config.styles.channel_name_wrapper,
        this.feed_config.styles.statistics,
        this.feed_config.styles.header_description,
        this.feed_config.styles.subscribe_button,
        this.feed_config.styles.header_box,
        this.feed_config.styles.video_title,
        this.feed_config.styles.video_statistics,
        this.feed_config.styles.video_description,
        this.feed_config.styles.youtube_pagination,
        this.feed_config.styles.item_box,
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