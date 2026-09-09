#!/usr/bin/env sh
set -eu
ENV_FILE=${ENV_FILE:-.env.staging}
test -f "$ENV_FILE" || { echo "Missing $ENV_FILE" >&2; exit 1; }
sh ./scripts/validate-staging-env.sh "$ENV_FILE"
export APP_ENV_FILE="$ENV_FILE"
compose="docker compose --env-file $ENV_FILE -f docker-compose.production.yml -f docker-compose.aws.yml"
$compose config --quiet
if test "${SKIP_BUILD:-0}" = 1; then $compose pull app frontend queue scheduler; else $compose build; fi
$compose up -d redis
$compose run --rm app php artisan migrate --force
$compose up -d
$compose ps
