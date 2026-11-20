#!/bin/bash
set -euo pipefail

ROOT="/www/wwwroot/www.xboard.com"
THEME_DIR="$ROOT/theme/Flavor"
PUBLIC_DIR="$ROOT/public/theme/Flavor"

cd "$THEME_DIR"

echo "Building Flavor..."
npx vite build

echo "Syncing built assets into theme/Flavor/assets..."
rm -rf assets
mkdir -p assets/.vite
cp -R dist/assets/. assets/
cp dist/.vite/manifest.json assets/.vite/manifest.json

echo "Syncing Flavor into public/theme/Flavor..."
rm -rf "$PUBLIC_DIR/assets"
mkdir -p "$PUBLIC_DIR/assets/.vite"
cp -R assets/. "$PUBLIC_DIR/assets/"
cp dashboard.blade.php "$PUBLIC_DIR/dashboard.blade.php"
cp config.json "$PUBLIC_DIR/config.json"

if command -v php >/dev/null 2>&1; then
  cd "$ROOT"
  php artisan view:clear >/dev/null 2>&1 || true
fi

echo "Flavor deployment finished."
