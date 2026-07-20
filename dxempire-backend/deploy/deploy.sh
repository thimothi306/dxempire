#!/usr/bin/env bash
# DXEMPIRE — Production deployment script
# Usage: bash deploy/deploy.sh [--skip-migrate]
# Run as: www-data or the deploy user, from the project root.

set -euo pipefail

APP_DIR="/var/www/dxempire-backend"
PHP="php8.0"
ARTISAN="${PHP} ${APP_DIR}/artisan"
COMPOSER="composer"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  DXEMPIRE deployment — $(date '+%Y-%m-%d %H:%M:%S')"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

cd "${APP_DIR}"

# ── 1. Pull latest code ────────────────────────────────────────────────────
echo "[1/9] Pulling latest code..."
git fetch --all --prune
git checkout main
git pull origin main

# ── 2. Install PHP dependencies (production only) ─────────────────────────
echo "[2/9] Installing Composer dependencies..."
${COMPOSER} install \
    --no-dev \
    --no-interaction \
    --no-ansi \
    --optimize-autoloader \
    --prefer-dist

# ── 3. Enable maintenance mode ────────────────────────────────────────────
echo "[3/9] Enabling maintenance mode..."
${ARTISAN} down --retry=10

# ── 4. Run database migrations ────────────────────────────────────────────
if [[ "${1:-}" != "--skip-migrate" ]]; then
    echo "[4/9] Running migrations..."
    ${ARTISAN} migrate --force
else
    echo "[4/9] Skipping migrations (--skip-migrate flag set)."
fi

# ── 5. Clear & rebuild caches ─────────────────────────────────────────────
echo "[5/9] Rebuilding caches..."
${ARTISAN} config:clear
${ARTISAN} config:cache
${ARTISAN} route:clear
${ARTISAN} route:cache
${ARTISAN} view:clear
${ARTISAN} view:cache
${ARTISAN} event:clear
${ARTISAN} event:cache

# ── 6. Storage link ───────────────────────────────────────────────────────
echo "[6/9] Ensuring storage symlink exists..."
${ARTISAN} storage:link --force 2>/dev/null || true

# ── 7. Set file permissions ───────────────────────────────────────────────
echo "[7/9] Setting file permissions..."
chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

# ── 8. Restart queue workers ──────────────────────────────────────────────
echo "[8/9] Restarting queue workers..."
${ARTISAN} queue:restart
supervisorctl restart dxempire:*

# ── 9. Disable maintenance mode ───────────────────────────────────────────
echo "[9/9] Bringing app back online..."
${ARTISAN} up

echo ""
echo "✓ Deployment complete — $(date '+%Y-%m-%d %H:%M:%S')"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
