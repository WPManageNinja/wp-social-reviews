// Marquee layout. Each row gets hidden copies of its cards so the CSS loop (translateX(-50%)) is
// seamless; rows stay static and scrollable until this runs, and always under reduced motion.
const FOCUSABLE = 'a, button, input, select, textarea, iframe, [tabindex]';

// Copies are duplicates for the loop only: hidden from assistive tech and the tab order, and a video
// becomes its poster (players are built at runtime, so a copied one would never work).
function copyCard(card) {
    const copy = card.cloneNode(true);
    copy.setAttribute('data-wpsr-clone', '');
    copy.setAttribute('aria-hidden', 'true');
    copy.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
    copy.querySelectorAll(FOCUSABLE).forEach(el => el.setAttribute('tabindex', '-1'));
    copy.querySelectorAll('.wpsr-testimonial-video').forEach(box => {
        const poster = box.querySelector('[poster], img.fluent-player-loader-poster');
        const still = document.createElement('div');
        still.className = 'wpsr-marquee-video-still';
        if (poster) {
            still.style.backgroundImage = 'url("' + (poster.getAttribute('poster') || poster.getAttribute('src') || '') + '")';
        }
        box.replaceChildren(still);
    });
    return copy;
}

function fillRow(row, pxPerSecond) {
    const track = row.querySelector('.wpsr-marquee-track');
    track.querySelectorAll('[data-wpsr-clone]').forEach(el => el.remove());
    const cards = Array.from(track.children);
    const setWidth = track.scrollWidth;
    if (!cards.length || !setWidth) {
        return;
    }

    // Half the track must be at least a row wide, or the loop shows a gap before it restarts.
    const repeats = Math.max(1, Math.ceil(row.clientWidth / setWidth));
    for (let i = 1; i < repeats * 2; i++) {
        cards.forEach(card => track.appendChild(copyCard(card)));
    }

    track.style.setProperty('--wpsr-marquee-duration', (setWidth * repeats / pxPerSecond).toFixed(2) + 's');
    row.classList.add('wpsr-marquee-ready');
}

function initWrapper(wrapper) {
    const toggle = wrapper.querySelector('.wpsr-marquee-toggle');
    const setPaused = paused => {
        wrapper.classList.toggle('wpsr-marquee-paused', paused);
        toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
        toggle.setAttribute('aria-label', toggle.getAttribute(paused ? 'data-label_play' : 'data-label_pause'));
        toggle.querySelector('i').className = paused ? 'icon-play' : 'icon-pause';
    };
    toggle.addEventListener('click', () => setPaused(!wrapper.classList.contains('wpsr-marquee-paused')));

    // A video in use must not drift away, so the rows stop until the toggle resumes them. Media "play"
    // does not bubble (capture phase); a YouTube/Vimeo frame only shows up as the page losing focus.
    wrapper.addEventListener('play', () => setPaused(true), true);
    wrapper.addEventListener('pointerdown', e => {
        if (e.target.closest('.wpsr-testimonial-video')) {
            setPaused(true);
        }
    }, true);
    window.addEventListener('blur', () => setTimeout(() => {
        if (document.activeElement && document.activeElement.tagName === 'IFRAME' && wrapper.contains(document.activeElement)) {
            setPaused(true);
        }
    }));

    // Clicks in a copy (Read More) still work, but must not put focus inside aria-hidden content.
    wrapper.addEventListener('mousedown', e => {
        if (e.target.closest('[aria-hidden="true"]')) {
            e.preventDefault();
        }
    }, true);

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const rows = Array.from(wrapper.querySelectorAll('.wpsr-marquee-row'));
    const pxPerSecond = parseInt(rows[0].getAttribute('data-speed'), 10) || 35;
    rows.forEach(row => fillRow(row, pxPerSecond));

    let width = wrapper.clientWidth;
    let timer;
    window.addEventListener('resize', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (wrapper.clientWidth !== width) {
                width = wrapper.clientWidth;
                rows.forEach(row => fillRow(row, pxPerSecond));
            }
        }, 200);
    });
}

function init() {
    document.querySelectorAll('.wpsr-reviews-layout-marquee').forEach(wrapper => {
        if (wrapper.querySelector('.wpsr-marquee-row') && !wrapper.hasAttribute('data-wpsr-marquee')) {
            wrapper.setAttribute('data-wpsr-marquee', '');
            initWrapper(wrapper);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
