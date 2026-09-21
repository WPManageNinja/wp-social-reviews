<template>
  <div class="wpsr-license-management">
    <div v-if="hasPermission(['wpsn_license_settings', 'wpsn_full_access'])">
        <div class="wpsr_set_header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
            <div class="wpsr_set_header_title">
                <h3>{{ $t('License Management') }}</h3>
            </div>
            <div class="wpsr_set_actions">
                <el-button
                    v-if="has_pro"
                    type="default"
                    size="default"
                    @click="getSettings">
                    <el-icon><Refresh /></el-icon>
                </el-button>
            </div>
        </div>
        <div class="wpsr-general-settings-wrapper">
            <div class="wpsr-settings-container">
                <div v-if="has_pro" v-loading="verifying" class="wpsr_set_body fc_narrow_box">
                    <!-- Loading Skeleton -->
                    <div v-if="fetching" class="wpsr-settings-container">
                        <WpsrSkeleton :rows="8" />
                    </div>

                    <!-- Content -->
                    <div v-else class="wpsr-license-body" :class="'wpsr-license-'+status">
                        <div v-if="alert" class="wpsr-license-alert" :class="'is-' + alert.type">
                            <div class="wpsr-license-alert-body">
                                <strong v-text="alert.title"></strong>
                                <p v-if="alert.description" v-text="alert.description"></p>
                            </div>
                            <a v-if="alert.action" :href="alert.action.url" target="_blank" rel="noopener"
                               class="wpsr-license-alert-btn" v-text="alert.action.label"></a>
                        </div>

                        <div v-if="showCard" class="wpsr-license-card">
                            <div class="wpsr-license-card-head">
                                <div class="wpsr-license-card-title">
                                    <!-- fallback: an older Pro version does not send product_title -->
                                    <h3 v-text="licenseData.product_title || 'WP Social Ninja Pro'"></h3>
                                    <p v-if="licenseData.variation_title" v-text="licenseData.variation_title"></p>
                                </div>
                                <span class="wpsr-license-pill" :class="'is-' + statusMeta.type" v-text="statusMeta.label"></span>
                            </div>

                            <ul v-if="licenseRows.length" class="wpsr-license-meta">
                                <li v-for="row in licenseRows" :key="row.label">
                                    <span class="wpsr-license-meta-label" v-text="row.label"></span>
                                    <span class="wpsr-license-meta-value">
                                        <span v-if="row.badge" class="wpsr-license-pill is-soft"
                                              :class="'is-' + row.badge" v-text="row.value"></span>
                                        <span v-else v-text="row.value"></span>
                                        <small v-if="row.hint" v-text="row.hint"></small>
                                    </span>
                                </li>
                            </ul>

                            <div class="wpsr-license-card-footer">
                                <div class="wpsr-license-checked">
                                    <span v-if="licenseData.last_checked_human">
                                        {{ $t('Last checked') }}: {{ licenseData.last_checked_human }}
                                    </span>
                                    <a href="#" @click.prevent="getSettings()">{{ $t('Refresh') }}</a>
                                    <a v-if="licenseData.account_url" :href="licenseData.account_url" target="_blank"
                                       rel="noopener">{{ $t('Your Account') }}</a>
                                    <a v-if="licenseData.support_url" :href="licenseData.support_url" target="_blank"
                                       rel="noopener">{{ $t('Get Support') }}</a>
                                </div>
                                <div class="wpsr-license-card-actions">
                                    <a v-if="licenseData.manage_url" :href="licenseData.manage_url" target="_blank"
                                       rel="noopener" class="el-button el-button--small">
                                        {{ $t('Manage Subscription') }}
                                    </a>
                                    <el-button size="small" @click="deactivateLicense()">
                                        {{ $t('Deactivate') }}
                                    </el-button>
                                </div>
                            </div>
                        </div>

                        <div v-if="showForm" class="wpsr-license-form" :class="{'is-swap': showCard}">
                            <div class="wpsr-license-form-head">
                                <h3 class="wpsr-license-form-title">
                                    {{ showCard ? $t('Activate a different license key') : $t('Enter Your WP Social Ninja Pro License Key to Get Started') }}
                                </h3>
                                <a v-if="showCard" href="#" class="wpsr-license-form-cancel"
                                   @click.prevent="cancelKeyChange()">{{ $t('Cancel') }}</a>
                            </div>

                            <p class="wpsr-license-form-help">
                                {{ showCard
                                    ? $t('The key you enter replaces the one this site currently uses.')
                                    : $t('Activate your license to enable auto-updates, premium support & full functionality.') }}
                            </p>

                            <div class="wpsr-license-form-field">
                                <el-input v-model="licenseKey" class="wpsr-input-default wpsr-border-left-side-around"
                                          :placeholder="$t('License Key')" @keyup.enter="verifyLicense()">
                                    <template #append>
                                        <el-button
                                            :disabled="!licenseKey.trim()"
                                            @click="verifyLicense()"
                                            class="wpsr_primary_btn">
                                            {{ $t('Verify License') }}
                                        </el-button>
                                    </template>
                                </el-input>
                            </div>

                            <p v-if="!showCard" class="wpsr-license-purchase-text">
                                {{ $t("Don't have a license key?") }}
                                <a target="_blank" rel="noopener" :href="licenseData.purchase_url"
                                   class="wpsr-license-purchase-link">{{ $t('Grab Your Pro License Here') }}</a>
                            </p>
                        </div>

                        <p v-if="showCard && !showForm" class="wpsr-license-switch">
                            {{ $t('Have a new license Key?') }}
                            <a href="#" class="wpsr-license-purchase-link"
                               @click.prevent="showNewLicenseInput = true">{{ $t('Click here') }}</a>
                        </p>

                        <div v-if="errorLines.length" class="wpsr-license-warning">
                            <p v-for="(line, index) in errorLines" :key="index" v-text="line"></p>
                        </div>
                    </div>
                </div>
                <div class="wpsr-setting-content-area" v-else>
                  <div class="wpsr-connection-upgrade-to-pro">
                    <div class="wpsr-connection-upgrade-message">
                      <h2>Upgrade to WP Social Ninja Pro and unlock all the features</h2>
                      <p>Get access to all premium features, priority support, and regular updates with our Pro version.</p>
                      <a :href="upgradeBtnConfig.pro_purchase_url" class="wpsr-upgrade-btn wpsr-extend-btn" target="_blank">
                        <span class="wpsr-crown-icon"><ProCrownIcon :width="20" :height="20"/></span> {{ upgradeBtnConfig.text || 'Upgrade to Pro' }}
                      </a>
                    </div>
                  </div>
                </div>
            </div>
        </div>

    </div>
    <PermissionsNotification v-if="!hasPermission(['wpsn_license_settings', 'wpsn_full_access'])"/>
  </div>
</template>

<script type="text/babel">

import PermissionsNotification from "../../advertise/PermissionsNotification";
import ProCrownIcon from "../../../pieces/icons/ProCrownIcon";
import { Refresh } from '@element-plus/icons-vue';
import { wpsrUpgradeUrl } from '../../../../utils/upgradeUrl';

export default {
    name: 'LicenseSettings',
    components: {
        PermissionsNotification,
        ProCrownIcon,
        Refresh
    },
    data() {
        return {
            fetching: true,
            verifying: false,
            licenseData: {},
            licenseKey: '',
            showNewLicenseInput: false,
            errorMessage: ''
        }
    },
    computed: {
        upgradeBtnConfig() {
            return {
                text: 'Upgrade to Pro',
                ...(this.appVars?.upgrade_btn_config || {}),
                pro_purchase_url: wpsrUpgradeUrl('settings_license_management')
            };
        },
        // convertToText() joins nested API errors with <br />; render them as
        // discrete text nodes instead of piping markup through v-html.
        errorLines() {
            if (!this.errorMessage) {
                return [];
            }

            return String(this.errorMessage)
                .split(/<br\s*\/?>/i)
                .map(line => line.trim())
                .filter(line => line);
        },
        status() {
            return this.licenseData.status || 'unregistered';
        },
        isExpired() {
            if (this.status === 'expired' || this.licenseData.is_expired) {
                return true;
            }

            // Trust the date over the status: a license whose expiry has passed
            // and whose grace window has run out is expired, whatever the store
            // still calls it.
            const days = this.licenseData.days_remaining;

            return !this.licenseData.is_lifetime && !this.inGracePeriod &&
                days !== null && days !== undefined && days < 0;
        },
        // The store keeps a license valid for a while past its expiry date, so
        // "Active" alone would hide the fact that the clock is already running.
        inGracePeriod() {
            return !!this.licenseData.in_grace_period;
        },
        // Only these states describe a license we actually hold, so only these
        // are worth rendering as a card.
        showCard() {
            return ['valid', 'expired', 'disabled'].indexOf(this.status) !== -1;
        },
        showForm() {
            return !this.showCard || this.showNewLicenseInput;
        },
        renewUrl() {
            return this.licenseData.renew_url || this.licenseData.purchase_url;
        },
        subscriptionActive() {
            return ['active', 'trialing', 'trial'].indexOf(this.licenseData.subscription_status) !== -1;
        },
        // An active subscription renews itself, so there is nothing to renew by hand.
        renewAction() {
            if (this.subscriptionActive) {
                return null;
            }

            return {url: this.renewUrl, label: this.$t('Renew License')};
        },
        supportAction() {
            if (!this.licenseData.support_url) {
                return null;
            }

            return {url: this.licenseData.support_url, label: this.$t('Contact Support')};
        },
        expiringSoon() {
            const days = this.licenseData.days_remaining;
            return days !== null && days !== undefined && days >= 0 && days <= 30;
        },
        subscriptionNeedsAttention() {
            const status = this.licenseData.subscription_status;
            if (!status) {
                return false;
            }
            return ['cancelled', 'canceled', 'paused', 'on_hold', 'pending', 'failing', 'expired'].indexOf(status) !== -1;
        },
        statusMeta() {
            if (this.inGracePeriod) {
                return {label: this.$t('Grace Period'), type: 'warning'};
            }

            if (this.isExpired) {
                return {label: this.$t('Expired'), type: 'danger'};
            }

            const map = {
                valid       : {label: this.$t('Active'), type: 'success'},
                expired     : {label: this.$t('Expired'), type: 'danger'},
                disabled    : {label: this.$t('Disabled'), type: 'danger'},
                invalid     : {label: this.$t('Invalid'), type: 'danger'},
                error       : {label: this.$t('Error'), type: 'danger'},
                unregistered: {label: this.$t('Not Activated'), type: 'neutral'}
            };

            return map[this.status] || {label: this.humanize(this.status), type: 'neutral'};
        },
        subscriptionMeta() {
            const status = this.licenseData.subscription_status;
            if (!status) {
                return null;
            }

            const types = {
                active   : 'success',
                trialing : 'success',
                trial    : 'success',
                paused   : 'warning',
                on_hold  : 'warning',
                pending  : 'warning',
                failing  : 'danger',
                cancelled: 'danger',
                canceled : 'danger',
                expired  : 'danger'
            };

            return {
                label: this.humanize(status),
                type : types[status] || 'neutral'
            };
        },
        expiryRow() {
            if (this.licenseData.is_lifetime) {
                return {
                    label: this.$t('Expires'),
                    value: this.$t('Lifetime'),
                    badge: 'success'
                };
            }

            if (!this.licenseData.expires_human) {
                return null;
            }

            const days = this.licenseData.days_remaining;
            let hint = '';

            if (days !== null && days !== undefined) {
                if (days === -1) {
                    hint = this.$t('expired 1 day ago');
                } else if (days < 0) {
                    hint = this.$t('expired %d days ago', Math.abs(days));
                } else if (days === 0) {
                    hint = this.$t('expires today');
                } else if (days === 1) {
                    hint = this.$t('in 1 day');
                } else {
                    hint = this.$t('in %d days', days);
                }
            }

            return {
                label: this.$t('Expires'),
                value: this.licenseData.expires_human,
                badge: days !== null && days !== undefined && days < 0 ? 'danger' : '',
                hint : hint
            };
        },
        // The grace window is worth naming in the card, but not dating: its
        // length is our assumption about the store, not something the store
        // reports, so promising a specific end date would be guessing out loud.
        graceRow() {
            if (!this.inGracePeriod) {
                return null;
            }

            return {
                label: this.$t('Updates & Support'),
                value: this.$t('Ending soon'),
                badge: 'warning'
            };
        },
        licenseRows() {
            const rows = [];

            if (this.expiryRow) {
                rows.push(this.expiryRow);
            }

            if (this.graceRow) {
                rows.push(this.graceRow);
            }

            if (this.subscriptionMeta) {
                rows.push({
                    label: this.$t('Subscription'),
                    value: this.subscriptionMeta.label,
                    badge: this.subscriptionMeta.type
                });
            }

            if (this.licenseData.license_key_masked) {
                rows.push({label: this.$t('License Key'), value: this.licenseData.license_key_masked});
            }

            if (this.licenseData.customer_name || this.licenseData.customer_email_masked) {
                rows.push({
                    label: this.$t('Licensed To'),
                    value: this.licenseData.customer_name || this.licenseData.customer_email_masked,
                    hint : this.licenseData.customer_name ? this.licenseData.customer_email_masked : ''
                });
            }

            return rows;
        },
        alert() {
            const remoteMessage = this.licenseData.error_message || this.licenseData.message;

            // Inside the grace window the license still works, so this is a
            // warning rather than an error - but it has to say when it stops.
            if (this.inGracePeriod) {
                return {
                    type       : 'warning',
                    title      : this.licenseData.expires_human
                        ? this.$t('Your license expired on %s', this.licenseData.expires_human)
                        : this.$t('Your license has expired.'),
                    description: this.$t('You are in a grace period, so everything keeps working for now. Updates and support will end soon - renew your license to keep them.'),
                    action     : this.renewAction
                };
            }

            if (this.isExpired) {
                return {
                    type       : 'danger',
                    title      : this.licenseData.expires_human
                        ? this.$t('Your license expired on %s', this.licenseData.expires_human)
                        : this.$t('Looks like your license has expired.'),
                    description: this.$t('Renew your license to keep receiving plugin updates, new features and support.'),
                    action     : this.renewAction
                };
            }

            if (this.status === 'disabled') {
                return {
                    type       : 'danger',
                    title      : this.$t('This license has been disabled.'),
                    description: remoteMessage || this.$t('Please contact our support team for assistance.'),
                    action     : this.supportAction
                };
            }

            if (this.status === 'invalid' || this.status === 'error') {
                return {
                    type       : 'danger',
                    title      : this.$t('We could not verify this license.'),
                    description: remoteMessage || '',
                    action     : this.supportAction
                };
            }

            if (this.status === 'valid' && this.subscriptionNeedsAttention) {
                return {
                    type       : 'warning',
                    title      : this.$t('Your subscription is not active (%s)', this.subscriptionMeta.label),
                    description: this.licenseData.expires_human
                        ? this.$t('Your license will not renew on its own. It expires on %s.', this.licenseData.expires_human)
                        : '',
                    action     : this.renewAction
                };
            }

            // A license that is about to expire on an active subscription renews
            // itself, so it is not worth warning about.
            if (this.status === 'valid' && this.expiringSoon && !this.subscriptionActive) {
                return {
                    type  : 'warning',
                    title : this.licenseData.days_remaining === 0
                        ? this.$t('Your license expires today.')
                        : this.$t('Your license expires in %d days', this.licenseData.days_remaining),
                    action: this.renewAction
                };
            }

            return null;
        }
    },
    methods: {
        humanize(value) {
            if (!value) {
                return '';
            }
            const text = String(value).replace(/_/g, ' ');
            return text.charAt(0).toUpperCase() + text.slice(1);
        },
        cancelKeyChange() {
            this.showNewLicenseInput = false;
            this.licenseKey = '';
            this.errorMessage = '';
        },
        getSettings() {
            this.errorMessage = '';
            this.fetching = true;
            this.$get('settings/license', {verify: true})
                .then((response) => {
                    this.licenseData = response;
                })
                .catch(errors => {
                    this.handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        verifyLicense() {
            if (!this.licenseKey) {
                this.errorMessage = this.$t('Please provide a license key');
                this.handleError(this.errorMessage);
                return;
            }

            this.verifying = true;

            this.errorMessage = '';
            this.$post('settings/license', {
                license_key: this.licenseKey
            })
                .then(response => {
                    this.licenseData = response.license_data;
                    this.licenseKey = '';
                    this.showNewLicenseInput = false;
                    this.handleSuccess(response.message);
                })
                .catch((errorResponse) => {
                    let errorMessage = '';

                    if(errorResponse && errorResponse.responseJSON) {
                        errorResponse = errorResponse.responseJSON;
                    }

                    if (typeof errorResponse === 'string') {
                        errorMessage = errorResponse;
                    } else if (errorResponse && errorResponse.message) {
                        errorMessage = errorResponse.message;
                    } else {
                        errorMessage = this.convertToText(errorResponse);
                    }
                    if (!errorMessage) {
                        errorMessage = this.$t('Something is wrong!');
                    }

                    this.errorMessage = errorMessage;

                    this.handleError(errorResponse);
                })
                .always(() => {
                    this.verifying = false;
                });
        },
        deactivateLicense() {
            this.$confirm(
                this.$t('This site will stop receiving plugin updates until a license is activated again.'),
                this.$t('Deactivate this license?'),
                {
                    confirmButtonText: this.$t('Yes, Deactivate'),
                    cancelButtonText : this.$t('Cancel'),
                    type             : 'warning'
                }
            ).then(() => {
                this.verifying = true;
                this.$del('settings/license')
                    .then(response => {
                        this.licenseData = response.license_data;
                        this.showNewLicenseInput = false;
                        this.handleSuccess(response.message);
                    })
                    .catch((errors) => {
                        this.handleError(errors)
                    })
                    .always(() => {
                        this.verifying = false;
                    });
            }).catch(() => {});
        }
    },
    mounted() {
        // Core/free has no license endpoint behind it, so asking would only raise an error.
        if (this.has_pro) {
            this.getSettings();
        }
    }
}
</script>
