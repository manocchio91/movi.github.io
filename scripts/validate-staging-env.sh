#!/usr/bin/env sh
set -eu
file=${1:-.env.staging}
test -f "$file" || { echo "Missing $file" >&2; exit 1; }
value() { sed -n "s/^$1=//p" "$file" | tail -n 1; }
require() { current=$(value "$1"); test -n "$current" || { echo "$1 is required" >&2; exit 1; }; }
for key in APP_KEY APP_URL FRONTEND_URL TRUSTED_PROXIES CORS_ALLOWED_ORIGINS DB_PASSWORD POSTGRES_PASSWORD REDIS_PASSWORD SANCTUM_STATEFUL_DOMAINS MAIL_HOST MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS; do require "$key"; done
test "$(value APP_ENV)" = staging || { echo 'APP_ENV must be staging' >&2; exit 1; }
test "$(value APP_DEBUG)" = false || { echo 'APP_DEBUG must be false' >&2; exit 1; }
printf '%s\n' "$(value APP_KEY)" | grep -Eq '^base64:.+' || { echo 'APP_KEY must be generated' >&2; exit 1; }
if grep -Eq '=(CHANGE_ME|STAGING_[A-Z0-9_]+)$' "$file"; then echo 'Replace every staging placeholder' >&2; exit 1; fi
printf 'Staging environment validated.\n'