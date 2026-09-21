#!/usr/bin/env python3
"""Deterministic loopback-only malformed/boundary public-input regression runner."""
from __future__ import annotations

import argparse
import json
import os
import pathlib
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[3]
DEFAULT_MANIFEST = ROOT / "tests/security/fuzz/corpus-manifest.json"
PHP_TESTS = ROOT / "tests/lab/scripts/php-tests.sh"
COMPOSE = [
    "docker", "compose", "--project-name", "wp-social-ninja-test-lab",
    "--project-directory", str(ROOT / "tests/lab"),
    "--file", str(ROOT / "tests/lab/compose.yaml"),
]


class PolicyFailure(AssertionError):
    pass


def run(command: list[str], check: bool = True) -> subprocess.CompletedProcess[str]:
    result = subprocess.run(command, cwd=ROOT, text=True, capture_output=True)
    if check and result.returncode:
        raise RuntimeError(f"command exited {result.returncode}: {' '.join(command)}\n{result.stdout}{result.stderr}")
    return result


def wp_fixture(mode: str, run_id: str) -> dict:
    result = run(["bash", str(PHP_TESTS), "tests/security/fuzz/fixtures.php", mode, run_id])
    marker = {
        "setup": "WPSR_FUZZ_SETUP=",
        "fingerprint": "WPSR_FUZZ_FINGERPRINT=",
        "cleanup": "WPSR_FUZZ_CLEANUP=",
    }[mode]
    for line in reversed((result.stdout + result.stderr).splitlines()):
        if line.startswith(marker):
            return json.loads(line[len(marker):])
    raise RuntimeError(f"fixture command omitted {marker}: {result.stdout}{result.stderr}")


def container_file(path: str, clear: bool = False) -> str:
    script = f": > {path}" if clear else f"if [ -f {path} ]; then cat {path}; fi"
    return run(COMPOSE + ["exec", "-T", "wordpress", "sh", "-c", script]).stdout


def upload_fingerprint() -> list[str]:
    script = (
        "root=/var/www/html/wp-content/uploads; "
        "if [ -d \"$root\" ]; then "
        "find \"$root\" -type f -print0 | sort -z | xargs -0 sha256sum 2>/dev/null || true; fi"
    )
    rows = container_file_command(script).splitlines()
    return sorted(rows)


def container_file_command(script: str) -> str:
    return run(COMPOSE + ["exec", "-T", "wordpress", "sh", "-c", script]).stdout


def expand(value, fixture):
    if isinstance(value, str):
        replacements = {
            "{{nonce}}": fixture["nonce"],
            "{{review_id}}": str(fixture["review_id"]),
            "{{youtube_id}}": str(fixture["youtube_id"]),
            "{{draft_id}}": str(fixture["draft_id"]),
            "{{long_text}}": "x" * 2048,
        }
        return replacements.get(value, value)
    if isinstance(value, list):
        return [expand(item, fixture) for item in value]
    if isinstance(value, dict):
        return {key: expand(item, fixture) for key, item in value.items()}
    return value


def encode_params(params: dict) -> bytes:
    pairs: list[tuple[str, str]] = []
    for key, value in params.items():
        if isinstance(value, list):
            pairs.extend((key + "[]", str(item)) for item in value)
        else:
            pairs.append((key, str(value)))
    return urllib.parse.urlencode(pairs).encode()


def extract_message(body: bytes) -> str | None:
    try:
        value = json.loads(body.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError):
        return None
    data = value.get("data") if isinstance(value, dict) else None
    return data.get("message") if isinstance(data, dict) else None


def enforce_response(expect: dict, status: int, body: bytes, elapsed: float, limits: dict) -> None:
    expected = int(expect.get("status", 0))
    if status >= 500:
        raise PolicyFailure(f"HTTP 5xx forbidden: {status}")
    if status != expected:
        raise PolicyFailure(f"expected HTTP {expected}, got {status}")
    if len(body) > int(limits["response_bytes"]):
        raise PolicyFailure(f"response exceeded {limits['response_bytes']} bytes: {len(body)}")
    if elapsed > float(limits["case_seconds"]):
        raise PolicyFailure(f"response exceeded {limits['case_seconds']} seconds: {elapsed:.3f}")
    expected_message = expect.get("json_message")
    if expected_message is not None and extract_message(body) != expected_message:
        raise PolicyFailure(f"expected JSON message {expected_message!r}, got {extract_message(body)!r}")


def request_case(target: str, case: dict, fixture: dict, limits: dict) -> tuple[int, bytes, float, dict]:
    params = expand(case["params"], fixture)
    payload = encode_params(params)
    if len(payload) > int(limits["request_bytes"]):
        raise PolicyFailure(f"committed seed exceeds request bound: {len(payload)}")
    request = urllib.request.Request(
        target,
        data=payload,
        method=case.get("method", "POST"),
        headers={"Content-Type": "application/x-www-form-urlencoded", "User-Agent": "wpsr-local-robustness/1.0"},
    )
    started = time.monotonic()
    try:
        response = urllib.request.urlopen(request, timeout=float(limits["case_seconds"]))
        status = response.status
        body = response.read(int(limits["response_bytes"]) + 1)
    except urllib.error.HTTPError as error:
        status = error.code
        body = error.read(int(limits["response_bytes"]) + 1)
    elapsed = time.monotonic() - started
    enforce_response(case["expect"], status, body, elapsed, limits)
    return status, body, elapsed, params


def redact(params: dict) -> dict:
    return {key: ("<test-nonce>" if key in {"nonce", "security"} else value) for key, value in params.items()}


def load_cases(manifest_path: pathlib.Path) -> tuple[dict, list[tuple[str, dict]]]:
    manifest = json.loads(manifest_path.read_text())
    if manifest.get("schema") != 1 or not manifest.get("corpora") or not manifest.get("limits"):
        raise RuntimeError("public-input corpus manifest malformed or empty")
    if manifest.get("target") != "http://127.0.0.1:8892/wp-admin/admin-ajax.php":
        raise RuntimeError("public-input target must remain exact loopback lab admin-ajax URL")
    cases: list[tuple[str, dict]] = []
    ids: set[str] = set()
    for family, relative in sorted(manifest["corpora"].items()):
        path = ROOT / relative
        rows = json.loads(path.read_text())
        if not isinstance(rows, list) or not rows:
            raise RuntimeError(f"corpus family {family} has zero seeds")
        for case in rows:
            case_id = case.get("id", "")
            if not case_id or case_id in ids or not case.get("params") or not case.get("expect"):
                raise RuntimeError(f"malformed/duplicate seed in {family}: {case_id!r}")
            ids.add(case_id)
            cases.append((family, case))
    if not cases:
        raise RuntimeError("public-input corpus discovered zero cases")
    return manifest, cases


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", default=str(DEFAULT_MANIFEST))
    parser.add_argument("--artifacts", required=True)
    args = parser.parse_args()
    manifest, cases = load_cases(pathlib.Path(args.manifest))
    artifacts = pathlib.Path(args.artifacts)
    artifacts.mkdir(parents=True, exist_ok=True)
    run_id = f"p26{int(time.time()):x}{os.getpid():x}"[-32:].lower()
    failures: list[dict] = []
    passed = 0
    fixture = None
    started = time.monotonic()
    try:
        fixture = wp_fixture("setup", run_id)
        expected_db = fixture["fixture_fingerprint"]
        expected_uploads = upload_fingerprint()
        for family, case in cases:
            container_file("/tmp/wpsr-browser-outbound.log", clear=True)
            container_file("/var/www/html/wp-content/debug.log", clear=True)
            status = 0
            body = b""
            elapsed = 0.0
            params = {}
            errors: list[str] = []
            try:
                status, body, elapsed, params = request_case(manifest["target"], case, fixture, manifest["limits"])
            except Exception as exc:
                errors.append(f"{type(exc).__name__}: {exc}")
            outbound = container_file("/tmp/wpsr-browser-outbound.log")
            debug = container_file("/var/www/html/wp-content/debug.log")
            if outbound.strip():
                errors.append("WordPress outbound HTTP/mail audit is nonempty")
            if "PHP Fatal error" in debug or "Uncaught " in debug:
                errors.append("PHP fatal/uncaught diagnostic reached debug.log")
            current_db = wp_fixture("fingerprint", run_id)
            if current_db != expected_db:
                errors.append("database fingerprint changed")
            if upload_fingerprint() != expected_uploads:
                errors.append("upload-tree fingerprint changed")
            if errors:
                failure = {
                    "family": family,
                    "id": case["id"],
                    "errors": errors,
                    "status": status,
                    "elapsed": round(elapsed, 6),
                    "params": redact(params),
                    "body": body.decode("utf-8", "replace"),
                    "outbound": outbound,
                    "debug": debug,
                }
                failures.append(failure)
                (artifacts / f"failure-{len(failures):03d}-{case['id']}.json").write_text(
                    json.dumps(failure, indent=2, sort_keys=True) + "\n"
                )
            else:
                passed += 1
                print(f"PASS family={family} seed={case['id']} status={status} bytes={len(body)} runtime={elapsed:.3f}s")
    except Exception as exc:
        failures.append({"family": "harness", "id": "bootstrap", "errors": [f"{type(exc).__name__}: {exc}"]})
    finally:
        if fixture is not None:
            try:
                wp_fixture("cleanup", run_id)
            except Exception as exc:
                failures.append({"family": "harness", "id": "cleanup", "errors": [f"{type(exc).__name__}: {exc}"]})
    runtime = time.monotonic() - started
    summary = {
        "schema": 1,
        "target": manifest["target"],
        "families": len(manifest["corpora"]),
        "seeds": len(cases),
        "cases": len(cases),
        "passed": passed,
        "failures": failures,
        "runtime_seconds": round(runtime, 3),
    }
    (artifacts / "summary.json").write_text(json.dumps(summary, indent=2, sort_keys=True) + "\n")
    print(
        f"PUBLIC-INPUT-FUZZ families={summary['families']} seeds={summary['seeds']} "
        f"cases={summary['cases']} passed={passed} failures={len(failures)} "
        f"artifacts={len(list(artifacts.glob('*')))} runtime={runtime:.3f}s output={artifacts}"
    )
    return 1 if failures or passed == 0 else 0


if __name__ == "__main__":
    raise SystemExit(main())
