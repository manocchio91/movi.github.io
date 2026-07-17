# Mobile Current State Report

## Ambiente Flutter

- SDK usato: `C:\src\flutter\bin\flutter.bat`.
- Flutter 3.44.6 stable, Dart 3.12.2, DevTools 2.57.0.
- `flutter analyze`: nessun problema.
- `flutter test`: 3 test superati; verificano login demo, shell customer e shell organizer.
- Web abilitato.
- Dispositivi rilevati: Chrome 150, Edge 150 e Windows desktop.
- Android SDK non rilevato; nessun emulatore o dispositivo Android rilevato.
- Visual Studio/toolchain C++ non installata, quindi Windows desktop non è compilabile nonostante il device sia elencato.
- Flutter e Dart non sono nel PATH; è stato usato il percorso assoluto senza modificare il PATH.
- Nove pacchetti hanno versioni più recenti incompatibili con i vincoli correnti. Non sono stati aggiornati.
- Docker backend, PostgreSQL, Redis, Nginx e frontend Vite risultano attivi; `app`, PostgreSQL sono healthy.
- La porta richiesta `8081` è occupata dal container esterno `appvino-api-preview` (`127.0.0.1:8081->80`) e restituisce 404.
- Flutter Web è stato compilato e serve HTTP 200 su `http://localhost:8082`; titolo HTML: `eventi_locali_mobile`.
- Il browser di QA integrato non espone browser disponibili. La verifica visuale/interattiva oltre i widget test non è quindi dichiarata come eseguita.

## Architettura

- Entry point: `lib/main.dart`, con `ProviderScope` e `MaterialApp.router`.
- Stato: Riverpod 2 (`NotifierProvider` per autenticazione, `Provider` per repository demo).
- Routing: `go_router`, due `StatefulShellRoute.indexedStack` separati per customer e organizer.
- Autenticazione: `AuthController` locale con due account demo e password hardcoded di preview.
- Persistenza sessione: assente; refresh o riavvio azzera lo stato.
- Storage token: assente.
- Client HTTP/Dio: assente.
- Base URL API: assente; non sono configurati né `http://localhost:8080` né `10.0.2.2`.
- Repository: interfaccia `DemoRepository` con implementazione `InMemoryDemoRepository`.
- Error handling: limitato al messaggio locale per credenziali demo errate; nessuna gestione di errori HTTP.
- Admin mobile: non presente.

## Schermate disponibili

| Schermata | Route | Stato verificato | API reale | Mock | Note |
|---|---|---|---|---|---|
| Login | `/login` | Funzionante nei widget test | No | Sì | Login customer/organizer completamente locale. |
| Home customer | `/customer` | Funzionante nei widget test | No | Sì | Apre la shell customer e usa `DemoRepository`. |
| Tutti gli Eventi | `/customer/all-events` | Compilata, non verificata interattivamente | No | Sì | Ricerca/filtri UI; vista mappa solo predisposta. |
| Eventi di Successo | `/customer/success` | Compilata, non verificata interattivamente | No | Sì | Deriva gli eventi conclusi dagli eventi demo. |
| Dettaglio evento | `/customer/events/:id` | Compilata, non verificata interattivamente | No | Sì | Gallery e mappa sintetiche; lookup in memoria. |
| Prenotazione evento | `/customer/events/:id/book` | Compilata, non verificata interattivamente | No | Sì | Conferma e QR esclusivamente demo. |
| Preferenze | `/customer/preferences` | Compilata, incompleta | No | Sì | Switch e salvataggio non collegati; callback vuote. |
| Recensione | `/customer/review/:id` | Compilata, incompleta | No | Sì | Invio e upload non collegati. |
| Prenotazioni customer | `/customer/bookings` | Compilata, non verificata interattivamente | No | Sì | Liste in memoria; annullamento non collegato. |
| Profilo customer | `/customer/profile` | Compilata, non verificata interattivamente | No | Sì | Dati `DemoUser`; logout locale presente. |
| Dashboard organizer | `/organizer` | Funzionante nei widget test | No | Sì | Metriche, recensioni e suggerimenti demo. |
| I miei Eventi | `/organizer/events` | Compilata, non verificata interattivamente | No | Sì | Azioni dettaglio/modifica/annulla senza implementazione. |
| Crea Evento | `/organizer/create` | Compilata, incompleta | No | Sì | Wizard UI senza persistenza API. |
| Opportunità | `/organizer/opportunities` | Compilata, non verificata interattivamente | No | Sì | Idee e ispirazioni in memoria. |
| Profilo organizer | `/organizer/profile` | Compilata, non verificata interattivamente | No | Sì | Dati demo; logout locale presente. |
| Calendario intelligente | `/organizer/calendar` | Compilata, non verificata interattivamente | No | Sì | Concorrenza e bottom sheet fittizi. |
| Prenotazioni organizer | `/organizer/bookings` | Compilata, incompleta | No | Sì | Check-in QR solo predisposto/demo. |
| Recensioni organizer | `/organizer/reviews` | Compilata, non verificata interattivamente | No | Sì | Statistiche e recensioni locali. |

Funzioni richieste ma senza schermata dedicata:

- Splash: non implementata come route/schermata Flutter; resta il bootstrap standard della piattaforma.
- Registrazione: non implementata.
- Notifiche: non esiste una schermata dedicata; sono presenti solo preferenze statiche e contenuti impliciti.
- Preferiti: non esiste una schermata o un repository dedicato.
- Admin: non implementato nell'app mobile.

## API reali collegate

Nessuna. Nel progetto non risultano dipendenze HTTP, client, interceptor, DTO API, repository remoti o configurazioni di base URL. L'app non chiama il backend Laravel su `http://localhost:8080`.

## Mock presenti

- Autenticazione locale con `customer@demo.it` e `organizer@demo.it`.
- Password demo locale.
- Eventi, eventi di successo, organizzatori, prenotazioni, recensioni e opportunità.
- Metriche organizer, statistiche recensioni e calendari concorrenza.
- QR, gallery, mappa e contenuti territoriali sintetici.
- Preferenze e notifiche senza persistenza.
- Azioni organizer e upload immagini con callback vuote o UI predisposta.

Tutte le 18 schermate implementate dipendono totalmente o parzialmente dai dati demo. Non sono presenti fixture esterne: i dati sono dichiarati nel repository in-memory.

## Errori di compilazione

- Nessun errore o warning da `flutter analyze`.
- Nessun errore nei 3 widget test.
- Problema Android bloccante: Android SDK non rilevato.
- Problema Windows desktop bloccante: Visual Studio con workload C++ non installato.
- Nove dipendenze hanno aggiornamenti incompatibili con i vincoli correnti; non bloccano la build attuale.

## Errori runtime

- Flutter Web risponde HTTP 200 su porta alternativa `8082`.
- Avvio su `8081` bloccato da un container esterno già in ascolto.
- Nessun errore runtime rilevato dai widget test.
- Test visuale browser completo non eseguito perché il browser QA integrato non era disponibile; le route non coperte dai test restano “compilate, non verificate”.

## Problemi di autenticazione

- Nessuna autenticazione Laravel/Sanctum.
- Nessun token Bearer, cookie Sanctum o storage sicuro.
- Nessun refresh/revoca token.
- Sessione non persistente.
- Redirect per ruolo presente solo localmente in `GoRouter` e basato su `DemoUser`.
- Logout azzera il provider locale e torna a `/login`; compilato ma non coperto da test dedicato.

## Problemi di rete o CORS

- L'app non genera richieste API, quindi non è possibile osservare CORS, 401, 403, 404, 422 o 500 del backend.
- Nessun URL API è chiamato dal browser.
- Il backend resta raggiungibile su `http://localhost:8080`, ma non è consumato dall'app Flutter.
- La porta `8081` non può essere usata finché `appvino-api-preview` la pubblica.

## Funzioni incomplete

- Registrazione, notifiche dedicate e preferiti non implementati.
- Integrazione API completa assente.
- Persistenza autenticazione/token assente.
- Prenotazione, cancellazione, recensione, preferenze, QR check-in e creazione evento sono demo o callback locali.
- Upload immagini/storage assente.
- Copertura test limitata a login e apertura delle due shell.
- Alcune stringhe mostrano mojibake nel sorgente (`OpportunitÃ `, apostrofi codificati male), possibile difetto visivo da verificare nel browser.

## Aggiornamento: autenticazione completata

Questa sezione sostituisce lo stato dell'autenticazione demo descritto nelle sezioni precedenti del report.

- Il login Flutter è collegato a `POST /api/v1/auth/login` sul backend Laravel reale.
- Laravel Sanctum restituisce un Bearer token opaco, persistito localmente con `SharedPreferences`.
- La sessione viene ripristinata e validata tramite `GET /api/v1/me`.
- Il logout chiama `POST /api/v1/auth/logout`, revoca il token corrente e pulisce lo storage locale.
- Il ruolo `customer` apre la shell Customer.
- Il ruolo `organizer` apre la shell Organizer.
- Non sono presenti whitelist, password o account demo hardcoded nel flusso di autenticazione Flutter.
- Gli errori HTTP `401`, `403`, `422` e `500` sono presentati con messaggi leggibili; quando disponibile viene mostrato il messaggio restituito dall'API.
- `DevelopmentDemoUsersSeeder` predispone in modo idempotente, esclusivamente in ambiente locale, un Customer attivo e un Organizer attivo con profilo verificato.
- `flutter analyze`, i 3 widget test e `flutter build web` sono verdi.
- Eventi, Home Customer e Dashboard Organizer restano alimentati dai dati demo/in-memory e non rientrano nell'integrazione autenticazione.

### Prossima fase

Integrazione della Home Customer con le API Laravel reali, mantenendo separati autenticazione e dati delle altre feature.

## Piano consigliato

1. **Build e configurazione**: liberare o assegnare stabilmente la porta web, definire base URL per web/Android, verificare encoding e predisporre Android SDK senza cambiare UI.
2. **Autenticazione**: introdurre in una fase approvata client Sanctum, storage token/sessione, login/logout reali e redirect per ruolo, mantenendo separato il fallback demo finché necessario.
3. **Customer**: collegare progressivamente home, eventi, dettaglio, preferenze, prenotazioni, recensioni e profilo, una feature alla volta con stati loading/error.
4. **Organizer**: collegare dashboard, eventi, creazione, opportunità, calendario, prenotazioni/check-in, recensioni e profilo.
5. **Integrazione API**: repository remoti tipizzati, gestione uniforme 401/403/404/422/500, CORS web e mapping dei contratti Laravel.
6. **Test end-to-end**: ampliare widget test, test repository/API con fake controllati e scenari browser/Android completi per customer e organizer.

Il primo gruppo consigliato è **build e configurazione**: risolvere il conflitto della porta `8081`, definire una configurazione ambiente esplicita per `http://localhost:8080` su web e rendere ripetibile l'avvio prima di collegare autenticazione o feature reali.
