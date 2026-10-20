import 'dart:math';

/// Limite superiore accettato da `Random.nextInt`.
///
/// Il limite documentato e' 2^32, ma la compilazione web (dart2js) valuta
/// `1 << 32` con la semantica di shift a 32 bit di JavaScript: il risultato
/// collassa a 0 e `nextInt` solleva `RangeError: max must be in range
/// 0 < max <= 2^32, was 0`, crashing l'applicazione all'avvio sul target web.
/// Usando 0xFFFFFFFF (2^32 - 1) il limite e' valido sia sulla VM Dart
/// (Android/iOS) sia su dart2js (web).
const int kAnalyticsSessionRandomBound = 0xFFFFFFFF;

/// Genera l'identificatore di sessione usato per le metriche di discovery.
///
/// Formato: `<timestamp microsecondi>-<random>`.
String buildAnalyticsSession({Random? random, DateTime? now}) {
  final generator = random ?? Random.secure();
  final timestamp = (now ?? DateTime.now()).microsecondsSinceEpoch;

  return '$timestamp-${generator.nextInt(kAnalyticsSessionRandomBound)}';
}
