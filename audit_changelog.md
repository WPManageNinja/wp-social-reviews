# FluentCart Audit -- Changes Made

Summary of all fixes applied from `audit_todo.md`, grouped by file.

---

## CRITICAL Fixes

### C1. syncRemoteReviews dedup for fluent-cart
**File:** `wp-social-reviews/app/Services/Platforms/Reviews/BaseReview.php`
**Problem:** The duplicate-detection switch had no branch for `fluent-cart`. It fell through to `reviewer_url` field with empty string value, causing all FluentCart reviews to match the same record. On re-sync, reviews were duplicated or overwritten with wrong data.
**Fix:** Added `fluent-cart` to the `review_id`-based dedup list (line 106) and added `fluent-cart` alongside `woocommerce` in the value extraction branch (line 129-130) so each review is matched by its unique `review_id`.

### C2. fetchProductData format mismatch + H3. array_column on Collection
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php`
**Problem:** `fetchProductData()` returned raw Eloquent Collection objects as `$data['reviews']`. When `formatData()` processed these, it expected keys like `review_date`, `review_text`, `review_rating` but the models have `review_time`, `reviewer_text`, `rating`. Additionally, `array_column()` on an Eloquent Collection returns empty array, so `average_rating` was always 0.
**Fix:** Replaced the raw Collection assignment with a `->map()` that transforms each model into the array format `formatData()` expects (with `review_id`, `reviewer_name`, `review_date` as Unix timestamp, `review_text`, `review_rating`, `reviewer_email`, `place_id`). Replaced `array_column()` with `$reviews->pluck('rating')->toArray()` for correct rating calculation. Also removed the dead `$reviews = [];` assignment (L6).

### C3. connectAllProducts product ID casing
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCart.php`
**Problem:** `Arr::get($product, 'ID')` used uppercase but Eloquent's `toArray()` produces lowercase `'id'`. When FluentCart models were available, `$productId` was `null` for every product, making the entire "Connect All Products" feature non-functional.
**Fix:** Changed to check both casings: `Arr::get($product, 'ID') ?: Arr::get($product, 'id')` for arrays, and `$product->ID ?? $product->id` for objects.

---

## HIGH Fixes

### H1. Missing esc_html in displayProductListRating + H5. Unescaped generateRatingIcon
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartTemplate.php`
**Problem:** Rating count in `displayProductListRating()` lacked `esc_html()` (unlike `displaySingleProductRating()` which had it). Star HTML from `generateRatingIcon()` was echoed without `wp_kses_post()`.
**Fix:** Added `esc_html()` around `number_format_i18n($total_rating)` in the list rating method. Wrapped `$stars` output in `wp_kses_post()` in both `displaySingleProductRating()` and `displayProductListRating()`.

### H4. saveBusinessInfo returns stale data on re-sync
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/ProductReviews/ProductReviewPlatform.php`
**Problem:** `saveBusinessInfo()` had an early return when business info already existed, returning old data without updating rating fields. Then `verifyCredential()` wrote this stale data back. Average rating and total count never updated after initial connection.
**Fix:** Replaced the early return with an update path: when business info exists, the method now always updates `average_rating`, `total_rating`, and `name` from the incoming data while preserving other fields.

### H6. Exception messages exposed in API responses
**File:** `wp-social-ninja-pro/app/Http/Controllers/SettingsController.php`
**Problem:** Raw `$e->getMessage()` was returned in error responses, potentially exposing DB table names, file paths, or SQL errors.
**Fix:** Now logs the full exception via `error_log()` and returns a generic user-facing message instead.

### H7. selectedBusinesses appended instead of replaced
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php`
**Problem:** `$template_meta['selectedBusinesses'][] = $product_id` appended to existing businesses. On a product page, if the template had pre-configured businesses, reviews from unrelated businesses would also show.
**Fix:** Changed to `$template_meta['selectedBusinesses'] = [$product_id]` to replace rather than append.

---

## MEDIUM Fixes

### M1. registered_post_type hook firing for every post type + L4. Commented-out code
**File:** `wp-social-ninja-pro/app/Hooks/Handlers/PlatformHandlerPro.php`
**Problem:** `add_action('registered_post_type', ...)` fires 15-30 times per page load (once per registered post type). Each call created a new FluentCart instance and re-registered all internal hooks. Also had leftover commented-out WooCommerce code.
**Fix:** Changed to `add_action('init', ..., 20)` which fires once. Removed the commented-out WooProductAdmin line.

### M2. Two FluentCartTemplate instances defeat caching + L11. Silent exception swallowing
**File:** `wp-social-ninja-pro/app/Hooks/actions.php`
**Problem:** Two separate `new FluentCartTemplate()` instances were created (one on `init`, one on `template_redirect`). Each had its own cache properties, so caching in instance 1 didn't benefit instance 2. Also, the FluentCart integration registration silently caught and discarded all exceptions.
**Fix:** Created a shared instance via `global $wpsrFluentCartTemplate` on `init`, then reused it on `template_redirect`. Added `error_log()` to the exception catch block for the integration registration.

### M3. connectAllProducts N+1 pattern
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCart.php`
**Problem:** `verifyCredential()` called in a loop with each iteration reading/writing options multiple times. The final `getBusinessInfo()` call after the loop was redundant.
**Fix:** Pre-loaded API settings before the loop. Refreshed business info after each successful connection so the skip logic stays current. Removed the redundant final `getBusinessInfo()` call.

### M4. getAllProducts with numberposts => -1
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php`
**Problem:** `numberposts => -1` loaded ALL products into memory. Could cause memory exhaustion on stores with thousands of products.
**Fix:** Added a `$limit` parameter (default 500). Both the FluentCart model path and WordPress fallback now respect the limit. Also modernized the fallback array syntax.

### M5. clearVerificationConfigs is a no-op
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/ProductReviews/ProductReviewPlatform.php`
**Problem:** Empty method body meant users could never fully disconnect FluentCart. Old settings and business info persisted.
**Fix:** Implemented the method to delete both the platform settings option and the business info option.

### M6. Race condition on business info writes
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/ProductReviews/ProductReviewPlatform.php`
**Problem:** Three write paths to the same business info option used read-modify-write without locking. Concurrent requests (e.g., cron + admin action) could lose data.
**Fix:** Added transient-based locking in `saveBusinessInfo()`. The method now acquires a lock before reading, modifying, and writing, then releases it in a `finally` block. Lock timeout is 10 seconds with a 5-second max wait.

### M7. JSON injection vector in admin column + O5. Missed cache
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartTemplate.php`
**Problem:** `$ratingsMap` keys came from option array keys without integer cast. Also used raw `get_option()` instead of the class-level cached method.
**Fix:** Cast product ID keys to `(int)` when building the ratings map. Replaced `get_option()` with `$this->getAllBusinessInfoCached()`.

### M9. reviewsinfo initialized as Array but typed as Object
**File:** `wp-social-reviews/resources/admin/components/views/platforms/reviews/ReviewsPlatforms.vue`
**Problem:** `reviewsinfo: []` but child component FluentCartForm declares `reviewsinfo: { type: Object }`. Vue emitted console warnings and the type confusion could cause subtle bugs with numeric keys.
**Fix:** Changed initialization from `[]` to `{}` in both the `data()` return and the `clearVerificationCredentials` reset.

### M10. Missing isGlobalTemplateGettingPrecedence check
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php`
**Problem:** Unlike WooCommerceHelper, FluentCartHelper always overrode template meta on product pages regardless of whether global settings or integration settings were configured.
**Fix:** Added a check for `isGlobalTemplateGettingPrecedence` or `isFluentCartIntegrationActive` before modifying template_meta, aligning with the WooCommerce pattern.

---

## LOW Fixes

### L1 & L2. Dead methods removed
**File:** `wp-social-ninja-pro/app/Services/Integrations/FluentCart/SocialNinjaConnect.php`
**What:** Removed `getProductIntegrationFeed()` and `getAllIntegrationFeeds()` -- never called anywhere, duplicated logic already in `FluentCartHelper::getFluentCartIntegrationSettings()`.

### L3. Empty processAction documented
**File:** `wp-social-ninja-pro/app/Services/Integrations/FluentCart/SocialNinjaConnect.php`
**What:** Added comment explaining why `processAction()` is intentionally empty (display-only integration, no order events).

### L7. Unused `type` data property removed
**File:** `wp-social-reviews/resources/admin/components/views/platforms/reviews/FluentCartForm.vue`
**What:** Removed `type: 'text'` from `data()` -- never referenced anywhere.

### L8. v-model on el-button removed
**File:** `wp-social-reviews/resources/admin/components/views/platforms/reviews/FluentCartForm.vue`
**What:** Removed `v-model="addNewBusiness"` from the `el-button` -- `v-model` has no effect on button elements. State is toggled via the `@click` handler.

### L9. Self-assignment removed
**File:** `wp-social-reviews/resources/admin/components/views/settings/reviews/ReviewSettings.vue`
**What:** Removed `this.reviewPlatform = val;` from the watcher -- it was a no-op (assigning the watched value back to itself).

### L12. Strict comparison for $_GET['page']
**File:** `wp-social-ninja-pro/app/Hooks/actions.php`
**What:** Changed from loose `==` to strict `===` with `sanitize_text_field(wp_unslash(...))` for WordPress coding standards compliance.

### L13. Post type validation in getEffectiveSettings
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php`
**What:** Added a check at the top of `getEffectiveSettings()` that returns defaults if the post type is not `fluent-products`, preventing settings leakage across post types.

### L15. FluentCart added to getBrandIcons
**File:** `wp-social-reviews/app/Hooks/Handlers/AdminMenuHandler.php`
**What:** Added `'fluent-cart'` entry pointing to `icon-fluent-cart-small.png` in the brand icons dictionary.

---

## Optimization Fixes

### O3. hasExistingReviews optimized
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCart.php`
**What:** Changed `Review::where(...)->count() > 0` to `->limit(1)->count() > 0` so the query stops at the first match instead of scanning all rows.

### O4. Loop-invariant lookups hoisted
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php`
**What:** Moved `get_the_title()` and `wp_get_attachment_image_src(get_post_thumbnail_id(...))` calls outside the review loop in `fetchProductDataFromWPSR()`. These values are the same for every review of the same product.

### O6. Styles only enqueued on relevant pages
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartTemplate.php`
**What:** Added a page-type check in `enqueueStarStyles()`. Inline CSS is now only added on FluentCart product pages or archives, not on every page. AJAX/REST requests are still allowed through since product modals load via REST.

### O9. displayReviews property casing defensiveness
**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartTemplate.php`
**What:** Changed `isset($product->id)` to `isset($product->id) || isset($product->ID)` with fallback `$product->id ?? $product->ID` to handle both FluentCart models (lowercase) and WP_Post objects (uppercase).

---

## Items NOT Fixed (and why)

| ID | Reason |
|----|--------|
| H2 | Route policy is shared by many endpoints. Creating a dedicated FluentCartPolicy requires architecture discussion. |
| M8 | Framework-level nonce verification -- needs JS audit, not a PHP code change. |
| M11 | Transaction wrapping would require restructuring shared parent class (`ProductReviewPlatform`) which affects WooCommerce too. |
| M12 | Covered by C2 fix (fetchProductData now returns correct format). |
| L5 | Pass-through wrappers match WooCommerceHelper pattern. Removing them would break architectural consistency. |
| L10 | `verifiedPlatform` prop is in a shared component used by many platforms. Removing could affect other callers. |
| L14 | Uninstall cleanup for `fct_product_meta` would require hooking into FluentCart's own uninstall process -- out of scope. |
| O1 | DRY refactor of fetchProductData/fetchProductDataFromWPSR is a larger refactor best done separately. |
| O2 | getEffectiveSettings array literal refactor is cosmetic -- lower priority. |
| O7 | Magic strings to constants is a codebase-wide convention change -- better as a dedicated PR. |
| O8 | getApiSettings return shape standardization affects WooCommerce pattern too. |
| O10 | Reviewer email as reviewer_id is inherited from FluentCart's data model -- not our data to hash. |
| O11 | error_log in ProductReviewPlatform is shared code used by all platforms. |
