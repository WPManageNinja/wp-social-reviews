<template>
    <div 
        class="wpsn-accounts-list" 
        v-if="validAccounts().length > 0" 
        v-loading="loading" 
        element-loading-text=""
    >
        <div class="wpsn-accounts-wrapper">
            <p class="wpsn-accounts-header-title wpsr-font-weight-500">{{ title }}</p>
            <div class="wpsn-accounts-card-wrapper">
                <div
                    v-for="item in validAccounts()"
                    :key="item.page_id"
                    class="wpsn-accounts-card"
                    :class="{ 'wpsn-accounts-card--selected': selectedAccounts.includes(item.page_id) }"
                    @click="toggleAccount(item)"
                >
                    <div class="wpsn-accounts-label-row">
                        <img 
                            :src="getAccountImage(item)" 
                            class="wpsn-accounts-icon wpsn-circle-avatar" 
                            :alt="item.username || item.name" 
                        />
                        <div class="wpsr-accounts-info">
                            <span class="wpsr-accounts-label">
                                {{ item.name }}
                            </span>
                            <span class="wpsr-accounts-secondary-label wpsr-secondary-label wpsr-body-regular-color">
                                {{ item.type }}
                            </span>

                            <span v-if="item.status === 'error'" class="wpsr-account-error-message wpsr-accounts-secondary-label wpsr-secondary-label wpsr-body-regular-color">    
                                <div class="wpsr-account-error-status">
                                    <span>
                                        <el-icon><InfoFilled /></el-icon>{{ $t('Source Invalid') }}
                                    </span>
                                </div>
                            </span>
                        </div>

                        <span
                            class="wpsn-accounts-checkbox"
                            :class="{
                                'checked': selectedAccounts.includes(item.page_id)
                            }"
                            style="cursor: pointer;"
                        >
                            <InputCheckIcon v-if="selectedAccounts.includes(item.page_id)" />
                            <InputUncheckIcon v-else />
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import InputCheckIcon from '../../pieces/icons/InputCheckIcon.vue';
import InputUncheckIcon from '../../pieces/icons/InputUncheckIcon.vue';

export default {
    name: 'AccountSelector',
    components: {
        InputCheckIcon,
        InputUncheckIcon
    },
    props: {
        accounts: {
            type: [Array, Object],
            default: () => []
        },
        selectedAccounts: {
            type: Array,
            default: () => []
        },
        title: {
            type: String,
            default: 'Select Accounts'
        },
        feed_type: {
            type: String,
            default: 'page_feed'
        },
        loading: {
            type: Boolean,
            default: false
        }
    },
    emits: ['toggle-account'],
    methods: {
        toggleAccount(account) {
            this.$emit('toggle-account', account);
        },
        getAccountImage(account) {
            return `https://graph.facebook.com/${account.page_id}/picture`;
        },
        validAccounts(){
            if(this.feed_type === 'event_feed'){
                return this.accounts.filter(account => account.is_event_enabled === true);
            }

            return this.accounts.filter(account => account.status !== 'error')
        }
    }
}
</script>
