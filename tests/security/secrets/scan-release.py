#!/usr/bin/env python3
"""Deterministic release-ZIP credential, private-key, and high-entropy scanner."""
import argparse
import collections
import hashlib
import json
import math
import pathlib
import re
import sys
import zipfile

VERSION = "wpsr-release-secrets/1.0.0"
MAX_ENTRY = 4 * 1024 * 1024
PATTERNS = [
    ("private-key", re.compile(r"-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----")),
    ("aws-access-key", re.compile(r"\bAKIA[0-9A-Z]{16}\b")),
    ("github-token", re.compile(r"\bgh[pousr]_[A-Za-z0-9]{30,255}\b")),
    ("google-api-key", re.compile(r"\bAIza[0-9A-Za-z_-]{35}\b")),
    ("slack-token", re.compile(r"\bxox[baprs]-[0-9A-Za-z-]{20,}\b")),
    ("stripe-live-key", re.compile(r"\b(?:sk|rk)_live_[0-9A-Za-z]{20,}\b")),
]
ASSIGNMENTS = [
    re.compile(
        r"(?i)(?:secret|password|passwd|token|api[_-]?key|client[_-]?secret)"
        r"[\s'\".\]-]{0,12}(?:=>|=|:)[\s]*['\"]([A-Za-z0-9_+\-/=]{24,512})['\"]"
    ),
    re.compile(
        r"(?im)^[ \t]*(?:export[ \t]+)?(?:secret|password|passwd|token|api[_-]?key|client[_-]?secret)"
        r"[A-Za-z0-9_.-]{0,32}[ \t]*=[ \t]*([A-Za-z0-9_+\-/=]{24,512})[ \t]*$"
    ),
    re.compile(
        r"(?i)define\s*\(\s*['\"][A-Za-z0-9_]*(?:SECRET|PASSWORD|TOKEN|API_KEY)[A-Za-z0-9_]*['\"]\s*,"
        r"\s*['\"]([A-Za-z0-9_+\-/=]{24,512})['\"]\s*\)"
    ),
]


def entropy(value):
    counts = collections.Counter(value)
    length = len(value)
    return -sum((count / length) * math.log2(count / length) for count in counts.values())


def finding(rule, path, line, token):
    digest = hashlib.sha256(token.encode()).hexdigest()
    return {"rule": rule, "path": path, "line": line, "fingerprint": digest}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--zip", required=True)
    parser.add_argument("--allowlist", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--version", action="store_true")
    args = parser.parse_args()
    if args.version:
        print(VERSION)
        return 0
    archive_path = pathlib.Path(args.zip)
    allowlist_data = json.loads(pathlib.Path(args.allowlist).read_text())
    rows = allowlist_data.get("allowlist", [])
    allow = {}
    if allowlist_data.get("schema") != 1 or not isinstance(rows, list):
        raise SystemExit("RELEASE-SECRETS allowlist schema must be 1 with an allowlist array")
    for row in rows:
        if not isinstance(row, dict) or any(not isinstance(row.get(field), str) for field in ("rule", "path", "fingerprint", "reason")):
            raise SystemExit("RELEASE-SECRETS malformed allowlist row; rule/path/fingerprint/reason must be strings")
        key = (row["rule"], row["path"], row["fingerprint"])
        path = row["path"]
        fingerprint_value = row["fingerprint"]
        if (not all(key) or path.startswith("/") or "*" in path or "?" in path
                or not re.fullmatch(r"[0-9a-f]{64}", fingerprint_value)
                or len(row["reason"].strip()) < 30):
            raise SystemExit("RELEASE-SECRETS malformed allowlist row; exact rule/path/fingerprint/reason required")
        if key in allow:
            raise SystemExit("RELEASE-SECRETS duplicate allowlist row: " + "|".join(key))
        allow[key] = row

    files = text_files = 0
    findings = []
    errors = []
    try:
        archive = zipfile.ZipFile(archive_path)
        bad = archive.testzip()
        if bad:
            errors.append("corrupt ZIP entry: " + bad)
        for info in archive.infolist():
            if info.is_dir():
                continue
            files += 1
            if info.file_size > MAX_ENTRY:
                errors.append(f"scannable text entry exceeds {MAX_ENTRY} byte safety bound: {info.filename}")
                continue
            raw = archive.read(info)
            if b"\x00" in raw:
                continue
            try:
                text = raw.decode("utf-8")
            except UnicodeDecodeError:
                continue
            text_files += 1
            for rule, pattern in PATTERNS:
                for match in pattern.finditer(text):
                    findings.append(finding(rule, info.filename, text.count("\n", 0, match.start()) + 1, match.group(0)))
            for assignment in ASSIGNMENTS:
                for match in assignment.finditer(text):
                    token = match.group(1)
                    if len(set(token)) >= 10 and entropy(token) >= 3.5:
                        findings.append(finding("high-entropy-credential", info.filename, text.count("\n", 0, match.start(1)) + 1, token))
    except (OSError, zipfile.BadZipFile) as exc:
        errors.append(str(exc))

    unique = {}
    for row in findings:
        unique[(row["rule"], row["path"], row["line"], row["fingerprint"])] = row
    findings = sorted(unique.values(), key=lambda row: (row["path"], row["line"], row["rule"]))
    accepted = []
    blocked = []
    seen_allow = set()
    for row in findings:
        key = (row["rule"], row["path"], row["fingerprint"])
        if key in allow:
            accepted.append(row)
            seen_allow.add(key)
        else:
            blocked.append(row)
    stale = sorted(set(allow) - seen_allow)
    if files == 0:
        errors.append("release ZIP contains zero files")
    if text_files == 0:
        errors.append("release ZIP contains zero scannable text files")
    if stale:
        errors.extend("stale allowlist row: " + "|".join(key) for key in stale)

    report = {
        "schema": 1,
        "scanner": VERSION,
        "zip": archive_path.name,
        "files_scanned": files,
        "text_files_scanned": text_files,
        "findings": findings,
        "blocked": blocked,
        "allowlisted": accepted,
        "errors": errors,
    }
    output = pathlib.Path(args.output)
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(report, indent=2, sort_keys=True) + "\n")
    failures = len(blocked) + len(errors)
    print(f"RELEASE-SECRETS files={files} text={text_files} findings={len(findings)} allowlisted={len(accepted)} blocked={len(blocked)} failures={failures}")
    for row in blocked:
        print(f"BLOCK {row['rule']} {row['path']}:{row['line']} fingerprint={row['fingerprint']}", file=sys.stderr)
    for error in errors:
        print("ERROR " + error, file=sys.stderr)
    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())
