/**
 * Upgrade URL helper for the admin SPA.
 *
 * URLs are always built in PHP (see App\Services\UpgradeUrl) so the
 * wpsocialreviews/link affiliate filter — which is allowed to replace the
 * destination outright — is never bypassed. The SPA only swaps in the placement
 * (utm_content) for the spot the user actually clicked, leaving the rest of the
 * tracking vocabulary exactly as PHP produced it.
 *
 * Placements are passed as plain strings — a shared one such as 'top_bar', or a
 * 'feature_lock_*' naming the locked feature — so the value stays greppable from
 * the analytics side. The full vocabulary lives in App\Services\UpgradeUrl.
 *
 * Shared components (UpgradeToProButton, UpgradeToProBanner) render on many
 * screens, so they take the placement as a prop: the caller knows the spot, the
 * component does not.
 */

/**
 * Re-point a PHP-built upgrade URL at a specific placement.
 *
 * @param {string} content Placement, e.g. 'top_bar' or 'feature_lock_notifications'.
 * @param {string} [baseUrl] Defaults to the localized upgrade URL.
 * @returns {string}
 */
export function wpsrUpgradeUrl(content, baseUrl = '') {
    const appVars = window.WPSocialReviewsAdmin || {};

    // upgrade_btn_config carries any live promo (utm_id, discount) and is present
    // on free installs; upgrade_url_base is always localized and is the fallback.
    const base = baseUrl || appVars.upgrade_btn_config?.pro_purchase_url || appVars.upgrade_url_base || '';

    if (!base || !content) {
        return base;
    }

    try {
        const url = new URL(base, window.location.origin);
        url.searchParams.set('utm_content', content);
        return url.toString();
    } catch (e) {
        // A partner filter may have swapped in something we cannot parse; sending
        // the untracked URL is better than sending the user nowhere.
        return base;
    }
}
