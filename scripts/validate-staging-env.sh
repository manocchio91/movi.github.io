#!/usr/bin/env sh
set -eu
file=${1:-.env.staging}
expected_env=${2:-staging}
test -f "$file" || { echo "Missing $file" >&2; exit 1; }
value() { sed -n "s/^$1=//p" "$file" | tail -n 1; }
require() { current=$(value "$1"); test -n "$current" || { echo "$1 is required" >&2; exit 1; }; }
for key in APP_KEY APP_URL FRONTEND_URL VITE_API_BASE_URL FLUTTER_API_BASE_URL TRUSTED_PROXIES CORS_ALLOWED_ORIGINS DB_PASSWORD POSTGRES_PASSWORD REDIS_PASSWORD CACHE_STORE QUEUE_CONNECTION SESSION_DRIVER SANCTUM_STATEFUL_DOMAINS MAIL_MAILER MAIL_HOST MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS; do require "$key"; done
test "$(value APP_ENV)" = "$expected_env" || { echo "APP_ENV must be $expected_env" >&2; exit 1; }
test "$(value APP_DEBUG)" = false || { echo 'APP_DEBUG must be false' >&2; exit 1; }
printf '%s\n' "$(value APP_KEY)" | grep -Eq '^base64:.+' || { echo 'APP_KEY must be generated' >&2; exit 1; }
for key in APP_URL FRONTEND_URL VITE_API_BASE_URL FLUTTER_API_BASE_URL CORS_ALLOWED_ORIGINS; do printf '%s\n' "$(value "$key")" | grep -Eq '^https://' || { echo "$key must use HTTPS" >&2; exit 1; }; done
test "$(value CORS_ALLOWED_ORIGINS)" != '*' || { echo 'CORS wildcard is forbidden in staging' >&2; exit 1; }
test "$(value QUEUE_CONNECTION)" = redis || { echo 'QUEUE_CONNECTION must be redis' >&2; exit 1; }
test "$(value CACHE_STORE)" = redis || { echo 'CACHE_STORE must be redis' >&2; exit 1; }
if grep -Eq '=(CHANGE_ME|(STAGING|PRODUCTION)_[A-Z0-9_]+)$|example\.com' "$file"; then echo 'Replace every environment placeholder' >&2; exit 1; fi
printf '%s environment validated.\n' "$expected_env"
