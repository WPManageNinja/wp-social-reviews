# Phase 13 coverage ledger

States: **DONE** behaviorally tested; **THIN** smoke/detector only; **PINNED**
source-contract pin; **LEFT** explicit gap. Coverage is execution evidence, not an
assertion-quality claim.

## Measurement contract

Command: `bash tests/bin/run-all.sh coverage`.

PCOV 1.0.12 runs only in tests-owned Docker image and defaults to
`pcov.enabled=0`. Opt-in tier merges **pure + smoke + permissions + integration**;
no narrow tier is reported as total. Reporter uses static executable-line
analysis with `show_uncovered=true`, so untouched files remain visible at 0%.
Every layer must report nonzero passing/total cases and produce a nonempty capture.
Zero captures, zero PCOV data, zero covered files, missing required tiers, or an
omitted filtered source file fail.

Included first-party PHP:

- all `app/**/*.php`, including first-party views and builder adapters;
- all `boot/**/*.php`, `config/**/*.php`, and `database/**/*.php`;
- `wp-social-reviews.php`, root `index.php`, `language/index.php`, and
  `resources/index.php`.

Explicit exclusions:

- `vendor/**`, `tests/**`, `dev/**`, and `node_modules/**`;
- generated `assets/**` and `builds/**` output;
- `app/Services/Libs/**`, bundled third-party Emogrifier/SimpleDom code;
- WP Social Ninja Pro checkout.

Ignored artifacts:

- `tests/.artifacts/coverage/raw/*.pcov`
- `tests/.artifacts/coverage/report/summary.json`
- `tests/.artifacts/coverage/report/clover.xml`
- `tests/.artifacts/coverage/report/coverage.txt`
- `tests/.artifacts/coverage/report/html/index.html`

## Baseline and final

| Metric | Pre-gap baseline | Phase 13 final |
|---|---:|---:|
| First-party PHP files in report | 256 | 256 |
| Files with covered executable lines | 100 | 103 |
| Files with no covered lines | 156 | 153 |
| Covered / executable lines | 10,082 / 39,886 | 10,232 / 39,883 |
| Line percentage | 25.28% | 25.66% |
| Executable files at 0% | 143 | 140 |
| Physical >100-line files at 0% | 58 | 55 |

Fresh static analysis moved executable denominator by -3 with production bytes
unchanged. No percentage threshold is a gate. Added tests target three safe,
customer-relevant gaps and add 150 measured covered lines; breadth and regression
value outrank percentage.

## Every baseline physical >100-line file at 0%

Required Phase 13 categories: **HIGH-VALUE ADD TEST NOW**,
**REMOTE/EXTERNAL INTEGRATION DEFERRED**, **VIEW/TEMPLATE/BROWSER-OWNED**,
**DEAD/LEGACY CANDIDATE**, **PRO/THIRD-PARTY-OWNED**, and
**UNSAFE/DESTRUCTIVE**. No file was labeled dead/legacy without evidence; count is
zero.

| Baseline physical / executable | File | Category | Explicit disposition |
|---:|---|---|---|
| 1760 / 1595 | `app/Services/Platforms/Feeds/Twitter/Config.php` | VIEW/TEMPLATE/BROWSER-OWNED | Large editor default/config catalog. Keep in denominator; cover via editor/browser contracts, not literal-by-literal Phase 13 assertions. |
| 1605 / 1580 | `app/Services/Platforms/Feeds/Facebook/Config.php` | VIEW/TEMPLATE/BROWSER-OWNED | Large Facebook editor default/config catalog. Browser/editor owner; no remote graph fabricated. |
| 1602 / 1577 | `app/Services/Platforms/Feeds/Instagram/Config.php` | VIEW/TEMPLATE/BROWSER-OWNED | Large Instagram editor default/config catalog. Browser/editor owner. |
| 1019 / 856 | `app/Services/Widgets/TwitterWidget.php` | PRO/THIRD-PARTY-OWNED | Elementor adapter requires third-party builder runtime; defer until real builder axis. |
| 873 / 713 | `app/Services/Widgets/ReviewsWidget.php` | PRO/THIRD-PARTY-OWNED | Elementor adapter; no fake Elementor classes. |
| 871 / 721 | `app/Services/Widgets/FacebookFeedWidget.php` | PRO/THIRD-PARTY-OWNED | Elementor adapter; no fake builder runtime. |
| 816 / 651 | `app/Services/Widgets/YoutubeWidget.php` | PRO/THIRD-PARTY-OWNED | Elementor adapter; defer to real builder integration. |
| 800 / 606 | `app/Hooks/Handlers/AdminMenuHandler.php` | VIEW/TEMPLATE/BROWSER-OWNED | Admin menu/assets/localization feed SPA. Phase 11 browser owns rendered admin behavior; capability registration needs separate focused round. |
| 728 / 591 | `app/Services/Widgets/InstagramWidget.php` | PRO/THIRD-PARTY-OWNED | Elementor adapter; no fake builder runtime. |
| 596 / 491 | `app/Services/Widgets/Oxygen/FacebookWidget.php` | PRO/THIRD-PARTY-OWNED | Oxygen adapter requires real third-party builder. |
| 583 / 461 | `app/Services/Widgets/Oxygen/TwitterWidget.php` | PRO/THIRD-PARTY-OWNED | Oxygen adapter requires real third-party builder. |
| 545 / 304 | `app/Services/Platforms/Feeds/Common/FeedFilters.php` | HIGH-VALUE ADD TEST NOW | Added local Twitter ordering/limit/header/non-mutation behavior in `tests/integration/coverage-gaps.php`; final 104/304 (34.21%). |
| 490 / 381 | `app/Services/Widgets/Oxygen/InstagramWidget.php` | PRO/THIRD-PARTY-OWNED | Oxygen adapter; defer until builder installed. |
| 486 / 230 | `app/Hooks/Handlers/ReviewsTemplateHandler.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public shortcode/template orchestration. Phase 13 avoids starting Phase 18 public-handler breadth; existing rendered fragments remain credited elsewhere. |
| 433 / 155 | `app/Services/Platforms/Feeds/Twitter/TwitterApi.php` | REMOTE/EXTERNAL INTEGRATION DEFERRED | OAuth/API transport and remote response lifecycle; outbound blocked and no fake provider application. |
| 431 / 181 | `app/Hooks/Handlers/TwitterTemplateHandler.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public Twitter template orchestration; defer to public/browser rendering tier. |
| 427 / 317 | `app/Services/Widgets/Oxygen/YouTubeWidget.php` | PRO/THIRD-PARTY-OWNED | Oxygen adapter requires real builder. |
| 423 / 142 | `app/Services/Platforms/Feeds/Instagram/SingleFeed.php` | REMOTE/EXTERNAL INTEGRATION DEFERRED | Instagram remote fetch/token/cache path; no real HTTP or credential fixture. |
| 417 / 391 | `app/Services/Widgets/Beaver/Facebook/FacebookWidget.php` | PRO/THIRD-PARTY-OWNED | Beaver Builder module adapter; no fake builder classes. |
| 372 / 345 | `app/Services/Widgets/Beaver/Twitter/TwitterWidget.php` | PRO/THIRD-PARTY-OWNED | Beaver Builder module adapter. |
| 324 / 299 | `app/Services/Widgets/Beaver/Reviews/ReviewsWidget.php` | PRO/THIRD-PARTY-OWNED | Beaver Builder module adapter. |
| 320 / 219 | `app/Services/Widgets/Oxygen/ReviewsWidget.php` | PRO/THIRD-PARTY-OWNED | Oxygen adapter requires real builder. |
| 287 / 261 | `app/Services/Widgets/Beaver/YouTube/YouTubeWidget.php` | PRO/THIRD-PARTY-OWNED | Beaver Builder module adapter. |
| 283 / 257 | `app/Services/Widgets/Beaver/Instagram/InstagramWidget.php` | PRO/THIRD-PARTY-OWNED | Beaver Builder module adapter. |
| 283 / 82 | `app/Services/Integrations/FluentPlayer/FluentPlayerEmbed.php` | PRO/THIRD-PARTY-OWNED | FluentPlayer integration requires sibling plugin runtime and its embed contracts. |
| 255 / 146 | `app/Hooks/Handlers/FacebookFeedTemplateHandler.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public Facebook template/render orchestration; Phase 18/public breadth deferred. |
| 236 / 91 | `app/Hooks/Handlers/InstagramTemplateHandler.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public Instagram template/render orchestration. |
| 235 / 87 | `app/Hooks/CLI/Commands.php` | UNSAFE/DESTRUCTIVE | Maintenance/import CLI mutates broad plugin state. No command run against shared lab baseline during gap fill. |
| 227 / 101 | `app/Services/Platforms/Feeds/Instagram/AccountFeed.php` | REMOTE/EXTERNAL INTEGRATION DEFERRED | Account feed requires Instagram API/token/cache response graph. |
| 223 / 117 | `app/Hooks/Handlers/YoutubeTemplateHandler.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public YouTube template/render orchestration. |
| 215 / 153 | `app/Views/public/reviews-templates/header.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public fragment with template-variable matrix; defer to full public render contract, not synthetic variable stubs. |
| 205 / 71 | `app/Views/email/report/body.php` | VIEW/TEMPLATE/BROWSER-OWNED | Scheduled email view; mail remains blocked and scheduler/email workflow belongs later background/public work. |
| 195 / 112 | `app/Services/Widgets/Beaver/Facebook/includes/frontend.css.php` | PRO/THIRD-PARTY-OWNED | Beaver dynamic CSS fragment; real builder rendering owns it. |
| 191 / 113 | `app/Http/Controllers/Platforms/AddonController.php` | UNSAFE/DESTRUCTIVE | Installs/downloads/activates plugins. Real network/filesystem/global activation forbidden. |
| 190 / 70 | `app/Views/public/feeds-templates/facebook/template1.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public Facebook view fragment; defer to full rendered feed. |
| 175 / 82 | `app/Views/public/reviews-templates/template1.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public review layout fragment; browser/public rendering owner. |
| 169 / 79 | `app/Views/public/reviews-templates/template2.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public review layout fragment; browser/public rendering owner. |
| 167 / 92 | `app/Services/Widgets/Beaver/Twitter/includes/frontend.css.php` | PRO/THIRD-PARTY-OWNED | Beaver dynamic CSS fragment. |
| 161 / 78 | `app/Views/public/reviews-templates/template4.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public review layout fragment. |
| 160 / 78 | `app/Views/public/reviews-templates/template5.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public review layout fragment. |
| 155 / 79 | `app/Views/public/reviews-templates/template3.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public review layout fragment. |
| 152 / 75 | `app/Views/public/feeds-templates/youtube/header.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public YouTube header fragment. |
| 149 / 75 | `app/Services/Platforms/Reviews/AirbnbHelper.php` | REMOTE/EXTERNAL INTEGRATION DEFERRED | Scrapes mutable Airbnb HTML through bundled parser and real HTTP; outbound-only mock cannot prove provider contract. |
| 143 / 71 | `app/Services/Platforms/Feeds/Instagram/RefreshToken.php` | REMOTE/EXTERNAL INTEGRATION DEFERRED | Token refresh/remote lifecycle requires credentials and provider response. |
| 141 / 92 | `app/Views/public/feeds-templates/facebook/header.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public Facebook header fragment. |
| 139 / 81 | `app/Services/Widgets/Beaver/Reviews/includes/frontend.css.php` | PRO/THIRD-PARTY-OWNED | Beaver dynamic CSS fragment. |
| 136 / 46 | `database/Migrations/ReviewsMigrator.php` | HIGH-VALUE ADD TEST NOW | Added real MariaDB scratch-table create/schema/index/idempotent-rerun test; final 33/43 (76.74%). |
| 131 / 57 | `app/Services/Onboarding/OnboardingHelper.php` | UNSAFE/DESTRUCTIVE | Fixed global session option and onboarding workflow state. Explicitly deferred; Phase 14 workflow breadth not started. |
| 124 / 88 | `app/Views/public/chat-templates/elements/channels.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public chat fragment with channel/config matrix. |
| 122 / 61 | `app/Hooks/Handlers/UninstallHandler.php` | UNSAFE/DESTRUCTIVE | Global uninstall/delete path. Never execute for coverage. |
| 120 / 68 | `app/Services/Widgets/Beaver/YouTube/includes/frontend.css.php` | PRO/THIRD-PARTY-OWNED | Beaver dynamic CSS fragment. |
| 120 / 66 | `app/Services/Widgets/Beaver/Instagram/includes/frontend.css.php` | PRO/THIRD-PARTY-OWNED | Beaver dynamic CSS fragment. |
| 118 / 71 | `app/Services/Widgets/ElementorWidget.php` | PRO/THIRD-PARTY-OWNED | Elementor bootstrap adapter requires real Elementor runtime. |
| 108 / 52 | `app/Views/public/feeds-templates/instagram/template1.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public Instagram layout fragment. |
| 106 / 70 | `app/Views/public/feeds-templates/instagram/header.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public Instagram header fragment. |
| 105 / 43 | `app/Views/public/feeds-templates/twitter/template1.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public Twitter layout fragment. |
| 103 / 35 | `app/Services/Platforms/Feeds/Instagram/OEmbed.php` | HIGH-VALUE ADD TEST NOW | Added exact wrapper/provider/TTL/non-target rendering assertions; final 14/35 (40.00%). |
| 101 / 68 | `app/Views/public/chat-templates/elements/channels-button.php` | VIEW/TEMPLATE/BROWSER-OWNED | Public chat button fragment; browser/public owner. |

Triage counts: **3 high-value add now**, **5 remote/external deferred**,
**24 view/template/browser-owned**, **0 dead/legacy candidate**,
**22 Pro/third-party-owned**, **4 unsafe/destructive** = **58**.

## Phase 13 behavioral additions and proof-of-catch

| Surface | State | Evidence | Proof-of-catch |
|---|---|---|---|
| Reviews schema/index migration | DONE | `tests/integration/coverage-gaps.php`; scratch table only; exact critical columns/default/nullability, seven index column lists, rerun identity, exact drop cleanup | Temporary create-SQL mutant removed `id` from `idx_review_time`; focused run red with two exact index-map failures. SHA-256 `83b92b25…` restored; focused 3/3 green. |
| Local Twitter feed ordering/limit | DONE | Same file; exact newest-first IDs, desktop limit, header, resize state, latest flag, caller non-mutation | Temporary ascending multiplier inversion returned `old,middle`; focused run red. SHA-256 `e5291200…` restored; green. |
| Instagram oEmbed rendering/provider | DONE | Same file; exact wrapper/classes, non-target identity, provider endpoint, existing provider preservation, TTL | Temporary wrapper-class mutant produced `wpsr-oembed-wrapper-mutant`; focused run red. SHA-256 `fde57f3c…` restored; green. |
| Coverage tooling false-green guards | DONE | `tests/coverage/fixtures/*`, `self-test-run.php`, `coverage-bootstrap.php`, `run-coverage.php`, `run-coverage.sh` | Tool self-test rejects zero captures with exit 2, rejects a `0/0` layer summary, and reports executed fixture `1/1` plus untouched fixture `0/1` with `show_uncovered=true`. |

Added runtime discovery: **1 integration file, 3 cases, 22 assertion/guard
sites**. Final integration tier: **44/51 passed, 0 failed, 7 existing skips**.
No new production finding or KNOWN-FAILURE skip surfaced.
