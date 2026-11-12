import TwitterPopup from './twitter-popup.js';
import YoutubePopup from './youtube-popup.js';
import InstagramPopup from './_instagram-popup';
import FacebookPopup from './_facebook-popup';
import TiktokPopup from './_tiktok-popup';
import ReviewPhotos from './_review-photos';

import Helper, { wpsrRelayoutMasonry } from './helper.js';

window.wpsrHelper = Helper;

jQuery(document).ready(function ($) {
    $(document).on('click', '.wpsr-yt-video-playmode', function (e) {
        YoutubePopup.init(e, this);
    });

    // template5's whole row looks clickable, but only the thumbnail/title are wired, so forward the rest here.
    // dispatchEvent rather than jQuery's .trigger(), which its own re-entrancy guard swallows.
    $(document).on('click', '.wpsr-yt-feed-template5 .wpsr-yt-all-feed .wpsr-yt-video', function (e) {
        if ($(e.target).closest('.wpsr-yt-video-playmode').length) {
            return;
        }
        let target = this.querySelector('.wpsr-yt-video-preview.wpsr-yt-video-playmode');
        if (target) {
            target.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true, view: window}));
        }
    });

    $(document).on('click', '.wpsr-twitter-playmode', function (e) {
        TwitterPopup.init(e, this);
    });

    $(document).on('click', '.wpsr-ig-playmode', function (e) {
        InstagramPopup.init(e, this);
    });

    $(document).on('click', '.wpsr-fb-feed-playmode', function (e) {
        FacebookPopup.init(e, this);
    });

    $(document).on('click', '.wpsr-tiktok-feed-playmode, .wpsr-tiktok-feed-video-playmode, .wpsr-tiktok-icon-position', function (e) {
        TiktokPopup.init(e, this);
    });

    $(document).on('click', '.wpsr-review-photos-playmode', function (e) {
        ReviewPhotos.init(e, this);
    });

    // Element that opened the popup, so focus can go back there on close.
    let wpsrPopupOpener = null;

    const wpsrFocusableInPopup = ($overlay) => $overlay
        .find('a[href], button, video[controls], [tabindex]:not([tabindex="-1"])')
        .filter(':visible');

    function wpsrCloseFeedPopup() {
        const $overlay = $('.wpsrm-overlay');
        if (!$overlay.length) {
            return;
        }

        let video = jQuery("#wpsr-feed-popup-video");
        if (video.length) {
            video[0].pause();
        }

        $overlay.remove();
        document.body.classList.remove('wpsr-feed-popup-active');

        if (wpsrPopupOpener && document.body.contains(wpsrPopupOpener)) {
            wpsrPopupOpener.focus();
        }
        wpsrPopupOpener = null;
    }

    // Popups are appended by each platform's init(), so adopt whichever one just opened.
    function wpsrAdoptOpenPopup(opener) {
        const $overlay = $('.wpsrm-overlay');
        if (!$overlay.length) {
            return;
        }

        wpsrPopupOpener = opener || null;
        $overlay.attr({'role': 'dialog', 'aria-modal': 'true'});
        if (!$overlay.attr('aria-label')) {
            $overlay.attr('aria-label', 'Post details');
        }

        const $target = $overlay.find('.wpsrm_close').first();
        ($target.length ? $target : $overlay).attr('tabindex', $target.length ? null : '-1').focus();
    }

    // Every popup opens through one of the .init() handlers above.
    $(document).on('click', '.wpsr-yt-video-playmode, .wpsr-twitter-playmode, .wpsr-ig-playmode, .wpsr-fb-feed-playmode, .wpsr-tiktok-feed-playmode, .wpsr-tiktok-feed-video-playmode, .wpsr-tiktok-icon-position, .wpsr-review-photos-playmode', function () {
        const opener = this;
        window.setTimeout(function () {
            wpsrAdoptOpenPopup(opener);
        }, 0);
    });

    $(document).on('keydown', function (e) {
        const $overlay = $('.wpsrm-overlay');
        if (!$overlay.length) {
            return;
        }

        if (e.key === 'Escape' || e.key === 'Esc') {
            e.preventDefault();
            wpsrCloseFeedPopup();
            return;
        }

        if (e.key !== 'Tab') {
            return;
        }

        // Keep Tab inside the dialog instead of walking the page behind it.
        const $focusable = wpsrFocusableInPopup($overlay);
        if (!$focusable.length) {
            e.preventDefault();
            return;
        }

        const first = $focusable.first()[0];
        const last = $focusable.last()[0];
        const active = document.activeElement;

        if (!$overlay[0].contains(active)) {
            e.preventDefault();
            first.focus();
        } else if (e.shiftKey && active === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && active === last) {
            e.preventDefault();
            first.focus();
        }
    });

    $(document).on('click', function (e) {
        if ($(e.target).closest(".wpsrm_close").length ||  $(e.target).has(".wpsrm_inner").length) {
            wpsrCloseFeedPopup();
        }
    });

    $(document).on("click", ".wpsr_read_more, .wpsr_read_less", function () {
        $(this).closest(".wpsr-yt-popup-video-meta-description, .wpsr-yt-popup-video-comment-text-inner").toggleClass("wpsr_show_less_content wpsr_show_more_content");
        // The card's own container, so a second template on the page keeps its columns.
        wpsrRelayoutMasonry($(this).closest('.wpsr-active-masonry-layout'));
    });
});