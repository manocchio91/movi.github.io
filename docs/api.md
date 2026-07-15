# API

Base path: `/api/v1`

Formato errori:
```json
{
  "message": "Descrizione errore",
  "errors": {},
  "request_id": "uuid"
}
```

## Autenticazione
- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout`
- `GET /me`

## Pubblico
- `GET /events`
- `GET /events/{event}`
- `GET /categories`
- `GET /locations/search?q=camp`

Filtri eventi implementati:
- `category_id`
- `province_id`
- `municipality_id`
- `date`

## Organizzatore
- `POST /organizers`
- `GET /organizer/profile`
- `GET /organizer/events`
- `POST /organizer/events`
- `PATCH /organizer/events/{event}`
- `POST /organizer/events/{event}/submit`

## Admin
- `GET /admin/events/pending`
- `POST /admin/events/{event}/approve`
- `POST /admin/events/{event}/reject`
- `POST /admin/events/{event}/suspend`

OpenAPI: `docs/openapi.yaml`.
