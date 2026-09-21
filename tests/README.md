# WP Social Ninja test suite

Core repository hosts family test harness; Pro contributes linked add-on tests from
`../wp-social-ninja-pro/tests/`. Paired add-on source is [Pro PR #71](https://github.com/WPManageNinja/wp-social-ninja-pro/pull/71)
on branch `development`; Core static contracts bind Pro metadata back
to Core PR #325 at minimum harness commit `d9cef50cef56711b2dd6e7084ab1ca082470e547`.
Suite covers static drift, REST permissions and behavior, real-database integration,
browser/E2E flows, coverage, throughput, mutation, hostile environments, security scanning,
family sweeping, and compatibility.

Current production defects and proposed fixes live only in `FIX-PLAN.md`. Coverage status,
gaps, and file evidence live in `COVERAGE-LEDGER.md`. Operational commands and safety rules
remain below; machine-readable inventories live beside their runners.

## Safety contract

- Only `tests/lab/` WordPress is used. Host/dev WordPress is never discovered.
- DB name must contain `test`; table prefix must not be `wp_`.
- Web publishes on `127.0.0.1` only; DB publishes no host port.
- Core checkout is dual-mounted read-only at `/workspace/wp-social-ninja` and
  `/var/www/html/wp-content/plugins/wp-social-ninja`; Pro is dual-mounted read-only
  at matching `wp-social-ninja-pro` paths but stays inactive in core environment.
- `DISABLE_WP_CRON` is true. HTTP and mail are blocked at WordPress boundaries; Phase 14 may return deterministic responses only for exact asserted intents.
- Six protected plugin-table counts match before/after every data-writing runtime tier.
- WordPress `posts`, `postmeta`, `users`, and `options` count/checksum fingerprints also match there.
- Serialized WordPress cron schedule matches before/after every data-writing runtime tier.
- Pure tier uses minimal WP bootstrap, no fixtures/models/query builders, and deletes its four test-owned rate-limit transients/timeouts exactly.
- Throughput capture uses real `$wpdb` `query` filters only during measured operations; setup/verification/cleanup SQL is excluded. Timings never gate.
- Browser and E2E processes use lab-mounted mu-plugin guards for HTTP/mail and block all
  non-loopback browser requests. Dedicated ephemeral admins and owned fixtures clean
  with protected/post/user/option fingerprints and serialized cron unchanged.
- E2E lab inspector is token-scoped, read-only except exact marker-owned failure cleanup,
  and never replaces UI as main action. Every complete flow audit remains HTTP `0`, mail `0`.
- Fresh-WordPress admin/public housekeeping is warmed before E2E fingerprints with a
  temporary admin removed before capture. Only exact blocked WordPress update-check
  intents are classified in preflight; no such intent is accepted during flows.
- Permission requests use runner-owned IDs and a controller-dispatch fuse; full
  database checksums must remain unchanged before fixture cleanup.
- Environment axes capture and restore protected tables, posts/postmeta/users/options,
  cron, activation rows, timezone rows, and global SQL mode exactly. Recovery command
  prints before configuration; canaries repeat inside every PHP process.
- Low-privilege identity uses a temporary read-only role with every plugin
  capability explicitly absent; user, role, and fixtures are removed in `finally`.
- Phase 25 scanners share WordPress's network namespace and accept only exact
  `http://127.0.0.1:80`; no arbitrary target override exists. ZAP stays passive;
  nuclei disables updates, OAST, fuzz, code, headless, stdin, and redirects.
- DAST artifacts include version/image digest/config hashes/target and policy
  output under ignored `tests/.artifacts/security/`. Any high/critical finding,
  off-target result, zero scan layer, missing result, or missing artifact fails.
- Phase 26 public-input requests use only exact loopback admin-ajax, committed benign
  seeds, owned fixtures, 3-second/128-KiB bounds, full DB/upload fingerprints, and
  per-case empty HTTP/mail audit. Core/Pro status and production hashes are guarded.
- Release scan builds only an ignored temporary ZIP from exact `build.sh` shipping
  paths. Allowlist rows require exact rule/path/fingerprint plus substantive reason.
- Semgrep runs pinned local Docker `1.90.0` with no network/metrics. Default executes
  positive/negative fixtures only; source packs remain opt-in.

## Run

```bash
bash tests/lab/scripts/setup.sh
bash tests/bin/run-all.sh static
bash tests/bin/run-all.sh pure
bash tests/bin/run-all.sh js
bash tests/bin/run-all.sh smoke
bash tests/bin/run-all.sh permissions
bash tests/bin/run-all.sh integration
bash tests/bin/run-all.sh

# Runtime environments. Core is default; Pro runs core host + registered Pro tests.
bash tests/bin/run-all.sh --env core
bash tests/bin/run-all.sh --env pro
bash tests/bin/run-all.sh addon --env pro
# Exact all-four-suite proof must report: PRO-ADDON: 41/41 passed, 0 failed
bash tests/lab/scripts/php-tests.sh --env pro tests/bin/run-addon.php plugin=pro

# Family tier: core host once, then every available registry sibling once.
bash tests/bin/run-all.sh --family
bash tests/bin/run-all.sh family

# Disposable version-pair matrix:
bash tests/bin/run-all.sh compatibility
bash tests/bin/run-compatibility-matrix.sh --list
bash tests/bin/run-compatibility-matrix.sh core-4-4-pro-4-4-current
# Known production defect remains executable red/nonzero; never convert to SKIP/PASS:
bash tests/bin/run-compatibility-matrix.sh core-4-4-rejects-pro-4-3-1
php tests/compatibility/lint.php
php tests/compatibility/test-proof-of-catch.php

php tests/family/sweep.php --lint
php tests/skips/lint.php
php tests/family/test-sweep-proof.php
bash tests/lab/scripts/php-tests.sh --env pro tests/bin/run-addon.php plugin=pro filter=routes

# Opt-in deterministic query-count/batch-size tier
bash tests/bin/run-all.sh throughput
bash tests/lab/scripts/php-tests.sh tests/bin/run-throughput.php filter=high-volume-reads

# Opt-in browser tier (tests-local Playwright; builds current SPA into ignored test artifact)
bash tests/bin/run-all.sh browser

# Opt-in real-UI flows (6 total)
bash tests/bin/run-all.sh e2e
bash tests/bin/run-e2e.sh filter=review-moderation
bash tests/bin/run-e2e.sh filter=review-category-lifecycle
bash tests/bin/run-e2e.sh filter=review-filtering-search
bash tests/bin/run-e2e.sh filter=translation-settings-persistence

# Opt-in public/unauthenticated handler tier
bash tests/bin/run-all.sh public
bash tests/lab/scripts/php-tests.sh tests/bin/run-public.php filter=public-handlers

# Opt-in merged PHP coverage (builds PCOV in Docker; emits ignored artifacts)
bash tests/bin/run-all.sh coverage

# Opt-in mutation audit; filters remain deterministic
bash tests/bin/run-all.sh mutation
php tests/bin/run-mutation.php --filter=M001,M002
php tests/bin/run-mutation.php --self-test-only

# Security registry, endpoint inventory, and family census
# Repeat or comma-separate values: OR within dimension, AND across dimensions.
tests/bin/security --list
tests/bin/security --list --module database --module mutation
tests/bin/security --list --module=database,mutation
tests/bin/security --list --module public-handlers --category authorization
tests/bin/security --category tooling-self-test
tests/bin/security --plugin core --category authorization
bash tests/bin/run-all.sh security --module database
tests/bin/security --module endpoint-inventory
# Family/add-on targets use family runner; PR #324 registry remains core-owned.
php tests/family/sweep.php --lint
php tests/security/endpoints/lint.php
php tests/security/endpoints/proof-of-catch.php
php tests/family/census.php
php tests/family/test-proof-of-catch.php

# Detector CLI and fixture-copy harness:
php tests/lint/detect_remote_auth_hmac.php
php tests/lint/detect_remote_auth_hmac.php --source-root=/path/to/core-copy
php tests/lint/detect_remote_auth_hmac.php \
  --source-file=app/Services/Platforms/RemoteAuth.php=/tmp/RemoteAuth.php

# Unified Phase 24 proof: 10 fixed-green/incomplete-red/mutated-red cases + 4 self-tests.
php tests/bin/run-source-contract-fixtures.php

# Phase 25 Docker safety + loopback-only DAST.
tests/bin/security doctor
tests/bin/security doctor --config-only
tests/bin/security self-test
tests/bin/security dast zap
tests/bin/security dast nuclei
tests/bin/security dast all
tests/bin/security fuzz audit
tests/bin/security secrets audit
tests/bin/security semgrep fixtures
tests/bin/security semgrep all
tests/bin/security audit
bash tests/bin/run-all.sh dast
bash tests/bin/run-all.sh fuzz
bash tests/bin/run-all.sh secrets
bash tests/bin/run-all.sh semgrep
bash tests/bin/run-all.sh audit
```

Focused route/inventory lints (static; no WordPress or database):

```bash
php tests/smoke/route-coverage.php
php tests/validation/route-coverage.php
node tests/browser/route-coverage.js
```

Focused smoke:

```bash
bash tests/lab/scripts/php-tests.sh tests/bin/run-smoke.php filter=templates
```

Focused permission smoke:

```bash
bash tests/lab/scripts/php-tests.sh tests/bin/run-permissions.php filter=templates
```

Focused pure file (unknown/zero selection fails):

```bash
bash tests/lab/scripts/php-tests.sh tests/bin/run-pure.php filter=deterministic-helpers
```

Focused JavaScript family (unknown/zero selection fails):

```bash
bash tests/bin/run-js.sh filter=request-layer
bash tests/bin/run-js.sh filter=admin-utils
bash tests/bin/run-js.sh filter=state-errors
```

Focused integration file (unknown/zero selection fails):

```bash
bash tests/lab/scripts/php-tests.sh tests/bin/run-integration.php filter=input-validation-authorization
```

Reset disposable lab:

```bash
bash tests/lab/scripts/reset.sh --yes
```

## Raw-SQL detector proof

Intentional fixture command must exit 1:

```bash
php tests/lint/raw-sql-prefix.php tests/lint/fixtures
```

Real core scan must exit 0 unless finding is parked in `FIX-PLAN.md`:

```bash
php tests/lint/raw-sql-prefix.php app boot database
```

## Layout

- `suite.config.php`: plugin/framework/database wiring
- `family/plugins.json`: authoritative schema-v2 host/add-on/bridge/out-of-family roster, paths, runtime policy, compatibility, activation, test-tree and missing-sibling contracts
- `family/census-lib.php`, `family/census.php`: fail-closed direct-child/configured-override discovery, exact classification, source/version/VCS/dependency validation, and named optional SKIP metadata
- `family/test-proof-of-catch.php`: derived fixture cases proving registration, repository/branch/ref identity, dependency, version, role/order, and missing-sibling failures without production mutation
- `addons/pro`: symlink to Pro-owned `wp-social-ninja-pro/tests/`; four callable suites plus 33-row REST manifest
- `family/test-tree-lib.php`, `family/test-trees.php`: exact registry/link/metadata/file/route/fixture/activation drift validation
- `family/test-tree-proof.php`: derived link/metadata/commit/file/activation/runtime mutations plus restored green checks
- `family/host.json`, `bin/run-host-static.php`: canonical eight-suite/30-file host default registration, non-recursive static commands, and summary labels
- `family/sweep-lib.php`, `family/sweep.php`: activation-ordered all-roster planning/execution, optional SKIP, per-plugin JSON, and exact aggregate reconciliation
- `family/test-sweep-proof.php`: direct parser/artifact catches for omission, duplication, malformed events, static/public failures, zero tests, SKIP metadata, and summary drift
- `skips/registry.json`, `skips/skip-lib.php`, `skips/lint.php`: authoritative strict SKIP inventory, active known-failure discovery, exact 0-default count, one optional-family declaration, and stable source/evidence/expiry drift lint
- `bin/run-family-canary.php`, `bin/run-addon.php`: mirrored core/Pro activation canary and generic registry-owned linked add-on runner
- `lib/harness.php`: REST dispatch, diagnostics, DB/cron guards, outbound mocks
- `smoke/routes.manifest.php`: 27 declarations plus real query variants
- `smoke/route-coverage.php`: adjacent source-to-manifest GET coverage detector
- `permissions/routes.manifest.php`: 66 mutating declarations with valid fixture shapes
- `permissions/route-coverage.php`: adjacent mutating-route/policy drift detector
- `round-trips/routes.manifest.php`: all 66 mutating declarations classified for Phase 9
- `round-trips/cases.manifest.php`: 13 required Phase 9 runtime families
- `round-trips/route-coverage.php`: mutating inventory/case/material-family/zero-discovery contract
- `validation/routes.manifest.php`: all 93 REST declarations classified by input family/disposition
- `validation/non-rest.manifest.php`: shortcode/AJAX/filename/upload deferment inventory
- `validation/cases.manifest.php`: 13 required Phase 10 runtime cases, including five parked findings
- `validation/route-coverage.php`: REST drift/material-family/runtime-reference/zero-discovery gate
- `browser/screens.manifest.js`: all 48 Vue-router leaf screens, real component markers, fixture tokens, and explicit skips
- `browser/route-coverage.js`: source router-to-screen/component/selector/zero-discovery drift gate
- `browser/run-smoke.js`: one-session hash-router Playwright mount/error/asset guard
- `bin/run-browser.sh`: ephemeral identity/fixtures, current-source test build, guard proof, cleanup/fingerprint launcher
- `e2e/flows.manifest.js`: exactly six source/selector-mapped flows, Phase 17=3 and Phase 21=3
- `e2e/flow-coverage.js`: exact per-phase counts, zero-discovery, ID, source/action drift gate
- `e2e/run-flows.js`: real Vue actions plus exact lab DB/public-render/no-op assertions
- `e2e/fixtures.php`: ephemeral admin, local reviews/settings, exact fingerprints and cleanup
- `bin/run-e2e.sh`: separate-process guard proof, Playwright orchestration, audit and teardown
- `public/handlers.manifest.php`: all core nopriv AJAX, shortcode, redirects, REST/rewrite absence, frontend mappings, and Pro-only classifications
- `public/handler-coverage.php`: source-to-inventory/zero-discovery drift plus all-93-REST-policy gate
- `public/public-handlers.php`: wrong-nonce full no-ops, object visibility, scoped/escaped reads, temp-file guard, rate budget, and redirect behavior
- `bin/run-public.php`: core-alone anonymous public runtime runner with exact cleanup
- `bin/run-permissions.php`: real-router anonymous and low-privilege denial runner
- `bin/run-pure.php`: sorted, zero-discovery-failing minimal-WP deterministic-helper runner
- `pure/deterministic-helpers.php`: table-driven production helpers, structural random/time assertions, non-mutation, and parked findings
- `js/`: isolated Vitest package/config, source inventory, zero-result verifier, and real-module request/state/utility specs
- `bin/run-js.sh`: exact-filter Vitest launcher; parses executed counts and rejects exit-0 zero-test summaries
- `bin/run-integration.php`: sorted, zero-discovery-failing real-model runner
- `bin/run-throughput.php`: opt-in sorted, zero-discovery-failing real-SQL throughput runner
- `lib/throughput.php`: narrow query capture, normalized fingerprints, count/growth and structural statement-cap assertions
- `throughput/`: two-size review/list/aggregate, bulk-write, migration, cache/image, sync, and serialization guards/findings
- `bin/run-coverage.sh`: opt-in Docker PCOV orchestration for pure + smoke + permissions + integration
- `bin/coverage-bootstrap.php`: starts capture before WordPress/plugin bootstrap and writes one raw tier capture
- `bin/run-coverage.php`: merges captures and emits uncovered-inclusive text, Clover, HTML, and JSON reports
- `coverage/`: locked reporter dependency plus executed/untouched coverage self-test fixtures
- `COVERAGE-LEDGER.md`: source policy, baseline/final metrics, every >100-line 0% disposition, and gap-test evidence
- `mutation/manifest.php`: deterministic source-traceable high-risk PHP mutants and narrow-tier ownership
- `mutation/Runner.php`, `mutation/self-test.php`: one-at-a-time mutation engine, full-suite survivor correction, restore/status guards, and fixture proofs
- `mutation/baseline.json`, `mutation/results.json`: committed Phase 19 baseline, per-mutant evidence, outcomes, hashes, runtimes, and survivor list
- `bin/run-mutation.php`: opt-in Phase 19 launcher; ignored latest artifacts live under `tests/.artifacts/mutation/`
- `compatibility/axes.php`, `compatibility/Environment.php`: Phase 20 axis registry and process-local strict/timezone/free canaries
- `compatibility/state.php`: exact lab baseline capture, timezone restore, activation/options/cron/table verification
- `compatibility/axes.php`: retained hostile-environment axis definitions used by compatibility canaries; no standalone `environment` selector is exposed
- `compatibility/matrix.json`, `matrix-lib.php`, `lint.php`: Phase 31 authoritative family version-pair cells, source constraints, dimensions, artifact refs, outcomes, and drift gate
- `compatibility/runtime-cell.php`, `bin/run-compatibility-matrix.sh`: disposable read-only fixture Docker boot, lifecycle/diagnostic/core REST/all-33-Pro-route smoke, exact per-cell artifacts, cleanup, and production hash/status guards
- `compatibility/test-proof-of-catch.php`: direct schema/repository/runtime-parser red proofs for missing, duplicate, malformed, foreign, mismatched, silent, zero, and residue failures
- `security/test-metadata.json`: 37-target reviewed Phase 22–27 core security metadata registry; family targets run through family/compatibility gates
- `lint/detect_*.php`, `lint/source-contract-lib.php`: 10 fail-closed method/structure/order detectors with alternate-source CLI
- `lint/source-contract-fixtures.php`, `lint/fixtures/<detector-id>/fixed/`: exact Phase 24 case/mutation manifest and minimal faithful guarded-source copies
- `bin/run-source-contract-fixtures.php`: unified fixed-green/incomplete-red/mutated-red harness with four self-tests, ephemeral cleanup, exact status, and production-hash guards
- `lint/known-failure-inert-feed-html.php`: registered static gate proving public and admin `sanitizeHtml` parse untrusted feed HTML in an inert `<template>`
- `security/lib/registry.php`, `security/run.php`: shared validation, selector algebra, deterministic ordering, and nonzero-count execution
- `security/validate-registry.php`, `security/registry-self-test.php`: filesystem ownership, exact `--list` expectations, and green-to-red registry proofs
- `bin/security`: unified selector launcher plus Phase 25 `doctor`, `self-test`, `dast`, and `audit` verbs
- `security/lab/doctor.php`: 32 config/47 live isolation checks, including dual read-only Pro mounts; derived-model mode exists only for proof harness
- `security/lab/self-test.php`: safe-green plus ten DB/bind/core+Pro-mount/database/target/health/tool red proofs
- `security/dast/`: target refusal, ZAP policy, nuclei safe templates, exact allowlist, policy self-test, provenance writer
- `bin/run-zap-baseline.sh`, `bin/run-nuclei-baseline.sh`: pinned scanner/container policy launchers
- `security/fuzz/`: six-family/23-seed loopback public-input corpus, owned WP fixtures, policy self-test, exact DB/upload/outbound/fatal/size/time guards
- `bin/run-fuzz.sh`: opt-in robustness launcher with exact core/Pro status and production-hash guards
- `security/secrets/`: deterministic scan-only release packager, private-key/assignment/token scanner, exact allowlist, committed benign fixture, synthetic proofs
- `bin/run-release-secrets.sh`: temporary release-ZIP audit and self-test launcher; generated ZIP/reports stay ignored
- `security/semgrep/`: 10 project-local PHP/JS rules, 10 positive/negative fixtures, exact source triage policies, verifier proof
- `bin/run-semgrep.sh`: pinned offline Docker fixture/security/quality pack launcher
- `security/audit.sh`: full doctor, fastest registry slice, release scan, Semgrep, public-input robustness, ZAP, then nuclei with nonzero layer artifacts
- `security/endpoints/inventory.json`: sole authoritative 153-row core+Pro REST/AJAX/shortcode/listener source inventory
- `security/endpoints/discovery.php`, `lint.php`: fail-closed parser, callback/policy/evidence validation, exact 1:1 source drift, and old-manifest consistency
- `security/endpoints/proof-of-catch.php`: nine derived source/inventory red proofs plus restored fixture/live green
- `lib/factory.php`: exact-owned review/post/category/cache/image/option/cron/user/role fixtures
- `integration/`: filtering, state machines, shared-table isolation, aggregates, domain invariants, round trips, validation/authorization, coverage gaps, and background automation
- `lab/`: disposable Docker WordPress/MariaDB/PHP runner
- `AGENT.md`: test-writing rules
- `FIX-AGENT.md`: separate future production-fix rules
