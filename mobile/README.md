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
