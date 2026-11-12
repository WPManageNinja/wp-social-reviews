/**
 * Frontend review search, sort and rating toolbar. Pro renders it as a sibling before the cards container, so
 * replacing that container never destroys these controls.
 */

// Read per call, not cached: a deferred bundle would otherwise skip the handlers and their preventDefault().
function feedApi() {
    const api = window.wpsrFeedApi;

    return (api && typeof api.feedState === 'function') ? api : null;
}

jQuery(document).ready(function () {
    // Bound unconditionally and delegated: a missing API costs a dead control, not a navigated-away page.
    jQuery(document).on('change', '.wpsr-reviews-sort-select, .wpsr-reviews-rating-select', function () {
        const $wrap = jQuery(this).closest('.wpsr-feed-wrap');

        if ($wrap.length) {
            applyFeedQuery($wrap, false);
        }
    });

    // Enter runs the search and never submits a theme form; Escape clears the box where no clear button exists.
    jQuery(document).on('keydown', '.wpsr-reviews-search-input', function (event) {
        const isEnter = event.key === 'Enter' || event.which === 13;
        const isEscape = event.key === 'Escape' || event.key === 'Esc' || event.which === 27;

        if (!isEnter && !isEscape) {
            return;
        }

        // Already empty, so leave Escape alone: some browsers use it to revert other state.
        if (isEscape && !jQuery(this).val()) {
            return;
        }

        event.preventDefault();

        if (isEscape) {
            jQuery(this).val('');
        }

        const $wrap = jQuery(this).closest('.wpsr-feed-wrap');

        if ($wrap.length) {
            applyFeedQuery($wrap, true);
        }
    });

    // Emptying the box is not a search, so it does not wait for Enter. Anything typed does.
    jQuery(document).on('input', '.wpsr-reviews-search-input', function () {
        if (jQuery(this).val()) {
            return;
        }

        const api = feedApi();
        const $wrap = jQuery(this).closest('.wpsr-feed-wrap');
        const state = (api && $wrap.length) ? api.feedState($wrap) : null;

        // Only when a term is actually applied, or every stray keystroke in an empty box re-fetches page 1.
        if (state && state.appliedSearch) {
            applyFeedQuery($wrap, true);
        }
    });

    // Template 2's star breakdown rows act as a second control for the same rating filter the toolbar owns.
    // PHP only marks a row clickable when the toolbar is active, so no extra gate is needed here.
    jQuery(document).on('click', '.wpsr-clickable-rating-bar', function () {
        handleRatingRowClick(jQuery(this));
    });

    jQuery(document).on('keydown', '.wpsr-clickable-rating-bar', function (event) {
        const isActivate = event.key === 'Enter' || event.key === ' ' || event.which === 13 || event.which === 32;

        if (!isActivate) {
            return;
        }

        // Space would otherwise scroll the page, since the row is a div acting as a button.
        event.preventDefault();
        handleRatingRowClick(jQuery(this));
    });
});

/**
 * Filter the feed to one star row's rating, or clear back to "all ratings" on a second click of the same row.
 */
function handleRatingRowClick($row) {
    const $wrap = $row.closest('.wpsr-feed-wrap');
    const api = feedApi();
    const state = (api && $wrap.length) ? api.feedState($wrap) : null;

    if (!state) {
        return;
    }

    const rowRating = normalizeRating($row.data('rating'));
    const nextRating = (state.appliedRating === rowRating) ? -1 : rowRating;

    const $toolbar = $wrap.find('.wpsr-reviews-sort-toolbar').first();
    const $ratingSelect = $toolbar.find('.wpsr-reviews-rating-select').first();

    if ($ratingSelect.length && hasSelectOption($ratingSelect, String(nextRating))) {
        $ratingSelect.val(String(nextRating));
    }

    // Set directly too: a feed without a rating dropdown still has clickable rows, and applyFeedQuery only
    // reads the select when one exists.
    state.rating = nextRating;
    updateRatingRowState($wrap, nextRating);
    applyFeedQuery($wrap, false);
}

/**
 * Mirror the active rating onto every clickable row, so the highlighted star matches what the server applied.
 */
function updateRatingRowState($wrap, rating) {
    $wrap.find('.wpsr-clickable-rating-bar[data-rating]').each(function () {
        const $row = jQuery(this);
        const isActive = parseInt($row.data('rating'), 10) === rating;

        $row.toggleClass('wpsr-active-progressbar-filter', isActive);
        $row.attr('aria-pressed', isActive ? 'true' : 'false');
    });
}

/**
 * Re-fetch one feed with the chosen sort, rating and search term, holding the old cards until it lands.
 *
 * useTypedSearch says whether the visitor submitted the box. Enter and clearing it pass true; a dropdown
 * passes false and keeps the term already on screen, so half-typed text is never searched behind their back.
 */
function applyFeedQuery($wrap, useTypedSearch) {
    const api = feedApi();

    // Only when the main bundle never ran; do nothing rather than throw on every change.
    if (!api) {
        return;
    }

    const $toolbar = $wrap.find('.wpsr-reviews-sort-toolbar').first();
    const isSlider = $wrap.hasClass('wpsr-reviews-layout-slider');

    // A slider fills .swiper-wrapper and has no .wpsr_feeds; everything below writes through this one reference.
    const $feeds = isSlider
        ? $wrap.find('.swiper-wrapper').first()
        : $wrap.find('.wpsr_feeds').first();

    const state = api.feedState($wrap);
    const templateId = parseInt($toolbar.data('template_id'), 10);

    // No toolbar, container or id: do nothing rather than empty the wrong element.
    if (!state || !$toolbar.length || !$feeds.length || !templateId) {
        return;
    }

    const $sortSelect = $toolbar.find('.wpsr-reviews-sort-select').first();
    const $ratingSelect = $toolbar.find('.wpsr-reviews-rating-select').first();
    const $searchInput = $toolbar.find('.wpsr-reviews-search-input').first();
    const $btn = $wrap.find('.wpsr_more').first();

    // Read every control, so sort, rating and search compose instead of resetting each other.
    if ($sortSelect.length) {
        state.order = String($sortSelect.val() || '');
    }

    if ($ratingSelect.length) {
        state.rating = normalizeRating($ratingSelect.val());
    }

    // Normalized the way the server will, so the guard below can compare it against what the server echoes back.
    if ($searchInput.length) {
        state.search = useTypedSearch ? normalizeSearch($searchInput.val()) : state.appliedSearch;
    }

    const queryKey = feedQueryKey(state.order, state.rating, state.search);

    const appliedKey = feedQueryKey(state.appliedOrder, state.appliedRating, state.appliedSearch);

    // Both keys, or flipping back mid-flight re-fetches page 1 and drops loaded pages. failedKey allows a retry.
    if (queryKey !== state.failedKey
        && (queryKey === state.pendingKey || queryKey === appliedKey)) {
        return;
    }

    const seq = ++state.seq;

    // A newer choice supersedes the one in flight. The selects stay enabled, or focus would move.
    if (state.xhr) {
        state.xhr.abort();
    }

    state.pendingKey = queryKey;
    // Spent on use, or a held Enter fires ~30 requests and the 429 locks the visitor out.
    state.failedKey = null;
    setFeedMessage($toolbar, '');
    $feeds.addClass('wpsr-loading-feeds').attr('aria-busy', 'true');
    $btn.prop('disabled', true);

    // Hold the track still while its slides are replaced; always() starts it again.
    if (isSlider) {
        api.setSliderAutoplay($wrap, false);
    }

    const params = {
        action: 'wpsr_get_more_feeds',
        page: 1,
        template_id: templateId,
        // The endpoint dispatches on this, and the toolbar only renders on reviews feeds.
        platform: 'reviews',
        rating: state.rating,
        // Sent even when empty: clearing the box is a query of its own.
        search: state.search || '',
        // Product scope, or a re-fetch falls back to every product in the store.
        product_id: parseInt($toolbar.data('product_id'), 10) || 0,
        security: wpsr_ajax_params.wpsr_nonce
    };

    // The server rejects 'random', so sending no order falls back to the saved random order.
    if (state.order && state.order !== 'random') {
        params.order = state.order;
    }

    // Only random feeds carry a seed; sending it back keeps every query on this feed repeatable.
    const seed = parseInt($toolbar.data('seed'), 10);

    if (seed > 0) {
        params.seed = seed;
    }

    // Claimed here, not before the DOM work: a throw up there would leave the lock set with nothing to clear it.
    state.inflight = true;

    const xhr = jQuery.get(wpsr_ajax_params.ajax_url, params);
    state.xhr = xhr;

    xhr.then(function (response) {
        if (seq !== state.seq) {
            return;
        }

        const content = (response && typeof response.content === 'string') ? response.content : '';
        const total = parseInt(response && response.total_reviews, 10) || 0;
        // Only a searched query sends this; null everywhere else, where total_reviews is a real count.
        const hasMore = (response && typeof response.has_more === 'boolean') ? response.has_more : null;
        const isMasonry = $wrap.hasClass('wpsr-reviews-layout-masonry');

        if (isSlider) {
            // Unhide the track first: Swiper measures a display:none element as zero.
            setSliderEmptyState($wrap, '');
        }

        // Only now, with a good response in hand, drop the old content.
        $feeds.empty();

        if (content.trim()) {
            $feeds.html(content);
        } else if (!isSlider) {
            // Sliders take the branch above: Swiper would measure this message as a slide.
            $feeds.append(jQuery('<div class="wpsr-reviews-empty-state"></div>')
                .text(wpsr_ajax_params.no_reviews_found || ''));

            // Masonry leaves an inline height behind, which would crop the message to nothing.
            if (isMasonry && $feeds.data('masonry')) {
                $feeds.masonry('destroy');
                $feeds.css('height', '');
            }
        }

        // The server is the authority on what was applied, not what was asked for.
        state.order = (response && response.order) ? String(response.order) : state.order;
        state.rating = normalizeRating(response && response.exactRating);
        // Typed, not truthy: '' is a real answer here, meaning the server dropped the term.
        state.search = (response && typeof response.search === 'string') ? response.search : state.search;

        // This query is now on screen, so it becomes the one a later failure reverts to.
        state.appliedOrder = state.order;
        state.appliedRating = state.rating;
        state.appliedSearch = state.search;

        // This query landed, so it is no longer the failed one and the guard may hold for it again.
        state.failedKey = null;

        syncFeedControls($toolbar, $sortSelect, $ratingSelect, state);
        updateRatingRowState($wrap, state.rating);
        syncLoadMoreButton($btn, state, total, hasMore);

        if (content.trim() && isMasonry) {
            api.relayoutMasonry('masonry', $wrap);
        }

        // Swiper still drives navigation from the removed slides until this runs — empty results included.
        if (isSlider) {
            api.relayoutSlider($wrap);

            if (!content.trim()) {
                setSliderEmptyState($wrap, wpsr_ajax_params.no_reviews_found || '');
            }
        }

        // Safe globally: it skips anything already carrying wpsr_more_added.
        api.appendReadMoreButton(wpsr_ajax_params.read_more, wpsr_ajax_params.read_less);

        // The notification card cycles these cards by position, so tell it the list changed.
        if (window.wpsrNotificationApi
            && typeof window.wpsrNotificationApi.resyncFeed === 'function') {
            window.wpsrNotificationApi.resyncFeed(templateId);
        }

        // Count the cards that landed: `total` is the full count, which the visitor may never reach.
        const shown = isSlider
            ? $feeds.children('.swiper-slide').length
            : $feeds.children().not('.wpsr-reviews-empty-state').length;

        announceFeedUpdate($toolbar, shown, total);
    }, function () {
        // then()'s 2nd arg, not .catch(), which would also catch success-handler throws. Token, not jqXHR.status.
        if (seq !== state.seq) {
            return;
        }

        // The old cards are still on screen, so put the controls back to the applied query.
        state.order = state.appliedOrder;
        state.rating = state.appliedRating;
        // The term reverts in state only; the typed text stays exactly as it is, caret included.
        state.search = state.appliedSearch;
        // Names the failed query, so retrying exactly it passes the guard but an unrelated event does not.
        state.failedKey = queryKey;
        syncFeedControls($toolbar, $sortSelect, $ratingSelect, state);
        updateRatingRowState($wrap, state.rating);

        // A failed clear leaves the box empty over an applied term; safe to write back only because it is empty.
        if ($searchInput.length && !$searchInput.val() && state.appliedSearch) {
            $searchInput.val(state.appliedSearch);
        }

        setFeedMessage($toolbar, wpsr_ajax_params.reviews_update_failed || '');
    }).always(function () {
        // A superseded response must not release a feed the newer request owns.
        if (seq !== state.seq) {
            return;
        }

        state.inflight = false;
        state.xhr = null;
        // Back to null, so the guard compares against what is applied rather than a query no longer in flight.
        state.pendingKey = null;
        $feeds.removeClass('wpsr-loading-feeds').attr('aria-busy', 'false');
        // Spinner too: this may have superseded a load-more whose cleanup bailed out.
        $btn.removeClass('wpsr-spinner-loading').prop('disabled', false);

        // Let the track move again, unless the filter left it with no slides to play.
        if (isSlider) {
            api.setSliderAutoplay($wrap, $feeds.children('.swiper-slide').length > 0);
        }
    });
}

/**
 * Show or clear a slider's "no reviews" message, placed next to the swiper root because Swiper measures every
 * child of the track as a slide.
 */
function setSliderEmptyState($wrap, text) {
    let $message = $wrap.find('.wpsr-reviews-empty-state').first();

    if (!text) {
        $message.remove();
        $wrap.removeClass('wpsr-slider-empty');
        return;
    }

    if (!$message.length) {
        $message = jQuery('<div class="wpsr-reviews-empty-state"></div>');
        $wrap.find('.swiper-container').first().after($message);
    }

    $message.text(text);

    // A class, not display:none on the root: the arrows and dots are siblings that must go too.
    $wrap.addClass('wpsr-slider-empty');
}

/**
 * Coerce anything into a rating the server accepts: 1-5, or -1 for "all ratings".
 */
function normalizeRating(value) {
    const rating = parseInt(value, 10);

    return (rating >= 1 && rating <= 5) ? rating : -1;
}

/**
 * The query one feed is showing, as one comparable string.
 */
function feedQueryKey(order, rating, search) {
    return String(order) + ' ' + String(rating) + ' ' + String(search);
}

/**
 * Bound a typed term the way the server does (Helper::sanitizeVisitorSearch): collapse whitespace runs, trim,
 * cap at 100 characters, and drop anything left under 2. Never lowercased, because the server does not either
 * and the no-op guard compares this against the term it echoes back.
 */
function normalizeSearch(value) {
    if (!value || typeof value !== 'string') {
        return '';
    }

    const collapsed = value.replace(/\s+/g, ' ').trim();

    // By code point, not UTF-16 unit, so the cap lands where mb_substr() does; re-trimmed as the server does.
    const capped = Array.from(collapsed).slice(0, 100).join('').trim();

    return Array.from(capped).length < 2 ? '' : capped;
}

/**
 * Does this select offer that value? Compared in a loop so it is never spliced into a selector.
 */
function hasSelectOption($select, value) {
    return $select.find('option').filter(function () {
        return this.value === value;
    }).length > 0;
}

/**
 * Point the toolbar and its selects at what the server actually applied.
 */
function syncFeedControls($toolbar, $sortSelect, $ratingSelect, state) {
    const search = state.search || '';

    // Attribute and cache both: jQuery keeps the value it read first, so setting one leaves the other stale.
    $toolbar.attr('data-order', state.order).data('order', state.order);
    $toolbar.attr('data-rating', state.rating).data('rating', state.rating);
    $toolbar.attr('data-search', search).data('search', search);

    if ($sortSelect.length && hasSelectOption($sortSelect, state.order)) {
        $sortSelect.val(state.order);
    }

    if ($ratingSelect.length && hasSelectOption($ratingSelect, String(state.rating))) {
        $ratingSelect.val(String(state.rating));
    }

    // The input is left alone: writing to it moves the caret and drops anything typed since the request left.
}

/**
 * Resize the load-more button's idea of the feed. A missing button is never created: a filter can only narrow.
 */
function syncLoadMoreButton($btn, state, total, hasMore) {
    if (!$btn.length) {
        return;
    }

    const paginate = parseInt($btn.data('paginate'), 10) || 0;

    // A search does not count its matches, so total is only a floor there and the server answers this directly.
    const more = (typeof hasMore === 'boolean') ? hasMore : (total > paginate);

    $btn.data('page', 1)
        .data('total', total)
        .data('has_more', more)
        .data('order', state.order)
        .data('rating', state.rating);

    if (paginate && more) {
        $btn.show();
    } else {
        $btn.hide();
    }
}

/**
 * Announce the cards now on screen, after the swap; `total` only says whether more matched.
 */
function announceFeedUpdate($toolbar, shown, total) {
    const $status = $toolbar.find('.wpsr-reviews-sort-status').first();

    if (!$status.length) {
        return;
    }

    const range = wpsr_ajax_params.reviews_showing_range || '';
    let message;

    if (!shown) {
        message = wpsr_ajax_params.no_reviews_found || '';
    } else if (total > shown && range) {
        // Falls through to the plain count when an older params object has no range string.
        message = range.replace('%1$s', shown).replace('%2$s', total);
    } else if (shown === 1) {
        message = wpsr_ajax_params.reviews_showing_one || '';
    } else {
        message = (wpsr_ajax_params.reviews_showing_many || '').replace('%s', shown);
    }

    $status.text(message);
}

/**
 * Show or clear the toolbar's message line, and mirror it into the live region.
 */
function setFeedMessage($toolbar, text) {
    let $message = $toolbar.find('.wpsr-reviews-sort-message').first();

    if (!text) {
        $message.remove();
        return;
    }

    if (!$message.length) {
        $message = jQuery('<div class="wpsr-reviews-sort-message"></div>');
        $toolbar.append($message);
    }

    $message.text(text);
    $toolbar.find('.wpsr-reviews-sort-status').first().text(text);
}
