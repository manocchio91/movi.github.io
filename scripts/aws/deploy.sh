#!/usr/bin/env sh
set -eu
: "${DEPLOY_ENV:?Set DEPLOY_ENV to staging or production}"
case "$DEPLOY_ENV" in staging|production) ;; *) echo 'Invalid DEPLOY_ENV' >&2; exit 1;; esac
cd "$(dirname "$0")/../.."
parameter="/movi/$DEPLOY_ENV/env"
aws ssm get-parameter --name "$parameter" --with-decryption --query Parameter.Value --output text > ".env.$DEPLOY_ENV"
chmod 600 ".env.$DEPLOY_ENV"
if test "$DEPLOY_ENV" = production; then sh scripts/validate-production-env.sh ".env.$DEPLOY_ENV"; else sh scripts/validate-staging-env.sh ".env.$DEPLOY_ENV"; fi
export APP_ENV_FILE=".env.$DEPLOY_ENV"
compose="docker compose --env-file .env.$DEPLOY_ENV -f docker-compose.production.yml -f docker-compose.aws.yml"
$compose config --quiet
$compose pull app frontend queue scheduler
$compose up -d redis
$compose run --rm app php artisan migrate --force
$compose up -d --remove-orphans
$compose ps
API_URL=$(sed -n 's/^APP_URL=//p' ".env.$DEPLOY_ENV")/api/v1 FRONTEND_URL=$(sed -n 's/^FRONTEND_URL=//p' ".env.$DEPLOY_ENV") sh scripts/aws/smoke.sh
