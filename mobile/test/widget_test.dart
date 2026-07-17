import 'package:eventi_locali_mobile/core/auth/auth_controller.dart';
import 'package:eventi_locali_mobile/core/auth/authentication_repository.dart';
import 'package:eventi_locali_mobile/core/models/app_role.dart';
import 'package:eventi_locali_mobile/core/network/api_client.dart';
import 'package:eventi_locali_mobile/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
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
    await tester.enterText(find.byType(TextField).first, 'customer@example.test');
    await tester.enterText(find.byType(TextField).last, 'password');
    await tester.tap(find.text('Entra'));
    await tester.pumpAndSettle();

    expect(find.text('Tutti gli Eventi'), findsWidgets);
    expect(find.byIcon(Icons.home_rounded), findsOneWidget);
  });

  testWidgets('real organizer login opens organizer shell', (tester) async {
    await _pumpApp(tester);
    await tester.enterText(find.byType(TextField).first, 'organizer@example.test');
    await tester.enterText(find.byType(TextField).last, 'password');
    await tester.tap(find.text('Entra'));
    await tester.pumpAndSettle();

    expect(find.text('Dashboard'), findsWidgets);
    expect(find.text('Opportunità'), findsWidgets);
  });
}

Future<void> _pumpApp(WidgetTester tester) async {
  await tester.binding.setSurfaceSize(const Size(430, 980));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(ProviderScope(
    overrides: [
      authenticationRepositoryProvider.overrideWithValue(_TestAuthRepository()),
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
  Future<AuthUser> login({required String email, required String password}) async {
    final role = email.startsWith('organizer')
        ? AppRole.organizer
        : AppRole.customer;
    return AuthUser(name: 'Test User', email: email, role: role);
  }

  @override
  Future<void> logout() async {}
}
