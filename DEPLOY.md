# Deploy Guide — Fin on Docker

## Local Development

```bash
# Clone and setup
git clone <repo-url> fin
cd fin
cp .env.example .env

# Generate APP_KEY
php artisan key:generate

# Start all services
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate

# Install dependencies (first time)
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app npm run build
```

Access: http://localhost:8090

### Dev Services

| Service | URL |
|---------|-----|
| App | http://localhost:8090 |
| phpMyAdmin | http://localhost:8091 |
| Mailpit | http://localhost:8026 |

### Dev Commands

```bash
# Artisan
docker compose exec app php artisan tinker
docker compose exec app php artisan migrate:fresh --seed

# NPM (hot reload)
docker compose exec app npm run dev

# Database CLI
docker compose exec db mariadb -u fin -p fin

# Cypress (E2E)
docker compose --profile testing run --rm cypress
```

---

## Production Deployment (VM 1vCPU / 1GB)

### Prerequisites

- Ubuntu Server 20.04/22.04/24.04 LTS
- Domain pointing to VM IP (GoDaddy DNS A record)
- SSH access to VM

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

### 3. Create Project Directory

```bash
mkdir -p /var/www/fin
```

> Files will be synced via CI/CD rsync. Git is not needed on server.

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

### 5. Start Containers (Production)

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

This builds and starts:
- `fin_app` — PHP-FPM
- `fin_queue` — Queue worker
- `fin_scheduler` — Task scheduler
- `fin_db` — MariaDB 10.11
- `fin_nginx` — Reverse proxy

### 6. Run Initial Migration

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

### 7. Cache Configuration

```bash
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
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

Deploy flow: CI builds dependencies → rsync to server → SSH runs deploy script.

### Deploy steps

1. CI installs PHP and Node dependencies
2. CI builds frontend assets (`npm run build`)
3. CI rsyncs all files to server (excluding `.env`, `storage/`)
4. Server runs `scripts/deploy.sh`:
   - `docker compose -f docker-compose.prod.yml up -d --build`
   - Wait for database healthcheck
   - `php artisan migrate --force`
   - Clear and rebuild config/route/view caches

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
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'npm'

      - name: Install dependencies
        run: |
          composer install --no-dev --optimize-autoloader
          npm ci

      - name: Build assets
        run: npm run build

      - name: Rsync to server
        uses: burnett01/rsync-deployments@6
        with:
          switches: -avzr --delete
            --exclude=.env
            --exclude=storage
            --exclude=node_modules
            --exclude=.git
            --exclude=cypress
            --exclude=tests
            --exclude=.specs
          path: ./
          remote_path: /var/www/fin/
          remote_host: ${{ secrets.SSH_HOST }}
          remote_user: ${{ secrets.SSH_USER }}
          remote_key: ${{ secrets.SSH_KEY }}

      - name: Deploy containers
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.SSH_HOST }}
          username: ${{ secrets.SSH_USER }}
          key: ${{ secrets.SSH_KEY }}
          script: cd /var/www/fin && ./scripts/deploy.sh
```

### Required GitHub Secrets

| Secret | Description |
|--------|-------------|
| `SSH_HOST` | VM IP address |
| `SSH_USER` | SSH username (must be in `docker` group) |
| `SSH_KEY` | Private SSH key for authentication |

## Monitoring (Production)

### Check Container Status

```bash
docker compose -f docker-compose.prod.yml ps
```

### View Logs

```bash
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml logs -f queue
docker compose -f docker-compose.prod.yml logs -f nginx
```

### Health Status

```bash
docker compose -f docker-compose.prod.yml ps --format "table {{.Name}}\t{{.Status}}"
```

## Useful Commands (Production)

```bash
# Enter app container
docker compose -f docker-compose.prod.yml exec app bash

# Artisan commands
docker compose -f docker-compose.prod.yml exec app php artisan tinker
docker compose -f docker-compose.prod.yml exec app php artisan cache:clear

# Restart single service
docker compose -f docker-compose.prod.yml restart queue

# View database
docker compose -f docker-compose.prod.yml exec database mariadb -u root -p

# Queue monitoring
docker compose -f docker-compose.prod.yml exec app php artisan queue:monitor database:default
```

## Troubleshooting (Production)

| Issue | Solution |
|-------|----------|
| Migration fails | `docker compose -f docker-compose.prod.yml exec app php artisan migrate:status` then fix |
| Queue not processing | `docker compose -f docker-compose.prod.yml restart queue` |
| 502 Bad Gateway | Check `app` container health: `docker compose -f docker-compose.prod.yml ps` |
| SSL expired | `docker compose -f docker-compose.prod.yml run --rm certbot renew` then `docker compose -f docker-compose.prod.yml exec nginx nginx -s reload` |
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
