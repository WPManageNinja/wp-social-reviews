// Params that change how WordPress REST handles a request. A GET with ?_method=POST runs as a POST.
const OVERRIDE_PARAMS = ['_method', '_jsonp', '_envelope'];

/**
 * The name PHP gives a query key. PHP stops at a null byte and drops leading spaces.
 * A "[" with a matching "]" after it starts an array, so "a[x]" is "a". A "[" with no "]"
 * is not an array: PHP turns every " ", "." and "[" into "_", so "rest[route" is "rest_route".
 */
export function phpParamName(key) {
    const name = key.split('\0')[0].replace(/^ +/, '');
    const open = name.indexOf('[');
    if (open !== -1 && name.indexOf(']', open) !== -1) {
        return name.slice(0, open).replace(/[ .]/g, '_');
    }
    return name.replace(/[ .[]/g, '_');
}

let wpsrRestBase = null;

function getRestBase() {
    const raw = window.WPSocialReviewsAdmin.rest.url;
    if (!wpsrRestBase || wpsrRestBase.raw !== raw) {
        const url = new URL(raw, window.location.href);
        const route = url.searchParams.get('rest_route'); // set on sites with plain permalinks
        wpsrRestBase = {
            raw,
            origin: url.origin,
            pathname: url.pathname,
            plain: route !== null,
            prefix: (route !== null ? route : url.pathname).replace(/\/$/, '') + '/'
        };
    }
    return wpsrRestBase;
}

/**
 * Route params come from the page URL, so a crafted link reaches this code.
 * A safe URL stays inside the plugin's REST base and has no param that changes the method or the route.
 */
export function isSafeRestUrl(fullUrl) {
    // "#" cuts off the rest of the URL, and browsers and servers read "\" differently
    if (/[#\\]/.test(fullUrl)) {
        return false;
    }

    const base = getRestBase();
    const url = new URL(fullUrl, window.location.href);

    let routeParams = 0;
    for (const key of url.searchParams.keys()) {
        // Read the key the way PHP does, so "%20_method", "_method%00" and "rest[route" are caught
        const name = phpParamName(key);
        if (OVERRIDE_PARAMS.includes(name)) {
            return false;
        }
        if (name === 'rest_route') {
            routeParams++;
        }
    }

    const path = (base.plain ? url.searchParams.get('rest_route') : url.pathname) || '';

    return url.origin === base.origin
        && routeParams === (base.plain ? 1 : 0)
        && (!base.plain || url.pathname === base.pathname)
        && path.startsWith(base.prefix)
        && !hasDotSegment(path);
}

// WordPress retries a failed rewrite match on the decoded path, so "..%2F" can act as "../"
function hasDotSegment(path) {
    let decoded;
    try {
        decoded = decodeURIComponent(path);
    } catch (e) {
        return true;
    }
    return [path, decoded].some(value => value.split('/').some(part => part === '.' || part === '..'));
}

// A fixed message: handleError renders HTML, so the route (from the page URL) must not appear in it.
// A jQuery Deferred, because callers chain .always() on the result.
function invalidRoute() {
    return window.jQuery.Deferred()
        .reject({message: 'Invalid REST route.', code: 'wpsr_invalid_rest_route'})
        .promise();
}

const request = function(method, route, data = {}) {
    let url = `${window.WPSocialReviewsAdmin.rest.url}/${route}`;

    const headers = {'X-WP-Nonce': window.WPSocialReviewsAdmin.rest.nonce};

    if (['PUT', 'PATCH', 'DELETE'].indexOf(method.toUpperCase()) !== -1) {
        headers['X-HTTP-Method-Override'] = method;
        method = 'POST';
    }

    // GET data goes into the URL here, not in jQuery, so the check below sees the URL that is sent
    if (method === 'GET') {
        const query = window.jQuery.param(data);
        if (query) {
            url += (url.indexOf('?') !== -1 ? '&' : '?') + query;
        }
        data = undefined;
    } else {
        data = JSON.stringify(data);
    }

    if (!isSafeRestUrl(url)) {
        return invalidRoute();
    }

    return window.jQuery.ajax({
        url: url,
        type: method,
        data: data,
        headers: headers,
        contentType: 'application/json',
        dataType: 'json',
        jsonp: false
    });
}

export default {
    get(route, data = {}) {
        // Clone first: the cache-busting stamp must not land on the caller's object.
        return request('GET', route, {...data, query_timestamp: Date.now()});
    },
    post(route, data = {}) {
        return request('POST', route, data);
    },
    delete(route, data = {}) {
        return request('DELETE', route, data);
    },
    put(route, data = {}) {
        return request('PUT', route, data);
    },
    patch(route, data = {}) {
        return request('PATCH', route, data);
    },
    uploadFile(route, formData) {
        const url = `${window.WPSocialReviewsAdmin.rest.url}/${route}`;
        if (!isSafeRestUrl(url)) {
            return invalidRoute();
        }
        const headers = {'X-WP-Nonce': window.WPSocialReviewsAdmin.rest.nonce};

        return window.jQuery.ajax({
            url: url,
            type: 'POST',
            data: formData,
            headers: headers,
            processData: false,
            contentType: false,
            dataType: 'json',
            jsonp: false
        });
    }
};

jQuery(document).ajaxSuccess((event, xhr, settings) => {
    const nonce = xhr.getResponseHeader('X-WP-Nonce');
    if (nonce) {
        window.WPSocialReviewsAdmin.rest.nonce = nonce;
    }
});
