# Schema database

## Implementato nella vertical slice
- `users`: identità, ruolo, autenticazione.
- `personal_access_tokens`: Laravel Sanctum.
- `regions`, `provinces`, `municipalities`: territorio italiano estendibile.
- `categories`: categorie evento e preferenze future.
- `organizer_profiles`: profilo dell'organizzatore.
- `events`: evento con stato, capienza, territorio e moderazione.
- `event_categories`: relazione molti-a-molti.

## Predisposto per slice successive
- `ticket_types`
- `bookings`
- `booking_items`
- `waitlist_entries`
- `check_ins`
- `favorites`
- `idempotency_keys`

## Vincoli chiave
- `events.status` limitato agli stati del dominio.
- `events.capacity_total >= events.available_total`.
- ULID univoci per entità esposte pubblicamente.
- Un profilo organizzatore per owner.
- Un preferito per coppia utente/evento.
- Una iscrizione waitlist per coppia utente/evento.

## Tabelle di supporto predisposte
`user_profiles`, `organizer_members`, `user_category_preferences`, `user_location_preferences`, `notification_preferences`, `venues`, `event_schedules`, `booking_participants_summary`, `notifications`, `notification_deliveries`, `reviews`, `media`, `reports`, `audit_logs`.

Queste tabelle sono presenti per stabilizzare il dominio e le relazioni future, ma la logica applicativa della prima vertical slice usa solo le aree necessarie alla pubblicazione e moderazione eventi.
