# Test-writing rules

Adapted from WP plugin test-suite skill assets.

## Ground truth

Run from core repo root:

```bash
bash tests/lab/scripts/setup.sh
bash tests/bin/run-all.sh static
bash tests/bin/run-all.sh smoke
bash tests/bin/run-all.sh permissions
bash tests/bin/run-all.sh integration
```

Docker lab is only WordPress target. Never add host WP discovery or dev-site
credentials. Every launcher stays under `tests/bin/`; static analyzers stay in
`dev/`.

## Proof-of-catch

Never keep test not observed red. Break test fixture or safe temporary working
copy behavior, confirm meaningful failure, restore, confirm green, record exact
red/green result. Never leave production modified.

## Prohibitions

- Never mock `$wpdb`, query builder, or models. Use real lab MariaDB.
- Mock only outbound HTTP/mail. Never run real remote calls, mail, or cron.
- Never fix production during test-writing run. Park actual defect as explicit
  `KNOWN-FAILURE` skip and add `tests/FIX-PLAN.md` file:line/mechanism entry.
- Never weaken test to get green.
- Never use destructive GET route. Manifest skip must state exact reason.
- Never depend on host/dev WordPress or default `wp_` prefix.

## Phase 1–3 invariants

- Every core GET declaration remains represented in smoke manifest.
- Route-coverage lint fails on missing, stale, malformed, or zero-discovery mappings.
- Parameterized routes use test-owned lab fixtures.
- Client query variations come from `resources/admin` source.
- Cache clears happen before protected-table baseline.
- Plugin diagnostics, DB errors, exception envelopes, status failures, and data
  count changes fail run.
- Every core mutating declaration remains represented exactly once in permission manifest.
- Anonymous denial is exactly `401/rest_forbidden`; explicit read-only user denial
  is exactly `403/rest_forbidden`. Matching envelope status and nonempty message required.
- Parameterized mutating routes resolve only to runner-owned fixtures. Controller
  fuse turns any policy bypass into `418/wpsr_permission_gate_bypassed` before writes.
- Low role has `read` only and no plugin/admin capabilities. User, role, posts,
  reviews, and category fixtures clean up in `finally` plus shutdown fallback.
- Full database fingerprint remains unchanged across denied requests; protected
  table counts still match after fixture cleanup.
- Core matrix only. Pro path remains registry-only until later phase.

## Phase 4 integration invariants

- Test files under `tests/integration/` return callables and run in sorted order.
- Every behavior case creates unique factory fixtures and cleans them in `finally`;
  runner shutdown cleanup is fallback and cleanup stays idempotent.
- Use real WPFluent models/query builder and real non-default-prefix lab DB only.
- Assert persisted and queried values, not model existence or status alone.
- Unknown filters, zero selected files, and files registering zero cases fail.
- Integration preserves protected counts; DB errors, plugin diagnostics, outbound
  HTTP/mail, and enabled cron violate suite contract.
- Phase 4 stays core-only and does not add Phase 5 status-transition breadth.

## Phase 5 state-machine invariants

- `review_approved` values come from production contracts: pending `0`, approved
  `1`, spam `2`; do not invent aliases or statuses.
- Initial policy decisions call `ReviewApprovalService`; transitions dispatch real
  `/reviews` and `/testimonials` REST handlers and reload real `Review` rows.
- Legitimate commands assert persisted values after every step: `disable` → `0`,
  `enable` → `1`, `mark-spam` → `2`, `not-spam` → `0`.
- Review/testimonial route scope is part of each transition invariant. Mixed owned
  IDs must mutate only route-owned rows.
- Invalid status/action values are exact no-ops with production response values.
- Phase 5 stays narrow: no shared-table discriminator matrix or aggregate breadth.

## Phase 6 shared-table invariants

- Material core families are inventoried from actual models/schema/callers, not generic WPFluent assumptions.
- Every discriminator case creates adjacent owned rows in one physical table and asserts exact included/excluded IDs, values, counts, and deterministic order.
- Covered families: WordPress posts (`post_type`, plus template publish scope), reviews (`platform_name`), cache rows (`platform + name`), optimize-image platform operations (`platform`), and review/category pivot ownership (`review_id + category_id`).
- Integration factories own posts, categories, relationships, caches, and optimize-image rows; cleanup verifies IDs/markers and runs in `finally` plus shutdown fallback.
- `posts` and `postmeta` count/checksum fingerprints must match before/after runtime tiers, alongside six protected plugin-table counts.
- Missing production ownership clauses become `KNOWN-FAILURE` skips plus `FIX-PLAN.md` entries; no test-run production fix.
- Phase 6 does not add report/aggregate semantics.

## Phase 7 report/aggregate invariants

- Inventory comes from core routes, controllers, models, services, and frontend consumers; do not invent generic reports.
- Exact mixed fixtures must make status, discriminator, platform/source/business, `GROUP BY`, count/sum/average, and zero-boundary mistakes observable.
- Covered aggregate families: review/testimonial status-scoped listing totals; shared-post template/widget/notification totals; category relationship counts; option-backed connected-business pagination; business/platform summaries and rating buckets; internal business count/average; public/editor filtered totals and review-template meta output.
- Aggregate assertions normalize deterministic maps/lists and check values, not only response health.
- Test options must contain factory marker, must not replace existing options, and must be deleted exactly in `finally` plus shutdown cleanup.
- Core-only scheduler/email reports, persisted status options, and selector pagination booleans are not reclassified as reports; Phase 8 background/invariant work stays out of scope.
- Production aggregate defects remain explicit `KNOWN-FAILURE` skips plus `FIX-PLAN.md` entries.

## Phase 8 domain-invariant rules

- Inventory production before testing; core has no durable generic queue, atomic claim API, or explicit lock/mutex layer.
- Category taxonomy backfill/sweep tests redirect public static option names to marker-owned options, shrink production batch constants in memory, and restore every static value in `finally`.
- Assert cursor values, ordered relationship state, per-call batch bounds, completion-marker timing, retry/resume behavior, and repeat idempotency through real migrator SQL.
- Cron tests invoke production registration methods only. Marker hooks/options may be created; fixed global hooks may only be observed/re-registered idempotently, never cleared or replaced.
- Full serialized WordPress cron option is fingerprinted before/after each runtime tier. Factory cleanup owns marked cron hooks and fixed-name options only when marker-bearing value was created from an absent row.
- Image eviction asserts exact oldest IDs, bounded deletion count, and platform ownership without creating/downloading files.
- Review-sync cursor tests call real `BaseReview::syncRemoteReviews()` with local arrays; assert repeat no-duplication, empty retry preservation, resume, and source-isolated markers.
- Scheduler callback workflows, remote refresh execution, email reports, concurrent cron workers, and full image-resize AJAX remain Phase 14 scope.

## Phase 9 mutating round-trip invariants

- `tests/round-trips/routes.manifest.php` classifies all 66 core mutating declarations exactly once; static composition fails missing/stale rows, missing material CRUD families, unknown cases, or zero discovery.
- Material CRUD families use authorized real REST controllers: review, testimonial, category, template, chat widget, and notification.
- Lifecycle contract is create → REST read plus persisted reload → update exactly one requested field → full normalized snapshot diff → delete → row/postmeta/pivot gone.
- Snapshot every stable persisted row, option, post, postmeta, and relationship field. Normalize only ORM `updated_at`, WordPress `post_modified`/`post_modified_gmt`, and decoded `_wpsr_template_config` serialization.
- Reversible local writes cover translations, advanced settings, YouTube platform settings, shoppable settings, widget config, review-template config, and feed/shoppable-template config.
- Fixed options may be touched only when absent and factory-owned for the case. Pre-existing `wpsr_statuses`, licenses, external credentials/OAuth, resets, onboarding, sync, installers, previews, and global deletion stay explicitly deferred.
- Route-created rows/posts/categories gain factory ownership immediately and clean in case `finally` plus shutdown fallback. Never delete an unowned ID.
- Proof mutants must overwrite a non-updated field or break deletion, fail on exact full-snapshot/gone assertions, restore byte-identically, then return green.

## Phase 10 validation/authorization invariants

- `tests/validation/routes.manifest.php` classifies all 93 core REST declarations by input family and disposition; static drift fails missing/stale/malformed rows, missing material families, missing runtime references, or zero discovery.
- `tests/validation/non-rest.manifest.php` records shortcode/AJAX/filename surfaces without starting Phase 18; core has no multipart `$_FILES` handler.
- Use SELECT-shaped, owned-fixture probes. Assert exact sanitized values, persisted state, query results, and rendered HTML; status-only assertions are insufficient.
- Cover literal LIKE wildcard semantics, sort allowlists/fallbacks, integer and pagination bounds, stale IDs, action/status enums, `wpsr_backend_sanitizer()` maps, URL/email/color/style values, nested config, stored markup, and output escaping.
- Limited-capability users own only explicit `wpsn_*` caps. Cross-review/testimonial and cross-post-type attempts must preserve full target state; global category mutation requires both review and testimonial caps.
- Factory owns temporary users/roles and removes them before other fixtures. Always restore administrator identity and refresh `PermissionManager` cache in `finally`.
- Credential/OAuth/sync, plugin install, global resets/delete-all, public AJAX/shortcode, remote image download, filesystem resize, and absent core upload handling stay explicitly deferred/excluded.
- Validation or authorization gaps stay executable behind explicit `KNOWN-FAILURE` skips with `FIX-PLAN.md` file:line/mechanism entries. Never fix production during this phase.
- Proof mutants must remove one allowlist, escape, sanitizer, clamp, scope, policy conjunct, or output escape; focused test must fail on exact value/state, production restores byte-identically, then focused/full gates return green.

## Phase 11 browser invariants

- `resources/admin/router/routes.js` leaf routes are static source of truth; every leaf stays represented in `tests/browser/screens.manifest.js` and zero discovery fails.
- Every runnable screen asserts route title plus component-owned selector/text from real Vue markup. Generic WordPress body/admin shell/HTTP 200 never passes.
- Hash navigation reuses one authenticated SPA page, waits relevant requests idle, and retries destroyed execution contexts. Core test-built asset reads retry three times; failed core assets/chunks remain failures.
- Browser identity is a dedicated ephemeral administrator minted only in Docker lab. Both logged-in/auth and secure-auth cookies are supplied. User, posts, postmeta, review form, option record, and marker state clean exactly; protected/user/post fingerprints and cron match.
- Browser process gets lab-mounted HTTP/mail mu-guard. Guard proof must audit one blocked HTTP and mail call before navigation; actual screen run must audit zero. Browser-side foreign network is blocked too.
- Console errors, page errors, core REST/asset failures, blank component markers, PHP/REST error text/UI, and login redirects fail. Only exact passive WordPress avatar/emoji, Inter font, and promotion YouTube iframe requests are filtered; blanket `ERR_FAILED` filtering is forbidden.
- Core-free blank/error findings remain `KNOWN-FAILURE` skips plus `FIX-PLAN.md` entries. Pro/custom TikTok add-on endpoints stay represented but skipped; Phase 11 adds no Pro tests.
- Browser is opt-in through `tests/bin/run-all.sh browser`; default `all` remains fast. Five consecutive complete browser-tier passes are required after proof red/green.

## Phase 12 pure-function rules

- `tests/pure/*.php` exercises real core PHP functions/classes through minimal Docker WordPress bootstrap; no models, query builders, fixture factory, outbound HTTP/mail, or cron.
- Inventory material deterministic helpers from callers/source and record tested/prior-covered/excluded disposition in `COVERAGE-LEDGER.md`.
- Prefer exact table vectors for empty, malformed, boundary, Unicode, timezone, and scalar coercion inputs. Assert caller arrays remain unchanged where transforms are by value.
- Current-time/random helpers need existing production seams or stable structural assertions. Never add production seams during test-writing. Document excluded branches.
- Rate-limit budget tests may use existing filters plus uniquely named transients; exact counters/windows/keys must be asserted and all transient/timeout rows deleted in `finally`.
- Do not duplicate Phase 10 nested sanitizer persistence/rendering. Distinct direct scalar sanitizer boundaries are allowed.
- Unknown pure filters, zero files, non-callable files, and files registering zero cases fail. Pure tier stays in default fast gate.
- Real defects remain executable `KNOWN-FAILURE` skips plus `FIX-PLAN.md` entries. Proof uses temporary production mutants and byte-identical restoration.
- Phase 12 adds no JS/Vitest, Pro, browser, coverage, or Phase 13 work.

## Phase 13 coverage rules

- Coverage is opt-in: `bash tests/bin/run-all.sh coverage`; default fast gate remains unchanged.
- PCOV runs only in Docker and must remain disabled in normal PHP processes.
- Merge pure, smoke, permissions, and integration captures. Never present one tier as total.
- Reporter includes all first-party core PHP under `app`, `boot`, `config`, and `database`, plus plugin/root index files. Exclude tests, vendor, dev, generated output, Pro, and bundled `app/Services/Libs` with explicit report metadata.
- `show_uncovered=true` is mandatory. Every filtered source file must appear, including untouched 0% files.
- Zero captures, zero PCOV data, zero covered files, omitted source files, missing tiers, or a layer summary with zero execution fail closed.
- Every physical >100-line 0% file needs one explicit ledger disposition. Do not invent dead/legacy status without evidence.
- Gap tests prioritize safe customer-critical local behavior, authorization, persistence, migrations, and rendering. Do not chase percentage or start Phase 14.
- Every new behavioral case needs a temporary production mutant observed red, byte-exact restoration, and focused green proof.

## Phase 14 background/automation rules

- Inventory actual core hooks/callbacks first. Classify Pro-owned, companion-owned, remote-only, callback-less extension hooks, and request-driven workflows explicitly; never invent queue/enrollment semantics.
- Invoke registered cron callbacks synchronously with `do_action()`. Never spawn cron, sleep, or wait for schedules.
- Every automation case resets/interrogates harness HTTP/mail captures. Exact approved transport intents may receive deterministic boundary responses; all unmatched attempts remain blocked.
- Assert active/inactive dispatch, exact hook payloads, cursor/state advance, completion markers, retry/no-op, and repeat idempotency where production has those concepts.
- Category maintenance reuses Phase 8 bounded cursor evidence. Image resize is synchronous AJAX, not a queue; test local item state only here and leave public/filesystem behavior to Phase 18.
- Fixed options are owned only from absent state and cleaned exactly. Real models/query builder/MariaDB remain mandatory.
- Core-free missing bridge/provider branches must preserve state and make zero unsigned requests. Remote provider contracts and Pro callbacks are not fabricated.
- `posts`, `postmeta`, `users`, `options`, protected plugin tables, and serialized cron fingerprints must match before/after runtime tiers.
- Production defects stay executable `KNOWN-FAILURE` skips plus `FIX-PLAN.md` entries.

## Phase 15 JavaScript unit-test rules

- Vitest dependencies/config/cache remain under `tests/js/`; launcher remains `tests/bin/run-js.sh`. Never add root package scripts/dependencies/config.
- Test real source imports. Stub only jQuery transport and browser globals; never copy request/store/utility implementations into tests.
- `resources/admin/Rest.js` is canonical request seam. Assert exact URL/query/method/body/header/nonce, success/error passthrough, and caller ownership.
- Core currently has no Vuex/store source despite root Vuex dependency. `tests/js/inventory.mjs` must fail if store signatures appear; never invent actions/mutations/getters to satisfy a generic checklist.
- Pure utilities use boundary tables and input non-mutation assertions. Vue/component/public DOM runtime stays explicitly inventoried, not shallow-mounted.
- Unknown filters, zero selected files, zero reported files/tests, and zero executed tests fail. Keep exit-0 zero-summary verifier self-test active.
- JS tier stays in default fast gate while reliable and under budget. Production JS findings remain executable `KNOWN-FAILURE` skips plus `FIX-PLAN.md` entries.
- Proof-of-catch uses temporary request/state/utility production mutants, byte-identical restore, and focused green reruns. No Pro JS tests.

## Phase 16 throughput rules

- `throughput` is opt-in and runs only real Docker MariaDB/models/query builder/REST behavior. Zero files/cases fail.
- Capture real SQL through narrow `wpdb` `query`-filter windows around measured operations; fixture setup, verification, and cleanup stay outside capture.
- Run every guard at explicit small N and larger M. Assert result cardinality, deterministic query-count budgets/growth, and structural `IN`/`VALUES`/`LIMIT` caps.
- Timings are informational only. Never gate wall-clock duration.
- Prime only documented request/option/object caches outside capture. Fixtures use unique markers; no empty-DB assumption.
- Query fingerprints and observed counts belong in Phase 16 inventory/report. Budget headroom cannot conceal per-item growth.
- Unbounded/N+1 production behavior stays executable behind `KNOWN-FAILURE` skips and receives a `FIX-PLAN.md` entry. Never fix production during throughput work.
- Proof mutants add one per-item query or remove one batch limit, must fail specifically on count/size budget, restore byte-identically, then focused/full throughput return green.
- Core-only. Remote provider delivery, filesystem image work, browser rendering, Pro, and Phase 17 E2E stay excluded.

## Phase 17 E2E rules

- Core has no checkout, payment, order capture, billing, or paid-subscription lifecycle. Record this N/A classification in `COVERAGE-LEDGER.md`. Never reinterpret shoppable links, newsletter/YouTube subscriptions, imported WooCommerce reviews, upgrade URLs, or license delegation as money flows.
- `tests/e2e/flows.manifest.js` contains exactly three substitutes: review moderation, review-template lifecycle, and chat-widget lifecycle. Zero discovery, count other than three, missing source/action snippets, unknown filters, or stale selectors fail.
- User actions go through real core-free Vue UI. Lab inspector may read exact DB state and perform failure-only teardown; it is never the main action.
- Separate Apache PHP process gets lab mu-plugin HTTP/mail guards. Launcher proves one blocked/audited HTTP and mail attempt before fixtures, clears proof rows, and requires zero flow audit rows after every flow/tier.
- Each flow owns marker fixtures. UI-created posts belong to dedicated ephemeral admin; flow `finally`, token-scoped mu cleanup, shell traps, and fixture teardown are layered. Six plugin tables plus posts/postmeta/users/usermeta/options and serialized cron fingerprints must restore exactly.
- Console errors, page errors, core asset failures, core/test REST failures, visible error UI, login redirects, and non-loopback browser requests fail. Only exact passive WordPress avatar/emoji/Inter requests and a fulfilled/read-preflighted WhatsApp icon navigation abort are filtered.
- E2E is opt-in through `bash tests/bin/run-all.sh e2e`. Stability requires five consecutive complete 3/3 runs; focused runs never count.
- Every flow needs an actual frontend/production outcome mutant red, byte-identical restore, and focused green. No Pro, real provider, install, license, newsletter, payment, or Phase 18 public-handler work.

## Phase 21 remaining-E2E rules

- Combined E2E manifest contains exactly six flows with explicit ownership: Phase 17=3 and Phase 21=3. Phase 21 adds review-category lifecycle, review filtering/search, and translation-settings persistence; it never relabels or duplicates Phase 17.
- Category main actions are UI bulk-assign/manage operations. Assert exact category row, pivot, chip, relationship count, rename/delete results, plus both rename-Cancel and delete-Cancel persisted no-ops.
- Search/filter is read-only. Assert exact visible inclusion/exclusion/count/empty state and complete persisted review-state equality before/after.
- Translation save starts from absent `wpsr_global_settings`, asserts exact sanitized option plus reloaded inputs and sibling preservation, then proves unsaved navigation is a persisted no-op.
- Per-flow cleanup may use token-scoped inspector only after UI actions. Categories/options/posts remain marker-owned; shell trap and fixture shutdown are idempotent fallback.
- Fresh-WordPress admin/public housekeeping is warmed before fixture fingerprints with a temporary admin deleted before capture. Only exact blocked WordPress update-check intents are classified there; flow audit remains strict HTTP `0`, mail `0`.
- Stability means five consecutive complete 6/6 runs. Focused proof/restored runs do not count. No Pro, remote credentials, mail, install, license, global reset, production fix, or Phase 22 work.

## Phase 18 public-handler rules

- `tests/public/handlers.manifest.php` is canonical core unauthenticated inventory. Static drift must fail on missing/stale/zero nopriv AJAX, shortcode, template-redirect, rewrite/query-var, or public REST classifications.
- Public runtime stays core-alone and anonymous. Dispatch real `wp_ajax_nopriv_*` hooks where handler termination permits; literal `die()` paths without existing seam become explicit coverage gaps, never production seams added during test writing.
- Every wrong nonce/token rejection asserts exact status/envelope/message plus unchanged full database fingerprint, test-owned upload-tree fingerprint, cron/options/posts, and zero HTTP/mail.
- Public nonces authenticate nothing because core localizes them for logged-out visitors. Public template post type/status/password ownership is separate mandatory scope.
- Allowed reads assert exact owned output, approved/source scope, escaping, hidden-row absence, and safe empty invalid IDs. Visitor request counters are exact owned transients and clean before fingerprint comparison.
- Image handlers point `upload_dir` only to marker-owned temp root. Core has no multipart upload handler; MIME/extension/size upload validation is Pro-only. Invalid filename/path/MIME-shaped input must create no file or outbound request.
- Template redirects query published post types only. Notification rendering and visitor order/rating/search toolbar behavior remain explicitly Pro-owned in core-free environment.
- Production public gaps stay executable/documented `KNOWN-FAILURE` skips plus `FIX-PLAN.md`; coverage gaps name exact hard termination/seam blocker.
- Proof mutants bypass nonce, dual image nonce, template object scope, shortcode visibility, visitor budget, or redirect publish scope; every mutant must produce meaningful red, restore bytes/hashes, then public/default gates return green.
- Phase 18 adds no Pro runtime tests, production fix, Phase 19 mutation audit, or commit.

## Phase 19 mutation-audit rules

- Mutation audit is opt-in and core-PHP-only. Manifest selection is deterministic and source-traceable; never mutate tests, vendor, Pro, generated, uninstall, or destructive paths.
- Apply one syntactically valid mutant at a time. Run its narrow owner first; every narrow survivor must run full default gate before outcome classification.
- Restore tracked production with `git checkout -- <file>` from byte backup context, never reconstructed text. Assert exact baseline `git status --porcelain=v1 -z --untracked-files=all` equality for core and Pro plus every target production SHA after each mutant; abort on drift.
- Baseline may contain untracked `tests/`. Equality to captured snapshot is required; blanket tree cleanliness is not.
- Survivors are deliverable. Classify test gaps, equivalent mutants, configuration/environment artifacts, or expected known-failure contracts honestly; never chase 100%.
- New survivor tests require survivor-red/restored-green proof. Production remains unchanged and findings stay in `FIX-PLAN.md`.

## Phase 20 environment-axis rules

- `tests/compatibility/axes.php` is canonical. Zero discovery and unknown selectors fail; matrix stays opt-in.
- Every axis runs complete PHP gate: static, pure, smoke, permissions, integration. JS/browser/E2E do not rerun for DB/time/free axes.
- Canary runs before tiers and inside every PHP runtime process. Strict SQL asserts exact session/global modes and exact non-default prefix; timezone asserts real local/UTC offset; free-alone asserts Pro file/constants/hooks/routes/endpoints absent.
- Capture exact protected/plugin and WordPress table fingerprints, options/cron, plugin activation rows, timezone rows, SQL mode, DB, and prefix before configuration. Print recovery command first; trap/finally restore and verify exact baseline.
- Never deactivate/reactivate plugins outside disposable Docker lab. Free-alone lab omits Pro mount and needs no activation mutation.
- Strict-axis production defects stay axis-only `KNOWN-FAILURE` skips plus FIX-PLAN. Never weaken product assertions or fix production.
- MariaDB-only WordPress core compatibility accommodations must be narrow, fixture-proven, and cannot relax product SQL mode.

## Phase 22 security-registry rules

- `tests/security/test-metadata.json` is sole selectable security-target registry. Every row has unique kebab-case ID, core plugin ownership, normalized sorted module/category tags, implemented runner, existing path, nonzero summary parser, and substantive claim.
- Selector semantics are OR within repeated/comma-separated values of one dimension and AND across plugin/module/category dimensions. Ordering is runner-first then ID.
- Unknown/runtime-only plugin, unknown module/category, malformed selector, and empty intersection exit 2. Child detector/test failure exits 1.
- `tests/security/validate-registry.php` owns filesystem discovery, orphan/phantom checks, family resolution, runner/path/count contracts, and exact representative `--list` IDs.
- `tests/security/registry-self-test.php` proves orphan, phantom, duplicate ID, bad plugin, bad tag, runtime-only selector, unknown selectors, empty intersection, and no-selector behavior. It stays in default static gate.
- Every executed registry target must expose an exact nonzero target/test/mutant/check count. Exit 0 with missing/zero summary is failure.
- Register only security-specific targets or inventories. Broad CRUD, browser, E2E, and throughput suites are not security targets without body-backed claims.
- Full registry execution is opt-in through `tests/bin/security` or `bash tests/bin/run-all.sh security`; default never reruns runtime/mutation tiers.
- Phase 22 adds no source-contract detector, DAST, fuzz, Semgrep, Pro runtime, or production change.

## Phase 23 source-contract detector rules

- `tests/lint/detect_*.php` is registry-discovered; every discovered detector must be registered and expose `SOURCE-CONTRACT ... checked=N failures=0` with `N>0`.
- Use `source-contract-lib.php` method/structure slices. Positional assertions apply only where order is security boundary. Missing files/methods, duplicates, parse ambiguity, unknown CLI, and zero checks fail closed.
- Detector CLI supports `--source-root=ROOT` and repeatable `--source-file=RELATIVE=PATH`; Phase 23 does not add fixture mutation harness.
- Pin only shipped, source/history-proven fixes. Never register detector for active FIX-PLAN gap. Intentional red source proof stays outside `detect_*.php` discovery.
- Core only. Existing raw-SQL detector remains sole raw-SQL contract; no duplicate SQL scanner.
- Temporary production proof mutants require byte backup, `finally` restore, SHA equality, and exact core/Pro status guards before restored green run.

## Phase 24 source-contract fixture rules

- Every registered `tests/lint/detect_*.php` target owns a minimal faithful fixed fixture tree under `tests/lint/fixtures/<detector-id>/fixed/`; manifest and registry discovery must match exactly and fail zero discovery.
- Detectors run unchanged through `--source-root=<fixture>` for fixed-green, incomplete-red, and mutated-red proofs. Harness never duplicates detector assertions or falls back to live production source.
- Each mutation changes exact fixture bytes, reintroduces one guarded flaw, and must exit `1` with expected detector ID/message. No-op mutations and unexpectedly green mutants fail.
- Temporary fixture workspaces live outside repository and clean in `finally` plus shutdown fallback. Any residue fails.
- Capture byte-exact core and Pro `git status --porcelain=v1 -z --untracked-files=all` baselines. Recheck after every self-test/case; untracked `tests/` baseline is allowed but must remain identical.
- Hash every tracked production path outside tests/dev/docs metadata in core and Pro. Recheck after every case and abort immediately on status/hash drift.
- Production is never mutated or fixed. Intentional inert-feed HTML known-failure proof remains separate and unregistered.

## Phase 25 Docker security-lab rules

- `tests/bin/security doctor` is full live preflight. It fails unless DB has no
  host port, web binds only `127.0.0.1`, DB/WordPress are healthy, exact core
  dual mounts are read-only, DB/prefix are disposable, Pro sibling is found but
  unmounted/inactive, reset is destructive-volume explicit, scanner images exist,
  and fixed-target refusal works.
- Default static gate runs only `doctor --config-only` plus derived doctor
  self-test. Never add ZAP/nuclei to default gate.
- Doctor proof mutates a model derived from live compose. Never edit/weaken live
  compose to prove unsafe DB ports, wildcard bind, writable/single mount, unsafe
  DB/prefix, external target, missing health, or missing tool failures.
- Scanner target is exactly `http://127.0.0.1:80` inside WordPress's shared
  network namespace. Wrappers accept no target argument or URL override.
- ZAP is passive baseline only. Nuclei uses committed HTTP templates only with
  update/OAST/fuzz/code/headless/stdin/cross-host redirects disabled. Phase 26
  fuzz, secrets, and Semgrep remain out of scope.
- Policy keeps exact known stock-WordPress/local-HTTP IDs visible. Never suppress
  whole categories. Every high/critical or off-target result fails. Zero scan
  layer, empty result, missing artifact, or zero nuclei evidence also fails.
- Artifacts remain under ignored `tests/.artifacts/security/`; each run records
  target, scanner version, image digest, profile, config hashes, and policy.
- Phase 25 is anonymous/passive. Do not activate Pro, seed real credentials,
  scan external sites, expose DB, or make plugin mounts writable.

## Phase 26 robustness/release/SAST rules

- Public-input robustness uses committed benign corpora for six actual core public input families and exact `http://127.0.0.1:8892/wp-admin/admin-ajax.php`; no target override exists.
- Every runtime seed enforces no PHP fatal/5xx, exact expected status/message, 128 KiB/3 s bounds, unchanged full MariaDB and upload-tree fingerprints, and empty WordPress HTTP/mail audit. Fixtures clean exactly and core/Pro status plus production hashes stay unchanged.
- Core has no raw-body/webhook or multipart public handler. Keep these explicit N/A; never add a production seam or activate Pro for Phase 26.
- Release credential scan packages the exact `build.sh` core shipping manifest into a temporary ignored ZIP without running or changing production build config. Zero archive/text files, blocked findings, stale allowlist rows, or malformed reason/path/fingerprint policy fail.
- Semgrep stays pinned to local Docker image `semgrep/semgrep:1.90.0`, `--network none`, metrics/version checks off. Every PHP/JS rule needs exactly one positive and one negative synthetic fixture; zero rules/files/results fail.
- Full public-input, release ZIP, source Semgrep, and audit runs stay opt-in. Default runs only corpus policy, synthetic release ZIP, Semgrep fixture, and config proofs.
- Production findings remain exact fingerprint rows plus `FIX-PLAN.md` entries. Never fix production during Phase 26.

## Phase 27 endpoint-inventory rules

- `tests/security/endpoints/inventory.json` is sole authoritative family endpoint inventory: core and Pro REST, AJAX, shortcodes, and public listener equivalents.
- Pro is source-parsed/classified only. Never activate or runtime-dispatch Pro in Phase 27.
- Source discovery must resolve current route groups, policies, callback arrays/variables/class constants, and callback existence. Ambiguity, duplicate identity, zero discovery, unsupported direct listener candidates, or missing callback fails closed.
- Exact source/inventory equality is mandatory. Every row declares owner, module, publicness/reachability, entry condition, auth, object scope, side effect, test disposition, runtime status, and file:line evidence.
- Phase 1/2 GET, Phase 3 permission, Phase 10 validation/non-REST, and Phase 18 public manifests remain purpose-specific but must exactly cross-check authoritative rows in Phase 27 lint.
- `DONE`, `THIN`, `PINNED`, and `LEFT` are gap dispositions, not Phase 28 ledger work. Pro source-only rows remain `LEFT` until later family runtime phases.
- Proof-of-catch mutates only derived system-temp source/inventory copies. Production core/Pro source must not change.
- Unauthenticated mutating listener without any request/object guard becomes parked `FIX-PLAN`; never fix production during inventory work.

## Phase 28 family-census rules

- `tests/family/plugins.json` schema v2 is sole family roster. Every plausible direct-child/configured-override plugin checkout must map exactly once to host/add-on/bridge or explicit out-of-family classification.
- Exactly one host exists. Add-ons/bridges name required host, activate later, and cannot form dependency cycles. IDs, slugs, and resolved paths stay unique.
- Header/source versions and declared compatibility sources/ranges must agree. Available checkouts must expose plugin header, classification evidence, branch, and 40-character HEAD commit.
- Required host absence, explicit unavailable overrides, stale rows, bad roles/orders, incompatible versions, and unclassified candidates fail closed.
- Optional absent siblings emit named `FAMILY-CENSUS SKIP` metadata. Pro remains runtime-only/unselectable with `test_tree.status=not-created` until Phase 29.
- Census proof mutates only synthetic system-temp plugin families/registries. Never create Pro tests, symlinks, mounts, activation, or `--env pro` in Phase 28.

## Phase 29 add-on test-tree rules

- Pro test files live only in `wp-social-ninja-pro/tests/`; core owns harness, launchers, Docker, fixture factory, and aggregate summary.
- `tests/addons/pro` is a symlink to Pro `tests/`. Registry path, link target, `addon.json`, four suites/four discovered files, and `routes.json` must agree exactly.
- Default/core environment mounts Pro read-only but keeps it inactive. Canary requires Pro constants, lifecycle hooks, public hooks, and `/pro` REST routes absent with exact `404/rest_no_route`.
- Pro environment activation commands select core then Pro; runtime requires both active and both lifecycle actions fired. WordPress may sort `active_plugins`; dependency boot proof is lifecycle-based.
- All 33 Pro REST declarations require runtime registration and exact anonymous `401/rest_forbidden`; read-only denial is exact `403/rest_forbidden`.
- Pro cases use `WpsrFactory` and real MariaDB. IDs are checked before use; per-case `finally`, full DB/fingerprint cleanup, and zero outbound HTTP/mail remain mandatory.
- PR #324 security registry remains core-owned. Pro runs only through linked-tree family/add-on launchers; unknown/malformed/empty family selectors fail closed.
- Phase 29 proof mutates only derived tree/artifact copies. Broken link, metadata ID/version/order, omitted file, activation absence/order, and meaningful DB assertion must turn red, then restored copies green.
- No Phase 30 sibling sweep or compatibility-pair breadth belongs here.

## Phase 30 sibling-sweep and SKIP-discipline rules

- `tests/family/plugins.json` remains sole family roster. Family execution iterates every row by `activation_order`; hardcoded Pro-only sweep lists are forbidden.
- `bash tests/bin/run-all.sh --family` runs the core host default gate once, then every available linked add-on/bridge once. Default core run need not execute siblings.
- Available plugins require nonzero registered suites/files and nonzero executed tests. Missing required host, unclassified candidate, broken available path/link/tree, duplicate/omitted execution, failed child, or summary drift fails.
- Optional absent siblings emit exactly one registered family-level SKIP with evidence and continue host safely. Available siblings can never SKIP.
- `tests/skips/registry.json` owns every PHP, JS, browser, and family SKIP. Every row requires stable ID, owner, exact suite/test/surface, one allowed reason category, evidence, future expiry, revisit trigger, and exact declaration.
- Runtime PHP/JS skips emit registered IDs and machine events. Undeclared, duplicate, stale, blanket, metadata-mismatched, FIX-PLAN-mismatched, or count-mismatched skips fail.
- Default runtime inventory remains exactly 18 active core known failures; Pro runtime has zero. Opt-in and family declarations remain visible in registry rather than hidden.
- Family sweep emits per-plugin human and JSON summaries plus exact aggregate. Pass/fail/skip must reconcile to total, and executed plugins plus family SKIPs must reconcile to roster.
- Security `--plugin` selection remains OR-within/AND-across and emits exact per-plugin selected/executed summaries without silent targets.
- Phase 30 adds no version-pair compatibility matrix or Phase 31 work.

## Phase 31 compatibility-matrix rules

- `tests/compatibility/matrix.json` is authoritative for Core-alone, current pair, declared boundary, both below-minimum directions, and supported/current WordPress/PHP dimensions.
- Runtime uses fresh Docker projects/volumes and read-only `git archive` fixtures. Never edit live version constants or mount production writable.
- Every runtime cell executes nonzero core lifecycle and REST smoke. Compatible pair dispatches all 33 Pro declarations; free-alone and incompatible cells require exact Pro lifecycle/hook/route absence.
- Incompatible cells must exercise production dependency diagnostics and fail closed before Pro service/route registration. Expected incompatibility is not permission to accept Pro boot.
- Missing refs/images, zero checks/routes, expected/actual mismatch, absent artifacts, silent historical SKIP, fixture residue, or production status/hash drift fail.
- Phase 20 retains strict SQL/timezone invariants. Phase 31 owns plugin version pairing and only records WordPress/PHP dimensions needed to prove supported/current pairing.
- Static default runs compatibility lint/proof only. Full disposable runtime is opt-in through `bash tests/bin/run-all.sh compatibility`.

## Reporting

Report command, exit, case/declaration/skip counts, runtime, protected counts,
raw-lint fixture red, real scan result, proof red/green, changed files, and
blockers. Green suite is hard gate.
