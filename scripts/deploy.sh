#!/usr/bin/env bash
set -e

COMPOSE="docker compose -f docker-compose.prod.yml"

echo "=== Fin Deploy ==="
echo "Started at: $(date)"
echo ""

cd "$(dirname "$0")/.."

chmod +x nginx/entrypoint.sh

echo "[1/4] Building and starting containers..."
$COMPOSE up -d --build

echo "[1.5/4] Fixing storage permissions..."
sudo chown -R 33:33 storage bootstrap/cache 2>/dev/null || \
  $COMPOSE exec -T app chown -R www-data:www-data storage bootstrap/cache

echo "[2/4] Waiting for database..."
until $COMPOSE exec database healthcheck.sh --connect > /dev/null 2>&1; do
    echo "  Waiting for DB..."
    sleep 2
done

echo "[3/4] Running migrations..."
$COMPOSE exec -T app php artisan migrate --force -v

echo "[4/4] Caching configuration..."
$COMPOSE exec app php artisan config:clear
$COMPOSE exec app php artisan config:cache
$COMPOSE exec app php artisan route:cache
$COMPOSE exec app php artisan view:cache

echo ""
echo "=== Deploy Complete ==="
echo "Finished at: $(date)"
