/**
 * Upgrade URLs are built in PHP (App\Services\UpgradeUrl) so the wpsocialreviews/link
 * affiliate filter is never bypassed. The SPA only sets utm_content to the clicked
 * placement, a greppable string such as 'top_bar' or 'feature_lock_*'.
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
