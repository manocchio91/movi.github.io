# Stato del progetto APP EVENTI

> Aggiornare questo documento ogni volta che cambia lo stato reale del repository.

## Riferimenti correnti

- Versione corrente: `v1.0-A - Organizer Bookings Management`
- Ultimo aggiornamento: 4 agosto 2026
- Repository: `manocchio91/app-eventi`
- Branch corrente: `feat/organizer-bookings-management`
- Commit milestone: da aggiornare dopo il commit `v1.0-A - Complete Organizer bookings management`
- Ultimo tag: `v0.5-customer-booking-qr`, verificato localmente sul commit `c5e9605`

## Stato delle aree

- Backend: API Laravel sotto `/api/v1`, autenticazione Sanctum, autorizzazioni per ruolo e contratto uniforme degli errori.
- Autenticazione web: registrazione Customer e Organizer, login/logout reali e ripristino sessione tramite `GET /api/v1/me`.
- Dashboard, lista, creazione e modifica eventi Organizer web: collegate esclusivamente alle API reali.
- Onboarding fiscale Organizer e approvazione Admin: collegati alle API reali, con accesso operativo vincolato allo stato approvato.
- Onboarding fiscale Organizer: Comune e Provincia vengono derivati dall'anagrafica backend al completamento di un CAP italiano valido, con gestione dei CAP associati a più Comuni.
- Customer Flutter: integrazione reale completata per autenticazione, eventi, booking, QR e annullamento.
- Organizer Flutter: schermate presenti, ma dati ancora forniti da `InMemoryDemoRepository`.

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

## Task corrente

Milestone v1.0-A Organizer Bookings Management completata e verificata.

## Funzionalità completate

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

## Verifiche

- Test mirati auth finali: 8 test e 84 asserzioni verdi.
- Test mirati auth e onboarding: 14 test e 135 asserzioni verdi.
- Suite Laravel completa: 84 test e 541 asserzioni verdi.
- Test mirati finali Customer dettaglio/booking e QR: 8 test e 55 asserzioni verdi.
- Pint, PHPStan, OpenAPI YAML 3.1, ESLint e build frontend verdi.
- Migrazione onboarding fiscale eseguita correttamente nell'ambiente Docker locale.
- Browser QA finale auth, logout e onboarding confermato dall'utente.
- Browser QA della compilazione automatica Comune/Provincia tramite CAP confermato dall'utente.
- Browser QA finale Prenotazioni Organizer e flusso Customer dettaglio/booking confermato dall'utente.

## Prossimo task

Nessuna nuova milestone avviata.

## Debiti aperti

- Check-in web Organizer.
- Statistiche avanzate.
- Organizer Flutter ancora basato su dati demo.
- Test frontend automatizzati.
- Upload immagini evento non incluso.
- Redesign grafico generale non incluso.

## Come riprendere il lavoro

1. Aprire la root del repository.
2. Leggere integralmente i documenti ufficiali.
3. Verificare branch, working tree e cronologia recente.
4. Analizzare il contratto backend delle prenotazioni Organizer prima di intervenire sul frontend.
5. Aggiornare questo documento al termine della sessione.
