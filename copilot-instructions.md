# WPSR Coding Standards & Naming Conventions

## Naming and Classname Prefix Instructions

All classnames, IDs, and any generated or suggested names in this project must always begin with the prefix `wpsr-`.

### Examples:
- **Classnames:** `wpsr-button`, `wpsr-header`, `wpsr-card-title`
- **Variable names:** `wpsr_init`, `wpsr_enqueue_styles`, `$wpsr_product_id`
- **Function names:** `wpsr_process_order()`, `wpsr_get_product()`

### Reason:
The `wpsr-` prefix is required to prevent naming conflicts with WordPress core files, themes, and other plugins.

### Core Rules:
- ✅ Do not use any class, ID, variable, or function name without the `wpsr-` prefix
- ✅ This applies to all CSS, HTML, JavaScript, and PHP code
- ✅ Always check that any new name or classname starts with `wpsr-`

---

## Validation Rules for IDs and Variables

**Always validate before using IDs or variables to prevent runtime errors.**

### PHP Validation Examples

#### ✅ In Loops (foreach, for, while)

**❌ WRONG - No validation:**
```php
foreach ($wpsr_product_ids as $wpsr_product_id) {
    (new WooCommerce())->verifyCredential($wpsr_product_id);
}
```

**✅ CORRECT - Validate and skip empty values:**
```php
foreach ($wpsr_product_ids as $wpsr_product_id) {
    if ($wpsr_product_id === '' || $wpsr_product_id === null || empty($wpsr_product_id)) {
        continue;
    }
    (new WooCommerce())->verifyCredential($wpsr_product_id);
}
```

**✅ ALTERNATIVE - More concise:**
```php
foreach ($wpsr_product_ids as $wpsr_product_id) {
    if (empty($wpsr_product_id)) {
        continue;
    }
    (new WooCommerce())->verifyCredential($wpsr_product_id);
}
```

#### ✅ In Functions

**Basic validation:**
```php
function wpsr_process_order($wpsr_order_id) {
    if (empty($wpsr_order_id)) {
        return;
    }
    // Process logic here
}
```

**With type validation and return value:**
```php
function wpsr_get_product($wpsr_product_id) {
    if (!isset($wpsr_product_id) || empty($wpsr_product_id)) {
        return false;
    }
    // Fetch product logic
    return $wpsr_product;
}
```

#### ✅ Before Database Queries

```php
// Always validate before querying
if (empty($wpsr_post_id)) {
    return;
}
$wpsr_post = get_post($wpsr_post_id);
```

### JavaScript Validation Examples

#### ✅ In Loops

**❌ WRONG - No validation:**
```javascript
wpsrProductIds.forEach(wpsrProductId => {
    wpsrVerifyCredential(wpsrProductId);
});
```

**✅ CORRECT - Validate and skip empty values:**
```javascript
wpsrProductIds.forEach(wpsrProductId => {
    if (!wpsrProductId || wpsrProductId === '' || wpsrProductId === null) {
        return;
    }
    wpsrVerifyCredential(wpsrProductId);
});
```

#### ✅ In Functions

```javascript
function wpsrProcessItem(wpsrItemId) {
    if (!wpsrItemId) {
        return;
    }
    // Process logic here
}
```

---

## Mandatory Validation Rules

1. **In loops**: Always validate each iteration variable before using it
   - Use `continue` (PHP) to skip invalid values in loops
   - Use `return` (JavaScript) to skip invalid values in array methods

2. **In functions**: Check if an ID or critical variable is empty at the start of the function

3. **Before operations**: Validate before passing IDs to:
   - API calls
   - Database queries
   - External functions or class methods

4. **Use appropriate checks**:
   - **PHP**: `empty()`, or explicit `=== ''`, `=== null` checks
   - **JavaScript**: `!variable`, `=== ''`, `=== null`, `=== undefined`

5. **Return early**: Exit function/iteration if the ID/variable is empty or invalid

6. **Never assume**: Don't assume an ID will always have a value, even in arrays or collections

---

## Common Error Scenarios to Prevent

- ❌ Empty strings in product/post/user ID arrays
- ❌ Null values passed to API functions
- ❌ Undefined variables in JavaScript event handlers
- ❌ Database queries with empty IDs
- ❌ Processing loop items without validation

---

## Real-World Example

### The Problem:
```php
// This throws an error when $productId is empty
foreach ($uniqueProductIds as $productId) {
    (new WooCommerce())->verifyCredential($productId);
}
```

### The Solution:
```php
// This safely skips empty values
foreach ($uniqueProductIds as $productId) {
    if ($productId === '' || $productId === null || empty($productId)) {
        continue;
    }
    (new WooCommerce())->verifyCredential($productId);
}
```

---

## Summary Checklist

Before committing code, verify:

- [ ] All classnames, IDs, variables, and functions start with `wpsr-` or `wpsr_`
- [ ] All loop iterations validate the variable before use
- [ ] All functions validate input parameters at the start
- [ ] All database queries validate IDs before execution
- [ ] All API calls validate parameters before sending
- [ ] No assumptions about variables always having values

---

**Strictly follow these conventions throughout the codebase.**