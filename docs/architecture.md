# Architettura

## Scelta frontend

La prima versione usa Vue 3 + TypeScript + Vite come app separata nel monorepo. Laravel espone API REST versionate e resta indipendente dalla UI. Inertia sarebbe una scelta valida per ridurre il salto mentale full-stack, ma in questo prodotto la PWA avrà flussi pubblici, dashboard partecipante, dashboard organizzatore e pannello admin: una SPA Vue separata mantiene più chiara la frontiera API e facilita l'evoluzione futura senza introdurre microservizi.

## Backend

Laravel è organizzato come monolite modulare:
- Controller API in `app/Http/Controllers/Api/V1`.
- Request validation in `app/Http/Requests`.
- Use case espliciti in `app/Actions`.
- Modelli Eloquent in `app/Models`.
- Policy Laravel per autorizzazioni.
- Migrazioni PostgreSQL con vincoli e indici.

## Infrastruttura locale

Docker Compose avvia:
- `app`: PHP-FPM Laravel.
- `nginx`: ingresso HTTP backend.
- `frontend`: Vite dev server.
- `postgres`: database applicativo.
- `redis`: cache, code, rate limiting e job futuri.

## Decisioni
- API REST sotto `/api/v1`.
- Sanctum token per autenticazione PWA nel primo MVP.
- PostgreSQL per ricerca e filtri iniziali; niente Elasticsearch.
- Redis predisposto per job asincroni, notifiche e rate limiting.
- Booking domain predisposto nello schema, codice rimandato alla slice prenotazioni.
