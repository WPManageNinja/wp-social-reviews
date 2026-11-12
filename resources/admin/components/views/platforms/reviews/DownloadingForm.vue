<template>
  <div>
    <!-- Pro sends this when the platform allows one business and one is connected. -->
    <div v-if="businessLimitNotice" class="wpsr-alert wpsr-alert-info wpsr-d-flex">
      <InfoIcon color="var(--wpsr-icon-info-color)"/>
      <p>{{ businessLimitNotice }}</p>
    </div>
    <el-button
        v-else-if="Object.keys(reviewsinfo).length"
        style="width: 100%"
        v-model="addNewBusiness"
        :class="{'display-form': !!addNewBusiness}"
        class="wpsr_primary_btn_outline"
        type="success"
        :disabled="!has_pro ? true : false"
        @click.prevent="addBusiness">
      <el-icon size="18"><Link /></el-icon>
      {{ $t('Add More Business') }} {{ !has_pro ? $t(' (Pro)') : ''}}
    </el-button>
    <div v-if="!businessLimitNotice && (addNewBusiness || Object.keys(reviewsinfo).length === 0)">

      <div v-if="platFormName === 'tripadvisor'">

        <el-tabs v-model="credentialsType" class="wpsr-connections-tabs">
          <el-tab-pane name="api_key">
            <template #label>
              <span class="wpsr-connections-tabs-label">
                <el-icon size="18"><Key /></el-icon>
                <div class="wpsr-connections-tabs-label-group">
                  <span>API Key (Recommended)</span>
                  <p>Connect via Token</p>
                </div>
              </span>
            </template>
            <div class="wpsr-accounts-connect-wrapper">
              <div class="wpsr-connection-modal-input-wrapper">
                <div class="wpsr-connection-modal-input-heading-wrapper">
                  <h4 class="wpsr-connection-modal-input-heading">
                    {{ $t('Add Your API Key Here') }}
                  </h4>
                  <p class="wpsr-connection-modal-input-description"> If you need help with getting your Tripadvisor API Key, <a :href="apiUrl" target="_blank">Click here</a></p>
                  <p class="wpsr-connection-modal-input-description">
                    Tripadvisor's free plan sends the <strong>3 most recent reviews</strong> each sync.
                    Your collection builds up over time — every scheduled sync adds any new ones and
                    keeps everything already saved. Tripadvisor also allows 1,000 items in total
                    before they start charging.
                  </p>
                </div>
                <span v-if="message" :class="messageType">{{ message }}</span>
                <InputPassword
                    v-model="localApiKey"
                    placeholder="Enter your api key.."
                    autocomplete="off"
                />
              </div>
              <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
                <div class="wpsr-connection-modal-input-heading-wrapper">
                  <h4 class="wpsr-connection-modal-input-heading">
                    {{ $t('Add Your Place ID Here') }}
                  </h4>
                  <p class="wpsr-connection-modal-input-description">
                    Copy the number after <strong>-d</strong> in your
                    <a target="_blank" :href="sourceUrl">Tripadvisor</a> page URL —
                    in <em>…-g60724-<strong>d8725965</strong>-…</em> it's <strong>8725965</strong>
                    (<strong>-g</strong> is the city).
                  </p>
                  <p class="wpsr-connection-modal-input-description">
                    Works for hotels, restaurants and attractions. Tripadvisor doesn't share
                    reviews for tours or vacation rentals, so those can't be connected.
                  </p>
                </div>
                <span v-if="source_message" :class="source_messageType">{{ source_message }}</span>
                <el-input
                    v-model="localSourceId"
                    autocomplete="off"
                    class="wpsr-input-default"
                    :placeholder="'Enter your Place ID..'"
                >
                  <template #prepend>
                    <el-icon><Place /></el-icon>
                  </template>
                </el-input>
              </div>
            </div>
          </el-tab-pane>
          <!--
            Business URL tab removed: Tripadvisor serves a DataDome challenge (403)
            to server-side requests on every page type, so the scrape path cannot
            read anything. Reconnecting it needs that to change, not a selector fix.
          -->
        </el-tabs>
      </div>

      <div class="wpsr-accounts-connect-wrapper" :class="Object.keys(reviewsinfo).length && !credentialsType ? 'wpsr-mt-20' : ''" v-if="credentialsType === 'business_url' && !hotelSearchMode">
        <div class="wpsr-connection-modal-input-wrapper">
          <div class="wpsr-connection-modal-input-heading-wrapper">
            <h4 class="wpsr-connection-modal-input-heading">
              {{ $t('Enter your business URL to get your reviews:') }}
            </h4>
            <p class="wpsr-connection-modal-input-description">Open your business/product's page on
              <strong v-if="platFormName !== 'booking.com'">{{platFormName}}.com</strong>
              <strong v-if="platFormName === 'booking.com'">{{platFormName}}</strong>
              in your browser. Copy the link and paste it into the input field to display your reviews. [e.g.:{{exampleURL}}]</p>
          </div>
          <el-input
              :type="type"
              v-model="localValue"
              @keyup.enter="saveReviews"
              class="wpsr-input-default"
              placeholder="Please enter your business url"
          >
            <template #prepend>
              <GlobalLineIcon/>
            </template>
          </el-input>
        </div>
      </div>

      <!-- booking.com: pick the hotel by name; the id it returns is what the sync uses -->
      <div class="wpsr-accounts-connect-wrapper" :class="Object.keys(reviewsinfo).length ? 'wpsr-mt-20' : ''" v-if="hotelSearchMode">
        <div class="wpsr-connection-modal-input-wrapper" v-if="!hotelIdMode">
          <div class="wpsr-connection-modal-input-heading-wrapper">
            <h4 class="wpsr-connection-modal-input-heading">
              {{ $t('Search your hotel on Booking.com') }}
            </h4>
            <p class="wpsr-connection-modal-input-description">
              {{ $t('Type the property name exactly as it appears on Booking.com and pick it from the list.') }}
            </p>
          </div>
          <span v-if="message" :class="messageType">{{ message }}</span>
          <span v-if="hotelError" class="wpsr-error">{{ hotelError }}</span>
          <el-select
              v-model="selectedHotelId"
              class="wpsr-select-field-primary"
              style="width: 100%;"
              filterable
              remote
              reserve-keyword
              :remote-method="searchHotels"
              :loading="hotelLoading"
              :placeholder="$t('e.g. Restart Accommodations Rome')"
              no-data-text="No hotels found"
              @change="selectHotel"
          >
            <el-option
                v-for="hotel in hotelResults"
                :key="hotel.hotel_id"
                :label="hotel.name + (hotel.location ? ' — ' + hotel.location : '')"
                :value="hotel.hotel_id"
            >
              <div class="wpsr-booking-hotel-option">
                <img v-if="hotel.photo" :src="hotel.photo" :alt="hotel.name" width="32" height="32" />
                <span>
                  <strong>{{ hotel.name }}</strong>
                  <small v-if="hotel.location"> — {{ hotel.location }}</small>
                </span>
              </div>
            </el-option>
          </el-select>
          <p class="wpsr-booking-hotel-id-switch">
            <a href="#" @click.prevent="openHotelIdForm">{{ $t("Can't find your hotel? Enter its Booking.com hotel ID.") }}</a>
          </p>
        </div>

        <!-- When the search fails or misses the hotel: the id and name, copied from the hotel's page source -->
        <template v-else>
          <div class="wpsr-connection-modal-input-wrapper">
            <div class="wpsr-connection-modal-input-heading-wrapper">
              <label class="wpsr-connection-modal-input-heading" for="wpsr-booking-hotel-id">
                {{ $t('Enter your Booking.com hotel ID') }}
              </label>
              <div class="wpsr-booking-hotel-id-steps">
                <h5 class="wpsr-booking-hotel-id-steps-title">{{ $t('How to Find Your Hotel ID') }}</h5>
                <ol>
                  <li>{{ $t("Open your hotel's page on Booking.com in a computer browser.") }}</li>
                  <li>{{ $t('Open the page source: press Ctrl+U (Windows) or Option+Command+U (Mac). In Firefox on a Mac, press Command+U. In Safari, first turn on Settings → Advanced → "Show features for web developers".') }}</li>
                  <li>{{ $t('Press Ctrl+F (Windows) or Command+F (Mac), and search for b_hotel_id.') }}</li>
                  <li>{{ $t("You see a line like b_hotel_id: '62665', b_hotel_name: 'Your Hotel'. Check that the name is your hotel.") }}</li>
                  <li>{{ $t('Copy the line, or only the number, and paste it below.') }}</li>
                </ol>
              </div>
            </div>
            <span v-if="message" :class="messageType">{{ message }}</span>
            <span v-if="hotelIdError" class="wpsr-error">{{ hotelIdError }}</span>
            <el-input
                id="wpsr-booking-hotel-id"
                v-model="hotelIdInput"
                autocomplete="off"
                class="wpsr-input-default"
                :placeholder="$t('e.g. 62665')"
                @input="updateHotelId"
            >
              <template #prepend>
                <el-icon><Place /></el-icon>
              </template>
            </el-input>
          </div>
          <div class="wpsr-connection-modal-input-wrapper wpsr-mt-20">
            <div class="wpsr-connection-modal-input-heading-wrapper">
              <label class="wpsr-connection-modal-input-heading" for="wpsr-booking-hotel-name">
                {{ $t('Hotel name') }}
              </label>
              <p class="wpsr-connection-modal-input-description">
                {{ $t('The name your widgets show. A pasted b_hotel_id line fills it.') }}
              </p>
            </div>
            <el-input
                id="wpsr-booking-hotel-name"
                v-model="hotelIdName"
                autocomplete="off"
                class="wpsr-input-default"
                :placeholder="$t('e.g. Hotel Adlon Kempinski Berlin')"
                @input="updateHotelId"
            >
              <template #prepend>
                <el-icon><OfficeBuilding /></el-icon>
              </template>
            </el-input>
          </div>
        </template>
      </div>

      <div class="wpsr-accounts-connect-wrapper wpsr-mt-20" v-if="platFormName === 'tripadvisor' && credentialsType === 'api_key'">
        <div class="wpsr-connection-modal-input-wrapper">
          <div class="wpsr-connection-modal-input-heading-wrapper">
            <h4 class="wpsr-connection-modal-input-heading">
              {{ $t('Select Language') }}
            </h4>
          </div>
          <el-select class="wpsr-select-field-primary" style="width: 100%;" v-model="language" @change="updateLanguage" filterable placeholder="Select Language" size="default">
            <el-option
                v-for="language in languages"
                :key="language.value"
                :label="language.label"
                :value="language.value"
            >
            </el-option>
          </el-select>
        </div>
      </div>

      <SaveAndResetButton
          v-if="(platFormName ==='airbnb'
          || platFormName === appVars.tp_slug
          || platFormName ==='tripadvisor'
          || platFormName === 'amazon'
          || platFormName === 'aliexpress'
          || platFormName ==='booking.com')"
          :platFormName="platFormName"
          :verifyPlatform="verifyPlatform"
          :reviewsinfo="reviewsinfo"
          :isDisable="getDisableState()"
          @save-reviews="saveReviews"
      />
    </div>

  </div>
</template>
<script type="text/babel">
import {CirclePlus, Key, Link, Remove} from '@element-plus/icons-vue';
import SaveAndResetButton from './SaveAndResetButton.vue';
import InputPassword from '../../../core-ui/editor/InputPassword.vue';
import GlobalLineIcon from '../../../pieces/icons/GlobalLineIcon.vue';
import ShieldCheckLineIcon from "../../../pieces/icons/ShieldCheckLineIcon.vue";
import InfoIcon from "../../../pieces/icons/InfoIcon.vue";

export default {
  name: 'DownloadingForm',
  components: {
    Key,
    ShieldCheckLineIcon,
    Link,
    CirclePlus,
    Remove,
    SaveAndResetButton,
    InputPassword,
    GlobalLineIcon,
    InfoIcon
  },
  props: ['value', 'exampleURL', 'apiUrl', 'sourceUrl', 'sourceText', 'verifyPlatform', 'platFormName', 'reviewsinfo', 'apiKey', 'sourceId', 'source_message', 'source_messageType', 'message', 'messageType', 'languages', 'connectMode', 'bookingHotel', 'businessLimitNotice'],
  data(){
    return{
      type: 'text',
      addNewBusiness: false,
      credentialsType: this.platFormName === 'tripadvisor' ? 'api_key' : 'business_url',
      language: 'en',
      localApiKey: this.apiKey || '',
      localSourceId: this.sourceId || '',
      localValue: this.value || '',
      hotelResults: [],
      hotelLoading: false,
      hotelError: '',
      selectedHotelId: '',
      hotelSearchTimer: null,
      hotelSearchSeq: 0,
      hotelSearchXhr: null,
      hotelIdMode: true,
      hotelIdInput: '',
      hotelIdName: '',
      hotelIdError: '',
    }
  },
  methods:{
    saveReviews(){
      this.$emit('save-reviews');
    },
    addBusiness(){
      this.addNewBusiness = !this.addNewBusiness;
      this.language = 'en';
      this.$emit('set-languages', this.language);
      this.resetHotel();
    },
    invalidateHotelSearch() {
      // Any search still waiting or in flight is stale from this point; its response is dropped.
      clearTimeout(this.hotelSearchTimer);
      if (this.hotelSearchXhr && this.hotelSearchXhr.abort) this.hotelSearchXhr.abort();
      this.hotelSearchXhr = null;
      this.hotelSearchSeq++;
      this.hotelLoading = false;
    },
    resetHotel() {
      this.invalidateHotelSearch();
      this.hotelResults = [];
      this.hotelError = '';
      this.selectedHotelId = '';
      this.hotelIdMode = true;
      this.hotelIdInput = '';
      this.hotelIdName = '';
      this.hotelIdError = '';
      this.$emit('booking-hotel', null);
    },
    openHotelIdForm() {
      this.invalidateHotelSearch();
      this.selectedHotelId = '';
      this.hotelIdMode = true;
      this.updateHotelId();
    },
    // The admin pastes the number, or the page-source line "b_hotel_id: '62665', b_hotel_name: '…'".
    // A room link (#RD6266502) holds the id with more digits, so it is not read as an id.
    parseHotelId(text) {
      const value = (text || '').trim();
      if (/^\d{1,12}$/.test(value)) {
        return { id: Number(value), name: '' };
      }
      const id = value.match(/b_hotel_id\s*[:=]\s*['"]?(\d{1,12})/);
      if (!id) {
        return null;
      }
      // The source is a JS string: a quote in the name is escaped (\'), and it may hold entities.
      const name = value.match(/b_hotel_name\s*[:=]\s*'((?:[^'\\]|\\.){1,200})'/);
      const plain = name ? name[1].replace(/\\(.)/g, '$1') : '';
      return {
        id: Number(id[1]),
        name: plain ? new DOMParser().parseFromString(plain, 'text/html').documentElement.textContent.trim() : ''
      };
    },
    updateHotelId() {
      const raw = (this.hotelIdInput || '').trim();
      const parsed = raw === '' ? null : this.parseHotelId(raw);
      const valid = parsed && parsed.id > 0;
      this.hotelIdError = raw !== '' && !valid ? this.$t('Paste the number after b_hotel_id.') : '';
      if (valid) {
        if (parsed.name && !(this.hotelIdName || '').trim()) {
          this.hotelIdName = parsed.name;
        }
        // Show what will be saved, not the pasted line.
        if (raw !== String(parsed.id)) {
          this.hotelIdInput = String(parsed.id);
        }
      }
      const name = (this.hotelIdName || '').trim();
      this.$emit('booking-hotel', valid && name
          ? { hotel_id: parsed.id, name, ufi: 0, country: '', location: '' }
          : null);
    },
    searchHotels(query) {
      this.invalidateHotelSearch();
      const seq = this.hotelSearchSeq;
      const term = (query || '').trim();
      // A typed query means the pick no longer matches the box, so drop it and disable
      // Save. Reopening sends an empty query and selecting sends none, so neither clears.
      if (term !== '' && this.selectedHotelId) {
        this.selectedHotelId = '';
        this.$emit('booking-hotel', null);
      }
      // Reopening the dropdown fires an empty query; leave the last results in place.
      if (term.length < 2) {
        return;
      }
      // Debounce so a typed name costs one request, not one per keystroke.
      this.hotelSearchTimer = setTimeout(() => {
        this.hotelLoading = true;
        this.hotelError = '';
        // Keep the jqXHR itself so invalidateHotelSearch() can abort it; .then() returns a plain promise.
        this.hotelSearchXhr = this.$get('platforms/reviews/search', {
          platform: this.platFormName,
          query: term
        });
        this.hotelSearchXhr.then(response => {
          if (seq !== this.hotelSearchSeq) return;
          this.hotelResults = (response && response.data && response.data.results) || [];
        }).catch(errors => {
          if (seq !== this.hotelSearchSeq) return;
          const json = (errors && errors.responseJSON) || {};
          this.hotelResults = [];
          this.hotelError = (json.data && json.data.message)
              || json.message
              || (errors && errors.message)
              || 'Hotel search failed. Please try again.';
        }).always(() => {
          if (seq === this.hotelSearchSeq) this.hotelLoading = false;
        });
      }, 350);
    },
    selectHotel(hotelId) {
      const hotel = this.hotelResults.find(item => item.hotel_id === hotelId) || null;
      this.$emit('booking-hotel', hotel);
    },
    updateLanguage(value) {
      this.language = value;
      this.$emit('set-languages', value);
    },
    getDisableState() {
      if (this.hotelSearchMode) {
        return this.hotelIdMode ? !this.bookingHotel : !this.selectedHotelId;
      }
      if (this.platFormName === 'tripadvisor') {
        if (this.credentialsType === 'api_key') {
          return !this.localApiKey || !this.localSourceId;
        } else {
          return !this.localValue;
        }
      }
      return !this.localValue;
    }
  },
  computed: {
    // Pro reports connect_mode 'search' when its hotel search handler exists; older Pro keeps the URL form.
    hotelSearchMode() {
      return this.platFormName === 'booking.com' && this.connectMode === 'search';
    }
  },
  watch: {
    localApiKey: {
      handler(newVal) {
        this.$emit('apiKey', newVal);
      },
      immediate: true
    },
    localSourceId: {
      handler(newVal) {
        this.$emit('sourceId', newVal);
      },
      immediate: true
    },
    localValue: {
      handler(newVal) {
        this.$emit('url_value', newVal);
      },
      immediate: true
    },
    apiKey(newVal) {
      this.localApiKey = newVal;
    },
    sourceId(newVal) {
      this.localSourceId = newVal;
    },
    value(newVal) {
      this.localValue = newVal;
    },
    credentialsType: {
      handler(newVal) {
        this.$emit('credentialsType', newVal);
      },
      immediate: true
    },
    platFormName() {
      this.addNewBusiness = false;
      this.credentialsType =  this.platFormName === 'tripadvisor' ? 'api_key' : 'business_url';
      this.resetHotel();
    },
    bookingHotel(hotel) {
      // The parent clears its pick after a finished save; clear the picker to match.
      if (!hotel && this.selectedHotelId) {
        this.resetHotel();
      }
    }
  },
  mounted() {
    this.addNewBusiness = false;
  },
  beforeUnmount() {
    this.invalidateHotelSearch();
  }
}
</script>
