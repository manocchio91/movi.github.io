# Schema database

## Tabelle principali
- `users`: identita, ruolo `customer|organizer|admin`, stato `active|pending|suspended`, autenticazione.
- `organizer_profiles`: profilo locale con comune, indirizzo, contatti e verifica.
- `subscription_plans`: piani SaaS Free Trial, Starter, Professional, Enterprise.
- `subscription_features`: feature e limiti associati ai piani.
- `organizer_subscriptions`: abbonamento attivo/trial/cancellato del locale.
- `invoices`: fatture predisposte per provider esterno.
- `organizer_followers`: utenti che seguono un organizzatore.
- `experience_views`: visualizzazioni esperienza per analytics e raccomandazioni future.
- `campaigns`: campagne promozionali semplici per tracciamento.
- `campaign_statistics`: views, click, booking e conversione campagna.
- `organizer_statistics`: KPI aggregati per periodo.
- `event_statistics`: KPI aggregati per esperienza.
- `reviews`: valutazioni multi-criterio collegate a evento, booking, utente e organizzatore.
- `review_images`: immagini predisposte per gallery recensione.
- `review_reports`: segnalazioni utenti sulle recensioni.
- `review_statistics`: medie, distribuzione stelle, soddisfazione e raccomandazione per evento o organizzatore.
- `analytics_events`: eventi analytics atomici.
- `dashboard_cache`: cache dashboard ricalcolabile da job.
- `organizer_opportunity_reports`: report deterministici dell'Organizer Opportunity Engine.
- `ai_event_suggestions`: suggerimenti AI/fallback generati server-side con input aggregato e tracciamento provider.
- `event_success_scores`: punteggio comparabile tra eventi basato su percentuali e qualita.
- `event_cancellations`: annullamenti organizzatore/admin con motivo, attore, prenotazioni e persone impattate.
- `event_rankings`: classifiche admin evento con creativita, partecipazione, recensioni e punteggio finale.
- `organizer_rankings`: classifiche admin organizzatore con eventi creati, completati, annullati, rating e riempimento.
- `event_series`: collega piu edizioni dello stesso format/evento.
- `customer_event_format_interests`: preferenze cliente per ricevere notifiche su format simili.
- `seasonal_event_templates`: template stagionali usati come fallback senza AI.
- `event_opportunity_snapshots`: snapshot idempotenti del livello di affollamento date.
- `regions`, `provinces`, `municipalities`: territorio italiano estendibile.
- `categories`: macroaree gestite da database.
- `user_event_preferences`: relazione molti-a-molti tra clienti e macroaree preferite.
- `notification_preferences`: canali, consenso promozionale e frequenza.
- `venues`: luoghi collegabili al locale.
- `events`: evento con stato, capienza, disponibilita, territorio, annullamento e post-moderazione.
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
- `users.role` limitato a `customer|organizer|admin`.
- `users.status` limitato a `active|pending|suspended`.
- ULID univoci per entita esposte pubblicamente.
- Un profilo organizzatore per owner.
- Una preferenza per coppia utente/categoria.
- Una prenotazione duplicata con stessa idempotency key non viene ricreata.
- Una recensione richiede partecipazione reale e una sola recensione per booking/evento.
- Gli organizzatori non possono recensire esperienze del proprio profilo.
- Solo organizzatori con `organizer_profiles.verification_status = verified` possono accedere alle route operative e pubblicare eventi direttamente.
- Il ciclo evento operativo e `draft -> published`; gli stati legacy di revisione restano compatibili a database ma non sono piu esposti da rotte/UI.
- Un evento futuro puo essere annullato dall'organizzatore proprietario o sospeso/archiviato dall'admin in post-moderazione.
- Le recensioni approvate alimentano statistiche evento, profilo organizzatore e Business Dashboard.
- Gli eventi possono salvare descrizione completa, programma, incluso/non incluso, note allergie, requisiti e policy cancellazione.
- Gli eventi nel feed ispirazione rispettano `show_in_inspiration_feed`.
- Gli Eventi di Successo customer rispettano `event_success_scores`, `event_series_id` e non espongono dati personali o ricavi.
- Le notifiche usano `deduplication_key` per evitare duplicati.
- Un follower riceve notifiche per nuove esperienze dell'organizzatore anche senza match categoria.
- Le statistiche aggregate sono progettate per evitare query pesanti in dashboard.

## Tabelle predisposte
`user_profiles`, `organizer_members`, `user_location_preferences`, `event_schedules`, `booking_participants_summary`, `waitlist_entries`, `check_ins`, `favorites`, `media`, `reports`, `idempotency_keys`.
