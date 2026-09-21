#!/usr/bin/env python3
"""Proof-of-catch for release ZIP secret policy using synthetic non-credentials."""
import hashlib
import json
import pathlib
import subprocess
import sys
import tempfile
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[3]
SCANNER = ROOT / "tests/security/secrets/scan-release.py"
BENIGN_FIXTURE = ROOT / "tests/security/secrets/fixtures/benign-assignment.txt"


def make_zip(path, entries):
    with zipfile.ZipFile(path, "w") as archive:
        for name, value in entries.items():
            archive.writestr(name, value)


def scan(zip_path, allow, report):
    return subprocess.run([sys.executable, str(SCANNER), "--zip", str(zip_path), "--allowlist", str(allow), "--output", str(report)], capture_output=True, text=True)


def main():
    proofs = failures = 0
    with tempfile.TemporaryDirectory(prefix="wpsr-secret-self-test-") as temp:
        temp = pathlib.Path(temp)
        empty_allow = temp / "empty-allow.json"
        empty_allow.write_text('{"schema":1,"allowlist":[]}\n')
        seeded = temp / "seeded.zip"
        make_zip(seeded, {
            "fixture/fake.txt": (
                "aws=AKIAFAKEFIXTURE00001\n"
                "-----BEGIN PRIVATE KEY-----\nsynthetic-fixture-only\n"
                "client_secret = 'SyntheticEntropyToken_7Xq9Vm2Lp4Rs8Tz'\n"
                "TOKEN=SyntheticUnquotedToken8Vm3Qp7Lx5Rz\n"
                "define('FIXTURE_API_KEY', 'SyntheticDefineToken9Qm4Vp8Ls6Ty');\n"
            )
        })
        result = scan(seeded, empty_allow, temp / "seeded.json")
        report = json.loads((temp / "seeded.json").read_text())
        proofs += 1
        rules = {row["rule"] for row in report["blocked"]}
        if result.returncode != 1 or not {"aws-access-key", "private-key", "high-entropy-credential"}.issubset(rules) or len(report["blocked"]) < 5:
            failures += 1

        benign_token = "PublicFixtureNonce_8Vn3Qp7Lm5Rx2Tz"
        benign = temp / "benign.zip"
        benign_path = "fixture/benign-assignment.txt"
        make_zip(benign, {benign_path: BENIGN_FIXTURE.read_text()})
        result = scan(benign, empty_allow, temp / "benign-blocked.json")
        proofs += 1
        if result.returncode != 1:
            failures += 1

        allow = temp / "allow.json"
        allow.write_text(json.dumps({"schema": 1, "allowlist": [{
            "rule": "high-entropy-credential",
            "path": benign_path,
            "fingerprint": hashlib.sha256(benign_token.encode()).hexdigest(),
            "reason": "Synthetic public fixture nonce proves exact fingerprint allowlisting without containing a real credential."
        }]}) + "\n")
        result = scan(benign, allow, temp / "benign-allowed.json")
        accepted = json.loads((temp / "benign-allowed.json").read_text())
        proofs += 1
        if result.returncode != 0 or len(accepted["allowlisted"]) != 1 or accepted["blocked"]:
            failures += 1

        opaque = temp / "opaque.zip"
        make_zip(opaque, {"config/runtime.credential": "-----BEGIN PRIVATE KEY-----\nsynthetic-opaque-fixture\n"})
        result = scan(opaque, empty_allow, temp / "opaque.json")
        opaque_report = json.loads((temp / "opaque.json").read_text())
        proofs += 1
        if result.returncode != 1 or not any(row["rule"] == "private-key" for row in opaque_report["blocked"]):
            failures += 1

        empty = temp / "empty.zip"
        make_zip(empty, {})
        result = scan(empty, empty_allow, temp / "empty.json")
        proofs += 1
        if result.returncode != 1 or "zero files" not in result.stderr:
            failures += 1

        broad = temp / "broad-allow.json"
        broad.write_text(json.dumps({"schema": 1, "allowlist": [{
            "rule": "high-entropy-credential",
            "path": "fixture/*",
            "fingerprint": hashlib.sha256(benign_token.encode()).hexdigest(),
            "reason": "Synthetic broad path must be rejected because production allowlists require exact archive paths."
        }]}) + "\n")
        result = scan(benign, broad, temp / "broad.json")
        proofs += 1
        if result.returncode == 0 or "malformed allowlist row" not in result.stderr:
            failures += 1

        wrong_type = temp / "wrong-type-allow.json"
        wrong_type.write_text(json.dumps({"schema": 1, "allowlist": [{
            "rule": 42,
            "path": benign_path,
            "fingerprint": hashlib.sha256(benign_token.encode()).hexdigest(),
            "reason": "Synthetic non-string rule must fail before allowlist matching or stale-row handling."
        }]}) + "\n")
        result = scan(benign, wrong_type, temp / "wrong-type.json")
        proofs += 1
        if result.returncode == 0 or "must be strings" not in result.stderr:
            failures += 1

    print(f"RELEASE-SECRETS-SELF-TEST proofs={proofs} seeded-findings={len(report['blocked'])} allowlisted=1 failures={failures}")
    return 1 if failures or proofs == 0 else 0


if __name__ == "__main__":
    raise SystemExit(main())
