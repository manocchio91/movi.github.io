# Roadmap APP EVENTI

> Aggiornare questa roadmap quando cambia lo stato reale del repository. Non segnare una milestone come completata senza verifiche tecniche e collaudo.

## Visione del prodotto

APP EVENTI è una piattaforma territoriale per scoprire, pubblicare, prenotare e gestire eventi ed esperienze locali, con esperienze separate per Customer, Organizer e Admin. Il prodotto mantiene un backend API unico e client web/mobile separati nel medesimo monorepo.

## Milestone

| Versione | Stato | Obiettivo |
| --- | --- | --- |
| v0.1 | Completata | Backend stabile e prima vertical slice operativa |
| v0.2 | Completata | Analisi statica PHPStan pulita |
| v0.3 | Completata | Autenticazione mobile collegata al backend reale |
| v0.4 | Completata | Customer Home, categorie ed eventi collegati alle API reali |
| v0.5 | Completata | Booking Customer reale, idempotenza, QR, lista/dettaglio e annullamento |
| v0.6 | In corso | Dashboard e lista eventi Organizer con dati reali |
| v0.7 | Pianificata, contenuto da definire | Evoluzione operativa Organizer |
| v0.8 | Pianificata, contenuto da definire | Analytics, recensioni e strumenti di supporto Organizer |
| v0.9 | Pianificata, contenuto da definire | Consolidamento Opportunity Engine e preparazione MVP |
| v1.0 | Pianificata | MVP validato e pronto al rilascio secondo criteri da definire |

## v0.6 — Dashboard Organizer

Ordine iniziale previsto:

1. Analisi dello stato reale dell'area Organizer. Completata.
2. Collegamento della dashboard ai dati reali. Completato.
3. KPI principali. Completati.
4. Lista degli eventi dell'organizzatore. Completata.
5. Distinzione tra eventi futuri, conclusi e non pubblicati. Completata.
6. Creazione e modifica evento. Prossimo blocco.
7. Gestione partecipanti.
8. Check-in.
9. Statistiche.

Il backend espone già API Organizer, ma l'app Flutter usa ancora dati demo: ogni voce deve essere verificata end-to-end prima di essere considerata completata.

## Attività future

### Organizer

- Sostituire progressivamente `InMemoryDemoRepository` con repository API reali.
- Validare dashboard, profilo, eventi, partecipanti e check-in.
- Allineare autorizzazioni, stati evento e gestione degli errori con il backend.

### Analytics e statistiche

- Verificare e integrare gli endpoint Organizer già presenti.
- Definire KPI comprensibili e coerenti con i dati reali.
- Coprire i calcoli con test backend e test Flutter.

### Recensioni

- Verificare i flussi Customer, Organizer e moderazione già esposti dalle API.
- Collegare le UI solo dopo aver confermato contratti, autorizzazioni e stati.

### Opportunity Engine

- Consolidare il motore deterministico presente nel backend.
- Validare suggerimenti, sovrapposizioni e utilizzo delle opportunità nel flusso Organizer.
- Non introdurre dipendenze AI esterne senza un requisito approvato.

### Preparazione MVP

- Rimuovere o isolare i dati demo dai flussi dichiarati reali.
- Allineare README, OpenAPI e documentazione allo stato effettivo.
- Eseguire test automatici, collaudo browser e verifica dei dati QA.
- Definire criteri di rilascio, sicurezza, osservabilità e gestione operativa.
