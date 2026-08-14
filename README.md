# MòVi

Piattaforma territoriale per scoprire, pubblicare e moderare eventi ed esperienze locali a numero chiuso. La prima vertical slice copre autenticazione, ruoli, territori del Molise, categorie, profilo organizzatore, creazione evento in bozza, invio in moderazione, approvazione amministrativa e pubblicazione nell'elenco pubblico.

## Stack
- Backend: PHP, Laravel, Sanctum, PostgreSQL, Redis.
- Frontend: Vue 3, TypeScript, Vite.
- Infrastruttura: Docker Compose, Nginx, Postgres, Redis.
- Qualità: PHPUnit, Laravel Pint, PHPStan, ESLint, TypeScript build.

## Avvio
```bash
cp backend/.env.example backend/.env
docker compose up --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

API backend: `http://localhost:8080/api/v1`

Frontend: `http://localhost:5173`

Health check: `http://localhost:8080/health`

## App mobile Flutter
Il prototipo mobile Android/iOS si trova in `mobile/` e contiene due esperienze separate, Customer e Organizer, con login demo e dati mock sostituibili dalle API reali.

```bash
cd mobile
flutter pub get
flutter analyze
flutter test
flutter run
```

Account mobile demo:
- Customer: `customer@demo.it` / `password`
- Organizer: `organizer@demo.it` / `password`

## Account demo
- Admin: `admin@example.test` / `password`
- Organizzatore: `organizer@example.test` / `password`
- Partecipante: `participant@example.test` / `password`

## Comandi qualità
```bash
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse
docker compose exec app php artisan test
docker compose exec frontend npm run lint
docker compose exec frontend npm run build
```

## Documentazione
- Visione prodotto: `docs/product-vision.md`
- Architettura: `docs/architecture.md`
- Modello dominio: `docs/domain-model.md`
- Schema database: `docs/database-schema.md`
- API/OpenAPI: `docs/api.md` e `docs/openapi.yaml`
- Sicurezza e privacy: `docs/security-privacy.md`
- Roadmap: `docs/roadmap.md`
- Deploy staging/production, backup e restore: `docs/deployment.md`

## Stato implementazione
Questa repository contiene lo scheletro operativo della prima vertical slice. Prenotazioni, QR check-in, lista d'attesa e notifiche sono documentati e predisposti nel dominio, ma non implementati nel codice applicativo iniziale.
