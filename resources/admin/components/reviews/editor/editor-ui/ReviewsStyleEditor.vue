<template>
    <div :style="style" >
        <template v-for="(options, index) in elements" :key="index">
          <el-collapse-item :name="index" v-if="feed_config.styles && show_section(index, options)">
            <template #title="{ isActive }">
                <span class="wpsr-editor-title-row">
                  <component :is="getSectionIcon(index)" class="wpsr-editor-icon" />
                  <span class="wpsr-editor-title-text wpsr-heading-text">{{options.title}}</span>
                </span>
                <span class="wpsr-custom-collapse-arrow">
                  <PlusIcon v-if="!isActive" />
                  <MinusIcon v-else/>
                </span>
            </template>
            <div class="wpsr-styling-components-wrapper">
                <UpgradeToProBanner />
                <div v-for="(option, insideIndex) in options" :key="insideIndex">
                  <!-- Add a conditional wrapper div to prevent empty divs -->
                  <div v-if="shouldRenderOption(option, insideIndex)" class="wpsr-styling-components" :class="!has_pro ? 'wpsr-style-upgrade':''">
                    <h3 v-if="option.title" class="wpsr-style-title wpsr-pb-0">{{ option.title }}</h3>
                        <span class="wpsr-style-typography-wrapper">
                          <FeedEditorGroup
                              v-if="option.key && feed_config.styles[option.key] && option.styles && Array.isArray(option.styles)"
                              :fieldsMaps="option.styles || []"
                              :proGroup="true"
                              :modelValue="feed_config.styles[option.key].color"
                              @update:modelValue="feed_config.styles[option.key].color = $event"
                          />
                          <TypographyElementWrapper
                              v-if="option.typography && feed_config.styles[option.key] && feed_config.styles[option.key].typography"
                              :typography="feed_config.styles[option.key].typography"
                              :identifier="option.key"
                              @resetMe="resetMe"
                              @update:typography="feed_config.styles[option.key].typography = $event"
                          />
                        </span>
                      <SpacingElement
                          v-if="option.spacing && feed_config.styles[option.key]"
                          :spacing="feed_config.styles[option.key].spacing"
                          :label="option.spacing.title"
                          @update:spacing="feed_config.styles[option.key].spacing = $event"
                      />
                        <BorderElement
                            v-if="option.border && feed_config.styles[option.key]"
                            :border="feed_config.styles[option.key].border"
                            @update:border="feed_config.styles[option.key].border = $event"
                        />
                        <SliderElement
                            v-if="option.slider && feed_config.styles[option.key]"
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
</template>

<script type="text/babel">
import FeedEditorGroup from './../../../core-ui/editor/EditorGroup';
import SpacingElement from './../../../core-ui/editor/SpacingElement';
import TypographyElementWrapper from "../../../core-ui/editor/TypographyElementWrapper";
import BorderElement from "../../../core-ui/editor/BorderElement";
import {helperStyle} from "../../../../mixins/helperStyle";
import SliderElement from "../../../core-ui/editor/SliderElement";
import PartyIcon from '../../../pieces/icons/PartyIcon';
import HeaderIcon from '../../../pieces/icons/HeaderIcon';
import ReviewerNameIcom from '../../../pieces/icons/ReviewerNameIcon';
import AuthorPositionIcon from '../../../pieces/icons/AuthorPositionIcon';
import DateIcon from '../../../pieces/icons/DateIcon';
import FilterIcon from '../../../pieces/icons/FilterIcon';
import SettingsLineIcon from '../../../pieces/icons/SettingsLineIcon';
import PaginationIcon from '../../../pieces/icons/PaginationIcon';
import BadgeIcon from '../../../pieces/icons/BadgeIcon';
import NotificationIcon from '../../../pieces/icons/NotificationIcon';
import ReviewBoxIcon from '../../../pieces/icons/ReviewBoxIcon';
import TitleIcon from '../../../pieces/icons/TitleIcon';
import ContentIcon from '../../../pieces/icons/ContentIcon'
import PlatformIcon from '../../../pieces/icons/PlatformsIcon'
import ReadMoreLessIcon from '../../../pieces/icons/ReadMoreLessIcon'
import PlusIcon from '../../../pieces/icons/PlusIcon';
import MinusIcon from '../../../pieces/icons/MinusIcon';
import UpgradeToProBanner from '../../../views/advertise/UpgradeToProBanner.vue';

export default {
  props:{
    feed_config: {
      type: Object,
      required: true
    },
    elements: {
      type: [Array, Object],
      required: true,
      default: () => []
    },
    template_meta: {
      type: Object,
      required: true
    }
  },
  mixins: [helperStyle],
  components: {
    SliderElement,
    TypographyElementWrapper,
    FeedEditorGroup,
    SpacingElement,
    BorderElement,
    PartyIcon,
    HeaderIcon,
    AuthorPositionIcon,
    FilterIcon,
    SettingsLineIcon,
    PaginationIcon,
    BadgeIcon,
    NotificationIcon,
    ReviewerNameIcom,
    ReviewBoxIcon,
    DateIcon,
    TitleIcon,
    ContentIcon,
    PlatformIcon,
    ReadMoreLessIcon,
    PlusIcon,
    MinusIcon,
    UpgradeToProBanner
  },
  data() {
    return {
      // define object's are for resetHandler
      temp_style_star_rating: {},
      temp_style_filled_star_rating: {},
      temp_style_empty_star_rating: {},
      temp_style_rating_title: {},
      temp_style_rating_number: {},
      temp_style_rating_text: {},
      temp_style_review_button: {},
      temp_style_header_progress_bar_fill: {},
      temp_style_header_progress_bar_background: {},
      temp_style_header_review_count: {},
      temp_style_business_info_wrapper: {},
      temp_style_reviewer: {},
      temp_style_author_position: {},
      temp_style_review_title: {},
      temp_style_content: {},
      temp_style_review_date: {},
      temp_style_review_platform: {},
      temp_style_read_more_less: {},
      temp_style_platform: {},
      temp_style_badge_title: {},
      temp_style_badge_rating_number: {},
      temp_style_badge_total_reviews: {},
      temp_style_notification_reviewer_name: {},
      temp_style_notification_title: {},
      temp_style_notification_review_time: {},
      temp_style_pagination: {},
      temp_style_review_box: {},
      temp_style_star_rating_responsive: {},
      temp_style_filled_star_rating_responsive: {},
      temp_style_empty_star_rating_responsive: {},
      temp_style_rating_title_responsive: {},
      temp_style_rating_number_responsive: {},
      temp_style_rating_text_responsive: {},
      temp_style_review_button_responsive: {},
      temp_style_header_progress_bar_fill_responsive: {},
      temp_style_header_progress_bar_background_responsive: {},
      temp_style_header_review_count_responsive: {},
      temp_style_reviewer_responsive: {},
      temp_style_author_position_responsive: {},
      temp_style_review_title_responsive: {},
      temp_style_content_responsive: {},
      temp_style_review_date_responsive: {},
      temp_style_review_platform_responsive: {},
      temp_style_badge_title_responsive: {},
      temp_style_badge_rating_number_responsive: {},
      temp_style_badge_total_reviews_responsive: {},
      temp_style_notification_reviewer_name_responsive: {},
      temp_style_notification_title_responsive: {},
      temp_style_notification_review_time_responsive: {},
      temp_style_read_more_less_responsive: {},
      temp_style_platform_responsive: {},
      temp_style_pagination_responsive: {},
      temp_style_verified_badge_star: {},
      temp_style_verified_badge_checkmark: {},
      temp_style_verified_badge_star_responsive: {},
      temp_style_verified_badge_checkmark_responsive: {}
    }
  },

  methods: {
    show_section(section_name, options) {
      // Check if options is defined and has condition property
      if (!options || !options.condition) {
        return true;
      }

      if (options.condition.operator === 'multiple') {
        if (!options.condition.terms || !Array.isArray(options.condition.terms)) {
          return true;
        }

        let terms = options.condition.terms, ok = 1;
        terms.forEach((term) => {
          if (!term || !term.key || !this.template_meta[term.key]) {
            ok = 0;
            return;
          }

          if (term.operator === 'includes') {
            ok &= (this.template_meta[term.key].includes(term.selector));
          } else {
            ok &= (this.template_meta[term.key] === term.selector);
          }
        });
        return ok === 1;
      } else {
        if (!options.condition.key || !this.template_meta[options.condition.key]) {
          return true;
        }
        return (this.template_meta[options.condition.key] === options.condition.selector);
      }
    },
    getResetSettings(){
      let identifierKey = [
        'star_rating',
        'filled_star_rating',
        'empty_star_rating',
        'rating_title',
        'rating_number',
        'rating_text',
        'review_button',
        'header_progress_bar_fill',
        'header_progress_bar_background',
        'header_review_count',
        'reviewer',
        'author_position',
        'content',
        'review_date',
        'read_more_less',
        'platform',
        'badge_title',
        'badge_rating_number',
        'badge_total_reviews',
        'notification_reviewer_name',
        'notification_title',
        'notification_review_time',
        'pagination',
        'review_title',
        'verified_badge_star',
        'verified_badge_checkmark'
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
    getSectionIcon(section) {
      const iconMap = {
        'header': 'HeaderIcon',
        'name': 'ReviewerNameIcom',
        'position': 'AuthorPositionIcon',
        'review_date': 'DateIcon',
        'review_title': 'TitleIcon',
        'content': 'ContentIcon',
        'read_more_less': 'ReadMoreLessIcon',
        'platform': 'PlatformIcon',
        'badge': 'BadgeIcon',
        'notification': 'NotificationIcon',
        'pagination': 'PaginationIcon',
        'review_box': 'ReviewBoxIcon',
      };

      if (iconMap[section]) return iconMap[section];
      // Fallback
      return 'FilterIcon';
    },
    shouldRenderOption(option, insideIndex) {
      // Add your custom logic here to determine whether to render the option or not
      // For example, you can check if the option has any styles defined
      if (option.key && this.feed_config.styles[option.key]) {
        return true;
      }

      // You can also check for specific conditions based on your requirements
      // For example, only render the first option as a placeholder
      if (insideIndex === 0) {
        return true;
      }

      return false; // Don't render the option by default
    }
  },

  computed: {
    style() {
      let style_container = [
        this.feed_config.styles.star_rating,
        this.feed_config.styles.filled_star_rating,
        this.feed_config.styles.empty_star_rating,
        this.feed_config.styles.rating_title,
        this.feed_config.styles.rating_number,
        this.feed_config.styles.rating_text,
        this.feed_config.styles.review_button,
        this.feed_config.styles.header_progress_bar_fill,
        this.feed_config.styles.header_progress_bar_background,
        this.feed_config.styles.header_review_count,
        this.feed_config.styles.business_info_wrapper,
        this.feed_config.styles.reviewer,
        this.feed_config.styles.author_position,
        this.feed_config.styles.review_title,
        this.feed_config.styles.reviewer_name_wrapper,
        this.feed_config.styles.platform,
        this.feed_config.styles.read_more_less,
        this.feed_config.styles.content,
        this.feed_config.styles.review_date,
        this.feed_config.styles.review_box,
        this.feed_config.styles.badge_title,
        this.feed_config.styles.badge_rating_number,
        this.feed_config.styles.badge_total_reviews,
        this.feed_config.styles.badge_wrapper_box,
        this.feed_config.styles.notification_close_icon,
        this.feed_config.styles.notification_reviewer_name,
        this.feed_config.styles.notification_title,
        this.feed_config.styles.notification_review_time,
        this.feed_config.styles.notification_wrapper_box,
        this.feed_config.styles.pagination,
        this.feed_config.styles.verified_badge_star,
        this.feed_config.styles.verified_badge_checkmark,

      ]

      this.handleStyles(style_container);
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
    }
  }
}
</script>