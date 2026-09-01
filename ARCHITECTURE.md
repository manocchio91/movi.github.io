# Architettura APP EVENTI

> Aggiornare questo documento quando cambiano componenti, contratti API o responsabilità architetturali reali.

## Panoramica

APP EVENTI è un monorepo composto da:

- backend Laravel che espone API REST versionate;
- frontend web Vue 3 + TypeScript + Vite;
- app Flutter con esperienze Customer e Organizer;
- infrastruttura locale Docker Compose con Nginx, PostgreSQL e Redis.

Il backend è un monolite modulare. Non sono presenti microservizi. I client consumano il contratto HTTP sotto `/api/v1`.

## Struttura principale

```text
backend/     API Laravel, dominio, migrazioni e test
frontend/    applicazione web Vue 3 e TypeScript
mobile/      applicazione Flutter
docs/        documentazione tecnica e OpenAPI
infra/       configurazione Nginx e inizializzazione PostgreSQL
.github/     configurazione GitHub del repository
```

## Backend Laravel

Il backend richiede PHP `^8.3` e Laravel `^11.0`.

Organizzazione principale:

- `app/Http/Controllers/Api/V1`: controller REST;
- `app/Http/Requests`: validazione server-side;
- `app/Actions`: casi d'uso, inclusi moduli Bookings, Events, Notifications, Preferences e Reviews;
- `app/Models`: modelli Eloquent;
- `app/Policies`: autorizzazioni;
- `app/Services`: metriche, ranking e servizi applicativi;
- `database/migrations`: schema PostgreSQL;
- `database/seeders`: dati di sviluppo versionati;
- `tests/Feature`: test delle API e dei flussi applicativi.

I controller delegano la logica applicativa alle Action quando il flusso lo richiede. Le risorse mutabili sono protette da autenticazione, ruoli e controlli di proprietà.

## Autenticazione e autorizzazione

Laravel Sanctum fornisce token API. Le route autenticate sono protette da `auth:sanctum` e ulteriormente separate per ruolo Customer, Organizer e Admin.

Nell'app Flutter:

- `AuthenticationRepository` esegue login, ripristino sessione e logout;
- il token viene conservato tramite `SharedPreferences`;
- Riverpod espone lo stato autenticato;
- GoRouter reindirizza gli utenti in base al ruolo e protegge i percorsi Customer/Organizer.

## PostgreSQL

PostgreSQL è il database applicativo. Il modello include utenti, profili Organizer, territori, categorie, eventi, tipologie di ticket, booking, booking item e altri moduli presenti nelle migrazioni.

Per il booking sono rilevanti due livelli di disponibilità:

- `events.available_total`: posti residui complessivi dell'evento;
- `ticket_types.max_quantity - ticket_types.booked_quantity`: disponibilità del contingente della singola tipologia.

I dati QA devono mantenere coerenti questi valori quando una sola tipologia attiva rappresenta tutti i posti vendibili.

## Redis

Redis è avviato da Docker Compose e configurato per il backend. L'architettura lo prevede per cache, rate limiting, job e notifiche; l'uso effettivo deve essere verificato per ogni nuovo flusso prima di documentarlo come operativo.

## Docker

`docker-compose.yml` avvia:

- `app`: PHP-FPM Laravel;
- `nginx`: ingresso HTTP sulla porta locale 8080;
- `frontend`: Vite sulla porta locale 5173;
- `postgres`: PostgreSQL 17;
- `redis`: Redis 7.

Il backend usa il database `eventi` e raggiunge PostgreSQL e Redis tramite i nomi dei servizi Docker.

## API REST

Le API sono versionate sotto `/api/v1`. Il contratto OpenAPI è in `docs/openapi.yaml`.

Endpoint Customer confermati:

- `GET /api/v1/categories`
- `GET /api/v1/events`
- `GET /api/v1/events/{event}`
- `GET /api/v1/customer/successful-events`
- `POST /api/v1/events/{event}/bookings`
- `GET /api/v1/me/bookings`
- `GET /api/v1/me/bookings/{booking}`
- `GET /api/v1/me/bookings/{booking}/qr`
- `POST /api/v1/me/bookings/{booking}/cancel`

Il backend espone inoltre route Organizer e Admin; il loro utilizzo da parte dei client deve essere documentato solo dopo verifica end-to-end.

## Frontend web Vue/Vite

Il frontend usa:

- Vue 3;
- TypeScript;
- Vue Router;
- Vite;
- ESLint e `vue-tsc` per qualità e build.

In sviluppo riceve `VITE_API_BASE_URL=http://localhost:8080/api/v1` dal servizio Docker Compose.

## App Flutter

L'app Flutter richiede Dart `>=3.4.0 <4.0.0` e usa:

- `flutter_riverpod` per dipendenze e stato;
- `go_router` per navigazione e redirect per ruolo;
- `http` per le API;
- `shared_preferences` per il token locale;
- `qr_flutter` per la rappresentazione del QR.

La struttura principale di `mobile/lib` comprende `core`, `features/auth`, `features/customer`, `features/organizer`, `features/demo` e componenti condivisi.

## Riverpod

Riverpod gestisce repository, autenticazione, caricamenti asincroni e controller di flusso. Nell'area Customer sono presenti provider per categorie, eventi, dettaglio evento, booking, lista prenotazioni, dettaglio, QR e annullamento.

Dopo un annullamento vengono invalidate lista e dettaglio della prenotazione, affinché stato e classificazione nelle tab siano ricaricati dal backend.

## GoRouter

GoRouter definisce shell distinte per Customer e Organizer. Tra le route Customer verificate sono presenti:

- `/customer`
- `/customer/all-events`
- `/customer/events/:id`
- `/customer/events/:id/book`
- `/customer/success`
- `/customer/bookings`
- `/customer/bookings/:id`

Le route Organizer esistono nell'app, ma le schermate correnti usano ancora il repository demo.

## Flusso Customer

1. Il Customer effettua login reale e riceve un token Sanctum.
2. Home, categorie, ricerca e liste eventi leggono le API.
3. Il dettaglio evento carica disponibilità e tipologie di ticket.
4. Il Customer apre il booking, seleziona ticket e partecipanti e invia la richiesta.
5. Lista e dettaglio prenotazioni vengono letti dal backend.
6. Il Customer recupera il QR e può annullare una prenotazione confermata finché l'evento non è concluso.

## Booking e QR

`CreateBooking` usa transazioni, lock di riga e idempotency key. Verifica disponibilità evento, contingenti ticket e coerenza tra ticket e partecipanti. Alla creazione aggiorna `available_total` e `booked_quantity`.

Il QR usa un token non prevedibile. Il backend conserva hash e valore cifrato, nasconde i campi interni nelle risposte e fornisce un endpoint autenticato per il recupero.

`CancelBooking` blocca i record coinvolti, aggiorna lo stato, libera posti evento e contingenti ticket. Il dettaglio è accessibile solo al Customer proprietario.

## Test e qualità

Backend:

- PHPUnit tramite `php artisan test`;
- Laravel Pint;
- PHPStan/Larastan livello 5;
- OpenAPI in `docs/openapi.yaml`.

Frontend web:

- ESLint;
- controllo TypeScript e build Vite.

Flutter:

- `flutter analyze`;
- `flutter test`;
- `flutter build web` per la build web di collaudo.

Le verifiche automatiche devono precedere il collaudo browser. Codice, test e documentazione devono evolvere insieme.

## Activity e Reservation Engine

L'Event Engine (`Event`, `Booking`, waitlist e check-in) resta separato dal Reservation Engine (`Activity`, `ActivityService`, disponibilità e `Reservation`). I domini condividono User, Organizer, notifiche, analytics e pattern di autorizzazione/locking, senza booking evento fittizie.

In Capacity Mode ogni slot espone coperti MòVi. La disponibilità sottrae le Reservation confermate; creazione e cancellazione usano transazioni, lock sulla riga Service e chiavi idempotenti. Le modifiche successive alle regole non invalidano Reservation esistenti.

Il Reservation Manager riusa questo dominio. Le API Organizer applicano ownership, filtri validati e transizioni esplicite; attendance è registrabile solo dopo la durata del servizio. Reminder e analytics riusano le infrastrutture esistenti, senza introdurre Table Mode o CRM separati.

## Discovery e Recommendation

`DiscoveryEngine` è un servizio applicativo indipendente dalle UI. Normalizza Event e Activity, applica disponibilità source-of-truth, distanza deterministica e Interest Profile esplicito. Il ranking usa pesi centralizzati; MòVi Plan e una futura MòVi AI potranno invocare questo servizio senza sostituirne i vincoli di dominio.

`PlanningEngine` consuma `DiscoveryEngine`, limita candidati e combinazioni, verifica compatibilità temporale/geografica e produce DTO temporanei senza persistenza. La futura MòVi AI fornirà intenti strutturati al Planning Engine e non accederà direttamente al database.
