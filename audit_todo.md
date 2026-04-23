# FluentCart Integration Audit Report

**Date:** 2026-02-27
**Scope:** All FluentCart-related code across wp-social-reviews (free) and wp-social-ninja-pro (pro)
**Methodology:** 6 parallel sub-agents: Security, Optimization, UI Traceability, Route Traceability, Service Traceability, Database Traceability

---

## Summary

| Category | Critical | High | Medium | Low | Total |
|----------|----------|------|--------|-----|-------|
| Security | 0 | 2 | 5 | 5 | 12 |
| Data Integrity | 3 | 1 | 2 | 0 | 6 |
| Optimization | 0 | 3 | 4 | 6 | 13 |
| Traceability | 0 | 1 | 1 | 4 | 6 |
| **Total** | **3** | **7** | **12** | **15** | **37** |

---

## GROUP 1: CRITICAL -- Data Corruption Bugs

These are functional bugs that will cause data corruption during normal operations. Fix before production use.

### C1. syncRemoteReviews() has no dedup logic for fluent-cart
- **Severity:** CRITICAL
- **File:** `wp-social-reviews/app/Services/Platforms/Reviews/BaseReview.php` lines 106-131
- **What:** The duplicate-detection switch in `syncRemoteReviews()` has no branch for `fluent-cart`. It falls through to `$fieldName = 'reviewer_url'` and `$value = ''`. Since all FluentCart reviews have empty `reviewer_url`, the query `WHERE platform_name='fluent-cart' AND reviewer_url=''` matches the FIRST review for ANY product. On re-sync, all reviews collapse into a single record.
- **Impact:** Review data corruption -- duplicates created or reviews overwritten with wrong data during cron sync or manual reconnect.
- **Fix:** Add `fluent-cart` to the `review_id`-based dedup list:
  ```php
  // Around line 106, add 'fluent-cart' alongside 'woocommerce':
  if (in_array($this->platform, $remoteSyncReviewerNames) || $this->platform === 'woocommerce' || $this->platform === 'fluent-cart' || ...) {
      $fieldName = 'review_id';
  }
  // Around line 130, add value extraction:
  } elseif ($this->platform === 'fluent-cart') {
      $value = Arr::get($review, 'review_id');
  }
  ```
- [x] DONE: Fix dedup logic

### C2. fetchProductData() returns wrong data format for syncRemoteReviews()
- **Severity:** CRITICAL
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php` line 263
- **What:** `fetchProductData()` sets `$data['reviews'] = $reviews` where `$reviews` is an Eloquent Collection of Review models. When `ProductReviewPlatform::formatData()` processes these, it expects array keys like `review_date`, `review_text`, `review_rating` -- but the model has `review_time`, `reviewer_text`, `rating`. Result: reviews saved with empty text, zero ratings, and wrong dates.
- **Impact:** Review content destroyed on re-sync via `verifyCredential()`.
- **Fix:** Either transform reviews to expected format or (better) skip re-syncing already-stored reviews:
  ```php
  // In fetchProductData(), return reviews in the expected format:
  $formattedReviews = $reviews->map(function($r) {
      return [
          'review_id'      => $r->review_id,
          'reviewer_name'  => $r->reviewer_name,
          'review_date'    => strtotime($r->review_time),
          'review_text'    => $r->reviewer_text,
          'review_rating'  => $r->rating,
          'reviewer_email' => $r->reviewer_email ?? '',
      ];
  })->toArray();
  $data['reviews'] = $formattedReviews;
  ```
- [x] DONE: Fix data format

### C3. connectAllProducts() uses wrong case for product ID key
- **Severity:** CRITICAL
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCart.php` line 121
- **What:** `Arr::get($product, 'ID')` uses uppercase, but FluentCart's `Product::toArray()` produces lowercase `'id'` (Eloquent convention). When FluentCart models are available (the primary path), `$productId` is `null` for every product, causing all `verifyCredential(null)` calls to throw exceptions.
- **Impact:** "Connect All Products" feature is completely broken when FluentCart is active.
- **Fix:**
  ```php
  $productId = is_array($product)
      ? (Arr::get($product, 'ID') ?: Arr::get($product, 'id'))
      : $product->ID;
  ```
- [x] DONE: Fix product ID key casing

---

## GROUP 2: HIGH -- Security & Data Issues

### H1. Missing esc_html() in displayProductListRating()
- **Severity:** HIGH (Security)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartTemplate.php` lines 212-215
- **What:** Rating count output lacks `esc_html()`. Compare with `displaySingleProductRating()` at line 167 which correctly escapes. If `$total_rating` were manipulated via corrupted `wp_options`, this is XSS.
- **Fix:** `esc_html(number_format_i18n($total_rating))`
- [x] DONE: Add escaping

### H2. Overly permissive route policy for FluentCart endpoint
- **Severity:** HIGH (Security)
- **File:** `wp-social-reviews/app/Http/Policies/SettingsPolicy.php` lines 14-21
- **Route:** `POST /pro/settings/fluent-cart/connect-all-products`
- **What:** `SettingsPolicy` grants access if user has ANY ONE of 5 permissions (OR logic). Users with only `wpsn_translation_settings` can reach the endpoint. The controller's `validateFluentCartAccess()` adds a stricter check, but the route-level policy is too permissive.
- **Fix:** Create a dedicated `FluentCartPolicy` or tighten `SettingsPolicy` for this route.
- [ ] TODO: Tighten route policy

### H3. array_column() on Eloquent Collection always returns empty
- **Severity:** HIGH (Data)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php` line 270
- **What:** `array_column($reviews, 'rating')` where `$reviews` is an Eloquent Collection. `array_column()` on Collection of model objects returns `[]`, so `average_rating` is always `0`.
- **Fix:**
  ```php
  $totalRating = array_sum($reviews->pluck('rating')->toArray());
  ```
- [x] DONE: Fix rating calculation (covered by C2)

### H4. saveBusinessInfo() returns stale data on re-sync
- **Severity:** HIGH (Data)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/ProductReviews/ProductReviewPlatform.php` lines 207-223
- **What:** `saveBusinessInfo()` has early-return logic: if business info exists, return existing data without updating. Then `verifyCredential()` writes back this stale data. Average rating and total count never update after initial connection.
- **Fix:** Remove the early return or always update rating fields.
- [x] DONE: Fix stale business info

### H5. Unescaped generateRatingIcon() HTML output
- **Severity:** HIGH (Security)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartTemplate.php` lines 157, 160, 207, 209
- **Source:** `wp-social-reviews/app/Services/Platforms/Reviews/Helper.php` lines 574-601
- **What:** `Helper::generateRatingIcon()` returns raw HTML echoed without `wp_kses_post()`. Currently safe because input is `(float)` cast, but a modification to the helper could introduce XSS.
- **Fix:** Wrap output in `wp_kses_post()`.
- [x] DONE: Add wp_kses_post

### H6. Exception messages exposed to users in API responses
- **Severity:** HIGH (Security)
- **File:** `wp-social-ninja-pro/app/Http/Controllers/SettingsController.php` lines 93, 116, 146, 210
- **What:** Raw `$e->getMessage()` returned in error responses. May contain DB table names, file paths, SQL errors.
- **Fix:** Log internally, return generic message.
- [x] DONE: Sanitize error responses

### H7. selectedBusinesses appended instead of replaced
- **Severity:** HIGH (Traceability)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php` line 171
- **What:** `$template_meta['selectedBusinesses'][] = $product_id` appends. If template has existing businesses, product page shows reviews from unrelated businesses.
- **Fix:** `$template_meta['selectedBusinesses'] = [$product_id];`
- [x] DONE: Replace instead of append

---

## GROUP 3: MEDIUM -- Performance & Correctness

### M1. registered_post_type hook fires for every post type
- **Severity:** MEDIUM (Performance)
- **File:** `wp-social-ninja-pro/app/Hooks/Handlers/PlatformHandlerPro.php` line 38
- **What:** `add_action('registered_post_type', [(new FluentCart()), 'registerHooks'])` -- fires 15-30 times per page load (once per registered post type). Each call creates new closures and re-registers hooks. WooCommerce uses its own one-shot hook.
- **Fix:** Use `fluent_cart/init` or guard with static flag.
- [x] DONE: Optimize hook registration

### M2. Two separate FluentCartTemplate instances defeat caching
- **Severity:** MEDIUM (Performance)
- **File:** `wp-social-ninja-pro/app/Hooks/actions.php` lines 115, 139
- **What:** Two `new FluentCartTemplate()` created per request. Each has its own cache properties (`$effectiveSettingsCache`, `$allBusinessInfo`). Caching in instance 1 doesn't benefit instance 2.
- **Fix:** Share a single instance.
- [x] DONE: Use singleton instance

### M3. connectAllProducts() N+1 query pattern
- **Severity:** MEDIUM (Performance)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCart.php` lines 103-152
- **What:** `verifyCredential()` called in loop per product. Each call reads/writes options 6+ times. For 50 products = 300+ DB operations.
- **Fix:** Batch-load business info, accumulate changes, write once after loop.
- [x] DONE: Batch operations

### M4. getAllProducts() with numberposts => -1
- **Severity:** MEDIUM (Performance/Scalability)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php` lines 380-386
- **What:** Loads ALL products into memory. Could cause memory exhaustion on large stores.
- **Fix:** Add reasonable limit or implement pagination.
- [x] DONE: Add query limit

### M5. clearVerificationConfigs is a no-op -- users can't disconnect
- **Severity:** MEDIUM (Data)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/ProductReviews/ProductReviewPlatform.php` lines 282-285
- **What:** Empty method body. When user disconnects FluentCart, options and business info persist. Old data continues to display.
- **Fix:** Implement proper cleanup.
- [x] DONE: Implement disconnect cleanup

### M6. Race condition on concurrent business info writes
- **Severity:** MEDIUM (Data)
- **Files:** `ProductReviewPlatform.php` lines 199-223 and 148-151, `ReviewsTemplateHandlerPro.php` lines 605-622
- **What:** Three write paths to same option key use read-modify-write without locking. Concurrent requests can lose data.
- **Fix:** Use transient locking or switch to per-product options.
- [x] DONE: Add write locking

### M7. JSON injection vector in admin column template
- **Severity:** MEDIUM (Security)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartTemplate.php` lines 76-90
- **What:** `$ratingsMap` keys come from option array keys without integer cast. If a crafted key entered the business_info option, JSON could be malformed.
- **Fix:** Cast keys: `$ratingsMap[(int) $productId] = ...`
- [x] DONE: Cast product ID keys

### M8. No CSRF nonce verification at framework level
- **Severity:** MEDIUM (Security)
- **File:** `wp-social-reviews/vendor/wpfluent/framework/src/WPFluent/Http/Route.php` line 142
- **What:** WPFluent relies on WordPress REST API nonce checking but doesn't explicitly enforce it. If any JS code omits `X-WP-Nonce`, CSRF attacks possible.
- **Fix:** Verify all frontend JS sends nonces.
- [x] AUDITED: No gaps found — CSRF protection is complete and correct.
  - **Admin REST API (142 + 52 Pro routes):** `wp_create_nonce('wp_rest')` localized via `AdminMenuHandler.php:376`. `resources/admin/Rest.js` sets `X-WP-Nonce` on every request via centralized `request()` function (line 4); nonce is also refreshed from response headers. All routes use `withPolicy()` → `permission_callback`, validated by WordPress REST before any handler runs.
  - **Public AJAX (state-modifying):** `wp_create_nonce('wpsr-ajax-nonce')` localized in `ShortcodeHandler.php:1124`. Image resizer (`image_resizer.js:28`), Twitter cards (`twitter-card.js:108`) send nonce with each call; server verifies via `wp_verify_nonce()` / `check_ajax_referer()`.
  - **Read-only public AJAX (`wpsr_get_more_feeds`):** No nonce required — acceptable, returns only feed HTML, no state mutation.
  - **No direct `$.ajax` / `fetch` bypasses found** outside `Rest.js` in admin JS.
  - Framework behaviour at `Route.php:142` (`permission_callback`) is correct — WordPress REST validates `X-WP-Nonce` before invoking it; no additional framework-level enforcement needed.

### M9. reviewsinfo initialized as Array but typed as Object
- **Severity:** MEDIUM (Traceability)
- **File:** `wp-social-reviews/resources/admin/components/views/platforms/reviews/ReviewsPlatforms.vue` line 573
- **What:** `reviewsinfo: []` but FluentCartForm declares `reviewsinfo: { type: Object }`. Vue emits console warnings. Could cause subtle bugs with numeric keys.
- **Fix:** Change to `reviewsinfo: {}` and update reset at line 964.
- [x] DONE: Fix type initialization

### M10. Missing isGlobalTemplateGettingPrecedence check
- **Severity:** MEDIUM (Correctness)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php` lines 164-176
- **What:** Unlike WooCommerceHelper, FluentCartHelper always overrides template meta on product pages regardless of global settings configuration.
- **Fix:** Align with WooCommerce pattern -- check `isGlobalTemplateGettingPrecedence` before modifying template_meta.
- [x] DONE: Add precedence check

### M11. No transaction wrapping on verifyCredential
- **Severity:** MEDIUM (Data)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/ProductReviews/ProductReviewPlatform.php` lines 118-153
- **What:** Five operations (save business info, sync reviews, save API settings, fire hook, update business info) not wrapped in transaction. PHP timeout leaves partial state.
- **Fix:** Add compensating error handling or wrap in transaction.
- [x] DONE: Wrapped four DB write operations in `START TRANSACTION / COMMIT / ROLLBACK` via `global $wpdb`. `do_action('wpsocialreviews/custom_review_updated')` moved to after commit (side effects cannot be rolled back). On rollback, `wp_cache_delete` clears the in-memory options cache for the two affected option keys so stale values are not served within the failed request.

### M12. fetchProductData inconsistent with fetchProductDataFromWPSR
- **Severity:** MEDIUM (Data)
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php` lines 239-334
- **What:** Two methods return different data structures for the same conceptual data. `fetchProductData` returns Eloquent models; `fetchProductDataFromWPSR` returns structured arrays.
- **Fix:** Unify return format (covered by C2 fix).
- [x] DONE: Covered by C2

---

## GROUP 4: LOW -- Code Quality & Minor Issues

### L1. Dead method: SocialNinjaConnect::getProductIntegrationFeed()
- **File:** `wp-social-ninja-pro/app/Services/Integrations/FluentCart/SocialNinjaConnect.php` lines 154-171
- **What:** Never called. Duplicates `FluentCartHelper::getFluentCartIntegrationSettings()`.
- [x] DONE: Remove dead method

### L2. Dead method: SocialNinjaConnect::getAllIntegrationFeeds()
- **File:** `wp-social-ninja-pro/app/Services/Integrations/FluentCart/SocialNinjaConnect.php` lines 178-199
- **What:** Never called anywhere.
- [x] DONE: Remove dead method

### L3. Empty processAction() without documentation
- **File:** `wp-social-ninja-pro/app/Services/Integrations/FluentCart/SocialNinjaConnect.php` lines 116-118
- **What:** Empty body with no comment explaining why.
- [x] DONE: Add explanatory comment

### L4. Commented-out WooCommerce code in PlatformHandlerPro
- **File:** `wp-social-ninja-pro/app/Hooks/Handlers/PlatformHandlerPro.php` line 39
- **What:** Leftover from copying WooCommerce registration pattern.
- [x] DONE: Remove commented-out code (covered by M1)

### L5. Pass-through methods: FluentCartHelper::isProductConnectedToWPSN() and getAllBusinessInfo()
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php` lines 342-356
- **What:** Just forward to ProductReviewHelper. Adds indirection.
- [ ] TODO: Consider removing wrappers

### L6. Unused $reviews = [] initialization
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php` line 260
- **What:** Dead assignment immediately overwritten.
- [x] DONE: Remove dead assignment (covered by C2)

### L7. Unused `type` data property in FluentCartForm.vue
- **File:** `wp-social-reviews/resources/admin/components/views/platforms/reviews/FluentCartForm.vue` line 82
- **What:** `type: 'text'` never used.
- [x] DONE: Remove unused data

### L8. v-model on el-button (FluentCartForm.vue)
- **File:** `wp-social-reviews/resources/admin/components/views/platforms/reviews/FluentCartForm.vue` line 8
- **What:** `v-model` on `el-button` has no effect. Dead code.
- [x] DONE: Remove v-model from button

### L9. Self-assignment in reviewPlatform watcher
- **File:** `wp-social-reviews/resources/admin/components/views/settings/reviews/ReviewSettings.vue` line 790
- **What:** `this.reviewPlatform = val` is a no-op.
- [x] DONE: Remove self-assignment

### L10. Unused prop verifiedPlatform in MultipleBusinessInfo
- **File:** `wp-social-reviews/resources/admin/components/views/platforms/reviews/MultipleBusinessInfo.vue` line 133
- **What:** Declared but never used or passed for fluent-cart.
- [ ] TODO: Remove unused prop

### L11. Silent exception swallowing in integration registration
- **File:** `wp-social-ninja-pro/app/Hooks/actions.php` lines 129-131
- **What:** `catch (\Throwable $e) {}` -- errors silently discarded.
- **Fix:** Add `error_log()`.
- [x] DONE: Log exceptions (covered by M2)

### L12. $_GET['page'] used without strict comparison
- **File:** `wp-social-ninja-pro/app/Hooks/actions.php` line 21
- **What:** Uses `==` instead of `===`, no `sanitize_text_field()`.
- [x] DONE: Use strict comparison

### L13. Product ID not validated as fluent-products post type
- **File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php` line 62
- **What:** `get_post_meta()` called without verifying post type.
- [x] DONE: Add post type check

### L14. Orphaned data not cleaned up on uninstall
- **File:** `wp-social-reviews/app/Hooks/Handlers/UninstallHandler.php`
- **What:** `wpsr-settings-fluent-cart` post meta and `fct_product_meta` entries persist after uninstall.
- [ ] TODO: Add cleanup for FluentCart data

### L15. Missing fluent-cart in getBrandIcons()
- **File:** `wp-social-reviews/app/Hooks/Handlers/AdminMenuHandler.php` lines 761-787
- **What:** No icon entry for `fluent-cart` in the brand icons dictionary.
- [x] DONE: Add FluentCart brand icon

---

## GROUP 5: Optimization Opportunities (Non-Urgent)

### O1. Duplicate fetchProductData / fetchProductDataFromWPSR logic
- **File:** `FluentCartHelper.php` lines 239-334
- **What:** 70% shared logic between these two methods.
- **Fix:** Extract shared product resolution and data construction into private helpers.
- [ ] TODO: DRY up fetch methods

### O2. getEffectiveSettings() has three large array literal returns
- **File:** `FluentCartHelper.php` lines 25-96
- **What:** Three branches each returning 10-12 key arrays with mostly identical values.
- **Fix:** Start with base defaults array, override per-branch.
- [ ] TODO: Refactor to base + overrides

### O3. hasExistingReviews() uses count() instead of exists()
- **File:** `FluentCart.php` line 39
- **What:** `Review::where('platform_name', 'fluent-cart')->count() > 0` scans all rows. `exists()` stops at first match.
- **Fix:** Use `->exists()` or `->limit(1)->count() > 0`.
- [x] DONE: Optimize existence check

### O4. get_the_title() and get_post_thumbnail_id() called inside review loop
- **File:** `FluentCartHelper.php` lines 312-326
- **What:** Same product name and thumbnail fetched per-review instead of once.
- **Fix:** Hoist outside loop.
- [x] DONE: Hoist invariants out of loop

### O5. injectRatingColumn() misses class-level cache
- **File:** `FluentCartTemplate.php` line 76
- **What:** Calls `get_option()` directly instead of `getAllBusinessInfoCached()`.
- **Fix:** Use the cached version.
- [x] DONE: Use cached business info

### O6. Styles enqueued on all pages
- **File:** `FluentCartTemplate.php` lines 298-315
- **What:** `enqueueStarStyles()` adds inline CSS on every page, not just FluentCart product pages.
- **Fix:** Add page-type check.
- [x] DONE: Conditionally enqueue

### O7. Magic strings for option/meta keys
- **Files:** Multiple
- **What:** Keys like `wpsr-settings-fluent-cart`, `wpsr_fluent-cart_global_settings` scattered as strings.
- **Fix:** Define as class constants in FluentCartHelper.
- [ ] TODO: Extract constants

### O8. Inconsistent getApiSettings() return structure
- **File:** `FluentCart.php` lines 85-96
- **What:** Returns `['data' => false]` while WooCommerce returns additional keys.
- **Fix:** Standardize the "no data" return.
- [ ] TODO: Standardize return shape

### O9. displayReviews() property casing inconsistency
- **File:** `FluentCartTemplate.php` line 338
- **What:** Uses `$product->id` (lowercase) while `extractProductId()` uses `$product->ID` (uppercase). Different model types have different conventions.
- **Fix:** Add defensive check: `$product->id ?? $product->ID`.
- [x] DONE: Handle both casings

### O10. Reviewer email used as reviewer_id without hashing
- **File:** `FluentCartHelper.php` line 319
- **What:** `'reviewer_email' => $reviewRecord->reviewer_id`. PII stored as-is.
- **Fix:** Ensure not exposed in public output.
- [ ] TODO: Review PII handling

### O11. error_log() in production code paths
- **File:** `ProductReviewPlatform.php` lines 244-245, 271-272
- **What:** Exception messages logged directly. May contain sensitive data.
- **Fix:** Sanitize before logging.
- [ ] TODO: Sanitize log output

---

## Recommended Fix Order

**Phase 1 -- Critical (do first):**
1. C1: syncRemoteReviews dedup for fluent-cart
2. C2: fetchProductData format mismatch
3. C3: connectAllProducts product ID casing

**Phase 2 -- High priority:**
4. H3: array_column on Collection
5. H4: stale business info on re-sync
6. H1: Missing esc_html
7. H7: selectedBusinesses append vs replace
8. H6: Exception message exposure

**Phase 3 -- Medium priority:**
9. M1: registered_post_type performance
10. M2: Shared FluentCartTemplate instance
11. M3: connectAllProducts N+1 pattern
12. M5: clearVerificationConfigs implementation
13. M10: isGlobalTemplateGettingPrecedence check
14. M9: reviewsinfo type fix

**Phase 4 -- Cleanup:**
15-37. All LOW and Optimization items in order listed above.

---

## Files Reference

| File | Plugin | Issues |
|------|--------|--------|
| `app/Services/Platforms/Reviews/BaseReview.php` | Free | C1 |
| `app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php` | Pro | C2, H3, H7, M10, M12, L5, L6, L13, O1, O2, O4, O7, O10 |
| `app/Services/Platforms/Reviews/FluentCart/FluentCart.php` | Pro | C3, O3, O8 |
| `app/Services/Platforms/Reviews/FluentCart/FluentCartTemplate.php` | Pro | H1, H5, M7, O5, O6, O9 |
| `app/Services/Platforms/Reviews/ProductReviews/ProductReviewPlatform.php` | Pro | H4, M5, M6, M11, O11 |
| `app/Http/Controllers/SettingsController.php` | Pro | H6 |
| `app/Http/Policies/SettingsPolicy.php` | Free | H2 |
| `app/Hooks/Handlers/PlatformHandlerPro.php` | Pro | M1, L4 |
| `app/Hooks/actions.php` | Pro | M2, L11, L12 |
| `app/Services/Integrations/FluentCart/SocialNinjaConnect.php` | Pro | L1, L2, L3 |
| `resources/admin/.../ReviewsPlatforms.vue` | Free | M9 |
| `resources/admin/.../FluentCartForm.vue` | Free | L7, L8 |
| `resources/admin/.../ReviewSettings.vue` | Free | L9 |
| `resources/admin/.../MultipleBusinessInfo.vue` | Free | L10 |
| `app/Hooks/Handlers/AdminMenuHandler.php` | Free | L15 |
| `app/Hooks/Handlers/UninstallHandler.php` | Free | L14 |
