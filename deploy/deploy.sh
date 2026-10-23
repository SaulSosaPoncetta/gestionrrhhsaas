#!/usr/bin/env bash
# ============================================================
#  Actualiza Gestión RRHH en el VPS con lo último de GitHub.
#  Uso (en el VPS):   bash /var/www/gestionrrhh/deploy/deploy.sh
#  Rama por defecto: main  ·  otra:  bash deploy/deploy.sh otra-rama
# ============================================================
set -euo pipefail

APP_DIR="/var/www/gestionrrhh"
RAMA="${1:-main}"
PHP="php8.4"

cd "$APP_DIR"
echo "▶ Actualizando Gestión RRHH (rama $RAMA)…"

$PHP artisan down --retry=30 || true
trap '$PHP artisan up' EXIT     # vuelve a levantar aunque algo falle

git fetch origin "$RAMA"
git checkout "$RAMA"
git reset --hard "origin/$RAMA"

composer install --no-dev --optimize-autoloader --no-interaction

$PHP artisan migrate --force

[ -L public/storage ] || $PHP artisan storage:link
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

# Permisos de escritura para PHP-FPM
sudo chown -R "$USER":www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache

sudo systemctl reload php8.4-fpm

echo "✅ Listo: https://gestionrrhh.migestion.com.ar"
