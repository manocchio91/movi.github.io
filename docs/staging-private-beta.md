# Staging e private beta

## Preparazione

1. Copiare `.env.staging.example` in `.env.staging`, mantenerlo fuori da Git e sostituire ogni placeholder.
2. Generare `APP_KEY` in modo sicuro; impostare dominio API/frontend HTTPS, proxy fidato, password distinte per PostgreSQL e Redis e credenziali SMTP.
3. Configurare DNS e TLS sul reverse proxy. `infra/nginx/staging-https.conf.example` mostra redirect HTTP→HTTPS, HSTS e passaggio corretto di HTTPS a Laravel; non avviare questa configurazione prima che certificato e chiave reali siano montati.
4. Verificare `APP_ENV=staging`, `APP_DEBUG=false`, cookie secure, CORS e `SANCTUM_STATEFUL_DOMAINS` senza schema.

## Deploy ripetibile

```bash
cp .env.staging.example .env.staging
ENV_FILE=.env.staging sh ./scripts/deploy-staging.sh
```

Il deploy usa esclusivamente `php artisan migrate --force`; non usa seed, `migrate:fresh` o `db:wipe`. Database, Redis e upload risiedono nei volumi `postgres-data`, `redis-data` e `app-storage` e sopravvivono a restart e ricreazione dei container. Usare un project name Compose dedicato al server di staging se sullo stesso host convivono altri stack.

## Account beta opzionali

Compilare le sei variabili `BETA_*` con email fittizie e password casuali di almeno 12 caratteri, poi eseguire una sola volta e soltanto se richiesto:

```bash
APP_ENV_FILE=.env.staging docker compose --env-file .env.staging -f docker-compose.production.yml run --rm app php artisan db:seed --class=StagingBetaSeeder --force
```

Il seeder è idempotente, accetta solo `APP_ENV=staging` e crea un Admin, un Organizer approvato, un Customer e categorie essenziali. Le password non sono versionate né stampate.

## Operazioni

```bash
export APP_ENV_FILE=.env.staging
docker compose --env-file .env.staging -f docker-compose.production.yml ps
docker compose --env-file .env.staging -f docker-compose.production.yml restart app nginx frontend queue scheduler
docker compose --env-file .env.staging -f docker-compose.production.yml logs --tail=200 app nginx queue scheduler
docker compose --env-file .env.staging -f docker-compose.production.yml exec app php artisan migrate:status
docker compose --env-file .env.staging -f docker-compose.production.yml exec app php artisan queue:failed
curl --fail https://STAGING_API_DOMAIN/api/v1/health
```

`ps` deve mostrare PostgreSQL, Redis, worker e scheduler attivi/healthy. Verificare una mail operativa e che `queue:failed` resti vuoto. Gli errori SMTP rimangono nella coda/log e non annullano booking o moderazione.

## Backup e restore

Prima di ogni deploy creare un dump e una copia coerente dello storage:

```bash
docker compose --env-file .env.staging -f docker-compose.production.yml exec -T postgres pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc > staging.dump
docker run --rm -v app-storage:/source:ro -v "$PWD":/backup alpine tar czf /backup/staging-storage.tgz -C /source .
```

Per il restore mettere l'app in manutenzione, ripristinare database e storage in ambiente verificato, eseguire `migrate --force`, riavviare e ripetere health/smoke test. Non usare restore distruttivi senza backup verificato.

## Rollback applicativo

Conservare l'identificatore dell'immagine precedente. In caso di rollback distribuire quella versione senza invertire automaticamente le migrazioni, riavviare app/worker/scheduler e ripetere health e smoke test. Se una migrazione non è backward-compatible, fermare le scritture e seguire il piano esplicito della release.

## Smoke test

```bash
STAGING_API_URL=https://STAGING_API_DOMAIN/api/v1 SMOKE_EMAIL=customer-beta@example.test SMOKE_PASSWORD='da-secret-manager' sh ./scripts/smoke-staging.sh
```

Lo script verifica health, eventi, categorie, login, `/me` e logout senza registrare token o password.

## Checklist private beta

- Customer: registrazione/login, eventi, dettaglio, booking e telefono, modifica, QR, annullamento, profilo.
- Organizer: login/onboarding, dashboard, evento e ticket, prenotazioni, partecipanti, CSV, check-in.
- Admin: login, dashboard, ricerca, Organizer/Eventi, approvazione, piani e accordi.
- Email: conferma e annullamento booking, approvazione Organizer, integrazione richiesta, rifiuto.
- Operazioni: console/Network puliti, health verde, queue senza failed job, scheduler attivo, upload presente dopo restart, backup acquisito.