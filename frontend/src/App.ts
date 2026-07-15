import { defineComponent, h, onMounted, ref } from 'vue';

type EventItem = {
  ulid: string;
  title: string;
  summary: string;
  starts_at: string;
  municipality?: { name: string };
  categories?: Array<{ name: string }>;
};

const apiBase = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1';

export default defineComponent({
  name: 'App',
  setup() {
    const events = ref<EventItem[]>([]);
    const loading = ref(true);

    onMounted(async () => {
      const response = await fetch(`${apiBase}/events`);
      const payload = await response.json();
      events.value = payload.data ?? [];
      loading.value = false;
    });

    return () => h('main', { class: 'shell' }, [
      h('header', { class: 'topbar' }, [
        h('div', [
          h('p', { class: 'eyebrow' }, 'Molise'),
          h('h1', 'Eventi ed esperienze locali'),
        ]),
        h('a', { class: 'button', href: '/login' }, 'Accedi'),
      ]),
      h('section', { class: 'filters', 'aria-label': 'Filtri eventi' }, [
        h('input', { type: 'search', placeholder: 'Cerca evento, comune o categoria' }),
        h('button', { class: 'button', type: 'button' }, 'Filtra'),
      ]),
      h('section', { class: 'event-list', 'aria-live': 'polite' }, [
        loading.value ? h('p', 'Caricamento eventi...') : null,
        ...events.value.map((event) => h('article', { class: 'event-card', key: event.ulid }, [
          h('p', { class: 'date' }, new Date(event.starts_at).toLocaleDateString('it-IT', { dateStyle: 'medium' })),
          h('h2', event.title),
          h('p', event.summary),
          h('p', { class: 'meta' }, `${event.municipality?.name ?? ''} · ${event.categories?.map((category) => category.name).join(', ') ?? ''}`),
        ])),
        !loading.value && events.value.length === 0 ? h('p', 'Nessun evento pubblicato trovato.') : null,
      ]),
    ]);
  },
});
