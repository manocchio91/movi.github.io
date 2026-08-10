#!/usr/bin/env sh
set -eu
ENV_FILE=${ENV_FILE:-.env.staging}
test -f "$ENV_FILE" || { echo "Missing $ENV_FILE" >&2; exit 1; }
sh ./scripts/validate-staging-env.sh "$ENV_FILE"
export APP_ENV_FILE="$ENV_FILE"
docker compose --env-file "$ENV_FILE" -f docker-compose.production.yml config --quiet
docker compose --env-file "$ENV_FILE" -f docker-compose.production.yml build
docker compose --env-file "$ENV_FILE" -f docker-compose.production.yml up -d postgres redis
docker compose --env-file "$ENV_FILE" -f docker-compose.production.yml run --rm app php artisan migrate --force
docker compose --env-file "$ENV_FILE" -f docker-compose.production.yml up -d
docker compose --env-file "$ENV_FILE" -f docker-compose.production.yml ps