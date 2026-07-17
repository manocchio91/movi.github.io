import { computed, defineComponent, h, ref } from 'vue';

type Role = 'guest' | 'customer' | 'organizer' | 'admin';
type AppRoute =
  | '/login'
  | '/customer/dashboard'
  | '/customer/explore'
  | '/customer/successful-events'
  | '/customer/bookings'
  | '/customer/preferences'
  | '/organizer/dashboard'
  | '/organizer/events'
  | '/organizer/create-event'
  | '/organizer/reviews'
  | '/organizer/opportunities'
  | '/admin/dashboard'
  | '/admin/organizers'
  | '/admin/events'
  | '/admin/customers'
  | '/admin/rankings'
  | '/admin/reviews'
  | '/admin/categories'
  | '/admin/territory'
  | '/admin/subscriptions'
  | '/admin/statistics'
  | '/admin/audit'
  | '/admin/settings'
  | '/unauthorized';

type DemoUser = {
  role: Exclude<Role, 'guest'>;
  name: string;
  email: string;
  password: string;
  redirect_to: AppRoute;
};

const apiBase = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1';

const demoUsers: DemoUser[] = [
  { role: 'customer', name: 'Cliente Demo', email: 'customer@example.test', password: 'password', redirect_to: '/customer/dashboard' },
  { role: 'organizer', name: 'Organizzatore Demo', email: 'organizer@example.test', password: 'password', redirect_to: '/organizer/dashboard' },
  { role: 'admin', name: 'Admin Molise', email: 'admin@example.test', password: 'password', redirect_to: '/admin/dashboard' },
];

const customerMenu = [
  ['Home', '/customer/dashboard'],
  ['Tutti gli Eventi', '/customer/explore'],
  ['Eventi di Successo', '/customer/successful-events'],
  ['Le mie prenotazioni', '/customer/bookings'],
  ['Preferenze', '/customer/preferences'],
] as const;

const organizerMenu = [
  ['Dashboard', '/organizer/dashboard'],
  ['I miei eventi', '/organizer/events'],
  ['Crea evento', '/organizer/create-event'],
  ['Recensioni', '/organizer/reviews'],
  ['Opportunita', '/organizer/opportunities'],
] as const;

const adminMenu = [
  ['Dashboard', '/admin/dashboard'],
  ['Eventi', '/admin/events'],
  ['Organizzatori', '/admin/organizers'],
  ['Clienti', '/admin/customers'],
  ['Classifiche', '/admin/rankings'],
  ['Recensioni', '/admin/reviews'],
  ['Categorie', '/admin/categories'],
  ['Territorio', '/admin/territory'],
  ['Abbonamenti', '/admin/subscriptions'],
  ['Statistiche', '/admin/statistics'],
  ['Audit Log', '/admin/audit'],
  ['Impostazioni', '/admin/settings'],
] as const;

const canAccess = (role: Role, route: AppRoute) => {
  if (route === '/login' || route === '/unauthorized') return true;
  if (route.startsWith('/customer')) return role === 'customer';
  if (route.startsWith('/organizer')) return role === 'organizer';
  if (route.startsWith('/admin')) return role === 'admin';

  return false;
};

const customerMobileDashboardDemo = {
  bookings_count: 2,
  favorites_count: 5,
  notifications_unread: 3,
  recommended_events: [
    {
      ulid: 'demo-pasta',
      title: 'Laboratorio di pasta fresca',
      category: 'Cucina',
      municipality: 'Campobasso',
      starts_at: new Date(Date.now() + 86400000 * 3).toISOString(),
      available_total: 8,
      is_free: false,
    },
    {
      ulid: 'demo-bimbi',
      title: 'Caccia al tesoro nel borgo',
      category: 'Famiglie',
      municipality: 'Sepino',
      starts_at: new Date(Date.now() + 86400000 * 6).toISOString(),
      available_total: 14,
      is_free: true,
    },
    {
      ulid: 'demo-vino',
      title: 'Degustazione tramonto',
      category: 'Degustazioni',
      municipality: 'Termoli',
      starts_at: new Date(Date.now() + 86400000 * 9).toISOString(),
      available_total: 5,
      is_free: false,
    },
  ],
};

const successfulEventsDemo = [
  {
    ulid: 'success-1',
    title: 'Cena contadina sotto le stelle',
    category: 'Cena esperienziale',
    municipality: 'Agnone',
    province: 'IS',
    average_rating: 4.8,
    fill_rate: 96,
    recommendation_rate: 94,
    review_count: 38,
    has_next_edition: true,
    next_edition: {
      title: 'Cena contadina - nuova edizione',
      starts_at: new Date(Date.now() + 86400000 * 12).toISOString(),
      available_total: 11,
    },
  },
  {
    ulid: 'success-2',
    title: 'Passeggiata e picnic nel bosco',
    category: 'Natura',
    municipality: 'Capracotta',
    province: 'IS',
    average_rating: 4.7,
    fill_rate: 91,
    recommendation_rate: 89,
    review_count: 24,
  },
];

export default defineComponent({
  name: 'App',
  setup() {
    const customerMobilePreview = window.location.pathname.includes('cliente-mobile') || window.location.search.includes('cliente=mobile');
    const currentUser = ref<DemoUser | null>(customerMobilePreview ? demoUsers[0] : null);
    const token = ref<string | null>(null);
    const route = ref<AppRoute>(customerMobilePreview ? '/customer/dashboard' : '/login');
    const loading = ref(false);
    const apiError = ref('');
    const organizerDashboard = ref<any>(null);
    const organizerEvents = ref<any[]>([]);
    const reviewEvents = ref<any[]>([]);
    const calendarDays = ref<any[]>([]);
    const selectedDay = ref<any | null>(null);
    const opportunities = ref<any>(null);
    const customerDashboard = ref<any>(null);
    const allCustomerEvents = ref<any[]>([]);
    const successfulEvents = ref<any[]>([]);
    const adminDashboard = ref<any>(null);
    const adminOverview = ref<any>(null);
    const adminEvents = ref<any[]>([]);
    const adminEventStats = ref<any>(null);
    const adminOrganizers = ref<any[]>([]);
    const adminEventRankings = ref<any[]>([]);
    const adminOrganizerRankings = ref<any[]>([]);

    if (customerMobilePreview) {
      customerDashboard.value = customerMobileDashboardDemo;
      allCustomerEvents.value = customerMobileDashboardDemo.recommended_events;
      successfulEvents.value = successfulEventsDemo;
    }

    const role = computed<Role>(() => currentUser.value?.role ?? 'guest');

    const api = async (path: string, options: RequestInit = {}) => {
      if (!token.value) return null;

      const response = await fetch(`${apiBase}${path}`, {
        ...options,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token.value}`,
          ...(options.headers ?? {}),
        },
      });

      if (!response.ok) {
        throw new Error(`API ${path}: ${response.status}`);
      }

      return response.status === 204 ? null : response.json();
    };

    const loadOrganizerData = async () => {
      if (role.value !== 'organizer' || !token.value) return;

      loading.value = true;
      apiError.value = '';
      try {
        const [dashboard, events, reviewGroups, availability, opportunityPayload] = await Promise.all([
          api('/organizer/dashboard'),
          api('/organizer/events?sort=asc'),
          api('/organizer/reviews/events'),
          api(`/organizer/calendar/availability?month=${new Date().getMonth() + 1}&year=${new Date().getFullYear()}`),
          api('/organizer/opportunities'),
        ]);
        organizerDashboard.value = dashboard?.data ?? null;
        organizerEvents.value = events?.data ?? [];
        reviewEvents.value = reviewGroups?.data ?? [];
        calendarDays.value = availability?.data ?? [];
        selectedDay.value = calendarDays.value.find((day) => day.events_count > 0) ?? calendarDays.value[0] ?? null;
        opportunities.value = opportunityPayload ?? null;
      } catch (error) {
        apiError.value = error instanceof Error ? error.message : 'API non disponibile';
      } finally {
        loading.value = false;
      }
    };

    const loadCustomerData = async () => {
      if (role.value !== 'customer' || !token.value) return;

      loading.value = true;
      apiError.value = '';
      try {
        const [dashboard, eventPayload, successPayload] = await Promise.all([
          api('/customer/dashboard'),
          api('/events'),
          api('/customer/successful-events'),
        ]);
        customerDashboard.value = dashboard?.data ?? null;
        allCustomerEvents.value = eventPayload?.data?.data ?? eventPayload?.data ?? [];
        successfulEvents.value = successPayload?.data ?? [];
      } catch (error) {
        apiError.value = error instanceof Error ? error.message : 'API customer non disponibile';
      } finally {
        loading.value = false;
      }
    };

    const loadAdminData = async () => {
      if (role.value !== 'admin' || !token.value) return;

      loading.value = true;
      apiError.value = '';
      try {
        const [dashboard, overview, events, organizers, eventRankings, organizerRankings] = await Promise.all([
          api('/admin/dashboard'),
          api('/admin/overview'),
          api('/admin/events'),
          api('/admin/organizers'),
          api('/admin/rankings/events'),
          api('/admin/rankings/organizers'),
        ]);
        adminDashboard.value = dashboard?.data ?? null;
        adminOverview.value = overview?.data ?? null;
        const eventPayload = events;
        const organizerPayload = organizers;
        adminEvents.value = adminOverview.value?.events ?? eventPayload?.data?.data ?? eventPayload?.data ?? [];
        adminEventStats.value = eventPayload?.stats ?? null;
        adminOrganizers.value = adminOverview.value?.organizers ?? organizerPayload?.data ?? [];
        adminEventRankings.value = eventRankings?.data ?? [];
        adminOrganizerRankings.value = organizerRankings?.data ?? [];
      } catch (error) {
        apiError.value = error instanceof Error ? error.message : 'API admin non disponibile';
      } finally {
        loading.value = false;
      }
    };

    const go = async (nextRoute: AppRoute) => {
      route.value = canAccess(role.value, nextRoute) ? nextRoute : '/unauthorized';
      if (route.value.startsWith('/organizer')) await loadOrganizerData();
      if (route.value.startsWith('/customer')) await loadCustomerData();
      if (route.value.startsWith('/admin')) await loadAdminData();
    };

    const login = async (user: DemoUser) => {
      loading.value = true;
      apiError.value = '';
      try {
        const response = await fetch(`${apiBase}/auth/login`, {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: user.email, password: user.password }),
        });
        if (!response.ok) throw new Error(`Login API: ${response.status}`);
        const payload = await response.json();
        token.value = payload.token;
        currentUser.value = { ...user, role: payload.user.role, redirect_to: payload.redirect_to };
        route.value = payload.redirect_to;
        await loadOrganizerData();
        await loadCustomerData();
        await loadAdminData();
      } catch (error) {
        currentUser.value = user;
        route.value = user.redirect_to;
        apiError.value = error instanceof Error ? `${error.message}. Avvia il backend per vedere dati DB reali.` : 'Backend non disponibile.';
      } finally {
        loading.value = false;
      }
    };

    const logout = () => {
      currentUser.value = null;
      token.value = null;
      route.value = '/login';
      apiError.value = '';
      organizerDashboard.value = null;
      organizerEvents.value = [];
      reviewEvents.value = [];
      calendarDays.value = [];
      opportunities.value = null;
      customerDashboard.value = null;
      allCustomerEvents.value = [];
      successfulEvents.value = [];
      adminDashboard.value = null;
      adminOverview.value = null;
      adminEvents.value = [];
      adminEventStats.value = null;
      adminOrganizers.value = [];
      adminEventRankings.value = [];
      adminOrganizerRankings.value = [];
    };

    const useSuggestion = async (suggestionUlid: string, index: number) => {
      await api(`/organizer/opportunities/${suggestionUlid}/use`, {
        method: 'POST',
        body: JSON.stringify({ index }),
      });
      await go('/organizer/events');
    };

    const inspireFromEvent = async (eventUlid: string) => {
      await api(`/organizer/top-events/${eventUlid}/inspire`, { method: 'POST' });
      await go('/organizer/events');
    };

    const cancelOrganizerEvent = async (eventUlid: string) => {
      await api(`/organizer/events/${eventUlid}/cancel`, {
        method: 'POST',
        body: JSON.stringify({
          reason_code: 'ORGANIZER_REQUEST',
          reason: 'Annullamento richiesto dall organizzatore dalla dashboard.',
        }),
      });
      await loadOrganizerData();
    };

    const notifySimilar = async (eventUlid: string) => {
      await api(`/customer/successful-events/${eventUlid}/notify-similar`, { method: 'POST' });
      await loadCustomerData();
    };

    const card = (title: string, value: string, note: string) => h('article', { class: 'role-card' }, [
      h('span', title),
      h('strong', value),
      h('small', note),
    ]);

    const emptyState = (message = 'Nessun dato disponibile dal database.') => h('article', { class: 'wide-panel empty-state' }, [
      h('strong', loading.value ? 'Caricamento...' : message),
      apiError.value ? h('p', apiError.value) : null,
    ]);

    const renderLogin = () => h('section', { class: 'auth-screen' }, [
      h('div', { class: 'auth-panel' }, [
        h('span', { class: 'eyebrow' }, 'Accesso ruolo'),
        h('h1', 'Eventi Locali'),
        h('p', 'Il login prova le API reali e usa role + redirect_to restituiti dal backend.'),
        apiError.value ? h('p', { class: 'api-error' }, apiError.value) : null,
        h('div', { class: 'login-grid' }, demoUsers.map((user) => h('button', {
          type: 'button',
          onClick: () => { void login(user); },
        }, [
          h('strong', user.role),
          h('span', user.email),
          h('small', user.redirect_to),
        ]))),
      ]),
    ]);

    const renderHeader = (title: string, menu: readonly (readonly [string, AppRoute])[]) => h('header', { class: 'app-header' }, [
      h('div', [
        h('span', currentUser.value?.email ?? ''),
        h('strong', title),
      ]),
      h('button', { type: 'button', onClick: logout }, 'Logout'),
      h('nav', menu.map(([label, target]) => h('button', {
        class: route.value === target ? 'active' : '',
        type: 'button',
        onClick: () => { void go(target); },
      }, label))),
    ]);

    const renderEventCard = (event: any) => h('article', { class: 'experience-card' }, [
      h('span', event.category ?? event.categories?.[0]?.name ?? 'evento'),
      h('h3', event.title),
      h('p', `${event.municipality?.name ?? event.municipality ?? 'Molise'}${event.starts_at ? ` - ${new Date(event.starts_at).toLocaleDateString('it-IT')}` : ''}`),
      h('div', [h('strong', event.is_free ? 'gratis' : 'prenotabile'), h('small', `${event.available_total ?? ''} posti`)]),
      h('button', { type: 'button', disabled: true, title: 'Dettaglio disponibile nell’app mobile' }, 'Dettaglio non disponibile'),
    ]);

    const renderSuccessfulEventCard = (event: any) => h('article', { class: 'experience-card success-card' }, [
      h('span', 'Evento di successo'),
      h('h3', event.title),
      h('p', `${event.category ?? 'categoria'} - ${event.municipality ?? ''} ${event.province ? `(${event.province})` : ''}`),
      h('div', { class: 'event-stats' }, [
        h('small', `Valutazione: ${event.average_rating ?? 0}/5`),
        h('small', `Riempimento: ${event.fill_rate ?? 0}%`),
        h('small', `Consigliato: ${event.recommendation_rate ?? 0}%`),
        h('small', `${event.review_count ?? 0} recensioni`),
      ]),
      event.has_next_edition ? h('strong', { class: 'next-edition-badge' }, 'Nuova edizione disponibile') : null,
      event.next_edition ? h('p', `Prossima: ${event.next_edition.title} - ${new Date(event.next_edition.starts_at).toLocaleDateString('it-IT')} - ${event.next_edition.available_total} posti`) : null,
      h('div', { class: 'action-row' }, [
        event.next_edition
          ? h('button', { type: 'button', disabled: true, title: 'Azione non ancora collegata in questa console' }, 'Nuova edizione non disponibile')
          : h('button', { type: 'button', disabled: true, title: 'Azione non ancora collegata in questa console' }, 'Eventi simili non disponibili'),
        h('button', { type: 'button', onClick: () => { void notifySimilar(event.ulid); } }, 'Avvisami quando torna'),
      ]),
    ]);

    const renderCustomerDashboard = () => [
      h('div', { class: 'metric-grid' }, [
        card('Prenotazioni', `${customerDashboard.value?.bookings_count ?? 0}`, 'confermate'),
        card('Preferiti', `${customerDashboard.value?.favorites_count ?? 0}`, 'salvati'),
        card('Notifiche', `${customerDashboard.value?.notifications_unread ?? 0}`, 'non lette'),
      ]),
      h('div', { class: 'section-title' }, [
        h('h2', 'Gli eventi piu amati'),
        h('button', { type: 'button', onClick: () => { void go('/customer/successful-events'); } }, 'Vedi tutti'),
      ]),
      successfulEvents.value.length === 0 ? emptyState('Nessun evento di successo disponibile dal database.') : h('div', { class: 'event-grid' }, successfulEvents.value.slice(0, 6).map(renderSuccessfulEventCard)),
      h('div', { class: 'section-title' }, [h('h2', 'Consigliati per te'), h('span', 'prossimi eventi')]),
      h('div', { class: 'event-grid' }, (customerDashboard.value?.recommended_events ?? allCustomerEvents.value).slice(0, 6).map(renderEventCard)),
    ];

    const renderAllEvents = () => [
      h('div', { class: 'section-title' }, [h('h2', 'Tutti gli Eventi'), h('span', 'filtri: categoria, comune, data, prezzo')]),
      h('div', { class: 'filter-strip' }, ['Categoria', 'Comune', 'Provincia', 'Data', 'Prezzo'].map((item) => h('button', {
        type: 'button',
        disabled: true,
        title: 'Filtro non ancora collegato',
      }, item))),
      allCustomerEvents.value.length === 0 ? emptyState('Nessun evento pubblicato disponibile dal database.') : h('div', { class: 'event-grid' }, allCustomerEvents.value.map(renderEventCard)),
    ];

    const renderSuccessfulEvents = () => [
      h('div', { class: 'section-title' }, [h('h2', 'Eventi di Successo'), h('span', 'ranking con success score aggregato')]),
      h('div', { class: 'filter-strip' }, ['Piu apprezzati', 'Piu recenti', 'Miglior valutazione', 'Riempimento', 'Con nuova edizione'].map((item) => h('button', {
        type: 'button',
        disabled: true,
        title: 'Filtro non ancora collegato',
      }, item))),
      successfulEvents.value.length === 0 ? emptyState('Nessun evento concluso valido nel feed successi.') : h('div', { class: 'event-grid' }, successfulEvents.value.map(renderSuccessfulEventCard)),
    ];

    const CustomerLayout = () => h('main', { class: 'role-shell customer-layout' }, [
      renderHeader('Dashboard cliente', customerMenu),
      h('section', { class: 'role-content' }, [
        apiError.value ? h('p', { class: 'api-error' }, apiError.value) : null,
        ...(route.value === '/customer/explore'
          ? renderAllEvents()
          : route.value === '/customer/successful-events'
            ? renderSuccessfulEvents()
            : renderCustomerDashboard()),
      ]),
    ]);

    const renderOrganizerDashboard = () => {
      const data = organizerDashboard.value;
      if (!data) return [emptyState('Avvia il backend e accedi come organizer per caricare la dashboard reale.')];

      return [
        h('div', { class: 'metric-grid' }, [
          card('Eventi attivi', `${data.active_experiences ?? 0}`, 'pubblicati o sold out'),
          card('Prenotazioni', `${data.bookings_total ?? 0}`, 'totali ricevute'),
          card('Follower', `${data.followers ?? 0}`, 'utenti che seguono'),
          card('Rating', `${data.bi?.average_rating ?? 0}`, `${data.bi?.review_analytics?.reviews_count ?? 0} recensioni`),
          card('Riempimento', `${Math.round(data.bi?.category_performance?.[0]?.fill_rate ?? 0)}%`, 'categoria migliore'),
          card('Posti disponibili', `${data.available_places ?? 0}`, 'ancora vendibili'),
        ]),
        h('div', { class: 'section-title' }, [h('h2', 'Grafici business'), h('span', 'dati aggregati API')]),
        h('div', { class: 'bar-chart' }, [40, 62, 48, 78, 91, 66].map((value) => h('span', { style: { height: `${value}%` } }))),
      ];
    };

    const renderOrganizerEvents = () => [
      h('div', { class: 'section-title' }, [h('h2', 'I miei eventi'), h('span', 'ordinati per data, solo proprietario')]),
      h('div', { class: 'filter-strip' }, ['open', 'closed', 'cancelled', 'future'].map((status) => h('button', {
        type: 'button',
        onClick: async () => {
          const payload = await api(`/organizer/events?status=${status}&sort=asc`);
          organizerEvents.value = payload?.data ?? [];
        },
      }, status))),
      organizerEvents.value.length === 0 ? emptyState() : h('div', { class: 'event-grid' }, organizerEvents.value.map((item) => h('article', { class: 'experience-card event-management-card' }, [
        h('span', { class: `status-badge status-${String(item.summary.visual_status).toLowerCase().replaceAll(' ', '-')}` }, item.summary.visual_status),
        h('h3', item.event.title),
        h('p', `${item.summary.municipality ?? ''} - ${new Date(item.summary.starts_at).toLocaleString('it-IT')}`),
        h('div', { class: 'event-stats' }, item.summary.visual_status === 'Aperto'
          ? [
              h('small', `Prenotati: ${item.summary.booked_people}`),
              h('small', `Capienza: ${item.summary.capacity_total}`),
              h('small', `Disponibili: ${item.summary.available_total}`),
              h('small', `Riempimento: ${item.summary.fill_rate}%`),
            ]
          : [
              h('small', `Prenotati: ${item.summary.booked_people}`),
              h('small', `Partecipanti: ${item.summary.checked_in_people}`),
              h('small', `No-show: ${item.summary.no_show}`),
              h('small', `Rating: ${item.summary.average_rating}`),
            ]),
        h('div', { class: 'action-row' }, [
          ...['Dettaglio', 'Modifica', 'Prenotazioni', 'Partecipanti', 'Recensioni', 'Statistiche'].map((label) => h('button', { type: 'button' }, label)),
          item.event.status !== 'cancelled' && new Date(item.event.starts_at).getTime() > Date.now()
            ? h('button', { type: 'button', class: 'danger-button', onClick: () => { void cancelOrganizerEvent(item.event.ulid); } }, 'Annulla evento')
            : null,
        ]),
      ]))),
    ];

    const renderCreateEvent = () => [
      h('div', { class: 'section-title' }, [h('h2', 'Crea evento'), h('span', 'calendario DB con colori backend')]),
      h('article', { class: 'wide-panel form-preview' }, [
        h('strong', 'Descrizione completa esperienza'),
        h('div', { class: 'form-grid' }, ['Descrizione breve', 'Descrizione completa', 'Programma', 'Incluso', 'Info importanti', 'Policy cancellazione', 'Allergie', 'Contatto'].map((field) => h('label', [field, h('input', { readonly: true })]))),
      ]),
      h('div', { class: 'legend-row' }, [
        h('span', { class: 'legend green' }, 'Verde: nessun evento'),
        h('span', { class: 'legend yellow' }, 'Giallo: un evento'),
        h('span', { class: 'legend red' }, 'Rosso: piu eventi'),
      ]),
      calendarDays.value.length === 0 ? emptyState('Nessun dato calendario disponibile.') : h('div', { class: 'calendar-grid' }, calendarDays.value.map((day) => h('button', {
        class: ['calendar-day', day.color, selectedDay.value?.date === day.date ? 'selected' : ''],
        type: 'button',
        onClick: () => { selectedDay.value = day; },
      }, [h('strong', new Date(day.date).getDate().toString()), h('small', `${day.events_count} eventi`)]))),
      selectedDay.value ? h('article', { class: 'wide-panel' }, [
        h('strong', `Eventi pubblici del ${selectedDay.value.date}`),
        ...(selectedDay.value.events ?? []).map((event: any) => h('p', `${event.time} - ${event.title} - ${event.category ?? 'categoria'} - ${event.municipality} - ${event.organizer}${event.sold_out ? ' - sold out' : ''}`)),
      ]) : null,
    ];

    const renderReviews = () => [
      h('div', { class: 'section-title' }, [h('h2', 'Recensioni per evento'), h('span', 'non lista unica')]),
      reviewEvents.value.length === 0 ? emptyState('Nessun evento con recensioni dal database.') : h('div', { class: 'review-feed' }, reviewEvents.value.map((group) => h('article', { class: 'review-card' }, [
        h('strong', group.event.title),
        h('p', `${group.statistics?.reviews_count ?? 0} recensioni - media ${group.statistics?.average_rating ?? 0} - consiglia ${group.statistics?.recommendation_rate ?? 0}%`),
        group.latest_review ? h('small', `Ultima: ${group.latest_review.comment ?? 'senza commento'}`) : null,
      ]))),
    ];

    const renderOpportunities = () => {
      const suggestion = opportunities.value?.suggestions;
      const ideas = suggestion?.suggestions?.suggestions ?? [];
      const topEvents = opportunities.value?.top_events ?? [];

      return [
        h('div', { class: 'section-title' }, [h('h2', 'Opportunita'), h('span', 'AI server-side + fallback')]),
        ideas.length === 0 ? emptyState('Nessun suggerimento disponibile.') : h('div', { class: 'event-grid' }, ideas.map((idea: any, index: number) => h('article', { class: 'experience-card opportunity' }, [
          h('span', idea.category),
          h('h3', idea.title),
          h('p', idea.reason),
          h('small', `${idea.recommended_date} - target ${idea.target} - ${idea.suggested_price_range?.min}/${idea.suggested_price_range?.max} euro`),
          h('button', { type: 'button', onClick: () => { void useSuggestion(suggestion.ulid, index); } }, 'Usa questa idea'),
        ]))),
        h('div', { class: 'section-title' }, [h('h2', 'Eventi di maggior successo'), h('span', 'aggregati pubblici')]),
        topEvents.length === 0 ? emptyState('Nessun evento nel feed ispirazione.') : h('div', { class: 'event-grid' }, topEvents.map((event: any) => h('article', { class: 'experience-card' }, [
          h('span', `score ${event.success_score}`),
          h('h3', event.title),
          h('p', `${event.category} - ${event.territory} - riempimento ${event.fill_rate}% - rating ${event.average_rating}`),
          h('small', `Fascia prezzo ${event.price_band}; capienza ${event.capacity_total}`),
          h('button', { type: 'button', onClick: () => { void inspireFromEvent(event.ulid); } }, 'Prendi ispirazione'),
        ]))),
      ];
    };

    const OrganizerLayout = () => h('main', { class: 'role-shell organizer-layout' }, [
      renderHeader('Dashboard organizzatore', organizerMenu),
      h('section', { class: 'role-content' }, [
        apiError.value ? h('p', { class: 'api-error' }, apiError.value) : null,
        ...(route.value === '/organizer/events'
          ? renderOrganizerEvents()
          : route.value === '/organizer/create-event'
            ? renderCreateEvent()
            : route.value === '/organizer/reviews'
              ? renderReviews()
              : route.value === '/organizer/opportunities'
                ? renderOpportunities()
                : renderOrganizerDashboard()),
      ]),
    ]);

    const adminIcon = (label: string) => ({
      Dashboard: 'DB',
      Eventi: 'EV',
      Organizzatori: 'OR',
      Clienti: 'CL',
      Classifiche: 'RK',
      Recensioni: 'RE',
      Categorie: 'CA',
      Territorio: 'TE',
      Abbonamenti: 'AB',
      Statistiche: 'ST',
      'Audit Log': 'AL',
      Impostazioni: 'IM',
    }[label] ?? label.slice(0, 2).toUpperCase());

    const number = (value: any) => new Intl.NumberFormat('it-IT').format(Number(value ?? 0));
    const overview = () => adminOverview.value ?? {};
    const kpis = () => overview().kpis ?? {};
    const chart = (key: string) => overview().charts?.[key] ?? [];
    const maxValue = (items: any[]) => Math.max(1, ...items.map((item) => Number(item.value ?? 0)));

    const adminKpi = (label: string, value: any, note: string, tone = 'neutral') => h('article', { class: ['admin-kpi', tone] }, [
      h('span', label),
      h('strong', number(value)),
      h('small', note),
    ]);

    const renderMiniBars = (title: string, items: any[]) => h('article', { class: 'admin-panel chart-panel' }, [
      h('div', { class: 'panel-heading' }, [h('h3', title), h('span', 'ultimi 6 mesi')]),
      h('div', { class: 'admin-bars' }, items.length === 0
        ? [h('span', { class: 'empty-bar' })]
        : items.map((item) => h('div', { class: 'bar-column' }, [
            h('span', { style: { height: `${Math.max(8, (Number(item.value ?? 0) / maxValue(items)) * 100)}%` } }),
            h('small', String(item.label).slice(5)),
          ]))),
    ]);

    const renderHorizontalBars = (title: string, items: any[]) => h('article', { class: 'admin-panel horizontal-chart' }, [
      h('div', { class: 'panel-heading' }, [h('h3', title), h('span', 'distribuzione')]),
      ...(items.length === 0 ? [h('p', 'Nessun dato disponibile')] : items.map((item) => h('div', { class: 'progress-row' }, [
        h('span', item.label),
        h('div', [h('i', { style: { width: `${Math.max(4, (Number(item.value ?? 0) / maxValue(items)) * 100)}%` } })]),
        h('strong', number(item.value)),
      ]))),
    ]);

    const adminTable = (headers: string[], rows: any[], renderRow: (row: any, index: number) => any[]) => h('article', { class: 'admin-panel table-panel' }, [
      rows.length === 0 ? emptyState('Nessun dato disponibile.') : h('div', { class: 'admin-table-wrap' }, [
        h('table', { class: 'admin-table' }, [
          h('thead', [h('tr', headers.map((header) => h('th', header)))]),
          h('tbody', rows.map((row, index) => h('tr', renderRow(row, index)))),
        ]),
      ]),
    ]);

    const statusPill = (status: string) => h('span', { class: `admin-pill status-${String(status).replaceAll('_', '-')}` }, status);

    const renderAdminDashboard = () => [
      h('section', { class: 'admin-hero' }, [
        h('div', [
          h('span', 'Centro di controllo'),
          h('h1', 'Piattaforma eventi locali'),
          h('p', `${number(kpis().events_created_today)} eventi creati oggi, ${number(kpis().bookings_today)} prenotazioni arrivate oggi.`),
        ]),
        h('div', { class: 'hero-signal' }, [
          h('strong', number(kpis().events_scheduled)),
          h('span', 'eventi in programma'),
        ]),
      ]),
      h('section', { class: 'admin-kpi-grid' }, [
        adminKpi('Organizzatori registrati', kpis().organizers_registered ?? adminOrganizers.value.length, 'offerta attiva', 'mint'),
        adminKpi('Clienti registrati', kpis().customers_registered ?? adminDashboard.value?.customers, 'domanda potenziale', 'blue'),
        adminKpi('Eventi pubblicati', kpis().events_published ?? adminEventStats.value?.published, 'visibili ai clienti', 'gold'),
        adminKpi('Eventi in programma', kpis().events_scheduled, 'prossime date', 'mint'),
        adminKpi('Eventi conclusi', kpis().events_completed, 'storico esperienze', 'blue'),
        adminKpi('Eventi annullati', kpis().events_cancelled, 'da monitorare', 'alert'),
        adminKpi('Prenotazioni totali', kpis().bookings_total, 'volume piattaforma', 'gold'),
        adminKpi('Partecipanti totali', kpis().participants_total, 'persone servite', 'mint'),
      ]),
      h('section', { class: 'admin-chart-grid' }, [
        renderMiniBars('Eventi pubblicati per mese', chart('events_by_month')),
        renderMiniBars('Prenotazioni mensili', chart('bookings_by_month')),
        renderMiniBars('Crescita organizzatori', chart('organizer_growth')),
        renderMiniBars('Crescita clienti', chart('customer_growth')),
        renderHorizontalBars('Eventi per categoria', chart('events_by_category')),
      ]),
      h('section', { class: 'admin-two-col' }, [
        h('div', [
          h('div', { class: 'panel-heading outside' }, [h('h2', 'Ultimi eventi inseriti'), h('span', 'stato operativo')]),
          renderEventsTable((overview().latest_events ?? adminEvents.value).slice(0, 8)),
        ]),
        h('div', [
          h('div', { class: 'panel-heading outside' }, [h('h2', 'Ultime registrazioni'), h('span', 'nuova domanda e offerta')]),
          renderRegistrations(),
          h('div', { class: 'panel-heading outside compact' }, [h('h2', 'Ultime recensioni'), h('span', 'qualita percepita')]),
          renderReviewsList((overview().latest_reviews ?? []).slice(0, 5)),
        ]),
      ]),
    ];

    const renderEventsTable = (rows = adminEvents.value) => adminTable(
      ['Titolo', 'Organizzatore', 'Categoria', 'Comune', 'Data', 'Stato', 'Prenotati', 'Partecipanti', 'Media'],
      rows,
      (event) => [
        h('td', h('strong', event.title)),
        h('td', event.organizer ?? event.organizer_profile?.name ?? '-'),
        h('td', event.category ?? event.categories?.[0]?.name ?? '-'),
        h('td', event.municipality ?? event.municipality?.name ?? '-'),
        h('td', event.date ?? (event.starts_at ? new Date(event.starts_at).toLocaleDateString('it-IT') : '-')),
        h('td', statusPill(event.status)),
        h('td', number(event.booked ?? event.bookings_count)),
        h('td', number(event.participants ?? event.participants_count)),
        h('td', `${event.average_rating ?? 0}/5`),
      ],
    );

    const renderAdminEvents = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Eventi'), h('p', 'Tutti gli eventi della piattaforma, con prenotazioni, partecipazione reale e qualita.')]),
      h('section', { class: 'admin-kpi-grid compact' }, [
        adminKpi('Pubblicati', adminEventStats.value?.published ?? kpis().events_published, 'online'),
        adminKpi('In programma', kpis().events_scheduled, 'future date'),
        adminKpi('Conclusi', kpis().events_completed, 'storico'),
        adminKpi('Annullati', kpis().events_cancelled, 'attenzione'),
      ]),
      renderEventsTable(),
      h('section', { class: 'admin-detail-grid' }, [
        h('article', { class: 'admin-panel' }, [h('h3', 'Informazioni evento'), h('p', 'Descrizione, programma, luogo, data, prezzo e immagini sono disponibili dal dettaglio evento admin.')]),
        h('article', { class: 'admin-panel' }, [h('h3', 'Prenotazioni'), h('p', 'Prenotati, partecipanti effettivi, no-show e cancellazioni cliente sono tracciati nella tabella.')]),
        h('article', { class: 'admin-panel' }, [h('h3', 'Recensioni'), h('p', 'Media, stelle e recensioni alimentano statistiche e classifiche.')]),
      ]),
    ];

    const renderAdminOrganizers = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Organizzatori'), h('p', 'Prestazioni dei locali: eventi creati, prenotazioni, follower e rating.')]),
      adminTable(
        ['Logo', 'Nome attivita', 'Comune', 'Provincia', 'Creati', 'Futuri', 'Conclusi', 'Annullati', 'Prenotazioni', 'Media'],
        adminOrganizers.value,
        (organizer) => [
          h('td', h('span', { class: 'logo-dot' }, String(organizer.name ?? 'O').slice(0, 1))),
          h('td', h('strong', organizer.name)),
          h('td', organizer.municipality ?? '-'),
          h('td', organizer.province ?? '-'),
          h('td', number(organizer.events_created ?? organizer.events_count)),
          h('td', number(organizer.future_events)),
          h('td', number(organizer.completed_events)),
          h('td', number(organizer.cancelled_events)),
          h('td', number(organizer.bookings_received)),
          h('td', `${organizer.average_rating ?? 0}/5`),
        ],
      ),
      h('section', { class: 'admin-chart-grid' }, [
        renderHorizontalBars('Organizzatori per eventi creati', adminOrganizers.value.slice(0, 8).map((item) => ({ label: item.name, value: item.events_created ?? item.events_count ?? 0 }))),
        renderHorizontalBars('Follower per organizzatore', adminOrganizers.value.slice(0, 8).map((item) => ({ label: item.name, value: item.followers ?? 0 }))),
      ]),
    ];

    const renderCustomers = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Clienti'), h('p', 'Anagrafica cliente con partecipazione, recensioni, preferiti e organizer seguiti.')]),
      adminTable(
        ['Nome', 'Email', 'Comune', 'Partecipati', 'Recensioni', 'Annullati', 'Preferiti', 'Seguiti', 'Registrazione'],
        overview().customers ?? [],
        (customer) => [
          h('td', h('strong', customer.name)),
          h('td', customer.email),
          h('td', customer.municipality ?? '-'),
          h('td', number(customer.events_attended)),
          h('td', number(customer.reviews_left)),
          h('td', number(customer.cancelled_events)),
          h('td', number(customer.favorites)),
          h('td', number(customer.followed_organizers)),
          h('td', customer.registered_at ?? '-'),
        ],
      ),
    ];

    const renderAdminRankings = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Classifiche'), h('p', 'Eventi migliori e organizzatori ordinati per qualita, partecipazione e crescita.')]),
      h('section', { class: 'admin-two-col' }, [
        h('div', [
          h('div', { class: 'panel-heading outside' }, [h('h2', 'Eventi migliori'), h('span', 'creativita, recensioni, riempimento')]),
          adminTable(['#', 'Titolo', 'Organizzatore', 'Punteggio'], adminEventRankings.value.slice(0, 10), (ranking, index) => [
            h('td', h('strong', `#${index + 1}`)),
            h('td', ranking.event?.title ?? '-'),
            h('td', ranking.event?.organizer_profile?.name ?? '-'),
            h('td', h('strong', `${ranking.final_score}`)),
          ]),
        ]),
        h('div', [
          h('div', { class: 'panel-heading outside' }, [h('h2', 'Organizzatori'), h('span', 'eventi, partecipanti, rating, follower')]),
          adminTable(['#', 'Organizzatore', 'Eventi', 'Partecipanti', 'Media', 'Follower'], adminOrganizerRankings.value.slice(0, 10), (ranking, index) => [
            h('td', h('strong', `#${index + 1}`)),
            h('td', ranking.organizer_profile?.name ?? '-'),
            h('td', number(ranking.events_created)),
            h('td', number(ranking.total_participants)),
            h('td', `${ranking.average_rating}/5`),
            h('td', number(ranking.follower_count)),
          ]),
        ]),
      ]),
    ];

    const renderReviewsList = (rows = overview().reviews ?? []) => h('div', { class: 'review-feed admin-review-feed' }, rows.length === 0 ? [emptyState('Nessuna recensione disponibile.')] : rows.map((review: any) => h('article', { class: 'review-card' }, [
      h('div', { class: 'review-topline' }, [h('strong', `${review.rating}/5`), statusPill(review.status)]),
      h('p', review.comment ?? 'Recensione senza commento.'),
      h('small', `${review.user ?? 'Utente'} - ${review.event ?? 'Evento'} - ${review.organizer ?? 'Organizzatore'}`),
    ])));

    const renderAdminReviews = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Recensioni'), h('p', 'Tutte le recensioni, filtrabili per evento, organizer, stelle e categoria.')]),
      h('div', { class: 'filter-strip' }, ['Organizzatore', 'Evento', 'Stelle', 'Categoria', 'Nascoste'].map((item) => h('button', {
        type: 'button',
        disabled: true,
        title: 'Filtro non ancora collegato',
      }, item))),
      renderReviewsList(),
    ];

    const renderCategories = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Categorie'), h('p', 'Gestione editoriale delle categorie con impatto su eventi e organizzatori.')]),
      adminTable(['Icona', 'Categoria', 'Colore', 'Eventi', 'Organizzatori'], overview().categories ?? [], (category) => [
        h('td', h('span', { class: 'logo-dot' }, category.icon ?? 'CA')),
        h('td', h('strong', category.name)),
        h('td', h('span', { class: 'color-swatch', style: { background: category.color } })),
        h('td', number(category.events_count)),
        h('td', number(category.organizers_count)),
      ]),
    ];

    const renderTerritory = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Territorio'), h('p', 'Regioni, province e comuni con densita eventi.')]),
      h('section', { class: 'admin-two-col' }, [
        adminTable(['Regione', 'Province', 'Eventi'], overview().territory?.regions ?? [], (region) => [h('td', h('strong', region.name)), h('td', number(region.provinces_count)), h('td', number(region.events_count))]),
        adminTable(['Comune', 'Provincia', 'Eventi'], overview().territory?.municipalities ?? [], (municipality) => [h('td', h('strong', municipality.name)), h('td', municipality.province ?? '-'), h('td', number(municipality.events_count))]),
      ]),
    ];

    const renderSubscriptions = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Abbonamenti'), h('p', 'Piani, rinnovi, stato e pagamenti predisposti.')]),
      adminTable(['Organizzatore', 'Piano', 'Rinnovo', 'Stato', 'Pagamenti'], overview().subscriptions ?? [], (subscription) => [
        h('td', h('strong', subscription.organizer ?? '-')),
        h('td', subscription.plan ?? '-'),
        h('td', subscription.renewal ?? '-'),
        h('td', statusPill(subscription.status ?? 'n/d')),
        h('td', subscription.payments ?? '-'),
      ]),
    ];

    const renderStatistics = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Statistiche'), h('p', 'Numeri chiave della piattaforma in un unica vista.')]),
      h('section', { class: 'admin-chart-grid' }, [
        renderMiniBars('Crescita utenti', chart('customer_growth')),
        renderMiniBars('Crescita organizzatori', chart('organizer_growth')),
        renderMiniBars('Eventi per mese', chart('events_by_month')),
        renderMiniBars('Prenotazioni', chart('bookings_by_month')),
        renderHorizontalBars('Eventi per categoria', chart('events_by_category')),
      ]),
    ];

    const renderAudit = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Audit Log'), h('p', 'Azioni operative e amministrative tracciate.')]),
      adminTable(['Azione', 'Oggetto', 'ID', 'Data'], overview().audit_logs ?? [], (log) => [
        h('td', h('strong', log.action)),
        h('td', log.subject_type ?? '-'),
        h('td', String(log.subject_id ?? '-')),
        h('td', log.created_at ?? '-'),
      ]),
    ];

    const renderSettings = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Impostazioni'), h('p', 'Configurazione piattaforma, moderazione e policy operative.')]),
      h('section', { class: 'admin-detail-grid' }, ['Moderazione post-evento', 'Notifiche operative', 'Policy recensioni', 'Piani e limiti'].map((item) => h('article', { class: 'admin-panel' }, [h('h3', item), h('p', 'Sezione pronta per configurazioni amministrative avanzate.')]))),
    ];

    const renderRegistrations = () => h('div', { class: 'registration-stack' }, [
      ...(overview().latest_registrations?.organizers ?? []).slice(0, 3).map((item: any) => h('article', [h('strong', item.name), h('small', `Organizer - ${item.municipality ?? 'territorio non definito'}`)])),
      ...(overview().latest_registrations?.customers ?? []).slice(0, 3).map((item: any) => h('article', [h('strong', item.name), h('small', `Cliente - ${item.registered_at ?? ''}`)])),
    ]);

    const renderAdminPage = () => {
      if (route.value === '/admin/events') return renderAdminEvents();
      if (route.value === '/admin/organizers') return renderAdminOrganizers();
      if (route.value === '/admin/customers') return renderCustomers();
      if (route.value === '/admin/rankings') return renderAdminRankings();
      if (route.value === '/admin/reviews') return renderAdminReviews();
      if (route.value === '/admin/categories') return renderCategories();
      if (route.value === '/admin/territory') return renderTerritory();
      if (route.value === '/admin/subscriptions') return renderSubscriptions();
      if (route.value === '/admin/statistics') return renderStatistics();
      if (route.value === '/admin/audit') return renderAudit();
      if (route.value === '/admin/settings') return renderSettings();

      return renderAdminDashboard();
    };

    const AdminLayout = () => h('main', { class: 'admin-console' }, [
      h('aside', { class: 'admin-sidebar' }, [
        h('div', { class: 'admin-brand' }, [h('strong', 'EL'), h('span', 'Eventi Locali')]),
        h('nav', adminMenu.map(([label, target]) => h('button', {
          class: route.value === target ? 'active' : '',
          type: 'button',
          onClick: () => { void go(target); },
        }, [h('i', adminIcon(label)), h('span', label)]))),
        h('button', { class: 'admin-logout', type: 'button', onClick: logout }, 'Logout'),
      ]),
      h('section', { class: 'admin-main' }, [
        h('header', { class: 'admin-topbar' }, [
          h('div', [h('span', currentUser.value?.email ?? ''), h('strong', 'Dashboard amministratore')]),
          h('button', { type: 'button', onClick: () => { void loadAdminData(); } }, loading.value ? 'Aggiorno...' : 'Aggiorna dati'),
        ]),
        apiError.value ? h('p', { class: 'api-error' }, apiError.value) : null,
        ...renderAdminPage(),
      ]),
    ]);

    const Unauthorized = () => h('main', { class: 'auth-screen' }, [
      h('div', { class: 'auth-panel unauthorized' }, [
        h('span', { class: 'eyebrow' }, '403'),
        h('h1', 'Accesso non autorizzato'),
        h('p', 'Questa pagina non appartiene al ruolo autenticato. Il backend applica lo stesso controllo con middleware role e policy.'),
        h('button', { type: 'button', onClick: () => currentUser.value ? void go(currentUser.value.redirect_to) : void go('/login') }, 'Torna alla dashboard corretta'),
      ]),
    ]);

    return () => {
      if (route.value === '/unauthorized') return Unauthorized();
      if (role.value === 'customer') return CustomerLayout();
      if (role.value === 'organizer') return OrganizerLayout();
      if (role.value === 'admin') return AdminLayout();

      return renderLogin();
    };
  },
});
