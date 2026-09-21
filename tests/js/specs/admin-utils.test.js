import { createRequire } from 'node:module';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    debounce,
    findIndex,
    findLastIndex,
    throttle,
    wpsrBuildAuthReturnUrl,
} from '../../../resources/admin/utils.js';
import { wpsrUpgradeUrl } from '../../../resources/admin/utils/upgradeUrl.js';
import {
    wpsrToolbarActive,
    wpsrToolbarCurrentOrder,
    wpsrToolbarRatingOptions,
    wpsrToolbarSearchEnabled,
    wpsrToolbarSortOptions,
    wpsrToolbarSupported,
    wpsrToolbarSupportsPlatforms,
} from '../../../resources/admin/utils/visitorToolbar.js';

const wpsrRequire = createRequire(import.meta.url);
const {
    wpsrBulkDeleteReviewPlatforms,
    wpsrCanBulkDeleteReview,
    wpsrFilterBulkDeleteReviews,
} = wpsrRequire('../../../resources/admin/utils/reviewBulkSelection.js');

beforeEach(() => {
    window.WPSocialReviewsAdmin = {};
    window.sessionStorage.clear();
    window.history.replaceState({}, '', 'https://site.test/wp-admin/admin.php?page=wpsocialninja.php');
});

afterEach(() => {
    vi.useRealTimers();
});

describe('debounce and throttle', () => {
    it('rejects non-callable debounce inputs', () => {
        expect.assertions(2);
        expect(() => debounce(null, 10)).toThrowError('Expected a function');
        expect(() => throttle('nope', 10)).toThrowError('Expected a function');
    });

    it('debounces to trailing call with latest context and arguments', () => {
        expect.assertions(8);
        vi.useFakeTimers();
        const wpsrCalls = [];
        const wpsrContext = { marker: 'context' };
        const wpsrDebounced = debounce(function (wpsrValue) {
            wpsrCalls.push([this.marker, wpsrValue]);
            return `done-${wpsrValue}`;
        }, 100);

        expect(wpsrDebounced.call(wpsrContext, 'first')).toBeUndefined();
        expect(wpsrDebounced.pending()).toBe(true);
        vi.advanceTimersByTime(50);
        wpsrDebounced.call(wpsrContext, 'latest');
        expect(wpsrCalls).toEqual([]);
        vi.advanceTimersByTime(99);
        expect(wpsrCalls).toEqual([]);
        vi.advanceTimersByTime(1);
        expect(wpsrCalls).toEqual([['context', 'latest']]);
        expect(wpsrDebounced.pending()).toBe(false);
        expect(wpsrDebounced.flush()).toBe('done-latest');
        expect(vi.getTimerCount()).toBe(0);
    });

    it('supports leading execution, cancellation, and explicit flush', () => {
        expect.assertions(8);
        vi.useFakeTimers();
        const wpsrFunction = vi.fn((wpsrValue) => `value-${wpsrValue}`);
        const wpsrLeading = debounce(wpsrFunction, 100, { leading: true, trailing: false });

        expect(wpsrLeading('one')).toBe('value-one');
        expect(wpsrLeading('two')).toBe('value-one');
        expect(wpsrFunction).toHaveBeenCalledTimes(1);
        wpsrLeading.cancel();
        expect(wpsrLeading.pending()).toBe(false);

        const wpsrTrailing = debounce(wpsrFunction, 100);
        expect(wpsrTrailing('three')).toBeUndefined();
        expect(wpsrTrailing.flush()).toBe('value-three');
        expect(wpsrFunction).toHaveBeenCalledTimes(2);
        expect(wpsrTrailing.pending()).toBe(false);
    });

    it('enforces maxWait during repeated calls', () => {
        expect.assertions(4);
        vi.useFakeTimers();
        const wpsrFunction = vi.fn();
        const wpsrDebounced = debounce(wpsrFunction, 100, { maxWait: 250 });

        wpsrDebounced('zero');
        vi.advanceTimersByTime(90);
        wpsrDebounced('ninety');
        vi.advanceTimersByTime(90);
        wpsrDebounced('one-eighty');
        expect(wpsrFunction).not.toHaveBeenCalled();
        vi.advanceTimersByTime(70);
        expect(wpsrFunction).toHaveBeenCalledTimes(1);
        expect(wpsrFunction).toHaveBeenLastCalledWith('one-eighty');
        expect(wpsrDebounced.pending()).toBe(false);
    });

    it('throttles leading and trailing calls with latest payload', () => {
        expect.assertions(4);
        vi.useFakeTimers();
        const wpsrFunction = vi.fn();
        const wpsrThrottled = throttle(wpsrFunction, 100);

        wpsrThrottled('first');
        wpsrThrottled('second');
        expect(wpsrFunction).toHaveBeenCalledTimes(1);
        expect(wpsrFunction).toHaveBeenLastCalledWith('first');
        vi.advanceTimersByTime(100);
        expect(wpsrFunction).toHaveBeenCalledTimes(2);
        expect(wpsrFunction).toHaveBeenLastCalledWith('second');
    });
});

describe('index lookup shorthands and boundaries', () => {
    it('finds forward values with function, property, pair, and object predicates', () => {
        expect.assertions(7);
        const wpsrRows = [
            { id: 1, active: false, type: 'review' },
            { id: 2, active: true, type: 'review' },
            { id: 3, active: true, type: 'testimonial' },
        ];

        expect(findIndex(wpsrRows, (wpsrRow) => wpsrRow.id > 1)).toBe(1);
        expect(findIndex(wpsrRows, 'active')).toBe(1);
        expect(findIndex(wpsrRows, ['type', 'testimonial'])).toBe(2);
        expect(findIndex(wpsrRows, { active: true, type: 'review' })).toBe(1);
        expect(findIndex(wpsrRows, ['id', 1], 1)).toBe(-1);
        expect(findIndex(wpsrRows, ['id', 2], -2)).toBe(1);
        expect(findIndex(null, 'active')).toBe(-1);
    });

    it('finds reverse values with clamped, negative, and malformed indexes', () => {
        expect.assertions(7);
        const wpsrValues = [0, 1, 0, 2];

        expect(findLastIndex(wpsrValues)).toBe(3);
        expect(findLastIndex(wpsrValues, (wpsrValue) => wpsrValue === 0)).toBe(2);
        expect(findLastIndex(wpsrValues, (wpsrValue) => wpsrValue === 0, 1)).toBe(0);
        expect(findLastIndex(wpsrValues, (wpsrValue) => wpsrValue === 2, 99)).toBe(3);
        expect(findLastIndex(wpsrValues, (wpsrValue) => wpsrValue === 1, -3)).toBe(1);
        expect(findLastIndex(wpsrValues, (wpsrValue) => wpsrValue === 0, 'bad')).toBe(0);
        expect(findLastIndex([], Boolean)).toBe(-1);
    });
});

describe('auth return URL construction', () => {
    it('uses fallback and appends encoded auth platform exactly once', () => {
        expect.assertions(3);
        expect(wpsrBuildAuthReturnUrl('', 'facebook reviews', 'https://bridge.test/start')).toBe(
            'https://bridge.test/start?wpsn_auth_platform=facebook%20reviews'
        );
        expect(wpsrBuildAuthReturnUrl('', 'google', 'https://bridge.test/start?flow=reviews')).toBe(
            'https://bridge.test/start?flow=reviews&wpsn_auth_platform=google'
        );
        expect(wpsrBuildAuthReturnUrl('', '', 'https://bridge.test/start')).toBe('https://bridge.test/start');
    });

    it('preserves editor context in session storage and returns admin page URL', () => {
        expect.assertions(4);
        window.history.replaceState(
            {},
            '',
            'https://site.test/wp-admin/admin.php?page=wpsocialninja.php#/edit-template/77'
        );

        const wpsrResult = wpsrBuildAuthReturnUrl(
            'edit-template',
            'google',
            'https://bridge.test/fallback'
        );

        expect(wpsrResult).toBe(
            'https://site.test/wp-admin/admin.php?page=wpsocialninja.php&wpsn_auth_platform=google'
        );
        expect(JSON.parse(window.sessionStorage.getItem('wpsn_auth_return_route'))).toEqual({
            name: 'edit-template',
            templateId: '77',
        });
        expect(window.location.hash).toBe('#/edit-template/77');
        expect(window.location.search).toBe('?page=wpsocialninja.php');
    });

    it('uses explicit editor ID and rejects non-editor or ID-less contexts', () => {
        expect.assertions(3);
        window.history.replaceState({}, '', 'https://site.test/wp-admin/admin.php?page=custom#/edit-template/no-id');
        expect(wpsrBuildAuthReturnUrl('edit-template', '', 'fallback', '88')).toBe(
            'https://site.test/wp-admin/admin.php?page=custom'
        );
        expect(JSON.parse(window.sessionStorage.getItem('wpsn_auth_return_route'))).toEqual({
            name: 'edit-template',
            templateId: '88',
        });
        window.history.replaceState({}, '', 'https://site.test/wp-admin/admin.php?page=custom#/reviews');
        expect(wpsrBuildAuthReturnUrl('edit-template', '', 'fallback')).toBe('fallback');
    });
});

describe('upgrade URL placement', () => {
    it('sets placement while preserving PHP-built campaign parameters', () => {
        expect.assertions(5);
        window.WPSocialReviewsAdmin = {
            upgrade_btn_config: {
                pro_purchase_url: 'https://wpsocialninja.com/?utm_source=plugin&utm_content=old&utm_id=sale',
            },
            upgrade_url_base: 'https://fallback.test/',
        };

        const wpsrResult = new URL(wpsrUpgradeUrl('feature_lock_notifications'));

        expect(wpsrResult.origin).toBe('https://wpsocialninja.com');
        expect(wpsrResult.searchParams.get('utm_source')).toBe('plugin');
        expect(wpsrResult.searchParams.get('utm_content')).toBe('feature_lock_notifications');
        expect(wpsrResult.searchParams.get('utm_id')).toBe('sale');
        expect(window.WPSocialReviewsAdmin.upgrade_btn_config.pro_purchase_url).toContain('utm_content=old');
    });

    it('honors explicit base and preserves empty or unparseable values', () => {
        expect.assertions(4);
        expect(wpsrUpgradeUrl('top_bar', 'https://partner.test/buy?ref=1')).toBe(
            'https://partner.test/buy?ref=1&utm_content=top_bar'
        );
        expect(wpsrUpgradeUrl('', 'https://partner.test/buy')).toBe('https://partner.test/buy');
        expect(wpsrUpgradeUrl('top_bar', '')).toBe('');
        expect(wpsrUpgradeUrl('top_bar', 'http://[')).toBe('http://[');
    });
});

describe('review bulk-selection policy', () => {
    it('allows exact local review platforms and filters without mutation', () => {
        expect.assertions(8);
        const wpsrRows = [
            { id: 1, platform_name: 'custom' },
            { id: 2, platform_name: 'google' },
            { id: 3, platform_name: 'fluent-cart' },
            { id: 4, platform_name: 'native_form' },
        ];
        const wpsrBefore = structuredClone(wpsrRows);
        const wpsrFiltered = wpsrFilterBulkDeleteReviews(wpsrRows);

        expect(wpsrBulkDeleteReviewPlatforms).toEqual([
            'custom', 'fluent-cart', 'fluent_forms', 'woocommerce', 'native_form',
        ]);
        expect(wpsrCanBulkDeleteReview(wpsrRows[0])).toBe(true);
        expect(wpsrCanBulkDeleteReview(wpsrRows[1])).toBe(false);
        expect(wpsrCanBulkDeleteReview(null)).toBe(false);
        expect(wpsrFiltered.map((wpsrRow) => wpsrRow.id)).toEqual([1, 3, 4]);
        expect(wpsrFiltered[0]).toBe(wpsrRows[0]);
        expect(wpsrRows).toEqual(wpsrBefore);
        expect(wpsrFilterBulkDeleteReviews('not-an-array')).toEqual([]);
    });
});

describe('visitor toolbar contracts', () => {
    it.each([
        [undefined, true],
        [[], true],
        [['google', 'custom'], true],
        [['booking.com'], false],
        [['google', 'booking.com'], false],
    ])('classifies supported platform set %#', (wpsrPlatforms, wpsrExpected) => {
        expect.assertions(1);
        expect(wpsrToolbarSupportsPlatforms(wpsrPlatforms)).toBe(wpsrExpected);
    });

    it.each([
        [{ templateType: 'grid', template: 'grid1', platform: ['google'] }, true],
        [{ templateType: 'masonry', template: 'grid10' }, true],
        [{ templateType: 'badge', template: 'grid1', badge_settings: { display_mode: 'popup' } }, true],
        [{ templateType: 'badge', template: 'grid1', badge_settings: { display_mode: 'inline' } }, false],
        [{ templateType: 'notification', template: 'grid1', notification_settings: { display_mode: 'popup' } }, true],
        [{ templateType: 'notification', template: 'grid1' }, false],
        [{ templateType: 'list', template: 'grid1' }, false],
        [{ templateType: 'grid', template: 'unknown' }, false],
        [{ templateType: 'grid', template: 'grid1', platform: ['booking.com'] }, false],
        [null, false],
    ])('applies structural toolbar gate %#', (wpsrMeta, wpsrExpected) => {
        expect.assertions(1);
        expect(wpsrToolbarSupported(wpsrMeta)).toBe(wpsrExpected);
    });

    it('requires Pro and exact enabled string without mutating metadata', () => {
        expect.assertions(5);
        const wpsrMeta = {
            templateType: 'grid',
            template: 'grid1',
            platform: ['google'],
            enable_visitor_sorting: 'true',
        };
        const wpsrBefore = structuredClone(wpsrMeta);

        expect(wpsrToolbarActive(wpsrMeta, true)).toBe(true);
        expect(wpsrToolbarActive({ ...wpsrMeta, enable_visitor_sorting: true }, true)).toBe(false);
        expect(wpsrToolbarActive(wpsrMeta, false)).toBe(false);
        expect(wpsrToolbarActive({ ...wpsrMeta, template: 'unknown' }, true)).toBe(false);
        expect(wpsrMeta).toEqual(wpsrBefore);
    });

    it.each([
        [{}, true],
        [{ isReviewerText: 'true' }, true],
        [{ isReviewerText: 'false', display_review_title: 'true' }, true],
        [{ isReviewerText: 'false', display_review_title: 'false', reviewer_name: true }, true],
        [{ isReviewerText: 'false', display_review_title: 'false', reviewer_name: '1', reviewer_name_format: 'first-name' }, false],
        [{ isReviewerText: 'false', display_review_title: 'false', reviewer_name: false }, false],
    ])('classifies searchable columns %#', (wpsrMeta, wpsrExpected) => {
        expect.assertions(1);
        expect(wpsrToolbarSearchEnabled(wpsrMeta)).toBe(wpsrExpected);
    });

    it('builds translated sort options and prepends featured only for random order', () => {
        expect.assertions(5);
        const wpsrMeta = { order: 'random' };
        const wpsrBefore = structuredClone(wpsrMeta);
        const wpsrTranslate = (wpsrValue) => `T:${wpsrValue}`;
        const wpsrOptions = wpsrToolbarSortOptions(wpsrMeta, wpsrTranslate);

        expect(wpsrOptions.map((wpsrOption) => wpsrOption.value)).toEqual([
            'random', 'newest', 'oldest', 'rating_desc', 'rating_asc',
        ]);
        expect(wpsrOptions[0]).toEqual({ value: 'random', label: 'T:Featured' });
        expect(wpsrOptions[1]).toEqual({ value: 'newest', label: 'T:Newest first' });
        expect(wpsrToolbarSortOptions({}, wpsrTranslate)).toHaveLength(4);
        expect(wpsrMeta).toEqual(wpsrBefore);
    });

    it.each([
        [{}, [-1, 5, 4, 3, 2, 1]],
        [{ starFilterVal: '3' }, [-1, 5, 4, 3]],
        [{ starFilterVal: '5' }, []],
        [{ starFilterVal: 'bad' }, [-1, 5, 4, 3, 2, 1]],
    ])('builds rating options at owner floor %#', (wpsrMeta, wpsrValues) => {
        expect.assertions(2);
        const wpsrOptions = wpsrToolbarRatingOptions(wpsrMeta, (wpsrValue) => wpsrValue);
        expect(wpsrOptions.map((wpsrOption) => wpsrOption.value)).toEqual(wpsrValues);
        if (wpsrOptions.length) {
            expect(wpsrOptions[wpsrOptions.length - 1].label).toMatch(/^\d Stars?$|All ratings$/);
        } else {
            expect(wpsrOptions).toEqual([]);
        }
    });

    it.each([
        [{}, 'newest'],
        [{ order: 'DESC' }, 'newest'],
        [{ order: 'asc' }, 'oldest'],
        [{ order: 'rating_desc' }, 'rating_desc'],
        [null, 'newest'],
    ])('normalizes current order %#', (wpsrMeta, wpsrExpected) => {
        expect.assertions(1);
        expect(wpsrToolbarCurrentOrder(wpsrMeta)).toBe(wpsrExpected);
    });
});
