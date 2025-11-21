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
            // Check if businessInfo has any platforms
            const platformKeys = Object.keys(businessInfo);
            if (platformKeys.length > 0) {
                // Access the first platform using the first key
                const firstPlatform = businessInfo[platformKeys[0]];
                return firstPlatform.platform_name; // Return the platform name
            }
            return ''; // Return an empty string if no platforms are found
        },
        filterPlatformName(platforms) {
            const uniquePlatforms = new Map(); // Use a Map to store unique platforms

            // Iterate over the platforms object
            for (const key in platforms) {
                const platform = platforms[key];

                // Skip custom platforms
                if (platform.platform_name === 'custom') {
                    continue;
                }

                // Check if the platform name is already in the Map
                if (!uniquePlatforms.has(platform.platform_name)) {
                    uniquePlatforms.set(platform.platform_name, platform); // Store the platform if it's unique
                }else {
                    // If platform already exists, check if current one has logo and existing doesn't
                    const existingPlatform = uniquePlatforms.get(platform.platform_name);
                    if (platform.logo && platform.logo !== '' && (!existingPlatform.logo || existingPlatform.logo === '')) {
                        uniquePlatforms.set(platform.platform_name, platform); // Replace with platform that has logo
                    }
                }
            }

            // Convert the Map values back to an array
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
                review.platform_name === 'trustpilot'||
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