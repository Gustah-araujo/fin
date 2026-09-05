#!/usr/bin/env bash
set -e

echo "=== Fin Deploy ==="
echo "Started at: $(date)"
echo ""

cd "$(dirname "$0")/.."

echo "[1/5] Pulling latest code..."
git pull origin main

echo "[2/5] Building and starting containers..."
docker compose up -d --build

echo "[3/5] Waiting for database..."
until docker compose exec database healthcheck.sh --connect > /dev/null 2>&1; do
    echo "  Waiting for DB..."
    sleep 2
done

echo "[4/5] Running migrations..."
docker compose exec -T app php artisan migrate --force -v

echo "[5/5] Caching configuration..."
docker compose exec app php artisan config:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

echo ""
echo "=== Deploy Complete ==="
echo "Finished at: $(date)"
