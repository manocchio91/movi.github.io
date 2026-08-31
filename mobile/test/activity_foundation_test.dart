import 'package:eventi_locali_mobile/features/activities/activity_repository.dart';
import 'package:eventi_locali_mobile/features/activities/activity_screens.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('activity model parses real services and following state', () {
    final activity = ActivityModel.fromJson({
      'ulid': '01ACTIVITY',
      'name': 'Ristorante Test',
      'description': 'Cucina locale',
      'is_following': true,
      'services': [
        {'ulid': '01SERVICE', 'name': 'Cena'},
      ],
    });

    expect(activity.id, '01ACTIVITY');
    expect(activity.following, isTrue);
    expect(activity.services.single.name, 'Cena');
  });

  testWidgets('activity list renders API-backed activity data', (tester) async {
    const activity = ActivityModel(
        '01ACTIVITY', 'Ristorante Test', 'Cucina locale', false, []);
    await tester.pumpWidget(ProviderScope(
      overrides: [
        activitiesProvider.overrideWith((ref) async => [activity]),
      ],
      child: const MaterialApp(home: ActivityListScreen()),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Ristorante Test'), findsOneWidget);
    expect(find.text('Cucina locale'), findsOneWidget);
  });
}
