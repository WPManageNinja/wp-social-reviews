#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const manifest = require('./screens.manifest');
const { collectRouterScreens } = require('./router-inventory');
const { matchesKnownFailure } = require('./known-failure-contract');
const { createRequestTracker } = require('./request-tracker');

const contextFile = process.env.WPSR_BROWSER_CONTEXT_FILE;
if (!contextFile || !fs.existsSync(contextFile)) {
    throw new Error('WPSR_BROWSER_CONTEXT_FILE missing. Use tests/bin/run-browser.sh.');
}
const auth = JSON.parse(fs.readFileSync(contextFile, 'utf8'));
const buildAssets = path.resolve(__dirname, '.build/assets');
const artifacts = path.resolve(__dirname, 'artifacts');
const routeTitles = new Map(collectRouterScreens().map(route => [route.name, route.title]));
const appOrigin = new URL(auth.base_url).origin;
const coreAssetPrefix = '/wp-content/plugins/wp-social-ninja/assets/';
const ignoredForeign = new Set();
const signals = [];
let currentScreen = 'bootstrap';

function record(kind, detail) {
    signals.push({ screen: currentScreen, kind, detail: String(detail) });
}

function isExpectedPassiveForeign(url) {
    const parsed = new URL(url);
    if (parsed.hostname === 'secure.gravatar.com' && parsed.pathname.startsWith('/avatar/')) {
        ignoredForeign.add('secure.gravatar.com/avatar (WordPress toolbar avatar; browser-blocked)');
        return true;
    }
    if (parsed.hostname === 'www.youtube.com' && parsed.pathname.startsWith('/embed/')) {
        ignoredForeign.add('www.youtube.com/embed (static promotion iframe; browser-blocked)');
        return true;
    }
    if (parsed.hostname === 'fonts.googleapis.com' && parsed.pathname === '/css2' && parsed.searchParams.get('family')?.startsWith('Inter:')) {
        ignoredForeign.add('fonts.googleapis.com/css2 Inter (optional admin font; browser-blocked)');
        return true;
    }
    if (parsed.hostname === 's.w.org' && /^\/images\/core\/emoji\/[0-9.]+\/svg\/[a-f0-9-]+\.svg$/.test(parsed.pathname)) {
        ignoredForeign.add('s.w.org/images/core/emoji (WordPress core emoji; browser-blocked)');
        return true;
    }
    return false;
}

function isRelevant(url) {
    const parsed = new URL(url);
    return parsed.origin === appOrigin && (
        parsed.pathname.startsWith(coreAssetPrefix) ||
        parsed.pathname.includes('/wp-json/wpsocialreviews/')
    );
}

const pendingRelevant = createRequestTracker(isRelevant);

function resolveScreen(item) {
    let routePath = item.path;
    if (item.token) {
        const value = auth.tokens[item.token];
        if (!value) throw new Error(`${item.name}: fixture token '${item.token}' is empty`);
        routePath = routePath.replace(/:[A-Za-z_][A-Za-z0-9_]*/, encodeURIComponent(String(value)));
    }
    return {
        ...item,
        routePath,
        expectedText: item.fixtureText ? auth.titles[item.fixtureText] : item.text,
    };
}

async function readAssetWithRetry(file) {
    let lastError;
    for (let attempt = 1; attempt <= 3; attempt++) {
        try {
            return await fs.promises.readFile(file);
        } catch (error) {
            lastError = error;
            if (attempt < 3) await new Promise(resolve => setTimeout(resolve, 50 * attempt));
        }
    }
    throw lastError;
}

async function waitRelevantIdle(timeoutMs = 10000) {
    const started = Date.now();
    let idleSince = 0;
    while (Date.now() - started < timeoutMs) {
        if (pendingRelevant.size === 0) {
            if (!idleSince) idleSince = Date.now();
            if (Date.now() - idleSince >= 250) return;
        } else {
            idleSince = 0;
        }
        await new Promise(resolve => setTimeout(resolve, 50));
    }
    throw new Error(`relevant requests did not settle: ${pendingRelevant.urls().join(', ')}`);
}

async function setHashWithRetry(page, routePath) {
    const wanted = `#${routePath}`;
    let lastError;
    for (let attempt = 1; attempt <= 3; attempt++) {
        try {
            await page.evaluate(hash => {
                if (window.location.hash !== hash) window.location.hash = hash;
            }, wanted);
            await page.waitForFunction(hash => window.location.hash === hash, wanted, { timeout: 5000 });
            return;
        } catch (error) {
            lastError = error;
            if (!/Execution context was destroyed|navigation|Target page/i.test(String(error))) throw error;
            await page.waitForTimeout(100 * attempt).catch(() => {});
        }
    }
    throw lastError;
}

async function assertScreen(page, item) {
    await setHashWithRetry(page, item.routePath);
    const expectedTitle = routeTitles.get(item.name);
    await page.waitForFunction(
        title => document.title === `WP Social Ninja :: ${title}`,
        expectedTitle,
        { timeout: 7000 }
    );

    let mounted = page.locator(item.selector).first();
    if (item.expectedText) mounted = mounted.filter({ hasText: item.expectedText });
    try {
        await mounted.waitFor({ state: 'visible', timeout: 12000 });
    } catch (error) {
        throw new Error(`component marker not visible: ${item.selector}`);
    }
    const box = await mounted.boundingBox();
    if (!box || box.width < 2 || box.height < 2) {
        throw new Error(`component marker is blank: ${item.selector}`);
    }
    const text = (await mounted.innerText()).replace(/\s+/g, ' ').trim();
    if (!text && !item.expectedText) {
        throw new Error(`component marker has no rendered content: ${item.selector}`);
    }

    await waitRelevantIdle();
    await page.waitForTimeout(100);

    if (/\/wp-login\.php(?:[?#]|$)/.test(page.url())) {
        throw new Error(`unexpected login redirect: ${page.url()}`);
    }
    const fatalText = await page.locator('body').innerText();
    if (/(?:Fatal error|Parse error|There has been a critical error|rest_forbidden|Internal Server Error)/i.test(fatalText)) {
        throw new Error('PHP/REST error text visible on screen');
    }
    const visibleError = page.locator('.el-message--error:visible, .notice-error:visible, .wpsr-error-message:visible');
    if (await visibleError.count()) {
        throw new Error(`error UI visible: ${(await visibleError.first().innerText()).slice(0, 240)}`);
    }
}

(async () => {
    fs.rmSync(artifacts, { recursive: true, force: true });
    fs.mkdirSync(artifacts, { recursive: true });
    const started = Date.now();
    const browser = await chromium.launch({ headless: process.env.WPSR_BROWSER_HEADED !== '1' });
    const browserContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });

    await browserContext.route('**/*', async route => {
        const requestUrl = route.request().url();
        let parsed;
        try {
            parsed = new URL(requestUrl);
        } catch (error) {
            return route.continue();
        }

        if (parsed.origin === appOrigin && parsed.pathname.startsWith(coreAssetPrefix)) {
            const relative = decodeURIComponent(parsed.pathname.slice(coreAssetPrefix.length));
            const file = path.resolve(buildAssets, relative);
            if (!file.startsWith(buildAssets + path.sep)) {
                record('asset-path', requestUrl);
                return route.abort('blockedbyclient');
            }
            try {
                const body = await readAssetWithRetry(file);
                return route.fulfill({ status: 200, body });
            } catch (error) {
                record('missing-core-asset', `${requestUrl}: ${error.message}`);
                return route.continue();
            }
        }

        if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
            if (parsed.origin !== appOrigin) {
                if (!isExpectedPassiveForeign(requestUrl)) record('unexpected-browser-outbound', requestUrl);
                return route.abort('blockedbyclient');
            }
        }
        return route.continue();
    });

    await browserContext.addCookies(auth.cookies.map(cookie => ({
        name: cookie.name,
        value: cookie.value,
        domain: auth.host,
        path: '/',
        httpOnly: true,
        secure: !!cookie.secure,
        sameSite: 'Lax',
    })));

    const page = await browserContext.newPage();
    page.on('console', message => {
        if (message.type() !== 'error') return;
        const locationUrl = message.location() && message.location().url;
        if (locationUrl) {
            try {
                if (new URL(locationUrl).origin !== appOrigin && isExpectedPassiveForeign(locationUrl)) return;
            } catch (error) {
                // Keep malformed locations visible as owned console failures.
            }
        }
        record('console-error', message.text());
    });
    page.on('pageerror', error => record('page-error', error.stack || error.message));
    page.on('request', request => pendingRelevant.start(request));
    page.on('requestfinished', request => pendingRelevant.finish(request));
    page.on('requestfailed', request => {
        pendingRelevant.finish(request);
        const url = request.url();
        if (isExpectedPassiveForeign(url)) return;
        record('request-failed', `${url}: ${request.failure() ? request.failure().errorText : 'unknown'}`);
    });
    page.on('response', response => {
        const url = response.url();
        const parsed = new URL(url);
        if (response.status() >= 400 && (
            parsed.pathname.startsWith(coreAssetPrefix) ||
            parsed.pathname.includes('/wp-json/wpsocialreviews/') ||
            response.status() >= 500
        )) {
            record('http-status', `${response.status()} ${url}`);
        }
    });

    let passed = 0;
    let failed = 0;
    let skipped = 0;
    try {
        currentScreen = 'bootstrap';
        await page.goto(`${auth.admin_url}#/`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        if (/\/wp-login\.php(?:[?#]|$)/.test(page.url())) throw new Error(`unexpected login redirect: ${page.url()}`);
        await page.locator('#wpsocialreviewsapp').waitFor({ state: 'attached', timeout: 15000 });
        await waitRelevantIdle();

        for (const rawItem of manifest) {
            const item = resolveScreen(rawItem);
            currentScreen = item.name;
            const signalStart = signals.length;
            try {
                await assertScreen(page, item);
                const ownSignals = signals.slice(signalStart);
                if (ownSignals.length) {
                    throw new Error(ownSignals.map(signal => `${signal.kind}: ${signal.detail}`).join(' | '));
                }
                if (item.knownFailure) {
                    failed++;
                    console.error(`FAIL  ${item.name} ${item.routePath}: unexpected success for ${item.knownFailure.id}; ${item.knownFailure.revisit}`);
                    continue;
                }
                passed++;
                console.log(`PASS  ${item.name} ${item.routePath} -> ${item.selector}${item.expectedText ? ` [${item.expectedText}]` : ''}`);
            } catch (error) {
                const safeName = item.name.replace(/[^a-z0-9_-]/gi, '_');
                await page.screenshot({ path: path.join(artifacts, `${safeName}.png`), fullPage: true }).catch(() => {});
                const ownSignals = signals.slice(signalStart);
                const signalText = ownSignals.length ? ` | ${ownSignals.map(signal => `${signal.kind}: ${signal.detail}`).join(' | ')}` : '';
                if (matchesKnownFailure(item, error, ownSignals)) {
                    skipped++;
                    console.log(`KNOWN-FAILURE  ${item.name} ${item.routePath} — ${item.knownFailure.id}: ${item.knownFailure.reason} Remediation: ${item.knownFailure.remediation} Observed exact expected symptom: ${error.message}`);
                    continue;
                }
                failed++;
                const mismatch = item.knownFailure
                    ? `; expected ${item.knownFailure.id} symptom /${item.knownFailure.expectedError}/ with no runtime signals`
                    : '';
                console.error(`FAIL  ${item.name} ${item.routePath}: ${error.message}${signalText}${mismatch}`);
            }
        }
    } finally {
        await browser.close();
    }

    const bootstrapSignals = signals.filter(signal => signal.screen === 'bootstrap');
    if (bootstrapSignals.length) {
        failed++;
        console.error('FAIL  bootstrap: ' + bootstrapSignals.map(signal => `${signal.kind}: ${signal.detail}`).join(' | '));
    }
    const runtime = ((Date.now() - started) / 1000).toFixed(2);
    console.log(`BROWSER: ${passed}/${manifest.length} passed, ${failed} failed, ${skipped} skipped; runtime ${runtime}s`);
    console.log(`Noise filters: ${ignoredForeign.size ? [...ignoredForeign].join('; ') : 'none exercised'}`);
    if (passed + failed + skipped !== manifest.length || passed === 0 || failed) process.exit(1);
})().catch(error => {
    console.error(error.stack || error.message);
    process.exit(1);
});
