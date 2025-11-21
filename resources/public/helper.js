/*
* Escape HTML
* Converts < > & " ' to HTML entities
*/
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

export function formatText(text, hashTagUrl) {
    let content = escapeHtml(text);
    content = generateURLsFromText(content);
    content = generateURLsFromHashTag(content, hashTagUrl);
    return nl2br(content);
}

export function nl2br(str, is_xhtml) {
    if (typeof str === 'undefined' || str === null) {
        return '';
    }
    let breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
}

export function generateURLsFromHashTag(str, url){
    if(str){
        return str.replace(/#[^\s!@#$%^&*()=+.\/,\[{\]};:'"?><]+/g, function( hash ) {
            let hashTag = hash.replace("#","");
            return '<a href="'+url+''+hashTag+'" target="_blank">'+hash+'</a>';
        });
    }
}

export function generateURLsFromText(str) {
    if (str) {
        // Regex to match URLs that are NOT inside HTML attributes (particularly img src)
        // This pattern excludes URLs that are preceded by certain HTML attribute patterns
        return str.replace(/(?<!src\s*=\s*["'][^"']*)[A-Za-z]+:\/\/[A-Za-z0-9-_]+\.[A-Za-z0-9-_:%&~\?\/.=]+(?![^<>]*>)/g, function (url) {
            return '<a href="' + url + '" target="_blank">' + url + '</a>';
        });
    }
}

export function formatDate(date) {
    let d = new Date(date),
        month = d.getMonth(),
        day = '' + d.getDate(),
        year = d.getFullYear();

    if (day.length < 2){
        day = '0' + day;
    }
    let months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    month = months[month];
    return `${month} ${day}, ${year}`;
}

export function formatDateWithTime(date) {
    let d = new Date(date),
        month = d.getMonth(),
        day = '' + d.getDate(),
        year = d.getFullYear();
        let hours = d.getHours();
        let minutes = d.getMinutes();
        let ampm = hours >= 12 ? 'pm' : 'am';
        minutes = minutes < 10 ? '0'+minutes : minutes;
        let strTime = hours + ':' + minutes + ' ' + ampm;

        if (day.length < 2){
            day = '0' + day;
        }
        let months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        month = months[month];
        return `${month} ${day}, ${year} ${strTime}`;
}

const excerptItem = function(content, limit, read_more_text, read_less_txt) {
    //This limit you can set after how much characters you want to show Read More.
    let characterLimit = limit ? limit : 100;
    // Text to show when text is collapsed
    let readMoreTxt = window.wpsr_ajax_params.read_more ?  window.wpsr_ajax_params.read_more : "Read More";
    // Text to show when text is expanded
    let readLessTxt =  window.wpsr_ajax_params.read_less ?  window.wpsr_ajax_params.read_less : "Read Less";
    if (content.length > characterLimit) {
        let excerpt = content.substring(0, characterLimit);
        let hideContent = content.substring(characterLimit, content.length);
        let newString = hideContent.replace("Read MoreRead Less", "");
        let addContent = '';
        addContent += excerpt + "<span class='wpsr_add_read_more_slice_content'>" + newString + "</span>";
        //addContent +=  readMoreTxt ? "..." : '';
        addContent += "<span class='wpsr_read_more'>" + readMoreTxt + "</span>";
        addContent += "<span class='wpsr_read_less'>" + readLessTxt + "</span>";
        return addContent;
    }
    return content;
}

export function htmlSubstring(text,len) {
    let length = len;
    let m, r = /<([^>\s]*)[^>]*>/g,
        stack = [],
        lasti = 0,
        result = '';

    let indexMap = [];
    //for each tag, while we don't have enough characters
    while((m = r.exec(text)) && length) {
        //get the text substring between the last tag and this one
        let temp = text.substring(lasti, m.index).substr(0, length);
        indexMap.push({'first':m.index,'second':r.lastIndex});
        //append to the result and count the number of characters added
        result += text.substring(lasti, m.index);
        length -= temp.length;
        lasti = r.lastIndex;
    }

    for(let i=1; i < indexMap.length; i++) {
        if( indexMap[i-1].first < 100 && indexMap[i].second > 100 ) {
            let characterLimit =  indexMap[i].second;
            return excerptItem(text, characterLimit)
        }
    }

    let characterLimit = 100;
    if(text.length > 100) {
        return excerptItem(text, characterLimit)
    }else{
        return text;
    }
}

export function appendReadMoreButton(readMore = '', readLess = '') {
    processContent(".wpsr_add_read_more", readMore, readLess, 'https://facebook.com/hashtag/');
    processContent(".wpsr-tiktok-feed-content", readMore, readLess, 'https://www.tiktok.com/tag/');
}

function processContent(selector, readMore, readLess, urlPrefix) {
    jQuery(selector + ":not(.wpsr_more_added)").each(function () {
        let length = jQuery(this).data('num-words-trim');
        let content = jQuery(this).html();

        content = content.replace(/\s+/g, ' ').trim();

        content = escapeHtml(content);

        let excerptText = '';
        let countWord = content.split(" ", length);

        if ((countWord.length > length || countWord.length !== 0) && length !== undefined) {
            let excerpt;
            content = nl2br(content);
            excerpt = truncateHTML(content, length);            
            excerpt = nl2br(excerpt);

            let hideContent = content.substring(excerpt.length, content.length);

            if( content.length > excerpt.length){
                excerptText = excerpt + "<span class='wpsr_add_read_more_slice_content'>" + hideContent + "</span><span class='wpsr_read_more' aria-label='"+readMore+"' tabindex='0'>" + readMore + "</span><span class='wpsr_read_less' aria-label='"+readLess+"' tabindex='0'>" + readLess + "</span>";
            } else {
                excerptText = excerpt;
            }
        } else {
            excerptText = content;
        }
        if (urlPrefix) {
            excerptText = generateURLsFromText(excerptText);
            excerptText = generateURLsFromHashTag(excerptText, urlPrefix);
        }

        jQuery(this).html(excerptText);
        jQuery(this).addClass('wpsr_more_added');
    });
}

export function animateTextTypingEffect(selector = '.wpsr-ai-review-summary', speed = 50) {
    const aiSummary = document.querySelector(selector);

    if(aiSummary && aiSummary.parentNode.classList.contains('wpsr-disable-typing-animation')) return;
    
    if (aiSummary === null) return;
    // Find the first direct text node inside #ai-summary
    let firstTextNode = null;
    aiSummary.childNodes.forEach(node => {
      if (node.nodeType === Node.TEXT_NODE && node.nodeValue.trim() !== "" && !firstTextNode) {
        firstTextNode = node;
      }
    });

    if (!firstTextNode) return; // No valid text node found, exit

    // Store the original text and clear it for animation
    const originalText = firstTextNode.nodeValue.trim();
    firstTextNode.nodeValue = "";

    let index = 0;

    function typeWriter() {
      if (index < originalText.length) {
        firstTextNode.nodeValue += originalText.charAt(index);
        index++;
        setTimeout(typeWriter, speed); // Adjust speed here
      }
    }

    typeWriter();
}

export function processAISummaryList($) {

    var $summaryListWrapper = $('.wpsr-review-summary-list');

    if (!$summaryListWrapper.length) {
        return;
    }

    var shouldDisplayReadMore = $summaryListWrapper.data('should-display-read-more');

    $summaryListWrapper.find('.wpsr-ai-review-summary-list').each(async function () {
        var $this = $(this);
        var numWords = $this.data('num-words-trim');
        var enableTextTypingAnimation = !($this.hasClass('wpsr-disable-typing-animation'));
        var excerptList = [];
        var excerptLength = 0;

        $this.find('li').each(function () {
            var $li = $(this);
            var $text = $li.find('.wpsr-text');
            var text = $text.text();
            var words = text.trim().split(' ');
            if (shouldDisplayReadMore === false || shouldDisplayReadMore === 'false') {
                excerptList.push({
                    excerpt: text,
                    remaining: ''
                });
            } else {
                if (excerptLength + words.length > numWords) {
                    var excerpt = words.slice(0, numWords - excerptLength).join(' ');
                    var remaining = ' ' + words.slice(numWords - excerptLength).join(' ');

                    excerptList.push({
                        excerpt: excerpt,
                        remaining: remaining
                    });

                    excerptLength = numWords;
                } else {
                    excerptList.push({
                        excerpt: text,
                        remaining: ''
                    });

                    excerptLength += words.length;
                }
            }
        });

        $this.empty();
        await processItems(excerptList, $this, $, enableTextTypingAnimation);

        // Move masonry initialization to a function and call it after 200ms
        setTimeout(() => {
            initializeMasonryLayout($);
        }, 200);

        if (shouldDisplayReadMore === false || shouldDisplayReadMore === 'false' || excerptLength < numWords ) {
            return;
        }

        // Text to show when text is collapsed
        let readMoreTxt = window.wpsr_ajax_params.read_more ?  window.wpsr_ajax_params.read_more : "Read More";
        // Text to show when text is expanded
        let readLessTxt =  window.wpsr_ajax_params.read_less ?  window.wpsr_ajax_params.read_less : "Read Less";

        var $readMore = $('<span class="wpsr_read_more" aria-label="Read more" tabindex="0">'+readMoreTxt+'</span>');
        var $readLess = $('<span class="wpsr_read_less" aria-label="Read less" tabindex="0">'+readLessTxt+'</span>');

        var summaryListWrapper = $(this).closest('.wpsr-review-summary-list');

        summaryListWrapper.append($readMore);
        summaryListWrapper.append($readLess);

        [$readMore, $readLess].forEach(function ($element) {
            $element.on('click', function () {
                summaryListWrapper.toggleClass('wpsr-ai-summary-list-collapsed');
                summaryListWrapper.toggleClass('wpsr-ai-summary-list-expanded');
            });
        });
    });
};

// Add this helper function for masonry initialization
function initializeMasonryLayout($) {
    $('.wpsr-row').each(function () {
        if($(this).hasClass('wpsr-active-masonry-layout')){
            var column = $(this).data("column");
            $(this).masonry({
                itemSelector: '.wpsr-col-' + column + ''
            });
        }
    });
}

async function typeText($p, text, enableTextTypingAnimation = true, $remaining = '') {
    const jQ = window.jQuery;
    let hasRemaining = $remaining && $remaining.text && $remaining.text().trim().length > 0;
    let $dots = hasRemaining ? jQ('<span class="wpsr-ai-summary-read-more-dots">...</span>') : null;

    if (!enableTextTypingAnimation) {
        $p.text(text);
        if ($dots) $p.append($dots);
        $p.append($remaining);
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        let i = 0;
        let interval = setInterval(() => {
            if (i < text.length) {
                $p.append(text.charAt(i));
                i++;
            } else {
                clearInterval(interval);
                if ($dots) $p.append($dots);
                $p.append($remaining);
                resolve();
            }
        }, 5); // Adjust the delay as needed
    });
}

async function processItems(excerptList, $this, $, enableTextTypingAnimation = true) {

    for (let item of excerptList) {
        var $remaining = $('<span class="wpsr-ai-review-summary-remaining"></span>');
        var $li = $('<li></li>');
        var $text = $('<span class="wpsr-text"></span>');
        var $point = $('<span class="wpsr-summary-point"></span>');
        var $check = $('<span class="wpsr-list-style-check"></span>');
        var $p = $('<p></p>');

        $remaining.text(item.remaining);

        $p.text(item.excerpt);
        $p.append($remaining);

        $text.append($p);
        $point.append($check);
        $point.append($text);
        $li.append($point);
        $this.append($li);

        if (item.excerpt.length <= 0) {
            $li.addClass('wpsr-ai-review-summary-remaining');
        } else {
            var html = $p.html();
            $p.html('');
            await typeText($p, item.excerpt, enableTextTypingAnimation, $remaining);
            // Adjust the speed of the animation by changing the interval time
        }
    }
}


function truncateHTML(html, wordLimit) {
    let div = document.createElement('div');
    div.innerHTML = html;

    let result = '';
    let wordCount = 0;

    function processNode(node) {
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
    }

    for (let child of div.childNodes) {
        if (wordCount < wordLimit) {
            processNode(child);
        }
    }

    return escapeHtml(result);
}


export function shortNumberFormat(number) {
    let symbol = ["", "k", "M", "G", "T", "P", "E"];
    let tier = Math.log10(number) / 3 | 0;
    // if zero, we don't need a suffix
    if (tier === 0) return number;
    // get suffix and determine scale
    let suffix = symbol[tier];
    let scale = Math.pow(10, tier * 3);
    // scale the number
    let scaled = number / scale;
    // format number and add suffix
    return parseFloat(scaled.toFixed(1)) + suffix;
}

export function carouselOptions(sliderSettings, id, index, $this){
    $this.parent().find(".swiper-button-prev").addClass("wpsr-swiper-btn-prev-" + index);
    $this.parent().find(".swiper-button-next").addClass("wpsr-swiper-btn-next-" + index);
    $this.parent().find(".wpsr-swiper-pagination").addClass("wpsr-swiper-pagination-" + index);

    let options = {
        a11y: window.wpsr_ajax_params.a11y,
        // loop: true,
        // loopFillGroupWithBlank: true,
        // centeredSlides: true,
        // autoHeight: true, // overlap reviews read more
        speed:1000,
        // initialSlide: 0,
        direction: 'horizontal',
        spaceBetween: sliderSettings.spaceBetween ? sliderSettings.spaceBetween : 20,
        // effect: 'slide',
        // loop: true,
        // slidesOffsetBefore: 0,
        // autoHeight: false,
        breakpoints: {
            320: {
                slidesPerView: sliderSettings.responsive_slides_to_show ? sliderSettings.responsive_slides_to_show.mobile : 1,
                slidesPerGroup: sliderSettings.responsive_slides_to_scroll ? sliderSettings.responsive_slides_to_scroll.mobile : 1,
            },
            480: {
                slidesPerView: sliderSettings.responsive_slides_to_show ? sliderSettings.responsive_slides_to_show.mobile : 1,
                slidesPerGroup: sliderSettings.responsive_slides_to_scroll ? sliderSettings.responsive_slides_to_scroll.mobile : 1,
            },
            640: {
                slidesPerView: sliderSettings.responsive_slides_to_show ? sliderSettings.responsive_slides_to_show.tablet : 2,
                slidesPerGroup: sliderSettings.responsive_slides_to_scroll ? sliderSettings.responsive_slides_to_scroll.tablet : 2,
            },
            768: {
                slidesPerView: sliderSettings.responsive_slides_to_show ? sliderSettings.responsive_slides_to_show.tablet : 2,
                slidesPerGroup: sliderSettings.responsive_slides_to_scroll ? sliderSettings.responsive_slides_to_scroll.tablet : 2,
            },
            1024: {
                slidesPerView: sliderSettings.responsive_slides_to_show ? sliderSettings.responsive_slides_to_show.desktop : sliderSettings.slides_to_show,
                slidesPerGroup: sliderSettings.responsive_slides_to_scroll ? sliderSettings.responsive_slides_to_scroll.desktop : sliderSettings.slides_to_scroll,
            },
        }
    };
    if(sliderSettings.autoplay && sliderSettings.autoplay === 'true'){
        options['autoplay'] = {
            delay: parseInt(sliderSettings.autoplay_speed),
            disableOnInteraction: false,
            pauseOnMouseEnter: true
        }
    }
    if(sliderSettings.navigation && (sliderSettings.navigation === 'dot' || sliderSettings.navigation === 'both')){
        options['pagination'] = {
            el: id+' .wpsr-swiper-pagination-'+ index,
            clickable: true
        }
    }
    if(sliderSettings.navigation && (sliderSettings.navigation === 'arrow' || sliderSettings.navigation === 'both')){
        options['navigation'] = {
            nextEl: id+" .wpsr-swiper-btn-next-" + index,
            prevEl: id+" .wpsr-swiper-btn-prev-" + index,
        }
    }
    return options;
}

export function ratingIcon(rating) {
    var stars = '';
  
    for (var i = 0; i < 5; i++) {
      var fillPercentage = '0%';
      var score = rating - i;
  
      if (score >= 1) {
        fillPercentage = '100%';
      } else if (score > 0) {
        fillPercentage = "".concat(score * 100, "%");
      }
  
      var filledClass = parseFloat(fillPercentage) > 10 ? 'wpsr-star-background-filled' : 'wpsr-star-background-empty';
      stars += "\n                            <div class=\"wpsr-star-container ".concat(filledClass, "\" style=\"--wpsr-review-star-fill: ").concat(fillPercentage, ";\">\n                                <div class=\"wpsr-star-empty\"></div>\n                                <div class=\"wpsr-star-filled\"></div>\n                            </div>\n                        ");
    }
  
    return stars;
}

export function openPopupWindow(sharer_url) {
    let params = `scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=800,height=500,left=100,top=100`;
    open(sharer_url, '', params);
}

export function checkBFScrollbar(){
    if (jQuery('html').hasClass('lenis')) {
        jQuery('.wpsr-feed-popup-open').attr('data-lenis-prevent', '');
    }
}

export default {
    generateURLsFromHashTag,
    animateTextTypingEffect,
    formatText,
    nl2br,
    formatDate,
    formatDateWithTime,
    excerpt: excerptItem,
    htmlSubstring,
    appendReadMoreButton,
    shortNumberFormat,
    carouselOptions,
    ratingIcon,
    openPopupWindow,
    checkBFScrollbar
}
