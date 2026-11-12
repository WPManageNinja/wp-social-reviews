/**
 * Shared tests for a testimonial video URL. Mirrored by Pro's TestimonialVideo service,
 * which renders the front end.
 */

const WPSR_VIDEO_FILE_PATTERN = /\.(mp4|webm|ogv|ogg|mov|m4v)(\?|#|$)/i;

/** Mirrors wpsr_trim_url(): .trim() covers all of it except U+200B-U+200D. */
const WPSR_TRIM_SET = '[\\s\\u00A0\\uFEFF\\u2000-\\u200D\\u2028\\u2029\\u3000]';
const WPSR_TRIM_CHARS = new RegExp('^' + WPSR_TRIM_SET + '+|' + WPSR_TRIM_SET + '+$', 'g');

/** Mirrors TestimonialVideo's host lists and id patterns, shape for shape. */
const WPSR_YOUTUBE_HOSTS = ['youtube.com', 'youtube-nocookie.com', 'youtu.be'];
const WPSR_VIMEO_HOSTS = ['vimeo.com'];
const WPSR_YOUTUBE_ID = /^[A-Za-z0-9_-]{11}$/;
const WPSR_YOUTUBE_PATH = /^\/(?:embed|shorts|live|v)\/([^/?#]+)/;
const WPSR_YOUTU_BE_PATH = /^\/([^/?#]+)/;
const WPSR_VIMEO_PATHS = [
    /^\/(\d+)(?:\/([A-Za-z0-9]{1,32}))?(?:\/|$)/,
    /^\/video\/(\d+)(?:\/([A-Za-z0-9]{1,32}))?/,
    /^\/channels\/[^/]+\/(\d+)/
];
const WPSR_VIMEO_HASH = /^[A-Za-z0-9]{1,32}$/;

/**
 * Trim the way wpsr_trim_url() does, so what the DOM gets is what PHP will store.
 *
 * @param {string} url
 * @returns {string}
 */
export function wpsrTrimUrl(url) {
    return (url || '').replace(WPSR_TRIM_CHARS, '');
}

/**
 * Parse a complete http(s) URL, authority included.
 *
 * "https:///path" parses in the browser but has no host for PHP, which 422s it.
 *
 * @param {string} url
 * @returns {URL|null} null when the value is not a complete http(s) URL
 */
function parseHttpUrl(url) {
    const trimmed = wpsrTrimUrl(url);

    if (!/^https?:\/\/[^/?#]/i.test(trimmed) || /[^\x21-\x7E]/.test(trimmed.replace(/^https?:\/\//i, '').split(/[/?#]/)[0])) {
        return null;
    }

    try {
        const parsed = new URL(trimmed);

        return /^https?:$/.test(parsed.protocol) && /[\p{L}\p{N}]/u.test(parsed.hostname) ? parsed : null;
    } catch (e) {
        return null;
    }
}

/**
 * @param {string} url
 * @returns {boolean} true for a full http(s) URL
 */
export function wpsrIsHttpUrl(url) {
    return parseHttpUrl(url) !== null;
}

/**
 * Match a host against a list on the suffix, as TestimonialVideo::hostIn() does.
 *
 * @param {string} host
 * @param {Array<string>} hosts
 * @returns {boolean}
 */
function hostIn(host, hosts) {
    return hosts.some(known => host === known || host.endsWith('.' + known));
}

/**
 * Build a YouTube or Vimeo player URL, so the editor can show the real video.
 *
 * @param {string} url
 * @returns {string} '' when no embed URL can be built for this URL
 */
export function wpsrVideoEmbedUrl(url) {
    const parsed = parseHttpUrl(url);

    if (!parsed) {
        return '';
    }

    const host = parsed.hostname.replace(/\.$/, '').replace(/^www\./, '');

    if (hostIn(host, WPSR_YOUTUBE_HOSTS)) {
        const id = wpsrYoutubeId(parsed, host);

        return id === '' ? '' : 'https://www.youtube-nocookie.com/embed/' + id;
    }

    if (hostIn(host, WPSR_VIMEO_HOSTS)) {
        return wpsrVimeoEmbedUrl(parsed);
    }

    return '';
}

/**
 * @param {URL} parsed
 * @param {string} host bare host, to tell youtu.be from youtube.com
 * @returns {string} '' when no id is present
 */
function wpsrYoutubeId(parsed, host) {
    // watch?v= wins, and may sit behind other parameters.
    let id = parsed.searchParams.get('v') || '';

    if (id === '') {
        const match = parsed.pathname.match(host === 'youtu.be' ? WPSR_YOUTU_BE_PATH : WPSR_YOUTUBE_PATH);
        id = match ? match[1] : '';
    }

    return WPSR_YOUTUBE_ID.test(id) ? id : '';
}

/**
 * @param {URL} parsed
 * @returns {string} '' when the path carries no video id
 */
function wpsrVimeoEmbedUrl(parsed) {
    let id = '';
    let hash = '';

    for (const pattern of WPSR_VIMEO_PATHS) {
        const match = parsed.pathname.match(pattern);

        if (match) {
            id = match[1];
            hash = match[2] || '';
            break;
        }
    }

    if (id === '') {
        return '';
    }

    // player.vimeo.com/video/{id}?h={hash} already carries it in the query.
    if (hash === '') {
        const queryHash = parsed.searchParams.get('h') || '';
        hash = WPSR_VIMEO_HASH.test(queryHash) ? queryHash : '';
    }

    return 'https://player.vimeo.com/video/' + id + (hash === '' ? '' : '?h=' + hash);
}

/**
 * A file a browser can play directly, as opposed to a YouTube or Vimeo page.
 *
 * @param {string} url
 * @returns {boolean}
 */
export function wpsrIsVideoFileUrl(url) {
    return wpsrIsHttpUrl(url) && WPSR_VIDEO_FILE_PATTERN.test(wpsrTrimUrl(url));
}
