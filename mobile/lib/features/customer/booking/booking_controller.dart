import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/auth_controller.dart';
import '../data/customer_api_models.dart';
import 'booking_models.dart';
import 'booking_repository.dart';

final bookingRepositoryProvider = Provider<BookingRepository>((ref) =>
    BookingRepository(ref.watch(apiClientProvider),
        ref.watch(authenticationRepositoryProvider)));

final customerBookingsProvider = FutureProvider<List<CustomerBooking>>(
    (ref) => ref.watch(bookingRepositoryProvider).getBookings());
final customerBookingDetailProvider =
    FutureProvider.family<CustomerBooking, String>(
        (ref, ulid) => ref.watch(bookingRepositoryProvider).getBooking(ulid));
final customerBookingQrProvider = FutureProvider.family<BookingQr, String>(
    (ref, ulid) => ref.watch(bookingRepositoryProvider).getBookingQr(ulid));

class BookingCancellationController extends StateNotifier<AsyncValue<void>> {
  BookingCancellationController(this._repository, this.bookingUlid,
      {void Function()? onCancelled})
      : _onCancelled = onCancelled,
        super(const AsyncData(null));
  final BookingRepository _repository;
  final String bookingUlid;
  final void Function()? _onCancelled;

  Future<bool> cancel() async {
    if (state.isLoading) return false;
    state = const AsyncLoading();
    try {
      await _repository.cancelBooking(bookingUlid);
      state = const AsyncData(null);
      _onCancelled?.call();
      return true;
    } on BookingApiException catch (error, stack) {
      state = AsyncError(error, stack);
      return false;
    } catch (error, stack) {
      state = AsyncError(
          const BookingApiException(
              'Impossibile annullare la prenotazione. Riprova.', 500),
          stack);
      return false;
    }
  }
}

final bookingCancellationProvider = StateNotifierProvider.autoDispose
    .family<BookingCancellationController, AsyncValue<void>, String>(
        (ref, ulid) => BookingCancellationController(
                ref.watch(bookingRepositoryProvider), ulid, onCancelled: () {
              ref.invalidate(customerBookingsProvider);
              ref.invalidate(customerBookingDetailProvider(ulid));
            }));

class BookingFlowState {
  const BookingFlowState({
    this.quantities = const {},
    this.adults = 1,
    this.children = 0,
    this.childrenAgeRanges = const [],
    this.dietaryNeeds = '',
    this.accessibilityNeeds = '',
    this.notes = '',
    this.isSubmitting = false,
    this.booking,
    this.qr,
    this.error,
    this.fieldErrors = const {},
  });
  final Map<int, int> quantities;
  final int adults;
  final int children;
  final List<String> childrenAgeRanges;
  final String dietaryNeeds;
  final String accessibilityNeeds;
  final String notes;
  final bool isSubmitting;
  final CustomerBooking? booking;
  final BookingQr? qr;
  final String? error;
  final Map<String, List<String>> fieldErrors;

  int get selectedTickets =>
      quantities.values.fold(0, (sum, value) => sum + value);
  int estimateTotal(List<TicketType> tickets) => tickets.fold(0,
      (sum, ticket) => sum + ticket.priceCents * (quantities[ticket.id] ?? 0));

  BookingFlowState copyWith({
    Map<int, int>? quantities,
    int? adults,
    int? children,
    List<String>? childrenAgeRanges,
    String? dietaryNeeds,
    String? accessibilityNeeds,
    String? notes,
    bool? isSubmitting,
    CustomerBooking? booking,
    BookingQr? qr,
    String? error,
    bool clearError = false,
    Map<String, List<String>>? fieldErrors,
  }) =>
      BookingFlowState(
        quantities: quantities ?? this.quantities,
        adults: adults ?? this.adults,
        children: children ?? this.children,
        childrenAgeRanges: childrenAgeRanges ?? this.childrenAgeRanges,
        dietaryNeeds: dietaryNeeds ?? this.dietaryNeeds,
        accessibilityNeeds: accessibilityNeeds ?? this.accessibilityNeeds,
        notes: notes ?? this.notes,
        isSubmitting: isSubmitting ?? this.isSubmitting,
        booking: booking ?? this.booking,
        qr: qr ?? this.qr,
        error: clearError ? null : error ?? this.error,
        fieldErrors: fieldErrors ?? this.fieldErrors,
      );
}

class BookingFlowController extends StateNotifier<BookingFlowState> {
  BookingFlowController(this._repository, this.eventUlid,
      {void Function()? onBookingCreated})
      : _idempotencyKey = _repository.newIdempotencyKey(),
        _onBookingCreated = onBookingCreated,
        super(const BookingFlowState());
  final BookingRepository _repository;
  final String eventUlid;
  final void Function()? _onBookingCreated;
  final String _idempotencyKey;
  String get idempotencyKey => _idempotencyKey;

  void setQuantity(TicketType ticket, int quantity,
      {required int eventAvailable}) {
    final maximum = ticket.availableQuantity;
    final otherTotal = state.quantities.entries
        .where((entry) => entry.key != ticket.id)
        .fold(0, (sum, entry) => sum + entry.value);
    final eventRemaining = (eventAvailable - otherTotal).clamp(0, 50);
    final effectiveMaximum =
        [maximum ?? 50, eventRemaining, 50].reduce((a, b) => a < b ? a : b);
    final safe = quantity.clamp(0, effectiveMaximum).toInt();
    final total = otherTotal + safe;
    final adults = state.adults.clamp(0, total);
    state = state.copyWith(
      quantities: {...state.quantities, ticket.id: safe},
      adults: adults,
      children: state.children.clamp(0, total - adults),
      clearError: true,
      fieldErrors: const {},
    );
  }

  void setAdults(int value) {
    final maximum = (state.selectedTickets - state.children).clamp(0, 50);
    state = state.copyWith(adults: value.clamp(0, maximum));
  }

  void setChildren(int value) {
    final maximum = (state.selectedTickets - state.adults).clamp(0, 50);
    final count = value.clamp(0, maximum);
    final ages = [...state.childrenAgeRanges];
    while (ages.length < count) {
      ages.add('');
    }
    if (ages.length > count) ages.removeRange(count, ages.length);
    state = state.copyWith(children: count, childrenAgeRanges: ages);
  }

  void setChildAge(int index, String value) {
    final ages = [...state.childrenAgeRanges];
    if (index < ages.length) ages[index] = value;
    state = state.copyWith(childrenAgeRanges: ages);
  }

  void setDietaryNeeds(String value) =>
      state = state.copyWith(dietaryNeeds: value);
  void setAccessibilityNeeds(String value) =>
      state = state.copyWith(accessibilityNeeds: value);
  void setNotes(String value) => state = state.copyWith(notes: value);

  String? validate(List<TicketType> tickets) {
    if (state.selectedTickets < 1) return 'Seleziona almeno un biglietto.';
    if (state.selectedTickets != state.adults + state.children) {
      return 'La somma dei biglietti deve coincidere con adulti e bambini.';
    }
    for (final ticket in tickets) {
      final quantity = state.quantities[ticket.id] ?? 0;
      if (quantity > 0 &&
          (!ticket.isAvailable ||
              (ticket.availableQuantity != null &&
                  quantity > ticket.availableQuantity!))) {
        return 'La quantità selezionata per ${ticket.name} non è disponibile.';
      }
    }
    return null;
  }

  Future<void> submit(List<TicketType> tickets) async {
    if (state.isSubmitting || state.booking != null) return;
    final validation = validate(tickets);
    if (validation != null) {
      state = state.copyWith(error: validation, fieldErrors: const {});
      return;
    }
    state = state
        .copyWith(isSubmitting: true, clearError: true, fieldErrors: const {});
    try {
      final request = CreateBookingRequest(
        adultsCount: state.adults,
        childrenCount: state.children,
        childrenAgeRanges: state.childrenAgeRanges
            .where((age) => age.trim().isNotEmpty)
            .toList(),
        dietaryNeeds: state.dietaryNeeds,
        accessibilityNeeds: state.accessibilityNeeds,
        notes: state.notes,
        items: state.quantities.entries
            .where((entry) => entry.value > 0)
            .map((entry) => BookingItemRequest(
                ticketTypeId: entry.key, quantity: entry.value))
            .toList(),
      );
      final booking =
          await _repository.createBooking(eventUlid, request, _idempotencyKey);
      state = state.copyWith(
          booking: booking, isSubmitting: false, clearError: true);
      _onBookingCreated?.call();
      await recoverQr();
    } on BookingApiException catch (error) {
      state = state.copyWith(
          isSubmitting: false, error: error.message, fieldErrors: error.errors);
    } catch (_) {
      state = state.copyWith(
          isSubmitting: false,
          error: 'Impossibile completare la prenotazione. Riprova.');
    }
  }

  Future<void> recoverQr() async {
    final booking = state.booking;
    if (booking == null) return;
    if (booking.qrToken != null) {
      state = state.copyWith(
          qr: BookingQr(bookingUlid: booking.ulid, qrToken: booking.qrToken!));
    }
    try {
      final qr = await _repository.getBookingQr(booking.ulid);
      state = state.copyWith(qr: qr, clearError: true);
    } on BookingApiException catch (error) {
      if (state.qr == null) state = state.copyWith(error: error.message);
    }
  }
}

final bookingFlowProvider = StateNotifierProvider.autoDispose
    .family<BookingFlowController, BookingFlowState, String>((ref, eventUlid) =>
        BookingFlowController(ref.watch(bookingRepositoryProvider), eventUlid,
            onBookingCreated: () => ref.invalidate(customerBookingsProvider)));
