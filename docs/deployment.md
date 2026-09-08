# Deploy staging/production

## Prerequisiti

Copiare `.env.production.example` in `.env.production` fuori dal versionamento, sostituire ogni placeholder e generare `APP_KEY` in modo sicuro. In produzione sono obbligatori `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS espliciti, SMTP reale, password PostgreSQL/Redis non predefinite e proxy fidati limitati.

Validare prima del build con `sh scripts/validate-production-env.sh .env.production`; il comando rifiuta placeholder, HTTP, CORS wildcard e queue/cache non Redis.

Impostare `VITE_API_BASE_URL` sull'URL HTTPS pubblico completo delle API prima della build web. Per Android compilare Flutter passando lo stesso endpoint: `flutter build apk --release --dart-define=API_BASE_URL=https://api.example.com/api/v1`. I fallback `localhost`/`10.0.2.2` sono esclusivamente locali.

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

Per il drill usare sempre un database separato con suffisso `_restore_smoke`, verificare migration/tabelle e rimuovere soltanto quel database al termine. Mai eseguire `--clean` contro production durante una prova.

## Gate

Verificare permessi dello storage persistente, TLS al reverse proxy, CORS, cookie sicuri, SMTP, coda, scheduler, rotazione log, backup e assenza di seed demo automatici.
