#!/usr/bin/env bash
set -e

DOMAIN="${1:?Usage: ./scripts/setup-ssl.sh <domain>}"
EMAIL="${2:?Usage: ./scripts/setup-ssl.sh <domain> <email>}"

COMPOSE="docker compose -f docker-compose.prod.yml"

echo "=== Fin SSL Setup ==="
echo "Domain: $DOMAIN"
echo "Email: $EMAIL"
echo ""

echo "[1/6] Creating directories..."
mkdir -p certbot/conf/live/$DOMAIN certbot/www

echo "[2/6] Generating dummy certificate..."
openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
  -keyout certbot/conf/live/$DOMAIN/privkey.pem \
  -out certbot/conf/live/$DOMAIN/fullchain.pem \
  -subj "/CN=localhost" 2>/dev/null

echo "[3/6] Starting containers..."
$COMPOSE up -d nginx app database

echo "[4/6] Waiting for nginx..."
sleep 5

echo "[5/6] Requesting Let's Encrypt certificate..."
rm -rf certbot/conf/live/$DOMAIN
$COMPOSE run --rm certbot certonly --webroot \
  --webroot-path=/var/www/certbot \
  -d "$DOMAIN" --email "$EMAIL" --agree-tos --no-eff-email

echo "[6/6] Reloading nginx and starting certbot..."
$COMPOSE exec nginx nginx -s reload
$COMPOSE up -d certbot

echo ""
echo "=== SSL Setup Complete ==="
echo "Site available at: https://$DOMAIN"
