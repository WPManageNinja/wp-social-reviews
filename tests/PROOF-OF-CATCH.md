# Phase 1 proof-of-catch

Date: 2026-08-10 (local run; no production file changed)

Temporary fixture mutation in `tests/smoke/routes.manifest.php` changed only
platform catalog route from `/platforms` to `/platforms-proof-missing`.

Red command:

```bash
bash tests/bin/run-all.sh smoke
```

Observed exit `1`:

```text
SMOKE: 39/40 passed, 1 failed, 0 skipped (0.05s)
1) platform catalog
   platform catalog [GET /platforms-proof-missing]
     unexpected status 404 (allowed: 200)
```

Manifest restored from byte-for-byte temporary backup. Same command then exited
`0`:

```text
SMOKE: 40/40 passed, 0 failed, 0 skipped (0.044s)
Protected counts: {"wpsr_reviews":0,"wpsr_caches":0,"wpsr_review_forms":0,"wpsr_optimize_images":0,"wpsr_review_categories":0,"wpsr_review_category_relationships":0}
Outbound mocked: HTTP 0, mail 0
```

Production tree stayed untouched throughout.
