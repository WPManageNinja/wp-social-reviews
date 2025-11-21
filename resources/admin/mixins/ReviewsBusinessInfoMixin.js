import { reviewsMixin } from './reviewsMixin';

export const ReviewsBusinessInfoMixin = {
    mixins: [reviewsMixin],
    computed: {
        hasBusinessInfo() {
            return Object.entries(this.businessInfo).length > 0;
        },
        isSinglePlatform() {
            return this.businessInfo.total_platforms === 1;
        },
        isSingleBusiness() {
            return this.businessInfo.total_business === 1 &&
                Object.keys(this.businessInfo.platforms).length;
        },
        isMultipleBusiness() {
            return this.businessInfo.total_business > 1;
        },
        firstPlatformUrl() {
            const firstKey = Object.keys(this.businessInfo.platforms)[0];
            return firstKey ? this.businessInfo.platforms[firstKey].url : '';
        },
        availablePlatforms() {
            return this.filterPlatformName(this.businessInfo.platforms)
                .filter(platform => platform.url && platform.url.length);
        },
        singlePlatformName() {
            if (!this.isSinglePlatform || !this.businessInfo.platforms || !this.businessInfo.platforms[0]) return '';
            const firstPlatform = this.businessInfo.platforms[0];
            const firstKey = Object.keys(firstPlatform)[0];
            return firstPlatform[firstKey] && firstPlatform[firstKey].platform_name || '';
        }
    },
    methods: {
        shouldShowCustomSourcesIcon(){
            return this.isMultipleBusiness &&
                this.badgeSettings.template === 'custom_sources' &&
                this.badgeSettings.display_platform_icon === 'true';
        },
        shouldShowPlatformIcon(platform, type = null) {
            if (type) {
                return this.isSinglePlatform &&
                    this.badgeSettings.template === type &&
                    this.businessInfo.platforms &&
                    this.badgeSettings.display_platform_icon === 'true';
            }
            return platform && platform.url && platform.url.length && this.show_platform_icon(platform.platform_name, this.metaStyles);
        },
        getPlatformIcon(size, platformName = null) {
            const name = platformName || this.businessInfo.platform_name;
            return this.get_platform_icon(name, size);
        },
        getCustomSourcesPlatformIcon(businessInfo){
            if (businessInfo && businessInfo.platforms) {
                for (const [key, platformData] of Object.entries(businessInfo.platforms)) {
                    if (platformData && platformData.platform_name !== 'custom' && platformData.logo) {
                        return platformData.logo;
                    }
                }
            }
        },
        getPlatformIconUrl(platform) {
            return platform.product_url ||
                `${this.assets_url}/images/icon/icon-${platform.platform_name}-small.png`;
        },
        formatPlatformName(name) {
            return this.$getFirstWord(name, true);
        }
    }
};