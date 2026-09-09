import { expect, test, type Page } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

const accounts = {
  customer: ['Cliente', 'customer@example.test'],
  organizer: ['Organizzatore', 'organizer@example.test'],
  admin: ['Admin', 'admin@example.test'],
} as const;

async function login(page: Page, role: keyof typeof accounts) {
  await page.goto('/');
  await page.getByRole('button', { name: accounts[role][0] }).click();
  await page.getByLabel('Email').fill(accounts[role][1]);
  await page.getByLabel('Password').fill('password');
  await page.locator('form.auth-form').getByRole('button', { name: 'Accedi', exact: true }).click();
  await expect(page.getByText(accounts[role][1])).toBeVisible();
}

test('cliente: navigazione, evento, disponibilità, booking, QR e preferenze', async ({ page }) => {
  await login(page, 'customer');
  const sidebar = page.locator('.app-header nav');
  await expect(sidebar).toBeVisible();
  await expect(sidebar).not.toContainText('MòVi Manager');
  await expect(sidebar).not.toContainText('Attività');
  await expect(sidebar).not.toContainText('Prenotazioni locali');

  await page.getByRole('button', { name: 'Tutti gli Eventi' }).click();
  await expect(page.getByRole('heading', { name: 'Laboratorio creativo in fattoria', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Apri Laboratorio creativo in fattoria' }).click();
  await expect(page.getByText(/22 posti prenotabili su 25/)).toBeVisible();
  await expect(page.getByText(/22 biglietti disponibili/).first()).toBeVisible();
  await page.getByLabel('Quantità Adulto').fill('1');
  await page.getByLabel('Telefono obbligatorio').fill('+393331234567');
  await page.getByRole('button', { name: 'Continua' }).click();
  await expect(page.getByRole('dialog')).toContainText('Conferma prenotazione');
  await page.getByRole('dialog').getByRole('button', { name: 'Conferma' }).click();
  await expect(page.getByText('Prenotazione confermata')).toBeVisible();
  await expect(page.getByRole('img', { name: /QR prenotazione/ })).toBeVisible();

  await page.getByRole('button', { name: 'Preferenze' }).click();
  await expect(page.getByRole('heading', { name: 'Preferenze e notifiche' })).toBeVisible();
  await page.getByLabel('Notifiche email').uncheck();
  await page.getByLabel('Frequenza').selectOption('weekly');
  await page.getByRole('button', { name: 'Salva preferenze' }).click();
  await expect(page.getByRole('status')).toContainText(/salvate|aggiornate/i);
});

test('cliente: modifica, QR, cancellazione e recensione vincolata', async ({ page }) => {
  await login(page, 'customer');
  await page.getByRole('button', { name: 'Le mie prenotazioni' }).click();
  await expect(page.getByText('EVT-DEMO01')).toBeVisible();
  await page.getByRole('button', { name: 'Visualizza QR' }).first().click();
  await expect(page.getByRole('img', { name: /QR prenotazione/ }).first()).toBeVisible();
  await page.getByRole('button', { name: 'Modifica prenotazione' }).first().click();
  await expect(page.getByRole('dialog')).toContainText('Modifica');
  await page.getByRole('dialog').getByRole('button', { name: 'Rivedi e salva' }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Conferma' }).click();
  await expect(page.getByText(/aggiornata/i)).toBeVisible();
  await expect(page.getByRole('button', { name: /Recensisci/ })).toHaveCount(0);
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: 'Cancella prenotazione' }).first().click();
  await expect(page.getByRole('status')).toContainText('Prenotazione annullata.');
});

test('organizzatore: menu, tutti gli eventi, modifica e profilo pending', async ({ page }) => {
  await login(page, 'organizer');
  await expect(page.getByText('Nuovo ristorante')).toHaveCount(0);
  await page.getByRole('button', { name: 'Crea evento', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Crea evento' })).toBeVisible();
  await page.getByLabel('Titolo').fill('Evento QA E2E');
  await page.getByLabel('Descrizione breve').fill('Evento deterministico della suite browser');
  await page.getByLabel('Descrizione', { exact: true }).fill('Descrizione completa evento QA E2E');
  await page.getByLabel('Categoria').selectOption({ index: 1 });
  await page.getByLabel('Luogo').selectOption({ index: 1 });
  await page.getByLabel('Data e ora di inizio').fill('2030-06-15T18:00');
  await page.getByLabel('Data e ora di fine').fill('2030-06-15T20:00');
  await page.getByLabel('Capienza totale').fill('12');
  await page.getByLabel('Nome biglietto').fill('Ingresso QA');
  await page.getByLabel('Prezzo (€)').fill('10');
  await page.getByLabel('Quantità massima ticket').fill('12');
  await page.getByRole('button', { name: 'Salva bozza' }).click();
  await expect(page.getByRole('status')).toContainText('Evento creato correttamente.');
  await page.getByRole('button', { name: 'Torna ai miei eventi' }).click();
  await page.getByRole('button', { name: 'I miei eventi' }).click();
  await expect(page.getByRole('button', { name: 'Tutti' })).toHaveClass(/active/);
  const publishedEvent = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Laboratorio creativo in fattoria', exact: true }) });
  await expect(publishedEvent).toContainText('Disponibili: 22');
  await publishedEvent.getByRole('button', { name: 'Modifica' }).click();
  await expect(page.getByRole('heading', { name: /Modifica evento/ })).toBeVisible();
  await expect(page.getByText(/In assenza della copertina verrà usato il logo/)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Usa il logo dell’Organizzatore' })).toBeVisible();
  await expect(page.getByText(/3 prenotati · 22 posti disponibili/)).toBeVisible();
  await page.getByLabel('Titolo').fill('Laboratorio creativo in fattoria E2E');
  await page.getByRole('button', { name: 'Salva bozza' }).click();
  await expect(page.getByRole('status')).toContainText(/aggiornato/i);

  await page.getByRole('button', { name: 'Profilo' }).click();
  await page.getByLabel('Descrizione').fill('Profilo aggiornato dalla suite E2E');
  await page.getByRole('button', { name: 'Invia modifiche per approvazione' }).click();
  await expect(page.getByText(/attesa di approvazione/i)).toBeVisible();
});

test('admin: vede e approva la richiesta di modifica organizer', async ({ page }) => {
  await login(page, 'admin');
  await page.getByRole('button', { name: /Richieste Organizzatori/ }).click();
  await page.getByLabel('Stato').selectOption('');
  await expect(page.getByText('Fattoria Didattica Demo', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Dettaglio' }).first().click();
  await expect(page.getByText('Profilo aggiornato dalla suite E2E')).toBeVisible();
  await page.getByRole('button', { name: 'Approva modifiche' }).click();
  await expect(page.getByRole('heading', { name: 'Modifiche profilo: approved' })).toBeVisible();
});

test('restaurant/menu: API reali restano operative senza entry cliente', async ({ page, request }) => {
  await login(page, 'customer');
  await expect(page.locator('.app-header nav')).not.toContainText('Attività');
  const auth = await request.post('http://127.0.0.1:18081/api/v1/auth/login', { data: { email: 'organizer@example.test', password: 'password' } });
  const token = (await auth.json()).token;
  const created = await request.post('http://127.0.0.1:18081/api/v1/organizer/activities', {
    headers: { Authorization: `Bearer ${token}` },
    data: { name: 'Ristorante QA E2E', type: 'restaurant', description: 'Fixture isolata', booking_enabled: true },
  });
  expect(created.ok()).toBeTruthy();
  const activities = await request.get('http://127.0.0.1:18081/api/v1/organizer/activities', { headers: { Authorization: `Bearer ${token}` } });
  expect(activities.ok()).toBeTruthy();
  const payload = await activities.json();
  expect(payload.data.length).toBeGreaterThan(0);
  const menus = await request.get(`http://127.0.0.1:18081/api/v1/organizer/activities/${payload.data[0].ulid}/menus`, { headers: { Authorization: `Bearer ${token}` } });
  expect(menus.ok()).toBeTruthy();
  expect(Array.isArray((await menus.json()).data)).toBeTruthy();
});
