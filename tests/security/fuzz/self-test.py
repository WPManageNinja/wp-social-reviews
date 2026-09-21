#!/usr/bin/env python3
"""Fast no-network proofs for corpus discovery and malformed-input response policy."""
import importlib.util
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parents[3]
SPEC = importlib.util.spec_from_file_location("wpsr_public_input_fuzz", ROOT / "tests/security/fuzz/run.py")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


def main():
    proofs = failures = 0
    try:
        manifest, cases = MODULE.load_cases(MODULE.DEFAULT_MANIFEST)
        proofs += 1
        if len(manifest["corpora"]) == 0 or len(cases) == 0:
            failures += 1

        proofs += 1
        try:
            MODULE.enforce_response({"status": 403}, 403, b"{}", 0.01, manifest["limits"])
        except Exception:
            failures += 1

        proofs += 1
        try:
            MODULE.enforce_response({"status": 403}, 200, b"{}", 0.01, manifest["limits"])
            failures += 1
        except MODULE.PolicyFailure as exc:
            if "expected HTTP 403, got 200" not in str(exc):
                failures += 1

        proofs += 1
        required_na = {"raw_php_input_webhooks", "multipart_uploads"}
        if not required_na.issubset(set(manifest.get("not_applicable", {}))):
            failures += 1
    except Exception as exc:
        print(f"FUZZ-POLICY-SELF-TEST bootstrap failure: {exc}", file=sys.stderr)
        failures += 1
    families = len(manifest.get("corpora", {})) if "manifest" in locals() else 0
    seeds = len(cases) if "cases" in locals() else 0
    print(f"FUZZ-POLICY-SELF-TEST proofs={proofs} families={families} seeds={seeds} failures={failures}")
    return 1 if failures or proofs == 0 or seeds == 0 else 0


if __name__ == "__main__":
    raise SystemExit(main())
