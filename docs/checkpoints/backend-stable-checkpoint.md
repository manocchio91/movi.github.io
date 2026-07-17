# Backend Stable Checkpoint

## Stato ambiente

- Docker operativo
- Laravel healthy
- PostgreSQL healthy
- Redis operativo
- Nginx operativo
- Frontend operativo

## Controlli superati

- 34 test Laravel
- 174 assertions
- Laravel Pint verde su 141 file

## Funzioni verificate

- Autenticazione e contratto uniforme degli errori API
- Ruoli customer, organizer e admin
- Preferenze e notifiche
- Pubblicazione eventi
- Prenotazioni e idempotenza
- QR check-in monouso
- Recensioni e cancellazione prenotazioni
- Opportunity Engine
- Dashboard e ranking admin
- Request ID e rate limiter auth

## Problemi residui

PHPStan usa correttamente `larastan/larastan` e completa l'analisi dei 119 file configurati, ma rileva 278 errori statici preesistenti. Gli errori non sono stati corretti in questo checkpoint.

## Comandi per riprendere

```bash
docker compose up -d
docker compose ps
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
```
