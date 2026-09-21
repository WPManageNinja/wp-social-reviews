import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const wpsrRepoRoot = fileURLToPath(new URL('../..', import.meta.url));
const wpsrSpecsDir = path.join(wpsrRepoRoot, 'tests/js/specs');
const wpsrMappedModules = new Map([
    ['resources/admin/Rest.js', 'request-layer.test.js'],
    ['resources/admin/utils.js', 'admin-utils.test.js'],
    ['resources/admin/utils/reviewBulkSelection.js', 'admin-utils.test.js'],
    ['resources/admin/utils/upgradeUrl.js', 'admin-utils.test.js'],
    ['resources/admin/utils/visitorToolbar.js', 'admin-utils.test.js'],
    ['resources/admin/errors/Errors.js', 'state-errors.test.js'],
]);

const wpsrErrors = [];
const wpsrTestFiles = fs.existsSync(wpsrSpecsDir)
    ? fs.readdirSync(wpsrSpecsDir).filter((wpsrFile) => wpsrFile.endsWith('.test.js')).sort()
    : [];

if (!wpsrTestFiles.length) {
    wpsrErrors.push('discovered zero Vitest files');
}

for (const [wpsrSource, wpsrSpec] of wpsrMappedModules) {
    const wpsrSourcePath = path.join(wpsrRepoRoot, wpsrSource);
    const wpsrSpecPath = path.join(wpsrSpecsDir, wpsrSpec);
    if (!fs.existsSync(wpsrSourcePath)) {
        wpsrErrors.push(`mapped production module missing: ${wpsrSource}`);
        continue;
    }
    if (!fs.existsSync(wpsrSpecPath)) {
        wpsrErrors.push(`mapped test file missing: tests/js/specs/${wpsrSpec}`);
        continue;
    }
    const wpsrSpecSource = fs.readFileSync(wpsrSpecPath, 'utf8');
    if (!wpsrSpecSource.includes(path.basename(wpsrSource))) {
        wpsrErrors.push(`test no longer imports real module: ${wpsrSource}`);
    }
}

const wpsrAdminRoot = path.join(wpsrRepoRoot, 'resources/admin');
const wpsrFrontendFiles = [];
const wpsrWalk = (wpsrDirectory) => {
    for (const wpsrEntry of fs.readdirSync(wpsrDirectory, { withFileTypes: true })) {
        const wpsrEntryPath = path.join(wpsrDirectory, wpsrEntry.name);
        if (wpsrEntry.isDirectory()) {
            wpsrWalk(wpsrEntryPath);
        } else if (/\.(js|vue)$/.test(wpsrEntry.name)) {
            wpsrFrontendFiles.push(wpsrEntryPath);
        }
    }
};
wpsrWalk(wpsrAdminRoot);

const wpsrStorePattern = /(?:from\s+['"]vuex['"]|require\(['"]vuex['"]\)|\bcreateStore\s*\(|\bnew\s+Vuex\.Store\s*\(|\bmap(?:State|Getters|Actions|Mutations)\s*\()/;
const wpsrStoreFiles = wpsrFrontendFiles.filter((wpsrFile) =>
    wpsrStorePattern.test(fs.readFileSync(wpsrFile, 'utf8'))
);

if (wpsrStoreFiles.length) {
    wpsrErrors.push(
        `Vuex/store source appeared without Phase 15 disposition: ${wpsrStoreFiles
            .map((wpsrFile) => path.relative(wpsrRepoRoot, wpsrFile))
            .join(', ')}`
    );
}

if (wpsrErrors.length) {
    console.error(`js-inventory: FAIL — ${wpsrErrors.join('; ')}. Suite wiring is broken.`);
    process.exit(1);
}

console.log(
    `js-inventory: ${wpsrMappedModules.size} real production modules mapped; `
    + `${wpsrTestFiles.length} Vitest files; ${wpsrStoreFiles.length} Vuex/store modules discovered (source has none).`
);
