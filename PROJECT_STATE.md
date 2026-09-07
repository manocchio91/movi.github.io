# Stato del progetto APP EVENTI

> Aggiornare questo documento ogni volta che cambia lo stato reale del repository.

## Riferimenti correnti

- Versione corrente: `v2.0-rc1 - Final Release Candidate`
- Ultimo aggiornamento: 13 agosto 2026
- Repository: `manocchio91/movi`
- Branch corrente: `feat/movi-engagement-automation`
- Commit milestone precedente: `v1.9 - Complete premium Flutter redesign and booking checkout`
- Ultimo tag: `v0.5-customer-booking-qr`, verificato localmente sul commit `c5e9605`

## Stato delle aree

- Backend: API Laravel sotto `/api/v1`, autenticazione Sanctum, autorizzazioni per ruolo e contratto uniforme degli errori.
- Autenticazione web: registrazione Customer e Organizer, login/logout reali e ripristino sessione tramite `GET /api/v1/me`.
- Dashboard, lista, creazione e modifica eventi Organizer web: collegate esclusivamente alle API reali.
- Onboarding fiscale Organizer e approvazione Admin: collegati alle API reali, con accesso operativo vincolato allo stato approvato.
- Onboarding fiscale Organizer: Comune e Provincia vengono derivati dall'anagrafica backend al completamento di un CAP italiano valido, con gestione dei CAP associati a più Comuni.
- Flutter multi-ruolo: Customer, Organizer e Admin usano API reali, role guard e design premium condiviso.
- Customer web: profilo e password, cronologia prenotazioni, QR recuperabile, annullamento, preferiti e recensioni collegati alle API reali.
- Organizer web: profilo pubblico/privato, logo e copertina, dati fiscali riservati e pagina pubblica con storico eventi.
- Admin web: dashboard KPI reale, ricerca globale, gestione completa di Organizer, Customer ed eventi, catalogo piani e accordi commerciali senza pagamenti automatici.
- Email operative: approvazione/revisione Organizer e conferma/annullamento booking accodate, configurabili via SMTP e isolate dalle transazioni business.

## Milestone completate

- v0.1: backend stabile
- v0.2: PHPStan pulito
- v0.3: autenticazione mobile reale
- v0.4: Customer Home collegata alle API reali
- v0.5: booking Customer reale con QR e annullamento
- v0.6: dashboard e lista eventi Organizer web con dati reali
- v0.7: registrazione e autenticazione web reali per Customer e Organizer
- v0.8: creazione e modifica eventi Organizer con gestione ticket sicura
- v0.9: onboarding fiscale Organizer, revisione Admin e correzione definitiva del flusso auth/logout
- v1.0: booking Customer, partecipanti, prenotazioni Organizer e check-in
- v1.1: profili MVP, piani commerciali, ricerca Admin, email operative e regressioni Customer risolte
- v1.2: backoffice Admin e business completo con KPI economici teorici, transizioni protette e storico accordi
- v1.3: hardening MVP, rimozione runtime demo e preparazione beta privata
- v1.4: configurazione, container, sicurezza e operatività pronte per staging/private beta
- v1.5: staging readiness, deploy ripetibile, seed beta sicuro, smoke test e runbook operativo
- v1.8: redesign premium web Customer, Organizer e Admin
- v1.9: redesign premium Flutter multi-ruolo e checkout booking completo
- v2.0-rc1: configurazione e documentazione della Release Candidate finale

## Task corrente

Macro 4 MòVi Engagement completata: analytics Organizer reali con writer centralizzato e funnel web/Flutter.

## Funzionalità completate

- Writer unico `AnalyticsRecorder` per discovery, share e mutazioni reali di booking, waitlist, check-in, review e follow.
- Impression e aperture dettaglio deduplicate per evento, hash di sessione e giorno, senza IP o dati personali.
- Funnel Organizer reale con cancellazioni, waitlist recuperate, check-in, no-show, recensioni, follower e tassi derivati.
- `categoryPerformance()` allineata alla semantica centralizzata delle sole booking valide.
- Dashboard Organizer web e Flutter alimentate dalle API reali, con zero/empty state al posto di KPI dimostrativi.

- URL canonico pubblico dell'evento generato dal backend tramite `FRONTEND_URL`.
- Web Share API con fallback copia link e azione WhatsApp nel dettaglio evento web.
- Share sheet nativa Flutter tramite package mantenuto `share_plus`.
- Route diretta evento già operativa; custom scheme mobile predisposto e configurazioni HTTPS esterne documentate senza dichiararle attive.

- Lista d'attesa Customer idempotente per eventi esauriti, con posizione FIFO calcolata dal backend.
- Promozione automatica dopo annullamento, rinuncia o scadenza, con una sola offerta attiva per evento.
- Capacità riservata atomicamente durante l'offerta e conversione idempotente in booking senza overbooking.
- Timeout configurabile, scheduler di scadenza, notifica operativa e UI reali web/Flutter.

- Follow/unfollow Organizer idempotente con stato `is_following`, conteggio e lista Customer degli Organizer seguiti.
- Elenco follower Organizer minimizzato tramite Resource, senza email o identificativi interni.
- Preferenze separate per notifiche operative, promozionali e nuovi eventi degli Organizer seguiti.
- Notifiche di pubblicazione elaborate da job dopo commit e deduplicate per evento, Customer e canale.
- UI Follow reale su web e Flutter, senza mock.

- Profilo fiscale Organizer salvabile parzialmente e inviabile alla revisione Admin.
- Stati profilo gestiti: `incomplete`, `pending`, `changes_requested`, `verified`, `rejected`, `suspended`.
- Redirect auth derivato dallo stato reale restituito dal backend.
- Revisione Admin con approvazione, richiesta modifiche e rifiuto motivato.
- Accesso alle funzioni operative consentito soltanto agli Organizer approvati.
- Dati fiscali riservati all'Organizer proprietario e agli Admin autorizzati.
- Logout resiliente agli errori di rete, pulizia completa della sessione locale e ritorno esplicito a `/login`.
- Selezione del ruolo priva di effetti di autenticazione; accesso alla shell solo dopo login o ripristino `/me` validi.
- Account QA `organizer@example.test` mantenuto approvato; i nuovi Organizer iniziano dall'onboarding fiscale.
- Ricerca Comune/Provincia per CAP eseguita esclusivamente sull'anagrafica Comuni backend, senza servizi esterni o dataset frontend.
- Lista e dettaglio Prenotazioni Organizer in sola lettura, con ricerca, filtri, ordinamento, paginazione e isolamento per proprietario evento.
- Flusso Customer web ripristinato dalla lista pubblica al dettaglio evento, scelta ticket, prenotazione idempotente e QR.
- Telefono Customer obbligatorio, normalizzato e storicizzato nella singola prenotazione; visibile solo al Customer proprietario, all'Organizer dell'evento e agli Admin autorizzati, incluso il CSV partecipanti.
- Prenotazioni Customer modificabili e annullabili con conferma, riepilogo dinamico, aggiornamento atomico di ticket, quantità e disponibilità, conservando booking code e QR.
- Identificatori pubblici di eventi e ticket uniformati su ULID nel flusso Customer.
- Partecipanti Organizer per evento con ricerca, filtri, paginazione, conteggi ed esportazione CSV; ogni riga conserva la semantica reale della prenotazione e di `total_people`.
- Check-in Organizer tramite token QR o codice prenotazione, con blocco dei riutilizzi, delle prenotazioni annullate e dei codici appartenenti ad altri eventi.
- Recupero atomico dei QR cifrati con una precedente `APP_KEY`, senza esporre errori tecnici e preservando la validità one-time del check-in.
- Piani `unlimited` e `pay_per_booking` con periodicità, importi in centesimi, stato, date e note dell'accordo gestiti dall'Admin.
- Dashboard Admin reale con KPI per Organizer, Customer, eventi, prenotazioni, partecipanti, check-in e ricavi teorici.
- Gestione Admin ricercabile e paginata di Organizer, Customer ed eventi, con dati fiscali riservati e transizioni backend protette.
- Sospensione e riattivazione eventi senza alterare prenotazioni esistenti.
- Accordi `UNLIMITED` e `PAY_PER_BOOKING` con tariffa personalizzata per partecipante e storico minimo delle modifiche.
- Rimossi fallback web, pulsanti non operativi, route e schermate mobile basate su `InMemoryDemoRepository`.
- Errori API 500 sempre generici anche con debug attivo; login non valido allineato a HTTP 401.
- Flutter Customer, Organizer e Admin completati con flussi reali e QA integrato.

## Verifiche

- Test mirati auth finali: 8 test e 84 asserzioni verdi.
- Test mirati auth e onboarding: 14 test e 135 asserzioni verdi.
- Suite Laravel completa: 97 test e 653 asserzioni verdi.
- Test mirati finali Customer dettaglio/booking e QR: 8 test e 55 asserzioni verdi.
- Pint, PHPStan, ESLint, build frontend, Flutter analyze/test e `git diff --check` verdi.
- Migrazione onboarding fiscale eseguita correttamente nell'ambiente Docker locale.
- Browser QA finale auth, logout e onboarding confermato dall'utente.
- Browser QA della compilazione automatica Comune/Provincia tramite CAP confermato dall'utente.
- Browser QA finale v1.1 confermato, incluso annullamento prenotazione Customer dopo il riavvio del frontend Vite.
- Browser QA finale v1.2 confermato dal Product Owner su dashboard, ricerca, dettagli, transizioni e accordi Admin.
- Browser QA finale v1.3 confermato dal Product Owner su web Customer/Organizer/Admin e mobile Customer/Organizer beta.
- Browser QA del telefono obbligatorio nella prenotazione Customer confermato dal Product Owner.
- Browser QA finale Customer Booking Experience confermato: singola PATCH, payload completo, aggiornamento UI e disponibilità, QR e booking code invariati.
- Regression gate Customer Booking Experience: test frontend 4/4, test backend mirati 3/3 con 71 asserzioni; suite Laravel 99 test e 715 asserzioni, Pint, PHPStan, ESLint, build e `git diff --check` verdi.
- OpenAPI Contract Cleanup: 111/111 route documentate, operationId univoci e lint Redocly passato con 0 errori e 0 warning.
- Production Readiness: health applicativo/DB/Redis, Docker production, queue e scheduler, upload hardening, SMTP, logging, CORS/proxy, pagine legali placeholder e procedure deploy/backup/restore verificati.
- Gate v1.4: suite Laravel completata con unica regressione di aspettativa corretta e test mirato finale 4/4; Pint, PHPStan, ESLint, build frontend, Redocly 0/0 e git diff --check verdi.
- Browser QA v1.4 confermato dal Product Owner su Customer, Organizer, Admin e responsive.
- Staging Readiness v1.5: build Docker backend/frontend, Compose staging/production, seeder beta, script deploy/smoke, template TLS e runbook verificati.
- Gate v1.5: 103 test e 725 asserzioni, Pint, PHPStan, ESLint, build frontend, Redocly 0/0, Compose staging/production e git diff --check verdi.

## Prossimo task

Deploy controllato della Release Candidate nel Prompt 8/8.

## Debiti aperti

- La copertura frontend automatizzata resta limitata al flusso critico di modifica prenotazione; ampliare progressivamente agli altri componenti.
- Redesign grafico generale non incluso.
- Deploy remoto, hosting, dominio/DNS, TLS, credenziali PostgreSQL/Redis, SMTP reale e testi legali definitivi sono posticipati alla fase finale dopo il completamento locale del prodotto.

## Come riprendere il lavoro

1. Aprire la root del repository.
2. Leggere integralmente i documenti ufficiali.
3. Verificare branch, working tree e cronologia recente.
4. Analizzare il contratto backend delle prenotazioni Organizer prima di intervenire sul frontend.
5. Aggiornare questo documento al termine della sessione.
## Flutter mobile multi-ruolo

- Customer: discovery eventi, booking, QR, modifica/annullamento e profilo via API.
- Organizer: dashboard, eventi, prenotazioni, partecipanti, check-in e profilo via API.
- Admin: dashboard, ricerca, gestione Organizer/Customer/Eventi e piani commerciali via API.
- Navigazione, role guard e design system Material 3 dedicati per ruolo.

## MòVi 2.0 - Activity e Reservation Capacity Mode

- Activity separata da Organizer e Venue, inizialmente operativa per Restaurant.
- Service generici, regole settimanali, eccezioni e chiusure straordinarie.
- Reservation separata da Event Booking, con transazioni PostgreSQL, lock, idempotenza e capacità derivata.
- API, web e Flutter usano disponibilità reale; follow, review verificata, notifiche operative e analytics sono integrati.

## MòVi Manager Reservation avanzato

- Dashboard, lista filtrabile, dettaglio e transizioni controllate `completed`/`no_show` per Organizer.
- Capacità e chiusure rapide proteggono le Reservation confermate.
- Clienti privacy-safe, reminder deduplicati, review verificata e KPI reali; Table Mode resta fuori scope.

## Discovery & Recommendation Foundation

- Feed normalizzato Event/Activity con filtri temporali, geografici, categoria, tipo, family e disponibilità reale.
- Interest Profile trasparente basato su preferenze e follow; scoring configurabile con motivazioni verificabili.
- Web e Flutter consumano ranking backend; nessun LLM, chatbot o dato simulato.

## MòVi Plan deterministico

- Composizione on-demand di massimo tre proposte e tre step usando Discovery, disponibilità, orari, distanza e prezzi reali.
- Budget verificato solo sui costi conosciuti; valori mancanti restano esplicitamente parziali.
- Revalidation per-step prima del booking supportata senza blocco capacità o transazioni multi-booking.

## MòVi AI

- Layer di interpretazione degli intenti strutturati sopra Planning, Discovery e Availability; non genera entità, prezzi o disponibilità.
- Provider configurabile e fallback deterministico; query complete e PII non sono registrate negli analytics.
- Web e Flutter espongono “Chiedi a MòVi” con contesto precedente leggero e Plan card reali.
