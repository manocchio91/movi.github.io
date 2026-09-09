#!/usr/bin/env sh
set -eu
: "${API_URL:?Set API_URL, including /api/v1}" "${FRONTEND_URL:?Set FRONTEND_URL}"
api=${API_URL%/}
curl -fsS "$api/health" | grep -q '"status":"ok"'
curl -fsS "$api/events?per_page=1" | grep -q '"data"'
curl -fsS "$FRONTEND_URL" | grep -qi '<div id="app"'
printf 'AWS pilot smoke passed.\n'
