# Deploying Athari

Athari runs as two containers on one small server: **MariaDB** and the **web app** (Drupal 11 on FrankenPHP, which is the Caddy web server with PHP built in). Caddy gets and renews the HTTPS certificate by itself.

```
Internet ──443──▶ Caddy (in the app container) ──▶ Drupal (PHP 8.3) ──▶ MariaDB
                   │ blocks private files, sets security headers
                   └─ volumes: uploaded files, private files, certificates
```

## What you need

| | |
|---|---|
| A server | Ubuntu 24.04 LTS, 2 GB RAM or more (1 GB is too small), 20 GB disk, a public IPv4 address |
| A name for the site | A domain or subdomain whose **A record** points at the server's IP (best), or leave it blank and the installer uses a free `athari.<ip>.sslip.io` name |
| Your Daraja sandbox values | Consumer key, consumer secret, passkey. Typed on the server when asked, never sent anywhere else |

## Install (one command)

SSH into the server as root (or a sudo user) and run:

```bash
curl -fsSL https://raw.githubusercontent.com/bmitigoa/athari/main/deploy/bootstrap.sh | sudo bash
```

It asks a few questions, builds everything (about 5 to 10 minutes the first time), installs Drupal from the exported configuration, loads the Kenya demo data and prints the address and the demo logins.

Where things end up:

| What | Where (on the server) |
|---|---|
| The code | `/opt/athari/app` (a Git clone) |
| Secrets (database password, admin password, Daraja keys) | `/opt/athari/secrets` (root only). `credentials.env` holds the passwords. They are **not** printed. |
| Backups | `/opt/athari/backups` (database and files, daily, 14 days kept) |
| Schedules | `/etc/cron.d/athari`: Drupal cron every 15 minutes, backup daily |

## Everyday tasks

```bash
cd /opt/athari/app
sudo ./deploy/update.sh                       # deploy the latest version (backs up first)
sudo ATHARI_REF=v1.0.0 ./deploy/update.sh     # deploy a specific tag
sudo ./deploy/backup.sh                       # backup now
docker compose --env-file /opt/athari/secrets/compose.env -f deploy/docker-compose.yml logs -f app   # watch the logs
docker compose --env-file /opt/athari/secrets/compose.env -f deploy/docker-compose.yml exec -w /app app drush --root=/app/web status   # run Drush
```

## What is protected

- Only ports 22, 80 and 443 are open (ufw); the database is not published at all. fail2ban slows SSH guessing; security updates install themselves.
- Caddy answers 404 for settings, YAML, SQL, `composer.*`, `/vendor`, `/config`, `/scripts`, `install.php`, `update.php`, hidden files and every PHP file except `index.php`. It adds HSTS, `X-Content-Type-Options`, `X-Frame-Options`, a referrer policy, and hides the server and Drupal version.
- Errors are logged, never shown. PHP's `expose_php` is off.
- The production image is built with `composer install --no-dev`, so Devel and the test tools are not on the server, and the "dev" config split is off.
- Drupal only answers to the configured host name (`trusted_host_patterns`).

## Check the live site

From any computer with this repository:

```powershell
powershell -File scripts/day5-05-security-http.ps1 -Base https://YOUR-SITE -Live
powershell -File scripts/day5-02-verify-api.ps1   -Base https://YOUR-SITE
```

## Trying it on your own computer first

With Docker (for example inside WSL) and no real server:

```bash
ATHARI_LOCAL_TEST=1 ATHARI_NONINTERACTIVE=1 ATHARI_SECRETS_DIR=/tmp/athari-secrets bash deploy/install.sh
# then open https://localhost:8443 (accept the self-signed certificate)
```

## Restoring a backup

```bash
cd /opt/athari/app
. /opt/athari/secrets/credentials.env
gunzip -c /opt/athari/backups/db-YYYYMMDD-HHMMSS.sql.gz | \
  docker compose --env-file /opt/athari/secrets/compose.env -f deploy/docker-compose.yml exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" athari
```
