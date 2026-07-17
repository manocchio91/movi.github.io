import 'package:eventi_locali_mobile/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('shows demo login screen', (tester) async {
    await tester.binding.setSurfaceSize(const Size(430, 980));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(const ProviderScope(child: EventiLocaliApp()));
    await tester.pumpAndSettle();

    expect(find.text('Eventi Locali\nMolise'), findsOneWidget);
    expect(find.text('Customer demo'), findsOneWidget);
    expect(find.text('Organizer demo'), findsOneWidget);
  });

  testWidgets('customer demo opens customer shell', (tester) async {
    await tester.binding.setSurfaceSize(const Size(430, 980));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(const ProviderScope(child: EventiLocaliApp()));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Customer demo'));
    await tester.pumpAndSettle();

    expect(find.text('Tutti gli Eventi'), findsWidgets);
    expect(find.byIcon(Icons.home_rounded), findsOneWidget);
  });

  testWidgets('organizer demo opens organizer shell', (tester) async {
    await tester.binding.setSurfaceSize(const Size(430, 980));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(const ProviderScope(child: EventiLocaliApp()));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Organizer demo'));
    await tester.pumpAndSettle();

    expect(find.text('Dashboard'), findsWidgets);
    expect(find.text('Opportunità'), findsWidgets);
  });
}
