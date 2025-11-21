<template>
    <span>
        <div :style="style" >
          <template v-for="(options, key) in elements">
            <el-collapse-item
                :name="key"
                v-if="feed_config.styles && elements && show_section(key, options)"
                :key="key">
                <template #title>
                    <EditorCollapsiblePanel :isActive="isActive" :iconKey="options.title.toLowerCase().replace(/ /g, '_')" :label="options.title"/>
                </template>
                <div class="wpsr-styling-components-wrapper">
                  <UpgradeToProBanner />
                  <div v-for="(option, index) in options" :key="index" >
                      <h3 v-if="show_section(key, option)" class="wpsr-style-title">{{ option.title }}</h3>
                      <div class="wpsr-styling-components" v-if="option.styles && option.styles.length && hasVisibleElements(option, key)" :class="!has_pro ? 'wpsr-style-upgrade':''">
                        <div class="wpsr-style-typography-wrapper">
                          <FeedEditorGroup
                              v-if="feed_config.styles[option.key] && feed_config.styles[option.key].color && show_section(key, option)"
                              :fieldsMaps="option.styles"
                              :proGroup="true"
                              :modelValue="feed_config.styles[option.key].color"
                              @update:modelValue="feed_config.styles[option.key].color = $event"
                          />
                          <TypographyElementWrapper
                              v-if="option.typography && feed_config.styles[option.key] && feed_config.styles[option.key].typography && show_section(key, option)"
                              :typography="feed_config.styles[option.key].typography"
                              :identifier="option.key"
                              @resetMe="resetMe"
                              @update:typography="feed_config.styles[option.key].typography = $event"
                          />
                        </div>
                        <SpacingElement
                            v-if="option.padding && feed_config.styles[option.key] && show_section(key, option)"
                            :label="'Padding'"
                            :spacing="feed_config.styles[option.key].padding"
                            @update:spacing="feed_config.styles[option.key].spacing = $event"
                        />
                        <BorderElement
                            :label="'Border'"
                            v-if="option.border && feed_config.styles[option.key] && show_section(key, option)"
                            :border="feed_config.styles[option.key].border"
                            @update:border="feed_config.styles[option.key].border = $event"
                        />
                        <SliderElement
                            v-if="option.slider && feed_config.styles[option.key] && show_section(key, option)"
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
import SliderElement from "../../../core-ui/editor/SliderElement";
import {helperStyle} from "../../../../mixins/helperStyle";
import {StyleEditorMixin} from "../../../../mixins/StyleEditorMixin";
import EditorCollapsiblePanel from '../../../core-ui/editor/EditorCollapsiblePanel.vue';
import UpgradeToProBanner from '../../../views/advertise/UpgradeToProBanner.vue';
export default {
  props: {
    feed_config: {
      type: Object,
      required: true
    },
    elements: {
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
      temp_style_description: {},
      temp_style_statistics: {},
      temp_style_author: {},
      temp_style_post_date: {},
      temp_style_post_content: {},
      temp_style_statistics_count: {},
      temp_style_follow_button: {},
      temp_style_tiktok_pagination: {},
      temp_style_user_name_responsive: {},
      temp_style_description_responsive: {},
      temp_style_statistics_responsive: {},
      temp_style_author_responsive: {},
      temp_style_post_date_responsive: {},
      temp_style_post_content_responsive: {},
      temp_style_statistics_count_responsive: {},
      temp_style_follow_button_responsive: {},
      temp_style_temp_style_tiktok_pagination_responsive: {},
    }
  },
  methods: {
    getResetSettings(){
      let identifierKey = [
        'user_name',
        'description',
        'statistics',
        'author',
        'post_date',
        'post_content',
        'statistics_count',
        'follow_button',
        'tiktok_pagination',
      ]

      identifierKey.forEach(identifier => {
        this.resetVariableHandler(this.feed_config, identifier);
      })
    },

    resetMe (val) {
      let styleVal = 'temp_style_'+val;
      let responsiveVal = 'temp_style_'+val+'_responsive';
      if (val){
        this.resetHandler(val , this[styleVal], this[responsiveVal]);
      }
    },
  },
  computed: {
    style() {
      let style_container = [
        this.feed_config.styles.user_name,
        this.feed_config.styles.description,
        this.feed_config.styles.statistics,
        this.feed_config.styles.header_box,
        this.feed_config.styles.author,
        this.feed_config.styles.post_date,
        this.feed_config.styles.post_content,
        this.feed_config.styles.statistics_count,
        this.feed_config.styles.read_more_link_color,
        this.feed_config.styles.follow_button,
        this.feed_config.styles.tiktok_pagination,
        this.feed_config.styles.item_box,
      ].filter(Boolean);
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