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

Nel checkpoint `v0.1-backend-stable`, PHPStan completava l'analisi dei 119 file configurati con 278 errori statici preesistenti. La pulizia è stata completata nel checkpoint successivo, come documentato di seguito.

## PHPStan cleanup completed

- Errori iniziali: 278
- Errori finali: 0 su 119 file analizzati
- Gruppi completati:
  1. parsing dei cast Eloquent;
  2. return type e generics delle relazioni;
  3. proprietà Eloquent reali;
  4. generics delle relazioni residue;
  5. `argument.type` e nullability dei consumer;
  6. `nullsafe.neverNull`.
- Test invariati e verdi: 34 test, 174 assertions
- Laravel Pint verde su 141 file
- Nessuna baseline PHPStan
- Nessun `ignoreErrors`
- Nessun abbassamento del livello di analisi

## Comandi per riprendere

```bash
docker compose up -d
docker compose ps
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
```

## Mobile Authentication Integration

- Flutter autentica Customer e Organizer tramite il backend Laravel reale.
- Laravel Sanctum emette un personal access token opaco, inviato come Bearer token alle richieste protette.
- Il login Customer reale è verificato con redirect alla shell Customer.
- Il login Organizer reale è verificato con redirect alla shell Organizer.
- `GET /api/v1/me` ripristina e valida l'utente autenticato.
- `POST /api/v1/auth/logout` revoca il token corrente e riporta l'app al login.
- Il token è persistito localmente tramite `SharedPreferences`; al riavvio l'app valida la sessione con `/me`.
- Il routing Flutter usa il ruolo restituito da Laravel: `customer` verso `/customer`, `organizer` verso `/organizer`.
- `DevelopmentDemoUsersSeeder` crea in modo idempotente e solo in ambiente locale gli account Customer e Organizer di sviluppo; il profilo Organizer è verificato.
- `flutter analyze` non rileva problemi.
- I 3 widget test Flutter sono verdi.
- La build Flutter Web è verde.
