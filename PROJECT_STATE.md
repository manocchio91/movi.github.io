# Stato del progetto APP EVENTI

> Aggiornare questo documento ogni volta che cambia lo stato reale del repository.

## Riferimenti correnti

- Versione corrente: `v0.6 - Dashboard Organizer Web`
- Ultimo aggiornamento: 20 luglio 2026
- Repository: `manocchio91/app-eventi`
- Branch corrente: `feat/organizer-event-list`
- Ultimo commit applicativo pubblicato: `6228983 - v0.6 - Complete Organizer web dashboard`
- Ultimo tag: `v0.5-customer-booking-qr`, verificato localmente sul commit `c5e9605`

L'hash documentato può essere precedente a HEAD quando l'ultimo commit aggiorna questo stesso documento. La verifica iniziale considera branch, working tree, cronologia recente e coerenza funzionale.

## Stato delle aree

- Backend: API Laravel operative sotto `/api/v1`; autenticazione Sanctum, booking, QR, annullamento, idempotenza, disponibilità e test presenti.
- Dashboard Organizer backend: contratto di `GET /api/v1/organizer/dashboard` stabilizzato; KPI centralizzati in `OrganizerDashboardMetrics`, documentati in OpenAPI e coperti da test feature.
- Dashboard Organizer web: collegata esclusivamente a `GET /api/v1/organizer/dashboard` e completata con dati reali.
- Lista eventi Organizer web: collegata a `GET /api/v1/organizer/events` con filtri e paginazione server-side, loader indipendente e stati UI dedicati.
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

Milestone Organizer Event List completata e verificata.

## Task completati nella sessione

- Collegata la lista eventi Organizer ai dati reali.
- Allineati alla dashboard i filtri `future`, `completed` e `unpublished`; gli eventi `cancelled` restano separati.
- Implementata paginazione reale con filtro mantenuto al cambio pagina.
- Introdotto un loader dedicato, indipendente da dashboard, recensioni, calendario e opportunità.
- Gestiti loading, errore leggibile, retry, empty state specifici, `401` e `403`.
- Rimossi dalla lista i pulsanti privi di flusso reale.
- Mantenuto l'annullamento evento reale con conferma, singolo invio, errore leggibile e refresh della lista.
- Allineate in italiano le etichette visibili e i badge degli stati reali.
- Aggiunti validazione, test backend mirati e documentazione OpenAPI del contratto paginato.
- Verificati suite Laravel, Pint, PHPStan, OpenAPI, lint e build frontend.
- Browser QA manuale completato con esito positivo su login, lista, filtri, paginazione, empty state e viewport desktop/mobile.
- `401`, `403`, retry e annullamento non sono stati riprodotti manualmente e non sono bloccanti; le verifiche automatiche sono verdi.

## Prossimo task

Sviluppare il prossimo blocco Organizer circoscritto alla creazione e modifica evento, verificando prima il contratto backend esistente.

## Debiti aperti

- Dettaglio evento Organizer.
- Modifica evento Organizer.
- Creazione evento Organizer completa.
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
4. Avviare l'analisi circoscritta della creazione e modifica evento Organizer prima di modificare codice o contratti API.
5. Aggiornare questo documento al termine della sessione.
