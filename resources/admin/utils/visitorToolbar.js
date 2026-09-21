/**
 * Where the visitor search/sort/rating toolbar can render. Mirrors pro's shouldRenderSortToolbar() and
 * Helper::supportsVisitorSorting(); the editor asks twice (offer the switch, preview the bar). Keep in step.
 */

// The layouts that draw a card container the load-more endpoint can replace.
const WPSR_CARD_LAYOUTS = [
    'grid1', 'grid2', 'grid3', 'grid4', 'grid5',
    'grid6', 'grid7', 'grid8', 'grid9', 'grid10',
    'testimonial1', 'testimonial2',
];

const WPSR_TOOLBAR_TEMPLATE_TYPES = ['grid', 'masonry', 'slider', 'badge', 'notification'];

// booking.com stores 1-10 ratings, which the 1-5 dropdown cannot describe, so both render sites refuse it.
export function wpsrToolbarSupportsPlatforms(platforms) {
    return !(platforms || []).includes('booking.com');
}

// The structural gate. Does not read the owner's switch, because it also decides whether to show it.
export function wpsrToolbarSupported(meta) {
    const wpsrMeta = meta || {};

    if (!wpsrToolbarSupportsPlatforms(wpsrMeta.platform)) {
        return false;
    }

    if (!WPSR_TOOLBAR_TEMPLATE_TYPES.includes(wpsrMeta.templateType)) {
        return false;
    }

    // Outside popup mode neither panel has a feed to browse; these settings objects may be absent.
    if (wpsrMeta.templateType === 'badge'
        && (wpsrMeta.badge_settings || {}).display_mode !== 'popup') {
        return false;
    }

    if (wpsrMeta.templateType === 'notification'
        && (wpsrMeta.notification_settings || {}).display_mode !== 'popup') {
        return false;
    }

    return WPSR_CARD_LAYOUTS.includes(wpsrMeta.template);
}

// Will a toolbar really render? Structural gate plus the owner's switch plus Pro, which the preview needs.
export function wpsrToolbarActive(meta, hasPro) {
    if (!hasPro || !wpsrToolbarSupported(meta)) {
        return false;
    }

    // Off unless turned on, and only the exact string 'true' counts, matching pro's Arr::get default.
    return (meta || {}).enable_visitor_sorting === 'true';
}

// Is any column left to search? Mirrors hasSearchableReviewColumn() and visitorSearchPredicates(); keep in step.
export function wpsrToolbarSearchEnabled(meta) {
    const wpsrMeta = meta || {};

    if (wpsrMeta.isReviewerText !== 'false') {
        return true;
    }

    if (wpsrMeta.display_review_title === 'true') {
        return true;
    }

    // Names only on the one format that prints them whole.
    const showsName = wpsrMeta.reviewer_name === true
        || wpsrMeta.reviewer_name === 'true'
        || wpsrMeta.reviewer_name === 1
        || wpsrMeta.reviewer_name === '1';

    return showsName && (wpsrMeta.reviewer_name_format || 'full-name') === 'full-name';
}

// As pro's getReviewSortOptions() builds them; a random feed gets "Featured" so the control can show its order.
export function wpsrToolbarSortOptions(meta, translate) {
    const t = translate || ((s) => s);
    const wpsrOrder = (meta || {}).order;

    const options = [
        { value: 'newest', label: t('Newest first') },
        { value: 'oldest', label: t('Oldest first') },
        { value: 'rating_desc', label: t('Highest rated') },
        { value: 'rating_asc', label: t('Lowest rated') },
    ];

    if (wpsrOrder === 'random') {
        options.unshift({ value: 'random', label: t('Featured') });
    }

    // A single option is a control the visitor can open but never change.
    return options.length < 2 ? [] : options;
}

/**
 * The rating dropdown's options, as pro's getReviewRatingOptions() builds them: never below the owner's floor.
 */
export function wpsrToolbarRatingOptions(meta, translate) {
    const t = translate || ((s) => s);
    const wpsrFloor = parseInt((meta || {}).starFilterVal, 10) || -1;
    const options = [];

    for (let rating = 5; rating >= 1; rating--) {
        if (wpsrFloor > 0 && rating < wpsrFloor) {
            continue;
        }

        // Same msgids pro's _n('%d Star', '%d Stars') uses, or the preview and the live bar read differently.
        const label = (rating === 1 ? t('%d Star') : t('%d Stars')).replace('%d', rating);

        options.push({ value: rating, label: label });
    }

    // One rating left means it and "All ratings" return the same reviews (admin floor of 5).
    if (options.length < 2) {
        return [];
    }

    options.unshift({ value: -1, label: t('All ratings') });

    return options;
}

// The order the dropdown arrives showing; 'desc'/'asc' are legacy keys, resolved as Helper::resolveOrderKey().
export function wpsrToolbarCurrentOrder(meta) {
    const wpsrOrder = String((meta || {}).order || '').toLowerCase();
    const legacy = { desc: 'newest', asc: 'oldest' };

    return legacy[wpsrOrder] || wpsrOrder || 'newest';
}
