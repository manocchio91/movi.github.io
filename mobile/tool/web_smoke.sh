#!/bin/sh
# Smoke test runtime del target web per la generazione della sessione analytics.
#
# Compila il codice reale con dart2js e lo esegue: e' l'unico modo per
# coprire il collapse di `1 << 32` a 0 che fa crashare la build web
# (i test `flutter test` girano sulla VM e non lo rilevano).
#
# Richiede: dart (o flutter) e node.
set -eu

cd "$(dirname "$0")/.."

OUT="${1:-build/web_smoke}"
mkdir -p "$(dirname "$OUT")"

echo "==> compilazione dart2js"
if command -v dart >/dev/null 2>&1; then
  dart compile js tool/web_smoke.dart -o "$OUT.js" >/dev/null
else
  flutter build web --release --target=tool/web_smoke.dart --output "$OUT" >/dev/null
  OUT="$OUT/main.dart.js"
fi

echo "==> esecuzione su node"
OUTPUT="$(node "$OUT")"
echo "$OUTPUT"

case "$OUTPUT" in
  *WEB_SMOKE_OK*) echo "web smoke test: OK" ;;
  *) echo "web smoke test: FALLITO" >&2; exit 1 ;;
esac
