#!/usr/bin/env sh
set -eu
file=${1:-.env.staging}
expected_env=${2:-staging}
test -f "$file" || { echo "Missing $file" >&2; exit 1; }
value() { sed -n "s/^$1=//p" "$file" | tail -n 1; }
require() { current=$(value "$1"); test -n "$current" || { echo "$1 is required" >&2; exit 1; }; }
for key in APP_KEY APP_URL FRONTEND_URL VITE_API_BASE_URL FLUTTER_API_BASE_URL TRUSTED_PROXIES CORS_ALLOWED_ORIGINS DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD REDIS_PASSWORD CACHE_STORE QUEUE_CONNECTION SESSION_DRIVER SANCTUM_STATEFUL_DOMAINS MAIL_MAILER FILESYSTEM_DISK PUBLIC_FILESYSTEM_DISK AWS_DEFAULT_REGION AWS_BUCKET AWS_BACKUP_PREFIX; do require "$key"; done
test "$(value APP_ENV)" = "$expected_env" || { echo "APP_ENV must be $expected_env" >&2; exit 1; }
test "$(value APP_DEBUG)" = false || { echo 'APP_DEBUG must be false' >&2; exit 1; }
printf '%s\n' "$(value APP_KEY)" | grep -Eq '^base64:.+' || { echo 'APP_KEY must be generated' >&2; exit 1; }
for key in APP_URL FRONTEND_URL VITE_API_BASE_URL FLUTTER_API_BASE_URL CORS_ALLOWED_ORIGINS; do printf '%s\n' "$(value "$key")" | grep -Eq '^https://' || { echo "$key must use HTTPS" >&2; exit 1; }; done
test "$(value CORS_ALLOWED_ORIGINS)" != '*' || { echo 'CORS wildcard is forbidden in staging' >&2; exit 1; }
test "$(value QUEUE_CONNECTION)" = redis || { echo 'QUEUE_CONNECTION must be redis' >&2; exit 1; }
test "$(value CACHE_STORE)" = redis || { echo 'CACHE_STORE must be redis' >&2; exit 1; }
test "$(value FILESYSTEM_DISK)" = s3 || { echo 'FILESYSTEM_DISK must be s3' >&2; exit 1; }
test "$(value PUBLIC_FILESYSTEM_DISK)" = s3 || { echo 'PUBLIC_FILESYSTEM_DISK must be s3' >&2; exit 1; }
if test "$(value MAIL_MAILER)" = smtp; then
  for key in MAIL_HOST MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS; do require "$key"; done
fi
if grep -Eq 'CHANGE_ME|STAGING_[A-Z0-9_]+|PRODUCTION_[A-Z0-9_]+|RDS_PRIVATE_ENDPOINT|MOVI_(STAGING|PRODUCTION)_BUCKET|ghcr\.io/owner/|:sha$|example\.com' "$file"; then echo 'Replace every environment placeholder' >&2; exit 1; fi
printf '%s environment validated.\n' "$expected_env"
