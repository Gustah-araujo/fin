# Deploy Guide — Fin on Docker (VM 1vCPU / 1GB)

## Prerequisites

- Ubuntu Server 20.04/22.04/24.04 LTS
- Domain pointing to VM IP (GoDaddy DNS A record)
- SSH access to VM

## Initial Setup

### 1. Install Docker

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
# Logout and back in for group to take effect
```

### 2. Create SWAP (2GB recommended)

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### 3. Clone Repository

```bash
cd /var/www
git clone <repo-url> fin
cd fin
```

### 4. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_DOMAIN=your-domain.com
APP_KEY=base64:generate-with-php-artisan-key:generate

DB_DATABASE=fin
DB_USERNAME=fin
DB_PASSWORD=<strong-password>
DB_ROOT_PASSWORD=<strong-root-password>

SESSION_ENCRYPT=true
QUEUE_CONNECTION=database
```

Generate APP_KEY:

```bash
php artisan key:generate --show
# Copy output to .env
```

## Build and Launch

### 5. Start Containers

```bash
docker compose up -d --build
```

This builds and starts:
- `fin_app` — PHP-FPM
- `fin_queue` — Queue worker
- `fin_scheduler` — Task scheduler
- `fin_db` — MariaDB 10.11
- `fin_nginx` — Reverse proxy

### 6. Run Initial Migration

```bash
docker compose exec app php artisan migrate --force
```

### 7. Cache Configuration

```bash
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

## SSL Certificate

### 8. Obtain Let's Encrypt Certificate

```bash
./scripts/setup-ssl.sh your-domain.com your@email.com
```

This script:
1. Creates dummy certificate for nginx to start
2. Starts containers
3. Requests real certificate via webroot challenge
4. Reloads nginx with real certificate
5. Starts certbot renewal service

Certificate auto-renews every 12 hours. Nginx reloads every 6 hours to pick up renewed certs.

## CI/CD Deploy (Updates)

The `scripts/deploy.sh` handles zero-downtime updates:

```bash
./scripts/deploy.sh
```

Or via SSH from CI:

```bash
ssh user@vm '/var/www/fin/scripts/deploy.sh'
```

Deploy steps:
1. `git pull origin main`
2. `docker compose up -d --build`
3. Wait for database healthcheck
4. `php artisan migrate --force`
5. Clear and rebuild config/route/view caches

### CI Example (GitHub Actions)

```yaml
name: Deploy
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.SSH_HOST }}
          username: ${{ secrets.SSH_USER }}
          key: ${{ secrets.SSH_KEY }}
          script: cd /var/www/fin && ./scripts/deploy.sh
```

## Monitoring

### Check Container Status

```bash
docker compose ps
```

### View Logs

```bash
docker compose logs -f app
docker compose logs -f queue
docker compose logs -f nginx
```

### Health Status

```bash
docker compose ps --format "table {{.Name}}\t{{.Status}}"
```

## Useful Commands

```bash
# Enter app container
docker compose exec app bash

# Artisan commands
docker compose exec app php artisan tinker
docker compose exec app php artisan cache:clear

# Restart single service
docker compose restart queue

# View database
docker compose exec database mariadb -u root -p

# Queue monitoring
docker compose exec app php artisan queue:monitor database:default
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Migration fails | `docker compose exec app php artisan migrate:status` then fix |
| Queue not processing | `docker compose restart queue` |
| 502 Bad Gateway | Check `app` container health: `docker compose ps` |
| SSL expired | `docker compose run --rm certbot renew` then `docker compose exec nginx nginx -s reload` |
| Out of disk | `docker system prune -f` and check logs |

## Architecture

```
Internet → Nginx (80/443) → PHP-FPM (app:9000)
                                  ↓
                            MariaDB (fin_db)
                                  
Services:
- app:         PHP-FPM (HTTP requests)
- queue:       queue:work (background jobs)
- scheduler:   schedule:work (task scheduling)
- database:    MariaDB 10.11
- nginx:       Reverse proxy + SSL termination
- certbot:     Let's Encrypt renewal
```

## Environment Variables Reference

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_DOMAIN` | Domain for nginx config | `fin.example.com` |
| `APP_URL` | Public URL | `https://fin.example.com` |
| `APP_KEY` | Laravel encryption key | `base64:...` |
| `DB_DATABASE` | Database name | `fin` |
| `DB_USERNAME` | Database user | `fin` |
| `DB_PASSWORD` | Database password | `...` |
| `DB_ROOT_PASSWORD` | Root password | `...` |
| `QUEUE_CONNECTION` | Queue driver | `database` |
| `SESSION_DRIVER` | Session driver | `database` |
| `SESSION_ENCRYPT` | Encrypt sessions | `true` |
