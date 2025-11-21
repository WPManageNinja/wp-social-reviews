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
                    this.is_enabled_platform = response.data.settings.global_settings ? response.data.settings.global_settings.is_enabled_platform : false;
                    
                    // Populate available templates for WooCommerce platform
                    if (platform === 'woocommerce' && response.data.templates) {
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