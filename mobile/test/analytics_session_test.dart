import 'dart:math';

import 'package:eventi_locali_mobile/core/network/analytics_session.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('buildAnalyticsSession', () {
    test('non solleva e produce un identificatore nel formato atteso', () {
      final session = buildAnalyticsSession();

      expect(session, matches(RegExp(r'^\d+-\d+$')));
    });

    test('il limite random e\' valido sia sulla VM Dart sia su dart2js', () {
      // 2^32 - 1: accettato dalla VM Dart e dalla compilazione web.
      // Con `1 << 32` la compilazione web collassa il limite a 0 e
      // Random.nextInt solleva RangeError: l'app non parte.
      expect(kAnalyticsSessionRandomBound, lessThanOrEqualTo(0xFFFFFFFF));
      expect(Random.secure().nextInt(kAnalyticsSessionRandomBound), isNonNegative);
    });

    test('usa il timestamp fornito', () {
      final instant = DateTime.utc(2026, 10, 20, 12, 30, 45);
      final session = buildAnalyticsSession(random: Random(42), now: instant);

      expect(session, startsWith('${instant.microsecondsSinceEpoch}-'));
    });

    test('sessioni consecutive diverse', () {
      final sessions = List.generate(50, (_) => buildAnalyticsSession());

      expect(sessions.toSet().length, greaterThan(1));
    });
  });
}
