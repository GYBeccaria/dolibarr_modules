#!/usr/bin/env bash
# impact-gate.sh — Gate U2 del pre-commit (AP-059). Doppio regime, deciso il 2026-07-08 (rivalutazione
# della scelta originale "A+B non C"): WARNING per il caso comune (informa, non blocca — l'attrito di un
# hard-block universale sarebbe aggirabile e la scelta resta all'umano/sessione); HARD-BLOCK solo per
# l'ALTO RISCHIO oggettivo — simbolo con ≥2 call-site CROSS-REPO a confidenza ALTA (nessun omonimo
# ambiguo): lì un cambio di firma/semantica rompe silenziosamente altri repo senza che nessun test
# locale lo veda. Eccezione consapevole e AUDITATA (come Gate D1): IMPACT_GATE_OVERRIDE="<motivo>".
# Best-effort: senza token o con HenaxMetrics irraggiungibile, esce in silenzio (0). Read-only.
set -uo pipefail
# Config condivisa (host/token) — lib-henaxis.sh dal playbook (impact-gate è COPIATO in scripts/ del repo:
# risolvo il playbook via env > config.env > path convenzionali, poi sorgo il loader da lì).
[ -z "${HENAXIS_PLAYBOOK:-}" ] && [ -r "$HOME/.config/henaxis/config.env" ] && \
  HENAXIS_PLAYBOOK=$(sed -n -E 's/^(export[[:space:]]+)?HENAXIS_PLAYBOOK=["'"'"']?([^"'"'"' ]+).*/\2/p' "$HOME/.config/henaxis/config.env" | tail -1)
_self="$(readlink -f "$0" 2>/dev/null || echo "$0")"
for _pb in "${HENAXIS_PLAYBOOK:-}" "$(dirname "$_self")/.." /opt/henaxis-playbook "$HOME/henaxis-playbook"; do
  [ -n "$_pb" ] && [ -r "$_pb/tools/lib-henaxis.sh" ] && { . "$_pb/tools/lib-henaxis.sh"; break; }
done
URL="${HM_METRICS_URL:-}"
TOKEN="${HM_INGEST_TOKEN:-}"
{ [ -n "$TOKEN" ] && [ -n "$URL" ]; } || exit 0   # niente token/URL → gate inattivo (best-effort)
command -v curl >/dev/null 2>&1 || exit 0
command -v python3 >/dev/null 2>&1 || exit 0

# Simboli DEFINITI toccati dal diff staged. TRE passate distinte, perché ognuna ha il nome in una
# posizione diversa e una passata sola non può estrarlo:
#   1. `function|def|class NOME`            → il nome è l'ULTIMO token del match
#   2. `const|let|var NOME [: tipo] = (…)`  → il nome è il SECONDO token, e il match finisce con `(`
#   3. `interface|type|enum NOME` (TS)      → ancorata a inizio riga, per non pescare la prosa
#
# La 2 era ROTTA da sempre: la regex la cercava (il commento diceva «const-arrow») ma l'estrattore
# prendeva `[A-Za-z_]\w*$`, e un match che termina con `(` non ha un identificatore in fondo.
# Risultato misurato: NESSUNA arrow function è mai stata coperta dal gate, in nessun linguaggio —
# non è una lacuna di TypeScript, è una lacuna che TypeScript ha reso visibile.
_gate_diff() {
  git diff --cached -U0 --diff-filter=ACMR -- \
    '*.php' '*.py' '*.js' '*.mjs' '*.cjs' '*.ts' '*.tsx' '*.jsx' 2>/dev/null \
    | grep -E '^[+-]' | grep -vE '^(\+\+\+|---)'
}
mapfile -t syms < <(
  {
    _gate_diff | grep -oE '(function|def|class)[[:space:]]+[A-Za-z_][A-Za-z0-9_]*' \
      | grep -oE '[A-Za-z_][A-Za-z0-9_]*$'
    _gate_diff | sed -nE 's/.*(const|let|var)[[:space:]]+([A-Za-z_][A-Za-z0-9_]*)[[:space:]]*(:[^=]*)?=[[:space:]]*(async[[:space:]]*)?(function|\().*/\2/p'
    _gate_diff | sed -nE 's/^[+-][[:space:]]*(export[[:space:]]+)?(declare[[:space:]]+)?(abstract[[:space:]]+)?(interface|type|enum)[[:space:]]+([A-Za-z_][A-Za-z0-9_]*).*/\5/p'
  } \
    | grep -vE '^(function|def|class|const|let|var|async|if|for|return|true|false|null|interface|type|enum|export|declare|abstract|from|import|default|new|await|this|super)$' \
    | LC_ALL=C sort -u
)
# LIMITE DICHIARATO (regex, non AST): un `const x: (a: T) => R = …` non viene estratto, perché
# l'annotazione di tipo-funzione contiene `=>` e la regex non può attraversare quell'`=`.
# La cura vera è l'estrazione per AST — dove vive il ponte codemappa, non qui.
#
# IL CAP SI DICHIARA, non si subisce: prima `head -8` scartava in silenzio, e un gate che
# controlla 8 simboli su 12 senza dirlo afferma una copertura che non ha.
_gate_tot="${#syms[@]}"
if [ "$_gate_tot" -gt 8 ]; then
  echo "⚠ impact-gate: $_gate_tot simboli toccati, ne controllo 8 (cap per non allungare il commit)." >&2
  echo "  non controllati: ${syms[*]:8}" >&2
  echo "  fix: spezza il commit (AP-003: due scope = due commit), oppure verificali a mano con tools/hm-impact.sh" >&2
  syms=("${syms[@]:0:8}")
fi
[ "${#syms[@]}" -gt 0 ] || exit 0

warned=0
hard=0
hard_syms=""
for s in "${syms[@]}"; do
  [ "${#s}" -ge 3 ] || continue
  out=$(curl -sS --max-time 4 -H "Authorization: Bearer $TOKEN" \
        "$URL/api/impact?symbol=$s&depth=2" 2>/dev/null) || continue
  # riga 1 = RISK (HIGH|LOW) · riga 2 = n. call-site cross · righe successive = dettaglio umano
  result=$(printf '%s' "$out" | python3 -c "
import json,sys
try: d=json.load(sys.stdin)
except Exception: sys.exit(0)
# SOLO PROFONDITÀ 1. La query chiede depth=2, ma la profondità ≥2 NON sono archi: sono SALTI-DI-NOME
# — simboli che si chiamano allo stesso modo in un altro grafo, non chiamanti reali. Appiattire ogni
# livello (\`for lvl in levels\`) li contava come call-site.
#
# Misurato il 2026-07-27, diagnosi di henaxmetrics sul servito: per \`attoreDi\` — funzione creata
# dieci minuti prima e impossibile da chiamare altrove — depth 1 dava 12 hit tutti nel playbook
# (corretti, e coincidenti con codemappa), depth 2 ne aggiungeva 28 in analgocard dentro \`badgeFor\`
# e \`rowClass\`. Il gate bloccava un commit citando file e righe che non usano il simbolo: un allarme
# falso con dettagli precisi è peggio di un allarme vago, perché è credibile.
hits=[h for lvl in d.get('levels',[]) if lvl.get('depth')==1 for h in lvl.get('hits',[]) if h.get('cross')]
if not hits: sys.exit(0)
conf=hits[0].get('confidence','bassa')
# ALTO RISCHIO: ≥2 call-site cross-repo E nessuna ambiguità (confidenza alta = 1 solo omonimo nel parco)
risk='HIGH' if (len(hits)>=2 and conf=='alta') else 'LOW'
print(risk); print(len(hits))
for h in hits[:5]: print(f\"    {h['repo']}/{h['file']}:{h['line']} (in {h.get('caller') or 'top-level'}) conf:{h['confidence']}\")
" 2>/dev/null) || continue
  [ -n "$result" ] || continue
  risk=$(printf '%s' "$result" | sed -n '1p'); n=$(printf '%s' "$result" | sed -n '2p')
  if [ "$warned" -eq 0 ]; then
    echo "" >&2
    echo "⚠ AP-059 — stai modificando simboli usati da ALTRI repo (impact-check):" >&2
    warned=1
  fi
  tag=""; [ "$risk" = HIGH ] && { hard=1; hard_syms="$hard_syms $s"; tag=" [ALTO RISCHIO: ≥2 call-site, confidenza alta → HARD-BLOCK]"; }
  echo "  • $s — $n call-site CROSS-REPO$tag:" >&2
  printf '%s\n' "$result" | tail -n +3 >&2
  # Notify Atlante sul bus (henaxis/impact/<repo-impattato>, MQTT.md): i repo i cui call-site
  # stanno per essere toccati vengono annunciati — visibilità live per tecnici/sala di controllo
  # (l'abbonamento per-repo delle sessioni è un'estensione futura). Best-effort, mai blocca.
  if command -v mqtt_pub >/dev/null 2>&1; then
    _repo_self="$(basename "$(git rev-parse --show-toplevel 2>/dev/null || pwd)")"
    printf '%s\n' "$result" | tail -n +3 | grep -oE '^\s+[A-Za-z0-9._-]+/' | tr -d ' /' | LC_ALL=C sort -u | while read -r _hit_repo; do
      [ -n "$_hit_repo" ] && mqtt_pub "henaxis/impact/$_hit_repo" "{\"symbol\":\"$s\",\"fromRepo\":\"$_repo_self\",\"crossCallsites\":$n,\"risk\":\"$risk\",\"session\":\"$(henaxis_ses 2>/dev/null || echo cli)\"}"
    done
  fi
done

[ "$warned" -eq 1 ] && {
  echo "  → verifica quei chiamanti prima di cambiare firma/semantica; se serve, coordina via news (AP-046)." >&2
  echo "  → dettaglio completo: tools/hm-impact.sh - <simbolo>" >&2
}

if [ "$hard" -eq 1 ]; then
  if [ -n "${IMPACT_GATE_OVERRIDE:-}" ]; then
    echo "" >&2
    echo "⚠ Gate U2 ALTO RISCHIO — ECCEZIONE CONSAPEVOLE (audita): IMPACT_GATE_OVERRIDE=\"$IMPACT_GATE_OVERRIDE\"" >&2
    exit 0
  fi
  echo "" >&2
  echo "✗ COMMIT BLOCCATO — Gate U2 alto rischio (AP-059):$hard_syms ha ≥2 call-site CROSS-REPO a confidenza ALTA." >&2
  echo "  Perché: cambiare firma/semantica qui rompe silenziosamente altri repo — nessun test locale lo vede." >&2
  echo "  Fix: verifica ogni call-site (tools/hm-impact.sh - <simbolo>), aggiorna i chiamanti o coordina (news/presidio, AP-046)." >&2
  echo "  Eccezione consapevole: IMPACT_GATE_OVERRIDE=\"<motivo>\" git commit ..." >&2
  exit 1
fi
exit 0   # rischio basso/warning: mai bloccare (resta A+B per il caso comune)
