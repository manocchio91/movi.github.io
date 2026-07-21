# Stato del progetto APP EVENTI

> Aggiornare questo documento ogni volta che cambia lo stato reale del repository.

## Riferimenti correnti

- Versione corrente: `v0.7 - Registrazione e autenticazione reale`
- Ultimo aggiornamento: 21 luglio 2026
- Repository: `manocchio91/app-eventi`
- Branch corrente: `feat/real-registration-auth`
- Commit milestone: da aggiornare dopo il commit `v0.7 - Complete real registration and authentication`
- Ultimo tag: `v0.5-customer-booking-qr`, verificato localmente sul commit `c5e9605`

## Stato delle aree

- Backend: API Laravel sotto `/api/v1`, autenticazione Sanctum, autorizzazioni per ruolo e contratto uniforme degli errori.
- Autenticazione web: registrazione Customer e Organizer, login/logout reali e ripristino sessione tramite `GET /api/v1/me`.
- Registrazione Organizer: `User` e `OrganizerProfile` sono creati atomicamente; il profilo nasce `pending`.
- Dashboard e lista eventi Organizer web: collegate alle API reali con filtri e paginazione server-side.
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

## Task corrente

Milestone registrazione e autenticazione reale completata e verificata.

## Task completati nella sessione

- Completata la registrazione Customer con ruolo corretto, token Sanctum e accesso immediato all'area Customer.
- Completata la registrazione Organizer con creazione atomica del profilo e stato iniziale `pending`.
- Aggiunti conferma password, unicità email e campi Organizer obbligatori validati server-side.
- Stabilizzati i payload di registrazione, login e sessione con ruolo, stato account, profilo Organizer e `redirect_to`.
- Reso reale il logout con revoca del token corrente, incluso il caso Sanctum stateful.
- Persistita la sessione web in `localStorage` e verificata all'avvio tramite `GET /api/v1/me`.
- Implementate tre modalità auth, errori per campo, loader indipendente e blocco del doppio invio.
- Rimossi i fallback che aprivano aree applicative dopo errori di login o rete; i pulsanti demo compilano soltanto il form.
- Aggiunta la schermata “Account in attesa di approvazione” senza chiamate alle route `organizer.approved`.
- Aggiornati test feature e OpenAPI.
- Suite Laravel: 60 test e 339 asserzioni verdi; Pint e PHPStan verdi.
- Frontend: lint e build verdi; OpenAPI YAML 3.1 valido.
- Browser QA manuale completato dall'utente su tutti i 19 punti, inclusi refresh sessione, errori auth, backend spento e viewport desktop/mobile.

## Prossimo task

**CREAZIONE E MODIFICA EVENTO ORGANIZER**, verificando prima il contratto backend esistente.

## Debiti aperti

- Dettaglio, creazione e modifica evento Organizer.
- Prenotazioni ricevute, partecipanti e check-in.
- Statistiche avanzate.
- Organizer Flutter ancora basato su dati demo.
- Test frontend automatizzati.
- Redesign grafico generale della dashboard.

## Come riprendere il lavoro

1. Aprire la root del repository.
2. Leggere integralmente i documenti ufficiali.
3. Verificare branch, working tree e cronologia recente.
4. Analizzare il contratto backend di creazione e modifica evento Organizer prima di intervenire sul frontend.
5. Aggiornare questo documento al termine della sessione.
