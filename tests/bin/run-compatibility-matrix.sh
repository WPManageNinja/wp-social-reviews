#!/usr/bin/env bash
# Phase 31 disposable family version-pair matrix. No live checkout is mounted writable.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PRO_ROOT="$(cd "$ROOT/../wp-social-ninja-pro" && pwd)"
MATRIX="$ROOT/tests/compatibility/matrix.json"
ARTIFACT_BASE="$ROOT/tests/.artifacts/compatibility"
SELECTOR="${1:-all}"

command -v docker >/dev/null 2>&1 || { echo 'COMPATIBILITY-MATRIX Docker unavailable.' >&2; exit 1; }
docker info >/dev/null 2>&1 || { echo 'COMPATIBILITY-MATRIX Docker daemon unavailable.' >&2; exit 1; }
php "$ROOT/tests/family/test-trees.php"
php "$ROOT/tests/compatibility/lint.php"

mapfile -t CELL_IDS < <(php -r '$m=json_decode(file_get_contents($argv[1]),true); if (!is_array($m)||empty($m["cells"])) exit(3); foreach ($m["cells"] as $c) echo $c["id"],"\n";' "$MATRIX")
[ "${#CELL_IDS[@]}" -gt 0 ] || { echo 'COMPATIBILITY-MATRIX zero cells.' >&2; exit 1; }
if [ "$SELECTOR" = "--list" ]; then
    printf '%s\n' "${CELL_IDS[@]}"
    exit 0
fi
if [ "$SELECTOR" != all ]; then
    known=0
    for id in "${CELL_IDS[@]}"; do [ "$id" != "$SELECTOR" ] || known=1; done
    [ "$known" -eq 1 ] || { echo "Unknown compatibility cell: $SELECTOR" >&2; exit 2; }
    CELL_IDS=("$SELECTOR")
fi

STAMP="$(date -u +%Y%m%dT%H%M%SZ)-$$"
RUN_DIR="$ARTIFACT_BASE/matrix-$STAMP"
mkdir -p "$RUN_DIR"
chmod 0777 "$RUN_DIR"
STATUS_CORE="$RUN_DIR/.core-status-before"
STATUS_PRO="$RUN_DIR/.pro-status-before"
HASH_CORE="$RUN_DIR/.core-production-before"
HASH_PRO="$RUN_DIR/.pro-production-before"
git -C "$ROOT" status --porcelain=v1 -z --untracked-files=all > "$STATUS_CORE"
git -C "$PRO_ROOT" status --porcelain=v1 -z --untracked-files=all > "$STATUS_PRO"
production_hash() {
    local repo="$1"
    (cd "$repo" && git ls-files -z -- app boot config database vendor '*.php' '*.json' '*.lock' \
        | while IFS= read -r -d '' file; do
            case "$file" in tests/*|dev/*|docs/*) continue ;; esac
            [ -f "$file" ] && printf '%s  %s\n' "$(sha256sum "$file" | awk '{print $1}')" "$file"
        done | sort | sha256sum | awk '{print $1}')
}
production_hash "$ROOT" > "$HASH_CORE"
production_hash "$PRO_ROOT" > "$HASH_PRO"

cleanup_projects=()
cleanup() {
    local code=$?
    trap - EXIT INT TERM
    for project_data in "${cleanup_projects[@]:-}"; do
        project="${project_data%%|*}"; compose="${project_data#*|}"
        docker compose -p "$project" -f "$compose" down -v --remove-orphans >/dev/null 2>&1 || true
    done
    if ! cmp -s "$STATUS_CORE" <(git -C "$ROOT" status --porcelain=v1 -z --untracked-files=all) \
        || ! cmp -s "$STATUS_PRO" <(git -C "$PRO_ROOT" status --porcelain=v1 -z --untracked-files=all) \
        || [ "$(cat "$HASH_CORE")" != "$(production_hash "$ROOT")" ] \
        || [ "$(cat "$HASH_PRO")" != "$(production_hash "$PRO_ROOT")" ]; then
        echo 'COMPATIBILITY-MATRIX production mutation/residue guard failed.' >&2
        code=1
    fi
    exit "$code"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

json_cell() {
    php -r '$m=json_decode(file_get_contents($argv[1]),true); foreach ($m["cells"] as $c) if ($c["id"]===$argv[2]) { echo json_encode($c,JSON_UNESCAPED_SLASHES); exit; } exit(2);' "$MATRIX" "$1"
}
json_value() {
    php -r '$v=json_decode($argv[1],true); foreach (explode(".",$argv[2]) as $k) $v=$v[$k]??null; if (is_bool($v)) echo $v?"true":"false"; elseif ($v!==null) echo $v;' "$1" "$2"
}
artifact_json() {
    php -r '$m=json_decode(file_get_contents($argv[1]),true); $a=$m["artifacts"][$argv[2]]??null; if (!$a) exit(2); echo json_encode($a,JSON_UNESCAPED_SLASHES);' "$MATRIX" "$1"
}
dimension_json() {
    php -r '$m=json_decode(file_get_contents($argv[1]),true); $d=$m["dimensions"][$argv[2]]??null; if (!$d) exit(2); echo json_encode($d,JSON_UNESCAPED_SLASHES);' "$MATRIX" "$1"
}
resolve_repo() { [ "$1" = core ] && printf '%s\n' "$ROOT" || printf '%s\n' "$PRO_ROOT"; }

PASS=0
FAIL=0
EXPECTED_INCOMPATIBLE=0
SKIP_COUNT="$(php -r '$m=json_decode(file_get_contents($argv[1]),true); echo count($m["historical_unavailable"]??[]);' "$MATRIX")"
RESULT_ROWS=()
MATRIX_STARTED="$(date +%s)"

for CELL_ID in "${CELL_IDS[@]}"; do
    CELL_STARTED="$(date +%s)"
    CELL_JSON="$(json_cell "$CELL_ID")"
    CORE_ID="$(json_value "$CELL_JSON" core)"
    PRO_ID="$(json_value "$CELL_JSON" pro)"
    DIM_ID="$(json_value "$CELL_JSON" dimension)"
    PURPOSE="$(json_value "$CELL_JSON" purpose)"
    EXPECTED_COMPAT="$(json_value "$CELL_JSON" expected.compatibility)"
    [ "$EXPECTED_COMPAT" != incompatible ] || EXPECTED_INCOMPATIBLE=$((EXPECTED_INCOMPATIBLE+1))
    CORE_ART="$(artifact_json "$CORE_ID")"
    CORE_REF="$(json_value "$CORE_ART" ref)"
    CORE_VERSION="$(json_value "$CORE_ART" version)"
    CORE_COMMIT="$(git -C "$ROOT" rev-parse "$CORE_REF^{commit}")"
    PRO_COMMIT=""; PRO_VERSION=""; PRO_REF=""
    if [ -n "$PRO_ID" ]; then
        PRO_ART="$(artifact_json "$PRO_ID")"
        PRO_REF="$(json_value "$PRO_ART" ref)"
        PRO_VERSION="$(json_value "$PRO_ART" version)"
        PRO_COMMIT="$(git -C "$PRO_ROOT" rev-parse "$PRO_REF^{commit}")"
    fi
    DIM_JSON="$(dimension_json "$DIM_ID")"
    WP_IMAGE="$(json_value "$DIM_JSON" wordpress_image)"
    CLI_IMAGE="$(json_value "$DIM_JSON" cli_image)"
    DB_IMAGE="$(json_value "$DIM_JSON" database_image)"

    CELL_DIR="$RUN_DIR/$CELL_ID"
    FIXTURE_DIR="$CELL_DIR/fixtures"
    mkdir -p "$FIXTURE_DIR/core" "$CELL_DIR/artifacts"
    git -C "$ROOT" archive "$CORE_REF" | tar -x -C "$FIXTURE_DIR/core"
    if [ -n "$PRO_ID" ]; then
        mkdir -p "$FIXTURE_DIR/pro"
        git -C "$PRO_ROOT" archive "$PRO_REF" | tar -x -C "$FIXTURE_DIR/pro"
    fi
    CORE_FIXTURE_HASH="$(cd "$FIXTURE_DIR/core" && find . -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | awk '{print $1}')"
    PRO_FIXTURE_HASH=""
    [ -z "$PRO_ID" ] || PRO_FIXTURE_HASH="$(cd "$FIXTURE_DIR/pro" && find . -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | awk '{print $1}')"

    for image in "$WP_IMAGE" "$CLI_IMAGE" "$DB_IMAGE"; do
        docker image inspect "$image" >/dev/null 2>&1 || docker pull "$image"
    done
    WP_DIGEST="$(docker image inspect "$WP_IMAGE" --format '{{.Id}}')"
    CLI_DIGEST="$(docker image inspect "$CLI_IMAGE" --format '{{.Id}}')"
    DB_DIGEST="$(docker image inspect "$DB_IMAGE" --format '{{.Id}}')"

    PROJECT="wpsrcompat$(printf '%s' "$CELL_ID-$STAMP" | sha256sum | cut -c1-12)"
    COMPOSE="$CELL_DIR/compose.yaml"
    PRO_VOLUMES_WORDPRESS=""; PRO_VOLUMES_CLI=""
    if [ -n "$PRO_ID" ]; then
        PRO_VOLUMES_WORDPRESS="      - \"$FIXTURE_DIR/pro:/var/www/html/wp-content/plugins/wp-social-ninja-pro:ro\""
        PRO_VOLUMES_CLI="$PRO_VOLUMES_WORDPRESS"
    fi
    cat > "$COMPOSE" <<YAML
services:
  db:
    image: "$DB_IMAGE"
    environment:
      MARIADB_DATABASE: wpsr_compatibility_test
      MARIADB_USER: wpsr
      MARIADB_PASSWORD: wpsr_only
      MARIADB_ROOT_PASSWORD: wpsr_root_only
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 2s
      timeout: 5s
      retries: 60
    volumes: ["db_data:/var/lib/mysql"]
  wordpress:
    image: "$WP_IMAGE"
    depends_on:
      db: {condition: service_healthy}
    environment:
      WORDPRESS_DB_HOST: db:3306
      WORDPRESS_DB_NAME: wpsr_compatibility_test
      WORDPRESS_DB_USER: wpsr
      WORDPRESS_DB_PASSWORD: wpsr_only
      WORDPRESS_TABLE_PREFIX: wpsrcompat_
      WORDPRESS_DEBUG: "1"
      WORDPRESS_CONFIG_EXTRA: |
        define('WP_ENVIRONMENT_TYPE', 'local');
        define('DISABLE_WP_CRON', true);
        define('AUTOMATIC_UPDATER_DISABLED', true);
    healthcheck:
      test: ["CMD", "php", "-r", "exit(is_file('/var/www/html/wp-includes/version.php') ? 0 : 1);"]
      interval: 2s
      timeout: 5s
      retries: 60
    volumes:
      - "wp_data:/var/www/html"
      - "$FIXTURE_DIR/core:/var/www/html/wp-content/plugins/wp-social-ninja:ro"
$PRO_VOLUMES_WORDPRESS
  cli:
    image: "$CLI_IMAGE"
    depends_on:
      db: {condition: service_healthy}
      wordpress: {condition: service_healthy}
    user: "33:33"
    environment:
      WORDPRESS_DB_HOST: db:3306
      WORDPRESS_DB_NAME: wpsr_compatibility_test
      WORDPRESS_DB_USER: wpsr
      WORDPRESS_DB_PASSWORD: wpsr_only
      WORDPRESS_TABLE_PREFIX: wpsrcompat_
    working_dir: /suite
    volumes:
      - "wp_data:/var/www/html"
      - "$FIXTURE_DIR/core:/var/www/html/wp-content/plugins/wp-social-ninja:ro"
$PRO_VOLUMES_CLI
      - "$ROOT:/suite:ro"
      - "$PRO_ROOT/tests:/suite-pro-tests:ro"
volumes:
  db_data:
  wp_data:
YAML
    cleanup_projects+=("$PROJECT|$COMPOSE")
    LOG="$CELL_DIR/runtime.log"
    CELL_EXIT=0
    {
        echo "COMPATIBILITY-MATRIX CELL $CELL_ID purpose=$PURPOSE expected=$EXPECTED_COMPAT"
        docker compose -p "$PROJECT" -f "$COMPOSE" up -d --wait db wordpress
        for attempt in $(seq 1 60); do
            if docker compose -p "$PROJECT" -f "$COMPOSE" run --rm cli core is-installed --path=/var/www/html >/dev/null 2>&1; then break; fi
            if [ "$attempt" -eq 60 ]; then
                docker compose -p "$PROJECT" -f "$COMPOSE" run --rm cli core install --path=/var/www/html --url=http://wordpress --title='WPSR compatibility' --admin_user=wpsradmin --admin_password=wpsr-only --admin_email=wpsr@example.test --skip-email
                break
            fi
            if [ "$attempt" -eq 2 ]; then
                docker compose -p "$PROJECT" -f "$COMPOSE" run --rm cli core install --path=/var/www/html --url=http://wordpress --title='WPSR compatibility' --admin_user=wpsradmin --admin_password=wpsr-only --admin_email=wpsr@example.test --skip-email && break || true
            fi
            sleep 1
        done
        docker compose -p "$PROJECT" -f "$COMPOSE" run --rm cli plugin activate wp-social-ninja --path=/var/www/html --skip-plugins --skip-themes
        if [ -n "$PRO_ID" ]; then
            docker compose -p "$PROJECT" -f "$COMPOSE" run --rm cli plugin activate wp-social-ninja-pro --path=/var/www/html --skip-plugins --skip-themes
        fi
        RESOLVED="$(php -r '$c=json_decode($argv[1],true); $c["resolved"]=["core_version"=>$argv[2],"pro_version"=>$argv[3],"commits"=>["core"=>$argv[4],"pro"=>$argv[5]?:null],"images"=>["wordpress"=>$argv[6],"cli"=>$argv[7],"database"=>$argv[8]],"dimension"=>json_decode($argv[9],true)]; echo json_encode($c,JSON_UNESCAPED_SLASHES);' "$CELL_JSON" "$CORE_VERSION" "$PRO_VERSION" "$CORE_COMMIT" "$PRO_COMMIT" "$WP_DIGEST" "$CLI_DIGEST" "$DB_DIGEST" "$DIM_JSON")"
        PAYLOAD="$(printf '%s' "$RESOLVED" | base64 | tr -d '\n')"
        docker compose -p "$PROJECT" -f "$COMPOSE" run --rm cli eval-file /suite/tests/compatibility/runtime-cell.php "payload=$PAYLOAD" --path=/var/www/html
    } > >(tee "$LOG") 2>&1 || CELL_EXIT=$?

    if grep -E '(WordPress database error|PHP (Fatal error|Parse error)|Uncaught (Error|Exception))' "$LOG" >/dev/null; then
        echo "COMPATIBILITY-MATRIX CELL $CELL_ID emitted fatal/database diagnostics." | tee -a "$LOG"
        CELL_EXIT=1
    fi
    RUNTIME_SECONDS=$(( $(date +%s) - CELL_STARTED ))
    RESULT="$CELL_DIR/artifacts/result.json"
    RESOLVED_BASE64="$(printf '%s' "$RESOLVED" | base64 | tr -d '\n')"
    RESULT_EXIT=0
    php "$ROOT/tests/compatibility/validate-runtime-result.php" \
        "--log=$LOG" \
        "--cell-base64=$RESOLVED_BASE64" \
        "--child-exit=$CELL_EXIT" \
        "--runtime-seconds=$RUNTIME_SECONDS" \
        "--core-fixture-hash=$CORE_FIXTURE_HASH" \
        "--pro-fixture-hash=$PRO_FIXTURE_HASH" \
        "--output=$RESULT" 2>&1 | tee -a "$LOG" || RESULT_EXIT=$?
    if [ "$RESULT_EXIT" -eq 0 ]; then
        STATUS=PASS
        PASS=$((PASS+1))
        RESULT_ROWS+=("$RESULT")
    else
        STATUS=FAIL
        FAIL=$((FAIL+1))
        rm -f "$RESULT"
    fi
    docker compose -p "$PROJECT" -f "$COMPOSE" down -v --remove-orphans >/dev/null 2>&1 || true
    echo "COMPATIBILITY-MATRIX CELL-SUMMARY id=$CELL_ID status=$STATUS expected=$EXPECTED_COMPAT runtime=${RUNTIME_SECONDS}s artifact=$RESULT"
done

TOTAL=$((PASS+FAIL+SKIP_COUNT))
[ "$TOTAL" -gt 0 ] || { echo 'COMPATIBILITY-MATRIX zero reconciled cells.' >&2; exit 1; }
SUMMARY="$RUN_DIR/summary.json"
CORE_HEAD="$(git -C "$ROOT" rev-parse HEAD)"
PRO_HEAD="$(git -C "$PRO_ROOT" rev-parse HEAD)"
php -r '$matrix=json_decode(file_get_contents($argv[1]),true); $selector=$argv[11]; $selected=[]; foreach ($matrix["cells"] as $cell) {if ($selector==="all" || $cell["id"]===$selector) $selected[]=$cell["id"];} $historical=array_values(array_map(static function ($row) {return $row["id"];},$matrix["historical_unavailable"]??[])); $results=[]; foreach (array_slice($argv,12) as $path) {$row=json_decode(file_get_contents($path),true); if (is_array($row)) $results[]=$row;} $out=["schema"=>1,"matrix"=>["family"=>"wp-social-ninja","source_sha256"=>hash_file("sha256",$argv[1]),"repositories"=>["core"=>$argv[9],"pro"=>$argv[10]]],"selector"=>$selector,"selected_cells"=>$selected,"historical_skip_ids"=>$historical,"cells"=>(int)$argv[2],"pass"=>(int)$argv[3],"expected_incompatible"=>(int)$argv[4],"fail"=>(int)$argv[5],"skip"=>(int)$argv[6],"runtime_seconds"=>(float)$argv[7],"results"=>$results]; file_put_contents($argv[8],json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");' "$MATRIX" "$TOTAL" "$PASS" "$EXPECTED_INCOMPATIBLE" "$FAIL" "$SKIP_COUNT" "$(( $(date +%s) - MATRIX_STARTED ))" "$SUMMARY" "$CORE_HEAD" "$PRO_HEAD" "$SELECTOR" "${RESULT_ROWS[@]}"
php "$ROOT/tests/compatibility/validate-summary.php" "$SUMMARY" "$MATRIX"
printf 'COMPATIBILITY-MATRIX cells=%d pass=%d expected-incompatible=%d fail=%d skip=%d runtime=%ds artifact=%s\n' "$TOTAL" "$PASS" "$EXPECTED_INCOMPATIBLE" "$FAIL" "$SKIP_COUNT" "$(( $(date +%s) - MATRIX_STARTED ))" "$SUMMARY"
[ "$FAIL" -eq 0 ]
