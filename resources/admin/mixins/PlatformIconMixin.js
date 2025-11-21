import { DarkModeMixin } from './DarkModeMixin';

/**
 * Platform Icon Mixin
 * 
 * Provides centralized platform icon functionality with dark mode support.
 * This mixin can be used across all Vue components that need to display platform icons.
 */
export const PlatformIconMixin = {
    mixins: [DarkModeMixin],

    computed: {
        /**
         * Get assets URL from various possible sources
         */
        assetsUrl() {
            return this.assets_url || this.appVars?.assets_url || window.WPSocialReviewsAdmin?.assets_url || '';
        }
    },

    methods: {
        /**
         * Get platform icon URL with dark mode support
         * 
         * @param {string} platform - Platform name (e.g., 'facebook', 'instagram', 'tiktok')
         * @param {string} size - Icon size ('small' or 'large')
         * @param {string} customUrl - Custom icon URL (optional)
         * @returns {string} Icon URL
         */
        getPlatformIcon(platform, size = 'small', customUrl = null) {
            let normalizedPlatform = this.normalizePlatformName(platform);

            // If dark mode is enabled, try to get dark version first (only for platforms that have dark versions)
            if (this.isDarkMode) {
                const platformsWithDarkIcons = ['facebook', 'amazon'];
                if (platformsWithDarkIcons.includes(normalizedPlatform)) {
                    const sizePrefix = size === 'small' ? '-small' : '';
                    const darkIconPath = `${this.assetsUrl}/images/icon/icon-${normalizedPlatform}${sizePrefix}-dark.png`;
                    return darkIconPath;
                }
            }

            // If custom URL is provided, return it (backend provides the correct icon)
            if (customUrl) {
                return customUrl;
            }

            // Check for custom platforms in business_info (for ReviewsEditor)
            // Safely access business_info to avoid Vue warnings when not defined
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
                // Silently catch any errors
            }
            
            if (businessInfo) {
                for (const [key, platformData] of Object.entries(businessInfo)) {
                    if (platformData && platformData.platform_name === normalizedPlatform && platformData.logo) {
                        return platformData.logo;
                    }
                }
            }

            // Fallback to regular icon
            return this.getRegularIcon(normalizedPlatform, size);
        },
        
        /**
         * Get platform icon HTML with dark mode support
         * 
         * @param {string} platform - Platform name
         * @param {string} cssClass - CSS class for the image (optional)
         * @returns {string} HTML string
         */
        getPlatformIconHtml(platform, cssClass = 'wpsr_platfrom_mini') {
            const iconUrl = this.getPlatformIcon(platform);
            return `<img src="${iconUrl}" class="${cssClass}" alt="${platform}" />`;
        },
        
        /**
         * Get multiple platform icons HTML
         * 
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
         * Normalize platform name for consistent handling
         * 
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
         * Get regular (light mode) icon URL
         * 
         * @param {string} platform - Normalized platform name
         * @param {string} size - Icon size
         * @returns {string} Regular icon URL
         */
        getRegularIcon(platform, size = 'small') {
            // Check if we have platform cards data
            if (window.WPSocialReviewsAdmin?.platforms_cards) {
                const platformCards = window.WPSocialReviewsAdmin.platforms_cards;
                const match = platformCards.find(item => item.platform === platform);
                if (match?.image) {
                    return match.image;
                }
            }

            // Check brand icons
            if (window.WPSocialReviewsAdmin?.brand_icons) {
                const icons = window.WPSocialReviewsAdmin.brand_icons;
                if (icons[platform]) {
                    return icons[platform];
                }
            }

            if(platform === 'allinone'){
                return this.assetsUrl + '/images/icon/icon-allinone-small.png';

            }

            // Fallback to constructed path for standard platforms
            if(size !== '' && window.WPSocialReviewsAdmin?.brand_icons && window.WPSocialReviewsAdmin.brand_icons[platform]) {
                const sizePrefix = size === 'small' ? '-small' : '';
                return `${this.assetsUrl}/images/icon/icon-${platform}${sizePrefix}.png`;
            }

            return this.assets_url + '/images/icon/icon-custom-platform.svg';
        },
        
        /**
         * Handle dark mode changes - called by DarkModeMixin
         */
        onDarkModeChange(isDarkMode) {
            // Force re-render when dark mode changes
            this.$forceUpdate();
        }
    }
};
