#!/usr/bin/env bash
# Builds a shared-hosting (cPanel, no SSH) package: deploy/dist/sparingin-deploy.zip
#
#   sparingin/     → upload to /home/<user>/sparingin   (app code, OUTSIDE public_html)
#   public_html/   → contents go to /home/<user>/public_html
#   schema.sql     → import via phpMyAdmin (replaces `php artisan migrate`)
#
# Usage: bash deploy/build.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/deploy/dist"
STAGE="$DIST/stage"
APP="$STAGE/sparingin"

echo "→ Cleaning $DIST"
rm -rf "$DIST"
bash "$ROOT/deploy/stage.sh" "$STAGE"

echo "→ .env for production (fresh APP_KEY; fill in DB + domain on the server)"
KEY="base64:$(openssl rand -base64 32)"
sed "s|^APP_KEY=.*|APP_KEY=$KEY|" "$ROOT/deploy/env.production" > "$APP/.env"

echo "→ Database schema (fresh migrate into a throwaway DB, then dump)"
TMPDB="sparingin_deploy_$$"
mysql -uroot -e "CREATE DATABASE \`$TMPDB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
(cd "$ROOT" && DB_DATABASE="$TMPDB" php artisan migrate --force --no-interaction >/dev/null)
{
  echo "-- SparingIn database schema. Import into an EMPTY database via phpMyAdmin."
  mysqldump -uroot --no-data --skip-comments --no-tablespaces --set-gtid-purged=OFF "$TMPDB" 2>/dev/null
  mysqldump -uroot --no-create-info --skip-comments --no-tablespaces --set-gtid-purged=OFF "$TMPDB" migrations 2>/dev/null
} | sed -E 's/ AUTO_INCREMENT=[0-9]+//' > "$STAGE/schema.sql"
mysql -uroot -e "DROP DATABASE \`$TMPDB\`"

echo "→ Zipping"
(cd "$STAGE" && zip -qr "$DIST/sparingin-deploy.zip" sparingin public_html schema.sql)
cp "$STAGE/schema.sql" "$DIST/schema.sql"  # also outside the zip, for phpMyAdmin import
rm -rf "$STAGE"

echo "✓ $(du -h "$DIST/sparingin-deploy.zip" | cut -f1)  $DIST/sparingin-deploy.zip"
