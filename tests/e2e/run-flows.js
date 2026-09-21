#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('../browser/node_modules/playwright');
const { createRequestTracker } = require('../browser/request-tracker');
const manifest = require('./flows.manifest');

const contextFile = process.env.WPSR_E2E_CONTEXT_FILE;
if (!contextFile || !fs.existsSync(contextFile)) {
    throw new Error('WPSR_E2E_CONTEXT_FILE missing. Use tests/bin/run-e2e.sh.');
}
const auth = JSON.parse(fs.readFileSync(contextFile, 'utf8'));
const filter = process.env.WPSR_E2E_FILTER || '';
const selected = filter ? manifest.filter(flow => flow.id === filter) : manifest;
if (!manifest.length) throw new Error('E2E discovered zero flows; suite wiring is broken.');
if (manifest.length !== 6) throw new Error(`E2E must discover exactly six Phase 17 + Phase 21 flows; found ${manifest.length}.`);
if (manifest.filter(flow => flow.phase === 17).length !== 3 || manifest.filter(flow => flow.phase === 21).length !== 3) {
    throw new Error('E2E phase ownership drift: expected Phase 17=3 and Phase 21=3.');
}
if (!selected.length) throw new Error(`E2E filter selected zero flows: ${filter}. Suite wiring is broken.`);

const buildAssets = path.resolve(__dirname, '..', 'browser', '.build', 'assets');
const sourceAssets = path.resolve(__dirname, '..', '..', 'assets');
const artifacts = path.resolve(__dirname, 'artifacts');
const appOrigin = new URL(auth.base_url).origin;
const coreAssetPrefix = '/wp-content/plugins/wp-social-ninja/assets/';
const signals = [];
const ignoredForeign = new Set();
const fulfilledCoreAssets = new Set();
let currentFlow = 'bootstrap';

function record(kind, detail) {
    signals.push({ flow: currentFlow, kind, detail: String(detail) });
}

function isExpectedPassiveForeign(url) {
    const parsed = new URL(url);
    if (parsed.hostname === 'secure.gravatar.com' && parsed.pathname.startsWith('/avatar/')) {
        ignoredForeign.add('secure.gravatar.com/avatar (WordPress toolbar avatar; browser-blocked)');
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
    const coreBlockingAsset = parsed.pathname.startsWith(coreAssetPrefix) && /\.(?:js|css|woff2?)$/i.test(parsed.pathname);
    const restRoute = parsed.searchParams.get('rest_route') || '';
    return parsed.origin === appOrigin && (
        coreBlockingAsset ||
        parsed.pathname.includes('/wp-json/wpsocialreviews/') ||
        parsed.pathname.includes('/wp-json/wpsr-test-lab/') ||
        restRoute.startsWith('/wpsocialreviews/') ||
        restRoute.startsWith('/wpsr-test-lab/')
    );
}

const pendingRelevant = createRequestTracker(isRelevant);

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

async function waitRelevantIdle(timeoutMs = 12000) {
    const started = Date.now();
    let idleSince = 0;
    while (Date.now() - started < timeoutMs) {
        if (pendingRelevant.size === 0) {
            if (!idleSince) idleSince = Date.now();
            if (Date.now() - idleSince >= 300) return;
        } else {
            idleSince = 0;
        }
        await new Promise(resolve => setTimeout(resolve, 50));
    }
    throw new Error(`relevant requests did not settle: ${pendingRelevant.urls().join(', ')}`);
}

async function setHash(page, route) {
    const wanted = `#${route}`;
    for (let attempt = 1; attempt <= 3; attempt++) {
        try {
            await page.evaluate(hash => { window.location.hash = hash; }, wanted);
            await page.waitForFunction(hash => window.location.hash === hash, wanted, { timeout: 5000 });
            await waitRelevantIdle();
            return;
        } catch (error) {
            if (attempt === 3 || !/Execution context was destroyed|navigation|Target page/i.test(String(error))) throw error;
            await page.waitForTimeout(100 * attempt).catch(() => {});
        }
    }
}

async function waitImages(page, selector) {
    await page.locator(selector).evaluateAll(images => Promise.all(images.map(image => {
        if (image.complete) return true;
        return new Promise(resolve => {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
        });
    })));
}

async function dismissSuccessUi(page) {
    const notices = page.locator('.el-notification:visible').filter({ hasText: 'Success' });
    for (let index = (await notices.count()) - 1; index >= 0; index--) {
        const close = notices.nth(index).locator('.el-notification__closeBtn');
        if (await close.count()) await close.click().catch(() => {});
    }
    const messages = page.locator('.el-message--success:visible');
    if (await messages.count()) await messages.last().waitFor({ state: 'hidden', timeout: 6000 }).catch(() => {});
}

async function assertNoVisibleErrors(page) {
    if (/\/wp-login\.php(?:[?#]|$)/.test(page.url())) throw new Error(`unexpected login redirect: ${page.url()}`);
    const body = await page.locator('body').innerText();
    if (/(?:Fatal error|Parse error|There has been a critical error|rest_forbidden|Internal Server Error)/i.test(body)) {
        throw new Error('PHP/REST error text visible on page');
    }
    const errors = page.locator('.el-message--error:visible, .notice-error:visible, .wpsr-error-message:visible');
    if (await errors.count()) throw new Error(`error UI visible: ${(await errors.first().innerText()).slice(0, 240)}`);
}

async function labRequest(browserContext, method, url, data) {
    const response = await browserContext.request.fetch(url, {
        method,
        headers: { 'X-WPSR-Lab-Token': auth.lab_token },
        data,
    });
    if (!response.ok()) throw new Error(`lab inspector ${method} failed: ${response.status()} ${await response.text()}`);
    return response.json();
}

async function getState(browserContext) {
    return labRequest(browserContext, 'GET', auth.state_url);
}

async function waitState(browserContext, predicate, label, timeoutMs = 10000) {
    const started = Date.now();
    let last;
    while (Date.now() - started < timeoutMs) {
        last = await getState(browserContext);
        if (predicate(last)) return last;
        await new Promise(resolve => setTimeout(resolve, 150));
    }
    throw new Error(`${label}; last state=${JSON.stringify(last)}`);
}

function reviewByKey(state, key) {
    return state.reviews[`${auth.marker}-${key}`];
}

function postById(state, id) {
    return state.posts[String(id)];
}

function categoryByName(state, name) {
    return Object.values(state.categories || {}).find(category => category.name === name);
}

async function cleanupOwned(browserContext, owned) {
    const ids = [...new Set(owned.postIds.filter(id => Number.isInteger(id) && id > 0))];
    const categoryIds = [...new Set(owned.categoryIds.filter(id => Number.isInteger(id) && id > 0))];
    const optionKeys = [...new Set(owned.optionKeys.filter(key => typeof key === 'string' && key))];
    if (!ids.length && !categoryIds.length && !optionKeys.length) return;
    await labRequest(browserContext, 'DELETE', auth.cleanup_url, {
        ids,
        category_ids: categoryIds,
        option_keys: optionKeys,
    });
}

async function gotoAdmin(page, route) {
    await page.goto(`${auth.admin_url}#${route}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.locator('#wpsocialreviewsapp').waitFor({ state: 'attached', timeout: 15000 });
    await waitRelevantIdle();
    await assertNoVisibleErrors(page);
    await dismissSuccessUi(page);
}

async function rowWithText(page, title) {
    const row = page.locator('.wpsr-templates-table .el-table__row').filter({ hasText: title }).first();
    await row.waitFor({ state: 'visible', timeout: 12000 });
    return row;
}

async function clickVisibleMenuItem(page, text) {
    const item = page.locator('.el-dropdown-menu:visible .el-dropdown-menu__item').filter({ hasText: text }).first();
    await item.waitFor({ state: 'visible', timeout: 5000 });
    await item.click();
}

async function selectVisibleOption(page, text) {
    const item = page.locator('.el-select-dropdown:visible .el-select-dropdown__item').filter({ hasText: text }).first();
    await item.waitFor({ state: 'visible', timeout: 5000 });
    await item.click();
}

async function bulkChatStatus(page, row, action) {
    await row.locator('.el-checkbox').first().click();
    await page.locator('.wpsr-controls-bulk-actions .el-select').click();
    await selectVisibleOption(page, action);
    await page.locator('.wpsr-controls-bulk-actions').getByRole('button', { name: 'Apply', exact: true }).click();
    const dialog = page.locator('.wpsr-confirmation-modal:visible').first();
    await dialog.getByRole('button', { name: 'Confirm', exact: true }).click();
    await waitRelevantIdle();
}

async function renameEditorTitle(page, title) {
    await page.locator('.settings_left .wpsr_light_edit').first().click();
    const dialog = page.locator('.el-dialog:visible').filter({ hasText: 'Change Template Title' }).first();
    await dialog.waitFor({ state: 'visible' });
    await dialog.locator('input').fill(title);
    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await waitRelevantIdle();
}

async function flowReviewModeration(page, browserContext) {
    const targetTitle = auth.titles.moderation_target;
    const controlTitle = auth.titles.moderation_control;
    await gotoAdmin(page, '/reviews?status=all&per_page=100');

    let targetRow = await rowWithText(page, targetTitle);
    const applyButton = page.locator('.wpsr-controls-bulk-actions').getByRole('button', { name: 'Apply', exact: true });
    if (!(await applyButton.isDisabled())) throw new Error('review no-selection Apply boundary is not disabled');
    let state = await getState(browserContext);
    if (reviewByKey(state, 'moderation_target')?.review_approved !== 0 || reviewByKey(state, 'moderation_control')?.review_approved !== 1) {
        throw new Error('review no-op boundary changed persisted target/control state');
    }

    await targetRow.locator('.wpsr-actions').click();
    await clickVisibleMenuItem(page, 'Approve');
    state = await waitState(browserContext, value => reviewByKey(value, 'moderation_target')?.review_approved === 1, 'review approval did not persist exact state 1');
    if (reviewByKey(state, 'moderation_control')?.review_approved !== 1) throw new Error('review approval changed adjacent control');

    targetRow = await rowWithText(page, targetTitle);
    await targetRow.locator('.wpsr-actions').click();
    await clickVisibleMenuItem(page, 'Mark as Spam');
    state = await waitState(browserContext, value => reviewByKey(value, 'moderation_target')?.review_approved === 2, 'spam moderation did not persist exact state 2');
    if (reviewByKey(state, 'moderation_control')?.review_approved !== 1) throw new Error('spam moderation changed adjacent control');

    await page.locator('.wpsr-tab-button').filter({ hasText: 'Spam' }).click();
    await waitRelevantIdle();
    targetRow = await rowWithText(page, targetTitle);
    if (!(await targetRow.getByText('Spam', { exact: true }).count())) throw new Error('spam state lacks customer-visible Spam label');
    await page.locator('.wpsr-tab-button').filter({ hasText: 'All' }).click();
    await waitRelevantIdle();
    targetRow = await rowWithText(page, targetTitle);
    await targetRow.locator('.wpsr-actions').click();
    await clickVisibleMenuItem(page, 'Not Spam');
    state = await waitState(browserContext, value => reviewByKey(value, 'moderation_target')?.review_approved === 0, 'not-spam did not persist exact pending state 0');
    if (reviewByKey(state, 'moderation_control')?.review_approved !== 1) throw new Error('not-spam changed adjacent control');

    await page.locator('.wpsr-tab-button').filter({ hasText: 'Pending' }).click();
    await waitRelevantIdle();
    await rowWithText(page, targetTitle);
    await page.locator('.wpsr-tab-button').filter({ hasText: 'Approved' }).click();
    await waitRelevantIdle();
    await rowWithText(page, controlTitle);
    await assertNoVisibleErrors(page);
}

async function flowReviewTemplate(page, browserContext, owned) {
    const title = `${auth.marker} Review Template`;
    await gotoAdmin(page, '/templates');
    await page.getByRole('button', { name: 'Create Template', exact: true }).first().click();
    await clickVisibleMenuItem(page, 'Add Reviews Template');
    await page.waitForFunction(() => /#\/edit-template\/\d+/.test(window.location.hash), null, { timeout: 12000 });
    const match = page.url().match(/#\/edit-template\/(\d+)/);
    const templateId = match ? Number(match[1]) : 0;
    if (!templateId) throw new Error('template UI create produced no route ID');
    owned.postIds.push(templateId);

    await page.locator('.wpsr_editor').waitFor({ state: 'visible', timeout: 15000 });
    const googlePlatform = page.locator('.wpsn-platform-card').filter({ hasText: 'Google Business Profile' }).first();
    await googlePlatform.waitFor({ state: 'visible', timeout: 12000 });
    await googlePlatform.click();
    await waitRelevantIdle();
    await page.getByText(auth.titles.template_review, { exact: true }).first().waitFor({ state: 'visible', timeout: 12000 });
    if (await page.getByText(auth.titles.moderation_target, { exact: true }).count()) {
        throw new Error('template preview rendered pending review');
    }

    await renameEditorTitle(page, title);
    await waitState(browserContext, state => postById(state, templateId)?.post_title === title, 'template title did not persist');

    const filters = page.locator('.el-collapse-item').filter({ has: page.locator('.wpsr-editor-title-text', { hasText: 'Filters' }) }).first();
    await filters.locator('.el-collapse-item__header').click();
    const numberInput = filters.locator('.wpsr-reviews-number input').first();
    const before = Number(await numberInput.inputValue());
    await filters.locator('.wpsr-editor-minus-btn').click();
    const after = Number(await numberInput.inputValue());
    if (after !== before - 1) throw new Error(`template review-count control expected ${before - 1}, got ${after}`);
    await page.locator('.settings_right .wpsr_secondary_btn').getByText('Save', { exact: true }).click();
    await waitRelevantIdle();
    await waitState(browserContext, state => Number(postById(state, templateId)?.config?.totalReviewsNumber?.desktop) === after, 'template config did not persist review count');

    await setHash(page, '/templates');
    let row = await rowWithText(page, title);
    const shortcode = `[wp_social_ninja id="${templateId}" platform="reviews"]`;
    if (!(await row.locator('.wpsr-shortcode').innerText()).includes(shortcode)) throw new Error('template list lacks exact customer shortcode');

    await row.locator('.wpsr-actions').click();
    await clickVisibleMenuItem(page, 'Delete');
    let dialog = page.locator('.wpsr-confirmation-modal:visible').first();
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await waitState(browserContext, state => !!postById(state, templateId), 'template delete-cancel no-op removed persisted post');
    row = await rowWithText(page, title);

    await row.locator('.wpsr-actions').click();
    await clickVisibleMenuItem(page, 'Delete');
    dialog = page.locator('.wpsr-confirmation-modal:visible').first();
    await dialog.getByRole('button', { name: 'Confirm', exact: true }).click();
    await waitRelevantIdle();
    await waitState(browserContext, state => !postById(state, templateId), 'template UI delete left persisted post/postmeta');
    if (await page.locator('.wpsr-templates-table .el-table__row').filter({ hasText: title }).count()) throw new Error('deleted template remains customer-visible');
    await assertNoVisibleErrors(page);
}

async function flowChatWidget(page, browserContext, owned) {
    const title = `${auth.marker} Chat Widget`;
    const label = `${auth.marker} WhatsApp`;
    const credential = '+15555550117';
    await gotoAdmin(page, '/chat-widgets');
    await page.getByRole('button', { name: /^(Create Chat Widget|Add New Widget)$/ }).first().click();
    await page.waitForFunction(() => /#\/edit-chat-widget\/\d+/.test(window.location.hash), null, { timeout: 12000 });
    const match = page.url().match(/#\/edit-chat-widget\/(\d+)/);
    const widgetId = match ? Number(match[1]) : 0;
    if (!widgetId) throw new Error('chat UI create produced no route ID');
    owned.postIds.push(widgetId);

    await page.locator('.wpsr_editor').waitFor({ state: 'visible', timeout: 15000 });
    await page.getByRole('tab', { name: 'Channels', exact: true }).click();
    await page.getByRole('button', { name: 'Add New Channel', exact: true }).click();
    const whatsapp = page.locator('.wpsr-social-channel-card').filter({ hasText: 'WhatsApp' }).first();
    await whatsapp.waitFor({ state: 'visible' });
    await whatsapp.locator('img').evaluate(image => image.complete || new Promise(resolve => {
        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
    }));
    await whatsapp.click();
    const form = page.locator('.wpsr-social-channel-form:visible').first();
    const inputs = form.locator('input');
    await inputs.nth(0).fill(credential);
    await inputs.nth(1).fill(label);
    await form.getByRole('button', { name: 'Save', exact: true }).click();
    await waitState(browserContext, state => {
        const channels = postById(state, widgetId)?.config?.chat_settings?.channels;
        return Array.isArray(channels) && channels.length === 1 && channels[0].name === 'whatsapp' && channels[0].credential === credential && channels[0].label === label;
    }, 'chat channel did not persist exact credential/label');

    await page.getByRole('tab', { name: 'General', exact: true }).click();
    const priority = page.locator('.el-collapse-item').filter({ hasText: 'Priority' }).first();
    await priority.locator('.el-collapse-item__header').click();
    const priorityInput = priority.locator('input').first();
    await priorityInput.fill('99');
    await priorityInput.press('Tab');
    await page.locator('.settings_right .wpsr_secondary_btn').filter({ hasText: 'Save' }).click();
    await waitState(browserContext, state => postById(state, widgetId)?.menu_order === 99, 'chat priority did not persist exact menu_order 99');

    await renameEditorTitle(page, title);
    await waitState(browserContext, state => postById(state, widgetId)?.post_title === title && postById(state, widgetId)?.post_status === 'publish', 'chat title/publish state did not persist');
    await page.locator('img[src*="/images/svg/whatsapp.svg"]').first().evaluate(image => image.complete || new Promise(resolve => {
        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
    }));

    await page.goto(auth.base_url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    const widget = page.locator(`#wpsr-chat-widget-${widgetId}`);
    await widget.waitFor({ state: 'attached', timeout: 12000 });
    const bubble = widget.locator('.wpsr-fm-bubble-btn');
    await bubble.waitFor({ state: 'visible', timeout: 8000 });
    if (await bubble.getAttribute('data-form-ids') !== credential) throw new Error('public chat render lacks exact configured credential');
    await waitImages(page, `#wpsr-chat-widget-${widgetId} img`);
    await assertNoVisibleErrors(page);

    await gotoAdmin(page, '/chat-widgets');
    let row = await rowWithText(page, title);
    await bulkChatStatus(page, row, 'Disable');
    await waitState(browserContext, state => postById(state, widgetId)?.post_status === 'draft', 'chat disable did not persist draft');
    await page.goto(auth.base_url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    if (await page.locator(`#wpsr-chat-widget-${widgetId}`).count()) throw new Error('disabled chat remains publicly rendered');

    await gotoAdmin(page, '/chat-widgets');
    row = await rowWithText(page, title);
    await bulkChatStatus(page, row, 'Enable');
    await waitState(browserContext, state => postById(state, widgetId)?.post_status === 'publish', 'chat re-enable did not persist publish');
    await page.goto(auth.base_url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.locator(`#wpsr-chat-widget-${widgetId} .wpsr-fm-bubble-btn`).waitFor({ state: 'visible', timeout: 12000 });
    await waitImages(page, `#wpsr-chat-widget-${widgetId} img`);

    await gotoAdmin(page, '/chat-widgets');
    row = await rowWithText(page, title);
    await row.locator('.wpsr-actions').click();
    await clickVisibleMenuItem(page, 'Delete');
    const dialog = page.locator('.wpsr-confirmation-modal:visible').first();
    await dialog.getByRole('button', { name: 'Delete Template', exact: true }).click();
    await waitRelevantIdle();
    await waitState(browserContext, state => !postById(state, widgetId), 'chat UI delete left persisted post/postmeta');
    if (await page.locator('.wpsr-templates-table .el-table__row').filter({ hasText: title }).count()) throw new Error('deleted chat remains customer-visible');
    await assertNoVisibleErrors(page);
}

async function flowReviewCategoryLifecycle(page, browserContext, owned) {
    const targetTitle = auth.titles.filter_custom;
    const originalName = `${auth.marker} Customer Success`;
    const renamedName = `${auth.marker} Priority Customers`;

    await gotoAdmin(page, '/reviews?status=all&per_page=100');
    let targetRow = await rowWithText(page, targetTitle);
    await targetRow.locator('.el-checkbox').first().click();
    await page.locator('.wpsr-controls-bulk-actions .el-select').click();
    await selectVisibleOption(page, 'Assign Category');
    await page.locator('.wpsr-controls-bulk-actions').getByRole('button', { name: 'Apply', exact: true }).click();

    const assignDialog = page.locator('.wpsr-bulk-category-modal:visible').first();
    await assignDialog.waitFor({ state: 'visible' });
    await assignDialog.getByText('New category', { exact: true }).click();
    await assignDialog.getByPlaceholder('Enter new category name').fill(originalName);
    await assignDialog.getByRole('button', { name: 'Assign Category', exact: true }).click();

    let state = await waitState(browserContext, value => {
        const category = categoryByName(value, originalName);
        const target = reviewByKey(value, 'filter_custom');
        return category && category.review_ids.length === 1 && category.review_ids[0] === auth.reviews.filter_custom &&
            target?.categories?.length === 1 && target.categories[0].name === originalName;
    }, 'category assign did not persist exact category/pivot ownership');
    const categoryId = categoryByName(state, originalName).id;
    owned.categoryIds.push(categoryId);
    await waitRelevantIdle();
    targetRow = await rowWithText(page, targetTitle);
    await targetRow.getByText(originalName, { exact: true }).waitFor({ state: 'visible' });

    await page.getByRole('button', { name: 'Manage Categories', exact: true }).click();
    let manageDialog = page.locator('.wpsr-manage-categories-modal:visible').first();
    await manageDialog.waitFor({ state: 'visible' });
    let categoryRow = manageDialog.locator('.wpsr-manage-categories-row').filter({ hasText: originalName }).first();
    await categoryRow.waitFor({ state: 'visible' });
    if (!(await categoryRow.getByText('1 review', { exact: true }).count())) throw new Error('category management lacks exact relationship count');

    await categoryRow.locator('button[title="Rename"]').click();
    let editingRow = manageDialog.locator('.wpsr-manage-categories-row').filter({ has: page.locator('input') }).first();
    await editingRow.locator('input').fill(`${auth.marker} Cancelled Rename`);
    await editingRow.getByRole('button', { name: 'Cancel', exact: true }).click();
    state = await getState(browserContext);
    if (!categoryByName(state, originalName) || categoryByName(state, `${auth.marker} Cancelled Rename`)) {
        throw new Error('category rename Cancel changed persisted category');
    }
    await categoryRow.getByText(originalName, { exact: true }).waitFor({ state: 'visible' });

    categoryRow = manageDialog.locator('.wpsr-manage-categories-row').filter({ hasText: originalName }).first();
    await categoryRow.locator('button[title="Rename"]').click();
    editingRow = manageDialog.locator('.wpsr-manage-categories-row').filter({ has: page.locator('input') }).first();
    await editingRow.locator('input').fill(renamedName);
    await editingRow.getByRole('button', { name: 'Save', exact: true }).click();
    await waitState(browserContext, value => {
        const category = value.categories[String(categoryId)];
        const target = reviewByKey(value, 'filter_custom');
        return category?.name === renamedName && category?.review_ids?.length === 1 &&
            target?.categories?.length === 1 && target.categories[0].name === renamedName;
    }, 'category rename did not persist exact name while preserving pivot');
    await waitRelevantIdle();
    manageDialog = page.locator('.wpsr-manage-categories-modal:visible').first();
    categoryRow = manageDialog.locator('.wpsr-manage-categories-row').filter({ hasText: renamedName }).first();
    await categoryRow.waitFor({ state: 'visible' });

    await categoryRow.locator('button[title="Delete"]').click();
    let confirm = page.locator('.el-message-box:visible').first();
    await confirm.waitFor({ state: 'visible' });
    if (!(await confirm.innerText()).includes('It will be removed from 1 review(s).')) throw new Error('category delete warning lacks exact impact count');
    await confirm.getByRole('button', { name: 'Cancel', exact: true }).click();
    state = await getState(browserContext);
    if (state.categories[String(categoryId)]?.name !== renamedName || reviewByKey(state, 'filter_custom')?.categories?.[0]?.id !== categoryId) {
        throw new Error('category delete Cancel changed persisted category/pivot');
    }

    categoryRow = manageDialog.locator('.wpsr-manage-categories-row').filter({ hasText: renamedName }).first();
    await categoryRow.locator('button[title="Delete"]').click();
    confirm = page.locator('.el-message-box:visible').first();
    await confirm.getByRole('button', { name: 'Delete', exact: true }).click();
    await waitState(browserContext, value => !value.categories[String(categoryId)] && reviewByKey(value, 'filter_custom')?.categories?.length === 0,
        'category UI delete left category or pivot');
    await waitRelevantIdle();
    if (await manageDialog.locator('.wpsr-manage-categories-row').filter({ hasText: renamedName }).count()) {
        throw new Error('deleted category remains customer-visible in management list');
    }
    await manageDialog.getByRole('button', { name: 'Done', exact: true }).click();
    targetRow = await rowWithText(page, targetTitle);
    if (await targetRow.getByText(renamedName, { exact: true }).count()) throw new Error('deleted category chip remains customer-visible');
    await assertNoVisibleErrors(page);
}

async function flowReviewFilteringSearch(page, browserContext) {
    const targetTitle = auth.titles.template_review;
    const customTitle = auth.titles.filter_custom;
    await gotoAdmin(page, '/reviews?status=all&per_page=100');
    const before = await getState(browserContext);
    const beforeReviews = JSON.stringify(before.reviews);
    const searchInput = page.locator('.wpsr-input-default input[placeholder="Search..."]').first();

    await searchInput.fill(targetTitle);
    await searchInput.press('Enter');
    await waitRelevantIdle();
    await rowWithText(page, targetTitle);
    if (await page.locator('.wpsr-templates-table .el-table__row').count() !== 1) throw new Error('exact review search did not render exactly one row');
    if (await page.getByText(customTitle, { exact: true }).count()) throw new Error('review search retained nonmatching control row');

    await searchInput.fill(`${auth.marker} no matching customer review`);
    await searchInput.press('Enter');
    await waitRelevantIdle();
    const empty = page.locator('.wpsr-empty-state').filter({ hasText: 'No reviews found yet' }).first();
    await empty.waitFor({ state: 'visible', timeout: 12000 });
    if (await page.locator('.wpsr-templates-table .el-table__row').count()) throw new Error('negative review search rendered rows');

    // Empty-result view intentionally hides controls. Remount route through another
    // customer screen so component-local search state resets without inspector writes.
    await setHash(page, '/templates');
    await setHash(page, '/reviews?status=all&per_page=100');
    await rowWithText(page, customTitle);
    const platformSelect = page.locator('.wpsr-controls-filter .el-select').first();
    await platformSelect.click();
    await selectVisibleOption(page, 'Custom');
    await waitRelevantIdle();
    await rowWithText(page, customTitle);
    if (await page.locator('.wpsr-templates-table .el-table__row').count() !== 1) throw new Error('Custom platform filter did not render exact owned row');
    if (await page.getByText(targetTitle, { exact: true }).count()) throw new Error('Custom platform filter retained Google control row');

    await platformSelect.click();
    await selectVisibleOption(page, 'All');
    await waitRelevantIdle();
    await rowWithText(page, targetTitle);
    await rowWithText(page, customTitle);
    if (await page.locator('.wpsr-templates-table .el-table__row').count() !== Object.keys(before.reviews).length) {
        throw new Error('clearing platform filter did not restore exact visible fixture count');
    }
    const after = await getState(browserContext);
    if (JSON.stringify(after.reviews) !== beforeReviews) throw new Error('read-only search/filter flow changed persisted review state');
    await assertNoVisibleErrors(page);
}

async function flowTranslationSettings(page, browserContext, owned) {
    owned.optionKeys.push('wpsr_global_settings');
    const rawReadMore = `${auth.marker} <b>Continue</b>`;
    const expectedReadMore = `${auth.marker} Continue`;
    const readLess = `${auth.marker} Collapse`;
    const translationRow = title => page.locator('.wpsr-translation-row').filter({
        has: page.locator('.wpsr-translation-original-text', { hasText: title }),
    }).first();

    await gotoAdmin(page, '/settings/translation-management');
    let readMoreRow = translationRow('Read More');
    let readLessRow = translationRow('Read Less');
    await readMoreRow.waitFor({ state: 'visible' });
    await readMoreRow.locator('input').fill(rawReadMore);
    await readLessRow.locator('input').fill(readLess);
    await page.getByRole('button', { name: 'Save Settings', exact: true }).click();
    await waitRelevantIdle();
    const success = page.locator('.el-notification:visible').filter({ hasText: 'Settings saved successfully!' }).first();
    await success.waitFor({ state: 'visible', timeout: 8000 });
    await waitState(browserContext, value => {
        const translations = value.global_settings?.global_settings?.translations;
        return translations?.read_more === expectedReadMore && translations?.read_less === readLess;
    }, 'translation save did not persist exact sanitized values');

    await setHash(page, '/reviews');
    await setHash(page, '/settings/translation-management');
    readMoreRow = translationRow('Read More');
    readLessRow = translationRow('Read Less');
    await readMoreRow.waitFor({ state: 'visible' });
    if (await readMoreRow.locator('input').inputValue() !== expectedReadMore) throw new Error('translation reload lacks exact persisted Read More value');
    if (await readLessRow.locator('input').inputValue() !== readLess) throw new Error('translation reload changed persisted sibling value');

    await readMoreRow.locator('input').fill(`${auth.marker} Unsaved Edit`);
    await setHash(page, '/reviews');
    let state = await getState(browserContext);
    if (state.global_settings?.global_settings?.translations?.read_more !== expectedReadMore) {
        throw new Error('unsaved translation edit changed persisted option');
    }
    await setHash(page, '/settings/translation-management');
    readMoreRow = translationRow('Read More');
    await readMoreRow.waitFor({ state: 'visible' });
    if (await readMoreRow.locator('input').inputValue() !== expectedReadMore) throw new Error('unsaved translation edit survived navigation reload');
    await assertNoVisibleErrors(page);
}

const handlers = {
    'review-moderation': flowReviewModeration,
    'review-template-lifecycle': flowReviewTemplate,
    'chat-widget-lifecycle': flowChatWidget,
    'review-category-lifecycle': flowReviewCategoryLifecycle,
    'review-filtering-search': flowReviewFilteringSearch,
    'translation-settings-persistence': flowTranslationSettings,
};

(async () => {
    fs.rmSync(artifacts, { recursive: true, force: true });
    fs.mkdirSync(artifacts, { recursive: true });
    const started = Date.now();
    const browser = await chromium.launch({ headless: process.env.WPSR_BROWSER_HEADED !== '1' });
    const browserContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });

    await browserContext.route('**/*', async route => {
        const requestUrl = route.request().url();
        let parsed;
        try { parsed = new URL(requestUrl); } catch (error) { return route.continue(); }

        if (parsed.origin === appOrigin && parsed.pathname.startsWith(coreAssetPrefix)) {
            const relative = decodeURIComponent(parsed.pathname.slice(coreAssetPrefix.length));
            const file = path.resolve(buildAssets, relative);
            if (!file.startsWith(buildAssets + path.sep)) {
                record('asset-path', requestUrl);
                return route.abort('blockedbyclient');
            }
            const sourceFile = path.resolve(sourceAssets, relative);
            const assetFile = fs.existsSync(file) ? file : sourceFile;
            if (!sourceFile.startsWith(sourceAssets + path.sep) || !fs.existsSync(assetFile)) {
                record('missing-core-asset', requestUrl);
                return route.abort('failed');
            }
            try {
                await route.fulfill({ status: 200, body: await readAssetWithRetry(assetFile) });
                fulfilledCoreAssets.add(requestUrl);
                return;
            } catch (error) {
                record('core-asset-read', `${requestUrl}: ${error.message}`);
                return route.abort('failed');
            }
        }

        if ((parsed.protocol === 'http:' || parsed.protocol === 'https:') && parsed.origin !== appOrigin) {
            if (!isExpectedPassiveForeign(requestUrl)) record('unexpected-browser-outbound', requestUrl);
            return route.abort('blockedbyclient');
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
            try { if (new URL(locationUrl).origin !== appOrigin && isExpectedPassiveForeign(locationUrl)) return; } catch (error) {}
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
        const failure = request.failure() ? request.failure().errorText : 'unknown';
        const parsed = new URL(url);
        if (failure === 'net::ERR_ABORTED' &&
            parsed.origin === appOrigin &&
            parsed.pathname === `${coreAssetPrefix}images/svg/whatsapp.svg` &&
            fulfilledCoreAssets.has(url)) {
            ignoredForeign.add('fulfilled WhatsApp icon navigation abort (exact asset; existence/read preflight passed)');
            return;
        }
        record('request-failed', `${url}: ${failure}`);
    });
    page.on('response', response => {
        const parsed = new URL(response.url());
        const restRoute = parsed.searchParams.get('rest_route') || '';
        if (response.status() >= 400 && (
            parsed.pathname.startsWith(coreAssetPrefix) ||
            parsed.pathname.includes('/wp-json/wpsocialreviews/') ||
            parsed.pathname.includes('/wp-json/wpsr-test-lab/') ||
            restRoute.startsWith('/wpsocialreviews/') ||
            restRoute.startsWith('/wpsr-test-lab/') ||
            response.status() >= 500
        )) record('http-status', `${response.status()} ${response.url()}`);
    });

    let passed = 0;
    let failed = 0;
    try {
        for (const flow of selected) {
            currentFlow = flow.id;
            const signalStart = signals.length;
            const owned = { postIds: [], categoryIds: [], optionKeys: [] };
            let error = null;
            try {
                await handlers[flow.id](page, browserContext, owned);
                const ownSignals = signals.slice(signalStart);
                if (ownSignals.length) throw new Error(ownSignals.map(item => `${item.kind}: ${item.detail}`).join(' | '));
                const flowAudit = await getState(browserContext);
                if (flowAudit.outbound.length) throw new Error(`flow outbound audit expected 0, got ${JSON.stringify(flowAudit.outbound)}`);
            } catch (caught) {
                error = caught;
                const safe = flow.id.replace(/[^a-z0-9_-]/gi, '_');
                await page.screenshot({ path: path.join(artifacts, `${safe}.png`), fullPage: true }).catch(() => {});
            } finally {
                try { await cleanupOwned(browserContext, owned); } catch (cleanupError) {
                    error = error || cleanupError;
                    if (error !== cleanupError) error.message += ` | cleanup: ${cleanupError.message}`;
                }
            }
            if (error) {
                failed++;
                console.error(`FAIL  ${flow.id}: ${error.message}`);
            } else {
                passed++;
                console.log(`PASS  ${flow.id} — ${flow.title}`);
            }
        }

        currentFlow = 'final-audit';
        const finalState = await getState(browserContext);
        if (finalState.outbound.length) {
            failed++;
            console.error(`FAIL  outbound-audit: expected 0, got ${JSON.stringify(finalState.outbound)}`);
        } else {
            console.log('E2E outbound audit: HTTP 0, mail 0.');
        }
    } finally {
        await browser.close();
    }

    const bootstrapSignals = signals.filter(signal => signal.flow === 'bootstrap' || signal.flow === 'final-audit');
    if (bootstrapSignals.length) {
        failed++;
        console.error('FAIL  E2E global signals: ' + bootstrapSignals.map(item => `${item.kind}: ${item.detail}`).join(' | '));
    }
    const runtime = ((Date.now() - started) / 1000).toFixed(2);
    console.log(`E2E: ${passed}/${selected.length} passed, ${failed} failed, 0 skipped; runtime ${runtime}s`);
    console.log(`Noise filters: ${ignoredForeign.size ? [...ignoredForeign].join('; ') : 'none exercised'}`);
    if (passed + failed < selected.length || passed === 0 || failed) process.exit(1);
})().catch(error => {
    console.error(error.stack || error.message);
    process.exit(1);
});
