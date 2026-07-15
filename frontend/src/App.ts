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
  | '/admin/reviews'
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
  ['Organizzatori', '/admin/organizers'],
  ['Eventi da approvare', '/admin/events'],
  ['Recensioni segnalate', '/admin/reviews'],
] as const;

const canAccess = (role: Role, route: AppRoute) => {
  if (route === '/login' || route === '/unauthorized') return true;
  if (route.startsWith('/customer')) return role === 'customer';
  if (route.startsWith('/organizer')) return role === 'organizer';
  if (route.startsWith('/admin')) return role === 'admin';

  return false;
};

export default defineComponent({
  name: 'App',
  setup() {
    const currentUser = ref<DemoUser | null>(null);
    const token = ref<string | null>(null);
    const route = ref<AppRoute>('/login');
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

    const go = async (nextRoute: AppRoute) => {
      route.value = canAccess(role.value, nextRoute) ? nextRoute : '/unauthorized';
      if (route.value.startsWith('/organizer')) await loadOrganizerData();
      if (route.value.startsWith('/customer')) await loadCustomerData();
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
      h('button', { type: 'button' }, 'Dettaglio evento'),
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
        event.next_edition ? h('button', { type: 'button' }, 'Vedi la nuova edizione') : h('button', { type: 'button' }, 'Scopri eventi simili'),
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
      h('div', { class: 'filter-strip' }, ['Categoria', 'Comune', 'Provincia', 'Data', 'Prezzo'].map((item) => h('button', { type: 'button' }, item))),
      allCustomerEvents.value.length === 0 ? emptyState('Nessun evento pubblicato disponibile dal database.') : h('div', { class: 'event-grid' }, allCustomerEvents.value.map(renderEventCard)),
    ];

    const renderSuccessfulEvents = () => [
      h('div', { class: 'section-title' }, [h('h2', 'Eventi di Successo'), h('span', 'ranking con success score aggregato')]),
      h('div', { class: 'filter-strip' }, ['Piu apprezzati', 'Piu recenti', 'Miglior valutazione', 'Riempimento', 'Con nuova edizione'].map((item) => h('button', { type: 'button' }, item))),
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
        h('div', { class: 'action-row' }, ['Dettaglio', 'Modifica', 'Prenotazioni', 'Partecipanti', 'Recensioni', 'Statistiche'].map((label) => h('button', { type: 'button' }, label))),
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

    const AdminLayout = () => h('main', { class: 'role-shell admin-layout' }, [
      renderHeader('Dashboard amministratore', adminMenu),
      h('section', { class: 'role-content' }, [
        h('div', { class: 'metric-grid' }, [
          card('Utenti', 'API', 'dashboard admin'),
          card('Organizer pending', 'API', 'moderazione'),
          card('Eventi pending', 'API', 'approvazioni'),
          card('Report review', 'API', 'segnalazioni'),
        ]),
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
