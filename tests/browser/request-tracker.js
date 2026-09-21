'use strict';

function createRequestTracker(isRelevant) {
    const pending = new Set();

    return {
        start(request) {
            if (isRelevant(request.url())) pending.add(request);
        },
        finish(request) {
            pending.delete(request);
        },
        get size() {
            return pending.size;
        },
        urls() {
            return [...pending].map(request => request.url());
        },
    };
}

module.exports = { createRequestTracker };
