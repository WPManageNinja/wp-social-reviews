# Parked foundation findings

Known production defects stay parked only after active probes reproduce exact
fingerprints against Docker MariaDB with non-default prefix. Unexpected success or
a different failure turns red. This PR does not edit production code. Registry IDs
are stable remediation keys; unpark only in a separate production-fix PR.


## Fixed foundation findings

### <a id="integration-optimize-image-platform-owner"></a>`integration-optimize-image-platform-owner` — FIXED

`OptimizeImage::updateData()` matched rows on `media_id` and `user_name` alone. Two
platforms can legitimately carry the same media id for the same user name, so an
Instagram write claimed an existing YouTube row — flipping its `platform` column and
discarding the YouTube record rather than storing both.

Evidence: `app/Models/OptimizeImage.php` — the `updateOrCreate()` identity includes
`platform` when the payload carries one, which the only production caller
(`BaseImageOptimizationHandler`) always does. No migration is needed: existing rows
already store their platform, so a same-platform write still matches its own row and
only cross-platform writes now get a row of their own.
`tests/integration/shared-table-discriminators.php` — unparked to assert both rows
survive, ordered `['youtube', 'instagram']`.

Note: `deleteMedia()`, `getMediaIds()` and `deleteMediaByUserName()` remain
platform-agnostic. They were outside this item and are not covered by its test.

### <a id="integration-google-refresh-retry-cursor"></a>`integration-google-refresh-retry-cursor` — FIXED

`GoogleMyBusiness::doCronEvent()` called `createCache()` at the end of every expired-cache
iteration, outside the `if ($apiKey)` guard that wraps the refresh itself. A business the
cron could not refresh — no access token, so not a single request — still had its cache
expiration pushed into the future, and the next run skipped it as fresh. The cache only
became eligible again after the full expiry window, so a transient token problem silently
suppressed refreshes long after it resolved.

Evidence: `app/Services/Platforms/Reviews/GoogleMyBusiness.php` — the `createCache()` call
moves inside the `if ($apiKey)` block, so the cursor advances only once a refresh has been
attempted. `app/Services/Platforms/Reviews/BaseReview.php` — `doCronEvent()` carried the
same shape for platforms that do not override it and is gated the same way on an
`$attempted` flag. Only the Google override is covered by the test; the base change is the
same defect in the same method, fixed by inspection.
`tests/integration/background-automation.php` — unparked to assertions that an
unrefreshable business performs no request, leaves its expiration byte-identical, and stays
expired so the next cron retries.

### <a id="integration-selected-business-count"></a>`integration-selected-business-count` — FIXED

`Helper::getSelectedBusinessInfoByPlatforms()` reads the all-business total into `$cnt`
from `getBusinessInfoByPlatforms()`, then increments it once per selected business.
Selecting one business out of two therefore reported three. The inflated count also
defeated the `$cnt === 1` branch that assigns `url` and `platform_name` for a
single-business summary.

Evidence: `app/Services/Platforms/Reviews/Helper.php` — `$cnt` resets to zero on entering
the selected-business branch, so the loop counts only what it filters. The all-business
total is still what the function returns when nothing is selected and the loop never runs.
`tests/integration/reports-and-aggregates.php` — unparked to assertions that one selection
out of two counts as one and aggregates only that business, with a positive control that
selecting both counts two and sums both ratings.

### <a id="integration-instagram-refresh-option-shape"></a>`integration-instagram-refresh-option-shape` — FIXED

`RefreshToken::getAccessToken()` wrote
`update_option($this->optionKey, array('connected_accounts' => $accounts, 'no'))`. The
`'no'` autoload argument was typed inside the array literal instead of as
`update_option()`'s third parameter, so every weekly refresh persisted a stray element
`0 => 'no'` alongside the accounts, and the no-autoload intent this project requires was
silently lost.

Evidence: `app/Services/Platforms/Feeds/Instagram/RefreshToken.php` — the closing
parenthesis moves so `'no'` becomes the third argument.
`tests/integration/background-automation.php` — unparked to assertions that the saved
option holds only `connected_accounts`, that the accounts survive as an array, that the
single token request still happens, and that the row is stored without autoload. The
autoload assertion accepts both `no` and `off` because WordPress 6.6 renamed the stored
values.

### <a id="integration-negative-page-id"></a>`integration-negative-page-id` — FIXED

`PagesController::index()` normalised `include_ids` with `array_map('absint', ...)`,
which maps `"-12"` onto post `12`. A negative id therefore resolved to a real object
instead of being rejected.

Evidence: `app/Http/Controllers/PagesController.php` — ids are cast with `intval()` and
anything not strictly positive is dropped. Filtering alone was not enough: an emptied
include list falls through to the general listing in `Helper::searchPagesList()`
(`:472` only honours the resolve path when the list is non-empty), so a request that
supplied only invalid ids returned every page plus the `Everywhere` row. The controller
now answers with an empty result in that case, matched to the same conditions the helper
uses — no search term, first page.
`tests/integration/input-validation-authorization.php` — unparked to assertions that a
negative id resolves to nothing, that a positive id still resolves, and that mixed input
keeps only the real id while dropping zero, negative and non-numeric tokens.

### <a id="integration-shoppable-delete-sanitization"></a>`integration-shoppable-delete-sanitization` — FIXED

`ShoppablesController::delete()` writes its `settings` payload straight to
`wpsr_global_shoppable_settings`. The payload is a client-supplied replacement for the
whole option, not a list of ids to remove, so markup and a `javascript:` URL persisted
verbatim into settings a public shoppable widget reads — while `update()` had run the
same shape through `recursive_sanitize()` all along.

Evidence: `app/Http/Controllers/ShoppablesController.php` — `delete()` now sanitizes
through the same method. It deliberately passes no required-field rules: `update()` uses
`['hashtags' => 'required']`, but adding a throw path to a destructive endpoint would
reject stored shapes that predate the rule. `tests/integration/input-validation-authorization.php`
— unparked to assertions that markup is stripped and a `javascript:` URL is rejected to
an empty string, plus a positive control that clean hashtags and a valid URL survive
unchanged and an emptied platform stays emptied.

### <a id="pure-plaintext-encryption-diagnostic"></a>`pure-plaintext-encryption-diagnostic` — FIXED

`DataProtector::maybe_encrypt()` decides whether a value is already encrypted by running
it through `decrypt()`. A plaintext token such as `access` is itself valid base64, so it
decoded to four bytes; `substr($raw, 0, 16)` then handed `openssl_decrypt()` a four-byte
IV and PHP warned `IV passed is only 4 bytes long ... padding with \0` on every
plaintext token encrypted.

Evidence: `app/Services/DataProtector.php` — `decrypt()` returns `false` when the base64
decode fails or yields no more bytes than the IV length. `encrypt()` emits
`base64( iv . ciphertext )`, so a genuine payload is always strictly longer than the IV
and no real value is newly rejected. Return values are unchanged throughout: plaintext
still resolves to `false`, `maybe_encrypt()` remains idempotent on an existing payload,
and the token helpers still leave empty and unlisted keys untouched without mutating
their caller's array. `tests/pure/deterministic-helpers.php` — unparked with the
round-trip, idempotency, non-mutation and undersized-candidate cases asserted directly.

### <a id="pure-css-functional-color-ranges"></a>`pure-css-functional-color-ranges` — FIXED

`wpsr_sanitize_color()` matched functional colours by shape only: `\d{1,3}` accepts
`999` and the alpha group accepts `1.5`, so `rgb(999, 0, 0)` and `rgba(0, 0, 0, 1.5)`
were returned verbatim as sanitized output.

Evidence: `boot/globals.php` — after the shape match the channels are range-checked
(0–255 for rgb, 0–360 then 0–100 for hsl, 0–100 for any percentage channel, 0–1 for
alpha) and an out-of-range colour returns the empty string like any other rejection.
The hex, named-colour and rejection branches are untouched, and the helper still uses
only PHP builtins as `boot/globals.php` requires.
`tests/pure/deterministic-helpers.php` — unparked to a table covering four out-of-range
forms and six in-range positive controls, plus hex, named and hostile input.

### <a id="pure-compact-number-carry"></a>`pure-compact-number-carry` — FIXED

`Helper::shortNumberFormat()` picked the unit by dividing while the value was at least
1000, then rounded to one decimal afterwards. A value just under the next threshold
rounded back up, so `999,999` rendered as `1,000K` instead of `1M` and `999,999,999`
as `1,000M`.

Evidence: `app/Services/Helper.php` — the rounded value is re-checked and carried into
the next unit. The loop is also bounded by the unit list, so a value beyond `T` clamps
instead of reading past the end of the array. `tests/pure/deterministic-helpers.php` —
unparked to a table covering both carry cases, an exact threshold, a sub-threshold
value, a retained decimal, a trimmed trailing zero, and the largest named unit.

### <a id="pure-airbnb-integer-parser"></a>`pure-airbnb-integer-parser` — FIXED

`Airbnb::validateAndCleanNumericInput()` matched only separator-bearing forms — a
decimal point or a trailing comma — so a bare integer such as `"123"` fell through to
the `else` branch and was rejected as unparseable.

Evidence: `app/Services/Platforms/Reviews/Airbnb.php` — a bare-integer branch runs
before the separator patterns and returns the value as an `int`. The separator forms
are untouched. `tests/pure/deterministic-helpers.php` — unparked to a table covering
bare integers and decimals, both trailing-comma forms, non-numeric and empty input,
plus an explicit type assertion.

Note: `"0"` still returns null because the guard is `empty($str)`. Widening that guard
would change the contract for every falsy input, so it stays out of this item.

### <a id="pure-facebook-malformed-identifiers"></a>`pure-facebook-malformed-identifiers` — FIXED

`getAlbumId()` and `validateAndRetrieveSingleVideoPlaylistId()` compared
`$url['host']` straight after `wp_parse_url()`. A non-URL parses to an array with no
host key, so every malformed identifier raised `Undefined array key "host"` twice per
call before returning its rejection value.

Evidence: `app/Services/Platforms/Feeds/Facebook/Helper.php` — both read the host
through a null-coalescing guard. Return values are unchanged: malformed input still
yields `null` and `false` respectively, and valid album and watch URLs still yield
their ids. `tests/pure/deterministic-helpers.php` — unparked to tables covering
malformed, empty, numeric and well-formed inputs for both parsers.

### <a id="pure-facebook-cache-date-type"></a>`pure-facebook-cache-date-type` — FIXED

`FacebookHelper::normalizeDateForCache()` is documented `@return int`, but every branch
returned `floor(...) * N`, and `floor()` yields a float. Cache keys built from it carried
a float where an integer was promised.

Evidence: `app/Services/Platforms/Feeds/Facebook/Helper.php` — all four return paths cast
to `(int)`. Values are unchanged, so existing cache keys stay stable.
`tests/pure/deterministic-helpers.php` — unparked; the absolute date asserts both exact
value and type, and the three clock-dependent branches assert the type contract.

### <a id="pure-facebook-positive-start-date"></a>`pure-facebook-positive-start-date` — FIXED

`FacebookHelper::fixRelativeDateInput()` documents that a bare number means past days
("50" becomes "-50 days"), but its bare-number branch appended the unit without the
sign, so a positive start value selected a window 50 days into the future instead of
50 days back. The sign is now added for every context except `end`, where a positive
number may legitimately be a future event — the same exception the later
unit-bearing branch already made.

Evidence: `app/Services/Platforms/Feeds/Facebook/Helper.php`.
`tests/pure/deterministic-helpers.php` — unparked to a table covering bare positive and
negative values in both contexts, an explicit `+3 hours` end date, an already-united
input, and the `now` short circuit.

### <a id="pure-reviewer-unicode-initials"></a>`pure-reviewer-unicode-initials` — FIXED

`Helper::handleReviewerName()` built initials with `strtoupper(substr($name, 0, 1))`.
`substr()` counts bytes, so the first byte of a multibyte character was taken alone:
`Élodie Ångström` rendered as a pair of stray lead bytes rather than `É. Å.`, and the
same for CJK names. This is visible output — any site showing non-ASCII reviewer names
in initials form was rendering broken characters in the public widget.

Evidence: `app/Services/Platforms/Reviews/Helper.php` — new private `nameInitial()` cuts
with `mb_substr()` and uppercases with `mb_strtoupper()`, falling back to a
`preg_match('/^./us', …)` character cut plus ASCII `strtoupper()` when mbstring is
absent, matching the fallback style `sanitizeVisitorSearch()` already uses. Applied to
both `initials-only` and `first-name-last-initial`, which shared the defect.
`tests/pure/deterministic-helpers.php` — unparked to active assertions for the accented
and CJK cases, with positive controls that plain ASCII names still uppercase correctly
and that the formatted output is valid UTF-8.

### <a id="pure-malformed-visitor-utf8"></a>`pure-malformed-visitor-utf8` — FIXED

`Helper::sanitizeVisitorSearch()` already retried its whitespace collapse without the
`/u` modifier when `preg_replace()` returned null on invalid UTF-8, but nothing removed
the offending bytes, so a malformed term reached the caller still malformed.

Evidence: `app/Services/Platforms/Reviews/Helper.php` — the term passes through
`wp_check_invalid_utf8($term, true)` before any other processing, so every path below it
operates on valid UTF-8. The substitution character that pass introduces is then removed
rather than searched for: leaving `U+FFFD` in a search term would guarantee zero matches,
whereas dropping it lets the characters the visitor did type still match.
`tests/pure/deterministic-helpers.php` — unparked to assertions that the invalid byte is
dropped and the result passes `mb_check_encoding()`, with positive controls that valid
multibyte input survives intact and truncation still counts characters, not bytes.

Registry rows removed from `tests/skips/registry.json` and the default active
known-failure inventory dropped 16 → 14 in `tests/skips/skip-lib.php`.

### <a id="integration-admin-like-wildcards"></a>`integration-admin-like-wildcards` — FIXED

`SearchableScope::scopeSearchBy()` interpolated the raw term straight into its LIKE
patterns (`"%$search%"`), so an admin searching `%` or `_` matched every row instead of
the rows containing those characters. Binding the value prevents SQL injection but not
wildcard expansion, which is a separate escape. `Post::getPosts()` had the same defect on
its sibling `filter` argument, which is also free request text
(`sanitize_text_field($request->get('filter'))`) and reaches the same predicate.

Evidence: `app/Models/Traits/SearchableScope.php` — new `escapeLikeTerm()` applies
`$wpdb->esc_like()` (falling back to `addcslashes($term, '_%\\')` outside WordPress)
and the scope builds one escaped pattern for every searchable column.
`app/Models/Post.php` — `getPosts()` escapes `$filter` through the same helper. The trait
is shared by `Notification`, `Widget`, `Template`, `Post` and `Review`, so all five admin
tables are covered by the one change.
`tests/integration/input-validation-authorization.php` — `known-admin-like-gap` unparked
to active assertions: `%` matches only the row containing a literal percent, `_` matches
nothing, plain-text and shared-prefix searches still resolve their exact rows, and the
`filter` parameter is checked on `/templates` as well. Registry row removed from
`tests/skips/registry.json` and the default active known-failure inventory dropped
16 → 15 in `tests/skips/skip-lib.php`.

Note: `Review::prepareReviewFilterLikeWord()` already escaped the visitor-facing search,
but via a dead `function_exists('esc_like')` branch — WordPress has no such global, so it
always fell through to its `addcslashes` fallback. The fallback is correct, so that path
is left alone; the new helper prefers `$wpdb->esc_like()` where it exists.

### <a id="public-facebook-album-guard"></a>`public-facebook-album-guard` — FIXED

`FacebookFeedTemplateHandler::handleAlbumPhoto()` was registered on
`wp_ajax_nopriv_wpsr_facebook_album_photo` but its whole body was
`do_action('wpsocialreviews/facebook_feed_handle_album_photo'); die();`, so any
unauthenticated request dispatched the extension listener with no nonce, no template
check and no input validation. Core now verifies the frontend nonce and that the
requested template is publicly renderable before dispatching, matching the guard
`YoutubeTemplateHandler::renderPopupFeed()` already uses.

Evidence: `app/Hooks/Handlers/FacebookFeedTemplateHandler.php` — `check_ajax_referer('wpsr-ajax-nonce', 'security', false)`
then `ShortcodeHandler::isPublicRenderableTemplate()` on the request's `template_id`,
both answering `403` before `do_action()`. `tests/lib/public-isolated-probe.php` gains
`facebook-album-allowed` and `facebook-album-hidden` modes and mints its own nonce as
user 0, since nonces are user-scoped and the parent suite runs as an administrator.
`tests/public/public-handlers.php` — the parked case becomes active assertions covering
the no-nonce denial, the valid-nonce/draft-template denial, and a positive control that
a published template still dispatches exactly once. Parking removed from
`tests/skips/registry.json` and from the `wpsr.php.public-album-handler-without-guard`
entry in `tests/security/semgrep/quality-policy.json`.

Note: the Pro listener `FacebookTemplateHandlerPro::handleAlbumPhoto()` already performed
both checks, so no released Pro behavior changes; this closes the gap for core-alone
installs and for any other extension hooking the same action.

### <a id="integration-settings-policy-scope"></a>`integration-settings-policy-scope` — FIXED

`SettingsPolicy` ORs seven feature capabilities for the whole `/settings` group, so
holding any one of them reached every other settings screen's routes — a
translation-settings manager could rewrite advanced settings, manage the licence, or
wipe all platform data. `SettingsController` now names, per action, the capabilities
whose screens actually call it, and refuses anything else with `403`.

The policy itself is unchanged on purpose: Pro gates its entire `pro/*` group on this
same class (`wp-social-ninja-pro/app/Http/Routes/api.php:17`), so narrowing
`verifyRequest()` would reach into released Pro. Pro already solved the same problem
this way in `ManagersController::authorizeFullAccess()`.

Evidence: `app/Http/Controllers/SettingsController.php` — `$screenCapabilities` plus
`denyUnlessScreenAllowed()`, applied to twitter cards, advanced settings,
translations, licence and delete-all-data. The generic platform routes (`index()`,
`update()`, `delete()`, `resetData()`) pick their target from the request, so
`platformCapabilities()` resolves the sanitized `platform` to its owning feed or review
capability and `denyUnlessPlatformAllowed()` refuses unknown identifiers before a hook
name is built from them. `saveAdvanceSettings()` reaches image reset
through the new unguarded `performResetData()` so its internal call is not re-authorized.
`app/Services/PermissionManager.php` — `wpsn_feeds_advance_settings` added to
`getReadablePermissions()`; it was in `pluginPermissions()` only, so the capability the
advanced-settings screen requires could never be granted in Pro's Managers UI.
`resources/admin/components/views/settings/advance/Advance.vue` — the Delete All
control now renders only for `wpsn_full_access`, matching the server.
`tests/integration/input-validation-authorization.php` — `known-settings-policy-gap`
unparked to active assertions covering the denial, the denied write leaving no option,
the refused user's own screen still working, and both owning capabilities still saving.

Note: `/settings/advance-settings` is posted by two screens — `Advance.vue`
(`wpsn_feeds_advance_settings`) and `GlobalReviewSettings.vue:457`
(`wpsn_reviews_platforms_settings`) — so it is scoped to both. Gating it on one would
have silently broken Global Reviews Settings. The aggregate `reviews` identifier is
owned by `wpsn_reviews_platforms_settings` alone: `GlobalFeedSettings.vue` also posted
it, but that `resetData()` was unreachable dead code — no control was ever bound to it —
and `platform === 'reviews'` routes to `review_reset_data`, so had it been wired the
feed screen would have reset review images. It is removed rather than re-pointed.
`resetErrorLog()` stays on the group gate because `ErrorNotice.vue` renders on every
screen.

### <a id="integration-template-object-scope"></a>`integration-template-object-scope` — FIXED

`TemplatePolicy` gated the whole `/templates` route group on `wpsn_manage_templates`,
but `delete()` and `updateTitle()` accepted any post ID whose type appeared in
`$managedPostTypes` — a list that also covers chat widgets, notification popups and
custom sources. A templates-only user could permanently delete or rename another
screen's objects. `$managedPostTypes` filtered by type, never by permission.
`TemplatesController` now maps each managed post type to the capability owning its
screen and checks it per object before either destructive path runs.

Evidence: `app/Http/Controllers/TemplatesController.php` — `$managedPostTypeCapabilities`
plus `userCanManageObject()`, applied in `delete()` (skips unauthorized IDs, so they
never reach `deletePost()` or the deleted count) and `updateTitle()` (403 before the
post is touched). `tests/integration/input-validation-authorization.php` —
`known-template-object-gap` unparked from `WpsrTest::parkKnownFailure()` to active
assertions: post row and postmeta both survive byte-identical, plus a positive control
(templates-only user still deletes its own template) and an allow case (holding both
capabilities does delete the chat widget). Registry row removed from
`tests/skips/registry.json` and the default active known-failure inventory dropped
18 → 17 in `tests/skips/skip-lib.php`. Static gate:
`SKIP-LINT registered=24 declared=24 default=17 failures=0`.

Note: `duplicate()` shares the route group and also accepts cross-family IDs, but it
is non-destructive and stays out of scope for this item.

### <a id="throughput-bulk-status-batch-limit"></a>`throughput-bulk-status-batch-limit` — FIXED

`scopeReviewIds()` already chunked the ownership lookup, but `applyStatusUpdate()` then
rebuilt the full id list for its `UPDATE ... WHERE id IN (…)` and for the read-back that
feeds `wpsocialreviews/custom_review_updated`. "Select all" makes that list as long as the
site's review count, so both statements grew past `max_allowed_packet` on large sites.
`applySpam()` carried the same two statements.

Evidence: `app/Http/Controllers/Platforms/Reviews/RecommendationsController.php` — the
mutation and the read-back each run in `ReviewCategoryRelationship::BATCH_SIZE` chunks, the
same constant the bulk-category path already used. They stay as two separate passes rather
than one interleaved loop: listeners recompute aggregates by re-querying the table, so every
row must carry its new status before the first listener runs.
`tests/throughput/bulk-review-writes.php` — unparked to active assertions at 3 and 501
reviews that no IN-list exceeds 500, that the observed statement count is 3 and 6, and that
exact cardinality still lands. A new sibling case covers `/reviews/spam`, which the parked
item named but never exercised.

### <a id="throughput-bulk-delete-batch-limit"></a>`throughput-bulk-delete-batch-limit` — FIXED

`applyDelete()` snapshotted and deleted every scoped review in two unbounded statements.
Only the pivot cleanup (`ReviewCategoryRelationship::deleteForReviews()`) was chunked.

Evidence: `app/Http/Controllers/Platforms/Reviews/RecommendationsController.php` — the
snapshot and the delete each run in 500-id chunks, kept as separate passes so the documented
ordering holds: all rows are snapshotted, then all rows are deleted, then the actions fire.
`$deletedCount` is summed across chunks. `tests/throughput/bulk-review-writes.php` —
unparked to active assertions at 3 and 501 reviews covering the 500-id cap, statement counts
of 4 and 8, zero surviving rows, and the reported deleted count, which is what catches a
dropped chunk in the sum.

Note: the snapshot still holds every row in memory at once, because the actions need the row
data after the DELETE. That is inherent to the current listener contract and was not part of
this item, which is about statement size.

### <a id="throughput-template-postmeta-n-plus-one"></a>`throughput-template-postmeta-n-plus-one` — FIXED

`Post::getPosts()` resolved each row's platform names through `getPlatformNames()` →
`getConfig()` → `get_post_meta()`. This ORM does no cache priming the way `WP_Query` does,
so every returned template cost one extra `postmeta` query: 4 statements for 2 rows, 10 for 8.

Evidence: `app/Models/Post.php` — the page's post ids are collected once and passed to
`update_meta_cache('post', $ids)` before the serialization loop, so the per-row
`get_post_meta()` calls read from cache. Where a caller already primed the meta cache the
call is a no-op. `tests/throughput/high-volume-reads.php` — unparked to active assertions
that the statement count is 3 at both 2 and 8 rows, that it is size-invariant, and that no
statement asks for more rows than the page LIMIT returned, with the exact owned ids unchanged.

### <a id="throughput-review-sync-query-per-item"></a>`throughput-review-sync-query-per-item` — FIXED

`BaseReview::syncRemoteReviews()` is the shared write path for all twelve review platforms
in Core and Pro, and it spent two statements per payload item: a `first()` existence lookup
and a single-row `INSERT`. A 100-review Google cron run cost 200 statements.

Evidence: `app/Services/Platforms/Reviews/BaseReview.php` — the identity column and value
are extracted into `syncIdentityColumn()` / `syncIdentityValue()` (a straight lift of the
existing branch chains, no logic change), `findReviewsByIdentity()` resolves every item in
one chunked `whereIn`, and new rows are staged and flushed by `insertSyncedReviews()` as
multi-row inserts. Google's junk-row delete moves ahead of the lookup as one statement,
which also closes a window where a per-item delete could remove a row a later item was about
to update. A repeated identity inside one payload still collapses to a single row carrying
the later item's data and the earlier row's approval, matching what the per-item path did.
`tests/throughput/cache-image-sync.php` — unparked to active assertions that a fresh sync
emits exactly one lookup and one insert at both 2 and 8 items, that nothing exceeds
`BaseReview::SYNC_BATCH_SIZE`, and that exact upsert cardinality holds. A new sibling case
re-runs the same payload and asserts it updates in place — one lookup, no inserts, no
duplicates — which is the guard on the batched lookup finding existing rows.

Note three deliberate limits. AliExpress is excluded from batching (`$batchable`): it deletes
by `(source_id, reviewer_name)` only after learning a review is new, so its delete must stay
interleaved with its own insert, and staging inserts would change which rows survive when one
buyer has two reviews on a product. A re-sync still issues one `UPDATE` per existing row —
batching those needs either a `CASE` expression or a unique key on
`(platform_name, review_id)` to upsert against, and neither exists today. And a multi-row
insert fails as a unit where twelve single-row inserts failed individually; rows are grouped
by key set so a payload with mixed shapes cannot produce a malformed statement, but a row
rejected by the database now takes its batch with it.

Registry rows removed from `tests/skips/registry.json`, which drops 6 → 2; all four were
already `default_gate: false`, so no `tests/skips/skip-lib.php` change was needed. Static
gate: `SKIP-LINT registered=2 declared=2 default=0 family=1 runtime-events=0 failures=0`.
`tests/security/endpoints/inventory.json` regenerated: the controller edits shifted six
handler line numbers and changed no source fingerprint, policy or capability. Two later
commits on this branch moved the same rows again — the transaction commit, then the removal
of the rollback `error_log()`. Sixteen rows now differ from the branch base, all of them line
numbers only; every source fingerprint, policy and capability is still unchanged.

### <a id="integration-sync-null-identity-lookup"></a>`integration-sync-null-identity-lookup` — FIXED

`findReviewsByIdentity()` folded every identity through `array_map('strval', …)`, so an item
carrying no identity became `''` and the batched lookup asked `IN ('')`. `review_id` and
`reviewer_url` are both nullable, and `WPDBConnection::spliceNullBindings()` (`:405`) splices a
literal `NULL` into the SQL for a null binding rather than letting `wpdb->prepare()` cast it to
`''`, so those rows really do hold `NULL` — which `IN ('')` never matches. The per-item
`where($fieldName, $value)` this replaced compiled to `IS NULL` (`Builder.php:901`), so the
batched path stopped finding rows the per-item path found: every sync re-inserted the row, one
extra row per cron run, without bound.

Evidence: `app/Services/Platforms/Reviews/BaseReview.php` — a chunk containing `''` matches
`whereIn($fieldName, $chunk) OR $fieldName IS NULL` inside one nested group, and a chunk without
it takes the untouched `whereIn` path, so the ordinary case is unchanged and the statement count
per chunk is still one. Keying needed no change: `(string) $row->{$fieldName}` already maps a
`NULL` column onto the same `''` key that the loop's `(string) $value` produces.
`tests/integration/domain-invariants.php` — a new case syncs a zomato payload with no identity,
asserts the stored column is a real `NULL`, and asserts a re-sync updates that row instead of
duplicating it. Controls: a chunk holding only real identities still does not sweep the `NULL`
row into its match, and a mixed chunk resolves both shapes in the one widened lookup. Reverting
the production change alone turns the case red at 2 rows where 1 is expected.

Note: only the batched path introduced by `throughput-review-sync-query-per-item` was affected.
AliExpress stays per-item, and Google self-heals because `deleteReviewsMissingReviewId()` clears
its null-identity rows ahead of the lookup. The older rule that one platform's empty-identity
rows all collapse onto a single row is deliberately unchanged — restoring it exactly is the point.

### <a id="js-rest-get-input-mutation"></a>`js-rest-get-input-mutation` — FIXED

`Rest.get()` wrote its cache-busting `query_timestamp` straight onto the `data` object the
caller passed in. JavaScript passes objects by reference, so the helper edited state it does
not own. A caller that keeps that object sees a key it never set, and a key whose value
changes on every request: deep-equality "did the filters change?" checks always report a
change, `JSON.stringify(params)` cache keys never hit, and a reactive object in a Vue
component re-triggers its watchers. No live caller reused such an object, so this was a
latent contract break rather than a visible bug.

Evidence: `resources/admin/Rest.js` — `get()` spreads the caller data into a new object and
stamps the timestamp on that clone, so the request still carries the timestamp and the caller
object is untouched. The clone is shallow on purpose; nested values stay shared by reference,
which matches what the transport needs and keeps the helper allocation-cheap. `post()` and the
override helpers already avoided this because `JSON.stringify()` copies.
`tests/js/specs/request-layer.test.js` — the parked fingerprint is replaced by two active
cases: one asserts the caller object still deep-equals its pre-call clone while the sent data
is a different object carrying the stamp, the other calls `get()` twice with the same object
and asserts the caller keys stay `['page']` while each request gets its own timestamp.
Reverting the production change alone turns both red.

Registry row removed from `tests/skips/registry.json`, which drops 6 -> 5. The two derived
known-failure proofs in `tests/family/test-sweep-proof.php` borrowed this row by ID; they now
build their own synthetic row and use the permanent optional-family row instead, so removing
any further known-failure row no longer breaks them.

### <a id="browser-license-core-free-error"></a>`browser-license-core-free-error` — FIXED

Every one of these five screens asked a Pro-or-add-on endpoint for data that core/free
cannot answer, then rendered whatever the failure left behind. The shared remediation is
the same: decide the tier before mounting, and render the tier's own screen instead of a
half-built editor. Observed core/free symptoms before the fix, from a real browser run:
license raised error UI, Custom Source and Review Form left 404 console errors (Review
Form also drew an empty `.wpsr_editor`), Social Wall threw
`TypeError: Cannot read properties of undefined (reading 'name')`, and the TikTok editor
marker never appeared.

Evidence: `resources/admin/components/views/settings/others/LicenseManagement.vue` —
`mounted()` called `getSettings()` unconditionally, so core/free always hit
`GET settings/license`. In free the `wpsr_get_license` filter has no subscriber, so
`SettingsController::getLicense()` returns 422 and the caller raised an error toast. The
hook now runs only when `has_pro`; the template already had the upgrade panel behind
`v-else`.

### <a id="browser-tiktok-addon-absent"></a>`browser-tiktok-addon-absent` — FIXED

Evidence: `resources/admin/components/feeds/editor/TiktokView.vue` — TikTok feeds live in
the free `Custom Feed for TikTok` add-on, and core alone registers no
`wpsocialreviews/get_editor_settings_tiktok` subscriber, so `feedConfig` never filled and
`.wpsr_editor` never rendered. The editor now checks
`appVars.is_custom_feed_for_tiktok_activated` before fetching and otherwise renders the
existing `TikTokFeedLandingPage`, which already explains the add-on and links to its
install route. `tests/browser/screens.manifest.js` asserts that fallback through
`.wpsr-tiktok-addon-required`. This is an add-on gap, not a Pro upsell, so it deliberately
does not use the upgrade panel.

### <a id="browser-social-wall-route-missing"></a>`browser-social-wall-route-missing` — FIXED

Evidence: `resources/admin/components/feeds/editor/SocialWallView.vue` — the
`templates/meta/social-wall/{id}` routes are commented out in core `app/Http/Routes/api.php`,
so `getSocialWallSettings()` 404'd and the channel rendering that follows it threw on
undefined channel data. `mounted()` now returns early without `has_pro`, and the free tier
renders the standard `.wpsr-managers-upgrade-to-pro` panel. `feedConfig` defaults to `[]`,
which is truthy, so the old `v-if="feedConfig"` could never have gated this by itself.

### <a id="browser-custom-source-core-tier"></a>`browser-custom-source-core-tier` — FIXED

Evidence: `resources/admin/components/reviews/editor/CustomSourceView.vue` —
`loadSourceData()` and `loadCategories()` call `pro/custom-sources/*`, which core does not
register, so the screen produced two 404s and an editor with no data. Both calls are now
behind `has_pro`, and the free tier renders the same upgrade panel and copy the
`/custom-sources` list screen already uses.

### <a id="browser-review-form-core-tier"></a>`browser-review-form-core-tier` — FIXED

Evidence: `resources/admin/components/views/review-forms/ReviewFormEditor.vue` —
`fetchForm()` calls `pro/review-forms/{id}`, absent in core, which 404'd and left
`.wpsr_editor` mounted with no content. The fetch is now behind `has_pro` and the free tier
renders the upgrade panel with the copy from the `/review-forms` list screen.

Verification for all five: `tests/browser/screens.manifest.js` drops all five
`knownFailure` tripwires and re-points the TikTok, Social Wall, and Review Form rows at the
markers core/free actually draws. A core-only browser run reports
`BROWSER: 48/48 passed, 0 failed, 0 skipped`, up from `43/48 passed, 4 failed, 1 skipped`.

## Uncounted limitations

These are not SKIPs or passing tests. Allowed YouTube-popup and review-image-resize
responses call literal `die`, so same-process response assertions remain absent.
Denied/object-scope branches stay active. Add isolated-process behavior coverage in
a focused public-test seam PR; do not represent this gap as a green skip.

## Advanced quality findings

### <a id="js-rest-get-input-mutation"></a>`js-rest-get-input-mutation`

`Rest.get()` adds `query_timestamp` to caller-owned query objects. Keep active JS
fingerprint parked until separate production-fix PR clones request data before augmentation.

### <a id="throughput-bulk-status-batch-limit"></a>`throughput-bulk-status-batch-limit`

Bulk status/spam updates validate review IDs in chunks, then rebuild unbounded ID
lists for mutation and post-update reads. Remediation: chunk every status mutation
and verification statement to 500 IDs; unpark after small/large shape fingerprint
meets bounded count and statement-size budgets.

### <a id="throughput-bulk-delete-batch-limit"></a>`throughput-bulk-delete-batch-limit`

Bulk review deletion chunks some ownership and pivot work but snapshots/deletes all
scoped reviews in unbounded statements. Remediation: chunk snapshots and deletes to
500 IDs; unpark after exact deletion cardinality and bounded shape contract pass.

### <a id="throughput-template-postmeta-n-plus-one"></a>`throughput-template-postmeta-n-plus-one`

Template list serialization loads metadata per returned post. Remediation: prime or
eager-load template metadata in bounded queries; unpark when 2-row and 8-row query
counts become size-invariant while exact owned IDs remain unchanged.

### <a id="throughput-review-sync-query-per-item"></a>`throughput-review-sync-query-per-item`

Remote review synchronization performs per-item lookup/upsert work. Remediation:
batch lookup and writes; unpark when 2-item and 8-item query growth stays within
bounded budget while exact upsert cardinality remains unchanged.

### <a id="browser-license-core-free-error"></a>`browser-license-core-free-error`

Core/free license route renders error UI. Remove parking after route renders expected
license-management content without error UI.

### <a id="browser-tiktok-addon-absent"></a>`browser-tiktok-addon-absent`

TikTok editor marker stays unavailable without add-on behavior. Remove parking after
core/free route reaches runnable editor state or receives explicit supported fallback.

### <a id="browser-social-wall-route-missing"></a>`browser-social-wall-route-missing`

Social Wall editor marker stays unavailable in core/free lab. Remove parking after route
reaches runnable editor state or receives explicit supported fallback.

### <a id="browser-custom-source-core-tier"></a>`browser-custom-source-core-tier`

Custom Source editor renders error UI when Pro endpoint is absent. Remove parking after
core/free route handles unavailable endpoint without error UI.

### <a id="browser-review-form-core-tier"></a>`browser-review-form-core-tier`

Review Form editor renders error UI when Pro endpoint is absent. Remove parking after
core/free route handles unavailable endpoint without error UI.

## Family compatibility findings

### <a id="compatibility-core-pro-fail-closed-boot"></a>`compatibility-core-pro-fail-closed-boot`

Core 4.4.0 declares Pro `>=4.4.0`, and
`DashboardNotices::maybeDisplayProUpdateNotice()` emits its update diagnostic for
Pro 4.3.1, but Core has no pre-registration gate. Exact Pro tag 4.3.1 still fires
`wp_social_ninja_pro_loaded`, registers 22 Pro route paths, and serves the
representative Pro endpoint. Parked proof: compatibility cell
`core-4-4-rejects-pro-4-3-1`, which stays red on purpose.

**Status 2026-08-12: stays parked. Do not implement the gate as specified.**

A compatibility investigation against the released `4.3.1` Pro tag found no
evidence that Pro 4.3.1 breaks on Core 4.4.0. All 43 Core classes Pro references
still exist. Only two `app/` methods changed since Core `4.3.2`, both by adding an
optional parameter: `Review::paginatedReviews()` gained `$knownTotal` and is never
called by Pro, and `ShortcodeHandler::isPublicRenderableTemplate()` gained
`$wpsr_extra_post_types` while Pro calls it with one argument at
`FacebookTemplateHandlerPro.php:680`, which stays valid. Both Core global functions
Pro uses exist, the WPFluent framework API is unchanged, and 16 of the 17 Core hooks
Pro listens on are still fired — the exception,
`wpsocialreviews/trustpilot_reviews_platform_icon`, was never fired by Core in
`4.3.2` either, so it is a pre-existing dead listener in Pro rather than drift. The
cell itself boots Pro 4.3.1 and serves the representative endpoint `200` with no
fatal.

The prescribed remediation is also not implementable as written.
`tests/compatibility/runtime-cell.php:54` asserts the lifecycle action fires for
every cell, and `:61` asserts Pro stays in `active_plugins`, so neither withholding
`wp_social_reviews_loaded_v2` nor deactivating Pro satisfies the contract. The hook
is additionally shared with the `custom-feed-for-tiktok` free plugin
(`custom-feed-for-tiktok.php:24`), so withholding it would disable an unrelated
shipped plugin. The only mechanism left that works against already-released Pro code
is Core reflecting over `$wp_filter` to strip Pro-owned closures — new bootstrap
machinery defending against unobserved harm.

Shipping the gate would break a combination that currently works, and it would break
it for exactly the users whose licence lapsed: Core auto-updates from wp.org while
Pro stays behind, and today their site keeps working. Unpark only if someone
demonstrates real breakage, or the owner of `WPSOCIALREVIEWS_PRO_MIN_VERSION`
confirms hard-blocking is intended policy. `tests/DEVELOPER-HANDOFF.md` has been
revised to match and no longer lists this as the highest-priority defect.

### <a id="security-inert-feed-html"></a>`security-inert-feed-html` — FIXED

`sanitizeHtml()` parsed untrusted third-party feed HTML into a live detached
`<div>`, so `<img src=x onerror=…>` fired at parse time before the filtering walk
ran. Both the public helper and the admin mirror now parse into an inert
`<template>` and walk `temp.content.childNodes`.

Evidence: `resources/public/helper.js`, `resources/admin/WPSocialReviews.js`;
`tests/lint/known-failure-inert-feed-html.php` extended to both files (8 checks)
and registered as the `inert feed HTML source contract` static gate; both parked
`wpsr.js.live-container-untrusted-html-parser` rows removed from
`tests/security/semgrep/quality-policy.json`. Browser check: live `<div>` fires
the handler, `<template>` does not, with identical parsed child nodes.

Note: commit `634b3f7a4` was never shipped — it exists only on unmerged branch
`fix/sanitizehtml-live-parse-xss`, and covered the public helper alone.

### <a id="security-json-decode-before-unslash"></a>`security-json-decode-before-unslash` — FIXED

Five controller paths called `json_decode()` then `wp_unslash()` on the decoded
array. The remedy this item originally prescribed — unslash the raw request text
first — is wrong for this codebase, and the eight sibling paths that already did it
that way were failing worse. Nothing slashes these values: `WP_REST_Server::serve_request()`
unslashes `$_POST`/`$_GET` (`wp-includes/rest-api/class-wp-rest-server.php:378`), an
`application/json` body is parsed from the raw body and never slashed at all, and the
WPFluent `Request` constructor runs `stripslashes_deep()` on both superglobals
(`vendor/wpfluent/framework/src/WPFluent/Http/Request/Request.php:119`). Every
`wp_unslash()` near these decodes is therefore a second strip pass on already-clean data.

Measured against real WordPress with `Rest.js`'s own `application/json` shape, a user
typing `x\y`:

| order | result |
| --- | --- |
| `json_decode` then `wp_unslash` (the 5 parked paths) | `xy` — backslash silently deleted |
| `wp_unslash` then `json_decode` (8 sibling paths) | `NULL`, JSON syntax error |
| decode only (fixed) | `x\y` |

The `NULL` mattered most: `SettingsController` and `ShoppablesController` turn a failed
decode into `[]` and pass it to `update_option()`, so one backslash typed into a
shoppable hashtag or a review setting wiped the whole stored option.

Evidence: `app/Http/Controllers/Controller.php` — new `decodeJsonParam()` decodes without
any unslash pass and returns `null` unless the result is an array, plus
`invalidJsonPayload()` for the shared `422`. Applied to all thirteen paths:
`Platforms/Chats/MetaController.php`, `Platforms/Feeds/ConfigsController.php`,
`Platforms/Feeds/MetaController.php` (2), `Platforms/SocialWall/MetaController.php`,
`Platforms/Reviews/MetaController.php` (3), `SettingsController.php` (3) and
`ShoppablesController.php` (2). The meta and configs endpoints answer `422` on an
unreadable payload instead of passing `null` into their hooks; `SettingsController` and
`ShoppablesController` keep their existing `[]` fallback, so only the corrupting unslash
was removed there. Every admin caller already sends the payload as `JSON.stringify(...)`,
so no screen loses a shape it used to send.

The Semgrep rule carried the same wrong advice in its message and marked
unslash-then-decode as `ok` in its fixture, so it would have invited the worse variant
back. `tests/security/semgrep/packs/quality.yml` — the rule becomes
`wpsr.php.unslash-around-json-decode` and matches all three shapes;
`tests/security/semgrep/fixtures/unslash-around-json-decode.php` replaces
`decode-before-unslash.php` with three positives and three negatives. All five rows
removed from `tests/security/semgrep/quality-policy.json`, which is now empty.

The rule excludes raw superglobal reads through eight `pattern-not` clauses, because
`$_POST`/`$_GET`/`$_REQUEST`/`$_COOKIE` really are slashed and unslashing them is
required — `ShortcodeHandler.php:1475` and Pro's
`ReviewForms/ReviewFormAjaxController.php:53` both depend on that. Two of the fixture's
negatives cover those shapes. The exclusion is exact-shape only: it cannot see through
an indirect variable, so the rule stays a tripwire on one shape rather than proof the
family is clean. Taint mode would close that gap, but `$request->get(...)` is already a
taint source in `packs/security.yml`, and promoting `wp_unslash()` to a sink there would
immediately flag the roughly twenty spurious unslash sites listed below.

That empty policy exposed a gate assumption: `verify.py` failed on `zero Semgrep source
findings` and `run-semgrep.sh` required `FINDINGS > 0`, so a fully clean pack turned red.
Both now require findings only while the policy still parks rows — `rules >= 1` and
`scanned >= 1` already prove the scan ran.

Note: `SocialWall/MetaController` is currently unrouted (`app/Http/Routes/api.php:200`
is commented out and Pro registers nothing for it). It is fixed for consistency, not
because it is reachable today.

Shifting these method bodies drifted `tests/security/endpoints/inventory.json`, whose
rows record `security_contract.handler.line` and a body fingerprint. Regenerated with
`php tests/security/endpoints/build-inventory.php`; the diff is exactly 27
`handler.line` shifts and 12 `handler.source_fingerprint` changes — one per routed
method edited — with no row added or removed and no `authorization`, `policy`,
`object_scope` or `side_effect` movement, so no security contract changed.

Verification: PHPStan level 2 `[OK] No errors`; PHPCS clean over 209 files (the gate's
own `-d error_reporting=24575` is what makes the PHPCompatibility sniff usable, since
the pinned PHP 7.4 binary is missing `libffi` on this machine); host static gate
`15/15 pass 0 fail`, including endpoint-inventory integrity and the
`meta-write-ownership` source contract whose two controllers are edited here; behaviour
proved against a live WordPress `WP_REST_Request` for backslash, quote, multibyte and
newline payloads plus four rejection cases.

**Not run locally**: Semgrep, which needs Docker. The fixture layout was checked by
feeding synthetic results to the real `verify.py` — it passes when the rule reports at
lines 4/11/18 and fails `expected 3 finding(s), got 5` if the superglobal negatives
fire, so a broken `pattern-not` turns the fixture self-test red rather than passing
silently. The matcher itself is still unproven: confirm 3/3 on the fixture before
treating this row as closed.

Remaining in the family, deliberately out of scope: roughly twenty `wp_unslash()` calls
on `$request->get()` data with no `json_decode` nearby, so the rule cannot see them —
`Platforms/Reviews/RecommendationsController.php` (13 sites),
`Platforms/Reviews/CategoriesController.php:42`, `Models/ReviewCategory.php:129`,
`Services/Platforms/Chats/Helper.php:195`, and
`Platforms/Chats/MetaController.php:262`, which still strips the Fluent Forms
credential inside a file fixed here (harmless — `getFluentFormsShortcode()` only reads a
numeric id out of a strict pattern). Pro carries the `NULL` variant at
`app/Http/Controllers/SettingsController.php:289`, outside this repo's gate.
