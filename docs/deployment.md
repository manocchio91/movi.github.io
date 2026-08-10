# Deploy staging/production

## Prerequisiti

Copiare `.env.production.example` in `.env.production` fuori dal versionamento, sostituire ogni placeholder e generare `APP_KEY` in modo sicuro. In produzione sono obbligatori `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS espliciti, SMTP reale, password PostgreSQL/Redis non predefinite e proxy fidati limitati.

## Deploy

```bash
docker compose -f docker-compose.production.yml build
docker compose -f docker-compose.production.yml up -d postgres redis
docker compose -f docker-compose.production.yml run --rm app php artisan migrate --force
docker compose -f docker-compose.production.yml run --rm app php artisan storage:link
docker compose -f docker-compose.production.yml up -d
curl --fail http://localhost:8080/api/v1/health
```

Non eseguire seed o `migrate:fresh` nel deploy. Queue worker e scheduler sono servizi separati. Il frontend è una build statica e non dipende dal Vite dev server.

## Backup PostgreSQL

```bash
docker compose -f docker-compose.production.yml exec -T postgres pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc > eventi-$(date +%F-%H%M).dump
```

Conservare i dump cifrati, fuori dal server applicativo, con accesso ristretto e retention definita. Verificare periodicamente il restore in un ambiente isolato.

## Restore

Arrestare le scritture applicative e verificare database e file target prima di procedere:

```bash
docker compose -f docker-compose.production.yml exec -T postgres pg_restore --clean --if-exists --no-owner -U "$POSTGRES_USER" -d "$POSTGRES_DB" < eventi-YYYY-MM-DD-HHMM.dump
```

Dopo il restore eseguire `php artisan migrate --force`, riavviare i servizi e verificare health check e flussi critici. Backup e restore dello storage upload devono seguire lo stesso punto temporale del database.

## Gate

Verificare permessi dello storage persistente, TLS al reverse proxy, CORS, cookie sicuri, SMTP, coda, scheduler, rotazione log, backup e assenza di seed demo automatici.