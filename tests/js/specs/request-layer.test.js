import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

let wpsrRest;
let wpsrAjaxSuccessHandler;
const wpsrAjax = vi.fn();

beforeAll(async () => {
    const wpsrJQuery = () => ({
        ajaxSuccess(wpsrHandler) {
            wpsrAjaxSuccessHandler = wpsrHandler;
        },
    });
    wpsrJQuery.ajax = wpsrAjax;

    globalThis.jQuery = wpsrJQuery;
    window.jQuery = wpsrJQuery;
    window.WPSocialReviewsAdmin = {
        rest: {
            url: 'https://example.test/wp-json/wpsocialreviews/v2',
            nonce: 'nonce-initial',
        },
    };

    wpsrRest = (await import('../../../resources/admin/Rest.js')).default;
});

beforeEach(() => {
    wpsrAjax.mockReset();
    window.WPSocialReviewsAdmin.rest.nonce = 'nonce-initial';
});

describe('admin REST transport', () => {
    it('constructs GET request with exact route, query, nonce, and cache timestamp', () => {
        expect.assertions(8);
        const wpsrTransportResult = { status: 200, body: { items: [] } };
        const wpsrQuery = { page: 2, search: 'A&B' };
        vi.spyOn(Date, 'now').mockReturnValue(1700000000123);
        wpsrAjax.mockReturnValue(wpsrTransportResult);

        const wpsrResult = wpsrRest.get('reviews', wpsrQuery);
        const wpsrOptions = wpsrAjax.mock.calls[0][0];

        expect(wpsrResult).toBe(wpsrTransportResult);
        expect(wpsrAjax).toHaveBeenCalledTimes(1);
        expect(wpsrOptions.url).toBe('https://example.test/wp-json/wpsocialreviews/v2/reviews');
        expect(wpsrOptions.type).toBe('GET');
        expect(wpsrOptions.data).toEqual({ page: 2, search: 'A&B', query_timestamp: 1700000000123 });
        expect(wpsrOptions.headers).toEqual({ 'X-WP-Nonce': 'nonce-initial' });
        expect(wpsrOptions.contentType).toBe('application/json');
        expect(wpsrOptions.processData).toBeUndefined();
    });

    it('serializes POST body without mutating caller payload', () => {
        expect.assertions(8);
        const wpsrPayload = { title: 'Exact title', nested: { enabled: true } };
        const wpsrBefore = structuredClone(wpsrPayload);
        const wpsrTransportResult = Promise.resolve({ created: true });
        wpsrAjax.mockReturnValue(wpsrTransportResult);

        const wpsrResult = wpsrRest.post('templates', wpsrPayload);
        const wpsrOptions = wpsrAjax.mock.calls[0][0];

        expect(wpsrResult).toBe(wpsrTransportResult);
        expect(wpsrOptions.url).toBe('https://example.test/wp-json/wpsocialreviews/v2/templates');
        expect(wpsrOptions.type).toBe('POST');
        expect(wpsrOptions.data).toBe('{"title":"Exact title","nested":{"enabled":true}}');
        expect(wpsrOptions.headers).toEqual({ 'X-WP-Nonce': 'nonce-initial' });
        expect(wpsrOptions.contentType).toBe('application/json');
        expect(wpsrPayload).toEqual(wpsrBefore);
        expect(wpsrPayload.nested).not.toBe(wpsrBefore.nested);
    });

    it.each([
        ['delete', 'DELETE'],
        ['put', 'PUT'],
        ['patch', 'PATCH'],
    ])('tunnels %s through POST with exact method override and body', (wpsrHelper, wpsrMethod) => {
        expect.assertions(8);
        const wpsrPayload = { ids: [7, 9] };
        const wpsrBefore = structuredClone(wpsrPayload);
        wpsrAjax.mockReturnValue({ ok: true });

        wpsrRest[wpsrHelper]('reviews/status', wpsrPayload);
        const wpsrOptions = wpsrAjax.mock.calls[0][0];

        expect(wpsrAjax).toHaveBeenCalledTimes(1);
        expect(wpsrOptions.url).toBe('https://example.test/wp-json/wpsocialreviews/v2/reviews/status');
        expect(wpsrOptions.type).toBe('POST');
        expect(wpsrOptions.headers).toEqual({
            'X-WP-Nonce': 'nonce-initial',
            'X-HTTP-Method-Override': wpsrMethod,
        });
        expect(wpsrOptions.data).toBe('{"ids":[7,9]}');
        expect(wpsrOptions.contentType).toBe('application/json');
        expect(wpsrPayload).toEqual(wpsrBefore);
        expect(wpsrOptions.data).not.toBe(wpsrPayload);
    });

    it('uploads FormData without JSON conversion', () => {
        expect.assertions(8);
        const wpsrFormData = new FormData();
        wpsrFormData.append('image', new Blob(['image-bytes']), 'review.jpg');
        const wpsrTransportResult = { upload: 'pending' };
        wpsrAjax.mockReturnValue(wpsrTransportResult);

        const wpsrResult = wpsrRest.uploadFile('media', wpsrFormData);
        const wpsrOptions = wpsrAjax.mock.calls[0][0];

        expect(wpsrResult).toBe(wpsrTransportResult);
        expect(wpsrOptions.url).toBe('https://example.test/wp-json/wpsocialreviews/v2/media');
        expect(wpsrOptions.type).toBe('POST');
        expect(wpsrOptions.data).toBe(wpsrFormData);
        expect(wpsrOptions.headers).toEqual({ 'X-WP-Nonce': 'nonce-initial' });
        expect(wpsrOptions.processData).toBe(false);
        expect(wpsrOptions.contentType).toBe(false);
        expect(wpsrFormData.get('image')).toBeInstanceOf(File);
    });

    it('passes successful transport values through unchanged', async () => {
        expect.assertions(2);
        const wpsrResponse = { data: { id: 42 }, message: 'saved' };
        wpsrAjax.mockResolvedValue(wpsrResponse);

        const wpsrPromise = wpsrRest.post('reviews', { id: 42 });

        expect(await wpsrPromise).toBe(wpsrResponse);
        expect(wpsrAjax).toHaveBeenCalledTimes(1);
    });

    it('passes rejected transport errors through unchanged', async () => {
        expect.assertions(2);
        const wpsrError = { status: 422, responseJSON: { message: 'Invalid review' } };
        wpsrAjax.mockRejectedValue(wpsrError);

        const wpsrPromise = wpsrRest.post('reviews', { rating: 9 });

        await expect(wpsrPromise).rejects.toBe(wpsrError);
        expect(wpsrAjax).toHaveBeenCalledTimes(1);
    });

    it('rotates nonce only when AJAX response supplies one', () => {
        expect.assertions(4);
        const wpsrNonceResponse = {
            getResponseHeader: vi.fn().mockReturnValueOnce('nonce-rotated').mockReturnValueOnce(null),
        };

        wpsrAjaxSuccessHandler({}, wpsrNonceResponse, {});
        expect(window.WPSocialReviewsAdmin.rest.nonce).toBe('nonce-rotated');
        expect(wpsrNonceResponse.getResponseHeader).toHaveBeenLastCalledWith('X-WP-Nonce');

        wpsrAjaxSuccessHandler({}, wpsrNonceResponse, {});
        expect(window.WPSocialReviewsAdmin.rest.nonce).toBe('nonce-rotated');
        expect(wpsrNonceResponse.getResponseHeader).toHaveBeenCalledTimes(2);
    });

    it('stamps GET cache timestamp on a clone and leaves caller query object unchanged', () => {
        expect.assertions(4);
        const wpsrQuery = { page: 2, filters: { status: 'published' } };
        const wpsrBefore = structuredClone(wpsrQuery);
        vi.spyOn(Date, 'now').mockReturnValue(1700000000456);
        wpsrAjax.mockReturnValue({ ok: true });

        wpsrRest.get('reviews', wpsrQuery);
        const wpsrSent = wpsrAjax.mock.calls[0][0].data;

        expect(wpsrQuery).toEqual(wpsrBefore);
        expect(wpsrSent).not.toBe(wpsrQuery);
        expect(wpsrSent).toEqual({ page: 2, filters: { status: 'published' }, query_timestamp: 1700000000456 });
        expect(wpsrSent.filters).toBe(wpsrQuery.filters);
    });

    it('stamps a fresh GET timestamp per call without accumulating keys on the caller object', () => {
        expect.assertions(3);
        const wpsrQuery = { page: 2 };
        vi.spyOn(Date, 'now').mockReturnValueOnce(1700000000111).mockReturnValueOnce(1700000000222);
        wpsrAjax.mockReturnValue({ ok: true });

        wpsrRest.get('reviews', wpsrQuery);
        wpsrRest.get('reviews', wpsrQuery);

        expect(Object.keys(wpsrQuery)).toEqual(['page']);
        expect(wpsrAjax.mock.calls[0][0].data).toEqual({ page: 2, query_timestamp: 1700000000111 });
        expect(wpsrAjax.mock.calls[1][0].data).toEqual({ page: 2, query_timestamp: 1700000000222 });
    });
});
