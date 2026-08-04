# Roadmap APP EVENTI

> Aggiornare questa roadmap quando cambia lo stato reale del repository. Non segnare una milestone come completata senza verifiche tecniche e collaudo.

## Visione del prodotto

APP EVENTI è una piattaforma territoriale per scoprire, pubblicare, prenotare e gestire eventi ed esperienze locali, con esperienze separate per Customer, Organizer e Admin.

## Milestone

| Versione | Stato | Obiettivo |
| --- | --- | --- |
| v0.1 | Completata | Backend stabile e prima vertical slice operativa |
| v0.2 | Completata | Analisi statica PHPStan pulita |
| v0.3 | Completata | Autenticazione mobile collegata al backend reale |
| v0.4 | Completata | Customer Home, categorie ed eventi collegati alle API reali |
| v0.5 | Completata | Booking Customer reale, idempotenza, QR e annullamento |
| v0.6 | Completata | Dashboard e lista eventi Organizer con dati reali |
| v0.7 | Completata | Registrazione e autenticazione reale per Customer e Organizer |
| v0.8 | Completata | Creazione e modifica eventi Organizer con ticket sicuri |
| v0.9 | Completata | Onboarding fiscale Organizer e approvazione Admin |
| v1.0-A | Completata | Gestione prenotazioni ricevute Organizer e ripristino booking Customer web |
| v1.0 | Completata | Booking Customer, gestione prenotazioni/partecipanti e check-in Organizer |
| v1.1 | Completata | Profili MVP, piani commerciali, ricerca Admin, email operative e regression gate |

## v0.8 — Organizer Event Creation and Editing

Completata end-to-end sul frontend web:

1. Creazione reale di eventi inizialmente in bozza.
2. Pubblicazione come transizione backend esplicita.
3. Modifica degli eventi propri negli stati consentiti.
4. Categorie, comuni e dettaglio evento caricati dalle API.
5. Ticket esistenti aggiornati per ULID senza perdere identità o prenotazioni.
6. Blocco della riduzione sotto `booked_quantity` e della rimozione di ticket prenotati.
7. Transazioni, lock, autorizzazioni, OpenAPI, test automatici e Browser QA completati.

## v0.9 - Organizer Fiscal Onboarding and Admin Approval

Completata end-to-end sul frontend web:

1. Raccolta e salvataggio progressivo dei dati fiscali Organizer.
2. Invio alla revisione con stati e redirect coerenti.
3. Coda e dettaglio Admin con approvazione, richiesta modifiche e rifiuto.
4. Protezione delle funzioni operative fino all'approvazione.
5. Sessione auth reale, logout completo e selezione ruolo senza accessi simulati.
6. Test automatici e Browser QA finale completati.
7. Compilazione automatica di Comune e Provincia dal CAP tramite anagrafica backend, inclusi CAP condivisi da più Comuni.

## v1.0-A - Organizer Bookings Management

Completata end-to-end sul frontend web:

1. Lista prenotazioni Organizer con ricerca, filtri, ordinamento e paginazione server-side.
2. Dettaglio in sola lettura con cliente, evento, ticket, persone, stato, note, QR e check-in.
3. Isolamento delle prenotazioni per Organizer proprietario e visibilità completa Admin.
4. Ripristino del flusso Customer lista evento, dettaglio, scelta ticket, booking e QR.
5. Test automatici, OpenAPI e Browser QA finale completati.

## Prossima milestone

Preparazione MVP: criteri di rilascio, hardening operativo e rimozione dei residui demo.

## v1.1 - MVP Profiles, Commercial Plans and Operations

Completata end-to-end sul frontend web:

1. Profilo Customer con modifica dati e password, booking, QR, annullamento, preferiti e recensioni reali.
2. Profilo Organizer pubblico e privato, upload validati, social facoltativi e dati fiscali non pubblici.
3. Ricerca Admin reale e gestione di piani `unlimited` e `pay_per_booking`.
4. Accordi commerciali manuali con stato, date e note, senza pagamenti o fatturazione automatica.
5. Email operative tramite infrastruttura Laravel e modalità sicura locale/test.
6. `APP_KEY` persistente tra i riavvii e recupero controllato dei QR cifrati con chiavi precedenti.
7. Suite automatica, analisi statica, build e Browser QA completati.

## Attività future

### Organizer

- Sostituzione progressiva di `InMemoryDemoRepository` nell'app Flutter.
- Statistiche e strumenti operativi avanzati.

### Preparazione MVP

- Rimuovere o isolare i dati demo dai flussi dichiarati reali.
- Aggiungere test frontend automatizzati quando il perimetro lo richiederà.
- Allineare README, OpenAPI e documentazione allo stato effettivo.
- Definire criteri di rilascio, sicurezza, osservabilità e gestione operativa.
