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
    },
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
      temp_style_likes: {},
      temp_style_author: {},
      temp_style_post_date: {},
      temp_style_event_date: {},
      temp_style_post_title: {},
      temp_style_album_meta: {},
      temp_style_album_breadcrumb: {},
      temp_style_event_title: {},
      temp_style_event_location: {},
      temp_style_event_statistics: {},
      temp_style_post_content: {},
      temp_style_summary_card: {},
      temp_style_summary_card_title: {},
      temp_style_summary_card_description: {},
      temp_style_like_and_share: {},
      temp_style_fb_pagination: {},
      temp_style_user_name_responsive: {},
      temp_style_description_responsive: {},
      temp_style_likes_responsive: {},
      temp_style_author_responsive: {},
      temp_style_post_date_responsive: {},
      temp_style_event_date_responsive: {},
      temp_style_post_title_responsive: {},
      temp_style_album_meta_responsive: {},
      temp_style_album_breadcrumb_responsive: {},
      temp_style_event_title_responsive: {},
      temp_style_event_location_responsive: {},
      temp_style_event_statistics_responsive: {},
      temp_style_post_content_responsive: {},
      temp_style_summary_card_responsive: {},
      temp_style_summary_card_title_responsive: {},
      temp_style_summary_card_description_responsive: {},
      temp_style_like_and_share_responsive: {},
      temp_style_fb_pagination_responsive: {},
    }
  },

  methods: {
    getResetSettings(){
      if (!this.feed_config || !this.feed_config.styles) {
        return;
      }

      let identifierKey = [
        'user_name',
        'description',
        'likes',
        'author',
        'post_date',
        'event_date',
        'post_title',
        'event_title',
        'event_location',
        'event_statistics',
        'album_meta',
        'album_breadcrumb',
        'summary_card',
        'summary_card_title',
        'summary_card_description',
        'post_content',
        'like_and_share',
        'fb_pagination',
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
        this.feed_config.styles.description,
        this.feed_config.styles.likes,
        this.feed_config.styles.header_box,
        this.feed_config.styles.author,
        this.feed_config.styles.post_date,
        this.feed_config.styles.event_date,
        this.feed_config.styles.post_title,
        this.feed_config.styles.album_meta,
        this.feed_config.styles.album_breadcrumb,
        this.feed_config.styles.event_title,
        this.feed_config.styles.event_location,
        this.feed_config.styles.event_statistics,
        this.feed_config.styles.summary_card,
        this.feed_config.styles.summary_card_title,
        this.feed_config.styles.summary_card_description,
        this.feed_config.styles.post_content,
        this.feed_config.styles.link_color,
        this.feed_config.styles.read_more_link_color,
        this.feed_config.styles.like_and_share,
        this.feed_config.styles.fb_pagination,
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