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

type Tab = 'events' | 'customer' | 'booking' | 'organizer';

const apiBase = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1';

const macroAreas = [
  'bambini',
  'cene ed esperienze gastronomiche',
  'teatro e spettacoli',
  'degustazioni',
  'musica dal vivo',
  'natura ed escursioni',
  'cultura',
  'corsi e laboratori',
  'eventi gratuiti',
];

const demoEvents: EventItem[] = [
  {
    ulid: 'demo-lab-fattoria',
    title: 'Laboratorio creativo in fattoria',
    summary: 'Attivita manuali, animali e merenda per bambini 4-9 anni.',
    starts_at: new Date(Date.now() + 1000 * 60 * 60 * 24 * 8).toISOString(),
    municipality: { name: 'Campobasso' },
    categories: [{ name: 'bambini' }, { name: 'corsi e laboratori' }],
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
    categories: [{ name: 'cene ed esperienze gastronomiche' }, { name: 'teatro e spettacoli' }],
    available_total: 5,
    capacity_total: 40,
    is_free: false,
    family_friendly: false,
    tone: 'coral',
    imageLabel: 'Cena',
  },
  {
    ulid: 'demo-escursione',
    title: 'Escursione guidata sul tratturo',
    summary: 'Passeggiata lenta con guida e racconto del territorio.',
    starts_at: new Date(Date.now() + 1000 * 60 * 60 * 24 * 15).toISOString(),
    municipality: { name: 'Isernia' },
    categories: [{ name: 'natura ed escursioni' }, { name: 'cultura' }],
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
    const activeTab = ref<Tab>('events');
    const query = ref('');
    const selectedMacro = ref('tutte');
    const selectedPreferences = ref(['bambini', 'cene ed esperienze gastronomiche', 'teatro e spettacoli']);
    const internalNotifications = ref(true);
    const emailNotifications = ref(true);
    const promotionalNotifications = ref(true);

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

    const filteredEvents = computed(() => events.value.filter((event) => {
      const text = `${event.title} ${event.summary} ${event.municipality?.name ?? ''} ${event.categories?.map((category) => category.name).join(' ') ?? ''}`.toLowerCase();
      const matchesQuery = query.value.trim() === '' || text.includes(query.value.toLowerCase());
      const matchesMacro = selectedMacro.value === 'tutte'
        || event.categories?.some((category) => category.name === selectedMacro.value);

      return matchesQuery && matchesMacro;
    }));

    const mainEvent = computed(() => filteredEvents.value[0] ?? demoEvents[0]);

    const progress = (event: EventItem) => {
      const available = event.available_total ?? 0;
      const capacity = event.capacity_total ?? 1;

      return Math.max(0, Math.min(100, ((capacity - available) / capacity) * 100));
    };

    const eventDate = (value: string) => new Date(value).toLocaleDateString('it-IT', {
      day: '2-digit',
      month: 'short',
    });

    const togglePreference = (macro: string) => {
      selectedPreferences.value = selectedPreferences.value.includes(macro)
        ? selectedPreferences.value.filter((item) => item !== macro)
        : [...selectedPreferences.value, macro];
    };

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
          h('span', event.is_free ? 'Gratis' : 'Pagamento sul posto'),
        ]),
        h('h3', event.title),
        h('p', event.summary),
        h('div', { class: 'event-meta' }, [
          h('span', event.municipality?.name ?? 'Molise'),
          h('span', event.family_friendly ? 'Famiglie' : 'Adulti'),
        ]),
        h('div', { class: 'availability' }, [
          h('div', [h('strong', `${event.available_total ?? 0}`), h('span', ' posti rimasti')]),
          h('div', { class: 'progress-track' }, [h('span', { style: { width: `${progress(event)}%` } })]),
        ]),
        h('button', { class: 'primary-action', type: 'button', onClick: () => { activeTab.value = 'booking'; } }, 'Prenota ora'),
      ]),
    ]);

    const renderEvents = () => h('section', { class: 'screen-content' }, [
      h('div', { class: 'mobile-hero' }, [
        h('p', 'Area cliente'),
        h('h1', 'Scopri e prenota eventi locali'),
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
        h('div', [h('strong', `${events.value.length}`), h('span', 'eventi DB/API')]),
        h('div', [h('strong', '3'), h('span', 'prenotazioni')]),
        h('div', [h('strong', '6'), h('span', 'notifiche')]),
      ]),
      h('div', { class: 'category-row' }, ['tutte', ...macroAreas].map((macro) => h('button', {
        class: ['chip', selectedMacro.value === macro ? 'active' : ''],
        type: 'button',
        onClick: () => { selectedMacro.value = macro; },
      }, macro))),
      h('div', { class: 'section-title' }, [
        h('h2', 'Eventi pubblicati'),
        h('span', loading.value ? 'carico...' : `${filteredEvents.value.length} risultati`),
      ]),
      renderEventCard(mainEvent.value, true),
      h('div', { class: 'mini-list' }, filteredEvents.value.slice(1).map((event) => h('button', {
        class: 'mini-event',
        type: 'button',
        key: event.ulid,
        onClick: () => { activeTab.value = 'booking'; },
      }, [
        h('span', { class: [`mini-art`, `tone-${event.tone}`] }, eventDate(event.starts_at)),
        h('span', { class: 'mini-copy' }, [
          h('strong', event.title),
          h('small', `${event.municipality?.name ?? 'Molise'} - ${event.available_total ?? 0} posti`),
        ]),
      ]))),
    ]);

    const renderCustomer = () => h('section', { class: 'screen-content' }, [
      h('div', { class: 'section-title' }, [
        h('h2', 'Profilo cliente'),
        h('span', 'customer@example.test'),
      ]),
      h('div', { class: 'info-panel' }, [
        h('h3', 'Macroaree preferite'),
        h('p', 'Queste preferenze sono salvate via API in /me/preferences e usate per notificare nuovi eventi pertinenti.'),
        h('div', { class: 'preference-grid' }, macroAreas.map((macro) => h('button', {
          class: ['preference-pill', selectedPreferences.value.includes(macro) ? 'selected' : ''],
          type: 'button',
          onClick: () => { togglePreference(macro); },
        }, macro))),
        h('button', { class: 'primary-action wide', type: 'button' }, 'Salva preferenze'),
      ]),
      h('div', { class: 'info-panel' }, [
        h('h3', 'Preferenze notifiche'),
        h('label', { class: 'switch-row' }, [
          h('span', 'Notifiche interne'),
          h('input', {
            checked: internalNotifications.value,
            type: 'checkbox',
            onChange: (event: Event) => { internalNotifications.value = (event.target as HTMLInputElement).checked; },
          }),
        ]),
        h('label', { class: 'switch-row' }, [
          h('span', 'Email'),
          h('input', {
            checked: emailNotifications.value,
            type: 'checkbox',
            onChange: (event: Event) => { emailNotifications.value = (event.target as HTMLInputElement).checked; },
          }),
        ]),
        h('label', { class: 'switch-row' }, [
          h('span', 'Promozionali per categorie scelte'),
          h('input', {
            checked: promotionalNotifications.value,
            type: 'checkbox',
            onChange: (event: Event) => { promotionalNotifications.value = (event.target as HTMLInputElement).checked; },
          }),
        ]),
        h('div', { class: 'frequency-row' }, [
          h('span', 'Immediata'),
          h('span', 'Giornaliera'),
          h('span', 'Settimanale'),
        ]),
      ]),
      h('div', { class: 'notification-list' }, [
        h('article', [h('strong', 'Nuovo evento per bambini'), h('span', 'Laboratorio creativo in fattoria - 7 posti disponibili')]),
        h('article', [h('strong', 'Prenotazione confermata'), h('span', 'Codice EVT-DEMO01, pagamento sul posto')]),
      ]),
    ]);

    const renderBooking = () => h('section', { class: 'screen-content' }, [
      h('div', { class: 'ticket-card' }, [
        h('div', { class: 'ticket-top' }, [
          h('span', 'Form prenotazione'),
          h('strong', mainEvent.value.available_total === 0 ? 'sold_out' : 'published'),
        ]),
        h('h2', mainEvent.value.title),
        h('p', `${eventDate(mainEvent.value.starts_at)} - ${mainEvent.value.municipality?.name ?? 'Molise'}`),
        h('div', { class: 'booking-form' }, [
          h('label', ['Adulti', h('input', { value: '2', readonly: true })]),
          h('label', ['Bambini', h('input', { value: '1', readonly: true })]),
          h('label', ['Biglietto adulto', h('input', { value: '12,00 euro', readonly: true })]),
          h('label', ['Biglietto bambino', h('input', { value: '8,00 euro', readonly: true })]),
        ]),
        h('div', { class: 'price-box' }, [
          h('span', 'Totale previsto'),
          h('strong', '32,00 euro'),
          h('small', 'Pagamento sul posto'),
        ]),
        h('button', { class: 'primary-action wide', type: 'button' }, 'Conferma prenotazione'),
      ]),
      h('div', { class: 'info-panel' }, [
        h('h3', 'Le mie prenotazioni'),
        h('div', { class: 'split-row' }, [h('span', 'EVT-DEMO01'), h('strong', 'confirmed')]),
        h('div', { class: 'split-row' }, [h('span', 'Posti'), h('strong', '3')]),
        h('div', { class: 'split-row' }, [h('span', 'QR ingresso'), h('strong', 'pronto')]),
      ]),
    ]);

    const renderOrganizer = () => h('section', { class: 'screen-content' }, [
      h('div', { class: 'section-title' }, [
        h('h2', 'Area locale'),
        h('span', 'organizer@example.test'),
      ]),
      h('div', { class: 'info-panel organizer-panel' }, [
        h('span', { class: 'status-pill' }, 'draft -> published'),
        h('h3', 'Crea evento collegato al DB'),
        h('div', { class: 'booking-form' }, [
          h('label', ['Titolo', h('input', { value: 'Cena messicana con musica live', readonly: true })]),
          h('label', ['Macroarea', h('input', { value: 'cene ed esperienze gastronomiche', readonly: true })]),
          h('label', ['Capienza massima', h('input', { value: '40', readonly: true })]),
          h('label', ['Prezzo persona', h('input', { value: '25,00 euro', readonly: true })]),
        ]),
        h('button', { class: 'primary-action wide', type: 'button' }, 'Pubblica evento'),
      ]),
      h('div', { class: 'metric-grid' }, [
        h('div', [h('strong', '4'), h('span', 'eventi attivi')]),
        h('div', [h('strong', '72'), h('span', 'prenotazioni')]),
      ]),
      h('div', { class: 'review-list' }, [
        h('article', { class: 'review-item' }, [h('div', [h('strong', 'Laboratorio creativo'), h('span', '22 posti disponibili')]), h('small', 'published')]),
        h('article', { class: 'review-item' }, [h('div', [h('strong', 'Cena messicana'), h('span', '40 posti, 0 prenotati')]), h('small', 'draft')]),
      ]),
    ]);

    const currentScreen = () => {
      if (activeTab.value === 'customer') return renderCustomer();
      if (activeTab.value === 'booking') return renderBooking();
      if (activeTab.value === 'organizer') return renderOrganizer();

      return renderEvents();
    };

    return () => h('main', { class: 'phone-stage' }, [
      h('div', { class: 'phone-shell' }, [
        h('header', { class: 'mobile-header' }, [
          h('div', [
            h('span', 'Eventi Locali'),
            h('strong', 'Piattaforma eventi'),
          ]),
          h('button', { class: 'avatar-button', type: 'button' }, 'E'),
        ]),
        currentScreen(),
        h('nav', { class: 'bottom-tabs' }, [
          renderTab('Eventi', 'E', 'events'),
          renderTab('Cliente', 'C', 'customer'),
          renderTab('Prenota', 'P', 'booking'),
          renderTab('Locale', 'L', 'organizer'),
        ]),
      ]),
    ]);
  },
});
