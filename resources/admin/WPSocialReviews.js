// import Vue from './elements';
// import Vue from 'vue';
import { createRouter, createWebHashHistory } from 'vue-router';
import Rest from './Rest';
import { routes } from './router/routes';
import {applyFilters, addFilter, addAction, doAction} from '@wordpress/hooks';
import moment from "moment";
import 'moment-duration-format';
import Clipboard from "clipboard";
import { ElNotification } from 'element-plus';

export default class WPSocialReviews {
    constructor() {
        // Initialize core properties
        this.applyFilters = applyFilters;
        this.addFilter = addFilter;
        this.addAction = addAction;
        this.doAction = doAction;
        this.$rest = Rest;
        this.appVars = window.WPSocialReviewsAdmin;
        this.assets_url = this.appVars.assets_url;
        this.assets_url_pro = this.appVars.assets_url_pro;
        
        // Initialize events
        this.events = {};
        
        // Bind methods to this instance
        this.$get = this.$get.bind(this);
        this.$post = this.$post.bind(this);
        this.$del = this.$del.bind(this);
        this.$put = this.$put.bind(this);
        this.$patch = this.$patch.bind(this);
        this.$t = this.$t.bind(this);
        this.handleError = this.handleError.bind(this);
        this.convertToText = this.convertToText.bind(this);
        this.slugify = this.slugify.bind(this);
        this.ucFirst = this.ucFirst.bind(this);
        this.wpsrdateFormat = this.wpsrDateFormat.bind(this);
        this.handleFullScreen = this.handleFullScreen.bind(this);
        this.checkFullScreen = this.checkFullScreen.bind(this);
        this.hasPermission = this.hasPermission.bind(this);
        this.handleSuccess = this.handleSuccess.bind(this);
        this.handlePro = this.handlePro.bind(this);
        this.clipboard = this.clipboard.bind(this);
        this.isUrl = this.isUrl.bind(this);
        this.nl2br = this.nl2br.bind(this);
        this.parseDuration = this.parseDuration.bind(this);
        this.shortNumberFormat = this.shortNumberFormat.bind(this);
        this.generateURLsFromHashTag = this.generateURLsFromHashTag.bind(this);
        this.escapeHtml = this.escapeHtml.bind(this);
        this.generateURLsFromText = this.generateURLsFromText.bind(this);
        this.numberWithCommas = this.numberWithCommas.bind(this);
        this.$getFirstWord = this.$getFirstWord.bind(this);
        this.$excerpt = this.$excerpt.bind(this);
        this.$trimWords = this.$trimWords.bind(this);
        this.$contentLanguage = this.$contentLanguage.bind(this);
        this.$addReadMoreBtn = this.$addReadMoreBtn.bind(this);
        this.truncateHTML = this.truncateHTML.bind(this);
        this.$showAjaxError = this.$showAjaxError.bind(this);
        this.get_platform_icon = this.get_platform_icon.bind(this);
        this.hasSelectedPlatformMultipleBusiness = this.hasSelectedPlatformMultipleBusiness.bind(this);
        this.show_platform_icon = this.show_platform_icon.bind(this);
        this.convertToPercentage = this.convertToPercentage.bind(this);
        this.ratingIcon = this.ratingIcon.bind(this);

        // Bind event methods
        this.$on = this.$on.bind(this);
        this.$off = this.$off.bind(this);
        this.$emit = this.$emit.bind(this);

        window.WPSocialReviewsAdmin.$on = this.$on;
        window.WPSocialReviewsAdmin.$off = this.$off;
        window.WPSocialReviewsAdmin.$emit = this.$emit;
    }

    // Event handling methods
    $on(event, callback) {
        if (!this.events[event]) {
            this.events[event] = [];
        }
        this.events[event].push(callback);
    }

    $off(event, callback) {
        if (!this.events[event]) return;
        if (!callback) {
            delete this.events[event];
        } else {
            this.events[event] = this.events[event].filter(cb => cb !== callback);
        }
    }

    $emit(event, ...args) {
        if (!this.events[event]) return;
        this.events[event].forEach(callback => callback(...args));
    }

    handleSuccess(message) {
        ElNotification({
            title: 'Success',
            message: message,
            type: 'success',
            position: 'top-right',
            duration: 3000
        });
    }

    handleError(response) {
        if (response.responseJSON) {
            response = response.responseJSON;
        }
        let errorMessage = '';
        if (typeof response === 'string') {
            errorMessage = response;
        } else if (response && response.message) {
            errorMessage = response.message;
        } else {
            errorMessage = this.convertToText(response);
        }
        if (!errorMessage) {
            errorMessage = 'Something is wrong!';
        }
        ElNotification({
            type: 'error',
            title: 'Error',
            message: errorMessage,
            position: 'top-right',
            duration: 3000,
            dangerouslyUseHTMLString: true
        });
    }

    handlePro() {
        let message = 'You need to upgrade to pro to use this feature';
        ElNotification({
            title: 'Pro Required',
            message: message,
            type: 'error',
            position: 'top-right',
            duration: 3000
        });
        return;
    }

    $showAjaxError(error) {
        if (error.responseJSON && error.responseJSON.message) {
            ElNotification.error(error.responseJSON.message);
        } else if (error.responseText) {
            ElNotification.error(error.responseText);
        } else {
            ElNotification.error('Something is wrong when doing ajax request! Please try again');
        }
    }

    $t(str, ...args) {
        if (!str) return '';
        
        const i18n = window.WPSocialReviewsAdmin?.i18n || {};
        let translation = i18n[str] || str;
        
        if (args.length > 0) {
            translation = translation.replace(/%s|%d|%1\$s|%2\$s|%3\$s|%4\$s|%5\$s|%6\$s|%7\$s|%8\$s|%9\$s/g, () => {
                return args.shift() || '';
            });
        }
        
        return translation;
    }

    $get(url, options = {}) {
        return this.$rest.get(url, options);
    }

    $post(url, options = {}) {
        return this.$rest.post(url, options);
    }

    $del(url, options = {}) {
        return this.$rest.delete(url, options);
    }

    $put(url, options = {}) {
        return this.$rest.put(url, options);
    }

    $patch(url, options = {}) {
        return this.$rest.patch(url, options);
    }

    slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^\w\\-]+/g, '')
            .replace(/\\-\\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    convertToText(obj) {
        const string = [];
        if (typeof (obj) === 'object' && (obj.join === undefined)) {
            for (const prop in obj) {
                string.push(this.convertToText(obj[prop]));
            }
        } else if (typeof (obj) === 'object' && !(obj.join === undefined)) {
            for (const prop in obj) {
                string.push(this.convertToText(obj[prop]));
            }
        } else if (typeof (obj) === 'function') {
            // Skip functions
        } else if (typeof (obj) === 'string') {
            string.push(obj)
        }
        return string.join('<br />')
    }
    
    wpsrDateFormat(date, format, date_type = '') {
        if (date_type === 'relative_time') {
            return moment(date, 'ddd MMM DD HH:mm:ss Z YYYY').fromNow();
        }

        if (date_type === 'time_from_now') {
            let current_date = new Date(date);
            return moment(current_date, 'ddd MMM DD HH:mm:ss Z YYYY').fromNow();
        }

        if (date_type === 'human_readable') {
            date = new Date(date);
        }

        if (date_type === 'human_readable_with_time') {
            date = new Date(date);
            return moment(date).format('MMMM DD YYYY HH:mm a');
        }

        if (date_type === 'week_day') {
            return moment(date).format('dddd');
        }

        if (!format) {
            format = 'MMM DD, YYYY';
        }

        let dateString = (date === undefined) ? null : date;
        let dateObj = moment(dateString);
        return dateObj.isValid() ? dateObj.format(format) : null;
    }
    ucFirst(text) {
        if (!text) {
            return text;
        }
        text = text[0].toUpperCase() + text.slice(1).toLowerCase();

        if(text.includes('_')){
            let rx = /\_([a-z])/g;
            return text.replace(rx, function (a, b) {
                return ' ' + b.toUpperCase();
            });
        } else {
            return text;
        }
    }
    handleFullScreen() {
        const $body = jQuery('body');
        let wasFullScreen = $body.hasClass('wpsr_full_screen');
        if (window.localStorage) {
            if (wasFullScreen) {
                window.localStorage.setItem('wpsr_is_full_screen', 'no');
            } else {
                window.localStorage.setItem('wpsr_is_full_screen', 'yes');
            }
        }

        $body.toggleClass('wpsr_full_screen');
        this.checkFullScreen();
    }
    checkFullScreen() {
        if (window.localStorage) {
            if (window.localStorage.getItem('wpsr_is_full_screen') == 'yes') {
                jQuery('body').addClass('wpsr_full_screen');
            }
        }
    }
    hasPermission(permissions) {
        if (typeof permissions === 'string') {
            permissions = [permissions];
        }
        
        if (!permissions || !permissions.length) {
            return true;
        }
    
        if (!this.appVars || !this.appVars.auth || !Array.isArray(this.appVars.auth.permissions)) {
            return false;
        }
        
        return permissions.some(permission => 
            this.appVars.auth.permissions.includes(permission)
        );
    }

    clipboard() {
        if (!window.clipboard) {
            window.clipboard = new Clipboard('.copy');
            window.clipboard.on('success', (e) => {
                let message = this.$t('Copied to Clipboard!')
                this.handleSuccess(message);
            });
        }
    }

    isUrl(s) {
        let regexp = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/
        return regexp.test(s);
    }

    nl2br(str, is_xhtml) {
        if (typeof str === 'undefined' || str === null) {
            return '';
        }
        let breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
        return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
    }

    parseDuration(time) {
        let duration = moment.duration(time);
        return duration.format('h:mm:ss').padStart(4, '0:0');
    }

    shortNumberFormat(number) {
        let symbols = ["", "k", "M", "G", "T", "P", "E"];
        let tier = Math.log10(number) / 3 | 0;
        if (tier === 0) return number;
        let suffix = symbols[tier];
        let scale = Math.pow(10, tier * 3);
        let scaled = number / scale;
        return parseFloat(scaled.toFixed(1)) + suffix;
    }

    /**
     * Escape HTML
     * Converts < > & " ' to HTML entities
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    generateURLsFromHashTag(str, url) {
        if (str) {
            return str.replace(/#[^\s!@#$%^&*()=+\/,\[{\]};:'"?><]+/g, function (hash) {
                let tag = hash.replace("#", "");
                return '<a href="' + url + '' + tag + '" target="_blank">' + hash + '</a>';
            });
        }
    }

    generateURLsFromText(str) {
        if (str) {
            return str.replace(/[A-Za-z]+:\/\/[A-Za-z0-9-_]+\.[A-Za-z0-9-_:%&~\?\/.=]+/g, function (url) {
                return '<a href="' + url + '" target="_blank">' + url + '</a>';
            });
        }
    }

    numberWithCommas(number) {
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    $getFirstWord(str, capitalize = false) {
        let words = str.split('_');
        if (capitalize) {
            return words[0].replace(/\b\w/g, val => val.toUpperCase());
        } else {
            return words[0];
        }
    }

    $excerpt(content) {
        let excerpt = content.substring(0, 100);
        return excerpt ? excerpt + '...' : '';
    }

    $trimWords(text, length, hasPro = false) {
        if((typeof text) === 'number') return text;
        if(!text) return '';
        if (hasPro === false) {
            return text;
        } else {
            let countWord = text.split(" ", length);
            if ((countWord.length > length || countWord.length !== 0) && length !== undefined) {
                if(length > countWord.length) return text;
                return countWord.join(" ") + '...';
            } else {
                return text;
            }
        }
    }

    $contentLanguage(content, contentLanguage) {
        let translated_by_google = content.indexOf('(Translated by Google)');
        let original = content.indexOf('(Original)');
        let contentGoogleArray = translated_by_google !== -1 ? content.split('(Translated by Google)') : content;
        let contentOriginalArray = original !== -1 ? content.split('(Original)') : content;

        if(contentLanguage === 'translated_by_google') {
            if(original === -1) {
                return translated_by_google !== 1 && translated_by_google !== -1 ? contentGoogleArray[1] : content;
            }
            return translated_by_google === 0 ? contentOriginalArray[0].replace('(Translated by Google)', '') : content;
        } else if(contentLanguage === 'original') {
            if(original === -1) {
                return translated_by_google !== 1 && translated_by_google !== -1 ? contentGoogleArray[0] : content;
            }
            return translated_by_google === 0 ? contentOriginalArray[1] : content;
        } else {
            return content;
        }
    }

    /**
     * Safe version of $contentLanguage that escapes HTML for display
     * Use this when rendering with v-html
     */
    $safeContentLanguage(content, contentLanguage) {
        const result = this.$contentLanguage(content, contentLanguage);
        return this.escapeHtml(result);
    }

    $addReadMoreBtn(content, length, contentLanguage) {
        content = this.$contentLanguage(content, contentLanguage);

        // Escape HTML
        content = this.escapeHtml(content);

        content = content.replace(/\s+/g, ' ').trim();
        let countWord = content.split(" ", length);

        if ((countWord.length > length || countWord.length !== 0) && length !== undefined) {
            let excerpt;
            let addContent;
            excerpt = this.truncateHTML(content, length);
            let hideContent = content.substring(excerpt.length, content.length);

            if(content.length > excerpt.length) {
                addContent = excerpt + "<span class='wpsr_add_read_more_slice_content'>" + hideContent + "</span><span class='wpsr_read_more'>" + this.$t('Read More') + "</span><span class='wpsr_read_less'>" + this.$t('Read Less') + "</span>";
            } else {
                addContent = excerpt;
            }
            return addContent;
        } else {
            return content;
        }
    }

    truncateHTML(html, wordLimit) {
        let div = document.createElement('div');
        div.innerHTML = html;

        let result = '';
        let wordCount = 0;

        const processNode = (node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                let words = node.nodeValue.split(/\s+/);

                if (wordCount + words.length > wordLimit) {
                    result += words.slice(0, wordLimit).join(" ");
                    wordCount = wordLimit;
                } else {
                    result += node.nodeValue;
                    wordCount += words.length;
                }
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                let elementHTML = `<${node.tagName.toLowerCase()}`;
                for (let attr of node.attributes) {
                    elementHTML += ` ${attr.name}="${attr.value}"`;
                }
                elementHTML += '>';
                result += elementHTML;

                for (let child of node.childNodes) {
                    if (wordCount < wordLimit) {
                        processNode(child);
                    }
                }

                result += `</${node.tagName.toLowerCase()}>`;
            }
        };

        for (let child of div.childNodes) {
            if (wordCount < wordLimit) {
                processNode(child);
            }
        }

        return this.escapeHtml(result);
    }

    get_platform_icon(platform, type = '') {
        let assets_url = platform === this.appVars.tp_slug ? this.assets_url_pro : this.assets_url + '/images/icon/';
        let icon_name = 'icon-' + platform + '.png';
        if (type === 'small') {
            icon_name = 'icon-' + platform + '-small.png';
        }
        return assets_url + icon_name;
    }

    hasSelectedPlatformMultipleBusiness(filtered_platforms) {
        if (Object.keys(filtered_platforms).length <= 1) {
            return false;
        }
        const platforms = Object.values(filtered_platforms).map(platform => platform.platform_name);
        const hasSamePlatform = platforms.every((val, _, arr) => val === arr[0]);
        return !hasSamePlatform;
    }

    show_platform_icon(platform, template_meta) {
        if (template_meta.display_header_business_logo === true && this.appVars.platforms_cards.some( card => card.platform === platform )) {
            if (platform !== this.appVars.tp_slug && template_meta.display_header_business_logo === true) {
                return true;
            }
            if (platform === this.appVars.tp_slug && template_meta.display_tp_brand === 'true') {
                return true;
            }
        }
        return false;
    }

    convertToPercentage(value) {
        const decimalPart = value - Math.floor(value);
        const percentage = Math.round(decimalPart * 100);
        return percentage + '%';
    }

    ratingIcon(rating) {
        let ratingValue = Number(rating);
        let stars = '';
        for (let i = 0; i < 5; i++) {
            let fillPercentage = '0%';
            const score = ratingValue - i;
            if (score >= 1) {
                fillPercentage = '100%';
            } else if (score > 0) {
                fillPercentage = `${score * 100}%`;
            }

            const filledClass = parseFloat(fillPercentage) > 10 ? 'wpsr-star-background-filled' : 'wpsr-star-background-empty';
            stars += `
                <div class="wpsr-star-container ${filledClass}" style="--wpsr-review-star-fill: ${fillPercentage};">
                    <div class="wpsr-star-empty"></div>
                    <div class="wpsr-star-filled"></div>
                </div>
            `;
        }
        return stars;
    }
}