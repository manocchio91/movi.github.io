#!/usr/bin/env sh
set -eu
: "${STAGING_API_URL:?Set STAGING_API_URL, for example https://api.staging.example.com/api/v1}"
: "${SMOKE_EMAIL:?Set SMOKE_EMAIL}"
: "${SMOKE_PASSWORD:?Set SMOKE_PASSWORD}"
command -v curl >/dev/null
command -v jq >/dev/null
base=${STAGING_API_URL%/}
curl -fsS "$base/health" | jq -e '.status == "ok" and .checks.database == "ok"'
curl -fsS "$base/events?per_page=1" | jq -e '.data != null'
curl -fsS "$base/categories" | jq -e '.data != null'
login=$(curl -fsS -X POST "$base/auth/login" -H 'Accept: application/json' -H 'Content-Type: application/json' --data "$(jq -n --arg email "$SMOKE_EMAIL" --arg password "$SMOKE_PASSWORD" '{email:$email,password:$password}')")
token=$(printf '%s' "$login" | jq -er '.token')
curl -fsS "$base/me" -H 'Accept: application/json' -H "Authorization: Bearer $token" | jq -e '.data // .user'
curl -fsS -X POST "$base/auth/logout" -H 'Accept: application/json' -H "Authorization: Bearer $token" >/dev/null
printf 'Staging smoke test passed.\n'