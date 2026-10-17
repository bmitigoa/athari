# Deploying Athari

Athari is **deployment-ready**: the repository contains everything needed to put it on a small server with one command. It has not been hosted publicly. The reference run was done on a local Docker stack that follows the same steps as a server (see [Status of the deployment bundle](#status-of-the-deployment-bundle)).

## How it runs in production

Two containers on one server:

```
Internet --443--> Caddy (inside the "app" container) --> Drupal 11 (PHP 8.3) --> MariaDB ("db" container)
                   |  HTTPS certificates, security headers, blocks private files
                   +- volumes: uploaded files, private files, certificates
```

The web app is Drupal 11 on **FrankenPHP**, which is the Caddy web server with PHP built in. Caddy obtains and renews the HTTPS certificate by itself. The database is never published outside the server.

The files are in [`deploy/`](../deploy):

| File | Purpose |
|---|---|
| `bootstrap.sh` | Run once as root on a fresh Ubuntu 24.04 server. Installs Docker, firewall, automatic security updates, fail2ban, swap, then runs `install.sh` |
| `install.sh` | Asks a few questions, writes secrets, builds the image, installs Drupal from `config/sync`, creates users, loads the Kenya demo data, schedules cron and backups |
| `Dockerfile`, `docker-compose.yml`, `Caddyfile`, `php.ini` | The production image and its settings (built with `composer install --no-dev`, so Devel and test tools are not present) |
| `create-users.php` | Creates the staff accounts (and `scripts/kenya-00-sectors.php` the five Sector terms) |
| `backup.sh`, `cron.sh`, `update.sh` | Nightly backup, Drupal cron, and the update routine |

## What you need

| | |
|---|---|
| A server | Ubuntu 24.04 LTS, 2 GB RAM or more (1 GB is too small), 20 GB disk, public IPv4. Intel/AMD or ARM64 |
| A host name | A domain or subdomain with an **A record** pointing at the server, or leave it blank and the installer uses a free `athari.<ip>.sslip.io` name |
| Daraja sandbox values | Consumer key, consumer secret, passkey (typed on the server, never stored in Git) |

## One-command install (any Ubuntu VPS)

SSH into the server and run:

```bash
curl -fsSL https://raw.githubusercontent.com/bmitigoa/athari/main/deploy/bootstrap.sh | sudo bash
```

To skip the host-name question: `... | sudo ATHARI_DOMAIN=athari.example.co.ke bash`.

It takes 10 to 15 minutes the first time and prints the address and two limited demo logins (`fieldofficer`, `reviewer`). The administrator password is generated and kept only on the server, in `/opt/athari/secrets/credentials.env` (root only). It is never printed.

| What | Where (on the server) |
|---|---|
| The code | `/opt/athari/app` (a Git clone) |
| Secrets (database, admin password, Daraja keys) | `/opt/athari/secrets` |
| Backups | `/opt/athari/backups` (daily, 14 days kept) |
| Schedules | `/etc/cron.d/athari`: Drupal cron every 15 minutes, backup daily at 02:30 |

The M-Pesa callback base URL is set to the site's own `https://` address, so Safaricom sandbox callbacks reach it directly.

## Oracle Cloud Always Free (Ampere A1), step by step

This is a free option that is large enough for Athari (2 OCPU, 12 GB RAM).

### 1. Sign up
1. Go to oracle.com/cloud/free and choose *Start for free*.
2. **Home region** cannot be changed later, and Always Free A1 servers only exist there. Pick one with good A1 capacity rather than the nearest (Frankfurt, Amsterdam and London are usually better than Johannesburg). Latency from Kenya is fine in any of them.
3. **Card check:** use a Visa or Mastercard with international payments and 3-D Secure enabled. A temporary hold of about USD 1 is placed and refunded. Prepaid and virtual cards are often rejected, and the name and address must match the card.
4. After the 30-day trial the account falls back to Always Free; nothing is charged unless you upgrade.

### 2. Create the server
Compute, Instances, *Create instance*:
1. Image: **Canonical Ubuntu 24.04** (aarch64).
2. Shape: **Ampere, VM.Standard.A1.Flex, 2 OCPU, 12 GB**.
3. Networking: keep *Assign a public IPv4 address* on.
4. SSH key: on Windows PowerShell run `ssh-keygen -t ed25519 -f $HOME\.ssh\oracle_athari`, then paste the contents of `oracle_athari.pub` (the public file only) into *Paste public keys*.
5. Create, wait for *Running*, and copy the **Public IP address**.

**"Out of capacity for shape VM.Standard.A1.Flex"** is common. Try, in order: retry later (early morning or late night); another Availability Domain; a smaller size (1 OCPU, 6 GB) and resize later; upgrade the account to *Pay As You Go* (nothing is charged within the Always Free limits and capacity is much better; set a USD 1 budget alert); or use any other 2 GB Ubuntu server, since the installer is the same.

### 3. Open ports 80 and 443
Two firewalls need to allow them.

1. **Oracle security list:** Networking, your VCN, your subnet, Security Lists, Default, *Add Ingress Rules*: source `0.0.0.0/0`, TCP 80; TCP 443; UDP 443.
2. **Ubuntu's iptables:** Oracle's Ubuntu image rejects everything except SSH. `bootstrap.sh` opens 80 and 443 and saves the rules. By hand:
   ```bash
   sudo iptables -I INPUT 5 -p tcp --dport 80 -j ACCEPT
   sudo iptables -I INPUT 5 -p tcp --dport 443 -j ACCEPT
   sudo iptables -I INPUT 5 -p udp --dport 443 -j ACCEPT
   sudo netfilter-persistent save
   ```

### 4. Connect and install
```powershell
ssh -i $HOME\.ssh\oracle_athari ubuntu@YOUR_PUBLIC_IP
```
then run the one-command install above.

### 5. Host name and HTTPS
HTTPS is automatic once the name points at the server and port 80 is open.
- **No domain:** press Enter at the host-name question; the installer uses `athari.<ip>.sslip.io`.
- **Free subdomain:** create one at duckdns.org (for example `athari-ke`), set its IP to the server's public IP, and use `athari-ke.duckdns.org`.
- **Own domain:** add an A record to the public IP and use that name.

## Everyday tasks

```bash
cd /opt/athari/app
sudo ./deploy/update.sh                       # deploy the latest version (backs up first)
sudo ATHARI_REF=v1.0.0 ./deploy/update.sh     # deploy a specific tag
sudo ./deploy/backup.sh                       # backup now
docker compose --env-file /opt/athari/secrets/compose.env -f deploy/docker-compose.yml logs -f app
docker compose --env-file /opt/athari/secrets/compose.env -f deploy/docker-compose.yml exec -w /app app drush --root=/app/web status
```

Restoring a backup:

```bash
cd /opt/athari/app
. /opt/athari/secrets/credentials.env
gunzip -c /opt/athari/backups/db-YYYYMMDD-HHMMSS.sql.gz | \
  docker compose --env-file /opt/athari/secrets/compose.env -f deploy/docker-compose.yml exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" athari
```

## What is protected

- Only ports 22, 80 and 443 are open; the database is not published. fail2ban slows SSH guessing and security updates install themselves.
- Caddy answers 404 for settings, YAML, SQL, `composer.*`, `/vendor`, `/config`, `/scripts`, `install.php`, `update.php`, hidden files and every PHP file except `index.php`. It adds HSTS and the usual security headers and hides the server and Drupal versions.
- Errors are logged, never shown. `expose_php` is off.
- The image is built without development packages and the "dev" configuration split is off.
- Drupal only answers to the configured host name (`trusted_host_patterns`).

## Checking a deployed site

From any computer with this repository:

```powershell
powershell -File scripts/day5-05-security-http.ps1 -Base https://YOUR-SITE -Live
powershell -File scripts/day5-02-verify-api.ps1   -Base https://YOUR-SITE
```

## Trying the installer on your own computer

With Docker (for example inside WSL), no real server needed:

```bash
ATHARI_LOCAL_TEST=1 ATHARI_NONINTERACTIVE=1 ATHARI_SECRETS_DIR=/tmp/athari-secrets bash deploy/install.sh
# then open https://localhost:8443 (accept the self-signed certificate)
```

Probe it with `-Insecure -Live -HttpBase http://localhost:8080`, for example:
`powershell -File scripts/day5-05-security-http.ps1 -Base https://localhost:8443 -Insecure -Live -HttpBase http://localhost:8080`.

## Status of the deployment bundle

| Check | Result |
|---|---|
| Full installer run on a local Docker stack (build, install from config, users, sectors, Kenya demo data, health check) | Passed |
| Anonymous API checks against that stack | 37 of 37 passed |
| HTTP security checks in live mode (HSTS, headers, blocked files, HTTP to HTTPS) | 48 of 48 passed |
| Devel and test tools absent from the image, dev config split off, errors hidden | Confirmed |
| `linux/arm64` | The production image builds under `docker buildx --platform linux/arm64` (QEMU emulation) and runs: `aarch64`, PHP 8.3, FrankenPHP 1.13 with Caddy 2.11, and the gd, intl, pdo_mysql, apcu, zip and OPcache extensions present. The full installer was run on x86-64 only, so the first install on a real ARM server is still the final check |
| Public hosting and a real HTTPS certificate | Not done: the project is not hosted live |
