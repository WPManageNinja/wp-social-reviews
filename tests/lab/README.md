# Disposable core test lab

Minimal MariaDB + WordPress + WP-CLI runner for core foundation tests. All
services use an internal no-egress network with zero host ports. Core source mounts
read-only at workspace and plugin paths. Default database `wpsocialninja_test_lab` and
non-default prefix `wpsntest_` are enforced in shell and PHP harnesses.

```bash
bash tests/lab/scripts/setup.sh
bash tests/bin/run-all.sh static
bash tests/bin/run-all.sh
bash tests/lab/scripts/php-tests.sh tests/bin/run-smoke.php
bash tests/lab/scripts/php-tests.sh tests/bin/run-permissions.php
bash tests/lab/scripts/php-tests.sh tests/bin/run-pure.php
bash tests/lab/scripts/php-tests.sh tests/bin/run-integration.php
bash tests/lab/scripts/php-tests.sh tests/bin/run-public.php
bash tests/lab/scripts/reset.sh --yes
```

`run-all.sh` executes host-side GET, mutating-permission, round-trip,
input-validation, public-handler, and SKIP-registry drift gates before Docker runtime suites.
It rejects unknown modes and each runtime runner rejects zero discovery.

Optional lab overrides belong in ignored `tests/lab/.env` as `WPSR_LAB_*`
values. Database name must contain `test`; `wp_` prefix is refused. WordPress
and WP-CLI containers define `DISABLE_WP_CRON`. MU plugins block and audit HTTP
and mail for both web and in-process test traffic. Lab reset requires explicit
`--yes` and removes only fixed Compose project containers and volumes.

Pro/family, browser, coverage, environment-axis, compatibility, and security
launchers belong to later stacked PRs. Foundation does not mount sibling
checkouts or reference their uncommitted files.
