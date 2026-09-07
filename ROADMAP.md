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
| v1.2 | Completata | Backoffice Admin e business, KPI economici teorici e gestione operativa completa |
| v1.3 | Completata | Hardening MVP, eliminazione runtime demo e preparazione beta privata |
| v1.4 | Completata | Production readiness tecnica per staging e private beta |
| v1.5 | Completata | Staging readiness e preparazione tecnica del deploy/private beta, senza deploy remoto |
| v1.8 | Completata | Redesign premium web multi-ruolo |
| v1.9 | Completata | Redesign premium Flutter multi-ruolo e checkout booking completo |
| v2.0-rc1 | Completata | Release Candidate locale pronta per il deploy |
| v2.1-engagement-1 | Completata | Follow Organizer e notifiche follower privacy-safe |
| v2.1-engagement-2 | Completata | Waitlist automatica FIFO con offerta temporanea e protezione overbooking |
| v2.1-engagement-3 | Completata | Condivisione evento web/mobile e fondazione deep link |
| v2.1-engagement-4 | Completata | Writer analytics reale e funnel Organizer web/mobile |

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

Foundation MòVi 2.0 / Activity, senza duplicare Event e i componenti di dominio riutilizzabili.

## v1.1 - MVP Profiles, Commercial Plans and Operations

Completata end-to-end sul frontend web:

1. Profilo Customer con modifica dati e password, booking, QR, annullamento, preferiti e recensioni reali.
2. Profilo Organizer pubblico e privato, upload validati, social facoltativi e dati fiscali non pubblici.
3. Ricerca Admin reale e gestione di piani `unlimited` e `pay_per_booking`.
4. Accordi commerciali manuali con stato, date e note, senza pagamenti o fatturazione automatica.
5. Email operative tramite infrastruttura Laravel e modalità sicura locale/test.
6. `APP_KEY` persistente tra i riavvii e recupero controllato dei QR cifrati con chiavi precedenti.
7. Suite automatica, analisi statica, build e Browser QA completati.

## v1.2 - Admin and Business Backoffice

Completata end-to-end sul frontend web:

1. Dashboard Admin reale con KPI operativi ed economici teorici.
2. Gestione ricercabile e paginata di Organizer, Customer ed eventi con dettagli protetti.
3. Transizioni backend protette per Organizer ed eventi, senza modifiche distruttive alle prenotazioni.
4. Piani `UNLIMITED` e `PAY_PER_BOOKING`, accordi in centesimi, tariffa personalizzata e storico modifiche.
5. Ricerca globale raggruppata e isolamento completo delle API Admin.
6. Suite automatica, analisi statica, lint, build e Browser QA completati.

## v1.3 - Hardened MVP and Private Beta

Completata end-to-end:

1. Rimossi dati, fallback, pulsanti, route e schermate runtime esclusivamente demo.
2. Customer mobile mantenuto soltanto sui flussi reali collegati al backend.
3. Organizer reso disponibile esclusivamente sul web per la beta; il mobile comunica il limite senza simulazioni.
4. Error handling, sessioni, autorizzazioni, messaggi e contratti modificati sottoposti a hardening.
5. Regression gate automatico e Browser QA desktop, tablet e mobile completati.
6. OpenAPI Contract Cleanup completato: 111 route reali coperte, operationId univoci e Redocly a 0 errori/0 warning.
7. Customer Booking Experience completata con telefono obbligatorio, riepilogo e conferma, modifica e annullamento, disponibilità atomica, QR e booking code invariati, aggiornamento degli strumenti Organizer e regression test frontend/backend.

## v1.4 - Production Readiness

Completata end-to-end:

1. Configurazione distinta per local, testing e production/staging senza segreti versionati.
2. Container production per Laravel, frontend statico, Nginx, PostgreSQL, Redis, queue worker e scheduler con volumi persistenti.
3. Health check applicazione/database/Redis, rate limiting sensibile, error handling sicuro e logging ruotato.
4. Upload Organizer limitati a JPEG, PNG e WebP con MIME reale, dimensioni e filesystem sostituibile con S3-compatible.
5. Email operative accodate e predisposte per SMTP reale senza influire sulle transazioni business.
6. Pagine legali placeholder, documentazione deploy, migrazioni, backup e restore PostgreSQL.
7. Gate automatici e Browser QA Customer, Organizer, Admin e responsive completati.

## v1.5 - Staging Readiness

Completata senza deploy remoto:

1. Ambiente staging distinto con `APP_ENV=staging`, debug disabilitato e nessun segreto versionato.
2. Deploy Docker ripetibile con migrazioni non distruttive, volumi persistenti e controlli per worker e scheduler.
3. Seeder beta opzionale e idempotente con credenziali esterne, smoke test leggero e preflight della configurazione.
4. Template TLS/HTTPS, procedure operative, backup/restore e checklist private beta pronti per la fase finale.
5. Artifact backend/frontend e gate automatici verificati localmente.
6. Hosting, dominio, DNS, TLS e SMTP reali intenzionalmente posticipati fino al completamento locale del prodotto.

## Attività future

### Preparazione MVP

- Configurare hosting, dominio/DNS, TLS, SMTP e segreti production nel Prompt 8/8.
- Eseguire backup iniziale, smoke test e osservabilità sull'ambiente remoto.
## Mobile multi-ruolo

- [x] Esperienza Customer operativa via API
- [x] Esperienza Organizer operativa via API
- [x] Esperienza Admin operativa via API
- [x] Navigazione e componenti Material 3 condivisi

## MòVi 2.0

- [x] Activity Foundation e Restaurant
- [x] Reservation Engine Capacity Mode
- [x] Follow/Review Activity, notifiche e analytics foundation
- [x] MòVi Manager Reservation avanzato
- [ ] Table Mode
- [x] Discovery & Recommendation Foundation
- [x] MòVi Plan deterministico
- [x] MòVi AI su intenti strutturati
- [x] Menu digitale e pre-order Restaurant
- Graphical restaurant floor plan — possible future enhancement, not required for MòVi 2.0.
