# Native Review Form System — Technical Architecture Document

## 1. System Architecture Overview

### Goal
Build a lightweight review collection mechanism as a **Pro feature** inside WP Social Ninja that allows site owners to create simple review forms, embed them via shortcode/block, collect submissions, and moderate them — all without building a full drag-and-drop form builder.

### Key Architectural Decisions

1. **No separate submissions table** — Submissions write directly to the existing `wpsr_reviews` table with `platform_name = 'native_form'`. The `review_approved` column handles moderation (0=pending, 1=approved, 2=spam). Extra metadata (email, media, form_id, IP, consent flags) goes in the `fields` JSON column. This means approved reviews are **immediately available** in all existing templates with zero pipeline changes.

2. **Table migration in base plugin (`wp-social-reviews`)** — The `wpsr_review_forms` migrator lives alongside the existing `ReviewsMigrator` and `CacheMigrator` in `database/Migrations/`, registered in `DBMigrator::$migrators`. This follows the existing pattern where all table creation happens in the base plugin via `ActivationHandler` → `DBMigrator::run()`.

3. **Model and Policy in base plugin** — `ReviewForm` model in `app/Models/` and `ReviewFormPolicy` in `app/Http/Policies/` live in the base plugin (namespace `WPSocialReviews`). This matches the existing pattern where `CustomSourcesPolicy` is defined in the base plugin but used by Pro routes.

4. **PHP business logic in Pro plugin (`wp-social-ninja-pro`)** — Controllers, services, shortcode handler, and form renderer live in the Pro plugin (namespace `WPSocialReviewsPro`). Pro routes reference base plugin policies by short name.

5. **Vue admin UI in base plugin** — All Vue components live in `wp-social-reviews/resources/admin/`. Pro features are gated via the existing `has_pro` global property (`v-if="has_pro"`, `:disabled="!has_pro"`, `UpgradeToProButton`). This matches Custom Sources, QR Codes, Shoppable, and all other Pro-gated features.

### Design Principles (Learned from Fluent Forms)
- **JSON-driven schema** — Form structure stored as JSON, rendered server-side (like FF's `form_fields` column)
- **Pipeline-based submission** — Validate → Sanitize → Save (like FF's `SubmissionHandlerService`)
- **Hook-driven extensibility** — Lifecycle hooks at render, validate, submit, and moderate stages
- **Conditional asset loading** — JS/CSS enqueued only when a review form is present on the page
- **Honeypot + CAPTCHA spam protection** — Adopted from FF's dual-layer approach

### What We Do NOT Adopt from Fluent Forms
- No drag-and-drop form editor (fixed field set instead)
- No `entry_details` normalized table
- No multi-step wizard, conditional logic engine, or repeater fields
- No payment system
- No form analytics/view counting table
- No component class hierarchy (only 8 field types — a simple `match` is sufficient)

---

## 2. Component Structure

### Base Plugin (`wp-social-reviews/`)

```
wp-social-reviews/
├── database/
│   ├── DBMigrator.php                              // Add ReviewFormsMigrator to $migrators array
│   └── Migrations/
│       ├── ReviewsMigrator.php                     // Existing
│       ├── CacheMigrator.php                       // Existing
│       ├── ImageOptimizationMigrator.php           // Existing
│       └── ReviewFormsMigrator.php                 // NEW — CREATE TABLE wpsr_review_forms
│
├── app/
│   ├── Models/
│   │   ├── Review.php                              // Existing (used for submissions)
│   │   └── ReviewForm.php                          // NEW — wpsr_review_forms model
│   │
│   └── Http/
│       └── Policies/
│           └── ReviewFormPolicy.php                // NEW — permission guard (wpsn_manage_reviews)
│
└── resources/
    └── admin/
        ├── router/
        │   └── routes.js                           // ADD review-forms routes
        │
        └── components/views/
            └── review-forms/                       // NEW — all Vue components
                ├── Index.vue                       // Pro gate: UpgradeToProModal if !has_pro
                ├── ReviewFormsList.vue              // List forms + copy shortcode + "View Submissions" link
                └── ReviewFormEditor.vue             // Create/edit form config
```

### Pro Plugin (`wp-social-ninja-pro/`)

```
wp-social-ninja-pro/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── ReviewForms/
│   │   │       ├── ReviewFormController.php        // Admin CRUD for form configs
│   │   │       └── ReviewFormAjaxController.php    // wp_ajax entry point for public submissions
│   │   └── Routes/
│   │       └── api.php                             // Admin review-forms route group (CRUD only)
│   │
│   ├── Services/
│   │   └── ReviewForms/
│   │       ├── FormRenderer.php                    // Schema → HTML
│   │       ├── SubmissionHandler.php               // Orchestrates: validate → sanitize → upload → save
│   │       ├── SubmissionValidator.php             // Schema-aware validation rules
│   │       ├── SpamProtection.php                  // Honeypot + reCAPTCHA/Turnstile + rate limit
│   │       └── MediaUploader.php                   // Optional image upload handler
│   │
│   └── Hooks/
│       ├── actions.php                             // ReviewFormHandler registration
│       ├── filters.php                             // platform push for native_form
│       └── Handlers/
│           └── ReviewFormHandler.php               // Shortcode + AJAX hooks + asset loading
```

The compiled frontend assets (`review-form.js`, `review-form.css`) are sourced from the **base plugin** (`wp-social-reviews/resources/public/` → `assets/`) and served via `WPSOCIALREVIEWS_URL`.

---

## 3. Database Schema

### New Table: `wpsr_review_forms`

**Location:** `wp-social-reviews/database/Migrations/ReviewFormsMigrator.php`
**Namespace:** `WPSocialReviews\Database\Migrations`

```php
namespace WPSocialReviews\Database\Migrations;

class ReviewFormsMigrator
{
    static $tableName = 'wpsr_review_forms';

    public static function migrate()
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . static::$tableName;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
            $sql = "CREATE TABLE $table (
                `id`            INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title`         VARCHAR(255) NOT NULL,
                `slug`          VARCHAR(255) NOT NULL,
                `schema`        LONGTEXT NOT NULL,
                `settings`      LONGTEXT NULL,
                `status`        VARCHAR(20) DEFAULT 'active',
                `created_by`    INT(11) NULL,
                `created_at`    TIMESTAMP NULL,
                `updated_at`    TIMESTAMP NULL,
                INDEX `idx_status` (`status`),
                INDEX `idx_slug` (`slug`)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
```

**Register in `DBMigrator`:**
```php
// database/DBMigrator.php
use WPSocialReviews\Database\Migrations\ReviewFormsMigrator;

protected static $migrators = [
    ReviewsMigrator::class,
    CacheMigrator::class,
    ImageOptimizationMigrator::class,
    ReviewFormsMigrator::class,   // NEW
];
```

### Submissions → Existing `wpsr_reviews` Table

No new submissions table. Review form submissions are written directly to `wpsr_reviews`:

| `wpsr_reviews` Column     | Value for Native Form Submissions                                    |
|---------------------------|----------------------------------------------------------------------|
| `platform_name`           | `'native_form'`                                                     |
| `source_id`               | Form ID (e.g., `'5'`) — links submission to its form                |
| `review_id`               | Unique submission ID: `'nf_{form_id}_{timestamp}_{random}'`         |
| `category`                | Optional category from hidden field or form config                  |
| `review_title`            | Review title (if enabled in form)                                   |
| `reviewer_name`           | Submitter's name                                                    |
| `reviewer_url`            | Empty or source URL                                                 |
| `reviewer_img`            | Gravatar URL derived from email, or uploaded avatar                 |
| `reviewer_text`           | Review body text                                                    |
| `review_time`             | Submission timestamp                                                |
| `rating`                  | 1–5 star rating                                                     |
| `review_approved`         | `0` = pending, `1` = approved, `2` = spam                          |
| `recommendation_type`     | `'positive'` (default)                                              |
| `fields`                  | JSON (see below)                                                    |

### The `fields` JSON Column

```json
{
  "reviewer_email": "john@example.com",
  "form_id": 5,
  "media": [
    { "id": 123, "url": "https://example.com/wp-content/uploads/wp-social-ninja/reviews/photo1.jpg" },
    { "id": 124, "url": "https://example.com/wp-content/uploads/wp-social-ninja/reviews/photo2.jpg" }
  ],
  "ip_address": "192.168.1.1",
  "source_url": "https://example.com/product/widget/",
  "product_id": "42",
  "user_id": 0,
  "user_agent": "Mozilla/5.0...",
  "privacy_accepted": true,
  "content_permission": true,
  "submission_source": "native_form"
}
```

### Why This Works

- **`review_approved = 0`** already means "pending" — `filteredReviewsQuery()` only shows `review_approved = 1` on the frontend, and `RecommendationsController` already supports filtering by status (publish/unpublish/spam).
- **`source_id` = form ID** — query all submissions for a form: `Review::where('platform_name', 'native_form')->where('source_id', $formId)`
- **`fields` is already cast to JSON** in the Review model (`protected $casts = ['fields' => 'json']`) and is already a `fillable` field.
- **`getInternalBusinessInfo()`** already computes `avg_rating` and `total_rating` dynamically from the reviews table — business info for native forms works out of the box.

---

## 4. Model & Policy (Base Plugin)

### ReviewForm Model

**Location:** `wp-social-reviews/app/Models/ReviewForm.php`

```php
namespace WPSocialReviews\App\Models;

class ReviewForm extends Model
{
    protected $table = 'wpsr_review_forms';

    protected $casts = [
        'schema'   => 'json',
        'settings' => 'json'
    ];

    protected $fillable = [
        'title',
        'slug',
        'schema',
        'settings',
        'status',
        'created_by'
    ];
}
```

### ReviewFormPolicy

**Location:** `wp-social-reviews/app/Http/Policies/ReviewFormPolicy.php`

```php
namespace WPSocialReviews\App\Http\Policies;

use WPSocialReviews\Framework\Request\Request;

class ReviewFormPolicy extends BasePolicy
{
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('wpsn_manage_reviews');
    }
}
```

This matches the existing `ReviewPolicy` pattern and allows Pro routes to reference it as `->withPolicy('ReviewFormPolicy')`.

---

## 5. Form Schema Format

A form's `schema` column holds a JSON object with form-level display settings and field definitions:

```json
{
  "form_title": {
    "enabled": true,
    "text": "Leave a Review"
  },
  "form_subtitle": {
    "enabled": true,
    "text": "We'd love to hear about your experience"
  },
  "rating_icon_style": "star",

  "fields": [
    {
      "type": "text",
      "name": "reviewer_name",
      "label": "Your Name",
      "placeholder": "Your full name",
      "required": true,
      "enabled": true,
      "maxlength": 100
    },
    {
      "type": "email",
      "name": "reviewer_email",
      "label": "Email Address",
      "placeholder": "you@example.com",
      "required": true,
      "enabled": true
    },
    {
      "type": "rating",
      "name": "rating",
      "label": "Rating",
      "required": true,
      "enabled": true,
      "max_stars": 5,
      "default": 0
    },
    {
      "type": "text",
      "name": "review_title",
      "label": "Review Title",
      "placeholder": "Summarize your experience",
      "required": false,
      "enabled": true,
      "maxlength": 200
    },
    {
      "type": "textarea",
      "name": "review_text",
      "label": "Your Review",
      "placeholder": "Share your experience…",
      "required": true,
      "enabled": true,
      "maxlength": 5000,
      "rows": 5
    },
    {
      "type": "media",
      "name": "media",
      "label": "Upload Photos (optional)",
      "required": false,
      "enabled": false,
      "max_files": 3,
      "max_size_mb": 5,
      "allowed_types": ["image/jpeg", "image/png", "image/webp"]
    },
    {
      "type": "privacy_policy",
      "name": "privacy_accepted",
      "label": "I agree to the <a href=\"{privacy_url}\" target=\"_blank\">Privacy Policy</a>",
      "required": true,
      "enabled": true
    },
    {
      "type": "content_permission",
      "name": "content_permission",
      "label": "I grant permission to share my review publicly on this website",
      "required": true,
      "enabled": true
    },
    {
      "type": "hidden",
      "name": "source_url",
      "value": "{{current_url}}"
    },
    {
      "type": "hidden",
      "name": "product_id",
      "value": ""
    }
  ]
}
```

### Rating Icon Style

The `rating_icon_style` field determines how ratings are rendered on the frontend form:

| Value       | Icon                | Description                              |
|-------------|---------------------|------------------------------------------|
| `star`      | ★ ☆                | Classic 5-star rating (default)          |
| `heart`     | ♥ ♡                | Heart icons for emotional/lifestyle      |
| `emoji`     | 😠 😐 😊 😄 🤩     | 5 emoji faces (angry → love)             |
| `thumbs`    | 👍 👎              | Simple thumbs up/down (binary: 1 or 5)  |
| `number`    | 1 2 3 4 5          | Clickable number badges                  |

The existing codebase already uses `template_meta.rating_style` for display templates (`default`, `style1`, `style2`). The native form's `rating_icon_style` controls the **input widget** on the form — the stored integer 1–5 is displayed using whatever `rating_style` the template uses, via the existing `ratingIcon()` method in `WPSocialReviews.js`.

### Privacy & Content Permission Fields

- **Privacy Policy** (`privacy_policy`): Checkbox with `{privacy_url}` placeholder replaced at render time. Auto-fills from WP Settings → Privacy if no URL configured.
- **Content Permission** (`content_permission`): Checkbox for explicit consent to display review publicly.
- Both stored as `true/false` in `fields` JSON for audit purposes.
- Rendered as `<label><input type="checkbox"> Label text</label>`.

### The `settings` Column

```json
{
  "general": {
    "submit_button_text": "Submit Review",
    "success_message": "Thank you! Your review has been submitted for moderation.",
    "duplicate_message": "You have already submitted a review.",
    "require_login": false,
    "one_review_per_email": true
  },
  "privacy": {
    "privacy_policy_enabled": true,
    "privacy_policy_url": "",
    "privacy_policy_label": "I agree to the <a href=\"{privacy_url}\" target=\"_blank\">Privacy Policy</a>",
    "content_permission_enabled": true,
    "content_permission_label": "I grant permission to share my review publicly on this website"
  },
  "spam": {
    "honeypot": true,
    "captcha_type": "none",
    "captcha_site_key": "",
    "captcha_secret_key": ""
  }
}
```

---

## 6. Rendering Pipeline

### Class: `FormRenderer`

```php
namespace WPSocialReviewsPro\App\Services\ReviewForms;

use WPSocialReviews\App\Models\ReviewForm;
use WPSocialReviews\Framework\Support\Arr;

class FormRenderer
{
    public static function render(int $formId, array $atts = []): string;
}
```

### Rendering Flow

```
[wpsr_review_form id="5" product_id="42"]
        │
        ▼
ReviewFormHandler::handleShortcode($atts)
        │
        ▼
ReviewForm::find($formId)          ← Load schema + settings from DB (base plugin model)
        │
        ▼
FormRenderer::render($formId, $atts)
   ├── Render form title + subtitle (if enabled)
   ├── Parse schema JSON
   ├── Iterate fields (only enabled ones)
   │   ├── text               → <input type="text">
   │   ├── email              → <input type="email">
   │   ├── rating             → Rating widget (style-dependent: star/heart/emoji/thumbs/number)
   │   ├── textarea           → <textarea>
   │   ├── media              → <input type="file" multiple> + preview area
   │   ├── privacy_policy     → <label><input type="checkbox"> Privacy text with link</label>
   │   ├── content_permission → <label><input type="checkbox"> Content permission text</label>
   │   └── hidden             → <input type="hidden"> (smart value replacement)
   ├── Inject honeypot field (hidden, CSS-disguised)
   ├── Inject nonce field: wp_nonce_field('wpsr_review_form_' . $formId)
   ├── Inject CAPTCHA widget (if enabled)
   ├── Render submit button
   └── Wrap in <form class="wpsr-review-form" data-form-id="5" data-rating-style="star">
        │
        ▼
Enqueue Assets (conditional — only when shortcode renders)
   ├── review-form.css (from Pro plugin assets/)
   ├── review-form.js (from Pro plugin assets/)
   └── reCAPTCHA/Turnstile script (if enabled)
```

### Field Rendering (Server-Side PHP)

```php
private static function renderField(array $field, int $formId, array $schema): string
{
    if (!Arr::get($field, 'enabled', true)) {
        return '';
    }

    $html = apply_filters(
        'wpsocialreviews/review_form_render_field_' . $field['type'],
        '', $field, $formId
    );
    if ($html) return $html;

    $iconStyle = Arr::get($schema, 'rating_icon_style', 'star');

    return match ($field['type']) {
        'text'               => self::renderTextInput($field),
        'email'              => self::renderEmailInput($field),
        'rating'             => self::renderRatingWidget($field, $iconStyle),
        'textarea'           => self::renderTextarea($field),
        'media'              => self::renderMediaUpload($field),
        'privacy_policy'     => self::renderCheckbox($field, $formId),
        'content_permission' => self::renderCheckbox($field, $formId),
        'hidden'             => self::renderHiddenInput($field),
        default              => '',
    };
}
```

---

## 7. Submission Pipeline

### Endpoint

```
POST /wp-admin/admin-ajax.php
Body: action=wpsr_submit_review_form, form_id={id}, ...all form fields (multipart FormData)
```

Registered via `wp_ajax_wpsr_submit_review_form` (authenticated) and `wp_ajax_nopriv_wpsr_submit_review_form` (guests). All security (nonce, honeypot, CAPTCHA, rate-limit) is enforced inside `SubmissionHandler` — `ReviewFormAjaxController` is intentionally thin.

### Why AJAX instead of REST

- No REST authentication overhead (`wp_rest` nonce not required)
- `admin-ajax.php` is universally available including multisite sub-directories and non-standard WordPress installations
- `wp_send_json_success` / `wp_send_json_error` give a consistent `{ success, data }` envelope matching the frontend contract
- Aligns with how Fluent Forms handles public form submissions

### Flow

```
Client POST to admin-ajax.php (FormData — supports file uploads, no custom headers)
        │
        ▼
ReviewFormAjaxController::submit()
   ├── Read form_id from $_POST
   └── Build Framework Request from $_POST + $_FILES
        │
        ▼
SubmissionHandler::handle($request, $formId)
        │
        ▼
1. Load Form ─────────── ReviewForm::findOrFail($formId)    // base plugin model
        │                 Reject if status != 'active'
        ▼
2. Nonce Check ──────── wp_verify_nonce('wpsr_review_form_' . $formId)
        │
        ▼
3. Spam Check ────────── SpamProtection::check($request, $form)
        │                 • Honeypot field must be empty
        │                 • reCAPTCHA/Turnstile token verified server-side
        │                 • Rate limit: max N submissions per IP per hour
        ▼
4. Validate ──────────── SubmissionValidator::validate($request, $form->schema)
        │                 • Required fields present
        │                 • Email format
        │                 • Rating 1–5
        │                 • Text maxlength
        │                 • File MIME + size
        │                 • Duplicate check (email + source_id in wpsr_reviews)
        │                 • Privacy/content permission checkboxes checked (if required)
        ▼
5. Sanitize ──────────── Field-specific sanitization map
        │                 • sanitize_text_field() for text/title
        │                 • sanitize_email() for email
        │                 • intval() + clamp for rating
        │                 • sanitize_textarea_field() for review_text
        │                 • wp_check_filetype() for uploads
        │                 • rest_sanitize_boolean() for checkboxes
        ▼
6. Upload Media ──────── MediaUploader::handle($files, $form)
        │                 • Move to wp-content/uploads/wp-social-ninja/reviews/
        │                 • Create WP attachment (optional)
        │                 • Return array of {id, url}
        ▼
7. Determine Status ──── Uses global Review Publishing Mode via ReviewApprovalService:
        │                 • auto: review_approved = 1
        │                 • manually: review_approved = 0 (pending)
        │                 • conditional: evaluated by min rating, blocked keywords, min length
        ▼
8. Save to wpsr_reviews ── Review::create([...])   // base plugin model
        │                 Uses existing fillable fields + fields JSON
        ▼
9. Update Business Info ── Review::getInternalBusinessInfo('native_form', [...])
        │                  update_option('wpsr_reviews_native_form_business_info', $info, 'no');
        ▼
10. Fire Hooks ─────────── do_action('wpsocialreviews/review_form_submitted', $review, $form)
        │                   do_action('wpsocialreviews/custom_review_created', $review)
        ▼
11. Respond ──────────── wp_send_json_success({ success: true, message: '...' })
        │               or wp_send_json_error({ message: '...' }, $httpCode)
        │               Frontend reads body.success + body.data.message
```

### Moderation Actions

Reuses **existing** `RecommendationsController` for approval/rejection/spam since data is in `wpsr_reviews`:

- `statusUpdate()` — toggles `review_approved` between 0 and 1
- `spamReviews()` — marks as spam (`review_approved = 2`) / not spam
- `delete()` — deletes by IDs

Moderation is handled entirely through the existing `AllReviews.vue` UI — no new submission management views needed.

**Required integration change:** Add `'native_form'` to the delete whitelist in `AllReviews.vue`'s `isRowSelectable()` method so native form reviews can be bulk-deleted alongside custom, fluent_forms, testimonial, and woocommerce reviews.

---

## 8. API Routes

### Admin REST Routes (`wp-social-ninja-pro/app/Http/Routes/api.php`)

Admin CRUD routes only. The public submit endpoint is handled by WordPress AJAX, not the REST router.

```php
use WPSocialReviewsPro\App\Http\Controllers\ReviewForms\ReviewFormController;

// Admin routes — uses ReviewFormPolicy from base plugin
$router->prefix('pro/review-forms')->withPolicy('ReviewFormPolicy')->group(function ($router) {
    $router->get('/', [ReviewFormController::class, 'index']);
    $router->post('/', [ReviewFormController::class, 'store']);
    $router->get('/{id}', [ReviewFormController::class, 'show'])->int('id');
    $router->put('/{id}', [ReviewFormController::class, 'update'])->int('id');
    $router->put('/status-update', [ReviewFormController::class, 'statusUpdate']);
    $router->delete('/', [ReviewFormController::class, 'bulkDestroy']);
    $router->post('/{id}/duplicate', [ReviewFormController::class, 'duplicate'])->int('id');
});
// No public submit REST route — submissions go through wp_ajax_wpsr_submit_review_form
```

### Public Submit — WordPress AJAX

Registered in `ReviewFormHandler::register()` (runs on `init`):

```php
add_action('wp_ajax_wpsr_submit_review_form',        [new ReviewFormAjaxController(), 'submit']);
add_action('wp_ajax_nopriv_wpsr_submit_review_form', [new ReviewFormAjaxController(), 'submit']);
```

`ReviewFormAjaxController::submit()` reads `form_id` from `$_POST`, constructs a framework `Request` object from `$_POST` + `$_FILES`, and delegates entirely to `SubmissionHandler::handle()`.

---

## 9. Platform & Hook Registration (Pro Plugin)

### ReviewFormHandler

**Location:** `wp-social-ninja-pro/app/Hooks/Handlers/ReviewFormHandler.php`

```php
namespace WPSocialReviewsPro\App\Hooks\Handlers;

use WPSocialReviews\App\Models\ReviewForm;
use WPSocialReviewsPro\App\Http\Controllers\ReviewForms\ReviewFormAjaxController;
use WPSocialReviewsPro\App\Services\ReviewForms\FormRenderer;

class ReviewFormHandler
{
    public function register()
    {
        add_shortcode('wpsr_review_form', [$this, 'handleShortcode']);

        // Public submission — authenticated and guest users both handled
        $controller = [new ReviewFormAjaxController(), 'submit'];
        add_action('wp_ajax_wpsr_submit_review_form',        $controller);
        add_action('wp_ajax_nopriv_wpsr_submit_review_form', $controller);
    }

    public function pushPlatforms($platforms)
    {
        $platforms['native_form'] = __('Native Review Form', 'wp-social-ninja-pro');
        return $platforms;
    }

    public function handleShortcode($atts)
    {
        $atts = shortcode_atts([
            'id'         => 0,
            'product_id' => '',
            'css_class'  => '',
        ], $atts, 'wpsr_review_form');

        $formId = intval($atts['id']);
        if (!$formId) return '';

        // Assets live in the base plugin and are served via WPSOCIALREVIEWS_URL
        wp_enqueue_style('wpsr-review-form', WPSOCIALREVIEWS_URL . 'assets/css/review-form.css', [], WPSOCIALREVIEWS_VERSION);
        wp_enqueue_script('wpsr-review-form', WPSOCIALREVIEWS_URL . 'assets/js/review-form.js', [], WPSOCIALREVIEWS_VERSION, true);

        // No REST nonce needed — form nonce (_wpsr_nonce) is embedded in the HTML by FormRenderer.
        // uploadAction is the wp_ajax action for the pre-upload endpoint (TempFileUploader);
        // action is the wp_ajax action for the final form submission (ReviewFormAjaxController).
        wp_localize_script('wpsr-review-form', 'wpsrReviewForm', [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'action'       => 'wpsr_submit_review_form',
            'uploadAction' => 'wpsr_review_form_upload',
        ]);

        return FormRenderer::render($formId, $atts);
    }
}
```

**Why no nonce in `wp_localize_script`:** The form nonce (`_wpsr_nonce`, action `wpsr_review_form_{id}`) is injected directly into the HTML by `FormRenderer` via `wp_nonce_field()`. It travels as a standard form field inside `FormData` and is verified inside `SubmissionHandler`. There is no separate REST nonce or `X-WP-Nonce` header.

### Hook Registration in `actions.php`

Following the existing `$app->addAction/addFilter` pattern:

```php
// In wp-social-ninja-pro/app/Hooks/actions.php
(new \WPSocialReviewsPro\App\Hooks\Handlers\ReviewFormHandler())->register();
```

`ReviewFormHandler::register()` wires up two AJAX action chains:

| wp_ajax action | Handler | Purpose |
|---|---|---|
| `wpsr_submit_review_form` | `ReviewFormAjaxController::submit` | Final form submission |
| `wpsr_review_form_upload` | `TempFileUploader::upload` | Per-file pre-upload before submit |

Both actions register `wp_ajax_*` (logged-in) and `wp_ajax_nopriv_*` (guest) variants so the form works for unauthenticated visitors.

### Filter Registration in `filters.php`

```php
// In wp-social-ninja-pro/app/Hooks/filters.php
$app->addFilter('wpsocialreviews/available_valid_reviews_platforms',
    'WPSocialReviewsPro\App\Hooks\Handlers\ReviewFormHandler@pushPlatforms');
```

---

## 10. Admin UI Architecture

### Vue Router (Base Plugin)

Add to `wp-social-reviews/resources/admin/router/routes.js`:

```js
import ReviewForms from '../components/views/review-forms/Index';
import ReviewFormEditor from '../components/views/review-forms/ReviewFormEditor';

// Inside the children array of the GlobalView route:
{
    path: '/review-forms',
    name: 'review-forms',
    component: ReviewForms,
    meta: { title: 'Review Forms' }
},
// Top-level routes (outside GlobalView, like edit-template):
{
    path: '/review-forms/new',
    name: 'review-form-new',
    component: ReviewFormEditor,
    meta: { title: 'Create Review Form' }
},
{
    path: '/review-forms/:id/edit',
    name: 'review-form-edit',
    component: ReviewFormEditor,
    meta: { title: 'Edit Review Form' }
},
```

### Pro Gating Pattern

**Index.vue** follows the exact pattern used by `CustomSources.vue`:

```vue
<template>
  <div v-if="!has_pro" class="wpsr-managers-upgrade-to-pro" style="height: 100vh;">
    <img :src="assets_url + '/images/promotion/review-forms.png'" alt="">
    <div class="wpsr-managers-upgrade-message">
      <h2>Collect Reviews Directly on Your Website</h2>
      <p>Create review forms, collect submissions, and moderate reviews.</p>
      <UpgradeToProButton />
    </div>
  </div>

  <div v-if="has_pro && hasPermission(['wpsn_manage_reviews', 'wpsn_full_access'])">
    <ReviewFormsList />
  </div>
</template>
```

### ReviewFormEditor.vue

**Form Display Section:**
- **Form Title** — toggle + text input
- **Form Subtitle** — toggle + text input
- **Rating Icon Style** — visual selector with icon previews: Star ★ | Heart ♥ | Emoji 😊 | Thumbs 👍 | Number 1-5

**Fields Configuration** — toggle-based list (not drag-and-drop):
- Each field row: Enabled toggle | Label input | Placeholder input | Required toggle
- Rating field: max stars selector (3 or 5)
- Media field: max files, max size, allowed types
- Hidden fields: key-value pairs

**Privacy & Consent Section:**
- Privacy Policy: enable toggle + URL input + label text (auto-detects WP privacy page)
- Content Permission: enable toggle + label text

**Settings Tabs:**
- General: submit button text, success message, login requirement, duplicate policy
- Spam Protection: honeypot toggle, CAPTCHA type selector + keys

**Shortcode Preview:** `[wpsr_review_form id="5"]` with copy button

### Submissions Management — Uses Existing `AllReviews.vue`

No dedicated submissions UI needed. The existing `AllReviews.vue` already provides:
- **Platform filter dropdown** — `native_form` appears automatically once registered via `pushPlatforms()`
- **Status tabs** — All / Approved / Pending / Spam (via `review_approved` column)
- **Bulk actions** — Approve, Disapprove, Mark as Spam, Not Spam, Delete
- **Search** — by reviewer_name, review_title, reviewer_text
- **Pagination** — built-in

**"View Submissions" link from ReviewFormsList.vue** navigates to existing All Reviews page with pre-applied platform filter:
```
/reviews?platform=native_form
```

**Required change in `AllReviews.vue`:**
Add `'native_form'` to `isRowSelectable()` delete whitelist:
```javascript
isRowSelectable(row) {
    if (this.bulkAction === 'delete') {
        return row.platform_name === 'custom' ||
               row.platform_name === 'fluent_forms' ||
               row.platform_name === 'testimonial' ||
               row.platform_name === 'woocommerce' ||
               row.platform_name === 'native_form';  // NEW
    }
    return true;
}
```

---

## 11. Security Considerations

### Nonce Validation
- Server-rendered nonce: `wp_nonce_field('wpsr_review_form_' . $formId, '_wpsr_nonce')` — injected by `FormRenderer`, travels as a `FormData` field
- Verified in `SubmissionHandler`: `wp_verify_nonce($request->get('_wpsr_nonce'), 'wpsr_review_form_' . $formId)`
- No `X-WP-Nonce` header or REST nonce required — AJAX endpoint uses the standard form nonce only

### Honeypot
- Hidden field with CSS `display: none !important`
- Field name: `wpsr_rf_{form_id}_hn`
- Server check: reject if non-empty

### CAPTCHA Support
- reCAPTCHA v2/v3 and Cloudflare Turnstile
- Keys in form settings or global settings
- Verified server-side

### Rate Limiting
- Transient: `wpsr_rf_rate_{form_id}_{ip_hash}`
- Default: max 5 submissions per IP per hour per form

### File Upload Security
- MIME whitelist: `image/jpeg`, `image/png`, `image/webp`
- Max file size: 5MB default
- `sanitize_file_name()` + `wp_check_filetype_and_ext()`
- Stored in `wp-content/uploads/wp-social-ninja/reviews/` (uses existing `WPSOCIALREVIEWS_UPLOAD_DIR_NAME` constant)

### Input Sanitization
- Text: `sanitize_text_field()`
- Email: `sanitize_email()`
- Rating: `intval()`, clamped 1–5
- Review text: `sanitize_textarea_field()`
- Checkboxes: `rest_sanitize_boolean()`
- No raw HTML accepted

### Consent & Compliance
- Privacy acceptance and content permission stored in `fields` JSON as audit trail
- Privacy policy label rendered with `wp_kses()` allowing only `<a>` tags

---

## 12. Performance Considerations

### Conditional Asset Loading
- JS/CSS enqueued only inside shortcode callback (lazy-enqueue)
- CAPTCHA scripts only when enabled
- Zero overhead on pages without forms

### Database
- Form schema cached: `wp_cache_get('wpsr_review_form_' . $formId)`
- Submissions use existing indexes on `platform_name`, `source_id`, `review_approved`
- Admin list paginated (default per-page from `Model::getPerPage()`)

### No Extra Table Overhead
- Reusing `wpsr_reviews` avoids JOINs, double-write sync, and orphan cleanup
- `fields` JSON only decoded when viewing individual submissions in admin

### Minimal JS Footprint
- Vanilla JS — no jQuery dependency
- Rating widgets: pure CSS + minimal JS
- File upload: native `<input type="file">` with JS preview
- Estimated bundle: < 15KB gzipped

---

## 13. Hooks Reference

### Actions
```php
// Form lifecycle
do_action('wpsocialreviews/review_form_created', $form);
do_action('wpsocialreviews/review_form_updated', $form);
do_action('wpsocialreviews/review_form_deleted', $formId);

// Submission lifecycle
do_action('wpsocialreviews/review_form_before_validate', $data, $form);
do_action('wpsocialreviews/review_form_submitted', $review, $form);

// Existing hooks fired for consistency:
do_action('wpsocialreviews/custom_review_created', $review);

// Rendering
do_action('wpsocialreviews/review_form_before_render', $form);
do_action('wpsocialreviews/review_form_after_fields', $form);
```

### Filters
```php
apply_filters('wpsocialreviews/review_form_render_field_{type}', $html, $field, $formId);
apply_filters('wpsocialreviews/review_form_html_attributes', $attrs, $form);
apply_filters('wpsocialreviews/review_form_validation_rules', $rules, $form);
apply_filters('wpsocialreviews/review_form_validation_errors', $errors, $data, $form);
apply_filters('wpsocialreviews/review_form_submission_data', $reviewData, $form);
apply_filters('wpsocialreviews/review_form_spam_check', $isSpam, $data, $form);
apply_filters('wpsocialreviews/review_form_rate_limit', 5, $formId);
apply_filters('wpsocialreviews/review_form_privacy_url', $url, $formId);
```

---

## 14. Default Form Schema Template

Pre-populated when creating a new form:

```json
{
  "form_title": {
    "enabled": true,
    "text": "Leave a Review"
  },
  "form_subtitle": {
    "enabled": true,
    "text": "We'd love to hear about your experience"
  },
  "rating_icon_style": "star",
  "fields": [
    { "type": "text",               "name": "reviewer_name",     "label": "Your Name",       "placeholder": "Your full name",  "required": true,  "enabled": true,  "maxlength": 100 },
    { "type": "email",              "name": "reviewer_email",    "label": "Email Address",   "placeholder": "you@example.com", "required": true,  "enabled": true },
    { "type": "rating",             "name": "rating",            "label": "Rating",          "required": true,  "enabled": true,  "max_stars": 5, "default": 0 },
    { "type": "text",               "name": "review_title",      "label": "Review Title",    "placeholder": "Summarize your experience", "required": false, "enabled": true, "maxlength": 200 },
    { "type": "textarea",           "name": "review_text",       "label": "Your Review",     "placeholder": "Share your experience…",    "required": true,  "enabled": true, "maxlength": 5000, "rows": 5 },
    { "type": "media",              "name": "media",             "label": "Upload Photos",   "required": false, "enabled": false, "max_files": 3, "max_size_mb": 5, "allowed_types": ["image/jpeg","image/png","image/webp"] },
    { "type": "privacy_policy",     "name": "privacy_accepted",  "label": "I agree to the <a href=\"{privacy_url}\" target=\"_blank\">Privacy Policy</a>", "required": true, "enabled": true },
    { "type": "content_permission", "name": "content_permission","label": "I grant permission to share my review publicly on this website", "required": true, "enabled": true },
    { "type": "hidden",             "name": "source_url",        "value": "{{current_url}}" },
    { "type": "hidden",             "name": "product_id",        "value": "" }
  ]
}
```

---

## Summary

| Aspect | Approach |
|---|---|
| **Base plugin additions** | Migration (`database/Migrations/`), Model (`app/Models/`), Policy (`app/Http/Policies/`), Vue components (`resources/admin/`) |
| **Pro plugin additions** | Controllers, Services, Hooks/Handlers, Routes, Frontend assets |
| **New tables** | Only `wpsr_review_forms` (form config) — migrated via base plugin's `DBMigrator` |
| **Submissions storage** | Directly in existing `wpsr_reviews` with `platform_name='native_form'` |
| **Metadata storage** | `fields` JSON column (email, media, IP, form_id, consent flags, etc.) |
| **Moderation** | Existing `review_approved` column: 0=pending, 1=approved, 2=spam |
| **Field system** | Fixed set of 8 types: text, email, rating, textarea, media, privacy_policy, content_permission, hidden |
| **Rating icons** | 5 styles: star, heart, emoji, thumbs, number — form-level setting |
| **Form display** | Configurable title + subtitle above form |
| **Privacy/Consent** | Privacy policy link + content sharing permission checkboxes with audit trail |
| **Rendering** | Server-side PHP in Pro plugin, `FormRenderer::render($formId)` |
| **Submission** | Pro REST endpoint → Nonce → Spam → Validate → Sanitize → Save to `wpsr_reviews` → Fire Hooks |
| **Integration** | Approved reviews appear in existing templates when `'native_form'` platform is selected |
| **Security** | Nonce + Honeypot + CAPTCHA + Rate limit + Full sanitization + Consent validation |
| **Performance** | Conditional assets, <15KB JS, object-cached schema, reuses existing DB indexes |
| **Admin UI** | Vue 3 + Element Plus in base plugin, Pro-gated via `has_pro` flag. Submissions managed via existing `AllReviews.vue` (no new submissions views) |
| **Extensibility** | WordPress hooks at every lifecycle stage |
