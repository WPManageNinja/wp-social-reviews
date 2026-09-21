#!/usr/bin/env python3
"""Build deterministic scan-only core release ZIP from build.sh shipping manifest."""
import argparse
import json
import pathlib
import re
import stat
import zipfile


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", required=True)
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    root = pathlib.Path(args.root).resolve()
    manifest = json.loads(pathlib.Path(args.manifest).read_text())
    if manifest.get("schema") != 1 or not manifest.get("include") or not manifest.get("archive_root"):
        raise SystemExit("RELEASE-PACKAGE manifest malformed or empty")
    build = (root / "build.sh").read_text()
    expected = 'copy_and_compress "." "builds/wp-social-reviews" "app" "assets" "boot" "config" "database" "language" "vendor" "composer.json" "wp-social-reviews.php" "index.php" "readme.txt"'
    if expected not in build:
        raise SystemExit("RELEASE-PACKAGE build.sh core shipping list drifted from scan manifest")

    excluded = set(manifest["exclude_names"])
    files = []
    for relative in manifest["include"]:
        source = root / relative
        if not source.exists():
            raise SystemExit(f"RELEASE-PACKAGE missing shipping path: {relative}")
        if source.is_file():
            files.append((source, pathlib.PurePosixPath(relative)))
        else:
            for path in sorted(source.rglob("*")):
                if not path.is_file() or any(part in excluded for part in path.relative_to(root).parts):
                    continue
                files.append((path, pathlib.PurePosixPath(path.relative_to(root).as_posix())))
    if not files:
        raise SystemExit("RELEASE-PACKAGE selected zero files")

    output = pathlib.Path(args.output)
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for source, relative in sorted(files, key=lambda row: str(row[1])):
            data = source.read_bytes()
            if str(relative) == "config/app.php":
                text = data.decode("utf-8")
                text, count = re.subn(r"(['\"]env['\"]\s*=>\s*)['\"][^'\"]*['\"]", r"\1'production'", text, count=1)
                if count != 1:
                    raise SystemExit("RELEASE-PACKAGE could not force config/app.php env to production")
                data = text.encode()
            name = f"{manifest['archive_root']}/{relative}"
            info = zipfile.ZipInfo(name, (1980, 1, 1, 0, 0, 0))
            mode = 0o755 if source.stat().st_mode & stat.S_IXUSR else 0o644
            info.external_attr = (stat.S_IFREG | mode) << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, data)
    print(f"RELEASE-PACKAGE files={len(files)} bytes={output.stat().st_size} output={output}")


if __name__ == "__main__":
    main()
