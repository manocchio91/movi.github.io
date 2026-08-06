# Stato del progetto APP EVENTI

> Aggiornare questo documento ogni volta che cambia lo stato reale del repository.

## Riferimenti correnti

- Versione corrente: `v1.3 - Hardened MVP for Private Beta`
- Ultimo aggiornamento: 6 agosto 2026
- Repository: `manocchio91/app-eventi`
- Branch corrente: `feat/mvp-beta-hardening`
- Commit milestone: `v1.3 - Harden MVP and prepare private beta`
- Ultimo tag: `v0.5-customer-booking-qr`, verificato localmente sul commit `c5e9605`

## Stato delle aree

- Backend: API Laravel sotto `/api/v1`, autenticazione Sanctum, autorizzazioni per ruolo e contratto uniforme degli errori.
- Autenticazione web: registrazione Customer e Organizer, login/logout reali e ripristino sessione tramite `GET /api/v1/me`.
- Dashboard, lista, creazione e modifica eventi Organizer web: collegate esclusivamente alle API reali.
- Onboarding fiscale Organizer e approvazione Admin: collegati alle API reali, con accesso operativo vincolato allo stato approvato.
- Onboarding fiscale Organizer: Comune e Provincia vengono derivati dall'anagrafica backend al completamento di un CAP italiano valido, con gestione dei CAP associati a più Comuni.
- Customer Flutter: nella beta usa esclusivamente flussi reali per autenticazione, eventi, booking, QR e annullamento.
- Customer web: profilo e password, cronologia prenotazioni, QR recuperabile, annullamento, preferiti e recensioni collegati alle API reali.
- Organizer web: profilo pubblico/privato, logo e copertina, dati fiscali riservati e pagina pubblica con storico eventi.
- Admin web: dashboard KPI reale, ricerca globale, gestione completa di Organizer, Customer ed eventi, catalogo piani e accordi commerciali senza pagamenti automatici.
- Email operative: approvazione/revisione Organizer e conferma/annullamento booking tramite mailer configurabile e fake nei test.
- Organizer nella beta: disponibile esclusivamente sul web; il mobile mostra un avviso esplicito e consente il logout.

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

## Task corrente

Customer Booking Experience completata end-to-end e approvata nel Browser QA dal Product Owner.

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
- Customer mobile limitato ai flussi realmente collegati; area Organizer dichiarata web-only per la beta.

## Verifiche

- Test mirati auth finali: 8 test e 84 asserzioni verdi.
- Test mirati auth e onboarding: 14 test e 135 asserzioni verdi.
- Suite Laravel completa: 97 test e 653 asserzioni verdi.
- Test mirati finali Customer dettaglio/booking e QR: 8 test e 55 asserzioni verdi.
- Pint, PHPStan, ESLint, build frontend e `git diff --check` verdi; Flutter SDK non disponibile nell'ambiente Codex.
- Migrazione onboarding fiscale eseguita correttamente nell'ambiente Docker locale.
- Browser QA finale auth, logout e onboarding confermato dall'utente.
- Browser QA della compilazione automatica Comune/Provincia tramite CAP confermato dall'utente.
- Browser QA finale v1.1 confermato, incluso annullamento prenotazione Customer dopo il riavvio del frontend Vite.
- Browser QA finale v1.2 confermato dal Product Owner su dashboard, ricerca, dettagli, transizioni e accordi Admin.
- Browser QA finale v1.3 confermato dal Product Owner su web Customer/Organizer/Admin e mobile Customer/Organizer beta.
- Browser QA del telefono obbligatorio nella prenotazione Customer confermato dal Product Owner.
- Browser QA finale Customer Booking Experience confermato: singola PATCH, payload completo, aggiornamento UI e disponibilità, QR e booking code invariati.
- Regression gate Customer Booking Experience: test frontend 4/4, test backend mirati 3/3 con 71 asserzioni; suite Laravel 99 test e 715 asserzioni, Pint, PHPStan, ESLint, build e `git diff --check` verdi.

## Prossimo task

Preparazione e gestione operativa della beta privata.

## Debiti aperti

- Il lint Redocly rileva 49 errori strutturali preesistenti e 176 warning; i contratti modificati sono coerenti e non è stata introdotta alcuna regressione. La bonifica globale resta un task dedicato.
- Flutter Organizer reale resta debito della fase successiva; nella beta le funzioni Organizer sono esclusivamente web.
- La copertura frontend automatizzata resta limitata al flusso critico di modifica prenotazione; ampliare progressivamente agli altri componenti.
- Upload immagini evento non incluso.
- Redesign grafico generale non incluso.

## Come riprendere il lavoro

1. Aprire la root del repository.
2. Leggere integralmente i documenti ufficiali.
3. Verificare branch, working tree e cronologia recente.
4. Analizzare il contratto backend delle prenotazioni Organizer prima di intervenire sul frontend.
5. Aggiornare questo documento al termine della sessione.
