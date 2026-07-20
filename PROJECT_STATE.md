# Stato del progetto APP EVENTI

> Aggiornare questo documento ogni volta che cambia lo stato reale del repository.

## Riferimenti correnti

- Versione corrente: `v0.6 - Dashboard Organizer Web`
- Ultimo aggiornamento: 20 luglio 2026
- Repository: `manocchio91/app-eventi`
- Branch corrente: `feat/organizer-dashboard-web`
- Ultimo commit applicativo pubblicato: `0d47bc4 - v0.6 - Stabilize Organizer dashboard contract`
- Ultimo tag: `v0.5-customer-booking-qr`, verificato localmente sul commit `c5e9605`

L'hash documentato può essere precedente a HEAD quando l'ultimo commit aggiorna questo stesso documento. La verifica iniziale considera branch, working tree, cronologia recente e coerenza funzionale.

## Stato delle aree

- Backend: API Laravel operative sotto `/api/v1`; autenticazione Sanctum, booking, QR, annullamento, idempotenza, disponibilità e test presenti.
- Dashboard Organizer backend: contratto di `GET /api/v1/organizer/dashboard` stabilizzato; KPI centralizzati in `OrganizerDashboardMetrics`, documentati in OpenAPI e coperti da test feature.
- Dashboard Organizer web: collegata esclusivamente a `GET /api/v1/organizer/dashboard` e completata con dati reali.
- Customer Flutter: integrazione reale completata per autenticazione, home, categorie, ricerca, eventi, dettaglio, eventi di successo, booking, QR, lista/dettaglio prenotazioni e annullamento.
- Organizer Flutter: schermate e navigazione presenti, ma i dati provengono ancora da `InMemoryDemoRepository`.
- Documentazione permanente: aggiornata; le decisioni architetturali e di processo sono registrate in `DECISIONS.md`.

## Milestone completate

- v0.1: backend stabile
- v0.2: PHPStan pulito
- v0.3: autenticazione mobile reale
- v0.4: Customer Home collegata alle API reali
- v0.5: booking Customer reale con QR e annullamento
- v0.6: Dashboard Organizer Web collegata ai dati reali

## Task corrente

Milestone Dashboard Organizer Web completata e verificata.

## Task completati nella sessione

- Collegata la dashboard web Organizer esclusivamente a `GET /api/v1/organizer/dashboard`.
- Visualizzati KPI reali per eventi futuri, conclusi e non pubblicati, booking valide, partecipanti, posti disponibili e occupati, follower, visualizzazioni, rating e recensioni.
- Introdotto un loader dedicato e indipendente dalle altre API Organizer.
- Gestiti loading dedicato, errore con retry, sessione non valida `401` e accesso negato `403`.
- Verificata la corretta visualizzazione dei valori zero.
- Gestito il rating `null` come assenza di recensioni.
- Rimosso il grafico con valori hardcoded.
- Eseguiti con esito positivo lint e build frontend.
- Browser QA manuale completato dall'utente con esito positivo.

## Prossimo task

Collegare la lista eventi Organizer ai dati reali, introducendo i filtri e la distinzione tra eventi futuri, conclusi e non pubblicati secondo la roadmap.

## Debiti aperti

- Lista eventi Organizer.
- Filtri degli eventi.
- Dettaglio evento Organizer.
- Modifica evento Organizer.
- Prenotazioni ricevute.
- Partecipanti.
- Check-in.
- Statistiche avanzate.
- Autenticazione demo trasversale.
- Test frontend automatizzati.
- Redesign grafico della dashboard.

## Come riprendere il lavoro

1. Aprire la root del repository.
2. Leggere integralmente `PROJECT_STATE.md`, `ROADMAP.md`, `ARCHITECTURE.md`, `DEVELOPMENT_RULES.md` e `DECISIONS.md`.
3. Verificare branch, working tree e cronologia recente.
4. Avviare l'analisi circoscritta della lista eventi Organizer prima di modificare codice o contratti API.
5. Aggiornare questo documento al termine della sessione.
