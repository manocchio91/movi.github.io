# Stato del progetto APP EVENTI

> Aggiornare questo documento ogni volta che cambia lo stato reale del repository.

## Riferimenti correnti

- Versione corrente: `v0.8 - Organizer Event Creation and Editing`
- Ultimo aggiornamento: 21 luglio 2026
- Repository: `manocchio91/app-eventi`
- Branch corrente: `feat/organizer-event-create-edit`
- Commit milestone: da aggiornare dopo il commit `v0.8 - Complete Organizer event creation and editing`
- Ultimo tag: `v0.5-customer-booking-qr`, verificato localmente sul commit `c5e9605`

## Stato delle aree

- Backend: API Laravel sotto `/api/v1`, autenticazione Sanctum, autorizzazioni per ruolo e contratto uniforme degli errori.
- Autenticazione web: registrazione Customer e Organizer, login/logout reali e ripristino sessione tramite `GET /api/v1/me`.
- Dashboard, lista, creazione e modifica eventi Organizer web: collegate esclusivamente alle API reali.
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

## Task corrente

Milestone Organizer Event Creation and Editing completata e verificata.

## Funzionalità completate

- Creazione evento reale assegnata automaticamente all'Organizer autenticato e approvato.
- Salvataggio iniziale sempre in stato `draft`.
- Pubblicazione tramite transizione backend esplicita `POST /organizer/events/{event}/publish`.
- Validazione server-side della completezza e dello stato prima della pubblicazione.
- Modifica reale dei soli eventi propri e negli stati ammessi dal dominio.
- Categorie e comuni caricati dalle API reali.
- Form web riutilizzabile per creazione e modifica, con date locali controllate, prezzi in euro e invio in centesimi.
- Creazione, modifica e rimozione controllata dei ticket type.
- ULID pubblico aggiunto ai ticket esistenti e nuovi; l'identità dei ticket viene mantenuta durante l'update.
- Sincronizzazione ticket atomica con lock di riga.
- `booked_quantity` non è accettato come input modificabile ed è sempre preservato.
- La quantità di un ticket non può scendere sotto `booked_quantity`.
- I ticket prenotati non possono essere rimossi; quelli senza prenotazioni possono essere rimossi.
- La capienza generale non può scendere sotto i posti già prenotati e la disponibilità viene ricalcolata senza perdere vendite.
- Customer, Organizer pending e Organizer non proprietari sono bloccati dalle autorizzazioni backend.
- Errori `401`, `403`, `404` e `422` gestiti senza perdita dei dati inseriti.

## Verifiche

- Test mirati eventi: 9 test e 57 asserzioni verdi.
- Suite Laravel completa: 69 test e 396 asserzioni verdi.
- Pint, PHPStan, OpenAPI YAML 3.1, ESLint e build frontend verdi.
- Migrazione ULID ticket eseguita correttamente nell'ambiente Docker locale.
- Browser QA manuale completato dall'utente su tutti i 30 punti, inclusi ticket prenotati, autorizzazioni e viewport desktop/mobile.

## Prossimo task

**GESTIONE PRENOTAZIONI E PARTECIPANTI ORGANIZER**

## Debiti aperti

- Dettaglio prenotazioni ricevute e gestione partecipanti Organizer.
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
