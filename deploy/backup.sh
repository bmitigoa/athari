#!/usr/bin/env bash
# Backs up the database and the uploaded files to /opt/athari/backups and keeps 14 days.
# Restore a database with:
#   gunzip -c db-DATE.sql.gz | docker compose ... exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" athari
set -euo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SECRETS_DIR="${ATHARI_SECRETS_DIR:-/opt/athari/secrets}"
BACKUP_DIR="${ATHARI_BACKUP_DIR:-/opt/athari/backups}"
STAMP="$(date +%Y%m%d-%H%M%S)"
COMPOSE=(docker compose --env-file "$SECRETS_DIR/compose.env" -f "$APP_DIR/deploy/docker-compose.yml")

umask 077
mkdir -p "$BACKUP_DIR"
# shellcheck disable=SC1091
. "$SECRETS_DIR/credentials.env"

"${COMPOSE[@]}" exec -T db mariadb-dump -uroot -p"$DB_ROOT_PASSWORD" --single-transaction --routines athari | gzip >"$BACKUP_DIR/db-$STAMP.sql.gz"
"${COMPOSE[@]}" exec -T app tar -C /app/web/sites/default -czf - files | cat >"$BACKUP_DIR/files-$STAMP.tar.gz"
find "$BACKUP_DIR" -type f -mtime +14 -delete
echo "$(date -Is) backup $STAMP done ($(du -h "$BACKUP_DIR/db-$STAMP.sql.gz" | cut -f1) database)"
