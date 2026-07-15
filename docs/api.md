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

## Organizzatore
- `GET /organizer/profile`
- `GET /organizer/dashboard`
- `GET /organizer/reviews`
- `GET /organizer/statistics`
- `GET /organizer/analytics`
- `GET /organizer/followers`
- `GET /organizer/categories`
- `GET /organizer/opportunities`
- `POST /organizer/campaigns`
- `GET /organizer/campaigns`
- `GET /organizer/calendar`
- `GET /organizer/map`
- `POST /organizer/profile`
- `PUT /organizer/profile`
- `GET /organizer/events`
- `POST /organizer/events`
- `GET /organizer/events/{event}`
- `PUT /organizer/events/{event}`
- `PATCH /organizer/events/{event}`
- `POST /organizer/events/{event}/submit`
- `POST /organizer/events/{event}/publish`
- `POST /organizer/events/{event}/close-bookings`
- `POST /organizer/events/{event}/cancel`
- `GET /organizer/events/{event}/bookings`

`GET/POST/PUT /organizer/profile` richiede `role:organizer`; le altre route organizzatore richiedono anche `organizer.approved`. Un organizzatore pending puo vedere/aggiornare lo stato del profilo, ma non puo creare, pubblicare o gestire eventi.

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
