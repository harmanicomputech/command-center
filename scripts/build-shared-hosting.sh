#!/usr/bin/env bash
# Builds the upload zips for DirectAdmin / cPanel hosting without SSH
# (adapted from Election Shield): production dependencies included, the
# compiled front end (public/build), a public_html with the front controller
# and PWA files, and a .env.
#
#   scripts/build-shared-hosting.sh            full install zip (includes .env)
#   scripts/build-shared-hosting.sh --update   update zip without .env, so
#                                              extracting it keeps your settings
#   scripts/build-shared-hosting.sh --ci       full zip whose .env holds
#                                              placeholders; the app fills in
#                                              fresh secrets on its first
#                                              request (safe to publish)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP=command-center
BUILD="$ROOT/build/shared-hosting"
DIST="$ROOT/dist"
MODE="${1:-full}"
SUFFIX=""
[ "$MODE" = "--update" ] && SUFFIX="-update"
ZIP="$DIST/$APP-shared-hosting$SUFFIX.zip"

# The front end is compiled here, never on the host (it has no Node).
(cd "$ROOT" && npm ci --no-audit --no-fund --silent && npm run build --silent >/dev/null)

rm -rf "$BUILD" && mkdir -p "$BUILD/$APP" "$BUILD/public_html" "$DIST"

# Application code (tracked files only, so local .env / databases never leak).
git -C "$ROOT" ls-files -z --cached --others --exclude-standard \
  | grep -zv -E '^(tests/|\.github/|deploy/|scripts/|public/|docs/|phpunit\.xml|node_modules/)' \
  | (cd "$ROOT" && while IFS= read -r -d '' file; do
      [ -f "$file" ] && cp --parents "$file" "$BUILD/$APP"
    done)

(cd "$BUILD/$APP" && COMPOSER_ALLOW_SUPERUSER=1 composer install \
  --prefer-dist --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts --quiet)
(cd "$BUILD/$APP" && php artisan package:discover --ansi >/dev/null)

find "$BUILD/$APP/vendor" -depth -type d \( -name .git -o -name .github \) -exec rm -rf {} +

mkdir -p "$BUILD/$APP/storage/"{app/private,framework/{cache/data,sessions,views},logs}
rm -f "$BUILD/$APP/bootstrap/cache/"*.php

# Web root: PWA files, fonts, icons and the compiled build, plus the
# shared-hosting front controller, which finds the app folder outside it.
(cd "$ROOT/public" && git -C "$ROOT" ls-files -z --cached --others --exclude-standard public | sed -z 's#^public/##' \
  | while IFS= read -r -d '' file; do cp --parents "$file" "$BUILD/public_html"; done)
cp -r "$ROOT/public/build" "$BUILD/public_html/"
cp "$ROOT/deploy/shared-hosting/"{index.php,.htaccess,robots.txt} "$BUILD/public_html/"

# A new service worker version on every build, so phones pick up the release.
VERSION="$(date -u +%Y%m%d%H%M)-$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo local)"
sed -i "s/^const VERSION = '[^']*';/const VERSION = '$VERSION';/" "$BUILD/public_html/sw.js"

case "$MODE" in
  --update) ;;
  --ci) cp "$ROOT/deploy/shared-hosting/env.template" "$BUILD/$APP/.env" ;;
  *) php -r '
      $env = file_get_contents($argv[1]);
      $values = [
          "APP_KEY" => "base64:".base64_encode(random_bytes(32)),
          "ADMIN_PASSWORD" => bin2hex(random_bytes(12)),
      ];
      foreach ($values as $key => $value) {
          $env = str_replace("{{".$key."}}", $value, $env);
      }
      file_put_contents($argv[2], $env);
    ' "$ROOT/deploy/shared-hosting/env.template" "$BUILD/$APP/.env" ;;
esac

rm -f "$ZIP"
(cd "$BUILD" && zip -qr "$ZIP" "$APP" public_html)

echo "Built $ZIP ($(du -h "$ZIP" | cut -f1)), service worker $VERSION"
[ "$MODE" = "full" ] && echo "Setup key (ADMIN_PASSWORD): $(grep '^ADMIN_PASSWORD=' "$BUILD/$APP/.env" | cut -d= -f2)"
exit 0
