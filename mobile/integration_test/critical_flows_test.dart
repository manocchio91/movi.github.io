import 'package:eventi_locali_mobile/main.dart' as app;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  setUp(() => SharedPreferences.setMockInitialValues({}));

  testWidgets('customer login, navigation, published event, booking and QR',
      (tester) async {
    await _startAndLogin(tester, 'customer@example.test');

    expect(find.textContaining('Ciao Cliente Demo'), findsOneWidget);
    await _tapNavigation(tester, 'Eventi');
    expect(find.text('Tutti gli Eventi'), findsOneWidget);
    await _waitFor(tester, find.text('Laboratorio creativo in fattoria'));
    await tester.tap(find.text('Laboratorio creativo in fattoria').first);
    await _waitFor(tester, find.text('Dettaglio evento'));
    await _waitFor(tester, find.text('Laboratorio creativo in fattoria'));
    expect(find.text('Laboratorio creativo in fattoria'), findsWidgets);

    await tester.tap(find.text('Prenota'));
    await tester.pumpAndSettle();
    expect(find.text('Prenota'), findsWidgets);
    await tester.enterText(
        find.byKey(const Key('contact-phone')), '+393331234567');
    final ticketPlus = find.byWidgetPredicate((widget) =>
        widget.key is Key &&
        widget.key.toString().contains('ticket-') &&
        widget.key.toString().contains('-plus'));
    await tester.tap(ticketPlus.first);
    await tester.pump();
    await tester.scrollUntilVisible(
      find.byKey(const Key('confirm-booking')),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.byKey(const Key('confirm-booking')));
    await _waitFor(tester, find.text('Prenotazione confermata'));
    expect(find.byKey(const Key('booking-qr')), findsOneWidget);
  });

  testWidgets('customer booking edit/cancel, plan and AI fallback',
      (tester) async {
    await _startAndLogin(tester, 'customer@example.test');
    await _tapNavigation(tester, 'Prenotazioni');
    await _waitFor(tester, find.textContaining('EVT-DEMO01'));
    await tester.tap(find.text('Laboratorio creativo in fattoria').first);
    await tester.pumpAndSettle();
    expect(find.bySemanticsLabel('QR della prenotazione'), findsOneWidget);
    expect(find.text('Modifica prenotazione'), findsOneWidget);
    await tester.tap(find.text('Modifica prenotazione'));
    await tester.pumpAndSettle();
    expect(find.text('Modifica prenotazione'), findsWidgets);
    await tester.tap(find.text('Annulla'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('cancel-booking')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-cancel-booking')));
    await _waitFor(tester, find.text('Prenotazione annullata.'));

    await tester.pageBack();
    await tester.pumpAndSettle();
    await _tapNavigation(tester, 'Home');
    await _scrollAndTap(tester, 'Organizza con MòVi');
    expect(find.textContaining('Chiedi a MòVi'), findsOneWidget);
    await tester.enterText(
        find.byType(TextField).first, 'Richiesta impossibile xyz');
    await tester.tap(find.textContaining('CHIEDI A'));
    await tester.pumpAndSettle(const Duration(seconds: 3));
    expect(
      find.text('Ho interpretato la richiesta').evaluate().isNotEmpty ||
          find.textContaining('errore').evaluate().isNotEmpty ||
          find.textContaining('disponibil').evaluate().isNotEmpty,
      isTrue,
    );
  });

  testWidgets('organizer login, main navigation, event create/edit surfaces',
      (tester) async {
    await _startAndLogin(tester, 'organizer@example.test');
    expect(find.text('Dashboard'), findsWidgets);
    await _tapNavigation(tester, 'Eventi');
    await _waitFor(tester, find.text('Laboratorio creativo in fattoria'));
    await tester.tap(find.text('Crea evento'));
    await tester.pumpAndSettle();
    expect(find.text('Crea evento'), findsWidgets);
    expect(find.text('Capienza totale'), findsOneWidget);
    expect(find.text('Nome ticket'), findsOneWidget);
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.text('Laboratorio creativo in fattoria').first);
    await _waitFor(tester, find.text('Modifica evento'));
    expect(find.text('Modifica evento'), findsOneWidget);
    await tester.tap(find.text('Modifica evento'));
    await tester.pumpAndSettle();
    expect(find.textContaining('posti disponibili'), findsOneWidget);
    expect(find.textContaining('Biglietti disponibili'), findsOneWidget);
    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.pageBack();
    await _waitFor(tester, find.byType(NavigationBar));
    await _tapNavigation(tester, 'Profilo');
    expect(
        find.textContaining('approvazione').evaluate().isNotEmpty ||
            find.textContaining('Verificato').evaluate().isNotEmpty,
        isTrue);
  });

  testWidgets('admin login and organizer approval surface', (tester) async {
    await _startAndLogin(tester, 'admin@example.test');
    expect(find.text('Dashboard Admin'), findsOneWidget);
    await _tapNavigation(tester, 'Gestione');
    await _waitFor(tester, find.text('Organizer Mobile Pending'));
    await tester.tap(find.text('Organizer Mobile Pending').first);
    await tester.pumpAndSettle();
    expect(find.text('Dettaglio Organizzatore'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Approva'), 300,
        scrollable: find.byType(Scrollable).first);
    expect(find.text('Approva'), findsOneWidget);
    await tester.tap(find.text('Approva'));
    await _waitFor(tester, find.text('Sospendi'));
    expect(find.text('Sospendi'), findsOneWidget);
  });
}

Future<void> _startAndLogin(WidgetTester tester, String email) async {
  app.main();
  await tester.pumpAndSettle();
  await tester.enterText(find.byType(TextField).at(0), email);
  await tester.enterText(find.byType(TextField).at(1), 'password');
  await tester.tap(find.text('Entra'));
  await _waitFor(tester, find.byType(NavigationBar));
}

Future<void> _tapNavigation(WidgetTester tester, String label) async {
  await tester.tap(find.text(label).last);
  await tester.pumpAndSettle();
}

Future<void> _scrollAndTap(WidgetTester tester, String text) async {
  final target = find.text(text);
  await tester.scrollUntilVisible(target, 300,
      scrollable: find.byType(Scrollable).first);
  await tester.tap(target);
  await tester.pumpAndSettle();
}

Future<void> _waitFor(WidgetTester tester, Finder finder) async {
  for (var attempt = 0; attempt < 80; attempt++) {
    await Future<void>.delayed(const Duration(milliseconds: 250));
    await tester.pump();
    if (finder.evaluate().isNotEmpty) return;
  }
  expect(finder, findsWidgets);
}
