# Regole permanenti del progetto

## Architettura
- Monorepo con `backend/` Laravel API e `frontend/` Vue 3 + TypeScript + Vite.
- Monolite modulare: Auth, Users, Organizers, Geography, Categories, Events, Moderation, Admin, Audit.
- API REST versionate sotto `/api/v1`.
- Controller sottili: validazione tramite Form Request, logica in Use Case/Application Service, persistenza tramite Eloquent/Repository quando serve.
- Non introdurre microservizi, codebase separate o dipendenze infrastrutturali non necessarie.

## Convenzioni
- Identificativi pubblici: ULID/UUID, mai ID sequenziali esposti come contratto API.
- Stati evento ammessi: `draft`, `pending_review`, `published`, `sold_out`, `cancelled`, `completed`, `rejected`, `suspended`.
- Ruoli utente: `participant`, `organizer`, `admin`.
- Errori API in formato uniforme: `message`, `errors`, `request_id`.
- Aggiornare documentazione e test insieme al codice.

## Sicurezza
- Ogni risorsa mutabile deve avere policy/autorizzazione esplicita.
- Proteggere da IDOR: un organizzatore non modifica eventi altrui e un partecipante non legge dati altrui.
- Validare server-side ogni input, usare whitelist dei campi aggiornabili.
- Non loggare password, token, allergie, esigenze particolari o altri dati sensibili.
- Dati delicati delle prenotazioni devono avere accesso limitato e retention ridotta.

## Dominio
- Solo eventi `published` e approvati appaiono pubblicamente.
- La disponibilità non deve mai diventare negativa.
- Prenotazioni future: usare transazioni, lock di riga, constraint DB e idempotency key.
- Il QR code futuro deve contenere token non prevedibile e validabile una sola volta.
- Notifiche promozionali e operative restano separate.

## Comandi
- Avvio locale: `docker compose up --build`.
- Backend: `docker compose exec app php artisan test`.
- Formatter backend: `docker compose exec app ./vendor/bin/pint`.
- Static analysis: `docker compose exec app ./vendor/bin/phpstan analyse`.
- Frontend: `docker compose exec frontend npm run lint && docker compose exec frontend npm run build`.

## Definition of done
- Migrazioni e seed girano in ambiente Docker.
- Test automatici aggiornati e verdi.
- API documentate in OpenAPI.
- Validazioni, policy e casi negativi coperti.
- README e documenti in `docs/` aggiornati.
