jQuery(document).ready(function($) {
    "use strict";
    
    let chatParams = `scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=800,height=500,left=100,top=100`;
    let popup_target = $('.wpsr-fm-chat-wrapper').data('popup-target');
    let selectedChannelName = '';
    let selectedChannelNumber = '';
    let selectedChannelMessageContext = '';
    let selectedChannelMessageContextFormat = '';
    let selectedChannelPageMessageFormat = '';
    let selectedChannelProductInfo = null;
    let prefilledPlatformName = ['whatsapp', 'sms'];
    let wpsrChannelProtocols = {
        'microsoft-teams': ['https:', 'msteams:'],
        phone: ['tel:'],
        sms: ['sms:'],
        email: ['mailto:'],
        wechat: ['weixin:'],
        viber: ['viber:'],
        fluent_forms: []
    };

    function wpsrIsSafeChatUrl(url, channel) {
        if (!url) {
            return false;
        }

        try {
            let wpsrParsedUrl = new URL(url, window.location.href);
            let wpsrAllowedProtocols = wpsrChannelProtocols[channel] || ['http:', 'https:'];

            return wpsrAllowedProtocols.includes(wpsrParsedUrl.protocol);
        } catch (error) {
            return false;
        }
    }

    function wpsrParseProductInfo(value) {
        if (!value) {
            return null;
        }

        if (typeof value === 'object') {
            return value;
        }

        try {
            return JSON.parse(value);
        } catch (error) {
            return null;
        }
    }

    function wpsrGetElementMessageContext($element) {
        let messageContext = $element.attr('data-message-context');

        if (messageContext === 'none') {
            return '';
        }

        return messageContext || '';
    }

    function wpsrGetElementProductInfo($element) {
        return $element.attr('data-product-context-info') || $element.attr('data-woocommerce-product-info');
    }

    function wpsrBuildFormattedContextMessage(format, productInfo) {
        productInfo = wpsrParseProductInfo(productInfo);

        let replacements = {
            '{page_title}': document.title,
            '{page_url}': window.location.href,
            '{page_link}': window.location.href,
            '{product_title}': productInfo ? productInfo.title || '' : '',
            '{product_price}': productInfo ? productInfo.price || '' : '',
            '{product_sku}': productInfo ? productInfo.sku || '' : '',
            '{product_stock}': productInfo ? productInfo.stock || '' : '',
            '{product_url}': productInfo ? productInfo.url || '' : '',
            '{product_link}': productInfo ? productInfo.url || '' : '',
            '{woocommerce_product_title}': productInfo ? productInfo.title || '' : '',
            '{woocommerce_product_price}': productInfo ? productInfo.price || '' : '',
            '{woocommerce_product_sku}': productInfo ? productInfo.sku || '' : '',
            '{woocommerce_product_stock}': productInfo ? productInfo.stock || '' : '',
            '{woocommerce_product_url}': productInfo ? productInfo.url || '' : '',
            '{woocommerce_product_link}': productInfo ? productInfo.url || '' : '',
            '{fluentcart_product_title}': productInfo ? productInfo.title || '' : '',
            '{fluentcart_product_price}': productInfo ? productInfo.price || '' : '',
            '{fluentcart_product_sku}': productInfo ? productInfo.sku || '' : '',
            '{fluentcart_product_stock}': productInfo ? productInfo.stock || '' : '',
            '{fluentcart_product_url}': productInfo ? productInfo.url || '' : '',
            '{fluentcart_product_link}': productInfo ? productInfo.url || '' : ''
        };

        let contextMessage = format || '';

        Object.keys(replacements).forEach(function(placeholder) {
            contextMessage = contextMessage.split(placeholder).join(replacements[placeholder]);
        });

        return contextMessage.trim();
    }

    function wpsrBuildContextMessage(messageContext, productInfo, contextFormat, pageFormat) {
        productInfo = wpsrParseProductInfo(productInfo);

        if ((messageContext === 'woocommerce_product' || messageContext === 'fluentcart_product') && !productInfo) {
            return wpsrBuildFormattedContextMessage(pageFormat, null);
        }

        return wpsrBuildFormattedContextMessage(contextFormat || pageFormat, productInfo);
    }

    function wpsrAppendContextToMessage(message, messageContext, productInfo, contextFormat, pageFormat) {
        let contextMessage = wpsrBuildContextMessage(messageContext, productInfo, contextFormat, pageFormat);

        if (!contextMessage) {
            return message;
        }

        return message ? message + '\n\n' + contextMessage : contextMessage;
    }

    function wpsrAppendContextToChatUrl(url, channel, messageContext, productInfo, contextFormat, pageFormat) {
        if (channel !== 'whatsapp' || !messageContext) {
            return url;
        }

        try {
            let wpsrParsedUrl = new URL(url, window.location.href);
            let wpsrMessage = wpsrParsedUrl.searchParams.get('text') || '';
            wpsrParsedUrl.searchParams.set('text', wpsrAppendContextToMessage(wpsrMessage, messageContext, productInfo, contextFormat, pageFormat));

            return wpsrParsedUrl.toString();
        } catch (error) {
            return url;
        }
    }

    //open popup window
    $('.wpsr-channels, .wpsr-channels-icons').children('.wpsr-channel-item').each(function (index){
        let $this = $(this);
        let channel = $this.find('.wpsr-channel-btn').data('channel');
        let isPrefilled = $this.find('.wpsr-channel-btn').data('prefilled');
        let $channelButton = $this.find('.wpsr-channel-btn');
        let messageContext = wpsrGetElementMessageContext($channelButton);
        let contextFormat = $channelButton.attr('data-message-context-format');
        let pageFormat = $channelButton.attr('data-page-message-format');
        let productInfo = wpsrGetElementProductInfo($channelButton);
        // stop redirect for fluent forms

        if(channel === 'fluent_forms' || (prefilledPlatformName.includes(channel)  && isPrefilled)){
            // $this.find('.wpsr-channel-btn.'+channel+index).attr('href', "#");
            return;
        }
        let url =  $this.find('.wpsr-channel-btn.'+channel+index).data("chat-url");
        if (!wpsrIsSafeChatUrl(url, channel)) {
            return;
        }
        url = wpsrAppendContextToChatUrl(url, channel, messageContext, productInfo, contextFormat, pageFormat);
        $this.find('.wpsr-channel-btn.'+channel+index).attr('href', url);
        $('.wpsr-channel-item.'+channel+index).on('click', function(event) {
            event.preventDefault();
            if(popup_target) {
                open(url, '', chatParams);
            } else {
                let target = (channel === 'phone' || (prefilledPlatformName.includes(channel) && isPrefilled)) ? '_self' : '_blank';
                open(url, target);
            }
        });
    });

    $('.wpsr-fm-chat-wrapper .wpsr-fm-btn-icon').each(function (){
        let $this = $(this);

        let channel = $this.find('.wpsr-fm-btn').data('channel');
        if(channel === 'fluent_forms'){
            $this.find('.wpsr-fm-btn.'+channel).attr('href', "#");
            return;
        }
        let url =  $this.find('.wpsr-fm-btn.'+channel).data("chat-url");
        if (!wpsrIsSafeChatUrl(url, channel)) {
            return;
        }
        let $chatButton = $this.find('.wpsr-fm-btn.'+channel);
        url = wpsrAppendContextToChatUrl(url, channel, wpsrGetElementMessageContext($chatButton), wpsrGetElementProductInfo($chatButton), $chatButton.attr('data-message-context-format'), $chatButton.attr('data-page-message-format'));
        $this.find('.wpsr-fm-btn.'+channel).attr('href', url);
        $('.wpsr-fm-btn-icon .'+channel).on('click', function(event) {
            event.preventDefault();
            if(popup_target) {
                open(url, '', chatParams);
            } else {
                open(url, '_blank');
            }
        });
    });

    let counter = 0;
    let chatPopupDelay = $('.wpsr-fm-chat-wrapper').data('chat-popup-delay');
    let chatDisplayPopup = $('.wpsr-fm-chat-wrapper').data('chat-display-popup');

    if( chatPopupDelay && chatDisplayPopup){
        counter++;
        $('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box').removeClass('wpsr-fm-chat-box-display');
        setTimeout( function (){
            $('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box').addClass('wpsr-fm-chat-box-display');
            // check is online or offline
            if ($('.wpsr-fm-chat-header').hasClass('wpsr-offline')) {
                $('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box').removeClass('wpsr-fm-chat-box-display');
            }
        }, chatPopupDelay);

        // animate chat body text
        setTimeout(function() {
            $('.wpsr-fm-chat').css({'display': 'none'});
            $('.wpsr-fm-greeting-msg').css({'display': 'block'});
        }, chatPopupDelay+500);
    }

    function toggleFormVisibility() {
        const chatBox = document.querySelector('.wpsr-fm-chat-box');
        const formWrapper = document.querySelector('.wpsr-fluentform-wrapper');

        if (chatBox && formWrapper) {
          formWrapper.style.display = '';
        }
    }

    toggleFormVisibility();

    function fluentFormCheck() {
        const channels = $('.wpsr-channel-item');
        let has_fluent_forms = false;
        let loopUsed = false;
        channels.each(function () {
            loopUsed = true;
            const channelItemName = $(this).data('channel-name');
            if(channelItemName === 'fluent_forms'){
                has_fluent_forms = true;
            }
        });

        if(!has_fluent_forms && loopUsed){
            const formWrapper = document.querySelector('.wpsr-fluentform-wrapper');
            if(formWrapper){
                formWrapper.style.display = 'none';
            }
        }
    }

    function toggleChat() {
        toggleFormVisibility();
        fluentFormCheck()
        $('.wpsr-fm-chat-box, .wpsr-channels-icons').toggleClass('wpsr-fm-chat-box-display');
        // $('.wpsr-fm-chat-wrapper').toggleClass('wpsr-fm-chat-box-active');
        jQuery('.wpsr-fm-chat-bubble').toggleClass('active');
        if (jQuery('.wpsr-fm-chat-bubble').hasClass('active')) {
            jQuery('.wpsr-chat-icons-closee').show();
            // jQuery('.wpsr-fm-bubble-btn img').hide();
        } else {
            jQuery('.wpsr-chat-icons-closee').hide();
            jQuery('.wpsr-fm-bubble-btn img').show();
        }

        if ($('.wpsr-has-fluent-form').length > 0) {
            $('.wpsr-fm-chat-wrapper').toggleClass('wpsr-has-fluent-form');
            $('.wpsr-has-fluent-form .wpsr-fm-chat-box .wpsr-fm-chat-room .wpsr-fm-btn-icon a').css({'display': 'block'});
            $('.wpsr-fm-multiple-btn').css({'display': 'block'});
            $(".frm-fluent-form").removeClass("ff_force_hide");
            $(".frm-fluent-form").css({'display': 'block'});
            $('.ff-message-success').css({'display': 'none'});
            $('.ff-errors-in-stack').css({'display': 'none'});
            $('.wpsr-channel-label').css({'display': 'block'});
        }

        toggleVisibility();
    }

    $('.wpsr-channels, .wpsr-channels-icons').on('click.wpsrChannelPrefilled', '.wpsr-channel-btn', function(e) {
        const wpsrIconLayout = document.querySelector('.wpsr-chat-icons-layout');
        if (!wpsrIconLayout) {
            return;
        }

        e.preventDefault();
        const dataChannel = $(this).data('channel');
        const dataNumber = $(this).data('number');
        const dataPrefilled = $(this).data('prefilled');
        const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
        selectedChannelName = dataChannel;
        selectedChannelNumber = dataNumber;
        selectedChannelMessageContext = wpsrGetElementMessageContext($(this));
        selectedChannelMessageContextFormat = $(this).attr('data-message-context-format') || '';
        selectedChannelPageMessageFormat = $(this).attr('data-page-message-format') || '';
        selectedChannelProductInfo = wpsrParseProductInfo(wpsrGetElementProductInfo($(this)));
        if(wpsrChatContainer){
            $('.wpsr-channels').css({'display': 'none'});
            $('.wpsr-fm-multiple-btn').css({'display': 'none'});
            if(prefilledPlatformName.includes(dataChannel)){
                if (dataPrefilled !== 1) {
                    jQuery('.wpsr-fm-chat-box').removeClass('wpsr-fm-chat-box-display');
                    jQuery('.wpsr-channels-icons').removeClass('wpsr-fm-chat-box-display');
                    jQuery('.wpsr-channels-icons').css({'visibility': 'hidden'});

                    jQuery('.wpsr-fm-chat-bubble').removeClass('active');
                    jQuery('.wpsr-chat-icons-closee').hide();
                    jQuery('.wpsr-fm-bubble-btn img').show();
                } else {
                    wpsrChatContainer.style.display = 'block';
                }
            } else if(dataChannel === 'fluent_forms') {

            } else {
                jQuery('.wpsr-channels, .wpsr-channels-icons').removeClass('wpsr-fm-chat-box-display');
                jQuery('.wpsr-channels, .wpsr-channels-icons').css({'display': 'none'});
            }
        }
    });

    $('.wpsr-fm-bubble-btn,.wpsn_chat_opener').on('click',function(e) {
        e.preventDefault();
        const whatsappContainer = document.querySelector('.wpsr-prefilled-input-container');
        const wpsrChannels = document.querySelector('.wpsr-channels');
        const btnTitle = document.querySelector('.wpsr-fm-multiple-btn');
        const wpsrIconLayout = document.querySelector('.wpsr-chat-icons-layout');
        let has_class = $('.wpsr-channels-icons');

        const clickedBtn = $(this);

        let matched = false;
        for (let i = 0; i < prefilledPlatformName.length; i++) {
            const platformName = prefilledPlatformName[i];
            if (clickedBtn.hasClass(platformName)) {
                selectedChannelName = platformName;
                selectedChannelNumber = clickedBtn.data('phone-number');
                selectedChannelMessageContext = wpsrGetElementMessageContext(clickedBtn);
                selectedChannelMessageContextFormat = clickedBtn.attr('data-message-context-format') || '';
                selectedChannelPageMessageFormat = clickedBtn.attr('data-page-message-format') || '';
                selectedChannelProductInfo = wpsrParseProductInfo(wpsrGetElementProductInfo(clickedBtn));
                matched = true;
                break;
            }
        }

        if (whatsappContainer) {
            whatsappContainer.style.display = matched ? 'flex' : 'none';
        }

        if (has_class.length > 0) {
            // $('.wpsr-fm-chat-box').toggleClass('wpsr-fm-chat-box-display');
            // $('.wpsr-channels-icons').toggleClass('wpsr-fm-chat-box-display');

            if ($('.wpsr-fm-chat-box').hasClass('wpsr-fm-chat-box-display')) {
                $('.wpsr-channels-icons').show();
                $('.wpsr-chat-icons-closee').show();
                $('.wpsr-fm-bubble-btn img').hide();
            } else {
                $('.wpsr-channels-icons').hide();
                $('.wpsr-chat-icons-closee').hide();
                $('.wpsr-fm-bubble-btn img').show();
            }
        }

        if (wpsrChannels && !has_class.length) {
            wpsrChannels.style.display = wpsrChannels.style.display === 'none' ? 'block' : 'none';
            if (btnTitle) {
                btnTitle.style.display = btnTitle.style.display === 'none' ? 'block' : 'none';
            }
        }

        toggleChat();

        if(has_class.length > 0){
            $('.wpsr-fm-chat-box-display').css({'display': 'block', 'visibility': 'visible'});
            const whatsappContainer = document.querySelector('.wpsr-prefilled-input-container');
            if (whatsappContainer) {
                whatsappContainer.style.display = 'none';
            }
        }
        if (wpsrChannels && !($('.wpsr-channels-icons').length > 0)) {
            $('.wpsr-channels wpsr-channels-icons').addClass('wpsr-fm-chat-box-display');
            wpsrChannels.style.display = '';
            btnTitle.style.display = '';

        } else {
            if(wpsrChannels){
                wpsrChannels.style.display = '';
            }
        }

        $('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box .wpsr-fm-chat-room .wpsr-fm-chat-btn-wrapper .wpsr-fm-btn-icon a').css({'display': 'flex'});
        e.preventDefault();
        let hideChatBox = jQuery('.wpsr-fm-chat-box-display-whatsapp').is(':visible');
        if(!hideChatBox){
            ++counter;
        }
        if( counter <= 2 ) {
            $('.wpsr-fm-chat').css({'display': 'block'});
            $('.wpsr-fm-greeting-msg').css({'display': 'none'});
            setTimeout(function() {
                $('.wpsr-fm-chat').css({'display': 'none'});
                $('.wpsr-fm-greeting-msg').css({'display': 'block'});
            }, 500);
        }

        let fluentForm = $(this).data('form-ids');
        if (typeof fluentForm === 'string') {
            let idsArray = fluentForm.match(/\d+/g);
            if (idsArray && idsArray.length > 0) {
                for (let i = 0; i < idsArray.length; i++) {
                    $(`.fluentform_wrapper_${idsArray[i]}`).css({
                        display: 'block',
                        width: 'auto'
                    });
                }
            }
        }

        if(wpsrIconLayout){
            const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
            if(wpsrChatContainer){
                wpsrChatContainer.style.display = 'none';
            }
        }
    });

    function toggleVisibility() {
        const channels = $('.wpsr-channels-icons');
        channels.each(function () {
            const $this = $(this);
            if (($this.css('opacity') === '0' || $this.css('visibility') === 'visible') && !$this.hasClass('wpsr-fm-chat-box-display')) {
                $this.css('visibility', 'hidden');
            } else if ($this.hasClass('wpsr-fm-chat-box-display')) {
                // $this.css('visibility', 'visible');
            }
        });
    }

    toggleVisibility();
    fluentFormCheck();

    $('.wpsr-prefilled-send-button').on('click', function (event) {
        event.preventDefault();
        let messageInput = $(this).closest('.wpsr-prefilled-input-container').find('.wpsr-prefilled-input');
        let message = messageInput.val().trim();

        if (!message) {
            alert('Please enter a message before sending.');
            return;
        }
        let phoneNumber = $(this).closest('.wpsr-prefilled-input-container').data('phone-number');
        if(!phoneNumber) {
            phoneNumber = selectedChannelNumber;
        }
        if (!phoneNumber) {
            alert('The phone number is missing. Please check and try again.');
            return;
        }
        let activeChannelName = $(this).closest('.wpsr-prefilled-input-container').data('channel-name') || selectedChannelName;
        let $prefilledContainer = $(this).closest('.wpsr-prefilled-input-container');
        let messageContext = wpsrGetElementMessageContext($prefilledContainer) || selectedChannelMessageContext;
        let contextFormat = $prefilledContainer.attr('data-message-context-format') || selectedChannelMessageContextFormat;
        let pageFormat = $prefilledContainer.attr('data-page-message-format') || selectedChannelPageMessageFormat;
        let productInfo = wpsrParseProductInfo(wpsrGetElementProductInfo($prefilledContainer)) || selectedChannelProductInfo;
        if (activeChannelName === 'whatsapp' && messageContext) {
            message = wpsrAppendContextToMessage(message, messageContext, productInfo, contextFormat, pageFormat);
        }
        let appUrl = '';
        if( activeChannelName === 'whatsapp') {
            appUrl = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
        } else if( activeChannelName === 'sms') {
            appUrl = `sms:${phoneNumber}?body=${encodeURIComponent(message)}`;
        }
        window.open(appUrl, '_blank');
    });

    $('.wpsr-channels .wpsr-channel-item .fluent_forms, .wpsr-fm-chat-btn-wrapper .fluent_forms').on('click', function (){
        $('.wpsr-fm-chat-wrapper').toggleClass('wpsr-has-fluent-form');
        $('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box').addClass('wpsr-fm-chat-box-display');
        
        if ($('.wpsr-has-fluent-form').length > 0) {
            $('.wpsr-channel-label').css({'display': 'none'});
            $('.wpsr-fm-multiple-btn').css({'display': 'none'});
            $('.wpsr-has-fluent-form .wpsr-fm-chat-box .wpsr-fm-chat-room .wpsr-fm-btn-icon a').css({'display': 'none'});
        }

        const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
        if(wpsrChatContainer){
            wpsrChatContainer.style.display = 'none';
        }
    });

    function adjustChatBoxHeight(innerHeight,ffHeight) {
        const MIN_FF_HEIGHT = 450;
        const DEFAULT_CHAT_HEIGHT = 600;

        if (ffHeight > MIN_FF_HEIGHT) {
            let chatHeight = DEFAULT_CHAT_HEIGHT;
            let deviceHeightHalf = innerHeight/2;
            if (ffHeight > deviceHeightHalf) {
                chatHeight = deviceHeightHalf;
            }else {
                chatHeight = ffHeight;
            }

            $('.wpsr-has-fluent-form .fluentform').css({ 'height': chatHeight });
            $('.wpsr-fm-chat-wrapper').addClass('wpsr-has-fluent-form-scrollbar');
        } else {
            $('.wpsr-has-fluent-form .fluentform').css({'height': ffHeight + 50})
            $('.wpsr-fm-chat-wrapper').removeClass('wpsr-has-fluent-form-scrollbar');
        }
    }

    $(window).resize(adjustChatBoxHeight);
    $('.wpsr-fm-chat-wrapper .wpsr-fm-chat-close').on('click', function() {
        jQuery('.wpsr-fm-chat-bubble').removeClass('active');
        jQuery('.wpsr-channels, .wpsr-channels-icons').removeClass('wpsr-fm-chat-box-display');
        jQuery('.wpsr-chat-icons-closee').hide();
        jQuery('.wpsr-fm-bubble-btn img').show();

        setTimeout(function() {
            jQuery('.wpsr-channels, .wpsr-channels-icons').css({'display': 'block'});
        }, 500);
        $('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box').removeClass('wpsr-fm-chat-box-display');
        $('.wpsr-fm-chat-wrapper').removeClass('wpsr-fm-chat-box-active');

        const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
        if(wpsrChatContainer){
            $('.wpsr-fm-multiple-btn').css({'display': 'none'});
            wpsrChatContainer.style.display = 'block';
        }
    });

    $(document).on('keyup', function(e) {
        if(e.keyCode === 27){
            $('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box').removeClass('wpsr-fm-chat-box-display');
        }
    });

    function getStatus(platformParams) {
        let showStatus = false;
        let daytimeOn = platformParams.dayTimeSchedule;

        if (daytimeOn === 'true') {
            const nowUTC = new Date();
            const weekday = nowUTC.toLocaleDateString('en-US', { weekday: 'long', timeZone: 'UTC' });

            let dayLists = platformParams.dayLists;

            let skipDayCheck = !Array.isArray(dayLists) || dayLists.length === 0;

            if (skipDayCheck || dayLists.indexOf(weekday) !== -1) {
                showStatus = true;
                let timeOn = platformParams.timeSchedule;

                if (timeOn === 'true') {
                    const today = nowUTC.toISOString().slice(0, 10);
                    const start = new Date(`${today}T${platformParams.startTime}Z`);
                    let end = new Date(`${today}T${platformParams.endTime}Z`);

                    if (end <= start) {
                        start.setUTCDate(start.getUTCDate() - 1);
                    }

                    if (nowUTC >= start && nowUTC <= end) {
                        showStatus = true;
                    } else {
                        showStatus = false;
                    }
                }
            } else {
                showStatus = false;
            }
        } else {
            showStatus = false;
        }
        return showStatus;
    }
    

    function addStatus(status,element) {
        if(!element){
            return;
        }
        status ? element.classList.add("wpsr-online") : element.classList.add("wpsr-offline");
        return;
    }

    function showStatusMessage() {
        let chatsParams  = $('.wpsr-fm-chat-wrapper').data("chats-params");
        if(chatsParams) {
            let showStatus = getStatus(chatsParams);
            let element = document.getElementById("wpsr-chat-active-status");
            addStatus(showStatus,element);
        }
    }
    showStatusMessage();

    $(document).ready(function () {
        $('.wpsr-channel-btn').on('click', function () {
            let fluentForm = $(this).attr('data-all-ff-ids');

            if (typeof fluentForm === 'string') {
                let idsArray = fluentForm.match(/\d+/g);
                let formIdAttribute = $(this).data('form-id');
                let formContent = formIdAttribute.match(/\d+/);
                let formId = formContent ? formContent[0] : null;

                if (!formId) return;

                for (let i = 0; i < idsArray.length; i++) {
                    let currentId = idsArray[i];
                    let $formDiv = $('.fluent_form_' + currentId);

                    let $ffcWrapper = $formDiv.closest('.ffc_conv_wrapper');
                    let $ffWrapper = $('.fluentform_wrapper_' + currentId);

                    if (currentId === formId) {
                        if ($ffcWrapper.length > 0) $ffcWrapper.css({ display: 'block', width: 'auto' });
                        if ($ffWrapper.length > 0) $ffWrapper.css({ display: 'block', width: 'auto' });

                        let formHeight = $formDiv.outerHeight(true);
                        if ($ffcWrapper.length > 0) $ffcWrapper.css('height', formHeight + 'px');
                        if ($ffWrapper.length > 0) $ffWrapper.css('height', formHeight + 'px');

                        let innerHeight = window.innerHeight;
                        if (typeof adjustChatBoxHeight === 'function') {
                            adjustChatBoxHeight(innerHeight, formHeight);
                        }
                    } else {
                        if ($ffcWrapper.length > 0) $ffcWrapper.css('display', 'none');
                        if ($ffWrapper.length > 0) $ffWrapper.css('display', 'none');
                    }
                }
            }

        });
    });

    const channelItems = document.querySelectorAll('.wpsr-channel-item');
    channelItems.forEach(item => {
        item.addEventListener('click', function () {
            const channelName = this.getAttribute('data-channel-name');
            const link = this.querySelector('a');
            if (!link) {
                return;
            }

            const dataPrefilled = link.getAttribute('data-prefilled');
            selectedChannelName   = link.getAttribute('data-channel');
            selectedChannelNumber = link.getAttribute('data-number');
            selectedChannelMessageContext = link.getAttribute('data-message-context') || '';
            if (selectedChannelMessageContext === 'none') {
                selectedChannelMessageContext = '';
            }
            selectedChannelMessageContextFormat = link.getAttribute('data-message-context-format') || '';
            selectedChannelPageMessageFormat = link.getAttribute('data-page-message-format') || '';
            selectedChannelProductInfo = wpsrParseProductInfo(link.getAttribute('data-product-context-info') || link.getAttribute('data-woocommerce-product-info'));

            const formWrapper = document.querySelector('.wpsr-fluentform-wrapper');
            if(channelName === 'fluent_forms'){
                formWrapper.style.display = ''
            }

            if(!prefilledPlatformName.includes(channelName) && channelName !== 'fluent_forms') {
                jQuery('.wpsr-fm-chat-bubble').toggleClass('active');
            } else {
                if(channelItems.length > 1) {
                    $('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box').addClass('wpsr-fm-chat-box-display');
                }
            }

            let isChatBox = jQuery('.wpsr-fm-chat-box.wpsr-fm-chat-box-display');

            if( (!isChatBox.length) && !prefilledPlatformName.includes(channelName) && channelName !== 'fluent_forms') {
                jQuery('.wpsr-channels, .wpsr-channels-icons').removeClass('wpsr-fm-chat-box-display');
                jQuery('.wpsr-chat-icons-closee').hide();
                jQuery('.wpsr-fm-bubble-btn img').show();
            }

            let messageInput = jQuery('.wpsr-prefilled-input-container').find('.wpsr-prefilled-input');
            if (messageInput.length > 0) {
                messageInput.val('');
            }

            if(dataPrefilled !== '1'){
                return ;
            }

            if (prefilledPlatformName.includes(channelName) ) {
                const prefilledContainer = document.querySelector('.wpsr-prefilled-input-container');
                const wpsrChannels = document.querySelector('.wpsr-channels');
                const enableIcons =  document.querySelector('.wpsr-channels-icons');
                const btnTitle = document.querySelector('.wpsr-fm-multiple-btn');
                if(enableIcons){
                    if (enableIcons) {
                        jQuery('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box.wpsr-fm-chat-box-display').css({'display': 'block'});
                    }
                }
                if (wpsrChannels && channelItems.length > 1 && !enableIcons) {
                    wpsrChannels.style.display = 'none';
                    if(btnTitle) {
                        btnTitle.style.display = 'none';
                    }
                }
                const hasFluentForm = $('.wpsr-fm-chat-wrapper').hasClass('wpsr-has-fluent-form');

                if (prefilledContainer && !hasFluentForm) {
                    const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
                    if(wpsrChatContainer){
                        if(channelItems.length === 1){
                            $('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box').toggleClass('wpsr-fm-chat-box-display');
                        }
                        $('.wpsr-fm-chat').css({'display': 'none'});
                        $('.wpsr-fm-greeting-msg').css({'display': 'block'});
                        wpsrChatContainer.style.display = 'block';
                    } else {
                        prefilledContainer.style.display = 'flex';
                    }
                }

            } else {
                const wpsrChannels = document.querySelector('.wpsr-channels');
                if (wpsrChannels) {
                    wpsrChannels.style.display = '';
                }
                const prefilledContainer = document.querySelector('.wpsr-prefilled-input-container');
                if (prefilledContainer) {
                    prefilledContainer.style.display = 'none';
                }
            }

            const activeChannel = jQuery('.wpsr-channel-item .whatsapp, .wpsr-channel-item .sms');
            if (activeChannel.length > 0 && prefilledPlatformName.includes(channelName)) {
                const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
                const prefilledContainer = document.querySelector('.wpsr-prefilled-input-container');
                if (prefilledContainer && !$('.wpsr-fm-chat-wrapper').hasClass('wpsr-has-fluent-form')) {
                    prefilledContainer.style.display = 'flex';
                }
            }
        });
    });
    
});
