import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const app = readFileSync(new URL('./App.ts', import.meta.url), 'utf8');
const styles = readFileSync(new URL('./styles.css', import.meta.url), 'utf8');

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
