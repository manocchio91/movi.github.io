import { cloneVNode, computed, defineComponent, h, onMounted, ref } from 'vue';
import { buildBookingUpdatePayload, runBookingUpdate } from './bookingUpdate';

type Role = 'guest' | 'customer' | 'organizer' | 'admin';
type AuthMode = 'login' | 'register-customer' | 'register-organizer';
type AuthAudience = Exclude<Role, 'guest'>;
type AppRoute =
  | '/login'
  | '/customer/dashboard'
  | '/customer/explore'
  | `/customer/events/${string}`
  | `/customer/organizers/${string}`
  | '/customer/successful-events'
  | '/customer/bookings'
  | '/customer/preferences'
  | '/customer/profile'
  | '/customer/following'
  | '/customer/activities'
  | `/customer/activities/${string}`
  | '/customer/reservations'
  | '/customer/plan'
  | '/organizer/dashboard'
  | '/organizer/onboarding'
  | '/organizer/pending'
  | '/organizer/rejected'
  | '/organizer/suspended'
  | '/organizer/events'
  | '/organizer/bookings'
  | '/organizer/profile'
  | `/organizer/bookings/${string}`
  | '/organizer/create-event'
  | `/organizer/events/${string}/edit`
  | `/organizer/events/${string}/participants`
  | '/organizer/reviews'
  | '/organizer/opportunities'
  | '/organizer/activities'
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
  verification_status: OrganizerVerificationStatus;
  review_notes: string | null;
};

type OrganizerVerificationStatus = 'incomplete' | 'pending' | 'changes_requested' | 'verified' | 'rejected' | 'suspended';
type OrganizerProfile = OrganizerProfileSummary & {
  type: string;
  description: string | null;
  municipality_id: number | null;
  municipality?: EventMunicipality | null;
  address: string | null;
  street_number: string | null;
  postal_code: string | null;
  province: string | null;
  country: string;
  vat_number: string | null;
  pec: string | null;
  sdi_code: string | null;
  phone: string | null;
  public_email: string | null;
  website_url: string | null;
  instagram_url: string | null;
  facebook_url: string | null;
  tiktok_url: string | null;
  youtube_url: string | null;
  logo_url?: string | null;
  cover_url?: string | null;
  submitted_at?: string | null;
  reviewed_at?: string | null;
  owner?: { name: string; email: string };
  subscription?: {
    status: string;
    agreement_starts_at?: string | null;
    agreement_ends_at?: string | null;
    agreement_notes?: string | null;
    booking_fee_cents?: number | null;
    plan?: { ulid: string; name: string; billing_model?: string };
    history?: Array<{ ulid: string; created_at: string; new_values: Record<string, unknown> }>;
  } | null;
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
  impressions: number; detail_views: number; shares: number; cancellations: number;
  waitlist_joined: number; waitlist_converted: number; check_in_people: number; no_show_people: number;
  followers_acquired: number; booking_conversion_rate: number; attendance_rate: number;
  cancellation_rate: number; no_show_rate: number; review_rate: number; follow_conversion_rate: number;
};

type OrganizerDashboardData = {
  bi: OrganizerDashboardBi;
  category_performance: Array<{ name: string; bookings_count: number; fill_rate: number }>;
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

type OrganizerBookingFilter = 'all' | 'confirmed' | 'checked_in' | 'completed' | 'cancelled';
type OrganizerBooking = {
  ulid: string; booking_code: string; customer: { name: string; email: string; phone: string | null };
  event: { ulid: string; title: string }; people: number; status: string; booked_at: string;
  checked_in_at: string | null; tickets?: Array<{ name: string; quantity: number }>;
  qr_token?: string | null; notes?: string | null;
};
type OrganizerBookingListResponse = { data: OrganizerBooking[]; meta: OrganizerEventPaginationMeta };
type OrganizerParticipant = { booking_ulid: string; booking_code: string; name: string; email: string; phone: string; tickets: string; people: number; status: string; checked_in: boolean; checked_in_at: string | null };
type OrganizerParticipantsResponse = { data: OrganizerParticipant[]; summary: { bookings_total: number; people_total: number; checked_in_total: number }; meta: OrganizerEventPaginationMeta };

type CustomerTicket = { ulid: string; name: string; audience: 'generic' | 'adult' | 'child'; price_cents: number; max_quantity: number | null; booked_quantity: number; is_active: boolean };
type CustomerEvent = {
  ulid: string; title: string; summary: string; description: string; status: 'published' | 'sold_out'; starts_at: string;
  capacity_total: number; available_total: number; is_free: boolean; municipality?: { name: string }; categories?: EventCategory[];
  category?: string; canonical_url: string; organizer_profile?: { ulid: string; name: string; logo_url?: string | null; cover_url?: string | null }; ticket_types: CustomerTicket[];
};
type CustomerBookingResult = { ulid: string; booking_code: string; total_people: number; total_price_cents: number; qr_token: string };
type CustomerBooking = {
  ulid: string; booking_code: string; contact_phone: string | null; total_people: number; total_price_cents: number; status: string; can_cancel: boolean; can_edit: boolean; notes: string | null;
  event: { ulid: string; title: string; starts_at: string };
  items: Array<{ quantity: number; ticket_type: CustomerTicket }>;
};
type BookingDraft = { booking: CustomerBooking; event: CustomerEvent; quantities: Record<string, number>; phone: string; notes: string; savePhone: boolean; confirmOpen: boolean; error: string };

type EventFormMode = 'create' | 'edit';

type EventCategory = { id: number; name: string };
type EventMunicipality = { id: number; name: string; province?: { code?: string } };
type PostalMunicipality = { id: number; name: string; province: string; postal_code: string };
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
  summary_metrics: OrganizerEventSummary;
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

const userMessage = (error: unknown, fallback: string): string => {
  if (!(error instanceof Error)) return fallback;
  if (/failed to fetch|networkerror|load failed/i.test(error.message)) return 'Servizio temporaneamente non raggiungibile. Verifica la connessione e riprova.';
  return error.message || fallback;
};

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
const analyticsStorageKey = 'movi.analytics-session';
const analyticsSession = localStorage.getItem(analyticsStorageKey) ?? crypto.randomUUID();
localStorage.setItem(analyticsStorageKey, analyticsSession);

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

const emptyOrganizerProfile = (): OrganizerProfile => ({
  ulid: '', name: '', type: '', description: '', municipality_id: null, address: '', street_number: '',
  postal_code: '', province: '', country: 'Italia', vat_number: '', pec: '', sdi_code: '', phone: '',
  public_email: '', website_url: '', instagram_url: '', facebook_url: '', tiktok_url: '', youtube_url: '',
  verification_status: 'incomplete', review_notes: null,
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

const customerMenu = [
  ['Home', '/customer/dashboard'],
  ['Tutti gli Eventi', '/customer/explore'],
  ['Eventi di Successo', '/customer/successful-events'],
  ['Le mie prenotazioni', '/customer/bookings'],
  ['Organizer seguiti', '/customer/following'],
  ['Attività', '/customer/activities'],
  ['Prenotazioni locali', '/customer/reservations'],
  ['Organizza con MòVi', '/customer/plan'],
  ['Preferenze', '/customer/preferences'],
  ['Profilo', '/customer/profile'],
] as const;

const organizerMenu = [
  ['Dashboard', '/organizer/dashboard'],
  ['I miei eventi', '/organizer/events'],
  ['Prenotazioni', '/organizer/bookings'],
  ['MòVi Manager', '/organizer/activities'],
  ['Profilo', '/organizer/profile'],
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

export default defineComponent({
  name: 'App',
  setup() {
    const currentUser = ref<AuthUser | null>(null);
    const token = ref<string | null>(null);
    const route = ref<AppRoute>('/login');
    const authAudience = ref<AuthAudience | null>(null);
    const authMode = ref<AuthMode>('login');
    const authLoading = ref(false);
    const authRestoring = ref(true);
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
    const organizerProfile = ref<OrganizerProfile>(emptyOrganizerProfile());
    const organizerProfileLoading = ref(false);
    const organizerProfileSaving = ref(false);
    const organizerProfileError = ref('');
    const organizerProfileSuccess = ref('');
    const organizerProfileErrors = ref<Record<string, string[]>>({});
    const postalMunicipalities = ref<PostalMunicipality[]>([]);
    const postalCodeLoading = ref(false);
    const postalCodeError = ref('');
    const loadedProfilePostalCode = ref('');
    const lastPostalCodeLookup = ref('');
    let postalCodeRequestId = 0;
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
    const organizerBookings = ref<OrganizerBooking[]>([]);
    const organizerBookingsMeta = ref<OrganizerEventPaginationMeta | null>(null);
    const organizerBookingsLoading = ref(false);
    const organizerBookingsError = ref('');
    const organizerBookingsPage = ref(1);
    const organizerBookingsFilter = ref<OrganizerBookingFilter>('confirmed');
    const organizerBookingsSearch = ref('');
    const organizerBookingsSort = ref('booked_at:desc');
    const selectedOrganizerBooking = ref<OrganizerBooking | null>(null);
    const organizerQrVisible = ref(false);
    const organizerParticipants = ref<OrganizerParticipant[]>([]);
    const organizerParticipantsMeta = ref<OrganizerEventPaginationMeta | null>(null);
    const organizerParticipantsSummary = ref({ bookings_total: 0, people_total: 0, checked_in_total: 0 });
    const organizerParticipantsLoading = ref(false);
    const organizerParticipantsError = ref('');
    const organizerParticipantsSearch = ref('');
    const organizerParticipantsFilter = ref('all');
    const organizerParticipantsPage = ref(1);
    const checkInValue = ref('');
    const checkInMode = ref<'qr_token' | 'booking_code'>('qr_token');
    const checkInMessage = ref('');
    const checkInLoading = ref(false);
    const eventFormMode = ref<EventFormMode>('create');
    const eventFormData = ref<EventFormData>(emptyEventForm());
    const eventBookedPeople = ref(0);
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
    let sessionVersion = 0;
    const reviewEvents = ref<any[]>([]);
    const calendarDays = ref<any[]>([]);
    const eventCompetitionDays = ref<any[]>([]);
    const eventCompetitionLoading = ref(false);
    const selectedDay = ref<any | null>(null);
    const opportunities = ref<any>(null);
    const customerDashboard = ref<any>(null);
    const allCustomerEvents = ref<any[]>([]);
    const discoveryResults = ref<any[]>([]);
    const recommendationResults = ref<any[]>([]);
    const successfulEvents = ref<any[]>([]);
    const selectedCustomerEvent = ref<CustomerEvent | null>(null);
    const customerEventLoading = ref(false);
    const customerEventError = ref('');
    const customerTicketQuantities = ref<Record<string, number>>({});
    const customerBookingLoading = ref(false);
    const customerBookingError = ref('');
    const customerBookingFieldErrors = ref<Record<string, string[]>>({});
    const customerBookingPhone = ref('');
    const customerBookingNotes = ref('');
    const customerBookingSavePhone = ref(false);
    const customerBookingConfirmOpen = ref(false);
    const customerBookingResult = ref<CustomerBookingResult | null>(null);
    const customerWaitlist = ref<any | null>(null);
    const customerWaitlistLoading = ref(false);
    const customerShareMessage = ref('');
    const customerBookingEdit = ref<BookingDraft | null>(null);
    const customerProfile = ref({ name: '', email: '', phone: null as string | null, created_at: '' });
    const customerPassword = ref({ current_password: '', password: '', password_confirmation: '' });
    const customerBookings = ref<CustomerBooking[]>([]);
    const cancellingCustomerBooking = ref<string | null>(null);
    const customerFavorites = ref<CustomerEvent[]>([]);
    const customerQrTokens = ref<Record<string, string>>({});
    const customerSectionLoading = ref(false);
    const customerSectionMessage = ref('');
    const publicOrganizer = ref<any>(null);
    const customerFollowing = ref<any[]>([]);
    const followActionLoading = ref(false);
    const customerActivities = ref<any[]>([]);
    const selectedActivity = ref<any | null>(null);
    const activityAvailability = ref<any[]>([]);
    const customerReservations = ref<any[]>([]);
    const moviPlans = ref<any[]>([]);
    const moviAiResponse = ref<any>(null);
    const moviAiMessage = ref('');
    const moviAiError = ref('');
    const planLoading = ref(false);
    const activityDate = ref(new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10));
    const activityServiceUlid = ref('');
    const activityPartySize = ref(2);
    const activityMessage = ref('');
    const organizerActivities = ref<any[]>([]);
    const organizerActivityReservations = ref<Record<string, any[]>>({});
    const organizerReservationDashboard = ref<any>(null);
    const organizerReservationCustomers = ref<any[]>([]);
    const organizerReservationAnalytics = ref<any>(null);
    const adminDashboard = ref<any>(null);
    const adminOverview = ref<any>(null);
    const adminEvents = ref<any[]>([]);
    const adminEventStats = ref<any>(null);
    const adminOrganizers = ref<any[]>([]);
    const adminOrganizerFilter = ref<OrganizerVerificationStatus | ''>('pending');
    const adminOrganizerSearch = ref('');
    const adminOrganizerPage = ref(1);
    const adminOrganizerMeta = ref<OrganizerEventPaginationMeta | null>(null);
    const adminOrganizerPendingCount = ref(0);
    const adminOrganizerLoading = ref(false);
    const adminOrganizerError = ref('');
    const selectedAdminOrganizer = ref<OrganizerProfile | null>(null);
    const adminOrganizerActionLoading = ref(false);
    const adminEventRankings = ref<any[]>([]);
    const adminOrganizerRankings = ref<any[]>([]);
    const adminPlans = ref<any[]>([]);
    const adminSearchQuery = ref('');
    const adminSearchResults = ref<any>(null);


    const role = computed<Role>(() => currentUser.value?.role ?? 'guest');

    const api = async <T = any>(path: string, options: RequestInit = {}): Promise<T | null> => {
      if (!token.value) return null;
      const requestToken = token.value;
      const requestSessionVersion = sessionVersion;

      const response = await fetch(`${apiBase}${path}`, {
        ...options,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Analytics-Session': analyticsSession,
          Authorization: `Bearer ${requestToken}`,
          ...(options.headers ?? {}),
        },
      });

      if (requestSessionVersion !== sessionVersion || token.value !== requestToken) return null;

      if (!response.ok) {
        const errorPayload = await response.json().catch(() => null) as { message?: string; errors?: Record<string, string[]> } | null;
        throw new ApiError(response.status, errorPayload?.message ?? `API ${path}: ${response.status}`, errorPayload?.errors ?? {});
      }

      return response.status === 204 ? null : response.json() as Promise<T>;
    };

    const clearOrganizerData = () => {
      organizerDashboard.value = null;
      dashboardLoading.value = false;
      organizerProfile.value = emptyOrganizerProfile();
      organizerProfileLoading.value = false;
      organizerProfileSaving.value = false;
      organizerProfileError.value = '';
      organizerProfileSuccess.value = '';
      organizerProfileErrors.value = {};
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
      eventFormLoading.value = false;
      eventFormSubmitting.value = false;
      categoriesLoading.value = false;
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

        dashboardError.value = userMessage(error, 'Impossibile caricare la dashboard Organizer.');
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

        organizerEventsError.value = userMessage(error, 'Impossibile caricare la lista eventi Organizer.');
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
      eventBookedPeople.value = event.summary_metrics.booked_people;
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
        eventBookedPeople.value = 0;
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

    const loadEventCompetition = async () => {
      const municipalityId = Number(eventFormData.value.municipality_id);
      const categoryId = Number(eventFormData.value.category_id);
      if (!municipalityId || !categoryId) { eventCompetitionDays.value = []; return; }
      const date = eventFormData.value.starts_at ? new Date(eventFormData.value.starts_at) : new Date();
      const query = new URLSearchParams({ month: String(date.getMonth() + 1), year: String(date.getFullYear()), municipality_id: String(municipalityId), category_id: String(categoryId) });
      if (eventFormData.value.ulid) query.set('exclude_event_ulid', eventFormData.value.ulid);
      eventCompetitionLoading.value = true;
      try {
        const response = await api<{ data: any[] }>(`/organizer/calendar/availability?${query}`);
        eventCompetitionDays.value = response?.data ?? [];
      } catch {
        eventCompetitionDays.value = [];
      } finally {
        eventCompetitionLoading.value = false;
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
      const capacity = Number(eventFormData.value.capacity_total);
      if (!Number.isInteger(capacity) || capacity < 1) {
        errors.capacity_total = ['Inserisci una capienza valida.'];
      } else if (capacity < eventBookedPeople.value) {
        errors.capacity_total = [`La capienza non può essere inferiore ai ${eventBookedPeople.value} posti già prenotati.`];
      }      eventFormErrors.value = errors;
      if (Object.keys(errors).length > 0) return null;

      return {
        title: eventFormData.value.title,
        summary: eventFormData.value.summary,
        description: eventFormData.value.description,
        municipality_id: Number(eventFormData.value.municipality_id),
        category_ids: [Number(eventFormData.value.category_id)],
        starts_at: localInputToApiDate(eventFormData.value.starts_at),
        ends_at: localInputToApiDate(eventFormData.value.ends_at),
        capacity_total: capacity,
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
        apiError.value = userMessage(error, 'API non disponibile');
      } finally {
        loading.value = false;
      }
    };

    const loadCustomerData = async () => {
      if (role.value !== 'customer' || !token.value) return;

      loading.value = true;
      apiError.value = '';
      try {
        const [dashboard, eventPayload, successPayload, discoveryPayload, recommendationPayload] = await Promise.all([
          api('/customer/dashboard'),
          api('/events'),
          api('/customer/successful-events'),
          api('/discovery?when=today&available=1'),
          api('/recommendations?available=1'),
        ]);
        customerDashboard.value = dashboard?.data ?? null;
        allCustomerEvents.value = eventPayload?.data?.data ?? eventPayload?.data ?? [];
        successfulEvents.value = successPayload?.data ?? [];
        discoveryResults.value = discoveryPayload?.data ?? [];
        recommendationResults.value = recommendationPayload?.data ?? [];
      } catch (error) {
        apiError.value = userMessage(error, 'API customer non disponibile');
      } finally {
        loading.value = false;
      }
    };

    const loadCustomerEvent = async (ulid: string) => {
      customerEventLoading.value = true;
      customerEventError.value = '';
      customerBookingError.value = '';
      customerBookingFieldErrors.value = {};
      customerBookingResult.value = null;
      customerBookingNotes.value = '';
      customerBookingSavePhone.value = false;
      customerBookingConfirmOpen.value = false;
      selectedCustomerEvent.value = null;
      try {
        const [eventResponse, profileResponse, waitlistResponse] = await Promise.all([
          api<{ data: CustomerEvent }>(`/events/${ulid}`),
          api<{ data: typeof customerProfile.value }>('/me/profile'),
          api<any>(`/events/${ulid}/waitlist`),
        ]);
        selectedCustomerEvent.value = eventResponse?.data ?? null;
        const canonical = document.querySelector<HTMLLinkElement>('#canonical-url');
        if (canonical && eventResponse?.data.canonical_url) canonical.href = eventResponse.data.canonical_url;
        if (eventResponse?.data.title) document.title = `${eventResponse.data.title} — MòVi`;
        if (profileResponse?.data) customerProfile.value = profileResponse.data;
        customerBookingPhone.value = profileResponse?.data.phone ?? '';
        customerTicketQuantities.value = Object.fromEntries((eventResponse?.data.ticket_types ?? []).filter((ticket) => ticket.is_active).map((ticket) => [ticket.ulid, 0]));
        customerWaitlist.value = waitlistResponse?.data ?? null;
      } catch (error) {
        customerEventError.value = userMessage(error, 'Impossibile caricare il dettaglio evento.');
      } finally {
        customerEventLoading.value = false;
      }
    };

    const loadCustomerSection = async (section: 'profile' | 'bookings' | 'favorites' | 'following') => {
      customerSectionLoading.value = true; customerSectionMessage.value = '';
      try {
        if (section === 'profile') customerProfile.value = (await api<any>('/me/profile'))?.data ?? customerProfile.value;
        if (section === 'bookings') customerBookings.value = (await api<any>('/me/bookings'))?.data ?? [];
        if (section === 'favorites') customerFavorites.value = (await api<any>('/me/favorites'))?.data ?? [];
        if (section === 'following') customerFollowing.value = (await api<any>('/customer/following'))?.data ?? [];
      } catch (error) { customerSectionMessage.value = userMessage(error, 'Dati non disponibili.'); }
      finally { customerSectionLoading.value = false; }
    };

    const saveCustomerProfile = async () => {
      customerSectionLoading.value = true; customerSectionMessage.value = '';
      try { customerProfile.value = (await api<any>('/me/profile', { method: 'PATCH', body: JSON.stringify({ name: customerProfile.value.name, email: customerProfile.value.email, phone: customerProfile.value.phone }) }))?.data ?? customerProfile.value; customerSectionMessage.value = 'Profilo aggiornato.'; }
      catch (error) { customerSectionMessage.value = userMessage(error, 'Salvataggio non riuscito.'); }
      finally { customerSectionLoading.value = false; }
    };
    const saveCustomerPassword = async () => {
      customerSectionLoading.value = true; customerSectionMessage.value = '';
      try {
        await api('/me/password', { method: 'PUT', body: JSON.stringify(customerPassword.value) });
        customerPassword.value = { current_password: '', password: '', password_confirmation: '' };
        customerSectionMessage.value = 'Password aggiornata.';
      } catch (error) {
        customerSectionMessage.value = userMessage(error, 'Aggiornamento password non riuscito.');
      } finally {
        customerSectionLoading.value = false;
      }
    };

    const loadPublicOrganizer = async (ulid: string) => {
      customerSectionLoading.value = true; customerSectionMessage.value = ''; publicOrganizer.value = null;
      try { publicOrganizer.value = await api(`/organizers/${ulid}`); } catch (error) { customerSectionMessage.value = userMessage(error, 'Organizer non disponibile.'); }
      finally { customerSectionLoading.value = false; }
    };

    const joinCustomerWaitlist = async () => {
      const event = selectedCustomerEvent.value; const ticket = event?.ticket_types.find((item) => item.is_active);
      if (!event || !ticket || customerWaitlistLoading.value) return;
      customerWaitlistLoading.value = true; customerBookingError.value = '';
      try { customerWaitlist.value = (await api<any>(`/events/${event.ulid}/waitlist`, { method: 'POST', body: JSON.stringify({ adults_count: 1, children_count: 0, items: [{ ticket_type_ulid: ticket.ulid, quantity: 1 }] }) }))?.data ?? null; }
      catch (error) { customerBookingError.value = userMessage(error, 'Ingresso in lista non riuscito.'); }
      finally { customerWaitlistLoading.value = false; }
    };
    const leaveCustomerWaitlist = async () => { const event = selectedCustomerEvent.value; if (!event) return; customerWaitlistLoading.value = true; try { customerWaitlist.value = (await api<any>(`/events/${event.ulid}/waitlist`, { method: 'DELETE' }))?.data ?? null; } finally { customerWaitlistLoading.value = false; } };
    const acceptCustomerWaitlist = async () => { const event = selectedCustomerEvent.value; if (!event || !customerBookingPhone.value) return; customerWaitlistLoading.value = true; try { customerBookingResult.value = (await api<any>(`/events/${event.ulid}/waitlist/accept`, { method: 'POST', body: JSON.stringify({ phone: customerBookingPhone.value }) }))?.data ?? null; customerWaitlist.value.status = 'accepted'; } catch (error) { customerBookingError.value = userMessage(error, 'Offerta non confermata.'); } finally { customerWaitlistLoading.value = false; } };
    const shareCustomerEvent = async (event: CustomerEvent) => {
      customerShareMessage.value = '';
      const shareData = { title: event.title, text: `Scopri ${event.title} su MòVi`, url: event.canonical_url };
      try {
        if (navigator.share) {
          await navigator.share(shareData);
          await api(`/events/${event.ulid}/share`, { method: 'POST' });
          customerShareMessage.value = 'Evento condiviso.';
          return;
        }
        await navigator.clipboard.writeText(event.canonical_url);
        await api(`/events/${event.ulid}/share`, { method: 'POST' });
        customerShareMessage.value = 'Link copiato negli appunti.';
      } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') return;
        customerShareMessage.value = 'Condivisione non disponibile. Copia il link manualmente.';
      }
    };
    const shareCustomerEventOnWhatsApp = (event: CustomerEvent) => {
      window.open(`https://wa.me/?text=${encodeURIComponent(`Scopri ${event.title} su MòVi ${event.canonical_url}`)}`, '_blank', 'noopener,noreferrer');
      void api(`/events/${event.ulid}/share`, { method: 'POST' });
    };

    const toggleOrganizerFollow = async () => {
      const organizer = publicOrganizer.value?.data;
      if (!organizer || followActionLoading.value) return;
      followActionLoading.value = true; customerSectionMessage.value = '';
      try {
        const wasFollowing = organizer.is_following === true;
        const response = await api<any>(`/customer/organizers/${organizer.ulid}/follow`, { method: wasFollowing ? 'DELETE' : 'POST' });
        organizer.is_following = response?.data?.is_following ?? !wasFollowing;
        organizer.followers_count = Math.max(0, Number(organizer.followers_count ?? 0) + (organizer.is_following ? 1 : -1));
      } catch (error) { customerSectionMessage.value = userMessage(error, 'Follow non aggiornato.'); }
      finally { followActionLoading.value = false; }
    };

    const removeFavorite = async (ulid: string) => { await api(`/me/favorites/${ulid}`, { method: 'DELETE' }); await loadCustomerSection('favorites'); };
    const reviewBooking = async (booking: any) => { const rating = Number(window.prompt('Valutazione da 1 a 5')); if (!Number.isInteger(rating) || rating < 1 || rating > 5) return; const comment = window.prompt('Recensione') ?? ''; try { await api(`/events/${booking.event.ulid}/reviews`, { method: 'POST', body: JSON.stringify({ rating, comment, would_recommend: rating >= 4 }) }); customerSectionMessage.value = 'Recensione pubblicata.'; } catch (error) { customerSectionMessage.value = userMessage(error, 'Recensione non pubblicata.'); } };

    const uploadOrganizerImage = async (kind: 'logo' | 'cover', file?: File) => {
      if (!file || !token.value) return; const form = new FormData(); form.append(kind, file); organizerProfileSaving.value = true;
      try { const response = await fetch(`${apiBase}/organizer/profile/images`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}` }, body: form }); if (!response.ok) throw new Error((await response.json()).message); organizerProfile.value = (await response.json()).data; organizerProfileSuccess.value = 'Immagine aggiornata.'; }
      catch (error) { organizerProfileError.value = userMessage(error, 'Caricamento non riuscito.'); } finally { organizerProfileSaving.value = false; }
    };

    const loadCustomerQrSafe = async (ulid: string) => {
      customerSectionMessage.value = '';
      try {
        customerQrTokens.value[ulid] = (await api<any>('/me/bookings/'+ulid+'/qr'))?.data?.qr_token ?? '';
      } catch {
        customerSectionMessage.value = 'QR non disponibile. Riprova più tardi.';
      }
    };
    const cancelCustomerBooking = async (booking: CustomerBooking) => {
      if (!booking.can_cancel || cancellingCustomerBooking.value || !window.confirm('Confermi l’annullamento della prenotazione?')) return;
      cancellingCustomerBooking.value = booking.ulid;
      customerSectionMessage.value = '';
      try {
        await api('/me/bookings/'+booking.ulid+'/cancel', { method: 'POST' });
        delete customerQrTokens.value[booking.ulid];
        await loadCustomerSection('bookings');
        customerSectionMessage.value = 'Prenotazione annullata.';
      } catch (error) {
        customerSectionMessage.value = userMessage(error, 'Annullamento non riuscito.');
      } finally {
        cancellingCustomerBooking.value = null;
      }
    };

    const formatEuro = (cents: number) => `€${(cents / 100).toFixed(2).replace('.', ',')}`;
    const ticketAvailable = (ticket: CustomerTicket) => ticket.max_quantity === null
      ? Number.POSITIVE_INFINITY
      : Math.max(0, ticket.max_quantity - ticket.booked_quantity);
    const purchasableTickets = (event: CustomerEvent, ticket: CustomerTicket) =>
      Math.min(event.available_total, ticketAvailable(ticket));
    const editableTickets = (draft: BookingDraft, ticket: CustomerTicket) => {
      const current = draft.booking.items.find((item) => item.ticket_type.ulid === ticket.ulid)?.quantity ?? 0;
      const eventLimit = draft.event.available_total + draft.booking.total_people;
      const ticketLimit = ticket.max_quantity === null ? eventLimit : ticketAvailable(ticket) + current;
      return Math.min(eventLimit, ticketLimit);
    };
    const selectedBookingItems = (event: CustomerEvent, quantities: Record<string, number>) => event.ticket_types
      .map((ticket) => ({ ticket, quantity: quantities[ticket.ulid] ?? 0, subtotal: ticket.price_cents * (quantities[ticket.ulid] ?? 0) }))
      .filter((item) => item.quantity > 0);

    const bookingCounts = (items: Array<{ ticket: CustomerTicket; quantity: number }>) => ({
      adults: items.filter(({ ticket }) => ticket.audience !== 'child').reduce((total, item) => total + item.quantity, 0),
      children: items.filter(({ ticket }) => ticket.audience === 'child').reduce((total, item) => total + item.quantity, 0),
    });

    const openCustomerBookingConfirmation = () => {
      const event = selectedCustomerEvent.value;
      if (!event) return;
      const items = selectedBookingItems(event, customerTicketQuantities.value);
      customerBookingError.value = '';
      if (!items.length) { customerBookingError.value = 'Seleziona almeno un biglietto.'; return; }
      if (!customerBookingPhone.value.trim()) { customerBookingFieldErrors.value = { phone: ['Il telefono è obbligatorio.'] }; customerBookingError.value = 'Correggi i campi evidenziati.'; return; }
      customerBookingConfirmOpen.value = true;
    };

    const confirmCustomerBooking = async () => {
      const event = selectedCustomerEvent.value;
      if (!event || customerBookingLoading.value) return;
      const items = selectedBookingItems(event, customerTicketQuantities.value);
      const counts = bookingCounts(items);
      customerBookingLoading.value = true; customerBookingError.value = ''; customerBookingFieldErrors.value = {};
      try {
        const response = await api<{ data: CustomerBookingResult }>(`/events/${event.ulid}/bookings`, {
          method: 'POST', headers: { 'Idempotency-Key': crypto.randomUUID() },
          body: JSON.stringify({ phone: customerBookingPhone.value, save_phone_to_profile: customerBookingSavePhone.value, notes: customerBookingNotes.value || null, adults_count: counts.adults, children_count: counts.children, items: items.map(({ ticket, quantity }) => ({ ticket_type_ulid: ticket.ulid, quantity })) }),
        });
        customerBookingResult.value = response?.data ?? null;
        customerBookingConfirmOpen.value = false;
        if (customerBookingResult.value) event.available_total = Math.max(0, event.available_total - customerBookingResult.value.total_people);
        if (customerBookingSavePhone.value) customerProfile.value.phone = customerBookingPhone.value;
      } catch (error) {
        customerBookingConfirmOpen.value = false;
        if (error instanceof ApiError && error.status === 422) { customerBookingFieldErrors.value = error.errors; customerBookingError.value = 'Correggi i campi evidenziati.'; }
        else customerBookingError.value = userMessage(error, 'Prenotazione non completata.');
      } finally { customerBookingLoading.value = false; }
    };

    const openCustomerBookingEdit = async (booking: CustomerBooking) => {
      customerSectionMessage.value = '';
      try {
        const response = await api<{ data: CustomerEvent }>(`/events/${booking.event.ulid}`);
        const event = response?.data;
        if (!event) throw new Error('Evento non disponibile.');
        customerBookingEdit.value = { booking, event, quantities: Object.fromEntries(event.ticket_types.map((ticket) => [ticket.ulid, booking.items.find((item) => item.ticket_type.ulid === ticket.ulid)?.quantity ?? 0])), phone: booking.contact_phone ?? customerProfile.value.phone ?? '', notes: booking.notes ?? '', savePhone: false, confirmOpen: false, error: '' };
      } catch (error) { customerSectionMessage.value = userMessage(error, 'Modifica non disponibile.'); }
    };

    const openCustomerBookingEditConfirmation = () => {
      const draft = customerBookingEdit.value;
      if (!draft || customerBookingLoading.value) return;
      const items = selectedBookingItems(draft.event, draft.quantities);
      draft.error = '';
      if (!items.length) { draft.error = 'Seleziona almeno un ticket.'; return; }
      if (!draft.phone.trim()) { draft.error = 'Il telefono è obbligatorio.'; return; }
      draft.confirmOpen = true;
    };

    const bookingUpdateErrorMessage = (error: unknown) => {
      if (!(error instanceof ApiError)) return 'Modifica non riuscita. Riprova.';
      if (error.status === 401) return 'Sessione scaduta. Accedi nuovamente.';
      if (error.status === 403) return 'Non sei autorizzato a modificare questa prenotazione.';
      if (error.status === 404) return 'Prenotazione non trovata.';
      if (error.status === 422) return error.message || 'Controlla quantità, telefono e disponibilità.';
      return 'Errore del server durante la modifica. Riprova.';
    };

    const confirmCustomerBookingEdit = async () => {
      const draft = customerBookingEdit.value;
      if (!draft) return;
      const items = selectedBookingItems(draft.event, draft.quantities);
      const counts = bookingCounts(items);
      const payload = buildBookingUpdatePayload({
        phone: draft.phone ?? customerProfile.value.phone ?? '',
        save_phone_to_profile: draft.savePhone,
        notes: draft.notes,
        adults_count: counts.adults,
        children_count: counts.children,
        items: items.map(({ ticket, quantity }) => ({ ticket_type_ulid: ticket.ulid, quantity })),
      });
      draft.error = '';
      await runBookingUpdate({
        isLoading: () => customerBookingLoading.value,
        setLoading: (loading) => { customerBookingLoading.value = loading; },
        request: () => api<{ data: CustomerBooking }>('/me/bookings/' + draft.booking.ulid, { method: 'PATCH', body: JSON.stringify(payload) }),
        onSuccess: async (response) => {
          if (!response?.data) throw new Error('Risposta di modifica non valida.');
          customerBookingEdit.value = null;
          await loadCustomerSection('bookings');
          customerSectionMessage.value = 'Prenotazione modificata.';
        },
        onError: (error) => {
          draft.error = bookingUpdateErrorMessage(error);
        },
      });
    };

    const loadOrganizerBookings = async () => {
      organizerBookingsLoading.value = true;
      organizerBookingsError.value = '';
      try {
        const [sort_by, sort] = organizerBookingsSort.value.split(':');
        const query = new URLSearchParams({ page: String(organizerBookingsPage.value), per_page: '15', filter: organizerBookingsFilter.value, sort_by, sort });
        if (organizerBookingsSearch.value.trim()) query.set('search', organizerBookingsSearch.value.trim());
        const response = await api<OrganizerBookingListResponse>(`/organizer/bookings?${query}`);
        organizerBookings.value = response?.data ?? [];
        organizerBookingsMeta.value = response?.meta ?? null;
      } catch (error) {
        organizerBookings.value = [];
        organizerBookingsError.value = userMessage(error, 'Impossibile caricare le prenotazioni.');
      } finally {
        organizerBookingsLoading.value = false;
      }
    };

    const loadOrganizerBookingDetail = async (ulid: string) => {
      organizerBookingsLoading.value = true;
      organizerBookingsError.value = '';
      selectedOrganizerBooking.value = null;
      try {
        const response = await api<{ data: OrganizerBooking }>(`/organizer/bookings/${ulid}`);
        selectedOrganizerBooking.value = response?.data ?? null;
      } catch (error) {
        organizerBookingsError.value = userMessage(error, 'Impossibile caricare il dettaglio.');
      } finally {
        organizerBookingsLoading.value = false;
      }
    };

    const loadOrganizerParticipants = async (eventUlid: string) => {
      organizerParticipantsLoading.value = true; organizerParticipantsError.value = ''; checkInMessage.value = '';
      try {
        const query = new URLSearchParams({ page: String(organizerParticipantsPage.value), per_page: '15', filter: organizerParticipantsFilter.value });
        if (organizerParticipantsSearch.value.trim()) query.set('search', organizerParticipantsSearch.value.trim());
        const response = await api<OrganizerParticipantsResponse>(`/organizer/events/${eventUlid}/participants?${query}`);
        organizerParticipants.value = response?.data ?? []; organizerParticipantsMeta.value = response?.meta ?? null;
        organizerParticipantsSummary.value = response?.summary ?? { bookings_total: 0, people_total: 0, checked_in_total: 0 };
      } catch (error) { organizerParticipants.value = []; organizerParticipantsError.value = userMessage(error, 'Impossibile caricare i partecipanti.'); }
      finally { organizerParticipantsLoading.value = false; }
    };

    const submitCheckIn = async (eventUlid: string) => {
      if (!checkInValue.value.trim()) return;
      checkInLoading.value = true; checkInMessage.value = '';
      try {
        await api(`/organizer/events/${eventUlid}/check-ins`, { method: 'POST', body: JSON.stringify({ [checkInMode.value]: checkInValue.value.trim() }) });
        checkInMessage.value = 'Check-in registrato correttamente.'; checkInValue.value = '';
        await loadOrganizerParticipants(eventUlid);
      } catch (error) { checkInMessage.value = userMessage(error, 'Check-in non riuscito.'); }
      finally { checkInLoading.value = false; }
    };

    const exportParticipantsCsv = async (eventUlid: string) => {
      if (!token.value) return;
      const query = new URLSearchParams({ format: 'csv', filter: organizerParticipantsFilter.value });
      if (organizerParticipantsSearch.value.trim()) query.set('search', organizerParticipantsSearch.value.trim());
      const response = await fetch(`${apiBase}/organizer/events/${eventUlid}/participants?${query}`, { headers: { Accept: 'text/csv', Authorization: `Bearer ${token.value}` } });
      if (!response.ok) { organizerParticipantsError.value = 'Esportazione CSV non riuscita.'; return; }
      const url = URL.createObjectURL(await response.blob()); const link = document.createElement('a'); link.href = url; link.download = `partecipanti-${eventUlid}.csv`; link.click(); URL.revokeObjectURL(url);
    };

    const loadOrganizerProfile = async () => {
      if (role.value !== 'organizer' || !token.value) return;
      organizerProfileLoading.value = true;
      organizerProfileError.value = '';
      try {
        const profilePayload = await api<{ data: OrganizerProfile }>('/organizer/profile');
        if (profilePayload?.data) {
          organizerProfile.value = profilePayload.data;
          loadedProfilePostalCode.value = profilePayload.data.postal_code ?? '';
          postalMunicipalities.value = profilePayload.data.municipality ? [{
            id: profilePayload.data.municipality.id,
            name: profilePayload.data.municipality.name,
            province: profilePayload.data.province ?? '',
            postal_code: profilePayload.data.postal_code ?? '',
          }] : [];
        }
      } catch (error) {
        organizerProfileError.value = userMessage(error, 'Impossibile caricare il profilo Organizer.');
      } finally {
        organizerProfileLoading.value = false;
      }
    };

    const lookupMunicipalitiesByPostalCode = async (postalCode: string) => {
      if (postalCode === lastPostalCodeLookup.value || !/^\d{5}$/.test(postalCode)) return;
      const requestId = ++postalCodeRequestId;
      lastPostalCodeLookup.value = postalCode;
      postalCodeLoading.value = true;
      postalCodeError.value = '';
      try {
        const response = await api<{ data: PostalMunicipality[] }>(`/locations/municipalities?postal_code=${encodeURIComponent(postalCode)}`);
        if (requestId !== postalCodeRequestId || organizerProfile.value.postal_code !== postalCode) return;
        postalMunicipalities.value = response?.data ?? [];
        if (postalMunicipalities.value.length === 0) {
          postalCodeError.value = 'Nessun Comune trovato per questo CAP';
          return;
        }
        if (postalMunicipalities.value.length === 1) {
          organizerProfile.value.municipality_id = postalMunicipalities.value[0].id;
          organizerProfile.value.province = postalMunicipalities.value[0].province;
        }
      } catch (error) {
        if (requestId === postalCodeRequestId) postalCodeError.value = userMessage(error, 'Impossibile cercare il Comune.');
      } finally {
        if (requestId === postalCodeRequestId) postalCodeLoading.value = false;
      }
    };

    const changeOrganizerPostalCode = (value: string) => {
      organizerProfile.value.postal_code = value.slice(0, 5);
      if (organizerProfile.value.postal_code === loadedProfilePostalCode.value) {
        const municipality = organizerProfile.value.municipality;
        if (municipality) {
          postalMunicipalities.value = [{ id: municipality.id, name: municipality.name, province: organizerProfile.value.province ?? '', postal_code: loadedProfilePostalCode.value }];
          organizerProfile.value.municipality_id = municipality.id;
        }
        return;
      }
      postalCodeRequestId++;
      postalCodeLoading.value = false;
      postalCodeError.value = '';
      postalMunicipalities.value = [];
      organizerProfile.value.municipality_id = null;
      organizerProfile.value.province = '';
      lastPostalCodeLookup.value = '';
      if (organizerProfile.value.postal_code.length === 5) {
        if (!/^\d{5}$/.test(organizerProfile.value.postal_code)) postalCodeError.value = 'Nessun Comune trovato per questo CAP';
        else void lookupMunicipalitiesByPostalCode(organizerProfile.value.postal_code);
      }
    };

    const selectPostalMunicipality = (id: number) => {
      const municipality = postalMunicipalities.value.find((item) => item.id === id);
      organizerProfile.value.municipality_id = municipality?.id ?? null;
      organizerProfile.value.province = municipality?.province ?? '';
    };

    const saveOrganizerProfile = async () => {
      organizerProfileSaving.value = true;
      organizerProfileError.value = '';
      organizerProfileSuccess.value = '';
      organizerProfileErrors.value = {};
      try {
        const payload = { ...organizerProfile.value, municipality_id: organizerProfile.value.municipality_id || null };
        const response = await api<{ data: OrganizerProfile }>('/organizer/profile', { method: 'PATCH', body: JSON.stringify(payload) });
        if (response?.data) organizerProfile.value = response.data;
        organizerProfileSuccess.value = 'Profilo salvato. Puoi continuare più tardi.';
      } catch (error) {
        if (error instanceof ApiError) organizerProfileErrors.value = error.errors;
        organizerProfileError.value = userMessage(error, 'Impossibile salvare il profilo.');
      } finally {
        organizerProfileSaving.value = false;
      }
    };

    const submitOrganizerProfile = async () => {
      await saveOrganizerProfile();
      if (organizerProfileError.value) return;
      organizerProfileSaving.value = true;
      organizerProfileSuccess.value = '';
      try {
        const response = await api<{ data: OrganizerProfile }>('/organizer/profile/submit', { method: 'POST' });
        if (!response?.data) return;
        organizerProfile.value = response.data;
        if (currentUser.value?.organizer_profile) currentUser.value.organizer_profile.verification_status = 'pending';
        route.value = '/organizer/pending';
      } catch (error) {
        if (error instanceof ApiError) organizerProfileErrors.value = error.errors;
        organizerProfileError.value = userMessage(error, 'Impossibile inviare la richiesta.');
      } finally {
        organizerProfileSaving.value = false;
      }
    };

    const loadAdminOrganizerRequests = async () => {
      if (role.value !== 'admin' || !token.value) return;
      adminOrganizerLoading.value = true;
      adminOrganizerError.value = '';
      try {
        const query = new URLSearchParams({ page: String(adminOrganizerPage.value), per_page: '15' });
        if (adminOrganizerFilter.value) query.set('status', adminOrganizerFilter.value);
        if (adminOrganizerSearch.value.trim()) query.set('q', adminOrganizerSearch.value.trim());
        const response = await api<{ data: { data: OrganizerProfile[] } & OrganizerEventPaginationMeta; pending_count: number }>(`/admin/organizers?${query}`);
        adminOrganizers.value = response?.data.data ?? [];
        adminOrganizerMeta.value = response?.data ?? null;
        adminOrganizerPendingCount.value = response?.pending_count ?? 0;
      } catch (error) {
        adminOrganizers.value = [];
        adminOrganizerError.value = userMessage(error, 'Impossibile caricare le richieste Organizer.');
      } finally {
        adminOrganizerLoading.value = false;
      }
    };

    const selectAdminOrganizer = async (organizer: OrganizerProfile) => {
      selectedAdminOrganizer.value = null;
      adminOrganizerError.value = '';
      try {
        const response = await api<{ data: OrganizerProfile }>(`/admin/organizers/${organizer.ulid}`);
        selectedAdminOrganizer.value = response?.data ?? null;
      } catch (error) {
        adminOrganizerError.value = userMessage(error, 'Impossibile caricare il dettaglio.');
      }
    };

    const reviewAdminOrganizer = async (decision: 'approve' | 'request-changes' | 'reject' | 'suspend' | 'reactivate') => {
      const organizer = selectedAdminOrganizer.value;
      if (!organizer || adminOrganizerActionLoading.value) return;
      let reason = '';
      if (decision === 'request-changes' || decision === 'reject' || decision === 'suspend') {
        reason = window.prompt(decision === 'reject' ? 'Motivazione del rifiuto' : decision === 'suspend' ? 'Motivazione della sospensione' : 'Integrazioni richieste')?.trim() ?? '';
        if (!reason) return;
      }
      const label = decision === 'approve' ? 'approvare' : decision === 'reject' ? 'rifiutare' : 'richiedere integrazioni per';
      if (!window.confirm(`Confermi di ${label} ${organizer.name}?`)) return;
      adminOrganizerActionLoading.value = true;
      adminOrganizerError.value = '';
      try {
        const response = await api<{ data: OrganizerProfile }>(`/admin/organizers/${organizer.ulid}/${decision}`, {
          method: 'POST', body: JSON.stringify(reason ? { reason } : {}),
        });
        selectedAdminOrganizer.value = response?.data ?? null;
        await loadAdminOrganizerRequests();
      } catch (error) {
        adminOrganizerError.value = userMessage(error, 'Operazione non completata.');
      } finally {
        adminOrganizerActionLoading.value = false;
      }
    };

    const loadAdminData = async () => {
      if (role.value !== 'admin' || !token.value) return;

      loading.value = true;
      apiError.value = '';
      try {
        const [dashboard, overview, events, eventRankings, organizerRankings] = await Promise.all([
          api('/admin/dashboard'),
          api('/admin/overview'),
          api('/admin/events'),
          api('/admin/rankings/events'),
          api('/admin/rankings/organizers'),
        ]);
        adminDashboard.value = dashboard?.data ?? null;
        adminOverview.value = overview?.data ?? null;
        const eventPayload = events;
        adminEvents.value = adminOverview.value?.events ?? eventPayload?.data?.data ?? eventPayload?.data ?? [];
        adminEventStats.value = eventPayload?.stats ?? null;
        adminEventRankings.value = eventRankings?.data ?? [];
        adminOrganizerRankings.value = organizerRankings?.data ?? [];
        await loadAdminOrganizerRequests();
      } catch (error) {
        apiError.value = userMessage(error, 'API admin non disponibile');
      } finally {
        loading.value = false;
      }
    };

    const loadCustomerActivities = async () => { customerActivities.value = (await api<any>('/activities'))?.data ?? []; };
    const loadActivityAvailability = async () => { if (!selectedActivity.value || !activityServiceUlid.value) return; activityAvailability.value = (await api<any>(`/activities/${selectedActivity.value.ulid}/services/${activityServiceUlid.value}/availability?date=${activityDate.value}`))?.data ?? []; };
    const loadCustomerActivity = async (ulid: string) => { const [activity, profile] = await Promise.all([api<any>(`/activities/${ulid}`), api<any>('/me/profile')]); selectedActivity.value = activity?.data ?? null; if (profile?.data) customerProfile.value = profile.data; activityServiceUlid.value = selectedActivity.value?.services?.[0]?.ulid ?? ''; await loadActivityAvailability(); };
    const createCustomerReservation = async (slot: any) => { if (!selectedActivity.value) return; activityMessage.value = ''; if (!customerProfile.value.phone) { activityMessage.value = 'Aggiungi un telefono reale nel profilo prima di prenotare.'; return; } try { const result = await api<any>(`/activities/${selectedActivity.value.ulid}/reservations`, { method: 'POST', headers: { 'Idempotency-Key': crypto.randomUUID() }, body: JSON.stringify({ service_ulid: activityServiceUlid.value, starts_at: slot.starts_at, party_size: activityPartySize.value, contact_phone: customerProfile.value.phone }) }); activityMessage.value = `Prenotazione confermata: ${result?.data?.ulid ?? ''}`; await loadActivityAvailability(); } catch (error) { activityMessage.value = userMessage(error, 'Prenotazione non riuscita.'); } };
    const toggleActivityFollow = async () => { if (!selectedActivity.value) return; const following = selectedActivity.value.is_following === true; const response = await api<any>(`/customer/activities/${selectedActivity.value.ulid}/follow`, { method: following ? 'DELETE' : 'POST' }); selectedActivity.value.is_following = response?.data?.is_following ?? !following; };
    const loadCustomerReservations = async () => { customerReservations.value = (await api<any>('/me/reservations'))?.data ?? []; };
    const cancelCustomerReservation = async (reservation: any) => { if (!window.confirm('Annullare questa prenotazione?')) return; await api(`/me/reservations/${reservation.ulid}/cancel`, { method: 'POST', body: JSON.stringify({ reason: 'Annullata dal Customer' }) }); await loadCustomerReservations(); };
    const generateMoviPlan = async (event: Event) => { event.preventDefault(); if (!moviAiMessage.value.trim()) return; planLoading.value = true; moviAiError.value = ''; try { const result = await api<any>('/ai/plan', { method: 'POST', body: JSON.stringify({ message: moviAiMessage.value.trim(), previous_intent: moviAiResponse.value?.parsed_intent ?? undefined }) }); moviAiResponse.value = result?.data ?? null; moviPlans.value = moviAiResponse.value?.plans ?? []; } catch (error) { moviAiError.value = userMessage(error, 'MòVi AI non è disponibile. Riprova.'); } finally { planLoading.value = false; } };
    const trackPlanInteraction = async (action: 'plan_opened' | 'plan_step_opened' | 'ai_plan_opened', plan: any, step?: any) => { await api('/plans/interaction', { method: 'POST', body: JSON.stringify({ action, plan_id: plan.plan_id, entity_type: step?.entity_type, entity_id: step?.entity_id }) }); };
    const loadOrganizerActivities = async () => { const [activities, municipalities, reservations, dashboard, customers, analytics] = await Promise.all([api<any>('/organizer/activities'), api<any>('/locations/municipalities'), api<any>('/organizer/reservations'), api<any>('/organizer/reservations/dashboard'), api<any>('/organizer/reservations/customers'), api<any>('/organizer/reservations/analytics')]); organizerActivities.value = activities?.data ?? []; eventMunicipalities.value = municipalities?.data ?? []; const rows = reservations?.data ?? []; organizerActivityReservations.value = Object.fromEntries(organizerActivities.value.map((activity: any) => [activity.ulid, rows.filter((row: any) => row.activity?.ulid === activity.ulid)])); organizerReservationDashboard.value = dashboard?.data ?? null; organizerReservationCustomers.value = customers?.data ?? []; organizerReservationAnalytics.value = analytics?.data ?? null; };
    const transitionOrganizerReservation = async (reservation: any, status: 'completed' | 'no_show') => { await api(`/organizer/reservations/${reservation.ulid}/status`, { method: 'PATCH', body: JSON.stringify({ status }) }); await loadOrganizerActivities(); };
    const createOrganizerActivity = async (event: Event) => { event.preventDefault(); const form = new FormData(event.target as HTMLFormElement); await api('/organizer/activities', { method: 'POST', body: JSON.stringify({ name: form.get('name'), type: 'restaurant', description: form.get('description'), municipality_id: Number(form.get('municipality_id')), address: form.get('address'), booking_enabled: true, status: 'published' }) }); (event.target as HTMLFormElement).reset(); await loadOrganizerActivities(); };
    const createActivityService = async (event: Event, activity: any) => { event.preventDefault(); const form = new FormData(event.target as HTMLFormElement); await api(`/organizer/activities/${activity.ulid}/services`, { method: 'POST', body: JSON.stringify({ name: form.get('type') === 'lunch' ? 'Pranzo' : 'Cena', type: form.get('type'), active: true, booking_enabled: true, min_party_size: Number(form.get('min_party_size')), max_party_size: Number(form.get('max_party_size')), minimum_advance_minutes: Number(form.get('minimum_advance_minutes')), indicative_duration_minutes: 90 }) }); await loadOrganizerActivities(); };
    const configureActivitySlot = async (event: Event, activity: any, service: any) => { event.preventDefault(); const form = new FormData(event.target as HTMLFormElement); await api(`/organizer/activities/${activity.ulid}/services/${service.ulid}/availability`, { method: 'PUT', body: JSON.stringify({ rules: [{ weekday: Number(form.get('weekday')), start_time: form.get('start_time'), capacity: Number(form.get('capacity')) }] }) }); await loadOrganizerActivities(); };
    const configureActivityClosure = async (event: Event, activity: any, service: any) => { event.preventDefault(); const form = new FormData(event.target as HTMLFormElement); await api(`/organizer/activities/${activity.ulid}/services/${service.ulid}/availability`, { method: 'PUT', body: JSON.stringify({ exceptions: [{ date: form.get('date'), is_closed: true, note: form.get('note') }] }) }); await loadOrganizerActivities(); };

    const go = async (nextRoute: AppRoute) => {
      route.value = canAccess(role.value, nextRoute) ? nextRoute : '/unauthorized';
      if (route.value === '/organizer/onboarding') await loadOrganizerProfile();
      else if (route.value === '/organizer/profile') await loadOrganizerProfile();
      else if (route.value === '/organizer/dashboard') await loadOrganizerDashboard();
      else if (route.value === '/organizer/bookings') await loadOrganizerBookings();
      else if (route.value.startsWith('/organizer/bookings/')) await loadOrganizerBookingDetail(route.value.split('/')[3]);
      else if (route.value === '/organizer/events') await loadOrganizerEvents();
      else if (route.value === '/organizer/activities') await loadOrganizerActivities();
      else if (route.value.startsWith('/organizer/events/') && route.value.endsWith('/participants')) await loadOrganizerParticipants(route.value.split('/')[3]);
      else if (route.value === '/organizer/create-event') await loadEventForm();
      else if (route.value.startsWith('/organizer/events/') && route.value.endsWith('/edit')) {
        await loadEventForm(route.value.split('/')[3]);
      }
      else if (route.value.startsWith('/organizer')) await loadOrganizerData();
      if (route.value.startsWith('/customer/events/')) await loadCustomerEvent(route.value.split('/')[3]);
      if (route.value === '/customer/activities') await loadCustomerActivities();
      if (route.value.startsWith('/customer/activities/')) await loadCustomerActivity(route.value.split('/')[3]);
      if (route.value === '/customer/reservations') await loadCustomerReservations();
      if (route.value.startsWith('/customer/organizers/')) await loadPublicOrganizer(route.value.split('/')[3]);
      if (route.value === '/customer/profile') await loadCustomerSection('profile');
      if (route.value === '/customer/bookings') await loadCustomerSection('bookings');
      if (route.value === '/customer/preferences') await loadCustomerSection('favorites');
      if (route.value === '/customer/following') await loadCustomerSection('following');
      if (route.value.startsWith('/customer')) await loadCustomerData();
      if (route.value.startsWith('/admin')) { await loadAdminData(); if (route.value === '/admin/subscriptions') await loadAdminPlans(); }
    };

    const clearSession = (message = '') => {
      sessionVersion += 1;
      currentUser.value = null;
      token.value = null;
      route.value = '/login';
      authAudience.value = null;
      loginForm.value = { email: '', password: '' };
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
      selectedAdminOrganizer.value = null;
      adminEventRankings.value = [];
      adminOrganizerRankings.value = [];
      loading.value = false;
      authLoading.value = false;

      try {
        localStorage.removeItem(sessionStorageKey);
      } catch {
        // La pulizia in memoria e il ritorno al login non dipendono dalla disponibilità dello storage.
      }
    };

    const loadAdminPlans = async () => { adminPlans.value = (await api<any>('/admin/plans'))?.data ?? []; };
    const saveAdminPlan = async (plan: any) => {
      const payload = { name: plan.name, description: plan.description ?? null, billing_model: plan.billing_model, billing_interval: plan.billing_interval ?? 'monthly', monthly_price_cents: Number(plan.monthly_price_cents || 0), booking_fee_cents: Number(plan.booking_fee_cents || 0), is_active: Boolean(plan.is_active) };
      const path = plan.ulid ? `/admin/plans/${plan.ulid}` : '/admin/plans'; await api(path, { method: plan.ulid ? 'PATCH' : 'POST', body: JSON.stringify(payload) }); await loadAdminPlans();
    };
    const assignAdminPlan = async (event: Event, organizer: OrganizerProfile) => {
      event.preventDefault();
      const form = new FormData(event.currentTarget as HTMLFormElement);
      await api('/admin/organizers/'+organizer.ulid+'/plan', {
        method: 'PUT',
        body: JSON.stringify({
          plan_ulid: form.get('plan_ulid'), status: form.get('status'),
          agreement_starts_at: form.get('agreement_starts_at') || null,
          agreement_ends_at: form.get('agreement_ends_at') || null,
          agreement_notes: form.get('agreement_notes') || null,
          booking_fee_cents: form.get('booking_fee_euro') === '' ? null : Math.round(Number(form.get('booking_fee_euro')) * 100),
        }),
      });
      await selectAdminOrganizer(organizer);
    };
    const runAdminSearch = async () => { if (adminSearchQuery.value.trim().length < 2) return; adminSearchResults.value = (await api<any>(`/admin/search?q=${encodeURIComponent(adminSearchQuery.value.trim())}`))?.data ?? null; };
    const moderateAdminEvent = async (event: any) => {
      const action = event.status === 'suspended' ? 'reactivate' : 'suspend';
      const reason = action === 'suspend' ? window.prompt('Motivazione della sospensione')?.trim() : '';
      if (action === 'suspend' && !reason) return;
      if (!window.confirm(action === 'suspend' ? 'Sospendere evento: ' + event.title + '?' : 'Riattivare evento: ' + event.title + '?')) return;
      await api(`/admin/events/${event.ulid}/${action}`, { method: 'POST', body: JSON.stringify(reason ? { reason } : {}) });
      await loadAdminData();
    };

    const completeAuth = async (payload: AuthResponse | SessionResponse, nextToken: string) => {
      clearSession();
      token.value = nextToken;
      currentUser.value = { ...payload.user, redirect_to: payload.redirect_to };
      localStorage.setItem(sessionStorageKey, nextToken);
      route.value = payload.redirect_to;

      if (route.value === '/organizer/onboarding') await loadOrganizerProfile();
      else if (route.value === '/organizer/pending' || route.value === '/organizer/rejected' || route.value === '/organizer/suspended') return;
      if (route.value === '/organizer/dashboard') await loadOrganizerDashboard(true);
      else if (route.value.startsWith('/organizer')) await loadOrganizerData();
      else if (route.value.startsWith('/customer')) await loadCustomerData();
      else if (route.value.startsWith('/admin')) await loadAdminData();
    };

    const submitAuth = async (path: '/auth/login' | '/auth/register', body: Record<string, string>) => {
      if (authLoading.value) return;
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

        if (path === '/auth/login' && authAudience.value && payload.user.role !== authAudience.value) {
          await fetch(`${apiBase}/auth/logout`, {
            method: 'POST',
            headers: { Accept: 'application/json', Authorization: `Bearer ${payload.token}` },
          }).catch(() => undefined);
          authError.value = `Questo account non appartiene all’area ${authAudience.value}.`;
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
      clearSession();

      if (!activeToken) return;

      try {
        const response = await fetch(`${apiBase}/auth/logout`, {
          method: 'POST',
          headers: { Accept: 'application/json', Authorization: `Bearer ${activeToken}` },
        });
        if (!response.ok) authError.value = 'Sessione locale chiusa; non è stato possibile confermare la revoca del token.';
      } catch {
        authError.value = 'Sessione locale chiusa; non è stato possibile contattare il servizio di logout.';
      }
    };

    onMounted(async () => {
      let savedToken: string | null = null;
      try {
        savedToken = localStorage.getItem(sessionStorageKey);
      } catch {
        clearSession();
      }
      if (!savedToken) {
        authRestoring.value = false;
        return;
      }
      const restoreVersion = sessionVersion;

      try {
        const response = await fetch(`${apiBase}/me`, {
          headers: { Accept: 'application/json', Authorization: `Bearer ${savedToken}` },
        });
        const payload = await response.json().catch(() => null) as SessionResponse | null;
        let tokenIsStillCurrent = false;
        try {
          tokenIsStillCurrent = localStorage.getItem(sessionStorageKey) === savedToken;
        } catch {
          tokenIsStillCurrent = false;
        }
        if (restoreVersion !== sessionVersion || !tokenIsStillCurrent) return;
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
          organizerEventsError.value = userMessage(error, 'Impossibile annullare l’evento.');
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

    const selectAuthAudience = (audience: AuthAudience) => {
      if (currentUser.value || token.value) return;
      authAudience.value = audience;
      authMode.value = 'login';
      authError.value = '';
      authFieldErrors.value = {};
      route.value = '/login';
    };

    const roleOptions: Array<{ audience: AuthAudience; icon: string; title: string; description: string; cta: string }> = [
      { audience: 'customer', icon: '✦', title: 'Customer', description: 'Scopri esperienze, prenota e porta sempre con te i tuoi QR.', cta: 'Scopri gli eventi' },
      { audience: 'organizer', icon: '◆', title: 'Organizer', description: 'Crea eventi, monitora prenotazioni e gestisci i partecipanti.', cta: 'Gestisci gli eventi' },
      { audience: 'admin', icon: '◈', title: 'Admin', description: 'Controlla la piattaforma, modera contenuti e gestisci gli accordi.', cta: 'Apri il backoffice' },
    ];

    const renderRoleSelection = () => h('section', { class: ['auth-screen', 'role-selection-screen'] }, [
      h('div', { class: ['auth-panel', 'role-selection-panel'] }, [
        h('div', { class: 'role-selection-brand' }, [
          h('img', { class: 'auth-brand-logo', src: '/branding/movi-logo-full.png', alt: 'MòVi — Vivi quello che succede.' }),
          h('span', { class: 'eyebrow' }, 'La tua città. I tuoi eventi. Adesso.'),
          h('h1', 'Vivi quello che succede.'),
          h('p', 'Scegli la tua area e accedi alla tua esperienza MòVi.'),
        ]),
        h('div', { class: 'auth-role-grid' }, roleOptions.map((option) =>
          h('button', {
            class: ['auth-role-card', 'auth-role-' + option.audience],
            type: 'button',
            onClick: () => selectAuthAudience(option.audience),
          }, [
            h('span', { class: 'auth-role-icon', 'aria-hidden': 'true' }, option.icon),
            h('span', { class: 'auth-role-copy' }, [
              h('strong', option.title),
              h('small', option.description),
            ]),
            h('span', { class: 'auth-role-cta' }, option.cta + ' →'),
          ]),
        )),
      ]),
    ]);
    const renderLoginForm = () => h('form', {
      class: 'auth-form',
      onSubmit: (event: Event) => { event.preventDefault(); void login(); },
    }, [
      authInput('Email', 'email', 'email', loginForm.value.email, (value) => { loginForm.value.email = value; }),
      authInput('Password', 'password', 'password', loginForm.value.password, (value) => { loginForm.value.password = value; }),
      h('button', { class: 'auth-submit', type: 'submit', disabled: authLoading.value }, authLoading.value ? 'Accesso in corso…' : 'Accedi'),
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

    const renderLogin = () => authAudience.value === null ? renderRoleSelection() : h('section', { class: 'auth-screen' }, [
      h('div', { class: 'auth-panel' }, [
        h('span', { class: 'eyebrow' }, 'Accesso sicuro'),
        h('h1', `Area ${authAudience.value}`),
        h('p', 'Accedi oppure crea un account reale. Le credenziali vengono sempre verificate dal backend.'),
        h('div', { class: 'auth-tabs', role: 'tablist' }, [
          h('button', { type: 'button', class: authMode.value === 'login' ? 'active' : '', onClick: () => setAuthMode('login') }, 'Accedi'),
          authAudience.value === 'customer' ? h('button', { type: 'button', class: authMode.value === 'register-customer' ? 'active' : '', onClick: () => setAuthMode('register-customer') }, 'Registrati come Customer') : null,
          authAudience.value === 'organizer' ? h('button', { type: 'button', class: authMode.value === 'register-organizer' ? 'active' : '', onClick: () => setAuthMode('register-organizer') }, 'Registrati come Organizer') : null,
          h('button', { type: 'button', onClick: () => { authAudience.value = null; authMode.value = 'login'; } }, 'Cambia area'),
        ]),
        authError.value ? h('p', { class: 'api-error', role: 'alert' }, authError.value) : null,
        authRestoring.value
          ? h('p', { class: 'auth-restoring' }, 'Verifica della sessione in corso…')
          : authMode.value === 'login' || authAudience.value === 'admin'
            ? renderLoginForm()
            : authMode.value === 'register-customer'
              ? renderCustomerRegistrationForm()
              : renderOrganizerRegistrationForm(),
      ]),
    ]);

    const profileInput = (label: string, name: keyof OrganizerProfile, type = 'text') => h('label', { class: 'auth-field' }, [
      h('span', label),
      h('input', {
        name, type, value: String(organizerProfile.value[name] ?? ''), disabled: organizerProfileSaving.value,
        onInput: (event: Event) => { (organizerProfile.value as any)[name] = (event.target as HTMLInputElement).value; },
      }),
      organizerProfileErrors.value[String(name)]?.map((message) => h('small', { class: 'field-error' }, message)),
    ]);

    const renderOrganizerOnboarding = () => h('main', { class: 'auth-screen onboarding-screen' }, [
      h('section', { class: 'auth-panel onboarding-panel' }, [
        h('span', { class: 'eyebrow' }, 'Profilo Organizer'),
        h('h1', route.value === '/organizer/profile' ? 'Modifica profilo Organizer' : 'Completa il profilo Organizer'),
        organizerProfile.value.verification_status === 'changes_requested' ? h('div', { class: 'review-reason' }, [
          h('strong', 'Integrazione richiesta'), h('p', organizerProfile.value.review_notes ?? ''),
        ]) : null,
        organizerProfileLoading.value ? h('p', 'Caricamento profilo…') : h('form', {
          class: 'onboarding-form',
          onSubmit: (event: Event) => { event.preventDefault(); route.value === '/organizer/profile' ? void saveOrganizerProfile() : void submitOrganizerProfile(); },
        }, [
          h('fieldset', [
            h('legend', 'Dati per la fatturazione'),
            h('div', { class: 'onboarding-grid' }, [
              profileInput('Denominazione o ragione sociale *', 'name'),
              profileInput('Indirizzo *', 'address'),
              profileInput('Numero civico *', 'street_number'),
              h('label', { class: 'auth-field' }, [
                h('span', 'CAP *'),
                h('input', { name: 'postal_code', inputmode: 'numeric', maxlength: 5, value: organizerProfile.value.postal_code ?? '', disabled: organizerProfileSaving.value, onInput: (event: Event) => { changeOrganizerPostalCode((event.target as HTMLInputElement).value); } }),
                postalCodeLoading.value ? h('small', 'Ricerca Comune…') : null,
                postalCodeError.value ? h('small', { class: 'field-error', role: 'alert' }, postalCodeError.value) : null,
                organizerProfileErrors.value.postal_code?.map((message) => h('small', { class: 'field-error' }, message)),
              ]),
              h('label', { class: 'auth-field' }, [
                h('span', 'Comune *'),
                postalMunicipalities.value.length > 1
                  ? h('select', { value: organizerProfile.value.municipality_id ?? '', disabled: organizerProfileSaving.value, onChange: (event: Event) => { selectPostalMunicipality(Number((event.target as HTMLSelectElement).value)); } }, [h('option', { value: '' }, 'Seleziona il Comune'), ...postalMunicipalities.value.map((item) => h('option', { value: item.id }, item.name))])
                  : h('input', { value: postalMunicipalities.value[0]?.name ?? organizerProfile.value.municipality?.name ?? '', readonly: true, disabled: organizerProfileSaving.value, placeholder: 'Compilato automaticamente dal CAP' }),
                organizerProfileErrors.value.municipality_id?.map((message) => h('small', { class: 'field-error' }, message)),
              ]),
              h('label', { class: 'auth-field' }, [h('span', 'Provincia *'), h('input', { value: organizerProfile.value.province ?? '', readonly: true, disabled: organizerProfileSaving.value, placeholder: 'Compilata automaticamente' }), organizerProfileErrors.value.province?.map((message) => h('small', { class: 'field-error' }, message))]),
              profileInput('Nazione *', 'country'),
              profileInput('Partita IVA *', 'vat_number'),
              profileInput('PEC *', 'pec', 'email'),
              profileInput('Codice SDI *', 'sdi_code'),
            ]),
          ]),
          h('fieldset', [
            h('legend', 'Dati pubblici facoltativi'),
            h('div', { class: 'onboarding-grid' }, [
              profileInput('Telefono', 'phone', 'tel'), profileInput('Email pubblica', 'public_email', 'email'),
              profileInput('Sito web', 'website_url', 'url'), profileInput('Instagram', 'instagram_url', 'url'),
              profileInput('Facebook', 'facebook_url', 'url'), profileInput('TikTok', 'tiktok_url', 'url'),
              profileInput('YouTube', 'youtube_url', 'url'),
              h('label', { class: 'auth-field' }, [h('span', 'Logo'), organizerProfile.value.logo_url ? h('img', { src: organizerProfile.value.logo_url, alt: 'Logo Organizer', class: 'organizer-profile-image' }) : null, h('input', { type: 'file', accept: 'image/*', onChange: (event: Event) => { void uploadOrganizerImage('logo', (event.target as HTMLInputElement).files?.[0]); } })]),
              h('label', { class: 'auth-field' }, [h('span', 'Copertina'), organizerProfile.value.cover_url ? h('img', { src: organizerProfile.value.cover_url, alt: 'Copertina Organizer', class: 'organizer-profile-image cover' }) : null, h('input', { type: 'file', accept: 'image/*', onChange: (event: Event) => { void uploadOrganizerImage('cover', (event.target as HTMLInputElement).files?.[0]); } })]),
              h('label', { class: 'auth-field onboarding-description' }, [h('span', 'Descrizione'), h('textarea', {
                value: organizerProfile.value.description ?? '',
                onInput: (event: Event) => { organizerProfile.value.description = (event.target as HTMLTextAreaElement).value; },
              })]),
            ]),
          ]),
          organizerProfileError.value ? h('p', { class: 'api-error' }, organizerProfileError.value) : null,
          organizerProfileSuccess.value ? h('p', { class: 'form-success' }, organizerProfileSuccess.value) : null,
          h('div', { class: 'onboarding-actions' }, [
            h('button', { type: 'button', disabled: organizerProfileSaving.value, onClick: () => { void saveOrganizerProfile(); } }, route.value === '/organizer/profile' ? 'Salva profilo' : 'Salva e continua più tardi'),
            route.value === '/organizer/profile' ? h('button', { type: 'button', onClick: () => { void go('/organizer/dashboard'); } }, 'Torna alla dashboard') : h('button', { class: 'auth-submit', type: 'submit', disabled: organizerProfileSaving.value }, organizerProfileSaving.value ? 'Salvataggio…' : 'Invia richiesta di approvazione'),
            h('button', { type: 'button', onClick: () => { void logout(); } }, 'Esci'),
          ]),
        ]),
      ]),
    ]);

    const renderOrganizerPending = () => h('main', { class: 'auth-screen' }, [
      h('section', { class: 'auth-panel pending-panel' }, [
        h('span', { class: 'eyebrow' }, 'Profilo Organizer'),
        h('h1', 'Account in attesa di approvazione'),
        h('p', `Ciao ${currentUser.value?.name ?? ''}, abbiamo ricevuto la richiesta di registrazione di ${currentUser.value?.organizer_profile?.name ?? 'questa organizzazione'}.`),
        h('p', 'Il nostro staff verificherà i dati dell’organizzazione. Potrai accedere alla dashboard e pubblicare eventi dopo l’approvazione.'),
        h('strong', 'Stato: in revisione'),
        h('button', { class: 'auth-submit', type: 'button', onClick: () => { void logout(); } }, 'Esci'),
      ]),
    ]);

    const renderOrganizerBlocked = (suspended = false) => h('main', { class: 'auth-screen' }, [
      h('section', { class: 'auth-panel pending-panel' }, [
        h('span', { class: 'eyebrow' }, 'Profilo Organizer'),
        h('h1', suspended ? 'Account sospeso' : 'Richiesta rifiutata'),
        h('p', currentUser.value?.organizer_profile?.review_notes ?? (suspended ? 'Contatta lo staff per maggiori informazioni.' : 'La richiesta non può essere approvata.')),
        h('button', { class: 'auth-submit', type: 'button', onClick: () => { void logout(); } }, 'Esci'),
      ]),
    ]);

    const roleNavIcon = (label: string) => ({ Home: '⌂', Eventi: '◫', Prenotazioni: '✓', Profilo: '◎', Dashboard: '⌂', Recensioni: '★', Opportunità: '↗' }[label] ?? '•');
    const renderHeader = (title: string, menu: readonly (readonly [string, AppRoute])[]) => h('header', { class: 'app-header' }, [
      h('div', { class: 'role-brand' }, [h('img', { class: 'role-brand-logo', src: '/branding/movi-icon.png', alt: '' }), h('div', [h('strong', 'MòVi'), h('span', title)])]),
      h('nav', { 'aria-label': title }, menu.map(([label, target]) => h('button', { class: route.value === target ? 'active' : '', type: 'button', 'aria-current': route.value === target ? 'page' : undefined, onClick: () => { void go(target); } }, [h('i', { 'aria-hidden': 'true' }, roleNavIcon(label)), h('span', label)]))),
      h('div', { class: 'role-account' }, [h('span', { class: 'account-avatar', 'aria-hidden': 'true' }, (currentUser.value?.name ?? 'U').slice(0, 1).toUpperCase()), h('div', [h('strong', currentUser.value?.name ?? title), h('small', currentUser.value?.email ?? '')]), h('button', { type: 'button', onClick: logout, 'aria-label': 'Esci dal tuo account' }, 'Esci')]),
    ]);

    const renderEventCard = (event: CustomerEvent) => {
      const date = event.starts_at ? new Date(event.starts_at) : null;
      const cover = event.organizer_profile?.cover_url;
      return h('article', { class: 'experience-card event-card' }, [
        h('div', { class: ['event-card-cover', cover ? 'has-image' : ''], style: cover ? { backgroundImage: `url(${cover})` } : undefined }, [h('span', { class: 'event-date' }, [h('strong', date ? String(date.getDate()).padStart(2, '0') : '—'), h('small', date ? date.toLocaleDateString('it-IT', { month: 'short' }) : '')]), h('span', { class: 'event-category' }, event.category ?? event.categories?.[0]?.name ?? 'Evento')]),
        h('div', { class: 'event-card-content' }, [h('p', { class: 'event-place' }, `⌖ ${event.municipality?.name ?? event.municipality ?? 'Molise'}`), h('h3', event.title), h('div', { class: 'event-card-footer' }, [h('div', [h('strong', event.is_free ? 'Gratis' : 'Prenotabile'), h('small', `${event.available_total ?? 0} posti`)]), h('button', { type: 'button', 'aria-label': `Apri ${event.title}`, onClick: () => { void go(`/customer/events/${event.ulid}`); } }, 'Scopri →')])]),
      ]);
    };
    const renderBookingSummary = (event: CustomerEvent, quantities: Record<string, number>) => {
      const items = selectedBookingItems(event, quantities); const total = items.reduce((sum, item) => sum + item.subtotal, 0);
      return h('section', { class: 'booking-summary', 'aria-live': 'polite' }, [h('h3', 'Riepilogo'), items.length ? h('ul', items.map((item) => h('li', [h('span', `${item.quantity} × ${item.ticket.name}`), h('strong', formatEuro(item.subtotal))]))) : h('p', 'Seleziona i ticket per visualizzare il totale.'), h('div', { class: 'booking-total' }, [h('span', 'Totale'), h('strong', formatEuro(total))])]);
    };

    const renderConfirmDialog = (title: string, event: CustomerEvent, quantities: Record<string, number>, onCancel: () => void, onConfirm: () => void) => h('div', { class: 'dialog-backdrop', role: 'presentation' }, h('section', { class: 'booking-dialog', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'booking-dialog-title' }, [h('h2', { id: 'booking-dialog-title' }, title), renderBookingSummary(event, quantities), h('p', 'Controlla i dati: dopo la conferma la disponibilità verrà aggiornata.'), h('div', { class: 'action-row' }, [h('button', { type: 'button', onClick: onCancel }, 'Torna alla modifica'), h('button', { type: 'button', disabled: customerBookingLoading.value, onClick: onConfirm }, customerBookingLoading.value ? 'Salvataggio…' : 'Conferma')])]));

    const renderCustomerEventDetail = () => {
      if (customerEventLoading.value) return [h('article', { class: 'wide-panel' }, 'Caricamento dettaglio evento…')];
      if (customerEventError.value) return [h('article', { class: 'wide-panel dashboard-error' }, [h('p', customerEventError.value), h('button', { onClick: () => { void loadCustomerEvent(route.value.split('/')[3]); } }, 'Riprova')])];
      const event = selectedCustomerEvent.value;
      if (!event) return [emptyState('Evento non disponibile.')];
      const activeTickets = event.ticket_types.filter((ticket) => ticket.is_active);
      return [h('div', { class: 'section-title' }, [h('div', [h('h2', event.title), h('span', `${event.municipality?.name ?? 'Luogo non indicato'} · ${new Date(event.starts_at).toLocaleString('it-IT')}`)]), h('div', { class: 'action-row' }, [h('button', { type: 'button', onClick: () => { void shareCustomerEvent(event); } }, 'Condividi'), h('button', { type: 'button', onClick: () => { shareCustomerEventOnWhatsApp(event); } }, 'WhatsApp'), h('button', { onClick: () => { void go('/customer/explore'); } }, 'Torna agli eventi')])]), customerShareMessage.value ? h('p', { role: 'status' }, customerShareMessage.value) : null, h('article', { class: 'wide-panel customer-event-detail' }, [
        h('p', event.description), h('p', [h('strong', `${event.available_total} posti disponibili su ${event.capacity_total}`), ' · Organizzato da ', event.organizer_profile ? h('button', { class: 'link-button', onClick: () => { void go(`/customer/organizers/${event.organizer_profile!.ulid}`); } }, event.organizer_profile.name) : 'Organizer']), h('h3', 'Scegli i ticket'),
        activeTickets.length === 0 ? h('p', 'Nessun ticket disponibile.') : h('div', { class: 'customer-ticket-list' }, activeTickets.map((ticket) => h('label', { class: 'customer-ticket-row' }, [h('span', [h('strong', ticket.name), h('small', formatEuro(ticket.price_cents)), h('small', `${purchasableTickets(event, ticket)} biglietti disponibili`), ticketAvailable(ticket) < event.available_total ? h('small', 'Disponibilità limitata per questa tipologia di biglietto.') : null]), h('input', { type: 'number', min: 0, max: purchasableTickets(event, ticket), value: customerTicketQuantities.value[ticket.ulid] ?? 0, disabled: customerBookingLoading.value || customerBookingResult.value !== null, 'aria-label': `Quantità ${ticket.name}`, onInput: (inputEvent: Event) => { customerTicketQuantities.value[ticket.ulid] = Math.min(purchasableTickets(event, ticket), Math.max(0, Number((inputEvent.target as HTMLInputElement).value) || 0)); } })]))),
        renderBookingSummary(event, customerTicketQuantities.value),
        h('label', { class: 'auth-field' }, [h('span', 'Telefono obbligatorio'), h('input', { type: 'tel', required: true, autocomplete: 'tel', value: customerBookingPhone.value, disabled: customerBookingLoading.value || customerBookingResult.value !== null, 'aria-invalid': customerBookingFieldErrors.value.phone ? 'true' : 'false', onInput: (inputEvent: Event) => { customerBookingPhone.value = (inputEvent.target as HTMLInputElement).value; delete customerBookingFieldErrors.value.phone; } }), customerBookingFieldErrors.value.phone ? h('small', { class: 'api-error' }, customerBookingFieldErrors.value.phone[0]) : null]),
        h('label', { class: 'checkbox-field' }, [h('input', { type: 'checkbox', checked: customerBookingSavePhone.value, disabled: customerBookingResult.value !== null, onChange: (inputEvent: Event) => { customerBookingSavePhone.value = (inputEvent.target as HTMLInputElement).checked; } }), h('span', 'Salva questo numero nel mio profilo')]),
        h('label', { class: 'auth-field' }, [h('span', 'Note facoltative'), h('textarea', { maxlength: 1000, value: customerBookingNotes.value, disabled: customerBookingResult.value !== null, onInput: (inputEvent: Event) => { customerBookingNotes.value = (inputEvent.target as HTMLTextAreaElement).value; } })]),
        customerBookingError.value ? h('p', { class: 'api-error', role: 'alert' }, customerBookingError.value) : null,
        customerBookingResult.value ? h('div', { class: 'booking-confirmation', role: 'status' }, [h('strong', 'Prenotazione confermata'), h('p', `Codice: ${customerBookingResult.value.booking_code}`), h('div', { class: 'booking-qr' }, customerBookingResult.value.qr_token)]) : event.available_total > 0 ? h('button', { type: 'button', disabled: customerBookingLoading.value || activeTickets.length === 0, onClick: openCustomerBookingConfirmation }, 'Continua') : customerWaitlist.value?.status === 'offered' ? h('div', { class: 'booking-confirmation', role: 'status' }, [h('strong', 'Posto disponibile'), h('p', `Offerta valida fino al ${new Date(customerWaitlist.value.reserved_until).toLocaleString('it-IT')}. La scadenza è determinata dal backend.`), h('button', { type: 'button', disabled: customerWaitlistLoading.value || !customerBookingPhone.value, onClick: () => { void acceptCustomerWaitlist(); } }, 'Conferma il posto')]) : ['waiting'].includes(customerWaitlist.value?.status) ? h('div', { class: 'booking-confirmation', role: 'status' }, [h('strong', 'Sei in lista d’attesa'), h('p', `Posizione: ${customerWaitlist.value.position ?? 'in aggiornamento'}`), h('button', { type: 'button', disabled: customerWaitlistLoading.value, onClick: () => { void leaveCustomerWaitlist(); } }, 'Esci dalla lista')]) : h('button', { type: 'button', disabled: customerWaitlistLoading.value || activeTickets.length === 0, onClick: () => { void joinCustomerWaitlist(); } }, customerWaitlistLoading.value ? 'Ingresso…' : 'Entra in lista d’attesa'),
      ]), customerBookingConfirmOpen.value ? renderConfirmDialog('Conferma prenotazione', event, customerTicketQuantities.value, () => { customerBookingConfirmOpen.value = false; }, () => { void confirmCustomerBooking(); }) : null];
    };
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
        event.next_edition ? h('button', { type: 'button', onClick: () => { void go(('/customer/events/' + event.next_edition.ulid) as AppRoute); } }, 'Apri nuova edizione') : null,
        h('button', { type: 'button', onClick: () => { void notifySimilar(event.ulid); } }, 'Avvisami quando torna'),
      ]),
    ]);

    const renderCustomerDashboard = () => [
      h('section', { class: 'customer-discovery-hero' }, [
        h('div', [h('span', { class: 'eyebrow' }, 'Scopri il Molise'), h('h1', 'Eventi locali, esperienze autentiche.'), h('p', 'Cultura, sapori e incontri selezionati dagli organizer del territorio.'), h('button', { type: 'button', onClick: () => { void go('/customer/explore'); } }, 'Esplora gli eventi')]),
        h('div', { class: 'discovery-signal', 'aria-label': 'Eventi disponibili' }, [h('strong', String(allCustomerEvents.value.length)), h('span', 'eventi da scoprire')]),
      ]),
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
      allCustomerEvents.value.length === 0 ? emptyState('Nessun evento pubblicato disponibile dal database.') : h('div', { class: 'event-grid' }, allCustomerEvents.value.map(renderEventCard)),
    ];

    const renderSuccessfulEvents = () => [
      h('div', { class: 'section-title' }, [h('h2', 'Eventi di Successo'), h('span', 'ranking con success score aggregato')]),
      successfulEvents.value.length === 0 ? emptyState('Nessun evento concluso valido nel feed successi.') : h('div', { class: 'event-grid' }, successfulEvents.value.map(renderSuccessfulEventCard)),
    ];

    const renderCustomerProfile = () => [
      h('div', { class: 'section-title' }, [h('h2', 'Il mio profilo')]),
      h('form', { class: 'wide-panel booking-detail', onSubmit: (event: Event) => { event.preventDefault(); void saveCustomerProfile(); } }, [
        h('label', { class: 'auth-field' }, [h('span', 'Nome'), h('input', { value: customerProfile.value.name, onInput: (event: Event) => { customerProfile.value.name = (event.target as HTMLInputElement).value; } })]),
        h('label', { class: 'auth-field' }, [h('span', 'Email'), h('input', { type: 'email', value: customerProfile.value.email, onInput: (event: Event) => { customerProfile.value.email = (event.target as HTMLInputElement).value; } })]),
        h('label', { class: 'auth-field' }, [h('span', 'Telefono'), h('input', { type: 'tel', autocomplete: 'tel', value: customerProfile.value.phone ?? '', onInput: (event: Event) => { customerProfile.value.phone = (event.target as HTMLInputElement).value; } })]),
        h('button', { disabled: customerSectionLoading.value, type: 'submit' }, 'Salva modifiche'),
      ]),
      h('form', { class: 'wide-panel booking-detail', onSubmit: (event: Event) => { event.preventDefault(); void saveCustomerPassword(); } }, [
        h('h3', 'Cambia password'),
        h('label', { class: 'auth-field' }, [h('span', 'Password attuale'), h('input', { type: 'password', autocomplete: 'current-password', value: customerPassword.value.current_password, onInput: (event: Event) => { customerPassword.value.current_password = (event.target as HTMLInputElement).value; } })]),
        h('label', { class: 'auth-field' }, [h('span', 'Nuova password'), h('input', { type: 'password', autocomplete: 'new-password', value: customerPassword.value.password, onInput: (event: Event) => { customerPassword.value.password = (event.target as HTMLInputElement).value; } })]),
        h('label', { class: 'auth-field' }, [h('span', 'Conferma nuova password'), h('input', { type: 'password', autocomplete: 'new-password', value: customerPassword.value.password_confirmation, onInput: (event: Event) => { customerPassword.value.password_confirmation = (event.target as HTMLInputElement).value; } })]),
        h('button', { disabled: customerSectionLoading.value, type: 'submit' }, 'Aggiorna password'),
      ]),
      customerSectionMessage.value ? h('p', { role: 'status' }, customerSectionMessage.value) : null,
    ];
    const renderCustomerFavorites = () => [h('div', { class: 'section-title' }, [h('h2', 'Eventi preferiti')]), customerFavorites.value.length === 0 ? emptyState('Nessun evento preferito.') : h('div', { class: 'event-grid' }, customerFavorites.value.map((event) => h('article', { class: 'experience-card' }, [h('h3', event.title), h('button', { onClick: () => { void go(`/customer/events/${event.ulid}`); } }, 'Apri'), h('button', { onClick: () => { void removeFavorite(event.ulid); } }, 'Rimuovi')])) )];
    const renderPublicOrganizer = () => { const payload = publicOrganizer.value; if (customerSectionLoading.value) return [h('article', { class: 'wide-panel' }, 'Caricamento Organizer…')]; if (!payload) return [emptyState(customerSectionMessage.value || 'Organizer non disponibile.')]; const organizer = payload.data; return [h('article', { class: 'wide-panel organizer-public' }, [organizer.cover_url ? h('img', { src: organizer.cover_url, alt: '', class: 'organizer-cover' }) : null, organizer.logo_url ? h('img', { src: organizer.logo_url, alt: `Logo ${organizer.name}`, class: 'organizer-logo' }) : null, h('h2', organizer.name), h('p', organizer.description ?? ''), h('p', `${organizer.public_email ?? ''} ${organizer.phone ?? ''}`), h('div', { class: 'action-row' }, [h('button', { type: 'button', disabled: followActionLoading.value, onClick: () => { void toggleOrganizerFollow(); } }, followActionLoading.value ? 'Aggiornamento…' : organizer.is_following ? 'Seguito · Smetti di seguire' : 'Segui'), ...['website_url', 'facebook_url', 'instagram_url', 'tiktok_url', 'youtube_url'].filter((key) => organizer[key]).map((key) => h('a', { href: organizer[key], target: '_blank', rel: 'noopener' }, key.replace('_url', '')))]), h('strong', `${organizer.events_count ?? 0} eventi · ${payload.reviews_count ?? 0} recensioni · ${organizer.followers_count ?? 0} follower`), customerSectionMessage.value ? h('p', { role: 'status' }, customerSectionMessage.value) : null]), h('h3', 'Eventi futuri'), h('div', { class: 'event-grid' }, (payload.future_experiences ?? []).map(renderEventCard)), h('h3', 'Eventi conclusi'), h('div', { class: 'event-grid' }, (payload.completed_experiences ?? []).map(renderEventCard))]; };
    const renderCustomerFollowing = () => [
      h('div', { class: 'section-title' }, [h('h2', 'Organizer seguiti'), h('span', `${customerFollowing.value.length} seguiti`)]),
      customerSectionLoading.value ? h('article', { class: 'wide-panel' }, 'Caricamento…') : customerFollowing.value.length === 0 ? emptyState('Non segui ancora alcun Organizer.') : h('div', { class: 'event-grid' }, customerFollowing.value.map((organizer) => h('article', { class: 'experience-card' }, [organizer.logo_url ? h('img', { src: organizer.logo_url, alt: '' }) : null, h('h3', organizer.name), h('p', organizer.municipality?.name ?? ''), h('button', { type: 'button', onClick: () => { void go(`/customer/organizers/${organizer.ulid}`); } }, 'Apri profilo')]))),
    ];

    const renderCustomerBookingEdit = () => {
      const draft = customerBookingEdit.value; if (!draft) return null;
      return h('div', { class: 'dialog-backdrop' }, h('section', { class: 'booking-dialog booking-edit-dialog', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'edit-booking-title' }, [h('h2', { id: 'edit-booking-title' }, `Modifica ${draft.booking.booking_code}`), h('div', { class: 'customer-ticket-list' }, draft.event.ticket_types.filter((ticket) => ticket.is_active || draft.quantities[ticket.ulid]).map((ticket) => h('label', { class: 'customer-ticket-row' }, [h('span', [h('strong', ticket.name), h('small', formatEuro(ticket.price_cents)), h('small', `${editableTickets(draft, ticket)} biglietti acquistabili`)]), h('input', { type: 'number', min: 0, max: editableTickets(draft, ticket), value: draft.quantities[ticket.ulid] ?? 0, onInput: (event: Event) => { draft.quantities[ticket.ulid] = Math.min(editableTickets(draft, ticket), Math.max(0, Number((event.target as HTMLInputElement).value) || 0)); } })]))), renderBookingSummary(draft.event, draft.quantities), h('label', { class: 'auth-field' }, [h('span', 'Telefono obbligatorio'), h('input', { type: 'tel', required: true, value: draft.phone, onInput: (event: Event) => { draft.phone = (event.target as HTMLInputElement).value; } })]), h('label', { class: 'checkbox-field' }, [h('input', { type: 'checkbox', checked: draft.savePhone, onChange: (event: Event) => { draft.savePhone = (event.target as HTMLInputElement).checked; } }), h('span', 'Salva questo numero nel mio profilo')]), h('label', { class: 'auth-field' }, [h('span', 'Note'), h('textarea', { maxlength: 1000, value: draft.notes, onInput: (event: Event) => { draft.notes = (event.target as HTMLTextAreaElement).value; } })]), draft.error ? h('p', { class: 'api-error', role: 'alert' }, draft.error) : null, h('div', { class: 'action-row' }, [h('button', { type: 'button', onClick: () => { customerBookingEdit.value = null; } }, 'Chiudi'), h('button', { type: 'button', disabled: customerBookingLoading.value, onClick: openCustomerBookingEditConfirmation }, 'Rivedi e salva')]), draft.confirmOpen ? renderConfirmDialog('Conferma modifica', draft.event, draft.quantities, () => { draft.confirmOpen = false; }, () => { void confirmCustomerBookingEdit(); }) : null]));
    };

    const renderCustomerBookings = () => [
      h('div', { class: 'section-title' }, [h('h2', 'Cronologia prenotazioni')]),
      customerSectionMessage.value ? h('p', { role: 'status' }, customerSectionMessage.value) : null,
      customerBookings.value.length === 0 ? emptyState('Nessuna prenotazione.') : h('div', { class: 'event-grid' }, customerBookings.value.map((booking) => h('article', { class: 'experience-card booking-history-card' }, [
        h('span', { class: `booking-status status-${booking.status}` }, bookingStatusLabel(booking.status)),
        h('h3', booking.event.title),
        h('dl', { class: 'booking-facts' }, [
          h('div', [h('dt', 'Data'), h('dd', new Date(booking.event.starts_at).toLocaleString('it-IT', { dateStyle: 'medium', timeStyle: 'short' }))]),
          h('div', [h('dt', 'Biglietti'), h('dd', booking.items.map((item) => `${item.ticket_type.name} × ${item.quantity}`).join(', '))]),
          h('div', [h('dt', 'Quantità'), h('dd', `${booking.total_people} persone`)]),
          h('div', [h('dt', 'Totale'), h('dd', formatEuro(booking.total_price_cents))]),
          h('div', [h('dt', 'Telefono'), h('dd', booking.contact_phone || 'Non indicato')]),
          h('div', [h('dt', 'Codice'), h('dd', booking.booking_code)]),
        ]),
        h('div', { class: 'action-row' }, [h('button', { type: 'button', onClick: () => { void loadCustomerQrSafe(booking.ulid); } }, customerQrTokens.value[booking.ulid] ? 'Aggiorna QR' : 'Mostra QR'), booking.can_edit ? h('button', { type: 'button', onClick: () => { void openCustomerBookingEdit(booking); } }, 'Modifica prenotazione') : null, booking.can_cancel ? h('button', { type: 'button', class: 'danger-button', disabled: cancellingCustomerBooking.value !== null, onClick: () => { void cancelCustomerBooking(booking); } }, cancellingCustomerBooking.value === booking.ulid ? 'Annullamento…' : 'Annulla prenotazione') : null, h('button', { type: 'button', onClick: () => { void reviewBooking(booking); } }, 'Lascia recensione')]),
        customerQrTokens.value[booking.ulid] ? h('div', { class: 'booking-qr', role: 'img', 'aria-label': 'Token QR della prenotazione' }, customerQrTokens.value[booking.ulid]) : null,
      ]))),
      renderCustomerBookingEdit(),
    ];
    const renderCustomerActivities = () => [h('div', { class: 'section-title' }, [h('h2', 'Attività prenotabili'), h('span', 'Ristoranti e servizi reali')]), customerActivities.value.length === 0 ? emptyState('Nessuna attività prenotabile.') : h('div', { class: 'event-grid' }, customerActivities.value.map((activity) => h('article', { class: 'experience-card' }, [h('span', activity.type === 'restaurant' ? 'Ristorante' : activity.type), h('h3', activity.name), h('p', activity.description ?? ''), h('p', activity.municipality?.name ?? ''), h('button', { type: 'button', onClick: () => { void go(`/customer/activities/${activity.ulid}`); } }, 'Vedi disponibilità')])) )];
    const renderCustomerActivityDetail = () => { const activity = selectedActivity.value; if (!activity) return [emptyState('Attività non disponibile.')]; const selectedService = activity.services?.find((service: any) => service.ulid === activityServiceUlid.value); return [h('div', { class: 'section-title' }, [h('div', [h('h2', activity.name), h('span', activity.municipality?.name ?? '')]), h('button', { type: 'button', onClick: () => { void toggleActivityFollow(); } }, activity.is_following ? 'Seguita · Smetti' : 'Segui attività')]), h('article', { class: 'wide-panel booking-detail' }, [h('p', activity.description ?? ''), h('label', { class: 'auth-field' }, [h('span', 'Servizio'), h('select', { value: activityServiceUlid.value, onChange: (event: Event) => { activityServiceUlid.value = (event.target as HTMLSelectElement).value; void loadActivityAvailability(); } }, (activity.services ?? []).map((service: any) => h('option', { value: service.ulid }, `${service.name} · ${service.min_party_size}-${service.max_party_size} persone`)))]), h('label', { class: 'auth-field' }, [h('span', 'Data'), h('input', { type: 'date', value: activityDate.value, onChange: (event: Event) => { activityDate.value = (event.target as HTMLInputElement).value; void loadActivityAvailability(); } })]), h('label', { class: 'auth-field' }, [h('span', 'Persone'), h('input', { type: 'number', min: selectedService?.min_party_size ?? 1, max: selectedService?.max_party_size ?? 20, value: activityPartySize.value, onInput: (event: Event) => { activityPartySize.value = Number((event.target as HTMLInputElement).value); } })]), h('div', { class: 'event-grid' }, activityAvailability.value.map((slot) => h('article', { class: 'experience-card' }, [h('strong', new Date(slot.starts_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })), h('p', slot.available === 0 ? 'Esaurito' : slot.available <= 3 ? `Pochi posti: ${slot.available}` : `${slot.available} coperti disponibili`), h('button', { type: 'button', disabled: slot.available < activityPartySize.value, onClick: () => { void createCustomerReservation(slot); } }, 'Prenota')]))), activityMessage.value ? h('p', { role: 'status' }, activityMessage.value) : null])]; };
    const renderCustomerReservations = () => [h('div', { class: 'section-title' }, [h('h2', 'Prenotazioni locali')]), customerReservations.value.length === 0 ? emptyState('Nessuna prenotazione locale.') : h('div', { class: 'event-grid' }, customerReservations.value.map((reservation) => h('article', { class: 'experience-card' }, [h('span', reservation.status), h('h3', reservation.activity?.name ?? 'Attività'), h('p', `${reservation.service?.name ?? ''} · ${new Date(reservation.starts_at).toLocaleString('it-IT')}`), h('strong', `${reservation.party_size} persone`), reservation.status === 'confirmed' && new Date(reservation.starts_at) > new Date() ? h('button', { type: 'button', onClick: () => { void cancelCustomerReservation(reservation); } }, 'Annulla') : null])))];
    const renderMoviPlan = () => [h('div', { class: 'page-heading' }, [h('h1', 'Chiedi a MòVi'), h('p', 'Descrivi cosa vuoi fare: i risultati arrivano dai motori reali MòVi.')]), h('form', { class: 'wide-panel', onSubmit: generateMoviPlan }, [h('label', 'Cosa vuoi fare?'), h('div', { class: 'action-row' }, [h('input', { value: moviAiMessage.value, onInput: (event: Event) => { moviAiMessage.value = (event.target as HTMLInputElement).value; }, maxlength: 1000, placeholder: 'Organizzami sabato sera con cena e musica.' }), h('button', { type: 'submit', disabled: planLoading.value || !moviAiMessage.value.trim() }, planLoading.value ? 'Ricerca…' : 'CHIEDI A MÒVI')]), h('div', { class: 'action-row' }, ['Stasera', 'Weekend', 'Cena + evento', 'Famiglia', 'Vicino a me'].map((suggestion) => h('button', { type: 'button', onClick: () => { moviAiMessage.value = suggestion; } }, suggestion)))]), moviAiError.value ? h('p', { class: 'error-message' }, moviAiError.value) : null, moviAiResponse.value ? h('section', { class: 'wide-panel' }, [h('h2', 'Ho interpretato la richiesta'), h('p', moviAiResponse.value.interpretation_summary), ...(moviAiResponse.value.warnings ?? []).map((warning: string) => h('p', { class: 'section-message' }, warning)), moviAiResponse.value.clarification_needed ? h('p', moviAiResponse.value.clarification_needed) : null]) : null, ...moviPlans.value.map((plan: any) => h('article', { class: 'wide-panel', onClick: () => { void trackPlanInteraction('plan_opened', plan); } }, [h('h2', plan.title), h('p', plan.reasons.join(' · ')), h('p', plan.known_total_price_cents === null ? 'Costo totale non completamente disponibile' : `Costo noto € ${(plan.known_total_price_cents / 100).toFixed(2)}`), ...plan.steps.map((step: any) => h('div', { class: 'booking-detail' }, [h('strong', new Date(step.start).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })), h('span', ` ${step.title} · ${step.location ?? ''} · ${step.availability_status}`), h('button', { type: 'button', onClick: (event: Event) => { event.stopPropagation(); void trackPlanInteraction('plan_step_opened', plan, step); void go((step.entity_type === 'event' ? `/customer/events/${step.entity_id}` : `/customer/activities/${step.entity_id}`) as AppRoute); } }, 'Dettaglio')]))]))];

    const CustomerLayout = () => h('main', { class: 'role-shell customer-layout' }, [
      renderHeader('Dashboard cliente', customerMenu),
      h('section', { class: 'role-content' }, [
        apiError.value ? h('p', { class: 'api-error' }, apiError.value) : null,
        ...(route.value.startsWith('/customer/activities/') ? renderCustomerActivityDetail()
          : route.value === '/customer/activities' ? renderCustomerActivities()
          : route.value === '/customer/reservations' ? renderCustomerReservations()
          : route.value === '/customer/plan' ? renderMoviPlan()
          : route.value.startsWith('/customer/events/')
          ? renderCustomerEventDetail()
          : route.value.startsWith('/customer/organizers/') ? renderPublicOrganizer()
          : route.value === '/customer/profile' ? renderCustomerProfile()
          : route.value === '/customer/following' ? renderCustomerFollowing()
          : route.value === '/customer/bookings' ? renderCustomerBookings()
          : route.value === '/customer/preferences' ? renderCustomerFavorites()
          : route.value === '/customer/explore'
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
        h('section', { class: 'organizer-command-hero' }, [
          h('div', [h('span', { class: 'eyebrow' }, 'Workspace organizer'), h('h1', 'La regia dei tuoi eventi.'), h('p', 'Numeri, operatività e prossime azioni in un unico spazio.')]),
          h('div', { class: 'command-actions' }, [h('button', { type: 'button', onClick: () => { void go('/organizer/create-event'); } }, '＋ Crea evento'), h('button', { type: 'button', onClick: () => { void go('/organizer/bookings'); } }, 'Gestisci prenotazioni')]),
        ]),
        h('div', { class: 'metric-grid organizer-kpis' }, [
          card('Eventi futuri', `${bi.future_events}`, 'pubblicati o sold out'),
          card('Eventi conclusi', `${bi.completed_events}`, 'stato completed'),
          card('Eventi non pubblicati', `${bi.unpublished_events}`, 'bozze e stati non pubblici'),
          card('Prenotazioni valide', `${bi.bookings_received}`, 'confermate o completate'),
          card('Partecipanti', `${bi.participants_total}`, 'persone in prenotazioni valide'),
          card('Posti disponibili', `${bi.available_places}`, 'eventi operativi futuri'),
          card('Posti occupati', `${bi.occupied_places}`, 'eventi operativi futuri'),
          card('Follower', `${bi.followers_total}`, 'utenti che seguono'),
          card('Visualizzazioni', `${bi.views_total}`, 'esperienze visualizzate'),
          card('Impression', `${bi.impressions}`, 'apparizioni deduplicate per sessione/giorno'),
          card('Aperture dettaglio', `${bi.detail_views}`, 'visite deduplicate per sessione/giorno'),
          card('Condivisioni', `${bi.shares}`, 'azioni di condivisione registrate'),
          card('Cancellazioni', `${bi.cancellations}`, `${bi.cancellation_rate}% delle prenotazioni create`),
          card('Waitlist recuperate', `${bi.waitlist_converted}/${bi.waitlist_joined}`, 'offerte convertite in booking'),
          card('Check-in', `${bi.check_in_people}`, `${bi.attendance_rate}% partecipazione`),
          card('No-show', `${bi.no_show_people}`, `${bi.no_show_rate}% dei partecipanti attesi`),
          card('Conversione booking', `${bi.booking_conversion_rate}%`, 'booking su aperture dettaglio'),
          card('Follower acquisiti', `${bi.followers_acquired}`, `${bi.follow_conversion_rate}% su aperture`),
          card('Tasso recensione', `${bi.review_rate}%`, 'recensioni su partecipanti verificati'),
          card('Rating medio', bi.average_rating === null ? '—' : `${bi.average_rating}`, bi.average_rating === null ? 'Nessuna recensione' : 'recensioni approvate'),
          card('Numero recensioni', `${bi.review_analytics.reviews_count}`, 'recensioni approvate'),
        ]),
        h('article', { class: 'wide-panel dashboard-state' }, [h('strong', 'Performance categorie'), data.category_performance?.length ? h('ul', data.category_performance.map((category: any) => h('li', `${category.name}: ${category.bookings_count} prenotazioni · ${Number(category.fill_rate).toFixed(1)}% riempimento`))) : h('p', 'Nessun dato categoria disponibile.')]),
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
          h('div', { class: 'action-row' }, [
            h('button', { type: 'button', onClick: () => { void go(`/organizer/events/${item.event.ulid}/participants`); } }, 'Partecipanti e check-in'),
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
          ]),
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
          onInput: (event: Event) => { (eventFormData.value[name] as string) = (event.target as HTMLInputElement).value; if (name === 'starts_at') void loadEventCompetition(); },
        }),
        ...eventFieldErrors(name),
      ]);

      const ticketCards = tickets.value.map((ticket, index) => h('article', { class: 'ticket-editor' }, [
        h('div', { class: 'ticket-editor-heading' }, [
          h('strong', `Biglietto ${index + 1}`),
          ticket.ulid ? h('small', `Biglietti venduti: ${ticket.booked_quantity}`) : h('small', 'Nuovo biglietto'),
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
            h('span', 'Quantità massima ticket'),
            h('input', { type: 'number', min: Math.max(1, ticket.booked_quantity), value: ticket.max_quantity, disabled: eventFormSubmitting.value, onInput: (event: Event) => { ticket.max_quantity = (event.target as HTMLInputElement).value; } }), h('small', 'Numero massimo disponibile per questa tipologia di biglietto.'), h('small', `Biglietti venduti: ${ticket.booked_quantity} · Biglietti disponibili: ${Math.max(0, Number(ticket.max_quantity) - ticket.booked_quantity)}`),
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
              h('select', { value: eventFormData.value.category_id, disabled: categoriesLoading.value || eventFormSubmitting.value, onChange: (event: Event) => { eventFormData.value.category_id = (event.target as HTMLSelectElement).value; void loadEventCompetition(); } }, [
                h('option', { value: '' }, categoriesLoading.value ? 'Caricamento…' : 'Seleziona categoria'),
                ...eventCategories.value.map((category) => h('option', { value: String(category.id) }, category.name)),
              ]),
              ...eventFieldErrors('category_ids'),
              ...eventFieldErrors('category_ids.0'),
            ]),
            h('label', { class: 'event-form-field' }, [
              h('span', 'Luogo'),
              h('select', { value: eventFormData.value.municipality_id, disabled: eventFormSubmitting.value, onChange: (event: Event) => { eventFormData.value.municipality_id = (event.target as HTMLSelectElement).value; void loadEventCompetition(); } }, [
                h('option', { value: '' }, 'Seleziona comune'),
                ...eventMunicipalities.value.map((municipality) => h('option', { value: String(municipality.id) }, `${municipality.name}${municipality.province?.code ? ` (${municipality.province.code})` : ''}`)),
              ]),
              ...eventFieldErrors('municipality_id'),
            ]),
            input('Data e ora di inizio', 'starts_at', 'datetime-local'),
            input('Data e ora di fine', 'ends_at', 'datetime-local'),
            h('section', { class: 'event-competition event-form-wide', 'aria-label': 'Concorrenza nel Comune' }, [
              h('div', { class: 'event-competition-legend' }, [
                h('strong', 'Concorrenza nel Comune'),
                h('span', { class: 'competition-yellow' }, 'Giallo: altri eventi nello stesso Comune'),
                h('span', { class: 'competition-red' }, 'Rosso: almeno 2 eventi nella stessa categoria'),
              ]),
              eventCompetitionLoading.value ? h('small', 'Aggiornamento calendario...') :
              eventCompetitionDays.value.length === 0 ? h('small', 'Seleziona Comune e categoria per vedere la concorrenza.') :
              h('div', { class: 'competition-calendar' }, eventCompetitionDays.value.map((day) =>
                h('div', { class: `competition-day competition-${day.color}`, title: `${day.events_count} eventi nel Comune, ${day.same_category_count} nella categoria` }, [
                  h('strong', String(new Date(`${day.date}T12:00:00`).getDate())),
                  day.events_count ? h('small', `${day.events_count}`) : null,
                ]))),
            ]),
            h('label', { class: 'event-form-field capacity-field' }, [
              h('span', 'Capienza totale'),
              h('input', {
                type: 'number',
                min: eventBookedPeople.value,
                value: eventFormData.value.capacity_total,
                disabled: eventFormSubmitting.value,
                onInput: (event: Event) => { eventFormData.value.capacity_total = (event.target as HTMLInputElement).value; },
              }),
              h('small', { class: 'capacity-summary' }, `${eventBookedPeople.value} prenotati · ${Math.max(0, Number(eventFormData.value.capacity_total) - eventBookedPeople.value)} posti disponibili`),
              eventBookedPeople.value > 0 ? h('small', `Capienza minima consentita: ${eventBookedPeople.value}`) : null,
              ...eventFieldErrors('capacity_total'),
            ]),
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

    const renderOrganizerActivity = (activity: any) => h('article', { class: 'wide-panel booking-detail' }, [
      h('div', { class: 'panel-heading' }, [h('h2', activity.name), h('span', activity.booking_enabled ? 'Prenotazioni attive' : 'Disattivate')]),
      h('p', activity.description ?? ''),
      h('form', { class: 'action-row', onSubmit: (event: Event) => { void createActivityService(event, activity); } }, [
        h('select', { name: 'type' }, [h('option', { value: 'lunch' }, 'Pranzo'), h('option', { value: 'dinner' }, 'Cena')]),
        h('input', { name: 'min_party_size', type: 'number', min: 1, value: 1, 'aria-label': 'Minimo persone' }),
        h('input', { name: 'max_party_size', type: 'number', min: 1, value: 8, 'aria-label': 'Massimo persone' }),
        h('input', { name: 'minimum_advance_minutes', type: 'number', min: 0, value: 60, 'aria-label': 'Anticipo minimo minuti' }),
        h('button', { type: 'submit' }, 'Aggiungi servizio'),
      ]),
      discoveryResults.value.length ? h('section', [h('div', { class: 'section-title' }, [h('h2', 'Oggi')]), h('div', { class: 'event-grid' }, discoveryResults.value.slice(0, 6).map((item: any) => h('article', { class: 'experience-card' }, [h('span', item.entity_type === 'event' ? 'Evento' : 'Attività'), h('h3', item.title), h('p', `${item.location ?? ''} · ${item.availability_status}`), h('button', { type: 'button', onClick: () => { void go((item.entity_type === 'event' ? `/customer/events/${item.entity_id}` : `/customer/activities/${item.entity_id}`) as AppRoute); } }, 'Apri')])) )]) : null,
      recommendationResults.value.length ? h('section', [h('div', { class: 'section-title' }, [h('h2', 'Per te')]), h('div', { class: 'event-grid' }, recommendationResults.value.slice(0, 6).map((item: any) => h('article', { class: 'experience-card' }, [h('h3', item.title), h('p', (item.recommendation_reasons ?? []).join(' · '))])))]) : null,
      ...(activity.services ?? []).map((service: any) => h('section', { class: 'capacity-summary' }, [
        h('h3', service.name), h('p', `${service.min_party_size}-${service.max_party_size} persone · anticipo ${service.minimum_advance_minutes} minuti`),
        h('form', { class: 'action-row', onSubmit: (event: Event) => { void configureActivitySlot(event, activity, service); } }, [
          h('select', { name: 'weekday' }, ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'].map((day, index) => h('option', { value: index + 1 }, day))),
          h('input', { name: 'start_time', type: 'time', required: true, value: service.type === 'dinner' ? '20:00' : '12:30' }),
          h('input', { name: 'capacity', type: 'number', min: 0, required: true, value: 10, 'aria-label': 'Coperti disponibili' }),
          h('button', { type: 'submit' }, 'Salva slot'),
        ]),
        h('ul', (service.rules ?? []).map((rule: any) => h('li', `Giorno ${rule.weekday} · ${String(rule.start_time).slice(0, 5)} · ${rule.capacity} coperti`))),
        h('form', { class: 'action-row', onSubmit: (event: Event) => { void configureActivityClosure(event, activity, service); } }, [
          h('input', { name: 'date', type: 'date', required: true, 'aria-label': 'Data chiusura' }),
          h('input', { name: 'note', placeholder: 'Motivo chiusura' }),
          h('button', { type: 'submit' }, 'Aggiungi chiusura'),
        ]),
        h('ul', (service.exceptions ?? []).map((exception: any) => h('li', `${exception.date} · ${exception.is_closed ? 'Chiuso' : 'Disponibilità speciale'}`))),
      ])),
    ]);
    const renderOrganizerActivityReservations = (activity: any) => h('section', { class: 'capacity-summary' }, [
      h('h3', 'Prenotazioni'),
      ...(organizerActivityReservations.value[activity.ulid] ?? []).map((reservation: any) => h('article', { class: 'action-row' }, [h('p', `${reservation.customer?.name ?? 'Cliente'} · ${reservation.service?.name ?? ''} · ${new Date(reservation.starts_at).toLocaleString('it-IT')} · ${reservation.party_size} persone · ${reservation.status}`), reservation.status === 'confirmed' && new Date(reservation.starts_at) < new Date() ? h('span', [h('button', { type: 'button', onClick: () => { void transitionOrganizerReservation(reservation, 'completed'); } }, 'Completata'), h('button', { type: 'button', onClick: () => { void transitionOrganizerReservation(reservation, 'no_show'); } }, 'No-show')]) : null])),
    ]);
    const renderOrganizerActivities = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'MòVi Manager · Attività'), h('p', 'Configura servizi e coperti senza gestione tavoli.')]),
      organizerReservationDashboard.value ? h('section', { class: 'metric-grid' }, [h('article', { class: 'metric-card' }, [h('strong', organizerReservationDashboard.value.today.reservations), h('span', 'Prenotazioni oggi')]), h('article', { class: 'metric-card' }, [h('strong', organizerReservationDashboard.value.today.covers), h('span', 'Coperti oggi')]), h('article', { class: 'metric-card' }, [h('strong', organizerReservationDashboard.value.today.cancellations), h('span', 'Cancellazioni')]), h('article', { class: 'metric-card' }, [h('strong', organizerReservationDashboard.value.today.no_show), h('span', 'No-show')])]) : null,
      organizerReservationAnalytics.value ? h('section', { class: 'capacity-summary' }, [h('h3', 'Andamento prenotazioni'), h('p', `${organizerReservationAnalytics.value.reservations} prenotazioni · ${organizerReservationAnalytics.value.covers} coperti · media ${organizerReservationAnalytics.value.average_party_size} · cancellazioni ${organizerReservationAnalytics.value.cancellation_rate}% · no-show ${organizerReservationAnalytics.value.no_show_rate}%`)]) : null,
      h('section', { class: 'capacity-summary' }, [h('h3', 'Clienti'), ...organizerReservationCustomers.value.slice(0, 10).map((customer: any) => h('p', `${customer.name} · ${customer.reservations_count} prenotazioni · ${customer.no_shows} no-show${customer.repeat_customer ? ' · abituale' : ''}`))]),
      h('form', { class: 'wide-panel booking-detail', onSubmit: createOrganizerActivity }, [h('h3', 'Nuovo ristorante'), h('input', { name: 'name', required: true, placeholder: 'Nome attività' }), h('textarea', { name: 'description', placeholder: 'Descrizione' }), h('select', { name: 'municipality_id', required: true }, [h('option', { value: '' }, 'Comune'), ...eventMunicipalities.value.map((municipality) => h('option', { value: municipality.id }, municipality.name))]), h('input', { name: 'address', placeholder: 'Indirizzo' }), h('button', { type: 'submit' }, 'Crea attività')]),
      ...organizerActivities.value.flatMap((activity: any) => [renderOrganizerActivity(activity), renderOrganizerActivityReservations(activity)]),
    ];

    const OrganizerLayout = () => h('main', { class: 'role-shell organizer-layout' }, [
      renderHeader('Dashboard organizzatore', organizerMenu),
      h('section', { class: 'role-content' }, [
        apiError.value ? h('p', { class: 'api-error' }, apiError.value) : null,
        ...(route.value === '/organizer/activities' ? renderOrganizerActivities()
          : route.value === '/organizer/bookings'
          ? renderOrganizerBookings()
          : route.value.startsWith('/organizer/bookings/')
            ? renderOrganizerBookingDetail()
            : route.value.startsWith('/organizer/events/') && route.value.endsWith('/participants')
              ? renderOrganizerParticipants()
            : route.value === '/organizer/events'
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
          h('tbody', rows.map((row, index) => h('tr', renderRow(row, index).map((cell, cellIndex) => cloneVNode(cell, { 'data-label': headers[cellIndex] ?? '' }))))),
        ]),
      ]),
    ]);

    const statusPill = (status: string) => {
      const labels: Record<string, string> = { draft: 'Bozza', pending: 'In revisione', pending_review: 'In revisione', published: 'Pubblicato', sold_out: 'Esaurito', cancelled: 'Annullato', completed: 'Concluso', rejected: 'Rifiutato', suspended: 'Sospeso', archived: 'Archiviato', active: 'Attivo', inactive: 'Non attivo', verified: 'Approvato', changes_requested: 'Integrazione richiesta', hidden: 'Nascosto', approved: 'Approvato' };
      return h('span', { class: `admin-pill status-${String(status).replaceAll('_', '-')}` }, labels[status] ?? status);
    };

    const renderAdminDashboard = () => [
      h('section', { class: 'admin-hero' }, [
        h('div', [
          h('span', 'Centro di controllo'),
          h('h1', 'Piattaforma eventi locali'),
          h('p', adminDashboard.value?.economics?.criteria ?? 'Indicatori calcolati sui dati reali della piattaforma.'),
        ]),
        h('div', { class: 'hero-signal' }, [
          h('strong', number(adminDashboard.value?.events?.future)),
          h('span', 'eventi in programma'),
        ]),
      ]),
      h('section', { class: 'admin-kpi-grid' }, [
        adminKpi('Organizer totali', adminDashboard.value?.organizers?.total, `${number(adminDashboard.value?.organizers?.approved)} approvati`, 'mint'),
        adminKpi('Organizer pending', adminDashboard.value?.organizers?.pending, `${number(adminDashboard.value?.organizers?.suspended)} sospesi`, 'alert'),
        adminKpi('Customer attivi', adminDashboard.value?.customers?.active, `${number(adminDashboard.value?.customers?.total)} totali`, 'blue'),
        adminKpi('Eventi pubblicati', adminDashboard.value?.events?.published, `${number(adminDashboard.value?.events?.draft)} bozze`, 'gold'),
        adminKpi('Prenotazioni valide', adminDashboard.value?.bookings?.valid, `${number(adminDashboard.value?.bookings?.cancelled)} annullate`, 'mint'),
        adminKpi('Partecipanti', adminDashboard.value?.participants, 'fatturabili sui piani PpB', 'blue'),
        adminKpi('Check-in', adminDashboard.value?.check_ins, 'registrati', 'gold'),
        adminKpi('Ricavi teorici', `€ ${(Number(adminDashboard.value?.economics?.theoretical_revenue_cents ?? 0) / 100).toFixed(2)}`, 'nessun incasso online', 'mint'),
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
      ['Titolo', 'Organizzatore', 'Categoria', 'Comune', 'Data', 'Stato', 'Prenotati', 'Partecipanti', 'Media', ''],
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
        h('td', h('button', { onClick: () => { void moderateAdminEvent(event); } }, event.status === 'suspended' ? 'Riattiva' : 'Sospendi')),
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

    const verificationLabels: Record<OrganizerVerificationStatus, string> = {
      incomplete: 'Incompleto', pending: 'In revisione', changes_requested: 'Integrazione richiesta',
      verified: 'Approvato', rejected: 'Rifiutato', suspended: 'Sospeso',
    };

    const bookingStatusLabel = (status: string) => ({ confirmed: 'Confermata', checked_in: 'Check-in', completed: 'Completata', cancelled: 'Annullata', cancelled_by_customer: 'Annullata dal cliente', cancelled_by_organizer: 'Annullata dall’organizzatore' }[status] ?? status);

    const renderOrganizerBookings = () => {
      const filters: Array<[OrganizerBookingFilter, string]> = [['all', 'Tutte'], ['confirmed', 'Confermate'], ['checked_in', 'Check-in'], ['completed', 'Completate'], ['cancelled', 'Annullate']];
      const controls = h('div', { class: 'booking-toolbar' }, [
        h('form', { onSubmit: (event: Event) => { event.preventDefault(); organizerBookingsPage.value = 1; void loadOrganizerBookings(); } }, [
          h('input', { value: organizerBookingsSearch.value, placeholder: 'Cerca cliente, email, evento o codice', 'aria-label': 'Ricerca prenotazioni', onInput: (event: Event) => { organizerBookingsSearch.value = (event.target as HTMLInputElement).value; } }),
          h('button', { type: 'submit', disabled: organizerBookingsLoading.value }, 'Cerca'),
        ]),
        h('select', { value: organizerBookingsSort.value, 'aria-label': 'Ordinamento', onChange: (event: Event) => { organizerBookingsSort.value = (event.target as HTMLSelectElement).value; organizerBookingsPage.value = 1; void loadOrganizerBookings(); } }, [
          h('option', { value: 'booked_at:desc' }, 'Più recenti'), h('option', { value: 'booked_at:asc' }, 'Meno recenti'),
          h('option', { value: 'event:asc' }, 'Evento A-Z'), h('option', { value: 'customer:asc' }, 'Cliente A-Z'), h('option', { value: 'people:desc' }, 'Più persone'),
        ]),
        h('div', { class: 'filter-strip organizer-event-filters' }, filters.map(([value, label]) => h('button', { class: organizerBookingsFilter.value === value ? 'active' : '', disabled: organizerBookingsLoading.value, onClick: () => { organizerBookingsFilter.value = value; organizerBookingsPage.value = 1; void loadOrganizerBookings(); } }, label))),
      ]);
      const heading = h('div', { class: 'section-title' }, [h('div', [h('h2', 'Prenotazioni'), h('span', 'Prenotazioni ricevute per i tuoi eventi')])]);
      if (organizerBookingsLoading.value) return [heading, controls, h('article', { class: 'wide-panel organizer-events-state' }, 'Caricamento prenotazioni…')];
      if (organizerBookingsError.value) return [heading, controls, h('article', { class: 'wide-panel dashboard-error' }, [h('p', organizerBookingsError.value), h('button', { onClick: () => { void loadOrganizerBookings(); } }, 'Riprova')])];
      if (!organizerBookings.value.length) return [heading, controls, h('article', { class: 'wide-panel empty-state' }, [h('strong', 'Nessuna prenotazione trovata'), h('p', 'Prova a modificare ricerca o filtri.')])];
      const table = h('div', { class: 'booking-table-wrap' }, h('table', { class: 'booking-table' }, [
        h('thead', h('tr', ['Evento', 'Cliente', 'Persone', 'Stato', 'Data'].map((label) => h('th', label)))),
        h('tbody', organizerBookings.value.map((booking) => h('tr', { tabindex: 0, onClick: () => { void go(`/organizer/bookings/${booking.ulid}`); }, onKeydown: (event: KeyboardEvent) => { if (event.key === 'Enter') void go(`/organizer/bookings/${booking.ulid}`); } }, [
          h('td', booking.event.title), h('td', [h('strong', booking.customer.name), h('small', booking.customer.email)]), h('td', String(booking.people)), h('td', h('span', { class: `status-badge status-${booking.status}` }, bookingStatusLabel(booking.status))), h('td', new Date(booking.booked_at).toLocaleString('it-IT')),
        ]))),
      ]));
      const meta = organizerBookingsMeta.value;
      return [heading, controls, table, meta ? h('nav', { class: 'organizer-events-pagination' }, [h('button', { disabled: meta.current_page <= 1, onClick: () => { organizerBookingsPage.value--; void loadOrganizerBookings(); } }, 'Precedente'), h('span', `Pagina ${meta.current_page} di ${meta.last_page} · ${meta.total} prenotazioni`), h('button', { disabled: meta.current_page >= meta.last_page, onClick: () => { organizerBookingsPage.value++; void loadOrganizerBookings(); } }, 'Successiva')]) : null];
    };

    const renderOrganizerBookingDetail = () => {
      const booking = selectedOrganizerBooking.value;
      if (organizerBookingsLoading.value) return [h('article', { class: 'wide-panel' }, 'Caricamento dettaglio…')];
      if (organizerBookingsError.value || !booking) return [h('article', { class: 'wide-panel dashboard-error' }, [h('p', organizerBookingsError.value || 'Prenotazione non disponibile.'), h('button', { onClick: () => { void loadOrganizerBookingDetail(route.value.split('/')[3]); } }, 'Riprova')])];
      return [
        h('div', { class: 'section-title' }, [h('div', [h('h2', `Prenotazione ${booking.booking_code}`), h('span', booking.event.title)]), h('button', { onClick: () => { void go('/organizer/bookings'); } }, 'Torna alle prenotazioni')]),
        h('article', { class: 'wide-panel booking-detail' }, [
          h('div', { class: 'booking-detail-grid' }, [
            h('div', [h('small', 'Cliente'), h('strong', booking.customer.name)]), h('div', [h('small', 'Email'), h('strong', booking.customer.email)]),
            h('div', [h('small', 'Telefono'), h('strong', booking.customer.phone ?? 'Non disponibile')]), h('div', [h('small', 'Persone'), h('strong', String(booking.people))]),
            h('div', [h('small', 'Ticket'), ...(booking.tickets ?? []).map((ticket) => h('strong', `${ticket.name} × ${ticket.quantity}`))]),
            h('div', [h('small', 'Data prenotazione'), h('strong', new Date(booking.booked_at).toLocaleString('it-IT'))]),
            h('div', [h('small', 'Stato'), h('strong', bookingStatusLabel(booking.status))]), h('div', [h('small', 'Check-in'), h('strong', booking.checked_in_at ? new Date(booking.checked_in_at).toLocaleString('it-IT') : 'Non effettuato')]),
            h('div', { class: 'booking-detail-wide' }, [h('small', 'Note'), h('p', booking.notes || 'Nessuna nota')]),
          ]),
          h('div', { class: 'action-row' }, [h('button', { disabled: !booking.qr_token, onClick: () => { organizerQrVisible.value = !organizerQrVisible.value; } }, organizerQrVisible.value ? 'Nascondi QR' : 'Visualizza QR'), h('button', { onClick: () => { void go(`/organizer/events/${booking.event.ulid}/edit`); } }, 'Apri Evento'), h('button', { onClick: () => { void go(`/organizer/events/${booking.event.ulid}/participants`); } }, 'Partecipanti e check-in')]),
          organizerQrVisible.value ? h('div', { class: 'booking-qr', role: 'img', 'aria-label': 'Token QR della prenotazione' }, booking.qr_token ?? 'QR non disponibile') : null,
        ]),
      ];
    };

    const renderOrganizerParticipants = () => {
      const eventUlid = route.value.split('/')[3]; const meta = organizerParticipantsMeta.value; const summary = organizerParticipantsSummary.value;
      const controls = h('div', { class: 'booking-toolbar' }, [
        h('form', { onSubmit: (event: Event) => { event.preventDefault(); organizerParticipantsPage.value = 1; void loadOrganizerParticipants(eventUlid); } }, [h('input', { value: organizerParticipantsSearch.value, placeholder: 'Nome, email o codice prenotazione', onInput: (event: Event) => { organizerParticipantsSearch.value = (event.target as HTMLInputElement).value; } }), h('button', { type: 'submit' }, 'Cerca')]),
        h('select', { value: organizerParticipantsFilter.value, onChange: (event: Event) => { organizerParticipantsFilter.value = (event.target as HTMLSelectElement).value; organizerParticipantsPage.value = 1; void loadOrganizerParticipants(eventUlid); } }, [h('option', { value: 'all' }, 'Tutti'), h('option', { value: 'pending' }, 'Da registrare'), h('option', { value: 'checked_in' }, 'Check-in effettuato')]),
        h('button', { onClick: () => { void exportParticipantsCsv(eventUlid); } }, 'Esporta CSV'), h('button', { onClick: () => { void go('/organizer/events'); } }, 'Torna agli eventi'),
      ]);
      const checkIn = h('article', { class: 'wide-panel booking-detail' }, [h('h3', 'Check-in'), h('p', 'Inserisci il contenuto del QR oppure il codice prenotazione.'), h('form', { class: 'booking-toolbar', onSubmit: (event: Event) => { event.preventDefault(); void submitCheckIn(eventUlid); } }, [h('select', { value: checkInMode.value, onChange: (event: Event) => { checkInMode.value = (event.target as HTMLSelectElement).value as 'qr_token' | 'booking_code'; } }, [h('option', { value: 'qr_token' }, 'Token QR'), h('option', { value: 'booking_code' }, 'Codice prenotazione')]), h('input', { value: checkInValue.value, placeholder: checkInMode.value === 'qr_token' ? 'Incolla il token QR' : 'Es. EVT-XXXXXXXX', onInput: (event: Event) => { checkInValue.value = (event.target as HTMLInputElement).value; } }), h('button', { disabled: checkInLoading.value || !checkInValue.value.trim(), type: 'submit' }, checkInLoading.value ? 'Verifica…' : 'Registra check-in')]), checkInMessage.value ? h('p', { role: 'status', class: checkInMessage.value.includes('correttamente') ? '' : 'api-error' }, checkInMessage.value) : null]);
      const heading = [h('div', { class: 'section-title' }, [h('h2', 'Partecipanti evento'), h('span', 'Ogni riga rappresenta una prenotazione e il relativo numero di persone')]), h('div', { class: 'metric-grid' }, [card('Prenotazioni', String(summary.bookings_total), 'valide'), card('Persone', String(summary.people_total), 'nelle prenotazioni valide'), card('Check-in', String(summary.checked_in_total), 'persone registrate')]), controls, checkIn];
      if (organizerParticipantsLoading.value) return [...heading, h('article', { class: 'wide-panel' }, 'Caricamento partecipanti…')];
      if (organizerParticipantsError.value) return [...heading, h('article', { class: 'wide-panel dashboard-error' }, [h('p', organizerParticipantsError.value), h('button', { onClick: () => { void loadOrganizerParticipants(eventUlid); } }, 'Riprova')])];
      if (!organizerParticipants.value.length) return [...heading, emptyState('Nessuna prenotazione valida trovata.')];
      return [...heading, h('div', { class: 'booking-table-wrap' }, h('table', { class: 'booking-table' }, [h('thead', h('tr', ['Codice', 'Cliente', 'Telefono', 'Biglietti', 'Persone', 'Stato', 'Check-in'].map((label) => h('th', label)))), h('tbody', organizerParticipants.value.map((participant) => h('tr', { tabindex: 0, onClick: () => { void go(`/organizer/bookings/${participant.booking_ulid}`); }, onKeydown: (event: KeyboardEvent) => { if (event.key === 'Enter') void go(`/organizer/bookings/${participant.booking_ulid}`); } }, [h('td', participant.booking_code), h('td', [h('strong', participant.name), h('small', participant.email)]), h('td', participant.phone), h('td', participant.tickets), h('td', String(participant.people)), h('td', bookingStatusLabel(participant.status)), h('td', participant.checked_in_at ? new Date(participant.checked_in_at).toLocaleString('it-IT') : 'Da effettuare')])))])), meta ? h('nav', { class: 'organizer-events-pagination' }, [h('button', { disabled: meta.current_page <= 1, onClick: () => { organizerParticipantsPage.value--; void loadOrganizerParticipants(eventUlid); } }, 'Precedente'), h('span', `Pagina ${meta.current_page} di ${meta.last_page}`), h('button', { disabled: meta.current_page >= meta.last_page, onClick: () => { organizerParticipantsPage.value++; void loadOrganizerParticipants(eventUlid); } }, 'Successiva')]) : null];
    };

    const renderAdminOrganizerDetail = () => {
      const organizer = selectedAdminOrganizer.value;
      if (!organizer) return null;
      const field = (label: string, value: unknown) => h('div', [h('small', label), h('strong', String(value ?? '-'))]);
      return h('section', { class: 'admin-panel organizer-request-detail' }, [
        h('div', { class: 'panel-heading' }, [h('h2', organizer.name), statusPill(verificationLabels[organizer.verification_status])]),
        h('div', { class: 'admin-detail-grid' }, [
          field('Referente', organizer.owner?.name), field('Email account', organizer.owner?.email),
          field('Partita IVA', organizer.vat_number), field('PEC', organizer.pec), field('Codice SDI', organizer.sdi_code),
          field('Indirizzo', `${organizer.address ?? ''} ${organizer.street_number ?? ''}, ${organizer.postal_code ?? ''}`),
          field('Comune', organizer.municipality?.name), field('Provincia', organizer.province), field('Nazione', organizer.country),
          field('Telefono', organizer.phone), field('Email pubblica', organizer.public_email), field('Sito web', organizer.website_url),
          field('Instagram', organizer.instagram_url), field('Facebook', organizer.facebook_url), field('TikTok', organizer.tiktok_url),
          field('Invio', organizer.submitted_at ? new Date(organizer.submitted_at).toLocaleString('it-IT') : '-'),
          field('Revisione', organizer.reviewed_at ? new Date(organizer.reviewed_at).toLocaleString('it-IT') : '-'),
        ]),
        organizer.description ? h('p', organizer.description) : null,
        organizer.review_notes ? h('div', { class: 'review-reason' }, [h('strong', 'Motivazione'), h('p', organizer.review_notes)]) : null,
        h('form', { class: 'booking-detail', onSubmit: (event: Event) => { void assignAdminPlan(event, organizer); } }, [
          h('h3', 'Accordo commerciale'),
          h('select', { name: 'plan_ulid', required: true }, [
            h('option', { value: '' }, 'Seleziona piano'),
            ...adminPlans.value.map((plan) => h('option', { value: plan.ulid, selected: organizer.subscription?.plan?.ulid === plan.ulid }, plan.name)),
          ]),
          h('select', { name: 'status', required: true }, ['pending', 'active', 'suspended', 'expired', 'cancelled'].map((status) => h('option', { value: status, selected: organizer.subscription?.status === status }, status))),
          h('label', ['Inizio accordo', h('input', { name: 'agreement_starts_at', type: 'date', value: organizer.subscription?.agreement_starts_at?.slice(0, 10) ?? '' })]),
          h('label', ['Fine accordo', h('input', { name: 'agreement_ends_at', type: 'date', value: organizer.subscription?.agreement_ends_at?.slice(0, 10) ?? '' })]),
          h('textarea', { name: 'agreement_notes', maxlength: 2000, placeholder: 'Note accordo offline', value: organizer.subscription?.agreement_notes ?? '' }),
          h('label', ['Costo personalizzato per partecipante (€)', h('input', { name: 'booking_fee_euro', type: 'number', min: 0, step: '0.01', value: ((organizer.subscription?.booking_fee_cents ?? 0) / 100).toFixed(2) })]),
          h('button', { type: 'submit' }, 'Salva accordo'),
        ]),
        organizer.verification_status === 'pending' ? h('div', { class: 'onboarding-actions' }, [
          h('button', { disabled: adminOrganizerActionLoading.value, onClick: () => { void reviewAdminOrganizer('approve'); } }, 'Approva'),
          h('button', { disabled: adminOrganizerActionLoading.value, onClick: () => { void reviewAdminOrganizer('request-changes'); } }, 'Richiedi integrazione'),
          h('button', { disabled: adminOrganizerActionLoading.value, onClick: () => { void reviewAdminOrganizer('reject'); } }, 'Rifiuta'),
        ]) : organizer.verification_status === 'verified' ? h('button', { disabled: adminOrganizerActionLoading.value, onClick: () => { void reviewAdminOrganizer('suspend'); } }, 'Sospendi Organizer') : organizer.verification_status === 'suspended' ? h('button', { disabled: adminOrganizerActionLoading.value, onClick: () => { void reviewAdminOrganizer('reactivate'); } }, 'Riattiva Organizer') : null,
      ]);
    };

    const renderAdminOrganizers = () => [
      h('div', { class: 'page-heading' }, [h('h1', 'Richieste Organizer'), h('p', 'Esamina i dati fiscali e gestisci l’abilitazione alle funzioni operative.')]),
      h('div', { class: 'filter-strip' }, [
        h('form', { onSubmit: (event: Event) => { event.preventDefault(); adminOrganizerPage.value = 1; void loadAdminOrganizerRequests(); } }, [h('input', { value: adminOrganizerSearch.value, placeholder: 'Cerca nome o email', onInput: (event: Event) => { adminOrganizerSearch.value = (event.target as HTMLInputElement).value; } }), h('button', { type: 'submit' }, 'Cerca')]),
        h('label', [h('span', 'Stato '), h('select', {
          value: adminOrganizerFilter.value,
          disabled: adminOrganizerLoading.value,
          onChange: (event: Event) => {
            adminOrganizerFilter.value = (event.target as HTMLSelectElement).value as OrganizerVerificationStatus | '';
            adminOrganizerPage.value = 1; selectedAdminOrganizer.value = null; void loadAdminOrganizerRequests();
          },
        }, [h('option', { value: '' }, 'Tutti'), ...(Object.entries(verificationLabels).map(([value, label]) => h('option', { value }, label)))]),
        ]),
        h('strong', `${adminOrganizerPendingCount.value} in revisione`),
      ]),
      adminOrganizerLoading.value ? h('article', { class: 'admin-panel' }, 'Caricamento richieste…') : null,
      adminOrganizerError.value ? h('article', { class: 'admin-panel dashboard-error' }, [h('p', adminOrganizerError.value), h('button', { onClick: () => { void loadAdminOrganizerRequests(); } }, 'Riprova')]) : null,
      !adminOrganizerLoading.value && !adminOrganizerError.value ? adminTable(
        ['Denominazione', 'Referente', 'Email account', 'Partita IVA', 'PEC', 'Codice SDI', 'Stato', 'Invio', ''],
        adminOrganizers.value,
        (organizer: OrganizerProfile) => [
          h('td', h('strong', organizer.name)), h('td', organizer.owner?.name ?? '-'), h('td', organizer.owner?.email ?? '-'),
          h('td', organizer.vat_number ?? '-'), h('td', organizer.pec ?? '-'), h('td', organizer.sdi_code ?? '-'),
          h('td', statusPill(verificationLabels[organizer.verification_status])),
          h('td', organizer.submitted_at ? new Date(organizer.submitted_at).toLocaleDateString('it-IT') : '-'),
          h('td', h('button', { onClick: () => { void selectAdminOrganizer(organizer); } }, 'Dettaglio')),
        ],
      ) : null,
      adminOrganizerMeta.value && adminOrganizerMeta.value.last_page > 1 ? h('div', { class: 'organizer-events-pagination' }, [
        h('button', { disabled: adminOrganizerPage.value <= 1, onClick: () => { adminOrganizerPage.value -= 1; void loadAdminOrganizerRequests(); } }, 'Precedente'),
        h('span', `Pagina ${adminOrganizerPage.value} di ${adminOrganizerMeta.value.last_page}`),
        h('button', { disabled: adminOrganizerPage.value >= adminOrganizerMeta.value.last_page, onClick: () => { adminOrganizerPage.value += 1; void loadAdminOrganizerRequests(); } }, 'Successiva'),
      ]) : null,
      renderAdminOrganizerDetail(),
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
      h('div', { class: 'page-heading' }, [h('h1', 'Abbonamenti'), h('p', 'Catalogo commerciale e accordi assegnati agli Organizer.')]),
      h('section', { class: 'subscription-section' }, [
        h('div', { class: 'panel-heading outside' }, [h('h2', 'Catalogo piani'), h('span', 'Importi visualizzati in euro e salvati in centesimi')]),
        h('div', { class: 'subscription-plan-grid' }, [...adminPlans.value, { name: '', description: '', billing_model: 'unlimited', billing_interval: 'monthly', monthly_price_cents: 0, booking_fee_cents: 0, is_active: true }].map((plan) => h('form', { class: 'admin-panel subscription-plan-form', onSubmit: (event: Event) => { event.preventDefault(); void saveAdminPlan(plan); } }, [
          h('label', [h('span', 'Nome piano'), h('input', { value: plan.name, placeholder: 'Nome piano', onInput: (event: Event) => { plan.name = (event.target as HTMLInputElement).value; } })]),
          h('label', { class: 'full-row' }, [h('span', 'Descrizione'), h('textarea', { value: plan.description ?? '', placeholder: 'Descrizione', onInput: (event: Event) => { plan.description = (event.target as HTMLTextAreaElement).value; } })]),
          h('label', [h('span', 'Modello'), h('select', { value: plan.billing_model, onChange: (event: Event) => { plan.billing_model = (event.target as HTMLSelectElement).value; } }, [h('option', { value: 'unlimited' }, 'UNLIMITED'), h('option', { value: 'pay_per_booking' }, 'PAY PER BOOKING')])]),
          h('label', [h('span', 'Periodicità'), h('select', { value: plan.billing_interval ?? 'monthly', onChange: (event: Event) => { plan.billing_interval = (event.target as HTMLSelectElement).value; } }, [h('option', { value: 'monthly' }, 'Mensile'), h('option', { value: 'yearly' }, 'Annuale')])]),
          h('label', [h('span', 'Canone (€)'), h('input', { type: 'number', min: 0, step: '0.01', value: (Number(plan.monthly_price_cents || 0) / 100).toFixed(2), onInput: (event: Event) => { plan.monthly_price_cents = Math.round(Number((event.target as HTMLInputElement).value || 0) * 100); } })]),
          h('label', [h('span', 'Tariffa per prenotazione (€)'), h('input', { type: 'number', min: 0, step: '0.01', disabled: plan.billing_model !== 'pay_per_booking', value: (Number(plan.booking_fee_cents || 0) / 100).toFixed(2), onInput: (event: Event) => { plan.booking_fee_cents = Math.round(Number((event.target as HTMLInputElement).value || 0) * 100); } })]),
          h('label', { class: 'full-row checkbox-row' }, [h('input', { type: 'checkbox', checked: plan.is_active, onChange: (event: Event) => { plan.is_active = (event.target as HTMLInputElement).checked; } }), ' Piano attivo']),
          h('button', { class: 'full-row', type: 'submit', disabled: !plan.name }, plan.ulid ? 'Aggiorna piano' : 'Crea piano'),
        ]))),
      ]),
      h('section', { class: 'subscription-section' }, [
        h('div', { class: 'panel-heading outside' }, [h('h2', 'Accordi Organizer'), h('span', 'Assegnazione e modifica dal dettaglio Organizer')]),
        adminTable(['Organizzatore', 'Piano', 'Scadenza accordo', 'Stato'], overview().subscriptions ?? [], (subscription) => [
          h('td', h('strong', subscription.organizer ?? '-')),
          h('td', subscription.plan ?? '-'),
          h('td', subscription.renewal ?? '-'),
          h('td', statusPill(subscription.status ?? 'n/d')),
        ]),
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
        h('div', { class: 'admin-brand' }, [h('img', { class: 'admin-brand-logo', src: '/branding/movi-icon.png', alt: '' }), h('span', 'MòVi')]),
        h('nav', adminMenu.map(([label, target]) => h('button', {
          class: route.value === target ? 'active' : '',
          type: 'button',
          onClick: () => { void go(target); },
        }, [h('i', adminIcon(label)), h('span', label === 'Organizzatori' ? `Richieste Organizer (${adminOrganizerPendingCount.value})` : label)]))),
        h('button', { class: 'admin-logout', type: 'button', onClick: logout }, 'Esci'),
      ]),
      h('section', { class: 'admin-main' }, [
        h('header', { class: 'admin-topbar' }, [
          h('div', [h('span', currentUser.value?.email ?? ''), h('strong', 'Dashboard amministratore')]),
          h('form', { onSubmit: (event: Event) => { event.preventDefault(); void runAdminSearch(); } }, [h('input', { value: adminSearchQuery.value, placeholder: 'Cerca Organizer, Customer, Eventi', onInput: (event: Event) => { adminSearchQuery.value = (event.target as HTMLInputElement).value; } }), h('button', { type: 'submit' }, 'Cerca')]),
          h('button', { type: 'button', onClick: () => { void loadAdminData(); } }, loading.value ? 'Aggiorno...' : 'Aggiorna dati'),
        ]),
        adminSearchResults.value ? h('section', { class: 'admin-panel' }, [h('h3', 'Risultati ricerca'), h('p', `${adminSearchResults.value.organizers.length} Organizer · ${adminSearchResults.value.customers.length} Customer · ${adminSearchResults.value.events.length} Eventi`), ...['organizers', 'customers', 'events'].flatMap((group) => adminSearchResults.value[group].map((item: any) => h('button', { type: 'button', onClick: () => { if (group === 'organizers') { void go('/admin/organizers'); void selectAdminOrganizer(item); } else void go(group === 'customers' ? '/admin/customers' : '/admin/events'); } }, [h('strong', item.name ?? item.title), h('small', item.email ?? item.status ?? '')])))]) : null,
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
      if (role.value === 'organizer' && route.value === '/organizer/onboarding') return renderOrganizerOnboarding();
      if (role.value === 'organizer' && route.value === '/organizer/profile') return renderOrganizerOnboarding();
      if (role.value === 'organizer' && route.value === '/organizer/pending') return renderOrganizerPending();
      if (role.value === 'organizer' && route.value === '/organizer/rejected') return renderOrganizerBlocked();
      if (role.value === 'organizer' && route.value === '/organizer/suspended') return renderOrganizerBlocked(true);
      if (role.value === 'organizer') return OrganizerLayout();
      if (role.value === 'admin') return AdminLayout();

      return renderLogin();
    };
  },
});
