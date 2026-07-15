import { computed, defineComponent, h, ref } from 'vue';

type Role = 'guest' | 'customer' | 'organizer' | 'admin';
type AppRoute =
  | '/login'
  | '/customer/dashboard'
  | '/customer/explore'
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
  redirect_to: AppRoute;
  status?: 'pending' | 'verified';
};

const demoUsers: DemoUser[] = [
  { role: 'customer', name: 'Cliente Demo', email: 'customer@example.test', redirect_to: '/customer/dashboard' },
  { role: 'organizer', name: 'Organizzatore Demo', email: 'organizer@example.test', redirect_to: '/organizer/dashboard', status: 'verified' },
  { role: 'admin', name: 'Admin Molise', email: 'admin@example.test', redirect_to: '/admin/dashboard' },
];

const customerMenu = [
  ['Home', '/customer/dashboard'],
  ['Esplora eventi', '/customer/explore'],
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

const events = [
  {
    title: 'Laboratorio creativo in fattoria',
    category: 'bambini',
    town: 'Campobasso',
    date: '23 lug',
    price: '32,00 euro',
    places: '7 posti',
  },
  {
    title: 'Cena con delitto nel borgo',
    category: 'gastronomia',
    town: 'Bojano',
    date: '27 lug',
    price: '25,00 euro',
    places: '5 posti',
  },
  {
    title: 'Escursione guidata sul tratturo',
    category: 'natura',
    town: 'Isernia',
    date: '30 lug',
    price: 'gratis',
    places: '14 posti',
  },
];

const competitorEvents = [
  { title: 'Degustazione in centro', category: 'degustazioni', town: 'Campobasso', distance: '2 km', time: '20:00', status: 'published' },
  { title: 'Teatro per famiglie', category: 'bambini', town: 'Campobasso', distance: '1.4 km', time: '17:30', status: 'sold out' },
  { title: 'Cena tipica molisana', category: 'gastronomia', town: 'Bojano', distance: '18 km', time: '20:30', status: 'published' },
];

const reviews = [
  { author: 'Marta C.', event: 'Laboratorio creativo in fattoria', rating: 5, text: 'Staff attento e orari rispettati.' },
  { author: 'Luca P.', event: 'Cena con delitto nel borgo', rating: 4, text: 'Bella esperienza, tempi da stringere.' },
  { author: 'Sara M.', event: 'Escursione guidata sul tratturo', rating: 3, text: 'Punto di ritrovo poco chiaro.' },
];

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
    const route = ref<AppRoute>('/login');

    const role = computed<Role>(() => currentUser.value?.role ?? 'guest');

    const go = (nextRoute: AppRoute) => {
      route.value = canAccess(role.value, nextRoute) ? nextRoute : '/unauthorized';
    };

    const login = (user: DemoUser) => {
      currentUser.value = user;
      route.value = user.redirect_to;
    };

    const logout = () => {
      currentUser.value = null;
      route.value = '/login';
    };

    const card = (title: string, value: string, note: string) => h('article', { class: 'role-card' }, [
      h('span', title),
      h('strong', value),
      h('small', note),
    ]);

    const renderLogin = () => h('section', { class: 'auth-screen' }, [
      h('div', { class: 'auth-panel' }, [
        h('span', { class: 'eyebrow' }, 'Accesso ruolo'),
        h('h1', 'Eventi Locali'),
        h('p', 'Dopo il login il backend restituisce role e redirect_to. La UI carica automaticamente il layout consentito.'),
        h('div', { class: 'login-grid' }, demoUsers.map((user) => h('button', {
          type: 'button',
          onClick: () => login(user),
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
        onClick: () => go(target),
      }, label))),
    ]);

    const CustomerLayout = () => h('main', { class: 'role-shell customer-layout' }, [
      renderHeader('Dashboard cliente', customerMenu),
      h('section', { class: 'role-content' }, route.value === '/customer/explore'
        ? [
            h('div', { class: 'section-title' }, [h('h2', 'Esplora eventi'), h('span', 'filtri: categoria, comune, data, prezzo')]),
            h('div', { class: 'filter-strip' }, ['Categoria', 'Comune', 'Provincia', 'Data', 'Prezzo'].map((item) => h('button', { type: 'button' }, item))),
            h('div', { class: 'event-grid' }, events.map((event) => h('article', { class: 'experience-card' }, [
              h('span', event.category),
              h('h3', event.title),
              h('p', `${event.town} - ${event.date}`),
              h('div', [h('strong', event.price), h('small', event.places)]),
              h('button', { type: 'button' }, 'Prenota'),
            ]))),
          ]
        : route.value === '/customer/bookings'
          ? [
              h('div', { class: 'section-title' }, [h('h2', 'Le mie prenotazioni'), h('span', 'storico e recensioni')]),
              h('article', { class: 'wide-panel' }, [
                h('strong', 'EVT-DEMO01'),
                h('p', 'Laboratorio creativo in fattoria - 2 adulti, 1 bambino - confirmed'),
                h('button', { type: 'button' }, 'Lascia recensione'),
              ]),
            ]
          : route.value === '/customer/preferences'
            ? [
                h('div', { class: 'section-title' }, [h('h2', 'Preferenze'), h('span', 'notifiche e macroaree')]),
                h('div', { class: 'preference-grid' }, ['bambini', 'gastronomia', 'teatro', 'natura', 'gratuiti'].map((item) => h('button', { type: 'button' }, item))),
                h('article', { class: 'wide-panel' }, [h('strong', 'Canali notifica'), h('p', 'Interne attive, email attiva, promozionali consentite.')]),
              ]
            : [
                h('div', { class: 'metric-grid' }, [
                  card('Prenotazioni', '3', 'confermate'),
                  card('Preferiti', '8', 'salvati'),
                  card('Notifiche', '6', 'non lette'),
                ]),
                h('div', { class: 'section-title' }, [h('h2', 'Consigliati per te'), h('span', 'solo funzioni cliente')]),
                h('div', { class: 'event-grid' }, events.map((event) => h('article', { class: 'experience-card' }, [
                  h('span', event.category),
                  h('h3', event.title),
                  h('p', `${event.town} - ${event.date}`),
                  h('button', { type: 'button' }, 'Dettaglio evento'),
                ]))),
              ]),
    ]);

    const OrganizerLayout = () => h('main', { class: 'role-shell organizer-layout' }, [
      renderHeader('Dashboard organizzatore', organizerMenu),
      h('section', { class: 'role-content' }, route.value === '/organizer/create-event'
        ? [
            h('div', { class: 'section-title' }, [h('h2', 'Crea evento'), h('span', 'con analisi concorrenza')]),
            h('article', { class: 'wide-panel form-preview' }, [
              h('strong', 'Nuova esperienza'),
              h('div', { class: 'form-grid' }, ['Titolo', 'Categoria', 'Comune', 'Data', 'Ora inizio', 'Prezzo adulto', 'Prezzo bambino', 'Capienza'].map((field) => h('label', [field, h('input', { readonly: true, value: field === 'Comune' ? 'Campobasso' : '' })]))),
            ]),
            h('div', { class: 'section-title' }, [h('h2', 'Altri eventi nella data'), h('span', 'dati pubblici o aggregati')]),
            h('div', { class: 'event-grid' }, competitorEvents.map((event) => h('article', { class: 'experience-card competitor' }, [
              h('span', event.status),
              h('h3', event.title),
              h('p', `${event.category} - ${event.town} - ${event.distance}`),
              h('small', `Orario ${event.time}`),
            ]))),
          ]
        : route.value === '/organizer/reviews'
          ? [
              h('div', { class: 'metric-grid' }, [
                card('Media', '4.7', '44 recensioni'),
                card('Consiglierebbero', '88%', 'utenti soddisfatti'),
                card('Critiche', '5', 'da gestire'),
              ]),
              h('div', { class: 'review-feed' }, reviews.map((review) => h('article', { class: 'review-card' }, [
                h('strong', `${review.rating}/5 - ${review.event}`),
                h('p', review.text),
                h('small', review.author),
              ]))),
            ]
          : route.value === '/organizer/opportunities'
            ? [
                h('div', { class: 'section-title' }, [h('h2', 'Opportunity Engine'), h('span', 'deterministico')]),
                h('article', { class: 'wide-panel opportunity' }, [
                  h('strong', 'La data scelta presenta bassa concorrenza nella categoria selezionata.'),
                  h('p', 'Nella stessa fascia oraria e presente un altra cena a tema a 2 km. Valuta di posticipare l evento di un ora.'),
                ]),
                h('div', { class: 'event-grid' }, competitorEvents.map((event) => h('article', { class: 'experience-card' }, [h('h3', event.title), h('p', `${event.category} - ${event.distance}`)]))),
              ]
            : [
                h('div', { class: 'metric-grid' }, [
                  card('Eventi attivi', '4', '2 futuri, 1 sold out'),
                  card('Prenotazioni', '72', 'posti occupati'),
                  card('Follower', '46', '+8 questo mese'),
                  card('Rating', '4.7', '44 recensioni'),
                  card('Riempimento', '74%', 'medio'),
                  card('Ritorno', '42%', 'clienti abituali'),
                ]),
                h('div', { class: 'section-title' }, [h('h2', 'Grafici business'), h('span', 'booking, follower, campagne')]),
                h('div', { class: 'bar-chart' }, [40, 62, 48, 78, 91, 66].map((value) => h('span', { style: { height: `${value}%` } }))),
              ]),
    ]);

    const AdminLayout = () => h('main', { class: 'role-shell admin-layout' }, [
      renderHeader('Dashboard amministratore', adminMenu),
      h('section', { class: 'role-content' }, route.value === '/admin/organizers'
        ? [
            h('div', { class: 'section-title' }, [h('h2', 'Moderazione organizzatori'), h('span', 'pending, approved, rejected')]),
            h('article', { class: 'wide-panel' }, [h('strong', 'Fattoria Didattica Demo'), h('p', 'verified - Campobasso'), h('button', { type: 'button' }, 'Sospendi')]),
          ]
        : route.value === '/admin/events'
          ? [
              h('div', { class: 'section-title' }, [h('h2', 'Eventi da approvare'), h('span', 'policy admin')]),
              h('article', { class: 'wide-panel' }, [h('strong', 'Cena messicana'), h('p', 'pending_review'), h('button', { type: 'button' }, 'Approva')]),
            ]
          : route.value === '/admin/reviews'
            ? [
                h('div', { class: 'section-title' }, [h('h2', 'Recensioni segnalate'), h('span', 'moderazione')]),
                h('article', { class: 'wide-panel' }, [h('strong', 'Segnalazione aperta'), h('p', 'contenuto improprio'), h('button', { type: 'button' }, 'Nascondi')]),
              ]
            : [
                h('div', { class: 'metric-grid' }, [
                  card('Utenti', '128', 'customer, organizer, admin'),
                  card('Organizer pending', '3', 'da verificare'),
                  card('Eventi pending', '7', 'da approvare'),
                  card('Report review', '4', 'aperti'),
                ]),
                h('article', { class: 'wide-panel' }, [h('strong', 'Audit log'), h('p', 'Ultime azioni amministrative e controlli piattaforma.')]),
              ]),
    ]);

    const Unauthorized = () => h('main', { class: 'auth-screen' }, [
      h('div', { class: 'auth-panel unauthorized' }, [
        h('span', { class: 'eyebrow' }, '403'),
        h('h1', 'Accesso non autorizzato'),
        h('p', 'Questa pagina non appartiene al ruolo autenticato. Il backend restituisce lo stesso controllo con middleware role e policy.'),
        h('button', { type: 'button', onClick: () => currentUser.value ? go(currentUser.value.redirect_to) : go('/login') }, 'Torna alla dashboard corretta'),
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
