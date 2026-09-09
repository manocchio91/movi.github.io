#!/usr/bin/env sh
set -eu
: "${RESTORE_S3_URI:?Set RESTORE_S3_URI}" "${RESTORE_DB_HOST:?}" "${RESTORE_DB_NAME:?}" "${RESTORE_DB_USERNAME:?}" "${RESTORE_DB_PASSWORD:?}"
case "$RESTORE_DB_NAME" in *_restore_smoke) ;; *) echo 'RESTORE_DB_NAME must end in _restore_smoke' >&2; exit 1;; esac
command -v aws >/dev/null
umask 077
tmp=$(mktemp "${TMPDIR:-/tmp}/movi-restore-XXXXXX.dump")
trap 'rm -f "$tmp"' EXIT HUP INT TERM
aws s3 cp "$RESTORE_S3_URI" "$tmp" --only-show-errors
docker run --rm -e PGPASSWORD="$RESTORE_DB_PASSWORD" postgres:17-alpine pg_restore --list < "$tmp" >/dev/null
docker run --rm -i -e PGPASSWORD="$RESTORE_DB_PASSWORD" postgres:17-alpine pg_restore -h "$RESTORE_DB_HOST" -p "${RESTORE_DB_PORT:-5432}" -U "$RESTORE_DB_USERNAME" -d "$RESTORE_DB_NAME" --no-owner --exit-on-error < "$tmp"
printf 'Restore verified into isolated database: %s\n' "$RESTORE_DB_NAME"
