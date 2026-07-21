# Roadmap APP EVENTI

> Aggiornare questa roadmap quando cambia lo stato reale del repository. Non segnare una milestone come completata senza verifiche tecniche e collaudo.

## Visione del prodotto

APP EVENTI è una piattaforma territoriale per scoprire, pubblicare, prenotare e gestire eventi ed esperienze locali, con esperienze separate per Customer, Organizer e Admin. Il prodotto mantiene un backend API unico e client web/mobile separati nel medesimo monorepo.

## Milestone

| Versione | Stato | Obiettivo |
| --- | --- | --- |
| v0.1 | Completata | Backend stabile e prima vertical slice operativa |
| v0.2 | Completata | Analisi statica PHPStan pulita |
| v0.3 | Completata | Autenticazione mobile collegata al backend reale |
| v0.4 | Completata | Customer Home, categorie ed eventi collegati alle API reali |
| v0.5 | Completata | Booking Customer reale, idempotenza, QR, lista/dettaglio e annullamento |
| v0.6 | Completata | Dashboard e lista eventi Organizer con dati reali |
| v0.7 | Completata | Registrazione e autenticazione reale per Customer e Organizer |
| v0.8 | Pianificata | Creazione e modifica evento Organizer |
| v0.9 | Pianificata | Analytics, recensioni e strumenti Organizer |
| v1.0 | Pianificata | MVP validato e pronto al rilascio secondo criteri da definire |

## v0.6 — Dashboard e lista eventi Organizer

Completati dashboard, KPI, lista eventi, classificazioni, filtri e paginazione reali. Creazione e modifica evento sono state spostate alla milestone successiva.

## v0.7 — Registrazione e autenticazione reale

Completata end-to-end sul frontend web:

1. Registrazione Customer con sessione autenticata.
2. Registrazione Organizer con profilo `pending` creato atomicamente.
3. Login e logout reali con token Sanctum.
4. Sessione persistita e verificata tramite `GET /api/v1/me`.
5. Redirect per ruolo e schermata dedicata all'Organizer non approvato.
6. Rimozione dei fallback demo dai flussi auth reali.
7. Validazioni, errori uniformi, test backend, OpenAPI e Browser QA manuale completati.

## Prossima milestone

**CREAZIONE E MODIFICA EVENTO ORGANIZER**

Il prossimo lavoro deve verificare e completare il contratto backend esistente prima di collegare i form web, senza includere prenotazioni, partecipanti o check-in.

## Attività future

### Organizer

- Creazione, dettaglio e modifica evento con dati reali.
- Prenotazioni ricevute, partecipanti e check-in.
- Sostituzione progressiva di `InMemoryDemoRepository` nell'app Flutter.
- Allineamento di autorizzazioni, stati evento ed errori tra tutti i client.

### Analytics, recensioni e Opportunity Engine

- Verificare e integrare gli endpoint Organizer già presenti.
- Definire KPI comprensibili e coerenti con i dati reali.
- Consolidare il motore deterministico senza dipendenze AI esterne non richieste.

### Preparazione MVP

- Rimuovere o isolare i dati demo dai flussi dichiarati reali.
- Allineare README, OpenAPI e documentazione allo stato effettivo.
- Eseguire test automatici, collaudo browser e verifica dei dati QA.
- Definire criteri di rilascio, sicurezza, osservabilità e gestione operativa.
