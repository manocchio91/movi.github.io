# Development checkpoint — 2026-07-16

## Stato ambiente

- Docker operativo.
- Laravel 11.55.0 su PHP 8.3.32 operativo; container `app` healthy.
- PostgreSQL 17 operativo e healthy.
- Redis 7 operativo.
- Nginx operativo su porta 8080.
- Frontend operativo su porta 5173.

## Correzioni completate

- Corrette le dipendenze Composer del backend e generato `composer.lock`.
- Sostituito il pacchetto OpenAPI precedente con `dedoc/scramble`.
- Sostituito `nunomaduro/larastan` con `larastan/larastan`.
- Installati Mockery, Faker e Collision tra le dipendenze di sviluppo.
- Configurata l'analisi statica PHPStan/Larastan.
- Predisposte le directory runtime `storage/framework` e `bootstrap/cache`.
- Configurati i servizi Docker per backend, Nginx, frontend, PostgreSQL e Redis.
- Configurato l'healthcheck Laravel del container applicativo.
- Uniformato il contratto JSON degli errori API con `message`, `errors` e `request_id`.
- Aggiunto il middleware Request ID con supporto all'header `X-Request-ID`.
- Configurato il rate limiter per registrazione e login.
- Verificato il check-in tramite QR monouso e l'autorizzazione dell'organizer proprietario.
- Corretto il setup del test QR creando esplicitamente customer e organizer attivi, con email verificata e profilo organizer verificato.
- Rafforzato `.gitignore` per escludere segreti, dipendenze, build, cache, log e file temporanei.

## Test superati

Comando eseguito:

```shell
docker compose exec app php artisan test --stop-on-failure
```

Test superati prima del primo fallimento:

- `ApiErrorAndCheckInTest`: contratto uniforme degli errori API e Request ID.
- `ApiErrorAndCheckInTest`: contratto degli errori di validazione.
- `ApiErrorAndCheckInTest`: QR monouso e autorizzazione organizer.

Risultato parziale: 3 test superati, 24 asserzioni.

La verifica Pint non è verde in questo checkpoint:

```text
141 files, 1 style issue
bootstrap/providers.php: fully_qualified_strict_types, single_line_after_imports
```

La correzione di stile è rinviata, in quanto questo checkpoint non introduce ulteriori correzioni.

## Test ancora falliti

Primo errore attuale:

```text
Tests\Feature\EventPlatformTest
customer saves and removes multiple category preferences

Expected 200 but received 403.
message: "Account non attivo."
```

La correzione è stata rinviata. La suite completa non è ancora verde.

## Comandi per riprendere

```shell
docker compose up --build -d
docker compose ps
docker compose exec app php artisan test --stop-on-failure
```

## Regole

- Non indebolire middleware e autorizzazioni.
- Correggere i setup dei test positivi.
- Procedere un errore alla volta.
