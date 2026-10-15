/**
 * API simulata per la demo statica su GitHub Pages.
 * Caricata solo dalla build `npm run build:demo` (VITE_DEMO=1): intercetta le chiamate a apiBase
 * e risponde con dati di esempio sul Molise, senza backend. Nessun dato viene salvato.
 */

type Json = Record<string, unknown>;

const DAY = 86400000;
const at = (days: number, hour = 19) => {
  const d = new Date(Date.now() + days * DAY);
  d.setHours(hour, 0, 0, 0);
  return d.toISOString();
};
const isoDate = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const monthLabel = (offset: number) => {
  const d = new Date();
  d.setDate(1);
  d.setMonth(d.getMonth() - offset);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};
const series = (values: number[]) => values.map((value, i) => ({ label: monthLabel(values.length - 1 - i), value }));

const publicEvents = [
  { ulid: 'ev-pasta', title: 'Laboratorio di cavatelli e sagne a pezzi', category: 'Cucina', municipality: 'Campobasso', starts_at: at(3), available_total: 8, is_free: false },
  { ulid: 'ev-tesoro', title: 'Caccia al tesoro tra le mura di Saepinum', category: 'Famiglie', municipality: 'Sepino', starts_at: at(6, 10), available_total: 14, is_free: true },
  { ulid: 'ev-vino', title: 'Tintilia al tramonto sul Trigno', category: 'Degustazioni', municipality: 'Termoli', starts_at: at(9, 18), available_total: 5, is_free: false },
  { ulid: 'ev-zampogne', title: 'Laboratorio di zampogna', category: 'Tradizioni', municipality: 'Scapoli', starts_at: at(11, 16), available_total: 10, is_free: false },
  { ulid: 'ev-campane', title: 'Visita alla Fonderia Pontificia Marinelli', category: 'Cultura', municipality: 'Agnone', starts_at: at(13, 11), available_total: 20, is_free: false },
  { ulid: 'ev-matese', title: 'Escursione all\'alba sul Matese', category: 'Natura', municipality: 'Campitello Matese', starts_at: at(15, 6), available_total: 12, is_free: true },
  { ulid: 'ev-trabucco', title: 'Cena sul trabucco', category: 'Cena esperienziale', municipality: 'Termoli', starts_at: at(18, 20), available_total: 4, is_free: false },
  { ulid: 'ev-ndocciata', title: 'Prove della \'Ndocciata', category: 'Tradizioni', municipality: 'Agnone', starts_at: at(21, 21), available_total: 30, is_free: true },
];

const successfulEvents = [
  { ulid: 'ok-cena', title: 'Cena contadina sotto le stelle', category: 'Cena esperienziale', municipality: 'Agnone', province: 'IS', average_rating: 4.8, fill_rate: 96, recommendation_rate: 94, review_count: 38, has_next_edition: true, next_edition: { title: 'Cena contadina – nuova edizione', starts_at: at(12, 20), available_total: 11 } },
  { ulid: 'ok-bosco', title: 'Passeggiata e picnic nel bosco degli abeti soprani', category: 'Natura', municipality: 'Pescopennataro', province: 'IS', average_rating: 4.7, fill_rate: 91, recommendation_rate: 89, review_count: 24 },
  { ulid: 'ok-caciocavallo', title: 'Dalla stalla al caciocavallo', category: 'Fattorie didattiche', municipality: 'Vastogirardi', province: 'IS', average_rating: 4.9, fill_rate: 100, recommendation_rate: 97, review_count: 41, has_next_edition: true, next_edition: { title: 'Dalla stalla al caciocavallo – autunno', starts_at: at(20, 10), available_total: 7 } },
  { ulid: 'ok-castello', title: 'Notte al castello Pandone', category: 'Cultura', municipality: 'Venafro', province: 'IS', average_rating: 4.6, fill_rate: 88, recommendation_rate: 90, review_count: 19 },
];

type OrgItem = { event: { ulid: string; title: string; status: string; starts_at: string }; summary: Json & { visual_status: string; starts_at: string } };
const orgItem = (ulid: string, title: string, days: number, municipality: string, cap: number, booked: number, status = 'published'): OrgItem => {
  const past = days < 0;
  return {
    event: { ulid, title, status: past ? 'completed' : status, starts_at: at(days) },
    summary: {
      visual_status: status === 'cancelled' ? 'Annullato' : past ? 'Concluso' : 'Aperto',
      municipality, starts_at: at(days),
      booked_people: booked, capacity_total: cap, available_total: Math.max(0, cap - booked),
      fill_rate: Math.round((booked / cap) * 100),
      checked_in_people: past ? booked - 1 : 0, no_show: past ? 1 : 0, average_rating: past ? 4.7 : 0,
    },
  };
};
let organizerEvents: OrgItem[] = [
  orgItem('org-1', 'Cena contadina sotto le stelle', 5, 'Agnone', 24, 19),
  orgItem('org-2', 'Laboratorio del pane di Agnone', 9, 'Agnone', 12, 7),
  orgItem('org-3', 'Merenda in fattoria per famiglie', 16, 'Agnone', 30, 11),
  orgItem('org-4', 'Degustazione di caciocavallo stagionato', -8, 'Agnone', 20, 20),
  orgItem('org-5', 'Serata di canti popolari', -21, 'Agnone', 40, 33),
];

const ideas = [
  { category: 'Famiglie', title: 'Mungitura e merenda per bambini', reason: 'Le esperienze per famiglie nel weekend in provincia di Isernia si riempiono al 94%.', recommended_date: isoDate(new Date(Date.now() + 17 * DAY)), target: 'famiglie', suggested_price_range: { min: 12, max: 18 } },
  { category: 'Cena esperienziale', title: 'Cena della transumanza', reason: 'Le cene a tema con racconto ottengono la media recensioni più alta (4,8).', recommended_date: isoDate(new Date(Date.now() + 24 * DAY)), target: 'coppie', suggested_price_range: { min: 35, max: 45 } },
  { category: 'Cultura', title: 'Bottega aperta: forgiare una campana', reason: 'Ricerca in crescita per laboratori artigiani ad Agnone negli ultimi 3 mesi.', recommended_date: isoDate(new Date(Date.now() + 31 * DAY)), target: 'turisti', suggested_price_range: { min: 15, max: 25 } },
];

const topEvents = [
  { ulid: 'top-1', success_score: 92, title: 'Dalla stalla al caciocavallo', category: 'Fattorie didattiche', territory: 'Vastogirardi (IS)', fill_rate: 100, average_rating: 4.9, price_band: '15–20 €', capacity_total: 18 },
  { ulid: 'top-2', success_score: 88, title: 'Tintilia al tramonto sul Trigno', category: 'Degustazioni', territory: 'Termoli (CB)', fill_rate: 95, average_rating: 4.8, price_band: '25–30 €', capacity_total: 16 },
  { ulid: 'top-3', success_score: 85, title: 'Caccia al tesoro a Saepinum', category: 'Famiglie', territory: 'Sepino (CB)', fill_rate: 93, average_rating: 4.7, price_band: 'gratis', capacity_total: 40 },
];

function calendar() {
  const now = new Date();
  const days = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
  return Array.from({ length: days }, (_, i) => {
    const d = new Date(now.getFullYear(), now.getMonth(), i + 1);
    const n = [0, 1, 0, 2, 0, 1, 3][(i * 5 + 3) % 7];
    const pool = publicEvents.concat(publicEvents);
    const events = Array.from({ length: n }, (_, k) => {
      const e = pool[(i + k) % publicEvents.length];
      return { time: `${17 + k}:30`, title: e.title, category: e.category, municipality: e.municipality, organizer: ['Masseria Le Querce', 'Pro Loco Sepino', 'Cantina del Trigno'][k % 3], sold_out: k === 2 };
    });
    return { date: isoDate(d), color: n === 0 ? 'green' : n === 1 ? 'yellow' : 'red', events_count: n, events };
  });
}

const adminEvents = [
  { title: 'Cena contadina sotto le stelle', organizer: 'Masseria Le Querce', category: 'Cena esperienziale', municipality: 'Agnone', date: new Date(at(5)).toLocaleDateString('it-IT'), status: 'published', booked: 19, participants: 0, average_rating: 4.8 },
  { title: 'Tintilia al tramonto sul Trigno', organizer: 'Cantina del Trigno', category: 'Degustazioni', municipality: 'Termoli', date: new Date(at(9)).toLocaleDateString('it-IT'), status: 'published', booked: 11, participants: 0, average_rating: 4.8 },
  { title: 'Cena sul trabucco', organizer: 'Trabucco Punta Ferruccio', category: 'Cena esperienziale', municipality: 'Termoli', date: new Date(at(18)).toLocaleDateString('it-IT'), status: 'sold_out', booked: 16, participants: 0, average_rating: 4.9 },
  { title: 'Laboratorio di zampogna', organizer: 'Circolo della Zampogna', category: 'Tradizioni', municipality: 'Scapoli', date: new Date(at(11)).toLocaleDateString('it-IT'), status: 'pending_review', booked: 0, participants: 0, average_rating: 0 },
  { title: 'Caccia al tesoro a Saepinum', organizer: 'Pro Loco Sepino', category: 'Famiglie', municipality: 'Sepino', date: new Date(at(6)).toLocaleDateString('it-IT'), status: 'published', booked: 26, participants: 0, average_rating: 4.7 },
  { title: 'Dalla stalla al caciocavallo', organizer: 'Masseria Le Querce', category: 'Fattorie didattiche', municipality: 'Vastogirardi', date: new Date(at(-8)).toLocaleDateString('it-IT'), status: 'completed', booked: 18, participants: 17, average_rating: 4.9 },
  { title: 'Concerto in piazza annullato per maltempo', organizer: 'Pro Loco Sepino', category: 'Musica', municipality: 'Sepino', date: new Date(at(-3)).toLocaleDateString('it-IT'), status: 'cancelled', booked: 42, participants: 0, average_rating: 0 },
  { title: 'Notte al castello Pandone', organizer: 'Museo Castello Pandone', category: 'Cultura', municipality: 'Venafro', date: new Date(at(-15)).toLocaleDateString('it-IT'), status: 'completed', booked: 35, participants: 33, average_rating: 4.6 },
];

const organizers = [
  { name: 'Masseria Le Querce', municipality: 'Agnone', province: 'IS', events_created: 14, future_events: 3, completed_events: 10, cancelled_events: 1, bookings_received: 312, average_rating: 4.8, followers: 186 },
  { name: 'Cantina del Trigno', municipality: 'Termoli', province: 'CB', events_created: 9, future_events: 2, completed_events: 7, cancelled_events: 0, bookings_received: 204, average_rating: 4.7, followers: 141 },
  { name: 'Pro Loco Sepino', municipality: 'Sepino', province: 'CB', events_created: 11, future_events: 2, completed_events: 8, cancelled_events: 1, bookings_received: 389, average_rating: 4.6, followers: 230 },
  { name: 'Museo Castello Pandone', municipality: 'Venafro', province: 'IS', events_created: 6, future_events: 1, completed_events: 5, cancelled_events: 0, bookings_received: 158, average_rating: 4.6, followers: 97 },
  { name: 'Trabucco Punta Ferruccio', municipality: 'Termoli', province: 'CB', events_created: 5, future_events: 2, completed_events: 3, cancelled_events: 0, bookings_received: 96, average_rating: 4.9, followers: 120 },
  { name: 'Circolo della Zampogna', municipality: 'Scapoli', province: 'IS', events_created: 3, future_events: 1, completed_events: 2, cancelled_events: 0, bookings_received: 41, average_rating: 4.5, followers: 58 },
];

const reviews = [
  { rating: 5, status: 'published', comment: 'Serata magica, cibo genuino e padroni di casa accoglienti.', user: 'Lucia R.', event: 'Cena contadina sotto le stelle', organizer: 'Masseria Le Querce' },
  { rating: 5, status: 'published', comment: 'I bambini si sono divertiti tantissimo tra le rovine.', user: 'Paolo D.', event: 'Caccia al tesoro a Saepinum', organizer: 'Pro Loco Sepino' },
  { rating: 4, status: 'published', comment: 'Vini ottimi, tramonto spettacolare. Un po\' di attesa all\'inizio.', user: 'Chiara M.', event: 'Tintilia al tramonto', organizer: 'Cantina del Trigno' },
  { rating: 2, status: 'hidden', comment: 'Contenuto segnalato e nascosto dalla moderazione.', user: 'Utente', event: 'Notte al castello Pandone', organizer: 'Museo Castello Pandone' },
  { rating: 5, status: 'published', comment: 'Esperienza autentica, torneremo in autunno.', user: 'Marco T.', event: 'Dalla stalla al caciocavallo', organizer: 'Masseria Le Querce' },
];

const overview = {
  kpis: { events_created_today: 3, bookings_today: 27, events_scheduled: 21, organizers_registered: 48, customers_registered: 1936, events_published: 64, events_completed: 112, events_cancelled: 4, bookings_total: 5240, participants_total: 4718 },
  charts: {
    events_by_month: series([9, 12, 14, 18, 22, 21]),
    bookings_by_month: series([410, 520, 640, 790, 1010, 960]),
    organizer_growth: series([28, 31, 35, 39, 44, 48]),
    customer_growth: series([980, 1150, 1340, 1520, 1760, 1936]),
    events_by_category: [{ label: 'Cena esperienziale', value: 31 }, { label: 'Natura', value: 26 }, { label: 'Famiglie', value: 22 }, { label: 'Degustazioni', value: 18 }, { label: 'Cultura', value: 15 }, { label: 'Tradizioni', value: 9 }],
  },
  latest_events: adminEvents,
  events: adminEvents,
  organizers,
  customers: [
    { name: 'Lucia Romano', email: 'lucia.r@example.test', municipality: 'Campobasso', events_attended: 9, reviews_left: 7, cancelled_events: 0, favorites: 12, followed_organizers: 4, registered_at: '2026-02-11' },
    { name: 'Paolo Di Iorio', email: 'paolo.d@example.test', municipality: 'Isernia', events_attended: 5, reviews_left: 3, cancelled_events: 1, favorites: 6, followed_organizers: 2, registered_at: '2026-04-03' },
    { name: 'Chiara Mancini', email: 'chiara.m@example.test', municipality: 'Termoli', events_attended: 7, reviews_left: 6, cancelled_events: 0, favorites: 9, followed_organizers: 5, registered_at: '2026-03-22' },
    { name: 'Marco Testa', email: 'marco.t@example.test', municipality: 'Agnone', events_attended: 3, reviews_left: 2, cancelled_events: 0, favorites: 4, followed_organizers: 1, registered_at: '2026-06-30' },
  ],
  reviews,
  latest_reviews: reviews,
  categories: [
    { icon: 'CE', name: 'Cena esperienziale', color: '#b45309', events_count: 31, organizers_count: 12 },
    { icon: 'NA', name: 'Natura', color: '#15803d', events_count: 26, organizers_count: 9 },
    { icon: 'FA', name: 'Famiglie', color: '#0f766e', events_count: 22, organizers_count: 11 },
    { icon: 'DE', name: 'Degustazioni', color: '#9b2f1e', events_count: 18, organizers_count: 7 },
    { icon: 'CU', name: 'Cultura', color: '#4338ca', events_count: 15, organizers_count: 6 },
    { icon: 'TR', name: 'Tradizioni', color: '#a16207', events_count: 9, organizers_count: 4 },
  ],
  territory: {
    regions: [{ name: 'Molise', provinces_count: 2, events_count: 121 }],
    municipalities: [
      { name: 'Termoli', province: 'CB', events_count: 24 }, { name: 'Agnone', province: 'IS', events_count: 21 }, { name: 'Campobasso', province: 'CB', events_count: 18 },
      { name: 'Sepino', province: 'CB', events_count: 14 }, { name: 'Isernia', province: 'IS', events_count: 12 }, { name: 'Venafro', province: 'IS', events_count: 9 },
    ],
  },
  subscriptions: [
    { organizer: 'Masseria Le Querce', plan: 'Professional', renewal: '2026-11-02', status: 'active', payments: 'Regolari' },
    { organizer: 'Cantina del Trigno', plan: 'Starter', renewal: '2026-10-28', status: 'active', payments: 'Regolari' },
    { organizer: 'Pro Loco Sepino', plan: 'Enterprise', renewal: '2027-01-15', status: 'active', payments: 'Annuale' },
    { organizer: 'Circolo della Zampogna', plan: 'Free Trial', renewal: '2026-10-30', status: 'trial', payments: '—' },
  ],
  audit_logs: [
    { action: 'event.approved', subject_type: 'Event', subject_id: '01J9ZK…Q3', created_at: '2026-10-15 09:12' },
    { action: 'organizer.verified', subject_type: 'OrganizerProfile', subject_id: '01J9ZH…7B', created_at: '2026-10-14 17:40' },
    { action: 'review.hidden', subject_type: 'Review', subject_id: '01J9YT…2C', created_at: '2026-10-14 11:05' },
    { action: 'event.rejected', subject_type: 'Event', subject_id: '01J9YR…M1', created_at: '2026-10-13 15:22' },
  ],
  latest_registrations: {
    organizers: [{ name: 'Trabucco Punta Ferruccio', municipality: 'Termoli' }, { name: 'Circolo della Zampogna', municipality: 'Scapoli' }],
    customers: [{ name: 'Marco Testa', registered_at: '2026-06-30' }, { name: 'Giulia Fabrizio', registered_at: '2026-10-14' }],
  },
};

const users: Record<string, { role: string; redirect_to: string }> = {
  'customer@example.test': { role: 'customer', redirect_to: '/customer/dashboard' },
  'organizer@example.test': { role: 'organizer', redirect_to: '/organizer/dashboard' },
  'admin@example.test': { role: 'admin', redirect_to: '/admin/dashboard' },
};

function route(method: string, path: string, query: URLSearchParams, body: Json): unknown {
  if (method === 'POST' && path === '/auth/login') {
    const u = users[String(body.email)];
    return u ? { token: `demo-${u.role}`, user: { role: u.role }, redirect_to: u.redirect_to } : null;
  }
  if (method === 'POST') {
    const cancel = path.match(/^\/organizer\/events\/([^/]+)\/cancel$/);
    if (cancel) {
      organizerEvents = organizerEvents.map((it) => it.event.ulid === cancel[1]
        ? { event: { ...it.event, status: 'cancelled' }, summary: { ...it.summary, visual_status: 'Annullato' } }
        : it);
      return {};
    }
    const use = path.match(/^\/organizer\/opportunities\/[^/]+\/use$/);
    if (use) {
      const idea = ideas[Number(body.index) || 0];
      organizerEvents = [orgItem(`org-${Date.now()}`, `${idea.title} (bozza)`, 17, 'Agnone', 20, 0), ...organizerEvents];
      return {};
    }
    const inspire = path.match(/^\/organizer\/top-events\/([^/]+)\/inspire$/);
    if (inspire) {
      const t = topEvents.find((e) => e.ulid === inspire[1]);
      organizerEvents = [orgItem(`org-${Date.now()}`, `Ispirato a: ${t?.title ?? 'evento'} (bozza)`, 25, 'Agnone', t?.capacity_total ?? 20, 0), ...organizerEvents];
      return {};
    }
    if (/^\/customer\/successful-events\/[^/]+\/notify-similar$/.test(path)) return undefined;
  }
  switch (path) {
    case '/customer/dashboard':
      return { data: { bookings_count: 2, favorites_count: 5, notifications_unread: 3, recommended_events: publicEvents.slice(0, 6) } };
    case '/events':
      return { data: { data: publicEvents } };
    case '/customer/successful-events':
      return { data: successfulEvents };
    case '/organizer/dashboard':
      return { data: { active_experiences: organizerEvents.filter((e) => e.summary.visual_status === 'Aperto').length, bookings_total: 312, followers: 186, available_places: 49, bi: { average_rating: 4.8, review_analytics: { reviews_count: 87 }, category_performance: [{ fill_rate: 94 }] } } };
    case '/organizer/events': {
      const status = query.get('status');
      const map: Record<string, (e: OrgItem) => boolean> = {
        open: (e) => e.summary.visual_status === 'Aperto',
        closed: (e) => e.summary.visual_status === 'Concluso',
        cancelled: (e) => e.summary.visual_status === 'Annullato',
        future: (e) => new Date(e.event.starts_at).getTime() > Date.now(),
      };
      const list = status && map[status] ? organizerEvents.filter(map[status]) : organizerEvents;
      return { data: [...list].sort((a, b) => a.event.starts_at.localeCompare(b.event.starts_at)) };
    }
    case '/organizer/reviews/events':
      return { data: [
        { event: { title: 'Degustazione di caciocavallo stagionato' }, statistics: { reviews_count: 17, average_rating: 4.9, recommendation_rate: 97 }, latest_review: { comment: 'Spiegazioni chiare e assaggi generosi.' } },
        { event: { title: 'Serata di canti popolari' }, statistics: { reviews_count: 26, average_rating: 4.7, recommendation_rate: 92 }, latest_review: { comment: 'Atmosfera bellissima, si sentiva la tradizione.' } },
        { event: { title: 'Cena contadina sotto le stelle' }, statistics: { reviews_count: 38, average_rating: 4.8, recommendation_rate: 94 }, latest_review: { comment: 'Tutto preparato con i prodotti della masseria.' } },
      ] };
    case '/organizer/calendar/availability':
      return { data: calendar() };
    case '/organizer/opportunities':
      return { suggestions: { ulid: 'sugg-demo', suggestions: { suggestions: ideas } }, top_events: topEvents };
    case '/admin/dashboard':
      return { data: { customers: overview.kpis.customers_registered } };
    case '/admin/overview':
      return { data: overview };
    case '/admin/events':
      return { data: { data: adminEvents }, stats: { published: overview.kpis.events_published } };
    case '/admin/organizers':
      return { data: organizers };
    case '/admin/rankings/events':
      return { data: [...adminEvents].filter((e) => e.average_rating > 0).sort((a, b) => b.average_rating - a.average_rating).map((e, i) => ({ event: { title: e.title, organizer_profile: { name: e.organizer } }, final_score: 95 - i * 4 })) };
    case '/admin/rankings/organizers':
      return { data: [...organizers].sort((a, b) => b.bookings_received - a.bookings_received).map((o) => ({ organizer_profile: { name: o.name }, events_created: o.events_created, total_participants: Math.round(o.bookings_received * 0.92), average_rating: o.average_rating, follower_count: o.followers })) };
    default:
      return null;
  }
}

function addBanner(): void {
  const bar = document.createElement('a');
  bar.href = '../';
  bar.textContent = 'Demo con dati di esempio · torna alla presentazione di MòVi';
  bar.setAttribute('style', [
    'position:fixed', 'left:50%', 'bottom:calc(env(safe-area-inset-bottom,0px) + 14px)', 'transform:translateX(-50%)', 'z-index:9999',
    'padding:8px 16px', 'border-radius:999px', 'background:#172126', 'color:#f6f4ee', 'font:600 13px/1.3 system-ui,sans-serif',
    'text-decoration:none', 'box-shadow:0 8px 24px rgba(0,0,0,.25)', 'white-space:nowrap', 'max-width:calc(100% - 32px)', 'overflow:hidden', 'text-overflow:ellipsis',
  ].join(';'));
  document.body.append(bar);
}

export function installDemoApi(apiBase: string): void {
  const realFetch = window.fetch.bind(window);
  window.fetch = async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    if (!url.startsWith(apiBase)) return realFetch(input, init);
    await new Promise((resolve) => setTimeout(resolve, 220));
    const [path, qs = ''] = url.slice(apiBase.length).split('?');
    let body: Json = {};
    try { body = init?.body ? JSON.parse(String(init.body)) as Json : {}; } catch { body = {}; }
    const result = route((init?.method ?? 'GET').toUpperCase(), path, new URLSearchParams(qs), body);
    if (result === undefined) return new Response(null, { status: 204 });
    if (result === null) return new Response(JSON.stringify({ message: 'Non disponibile nella demo.', errors: {}, request_id: 'demo' }), { status: 404, headers: { 'Content-Type': 'application/json' } });
    return new Response(JSON.stringify(result), { status: 200, headers: { 'Content-Type': 'application/json' } });
  };
  if (document.body) addBanner();
  else document.addEventListener('DOMContentLoaded', addBanner);
}
