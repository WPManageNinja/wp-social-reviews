export const settingsMixin = {
    methods: {
        get(platform) {
            this.loading = true;
            this.platform = platform;

            this.$get(`settings`, {
                platform: platform
            }).then(response => {
                if (response.data) {
                    this.allGlobalSettings = response.data.settings;
                    // Ensure expiration is a number so el-select can match it against numeric option values
                    if (this.allGlobalSettings && this.allGlobalSettings.global_settings && this.allGlobalSettings.global_settings.expiration) {
                        this.allGlobalSettings.global_settings.expiration = parseInt(this.allGlobalSettings.global_settings.expiration, 10);
                    }
                    this.is_enabled_platform = response.data.settings.global_settings ? response.data.settings.global_settings.is_enabled_platform : false;
                    
                    // Populate available templates for WooCommerce and FluentCart platforms
                    if ((platform === 'woocommerce' || platform === 'fluent-cart') && response.data.templates) {
                        this.availableTemplates = Object.keys(response.data.templates).map(key => ({
                            value: key,
                            label: response.data.templates[key]
                        }));
                    }
                } else {
                    this.allGlobalSettings = false;
                    this.is_enabled_platform = false;
                }
            }).catch((errors) => {
                this.handleError(errors);
            }).always(() => {
                this.loading = false;
            });
        },
        update(platform, data) {
            this.loading = true;
            this.$put(`settings`, {
                platform: platform,
                settings: JSON.stringify(data)
            }).then(response => {
                if (response.data) {
                    this.get(platform);
                    this.handleSuccess(response.data.message);
                }
            }).catch((errors) => {
                this.handleError(errors);
            }).always(() => {
                this.loading = false;
            });
        }
    }
}