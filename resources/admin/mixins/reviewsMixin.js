export const reviewsMixin = {
    data(){
        return{

        }
    },
    methods: {
        convertWpToMomentFormat(str) {
            if(!str) return str;
            let conversions = {
                'd': 'DD',
                'D': 'ddd',
                'j': 'D',
                'l': 'dddd',
                'N': 'E',
                'S': 'o',
                'w': 'e',
                'z': 'DDD',
                'W': 'W',
                'F': 'MMMM',
                'm': 'MM',
                'M': 'MMM',
                'n': 'M',
                't': '',
                'L': '',
                'o': 'YYYY',
                'Y': 'YYYY',
                'y': 'YY',
                'a': 'a',
                'A': 'A',
                'B': '',
                'g': 'h',
                'G': 'H',
                'h': 'hh',
                'H': 'HH',
                'i': 'mm',
                's': 'ss',
                'u': 'SSS',
                'e': 'zz',
                'I': '',
                'O': '',
                'P': '',
                'T': '',
                'Z': '',
                'c': '',
                'r': '',
                'U': 'X',
            };
            return str.replace(/[A-Za-z]+/g, (match) => {
                return conversions[match] || match;
            });
        },
        getPlatformName(businessInfo) {
            const platformKeys = Object.keys(businessInfo);
            if (platformKeys.length > 0) {
                const firstPlatform = businessInfo[platformKeys[0]];
                return firstPlatform.platform_name;
            }
            return '';
        },
        filterPlatformName(platforms) {
            const uniquePlatforms = new Map();

            for (const key in platforms) {
                const platform = platforms[key];

                if (platform.platform_name === 'custom') {
                    continue;
                }

                if (!uniquePlatforms.has(platform.platform_name)) {
                    uniquePlatforms.set(platform.platform_name, platform);
                }else {
                    // Prefer the entry that has a logo.
                    const existingPlatform = uniquePlatforms.get(platform.platform_name);
                    if (platform.logo && platform.logo !== '' && (!existingPlatform.logo || existingPlatform.logo === '')) {
                        uniquePlatforms.set(platform.platform_name, platform);
                    }
                }
            }

            return Array.from(uniquePlatforms.values());
        },
        allPlatforms(platforms) {
            return Object.values(platforms);
        },
        getReviewerIconImage(review) {
            return (review.platform_name === 'ai' &&
            this.template_meta.ai_summary.display_ai_summary_icon === false) ? false: this.template_meta.reviewer_image;
        },
        shouldShowVerifiedBadge(review){
            if(
                review.platform_name !== 'ai' ||
                review.platform_name === 'google'||
                review.platform_name === 'airbnb'||
                review.platform_name === this.appVars.tp_slug||
                review.platform_name === 'yelp'||
                review.platform_name === 'tripadvisor'||
                review.platform_name === 'aliexpress'||
                review.platform_name === 'booking.com'||
                review.platform_name === 'facebook'||
                review.platform_name === 'testimonial'||
                review.platform_name === 'woocommerce'
            ){
                return this.template_meta.enable_verified_badge;
            }

            return 'false';
        },
        externalLink(review){
            if(review.platform_name === 'ai'){
                return false;
            }
            return this.template_meta.enableExternalLink
        },
        customLogoSrc(sourceId, businessInfo) {
            return sourceId && businessInfo && businessInfo.platforms && businessInfo.platforms[sourceId]?.logo !== '' ? businessInfo.platforms[sourceId]?.logo : '';
        }
    },
    computed:{
        reponsiveClasses(){
            return this.template_meta.templateType === 'slider' && this.has_pro ? 'swiper-slide' : 'wpsr-col-'+this.template_meta.responsive_column_number.desktop + ' wpsr-col-sm-'+this.template_meta.responsive_column_number.tablet + ' wpsr-col-xs-'+this.template_meta.responsive_column_number.mobile;
        }
    }
}