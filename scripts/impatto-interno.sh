#!/usr/bin/env bash
# impatto-interno.sh — chi usa, DENTRO questo repo, i simboli condivisi che stai toccando.
#
# Complemento di impact-gate.sh, che guarda il parco repo (AP-059): lì il rischio è rompere
# ALTRI repo, qui è rompere questo. Nato da un caso vero (concilia, 30/07/2026): cambiata la
# semantica di `parseNumero` in shared/calcoli.ts — 24 call-site interni, incluso il parser
# della fattura principale — e nessun gate lo mostrava. I test erano verdi: coprivano la
# funzione, non tutti i suoi consumatori.
#
# NON blocca: informa, elencando i call-site da guardare. Il giudizio resta umano.
# Usa codemappa (Atlante scopre + Serena risolve): se non è disponibile, esce in silenzio.
#
# Perimetro dei file "condivisi": IMPATTO_INTERNO_GLOB (spazio-separato) se impostato,
# altrimenti shared/ — la convenzione del parco per il codice usato da server e client.
set -uo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)" || exit 0

read -r -a PERIMETRO <<< "${IMPATTO_INTERNO_GLOB:-shared/*.ts shared/*.tsx shared/*.mts shared/*.mjs}"
CONDIVISI="$(git diff --cached --name-only --diff-filter=ACMR -- "${PERIMETRO[@]}" 2>/dev/null)"
[ -n "$CONDIVISI" ] || exit 0

CODEMAPPA=""
for d in "${HENAXIS_PLAYBOOK:-}" "$(dirname "$0")/.." /opt/henaxis-playbook "$HOME/henaxis-playbook"; do
  [ -n "$d" ] && [ -r "$d/tools/codemappa.mjs" ] && { CODEMAPPA="$d/tools/codemappa.mjs"; break; }
done
[ -n "$CODEMAPPA" ] || exit 0
command -v node >/dev/null 2>&1 || exit 0

# simboli ESPORTATI toccati dal diff (sono quelli che qualcun altro può usare)
mapfile -t syms < <(
  git diff --cached -U0 --diff-filter=ACMR -- "${PERIMETRO[@]}" 2>/dev/null \
    | grep -E '^[+-]' | grep -vE '^(\+\+\+|---)' \
    | sed -nE 's/^[+-][[:space:]]*export[[:space:]]+(async[[:space:]]+)?(function|const|let|class|interface|type|enum)[[:space:]]+([A-Za-z_][A-Za-z0-9_]*).*/\3/p' \
    | LC_ALL=C sort -u | head -6
)
[ "${#syms[@]}" -gt 0 ] || exit 0

REPO="$(basename "$(pwd)")"
detto=0
for s in "${syms[@]}"; do
  # codemappa/Atlante rifiutano i simboli sotto i 3 caratteri: il salto si DICHIARA (AP-086),
  # mai in silenzio — un simbolo sotto soglia è fuori da OGNI analisi e va rinominato
  # (nomi-simboli-gate.sh blocca i nuovi; questo avviso copre il legacy).
  if [ "${#s}" -lt 3 ]; then
    echo "⚠  impatto-interno: «$s» è sotto i 3 caratteri — INVISIBILE all'analisi d'impatto (AP-086). Rinominalo (Serena propaga i riferimenti)." >&2
    continue
  fi
  out="$(timeout 60 node "$CODEMAPPA" "$REPO" "$s" 2>/dev/null | head -2)" || continue
  n="$(printf '%s' "$out" | sed -nE 's/.*confermati: ([0-9]+).*/\1/p')"
  [ -n "$n" ] && [ "$n" -gt 0 ] 2>/dev/null || continue
  if [ "$detto" -eq 0 ]; then
    echo "" >&2
    echo "ℹ  stai modificando simboli CONDIVISI — chi li usa in questo repo:" >&2
    detto=1
  fi
  echo "   • $s → $n call-site confermati" >&2
done
[ "$detto" -eq 1 ] && {
  echo "   verifica che il cambiamento regga per TUTTI (i test coprono la funzione," >&2
  echo "   non è detto che coprano ogni consumatore). Dettaglio:" >&2
  echo "     node $CODEMAPPA $REPO <simbolo>" >&2
  echo "" >&2
}
exit 0
