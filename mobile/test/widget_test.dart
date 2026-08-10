import 'dart:io';

import 'package:eventi_locali_mobile/core/auth/auth_controller.dart';
import 'package:eventi_locali_mobile/core/auth/authentication_repository.dart';
import 'package:eventi_locali_mobile/core/models/app_role.dart';
import 'package:eventi_locali_mobile/core/network/api_client.dart';
import 'package:eventi_locali_mobile/features/customer/data/customer_repositories.dart';
import 'package:eventi_locali_mobile/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('customer API errors expose a readable message', () {
    expect(const CustomerApiException('Errore API').toString(), 'Errore API');
  });

  testWidgets('shows real login screen', (tester) async {
    await _pumpApp(tester);

    expect(find.text('Eventi Locali\nMolise'), findsOneWidget);
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
    expect(sources, contains('Crea evento'));
    expect(router, isNot(contains('/organizer/opportunities')));
    expect(router, isNot(contains('/customer/preferences')));
    expect(sources, isNot(contains('InMemoryDemoRepository')));
    expect(sources, isNot(contains('demoRepositoryProvider')));
  });
}

Future<void> _pumpApp(WidgetTester tester) async {
  await tester.binding.setSurfaceSize(const Size(430, 980));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(ProviderScope(
    overrides: [
      authenticationRepositoryProvider.overrideWithValue(_TestAuthRepository()),
      apiClientProvider.overrideWithValue(_TestApiClient()),
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
    if (path == '/organizer/dashboard') {
      return const ApiResponse(statusCode: 200, body: {'data': {}});
    }
    if (path == '/admin/dashboard') {
      return const ApiResponse(statusCode: 200, body: {'data': {}});
    }
    if (path.startsWith('/events')) {
      return const ApiResponse(statusCode: 200, body: {
        'data': [],
        'current_page': 1,
        'last_page': 1,
        'total': 0,
      });
    }
    return const ApiResponse(statusCode: 404, body: {'message': 'Not found'});
  }
}
