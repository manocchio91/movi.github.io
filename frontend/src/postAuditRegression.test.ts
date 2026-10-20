import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const app = readFileSync(new URL('./App.ts', import.meta.url), 'utf8');
const styles = readFileSync(new URL('./styles.css', import.meta.url), 'utf8');

/**
 * Estrae l'ultimo blocco @media (max-width: 900px) che contiene la regola indicata:
 * il foglio contiene piu' blocchi omonimi e ad applicarsi e' l'ultimo.
 */
function mediaBlock(selector: string): string {
  const blocks = styles.split('@media');
  const matching = blocks.filter((block) => block.includes(selector) && block.includes('max-width: 900px'));

  assert.ok(matching.length > 0, `blocco @media (max-width: 900px) con "${selector}" non trovato`);

  return matching[matching.length - 1];
}

test('admin console non deborda orizzontalmente su viewport stretti', () => {
  const block = mediaBlock('.admin-console');
  // Il track della griglia non deve piu' ereditare il min-content della sidebar.
  assert.match(block, /\.admin-console \{ grid-template-columns: minmax\(0, 1fr\); \}/);
  // La nav e' un contenitore a scorrimento: deve poter restringersi.
  assert.match(block, /\.admin-sidebar nav \{ min-width: 0; \}/);
  assert.match(block, /\.admin-sidebar \{ min-width: 0; \}/);
  assert.match(block, /\.admin-main \{ min-width: 0; \}/);
});

test('la regola admin non altera il layout desktop', () => {
  // Fuori dai media query la griglia resta a due colonne con sidebar fissa.
  assert.match(styles, /\.admin-console \{[^}]*grid-template-columns: 248px minmax\(0,1fr\)/);
  assert.match(styles, /\.admin-console \{[^}]*grid-template-columns: 280px minmax\(0, 1fr\)/);
});

test('i KPI admin non riducono un importo formattato a NaN', () => {
  // adminKpi applicava Number() a valori gia' formattati ("€ 19.00" -> NaN).
  assert.match(app, /const kpiValue = \(value: any\) => \(typeof value === 'number'/);
  assert.match(app, /h\('strong', kpiValue\(value\)\)/);
  // Il ricavo teorico usa il formattatore in euro condiviso.
  assert.match(app, /adminKpi\('Ricavi teorici', formatEuro\(/);
  assert.doesNotMatch(app, /adminKpi\('Ricavi teorici', `€/);
});

test('le azioni di moderazione evento sono mostrate solo quando consentite', () => {
  assert.match(app, /\['published', 'sold_out'\]\.includes\(event\.status\)/);
  assert.doesNotMatch(app, /event\.status === 'suspended' \? 'Riattiva' : 'Sospendi'/);
});

test('le proposte MòVi Plan distinguono l\'organizzatore', () => {
  // L'organizzatore e' mostrato solo quando differisce dal titolo: evita ripetizioni
  // e rende distinguibili locali omonimi.
  assert.match(app, /step\.organizer && step\.organizer !== step\.title/);
});
