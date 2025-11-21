const request = function(method, route, data = {}) {
    const url = `${window.WPSocialReviewsAdmin.rest.url}/${route}`;

    const headers = {'X-WP-Nonce': window.WPSocialReviewsAdmin.rest.nonce};

    if (['PUT', 'PATCH', 'DELETE'].indexOf(method.toUpperCase()) !== -1) {
        headers['X-HTTP-Method-Override'] = method;
        method = 'POST';
    }

    if (method !== 'GET') {
        data = JSON.stringify(data);
    }

    return window.jQuery.ajax({
        url: url,
        type: method,
        data: data,
        headers: headers,
        contentType: 'application/json'
    });
}

export default {
    get(route, data = {}) {
        data.query_timestamp = Date.now();
        return request('GET', route, data);
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
        const headers = {'X-WP-Nonce': window.WPSocialReviewsAdmin.rest.nonce};
        
        return window.jQuery.ajax({
            url: url,
            type: 'POST',
            data: formData,
            headers: headers,
            processData: false,
            contentType: false
        });
    }
};

jQuery(document).ajaxSuccess((event, xhr, settings) => {
    const nonce = xhr.getResponseHeader('X-WP-Nonce');
    if (nonce) {
        window.WPSocialReviewsAdmin.rest.nonce = nonce;
    }
});
