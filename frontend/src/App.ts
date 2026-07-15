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
  indoor_outdoor?: string;
};

const apiBase = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1';

const demoEvents: EventItem[] = [
  {
    ulid: 'demo-lab-fattoria',
    title: 'Laboratorio creativo in fattoria',
    summary: 'Pomeriggio per bambini 4-9 anni con attivita manuali, animali e merenda.',
    starts_at: new Date(Date.now() + 1000 * 60 * 60 * 24 * 8).toISOString(),
    municipality: { name: 'Campobasso' },
    categories: [{ name: 'Laboratori creativi' }, { name: 'Fattorie e natura' }],
    available_total: 7,
    capacity_total: 25,
    is_free: false,
    family_friendly: true,
    indoor_outdoor: 'mixed',
  },
  {
    ulid: 'demo-cena-delitto',
    title: 'Cena con delitto nel borgo',
    summary: 'Menu degustazione, attori dal vivo e gioco investigativo a squadre.',
    starts_at: new Date(Date.now() + 1000 * 60 * 60 * 24 * 12).toISOString(),
    municipality: { name: 'Bojano' },
    categories: [{ name: 'Cene a tema' }, { name: 'Teatro e spettacoli' }],
    available_total: 5,
    capacity_total: 40,
    is_free: false,
    family_friendly: false,
    indoor_outdoor: 'indoor',
  },
  {
    ulid: 'demo-escursione',
    title: 'Escursione guidata sul tratturo',
    summary: 'Passeggiata lenta con guida, racconto del territorio e pranzo al sacco.',
    starts_at: new Date(Date.now() + 1000 * 60 * 60 * 24 * 15).toISOString(),
    municipality: { name: 'Isernia' },
    categories: [{ name: 'Escursioni' }, { name: 'Cultura' }],
    available_total: 14,
    capacity_total: 30,
    is_free: true,
    family_friendly: true,
    indoor_outdoor: 'outdoor',
  },
];

export default defineComponent({
  name: 'App',
  setup() {
    const events = ref<EventItem[]>(demoEvents);
    const loading = ref(true);
    const activeView = ref<'discover' | 'organizer' | 'admin'>('discover');
    const query = ref('');
    const selectedCity = ref('Tutti i comuni');

    onMounted(async () => {
      try {
        const response = await fetch(`${apiBase}/events`);
        if (response.ok) {
          const payload = await response.json();
          const apiEvents = payload.data?.data ?? payload.data ?? [];
          events.value = apiEvents.length > 0 ? apiEvents : demoEvents;
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
      const matchesCity = selectedCity.value === 'Tutti i comuni' || event.municipality?.name === selectedCity.value;

      return matchesQuery && matchesCity;
    }));

    const renderNavButton = (label: string, view: typeof activeView.value) => h('button', {
      class: ['nav-button', activeView.value === view ? 'active' : ''],
      type: 'button',
      onClick: () => {
        activeView.value = view;
      },
    }, label);

    const renderEventCard = (event: EventItem) => h('article', { class: 'event-card', key: event.ulid }, [
      h('div', { class: 'card-media' }, [
        h('span', { class: 'date-badge' }, new Date(event.starts_at).toLocaleDateString('it-IT', { day: '2-digit', month: 'short' })),
      ]),
      h('div', { class: 'card-body' }, [
        h('div', { class: 'tag-row' }, [
          ...(event.categories ?? []).slice(0, 2).map((category) => h('span', { class: 'tag' }, category.name)),
          event.is_free ? h('span', { class: 'tag free' }, 'Gratis') : h('span', { class: 'tag paid' }, 'Pagamento sul posto'),
        ]),
        h('h3', event.title),
        h('p', event.summary),
        h('div', { class: 'card-meta' }, [
          h('span', event.municipality?.name ?? 'Molise'),
          h('span', `${event.available_total ?? 0}/${event.capacity_total ?? 0} posti disponibili`),
          h('span', event.family_friendly ? 'Famiglie' : 'Adulti'),
        ]),
        h('div', { class: 'card-actions' }, [
          h('button', { class: 'button primary', type: 'button' }, 'Vedi dettagli'),
          h('button', { class: 'button secondary', type: 'button' }, 'Salva'),
        ]),
      ]),
    ]);

    const renderDiscover = () => h('section', { class: 'workspace' }, [
      h('div', { class: 'hero' }, [
        h('div', [
          h('p', { class: 'eyebrow' }, 'Campobasso e Molise'),
          h('h1', 'Eventi locali, posti veri, prenotazioni semplici'),
          h('p', { class: 'hero-copy' }, 'Scopri laboratori, cene a tema, escursioni e attivita per famiglie. La disponibilita e il percorso di prenotazione sono al centro del prodotto.'),
          h('div', { class: 'hero-actions' }, [
            h('button', { class: 'button primary', type: 'button' }, 'Esplora eventi'),
            h('button', { class: 'button secondary', type: 'button', onClick: () => { activeView.value = 'organizer'; } }, 'Pubblica un evento'),
          ]),
        ]),
        h('div', { class: 'booking-preview' }, [
          h('p', { class: 'panel-title' }, 'Flusso prenotazione'),
          h('ol', [
            h('li', 'Scoperta evento'),
            h('li', 'Disponibilita posti'),
            h('li', 'Prenotazione'),
            h('li', 'QR ingresso'),
          ]),
        ]),
      ]),
      h('div', { class: 'filters' }, [
        h('input', {
          value: query.value,
          type: 'search',
          placeholder: 'Cerca evento, comune o categoria',
          onInput: (event: Event) => {
            query.value = (event.target as HTMLInputElement).value;
          },
        }),
        h('select', {
          value: selectedCity.value,
          onChange: (event: Event) => {
            selectedCity.value = (event.target as HTMLSelectElement).value;
          },
        }, ['Tutti i comuni', 'Campobasso', 'Bojano', 'Isernia'].map((city) => h('option', { value: city }, city))),
        h('button', { class: 'button primary', type: 'button' }, 'Filtra'),
      ]),
      h('div', { class: 'section-heading' }, [
        h('h2', 'Eventi in evidenza'),
        h('p', loading.value ? 'Caricamento dati...' : `${filteredEvents.value.length} eventi disponibili`),
      ]),
      h('div', { class: 'event-grid' }, filteredEvents.value.map(renderEventCard)),
    ]);

    const renderOrganizer = () => h('section', { class: 'dashboard-grid' }, [
      h('div', { class: 'panel wide' }, [
        h('p', { class: 'eyebrow' }, 'Organizzatore'),
        h('h2', 'Crea e manda in approvazione un evento'),
        h('div', { class: 'form-grid' }, [
          h('label', ['Titolo evento', h('input', { value: 'Cena messicana con musica dal vivo', readonly: true })]),
          h('label', ['Comune', h('input', { value: 'Campobasso', readonly: true })]),
          h('label', ['Capienza', h('input', { value: '40 posti', readonly: true })]),
          h('label', ['Categoria', h('input', { value: 'Cene a tema', readonly: true })]),
        ]),
        h('div', { class: 'workflow' }, [
          h('span', 'Bozza'),
          h('span', 'In moderazione'),
          h('span', 'Pubblicato'),
        ]),
      ]),
      h('div', { class: 'panel' }, [
        h('p', { class: 'panel-title' }, 'Statistiche essenziali'),
        h('strong', '72%'),
        h('span', 'posti medi prenotati sugli eventi pubblicati'),
      ]),
      h('div', { class: 'panel' }, [
        h('p', { class: 'panel-title' }, 'Lista partecipanti'),
        h('strong', '25'),
        h('span', 'partecipanti attesi al prossimo evento'),
      ]),
    ]);

    const renderAdmin = () => h('section', { class: 'dashboard-grid' }, [
      h('div', { class: 'panel wide' }, [
        h('p', { class: 'eyebrow' }, 'Admin'),
        h('h2', 'Moderazione eventi'),
        h('table', [
          h('thead', h('tr', [h('th', 'Evento'), h('th', 'Organizzatore'), h('th', 'Stato'), h('th', 'Azione')])),
          h('tbody', [
            h('tr', [h('td', 'Cena messicana con musica'), h('td', 'Ristorante Demo'), h('td', 'pending_review'), h('td', h('button', { class: 'button primary', type: 'button' }, 'Approva'))]),
            h('tr', [h('td', 'Laboratorio creativo'), h('td', 'Fattoria Demo'), h('td', 'published'), h('td', h('button', { class: 'button secondary', type: 'button' }, 'Dettagli'))]),
          ]),
        ]),
      ]),
      h('div', { class: 'panel' }, [
        h('p', { class: 'panel-title' }, 'Organizzatori da verificare'),
        h('strong', '3'),
        h('span', 'profili in attesa'),
      ]),
      h('div', { class: 'panel' }, [
        h('p', { class: 'panel-title' }, 'Audit log'),
        h('strong', '128'),
        h('span', 'operazioni tracciate'),
      ]),
    ]);

    return () => h('main', { class: 'app-shell' }, [
      h('header', { class: 'app-header' }, [
        h('div', [
          h('p', { class: 'brand-kicker' }, 'Eventi Locali'),
          h('strong', 'Molise Experience Platform'),
        ]),
        h('nav', [
          renderNavButton('Scopri', 'discover'),
          renderNavButton('Organizzatore', 'organizer'),
          renderNavButton('Admin', 'admin'),
        ]),
      ]),
      activeView.value === 'discover' ? renderDiscover() : null,
      activeView.value === 'organizer' ? renderOrganizer() : null,
      activeView.value === 'admin' ? renderAdmin() : null,
    ]);
  },
});
