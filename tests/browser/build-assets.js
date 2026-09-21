#!/usr/bin/env node
'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const browserDir = __dirname;
const repoRoot = path.resolve(browserDir, '..', '..');
const buildDir = path.join(browserDir, '.build');
const stampFile = path.join(buildDir, 'source.sha256');
const expected = [
    'assets/js/social-review-boot.js',
    'assets/js/social-review-admin.js',
    'assets/css/social-review-admin.css',
];

function filesUnder(target) {
    const result = [];
    const visit = current => {
        const stat = fs.statSync(current);
        if (stat.isDirectory()) {
            for (const entry of fs.readdirSync(current).sort()) visit(path.join(current, entry));
        } else if (stat.isFile()) {
            result.push(current);
        }
    };
    visit(target);
    return result;
}

const inputs = [
    path.join(repoRoot, 'resources/admin'),
    path.join(repoRoot, 'resources/scss/admin'),
    path.join(repoRoot, 'resources/images'),
    path.join(repoRoot, 'resources/fonts'),
    path.join(browserDir, 'webpack.mix.browser.js'),
    path.join(browserDir, 'package-lock.json'),
];
const hash = crypto.createHash('sha256');
for (const input of inputs) {
    for (const file of filesUnder(input)) {
        hash.update(path.relative(repoRoot, file));
        hash.update('\0');
        hash.update(fs.readFileSync(file));
        hash.update('\0');
    }
}
const digest = hash.digest('hex');
const current = fs.existsSync(stampFile) ? fs.readFileSync(stampFile, 'utf8').trim() : '';
if (current === digest && expected.every(file => fs.existsSync(path.join(buildDir, file)))) {
    console.log(`browser-assets: cache hit ${digest.slice(0, 12)}`);
    process.exit(0);
}

fs.rmSync(buildDir, { recursive: true, force: true });
fs.mkdirSync(buildDir, { recursive: true });
console.log(`browser-assets: compiling current admin source ${digest.slice(0, 12)}`);
const binDir = path.join(browserDir, 'node_modules', '.bin');
const command = path.join(binDir, process.platform === 'win32' ? 'mix.cmd' : 'mix');
const result = spawnSync(command, [
    '--mix-config',
    path.join(browserDir, 'webpack.mix.browser.js'),
    '--production',
    '--no-progress',
], {
    cwd: browserDir,
    env: { ...process.env, NODE_ENV: 'production', PATH: `${binDir}${path.delimiter}${process.env.PATH || ''}` },
    encoding: 'utf8',
    stdio: 'inherit',
});
if (result.error) throw result.error;
if (result.status !== 0) process.exit(result.status || 1);
for (const file of expected) {
    if (!fs.existsSync(path.join(buildDir, file))) {
        throw new Error(`Browser build omitted ${file}`);
    }
}
fs.writeFileSync(stampFile, digest + '\n');
console.log(`browser-assets: ready ${digest.slice(0, 12)}`);
