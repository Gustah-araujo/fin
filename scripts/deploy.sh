#!/usr/bin/env bash
set -e

COMPOSE="docker compose -f docker-compose.prod.yml"

echo "=== Fin Deploy ==="
echo "Started at: $(date)"
echo ""

cd "$(dirname "$0")/.."

echo "[1/5] Pulling latest code..."
git pull origin main

echo "[2/5] Building and starting containers..."
$COMPOSE up -d --build

echo "[3/5] Waiting for database..."
until $COMPOSE exec database healthcheck.sh --connect > /dev/null 2>&1; do
    echo "  Waiting for DB..."
    sleep 2
done

echo "[4/5] Running migrations..."
$COMPOSE exec -T app php artisan migrate --force -v

echo "[5/5] Caching configuration..."
$COMPOSE exec app php artisan config:clear
$COMPOSE exec app php artisan config:cache
$COMPOSE exec app php artisan route:cache
$COMPOSE exec app php artisan view:cache

echo ""
echo "=== Deploy Complete ==="
echo "Finished at: $(date)"
