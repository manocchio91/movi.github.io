import { computed, defineComponent, h, onMounted, ref } from 'vue';

type EventItem = {
  ulid: string;
  title: string;
  summary: string;
  starts_at: string;
  municipality?: { name: string };
  categories?: Array<{ name: string }>;
  available_total?: number;
  capacity_total?: number;
  is_free?: boolean;
  family_friendly?: boolean;
  tone: string;
  imageLabel: string;
};

type Tab = 'discover' | 'booking' | 'organizer' | 'admin';

const apiBase = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1';

const demoEvents: EventItem[] = [
  {
    ulid: 'demo-lab-fattoria',
    title: 'Laboratorio creativo in fattoria',
    summary: 'Attivita manuali, animali e merenda per bambini 4-9 anni.',
    starts_at: new Date(Date.now() + 1000 * 60 * 60 * 24 * 8).toISOString(),
    municipality: { name: 'Campobasso' },
    categories: [{ name: 'Bambini' }, { name: 'Natura' }],
    available_total: 7,
    capacity_total: 25,
    is_free: false,
    family_friendly: true,
    tone: 'mint',
    imageLabel: 'Fattoria',
  },
  {
    ulid: 'demo-cena-delitto',
    title: 'Cena con delitto nel borgo',
    summary: 'Menu degustazione, attori dal vivo e gioco investigativo.',
    starts_at: new Date(Date.now() + 1000 * 60 * 60 * 24 * 12).toISOString(),
    municipality: { name: 'Bojano' },
    categories: [{ name: 'Cena' }, { name: 'Spettacolo' }],
    available_total: 5,
    capacity_total: 40,
    is_free: false,
    family_friendly: false,
    tone: 'coral',
    imageLabel: 'Teatro',
  },
  {
    ulid: 'demo-escursione',
    title: 'Escursione guidata sul tratturo',
    summary: 'Passeggiata lenta con guida e racconto del territorio.',
    starts_at: new Date(Date.now() + 1000 * 60 * 60 * 24 * 15).toISOString(),
    municipality: { name: 'Isernia' },
    categories: [{ name: 'Escursioni' }, { name: 'Cultura' }],
    available_total: 14,
    capacity_total: 30,
    is_free: true,
    family_friendly: true,
    tone: 'sky',
    imageLabel: 'Outdoor',
  },
];

export default defineComponent({
  name: 'App',
  setup() {
    const events = ref<EventItem[]>(demoEvents);
    const loading = ref(true);
    const activeTab = ref<Tab>('discover');
    const query = ref('');
    const selectedCategory = ref('Tutti');

    onMounted(async () => {
      try {
        const response = await fetch(`${apiBase}/events`);
        if (response.ok) {
          const payload = await response.json();
          const apiEvents = payload.data?.data ?? payload.data ?? [];
          if (apiEvents.length > 0) {
            events.value = apiEvents.map((event: EventItem, index: number) => ({
              ...demoEvents[index % demoEvents.length],
              ...event,
            }));
          }
        }
      } catch {
        events.value = demoEvents;
      } finally {
        loading.value = false;
      }
    });

    const categories = ['Tutti', 'Bambini', 'Cena', 'Natura', 'Cultura'];

    const filteredEvents = computed(() => events.value.filter((event) => {
      const text = `${event.title} ${event.summary} ${event.municipality?.name ?? ''} ${event.categories?.map((category) => category.name).join(' ') ?? ''}`.toLowerCase();
      const matchesQuery = query.value.trim() === '' || text.includes(query.value.toLowerCase());
      const matchesCategory = selectedCategory.value === 'Tutti'
        || event.categories?.some((category) => category.name === selectedCategory.value);

      return matchesQuery && matchesCategory;
    }));

    const progress = (event: EventItem) => {
      const available = event.available_total ?? 0;
      const capacity = event.capacity_total ?? 1;

      return Math.max(0, Math.min(100, ((capacity - available) / capacity) * 100));
    };

    const eventDate = (value: string) => new Date(value).toLocaleDateString('it-IT', {
      day: '2-digit',
      month: 'short',
    });

    const renderTab = (label: string, icon: string, tab: Tab) => h('button', {
      class: ['tab-button', activeTab.value === tab ? 'active' : ''],
      type: 'button',
      onClick: () => {
        activeTab.value = tab;
      },
    }, [
      h('span', { class: 'tab-icon' }, icon),
      h('span', label),
    ]);

    const renderCategory = (category: string) => h('button', {
      class: ['chip', selectedCategory.value === category ? 'active' : ''],
      type: 'button',
      onClick: () => {
        selectedCategory.value = category;
      },
    }, category);

    const renderEventCard = (event: EventItem, featured = false) => h('article', {
      class: ['event-card', featured ? 'featured' : '', `tone-${event.tone}`],
      key: event.ulid,
    }, [
      h('div', { class: 'event-art' }, [
        h('span', { class: 'event-date' }, eventDate(event.starts_at)),
        h('span', { class: 'image-label' }, event.imageLabel),
      ]),
      h('div', { class: 'event-content' }, [
        h('div', { class: 'event-tags' }, [
          ...(event.categories ?? []).slice(0, 2).map((category) => h('span', category.name)),
          h('span', event.is_free ? 'Gratis' : 'Sul posto'),
        ]),
        h('h3', event.title),
        h('p', event.summary),
        h('div', { class: 'event-meta' }, [
          h('span', event.municipality?.name ?? 'Molise'),
          h('span', event.family_friendly ? 'Famiglie' : 'Adulti'),
        ]),
        h('div', { class: 'availability' }, [
          h('div', [
            h('strong', `${event.available_total ?? 0}`),
            h('span', ' posti rimasti'),
          ]),
          h('div', { class: 'progress-track' }, [
            h('span', { style: { width: `${progress(event)}%` } }),
          ]),
        ]),
        h('button', { class: 'primary-action', type: 'button' }, 'Prenota'),
      ]),
    ]);

    const renderDiscover = () => h('section', { class: 'screen-content' }, [
      h('div', { class: 'mobile-hero' }, [
        h('p', 'Campobasso e provincia'),
        h('h1', 'Trova la prossima esperienza da vivere'),
        h('div', { class: 'search-box' }, [
          h('span', 'Cerca'),
          h('input', {
            value: query.value,
            type: 'search',
            placeholder: 'laboratorio, cena, escursione',
            onInput: (event: Event) => {
              query.value = (event.target as HTMLInputElement).value;
            },
          }),
        ]),
      ]),
      h('div', { class: 'quick-stats' }, [
        h('div', [h('strong', '18'), h('span', 'eventi')]),
        h('div', [h('strong', '5'), h('span', 'comuni')]),
        h('div', [h('strong', '42'), h('span', 'posti liberi')]),
      ]),
      h('div', { class: 'category-row' }, categories.map(renderCategory)),
      h('div', { class: 'section-title' }, [
        h('h2', 'In evidenza'),
        h('span', loading.value ? '...' : `${filteredEvents.value.length} risultati`),
      ]),
      filteredEvents.value[0] ? renderEventCard(filteredEvents.value[0], true) : h('p', { class: 'empty-state' }, 'Nessun evento trovato.'),
      h('div', { class: 'section-title compact' }, [
        h('h2', 'Questa settimana'),
        h('button', { type: 'button' }, 'Vedi tutti'),
      ]),
      h('div', { class: 'mini-list' }, filteredEvents.value.slice(1).map((event) => h('button', {
        class: 'mini-event',
        type: 'button',
        key: event.ulid,
      }, [
        h('span', { class: [`mini-art`, `tone-${event.tone}`] }, eventDate(event.starts_at)),
        h('span', { class: 'mini-copy' }, [
          h('strong', event.title),
          h('small', `${event.municipality?.name ?? 'Molise'} - ${event.available_total ?? 0} posti`),
        ]),
      ]))),
    ]);

    const renderBooking = () => h('section', { class: 'screen-content' }, [
      h('div', { class: 'ticket-card' }, [
        h('div', { class: 'ticket-top' }, [
          h('span', 'Prenotazione confermata'),
          h('strong', 'LAB-4821'),
        ]),
        h('h2', 'Laboratorio creativo in fattoria'),
        h('p', 'Sabato 24 luglio, ore 17:00 - Campobasso'),
        h('div', { class: 'qr-box' }, [
          h('div', { class: 'qr-grid' }, Array.from({ length: 25 }).map((_, index) => h('span', {
            class: index % 3 === 0 || index % 7 === 0 ? 'dark' : '',
          }))),
        ]),
        h('button', { class: 'primary-action wide', type: 'button' }, 'Mostra QR all ingresso'),
      ]),
      h('div', { class: 'info-panel' }, [
        h('h3', 'Partecipanti'),
        h('div', { class: 'split-row' }, [h('span', 'Adulti'), h('strong', '2')]),
        h('div', { class: 'split-row' }, [h('span', 'Bambini 4-7 anni'), h('strong', '1')]),
        h('div', { class: 'split-row' }, [h('span', 'Promemoria'), h('strong', 'Email')]),
      ]),
    ]);

    const renderOrganizer = () => h('section', { class: 'screen-content' }, [
      h('div', { class: 'section-title' }, [
        h('h2', 'Area organizzatore'),
        h('span', 'Demo'),
      ]),
      h('div', { class: 'info-panel organizer-panel' }, [
        h('span', { class: 'status-pill' }, 'Bozza'),
        h('h3', 'Cena messicana con musica live'),
        h('p', 'Completa scheda evento, posti, biglietti e invia alla moderazione.'),
        h('div', { class: 'stepper' }, [
          h('span', { class: 'done' }),
          h('span', { class: 'done' }),
          h('span'),
          h('span'),
        ]),
        h('button', { class: 'primary-action wide', type: 'button' }, 'Continua creazione'),
      ]),
      h('div', { class: 'metric-grid' }, [
        h('div', [h('strong', '25'), h('span', 'prenotati')]),
        h('div', [h('strong', '7'), h('span', 'in lista')]),
      ]),
    ]);

    const renderAdmin = () => h('section', { class: 'screen-content' }, [
      h('div', { class: 'section-title' }, [
        h('h2', 'Moderazione'),
        h('span', 'Admin'),
      ]),
      h('div', { class: 'review-list' }, [
        ['Cena messicana con musica', 'Ristorante Demo', 'Da approvare'],
        ['Degustazione in cantina', 'Enoteca Centro', 'Verifica profilo'],
        ['Mercatino serale', 'Pro Loco', 'Segnalazione'],
      ].map(([title, owner, status]) => h('article', { class: 'review-item' }, [
        h('div', [h('strong', title), h('span', owner)]),
        h('small', status),
      ]))),
      h('div', { class: 'metric-grid' }, [
        h('div', [h('strong', '3'), h('span', 'eventi pending')]),
        h('div', [h('strong', '128'), h('span', 'audit log')]),
      ]),
    ]);

    const currentScreen = () => {
      if (activeTab.value === 'booking') {
        return renderBooking();
      }

      if (activeTab.value === 'organizer') {
        return renderOrganizer();
      }

      if (activeTab.value === 'admin') {
        return renderAdmin();
      }

      return renderDiscover();
    };

    return () => h('main', { class: 'phone-stage' }, [
      h('div', { class: 'phone-shell' }, [
        h('header', { class: 'mobile-header' }, [
          h('div', [
            h('span', 'Eventi Locali'),
            h('strong', 'Ciao Alessio'),
          ]),
          h('button', { class: 'avatar-button', type: 'button' }, 'A'),
        ]),
        currentScreen(),
        h('nav', { class: 'bottom-tabs' }, [
          renderTab('Scopri', 'S', 'discover'),
          renderTab('QR', 'Q', 'booking'),
          renderTab('Org', 'O', 'organizer'),
          renderTab('Admin', 'A', 'admin'),
        ]),
      ]),
    ]);
  },
});
