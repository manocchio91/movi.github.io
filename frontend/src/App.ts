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

const ratingBreakdown = [
  { label: 'Qualita esperienza', value: 5 },
  { label: 'Organizzazione', value: 4 },
  { label: 'Location', value: 5 },
  { label: 'Qualita/prezzo', value: 4 },
  { label: 'Orari', value: 5 },
  { label: 'Staff', value: 5 },
];

const demoReviews = [
  {
    author: 'Marta C.',
    event: 'Laboratorio creativo in fattoria',
    rating: 5,
    comment: 'Esperienza curata, bambini coinvolti e staff molto presente.',
    date: '12 lug',
    recommend: true,
  },
  {
    author: 'Luca P.',
    event: 'Cena con delitto nel borgo',
    rating: 4,
    comment: 'Formato riuscito, tempi leggermente lunghi tra una portata e l altra.',
    date: '08 lug',
    recommend: true,
  },
  {
    author: 'Sara M.',
    event: 'Escursione guidata sul tratturo',
    rating: 3,
    comment: 'Bella guida, ma serviva una comunicazione migliore sul punto di ritrovo.',
    date: '02 lug',
    recommend: false,
  },
];

const starDistribution = [
  { stars: 5, count: 28 },
  { stars: 4, count: 11 },
  { stars: 3, count: 4 },
  { stars: 2, count: 1 },
  { stars: 1, count: 0 },
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
    const reviewRating = ref(5);
    const reviewRecommend = ref(true);
    const reviewChildren = ref(true);

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

    const renderScoreDots = (value: number, onSelect?: (score: number) => void) => h('div', { class: 'score-dots' }, [1, 2, 3, 4, 5].map((score) => h('button', {
      class: score <= value ? 'active' : '',
      type: 'button',
      onClick: () => { onSelect?.(score); },
    }, `${score}`)));

    const renderReviewSummary = (compact = false) => h('div', { class: ['review-summary', compact ? 'compact' : ''] }, [
      h('div', { class: 'rating-widget' }, [
        h('strong', '4.7'),
        h('span', 'media generale'),
        h('small', '44 recensioni'),
      ]),
      h('div', { class: 'review-kpis' }, [
        h('div', [h('strong', '91%'), h('span', 'soddisfatti')]),
        h('div', [h('strong', '88%'), h('span', 'consigliano')]),
      ]),
    ]);

    const renderStarChart = () => h('div', { class: 'star-chart' }, starDistribution.map((row) => h('div', { class: 'star-row', key: row.stars }, [
      h('span', `${row.stars}`),
      h('div', { class: 'star-track' }, [h('i', { style: { width: `${(row.count / 28) * 100}%` } })]),
      h('strong', `${row.count}`),
    ])));

    const renderReviewList = () => h('div', { class: 'review-feed' }, demoReviews.map((review) => h('article', { class: 'review-card', key: `${review.author}-${review.event}` }, [
      h('div', { class: 'review-head' }, [
        h('div', [h('strong', review.author), h('span', review.event)]),
        h('b', `${review.rating}/5`),
      ]),
      h('p', review.comment),
      h('div', { class: 'review-foot' }, [
        h('span', review.date),
        h('button', { type: 'button' }, 'Segnala'),
      ]),
    ])));

    const renderEvents = () => h('section', { class: 'screen-content' }, [
      h('div', { class: 'mobile-hero' }, [
        h('p', 'Esperienze locali'),
        h('h1', 'Scopri esperienze, non solo eventi'),
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
        h('div', [h('strong', `${events.value.length}`), h('span', 'esperienze')]),
        h('div', [h('strong', '3'), h('span', 'prenotazioni')]),
        h('div', [h('strong', '6'), h('span', 'notifiche')]),
      ]),
      h('div', { class: 'category-row' }, ['tutte', ...macroAreas].map((macro) => h('button', {
        class: ['chip', selectedMacro.value === macro ? 'active' : ''],
        type: 'button',
        onClick: () => { selectedMacro.value = macro; },
      }, macro))),
      h('div', { class: 'section-title' }, [
        h('h2', 'Esperienze pubblicate'),
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
      h('div', { class: 'info-panel follow-panel' }, [
        h('h3', 'Organizzatori seguiti'),
        h('p', 'Quando un locale seguito pubblica una nuova esperienza ricevi una notifica anche se la categoria non e tra le tue preferite.'),
        h('div', { class: 'split-row' }, [h('span', 'Fattoria Didattica Demo'), h('strong', 'segui')]),
        h('div', { class: 'split-row' }, [h('span', 'Enoteca Centro'), h('strong', 'segui')]),
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
      h('div', { class: 'section-title' }, [
        h('h2', 'Recensioni pubblicate'),
        h('span', 'recenti'),
      ]),
      renderReviewSummary(true),
      renderReviewList(),
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
      h('div', { class: 'review-form-panel' }, [
        h('div', { class: 'section-title compact' }, [
          h('h2', 'Lascia recensione'),
          h('span', 'check-in ok'),
        ]),
        h('div', { class: 'overall-rating' }, [
          h('span', 'Valutazione generale'),
          renderScoreDots(reviewRating.value, (score) => { reviewRating.value = score; }),
        ]),
        h('div', { class: 'rating-breakdown' }, ratingBreakdown.map((item) => h('div', { class: 'rating-line', key: item.label }, [
          h('span', item.label),
          renderScoreDots(item.value),
        ]))),
        h('div', { class: 'toggle-grid' }, [
          h('button', {
            class: reviewChildren.value ? 'active' : '',
            type: 'button',
            onClick: () => { reviewChildren.value = !reviewChildren.value; },
          }, 'Adatto ai bambini'),
          h('button', {
            class: reviewRecommend.value ? 'active' : '',
            type: 'button',
            onClick: () => { reviewRecommend.value = !reviewRecommend.value; },
          }, 'Lo consiglierei'),
        ]),
        h('label', { class: 'comment-box' }, [
          h('span', 'Commento'),
          h('textarea', { value: 'Esperienza molto curata, staff gentile e orari rispettati.', readonly: true }),
        ]),
        h('div', { class: 'image-upload-placeholder' }, [
          h('strong', 'Immagini'),
          h('span', 'Upload predisposto'),
        ]),
        h('button', { class: 'primary-action wide', type: 'button' }, 'Invia recensione'),
      ]),
    ]);

    const renderOrganizer = () => h('section', { class: 'screen-content' }, [
      h('div', { class: 'section-title' }, [
        h('h2', 'Dashboard SaaS locale'),
        h('span', 'organizer@example.test'),
      ]),
      h('div', { class: 'plan-card' }, [
        h('span', 'Piano attivo'),
        h('h3', 'Starter'),
        h('p', 'Esperienze illimitate, gestione prenotazioni e statistiche base. Pagamenti online predisposti per Stripe.'),
        h('div', { class: 'plan-row' }, [
          h('strong', '19 euro/mese'),
          h('button', { type: 'button' }, 'Cambia piano'),
        ]),
      ]),
      h('div', { class: 'info-panel organizer-panel' }, [
        h('span', { class: 'status-pill' }, 'draft -> published'),
        h('h3', 'Crea esperienza collegata al DB'),
        h('div', { class: 'booking-form' }, [
          h('label', ['Titolo', h('input', { value: 'Cena messicana con musica live', readonly: true })]),
          h('label', ['Macroarea', h('input', { value: 'cene ed esperienze gastronomiche', readonly: true })]),
          h('label', ['Capienza massima', h('input', { value: '40', readonly: true })]),
          h('label', ['Prezzo persona', h('input', { value: '25,00 euro', readonly: true })]),
        ]),
        h('button', { class: 'primary-action wide', type: 'button' }, 'Pubblica esperienza'),
      ]),
      h('div', { class: 'metric-grid' }, [
        h('div', [h('strong', '4'), h('span', 'esperienze attive')]),
        h('div', [h('strong', '72'), h('span', 'prenotazioni')]),
        h('div', [h('strong', '318'), h('span', 'visualizzazioni')]),
        h('div', [h('strong', '46'), h('span', 'follower')]),
        h('div', [h('strong', '42%'), h('span', 'ritorno clienti')]),
        h('div', [h('strong', '2'), h('span', 'sold out')]),
      ]),
      renderReviewSummary(),
      h('div', { class: 'chart-panel' }, [
        h('div', { class: 'section-title compact' }, [h('h2', 'Distribuzione stelle'), h('span', 'review')]),
        renderStarChart(),
      ]),
      h('div', { class: 'info-panel bi-panel' }, [
        h('h3', 'Organizer Opportunity Engine'),
        h('p', 'Analisi deterministica dello slot selezionato prima della pubblicazione.'),
        h('div', { class: 'suggestion-list' }, [
          h('span', 'Nessuna esperienza gastronomica nello stesso orario.'),
          h('span', 'Nello stesso comune e presente un laboratorio bambini alle 17:00.'),
          h('span', 'Valuta fascia 20:30 per ridurre sovrapposizioni.'),
        ]),
      ]),
      h('div', { class: 'chart-panel' }, [
        h('div', { class: 'section-title compact' }, [h('h2', 'Prenotazioni mensili'), h('span', 'BI')]),
        h('div', { class: 'bar-chart' }, [42, 58, 37, 74, 91, 66].map((value) => h('span', { style: { height: `${value}%` } }))),
      ]),
      h('div', { class: 'chart-panel' }, [
        h('div', { class: 'section-title compact' }, [h('h2', 'Evoluzione recensioni'), h('span', '30 giorni')]),
        h('div', { class: 'line-chart-lite' }, [32, 38, 35, 44, 51, 47, 58].map((value) => h('span', { style: { height: `${value}%` } }))),
      ]),
      h('div', { class: 'info-panel' }, [
        h('h3', 'Categorie migliori per recensioni'),
        h('div', { class: 'split-row' }, [h('span', 'Cene a tema'), h('strong', '4.8/5')]),
        h('div', { class: 'split-row' }, [h('span', 'Degustazioni'), h('strong', '4.6/5')]),
        h('div', { class: 'split-row' }, [h('span', 'Laboratori'), h('strong', '4.5/5')]),
      ]),
      h('div', { class: 'section-title' }, [
        h('h2', 'Recensioni organizer'),
        h('span', 'migliori e critiche'),
      ]),
      renderReviewList(),
      h('div', { class: 'planning-grid' }, [
        h('div', { class: 'planning-card calendar-card' }, [h('strong', 'Calendario'), h('span', '3 esperienze oggi'), h('small', 'colori per categoria')]),
        h('div', { class: 'planning-card map-card' }, [h('strong', 'Mappa'), h('span', '5 marker vicini'), h('small', 'distanza e fascia oraria')]),
      ]),
      h('div', { class: 'campaign-card' }, [
        h('span', 'Campagna attiva'),
        h('h3', 'Cena Messicana'),
        h('div', { class: 'campaign-metrics' }, [
          h('div', [h('strong', '640'), h('small', 'views')]),
          h('div', [h('strong', '112'), h('small', 'click')]),
          h('div', [h('strong', '41'), h('small', 'booking')]),
          h('div', [h('strong', '36%'), h('small', 'conversione')]),
        ]),
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
