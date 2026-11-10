#!/usr/bin/env bash
# Deploy from this laptop to cPanel over FTP — same result as the GitHub Actions workflow:
#   tests → build (deploy/stage.sh) → upload ONLY changed files → flag post-deploy (cron migrates).
#
# Change detection is content-based: deploy/dist/last-deployed/ mirrors what was uploaded last time,
# and rsync --checksum against it gives the exact files to upload / delete. First run uploads everything.
#
# Never touched on the server: .env, storage/ (logs, sessions, private proofs), public_html/storage (uploads).
#
# Setup:  cp deploy/ftp.env.example deploy/ftp.env   (fill in; it's gitignored)
# Usage:  bash deploy/ftp-deploy.sh            deploy
#         bash deploy/ftp-deploy.sh --dry-run  show what would be uploaded/deleted, upload nothing
#         bash deploy/ftp-deploy.sh --skip-tests
#         bash deploy/ftp-deploy.sh --check      only test the FTP login + list the remote folders
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="$ROOT/deploy/dist/stage"
LAST="$ROOT/deploy/dist/last-deployed"
CONF="$ROOT/deploy/ftp.env"
DRY=0; TESTS=1; CHECK=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY=1 ;;
    --check) CHECK=1 ;;
    --skip-tests) TESTS=0 ;;
    *) echo "Unknown option: $arg"; exit 2 ;;
  esac
done

command -v lftp >/dev/null || { echo "✗ lftp not installed: brew install lftp"; exit 1; }
[[ -f "$CONF" ]] || { echo "✗ $CONF missing — copy deploy/ftp.env.example and fill it in"; exit 1; }
# Read KEY=VALUE lines literally (no shell evaluation), so passwords with & $ ; ! etc. are safe.
while IFS= read -r line || [[ -n "$line" ]]; do
  [[ "$line" =~ ^[[:space:]]*(#|$) ]] && continue
  key="${line%%=*}"; value="${line#*=}"
  key="${key//[[:space:]]/}"
  # strip one pair of surrounding quotes, if any
  if [[ "$value" =~ ^\"(.*)\"$ || "$value" =~ ^\'(.*)\'$ ]]; then value="${BASH_REMATCH[1]}"; fi
  case "$key" in
    FTP_HOST|FTP_USER|FTP_PASSWORD|FTP_PROTOCOL|FTP_VERIFY_CERT|FTP_APP_DIR|FTP_WEB_DIR) printf -v "$key" '%s' "$value" ;;
  esac
done < "$CONF"
: "${FTP_HOST:?}" "${FTP_USER:?}" "${FTP_PASSWORD:?}"
FTP_PROTOCOL="${FTP_PROTOCOL:-ftps}"
FTP_APP_DIR="${FTP_APP_DIR:-sparingin}"
FTP_WEB_DIR="${FTP_WEB_DIR:-public_html}"
FTP_VERIFY_CERT="${FTP_VERIFY_CERT:-yes}"

# Run lftp commands on the server. Password goes via env (not argv), so it's not in the process list.
ftp_run() {
  local ssl="set ftp:ssl-allow no;"
  if [[ "$FTP_PROTOCOL" == "ftps" ]]; then
    ssl="set ftp:ssl-force yes; set ftp:ssl-protect-data yes; set ssl:verify-certificate $FTP_VERIFY_CERT;"
  fi
  local open="open -u \"$FTP_USER\" --env-password \"$FTP_HOST\""
  [[ "$FTP_HOST" == file://* ]] && open="open \"$FTP_HOST\""   # local folder target, for testing the script
  LFTP_PASSWORD="$FTP_PASSWORD" lftp -c "
    set net:max-retries 3; set net:timeout 30; set cmd:fail-exit yes; $ssl
    $open
    $1
  "
}

if (( CHECK )); then
  echo "→ Logging in to $FTP_HOST as $FTP_USER ($FTP_PROTOCOL)"
  ftp_run "echo '[home]'; cls -1; echo '[$FTP_APP_DIR]'; cls -1 \"$FTP_APP_DIR\"; echo '[$FTP_WEB_DIR]'; cls -1 \"$FTP_WEB_DIR\"" | head -60
  echo "✓ FTP login works"; exit 0
fi

cd "$ROOT"
if [[ -n "$(git status --porcelain 2>/dev/null)" ]]; then
  echo "⚠ Uncommitted changes — they WILL be deployed:"; git status --short | head -10
fi

if (( TESTS )); then
  echo "→ Running tests"
  php artisan test --compact || { echo "✗ Tests failed — nothing deployed"; exit 1; }
fi

bash deploy/stage.sh "$STAGE"
RELEASE="$(git rev-parse --short HEAD 2>/dev/null || echo local) $(date -u +%FT%TZ)"

# Build an lftp command script for one side (app or web): changed files → put, removed files → rm.
plan() { # $1 = sparingin|public_html, $2 = remote dir
  local side="$1" remote="$2"
  mkdir -p "$LAST/$side"
  rsync -ai --checksum --delete --dry-run "$STAGE/$side/" "$LAST/$side/" |
  while IFS= read -r line; do
    local flags="${line%% *}" path="${line#* }"
    if [[ "$flags" == ">f"* ]]; then
      local dir; dir="$(dirname "$path")"
      [[ "$dir" == "." ]] && dir="" || dir="/$dir"
      printf 'mkdir -p -f "%s%s"\nput -O "%s%s" "%s"\n' "$remote" "$dir" "$remote" "$dir" "$STAGE/$side/$path"
    elif [[ "$flags" == "*deleting" && "$path" != */ ]]; then
      printf 'rm -f "%s/%s"\n' "$remote" "$path"
    fi
  done
}

CMDS="$ROOT/deploy/dist/lftp-commands.txt"
{ plan sparingin "$FTP_APP_DIR"; plan public_html "$FTP_WEB_DIR"; } > "$CMDS"
UPLOADS=$(grep -c '^put ' "$CMDS" || true)
DELETES=$(grep -c '^rm ' "$CMDS" || true)
echo "→ Changes: $UPLOADS file(s) to upload, $DELETES to delete"

if (( DRY )); then
  # put -O "<remote dir>" "<local file>"  →  ↑ <remote dir>/<file name>
  awk -F'"' '/^put /{n=split($4,p,"/"); print "  ↑ " $2 "/" p[n]} /^rm /{print "  ✗ " $2}' "$CMDS" | head -40 || true
  if (( UPLOADS + DELETES > 40 )); then echo "  … ($((UPLOADS + DELETES - 40)) more)"; fi
  echo "(dry run — nothing uploaded)"; exit 0
fi

# The post-deploy flag goes last, so the cron only migrates once all code is on the server.
echo "$RELEASE" > "$STAGE/deploy-pending"
printf 'mkdir -p -f "%s/storage/app"\nput -O "%s/storage/app" "%s"\n' "$FTP_APP_DIR" "$FTP_APP_DIR" "$STAGE/deploy-pending" >> "$CMDS"

echo "→ Uploading over $FTP_PROTOCOL to $FTP_HOST"
ftp_run "source \"$CMDS\""

# Remember what's on the server now, for the next diff.
rsync -a --delete "$STAGE/sparingin/" "$LAST/sparingin/"
rsync -a --delete "$STAGE/public_html/" "$LAST/public_html/"
rm -f "$CMDS" "$STAGE/deploy-pending"

echo "✓ Deployed $RELEASE — the cPanel cron runs migrations within ~5 minutes."
