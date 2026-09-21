import fs from 'node:fs';

const [wpsrResultsPath, wpsrExpectedFileCount = '0'] = process.argv.slice(2);

if (!wpsrResultsPath || !fs.existsSync(wpsrResultsPath)) {
    console.error('JS result verifier: missing Vitest JSON report.');
    process.exit(1);
}

let wpsrResults;
try {
    wpsrResults = JSON.parse(fs.readFileSync(wpsrResultsPath, 'utf8'));
} catch (wpsrError) {
    console.error(`JS result verifier: invalid Vitest JSON report: ${wpsrError.message}`);
    process.exit(1);
}

const wpsrExpectedFiles = Number(wpsrExpectedFileCount);
const wpsrReportedFiles = Array.isArray(wpsrResults.testResults) ? wpsrResults.testResults.length : 0;
const wpsrPassed = Number(wpsrResults.numPassedTests || 0);
const wpsrFailed = Number(wpsrResults.numFailedTests || 0);
const wpsrSkipped = Number(wpsrResults.numPendingTests || 0) + Number(wpsrResults.numTodoTests || 0);
const wpsrTotal = Number(wpsrResults.numTotalTests || 0);
const wpsrExecuted = wpsrPassed + wpsrFailed;
const wpsrErrors = [];
const wpsrSkipRegistryUrl = new URL('../skips/registry.json', import.meta.url);
let wpsrSkipRows = new Map();
try {
    const wpsrSkipRegistry = JSON.parse(fs.readFileSync(wpsrSkipRegistryUrl, 'utf8'));
    wpsrSkipRows = new Map(wpsrSkipRegistry.skips.map((wpsrRow) => [wpsrRow.id, wpsrRow]));
} catch (wpsrError) {
    wpsrErrors.push(`strict SKIP registry unavailable: ${wpsrError.message}`);
}
const wpsrSkipEvents = [];
for (const wpsrFileResult of wpsrResults.testResults || []) {
    for (const wpsrAssertion of wpsrFileResult.assertionResults || []) {
        if (!['pending', 'todo', 'skipped', 'disabled'].includes(wpsrAssertion.status)) continue;
        const wpsrTitle = String(wpsrAssertion.title || wpsrAssertion.fullName || '');
        const wpsrMatch = wpsrTitle.match(/^SKIP-ID ([a-z0-9]+(?:-[a-z0-9]+)*) — (.+)$/);
        if (!wpsrMatch) {
            wpsrErrors.push(`undeclared runtime JS skip: ${wpsrTitle || '(untitled)'}`);
            continue;
        }
        const [, wpsrId, wpsrTest] = wpsrMatch;
        const wpsrRow = wpsrSkipRows.get(wpsrId);
        if (!wpsrRow || wpsrRow.owner_plugin !== 'core' || wpsrRow.suite !== 'js' || wpsrRow.test !== wpsrTest) {
            wpsrErrors.push(`runtime JS skip metadata mismatch: ${wpsrId}`);
            continue;
        }
        if (wpsrSkipEvents.some((wpsrEvent) => wpsrEvent.id === wpsrId)) {
            wpsrErrors.push(`duplicate runtime JS skip: ${wpsrId}`);
            continue;
        }
        wpsrSkipEvents.push(wpsrRow);
    }
}
if (wpsrSkipEvents.length !== wpsrSkipped) {
    wpsrErrors.push(`JS skip summary mismatch: reported ${wpsrSkipped}, registered events ${wpsrSkipEvents.length}`);
}

if (!Number.isInteger(wpsrExpectedFiles) || wpsrExpectedFiles < 1) {
    wpsrErrors.push('selected zero test files');
}
if (wpsrReportedFiles < 1) {
    wpsrErrors.push('Vitest reported zero test files');
}
if (wpsrReportedFiles !== wpsrExpectedFiles) {
    wpsrErrors.push(`expected ${wpsrExpectedFiles} files but Vitest reported ${wpsrReportedFiles}`);
}
if (wpsrTotal < 1) {
    wpsrErrors.push('Vitest discovered zero tests');
}
if (wpsrExecuted < 1) {
    wpsrErrors.push('Vitest executed zero tests');
}
if (wpsrFailed > 0 || wpsrResults.success !== true) {
    wpsrErrors.push(`Vitest result is red (${wpsrFailed} failed)`);
}

if (wpsrErrors.length) {
    console.error(`JS result verifier: FAIL — ${wpsrErrors.join('; ')}. Suite wiring is broken.`);
    process.exit(1);
}

for (const wpsrRow of wpsrSkipEvents) {
    console.log(`WPSR-SKIP-JSON ${JSON.stringify({
        id: wpsrRow.id,
        owner_plugin: wpsrRow.owner_plugin,
        suite: wpsrRow.suite,
        test: wpsrRow.test,
        reason_category: wpsrRow.reason_category,
        reason: wpsrRow.reason,
        evidence: wpsrRow.evidence,
        expires_on: wpsrRow.expires_on,
        revisit: wpsrRow.revisit,
    })}`);
}
console.log(
    `JS-RESULTS: files=${wpsrReportedFiles}/${wpsrExpectedFiles} `
    + `tests=${wpsrPassed}/${wpsrTotal} failed=${wpsrFailed} skipped=${wpsrSkipped} executed=${wpsrExecuted}`
);
console.log(`WPSR-SUITE-JSON ${JSON.stringify({
    suite: 'JS-RESULTS',
    pass: wpsrPassed,
    fail: wpsrFailed,
    skip: wpsrSkipped,
    total: wpsrTotal,
    assertion_failures: wpsrFailed,
    runtime: 0,
    skip_ids: wpsrSkipEvents.map((wpsrRow) => wpsrRow.id),
})}`);
