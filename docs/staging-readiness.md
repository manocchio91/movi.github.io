# Staging readiness

## Release configuration matrix

| Item | Local | Staging | Production | Status | External config |
|---|---|---|---|---|---|
| DB | PostgreSQL Docker | volume persistente | PostgreSQL gestito o volume con backup | pronto | credenziali/host |
| Redis | Docker senza password | password + AOF | password + AOF/servizio gestito | pronto | credenziali |
| SMTP | log | SMTP sandbox/reale | SMTP reale | predisposto | provider e credenziali |
| Storage | volume/bind mount | `app-storage` persistente | volume o object storage | pronto | retention e backup |
| HTTPS/domain | HTTP locale | template Nginx TLS | terminazione TLS obbligatoria | predisposto | DNS e certificati |
| Queue | Redis worker | servizio `queue` | servizio `queue` | pronto | monitoring host |
| Scheduler | manuale | servizio `scheduler` | servizio `scheduler` | pronto | nessuna |
| AI provider | fallback deterministico | default `none` | default `none` | pronto | provider/key opzionali |
| Deep link | schema `movi://` | template association files | association files definitivi | predisposto | dominio, app ID, fingerprint/team ID |
| Backup | smoke locale | dump DB + archivio storage | cifratura e retention off-host | predisposto | destinazione/retention |
| Logging | stack/daily | stderr strutturato dal runtime | stderr + collector | pronto | collector/alerting |
| Monitoring | health endpoint | probe `/api/v1/health` | probe + alert | predisposto | piattaforma alerting |

## Smoke operativo

Prima del deploy validare l'env con `sh scripts/validate-staging-env.sh .env.staging`. Avviare con `ENV_FILE=.env.staging sh scripts/deploy-staging.sh`, quindi verificare `docker compose ... ps`, `/api/v1/health`, `php artisan migrate:status`, `php artisan queue:failed` e `php artisan schedule:run`. Per il probe worker eseguire in ordine `php artisan app:queue-smoke`, `php artisan queue:work --stop-when-empty` e `php artisan app:queue-smoke-status`.

Lo smoke API automatizzato (`scripts/smoke-staging.sh`) copre health, letture pubbliche, login, identità e logout. I flussi Customer/Organizer/Admin, booking, waitlist, reservation, menu/pre-order, MòVi Plan e fallback AI sono coperti dalle suite Feature/UI; su staging vanno ripetuti con gli account beta prima dell'apertura.

## Backup/restore drill

Eseguire `pg_dump -Fc`, creare un database isolato con suffisso `_restore_smoke`, ripristinare con `pg_restore --no-owner`, confrontare almeno conteggio migration e tabelle, poi eliminare esclusivamente il database smoke. Archiviare insieme lo storage persistente. In staging/production cifrare i backup, conservarli off-host e definire retention/RPO/RTO.

## Deep link e release mobile

Pubblicare i template in `infra/deep-links/` come `/.well-known/assetlinks.json` e `/.well-known/apple-app-site-association` dopo aver sostituito package, fingerprint, Team ID e Bundle ID. Aggiungere quindi host HTTPS definitivi ai manifest/entitlement. Le build release devono passare `--dart-define=API_BASE_URL=https://.../api/v1`; signing e store credentials restano esterni.

## AI e rollback

`AI_PROVIDER=none` mantiene il fallback deterministico. Per un provider reale impostare endpoint/modello/key da secret manager, mantenere timeout breve e verificare fallback/rate limit senza loggare prompt sensibili o chiavi. Per rollback riutilizzare l'immagine precedente, non invertire automaticamente le migration, riavviare app/queue/scheduler e ripetere health e smoke.
