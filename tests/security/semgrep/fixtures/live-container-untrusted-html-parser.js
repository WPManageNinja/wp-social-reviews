function sanitizeHtml(html) {
    // ruleid: wpsr.js.live-container-untrusted-html-parser
    const temp = document.createElement('div');
    temp.innerHTML = html;
    return temp.textContent;
}

function sanitizeHtmlSafely(html) {
    // ok: wpsr.js.live-container-untrusted-html-parser
    const temp = document.createElement('template');
    temp.innerHTML = html;
    return temp.content.textContent;
}
