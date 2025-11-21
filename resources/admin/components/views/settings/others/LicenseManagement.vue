<template>
  <div class="wpsr-license-management">
    <div v-if="hasPermission(['wpsn_license_settings', 'wpsn_full_access'])">
        <div class="wpsr_set_header wpsr-d-flex wpsr-jc-between wpsr-flex-align-center">
            <div class="wpsr_set_header_title">
                <h3>License Management</h3>
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
                    <div v-else-if="!fetching" class="text-align-center"
                        :class="'wpsr-license-'+licenseData.status">
                        <div v-if="licenseData.status == 'expired'">
                            <h3>Looks like your license has been expired {{ licenseData.expires | nsHumanDiffTime }}</h3>
                            <a :href="licenseData.renew_url" target="_blank"
                            class="el-button el-button--danger el-button--small">Click Here to Renew your License</a>

                            <hr style="margin: 20px 0px;"/>
                            <p v-if="!showNewLicenseInput">Have a new license Key? <a
                                @click.prevent="showNewLicenseInput = !showNewLicenseInput" href="#">Click here</a></p>
                            <div v-else>
                                <h3>Your License Key</h3>
                                <el-input v-model="licenseKey" class="wpsr-input-default wpsr-border-left-side-around" placeholder="License Key">
                                    <template #append>
                                        <el-button 
                                            :disabled="!licenseKey.trim()" 
                                            @click="verifyLicense()" 
                                            class="wpsr_primary_btn">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-lock" viewBox="0 0 16 16">
                                                <path fill-rule="evenodd" d="M8 0a4 4 0 0 1 4 4v2.05a2.5 2.5 0 0 1 2 2.45v5a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 2 13.5v-5a2.5 2.5 0 0 1 2-2.45V4a4 4 0 0 1 4-4M4.5 7A1.5 1.5 0 0 0 3 8.5v5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 11.5 7zM8 1a3 3 0 0 0-3 3v2h6V4a3 3 0 0 0-3-3"/>
                                            </svg>
                                            <span class="wpsr-ml-5">{{ $t('Verify License') }}</span>
                                        </el-button>
                                    </template>
                                </el-input>
                            </div>
                        </div>
                        <div v-else-if="licenseData.status == 'valid'">
                            <div class="text-align-center">
                                <el-icon class="wpsr-activated-icon"><CircleCheck /></el-icon>
                            </div>
                            <h3 class="wpsr-license-form-title">You license key is valid and activated</h3>
                            <p class="wpsr-deactivate-license-text">Want to deactivate this license? <a class="wpsr-license-purchase-link" @click.prevent="deactivateLicense()" href="#">Click here</a>
                            </p>
                        </div>
                        <div v-else>
                            <h3>{{$t('Enter Your WP Social Ninja Pro License Key to Get Started')}}</h3>
                            <el-input v-model="licenseKey" class="wpsr-input-default wpsr-border-left-side-around" placeholder="License Key">
                                <template #append>
                                    <el-button 
                                        :disabled="!licenseKey.trim()" 
                                        @click="verifyLicense()" 
                                        class="wpsr_primary_btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-lock" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M8 0a4 4 0 0 1 4 4v2.05a2.5 2.5 0 0 1 2 2.45v5a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 2 13.5v-5a2.5 2.5 0 0 1 2-2.45V4a4 4 0 0 1 4-4M4.5 7A1.5 1.5 0 0 0 3 8.5v5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 11.5 7zM8 1a3 3 0 0 0-3 3v2h6V4a3 3 0 0 0-3-3"/>
                                        </svg>
                                        <span class="wpsr-ml-5">{{ $t('Verify License') }}</span>
                                    </el-button>
                                </template>
                            </el-input>
                            <p>{{$t('Activate your license to enable auto-updates, premium support & full functionality.')}}</p>

                            <p v-if="!showNewLicenseInput" class="wpsr-license-purchase-text">{{$t('Don\'t have a license key?')}} <a target="_blank"
                                                                                        :href="licenseData.purchase_url" class="wpsr-license-purchase-link">{{$t('Grab Your Pro License Here')}}</a></p>
                        </div>
                    </div>
                    <p v-if="errorMessage" class="text-align-center wpsr-pb-20" style="color: red;" v-html="errorMessage"></p>
                </div>
                <div class="wpsr-setting-content-area" v-else>
                  <div class="wpsr-connection-upgrade-to-pro">
                    <div class="wpsr-connection-upgrade-message">
                      <h2>Upgrade to WP Social Ninja Pro and unlock all the features</h2>
                      <p>Get access to all premium features, priority support, and regular updates with our Pro version.</p>
                      <a :href="pro_purchase_url" class="wpsr-upgrade-btn wpsr-extend-btn" target="_blank">
                        <span class="wpsr-crown-icon"><ProCrownIcon :width="20" :height="20"/></span> Upgrade to Pro
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
import { Refresh, Lock, CircleCheck } from '@element-plus/icons-vue';
export default {
    name: 'LicenseSettings',
    components: {
        PermissionsNotification, 
        ProCrownIcon,
        Refresh,
        Lock,
        CircleCheck
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
    methods: {
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
                this.$notify.error('Please provide a license key');
                this.errorMessage = 'Please provide a license key';
                return;
            }

            this.verifying = true;

            this.errorMessage = '';
            this.$post('settings/license', {
                license_key: this.licenseKey
            })
                .then(response => {
                    this.licenseData = response.license_data;
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
                        errorMessage = 'Something is wrong!';
                    }

                    this.errorMessage = errorMessage;

                    this.handleError(errorResponse);
                })
                .always(() => {
                    this.verifying = false;
                });
        },
        deactivateLicense() {
            this.verifying = true;
            this.$del('settings/license')
                .then(response => {
                    this.licenseData = response.license_data;
                    this.handleSuccess(response.message);
                })
                .catch((errors) => {
                    this.handleError(errors)
                })
                .always(() => {
                    this.verifying = false;
                });
        }
    },
    mounted() {
        this.getSettings();
    }
}
</script>
