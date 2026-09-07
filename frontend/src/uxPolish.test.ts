import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const app = readFileSync(new URL('./App.ts', import.meta.url), 'utf8');
const styles = readFileSync(new URL('./styles.css', import.meta.url), 'utf8');

test('customer discovery renders only real unified API results', () => {
  assert.match(app, /api\('\/discovery\?when=today&available=1'\)/);
  assert.match(app, /api\('\/recommendations\?available=1'\)/);
  assert.match(app, /recommendation_reasons/);
});

test('MòVi Plan uses structured inputs and backend-generated steps', () => {
  assert.match(app, /Chiedi a MòVi/);
  assert.match(app, /api<any>\('\/ai\/plan'/);
  assert.match(app, /moviAiResponse\.value\?\.plans/);
  assert.doesNotMatch(app, /OpenAI API/);
});

test('successful events link to an available next edition', () => {
  assert.match(app, /event\.next_edition[\s\S]*Apri nuova edizione/);
  assert.doesNotMatch(app, /Nuova edizione non ancora collegata/);
});

test('booking history exposes the essential booking facts', () => {
  for (const label of ['Data', 'Biglietti', 'Quantità', 'Totale', 'Telefono', 'Codice']) {
    assert.match(app, new RegExp("h\\('dt', '" + label + "'\\)"));
  }
});

test('admin event state changes require explicit confirmation', () => {
  assert.match(app, /window\.confirm\(action === 'suspend'/);
});

test('responsive design system keeps focus and compact breakpoints', () => {
  assert.match(styles, /:focus-visible/);
  assert.match(styles, /@media \(max-width: 600px\)/);
  assert.match(styles, /\.booking-facts \{ grid-template-columns: 1fr; \}/);
});

test('MòVi role selection uses official assets and premium role cards', () => {
  assert.ok(app.includes('/branding/movi-logo-full.png'));
  assert.ok(app.includes('role-selection-screen'));
  assert.ok(app.includes('auth-role-card'));
  assert.ok(app.includes('Vivi quello che succede.'));
  assert.ok(!app.includes('La scelta apre soltanto'));
  assert.ok(styles.includes('.auth-role-card:hover'));
  assert.ok(styles.includes('.auth-role-card:focus-visible'));
});
test('event capacity is total-only with booked minimum and derived availability', () => {
  assert.ok(app.includes("h('span', 'Capienza totale')"));
  assert.ok(app.includes('eventBookedPeople.value'));
  assert.ok(app.includes('posti già prenotati.'));
  assert.ok(app.includes('posti disponibili'));
  assert.ok(!app.includes("input('Posti disponibili'"));
  assert.ok(styles.includes('.capacity-summary'));
});
test('event, ticket and purchasable availability stay distinct', () => {
  assert.ok(app.includes('Math.min(event.available_total, ticketAvailable(ticket))'));
  assert.ok(app.includes('biglietti disponibili'));
  assert.ok(app.includes('Disponibilità limitata per questa tipologia di biglietto.'));
  assert.ok(app.includes('editableTickets(draft, ticket)'));
  assert.ok(app.includes('Quantità massima ticket'));
  assert.ok(app.includes('Biglietti venduti'));
});

test('visible sources preserve UTF-8 and Italian euro formatting', () => {
  assert.ok(app.includes('const formatEuro'));
  assert.ok(app.includes("replace('.', ',')"));
  assert.ok(app.includes('Disponibilità'));
  assert.ok(app.includes('Quantità'));
  assert.doesNotMatch(app + styles, /Ã|Â|â€™|â‚¬|ï¿½|�/);
});

test('event detail exposes native share copy fallback and WhatsApp', () => {
  assert.ok(app.includes('navigator.share'));
  assert.ok(app.includes('navigator.clipboard.writeText(event.canonical_url)'));
  assert.ok(app.includes('https://wa.me/?text='));
  assert.ok(app.includes("'Condividi'"));
  assert.ok(app.includes("'X-Analytics-Session': analyticsSession"));
  assert.ok(app.includes("`/events/${event.ulid}/share`"));
});

test('activity reservations use real capacity APIs without table mode', () => {
  assert.ok(app.includes("api<any>('/activities')"));
  assert.ok(app.includes('/availability?date='));
  assert.ok(app.includes("'Idempotency-Key': crypto.randomUUID()"));
  assert.ok(app.includes("api<any>('/me/reservations')"));
  assert.ok(app.includes('MòVi Manager · Attività'));
  assert.ok(!app.includes('floor-plan editor'));
});

test('restaurant menu and manager preorder summary use real APIs', () => {
  assert.match(app, /`\/activities\/\$\{ulid\}\/menu`/);
  assert.match(app, /Pre-order da preparare oggi/);
  assert.match(app, /Le informazioni sugli allergeni provengono dal locale/);
  assert.doesNotMatch(app, /pagamento effettuato/);
});
