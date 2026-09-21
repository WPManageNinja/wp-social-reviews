#!/usr/bin/env python3
"""Fail-closed Semgrep fixture and source policy verifier."""
import argparse
import hashlib
import json
import pathlib
import re
import sys

PREFIX = "tests.security.semgrep.packs."


def rule_id(value):
    return value[len(PREFIX):] if value.startswith(PREFIX) else value


def fingerprint(result):
    rid = rule_id(result["check_id"])
    path = result["path"].replace("/src/", "").replace("/out/fixture-work/", "")
    line = int(result["start"]["line"])
    snippet = result.get("extra", {}).get("lines", "").strip()
    return hashlib.sha256(f"{rid}\0{path}\0{line}\0{snippet}".encode()).hexdigest()


def load(path):
    data = json.loads(pathlib.Path(path).read_text())
    if not isinstance(data, dict) or not isinstance(data.get("results"), list):
        raise RuntimeError("Semgrep result artifact is malformed or lacks results array")
    if data.get("errors"):
        raise RuntimeError(f"Semgrep reported parser/runtime errors: {data['errors']}")
    return data


def load_policy(path):
    policy = json.loads(pathlib.Path(path).read_text())
    if not isinstance(policy, dict) or policy.get("schema") != 1:
        raise RuntimeError("Semgrep policy schema must be 1")
    classified = {}
    for section in ("known_findings", "benign_allowlist"):
        rows = policy.get(section)
        if not isinstance(rows, list):
            raise RuntimeError(f"Semgrep policy {section} must be an array")
        for row in rows:
            fields = ("rule", "path", "fingerprint", "reason")
            if not isinstance(row, dict) or any(not isinstance(row.get(field), str) for field in fields):
                raise RuntimeError(f"Semgrep policy {section} row fields must be strings")
            key = (row["rule"], row["path"], row["fingerprint"])
            if (not row["rule"].startswith("wpsr.") or not row["path"] or row["path"].startswith("/")
                    or "*" in row["path"] or "?" in row["path"]
                    or not re.fullmatch(r"[0-9a-f]{64}", row["fingerprint"])
                    or len(row["reason"].strip()) < 30):
                raise RuntimeError(f"Semgrep policy malformed exact row: {section} {key}")
            if key in classified:
                raise RuntimeError(f"Semgrep policy duplicate classification: {key}")
            classified[key] = section
    return policy


def fixture_mode(args):
    data = load(args.results)
    root = pathlib.Path(args.fixtures)
    config_text = "\n".join(pathlib.Path(p).read_text() for p in args.config)
    rules = sorted(set(re.findall(r"^\s*- id: (wpsr\.[a-z0-9.-]+)\s*$", config_text, re.M)))
    if not rules:
        raise RuntimeError("Semgrep fixture verifier discovered zero rules")
    files = sorted(p for p in root.rglob("*") if p.is_file() and p.suffix in {".php", ".js"})
    if not files:
        raise RuntimeError("Semgrep fixture verifier discovered zero files")

    markers = {}
    for path in files:
        rel = path.name
        lines = path.read_text().splitlines()
        for index, line in enumerate(lines, 1):
            match = re.search(r"//\s*(ruleid|ok):\s*(wpsr\.[a-z0-9.-]+)", line)
            if match:
                markers.setdefault(match.group(2), {}).setdefault(match.group(1), []).append((rel, index))

    findings = {}
    for result in data.get("results", []):
        rid = rule_id(result["check_id"])
        path = pathlib.Path(result["path"]).name
        findings.setdefault(rid, []).append((path, int(result["start"]["line"])))

    failures = []
    for rid in rules:
        positive = sorted(markers.get(rid, {}).get("ruleid", []))
        negative = sorted(markers.get(rid, {}).get("ok", []))
        hits = sorted(findings.get(rid, []))
        if not positive or not negative:
            failures.append(f"{rid}: needs at least one positive and one negative marker")
            continue
        if len(hits) != len(positive):
            failures.append(f"{rid}: expected {len(positive)} finding(s), got {len(hits)}")
            continue
        for (positive_file, positive_line), (hit_file, hit_line) in zip(positive, hits):
            if hit_file != positive_file or hit_line <= positive_line:
                failures.append(f"{rid}: finding {hit_file}:{hit_line} does not follow positive {positive_file}:{positive_line}")
        for negative_file, negative_line in negative:
            if any(hit_file == negative_file and hit_line >= negative_line for hit_file, hit_line in hits):
                failures.append(f"{rid}: safe example at {negative_file}:{negative_line} fired")

    unknown = sorted(set(findings) - set(rules))
    if unknown:
        failures.append("unknown finding rules: " + ",".join(unknown))
    if not data.get("results"):
        failures.append("zero Semgrep fixture findings")
    for failure in failures:
        print("SEMGREP-FIXTURE FAIL " + failure, file=sys.stderr)
    print(f"SEMGREP-FIXTURES rules={len(rules)} files={len(files)} findings={len(data.get('results', []))} failures={len(failures)}")
    return 1 if failures else 0


def source_mode(args):
    data = load(args.results)
    policy = load_policy(args.policy)
    known = {(row["rule"], row["path"], row["fingerprint"]): row for row in policy["known_findings"]}
    benign = {(row["rule"], row["path"], row["fingerprint"]): row for row in policy["benign_allowlist"]}
    findings = data.get("results", [])
    files = sorted(set(pathlib.Path(r["path"]).as_posix() for r in findings))
    classified_known = classified_benign = 0
    failures = []
    if int(args.rules) < 1:
        failures.append("zero Semgrep rules supplied by launcher")
    if int(args.scanned) < 1:
        failures.append("zero Semgrep scanned paths supplied by result artifact")
    seen = set()
    for result in findings:
        rid = rule_id(result["check_id"])
        path = result["path"].replace("/src/", "")
        key = (rid, path, fingerprint(result))
        seen.add(key)
        if key in known:
            classified_known += 1
        elif key in benign:
            classified_benign += 1
        else:
            failures.append(f"untriaged {rid} {path}:{result['start']['line']} fingerprint={key[2]}")
    for key, row in {**known, **benign}.items():
        if key not in seen:
            failures.append(f"stale policy row {key[0]} {key[1]} fingerprint={key[2]}")
        if len(str(row.get("reason", "")).strip()) < 30:
            failures.append(f"policy row lacks substantive reason: {key[0]} {key[1]}")
    # Only demand findings while the policy still parks some. A clean pack is the goal,
    # and rules>=1 plus scanned>=1 already prove the scan really ran.
    if known or benign:
        if not findings:
            failures.append("zero Semgrep source findings")
        if not files:
            failures.append("zero Semgrep source files represented by findings")
    for failure in failures:
        print("SEMGREP-POLICY FAIL " + failure, file=sys.stderr)
    print(f"SEMGREP-AUDIT rules={int(args.rules)} scanned={int(args.scanned)} findings={len(findings)} known={classified_known} benign={classified_benign} failures={len(failures)}")
    return 1 if failures else 0


def main():
    parser = argparse.ArgumentParser()
    sub = parser.add_subparsers(dest="mode", required=True)
    fixture = sub.add_parser("fixtures")
    fixture.add_argument("--results", required=True)
    fixture.add_argument("--fixtures", required=True)
    fixture.add_argument("--config", action="append", required=True)
    source = sub.add_parser("source")
    source.add_argument("--results", required=True)
    source.add_argument("--policy", required=True)
    source.add_argument("--rules", required=True)
    source.add_argument("--scanned", required=True)
    args = parser.parse_args()
    try:
        return fixture_mode(args) if args.mode == "fixtures" else source_mode(args)
    except Exception as exc:
        print(f"SEMGREP-VERIFY FAIL {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
