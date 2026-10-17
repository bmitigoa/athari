#!/usr/bin/env bash
# Runs Drupal's cron inside the app container (called every 15 minutes by /etc/cron.d/athari).
set -euo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SECRETS_DIR="${ATHARI_SECRETS_DIR:-/opt/athari/secrets}"
docker compose --env-file "$SECRETS_DIR/compose.env" -f "$APP_DIR/deploy/docker-compose.yml" \
  exec -T -w /app app drush --root=/app/web cron
