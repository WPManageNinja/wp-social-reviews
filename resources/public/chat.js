"use strict";

let chatParams = `scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=800,height=500,left=100,top=100`;
let popup_target = false;
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

function wpsrGetElementMessageContext(element) {
    if (!element) {
        return '';
    }

    let messageContext = element.getAttribute('data-message-context');

    if (messageContext === 'none') {
        return '';
    }

    return messageContext || '';
}

function wpsrGetElementProductInfo(element) {
    if (!element) {
        return null;
    }

    return element.getAttribute('data-product-context-info') || element.getAttribute('data-woocommerce-product-info');
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

    Object.keys(replacements).forEach(function (placeholder) {
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

// .wpsr-chat-icons-closee and .wpsr-fm-bubble-btn img are shown/hidden purely via the
// `.wpsr-fm-chat-bubble.active` CSS class; clearing an inline override (not hard-coding a
// value) lets that class cascade win, matching jQuery's .show() here. .wpsr-channels-icons has
// no CSS display rule at all, so its shown value really is the literal 'block' used elsewhere
// in this file for the same selector.
function wpsrShowBubbleCloseIcon() {
    const el = document.querySelector('.wpsr-chat-icons-closee');
    if (el) {
        el.style.removeProperty('display');
    }
}

function wpsrHideBubbleCloseIcon() {
    const el = document.querySelector('.wpsr-chat-icons-closee');
    if (el) {
        el.style.display = 'none';
    }
}

function wpsrShowBubbleDefaultIcon() {
    const el = document.querySelector('.wpsr-fm-bubble-btn img');
    if (el) {
        el.style.removeProperty('display');
    }
}

function wpsrHideBubbleDefaultIcon() {
    const el = document.querySelector('.wpsr-fm-bubble-btn img');
    if (el) {
        el.style.display = 'none';
    }
}

function toggleFormVisibility() {
    const chatBox = document.querySelector('.wpsr-fm-chat-box');
    const formWrapper = document.querySelector('.wpsr-fluentform-wrapper');

    if (chatBox && formWrapper) {
        formWrapper.style.display = '';
    }
}

function fluentFormCheck() {
    const channels = document.querySelectorAll('.wpsr-channel-item');
    let has_fluent_forms = false;
    let loopUsed = false;
    channels.forEach(function (item) {
        loopUsed = true;
        const channelItemName = item.dataset.channelName;
        if (channelItemName === 'fluent_forms') {
            has_fluent_forms = true;
        }
    });

    if (!has_fluent_forms && loopUsed) {
        const formWrapper = document.querySelector('.wpsr-fluentform-wrapper');
        if (formWrapper) {
            formWrapper.style.display = 'none';
        }
    }
}

function toggleChat() {
    toggleFormVisibility();
    fluentFormCheck();

    document.querySelectorAll('.wpsr-fm-chat-box, .wpsr-channels-icons').forEach(function (el) {
        el.classList.toggle('wpsr-fm-chat-box-display');
    });

    const bubble = document.querySelector('.wpsr-fm-chat-bubble');
    if (bubble) {
        bubble.classList.toggle('active');
    }

    if (bubble && bubble.classList.contains('active')) {
        wpsrShowBubbleCloseIcon();
    } else {
        wpsrHideBubbleCloseIcon();
        wpsrShowBubbleDefaultIcon();
    }

    if (document.querySelector('.wpsr-has-fluent-form')) {
        const wrapper = document.querySelector('.wpsr-fm-chat-wrapper');
        if (wrapper) {
            wrapper.classList.toggle('wpsr-has-fluent-form');
        }
        document.querySelectorAll('.wpsr-has-fluent-form .wpsr-fm-chat-box .wpsr-fm-chat-room .wpsr-fm-btn-icon a').forEach(function (el) {
            el.style.display = 'block';
        });
        document.querySelectorAll('.wpsr-fm-multiple-btn').forEach(function (el) {
            el.style.display = 'block';
        });
        document.querySelectorAll('.frm-fluent-form').forEach(function (el) {
            el.classList.remove('ff_force_hide');
            el.style.display = 'block';
        });
        document.querySelectorAll('.ff-message-success').forEach(function (el) {
            el.style.display = 'none';
        });
        document.querySelectorAll('.ff-errors-in-stack').forEach(function (el) {
            el.style.display = 'none';
        });
        document.querySelectorAll('.wpsr-channel-label').forEach(function (el) {
            el.style.display = 'block';
        });
    }

    toggleVisibility();
}

function toggleVisibility() {
    const channels = document.querySelectorAll('.wpsr-channels-icons');
    channels.forEach(function (el) {
        const computed = getComputedStyle(el);
        if ((computed.opacity === '0' || computed.visibility === 'visible') && !el.classList.contains('wpsr-fm-chat-box-display')) {
            el.style.visibility = 'hidden';
        } else if (el.classList.contains('wpsr-fm-chat-box-display')) {
            // intentionally no-op; mirrors the original's own commented-out visibility:visible line
        }
    });
}

function adjustChatBoxHeight(innerHeight, ffHeight) {
    const MIN_FF_HEIGHT = 450;
    const DEFAULT_CHAT_HEIGHT = 600;

    if (ffHeight > MIN_FF_HEIGHT) {
        let chatHeight = DEFAULT_CHAT_HEIGHT;
        let deviceHeightHalf = innerHeight / 2;
        if (ffHeight > deviceHeightHalf) {
            chatHeight = deviceHeightHalf;
        } else {
            chatHeight = ffHeight;
        }

        document.querySelectorAll('.wpsr-has-fluent-form .fluentform').forEach(function (el) {
            el.style.height = chatHeight + 'px';
        });
        const wrapper = document.querySelector('.wpsr-fm-chat-wrapper');
        if (wrapper) {
            wrapper.classList.add('wpsr-has-fluent-form-scrollbar');
        }
    } else {
        document.querySelectorAll('.wpsr-has-fluent-form .fluentform').forEach(function (el) {
            el.style.height = (ffHeight + 50) + 'px';
        });
        const wrapper = document.querySelector('.wpsr-fm-chat-wrapper');
        if (wrapper) {
            wrapper.classList.remove('wpsr-has-fluent-form-scrollbar');
        }
    }
}

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

function addStatus(status, element) {
    if (!element) {
        return;
    }
    status ? element.classList.add('wpsr-online') : element.classList.add('wpsr-offline');
    return;
}

function showStatusMessage() {
    const wrapperEl = document.querySelector('.wpsr-fm-chat-wrapper');
    const chatsParamsRaw = wrapperEl ? wrapperEl.getAttribute('data-chats-params') : null;
    let chatsParams = null;
    if (chatsParamsRaw) {
        try {
            chatsParams = JSON.parse(chatsParamsRaw);
        } catch (error) {
            chatsParams = null;
        }
    }
    if (chatsParams) {
        let showStatus = getStatus(chatsParams);
        let element = document.getElementById('wpsr-chat-active-status');
        addStatus(showStatus, element);
    }
}

// Structurally distinct from the delegated .wpsr-channel-btn handler below (contract: preserve
// both, do not de-duplicate) — called inline rather than behind its own DOMContentLoaded guard
// because by the time wpsrRunChatWidget() runs, the DOM is already ready, so jQuery's original
// nested $(document).ready(...) here always fired synchronously in place, not deferred.
function wpsrBindFluentFormChannelClicks() {
    document.querySelectorAll('.wpsr-channel-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            let fluentForm = btn.getAttribute('data-all-ff-ids');

            if (typeof fluentForm === 'string') {
                let idsArray = fluentForm.match(/\d+/g);
                let formIdAttribute = btn.dataset.formId;
                let formContent = formIdAttribute ? formIdAttribute.match(/\d+/) : null;
                let formId = formContent ? formContent[0] : null;

                if (!formId) return;

                for (let i = 0; i < idsArray.length; i++) {
                    let currentId = idsArray[i];
                    let formDiv = document.querySelector('.fluent_form_' + currentId);
                    let ffcWrapper = formDiv ? formDiv.closest('.ffc_conv_wrapper') : null;
                    let ffWrapper = document.querySelector('.fluentform_wrapper_' + currentId);

                    if (currentId === formId) {
                        if (ffcWrapper) {
                            ffcWrapper.style.display = 'block';
                            ffcWrapper.style.width = 'auto';
                        }
                        if (ffWrapper) {
                            ffWrapper.style.display = 'block';
                            ffWrapper.style.width = 'auto';
                        }

                        let formHeight = 0;
                        if (formDiv) {
                            let formRect = formDiv.getBoundingClientRect();
                            let formComputed = getComputedStyle(formDiv);
                            formHeight = formRect.height + parseFloat(formComputed.marginTop) + parseFloat(formComputed.marginBottom);
                        }
                        if (ffcWrapper) {
                            ffcWrapper.style.height = formHeight + 'px';
                        }
                        if (ffWrapper) {
                            ffWrapper.style.height = formHeight + 'px';
                        }

                        let innerHeight = window.innerHeight;
                        if (typeof adjustChatBoxHeight === 'function') {
                            adjustChatBoxHeight(innerHeight, formHeight);
                        }
                    } else {
                        if (ffcWrapper) {
                            ffcWrapper.style.display = 'none';
                        }
                        if (ffWrapper) {
                            ffWrapper.style.display = 'none';
                        }
                    }
                }
            }
        });
    });
}

function wpsrRunChatWidget() {
    const wpsrChatWrapperEl = document.querySelector('.wpsr-fm-chat-wrapper');
    popup_target = wpsrChatWrapperEl ? wpsrChatWrapperEl.dataset.popupTarget === 'true' : false;

    // open popup window — flatten children across every matched container into one index
    // space, matching jQuery's $('.a, .b').children('.item').each((index) => ...) semantics
    // exactly (a combined collection indexed once, not reset per container).
    let wpsrChannelItemsForLinks = [];
    document.querySelectorAll('.wpsr-channels, .wpsr-channels-icons').forEach(function (container) {
        Array.prototype.push.apply(wpsrChannelItemsForLinks, container.querySelectorAll(':scope > .wpsr-channel-item'));
    });
    wpsrChannelItemsForLinks.forEach(function (item, index) {
        const channelButton = item.querySelector('.wpsr-channel-btn');
        if (!channelButton) {
            return;
        }
        const channel = channelButton.dataset.channel;
        const isPrefilled = channelButton.dataset.prefilled;
        const messageContext = wpsrGetElementMessageContext(channelButton);
        const contextFormat = channelButton.getAttribute('data-message-context-format');
        const pageFormat = channelButton.getAttribute('data-page-message-format');
        const productInfo = wpsrGetElementProductInfo(channelButton);

        if (channel === 'fluent_forms' || (prefilledPlatformName.includes(channel) && isPrefilled)) {
            return;
        }

        const targetButton = item.querySelector('.wpsr-channel-btn.' + channel + index);
        if (!targetButton) {
            return;
        }
        let url = targetButton.dataset.chatUrl;
        if (!wpsrIsSafeChatUrl(url, channel)) {
            return;
        }
        url = wpsrAppendContextToChatUrl(url, channel, messageContext, productInfo, contextFormat, pageFormat);
        targetButton.setAttribute('href', url);

        // channels.php re-renders channels-button.php a second time inside the hidden
        // .wpsr_icon_chat_container, so this class also matches a hidden duplicate node —
        // bind to every match (like jQuery's class selector would), not just the first.
        document.querySelectorAll('.wpsr-channel-item.' + channel + index).forEach(function (clickTargetEl) {
            clickTargetEl.addEventListener('click', function (event) {
                event.preventDefault();
                if (popup_target) {
                    open(url, '', chatParams);
                } else {
                    const target = (channel === 'phone' || (prefilledPlatformName.includes(channel) && isPrefilled)) ? '_self' : '_blank';
                    open(url, target);
                }
            });
        });
    });

    document.querySelectorAll('.wpsr-fm-chat-wrapper .wpsr-fm-btn-icon').forEach(function (item) {
        const fmButton = item.querySelector('.wpsr-fm-btn');
        if (!fmButton) {
            return;
        }
        const channel = fmButton.dataset.channel;
        if (channel === 'fluent_forms') {
            fmButton.setAttribute('href', '#');
            return;
        }
        let url = fmButton.dataset.chatUrl;
        if (!wpsrIsSafeChatUrl(url, channel)) {
            return;
        }
        url = wpsrAppendContextToChatUrl(url, channel, wpsrGetElementMessageContext(fmButton), wpsrGetElementProductInfo(fmButton), fmButton.getAttribute('data-message-context-format'), fmButton.getAttribute('data-page-message-format'));
        fmButton.setAttribute('href', url);

        // same reasoning as the channel-item binding above: bind to every match of this
        // class, matching jQuery's selector semantics, not just the first.
        document.querySelectorAll('.wpsr-fm-btn-icon .' + channel).forEach(function (clickTargetEl) {
            clickTargetEl.addEventListener('click', function (event) {
                event.preventDefault();
                if (popup_target) {
                    open(url, '', chatParams);
                } else {
                    open(url, '_blank');
                }
            });
        });
    });

    let counter = 0;
    const wpsrChatWrapperForDelay = document.querySelector('.wpsr-fm-chat-wrapper');
    const chatPopupDelay = wpsrChatWrapperForDelay ? Number(wpsrChatWrapperForDelay.dataset.chatPopupDelay) : NaN;
    const chatDisplayPopup = wpsrChatWrapperForDelay ? wpsrChatWrapperForDelay.dataset.chatDisplayPopup === 'true' : false;

    if (chatPopupDelay && chatDisplayPopup) {
        counter++;
        const wpsrChatBoxForDelay = document.querySelector('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box');
        if (wpsrChatBoxForDelay) {
            wpsrChatBoxForDelay.classList.remove('wpsr-fm-chat-box-display');
        }
        setTimeout(function () {
            const box = document.querySelector('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box');
            if (box) {
                box.classList.add('wpsr-fm-chat-box-display');
            }
            const header = document.querySelector('.wpsr-fm-chat-header');
            if (header && header.classList.contains('wpsr-offline') && box) {
                box.classList.remove('wpsr-fm-chat-box-display');
            }
        }, chatPopupDelay);

        setTimeout(function () {
            const chatEl = document.querySelector('.wpsr-fm-chat');
            const greetingEl = document.querySelector('.wpsr-fm-greeting-msg');
            if (chatEl) {
                chatEl.style.display = 'none';
            }
            if (greetingEl) {
                greetingEl.style.display = 'block';
            }
        }, chatPopupDelay + 500);
    }

    toggleFormVisibility();

    document.querySelectorAll('.wpsr-channels, .wpsr-channels-icons').forEach(function (container) {
        container.addEventListener('click', function (e) {
            const target = e.target.closest('.wpsr-channel-btn');
            if (!target || !container.contains(target)) {
                return;
            }
            const wpsrIconLayout = document.querySelector('.wpsr-chat-icons-layout');
            if (!wpsrIconLayout) {
                return;
            }

            e.preventDefault();
            const dataChannel = target.dataset.channel;
            const dataNumber = target.dataset.number;
            const dataPrefilledRaw = target.dataset.prefilled;
            const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
            selectedChannelName = dataChannel;
            selectedChannelNumber = dataNumber;
            selectedChannelMessageContext = wpsrGetElementMessageContext(target);
            selectedChannelMessageContextFormat = target.getAttribute('data-message-context-format') || '';
            selectedChannelPageMessageFormat = target.getAttribute('data-page-message-format') || '';
            selectedChannelProductInfo = wpsrParseProductInfo(wpsrGetElementProductInfo(target));
            if (wpsrChatContainer) {
                document.querySelectorAll('.wpsr-channels').forEach(function (el) { el.style.display = 'none'; });
                document.querySelectorAll('.wpsr-fm-multiple-btn').forEach(function (el) { el.style.display = 'none'; });
                if (prefilledPlatformName.includes(dataChannel)) {
                    // this path compares data-prefilled loosely as a number (contract: preserve
                    // per-block, do not unify with the strict '1' string comparison used below)
                    if (Number(dataPrefilledRaw) !== 1) {
                        const chatBoxEl = document.querySelector('.wpsr-fm-chat-box');
                        if (chatBoxEl) {
                            chatBoxEl.classList.remove('wpsr-fm-chat-box-display');
                        }
                        document.querySelectorAll('.wpsr-channels-icons').forEach(function (el) {
                            el.classList.remove('wpsr-fm-chat-box-display');
                            el.style.visibility = 'hidden';
                        });
                        const bubbleEl = document.querySelector('.wpsr-fm-chat-bubble');
                        if (bubbleEl) {
                            bubbleEl.classList.remove('active');
                        }
                        wpsrHideBubbleCloseIcon();
                        wpsrShowBubbleDefaultIcon();
                    } else {
                        wpsrChatContainer.style.display = 'block';
                    }
                } else if (dataChannel === 'fluent_forms') {
                    // no-op, mirrors the original's empty branch
                } else {
                    document.querySelectorAll('.wpsr-channels, .wpsr-channels-icons').forEach(function (el) {
                        el.classList.remove('wpsr-fm-chat-box-display');
                        el.style.display = 'none';
                    });
                }
            }
        });
    });

    document.querySelectorAll('.wpsr-fm-bubble-btn,.wpsn_chat_opener').forEach(function (bubbleBtn) {
        bubbleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const whatsappContainer = document.querySelector('.wpsr-prefilled-input-container');
            const wpsrChannels = document.querySelector('.wpsr-channels');
            const btnTitle = document.querySelector('.wpsr-fm-multiple-btn');
            const wpsrIconLayout = document.querySelector('.wpsr-chat-icons-layout');
            const hasClassList = document.querySelectorAll('.wpsr-channels-icons');

            const clickedBtn = this;

            let matched = false;
            for (let i = 0; i < prefilledPlatformName.length; i++) {
                const platformName = prefilledPlatformName[i];
                if (clickedBtn.classList.contains(platformName)) {
                    selectedChannelName = platformName;
                    selectedChannelNumber = clickedBtn.dataset.phoneNumber;
                    selectedChannelMessageContext = wpsrGetElementMessageContext(clickedBtn);
                    selectedChannelMessageContextFormat = clickedBtn.getAttribute('data-message-context-format') || '';
                    selectedChannelPageMessageFormat = clickedBtn.getAttribute('data-page-message-format') || '';
                    selectedChannelProductInfo = wpsrParseProductInfo(wpsrGetElementProductInfo(clickedBtn));
                    matched = true;
                    break;
                }
            }

            if (whatsappContainer) {
                whatsappContainer.style.display = matched ? 'flex' : 'none';
            }

            if (hasClassList.length > 0) {
                const chatBoxEl = document.querySelector('.wpsr-fm-chat-box');
                if (chatBoxEl && chatBoxEl.classList.contains('wpsr-fm-chat-box-display')) {
                    document.querySelectorAll('.wpsr-channels-icons').forEach(function (el) { el.style.display = 'block'; });
                    wpsrShowBubbleCloseIcon();
                    wpsrHideBubbleDefaultIcon();
                } else {
                    document.querySelectorAll('.wpsr-channels-icons').forEach(function (el) { el.style.display = 'none'; });
                    wpsrHideBubbleCloseIcon();
                    wpsrShowBubbleDefaultIcon();
                }
            }

            if (wpsrChannels && !hasClassList.length) {
                wpsrChannels.style.display = wpsrChannels.style.display === 'none' ? 'block' : 'none';
                if (btnTitle) {
                    btnTitle.style.display = btnTitle.style.display === 'none' ? 'block' : 'none';
                }
            }

            toggleChat();

            if (hasClassList.length > 0) {
                document.querySelectorAll('.wpsr-fm-chat-box-display').forEach(function (el) {
                    el.style.display = 'block';
                    el.style.visibility = 'visible';
                });
                const whatsappContainerAfter = document.querySelector('.wpsr-prefilled-input-container');
                if (whatsappContainerAfter) {
                    whatsappContainerAfter.style.display = 'none';
                }
            }
            if (wpsrChannels && !(document.querySelectorAll('.wpsr-channels-icons').length > 0)) {
                document.querySelectorAll('.wpsr-channels wpsr-channels-icons').forEach(function (el) {
                    el.classList.add('wpsr-fm-chat-box-display');
                });
                wpsrChannels.style.display = '';
                if (btnTitle) {
                    btnTitle.style.display = '';
                }
            } else {
                if (wpsrChannels) {
                    wpsrChannels.style.display = '';
                }
            }

            document.querySelectorAll('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box .wpsr-fm-chat-room .wpsr-fm-chat-btn-wrapper .wpsr-fm-btn-icon a').forEach(function (el) {
                el.style.display = 'flex';
            });
            e.preventDefault();
            const whatsappVisibleEl = document.querySelector('.wpsr-fm-chat-box-display-whatsapp');
            const hideChatBox = !!(whatsappVisibleEl && (whatsappVisibleEl.offsetWidth || whatsappVisibleEl.offsetHeight || whatsappVisibleEl.getClientRects().length));
            if (!hideChatBox) {
                ++counter;
            }
            if (counter <= 2) {
                const chatEl = document.querySelector('.wpsr-fm-chat');
                const greetingEl = document.querySelector('.wpsr-fm-greeting-msg');
                if (chatEl) {
                    chatEl.style.display = 'block';
                }
                if (greetingEl) {
                    greetingEl.style.display = 'none';
                }
                setTimeout(function () {
                    if (chatEl) {
                        chatEl.style.display = 'none';
                    }
                    if (greetingEl) {
                        greetingEl.style.display = 'block';
                    }
                }, 500);
            }

            const fluentForm = clickedBtn.dataset.formIds;
            if (typeof fluentForm === 'string') {
                const idsArray = fluentForm.match(/\d+/g);
                if (idsArray && idsArray.length > 0) {
                    for (let i = 0; i < idsArray.length; i++) {
                        document.querySelectorAll('.fluentform_wrapper_' + idsArray[i]).forEach(function (el) {
                            el.style.display = 'block';
                            el.style.width = 'auto';
                        });
                    }
                }
            }

            if (wpsrIconLayout) {
                const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
                if (wpsrChatContainer) {
                    wpsrChatContainer.style.display = 'none';
                }
            }
        });
    });

    toggleVisibility();
    fluentFormCheck();

    document.querySelectorAll('.wpsr-prefilled-send-button').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            const container = btn.closest('.wpsr-prefilled-input-container');
            if (!container) {
                return;
            }
            const messageInput = container.querySelector('.wpsr-prefilled-input');
            let message = messageInput ? messageInput.value.trim() : '';

            if (!message) {
                alert('Please enter a message before sending.');
                return;
            }
            let phoneNumber = container.dataset.phoneNumber;
            if (!phoneNumber) {
                phoneNumber = selectedChannelNumber;
            }
            if (!phoneNumber) {
                alert('The phone number is missing. Please check and try again.');
                return;
            }
            const activeChannelName = container.dataset.channelName || selectedChannelName;
            const messageContext = wpsrGetElementMessageContext(container) || selectedChannelMessageContext;
            const contextFormat = container.getAttribute('data-message-context-format') || selectedChannelMessageContextFormat;
            const pageFormat = container.getAttribute('data-page-message-format') || selectedChannelPageMessageFormat;
            const productInfo = wpsrParseProductInfo(wpsrGetElementProductInfo(container)) || selectedChannelProductInfo;
            if (activeChannelName === 'whatsapp' && messageContext) {
                message = wpsrAppendContextToMessage(message, messageContext, productInfo, contextFormat, pageFormat);
            }
            let appUrl = '';
            if (activeChannelName === 'whatsapp') {
                appUrl = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
            } else if (activeChannelName === 'sms') {
                appUrl = `sms:${phoneNumber}?body=${encodeURIComponent(message)}`;
            }
            window.open(appUrl, '_blank');
        });
    });

    document.querySelectorAll('.wpsr-channels .wpsr-channel-item .fluent_forms, .wpsr-fm-chat-btn-wrapper .fluent_forms').forEach(function (el) {
        el.addEventListener('click', function () {
            const wrapper = document.querySelector('.wpsr-fm-chat-wrapper');
            if (wrapper) {
                wrapper.classList.toggle('wpsr-has-fluent-form');
            }
            const chatBoxEl = document.querySelector('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box');
            if (chatBoxEl) {
                chatBoxEl.classList.add('wpsr-fm-chat-box-display');
            }

            if (document.querySelector('.wpsr-has-fluent-form')) {
                document.querySelectorAll('.wpsr-channel-label').forEach(function (e) { e.style.display = 'none'; });
                document.querySelectorAll('.wpsr-fm-multiple-btn').forEach(function (e) { e.style.display = 'none'; });
                document.querySelectorAll('.wpsr-has-fluent-form .wpsr-fm-chat-box .wpsr-fm-chat-room .wpsr-fm-btn-icon a').forEach(function (e) { e.style.display = 'none'; });
            }

            const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
            if (wpsrChatContainer) {
                wpsrChatContainer.style.display = 'none';
            }
        });
    });

    window.addEventListener('resize', adjustChatBoxHeight);

    document.querySelectorAll('.wpsr-fm-chat-wrapper .wpsr-fm-chat-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const bubbleEl = document.querySelector('.wpsr-fm-chat-bubble');
            if (bubbleEl) {
                bubbleEl.classList.remove('active');
            }
            document.querySelectorAll('.wpsr-channels, .wpsr-channels-icons').forEach(function (el) {
                el.classList.remove('wpsr-fm-chat-box-display');
            });
            wpsrHideBubbleCloseIcon();
            wpsrShowBubbleDefaultIcon();

            setTimeout(function () {
                document.querySelectorAll('.wpsr-channels, .wpsr-channels-icons').forEach(function (el) {
                    el.style.display = 'block';
                });
            }, 500);
            const chatBoxEl = document.querySelector('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box');
            if (chatBoxEl) {
                chatBoxEl.classList.remove('wpsr-fm-chat-box-display');
            }
            const wrapperEl = document.querySelector('.wpsr-fm-chat-wrapper');
            if (wrapperEl) {
                wrapperEl.classList.remove('wpsr-fm-chat-box-active');
            }

            const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
            if (wpsrChatContainer) {
                document.querySelectorAll('.wpsr-fm-multiple-btn').forEach(function (el) { el.style.display = 'none'; });
                wpsrChatContainer.style.display = 'block';
            }
        });
    });

    document.addEventListener('keyup', function (e) {
        if (e.keyCode === 27) {
            const chatBoxEl = document.querySelector('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box');
            if (chatBoxEl) {
                chatBoxEl.classList.remove('wpsr-fm-chat-box-display');
            }
        }
    });

    showStatusMessage();

    wpsrBindFluentFormChannelClicks();

    const channelItems = document.querySelectorAll('.wpsr-channel-item');
    channelItems.forEach(function (item) {
        item.addEventListener('click', function () {
            const channelName = this.getAttribute('data-channel-name');
            const link = this.querySelector('a');
            if (!link) {
                return;
            }

            const dataPrefilled = link.getAttribute('data-prefilled');
            selectedChannelName = link.getAttribute('data-channel');
            selectedChannelNumber = link.getAttribute('data-number');
            selectedChannelMessageContext = link.getAttribute('data-message-context') || '';
            if (selectedChannelMessageContext === 'none') {
                selectedChannelMessageContext = '';
            }
            selectedChannelMessageContextFormat = link.getAttribute('data-message-context-format') || '';
            selectedChannelPageMessageFormat = link.getAttribute('data-page-message-format') || '';
            selectedChannelProductInfo = wpsrParseProductInfo(link.getAttribute('data-product-context-info') || link.getAttribute('data-woocommerce-product-info'));

            const formWrapper = document.querySelector('.wpsr-fluentform-wrapper');
            if (channelName === 'fluent_forms') {
                formWrapper.style.display = '';
            }

            if (!prefilledPlatformName.includes(channelName) && channelName !== 'fluent_forms') {
                const bubbleEl = document.querySelector('.wpsr-fm-chat-bubble');
                if (bubbleEl) {
                    bubbleEl.classList.toggle('active');
                }
            } else {
                if (channelItems.length > 1) {
                    const chatBoxEl = document.querySelector('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box');
                    if (chatBoxEl) {
                        chatBoxEl.classList.add('wpsr-fm-chat-box-display');
                    }
                }
            }

            const isChatBoxEl = document.querySelector('.wpsr-fm-chat-box.wpsr-fm-chat-box-display');

            if (!isChatBoxEl && !prefilledPlatformName.includes(channelName) && channelName !== 'fluent_forms') {
                document.querySelectorAll('.wpsr-channels, .wpsr-channels-icons').forEach(function (el) {
                    el.classList.remove('wpsr-fm-chat-box-display');
                });
                wpsrHideBubbleCloseIcon();
                wpsrShowBubbleDefaultIcon();
            }

            const prefilledInputContainerEl = document.querySelector('.wpsr-prefilled-input-container');
            const messageInputEl = prefilledInputContainerEl ? prefilledInputContainerEl.querySelector('.wpsr-prefilled-input') : null;
            if (messageInputEl) {
                messageInputEl.value = '';
            }

            if (dataPrefilled !== '1') {
                return;
            }

            if (prefilledPlatformName.includes(channelName)) {
                const prefilledContainer = document.querySelector('.wpsr-prefilled-input-container');
                const wpsrChannels = document.querySelector('.wpsr-channels');
                const enableIcons = document.querySelector('.wpsr-channels-icons');
                const btnTitle = document.querySelector('.wpsr-fm-multiple-btn');
                if (enableIcons) {
                    document.querySelectorAll('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box.wpsr-fm-chat-box-display').forEach(function (el) {
                        el.style.display = 'block';
                    });
                }
                if (wpsrChannels && channelItems.length > 1 && !enableIcons) {
                    wpsrChannels.style.display = 'none';
                    if (btnTitle) {
                        btnTitle.style.display = 'none';
                    }
                }
                const wrapperElForFF = document.querySelector('.wpsr-fm-chat-wrapper');
                const hasFluentForm = wrapperElForFF ? wrapperElForFF.classList.contains('wpsr-has-fluent-form') : false;

                if (prefilledContainer && !hasFluentForm) {
                    const wpsrChatContainer = document.querySelector('.wpsr_icon_chat_container');
                    if (wpsrChatContainer) {
                        if (channelItems.length === 1) {
                            const chatBoxEl = document.querySelector('.wpsr-fm-chat-wrapper .wpsr-fm-chat-box');
                            if (chatBoxEl) {
                                chatBoxEl.classList.toggle('wpsr-fm-chat-box-display');
                            }
                        }
                        const chatEl = document.querySelector('.wpsr-fm-chat');
                        const greetingEl = document.querySelector('.wpsr-fm-greeting-msg');
                        if (chatEl) {
                            chatEl.style.display = 'none';
                        }
                        if (greetingEl) {
                            greetingEl.style.display = 'block';
                        }
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

            const activeChannel = document.querySelector('.wpsr-channel-item .whatsapp, .wpsr-channel-item .sms');
            if (activeChannel && prefilledPlatformName.includes(channelName)) {
                const prefilledContainer = document.querySelector('.wpsr-prefilled-input-container');
                const wrapperElForFF2 = document.querySelector('.wpsr-fm-chat-wrapper');
                const hasFluentForm2 = wrapperElForFF2 ? wrapperElForFF2.classList.contains('wpsr-has-fluent-form') : false;
                if (prefilledContainer && !hasFluentForm2) {
                    prefilledContainer.style.display = 'flex';
                }
            }
        });
    });
}

if (document.readyState !== 'loading') {
    wpsrRunChatWidget();
} else {
    document.addEventListener('DOMContentLoaded', wpsrRunChatWidget);
}
