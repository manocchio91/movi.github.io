import { computed, defineComponent, h, onMounted, ref } from 'vue';

type Role = 'guest' | 'customer' | 'organizer' | 'admin';
type AuthMode = 'login' | 'register-customer' | 'register-organizer';
type AppRoute =
  | '/login'
  | '/customer/dashboard'
  | '/customer/explore'
  | '/customer/successful-events'
  | '/customer/bookings'
  | '/customer/preferences'
  | '/organizer/dashboard'
  | '/organizer/pending'
  | '/organizer/events'
  | '/organizer/create-event'
  | `/organizer/events/${string}/edit`
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

type OrganizerProfileSummary = {
  ulid: string;
  name: string;
  verification_status: 'pending' | 'verified' | 'rejected';
};

type AuthUser = {
  role: Exclude<Role, 'guest'>;
  name: string;
  email: string;
  status: 'active' | 'pending' | 'suspended';
  organizer_profile: OrganizerProfileSummary | null;
  redirect_to: AppRoute;
};

type AuthResponse = {
  user: Omit<AuthUser, 'redirect_to'>;
  token: string;
  redirect_to: AppRoute;
};

type SessionResponse = Omit<AuthResponse, 'token'>;

type DemoCredentials = {
  role: Exclude<Role, 'guest'>;
  email: string;
  password: string;
};

type OrganizerReviewAnalytics = {
  reviews_count: number;
};

type OrganizerDashboardBi = {
  future_events: number;
  completed_events: number;
  unpublished_events: number;
  bookings_received: number;
  participants_total: number;
  available_places: number;
  occupied_places: number;
  views_total: number;
  followers_total: number;
  average_rating: number | null;
  review_analytics: OrganizerReviewAnalytics;
};

type OrganizerDashboardData = {
  bi: OrganizerDashboardBi;
};

type OrganizerDashboardResponse = {
  data: OrganizerDashboardData;
};

type OrganizerEventStatus = 'draft' | 'pending_review' | 'published' | 'sold_out' | 'cancelled' | 'completed' | 'rejected' | 'suspended' | 'archived';
type OrganizerEventFilter = 'future' | 'completed' | 'unpublished';

type OrganizerEvent = {
  ulid: string;
  title: string;
  status: OrganizerEventStatus;
  starts_at: string;
  ends_at: string | null;
  capacity_total: number;
  available_total: number;
};

type OrganizerEventSummary = {
  municipality: string | null;
  starts_at: string;
  capacity_total: number;
  booked_people: number;
  available_total: number;
  fill_rate: number;
  checked_in_people: number;
  no_show: number;
  average_rating: number;
  reviews_count: number;
  bookings_open: boolean;
};

type OrganizerEventListItem = {
  event: OrganizerEvent;
  summary: OrganizerEventSummary;
};

type OrganizerEventPaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
};

type OrganizerEventListResponse = {
  data: OrganizerEventListItem[];
  meta: OrganizerEventPaginationMeta;
};

type EventFormMode = 'create' | 'edit';

type EventCategory = { id: number; name: string };
type EventMunicipality = { id: number; name: string; province?: { code?: string } };
type EventFormTicket = {
  ulid?: string;
  name: string;
  audience: 'generic' | 'adult' | 'child';
  price: string;
  max_quantity: string;
  booked_quantity: number;
  is_active: boolean;
};
type OrganizerEventDetail = OrganizerEvent & {
  summary: string;
  description: string;
  municipality_id: number;
  municipality: EventMunicipality;
  categories: EventCategory[];
  ticket_types: Array<{
    ulid: string;
    name: string;
    audience: 'generic' | 'adult' | 'child';
    price_cents: number;
    max_quantity: number;
    booked_quantity: number;
    is_active: boolean;
  }>;
  is_free: boolean;
  family_friendly: boolean;
  indoor_outdoor: 'indoor' | 'outdoor' | 'mixed';
};
type EventFormData = {
  ulid: string | null;
  status: OrganizerEventStatus;
  title: string;
  summary: string;
  description: string;
  municipality_id: string;
  category_id: string;
  starts_at: string;
  ends_at: string;
  capacity_total: string;
  is_free: boolean;
  family_friendly: boolean;
  indoor_outdoor: 'indoor' | 'outdoor' | 'mixed';
};

class ApiError extends Error {
  constructor(public readonly status: number, message: string, public readonly errors: Record<string, string[]> = {}) {
    super(message);
  }
}

const apiBase = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1';

const organizerEventStatusLabels: Record<OrganizerEventStatus, string> = {
  draft: 'Bozza',
  pending_review: 'In revisione',
  published: 'Pubblicato',
  sold_out: 'Esaurito',
  cancelled: 'Annullato',
  completed: 'Concluso',
  rejected: 'Rifiutato',
  suspended: 'Sospeso',
  archived: 'Archiviato',
};

const sessionStorageKey = 'app-eventi.auth-token';

const emptyEventForm = (): EventFormData => ({
  ulid: null,
  status: 'draft',
  title: '',
  summary: '',
  description: '',
  municipality_id: '',
  category_id: '',
  starts_at: '',
  ends_at: '',
  capacity_total: '',
  is_free: false,
  family_friendly: false,
  indoor_outdoor: 'mixed',
});

const emptyEventTicket = (): EventFormTicket => ({
  name: 'Ingresso',
  audience: 'generic',
  price: '0,00',
  max_quantity: '',
  booked_quantity: 0,
  is_active: true,
});

const apiDateToLocalInput = (value: string | null) => {
  if (!value) return '';
  const date = new Date(value);
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
};

const localInputToApiDate = (value: string) => value ? new Date(value).toISOString() : null;

const centsToEuroInput = (cents: number) => `${Math.floor(cents / 100)},${String(cents % 100).padStart(2, '0')}`;

const euroInputToCents = (value: string) => {
  const normalized = value.trim().replace(',', '.');
  if (!/^\d+(\.\d{1,2})?$/.test(normalized)) return null;
  const [euros, decimals = ''] = normalized.split('.');
  return Number(euros) * 100 + Number(decimals.padEnd(2, '0'));
};

const demoUsers: DemoCredentials[] = [
  { role: 'customer', email: 'customer@example.test', password: 'password' },
  { role: 'organizer', email: 'organizer@app-eventi.test', password: 'password' },
  { role: 'admin', email: 'admin@example.test', password: 'password' },
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
  if (route === '/organizer/pending') return role === 'organizer';
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
    const currentUser = ref<AuthUser | null>(customerMobilePreview ? {
      role: 'customer',
      name: 'Cliente Demo',
      email: demoUsers[0].email,
      status: 'active',
      organizer_profile: null,
      redirect_to: '/customer/dashboard',
    } : null);
    const token = ref<string | null>(null);
    const route = ref<AppRoute>(customerMobilePreview ? '/customer/dashboard' : '/login');
    const authMode = ref<AuthMode>('login');
    const authLoading = ref(false);
    const authRestoring = ref(!customerMobilePreview);
    const authError = ref('');
    const authFieldErrors = ref<Record<string, string[]>>({});
    const loginForm = ref({ email: '', password: '' });
    const customerRegistrationForm = ref({ name: '', email: '', password: '', password_confirmation: '' });
    const organizerRegistrationForm = ref({
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
      organization_name: '',
      organization_type: '',
    });
    const loading = ref(false);
    const apiError = ref('');
    const organizerDashboard = ref<OrganizerDashboardData | null>(null);
    const dashboardLoading = ref(false);
    const dashboardError = ref('');
    const dashboardForbidden = ref(false);
    const organizerEvents = ref<OrganizerEventListItem[]>([]);
    const organizerEventsLoading = ref(false);
    const organizerEventsError = ref('');
    const organizerEventsForbidden = ref(false);
    const organizerEventsPage = ref(1);
    const organizerEventsMeta = ref<OrganizerEventPaginationMeta | null>(null);
    const organizerEventsFilter = ref<OrganizerEventFilter>('future');
    const cancellingOrganizerEvent = ref<string | null>(null);
    const organizerEventsLoadedKey = ref('');
    const eventFormMode = ref<EventFormMode>('create');
    const eventFormData = ref<EventFormData>(emptyEventForm());
    const eventFormLoading = ref(false);
    const eventFormSubmitting = ref(false);
    const eventFormErrors = ref<Record<string, string[]>>({});
    const eventFormGeneralError = ref('');
    const eventFormSuccess = ref('');
    const categoriesLoading = ref(false);
    const eventCategories = ref<EventCategory[]>([]);
    const eventMunicipalities = ref<EventMunicipality[]>([]);
    const tickets = ref<EventFormTicket[]>([emptyEventTicket()]);
    let organizerEventsRequestId = 0;
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

    const api = async <T = any>(path: string, options: RequestInit = {}): Promise<T | null> => {
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
        const errorPayload = await response.json().catch(() => null) as { message?: string; errors?: Record<string, string[]> } | null;
        throw new ApiError(response.status, errorPayload?.message ?? `API ${path}: ${response.status}`, errorPayload?.errors ?? {});
      }

      return response.status === 204 ? null : response.json() as Promise<T>;
    };

    const clearOrganizerData = () => {
      organizerDashboard.value = null;
      organizerEvents.value = [];
      organizerEventsLoading.value = false;
      organizerEventsError.value = '';
      organizerEventsForbidden.value = false;
      organizerEventsPage.value = 1;
      organizerEventsMeta.value = null;
      organizerEventsLoadedKey.value = '';
      cancellingOrganizerEvent.value = null;
      eventFormData.value = emptyEventForm();
      tickets.value = [emptyEventTicket()];
      eventFormErrors.value = {};
      eventFormGeneralError.value = '';
      eventFormSuccess.value = '';
      eventCategories.value = [];
      eventMunicipalities.value = [];
      reviewEvents.value = [];
      calendarDays.value = [];
      selectedDay.value = null;
      opportunities.value = null;
    };

    const loadOrganizerDashboard = async (force = false) => {
      if (role.value !== 'organizer' || !token.value) return;
      if (organizerDashboard.value && !force) return;

      dashboardLoading.value = true;
      dashboardError.value = '';
      dashboardForbidden.value = false;
      organizerDashboard.value = null;

      try {
        const response = await api<OrganizerDashboardResponse>('/organizer/dashboard');
        organizerDashboard.value = response?.data ?? null;
        if (!organizerDashboard.value) dashboardError.value = 'La dashboard non ha restituito dati.';
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          clearOrganizerData();
          localStorage.removeItem(sessionStorageKey);
          token.value = null;
          currentUser.value = null;
          route.value = '/login';
          apiError.value = 'Sessione scaduta o non valida. Accedi di nuovo.';
          return;
        }

        if (error instanceof ApiError && error.status === 403) {
          dashboardForbidden.value = true;
          dashboardError.value = error.message || 'Accesso non autorizzato o profilo Organizer non approvato.';
          return;
        }

        dashboardError.value = error instanceof Error ? error.message : 'Impossibile caricare la dashboard Organizer.';
      } finally {
        dashboardLoading.value = false;
      }
    };

    const loadOrganizerEvents = async (force = false) => {
      if (role.value !== 'organizer' || !token.value) return;

      const requestKey = `${organizerEventsFilter.value}:${organizerEventsPage.value}`;
      if (!force && organizerEventsLoadedKey.value === requestKey) return;

      const requestId = ++organizerEventsRequestId;
      organizerEventsLoading.value = true;
      organizerEventsError.value = '';
      organizerEventsForbidden.value = false;
      organizerEvents.value = [];
      organizerEventsMeta.value = null;

      try {
        const query = new URLSearchParams({
          filter: organizerEventsFilter.value,
          page: String(organizerEventsPage.value),
          per_page: '12',
          sort: organizerEventsFilter.value === 'completed' ? 'desc' : 'asc',
        });
        const response = await api<OrganizerEventListResponse>(`/organizer/events?${query.toString()}`);
        if (requestId !== organizerEventsRequestId) return;

        organizerEvents.value = response?.data ?? [];
        organizerEventsMeta.value = response?.meta ?? null;
        organizerEventsLoadedKey.value = requestKey;
        if (!response?.meta) organizerEventsError.value = 'La lista eventi non ha restituito metadati di paginazione.';
      } catch (error) {
        if (requestId !== organizerEventsRequestId) return;

        if (error instanceof ApiError && error.status === 401) {
          clearOrganizerData();
          localStorage.removeItem(sessionStorageKey);
          token.value = null;
          currentUser.value = null;
          route.value = '/login';
          apiError.value = 'Sessione scaduta o non valida. Accedi di nuovo.';
          return;
        }

        if (error instanceof ApiError && error.status === 403) {
          organizerEventsForbidden.value = true;
          organizerEventsError.value = error.message || 'Accesso non autorizzato o profilo Organizer non approvato.';
          return;
        }

        organizerEventsError.value = error instanceof Error ? error.message : 'Impossibile caricare la lista eventi Organizer.';
      } finally {
        if (requestId === organizerEventsRequestId) organizerEventsLoading.value = false;
      }
    };

    const applyEventDetailToForm = (event: OrganizerEventDetail) => {
      eventFormData.value = {
        ulid: event.ulid,
        status: event.status,
        title: event.title,
        summary: event.summary,
        description: event.description,
        municipality_id: String(event.municipality_id),
        category_id: String(event.categories[0]?.id ?? ''),
        starts_at: apiDateToLocalInput(event.starts_at),
        ends_at: apiDateToLocalInput(event.ends_at),
        capacity_total: String(event.capacity_total),
        is_free: event.is_free,
        family_friendly: event.family_friendly,
        indoor_outdoor: event.indoor_outdoor,
      };
      tickets.value = event.ticket_types.length > 0 ? event.ticket_types.map((ticket) => ({
        ulid: ticket.ulid,
        name: ticket.name,
        audience: ticket.audience,
        price: centsToEuroInput(ticket.price_cents),
        max_quantity: String(ticket.max_quantity),
        booked_quantity: ticket.booked_quantity,
        is_active: ticket.is_active,
      })) : [emptyEventTicket()];
      if (event.municipality && !eventMunicipalities.value.some((item) => item.id === event.municipality.id)) {
        eventMunicipalities.value.push(event.municipality);
      }
    };

    const loadEventForm = async (eventUlid?: string) => {
      if (role.value !== 'organizer' || !token.value) return;
      eventFormMode.value = eventUlid ? 'edit' : 'create';
      eventFormLoading.value = true;
      categoriesLoading.value = true;
      eventFormGeneralError.value = '';
      eventFormSuccess.value = '';
      eventFormErrors.value = {};
      if (!eventUlid) {
        eventFormData.value = emptyEventForm();
        tickets.value = [emptyEventTicket()];
      }

      try {
        const [categoryPayload, municipalityPayload, eventPayload] = await Promise.all([
          api<{ data: EventCategory[] }>('/categories'),
          api<{ data: EventMunicipality[] }>('/locations/search'),
          eventUlid ? api<{ data: OrganizerEventDetail }>(`/organizer/events/${eventUlid}`) : Promise.resolve(null),
        ]);
        eventCategories.value = categoryPayload?.data ?? [];
        eventMunicipalities.value = municipalityPayload?.data ?? [];
        if (eventPayload?.data) applyEventDetailToForm(eventPayload.data);
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          clearSession('Sessione scaduta o non valida. Accedi di nuovo.');
          return;
        }
        if (error instanceof ApiError && error.status === 403) {
          eventFormGeneralError.value = 'Non sei autorizzato ad accedere a questo evento.';
        } else if (error instanceof ApiError && error.status === 404) {
          eventFormGeneralError.value = 'Evento non trovato.';
        } else {
          eventFormGeneralError.value = 'Impossibile caricare il form evento. Riprova.';
        }
      } finally {
        eventFormLoading.value = false;
        categoriesLoading.value = false;
      }
    };

    const eventPayload = () => {
      const errors: Record<string, string[]> = {};
      const ticketPayload = tickets.value.map((ticket, index) => {
        const priceCents = eventFormData.value.is_free ? 0 : euroInputToCents(ticket.price);
        const maxQuantity = Number(ticket.max_quantity);
        if (priceCents === null) errors[`ticket_types.${index}.price_cents`] = ['Inserisci un prezzo valido con massimo due decimali.'];
        if (!Number.isInteger(maxQuantity) || maxQuantity < 1) errors[`ticket_types.${index}.max_quantity`] = ['Inserisci una quantità valida.'];
        if (maxQuantity < ticket.booked_quantity) errors[`ticket_types.${index}.max_quantity`] = [`La quantità non può essere inferiore a ${ticket.booked_quantity}.`];

        return {
          ...(ticket.ulid ? { ulid: ticket.ulid } : {}),
          name: ticket.name,
          audience: ticket.audience,
          price_cents: priceCents,
          max_quantity: maxQuantity,
          is_active: ticket.is_active,
        };
      });

      if (tickets.value.length === 0) errors.ticket_types = ['Aggiungi almeno un biglietto.'];
      eventFormErrors.value = errors;
      if (Object.keys(errors).length > 0) return null;

      return {
        title: eventFormData.value.title,
        summary: eventFormData.value.summary,
        description: eventFormData.value.description,
        municipality_id: Number(eventFormData.value.municipality_id),
        category_ids: [Number(eventFormData.value.category_id)],
        starts_at: localInputToApiDate(eventFormData.value.starts_at),
        ends_at: localInputToApiDate(eventFormData.value.ends_at),
        capacity_total: Number(eventFormData.value.capacity_total),
        is_free: eventFormData.value.is_free,
        family_friendly: eventFormData.value.family_friendly,
        indoor_outdoor: eventFormData.value.indoor_outdoor,
        ticket_types: ticketPayload,
      };
    };

    const submitEventForm = async (publish: boolean) => {
      if (eventFormSubmitting.value) return;
      eventFormGeneralError.value = '';
      eventFormSuccess.value = '';
      eventFormErrors.value = {};
      const payload = eventPayload();
      if (!payload) {
        eventFormGeneralError.value = 'Correggi i campi evidenziati.';
        return;
      }

      eventFormSubmitting.value = true;
      try {
        const isEdit = eventFormMode.value === 'edit' && eventFormData.value.ulid;
        const saved = await api<{ data: OrganizerEventDetail }>(
          isEdit ? `/organizer/events/${eventFormData.value.ulid}` : '/organizer/events',
          { method: isEdit ? 'PUT' : 'POST', body: JSON.stringify(payload) },
        );
        if (!saved?.data) throw new Error('Risposta evento non valida.');

        let finalEvent = saved.data;
        if (publish) {
          const published = await api<{ data: OrganizerEventDetail }>(`/organizer/events/${saved.data.ulid}/publish`, { method: 'POST' });
          if (!published?.data) throw new Error('Risposta pubblicazione non valida.');
          finalEvent = published.data;
        }

        eventFormMode.value = 'edit';
        route.value = `/organizer/events/${finalEvent.ulid}/edit`;
        applyEventDetailToForm(finalEvent);
        eventFormSuccess.value = publish ? 'Evento pubblicato.' : 'Evento salvato.';
        organizerEventsLoadedKey.value = '';
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          clearSession('Sessione scaduta o non valida. Accedi di nuovo.');
          return;
        }
        if (error instanceof ApiError && error.status === 422) {
          eventFormErrors.value = error.errors;
          eventFormGeneralError.value = 'Correggi i campi evidenziati.';
        } else if (error instanceof ApiError && error.status === 403) {
          eventFormGeneralError.value = 'Non sei autorizzato a modificare questo evento.';
        } else if (error instanceof ApiError && error.status === 404) {
          eventFormGeneralError.value = 'Evento non trovato.';
        } else {
          eventFormGeneralError.value = 'Impossibile salvare l’evento. Riprova.';
        }
      } finally {
        eventFormSubmitting.value = false;
      }
    };

    const loadOrganizerData = async () => {
      if (role.value !== 'organizer' || !token.value) return;

      loading.value = true;
      apiError.value = '';
      try {
        const [reviewGroups, availability, opportunityPayload] = await Promise.all([
          api('/organizer/reviews/events'),
          api(`/organizer/calendar/availability?month=${new Date().getMonth() + 1}&year=${new Date().getFullYear()}`),
          api('/organizer/opportunities'),
        ]);
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
      if (route.value === '/organizer/dashboard') await loadOrganizerDashboard();
      else if (route.value === '/organizer/events') await loadOrganizerEvents();
      else if (route.value === '/organizer/create-event') await loadEventForm();
      else if (route.value.startsWith('/organizer/events/') && route.value.endsWith('/edit')) {
        await loadEventForm(route.value.split('/')[3]);
      }
      else if (route.value.startsWith('/organizer')) await loadOrganizerData();
      if (route.value.startsWith('/customer')) await loadCustomerData();
      if (route.value.startsWith('/admin')) await loadAdminData();
    };

    const clearSession = (message = '') => {
      localStorage.removeItem(sessionStorageKey);
      currentUser.value = null;
      token.value = null;
      route.value = '/login';
      authError.value = message;
      apiError.value = '';
      dashboardError.value = '';
      dashboardForbidden.value = false;
      clearOrganizerData();
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

    const completeAuth = async (payload: AuthResponse | SessionResponse, nextToken: string) => {
      token.value = nextToken;
      currentUser.value = { ...payload.user, redirect_to: payload.redirect_to };
      localStorage.setItem(sessionStorageKey, nextToken);
      route.value = payload.redirect_to;

      if (route.value === '/organizer/pending') return;
      if (route.value === '/organizer/dashboard') await loadOrganizerDashboard(true);
      else if (route.value.startsWith('/organizer')) await loadOrganizerData();
      else if (route.value.startsWith('/customer')) await loadCustomerData();
      else if (route.value.startsWith('/admin')) await loadAdminData();
    };

    const submitAuth = async (path: '/auth/login' | '/auth/register', body: Record<string, string>) => {
      authLoading.value = true;
      authError.value = '';
      authFieldErrors.value = {};
      try {
        const response = await fetch(`${apiBase}${path}`, {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => null) as (AuthResponse & { message?: string; errors?: Record<string, string[]> }) | null;
        if (!response.ok || !payload?.token) {
          authFieldErrors.value = payload?.errors ?? {};
          authError.value = payload?.message ?? (response.status === 401 || response.status === 422
            ? 'I dati inseriti non sono validi.'
            : 'Accesso non disponibile. Riprova.');
          return;
        }

        await completeAuth(payload, payload.token);
      } catch (error) {
        authError.value = error instanceof Error
          ? 'Impossibile contattare il servizio. Verifica la connessione e riprova.'
          : 'Accesso non disponibile. Riprova.';
      } finally {
        authLoading.value = false;
      }
    };

    const login = () => submitAuth('/auth/login', loginForm.value);

    const registerCustomer = () => submitAuth('/auth/register', {
      ...customerRegistrationForm.value,
      role: 'customer',
    });

    const registerOrganizer = () => submitAuth('/auth/register', {
      ...organizerRegistrationForm.value,
      role: 'organizer',
    });

    const logout = async () => {
      const activeToken = token.value;
      if (activeToken) {
        try {
          await fetch(`${apiBase}/auth/logout`, {
            method: 'POST',
            headers: { Accept: 'application/json', Authorization: `Bearer ${activeToken}` },
          });
        } catch {
          // La sessione locale viene comunque rimossa; il token server scadrà o potrà essere revocato al prossimo accesso.
        }
      }
      clearSession();
    };

    onMounted(async () => {
      if (customerMobilePreview) return;
      const savedToken = localStorage.getItem(sessionStorageKey);
      if (!savedToken) {
        authRestoring.value = false;
        return;
      }

      try {
        const response = await fetch(`${apiBase}/me`, {
          headers: { Accept: 'application/json', Authorization: `Bearer ${savedToken}` },
        });
        const payload = await response.json().catch(() => null) as SessionResponse | null;
        if (!response.ok || !payload?.user || !payload.redirect_to) {
          clearSession('La sessione salvata non è più valida. Accedi di nuovo.');
          return;
        }
        await completeAuth(payload, savedToken);
      } catch {
        clearSession('Non è stato possibile verificare la sessione. Accedi di nuovo.');
      } finally {
        authRestoring.value = false;
      }
    });

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
      if (cancellingOrganizerEvent.value || !window.confirm('Confermi l’annullamento di questo evento?')) return;

      cancellingOrganizerEvent.value = eventUlid;
      organizerEventsError.value = '';
      try {
        await api(`/organizer/events/${eventUlid}/cancel`, {
          method: 'POST',
          body: JSON.stringify({
            reason_code: 'ORGANIZER_REQUEST',
            reason: 'Annullamento richiesto dall organizzatore dalla lista eventi.',
          }),
        });
        organizerEventsLoadedKey.value = '';
        await loadOrganizerEvents(true);
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          clearOrganizerData();
          localStorage.removeItem(sessionStorageKey);
          token.value = null;
          currentUser.value = null;
          route.value = '/login';
          apiError.value = 'Sessione scaduta o non valida. Accedi di nuovo.';
        } else if (error instanceof ApiError && error.status === 403) {
          organizerEventsForbidden.value = true;
          organizerEventsError.value = error.message || 'Non sei autorizzato ad annullare questo evento.';
          organizerEvents.value = [];
          organizerEventsMeta.value = null;
        } else {
          organizerEventsError.value = error instanceof Error ? error.message : 'Impossibile annullare l’evento.';
        }
      } finally {
        cancellingOrganizerEvent.value = null;
      }
    };

    const selectOrganizerEventsFilter = async (filter: OrganizerEventFilter) => {
      if (organizerEventsFilter.value === filter && organizerEventsPage.value === 1) return;
      organizerEventsFilter.value = filter;
      organizerEventsPage.value = 1;
      organizerEventsLoadedKey.value = '';
      await loadOrganizerEvents(true);
    };

    const changeOrganizerEventsPage = async (page: number) => {
      const lastPage = organizerEventsMeta.value?.last_page ?? 1;
      if (page < 1 || page > lastPage || page === organizerEventsPage.value) return;
      organizerEventsPage.value = page;
      organizerEventsLoadedKey.value = '';
      await loadOrganizerEvents(true);
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

    const authInput = (
      label: string,
      name: string,
      type: string,
      value: string,
      update: (value: string) => void,
    ) => h('label', { class: 'auth-field' }, [
      h('span', label),
      h('input', {
        name,
        type,
        value,
        autocomplete: type === 'password' ? 'new-password' : undefined,
        disabled: authLoading.value,
        onInput: (event: Event) => update((event.target as HTMLInputElement).value),
      }),
      authFieldErrors.value[name]?.map((message) => h('small', { class: 'field-error' }, message)),
    ]);

    const setAuthMode = (mode: AuthMode) => {
      authMode.value = mode;
      authError.value = '';
      authFieldErrors.value = {};
    };

    const renderLoginForm = () => h('form', {
      class: 'auth-form',
      onSubmit: (event: Event) => { event.preventDefault(); void login(); },
    }, [
      authInput('Email', 'email', 'email', loginForm.value.email, (value) => { loginForm.value.email = value; }),
      authInput('Password', 'password', 'password', loginForm.value.password, (value) => { loginForm.value.password = value; }),
      h('button', { class: 'auth-submit', type: 'submit', disabled: authLoading.value }, authLoading.value ? 'Accesso in corso…' : 'Accedi'),
      h('div', { class: 'demo-fill' }, demoUsers.map((user) => h('button', {
        type: 'button',
        disabled: authLoading.value,
        onClick: () => { loginForm.value = { email: user.email, password: user.password }; },
      }, `Compila ${user.role}`))),
    ]);

    const renderCustomerRegistrationForm = () => h('form', {
      class: 'auth-form',
      onSubmit: (event: Event) => { event.preventDefault(); void registerCustomer(); },
    }, [
      authInput('Nome', 'name', 'text', customerRegistrationForm.value.name, (value) => { customerRegistrationForm.value.name = value; }),
      authInput('Email', 'email', 'email', customerRegistrationForm.value.email, (value) => { customerRegistrationForm.value.email = value; }),
      authInput('Password', 'password', 'password', customerRegistrationForm.value.password, (value) => { customerRegistrationForm.value.password = value; }),
      authInput('Conferma password', 'password_confirmation', 'password', customerRegistrationForm.value.password_confirmation, (value) => { customerRegistrationForm.value.password_confirmation = value; }),
      h('button', { class: 'auth-submit', type: 'submit', disabled: authLoading.value }, authLoading.value ? 'Registrazione in corso…' : 'Crea account Cliente'),
    ]);

    const renderOrganizerRegistrationForm = () => h('form', {
      class: 'auth-form',
      onSubmit: (event: Event) => { event.preventDefault(); void registerOrganizer(); },
    }, [
      authInput('Nome referente', 'name', 'text', organizerRegistrationForm.value.name, (value) => { organizerRegistrationForm.value.name = value; }),
      authInput('Email', 'email', 'email', organizerRegistrationForm.value.email, (value) => { organizerRegistrationForm.value.email = value; }),
      authInput('Password', 'password', 'password', organizerRegistrationForm.value.password, (value) => { organizerRegistrationForm.value.password = value; }),
      authInput('Conferma password', 'password_confirmation', 'password', organizerRegistrationForm.value.password_confirmation, (value) => { organizerRegistrationForm.value.password_confirmation = value; }),
      authInput('Nome organizzazione', 'organization_name', 'text', organizerRegistrationForm.value.organization_name, (value) => { organizerRegistrationForm.value.organization_name = value; }),
      authInput('Tipo organizzazione', 'organization_type', 'text', organizerRegistrationForm.value.organization_type, (value) => { organizerRegistrationForm.value.organization_type = value; }),
      h('button', { class: 'auth-submit', type: 'submit', disabled: authLoading.value }, authLoading.value ? 'Registrazione in corso…' : 'Crea account Organizer'),
    ]);

    const renderLogin = () => h('section', { class: 'auth-screen' }, [
      h('div', { class: 'auth-panel' }, [
        h('span', { class: 'eyebrow' }, 'Accesso sicuro'),
        h('h1', 'Eventi Locali'),
        h('p', 'Accedi oppure crea un account reale. Le credenziali vengono sempre verificate dal backend.'),
        h('div', { class: 'auth-tabs', role: 'tablist' }, [
          h('button', { type: 'button', class: authMode.value === 'login' ? 'active' : '', onClick: () => setAuthMode('login') }, 'Accedi'),
          h('button', { type: 'button', class: authMode.value === 'register-customer' ? 'active' : '', onClick: () => setAuthMode('register-customer') }, 'Registrati come Cliente'),
          h('button', { type: 'button', class: authMode.value === 'register-organizer' ? 'active' : '', onClick: () => setAuthMode('register-organizer') }, 'Registrati come Organizer'),
        ]),
        authError.value ? h('p', { class: 'api-error', role: 'alert' }, authError.value) : null,
        authRestoring.value
          ? h('p', { class: 'auth-restoring' }, 'Verifica della sessione in corso…')
          : authMode.value === 'login'
            ? renderLoginForm()
            : authMode.value === 'register-customer'
              ? renderCustomerRegistrationForm()
              : renderOrganizerRegistrationForm(),
      ]),
    ]);

    const renderOrganizerPending = () => h('main', { class: 'auth-screen' }, [
      h('section', { class: 'auth-panel pending-panel' }, [
        h('span', { class: 'eyebrow' }, 'Profilo Organizer'),
        h('h1', 'Account in attesa di approvazione'),
        h('p', `Ciao ${currentUser.value?.name ?? ''}. Il profilo ${currentUser.value?.organizer_profile?.name ?? 'della tua organizzazione'} è stato ricevuto.`),
        h('p', 'Dashboard, eventi e funzioni operative saranno disponibili dopo la verifica da parte dello staff.'),
        h('strong', 'Stato: in revisione'),
        h('button', { class: 'auth-submit', type: 'button', onClick: () => { void logout(); } }, 'Logout'),
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
      if (dashboardLoading.value) {
        return [h('article', { class: 'wide-panel dashboard-state' }, [
          h('strong', 'Caricamento dashboard...'),
          h('p', 'Recupero dei KPI Organizer dal backend.'),
        ])];
      }

      if (dashboardForbidden.value) {
        return [h('article', { class: 'wide-panel dashboard-state dashboard-forbidden' }, [
          h('strong', 'Dashboard non accessibile'),
          h('p', dashboardError.value || 'Accesso non autorizzato o profilo Organizer non approvato.'),
        ])];
      }

      if (dashboardError.value) {
        return [h('article', { class: 'wide-panel dashboard-state dashboard-error' }, [
          h('strong', 'Impossibile caricare la dashboard'),
          h('p', dashboardError.value),
          h('button', { type: 'button', onClick: () => { void loadOrganizerDashboard(true); } }, 'Riprova'),
        ])];
      }

      const data = organizerDashboard.value;
      if (!data) return [emptyState('Nessun dato dashboard disponibile.')];
      const bi = data.bi;

      return [
        h('div', { class: 'metric-grid' }, [
          card('Eventi futuri', `${bi.future_events}`, 'pubblicati o sold out'),
          card('Eventi conclusi', `${bi.completed_events}`, 'stato completed'),
          card('Eventi non pubblicati', `${bi.unpublished_events}`, 'bozze e stati non pubblici'),
          card('Prenotazioni valide', `${bi.bookings_received}`, 'confermate o completate'),
          card('Partecipanti', `${bi.participants_total}`, 'persone in prenotazioni valide'),
          card('Posti disponibili', `${bi.available_places}`, 'eventi operativi futuri'),
          card('Posti occupati', `${bi.occupied_places}`, 'eventi operativi futuri'),
          card('Follower', `${bi.followers_total}`, 'utenti che seguono'),
          card('Visualizzazioni', `${bi.views_total}`, 'esperienze visualizzate'),
          card('Rating medio', bi.average_rating === null ? '—' : `${bi.average_rating}`, bi.average_rating === null ? 'Nessuna recensione' : 'recensioni approvate'),
          card('Numero recensioni', `${bi.review_analytics.reviews_count}`, 'recensioni approvate'),
        ]),
        h('article', { class: 'wide-panel dashboard-state' }, [
          h('strong', 'Andamento nel tempo'),
          h('p', 'Serie temporale non disponibile.'),
        ]),
      ];
    };

    const renderOrganizerEvents = () => {
      const filterLabels: Record<OrganizerEventFilter, string> = {
        future: 'Futuri',
        completed: 'Conclusi',
        unpublished: 'Non pubblicati',
      };
      const emptyMessages: Record<OrganizerEventFilter, string> = {
        future: 'Nessun evento futuro.',
        completed: 'Nessun evento concluso.',
        unpublished: 'Nessun evento non pubblicato.',
      };
      const filters = (Object.keys(filterLabels) as OrganizerEventFilter[]).map((filter) => h('button', {
        type: 'button',
        class: organizerEventsFilter.value === filter ? 'active' : '',
        disabled: organizerEventsLoading.value,
        onClick: () => { void selectOrganizerEventsFilter(filter); },
      }, filterLabels[filter]));
      const header = [
        h('div', { class: 'section-title' }, [
          h('div', [h('h2', 'I miei eventi'), h('span', 'dati reali, filtrati e paginati dal backend')]),
          h('button', { type: 'button', onClick: () => { void go('/organizer/create-event'); } }, 'Crea evento'),
        ]),
        h('div', { class: 'filter-strip organizer-event-filters' }, filters),
      ];

      if (organizerEventsLoading.value) {
        return [...header, h('article', { class: 'wide-panel organizer-events-state' }, [
          h('strong', 'Caricamento eventi...'),
          h('p', `Recupero degli eventi ${filterLabels[organizerEventsFilter.value].toLowerCase()}.`),
        ])];
      }

      if (organizerEventsError.value) {
        return [...header, h('article', { class: ['wide-panel', 'organizer-events-state', organizerEventsForbidden.value ? 'dashboard-forbidden' : 'dashboard-error'] }, [
          h('strong', organizerEventsForbidden.value ? 'Lista eventi non accessibile' : 'Impossibile caricare gli eventi'),
          h('p', organizerEventsError.value),
          organizerEventsForbidden.value ? null : h('button', { type: 'button', onClick: () => { void loadOrganizerEvents(true); } }, 'Riprova'),
        ])];
      }

      if (organizerEvents.value.length === 0) {
        return [...header, h('article', { class: 'wide-panel empty-state organizer-events-state' }, [
          h('strong', emptyMessages[organizerEventsFilter.value]),
          h('p', 'Il filtro è stato applicato dal backend.'),
        ])];
      }

      const cards = h('div', { class: 'event-grid' }, organizerEvents.value.map((item) => {
        const eventEndsAt = item.event.ends_at ?? item.event.starts_at;
        const canCancel = !['cancelled', 'completed', 'archived'].includes(item.event.status)
          && new Date(eventEndsAt).getTime() > Date.now();
        const canEdit = ['draft', 'published'].includes(item.event.status)
          && new Date(item.event.starts_at).getTime() > Date.now();

        return h('article', { class: 'experience-card event-management-card' }, [
          h('span', { class: `status-badge status-${item.event.status}` }, organizerEventStatusLabels[item.event.status]),
          h('h3', item.event.title),
          h('p', `${item.summary.municipality ?? 'Luogo non indicato'} · ${new Date(item.summary.starts_at).toLocaleString('it-IT')}`),
          h('div', { class: 'event-stats' }, [
            h('small', `Prenotati: ${item.summary.booked_people}`),
            h('small', `Capienza: ${item.summary.capacity_total}`),
            h('small', `Disponibili: ${item.summary.available_total}`),
            h('small', `Riempimento: ${item.summary.fill_rate}%`),
            item.event.status === 'completed' ? h('small', `Partecipanti: ${item.summary.checked_in_people}`) : null,
            item.event.status === 'completed' ? h('small', `Assenti: ${item.summary.no_show}`) : null,
            item.event.status === 'completed' && item.summary.reviews_count > 0 ? h('small', `Valutazione: ${item.summary.average_rating}`) : null,
          ]),
          canEdit || canCancel ? h('div', { class: 'action-row' }, [
            canEdit ? h('button', {
              type: 'button',
              onClick: () => { void go(`/organizer/events/${item.event.ulid}/edit`); },
            }, 'Modifica') : null,
            canCancel ? h('button', {
              type: 'button',
              class: 'danger-button',
              disabled: cancellingOrganizerEvent.value !== null,
              onClick: () => { void cancelOrganizerEvent(item.event.ulid); },
            }, cancellingOrganizerEvent.value === item.event.ulid ? 'Annullamento...' : 'Annulla evento') : null,
          ]) : null,
        ]);
      }));

      const meta = organizerEventsMeta.value;
      const pagination = meta ? h('nav', { class: 'organizer-events-pagination', 'aria-label': 'Paginazione eventi' }, [
        h('button', {
          type: 'button',
          disabled: meta.current_page <= 1 || organizerEventsLoading.value,
          onClick: () => { void changeOrganizerEventsPage(meta.current_page - 1); },
        }, 'Precedente'),
        h('span', `Pagina ${meta.current_page} di ${meta.last_page} · ${meta.total} eventi`),
        h('button', {
          type: 'button',
          disabled: meta.current_page >= meta.last_page || organizerEventsLoading.value,
          onClick: () => { void changeOrganizerEventsPage(meta.current_page + 1); },
        }, 'Successivo'),
      ]) : null;

      return [...header, cards, pagination];
    };

    const eventFieldErrors = (name: string) => eventFormErrors.value[name]?.map((message) => h('small', { class: 'field-error' }, message)) ?? [];

    const renderEventForm = () => {
      const eventUlid = eventFormData.value.ulid ?? undefined;
      const heading = eventFormMode.value === 'create' ? 'Crea evento' : 'Modifica evento';
      const retry = () => { void loadEventForm(eventUlid); };

      if (eventFormLoading.value) {
        return [h('article', { class: 'wide-panel organizer-events-state' }, [h('strong', 'Caricamento evento…')])];
      }

      if (eventFormGeneralError.value && eventCategories.value.length === 0) {
        return [h('article', { class: 'wide-panel dashboard-error' }, [
          h('strong', 'Impossibile caricare l’evento'),
          h('p', eventFormGeneralError.value),
          h('button', { type: 'button', onClick: retry }, 'Riprova'),
          h('button', { type: 'button', onClick: () => { void go('/organizer/events'); } }, 'Torna ai miei eventi'),
        ])];
      }

      const input = (label: string, name: keyof EventFormData, type = 'text') => h('label', { class: 'event-form-field' }, [
        h('span', label),
        h('input', {
          type,
          value: String(eventFormData.value[name] ?? ''),
          disabled: eventFormSubmitting.value,
          onInput: (event: Event) => { (eventFormData.value[name] as string) = (event.target as HTMLInputElement).value; },
        }),
        ...eventFieldErrors(name),
      ]);

      const ticketCards = tickets.value.map((ticket, index) => h('article', { class: 'ticket-editor' }, [
        h('div', { class: 'ticket-editor-heading' }, [
          h('strong', `Biglietto ${index + 1}`),
          ticket.ulid ? h('small', `Quantità prenotata: ${ticket.booked_quantity}`) : h('small', 'Nuovo biglietto'),
          h('button', {
            type: 'button',
            disabled: eventFormSubmitting.value || ticket.booked_quantity > 0,
            title: ticket.booked_quantity > 0 ? 'Un biglietto prenotato non può essere rimosso.' : '',
            onClick: () => { tickets.value.splice(index, 1); },
          }, 'Rimuovi'),
        ]),
        h('div', { class: 'event-form-grid' }, [
          h('label', { class: 'event-form-field' }, [
            h('span', 'Nome biglietto'),
            h('input', { value: ticket.name, disabled: eventFormSubmitting.value, onInput: (event: Event) => { ticket.name = (event.target as HTMLInputElement).value; } }),
            ...eventFieldErrors(`ticket_types.${index}.name`),
          ]),
          h('label', { class: 'event-form-field' }, [
            h('span', 'Destinatari'),
            h('select', { value: ticket.audience, disabled: eventFormSubmitting.value, onChange: (event: Event) => { ticket.audience = (event.target as HTMLSelectElement).value as EventFormTicket['audience']; } }, [
              h('option', { value: 'generic' }, 'Tutti'),
              h('option', { value: 'adult' }, 'Adulti'),
              h('option', { value: 'child' }, 'Bambini'),
            ]),
          ]),
          h('label', { class: 'event-form-field' }, [
            h('span', 'Prezzo (€)'),
            h('input', { value: eventFormData.value.is_free ? '0,00' : ticket.price, disabled: eventFormSubmitting.value || eventFormData.value.is_free, inputmode: 'decimal', onInput: (event: Event) => { ticket.price = (event.target as HTMLInputElement).value; } }),
            ...eventFieldErrors(`ticket_types.${index}.price_cents`),
          ]),
          h('label', { class: 'event-form-field' }, [
            h('span', 'Quantità disponibile'),
            h('input', { type: 'number', min: Math.max(1, ticket.booked_quantity), value: ticket.max_quantity, disabled: eventFormSubmitting.value, onInput: (event: Event) => { ticket.max_quantity = (event.target as HTMLInputElement).value; } }),
            ...eventFieldErrors(`ticket_types.${index}.max_quantity`),
          ]),
        ]),
      ]));

      return [
        h('div', { class: 'section-title' }, [
          h('div', [h('h2', heading), eventFormMode.value === 'edit' ? h('span', `Stato: ${organizerEventStatusLabels[eventFormData.value.status]}`) : h('span', 'Nuovo evento in bozza')]),
          h('button', { type: 'button', disabled: eventFormSubmitting.value, onClick: () => { void go('/organizer/events'); } }, 'Torna ai miei eventi'),
        ]),
        eventFormSuccess.value ? h('p', { class: 'form-success', role: 'status' }, eventFormSuccess.value) : null,
        eventFormGeneralError.value ? h('p', { class: 'api-error', role: 'alert' }, eventFormGeneralError.value) : null,
        h('form', { class: 'wide-panel event-editor', onSubmit: (event: Event) => event.preventDefault() }, [
          h('div', { class: 'event-form-grid' }, [
            input('Titolo', 'title'),
            input('Descrizione breve', 'summary'),
            h('label', { class: 'event-form-field event-form-wide' }, [
              h('span', 'Descrizione'),
              h('textarea', { value: eventFormData.value.description, disabled: eventFormSubmitting.value, onInput: (event: Event) => { eventFormData.value.description = (event.target as HTMLTextAreaElement).value; } }),
              ...eventFieldErrors('description'),
            ]),
            h('label', { class: 'event-form-field' }, [
              h('span', 'Categoria'),
              h('select', { value: eventFormData.value.category_id, disabled: categoriesLoading.value || eventFormSubmitting.value, onChange: (event: Event) => { eventFormData.value.category_id = (event.target as HTMLSelectElement).value; } }, [
                h('option', { value: '' }, categoriesLoading.value ? 'Caricamento…' : 'Seleziona categoria'),
                ...eventCategories.value.map((category) => h('option', { value: String(category.id) }, category.name)),
              ]),
              ...eventFieldErrors('category_ids'),
              ...eventFieldErrors('category_ids.0'),
            ]),
            h('label', { class: 'event-form-field' }, [
              h('span', 'Luogo'),
              h('select', { value: eventFormData.value.municipality_id, disabled: eventFormSubmitting.value, onChange: (event: Event) => { eventFormData.value.municipality_id = (event.target as HTMLSelectElement).value; } }, [
                h('option', { value: '' }, 'Seleziona comune'),
                ...eventMunicipalities.value.map((municipality) => h('option', { value: String(municipality.id) }, `${municipality.name}${municipality.province?.code ? ` (${municipality.province.code})` : ''}`)),
              ]),
              ...eventFieldErrors('municipality_id'),
            ]),
            input('Data e ora di inizio', 'starts_at', 'datetime-local'),
            input('Data e ora di fine', 'ends_at', 'datetime-local'),
            input('Capienza generale', 'capacity_total', 'number'),
            h('label', { class: 'event-form-field' }, [
              h('span', 'Svolgimento'),
              h('select', { value: eventFormData.value.indoor_outdoor, disabled: eventFormSubmitting.value, onChange: (event: Event) => { eventFormData.value.indoor_outdoor = (event.target as HTMLSelectElement).value as EventFormData['indoor_outdoor']; } }, [
                h('option', { value: 'indoor' }, 'Al chiuso'),
                h('option', { value: 'outdoor' }, 'All’aperto'),
                h('option', { value: 'mixed' }, 'Misto'),
              ]),
            ]),
            h('label', { class: 'event-form-check' }, [h('input', { type: 'checkbox', checked: eventFormData.value.is_free, disabled: eventFormSubmitting.value, onChange: (event: Event) => { eventFormData.value.is_free = (event.target as HTMLInputElement).checked; } }), 'Evento gratuito']),
            h('label', { class: 'event-form-check' }, [h('input', { type: 'checkbox', checked: eventFormData.value.family_friendly, disabled: eventFormSubmitting.value, onChange: (event: Event) => { eventFormData.value.family_friendly = (event.target as HTMLInputElement).checked; } }), 'Adatto alle famiglie']),
          ]),
          h('div', { class: 'ticket-section-heading' }, [
            h('div', [h('h3', 'Biglietti'), h('p', 'Le quantità prenotate sono protette dal backend.')]),
            h('button', { type: 'button', disabled: eventFormSubmitting.value, onClick: () => { tickets.value.push(emptyEventTicket()); } }, 'Aggiungi biglietto'),
          ]),
          ...eventFieldErrors('ticket_types'),
          h('div', { class: 'ticket-editor-list' }, ticketCards),
          h('div', { class: 'event-form-actions' }, [
            h('button', { type: 'button', disabled: eventFormSubmitting.value, onClick: () => { void go('/organizer/events'); } }, 'Annulla'),
            h('button', { type: 'button', disabled: eventFormSubmitting.value, onClick: () => { void submitEventForm(false); } }, eventFormSubmitting.value ? 'Salvataggio…' : 'Salva bozza'),
            eventFormData.value.status === 'draft' ? h('button', { class: 'publish-button', type: 'button', disabled: eventFormSubmitting.value, onClick: () => { void submitEventForm(true); } }, eventFormSubmitting.value ? 'Pubblicazione…' : 'Pubblica evento') : null,
          ]),
        ]),
      ];
    };

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
          : route.value === '/organizer/create-event' || (route.value.startsWith('/organizer/events/') && route.value.endsWith('/edit'))
            ? renderEventForm()
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
      if (role.value === 'organizer' && route.value === '/organizer/pending') return renderOrganizerPending();
      if (role.value === 'organizer') return OrganizerLayout();
      if (role.value === 'admin') return AdminLayout();

      return renderLogin();
    };
  },
});
