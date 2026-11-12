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
        this.applyFilters = applyFilters;
        this.addFilter = addFilter;
        this.addAction = addAction;
        this.doAction = doAction;
        this.$rest = Rest;
        this.appVars = window.WPSocialReviewsAdmin;
        this.assets_url = this.appVars.assets_url;
        this.assets_url_pro = this.appVars.assets_url_pro;
        
        this.events = {};
        
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
        this.escapeHtmlAttr = this.escapeHtmlAttr.bind(this);
        this.escapeHtml = this.escapeHtml.bind(this);
        this.sanitizeHtml = this.sanitizeHtml.bind(this);
        this.generateURLsFromHashTag = this.generateURLsFromHashTag.bind(this);
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
        this.ratingQualityLabel = this.ratingQualityLabel.bind(this);
        this.platformDisplayName = this.platformDisplayName.bind(this);
        this.platformIconsFor = this.platformIconsFor.bind(this);

        this.$on = this.$on.bind(this);
        this.$off = this.$off.bind(this);
        this.$emit = this.$emit.bind(this);

        window.WPSocialReviewsAdmin.$on = this.$on;
        window.WPSocialReviewsAdmin.$off = this.$off;
        window.WPSocialReviewsAdmin.$emit = this.$emit;
    }

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
        let message = 'Something is wrong when doing ajax request! Please try again';
        if (error && error.responseJSON && error.responseJSON.message) {
            message = error.responseJSON.message;
        } else if (error && error.responseText) {
            message = error.responseText;
        }
        ElNotification.error({
            message: message
        });
    }

    $t(str, ...args) {
        if (!str) return '';
        
        const i18n = window.WPSocialReviewsAdmin?.i18n || {};
        let translation = i18n[str] || str;
        
        if (args.length > 0) {
            // sprintf rules: %n$s reads argument n, so translators can reorder; bare %s/%d read in order.
            let wpsrNextArg = 0;
            translation = translation.replace(/%([1-9])\$[sd]|%[sd]/g, (match, position) => {
                const value = position ? args[position - 1] : args[wpsrNextArg++];
                return value === undefined || value === null ? '' : value;
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
     * Escape a value for use inside an HTML attribute.
     *
     * @param {string} text - Text to escape
     * @returns {string} Escaped text safe for HTML attributes
     */
    escapeHtmlAttr(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /**
     * Escape text so any HTML in it shows as plain text.
     *
     * @param {string} text - Text to escape
     * @returns {string} Escaped text with all HTML converted to entities
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Allowed tags: img, p, span, br, strong, em. Script content, event handlers,
     * style attributes and unsafe URLs are dropped.
     *
     * @param {string} html - HTML content to sanitize
     * @returns {string} Sanitized HTML with only safe tags and attributes
     */
    sanitizeHtml(html) {
        if (!html) return '';

        // Remove obfuscated script-like constructs early
        html = String(html).replace(/<\s*scr[^>]*>/ig, function(m){ return m.replace('<','&lt;'); });

        // Strip inline event handlers and style attributes to prevent execution when parsing
        html = html.replace(/\s*on\w+\s*=\s*(?:"[^"]*"|'[^']*'|[^>\s]+)/ig, '');
        html = html.replace(/\s*on\s+\w+\s*=\s*(?:"[^"]*"|'[^']*'|[^>\s]+)/ig, '');
        html = html.replace(/\s*style\s*=\s*(?:"[^"]*"|'[^']*'|[^>\s]+)/ig, '');

        // Park src/href in data-* attributes so nothing loads while we parse.
        html = html.replace(/\s*(src|href)\s*=\s*(?:"([^\"]*)"|'([^']*)'|([^>\s]+))/ig, function(match, p1, g1, g2, g3){
            var val = g1 || g2 || g3 || '';
            val = val.replace(/"/g, '&quot;');
            return ' data-wpsr-' + p1 + '="' + val + '"';
        });

        // A <template>'s content is inert, so the parse below cannot load
        // resources or fire handlers regardless of what the regexes above missed.
        const temp = document.createElement('template');
        temp.innerHTML = html;

        const allowedTags = ['IMG', 'P', 'SPAN', 'BR', 'STRONG', 'EM'];
        const discardContentTags = ['SCRIPT', 'STYLE', 'IFRAME', 'NOSCRIPT'];
        const allowedAttributes = {
            'IMG': ['src', 'alt', 'title', 'width', 'height', 'draggable', 'role', 'class'],
            'SPAN': ['class', 'aria-label', 'tabindex'],
            'P': ['class']
        };

        function isSafeUrl(attrName, value) {
            if (!value) return false;
            const v = value.trim();
            const lower = v.toLowerCase();

            if (lower.startsWith('javascript:') || lower.startsWith('vbscript:')) return false;

            if (lower.startsWith('data:')) {
                return /^data:image\/(png|jpe?g|gif);base64,[a-z0-9+/=]+$/i.test(v);
            }

            if (lower.startsWith('http:') || lower.startsWith('https:') || lower.startsWith('//') || v.startsWith('/')) return true;
            if (!/^[a-z0-9+\-.]+:/i.test(v)) return true;

            return false;
        }

        let that = this;
        function cleanNode(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                return that.escapeHtml(node.nodeValue);
            }

            if (node.nodeType === Node.ELEMENT_NODE) {
                const tagName = node.tagName.toUpperCase();

                if (discardContentTags.includes(tagName)) {
                    return '';
                }

                if (!allowedTags.includes(tagName)) {
                    let textContent = '';
                    for (let child of node.childNodes) {
                        textContent += cleanNode(child);
                    }
                    return textContent;
                }

                const tagLower = node.tagName.toLowerCase();
                let elementHTML = `<${tagLower}`;

                const allowed = allowedAttributes[tagName] || [];

                for (let attr of Array.from(node.attributes)) {
                    let name = attr.name.toLowerCase();
                    let value = attr.value;

                    // Restore the parked src/href; the URL check below still applies.
                    if (name === 'data-wpsr-src') {
                        name = 'src';
                    }
                    if (name === 'data-wpsr-href') {
                        name = 'href';
                    }

                    if (name.startsWith('on')) continue;
                    if (!allowed.includes(name)) continue;

                    if (name === 'src' || name === 'href') {
                        if (!isSafeUrl(name, value)) continue;
                    }

                    if (value && value.toLowerCase().includes('javascript:')) continue;

                    elementHTML += ` ${name}="${that.escapeHtmlAttr(value)}"`;
                }

                if (tagName === 'IMG' || tagName === 'BR') {
                    elementHTML += ' />';
                    return elementHTML;
                }

                elementHTML += '>';
                for (let child of node.childNodes) {
                    elementHTML += cleanNode(child);
                }
                elementHTML += `</${tagLower}>`;
                return elementHTML;
            }

            return '';
        }

        let content = '';
        for (let child of temp.content.childNodes) {
            content += cleanNode(child);
        }

        return content;
    }

    /**
     * Convert hashtags to clickable links
     * Example: "#javascript" becomes <a href="https://facebook.com/hashtag/javascript">#javascript</a>
     *
     * @param {string} str - Text containing hashtags
     * @param {string} url - Base URL for hashtag links
     * @returns {string} Text with hashtags converted to links
     */
    generateURLsFromHashTag(str, url) {
        if (!str) return '';

        return str.replace(/#[^\s!@#$%^&*()=+\/,\[{\]};:'"?><]+/g, (hash) => {
            let tag = hash.replace("#", "");
            let escapedUrl = this.escapeHtmlAttr(url + tag);
            let escapedHash = this.escapeHtml(hash);
            return '<a href="' + escapedUrl + '" target="_blank">' + escapedHash + '</a>';
        });
    }

    /**
     * Convert plain text URLs to clickable links
     *
     * @param {string} str - Text containing URLs
     * @returns {string} Text with URLs converted to links
     */
    generateURLsFromText(str) {
        if (!str) return '';

        return str.replace(/[A-Za-z]+:\/\/[A-Za-z0-9-_]+\.[A-Za-z0-9-_:%&~\?\/.=]+/g, (url) => {
            let escapedUrl = this.escapeHtmlAttr(url);
            let escapedText = this.escapeHtml(url);
            return '<a href="' + escapedUrl + '" target="_blank">' + escapedText + '</a>';
        });
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

    /**
     * Extract content based on language preference
     * @param {string} content - Content to process
     * @param {string} contentLanguage - Language preference ('translated_by_google', 'original', or default)
     * @returns {string} Processed content
     */
    $contentLanguage(content, contentLanguage) {
        if (!content) return '';

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
            return content || '';
        }
    }

    /**
     * $contentLanguage() output, sanitized for v-html.
     * @param {string} content - Content to process
     * @param {string} contentLanguage - Language preference
     * @returns {string} Escaped content
     */
    $safeContentLanguage(content, contentLanguage) {
        if (!content) return '';
        const result = this.$contentLanguage(content, contentLanguage);
        return this.sanitizeHtml(result) || '';
    }

    /**
     * Add Read More/Less button to content
     * @param {string} content - Content to process
     * @param {number} length - Word limit
     * @param {string} contentLanguage - Language preference
     * @returns {string} Processed content with Read More/Less buttons
     */
    $addReadMoreBtn(content, length, contentLanguage) {
        if (!content) return '';

        content = this.$contentLanguage(content, contentLanguage);

        if (!content) return '';

        content = this.sanitizeHtml(content);

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
            return addContent || '';
        } else {
            return content || '';
        }
    }

    /**
     * Truncate HTML content by word count while preserving safe HTML structure
     * @param {string} html - HTML content to truncate
     * @param {number} wordLimit - Maximum number of words
     * @returns {string} Truncated HTML with only safe tags
     */
    truncateHTML(html, wordLimit) {
        if (!html) return '';
        if (!wordLimit) return html;

        let div = document.createElement('div');
        div.innerHTML = html;

        let result = '';
        let wordCount = 0;

        // No A: links are dropped.
        const allowedTags = ['P', 'BR', 'SPAN'];

        const allowedAttributes = {
            'SPAN': ['class']
        };

        const processNode = (node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                let words = node.nodeValue.split(/\s+/);

                if (wordCount + words.length > wordLimit) {
                    result += words.slice(0, wordLimit - wordCount).join(" ");
                    wordCount = wordLimit;
                } else {
                    result += node.nodeValue;
                    wordCount += words.length;
                }
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                const tagName = node.tagName.toUpperCase();

                // A disallowed tag keeps only its text.
                if (!allowedTags.includes(tagName)) {
                    for (let child of node.childNodes) {
                        if (wordCount < wordLimit) {
                            processNode(child);
                        }
                    }
                    return;
                }

                let elementHTML = `<${node.tagName.toLowerCase()}`;

                const allowed = allowedAttributes[tagName] || [];
                for (let attr of node.attributes) {
                    if (allowed.includes(attr.name.toLowerCase())) {
                        // Extra check for href to prevent javascript: URLs
                        if (attr.name.toLowerCase() === 'href') {
                            const href = attr.value.trim().toLowerCase();
                            if (href.startsWith('javascript:') || href.startsWith('data:') || href.startsWith('vbscript:')) {
                                continue;
                            }
                        }
                        const escapedValue = this.escapeHtmlAttr(attr.value);
                        elementHTML += ` ${attr.name}="${escapedValue}"`;
                    }
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

        return result || '';
    }

    get_platform_icon(platform, type = '') {
        let assets_url = platform === this.appVars.tp_slug ? this.assets_url_pro : this.assets_url + '/images/icon/';
        let icon_name = 'icon-' + platform + '.png';
        if (type === 'small') {
            icon_name = 'icon-' + platform + '-small.png';
        }
        // Like the bundled-file part of Helper::platformIcon(). Custom logos and icon filters are not checked here.
        if (platform !== this.appVars.tp_slug) {
            const wpsrHiddenPlatforms = this.appVars.platforms_without_icon;
            const wpsrBundledIcons = this.appVars.bundled_platform_icons;
            if ((Array.isArray(wpsrHiddenPlatforms) && wpsrHiddenPlatforms.includes(platform)) ||
                (Array.isArray(wpsrBundledIcons) && !wpsrBundledIcons.includes(icon_name))) {
                return '';
            }
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

    // Short quality word for a rating (Excellent/Great/Average/Poor/Bad), shared by badge and
    // notification templates; keep thresholds in sync with Helper::ratingQualityLabel().
    ratingQualityLabel(rating) {
        const ratingValue = parseFloat(rating);
        if (!ratingValue || ratingValue <= 0) {
            return '';
        }
        if (ratingValue >= 4.5) {
            return this.$t('Excellent');
        }
        if (ratingValue >= 3.9) {
            return this.$t('Great');
        }
        if (ratingValue >= 2.9) {
            return this.$t('Average');
        }
        if (ratingValue >= 2.1) {
            return this.$t('Poor');
        }
        return this.$t('Bad');
    }

    // Human-readable platform name, shared by notification and other templates; keep this map
    // in sync with Helper::platformDisplayName().
    platformDisplayName(platform_name, fallbackName = '') {
        const labels = {
            google: this.$t('Google'),
            facebook: this.$t('Facebook'),
            airbnb: this.$t('Airbnb'),
            yelp: this.$t('Yelp'),
            tripadvisor: this.$t('Tripadvisor'),
            amazon: this.$t('Amazon'),
            aliexpress: this.$t('AliExpress'),
            'booking.com': this.$t('Booking.com'),
            woocommerce: this.$t('WooCommerce'),
            'fluent-cart': this.$t('FluentCart'),
            fluent_forms: this.$t('Fluent Forms'),
            testimonial: this.$t('Testimonial'),
            custom: fallbackName || this.$t('Custom'),
        };
        if (labels[platform_name]) {
            return labels[platform_name];
        }
        return platform_name ? platform_name.charAt(0).toUpperCase() + platform_name.slice(1) : '';
    }

    // {name, icon} pairs for an aggregate's contributing platforms, shared by Badge1 (avatar-stack/score-strip)
    // and Notification's Rating Pulse. A source's own logo first (Fluent Forms has no platform icon), then
    // the platform icon file; multi-platform skips sourceless entries. TP is opt-in (displayTpBrand),
    // same rule as Helper::hideLogo() — their brand terms require explicit permission. Keep in sync with
    // Pro's ReviewsTemplateHandlerPro::badgePlatformIcons().
    platformIconsFor(businessInfo, showIcons, displayTpBrand) {
        if (!showIcons || !businessInfo) {
            return [];
        }
        const hasIconFile = name => this.appVars.platforms_cards.some(card => card.platform === name);
        const multiple = businessInfo.total_platforms > 1;
        const icons = {};
        Object.entries(businessInfo.platforms || {}).forEach(([sourceId, platform]) => {
            const name = platform && platform.platform_name;
            if (!name || (name.startsWith('trust') && displayTpBrand !== 'true')) {
                return;
            }
            // Every generic custom source shares platform_name 'custom' — key those by source id,
            // or a second custom source's logo would be dropped as a "duplicate" of the first.
            const key = name === 'custom' ? sourceId : name;
            if (icons[key] || (multiple && !platform.url && !platform.logo)) {
                return;
            }
            const icon = platform.logo || (hasIconFile(name) ? this.get_platform_icon(name, 'small') : '');
            if (icon) {
                // Consumers key their v-for by this name — an unlabeled custom source still needs a
                // unique one, or a second unlabeled source collides back onto the same list key.
                const label = platform.platform_label || (key === name ? name : `${name} ${sourceId}`);
                icons[key] = { label, icon };
            }
        });
        return Object.values(icons).map(({ label, icon }) => ({ name: label, icon }));
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
