# Decisioni del progetto APP EVENTI

## ADR-001

### Backend prima del frontend

Stato: Accepted

Contesto: le integrazioni frontend richiedono contratti API verificati.

Decisione: stabilizzare e testare il backend prima di collegare una UI ai dati reali.

Conseguenze: il frontend implementa contratti noti e riduce fallback, duplicazioni e correzioni tardive.

## ADR-002

### Nessun dato simulato nei flussi reali

Stato: Accepted

Contesto: dati demo presentati come reali rendono il collaudo inaffidabile.

Decisione: nei flussi dichiarati reali mostrare esclusivamente dati provenienti dalle API reali.

Conseguenze: loading, empty state ed errori devono essere espliciti; i mock restano confinati agli ambienti che li dichiarano.

## ADR-003

### KPI Organizer centralizzati in OrganizerDashboardMetrics

Stato: Accepted

Contesto: definizioni duplicate dei KPI possono produrre risultati incoerenti.

Decisione: centralizzare i calcoli della dashboard Organizer in `OrganizerDashboardMetrics`.

Conseguenze: controller, contratto OpenAPI e test condividono la stessa semantica dei KPI.

## ADR-004

### Dashboard Organizer indipendente dalle altre API Organizer

Stato: Accepted

Contesto: il caricamento aggregato rendeva la dashboard dipendente da eventi, recensioni, calendario e opportunità.

Decisione: caricare la dashboard esclusivamente tramite `GET /api/v1/organizer/dashboard`, con stato e retry dedicati.

Conseguenze: il fallimento di altre API Organizer non impedisce la visualizzazione dei KPI.

## ADR-005

### Booking valide: confirmed, checked_in, completed

Stato: Accepted

Contesto: i KPI devono escludere prenotazioni cancellate o non valide.

Decisione: considerare valide esclusivamente le booking con stato `confirmed`, `checked_in` o `completed`.

Conseguenze: prenotazioni ricevute e partecipanti usano una definizione uniforme nel backend.

## ADR-006

### Tag Git solo al completamento di una milestone

Stato: Accepted

Contesto: un tag rappresenta uno stato applicativo verificato e stabile.

Decisione: creare tag soltanto dopo il completamento e la validazione dell'intera milestone.

Conseguenze: i task intermedi restano identificati dai commit e dai branch di lavoro.

## ADR-007

### Browser QA manuale quando il browser integrato non è disponibile

Stato: Accepted

Contesto: il browser integrato può non esporre una sessione utilizzabile durante il collaudo.

Decisione: in tale caso il Browser QA può essere eseguito manualmente dall'utente e registrato esplicitamente nel report di milestone.

Conseguenze: il collaudo non viene dichiarato automatico e resta distinta la responsabilità della verifica manuale.

## ADR-008

### Filtri e paginazione delle liste reali sono server-side

Stato: Accepted

Contesto: filtrare o paginare nel browser una sola pagina produce elenchi incompleti e classificazioni incoerenti.

Decisione: applicare filtri, ordinamento e paginazione nell'endpoint backend; il frontend invia i parametri correnti e visualizza i metadati restituiti.

Conseguenze: ogni cambio filtro genera una nuova richiesta, torna alla pagina 1 e non riutilizza dati appartenenti al filtro precedente.

## ADR-009

### Sessione web persistita e verificata dal backend

Stato: Accepted

Contesto: conservare il token soltanto in memoria interrompe la sessione a ogni refresh e non consente di verificarne validità e ruolo.

Decisione: conservare il token Sanctum in `localStorage` e ripristinare la sessione esclusivamente dopo una risposta valida di `GET /api/v1/me`.

Conseguenze: token invalidi o revocati vengono rimossi e l'utente torna al login con un messaggio leggibile.

## ADR-010

### Nessun accesso demo dopo un fallimento auth

Stato: Accepted

Contesto: i fallback demo dopo errori di rete o credenziali non valide simulavano un accesso riuscito e rendevano inaffidabile il collaudo.

Decisione: nessun errore di login, validazione o rete può impostare un utente autenticato o aprire una dashboard; i dati demo possono soltanto precompilare il form.

Conseguenze: tutte le sessioni web dichiarate reali dipendono da un token emesso e verificato dal backend.

## ADR-011

### Approvazione Organizer prima delle funzioni operative

Stato: Accepted

Contesto: la registrazione Organizer deve creare un profilo reale senza aggirare il processo di verifica esistente.

Decisione: creare `User` e `OrganizerProfile` in una sola transazione, inizializzare il profilo come `incomplete` e indirizzare l'utente all'onboarding fiscale. Soltanto l'invio completo porta il profilo a `pending`; `changes_requested` riapre l'onboarding, mentre `verified`, `rejected` e `suspended` hanno destinazioni dedicate.

Conseguenze: gli Organizer non verificati possono autenticarsi e fare logout, ma non inviano richieste alle route protette da `organizer.approved`; ogni redirect deriva dallo stato reale del profilo autenticato.

## ADR-012

### Ciclo di vita esplicito degli eventi e ticket immutabili nelle vendite

Stato: Accepted

Contesto: creare eventi già pubblicati e ricreare i ticket durante ogni modifica rendeva incoerente la pubblicazione e poteva perdere identità e quantità prenotate.

Decisione: ogni evento viene creato come `draft` e può diventare `published` soltanto tramite una transizione backend esplicita. I ticket esistenti vengono sincronizzati tramite ULID in transazione e con lock, preservando `booked_quantity`; un ticket prenotato non può essere rimosso e la sua quantità non può scendere sotto quella già prenotata.

Conseguenze: creazione, modifica e pubblicazione restano operazioni distinte; le vendite già registrate non vengono alterate dai successivi aggiornamenti dell'evento.

## ADR-013

### Riservatezza dei dati fiscali Organizer

Stato: Accepted

Contesto: Partita IVA, PEC, Codice SDI e indirizzo di fatturazione identificano fiscalmente l'Organizer e non appartengono al profilo pubblico.

Decisione: i dati fiscali dell'Organizer sono riservati e possono essere visualizzati esclusivamente dall'Organizer proprietario e dagli Admin autorizzati. Non devono essere esposti nelle schermate o API pubbliche.

Conseguenze: endpoint, serializer e interfacce pubbliche devono escludere questi campi; ogni nuova esposizione richiede autorizzazione esplicita e test dedicati.

## ADR-015

### KPI economici Admin teorici e deterministici

Stato: Accepted

Contesto: il backoffice deve stimare il valore commerciale senza introdurre pagamenti online o fatturazione automatica.

Decisione: calcolare i ricavi teorici sugli accordi `active`: canone `UNLIMITED` e partecipanti delle prenotazioni `confirmed`, `checked_in` o `completed` moltiplicati per la tariffa personalizzata `PAY_PER_BOOKING`. Gli importi restano in centesimi nel backend e sono mostrati in euro nel frontend.

Conseguenze: i KPI sono riproducibili e testabili, non rappresentano incassi o fatture e condividono la definizione canonica di prenotazione valida.

## ADR-016

### Sospensione amministrativa non distruttiva degli eventi

Stato: Accepted

Contesto: sospendere un evento per moderazione non deve cancellare o riscrivere prenotazioni e partecipanti già acquisiti.

Decisione: la sospensione Admin modifica esclusivamente lo stato dell'evento; la riattivazione è una transizione backend esplicita e protetta. Cancellazione e sospensione amministrativa restano operazioni distinte.

Conseguenze: prenotazioni, partecipanti e check-in restano integri durante la moderazione e le transizioni non sono alterabili dal client.

## ADR-017

### Organizer web-only nella beta privata

Stato: Accepted

Contesto: l'area Organizer Flutter era interamente simulata, mentre il backoffice web è completo e collegato alle API reali.

Decisione: nella beta privata i Customer mobile usano esclusivamente flussi reali; le funzioni Organizer sono disponibili soltanto sul web. Route, schermate e repository mobile basati su `InMemoryDemoRepository` vengono rimossi e il mobile comunica esplicitamente il limite.

Conseguenze: nessuna funzionalità simulata è presentata come operativa; l'implementazione Flutter Organizer reale resta una fase successiva.

## ADR-014

### APP_KEY stabile e recupero controllato dei QR

Stato: Accepted

Contesto: rigenerare `APP_KEY` a ogni avvio locale rende indecifrabili i token QR già persistiti, pur lasciando valido soltanto il relativo hash one-way.

Decisione: generare `APP_KEY` esclusivamente quando manca. Se un QR persistito non è più decifrabile, rigenerare sotto lock un token imprevedibile aggiornando atomicamente ciphertext e hash, senza modificare la prenotazione o creare un secondo flusso di check-in.

Conseguenze: i riavvii non invalidano più i dati cifrati; i record già danneggiati vengono recuperati alla prima richiesta autorizzata e il frontend non espone errori crittografici.

## ADR-018

### Deploy remoto dopo il completamento locale del prodotto

Stato: Accepted

Contesto: la staging readiness tecnica è completa, ma acquistare e configurare ora hosting, dominio, DNS, TLS e SMTP introdurrebbe costi e attività operative prima della conclusione funzionale e UX/UI del prodotto.

Decisione: conservare versionata e verificata tutta la preparazione staging, posticipando il deploy remoto e l’acquisto/configurazione dei servizi esterni alla fase finale. La prossima macrofase si svolge integralmente in locale ed è `Final Product Completion & UX/UI Polish`.

Conseguenze: script, env example, container, seed beta, smoke test e runbook restano il percorso ufficiale di deploy; nessun ambiente remoto viene dichiarato attivo finché non saranno forniti e verificati i dati esterni reali.

## ADR-019

### Follow privacy-safe e notifiche follower asincrone

Stato: Accepted

Decisione: le API Follow usano Resource dedicate e non serializzano direttamente `User`. Le preferenze per eventi degli Organizer seguiti sono separate dalle notifiche operative e dal marketing per categorie. La pubblicazione evento accoda il calcolo dei destinatari dopo il commit e conserva la deduplicazione per evento, utente e canale.

Conseguenze: follow/unfollow sono idempotenti, i client leggono `is_following`, e nessuna risposta follower espone email o identificativi interni.

## ADR-020

### Waitlist FIFO con capacità riservata durante l'offerta

Stato: Accepted

Decisione: la promozione avviene in transazione con lock di riga. Una sola offerta può essere attiva per evento; i posti sono sottratti dalla disponibilità quando nasce l'offerta e restituiti in caso di rinuncia o scadenza. L'accettazione usa una chiave idempotente derivata dalla voce di waitlist.

Conseguenze: capacità già offerta non può essere acquistata in concorrenza, retry e job duplicati non creano più prenotazioni, e il candidato successivo compatibile viene promosso automaticamente in ordine FIFO.
