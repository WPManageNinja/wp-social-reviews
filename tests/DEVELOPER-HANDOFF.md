# WP Social Ninja Production-Fix Handoff

Automated test suite is merged into Core and Pro `development`. Test-suite setup is complete. Remaining work is production defect remediation.

## Start Here

Read these files before changing production code:

- `tests/FIX-PLAN.md` — production defects and exact expected behavior
- `tests/FIX-AGENT.md` — production-fix workflow
- `tests/README.md` — runner commands
- `tests/AGENT.md` — test contracts and constraints
- `tests/COVERAGE-LEDGER.md` — coverage inventory

## Required Workflow

For each `FIX-PLAN` item:

1. Create a production-fix branch from current `development`.
2. Run the listed reproduction and confirm it fails.
3. Fix production code only where necessary.
4. Remove or update known-failure parking only after the test passes for the correct reason.
5. Run the focused affected suite.
6. Run the static gate.
7. Verify proof-of-catch still rejects the corresponding intentional mutation.
8. Update `tests/FIX-PLAN.md` status and evidence.
9. Keep Core and Pro compatibility synchronized.
10. Submit small PRs grouped by defect family where practical.

Do not weaken assertions, broaden accepted fingerprints, add skips, or change expected behavior merely to make tests green.

## Core 4.4.0 with Pro 4.3.1 — investigated, not a defect to fix

This was previously listed here as the highest-priority defect. It is not. See
`compatibility-core-pro-fail-closed-boot` in `tests/FIX-PLAN.md` for the full
investigation; the summary is:

- No breakage exists. All 43 Core classes Pro 4.3.1 references still exist, and the two
  `app/` methods that changed since `4.3.2` only gained optional parameters.
- The prescribed remediation is not implementable. `tests/compatibility/runtime-cell.php:54`
  asserts the lifecycle action fires for every cell and `:61` asserts Pro stays in
  `active_plugins`, so neither withholding `wp_social_reviews_loaded_v2` nor deactivating
  Pro satisfies the contract.
- `wp_social_reviews_loaded_v2` is shared with the free `custom-feed-for-tiktok` plugin,
  so withholding it would disable an unrelated shipped plugin.

Shipping the gate would break a combination that currently works, for exactly the users
whose licence lapsed. Do not start work here unless someone demonstrates real breakage, or
the owner of `WPSOCIALREVIEWS_PRO_MIN_VERSION` confirms hard-blocking is intended policy.

## Core Validation

```bash
bash tests/bin/run-all.sh static
bash tests/bin/run-all.sh php
bash tests/bin/run-all.sh --family
```

## Pro Validation

Run from the Core checkout with the Pro sibling checkout on `development`:

```bash
bash tests/lab/scripts/php-tests.sh --env pro tests/bin/run-addon.php plugin=pro
bash tests/lab/scripts/php-tests.sh --env pro tests/bin/run-family-canary.php
```

## Expensive Tiers

Run only when relevant to the production change:

```bash
bash tests/bin/run-all.sh browser
bash tests/bin/run-all.sh e2e
bash tests/bin/run-all.sh coverage
bash tests/bin/run-all.sh mutation
bash tests/bin/run-all.sh security
bash tests/bin/run-all.sh dast
bash tests/bin/run-all.sh fuzz
```

## Completion Criteria

A production-fix pass is complete when:

- Applicable focused failures are green.
- No registered known failure remains for fixed behavior.
- Static gate passes.
- Family census and linked Pro tree pass.
- Proof-of-catch remains red against intentional mutants.
- No new skip or weakened assertion was introduced.
- `tests/FIX-PLAN.md` and `tests/COVERAGE-LEDGER.md` match current state.
