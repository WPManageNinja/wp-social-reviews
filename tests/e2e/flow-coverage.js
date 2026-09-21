#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const flows = require('./flows.manifest');

const repoRoot = path.resolve(__dirname, '..', '..');
const expectedIds = [
    'review-moderation',
    'review-template-lifecycle',
    'chat-widget-lifecycle',
    'review-category-lifecycle',
    'review-filtering-search',
    'translation-settings-persistence',
];
const expectedByPhase = { 17: 3, 21: 3 };
const errors = [];

if (!Array.isArray(flows) || flows.length === 0) {
    errors.push('discovered zero E2E flows; manifest wiring is broken');
}
if (flows.length !== expectedIds.length) {
    errors.push(`Phases 17 + 21 must discover exactly ${expectedIds.length} flows; found ${flows.length}`);
}

const ids = [];
const phaseCounts = {};
for (const flow of flows) {
    if (!flow || typeof flow !== 'object' || !flow.id || !flow.title || !flow.route || ![17, 21].includes(flow.phase) || !Array.isArray(flow.sources) || !flow.sources.length) {
        errors.push(`malformed E2E flow: ${JSON.stringify(flow)}`);
        continue;
    }
    ids.push(flow.id);
    phaseCounts[flow.phase] = (phaseCounts[flow.phase] || 0) + 1;
    for (const source of flow.sources) {
        if (!source.file || !Array.isArray(source.snippets) || !source.snippets.length) {
            errors.push(`${flow.id}: malformed source contract ${JSON.stringify(source)}`);
            continue;
        }
        const file = path.join(repoRoot, source.file);
        if (!fs.existsSync(file)) {
            errors.push(`${flow.id}: source file missing: ${source.file}`);
            continue;
        }
        const text = fs.readFileSync(file, 'utf8');
        for (const snippet of source.snippets) {
            if (!snippet || !text.includes(snippet)) {
                errors.push(`${flow.id}: selector/action contract '${snippet}' absent from ${source.file}`);
            }
        }
    }
}

if (new Set(ids).size !== ids.length) errors.push('duplicate E2E flow IDs');
if (JSON.stringify(ids) !== JSON.stringify(expectedIds)) {
    errors.push(`E2E flow inventory drift: expected ${expectedIds.join(', ')}; found ${ids.join(', ')}`);
}
for (const [phase, count] of Object.entries(expectedByPhase)) {
    if ((phaseCounts[phase] || 0) !== count) {
        errors.push(`Phase ${phase} expected exactly ${count} flows; found ${phaseCounts[phase] || 0}`);
    }
}
for (const phase of Object.keys(phaseCounts)) {
    if (!Object.prototype.hasOwnProperty.call(expectedByPhase, phase)) {
        errors.push(`unexpected E2E phase ${phase}`);
    }
}

console.log(`e2e-flow-coverage: discovered ${flows.length} flows; Phase 17=${phaseCounts[17] || 0}; Phase 21=${phaseCounts[21] || 0}; ${ids.length} source-mapped rows.`);
if (errors.length) {
    console.error('e2e-flow-coverage: FAIL');
    for (const error of errors) console.error(`  - ${error}`);
    process.exit(1);
}
console.log('OK — all six Phase 17 + Phase 21 core-free flows represented; selector/action source contracts present.');
