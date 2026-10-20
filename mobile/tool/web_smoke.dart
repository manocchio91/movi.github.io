// Smoke test runtime per il target web.
//
// La VM Dart e dart2js interpretano gli shift a 32 bit in modo diverso:
// sul web `1 << 32` vale 0 e `Random.nextInt` solleva RangeError, facendo
// crashare l'app all'avvio. I test `flutter test` girano sulla VM e non
// vedono il problema: questo script compila il codice reale con
// `dart compile js` e lo esegue, coprendo il target web.
//
// L'helper e' l'unico punto in cui viene generato l'identificatore di
// sessione: ApiClient lo usa al bootstrap, quindi il percorso di avvio
// dell'app e' coperto.
//
// Uso: dart compile js tool/web_smoke.dart -o /tmp/web_smoke.js && node /tmp/web_smoke.js
// ignore_for_file: avoid_print
import 'package:eventi_locali_mobile/core/network/analytics_session.dart';

void main() {
  final session = buildAnalyticsSession();
  print('analytics_session=$session');

  if (!RegExp(r'^\d+-\d+$').hasMatch(session)) {
    print('WEB_SMOKE_FAIL');
    return;
  }

  print('WEB_SMOKE_OK');
}
