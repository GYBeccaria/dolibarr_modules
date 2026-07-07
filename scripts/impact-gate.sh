#!/usr/bin/env bash
# impact-gate.sh — Gate U2 del pre-commit (AP-059): avverte se il commit modifica la firma/semantica
# di un simbolo con call-site CROSS-REPO. WARNING, NON blocca (decisione: A+B, non C): il gate hard
# sarebbe attrito aggirabile; qui si INFORMA con l'impatto reale, la scelta resta all'umano/sessione.
# Best-effort: senza token o con HenaxMetrics irraggiungibile, esce in silenzio (0). Read-only.
set -uo pipefail
URL="${HM_METRICS_URL:-https://metrics.henaxis.com}"
TOKFILE="${HOME}/.config/henaxmetrics/ingest-token"
TOKEN="${HM_INGEST_TOKEN:-}"
[ -z "$TOKEN" ] && [ -r "$TOKFILE" ] && TOKEN="$(head -1 "$TOKFILE" 2>/dev/null || true)"
[ -n "$TOKEN" ] || exit 0                      # niente token → gate inattivo (best-effort)
command -v curl >/dev/null 2>&1 || exit 0
command -v python3 >/dev/null 2>&1 || exit 0

# simboli DEFINITI toccati dal diff staged (righe +/- su file di codice): function/def/class/const-arrow.
mapfile -t syms < <(
  git diff --cached -U0 --diff-filter=ACMR -- '*.php' '*.py' '*.js' '*.mjs' '*.cjs' 2>/dev/null \
    | grep -E '^[+-]' | grep -vE '^(\+\+\+|---)' \
    | grep -oE '(function|def|class)[[:space:]]+[A-Za-z_][A-Za-z0-9_]*|(const|let|var)[[:space:]]+[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=[[:space:]]*(async[[:space:]]*)?(function|\()' \
    | grep -oE '[A-Za-z_][A-Za-z0-9_]*$' \
    | grep -vE '^(function|def|class|const|let|var|async|if|for|return|true|false|null)$' \
    | sort -u | head -8
)
[ "${#syms[@]}" -gt 0 ] || exit 0

warned=0
for s in "${syms[@]}"; do
  [ "${#s}" -ge 3 ] || continue
  out=$(curl -sS --max-time 4 -H "Authorization: Bearer $TOKEN" \
        "$URL/api/impact?symbol=$s&depth=2" 2>/dev/null) || continue
  # solo se ESISTE almeno un call-site cross-repo
  cross=$(printf '%s' "$out" | python3 -c "
import json,sys
try: d=json.load(sys.stdin)
except Exception: sys.exit(0)
hits=[h for lvl in d.get('levels',[]) for h in lvl.get('hits',[]) if h.get('cross')]
if not hits: sys.exit(0)
print(f\"{len(hits)}\")
for h in hits[:5]: print(f\"    {h['repo']}/{h['file']}:{h['line']} (in {h.get('caller') or 'top-level'}) conf:{h['confidence']}\")
" 2>/dev/null) || continue
  [ -n "$cross" ] || continue
  n=$(printf '%s' "$cross" | head -1)
  if [ "$warned" -eq 0 ]; then
    echo "" >&2
    echo "⚠ AP-059 — stai modificando simboli usati da ALTRI repo (impact-check, non bloccante):" >&2
    warned=1
  fi
  echo "  • $s — $n call-site CROSS-REPO:" >&2
  printf '%s\n' "$cross" | tail -n +2 >&2
done

[ "$warned" -eq 1 ] && {
  echo "  → verifica quei chiamanti prima di cambiare firma/semantica; se serve, coordina via news (AP-046)." >&2
  echo "  → dettaglio completo: tools/hm-impact.sh - <simbolo>" >&2
}
exit 0   # WARNING only (A+B, non C): mai bloccare
