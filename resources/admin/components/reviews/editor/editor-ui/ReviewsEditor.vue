<template>
    <el-collapse-item name="1" v-if="template_meta.platform && !template_meta.platform.includes('testimonial')">
      <template #title="{ isActive}">
        <span class="wpsr-editor-title-row">
          <PlatformIcon class="wpsr-editor-icon" />
          <span class="wpsr-editor-title-text wpsr-heading-text">{{ $t('Platforms') }}</span>
        </span>
        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>
      <div 
        class="wpsn-platforms-list" 
        v-loading="platformSelectorLoading" 
        element-loading-text=""
      >
        <div class="wpsn-platforms-wrapper">
          <p class="wpsn-platform-header-title">{{ $t('Select Platforms') }}</p>
          <div class="wpsn-platform-card-wrapper">
            <div
              v-for="item in platforms"
              :key="item.value"
              class="wpsn-platform-card"
              :class="{ 'wpsn-platform-card--selected': template_meta.platform.includes(item.value) }"
              @click="togglePlatform(item.value)"
            >
              <div class="wpsn-platform-label-row">
                <img :src="getPlatformIcon(item.value)" class="wpsn-platform-icon" :alt="item.label" />
                <span class="wpsr-platform-label">
                  {{ item.value === 'google' ? $t('Google Business Profile') : item.label }}
                </span>
                <span
                  class="wpsn-platform-checkbox"
                  :class="{
                    'checked': item.connected && template_meta.platform.includes(item.value)
                  }"
                  style="cursor: pointer;"
                >
                  <InputCheckIcon v-if="template_meta.platform && template_meta.platform.includes(item.value)" />
                  <InputUncheckIcon v-else />
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </el-collapse-item>

    <el-collapse-item name="2" v-if="template_meta.templateType !== 'notification' || (template_meta.templateType === 'notification' && template_meta.notification_settings.display_mode === 'popup')">
      <template #title="{ isActive}">
        <span class="wpsr-editor-title-row">
          <TemplateIcon class="wpsr-editor-icon" />
          <span class="wpsr-editor-title-text wpsr-heading-text">{{ $t('Template') }}</span>
        </span>
        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>

      <div class="wpsr_editor_layout">
        <div class="wpsr_editor_edit_item-group">
          <div class="wpsr-editor-edit-item-field" v-if="template_meta.templateType !== 'notification'">
            <transition name="fade">
              <ImageSelect v-model="template_meta.templateType" :options="templateTypes" :headerTitle="'Layout Type'"/>
            </transition>
          </div>
          <div v-if="template_meta.templateType === 'badge'" class="wpsr-editor-edit-item-field">
            <transition name="fade">
              <ImageSelect v-if="badge_skins" :showSeeMoreButton="false" :loading="badgeSkinLoading" v-model="template_meta.badge_settings.template" :options="badge_skins"/>
            </transition>
          </div>
          <div v-if="template_meta.templateType !== 'badge'" class="wpsr-editor-edit-item-field">
            <transition name="fade">
              <ImageSelect v-if="available_grid_skins.testimonial_skins && available_grid_skins.reviews_skins"  v-model="template_meta.template" :options="template_meta.platform && template_meta.platform.includes('testimonial') ? available_grid_skins.testimonial_skins : available_grid_skins.reviews_skins"/>
            </transition>
          </div>
          <div class="wpsr-editor-edit-item-field" v-if="template_meta.templateType !== 'slider'">
            <div class="wpsr-filters-row">
              <span class="wpsr-editor-sidebar-section-title wpsr-label-small">Layout Styling</span>
            </div>
          </div>
          <EditorGroup
              :fieldsMaps="layout_settings"
              :modelValue="template_meta"
              @input="onLayoutSettingsInput"
          />
        </div>
      </div>

    </el-collapse-item>

    <el-collapse-item name="3" v-if="template_meta.templateType === 'badge'">
      <template #title="{ isActive }">
        <span class="wpsr-editor-title-row">
          <BadgeIcon class="wpsr-editor-icon" />
          <span class="wpsr-element-label wpsr-editor-title-text wpsr-heading-text">{{ $t('Badge') }} <ProCrownIcon v-if="!has_pro" /></span>
        </span>

        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>
      <EditorGroup
          :fieldsMaps="badge_settings"
          :modelValue="template_meta.badge_settings"
          @input="(val) => {
              badgeSkinLoading = true;
              template_meta.badge_settings = val;
              $emit('fetchReviews');
          }"
      />
    </el-collapse-item>

    <el-collapse-item name="4" v-if="template_meta.templateType === 'notification'">
      <template #title="{ isActive }">
        <span class="wpsr-editor-title-row">
          <NotificationIcon class="wpsr-editor-icon" />
          <span class="wpsr-editor-title-text wpsr-heading-text">{{ $t('Notification') }}</span>
        </span>
        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>
      <notification-settings :pages="pages" :post_types="post_types" :template_meta="template_meta" @on-change="$emit('fetchReviews')"></notification-settings>
    </el-collapse-item>

    <el-collapse-item name="5">
      <template #title="{ isActive }">
        <span class="wpsr-editor-title-row">
          <FilterIcon class="wpsr-editor-icon" />
          <span class="wpsr-editor-title-text wpsr-heading-text">{{ $t('Filters') }}</span>
        </span>
        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>
      <div class="wpsr-filters-card">
        <div class="wpsr_editor_edit_item-group wpsr_editor_inside_gap_16">
          <div class="wpsr-filters-row">
            <div class="wpsr-editor-inside-left">
              <span class="wpsr-tooltip-position wpsr-input-field-label">
                Number of Reviews
                <el-tooltip
                  :raw-content="true"
                  effect="dark"
                  placement="top-start"
                  :content="`The responsive number of reviews will only work on your site’s preview/live pages/posts.`"
                >
                  <el-icon ><InfoFilled /></el-icon>
                </el-tooltip>
              </span>
              <ResponsiveBar
                  type="dropdown"
                  :field="{responsive: true, hide_tablet: true}"
                  :activeDevice="activeDevice"
                  @handleDevice="handleDevice"
              />
            </div>
            <div class="wpsr-reviews-number wpsr-editor-inside-right">
              <el-input-number
                v-model="template_meta.totalReviewsNumber[device]"
                :min="1"
                :max="1000"
                controls-position="right"
                size="large"
                @change="handleChange"
                class="wpsr-text-input"
              />
              <div class="wpsr-group-input">
                <el-button class="wpsr-editor-minus-btn" size="small" @click="template_meta.totalReviewsNumber[device] = Math.max(1, (template_meta.totalReviewsNumber[device] || 1) - 1)">-</el-button>
                <el-button class="wpsr-editor-plus-btn" size="small" @click="template_meta.totalReviewsNumber[device] = Math.min(200, (template_meta.totalReviewsNumber[device] || 1) + 1)">+</el-button>
              </div>
            </div>
          </div>
          <div class="wpsr-filters-row">
            <span class="wpsr-editor-inside-left wpsr-element-label wpsr-input-field-label">
              <span>{{$t('Filters by Minimum Rating')}} <ProCrownIcon v-if="!has_pro" /></span>
            </span>
            <div class="wpsr-editor-inside-right">
              <el-select v-model="template_meta.starFilterVal" placeholder="Select" size="small" class="wpsr-text-input">
                <el-option v-for="item in extendStarFilters()" :key="item.value" :label="item.label" :value="item.value" :disabled="item.pro" />
              </el-select>
            </div>
          </div>
          <div class="wpsr-filters-row">
            <span class="wpsr-editor-inside-left wpsr-input-field-label">Order</span>
            <div class="wpsr-editor-inside-right">
              <el-select v-model="template_meta.order" @change="throttledFetchReviews" placeholder="Select" size="small" class="wpsr-text-input">
                <el-option v-for="item in orders" :key="item.value" :label="item.label" :value="item.value" />
              </el-select>
            </div>
          </div>
          <div class="wpsr-filters-row">
            <span class="wpsr-editor-inside-left wpsr-element-label wpsr-input-field-label">
              <span>Hide Reviews Without Text <ProCrownIcon v-if="!has_pro" /></span>
            </span>
            <div class="wpsr-editor-inside-right">
              <el-switch v-model="template_meta.hide_empty_reviews" @change="throttledFetchReviews" active-color="#4f8cff" inactive-color="#e0e0e0" :disabled="!has_pro"/>
            </div>
          </div>
          <div class="wpsr-filters-row">
            <span class="wpsr-editor-inside-left wpsr-element-label wpsr-input-field-label">

              <span>Filter by Include/Exclude <ProCrownIcon v-if="!has_pro" /></span>
            </span>
            <div class="wpsr-editor-inside-right">
              <el-select v-model="template_meta.filterByTitle" placeholder="All" size="small" class="wpsr-text-input">
                <el-option v-for="item in title_options" :key="item.value" :label="item.label" :value="item.value" :disabled="item.pro"/>
              </el-select>
            </div>
          </div>
          <div class="wpsr-settings-select" v-if="template_meta.filterByTitle === 'include'">
            <span class="wpsr-editor-inside-left wpsr-input-field-label">{{$t(`Include(Hand-pick ${options[type].type_name}s by Name)`)}}</span>
            <div class="wpsr-editor-inside-right">
              <transition name="fade">
                <el-select class="wpsr-text-input" v-model="template_meta.selectedIncList" :disabled="!this.has_pro" multiple filterable :placeholder="`Select ${options[type].type_name}s to include`" size="large" @change="throttledFetchReviews">
                  <el-option
                      v-for="item in all_reviews"
                      :key="item.id"
                      :label="$getFirstWord( $trimWords(item.review_title, 3, true), true) +' by '+item.reviewer_name"
                      :value="item.id"
                  >
                  </el-option>
                </el-select>
              </transition>
            </div>
          </div>
          <div class="wpsr-settings-select" v-if="template_meta.filterByTitle === 'exclude'">
            <span class="wpsr-editor-inside-left wpsr-input-field-label">{{$t(`Exclude ${options[type].type_name} by Name`)}}</span>
            <div class="wpsr-editor-inside-right">
              <transition name="fade">
                <el-select class="wpsr-text-input" v-model="template_meta.selectedExcList" :disabled="!this.has_pro" multiple filterable :placeholder="`Select ${options[type].type_name}s to exclude`" size="large" @change="throttledFetchReviews">
                  <el-option
                      v-for="item in all_reviews"
                      :key="item.id"
                      :label="$getFirstWord($trimWords(item.review_title, 3, true), true) +' by '+item.reviewer_name"
                      :value="item.id">
                  </el-option>
                </el-select>
              </transition>
            </div>
          </div>
          <div class="wpsr-filters-row">
            <div class="wpsr-element-label wpsr-editor-inside-left wpsr-align-top wpsr-input-field-label">
              <span>{{$t('Show reviews containing these words ')}}</span>
              <span class="wpsr-tooltip-icon">
                <ProCrownIcon v-if="!has_pro" />
                <el-tooltip
                  :raw-content="true"
                  effect="dark"
                  placement="top-start"
                  :content="`You can use this setting to show reviews that contain certain specific words in the reviews text. <br/> Separate multiple words using commas. ex: word1,word2`"
                >
                  <el-icon><InfoFilled /></el-icon>
                </el-tooltip>
              </span>
            </div>
            <div class="wpsr-editor-inside-right">
              <el-input
                class="wpsr-text-input wpsr-editor-text-input"
                :disabled="!has_pro"
                :rows="2"
                type="textarea"
                placeholder="Type something and press enter...."
                @keyup.enter="throttledFetchReviews"
                v-model="template_meta.includes_inputs">
              </el-input>
            </div>
          </div>
          <div class="wpsr-filters-row">
            <div class="wpsr-element-label wpsr-editor-inside-left wpsr-align-top wpsr-input-field-label">
              <span>{{$t('Hide reviews containing these words ')}}</span>
              <span class="wpsr-tooltip-icon">
                <ProCrownIcon v-if="!has_pro" />
                <el-tooltip
                  :raw-content="true"
                  effect="dark"
                  placement="top-start"
                  :content="`You can use this setting to hide reviews that contain certain specific words in the reviews text. <br/> Separate multiple words using commas. ex: word1,word2`"
                >
                  <el-icon><InfoFilled /></el-icon>
                </el-tooltip>
              </span>
            </div>
            <div class="wpsr-editor-inside-right">
              <el-input
                class="wpsr-text-input wpsr-editor-text-input"
                :disabled="!has_pro"
                :rows="2"
                type="textarea"
                placeholder="Type something and press enter...."
                @keyup.enter="throttledFetchReviews"
                v-model="template_meta.excludes_inputs">
              </el-input>
            </div>
          </div>
          <div class="wpsr-filters-row">
            <span class="wpsr-editor-inside-left wpsr-element-label wpsr-input-field-label">{{$t('Filter by Business/Product')}} <ProCrownIcon v-if="!has_pro" /></span>
            <div class="wpsr-editor-inside-right">
              <el-select v-model="template_meta.selectedBusinesses" multiple placeholder="Select business to include" size="small" class="wpsr-text-input">
                <el-option
                  v-for="(business, key) in business_info"
                  :disabled="!has_pro"
                  :key="key"
                  :label="(business.name ? $trimWords(business.name, 3, true) + (business.platform_name === 'airbnb' ? ' (' + key + ')' : '') : key) + ' (' + ucFirst(business.platform_name) + ')'"
                  :value="key"
                />
              </el-select>
            </div>
          </div>
          <div class="wpsr-filters-row">
            <span class="wpsr-editor-inside-left wpsr-element-label wpsr-input-field-label">{{$t('Filter by Category')}} <ProCrownIcon v-if="!has_pro" /></span>
            <div class="wpsr-editor-inside-right">
              <el-select
                  class="wpsr-text-input"
                  v-model="template_meta.selectedCategories"
                  multiple
                  collapse-tags
                  collapse-tags-tooltip
                  placeholder="Select categories to include"
                  size="small"
                  @change="throttledFetchReviews"
              >
                <el-option
                    v-for="(category, key) in categories"
                    :key="key"
                    :disabled="!has_pro"
                    :label="category"
                    :value="category">
                </el-option>
              </el-select>
            </div>
          </div>
        </div>
      </div>
    </el-collapse-item>

    <el-collapse-item name="6" v-if="template_meta">
      <template #title="{ isActive }">
        <span class="wpsr-editor-title-row">
          <SettingsLineIcon class="wpsr-editor-icon" />
          <span class="wpsr-editor-title-text wpsr-heading-text wpsr-input-field-label">{{ $t('Settings') }}</span>
        </span>

        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>
      <div class="wpsr_editor_edit_item-group wpsr_editor_inside_gap_16">
          <div v-if="image_settings.optimized_images === 'true'" class="wpsr-filters-row wpsr-editor-edit-item-field">
            <h3 class="wpsr-editor-inside-left wpsr-input-field-label">{{ $t('Images Resolution') }}</h3>
            <div class="wpsr-editor-inside-right">
              <el-select v-model="template_meta.resolution" placeholder="Select an option" size="small" class="wpsr-text-input">
                <el-option
                    v-for="item in image_resolution_options"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                >
                </el-option>
              </el-select>
            </div>
          </div>
          <div class="wpsr-settings-switch">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t('Display Rating') }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                v-model="template_meta.reviewerrating"
                active-color="#5c8df6"
                inactive-color="#b7b7b9"
                active-value="true"
                inactive-value="false"
              >
              </el-switch>
            </div>
          </div>
          <div class="wpsr-filters-row wpsr-settings-rating" v-if="template_meta.reviewerrating == 'true'">
            <span class="wpsr-editor-inside-left wpsr-input-field-label">{{ $t('Rating Style') }}</span>
            <div class="wpsr-editor-inside-right">
              <el-select v-model="template_meta.rating_style" placeholder="Select a Star Style" size="small" class="wpsr-text-input">
                <el-option
                    v-for="item in star_options"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                >
                </el-option>
              </el-select>
            </div>
          </div>

          <div class="wpsr-settings-switch">
            <span class="wpsr-element-label demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t('Display Verified Badge') }} <ProCrownIcon v-if="!has_pro" /></span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                :disabled="!has_pro || template_meta.reviewer_name === 'false'"
                v-model="template_meta.enable_verified_badge"
                active-color="#5c8df6"
                inactive-color="#b7b7b9"
                active-value="true"
                inactive-value="false"
              >
              </el-switch>
            </div>
          </div>

        <div class="wpsr_editor_edit_item" v-if="template_meta.enable_verified_badge !== 'false'">
          <div class="wpsr-filters-row">
            <div class="wpsr-editor-inside-left wpsr-input-field-label wpsr-element-center">
              <span>{{ $t('Verified Badge Tooltip Text') }}</span>
            </div>
            <div class="wpsr-editor-inside-right">
              <el-input
                  v-model="template_meta.verified_badge_tooltip_text"
                  placeholder="Enter tooltip text"
                  size="mini"
                  class="wpsr-text-input wpsr-editor-text-input"
              >
              </el-input>
            </div>
          </div>
        </div>

          <div class="wpsr-settings-rating">
            <span class="wpsr-editor-inside-left wpsr-input-field-label"> {{ $t('Display Date') }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                v-model="template_meta.timestamp"
                active-color="#5c8df6"
                inactive-color="#b7b7b9"
                active-value="true"
                inactive-value="false"
              >
              </el-switch>
            </div>
          </div>

          <div class="wpsr-settings-switch">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t(`Display ${options[type].owner_name} Name`) }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                v-model="template_meta.reviewer_name"
                active-color="#5c8df6"
                inactive-color="#b7b7b9"
                active-value='true'
                inactive-value='false'
              >
              </el-switch>
            </div>
          </div>

          <div class="wpsr-filters-row">
            <span class="wpsr-editor-inside-left wpsr-element-label wpsr-element-center wpsr-input-field-label">
              {{ $t(`${options[type].owner_name} Name Format`) }} <ProCrownIcon v-if="!has_pro" />
            </span>
            <div class="wpsr-editor-inside-right">
              <el-select v-model="template_meta.reviewer_name_format" :disabled="!has_pro || template_meta.reviewer_name === 'false'" placeholder="Select an option" size="small" class="wpsr-text-input">
                <el-option
                    v-for="item in image_name_format"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                >
                </el-option>
              </el-select>
            </div>
          </div>

          <div class="wpsr-settings-switch" v-if="template_meta.platform.includes('testimonial')">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t(`Display Author Position`) }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                  v-model="template_meta.author_position"
                  active-color="#5c8df6"
                  inactive-color="#b7b7b9"
                  active-value='true'
                  inactive-value='false'
              >
              </el-switch>
            </div>
          </div>

          <div class="wpsr-settings-switch" v-if="template_meta.platform.includes('testimonial')">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t(`Display Author Company Name`) }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                v-model="template_meta.author_company_name"
                active-color="#5c8df6"
                inactive-color="#b7b7b9"
                active-value='true'
                inactive-value='false'
              >
              </el-switch>
            </div>
          </div>

          <div class="wpsr-settings-switch" v-if="template_meta.platform.includes('testimonial')">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t(`Display Author Website Logo`) }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                v-model="template_meta.website_logo"
                active-color="#5c8df6"
                inactive-color="#b7b7b9"
                active-value='true'
                inactive-value='false'
              >
              </el-switch>
            </div>
          </div>

          <div class="wpsr-settings-switch">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t(`Display ${options[type].owner_name} Image`) }}</span>
              <div class="wpsr-editor-inside-right">
                <el-switch
                  v-model="template_meta.reviewer_image"
                  active-color="#5c8df6"
                  inactive-color="#b7b7b9"
                  active-value='true'
                  inactive-value='false'
                >
                </el-switch>
              </div>
          </div>

          <div class="wpsr-settings-switch" v-if="!template_meta.platform.includes('testimonial')">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t('Display Platform Icon') }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                  v-model="template_meta.isPlatformIcon"
                  active-color="#5c8df6"
                  inactive-color="#b7b7b9"
                  active-value='true'
                  inactive-value='false'
              >
              </el-switch>
            </div>
          </div>

          <div class="wpsr-settings-switch" v-if="has_pro && template_meta.platform && template_meta.platform.includes(tp_slug)">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-tooltip-position wpsr-input-field-label">{{ $t('Display ' + ucFirst(tp_slug) + ' Icon') }}
              <el-tooltip
                  :raw-content="true"
                  effect="dark"
                  placement="top-start"
                  :content="`Before enabling the ${ucFirst(tp_slug)} logo, please make sure you have permission to display it on your site. <br> Please check out their privacy policy to learn more about this.`"
              >
                  <el-icon ><InfoFilled /></el-icon>
              </el-tooltip>
            </span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                v-model="template_meta.display_tp_brand"
                active-color="#5c8df6"
                inactive-color="#b7b7b9"
                active-value='true'
                inactive-value='false'
              >
              </el-switch>
            </div>

          </div>

          <div class="wpsr-settings-switch">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t(`Display ${options[type].type_name} Title`) }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                  v-model="template_meta.display_review_title"
                  active-color="#5c8df6"
                  inactive-color="#b7b7b9"
                  active-value='true'
                  inactive-value='false'
              >
              </el-switch>
            </div>
          </div>

          <div class="wpsr-settings-switch">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t(`Display ${options[type].type_name} Text`) }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch
                  v-model="template_meta.isReviewerText"
                  active-color="#5c8df6"
                  inactive-color="#b7b7b9"
                  active-value='true'
                  inactive-value='false'
              >
              </el-switch>
            </div>
          </div>

          <div class="wpsr-settings-rating" v-if="template_meta.isReviewerText == 'true' && template_meta.platform.includes('google')">
            <span class="wpsr-tooltip-position wpsr-editor-inside-left wpsr-input-field-label">
              <span>
                {{ $t('Content Language') }}
              </span>
              <el-tooltip
                    :raw-content="true"
                    effect="dark"
                    placement="top-start"
                    content="This setting only works for Google Business Profile translated text."
              >
                <el-icon ><InfoFilled /></el-icon>
              </el-tooltip>
            </span>
            <div class="wpsr-editor-inside-right">
              <el-select v-model="template_meta.contentLanguage" size="small" class="wpsr-text-input">
                <el-option v-for="item in content_language_types" :key="item.value" :label="item.label" :value="item.value"></el-option>
              </el-select>
            </div>
          </div>

          <div class="wpsr-filters-row wpsr-settings-rating" v-if="template_meta.isReviewerText == 'true'">
            <span class="wpsr-editor-inside-left wpsr-input-field-label">{{ $t('Content Type') }}</span>
            <div class="wpsr-editor-inside-right">
              <el-select v-model="template_meta.contentType" size="small" class="wpsr-text-input">
                <el-option v-for="item in content_types" :key="item.value" :label="item.label" :value="item.value"></el-option>
              </el-select>
            </div>
          </div>

          <div class="wpsr-settings-switch" v-if="template_meta.contentType === 'excerpt'">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t('Trim Excerpt Words') }}</span>
            <!-- <el-input-number :min="4" v-model="template_meta.content_length" size="small"></el-input-number> -->
            <div class="wpsr-reviews-number wpsr-editor-inside-right">
              <el-input-number
                  v-model="template_meta.content_length"
                  :min="1"
                  controls-position="right"
                  size="large"
                  @change="handleChange"
                  class="wpsr-text-input"
              />
              <div class="wpsr-group-input">
                <el-button class="wpsr-editor-minus-btn" size="small" @click="template_meta.content_length = Math.min(200, (template_meta.content_length || 1) - 1)">-</el-button>
                <el-button class="wpsr-editor-plus-btn" size="small" @click="template_meta.content_length = Math.max(1, (template_meta.content_length || 1) + 1)">+</el-button>
              </div>
            </div>
          </div>

          <div class="wpsr-settings-switch">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t(' Enable External Links') }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch v-model="template_meta.enableExternalLink" active-color="#5c8df6" inactive-color="#b7b7b9" active-value='true' inactive-value='false'></el-switch>
            </div>

          </div>

          <div class="wpsr-settings-switch" v-if="template_meta.contentType === 'excerpt'">
            <span class="wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">{{ $t('Equal Height') }}</span>
            <div class="wpsr-editor-inside-right">
              <el-switch v-model="template_meta.equal_height" active-color="#5c8df6" inactive-color="#b7b7b9" active-value='true' inactive-value='false'></el-switch>
            </div>
          </div>

          <div class="wpsr-settings-slider" v-if="template_meta.equal_height === 'true' && template_meta.contentType === 'excerpt'">
            <div class="wpsr-runway-slider wpsr-editor-inside-left wpsr-input-field-label">
              <el-slider :min="100" :max="2000" v-model="template_meta.equalHeightLen"></el-slider>
            </div>
            <div class="wpsr-reviews-number wpsr-editor-inside-right">
              <el-input-number
                  v-model="template_meta.equalHeightLen"
                  :min="100"
                  :max="2000"
                  controls-position="right"
                  size="large"
                  @change="handleChange"
                  class="wpsr-text-input"
              />
              <div class="wpsr-group-input">
                <el-button class="wpsr-editor-minus-btn" size="small" @click="template_meta.equalHeightLen = Math.max(1, (template_meta.equalHeightLen || 1) - 1)">-</el-button>
                <el-button class="wpsr-editor-plus-btn" size="small" @click="template_meta.equalHeightLen = Math.min(10, (template_meta.equalHeightLen || 1) + 1)">+</el-button>
              </div>
            </div>
          </div>

          <div class="wpsr-settings-input" v-if="(template_meta.template === 'grid3' || template_meta.template === 'grid8') && (template_meta.platform.includes('fluent_forms') || template_meta.platform.includes('custom') || template_meta.platform.includes('woocommerce'))">
            <h3 class="wpsr-editor-inside-left wpsr-input-field-label">{{$t('Platform Label :')}}</h3>
            <div class="wpsr-editor-inside-right"><el-input type="text" placeholder="Ex: Onsite" v-model="template_meta.platform_label" size="small"></el-input></div>
          </div>
      </div>
    </el-collapse-item>

    <el-collapse-item name="7" v-if="template_meta.platform && !template_meta.platform.includes('testimonial')">
      
      <template #title="{ isActive}">
        <span class="wpsr-editor-title-row">
          <HeaderIcon class="wpsr-editor-icon" />
          <span class="wpsr-editor-title-text wpsr-heading-text"> {{ $t('Header') }} <ProCrownIcon v-if="!has_pro" /></span>
        </span>

        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>
      <UpgradeToProBanner v-if="!has_pro" />
      <div class="wpsr-switch-row" v-if="template_meta.show_header">
        <span class="wpsr-element-label wpsr-demonstration wpsr-editor-inside-left wpsr-input-field-label">
          <span>
            {{ $t('Display Header') }} 
          </span>
        </span>
        <el-switch
            v-model="template_meta.show_header"
            class="wpsr-editor-inside-right"
            active-color="#5c8df6"
            inactive-color="#b7b7b9"
            active-value="true"
            inactive-value="false"
            :disabled="!this.has_pro"
        >
        </el-switch>
      </div>

      <div class="wpsr-switch-group" v-if="template_meta.show_header === 'true'">
        <div class="wpsr-filters-row wpsr-gap-16">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label wpsr-element-center">  
            
            <span>
              {{ $t('Header Template') }} 
            </span>
          
          </span>
          <div class="wpsr-editor-inside-right">
              <el-select
                  size="small"
                  v-model="template_meta.header_template"
                  class="wpsr-text-input"
                  :disabled="!this.has_pro"
              >
                  <el-option
                    v-for="item in header_templates_options"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  >
                  </el-option>
              </el-select>
          </div>
        </div>
        <div class="wpsr-switch-row">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label"><span>{{ $t('Display Logo') }} </span></span>
          <el-switch class="wpsr-editor-inside-right" :disabled="!this.has_pro" v-model="template_meta.display_header_business_logo" />
        </div>
        <div class="wpsr-switch-row">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label"><span>{{ $t('Display Title') }} </span></span>
          <el-switch class="wpsr-editor-inside-right" :disabled="!this.has_pro" v-model="template_meta.display_header_business_name" />
        </div>
        <div class="wpsr-switch-row">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label"><span>{{ $t('Display Rating') }} </span></span>
          <el-switch class="wpsr-editor-inside-right" :disabled="!this.has_pro" v-model="template_meta.display_header_rating" />
        </div>
        <div class="wpsr-switch-row">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label"><span>{{ $t('Display Number of Reviews') }} </span></span>
          <el-switch class="wpsr-editor-inside-right" :disabled="!this.has_pro" v-model="template_meta.display_header_reviews" />
        </div>
        <div class="wpsr-switch-row">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label"><span>{{ $t('Display Write a Review Button') }} </span></span>
          <el-switch class="wpsr-editor-inside-right" :disabled="!this.has_pro" v-model="template_meta.display_header_write_review" />
        </div>
      </div>

<!--      <div role="group" aria-label="checkbox-group" class="el-checkbox-group">-->
<!--        <label class="el-checkbox is-checked" v-for="option in header_options">-->
<!--          <span class="el-checkbox__input" :class="checked_header_options.includes(option.value) ? 'is-checked' : ''">-->
<!--            <span class="el-checkbox__inner"></span>-->
<!--            <input type="checkbox" aria-hidden="false" class="el-checkbox__original" v-model="checked_header_options" :value="option.value">-->
<!--          </span>-->
<!--          <span class="el-checkbox__label">{{option.label}}</span>-->
<!--        </label>-->
<!--      </div>-->

      <EditorGroup
          v-if="template_meta.show_header === 'true'"
          :fieldsMaps="header_settings"
          :modelValue="template_meta"
          :proGroup="true"
          @input="$emit('update:template_meta', $event)"
      />
    </el-collapse-item>

    <el-collapse-item name="8" v-if="template_meta.templateType === 'slider'">
      <template #title="{ isActive}">
        <span class="wpsr-editor-title-row">
          <ChatEditorAccordionsIconPack icon-key="carousel" class="wpsr-editor-icon" width="20" height="20"/>
          <span class="wpsr-editor-title-text wpsr-heading-text wpsr-input-field-label"> {{ $t('Slider') }}</span>
        </span>
        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>
      <EditorGroup
          :fieldsMaps="carousel_settings"
          v-model="template_meta.carousel_settings"
          :modelValue="template_meta.carousel_settings"
          @input="handleCarouselSettingsUpdate"
      />
    </el-collapse-item>

    <el-collapse-item name="9" v-if="template_meta.templateType != 'slider'">
      <template #title="{ isActive}">
        <span class="wpsr-editor-title-row">
          <PaginationIcon class="wpsr-editor-icon" />
          <span class="wpsr-editor-title-text wpsr-heading-text"> {{ $t('Pagination') }}</span>
        </span>
        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>
      <EditorGroup
        :fieldsMaps="pagination_settings"
        :modelValue="template_meta"
        @update:modelValue="$emit('update:template_meta', $event)"
      />
    </el-collapse-item>

    <el-collapse-item name="12" v-if="template_meta.platform && !template_meta.platform.includes('testimonial')">
      <template #title="{ isActive}">
        <span class="wpsr-editor-title-row">
          <AISummarizerIcon class="wpsr-editor-icon" />
          <span class="wpsr-editor-title-text-row">
            <span class="wpsr-editor-title-text wpsr-heading-text"> {{ $t('AI Summarizer') }} <ProCrownIcon v-if="!has_pro" /></span>
            <span class="wpsr-editor-new-feature">{{ $t('New') }}</span>
          </span>
        </span>

        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>
      <span class="wpsr-ai-summary-controls">
        <p class="wpsr-editor-feature-info">Use AI to summarize your reviews inside the widget.</p>
        <div class="wpsr-settings-instruction" v-if="!can_enable_ai_summary">
          <p>
            Note: To be able to use this feature you need put your own API keys <a :href="this.appVars.admin_page_url+'#/settings/global-review-settings#wpsr-ai-review-summarizer-settings'" target="_blank">here</a>
          </p>
        </div>
        <div class="wpsr-switch-row wpsr-display-block">
          <div class="wpsr-settings-switch">
            <span class="wpsr-demonstration wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label">
              <span>
                <!-- Because it has a pro icon we need to add an extra span unline other labels and need to add class to the row parent-->
                {{ $t("Display AI Summary") }}
                <ProCrownIcon v-if="!has_pro" />
              </span> 
            </span>
            <el-switch
              v-model="template_meta.ai_summary.enabled"
              class="wpsr-editor-inside-right"
              active-color="#5c8df6"
              inactive-color="#b7b7b9"
              active-value="true"
              inactive-value="false"
              @change="handleAISummaryToggle"
              :disabled="aiSummaryToggleDisabled"
            >
            </el-switch>
          </div>
        </div>


        <div class="wpsr-switch-row" v-if="template_meta.ai_summary.enabled === 'true'">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label">{{ $t("Summary Style") }}</span>
          <div class="wpsr-editor-inside-right">
            <el-select
              v-model="template_meta.ai_summary.style"
              :placeholder="$t('Select AI summary style')"
              size="small"
              :disabled="!this.has_pro"
              class="wpsr-text-input"
            >
              <el-option
                v-for="item in summaryStyleOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              >
              </el-option>
            </el-select>
          </div>
        </div>

        <div class="wpsr-switch-row" v-if="template_meta.ai_summary.enabled === 'true'">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label">{{ $t("Display Read More") }}</span>
          <el-switch
            v-model="template_meta.ai_summary.display_readmore"
            class="wpsr-editor-inside-right"
            active-color="#5c8df6"
            inactive-color="#b7b7b9"
            :active-value="true"
            :inactive-value="false"
            :disabled="!this.has_pro"
          >
          </el-switch>
        </div>

        <div class="wpsr-switch-row" v-if="template_meta.ai_summary.enabled === 'true'">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label">{{ $t("Text Typing Animation") }}</span>
          <el-switch
            v-model="template_meta.ai_summary.text_typing_animation"
            class="wpsr-editor-inside-right"
            active-color="#5c8df6"
            inactive-color="#b7b7b9"
            :active-value="true"
            :inactive-value="false"
            :disabled="!this.has_pro"
          >
          </el-switch>
        </div>

        <div class="wpsr-switch-row" v-if="template_meta.ai_summary.enabled === 'true'">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label">{{ $t("Display AI Summary Icon") }}</span>
          <el-switch
            v-model="template_meta.ai_summary.display_ai_summary_icon"
            class="wpsr-editor-inside-right"
            active-color="#5c8df6"
            inactive-color="#b7b7b9"
            :active-value="true"
            :inactive-value="false"
            :disabled="!this.has_pro"
          >
          </el-switch>
        </div>

        <div class="wpsr-switch-row" v-if="template_meta.ai_summary.enabled === 'true'">
          <span class="wpsr-element-label wpsr-editor-inside-left wpsr-input-field-label">{{ $t("Regenerate AI Summary") }}</span>
          <div
            class="wpsr-editor-circle-button wpsr-editor-inside-right"
            :class="{ 'wpsr-disabled': !has_pro }"
            @click="regenerateAIReviewSummary"
          >
            <svg color="#5c8df6" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 5C8.28215 5 6.76568 5.86641 5.86527 7.1875H7.5V8.4375H3.75V4.6875H5V6.2496C6.13988 4.73229 7.95477 3.75 10 3.75C13.4517 3.75 16.25 6.54822 16.25 10H15C15 7.23857 12.7614 5 10 5ZM5 10C5 12.7614 7.23857 15 10 15C11.7179 15 13.2343 14.1336 14.1348 12.8125H12.5V11.5625H16.25V15.3125H15V13.7504C13.8601 15.2677 12.0452 16.25 10 16.25C6.54822 16.25 3.75 13.4517 3.75 10H5Z" fill="#525866"/>
            </svg>
          </div>
        </div>
      </span>
    </el-collapse-item>

    <el-collapse-item name="10" v-if="template_meta.templateType !== 'notification' && template_meta.platform &&!template_meta.platform.includes('testimonial')">
      <template #title="{ isActive}">
        <span class="wpsr-editor-title-row">
          <SchemaSnippets class="wpsr-editor-icon" />
          <span class="wpsr-editor-title-text wpsr-heading-text"> {{ $t('Schema Snippet') }}</span>
        </span>
        <span class="wpsr-custom-collapse-arrow">
          <PlusIcon v-if="!isActive" />
          <MinusIcon v-else/>
        </span>
      </template>
      <div class="wpsr-settings-switch">
        <span class="wpsr-demonstration wpsr-editor-inside-left">{{ $t('Enable Schema') }}</span>
        <el-switch
            v-model="template_meta.enable_schema"
            class="wpsr-editor-inside-right"
            active-color="#5c8df6"
            inactive-color="#b7b7b9"
            active-value="true"
            inactive-value="false"
        >
        </el-switch>
      </div>
      <div class="wpsr-item-field-note">
        <div class="wpsr-item-field-note-icon">
          <el-icon>
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 17.5C5.85775 17.5 2.5 14.1423 2.5 10C2.5 5.85775 5.85775 2.5 10 2.5C14.1423 2.5 17.5 5.85775 17.5 10C17.5 14.1423 14.1423 17.5 10 17.5ZM9.25 9.25V13.75H10.75V9.25H9.25ZM9.25 6.25V7.75H10.75V6.25H9.25Z" fill="#335CFF"/>
            </svg>
          </el-icon>
        </div>
        <p>
          <strong>Note:</strong> You may test your schema snippet is valid or not from <a href="https://developers.google.com/search/docs/advanced/structured-data" target="_blank">here</a>
        </p>
      </div>
      <EditorGroup
          v-if="template_meta.enable_schema === 'true'"
          :fieldsMaps="schema_settings"
          v-model="template_meta.schema_settings"
          :modelValue="template_meta.schema_settings"
      />
    </el-collapse-item>

    <el-collapse-item name="11"  v-if="template_meta.templateType === 'notification'">
      <template #title="{ isActive}">
        <EditorCollapsiblePanel :isActive="isActive" iconKey="priority" :label="$t('Priority')"/>
      </template>
      <h3 class="wpsr-demonstration">Priority for this Stream</h3>
      <!-- <el-input-number v-model=""></el-input-number> -->

      <EditorGroup
          :fieldsMaps="priority_settings"
          v-model="template_meta.notification_settings"
          :modelValue="template_meta.notification_settings"
      />
      <p>The more priority you give, it will match for the condition and show on your appropriate pages</p>
    </el-collapse-item>

    <!-- <UpgradeToProButton/> -->

</template>

<script type="text/babel">
import NotificationSettings from "./NotificationSettings";
import ImageSelect from "../../../core-ui/editor/ImageSelect";
import EditorGroup from '../../../core-ui/editor/EditorGroup';
import UpgradeToProButton from '../../../views/advertise/UpgradeToProButton';
import throttle from 'lodash/throttle';
import PlusIcon from "../../../pieces/icons/PlusIcon";
import MinusIcon from "../../../pieces/icons/MinusIcon";
import PlatformIcon from  "../../../pieces/icons/PlatformIcon";
import TemplateIcon from "../../../pieces/icons/TemplateIcon";
import FilterIcon from "../../../pieces/icons/FilterIcon";
import NotificationIcon from '../../../pieces/icons/NotificationIcon';
import SettingsLineIcon from "../../../pieces/icons/SettingsLineIcon";
import HeaderIcon from "../../../pieces/icons/HeaderIcon";
import PaginationIcon from  "../../../pieces/icons/PaginationIcon";
import AISummarizerIcon from "../../../pieces/icons/AISummarizerIcon";
import SchemaSnippets from '../../../pieces/icons/SchemaSnippetsIcon';
import InputCheckIcon from "../../../pieces/icons/InputCheckIcon";
import InputUncheckIcon from "../../../pieces/icons/InputUncheckIcon";
import BadgeIcon from "../../../pieces/icons/BadgeIcon";
import ResponsiveBar from '../../../core-ui/editor/ResponsiveBar';
import EditorCollapsiblePanel from "../../../core-ui/editor/EditorCollapsiblePanel.vue";
import ProCrownIcon from "../../../pieces/icons/ProCrownIcon.vue";
import UpgradeToProBanner from "../../../views/advertise/UpgradeToProBanner.vue";
import ChatEditorAccordionsIconPack from "../../../pieces/icons/ChatEditorAccordionsIconPack.vue";
import {PlatformIconMixin} from "../../../../mixins/PlatformIconMixin";

export default {
  name: "ReviewsEditor",
  mixins: [PlatformIconMixin],
  props: ['template_meta', 'post_types', 'pages', 'country_list', 'categories', 'platforms', 'all_reviews', 'business_info', 'type', 'types', 'can_enable_ai_summary', 'image_settings', 'available_grid_skins'],
  emits: ['update:template_meta', 'fetchReviews', 'saveTemplateMeta', 'toggleAISummary', 'regenerateAISummaryForReviews', 'handleDevice'],
  components: {
    ChatEditorAccordionsIconPack,
    NotificationSettings,
    ImageSelect,
    EditorGroup,
    UpgradeToProButton,
    PlusIcon,
    MinusIcon,
    PlatformIcon,
    TemplateIcon,
    FilterIcon,
    SettingsLineIcon,
    HeaderIcon,
    PaginationIcon,
    AISummarizerIcon,
    SchemaSnippets,
    InputCheckIcon,
    InputUncheckIcon,
    BadgeIcon,
    ResponsiveBar,
    NotificationIcon,
    EditorCollapsiblePanel,
    ProCrownIcon,
    UpgradeToProBanner
  },
  data() {
    return {
      layout_settings: [
        {
          fieldKey: 'responsive_column_number',
          type: 'select',
          title: this.$t('Number of Columns'),
          responsive: true,
          condition: {
            key: 'templateType',
            operator: 'Arr',
            selector: ['grid', 'masonry']
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
            }
          ]
        }
      ],
      filter_options: [
        {
          fieldKey: 'totalReviewsNumber',
          type: 'classic_number',
          title: this.$t('Number of ' + this.types),
          min: -1,
          max: 200,
          hide_tablet: true,
          responsive: true,
          tooltip: true,
          tooltipText: "The responsive number of reviews will only work on your site's preview/live pages/posts."
        }
      ],
      tp_slug: this.appVars.tp_slug,
      checked_header_options: ['display_header_business_logo', 'display_header_business_name', 'display_header_rating', 'display_header_reviews', 'display_header_write_review'],
      header_options: [
        {
          value: 'display_header_business_logo',
          label: 'Display Logo',
          disabled: this.has_pro
        },
        {
          value: 'display_header_business_name',
          label: 'Display Title',
          disabled: this.has_pro
        },
        {
          value: 'display_header_rating',
          label: 'Display Rating',
          disabled: this.has_pro
        },
        {
          value: 'display_header_reviews',
          label: 'Display Number of Reviews',
          disabled: this.has_pro
        },
        {
          value: 'display_header_write_review',
          label: 'Display Write a Review Button',
          disabled: this.has_pro
        }
      ],
      header_settings: [
        {
          fieldKey: 'custom_title_text',
          type: 'text',
          title: this.$t('Custom Title Text'),
          placeholder: this.$t('Rating'),
          disabled: !this.has_pro
        },
        {
          fieldKey: 'custom_number_of_reviews_text',
          type: 'textarea',
          title: this.$t('Custom Number of Reviews Text'),
          placeholder: this.$t('Based on {total_reviews} Reviews'),
          disabled: !this.has_pro,
          tooltip: true,
          tooltipText: this.$t('You can use this {total_reviews} shortcode to show dynamic value in a text. Ex: Based on {total_reviews} Reviews.')
        },
        {
          fieldKey: 'custom_write_review_text',
          type: 'text',
          title: this.$t('Custom Button Text'),
          placeholder: this.$t('Write a Review'),
          disabled: !this.has_pro
        },
        {
          fieldKey: 'add_custom_war_btn_url',
          type: 'switch',
          title: this.$t('Add "Write a Review" URL?'),
          active_value: 'true',
          inactive_value: 'false',
          disabled: !this.has_pro
        },
        {
          fieldKey: 'war_btn_source',
          type: 'select',
          disabled: !this.has_pro,
          title: this.$t('Button Source Type'),
          condition: {
            key: 'add_custom_war_btn_url',
            selector: 'true'
          },
          options: [
            {
              value: 'custom_url',
              label: 'Custom URL'
            },
            {
              value: 'form_id',
              label: 'Fluent Form Shortcode ID'
            }
          ]
        },
        {
          fieldKey: 'war_btn_source_custom_url',
          type: 'text',
          title: this.$t('Custom URL'),
          placeholder: this.$t('Enter Custom URL'),
          condition: {
            operator: 'multiple',
            terms: [
              {
                key: 'war_btn_source',
                selector: 'custom_url'
              },
              {
                key: 'add_custom_war_btn_url',
                selector: 'true'
              }
            ]
          }
        },
        {
          fieldKey: 'war_btn_open_in_new_window',
          type: 'switch',
          title: this.$t('Open in new window'),
          active_value: 'true',
          inactive_value: 'false',
          condition: {
            operator: 'multiple',
            terms: [
              {
                key: 'war_btn_source',
                selector: 'custom_url'
              },
              {
                key: 'add_custom_war_btn_url',
                selector: 'true'
              }
            ]
          }
        },
        {
          fieldKey: 'war_btn_source_form_shortcode_id',
          type: 'classic_number',
          title: this.$t('Form Shortcode ID'),
          placeholder: this.$t('Ex: 1'),
          tooltip: true,
          tooltipText: this.$t("Fluent Forms will only display on your site's preview/live pages/posts <br> not while you're in editing mode in the WordPress Editor."),
          condition: {
            operator: 'multiple',
            terms: [
              {
                key: 'war_btn_source',
                selector: 'form_id'
              },
              {
                key: 'add_custom_war_btn_url',
                selector: 'true'
              }
            ]
          }
        }
      ],
      pagination_settings: [
        {
          fieldKey: 'pagination_type',
          type: 'select',
          title: this.$t('Pagination Type'),
          options: [
            {
              value: 'none',
              label: this.$t('None')
            },
            {
              value: 'load_more',
              label: this.$t('Load More')
            }
          ],
          tooltip: true,
          tooltipText: this.$t("The Load More button will only display on your site's preview/live pages/posts <br> not while you're in editing mode in the WordPress Editor.")
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
          title: this.$t('Reviews Per Page :'),
          min: 1,
          max: 20,
          flex: true,
          condition: {
            'key': 'pagination_type',
            'selector': 'load_more'
          }
        },
      ],
      /* templateTypes moved to computed property to allow dynamic filtering based on platform (e.g. hide 'badge' for testimonials) */
      badge_skins: [
        {
          key: 'badge1',
          title: 'Badge1',
          img: this.assets_url + '/images/template/review-template/badge1.png',
        },
        {
          key: 'badge2',
          title: 'Badge2',
          img: this.assets_url + '/images/template/review-template/badge2.png',
        }
      ],
      //filters options
      star_filters: [
          {
            value: -1,
            label: 'No Minimum Rating',
          },
          {
            value: 1,
            label: '1 Star',
            pro: !this.has_pro
          },
          {
            value: 2,
            label: '2 Stars',
            pro: !this.has_pro
          },
          {
            value: 3,
            label: '3 Stars',
            pro: !this.has_pro
          },
          {
            value: 4,
            label: '4 Stars',
            pro: !this.has_pro
          },
          {
            value: 5,
            label: '5 Stars',
            pro: !this.has_pro
          }
      ],
      orders: [
          {
              value: 'asc',
              label: 'Oldest',
          },
          {
              value: 'desc',
              label: 'Newest',
          },
          {
            value: 'random',
            label: 'Random',
          }
      ],
      title_options: [
        {
          value: 'all',
          label: 'All'
        },
        {
          value: 'include',
          label: 'Include',
          pro: !this.has_pro
        },
        {
          value: 'exclude',
          label: 'Exclude',
          pro: !this.has_pro
        }
      ],

      //settings options
      star_options: [
          {
            label: 'Default',
            value: 'default'
          },
          {
            label: 'Number Style Rating',
            value: 'style1'
          },
          {
            label: 'Icon with Number Style Rating',
            value: 'style2'
          }
      ],
      summaryStyleOptions:[
        {
          label: 'List',
          value: 'list'
        },
        {
          label: 'Text',
          value: 'text'
        }
      ],

      image_resolution_options: [
        {
          label: this.$t('Thumbnail (80*80)'),
          value: 'thumb'
        },
        {
          label: this.$t('Medium (120*120)'),
          value: 'low'
        },
        {
          label: this.$t('Full Size (150*150)'),
          value: 'full'
        }
      ],

      image_name_format: [
        {
          label: this.$t('Full Name (John Wick)'),
          value: 'full-name'
        },
        {
          label: this.$t('First Name Only (John)'),
          value: 'first-name'
        },
        {
          label: this.$t('First Name and Initial of Last Name (John W.)'),
          value: 'first-name-last-initial'
        },
        {
          label: this.$t('Initials Only (J. W.)'),
          value: 'initials-only'
        },
      ],

      options: {
        testimonial: {
          type_name: 'Testimonial',
          owner_name: 'Author'
        },

        review: {
          type_name: 'Review',
          owner_name: 'Reviewer'
        }
      },
      content_types: [
        {
          label: 'Excerpt',
          value: 'excerpt'
        },
        {
          label: 'Full Content in Scrollbar',
          value: 'content_in_scrollbar'
        },
        {
          label: 'Full Content',
          value: 'full_content'
        }
      ],
      content_language_types: [
        {
          label: 'Default',
          value: 'default'
        },
        {
          label: 'Original',
          value: 'original'
        },
        {
          label: 'English(Translated By Google)',
          value: 'translated_by_google'
        }
      ],

      //badge settings
      badge_settings: [
        {
          fieldKey: 'badge_position',
          type: 'select',
          title: this.$t('Badge Position'),
          options: [
            {
              value: 'default',
              label: this.$t('Default'),
            },
            {
              value: 'float_left_bottom',
              label: this.$t('Float Bottom Left'),
            },
            {
              value: 'float_right_bottom',
              label: this.$t('Float Bottom Right'),
            },
            {
              value: 'float_top_left',
              label: this.$t('Float Top Left'),
            },
            {
              value: 'float_top_right',
              label: this.$t('Float Top Right'),
            }
          ]
        },
        {
          fieldKey: 'display_platform_icon',
          type: 'switch',
          title: this.$t('Display Platform Icon :'),
          active_value: 'true',
          inactive_value: 'false',
        },
        {
          fieldKey: 'custom_title',
          type: 'text',
          title: this.$t('Custom Title :'),
          placeholder: this.$t('Rating')
        },
        {
          fieldKey: 'custom_num_of_reviews_text',
          type: 'text',
          title: this.$t('Custom Button Text'),
          placeholder: this.$t('Read our {reviews_count} Reviews'),
          tooltip: true,
          tooltipText: this.$t('You can use this {reviews_count} shortcode to show dynamic value in a text. Ex: Read our {reviews_count} Reviews')
        },
        {
          fieldKey: 'display_mode',
          type: 'select',
          title: this.$t('Button Source Type'),
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
            },
            {
              value: 'form_shortcode_id',
              label: 'Fluent Form Shortcode ID',
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
          fieldKey: 'form_shortcode_id',
          type: 'classic_number',
          title: this.$t('Fluent Form Shortcode ID'),
          placeholder: this.$t('Ex: 1'),
          tooltip: true,
          tooltipText: this.$t("Fluent Forms will only display on your site's preview/live pages/posts <br> not while you're in editing mode in the WordPress Editor."),
          condition: {
            operator: 'multiple',
            terms: [
              {
                'key': 'war_btn_source',
                'selector': 'form_id'
              },
              {
                'key': 'add_custom_war_btn_url',
                'selector': 'true'
              }
            ]
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
          fieldKey: 'open_in_new_window',
          type: 'switch',
          title: this.$t('Open in new window'),
          active_value: 'true',
          inactive_value: 'false',
          condition: {
            'key': 'display_mode',
            'operator': 'Arr',
            'selector': ['page', 'custom_url']
          },
        },
      ],
      carousel_settings: [
        {
          fieldKey: 'autoplay',
          type: 'switch',
          title: this.$t('Autoplay :'),
          active_value: 'true',
          inactive_value: 'false',
        },
        {
          fieldKey:'autoplay_speed',
          type:'number',
          title: this.$t('Autoplay Speed :'),
          min: 1,
          max: 10000,
          flex: true
        },
        {
          fieldKey: 'responsive_slides_to_show',
          type: 'number',
          title: this.$t('Slides to Show :'),
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
          title: this.$t('Space between slide:'),
          min: 0,
          max: 200,
          flex: true
        },
        {
          fieldKey: 'navigation',
          type: 'select',
          title: this.$t('Navigation'),
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
      priority_settings:[
        {
          fieldKey: 'notification_priority',
          type: 'number',
          title: this.$t('Priority :'),
          min: 0,
          flex: true,
          tooltip: true,
          tooltipText: this.$t('The higher the number, the higher the priority.'),
        }
      ],
      schema_settings: [
        {
          fieldKey: 'business_logo',
          type: 'image',
          title: this.$t('Business Logo'),
        },
        {
          fieldKey: 'business_name',
          type: 'text',
          title: this.$t('Business Name'),
        },
        {
          fieldKey: 'business_type',
          type: 'text',
          title: this.$t('Business Type'),
          note: '<strong>Note: </strong>Please provide a valid business/organization type. If you want to know more about business type please visit <a href="https://schema.org/docs/schemas.html" target="_blank">schema.org</a>'
        },
        {
          fieldKey: 'business_telephone',
          type: 'text',
          title: this.$t('Business Phone Number'),
        },
        {
          fieldKey: 'include_business_address',
          type: 'switch',
          title: this.$t('Include Business Address'),
          active_value: 'true',
          inactive_value: 'false',
        },
        {
          fieldKey: 'business_street_address',
          type: 'text',
          title: this.$t('Street Address'),
          condition: {
            'key': 'include_business_address',
            'selector': 'true'
          }
        },
        {
          fieldKey: 'business_address_city',
          type: 'text',
          title: this.$t('City'),
          condition: {
            'key': 'include_business_address',
            'selector': 'true'
          }
        },
        {
          fieldKey: 'business_address_state',
          type: 'text',
          title: this.$t('State/Region'),
          condition: {
            'key': 'include_business_address',
            'selector': 'true'
          }
        },
        {
          fieldKey: 'business_address_postal_code',
          type: 'text',
          title: this.$t('Postal Code'),
          condition: {
            'key': 'include_business_address',
            'selector': 'true'
          }
        },
        {
          fieldKey: 'business_address_country',
          type: 'select',
          event: 'on_change',
          isFilterable: true,
          title: this.$t('Country'),
          options: this.country_list,
          condition: {
            'key': 'include_business_address',
            'selector': 'true'
          }
        },
        {
          fieldKey: 'business_average_rating',
          type: 'number',
          precision: 1,
          step: 0.1,
          max: 10,
          flex: true,
          title: this.$t('Fallback Average Rating'),
        },
        {
          fieldKey: 'business_total_rating',
          type: 'number',
          min: 0,
          flex: true,
          title: this.$t('Fallback Total Rating'),
        }
      ],
      aiSummary: {
        enabled: false,
        summaryStyle: 'list',
        summaryStyleOptions: [
          {
            label: 'List',
            value: 'list'
          },
          {
            label: 'Grid',
            value: 'grid'
          }
        ]
      },
      isUpdating: false,
      lastEmittedValue: null,
      device: 'desktop',
      activeDevice: 'dashicons-desktop',
      platformSelectorLoading: false,
      badgeSkinLoading: false,
    }
  },

  created() {
    // Throttle fetchReviews to 500ms
    this.throttledFetchReviews = throttle(this.fetchReviews, 500);
  },

  methods: {
    extendStarFilters() {
      let platforms = this.template_meta.platform;
      let star_filters = [];
      if(platforms && platforms.includes('booking.com')) {
        star_filters = [{
            value: 6,
            label: '6 Stars',
            pro: !this.has_pro
          }, {
            value: 7,
            label: '7 Stars',
            pro: !this.has_pro
          }, {
            value: 8,
            label: '8 Stars',
            pro: !this.has_pro
          }, {
            value: 9,
            label: '9 Stars',
            pro: !this.has_pro
          }, {
            value: 10,
            label: '10 Stars',
            pro: !this.has_pro
        }];
      }

      let arr = this.star_filters.concat(star_filters);
      return arr;
    },
    fetchReviews() {
      this.$emit('fetchReviews');
    },
    handleChange() {
      // Handle change events for input number fields
      // Emit update to parent component to reflect changes
      this.$emit('update:template_meta', { ...this.template_meta });
    },
    regenerateAIReviewSummary(){
      if (!this.has_pro) return;
      this.$emit('regenerateAISummaryForReviews');
    },
    saveTemplateMetaAndReload(){
      this.$emit('saveTemplateMeta');
    },
    handleAISummaryToggle(value) {
      if (!this.has_pro) return;
      this.$emit('toggleAISummary', value);
    },
    onLayoutSettingsInput(updatedFields) {
      // Only update the responsive_column_number field
      this.template_meta.responsive_column_number = updatedFields.responsive_column_number;
      this.$emit('update:template_meta', { ...this.template_meta });
    },
    onFilterOptionsUpdate(newVal) {
      if (this.isUpdating) return;

      this.isUpdating = true;
      const updatedValue = JSON.parse(JSON.stringify(newVal));

      // Update the template_meta directly
      Object.assign(this.template_meta, updatedValue);

      // Emit the update
      this.$emit('update:template_meta', this.template_meta);

      setTimeout(() => {
        this.isUpdating = false;
      }, 0);
    },

    handleInputChange(newVal) {
      // Only update the local value without emitting
      const updatedValue = JSON.parse(JSON.stringify(newVal));
      Object.assign(this.template_meta, updatedValue);
    },
    
    handleCarouselSettingsUpdate(newCarouselSettings) {
      // Properly update only the carousel_settings property
      // Create a proper update object that only modifies carousel_settings
      const updatedTemplateMeta = { ...this.template_meta };
      updatedTemplateMeta.carousel_settings = newCarouselSettings;
      
      // Emit the complete template_meta with updated carousel_settings
      this.$emit('update:template_meta', updatedTemplateMeta);
    },
    
    togglePlatform(value) {
      this.platformSelectorLoading = true;
      
      const idx = this.template_meta.platform.indexOf(value);
      if (idx === -1) {
        this.template_meta.platform.push(value);
      } else {
        this.template_meta.platform.splice(idx, 1);
      }
      this.throttledFetchReviews();
    },

    handleDevice(val, activeDevice) {
      this.device = val;
      this.activeDevice = activeDevice;
      this.$emit('handleDevice', val, activeDevice);
    },
  },
  computed: {
    aiSummaryToggleDisabled() {
      return !this.has_pro || !this.can_enable_ai_summary;
    },
    templateTypes() {
      const types = [
        {
          key: 'grid',
          title: 'Grid',
          img: this.assets_url + '/images/template/global-template/grid.png',
        },
        {
          key: 'slider',
          title: 'Slider',
          pro: !this.has_pro,
          img: this.assets_url + '/images/template/global-template/slider.png',
        },
        {
          key: 'masonry',
          title: 'Masonry',
          pro: !this.has_pro,
          img: this.assets_url + '/images/template/global-template/masonry.png',
        },
        {
          key: 'badge',
          title: 'Badge',
          pro: !this.has_pro,
          img: this.assets_url + '/images/template/global-template/badge.png',
        }
      ];

      // If the currently selected platforms include 'testimonial', hide the badge option
      if (this.template_meta && this.template_meta.platform && this.template_meta.platform.includes('testimonial')) {
        return types.filter(t => t.key !== 'badge');
      }

      return types;
    },
    header_templates_options() {
      const platforms = this.template_meta.platform || [];

      // Base template that always shows
      const baseTemplates = [
        {
          value: 'template1',
          label: this.$t('Template 1'),
        }
      ];

      // Template 2 eligible platforms
      const template2EligiblePlatforms = ['woocommerce', 'custom', 'fluent_forms'];

      // Check if all selected platforms are eligible for Template 2
      const shouldShowTemplate2 = platforms.length > 0 &&
          platforms.every(platform => template2EligiblePlatforms.includes(platform));

      if (shouldShowTemplate2) {
        baseTemplates.push({
          value: 'template2',
          label: this.$t('Template 2'),
        });
      }
      return baseTemplates;
    }
  },
  watch: {
    template_meta: {
      handler(newVal) {
        if (newVal && !this.isUpdating) {
          const newValueStr = JSON.stringify(newVal);
          const currentValueStr = JSON.stringify(this.lastEmittedValue);

          if (newValueStr !== currentValueStr) {
            this.lastEmittedValue = JSON.parse(JSON.stringify(newVal));
          }
        }
      },
      deep: true,
      immediate: true
    },
    'template_meta.platform': {
      handler(newPlatforms) {
        // Check if Template2 is currently selected
        if (this.template_meta.header_template === 'template2') {
          const platforms = newPlatforms || [];
          const template2EligiblePlatforms = ['woocommerce', 'custom', 'fluent_forms'];

          // Check if Template2 should no longer be available
          const shouldShowTemplate2 = platforms.length > 0 &&
              platforms.every(platform => template2EligiblePlatforms.includes(platform));

          // If Template2 should not be shown, switch to Template1
          if (!shouldShowTemplate2) {
            this.template_meta.header_template = 'template1';
          }
        }
      },
      deep: true
    },
    all_reviews: {
      handler(newReviews, oldReviews) {
        // Stop loading when reviews data is updated after API call completes
        if ((this.platformSelectorLoading || this.badgeSkinLoading) && newReviews && newReviews !== oldReviews) {
          this.$nextTick(() => {
            this.platformSelectorLoading = false;
            this.badgeSkinLoading = false;
          });
        }
      },
      deep: true
    },
    aiSummaryToggleDisabled: {
      handler(isDisabled) {
        // When AI summary toggle is disabled, automatically set ai_summary.enabled to false
        if (isDisabled && this.template_meta && this.template_meta.ai_summary) {
          this.template_meta.ai_summary.enabled = 'false';
        }
      },
      immediate: true // Run on component creation
    }
  },
  beforeUnmount() {
    if (this.updateTimeout) {
      clearTimeout(this.updateTimeout);
    }
  }
}
</script>

