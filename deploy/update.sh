#!/usr/bin/env bash
#
# Deploys a newer version of Athari on the server:
#   cd /opt/athari/app && sudo ./deploy/update.sh            # latest main
#   cd /opt/athari/app && sudo ATHARI_REF=v1.0.1 ./deploy/update.sh
#
# Steps: back up, fetch the code, rebuild the image, restart, run database
# updates, import configuration, clear caches. Content is never reseeded.
set -euo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SECRETS_DIR="${ATHARI_SECRETS_DIR:-/opt/athari/secrets}"
REF="${ATHARI_REF:-main}"
COMPOSE=(docker compose --env-file "$SECRETS_DIR/compose.env" -f "$APP_DIR/deploy/docker-compose.yml")

echo "==> Backing up first"
ATHARI_SECRETS_DIR="$SECRETS_DIR" "$APP_DIR/deploy/backup.sh"

echo "==> Fetching $REF"
git -C "$APP_DIR" fetch --tags origin
git -C "$APP_DIR" checkout "$REF"
git -C "$APP_DIR" pull --ff-only origin "$REF" || true

echo "==> Rebuilding and restarting"
"${COMPOSE[@]}" build app
"${COMPOSE[@]}" up -d app
sleep 8

echo "==> Database updates, configuration import, cache rebuild"
"${COMPOSE[@]}" exec -T -w /app app drush --root=/app/web deploy -y
echo "Done."
