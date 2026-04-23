const mix = require('laravel-mix');
const path = require('path');
const fs = require("fs-extra");

// -------------------------------------------------
// Babel: Force modern Babel config to avoid legacy transforms and regenerator-runtime
// -------------------------------------------------
mix.babelConfig({
    presets: [
        ['@babel/preset-env', {
            targets: { esmodules: true },
            useBuiltIns: false,
            modules: false
        }]
    ]
});

// -------------------------------------------------
// Webpack configuration
// -------------------------------------------------
mix.webpackConfig({
    resolve: {
        extensions: ['.js', '.vue', '.json'],
        alias: {
            '@': path.resolve('resources/assets')
        }
    },
    module: {
        rules: [
            {
                test: /\.mjs$/,
                resolve: {fullySpecified: false},
                include: /node_modules/,
                type: "javascript/auto"
            }
        ]
    },
    // Fix stats configuration
    stats: {
        children: true
    },
    // Fix performance hints
    performance: {
        hints: false
    },
});

// -------------------------------------------------
// Source maps (dev only) + clean assets (production)
// -------------------------------------------------
if (!mix.inProduction()) {
    mix.webpackConfig({
        devtool: 'source-map'
    }).sourceMaps(true, 'source-map');
} else {
    // Clean assets directory before production build
    let fs = require('fs-extra');
    fs.remove('assets');
}

// -------------------------------------------------
// Mix setup
// -------------------------------------------------
mix
    .setPublicPath('assets')
    .setResourceRoot('../')

    // JavaScript files
    .js('resources/admin/Boot.js', 'assets/js/social-review-boot.js').vue({ version: 3 })
    .js('resources/admin/main.js', 'assets/js/social-review-admin.js').vue({ version: 3 })
    .js('resources/public/chat.js', 'assets/js/chat.js')
    .js('resources/public/wp-social-review.js', 'assets/js/wp-social-review.js')
    .js('resources/public/social-ninja-modal.js', 'assets/js/social-ninja-modal.js')
    .js('resources/public/wpsn-notification.js', 'assets/js/wpsn-notification.js')
    .js('resources/public/wpsr-fb-album.js', 'assets/js/wpsr-fb-album.js')
    .js('resources/public/image_resizer.js', 'assets/js/image_resizer.js')
    .js('resources/public/reviews-image-resizer.js', 'assets/js/reviews-image-resizer.js')
    .js('resources/public/review-form.js', 'assets/js/review-form.js')
    .js('resources/admin/Onboarding/onboarding.js', 'assets/js/onboarding.js')

    // React support for Gutenberg block
    .js('resources/admin/gutenblocks/shortcodeBlock.js', 'assets/js/wpsr-shortcode-block.js')
    .react()

    // SCSS files
    .sass('resources/scss/admin/app.scss', 'assets/css/social-review-admin.css')
    .sass('resources/scss/public/reviews.scss', 'assets/css/wp_social_ninja_reviews.css')
    .sass('resources/scss/public/testimonial.scss', 'assets/css/wp_social_ninja_testimonial.css')
    .sass('resources/scss/public/ig.scss', 'assets/css/wp_social_ninja_ig.css')
    .sass('resources/scss/public/yt.scss', 'assets/css/wp_social_ninja_yt.css')
    .sass('resources/scss/public/fb.scss', 'assets/css/wp_social_ninja_fb.css')
    .sass('resources/scss/public/tt.scss', 'assets/css/wp_social_ninja_tt.css')
    .sass('resources/scss/public/tw.scss', 'assets/css/wp_social_ninja_tw.css')
    .sass('resources/scss/public/chat.scss', 'assets/css/social-review-chat.css')
    .sass('resources/scss/public/review-form.scss', 'assets/css/review-form.css')
    .sass('resources/scss/admin/gutenBlock.scss', 'assets/css/social-review-gutenblock.css')
    .sass('resources/admin/Onboarding/onboarding.scss', 'assets/css/onboarding.css')

    // Assets
    .copy('resources/images', 'assets/images')
    .copy('resources/fonts', 'assets/fonts');

// Keep comment extraction enabled so release builds ship the required JS license files.
mix.options({
    legacyNodePolyfills: false,
    terser: {
        extractComments: {
            condition: /^\**!|@preserve|@license|@cc_on/i,
            filename(fileData) {
                return `${fileData.filename}.LICENSE.txt${fileData.query}`;
            },
            banner(licenseFile) {
                return `License information can be found in ${path.basename(licenseFile)}`;
            }
        },
    }
});


// DEBUG: Print full config to diagnose other issues (optional)
if (process.env.DEBUG_WEBPACK === 'true') {
    console.log(JSON.stringify(mix.config.webpackConfig, null, 2));
}
