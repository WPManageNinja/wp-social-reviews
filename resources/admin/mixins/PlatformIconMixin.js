import { DarkModeMixin } from './DarkModeMixin';

/**
 * Platform icon URLs and markup, with dark-mode variants.
 */
export const PlatformIconMixin = {
    mixins: [DarkModeMixin],

    computed: {
        assetsUrl() {
            return this.assets_url || this.appVars?.assets_url || window.WPSocialReviewsAdmin?.assets_url || '';
        }
    },

    methods: {
        /**
         * @param {string} platform - Platform name (e.g., 'facebook', 'instagram', 'tiktok')
         * @param {string} size - Icon size ('small' or 'large')
         * @param {string} customUrl - Custom icon URL (optional)
         * @returns {string} Icon URL
         */
        getPlatformIcon(platform, size = 'small', customUrl = null) {
            let normalizedPlatform = this.normalizePlatformName(platform);

            // Only these platforms ship a dark icon.
            if (this.isDarkMode) {
                const platformsWithDarkIcons = ['facebook', 'amazon'];
                if (platformsWithDarkIcons.includes(normalizedPlatform)) {
                    const sizePrefix = size === 'small' ? '-small' : '';
                    const darkIconPath = `${this.assetsUrl}/images/icon/icon-${normalizedPlatform}${sizePrefix}-dark.png`;
                    return darkIconPath;
                }
            }

            // customUrl comes from the backend, already resolved.
            if (customUrl) {
                return customUrl;
            }

            // Custom platforms carry their logo in business_info (ReviewsEditor). Read it via
            // hasOwnProperty so components without it don't trigger Vue warnings.
            let businessInfo = null;
            try {
                const data = this.$data || {};
                if (Object.prototype.hasOwnProperty.call(data, 'business_info')) {
                    businessInfo = data.business_info;
                } else {
                    const props = this.$props || {};
                    if (Object.prototype.hasOwnProperty.call(props, 'business_info')) {
                        businessInfo = props.business_info;
                    }
                }
            } catch (e) {
            }
            
            if (businessInfo) {
                for (const [key, platformData] of Object.entries(businessInfo)) {
                    if (platformData && platformData.platform_name === normalizedPlatform && platformData.logo) {
                        return platformData.logo;
                    }
                }
            }

            return this.getRegularIcon(normalizedPlatform, size);
        },
        
        /**
         * @param {string} platform - Platform name
         * @param {string} cssClass - CSS class for the image (optional)
         * @returns {string} HTML string
         */
        getPlatformIconHtml(platform, cssClass = 'wpsr_platfrom_mini') {
            const iconUrl = this.getPlatformIcon(platform);
            return `<img src="${iconUrl}" class="${cssClass}" alt="${platform}" />`;
        },
        
        /**
         * @param {string} platforms - Comma-separated platform names
         * @param {string} cssClass - CSS class for the wrapper (optional)
         * @returns {string} HTML string
         */
        getPlatformIconsHtml(platforms, cssClass = 'wpsr_platforms') {
            if (!platforms) {
                return '';
            }
            
            const platformsArray = platforms.split(',').map(item => item.trim());
            
            let wrapperClass = cssClass;
            if (platformsArray.length > 1) {
                wrapperClass += ' wpsr_has_platform_multi';
            }
            
            let html = `<span class="${wrapperClass}">`;
            platformsArray.forEach(platform => {
                html += this.getPlatformIconHtml(platform);
            });
            
            return html + '</span>';
        },
        
        /**
         * @param {string} platform - Platform name
         * @returns {string} Normalized platform name
         */
        normalizePlatformName(platform) {
            const platformMap = {
                facebook_feed: 'facebook',
                booking: 'booking.com'
            };
            
            return platformMap[platform] || platform;
        },
        

        
        /**
         * Light-mode icon URL.
         * @param {string} platform - Normalized platform name
         * @param {string} size - Icon size
         * @returns {string} Regular icon URL
         */
        getRegularIcon(platform, size = 'small') {
            if (window.WPSocialReviewsAdmin?.platforms_cards) {
                const platformCards = window.WPSocialReviewsAdmin.platforms_cards;
                const match = platformCards.find(item => item.platform === platform);
                if (match?.image) {
                    return match.image;
                }
            }

            if (window.WPSocialReviewsAdmin?.brand_icons) {
                const icons = window.WPSocialReviewsAdmin.brand_icons;
                if (icons[platform]) {
                    return icons[platform];
                }
            }

            if(platform === 'allinone'){
                return this.assetsUrl + '/images/icon/icon-allinone-small.png';

            }

            if(size !== '' && window.WPSocialReviewsAdmin?.brand_icons && window.WPSocialReviewsAdmin.brand_icons[platform]) {
                const sizePrefix = size === 'small' ? '-small' : '';
                return `${this.assetsUrl}/images/icon/icon-${platform}${sizePrefix}.png`;
            }

            return this.assets_url + '/images/icon/icon-custom-platform.svg';
        },
        
        /**
         * Called by DarkModeMixin.
         */
        onDarkModeChange(isDarkMode) {
            this.$forceUpdate();
        }
    }
};
