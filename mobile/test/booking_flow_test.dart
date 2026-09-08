import 'dart:async';

import 'package:eventi_locali_mobile/core/auth/authentication_repository.dart';
import 'package:eventi_locali_mobile/core/network/api_client.dart';
import 'package:eventi_locali_mobile/features/customer/booking/booking_controller.dart';
import 'package:eventi_locali_mobile/features/customer/booking/booking_flow_screen.dart';
import 'package:eventi_locali_mobile/features/customer/booking/booking_list_screen.dart';
import 'package:eventi_locali_mobile/features/customer/booking/booking_models.dart';
import 'package:eventi_locali_mobile/features/customer/booking/booking_repository.dart';
import 'package:eventi_locali_mobile/features/customer/data/customer_api_models.dart';
import 'package:eventi_locali_mobile/features/customer/data/customer_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('parses nullable ticket fields and numeric JSON safely', () {
    final ticket = TicketType.fromJson({
      'id': '7',
      'name': 'Ingresso',
      'description': null,
      'price_cents': '1250',
      'max_quantity': null,
      'booked_quantity': '2',
      'is_active': true,
    });
    expect(ticket.id, 7);
    expect(ticket.description, '');
    expect(ticket.priceCents, 1250);
    expect(ticket.availableQuantity, isNull);
    expect(ticket.isAvailable, isTrue);
  });

  test('parses booking, items and QR without internal fields', () {
    final booking = CustomerBooking.fromJson({
      'ulid': '01J00000000000000000000000',
      'booking_code': 'EVT-TEST',
      'status': 'confirmed',
      'total_people': '2',
      'total_price_cents': 2500,
      'qr_token': 'secure-token',
      'event': {'title': 'Evento reale'},
      'items': [
        {
          'quantity': 2,
          'unit_price_cents': 1250,
          'total_price_cents': 2500,
          'ticket_type': {'name': 'Ingresso'}
        }
      ]
    });
    final qr = BookingQr.fromJson({
      'booking_ulid': booking.ulid,
      'qr_token': 'secure-token',
    });
    expect(booking.items.single.ticketName, 'Ingresso');
    expect(booking.totalPeople, 2);
    expect(qr.bookingUlid, booking.ulid);
    expect(qr.qrToken, 'secure-token');
  });

  test('booking edit restores current places before applying ticket limit', () {
    final booking = CustomerBooking.fromJson({
      'ulid': 'booking-edit',
      'booking_code': 'EDIT-1',
      'status': 'confirmed',
      'total_people': 2,
      'event': {
        'title': 'Evento',
        'capacity_total': 35,
        'available_total': 25,
      },
      'items': [
        {
          'quantity': 2,
          'ticket_type': {
            'ulid': 'ticket-edit',
            'name': 'Ingresso',
            'max_quantity': 18,
            'booked_quantity': 10,
          },
        }
      ],
    });
    final item = booking.items.single;
    expect(editableTicketMaximum(booking, item, {'ticket-edit': 2}), 10);
  });
  test('controller validates ticket sum and maximum quantity', () {
    final repository = _FakeBookingRepository();
    final controller = _bookingController(repository, 'event-ulid');
    final ticket = _ticket(maximum: 2);
    expect(controller.validate([ticket]), 'Seleziona almeno un biglietto.');
    controller.setQuantity(ticket, 2, eventAvailable: 20);
    expect(controller.validate([ticket]),
        'La somma dei biglietti deve coincidere con adulti e bambini.');
    controller.setAdults(2);
    expect(controller.validate([ticket]), isNull);
    controller.setQuantity(ticket, 8, eventAvailable: 20);
    expect(controller.state.quantities[ticket.id], 2);
  });

  test(
      'ticket limit uses ticket and event availability without hardcoded three',
      () {
    final controller = _bookingController(_FakeBookingRepository(), 'event');
    final ticket = _ticket(maximum: 12);
    controller.setQuantity(ticket, 8, eventAvailable: 9);
    expect(controller.state.quantities[ticket.id], 8);
    controller.setQuantity(ticket, 20, eventAvailable: 9);
    expect(controller.state.quantities[ticket.id], 9);
  });

  test('adults and children cannot exceed selected tickets', () {
    final controller = _bookingController(_FakeBookingRepository(), 'event');
    final ticket = _ticket(maximum: 10);
    controller.setQuantity(ticket, 4, eventAvailable: 10);
    controller.setAdults(3);
    controller.setChildren(9);
    expect(controller.state.adults, 3);
    expect(controller.state.children, 1);
    controller.setAdults(9);
    expect(controller.state.adults, 3);
  });

  test('checkout maps real ticket ULID, phone, people and notes', () async {
    final repository = _FakeBookingRepository();
    final controller = _bookingController(repository, 'event-checkout');
    final ticket = _ticket(maximum: 6);
    controller.setContactPhone('+393331234567');
    controller.setQuantity(ticket, 3, eventAvailable: 6);
    controller.setAdults(2);
    controller.setChildren(1);
    controller.setNotes('Arriveremo dieci minuti prima');

    await controller.submit([ticket]);

    final payload = repository.lastRequest!.toJson();
    expect(payload['contact_phone'], '+393331234567');
    expect(payload['phone'], '+393331234567');
    expect(payload['adults_count'], 2);
    expect(payload['children_count'], 1);
    expect(payload['notes'], 'Arriveremo dieci minuti prima');
    expect(payload['items'], [
      {'ticket_type_ulid': ticket.ulid, 'quantity': 3}
    ]);
    expect(controller.state.qr?.qrToken, 'secure-token');
  });

  test('phone and notes survive a controlled checkout error', () async {
    final repository = _FakeBookingRepository(errorStatus: 422);
    final controller = _bookingController(repository, 'event-error');
    final ticket = _ticket();
    controller.setContactPhone('+393339998887');
    controller.setNotes('Nota da preservare');
    controller.setQuantity(ticket, 1, eventAvailable: 5);

    await controller.submit([ticket]);

    expect(controller.state.contactPhone, '+393339998887');
    expect(controller.state.notes, 'Nota da preservare');
    expect(controller.state.error, 'Controlla i dati evidenziati e riprova.');
    expect(controller.state.fieldErrors['tickets'],
        ['Seleziona nuovamente i biglietti disponibili.']);
    expect(controller.state.fieldErrors.keys, isNot(contains('items')));
  });
  test('free event estimate is zero', () {
    final controller = _bookingController(_FakeBookingRepository(), 'event');
    final free = _ticket(price: 0);
    controller.setQuantity(free, 1, eventAvailable: 20);
    expect(controller.state.estimateTotal([free]), 0);
  });

  test('same idempotency key is reused and double submit sends one request',
      () async {
    final repository = _FakeBookingRepository(delay: true);
    final controller = _bookingController(repository, 'event-ulid');
    final ticket = _ticket();
    controller.setQuantity(ticket, 1, eventAvailable: 20);
    final key = controller.idempotencyKey;
    final first = controller.submit([ticket]);
    final second = controller.submit([ticket]);
    expect(controller.state.isSubmitting, isTrue);
    expect(repository.createCalls, 1);
    repository.complete();
    await Future.wait([first, second]);
    expect(repository.keys, [key]);
    expect(controller.state.booking?.ulid, 'booking-event-ulid');
    expect(controller.state.qr?.qrToken, 'secure-token');
  });

  test('two event controllers keep separate state and post correct ULIDs',
      () async {
    final repository = _FakeBookingRepository();
    final first = _bookingController(repository, 'event-one');
    final second = _bookingController(repository, 'event-two');
    final ticket = _ticket();
    first.setQuantity(ticket, 1, eventAvailable: 5);
    second.setQuantity(ticket, 2, eventAvailable: 5);
    second.setAdults(2);
    await first.submit([ticket]);
    await second.submit([ticket]);
    expect(repository.eventIds, ['event-one', 'event-two']);
    expect(first.state.quantities[ticket.id], 1);
    expect(second.state.quantities[ticket.id], 2);
    expect(first.state.booking?.eventTitle, 'Server event-one');
    expect(second.state.booking?.eventTitle, 'Server event-two');
  });

  test('successful creation invalidates bookings list', () async {
    var invalidations = 0;
    final controller = _bookingController(_FakeBookingRepository(), 'event',
        onBookingCreated: () => invalidations++);
    final ticket = _ticket();
    controller.setQuantity(ticket, 1, eventAvailable: 5);
    await controller.submit([ticket]);
    expect(invalidations, 1);
  });

  test('successful cancellation sends once and invalidates list and detail',
      () async {
    var invalidations = 0;
    final repository = _FakeBookingRepository();
    repository.currentBooking = _serverBooking('cancel-unit');
    final controller = BookingCancellationController(
        repository, 'booking-cancel-unit',
        onCancelled: () => invalidations++);
    expect(await controller.cancel(), isTrue);
    expect(repository.cancelCalls, 1);
    expect(invalidations, 1);
  });

  test('free confirmed booking is upcoming and tabs classify all states', () {
    final free = _serverBooking('free', price: 0, status: 'confirmed');
    final completed = _serverBooking('past', status: 'completed');
    final cancelled =
        _serverBooking('cancelled', status: 'cancelled_by_customer');
    expect(free.totalPriceCents, 0);
    expect(classifyBooking(free), BookingGroup.upcoming);
    expect(classifyBooking(completed), BookingGroup.completed);
    expect(classifyBooking(cancelled), BookingGroup.cancelled);
  });

  test('confirmed booking stays cancellable until the event ends', () {
    final inProgress = _serverBooking(
      'in-progress',
      startsAt: DateTime.now().subtract(const Duration(hours: 1)),
      endsAt: DateTime.now().add(const Duration(hours: 1)),
    );
    expect(inProgress.canCancel, isTrue);
    expect(classifyBooking(inProgress), BookingGroup.upcoming);
  });

  testWidgets('real bookings tab includes free booking and no demo data',
      (tester) async {
    final free = _serverBooking('free', price: 0, status: 'confirmed');
    await tester.pumpWidget(ProviderScope(overrides: [
      customerBookingsProvider.overrideWith((ref) async => [free]),
    ], child: const MaterialApp(home: CustomerBookingsScreen())));
    await tester.pumpAndSettle();
    expect(find.text('Server free'), findsOneWidget);
    expect(find.textContaining('Gratis'), findsOneWidget);
    expect(find.textContaining('demo'), findsNothing);
  });

  testWidgets('ticket with nineteen available reaches four in the UI',
      (tester) async {
    await _pumpBookingFlow(tester, _eventWithAvailability(maximum: 19));
    final plus = find.byKey(const Key('ticket-1-plus'));
    for (var count = 0; count < 4; count++) {
      await tester.tap(plus);
      await tester.pump();
    }
    expect(tester.widget<Text>(find.byKey(const Key('ticket-1-quantity'))).data,
        '4');
  });

  testWidgets('ticket with three available stops at three and explains limit',
      (tester) async {
    await _pumpBookingFlow(
        tester, _eventWithAvailability(maximum: 4, booked: 1));
    final plus = find.byKey(const Key('ticket-1-plus'));
    for (var count = 0; count < 4; count++) {
      await tester.tap(plus);
      await tester.pump();
    }
    expect(find.text('3 biglietti disponibili'), findsOneWidget);
    expect(tester.widget<Text>(find.byKey(const Key('ticket-1-quantity'))).data,
        '3');
    expect(tester.widget<IconButton>(plus).onPressed, isNull);
  });

  testWidgets('event 25 and ticket 8 expose context and cap purchases at 8',
      (tester) async {
    await _pumpBookingFlow(
      tester,
      _eventWithAvailability(
        maximum: 18,
        booked: 10,
        eventAvailable: 25,
        capacity: 35,
        price: 3500,
      ),
    );
    expect(find.text('8 posti prenotabili su 35'), findsOneWidget);
    expect(find.text('€35,00'), findsOneWidget);
    expect(find.text('8 biglietti disponibili'), findsOneWidget);
    expect(
      find.text('Disponibilità limitata per questa tipologia di biglietto.'),
      findsOneWidget,
    );
    final plus = find.byKey(const Key('ticket-1-plus'));
    for (var count = 0; count < 9; count++) {
      await tester.tap(plus);
      await tester.pump();
    }
    expect(tester.widget<Text>(find.byKey(const Key('ticket-1-quantity'))).data,
        '8');
    expect(tester.widget<IconButton>(plus).onPressed, isNull);
  });

  testWidgets('ticket sold out and event sold out disable purchases',
      (tester) async {
    await _pumpBookingFlow(
      tester,
      _eventWithAvailability(maximum: 10, booked: 10, eventAvailable: 25),
    );
    expect(find.text('0 biglietti disponibili'), findsOneWidget);
    expect(find.text('Tipologia esaurita'), findsOneWidget);
    expect(
        tester
            .widget<IconButton>(find.byKey(const Key('ticket-1-plus')))
            .onPressed,
        isNull);
  });

  testWidgets('event sold out disables booking confirmation', (tester) async {
    await _pumpBookingFlow(
      tester,
      _eventWithAvailability(maximum: 10, eventAvailable: 0),
    );
    await tester.drag(find.byType(ListView).last, const Offset(0, -1200));
    await tester.pumpAndSettle();
    expect(find.text('Evento esaurito'), findsOneWidget);
  });
  test('multiple ticket types share the event availability ceiling', () {
    final controller = _bookingController(_FakeBookingRepository(), 'event');
    final adult = _ticket(maximum: 8);
    const child = TicketType(
      id: 2,
      ulid: '01JTESTTICKETCHILD00000001',
      name: 'Bambino',
      description: '',
      priceCents: 500,
      maxQuantity: 8,
      bookedQuantity: 0,
      isActive: true,
    );
    controller.setQuantity(adult, 4, eventAvailable: 6);
    controller.setQuantity(child, 4, eventAvailable: 6);
    expect(controller.state.quantities[adult.id], 4);
    expect(controller.state.quantities[child.id], 2);
  });
  testWidgets('confirmed booking asks confirmation and sends one cancellation',
      (tester) async {
    final repository = _FakeBookingRepository();
    repository.currentBooking = _serverBooking('cancel-me');
    await tester.pumpWidget(ProviderScope(
        overrides: [
          bookingRepositoryProvider.overrideWithValue(repository),
        ],
        child: const MaterialApp(
          home: CustomerBookingDetailScreen(bookingUlid: 'booking-cancel-me'),
        )));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('cancel-booking')), findsOneWidget);
    await tester.tap(find.byKey(const Key('cancel-booking')));
    await tester.pumpAndSettle();
    expect(find.text('Annullare la prenotazione?'), findsOneWidget);
    await tester.tap(find.byKey(const Key('confirm-cancel-booking')));
    await tester.pumpAndSettle();
    expect(repository.cancelCalls, 1);
    expect(find.byKey(const Key('cancel-booking')), findsNothing);
    expect(repository.currentBooking?.status, 'cancelled_by_customer');
    expect(classifyBooking(repository.currentBooking!), BookingGroup.cancelled);
  });

  testWidgets('cancelled completed and checked-in bookings hide cancellation',
      (tester) async {
    for (final status in ['cancelled_by_customer', 'completed', 'checked_in']) {
      final repository = _FakeBookingRepository();
      repository.currentBooking = _serverBooking('hidden', status: status);
      await tester.pumpWidget(ProviderScope(
          overrides: [
            bookingRepositoryProvider.overrideWithValue(repository),
          ],
          child: const MaterialApp(
            home: CustomerBookingDetailScreen(bookingUlid: 'booking-hidden'),
          )));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('cancel-booking')), findsNothing);
    }
  });

  testWidgets('past event hides cancellation', (tester) async {
    final repository = _FakeBookingRepository();
    repository.currentBooking = _serverBooking(
      'past-event',
      startsAt: DateTime.now().subtract(const Duration(hours: 2)),
      endsAt: DateTime.now().subtract(const Duration(hours: 1)),
    );
    await tester.pumpWidget(ProviderScope(
        overrides: [
          bookingRepositoryProvider.overrideWithValue(repository),
        ],
        child: const MaterialApp(
          home: CustomerBookingDetailScreen(bookingUlid: 'booking-past-event'),
        )));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('cancel-booking')), findsNothing);
  });

  testWidgets('cancellation API error is readable', (tester) async {
    final repository = _FakeBookingRepository(cancelErrorStatus: 409);
    repository.currentBooking = _serverBooking('error');
    await tester.pumpWidget(ProviderScope(
        overrides: [
          bookingRepositoryProvider.overrideWithValue(repository),
        ],
        child: const MaterialApp(
          home: CustomerBookingDetailScreen(bookingUlid: 'booking-error'),
        )));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('cancel-booking')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-cancel-booking')));
    await tester.pumpAndSettle();
    expect(find.textContaining('Errore annullamento 409'), findsOneWidget);
  });

  for (final status in [409, 422]) {
    test('surfaces controlled $status booking error', () async {
      final repository = _FakeBookingRepository(errorStatus: status);
      final controller = _bookingController(repository, 'event');
      final ticket = _ticket();
      controller.setQuantity(ticket, 1, eventAvailable: 20);
      await controller.submit([ticket]);
      expect(controller.state.error, isNot(contains('$status')));
      expect(controller.state.error, isNot(contains('items.')));
      expect(controller.state.isSubmitting, isFalse);
    });
  }

  testWidgets('submit button is disabled while request is in flight',
      (tester) async {
    final repository = _FakeBookingRepository(delay: true);
    final event = _event();
    await tester.pumpWidget(ProviderScope(overrides: [
      eventDetailProvider(event.id).overrideWith((ref) async => event),
      bookingRepositoryProvider.overrideWithValue(repository),
    ], child: const MaterialApp(home: BookingFlowScreen(id: 'event-ulid'))));
    await tester.pumpAndSettle();
    await tester.enterText(
        find.byKey(const Key('contact-phone')), '+393331234567');
    await tester.tap(find.byKey(const Key('ticket-1-plus')));
    await tester.pump();
    await tester.scrollUntilVisible(
        find.byKey(const Key('confirm-booking')), 400,
        scrollable: find.byType(Scrollable).first);
    await tester.tap(find.byKey(const Key('confirm-booking')));
    await tester.pump();
    final button =
        tester.widget<FilledButton>(find.byKey(const Key('confirm-booking')));
    expect(button.onPressed, isNull);
    await tester.tap(find.byKey(const Key('confirm-booking')));
    expect(repository.createCalls, 1);
    repository.complete();
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('booking-qr')), findsOneWidget);
    expect(find.text('Server event-ulid'), findsOneWidget);
    expect(find.text('Evento reale'), findsNothing);
    expect(find.textContaining('demo'), findsNothing);
  });
}

TicketType _ticket({int price = 1500, int maximum = 5}) => TicketType(
      id: 1,
      ulid: '01JTESTTICKETTYPE000000001',
      name: 'Ingresso',
      description: '',
      priceCents: price,
      maxQuantity: maximum,
      bookedQuantity: 0,
      isActive: true,
    );

ApiEvent _event() => ApiEvent.fromJson({
      'ulid': 'event-ulid',
      'title': 'Evento reale',
      'summary': '',
      'description': '',
      'capacity_total': 5,
      'available_total': 5,
      'is_free': false,
      'ticket_types': [
        {
          'id': 1,
          'ulid': '01JTESTTICKETTYPE000000001',
          'name': 'Ingresso',
          'price_cents': 1500,
          'max_quantity': 5,
          'booked_quantity': 0,
          'is_active': true,
        }
      ]
    });

ApiEvent _eventWithAvailability({
  required int maximum,
  int booked = 0,
  int eventAvailable = 19,
  int capacity = 30,
  int price = 1500,
}) =>
    ApiEvent.fromJson({
      'ulid': 'event-limit',
      'title': 'Evento disponibilità',
      'summary': '',
      'description': '',
      'capacity_total': capacity,
      'available_total': eventAvailable,
      'is_free': false,
      'ticket_types': [
        {
          'id': 1,
          'ulid': '01JTESTTICKETTYPE000000001',
          'name': 'Ingresso',
          'price_cents': price,
          'max_quantity': maximum,
          'booked_quantity': booked,
          'is_active': true,
        }
      ]
    });

Future<void> _pumpBookingFlow(WidgetTester tester, ApiEvent event) async {
  await tester.pumpWidget(ProviderScope(overrides: [
    eventDetailProvider(event.id).overrideWith((ref) async => event),
    bookingRepositoryProvider.overrideWithValue(_FakeBookingRepository()),
  ], child: MaterialApp(home: BookingFlowScreen(id: event.id))));
  await tester.pumpAndSettle();
}

CustomerBooking _serverBooking(String eventUlid,
        {int price = 1500,
        String status = 'confirmed',
        DateTime? startsAt,
        DateTime? endsAt}) =>
    CustomerBooking(
      ulid: 'booking-$eventUlid',
      bookingCode: 'EVT-$eventUlid',
      status: status,
      totalPeople: 1,
      totalPriceCents: price,
      items: const [],
      eventTitle: 'Server $eventUlid',
      eventStartsAt: startsAt ?? DateTime.now().add(const Duration(days: 2)),
      eventEndsAt:
          endsAt ?? DateTime.now().add(const Duration(days: 2, hours: 2)),
      qrToken: 'secure-token',
    );

class _FakeBookingRepository extends BookingRepository {
  _FakeBookingRepository(
      {this.delay = false, this.errorStatus, this.cancelErrorStatus})
      : super(ApiClient(), AuthenticationRepository(ApiClient()));
  final bool delay;
  final int? errorStatus;
  final int? cancelErrorStatus;
  final List<String> keys = [];
  final List<String> eventIds = [];
  int createCalls = 0;
  int cancelCalls = 0;
  CustomerBooking? currentBooking;
  CreateBookingRequest? lastRequest;
  Completer<CustomerBooking>? _completer;

  @override
  String newIdempotencyKey() => 'stable-idempotency-key';

  @override
  Future<CustomerBooking> createBooking(
      String eventUlid, CreateBookingRequest request, String idempotencyKey) {
    createCalls++;
    lastRequest = request;
    eventIds.add(eventUlid);
    keys.add(idempotencyKey);
    if (errorStatus != null) {
      return Future.error(BookingApiException(
          'Errore disponibilità $errorStatus',
          errorStatus!,
          errorStatus == 422
              ? {
                  'items': ['Biglietti non disponibili.']
                }
              : const {}));
    }
    if (!delay) return Future.value(_serverBooking(eventUlid));
    _completer = Completer<CustomerBooking>();
    return _completer!.future;
  }

  void complete() => _completer!.complete(_serverBooking(eventIds.last));

  @override
  Future<BookingQr> getBookingQr(String bookingUlid) async =>
      BookingQr(bookingUlid: bookingUlid, qrToken: 'secure-token');

  @override
  Future<CustomerBooking> getBooking(String bookingUlid) async =>
      currentBooking ?? _serverBooking(bookingUlid);

  @override
  Future<CustomerBooking> cancelBooking(String bookingUlid) async {
    cancelCalls++;
    if (cancelErrorStatus != null) {
      throw BookingApiException(
          'Errore annullamento $cancelErrorStatus', cancelErrorStatus!);
    }
    currentBooking = _serverBooking(
        (currentBooking?.eventTitle ?? 'Server cancelled')
            .replaceFirst('Server ', ''),
        status: 'cancelled_by_customer');
    return currentBooking!;
  }
}

BookingFlowController _bookingController(
  BookingRepository repository,
  String eventUlid, {
  void Function()? onBookingCreated,
}) =>
    BookingFlowController(
      repository,
      eventUlid,
      initialPhone: '3331234567',
      onBookingCreated: onBookingCreated,
    );
