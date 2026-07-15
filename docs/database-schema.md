# Schema database

## Tabelle principali
- `users`: identita, ruolo `customer|organizer|admin`, autenticazione.
- `organizer_profiles`: profilo locale con comune, indirizzo, contatti e verifica.
- `subscription_plans`: piani SaaS Free Trial, Starter, Professional, Enterprise.
- `subscription_features`: feature e limiti associati ai piani.
- `organizer_subscriptions`: abbonamento attivo/trial/cancellato del locale.
- `invoices`: fatture predisposte per provider esterno.
- `organizer_followers`: utenti che seguono un organizzatore.
- `experience_views`: visualizzazioni esperienza per analytics e raccomandazioni future.
- `regions`, `provinces`, `municipalities`: territorio italiano estendibile.
- `categories`: macroaree gestite da database.
- `user_event_preferences`: relazione molti-a-molti tra clienti e macroaree preferite.
- `notification_preferences`: canali, consenso promozionale e frequenza.
- `venues`: luoghi collegabili al locale.
- `events`: evento con stato, capienza, disponibilita, territorio e moderazione.
- `event_categories`: relazione molti-a-molti tra eventi e macroaree.
- `ticket_types`: prezzo unico/adulto/bambino, quantita massima, prenotato e stato.
- `bookings`: prenotazioni confermate con ULID, codice, idempotency key e totale prezzo.
- `booking_items`: righe biglietto della prenotazione.
- `notifications`: notifiche interne/email collegate a utente, evento e categoria.
- `notification_deliveries`: tracciamento invio per canale.
- `audit_logs`: audit amministrativo e organizzatore.

## Vincoli chiave
- `events.status` limitato agli stati del dominio.
- `events.capacity_total >= events.available_total`.
- ULID univoci per entita esposte pubblicamente.
- Un profilo organizzatore per owner.
- Una preferenza per coppia utente/categoria.
- Una prenotazione duplicata con stessa idempotency key non viene ricreata.
- Le notifiche usano `deduplication_key` per evitare duplicati.
- Un follower riceve notifiche per nuove esperienze dell'organizzatore anche senza match categoria.

## Tabelle predisposte
`user_profiles`, `organizer_members`, `user_location_preferences`, `event_schedules`, `booking_participants_summary`, `waitlist_entries`, `check_ins`, `favorites`, `reviews`, `media`, `reports`, `idempotency_keys`.
