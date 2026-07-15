# API

Base path: `/api/v1`

## Autenticazione
- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout`
- `GET /me`

## Pubblico
- `GET /categories`
- `GET /events`
- `GET /events/{event}`
- `GET /locations/search?q=camp`

Filtri eventi: `category_id`, `province_id`, `municipality_id`, `date`, `free`, `available`.

## Cliente
- `GET /me/preferences`
- `PUT /me/preferences`
- `GET /me/notification-preferences`
- `PUT /me/notification-preferences`
- `POST /events/{event}/bookings`
- `GET /me/bookings`
- `GET /me/bookings/{booking}`
- `POST /me/bookings/{booking}/cancel`
- `GET /me/notifications`
- `POST /me/notifications/{notification}/read`
- `POST /me/notifications/read-all`

Per `POST /events/{event}/bookings` usare `Idempotency-Key` per evitare prenotazioni duplicate.

## Organizzatore
- `GET /organizer/profile`
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

## Admin
- `GET /admin/events/pending`
- `POST /admin/events/{event}/approve`
- `POST /admin/events/{event}/reject`
- `POST /admin/events/{event}/suspend`
- `GET /admin/organizers/pending`
- `POST /admin/organizers/{organizer}/approve`
- `POST /admin/organizers/{organizer}/reject`

OpenAPI: `docs/openapi.yaml`.
