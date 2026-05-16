#!/usr/bin/env bash
# Build a BizSpine release ZIP for shared hosting.
# Usage: ./scripts/build-release.sh [Subdir] [SiteUrl]
set -euo pipefail

SUBDIR="${1:-BizSpine}"
SITE_URL="${2:-}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"
DEPLOY="$ROOT/deploy"
OUT="$ROOT/release"
ZIP_NAME="BizSpine-release.zip"
BASE_PATH="/${SUBDIR}/"

echo "BizSpine release build"
echo "======================"

cd "$FRONTEND"
if [[ ! -d node_modules ]]; then
  npm ci
fi
export VITE_BASE_PATH="$BASE_PATH"
if [[ -n "$SITE_URL" ]]; then
  export VITE_API_BASE_URL="${SITE_URL%/}/api"
else
  unset VITE_API_BASE_URL || true
fi
npm run build

cd "$BACKEND"
composer install --no-dev --optimize-autoloader --no-interaction

STAGING="$OUT/staging"
rm -rf "$STAGING"
mkdir -p "$STAGING/bizspine-backend"
mkdir -p "$STAGING/public_html/$SUBDIR/api"

rsync -a \
  --exclude coverage-report --exclude tests --exclude .phpunit.cache --exclude node_modules \
  --exclude 'database.sqlite' --exclude '.env' --exclude '.bizspine-installed' \
  "$BACKEND/" "$STAGING/bizspine-backend/"
mkdir -p "$STAGING/bizspine-backend/protected/db"

cp -a "$FRONTEND/dist/." "$STAGING/public_html/$SUBDIR/"

cp "$DEPLOY/BizSpine-frontend.htaccess" "$STAGING/public_html/$SUBDIR/.htaccess"
sed -i "s|/BizSpine/|/$SUBDIR/|g" "$STAGING/public_html/$SUBDIR/.htaccess"

cp "$DEPLOY/BizSpine-api-index.php" "$STAGING/public_html/$SUBDIR/api/index.php"
cp "$DEPLOY/BizSpine-api.htaccess" "$STAGING/public_html/$SUBDIR/api/.htaccess"
sed -i "s|/BizSpine/|/$SUBDIR/|g" "$STAGING/public_html/$SUBDIR/api/.htaccess"

cp "$DEPLOY/install.php" "$STAGING/public_html/$SUBDIR/install.php"
cp "$DEPLOY/INSTALL.html" "$STAGING/public_html/$SUBDIR/INSTALL.html"

mkdir -p "$OUT"
rm -f "$OUT/$ZIP_NAME"
(cd "$STAGING" && zip -rq "$OUT/$ZIP_NAME" .)
rm -rf "$STAGING"

echo ""
echo "Done: $OUT/$ZIP_NAME"
echo "Upload and extract on the server, then open /$SUBDIR/install.php"
