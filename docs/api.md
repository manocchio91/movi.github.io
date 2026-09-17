# API

Base path: `/api/v1`

## Discovery e MòVi Plan

La tassonomia `categories` è unica: Event e Activity possono condividere una categoria, mentre `Activity.type` descrive che cosa è il locale. `category_id` e il contesto Famiglia includono entrambi i tipi soltanto con un'associazione persistita; nessun interesse viene dedotto dal nome o dal tipo.

Il ranking è deterministico: categoria/preferenze, follow e disponibilità alimentano lo score; a parità si ordinano tipo e ULID. Il Plan combina fino a tre risultati e tra tappe applica 30 minuti più 3 minuti per chilometro. La disponibilità proviene dai motori Event e Reservation. Il budget usa solo prezzi reali: se uno manca, il totale è `null` e la verifica è `partial`.

## Autenticazione
- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout`
- `GET /me`

`POST /auth/login` restituisce `user.role` e `redirect_to`:
- `customer` -> `/customer/dashboard`
- `organizer` -> `/organizer/dashboard`
- `admin` -> `/admin/dashboard`

## Pubblico
- `GET /categories`
- `GET /activities` (`category_id` usa la stessa tassonomia degli Eventi)
- `GET /events`
- `GET /events/{event}`
- `GET /events/{event}/reviews`
- `GET /locations/search?q=camp`
- `GET /subscription-plans`
- `GET /organizers/{organizer}`

Filtri eventi: `q` (ricerca case-insensitive in titolo e descrizione), `category_id`, `province_id`, `municipality_id`, `date`, `free`, `available`. I filtri sono combinabili e la risposta mantiene la paginazione standard Laravel.

`GET /locations/search` e `GET /locations/municipalities` espongono soltanto i comuni della regione configurata tramite `TERRITORY_REGION_CODE`. Il catalogo strutturale del territorio pilota contiene i 136 comuni molisani con codici ISTAT e province `CB`/`IS`; la stessa regola viene applicata server-side alla creazione e modifica evento.

## Cliente
- `GET /customer/dashboard`
- `GET /customer/successful-events`
- `GET /customer/successful-events/{event}`
- `GET /customer/successful-events/{event}/next-editions`
- `GET /customer/successful-events/{event}/similar`
- `POST /customer/successful-events/{event}/notify-similar`
- `DELETE /customer/successful-events/{event}/notify-similar`
- `GET /me/preferences`
- `PUT /me/preferences`
- `GET /me/favorites`
- `POST /me/favorites/{event}`
- `DELETE /me/favorites/{event}`
- `GET /me/notification-preferences`
- `PUT /me/notification-preferences`
- `POST /events/{event}/bookings`
- `POST /events/{event}/reviews`
- `POST /reviews/{review}/report`
- `GET /me/bookings`
- `GET /me/bookings/{booking}`
- `GET /me/bookings/{booking}/qr`
- `POST /me/bookings/{booking}/cancel`
- `GET /me/notifications`
- `POST /me/notifications/{notification}/read`
- `POST /me/notifications/read-all`
- `POST /organizers/{organizer}/follow`
- `DELETE /organizers/{organizer}/follow`
- `POST /customer/organizers/{organizer}/follow`
- `DELETE /customer/organizers/{organizer}/follow`
- `GET /customer/following`

Le route `/customer/organizers` sono il contratto corrente; gli endpoint precedenti restano alias compatibili. Il profilo pubblico espone `is_following` e `followers_count`. Le risposte follower/following sono minimizzate e non contengono email o ID sequenziali. Le preferenze distinguono notifiche operative, marketing per categorie e nuovi eventi degli Organizer seguiti; la pubblicazione accoda un job idempotente dopo il commit.

Per `POST /events/{event}/bookings` usare `Idempotency-Key` per evitare prenotazioni duplicate. Un retry restituisce lo stesso booking e lo stesso `qr_token`, senza decrementare nuovamente disponibilita evento o ticket. Il vincolo univoco database resta la protezione finale in caso di collisione concorrente.

Il QR usa un token casuale ad alta entropia. Il database conserva separatamente l'hash per il check-in e il valore cifrato reversibile per il Customer; nessuno dei due campi interni viene serializzato. `GET /me/bookings/{booking}/qr` restituisce il token solo al proprietario autenticato. Un booking legacy privo del valore cifrato riceve `409`; dopo il check-in il token resta recuperabile, ma un secondo utilizzo viene rifiutato dal backend.

Per `POST /events/{event}/reviews` l'utente deve avere un booking proprio non cancellato e l'evento deve essere terminato. Ogni prenotazione/evento puo generare una sola recensione e l'organizzatore non puo recensire i propri eventi.

Per `POST /activities/{activity}/reviews` il cliente deve indicare una propria Reservation non cancellata oppure un proprio Event Booking valido dello stesso Organizer e territorio. La recensione del locale è accettata soltanto dal giorno successivo all'esperienza; richieste anticipate, riferite ad altre Activity o duplicate sono respinte dal backend.

Lo scheduler accoda richieste recensione post-visita usando la stessa eligibility degli endpoint di recensione. Le notifiche operative rispettano l'opt-out e i canali abilitati, includono il collegamento all'esperienza corretta e sono deduplicate per esperienza e canale. Le Reservation cancellate/no-show e le esperienze già recensite sono escluse.

`GET /organizer/reservations/analytics` accetta `period=7|30|90|custom`; per `custom` richiede `from` e `to`. Tutti i dati sono aggregati e isolati sull'Organizer autenticato. `return` indica un Cliente che effettua nel periodo una nuova Reservation presso la stessa Activity dopo una Reservation valida precedente; `repeat_rate` è la quota di tali Clienti sui Clienti unici del periodo. Il funnel usa esclusivamente Activity View, Availability View, Reservation, stati `completed`/`no_show`, Review e Return realmente registrati. `Valore preordini` somma soltanto i subtotali snapshot reali dei piatti in preordini confermati e non rappresenta fatturato.

Le nuove Reservation locali e gli Event Booking accodano dopo il commit una notifica WhatsApp operativa verso il telefono dell'Organizer. Il provider è selezionato tramite `WHATSAPP_PROVIDER`; credenziali ed endpoint provengono dall'ambiente. Il provider `fake` è riservato ai test e un errore di invio non annulla la prenotazione.

Tutte le route cliente sono protette da `auth:sanctum` + `role:customer`.

La UI customer mostra la pagina `Tutti gli Eventi` al posto di `Esplora eventi`. La route tecnica puo restare `/customer/explore`, ma il testo esposto all'utente e `Tutti gli Eventi`.

`Eventi di Successo` usa `event_success_scores` e mostra solo eventi conclusi, non annullati/sospesi, con `show_in_inspiration_feed = true`. I dati esposti sono pubblici o aggregati: titolo, categoria, organizer, territorio, rating, review count, fill rate, recommendation rate e nuova edizione se collegata tramite `event_series_id`.

Nuove edizioni:
- collegate tramite `event_series`;
- mostrate solo se future e pubblicate/sold out;
- l'edizione conclusa non espone prenotazione, la nuova edizione puo essere prenotabile.

Eventi simili:
- deterministici su categoria e territorio;
- futuri e pubblici;
- non usano AI.

`notify-similar` salva una preferenza reale in `customer_event_format_interests` e sincronizza la categoria in `user_event_preferences`.

## Organizzatore
- `GET /organizer/activities`
- `POST /organizer/activities`
- `PUT /organizer/activities/{activity}` (`category_ids` associa interessi amministrati esistenti)

- `GET /organizer/profile`
- `GET /organizer/dashboard`
- `GET /organizer/reviews`
- `GET /organizer/statistics`
- `GET /organizer/analytics`
- `GET /organizer/followers`
- `GET /organizer/categories`
- `GET /organizer/opportunities`
- `POST /organizer/opportunities/generate`
- `GET /organizer/opportunities/top-events`
- `POST /organizer/opportunities/{suggestion}/use`
- `POST /organizer/top-events/{event}/inspire`
- `POST /organizer/campaigns`
- `GET /organizer/campaigns`
- `GET /organizer/calendar`
- `GET /organizer/calendar/availability`
- `GET /organizer/calendar/events`
- `GET /organizer/map`
- `POST /organizer/profile`
- `PUT /organizer/profile`
- `GET /organizer/events`
- `POST /organizer/events`
- `GET /organizer/events/{event}`
- `GET /organizer/events/{event}/summary`
- `GET /organizer/events/{event}/participants`
- `GET /organizer/events/{event}/reviews`
- `PUT /organizer/events/{event}`
- `PATCH /organizer/events/{event}`
- `POST /organizer/events/{event}/publish`
- `POST /organizer/events/{event}/close-bookings`
- `POST /organizer/events/{event}/cancel`
- `GET /organizer/events/{event}/bookings`
- `GET /organizer/bookings`
- `GET /organizer/bookings/{booking}`
- `GET /organizer/reviews/events`
- `GET /organizer/reviews/events/{event}`

`GET/POST/PUT /organizer/profile` richiede `role:organizer`; le altre route organizzatore richiedono anche `organizer.approved`. Un organizzatore pending puo vedere/aggiornare lo stato del profilo, ma non puo creare, pubblicare o gestire eventi. Il flusso evento e `draft -> published`: un organizzatore verificato pubblica direttamente, senza approvazione preventiva admin.

`GET /organizer/events` è sempre limitato al profilo dell'Organizer autenticato. Il filtro `cancelled` conserva nello storico esclusivamente gli eventi annullati del proprietario, che non sono modificabili o ripubblicabili. Il KPI `bi.total_events` della dashboard usa la stessa relazione e corrisponde al totale non filtrato mostrato in `I miei eventi`; i KPI futuri, conclusi e non pubblicati restano sottoinsiemi distinti.

`GET /organizer/bookings` offre ricerca per cliente, email, evento e codice, filtri di stato, ordinamento e paginazione server-side. Lista e dettaglio sono in sola lettura: l'Organizer vede esclusivamente prenotazioni dei propri eventi; l'Admin mantiene visibilita completa. Il QR viene restituito soltanto nel dettaglio autorizzato.

`POST /organizer/events/{event}/cancel` richiede `reason_code` e `reason`. L'annullamento e transazionale: aggiorna evento, prenotazioni valide, notifiche cliente, audit log e `event_cancellations`; dopo il commit accoda una sola email operativa per ogni prenotazione coinvolta.

Calendario creazione evento:
- `empty`/verde: nessun evento pubblico nello stesso territorio e giorno.
- `low`/giallo: un evento pubblico.
- `crowded`/rosso: due o piu eventi pubblici.
- gli eventi annullati non influenzano il colore.

Formula `success_score`:
`fill_rate*0.35 + attendance_rate*0.25 + rating_normalizzato*0.20 + recommendation_rate*0.10 + favorite_bonus - no_show_rate*0.20`.

Prompt AI server-side v1:
il backend prepara solo dati aggregati: tipo organizer, territorio, mese/stagione, date libere, categorie popolari, storico aggregato del locale. Nessun partecipante, email, telefono, ricavo o dato privato viene inviato. Se il provider non e disponibile, il sistema usa `seasonal_event_templates`.

Job asincroni:
- `RecalculateEventSuccessScore`: aggiorna `event_success_scores` in modo idempotente.
- `GenerateOrganizerEventSuggestions`: genera o aggiorna `ai_event_suggestions` per mese/territorio.
- `RecalculateOpportunitySnapshot`: salva snapshot calendario in `event_opportunity_snapshots`.

## Admin
- `GET /admin/dashboard`
- `GET /admin/overview`
- `GET /admin/organizers`
- `GET /admin/events`
- `GET /admin/events/{event}`
- `POST /admin/categories`
- `POST /admin/events/{event}/suspend`
- `POST /admin/events/{event}/archive`
- `GET /admin/organizers/pending`
- `GET /admin/organizers/{organizer}`
- `GET /admin/organizers/{organizer}/events`
- `GET /admin/organizers/{organizer}/reviews`
- `GET /admin/organizers/{organizer}/bookings`
- `GET /admin/organizers/{organizer}/cancellations`
- `POST /admin/organizers/{organizer}/approve`
- `POST /admin/organizers/{organizer}/reject`
- `GET /admin/rankings/events`
- `GET /admin/rankings/organizers`
- `GET /admin/rankings/events/{event}/breakdown`
- `POST /admin/reviews/{review}/approve`
- `POST /admin/reviews/{review}/hide`
- `DELETE /admin/reviews/{review}`

Tutte le route admin sono protette da `auth:sanctum` + `role:admin`. Il dettaglio evento espone all'Admin proprietario, capienza, ticket e i soli dati essenziali dei clienti con prenotazione valida. La creazione categoria normalizza nome e slug e rifiuta duplicati. L'approvazione di un Organizer accoda una email operativa una sola volta. L'admin non approva preventivamente gli eventi: interviene in post-moderazione con sospensione, archivio, segnalazioni, audit e sospensione organizer.

Classifiche admin:
- eventi: `final_score = creativity_score*0.30 + participation_score*0.35 + review_score*0.35`;
- organizzatori: ordinamento primario per eventi creati, con rating, riempimento e partecipanti come indicatori qualitativi.

`GET /admin/overview` alimenta la console amministratore completa: KPI piattaforma, grafici mensili, ultimi eventi, ultime registrazioni, recensioni, tabelle eventi/organizzatori/clienti, categorie, territorio, abbonamenti e audit log.

## Middleware e policy
- `role:customer|organizer|admin`: verifica il ruolo reale dal backend e lo stato utente.
- `organizer.approved`: consente l'accesso operativo solo a profili organizzatore verificati.
- `EventPolicy`: limita lettura/modifica delle esperienze al proprietario o all'admin moderatore.
- `OrganizerProfilePolicy`: limita moderazione organizer agli admin e gestione profilo al proprietario.

OpenAPI: `docs/openapi.yaml`.

## MòVi Manager

L'area Organizer espone Dashboard, Calendario coperti, Prenotazioni, Menu ed Eventi come flussi distinti. Le regole capacity-mode accettano giorni della settimana, slot, coperti MòVi e intervalli `effective_from`/`effective_to`; le eccezioni giornaliere possono chiudere una data o ridefinirne gli slot senza cancellare prenotazioni confermate.

L'import menu DOCX/XLSX è in due fasi: `POST /organizer/activities/{activity}/menus/import` restituisce esclusivamente l'anteprima strutturata, mentre `POST /organizer/activities/{activity}/menus/import/confirm` salva la versione corretta scegliendo `add` o `replace`. MòVi Plan filtra Activity ed Event in base ai dati reali, inclusi data, fascia oraria e coperti richiesti; se nessuna disponibilità è compatibile restituisce un risultato vuoto esplicito.
