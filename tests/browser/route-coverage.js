#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const manifest = require('./screens.manifest');
const { collectRouterScreens, repoRoot } = require('./router-inventory');
const { assertKnownFailureMatcherContract } = require('./known-failure-contract');

const errors = [];
const routes = collectRouterScreens();
const fixPlanPath = path.join(repoRoot, 'tests/FIX-PLAN.md');
const fixPlanSource = fs.existsSync(fixPlanPath) ? fs.readFileSync(fixPlanPath, 'utf8') : '';

try {
    assertKnownFailureMatcherContract();
} catch (error) {
    errors.push(`known-failure matcher contract is broken: ${error.message}`);
}

if (!routes.length) errors.push('discovered zero leaf SPA routes; router collection is broken');
if (!manifest.length) errors.push('discovered zero browser screen cases; manifest wiring is broken');

const routeByName = new Map();
for (const route of routes) {
    if (!route.name) errors.push(`anonymous leaf route at ${route.path}`);
    if (routeByName.has(route.name)) errors.push(`duplicate router name: ${route.name}`);
    routeByName.set(route.name, route);
}

const manifestByName = new Map();
const knownFailureIds = new Set();
let active = 0;
let knownFailures = 0;
for (const item of manifest) {
    if (!item || typeof item !== 'object') {
        errors.push('malformed non-object manifest row');
        continue;
    }
    if (!item.name || !item.path || !item.component || !item.componentFile || !item.selector) {
        errors.push(`malformed manifest row: ${JSON.stringify(item)}`);
        continue;
    }
    if (manifestByName.has(item.name)) errors.push(`duplicate manifest name: ${item.name}`);
    manifestByName.set(item.name, item);

    const route = routeByName.get(item.name);
    if (!route) {
        errors.push(`stale/phantom manifest route: ${item.name} (${item.path})`);
        continue;
    }
    for (const field of ['path', 'component', 'componentFile']) {
        if (item[field] !== route[field]) {
            errors.push(`${item.name}: manifest ${field} '${item[field]}' != router '${route[field]}'`);
        }
    }

    const placeholders = [...item.path.matchAll(/:([A-Za-z_][A-Za-z0-9_]*)/g)];
    if (placeholders.length && !item.token) errors.push(`${item.name}: parameterized route lacks fixture token`);
    if (!placeholders.length && item.token) errors.push(`${item.name}: non-parameterized route has stale fixture token`);

    if (item.skip) {
        errors.push(`${item.name}: unconditional browser skip is forbidden: ${item.skip}`);
    }
    active++;
    if (item.knownFailure) {
        knownFailures++;
        for (const field of ['id', 'reason', 'expectedError', 'remediation', 'revisit']) {
            if (!item.knownFailure[field]) errors.push(`${item.name}: known failure lacks ${field}`);
        }
        if (item.knownFailure.expectedError) {
            try {
                new RegExp(item.knownFailure.expectedError);
            } catch (error) {
                errors.push(`${item.name}: known failure expectedError is invalid: ${error.message}`);
            }
            if (!item.knownFailure.expectedError.startsWith('^') || !item.knownFailure.expectedError.endsWith('$')) {
                errors.push(`${item.name}: known failure expectedError must be anchored`);
            }
        }
        if (item.knownFailure.remediation !== `tests/FIX-PLAN.md#${item.knownFailure.id}`) {
            errors.push(`${item.name}: known failure lacks exact durable FIX-PLAN remediation`);
        } else if (!fixPlanSource.includes(`<a id="${item.knownFailure.id}"></a>`)) {
            errors.push(`${item.name}: known failure remediation anchor is missing from tests/FIX-PLAN.md`);
        }
        if (knownFailureIds.has(item.knownFailure.id)) {
            errors.push(`${item.name}: duplicate known-failure ID: ${item.knownFailure.id}`);
        }
        knownFailureIds.add(item.knownFailure.id);
    }

    const componentPath = path.join(repoRoot, item.componentFile);
    if (!fs.existsSync(componentPath)) {
        errors.push(`${item.name}: component source missing: ${item.componentFile}`);
        continue;
    }
    const componentSource = fs.readFileSync(componentPath, 'utf8');
    const classTokens = [...item.selector.matchAll(/\.([A-Za-z_][A-Za-z0-9_-]*)/g)].map(match => match[1]);
    if (!classTokens.length) errors.push(`${item.name}: selector has no component class token: ${item.selector}`);
    for (const classToken of classTokens) {
        if (!componentSource.includes(classToken)) {
            errors.push(`${item.name}: selector class '${classToken}' absent from ${item.componentFile}`);
        }
    }
}

for (const route of routes) {
    if (!manifestByName.has(route.name)) {
        errors.push(`missing browser smoke mapping: ${route.name} ${route.path} -> ${route.component}`);
    }
}
if (!active) errors.push('zero runnable browser screens; browser tier wiring is broken');

console.log(`browser-route-coverage: discovered ${routes.length} leaf SPA routes; ${manifest.length} manifest rows; ${active} runnable; ${knownFailures} active known-failure tripwires.`);
if (errors.length) {
    console.error('browser-route-coverage: FAIL');
    for (const error of errors) console.error(`  - ${error}`);
    process.exit(1);
}
console.log(`OK — ${routes.length}/${routes.length} core SPA leaf routes represented; component selectors source-verified; no drift.`);
