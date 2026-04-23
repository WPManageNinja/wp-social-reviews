# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WP Social Ninja — a WordPress plugin for aggregating social media feeds, reviews, chat widgets, testimonials, and shoppable content from 12+ platforms (Google, Facebook, Instagram, TikTok, YouTube, Airbnb, Yelp, TripAdvisor, Amazon, AliExpress, Booking, WooCommerce).

## Build & Development Commands

```bash
# Install dependencies
npm install
composer install

# Development (watch mode)
npx mix watch

# Production build
npx mix --production

# Release build (free & pro)
sh ./build.sh --node-build --with-pro

# Tests (requires WordPress test suite)
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
# Test files follow naming: test-*.php in /tests
```

Build is Laravel Mix 6 (`webpack.mix.js`). It compiles ~10 JS entry points and ~10 SCSS files. Vue 3 for admin, React for Gutenberg blocks.

## Architecture

**Framework**: Custom WPFluent framework (`vendor/wpfluent/`) — Laravel-inspired with Eloquent-like ORM, service container, routing, and migrations. The `Application` class bootstraps in `boot/app.php`.

**PHP Backend** (`app/`):
- `Http/Controllers/` — REST controllers
- `Http/Routes/api.php` — All REST endpoints, policy-authorized (`AdminPolicy`, `PlatformPolicy`, etc.)
- `Services/Platforms/` — Platform integrations organized by type: `Reviews/`, `Feeds/`, `Chats/`
- `Models/` — Eloquent-like models (tables prefixed `wpsr_`)
- `Hooks/Handlers/` — WordPress hook handlers
- `Views/` — PHP template files

**Frontend** (`resources/`):
- `admin/` — Vue 3 + Vuex + Vue Router + Element Plus admin SPA
- `admin/main.js` — App entry, registers global `$get/$post/$put/$del/$patch` REST helpers
- `public/` — Public-facing vanilla JS
- `scss/` — SASS stylesheets

**Database**: Migrations in `database/Migrations/` using classmap autoloading. Tables: `wpsr_reviews`, `wpsr_caches`, `wpsr_review_forms`, etc.

**Global Functions** (`boot/globals.php`): Plugin-wide helpers loaded before framework — `wpsrSocialReviews()`, `wpsrDb()`, `wpsr_backend_sanitizer()`, and CSS/style sanitizers. Do not call framework services from this file.

## Key Conventions

### Naming Prefixes (mandatory)
- CSS/HTML classes and IDs: `wpsr-` prefix (e.g., `wpsr-button`, `wpsr-card-title`)
- PHP variables/functions: `wpsr_` prefix (e.g., `wpsr_process_order()`, `$wpsr_product_id`)
- JS variables/functions: `wpsr` camelCase prefix (e.g., `wpsrProductIds`)
- DB tables: `wpsr_` prefix
- WordPress options: `wpsr_` prefix
- Hook/filter namespace: `wpsocialreviews/` (e.g., `wpsocialreviews/backend_sanitized_values`)

### Validation
- Always validate IDs and variables before use — in loops (`continue` on empty), in functions (early return), before DB queries and API calls
- Use `empty()` in PHP, `!variable` in JS
- Never assume an ID will have a value, even in arrays

### Sanitization
- Use `wpsr_backend_sanitizer()` with a sanitize map for backend input
- WordPress options: `update_option($key, $value, 'no')` (disable autoload)
- Style configs: use the `wpsr_sanitize_*` helpers (`wpsr_sanitize_color`, `wpsr_sanitize_css_unit`, etc.)

### PHP Patterns
- Namespace: `WPSocialReviews\App\` (PSR-4)
- Framework namespace: `WPSocialReviews\Framework\`
- Helper aliases: `GlobalHelper` and `ServicesHelper` both map to `App\Services\Helper`
- Suppress PHP CS warnings with: `// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log`
- Platform services extend base classes (e.g., `BaseReview` for review platforms)

### Plugin Constants
- `WPSOCIALREVIEWS_VERSION`, `WPSOCIALREVIEWS_DB_VERSION`, `WPSOCIALREVIEWS_URL`, `WPSOCIALREVIEWS_DIR`, `WPSOCIALREVIEWS_UPLOAD_DIR_NAME`

### REST API
Routes defined in `app/Http/Routes/api.php`. All endpoints use policy-based authorization. Major route groups: `/platforms/`, `/reviews/`, `/testimonials/`, `/settings/`, `/chat-widgets/`, `/notifications/`, `/templates/`, `/custom-sources/`.
