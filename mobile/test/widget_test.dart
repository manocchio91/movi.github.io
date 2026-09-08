import 'dart:io';

import 'package:eventi_locali_mobile/core/auth/auth_controller.dart';
import 'package:eventi_locali_mobile/core/auth/authentication_repository.dart';
import 'package:eventi_locali_mobile/core/models/app_role.dart';
import 'package:eventi_locali_mobile/core/network/api_client.dart';
import 'package:eventi_locali_mobile/features/customer/data/customer_api_models.dart';
import 'package:eventi_locali_mobile/features/customer/data/customer_repositories.dart';
import 'package:eventi_locali_mobile/features/shared/state/event_data_revision.dart';
import 'package:eventi_locali_mobile/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('customer API errors expose a readable message', () {
    expect(const CustomerApiException('Errore API').toString(), 'Errore API');
  });

  test('event JSON maps summary only to the short description', () {
    final event = ApiEvent.fromJson(_TestApiClient().event);
    expect(event.title, 'Evento Sync QA');
    expect(event.summary, 'Descrizione breve QA');
    expect(event.description, 'Descrizione completa QA');
    expect(event.capacity, 50);
    expect(event.availableSeats, 32);
  });

  test('event media falls back to organizer logo and ticket availability is authoritative', () {
    final json = Map<String, dynamic>.from(_TestApiClient().event)
      ..['available_total'] = 16
      ..['bookable_available_total'] = 0
      ..['organizer_profile'] = {'ulid': 'org-1', 'name': 'Organizer', 'logo_url': 'https://example.test/logo.png'};
    final event = ApiEvent.fromJson(json);

    expect(event.imageUrl, 'https://example.test/logo.png');
    expect(event.availableSeats, 16);
    expect(event.bookableSeats, 0);
  });

  test('fresh organizer summary replaces a stale event snapshot', () {
    final container = ProviderContainer();
    addTearDown(container.dispose);
    final store = container.read(eventDomainStoreProvider.notifier);
    store.acceptUpdate({
      'ulid': '01SUMMARYQA',
      'capacity_total': 30,
      'available_total': 30,
    });

    final current = store.ingest({
      'ulid': '01SUMMARYQA',
      'capacity_total': 30,
      'summary': {
        'capacity_total': 30,
        'booked_people': 4,
        'available_total': 26,
      },
    });

    final summary = Map<String, dynamic>.from(current['summary'] as Map);
    expect(summary['capacity_total'], 30);
    expect(summary['booked_people'], 4);
    expect(summary['available_total'], 26);
  });
  testWidgets('shows real login screen', (tester) async {
    await _pumpApp(tester);

    expect(find.bySemanticsLabel('MòVi — Vivi quello che succede'),
        findsOneWidget);
    expect(find.text('Email'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Entra'), findsOneWidget);
    expect(find.textContaining('demo'), findsNothing);
  });

  testWidgets('real customer login opens customer shell', (tester) async {
    await _pumpApp(tester);
    await tester.enterText(
        find.byType(TextField).first, 'customer@example.test');
    await tester.enterText(find.byType(TextField).last, 'password');
    await tester.tap(find.text('Entra'));
    await tester.pumpAndSettle();

    expect(find.byType(NavigationBar), findsOneWidget);
    expect(find.byIcon(Icons.home_rounded), findsOneWidget);
  });

  testWidgets('real organizer login opens organizer shell', (tester) async {
    await _pumpApp(tester);
    await tester.enterText(
        find.byType(TextField).first, 'organizer@example.test');
    await tester.enterText(find.byType(TextField).last, 'password');
    await tester.tap(find.text('Entra'));
    await tester.pumpAndSettle();

    expect(find.text('Dashboard'), findsWidgets);
    expect(find.text('Eventi'), findsOneWidget);
    expect(find.text('Prenotazioni'), findsWidgets);
    expect(find.text('Profilo'), findsOneWidget);
    expect(find.textContaining('disponibili nella versione web'), findsNothing);
    expect(find.byType(NavigationBar), findsOneWidget);
  });

  testWidgets('organizer edits an existing event end to end', (tester) async {
    final api = _TestApiClient();
    await _pumpApp(tester, apiClient: api);

    await tester.enterText(
        find.byType(TextField).first, 'customer@example.test');
    await tester.enterText(find.byType(TextField).last, 'password');
    await tester.tap(find.text('Entra'));
    await tester.pumpAndSettle();
    await tester.tap(find.descendant(
      of: find.byType(NavigationBar),
      matching: find.text('Eventi'),
    ));
    await tester.pumpAndSettle();
    expect(find.text('32 posti'), findsOneWidget);
    await tester.tap(find.text('Evento Sync QA'));
    await tester.pumpAndSettle();
    expect(find.text('32 posti prenotabili su 50'), findsOneWidget);
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.descendant(
      of: find.byType(NavigationBar),
      matching: find.text('Profilo'),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithIcon(OutlinedButton, Icons.logout_rounded));
    await tester.pumpAndSettle();

    await tester.enterText(
        find.byType(TextField).first, 'organizer@example.test');
    await tester.enterText(find.byType(TextField).last, 'password');
    await tester.tap(find.text('Entra'));
    await tester.pumpAndSettle();

    await tester.tap(find.descendant(
      of: find.byType(NavigationBar),
      matching: find.text('Eventi'),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Evento Sync QA'));
    await tester.pumpAndSettle();
    final editButton = find.widgetWithText(FilledButton, 'Modifica evento');
    expect(editButton, findsOneWidget);
    await tester.scrollUntilVisible(editButton, 300);
    await tester.tap(editButton);
    await tester.pumpAndSettle();

    expect(find.text('Modifica esperienza'), findsOneWidget);
    final titleField = find.byType(TextFormField).at(0);
    final summaryField = find.byType(TextFormField).at(1);
    final descriptionField = find.byType(TextFormField).at(2);
    final capacityField = find.byType(TextFormField).at(3);
    expect(tester.widget<TextFormField>(titleField).controller?.text,
        'Evento Sync QA');
    expect(tester.widget<TextFormField>(summaryField).controller?.text,
        'Descrizione breve QA');
    expect(tester.widget<TextFormField>(descriptionField).controller?.text,
        'Descrizione completa QA');
    expect(tester.widget<TextFormField>(capacityField).controller?.text, '50');

    expect(find.text('18 prenotati · 32 posti disponibili'), findsOneWidget);
    expect(find.text('Capienza minima consentita: 18'), findsOneWidget);

    await tester.enterText(capacityField, '17');
    final saveButton = find.widgetWithText(FilledButton, 'Salva evento').last;
    await tester.drag(find.byType(ListView).last, const Offset(0, -1000));
    await tester.pumpAndSettle();
    await tester.tap(saveButton);
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView).last, const Offset(0, 1000));
    await tester.pumpAndSettle();
    expect(
        find.text(
            'La capienza non può essere inferiore ai 18 posti già prenotati.'),
        findsOneWidget);
    expect(api.patchPath, isNull);

    await tester.enterText(capacityField, '29');
    await tester.drag(find.byType(ListView).last, const Offset(0, -1000));
    await tester.pumpAndSettle();
    await tester.tap(saveButton);
    await tester.pumpAndSettle();

    expect(api.patchPath, '/organizer/events/01QAULID');
    expect(api.patchBody?['title'], 'Evento Sync QA');
    expect(api.patchBody?['capacity_total'], 29);
    expect(api.patchBody?['summary'], 'Descrizione breve QA');
    expect(api.patchBody?['description'], 'Descrizione completa QA');
    final patchedTickets = api.patchBody?['ticket_types'] as List;
    expect(patchedTickets, hasLength(2));
    expect((patchedTickets[1] as Map)['ulid'], '01TICKETADULT');
    expect(api.patchBody?['starts_at'], '2026-12-20T18:00:00.000Z');
    expect(api.bookingsCount, 18);
    expect(find.text('Evento Sync QA'), findsOneWidget);
    expect(
        find.text('29 totali · 18 prenotati · 11 disponibili'), findsOneWidget);

    final reopenEdit = find.widgetWithText(FilledButton, 'Modifica evento');
    await tester.scrollUntilVisible(reopenEdit, 300);
    await tester.tap(reopenEdit);
    await tester.pumpAndSettle();
    expect(
        tester
            .widget<TextFormField>(find.byType(TextFormField).at(1))
            .controller
            ?.text,
        'Descrizione breve QA');
    expect(
        tester
            .widget<TextFormField>(find.byType(TextFormField).at(2))
            .controller
            ?.text,
        'Descrizione completa QA');
    expect(
        tester
            .widget<TextFormField>(find.byType(TextFormField).at(3))
            .controller
            ?.text,
        '29');
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.pageBack();
    await tester.pumpAndSettle();
    expect(find.text('Evento Sync QA'), findsOneWidget);
    expect(find.text('18 / 29 prenotati · 11 disponibili'), findsOneWidget);
    await tester.tap(find.descendant(
      of: find.byType(NavigationBar),
      matching: find.text('Dashboard'),
    ));
    await tester.pumpAndSettle();
    expect(find.text('11'), findsWidgets);

    await tester.tap(find.descendant(
      of: find.byType(NavigationBar),
      matching: find.text('Profilo'),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithIcon(OutlinedButton, Icons.logout_rounded));
    await tester.pumpAndSettle();

    await tester.enterText(
        find.byType(TextField).first, 'customer@example.test');
    await tester.enterText(find.byType(TextField).last, 'password');
    await tester.tap(find.text('Entra'));
    await tester.pumpAndSettle();
    await tester.tap(find.descendant(
      of: find.byType(NavigationBar),
      matching: find.text('Eventi'),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Evento Sync QA'), findsOneWidget);
    expect(find.text('11 posti'), findsOneWidget);
    await tester.tap(find.text('Evento Sync QA'));
    await tester.pumpAndSettle();
    expect(find.text('11 posti prenotabili su 29'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Prenota'));
    await tester.pumpAndSettle();
    expect(find.text('11 posti prenotabili su 29'), findsOneWidget);
  });
  testWidgets('real admin login opens admin shell', (tester) async {
    await _pumpApp(tester);
    await tester.enterText(find.byType(TextField).first, 'admin@example.test');
    await tester.enterText(find.byType(TextField).last, 'password');
    await tester.tap(find.text('Entra'));
    await tester.pumpAndSettle();

    expect(find.text('Dashboard Admin'), findsOneWidget);
    expect(find.text('Ricerca'), findsOneWidget);
    expect(find.text('Gestione'), findsOneWidget);
    expect(find.text('Piani'), findsOneWidget);
    expect(find.byType(NavigationBar), findsOneWidget);
  });
  test('demo routes and repositories are absent from the mobile application',
      () {
    final router = File('lib/core/router/app_router.dart').readAsStringSync();
    final sources = Directory('lib')
        .listSync(recursive: true)
        .whereType<File>()
        .where((file) => file.path.endsWith('.dart'))
        .map((file) => file.readAsStringSync())
        .join('\n');

    expect(router, contains('/organizer/events'));
    expect(router, contains('/admin/management'));
    expect(router, contains('/admin/plans'));
    expect(router, contains('create'));
    expect(router, contains("path: ':id/edit'"));
    expect(
      File('lib/features/organizer/organizer_screens.dart').readAsStringSync(),
      contains(r'/organizer/events/${widget.id}/cancel'),
    );
    expect(sources, contains('Crea evento'));
    expect(router, isNot(contains('/organizer/opportunities')));
    expect(router, isNot(contains('/customer/preferences')));
    expect(sources, isNot(contains('InMemoryDemoRepository')));
    expect(sources, isNot(contains('demoRepositoryProvider')));
  });
}

Future<void> _pumpApp(WidgetTester tester, {ApiClient? apiClient}) async {
  await tester.binding.setSurfaceSize(const Size(430, 980));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(ProviderScope(
    overrides: [
      authenticationRepositoryProvider.overrideWithValue(_TestAuthRepository()),
      apiClientProvider.overrideWithValue(apiClient ?? _TestApiClient()),
    ],
    child: const EventiLocaliApp(),
  ));
  await tester.pump();
  await tester.pump();
}

class _TestAuthRepository extends AuthenticationRepository {
  _TestAuthRepository() : super(ApiClient());

  @override
  Future<AuthUser?> currentUser() async => null;

  @override
  Future<AuthUser> login(
      {required String email, required String password}) async {
    final role = email.startsWith('admin')
        ? AppRole.admin
        : email.startsWith('organizer')
            ? AppRole.organizer
            : AppRole.customer;
    return AuthUser(name: 'Test User', email: email, role: role);
  }

  @override
  Future<void> logout() async {}

  @override
  Future<String?> accessToken() async => 'test-token';
}

class _TestApiClient extends ApiClient {
  String title = 'Evento Sync QA';
  int capacity = 50;
  final int bookingsCount = 18;
  String? patchPath;
  Map<String, dynamic>? patchBody;

  Map<String, dynamic> get servedEvent => event;

  Map<String, dynamic> get event => {
        'ulid': '01QAULID',
        'title': title,
        'summary': 'Descrizione breve QA',
        'description': 'Descrizione completa QA',
        'status': 'published',
        'starts_at': '2026-12-20T18:00:00.000Z',
        'capacity_total': capacity,
        'available_total': capacity - bookingsCount,
        'is_free': true,
        'family_friendly': true,
        'municipality': {
          'id': 1,
          'name': 'Campobasso',
          'province': {'code': 'CB'}
        },
        'categories': [
          {'id': 1, 'name': 'Enogastronomia'}
        ],
        'ticket_types': [
          {
            'ulid': '01TICKETQA',
            'name': 'Ingresso',
            'audience': 'generic',
            'price_cents': 0,
            'max_quantity': 30,
            'booked_quantity': 6,
            'is_active': true,
          },
          {
            'ulid': '01TICKETADULT',
            'name': 'Adulto',
            'audience': 'adult',
            'price_cents': 1200,
            'max_quantity': 25,
            'booked_quantity': 12,
            'is_active': true,
          }
        ],
        'summary_metrics': {
          'capacity_total': capacity,
          'booked_people': bookingsCount,
          'available_total': capacity - bookingsCount,
        },
      };

  @override
  Future<ApiResponse> get(String path, {String? token}) async {
    if (path == '/categories') {
      return const ApiResponse(statusCode: 200, body: {
        'data': [
          {'id': 1, 'name': 'Enogastronomia'}
        ]
      });
    }
    if (path.startsWith('/customer/successful-events')) {
      return const ApiResponse(statusCode: 200, body: {'data': []});
    }
    if (path == '/me/profile') {
      return const ApiResponse(statusCode: 200, body: {
        'data': {'name': 'Cliente QA', 'email': 'customer@example.test'}
      });
    }
    if (path == '/organizer/dashboard') {
      return ApiResponse(statusCode: 200, body: {
        'data': {'available_places': capacity - bookingsCount}
      });
    }
    if (path == '/organizer/profile') {
      return const ApiResponse(statusCode: 200, body: {
        'data': {'name': 'Organizer QA', 'verification_status': 'approved'}
      });
    }
    if (path.startsWith('/organizer/events?')) {
      return ApiResponse(statusCode: 200, body: {
        'data': [
          {
            'event': servedEvent,
            'summary': servedEvent['summary_metrics'],
          }
        ]
      });
    }
    if (path == '/organizer/events/01QAULID') {
      return ApiResponse(statusCode: 200, body: {'data': servedEvent});
    }
    if (path == '/admin/dashboard') {
      return const ApiResponse(statusCode: 200, body: {'data': {}});
    }
    if (path == '/events/01QAULID') {
      return ApiResponse(statusCode: 200, body: {'data': servedEvent});
    }
    if (path.startsWith('/events')) {
      return ApiResponse(statusCode: 200, body: {
        'data': [servedEvent],
        'current_page': 1,
        'last_page': 1,
        'total': 1,
      });
    }
    return const ApiResponse(statusCode: 404, body: {'message': 'Not found'});
  }

  @override
  Future<ApiResponse> patch(String path,
      {Map<String, dynamic>? body, String? token}) async {
    patchPath = path;
    patchBody = body;
    title = body?['title'] as String? ?? title;
    capacity = body?['capacity_total'] as int? ?? capacity;
    return ApiResponse(statusCode: 200, body: {'data': event});
  }
}
