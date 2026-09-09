#!/usr/bin/env sh
set -eu
ENV_FILE=${ENV_FILE:-.env.production}
test -f "$ENV_FILE" || { echo "Missing $ENV_FILE" >&2; exit 1; }
value() { sed -n "s/^$1=//p" "$ENV_FILE" | tail -n 1; }
DB_HOST=$(value DB_HOST)
DB_PORT=$(value DB_PORT); DB_PORT=${DB_PORT:-5432}
DB_DATABASE=$(value DB_DATABASE)
DB_USERNAME=$(value DB_USERNAME)
DB_PASSWORD=$(value DB_PASSWORD)
AWS_BUCKET=$(value AWS_BUCKET)
AWS_BACKUP_PREFIX=$(value AWS_BACKUP_PREFIX)
: "${DB_HOST:?}" "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${DB_PASSWORD:?}" "${AWS_BUCKET:?}" "${AWS_BACKUP_PREFIX:?}"
command -v aws >/dev/null
umask 077
tmp=$(mktemp "${TMPDIR:-/tmp}/movi-db-XXXXXX.dump")
trap 'rm -f "$tmp"' EXIT HUP INT TERM
docker run --rm -e PGPASSWORD="$DB_PASSWORD" postgres:17-alpine pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -Fc > "$tmp"
pg_restore_check=$(docker run --rm -i postgres:17-alpine pg_restore --list < "$tmp")
test -n "$pg_restore_check"
key="${AWS_BACKUP_PREFIX%/}/movi-${DB_DATABASE}-$(date -u +%Y%m%dT%H%M%SZ).dump"
aws s3 cp "$tmp" "s3://$AWS_BUCKET/$key" --only-show-errors --sse AES256
printf 'Backup uploaded: s3://%s/%s\n' "$AWS_BUCKET" "$key"
