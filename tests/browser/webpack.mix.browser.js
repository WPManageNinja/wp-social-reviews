'use strict';

const mix = require('laravel-mix');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..');
const output = path.resolve(__dirname, '.build/assets');

mix.babelConfig({
    presets: [[
        '@babel/preset-env',
        { targets: { esmodules: true }, useBuiltIns: false, modules: false },
    ]],
});

mix.webpackConfig({
    resolve: {
        extensions: ['.js', '.vue', '.json'],
        modules: [path.join(__dirname, 'node_modules'), 'node_modules'],
        alias: { '@': path.join(repoRoot, 'resources/assets') },
    },
    module: {
        rules: [{
            test: /\.mjs$/,
            resolve: { fullySpecified: false },
            include: /node_modules/,
            type: 'javascript/auto',
        }],
    },
    stats: { children: false, warnings: false },
    performance: { hints: false },
    externals: {
        '@wordpress/blocks': 'wp.blocks',
        '@wordpress/components': 'wp.components',
        '@wordpress/element': 'wp.element',
        '@wordpress/i18n': 'wp.i18n',
        '@wordpress/block-editor': 'wp.blockEditor',
        '@wordpress/editor': 'wp.editor',
    },
});

mix
    .setPublicPath('.build/assets')
    .setResourceRoot('../')
    .js(path.join(repoRoot, 'resources/admin/Boot.js'), '.build/assets/js/social-review-boot.js').vue({ version: 3 })
    .js(path.join(repoRoot, 'resources/admin/main.js'), '.build/assets/js/social-review-admin.js').vue({ version: 3 })
    .sass(path.join(repoRoot, 'resources/scss/admin/app.scss'), '.build/assets/css/social-review-admin.css')
    .copyDirectory(path.join(repoRoot, 'resources/images'), path.join(output, 'images'))
    .copyDirectory(path.join(repoRoot, 'resources/fonts'), path.join(output, 'fonts'));

mix.options({ legacyNodePolyfills: false });
