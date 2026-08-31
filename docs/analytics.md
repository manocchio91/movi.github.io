# Organizer analytics semantics

- `event_impression`: event returned in public discovery; deduplicated by event, anonymous app session and calendar day.
- `event_detail_view`: successful public detail opening; same deduplication and mirrored once in `experience_views`.
- `event_share`: completed native/web share or an explicit copy/WhatsApp action.
- Business events (`booking_created`, `booking_cancelled`, `waitlist_joined`, `waitlist_converted`, `check_in`, `review_created`, `organizer_followed`, `organizer_unfollowed`) are emitted only after the corresponding domain mutation succeeds.

Session identifiers are stored only as SHA-256 hashes. Analytics metadata must not contain email, phone, booking notes, dietary/accessibility data or other personal payloads. Booking, participant, cancellation, attendance, no-show and review totals remain derived from source-of-truth domain tables; the event stream supplies discovery, share, waitlist and acquisition signals.
