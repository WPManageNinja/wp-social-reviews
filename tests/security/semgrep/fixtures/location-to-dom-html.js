function unsafeLocation(slot) {
    const value = new URLSearchParams(window.location.search).get('message');
    // ruleid: wpsr.js.location-to-dom-html
    slot.innerHTML = value;
}

function safeLocation(slot) {
    const value = new URLSearchParams(window.location.search).get('message');
    // ok: wpsr.js.location-to-dom-html
    slot.textContent = value;
}
