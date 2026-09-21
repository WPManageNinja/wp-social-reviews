function unsafeAjax(response, slot) {
    // ruleid: wpsr.js.ajax-response-to-dom-html
    slot.html(response.content);
}

function safeAjax(response, slot) {
    // ok: wpsr.js.ajax-response-to-dom-html
    slot.text(response.content);
}
