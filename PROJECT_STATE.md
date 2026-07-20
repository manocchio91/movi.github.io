# Stato del progetto APP EVENTI

> Aggiornare questo documento ogni volta che cambia lo stato reale del repository.

## Riferimenti correnti

- Versione corrente: `v0.5-customer-booking-qr`
- Ultimo aggiornamento: 20 luglio 2026
- Repository: `manocchio91/app-eventi`
- Branch corrente: `feat/organizer-dashboard-contract`
- Ultimo commit applicativo: `c5e9605 - v0.5 - Customer booking reale con QR e annullamento`
- Ultimo aggiornamento documentale noto: `ddee2bb - docs: align project state with repository`
- Ultimo tag: `v0.5-customer-booking-qr`, verificato localmente sul commit `c5e9605`

Il commit documentale indicato può essere precedente a HEAD quando l'ultimo commit riguarda esclusivamente l'aggiornamento di questo stesso documento. Non costituisce un blocco se HEAD è successivo e il working tree è coerente.

## Stato delle aree

- Backend: API Laravel operative sotto `/api/v1`; autenticazione Sanctum, booking, QR, annullamento, idempotenza, disponibilità e test presenti.
- Dashboard Organizer backend: contratto stabilizzato per KPI eventi, booking e partecipanti; OpenAPI e test feature dedicati aggiornati.
- Customer Flutter: integrazione reale completata per autenticazione, home, categorie, ricerca, eventi, dettaglio, eventi di successo, booking, QR, lista/dettaglio prenotazioni e annullamento.
- Organizer Flutter: schermate e navigazione presenti, ma i dati provengono ancora da `InMemoryDemoRepository`. Il backend Organizer espone già dashboard, eventi, partecipanti, check-in, analytics e altre API da analizzare prima dell'integrazione.
- Frontend web: applicazione Vue 3 + TypeScript + Vite presente nel monorepo.
- Documentazione permanente: completata e pubblicata.

## Milestone completate

- v0.1: backend stabile
- v0.2: PHPStan pulito
- v0.3: autenticazione mobile reale
- v0.4: Customer Home collegata alle API reali
- v0.5: booking Customer reale con QR e annullamento

## Task corrente

Contratto backend di `GET /api/v1/organizer/dashboard` stabilizzato e verificato; modifiche in attesa di approvazione per commit e push.

## Task completati nella sessione

- Centralizzata la definizione delle booking valide: `confirmed`, `checked_in`, `completed`.
- Definito `participants_total` come somma di `bookings.total_people` delle sole booking valide.
- Stabilite le classificazioni `future_events`, `completed_events` e `unpublished_events`.
- Allineati i KPI top-level della dashboard ai corrispondenti valori BI.
- Aggiunti test feature dedicati per contratto, isolamento Organizer, stati evento, booking e disponibilità.
- Documentato il payload reale della dashboard in OpenAPI.
- Verificati test mirati, suite backend, Pint, PHPStan e OpenAPI.

## Prossimo task

Revisione delle modifiche e, dopo approvazione esplicita, commit e push del branch `feat/organizer-dashboard-contract`.

## Problemi aperti

Nessun problema bloccante. Restano fuori scope la semantica di `new_customers_rate`, le statistiche storiche già persistite e la distinzione visuale tra evento concluso e prenotazioni chiuse.

## Come riprendere il lavoro

1. Aprire la root del repository.
2. Eseguire:

   ```bash
   git status
   git branch --show-current
   git log --oneline -5
   ```

3. Leggere `PROJECT_STATE.md`, `ROADMAP.md`, `ARCHITECTURE.md` e `DEVELOPMENT_RULES.md`.
4. Verificare che il branch sia quello previsto per il task e che il working tree non contenga modifiche estranee.
5. Avviare la ricognizione dell'area Organizer esistente prima di modificare codice, API o test.
6. Aggiornare questo documento al termine della sessione.
