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
