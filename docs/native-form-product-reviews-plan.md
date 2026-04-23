# Native Review Forms for Product Reviews (FluentCart / WooCommerce)

## Goal

Allow native review forms to collect reviews for FluentCart and WooCommerce products, storing them with the correct `platform_name` and `source_id` so they appear in existing product review templates and update business info.

## Current State

### Native Form Submission (`SubmissionHandler.php`)
```php
'platform_name' => 'native_form',
'source_id'     => (string) $formId,
'reviewer_email' => $sanitizedEmail ?: null, // new indexed column
```
Reviews are always tied to the form, not to a product.

### DB Unique Constraint
```sql
UNIQUE INDEX uniq_platform_source_email (platform_name(20), source_id(20), reviewer_email(191))
```
Enforces one review per email per platform+source. Already scopes correctly per-product when `source_id` is a product ID.

### Fluent Forms for FluentCart (`Fluentform.php::notify()`)
```php
$platformName = Arr::get($feed, 'settings.list_id', 'fluent_forms'); // 'fluent-cart'
$sourceId = Arr::get($feedData, 'source_id', null);                  // product_id from hidden field

'platform_name' => $platformName,   // 'fluent-cart'
'source_id'     => intval($sourceId) // product_id
```
After creating the review, updates business info:
```php
update_option('wpsr_reviews_' . $platformName . '_business_info', $businessInfo, false);
```

### FluentCart Product Page Query
```sql
WHERE platform_name = 'fluent-cart' AND source_id = $product_id
```
Business info stored in `wpsr_reviews_fluent-cart_business_info` (keyed by product_id).

## Core Concept

Add a `target_platform` setting to native forms. When set to `'fluent-cart'` or `'woocommerce'`, the SubmissionHandler stores reviews with the matching `platform_name` and `source_id = product_id` — exactly how Fluent Forms does it via `list_id`.

---

## Implementation Steps

### Step 1: SubmissionHandler — Resolve target platform early, route reviews

**File:** `wp-social-ninja-pro/app/Services/ReviewForms/SubmissionHandler.php`

**Critical:** Resolve `$platformName` and `$sourceId` **before** the transient lock (step 4) and validator (step 5), since both need the correct scope. Current flow:

```
1. Load form → 2. Nonce → 3. Spam → 4. Lock → 5. Validate → ... → 9. Save
```

Insert resolution between steps 3 and 4:

```php
// 3b. Resolve target platform and source ID
$targetPlatform = Arr::get($settings, 'general.target_platform', 'native_form');
$productId = $request->get('product_id', '');

if (($targetPlatform === 'fluent-cart' || $targetPlatform === 'woocommerce') && !empty($productId)) {
    $platformName = $targetPlatform;
    $sourceId = intval($productId);
} else {
    $platformName = 'native_form';
    $sourceId = (string) $formId;
}
```

**Update transient lock** (step 4) to use resolved scope:
```php
// Before (form-scoped — two products on the same form share a lock):
$lockKey = 'wpsr_rf_lock_' . $formId . '_' . md5($email);

// After (platform+source-scoped):
$lockKey = 'wpsr_rf_lock_' . $platformName . '_' . $sourceId . '_' . md5($email);
```

**Pass resolved values to validator** (step 5):
```php
$errors = $validator->validate($request, $schema, $settings, $formId, $platformName, $sourceId);
```

Use resolved values in `$reviewData`:
```php
'platform_name' => $platformName,
'source_id'     => (string) $sourceId,
'reviewer_email' => $sanitizedEmail ?: null, // always populated for unique constraint
```

After `Review::create()`, update business info for product platforms:
```php
if ($platformName !== 'native_form' && $sourceId) {
    $dataSource = ['source_id' => $sourceId, 'handle' => get_the_title($sourceId)];
    $businessInfo = Review::getInternalBusinessInfo($platformName, $dataSource);
    update_option('wpsr_reviews_' . $platformName . '_business_info', $businessInfo, false);
}
```

> **Note:** `get_the_title($sourceId)` works for both WooCommerce products (WP posts) and FluentCart products (`fluent-products` post type — confirmed WP posts).

---

### Step 2: SubmissionValidator — Use resolved platform/source for duplicate check

**File:** `wp-social-ninja-pro/app/Services/ReviewForms/SubmissionValidator.php`

The current duplicate check hardcodes `'native_form'` and `$formId`:
```php
// Current (wrong for product reviews):
Review::where('platform_name', 'native_form')
    ->where('source_id', (string) $formId)
    ->where('reviewer_email', $email)
    ->exists();
```

Update signature and query to use resolved values:
```php
public function validate(Request $request, array $schema, array $settings, int $formId, string $platformName = 'native_form', $sourceId = null): array
{
    // ...
    $checkSourceId = $sourceId ?? (string) $formId;

    $exists = Review::where('platform_name', $platformName)
        ->where('source_id', (string) $checkSourceId)
        ->where('reviewer_email', $email)
        ->exists();
}
```

**Scope clarification:** The `one_review_per_email` form setting enables the feature, but enforcement is **per platform+source** (i.e., per product). The same email can review product A and product B through the same form — this is correct behavior.

---

### Step 3: FormRenderer — Inject product_id dynamically

**File:** `wp-social-ninja-pro/app/Services/ReviewForms/FormRenderer.php`

Accept `product_id` from shortcode attribute and inject into the hidden field:

```
[wpsr_review_form id="5" product_id="123"]
```

Before rendering fields, override the hidden `product_id` field value:
```php
$productIdOverride = Arr::get($atts, 'product_id', '');
if (!$productIdOverride) {
    $productIdOverride = self::detectProductId();
}
if ($productIdOverride) {
    foreach ($fields as &$field) {
        if ($field['name'] === 'product_id' && $field['type'] === 'hidden') {
            $field['value'] = $productIdOverride;
        }
    }
    unset($field);
}
```

Auto-detection for product pages (when shortcode attribute is empty):
```php
private static function detectProductId(): string
{
    // FluentCart (post type: fluent-products)
    if (defined('FLUENTCART_VERSION')) {
        global $post;
        if ($post && $post->post_type === 'fluent-products') {
            return (string) $post->ID;
        }
    }
    // WooCommerce
    if (function_exists('is_product') && is_product()) {
        global $post;
        return (string) $post->ID;
    }
    return '';
}
```

---

### Step 4: Form Editor UI — Add target_platform setting

**File:** `wp-social-reviews/resources/admin/components/views/review-forms/ReviewFormEditor.vue`

Add `target_platform` to default settings:
```js
general: {
    // ...existing...
    target_platform: 'native_form', // 'native_form' | 'fluent-cart' | 'woocommerce'
}
```

Add dropdown in General Settings section:
```vue
<el-select v-model="settings.general.target_platform" size="small">
    <el-option value="native_form" label="Native Form (Generic)" />
    <el-option v-if="hasFluentCart" value="fluent-cart" label="Fluent Cart Product" />
    <el-option v-if="hasWooCommerce" value="woocommerce" label="WooCommerce Product" />
</el-select>
```

Show options conditionally based on `hasFluentCart` / `hasWooCommerce` flags from admin vars.

---

### Step 5: Admin vars — Pass platform availability

**File:** `wp-social-ninja-pro/app/Hooks/Handlers/ReviewsTemplateHandlerPro.php`

Add to `adminAppVars()`:
```php
$vars['hasFluentCart'] = defined('FLUENTCART_VERSION');
$vars['hasWooCommerce'] = defined('WC_VERSION');
```

---

### Step 6: FluentCart drawer — Render native form

**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartTemplate.php`

In `renderReviewDrawer()`, check if native form is selected:
```php
$reviewsForm = Arr::get($effectiveSettings, 'reviews_form', 'fluent-cart');
$nativeFormId = Arr::get($globalSettings, 'native_form_id', 0);

if ($reviewsForm === 'native_form' && $nativeFormId) {
    echo '<div class="wpsr-fcd-form-container">';
    echo do_shortcode('[wpsr_review_form id="' . intval($nativeFormId) . '" product_id="' . esc_attr($product_id) . '"]');
    echo '</div>';
}
```

**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/FluentCart/FluentCartHelper.php`

Add to default settings:
```php
'reviews_form'   => 'fluent-cart',  // 'fluent-cart' | 'native_form' | 'none'
'native_form_id' => 0,             // ID from wpsr_review_forms table
```

---

### Step 7: Fix business info name resolution

**File:** `wp-social-ninja-pro/app/Hooks/Handlers/ReviewsTemplateHandlerPro.php`

In `updateBusinessInfo()`, handle `fluent-cart` alongside `woocommerce`:
```php
$name = '';
if ($platformName === 'woocommerce' && defined('WC_VERSION')) {
    $name = get_the_title($sourceId);
} elseif ($platformName === 'fluent-cart' && defined('FLUENTCART_VERSION')) {
    $name = get_the_title($sourceId);
}
```

---

### Step 8: WooCommerce parity (same pattern)

**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/WooCommerce/WooProductAdmin.php`
- Add `'native_form'` option to review form selector

**File:** `wp-social-ninja-pro/app/Services/Platforms/Reviews/WooCommerce/WooProductTemplate.php`
- Render native form when `reviews_form = 'native_form'`

---

### Step 9: Edge cases

- **Missing product_id:** If `target_platform` is product-based but `product_id` is empty, fall back to `native_form` behavior (don't error out) — already handled by the `!empty($productId)` check in Step 1
- **Invalid product:** Validate `product_id` is a real post of the correct type before storing
- **Duplicate check:** Uses resolved `$platformName + $sourceId + reviewer_email` (not form-scoped) — same email can review different products through the same form, which is correct
- **No product_id hidden field:** If form schema has no `product_id` hidden field but `target_platform` is a product platform, auto-detect from page context (Step 3's `detectProductId()`)
- **reviewer_email column:** Always populated regardless of `target_platform`, so the DB unique constraint works for all platforms without extra logic

---

## Implementation Order

| Priority | Files | What |
|----------|-------|------|
| 1 | `SubmissionHandler.php` + `SubmissionValidator.php` + `FormRenderer.php` | Core routing + validation + product_id injection — testable with `[wpsr_review_form id="5" product_id="123"]` |
| 2 | `ReviewFormEditor.vue` | Admin UI for `target_platform` dropdown |
| 3 | `ReviewsTemplateHandlerPro.php` | Business info fix + admin vars (`hasFluentCart`, `hasWooCommerce`) |
| 4 | `FluentCartTemplate.php` + `FluentCartHelper.php` | Drawer integration — render native form with product context |
| 5 | `WooProductAdmin.php` + `WooProductTemplate.php` | WooCommerce parity |

---

## File Summary

| File | Plugin | Change |
|------|--------|--------|
| `SubmissionHandler.php` | pro | Resolve target platform early (before lock/validate); route `platform_name`/`source_id`; scope transient lock by platform+source; update business info |
| `SubmissionValidator.php` | pro | Accept resolved `$platformName`/`$sourceId`; use them in duplicate check instead of hardcoded `native_form`/`$formId` |
| `FormRenderer.php` | pro | Inject `product_id` from shortcode attr or auto-detect from page |
| `ReviewFormEditor.vue` | free | Add `target_platform` dropdown in General Settings |
| `ReviewsTemplateHandlerPro.php` | pro | Fix `updateBusinessInfo` for fluent-cart; add admin vars |
| `FluentCartTemplate.php` | pro | Render native form in drawer when configured |
| `FluentCartHelper.php` | pro | Add `native_form_id` to default settings |
| `WooProductAdmin.php` | pro | Add `native_form` option |
| `WooProductTemplate.php` | pro | Render native form when selected |
