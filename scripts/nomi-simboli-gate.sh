#!/usr/bin/env bash
# nomi-simboli-gate.sh — nessun simbolo di MODULO sotto i 3 caratteri (AP-086).
#
# Perché è un gate e non una convenzione: gli strumenti di analisi d'impatto del parco
# (Atlante/codemappa, hm-impact) RIFIUTANO i simboli sotto i 3 caratteri. Un nome come `r2`
# non è solo poco leggibile: è INVISIBILE a ogni verifica di "chi lo usa". Il 30/07/2026
# `r2` (arrotondamento del denaro in concilia, usato ovunque) è passato sotto ai radar così.
#
# Perimetro: simboli a livello di MODULO (indentazione 0) — quelli che attraversano i
# confini di file e che gli strumenti cercano. Le variabili locali dentro una funzione
# NON sono simboli per il codegraph e restano libere: irrigidirle sarebbe rumore.
#
# Blocca il commit elencando i nomi da correggere (AP-057: cosa, perché, come).
# Promosso dalla sessione concilia (2026-07-30); vendorizzato nei repo da setup-repo.sh.
set -uo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)" || exit 0
command -v python3 >/dev/null 2>&1 || exit 0

# solo i file TS/JS in staging: il gate guarda ciò che stai per introdurre
mapfile -t files < <(git diff --cached --name-only --diff-filter=ACMR -- '*.ts' '*.tsx' '*.mts' '*.js' '*.mjs' 2>/dev/null)
[ "${#files[@]}" -gt 0 ] || exit 0

python3 - "${files[@]}" <<'PY'
import re, sys, pathlib
pat = re.compile(r'^(export\s+)?(?:async\s+)?(?:function|const|let|var|class|interface|type|enum)\s+([A-Za-z_]\w*)')
fuori = []
for nome in sys.argv[1:]:
    f = pathlib.Path(nome)
    if not f.is_file() or any(x in nome for x in ('node_modules', 'dist/')):
        continue
    for i, riga in enumerate(f.read_text(encoding='utf-8', errors='ignore').split('\n'), 1):
        m = pat.match(riga)
        if m and len(m.group(2)) < 3:
            fuori.append((nome, i, m.group(2), 'esportato' if m.group(1) else 'di modulo'))
if not fuori:
    sys.exit(0)
print("", file=sys.stderr)
print("✗ NOMI — simboli di modulo sotto i 3 caratteri (invisibili all'analisi d'impatto, AP-086):", file=sys.stderr)
for nome, riga, simbolo, tipo in fuori:
    print(f"   {nome}:{riga}  «{simbolo}»  ({tipo})", file=sys.stderr)
print("", file=sys.stderr)
print("  perché: Atlante/codemappa e hm-impact RIFIUTANO i simboli < 3 caratteri, quindi", file=sys.stderr)
print("          nessuno strumento può dirti chi li usa. È già costato caro con «r2».", file=sys.stderr)
print("  fix:    dai un nome parlante. Con Serena la rinomina propaga i riferimenti:", file=sys.stderr)
print("          rename_symbol(<nome>, <file>, <nuovo nome>)", file=sys.stderr)
print("  nota:   le variabili LOCALI dentro le funzioni non sono toccate da questo gate.", file=sys.stderr)
print("", file=sys.stderr)
sys.exit(1)
PY
