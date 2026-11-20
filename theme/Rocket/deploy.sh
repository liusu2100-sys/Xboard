#!/bin/bash
set -euo pipefail

ROOT="/www/wwwroot/www.xboard.com"
THEME_DIR="$ROOT/theme/Rocket"
PUBLIC_DIR="$ROOT/public/theme/Rocket"

cd "$THEME_DIR"

echo "Building Rocket..."
if [ -x "$THEME_DIR/node_modules/.bin/vite" ]; then
  "$THEME_DIR/node_modules/.bin/vite" build
elif [ -x "$ROOT/theme/Flavor/node_modules/.bin/vite" ]; then
  NODE_PATH="$ROOT/theme/Flavor/node_modules" "$ROOT/theme/Flavor/node_modules/.bin/vite" build
else
  npx vite build
fi

echo "Syncing built assets into theme/Rocket/assets..."
rm -rf assets
mkdir -p assets/.vite
cp -R dist/assets/. assets/
cp dist/.vite/manifest.json assets/.vite/manifest.json
MAIN_JS="$(find dist/assets -maxdepth 1 -type f -name 'app*.js' | head -n 1)"
MAIN_CSS="$(find dist/assets -maxdepth 1 -type f -name 'style*.css' | head -n 1)"
cp "$MAIN_JS" assets/app.js
cp "$MAIN_CSS" assets/style.css

# Compatibility aliases for pages/CDNs that still reference older Rocket builds.
for legacy_js in app.C9iEFlLI.js app.CZdzYtQd.js; do
  printf 'import "./app.js";\n' > "assets/$legacy_js"
done
for legacy_css in style.DDfWiIPM.css style.WUIc_nfn.css; do
  printf '@import "./style.css";\n' > "assets/$legacy_css"
done

echo "Syncing Rocket into public/theme/Rocket..."
rm -rf "$PUBLIC_DIR"
mkdir -p "$PUBLIC_DIR/assets/.vite"
cp -R assets/. "$PUBLIC_DIR/assets/"
cp dashboard.blade.php "$PUBLIC_DIR/dashboard.blade.php"
cp config.json "$PUBLIC_DIR/config.json"

if command -v php >/dev/null 2>&1; then
  cd "$ROOT"
  php artisan view:clear >/dev/null 2>&1 || true
fi

echo "Rocket deployment finished."
