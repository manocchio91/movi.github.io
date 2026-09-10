import { expect, test, type Page } from '@playwright/test';

function crc32(data: Buffer) {
  let crc = 0xffffffff;
  for (const byte of data) {
    crc ^= byte;
    for (let bit = 0; bit < 8; bit += 1) crc = (crc >>> 1) ^ (0xedb88320 & -(crc & 1));
  }
  return (crc ^ 0xffffffff) >>> 0;
}

function officeArchive(entries: Record<string, string>) {
  const locals: Buffer[] = [];
  const centrals: Buffer[] = [];
  let offset = 0;
  for (const [name, contents] of Object.entries(entries)) {
    const filename = Buffer.from(name);
    const data = Buffer.from(contents);
    const checksum = crc32(data);
    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0); local.writeUInt16LE(20, 4); local.writeUInt32LE(checksum, 14); local.writeUInt32LE(data.length, 18); local.writeUInt32LE(data.length, 22); local.writeUInt16LE(filename.length, 26);
    locals.push(local, filename, data);
    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0); central.writeUInt16LE(20, 4); central.writeUInt16LE(20, 6); central.writeUInt32LE(checksum, 16); central.writeUInt32LE(data.length, 20); central.writeUInt32LE(data.length, 24); central.writeUInt16LE(filename.length, 28); central.writeUInt32LE(offset, 42);
    centrals.push(central, filename);
    offset += local.length + filename.length + data.length;
  }
  const centralSize = centrals.reduce((sum, part) => sum + part.length, 0);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0); end.writeUInt16LE(Object.keys(entries).length, 8); end.writeUInt16LE(Object.keys(entries).length, 10); end.writeUInt32LE(centralSize, 12); end.writeUInt32LE(offset, 16);
  return Buffer.concat([...locals, ...centrals, end]);
}

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

test('MòVi Manager: calendario, menu, import e prenotazioni locale sono operativi', async ({ page, request }) => {
  const auth = await request.post('http://127.0.0.1:18081/api/v1/auth/login', { data: { email: 'organizer@example.test', password: 'password' } });
  const token = (await auth.json()).token;
  const headers = { Authorization: `Bearer ${token}` };
  const activityResponse = await request.post('http://127.0.0.1:18081/api/v1/organizer/activities', { headers, data: { name: 'Ristorante Manager E2E', type: 'restaurant', description: 'Fixture isolata Manager', status: 'published', booking_enabled: true } });
  expect(activityResponse.ok()).toBeTruthy();
  const activity = (await activityResponse.json()).data;
  const dinnerResponse = await request.post(`http://127.0.0.1:18081/api/v1/organizer/activities/${activity.ulid}/services`, { headers, data: { name: 'Cena', type: 'dinner', active: true, booking_enabled: true, min_party_size: 1, max_party_size: 8, minimum_advance_minutes: 0, indicative_duration_minutes: 90 } });
  expect(dinnerResponse.ok()).toBeTruthy();
  const dinner = (await dinnerResponse.json()).data;

  await login(page, 'organizer');
  const sidebar = page.locator('.app-header nav');
  await expect(sidebar).toContainText('Calendario');
  await expect(sidebar).toContainText('Menu');
  await expect(page.getByRole('heading', { name: 'La regia della tua attività.' })).toBeVisible();

  await page.getByRole('button', { name: 'Calendario', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Calendario disponibilità' })).toBeVisible();
  const calendar = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Ristorante Manager E2E' }) });
  await calendar.getByLabel('Dal', { exact: true }).fill('2026-10-15');
  await calendar.getByLabel('Al', { exact: true }).fill('2026-11-30');
  await calendar.getByLabel('Turno').selectOption('dinner');
  await calendar.getByLabel('Numero coperti MòVi').fill('20');
  await calendar.getByLabel('Lunedì').check();
  await calendar.getByLabel('Mercoledì').check();
  await calendar.getByLabel('Venerdì').check();
  await calendar.getByRole('button', { name: 'Salva disponibilità' }).click();
  await expect(calendar.getByRole('status')).toContainText('Disponibilita salvate.');
  await page.getByRole('button', { name: 'Dashboard', exact: true }).click();
  await page.getByRole('button', { name: 'Calendario', exact: true }).click();
  await expect(page.getByText(/20 coperti · 2026-10-15 → 2026-11-30/).first()).toBeVisible();

  await page.getByRole('button', { name: 'Menu', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Menu', exact: true })).toBeVisible();
  const menuWorkspace = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Ristorante Manager E2E' }) });
  await menuWorkspace.getByLabel('Nome menu', { exact: true }).fill('Menu QA manuale');
  await menuWorkspace.getByRole('button', { name: 'Crea menu' }).click();
  await menuWorkspace.getByPlaceholder('Nuova sezione').fill('Primi');
  await menuWorkspace.getByRole('button', { name: 'Aggiungi sezione' }).click();
  await menuWorkspace.getByPlaceholder('Nuovo piatto').fill('Cavatelli QA');
  await menuWorkspace.getByPlaceholder('Descrizione').fill('Piatto fixture isolato');
  await menuWorkspace.getByPlaceholder('Prezzo €').fill('12.50');
  await menuWorkspace.getByRole('button', { name: 'Aggiungi piatto' }).click();
  await expect(menuWorkspace.getByText('Cavatelli QA').first()).toBeVisible();

  const docxContentTypes = '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';
  const rootRels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
  const docx = officeArchive({ '[Content_Types].xml': docxContentTypes, '_rels/.rels': rootRels, 'word/document.xml': '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>ANTIPASTI</w:t></w:r></w:p><w:p><w:r><w:t>Bruschetta .... 8,50</w:t></w:r></w:p></w:body></w:document>' });
  await menuWorkspace.getByLabel('Carica menu DOCX/XLSX').setInputFiles({ name: 'menu.docx', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', buffer: docx });
  await menuWorkspace.getByRole('button', { name: 'GENERA ANTEPRIMA' }).click();
  await expect(menuWorkspace.getByRole('heading', { name: 'Anteprima import' })).toBeVisible();
  expect(await menuWorkspace.locator('input').evaluateAll(inputs => inputs.some(input => (input as HTMLInputElement).value.startsWith('Bruschetta')))).toBeTruthy();
  await menuWorkspace.getByRole('button', { name: 'CONFERMA IMPORT' }).click();
  await expect(menuWorkspace.getByText('Menu importato in bozza.')).toBeVisible();

  const xlsxContentTypes = '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>';
  const xlsx = officeArchive({ '[Content_Types].xml': xlsxContentTypes, '_rels/.rels': rootRels.replace('word/document.xml', 'xl/workbook.xml'), 'xl/workbook.xml': '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Menu" sheetId="1"/></sheets></workbook>', 'xl/sharedStrings.xml': '<sst><si><t>Sezione</t></si><si><t>Piatto</t></si><si><t>Prezzo</t></si><si><t>Secondi</t></si><si><t>Tagliata QA</t></si></sst>', 'xl/worksheets/sheet1.xml': '<worksheet><sheetData><row><c t="s"><v>0</v></c><c t="s"><v>1</v></c><c t="s"><v>2</v></c></row><row><c t="s"><v>3</v></c><c t="s"><v>4</v></c><c><v>18.00</v></c></row></sheetData></worksheet>' });
  await menuWorkspace.getByLabel('Carica menu DOCX/XLSX').setInputFiles({ name: 'menu.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: xlsx });
  await menuWorkspace.getByRole('button', { name: 'GENERA ANTEPRIMA' }).click();
  await expect(menuWorkspace.getByRole('heading', { name: 'Anteprima import' })).toBeVisible();
  await menuWorkspace.getByRole('button', { name: 'CONFERMA IMPORT' }).click();

  const menusResponse = await request.get(`http://127.0.0.1:18081/api/v1/organizer/activities/${activity.ulid}/menus`, { headers });
  const manualMenu = (await menusResponse.json()).data.find((menu: { name: string }) => menu.name === 'Menu QA manuale');
  const item = manualMenu.sections[0].items[0];
  await request.post(`http://127.0.0.1:18081/api/v1/organizer/activities/${activity.ulid}/menus/${manualMenu.ulid}/publish`, { headers });
  await request.put(`http://127.0.0.1:18081/api/v1/organizer/activities/${activity.ulid}/services/${dinner.ulid}`, { headers, data: { name: 'Cena', type: 'dinner', active: true, booking_enabled: true, min_party_size: 1, max_party_size: 8, minimum_advance_minutes: 0, indicative_duration_minutes: 90, preorder_enabled: true, preorder_edit_deadline_minutes: 120, preorder_menu_ulid: manualMenu.ulid } });
  const customerAuth = await request.post('http://127.0.0.1:18081/api/v1/auth/login', { data: { email: 'customer@example.test', password: 'password' } });
  const customerHeaders = { Authorization: `Bearer ${(await customerAuth.json()).token}` };
  const availabilityResponse = await request.get(`http://127.0.0.1:18081/api/v1/activities/${activity.ulid}/services/${dinner.ulid}/availability?date=2026-10-16`, { headers: customerHeaders });
  const availableSlot = (await availabilityResponse.json()).data[0];
  expect(availableSlot.available).toBe(20);
  const reservationResponse = await request.post(`http://127.0.0.1:18081/api/v1/activities/${activity.ulid}/reservations`, { headers: { ...customerHeaders, 'Idempotency-Key': 'movi-manager-e2e-reservation' }, data: { service_ulid: dinner.ulid, starts_at: availableSlot.starts_at, party_size: 2, contact_phone: '+393331234567' } });
  const reservationPayload = await reservationResponse.json();
  expect(reservationResponse.ok(), JSON.stringify(reservationPayload)).toBeTruthy();
  const reservation = reservationPayload.data;
  const preorderResponse = await request.put(`http://127.0.0.1:18081/api/v1/me/reservations/${reservation.ulid}/preorder`, { headers: customerHeaders, data: { items: [{ menu_item_ulid: item.ulid, quantity: 2 }] } });
  expect(preorderResponse.ok()).toBeTruthy();

  await page.getByRole('button', { name: 'Prenotazioni', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Prenotazioni locale' })).toBeVisible();
  await expect(page.getByText(/Cliente Demo.*Cena.*2 persone.*confirmed/)).toBeVisible();
  await expect(page.getByText(/Cavatelli QA × 2/)).toBeVisible();
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
