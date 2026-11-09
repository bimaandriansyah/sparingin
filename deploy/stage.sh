#!/usr/bin/env bash
# Assembles the two deployable folders (no secrets, no database):
#
#   $STAGE/sparingin/    → /home/<user>/sparingin      (app code, OUTSIDE public_html)
#   $STAGE/public_html/  → /home/<user>/public_html    (web root)
#
# Used by deploy/build.sh (manual zip) and .github/workflows/deploy.yml (FTP).
# Usage: bash deploy/stage.sh [stage-dir]   (default: deploy/dist/stage)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="${1:-$ROOT/deploy/dist/stage}"
APP="$STAGE/sparingin"
WEB="$STAGE/public_html"

rm -rf "$STAGE" && mkdir -p "$APP" "$WEB"

echo "→ Building frontend assets"
(cd "$ROOT" && npm run build >/dev/null)

echo "→ Copying app code"
rsync -a "$ROOT/" "$APP/" \
  --exclude '/.git' --exclude '/.github' --exclude '/.claude' --exclude '/node_modules' --exclude '/vendor' \
  --exclude '/deploy' --exclude '/tests' --exclude '/.env' --exclude '/.env.*' \
  --exclude '/public' --exclude '/storage' --exclude '/database/database.sqlite' \
  --exclude '/bootstrap/cache/*.php' --exclude '/*.md' --exclude '/phpunit.xml' \
  --exclude '/package*.json' --exclude '/vite.config.js' --exclude '/.phpunit*' --exclude '/resources/brand'

echo "→ Empty storage skeleton (writable dirs only, no local uploads/logs)"
for d in app/private app/public framework/cache/data framework/sessions framework/views logs; do
  mkdir -p "$APP/storage/$d"
done

echo "→ Installing production PHP dependencies (no dev)"
(cd "$APP" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

echo "→ Web root (public/ → public_html/)"
rsync -a "$ROOT/public/" "$WEB/" --exclude 'storage' --exclude 'hot'
mkdir -p "$WEB/storage"   # uploads land here (PUBLIC_STORAGE_PATH)
cp "$ROOT/deploy/index.php" "$WEB/index.php"

echo "✓ staged in $STAGE"
