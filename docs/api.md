# API

Base path: `/api/v1`

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
- `GET /events`
- `GET /events/{event}`
- `GET /events/{event}/reviews`
- `GET /locations/search?q=camp`
- `GET /subscription-plans`
- `GET /organizers/{organizer}`

Filtri eventi: `category_id`, `province_id`, `municipality_id`, `date`, `free`, `available`.

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
- `POST /me/bookings/{booking}/cancel`
- `GET /me/notifications`
- `POST /me/notifications/{notification}/read`
- `POST /me/notifications/read-all`
- `POST /organizers/{organizer}/follow`
- `DELETE /organizers/{organizer}/follow`

Per `POST /events/{event}/bookings` usare `Idempotency-Key` per evitare prenotazioni duplicate.

Per `POST /events/{event}/reviews` l'utente deve avere una prenotazione confermata e avere partecipato: check-in effettuato, booking completato oppure evento concluso. Ogni prenotazione/evento puo generare una sola recensione e l'organizzatore non puo recensire i propri eventi.

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
- `POST /organizer/events/{event}/submit`
- `POST /organizer/events/{event}/publish`
- `POST /organizer/events/{event}/close-bookings`
- `POST /organizer/events/{event}/cancel`
- `GET /organizer/events/{event}/bookings`
- `GET /organizer/reviews/events`
- `GET /organizer/reviews/events/{event}`

`GET/POST/PUT /organizer/profile` richiede `role:organizer`; le altre route organizzatore richiedono anche `organizer.approved`. Un organizzatore pending puo vedere/aggiornare lo stato del profilo, ma non puo creare, pubblicare o gestire eventi.

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
- `GET /admin/organizers`
- `GET /admin/events/pending`
- `POST /admin/events/{event}/approve`
- `POST /admin/events/{event}/reject`
- `POST /admin/events/{event}/suspend`
- `GET /admin/organizers/pending`
- `POST /admin/organizers/{organizer}/approve`
- `POST /admin/organizers/{organizer}/reject`
- `POST /admin/reviews/{review}/approve`
- `POST /admin/reviews/{review}/hide`
- `DELETE /admin/reviews/{review}`

Tutte le route admin sono protette da `auth:sanctum` + `role:admin`.

## Middleware e policy
- `role:customer|organizer|admin`: verifica il ruolo reale dal backend e lo stato utente.
- `organizer.approved`: consente l'accesso operativo solo a profili organizzatore verificati.
- `EventPolicy`: limita lettura/modifica delle esperienze al proprietario o all'admin moderatore.
- `OrganizerProfilePolicy`: limita moderazione organizer agli admin e gestione profilo al proprietario.

OpenAPI: `docs/openapi.yaml`.
