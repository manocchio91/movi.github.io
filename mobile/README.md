# MòVi Mobile

Prototipo Flutter Material 3 per smartphone Android e iOS, con due esperienze separate:

- Customer App
- Organizer App
- Admin App

Il backend e il pannello amministratore web non sono modificati. I dati sono mock realistici del Molise e sono isolati in `lib/features/demo/data`, pronti per essere sostituiti da repository API reali.

## Accesso

L’app usa esclusivamente autenticazione e sessioni reali delle API Laravel. Non contiene account demo o repository in-memory.

## Comandi

```bash
cd mobile
flutter pub get
flutter analyze
flutter test
flutter run
```

## Test automatici su device

I test in `integration_test/` usano le API Laravel reali e un database Docker
ripristinato con i seed di sviluppo. Su Windows, con un emulatore Android già
avviato:

```powershell
.\scripts\test-mobile-integration.ps1
```

Il comando usa lo stack Docker isolato `movi-e2e`, ricrea esclusivamente il suo
volume temporaneo e lo rimuove al termine. Il database locale principale non
viene modificato.

È possibile indicare il device: `-Device emulator-5554`.
Il ripristino riguarda esclusivamente il database del compose locale del
progetto. Il comando Flutter equivalente, a backend già predisposto, è:

```bash
cd mobile
flutter test integration_test -d emulator-5554 \
  --dart-define=API_BASE_URL=http://10.0.2.2:18081/api/v1
```

Su iOS la stessa suite è compatibile con Simulator usando
`-d <simulator-id>` e `API_BASE_URL=http://localhost:18081/api/v1`. Serve un
host macOS con Xcode/CocoaPods; il Simulator non richiede signing. Device reale,
TestFlight e Play Internal Testing richiederanno in seguito bundle/application
ID definitivi, team Apple/provisioning e account store, che non vengono creati
da questo progetto. La suite produce un APK testabile anche su Firebase Test
Lab senza dipenderne in CI.

Se la cartella viene aperta su una macchina dove Flutter non ha ancora generato le piattaforme native, eseguire una volta:

```bash
flutter create . --platforms=android,ios
```

## Struttura

```text
lib/
  core/
    auth/
    models/
    router/
    theme/
  features/
    auth/
    customer/
    demo/
    organizer/
    shared/
```

## Route Principali

- `/login`
- `/customer`
- `/customer/all-events`
- `/customer/success`
- `/customer/bookings`
- `/customer/profile`
- `/customer/events/:id`
- `/customer/events/:id/book`
- `/customer/preferences`
- `/customer/review/:id`
- `/organizer`
- `/organizer/events`
- `/organizer/create`
- `/organizer/opportunities`
- `/organizer/profile`
- `/organizer/calendar`
- `/organizer/bookings`
- `/organizer/reviews`

## Schermate Customer

- Home con ricerca, macroaree, notifiche, eventi consigliati, vicini, weekend, amati, organizzatori seguiti e preferenze
- Tutti gli Eventi con ricerca, filtri, chips, ordinamento e vista mappa predisposta
- Dettaglio evento con gallery placeholder, luogo, mappa sintetica, programma, incluso/non incluso, policy, recensioni e simili
- Flusso prenotazione con biglietti, adulti, bambini, note, esigenze, condizioni, conferma e QR demo
- Eventi di Successo
- Prenotazioni con tab prossime, concluse e annullate
- Recensione customer
- Preferenze e notifiche
- Profilo customer

## Schermate Organizer

- Dashboard mobile con metriche, grafico sintetico, eventi prossimi, recensioni e suggerimenti
- I miei Eventi con tab aperti, chiusi, annullati e bozze
- Crea Evento con wizard a sei passaggi
- Calendario intelligente con legenda e bottom sheet dei concorrenti pubblici
- Prenotazioni organizer con ricerca, filtri e check-in QR demo
- Recensioni per evento, non lista unica
- OpportunitÃ  con idee stagionali e ispirazioni aggregate
- Profilo attivitÃ 

## Mock Ancora Presenti

- Autenticazione demo locale
- Eventi, organizzatori, prenotazioni, recensioni, notifiche implicite e opportunitÃ 
- QR code demo e mappa sintetica
- Calendario concorrenza con dati aggregati fittizi
- Upload immagini e foto recensioni predisposti ma non collegati a storage/API
