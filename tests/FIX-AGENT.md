# Future production-fix rules

Use only for separate fix pass against `tests/FIX-PLAN.md`. Test-writing rule
"never fix production" is inverted only then.

1. Observe exact parked `KNOWN-FAILURE` skip.
2. Change only production file named by finding.
3. Remove skip only when case passes unchanged.
4. Run full built suite; verify protected counts and skip-count delta.
5. Never weaken/delete test, edit vendor, change route contract, or combine
   unrelated findings.
6. Flag ambiguous/schema/vendor work instead of guessing.
7. Never commit red suite or production changes mixed with test-suite build.
