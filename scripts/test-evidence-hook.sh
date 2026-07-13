#!/usr/bin/env bash
# test-evidence-hook.sh — hook pre-push: riporta AUTOMATICAMENTE una battery (hm-test.sh) per il
# commit che si sta per pushare, SE il repo ha un comando registrato in test-commands.json (playbook).
# Nato da: testEvidence si azzera ad OGNI nuovo commit (richiede match sull'HEAD attuale) — uno sweep
# manuale una-tantum torna rosso al primo push successivo, ovunque. Stesso principio "strumento non
# disciplina" applicato oggi a Serena/Gate D1/hm-test.sh (sessione HenaxMetrics, piano Compliance-a-
# verde). Best-effort, MAI bloccante: un report perso o un comando assente non impedisce il push
# (stessa REGOLA D'ORO di hm-test.sh) — un test che FALLISCE viene comunque riportato onestamente
# (no-go), mai nascosto, ma il push prosegue: il gate vero sul deploy resta Gate D1 (battery-gate.sh).
set -uo pipefail

_self="$(readlink -f "$0" 2>/dev/null || echo "$0")"
PLAYBOOK=""
for _pb in "${HENAXIS_PLAYBOOK:-}" "$(dirname "$_self")/.." /opt/henaxis-playbook "$HOME/henaxis-playbook"; do
  [ -n "$_pb" ] && [ -r "$_pb/tools/hm-test.sh" ] && { PLAYBOOK="$_pb"; break; }
done
[ -n "$PLAYBOOK" ] && [ -r "$PLAYBOOK/test-commands.json" ] || exit 0

REPO="$(basename "$(git rev-parse --show-toplevel 2>/dev/null || pwd)")"
SHA="$(git rev-parse HEAD 2>/dev/null || true)"
[ -n "$SHA" ] || exit 0

# Due lookup JSON indipendenti (non un unico output con delimitatore): i comandi registrati
# contengono spesso `|` (pipe di shell) — usarlo come separatore tronca il comando al primo
# pipe interno, bug reale trovato testando l'hook (2026-07-09, es. "find ... | xargs ...").
CMD="$(python3 -c "
import json, sys
try: d = json.load(open(sys.argv[1]))
except Exception: sys.exit(0)
e = d.get(sys.argv[2])
if e and e.get('cmd'): print(e['cmd'])
" "$PLAYBOOK/test-commands.json" "$REPO" 2>/dev/null)"
METH="$(python3 -c "
import json, sys
try: d = json.load(open(sys.argv[1]))
except Exception: sys.exit(0)
e = d.get(sys.argv[2])
print(e.get('methodology', 'unit') if e else '')
" "$PLAYBOOK/test-commands.json" "$REPO" 2>/dev/null)"
[ -n "$METH" ] || METH="unit"
# selfReports=true: il comando riporta GIA' da solo, per-test, via hm-test.sh (es. report-battery.sh
# di henaxmetrics o la test-battery di Domicare) — NON va wrappato, altrimenti si crea un secondo run
# aggregato ridondante accanto a quello ricco. In quel caso il hook lo esegue e basta; l'evidenza
# per-test la scrive il comando. (2026-07-10: aggiunto per non ridurre 136 test a 2 righe opache.)
SELF="$(python3 -c "
import json, sys
try: d = json.load(open(sys.argv[1]))
except Exception: sys.exit(0)
e = d.get(sys.argv[2])
print('1' if e and e.get('selfReports') else '')
" "$PLAYBOOK/test-commands.json" "$REPO" 2>/dev/null)"

if [ -z "${CMD:-}" ]; then
  echo "· test-evidence-hook: '$REPO' non ha un comando registrato in test-commands.json (playbook) — nessun report automatico." >&2
  echo "  Registralo lì (repo → {cmd, methodology}) o riporta a mano prima del push: RID=\$(hm-test.sh run-start $REPO dev full)" >&2
  exit 0
fi

if [ "$SELF" = "1" ]; then
  # il comando possiede il proprio run (run-start/test/run-end): eseguilo, non wrapparlo.
  bash -c "$CMD"; RC=$?
  [ "$RC" -eq 0 ] || echo "⚠ test-evidence-hook: '$REPO' — battery self-reporting FALLITA (l'ha riportata lei come no-go, push NON bloccato)." >&2
  exit 0
fi

RID="$(bash "$PLAYBOOK/tools/hm-test.sh" run-start "$REPO" dev full pre-push-hook "$SHA" 2>/dev/null)"
[ -n "$RID" ] || exit 0
bash "$PLAYBOOK/tools/hm-test.sh" wrap "$RID" "pre-push auto ($METH)" "$METH" -- bash -c "$CMD"
RC=$?
bash "$PLAYBOOK/tools/hm-test.sh" run-end "$RID" "$([ "$RC" -eq 0 ] && echo go || echo no-go)" >/dev/null 2>&1
[ "$RC" -eq 0 ] || echo "⚠ test-evidence-hook: '$REPO' — comando registrato FALLITO (riportato no-go onestamente, push NON bloccato)." >&2
exit 0
