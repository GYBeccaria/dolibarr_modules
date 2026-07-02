#!/usr/bin/env bash
# anti-drift-hook.sh — pre-commit + commit-msg, modulare.
#   $1 vuoto       -> pre-commit (Gate 0 sync + gate universali + overlay per-repo)
#   $1 valorizzato -> commit-msg (Gate 3: sezione ANTI-DRIFT CHECK)
# CORE universale qui; i gate PROGETTO/verticale (anglismi, design-token, tsc TS, ...)
# vivono in scripts/playbook-gates.sh del repo (overlay opzionale) — vedi REFINEMENT.md.
set -e
FAIL=0
COMMIT_MSG_FILE="${1:-}"

# --- commit-msg context: Gate 3 ---
if [ -n "$COMMIT_MSG_FILE" ] && [ -f "$COMMIT_MSG_FILE" ]; then
  if ! grep -q "^ANTI-DRIFT CHECK:" "$COMMIT_MSG_FILE"; then
    cat >&2 <<'MSG'
✗ COMMIT RIFIUTATO — manca la sezione ANTI-DRIFT CHECK (obbligatoria, COMMIT-CONVENTION.md).
  Incolla e compila questo blocco IN FONDO al messaggio (prima dei trailer):

ANTI-DRIFT CHECK:
- Valore durevole oltre lo scope minimo: <test/astrazione/cleanup/decisione — o "nessuno", onesto>
- Anti-pattern catalogati passati per veto: <AP-NNN, ... | nessuno>
- Catch onesti presenti in questo commit: <1 riga ciascuno | nessuno>
- Debito tracciato (se scorciatoia presa): <descrizione + dove | nessuno>
MSG
    exit 1
  fi
  # Gate 3b (AP-055): nome sessione obbligatorio — nessun commit anonimo. Trailer 'Session: <nome> (<hex8>)'.
  if ! grep -qiE "^Session:[[:space:]]*[^[:space:]]" "$COMMIT_MSG_FILE"; then
    HEX="$(printf '%.8s' "${CLAUDE_CODE_SESSION_ID:-}")"
    cat >&2 <<MSG
✗ COMMIT RIFIUTATO — manca il trailer 'Session:' = QUALE sessione ha eseguito il commit (AP-055).
  Formato: nome human-friendly ASSEGNATO DALL'UMANO + hex8 tra parentesi. Aggiungi in fondo:

Session: <nome> (${HEX:-<hex8>})

  Il tuo hex8 è: ${HEX:-"sconosciuto (CLAUDE_CODE_SESSION_ID assente)"} — è lo stesso id di news/tasks/presence.
  Se la sessione NON ha ancora un nome: FERMATI e chiedi all'umano di assegnarne uno (mai committare anonimo).
MSG
    exit 1
  fi
  exit 0
fi

# --- pre-commit context ---
# Gate 0: sync CLAUDE.md da docs/CLAUDE-core.md (+ auto-stage se cambiato)
if [ -x "scripts/sync-claude-shared.sh" ] && [ -e "docs/CLAUDE-core.md" ]; then
  if bash scripts/sync-claude-shared.sh; then
    git diff --quiet CLAUDE.md 2>/dev/null || { git add CLAUDE.md; echo "→ Gate 0: CLAUDE.md rigenerato (auto-staged)"; }
  else
    echo "✗ COMMIT BLOCCATO — Gate 0: sync di CLAUDE.md da docs/CLAUDE-core.md fallito." >&2
    echo "  Diagnosi: esegui 'bash scripts/sync-claude-shared.sh' a mano per vedere l'errore" >&2
    echo "  (cause tipiche: marker SHARED mancanti/duplicati in CLAUDE.md, docs/CLAUDE-core.md assente)." >&2
    exit 1
  fi
fi

# Gate U1 (universale, warning): tic verbale di riempimento nei .md (AP-007)
for f in $(git diff --cached --name-only --diff-filter=ACMR 2>/dev/null | grep -E '\.md$' || true); do
  [ -f "$f" ] || continue
  n=$(grep -ciE '\barticol(at[oaie]|ar[ei]|iamo|ando)\b' "$f" 2>/dev/null || true)
  [ -n "$n" ] && [ "$n" -gt 3 ] && echo "⚠ AP-007: 'articolato' >3 in $f ($n) — verifica retorica."
done

# Overlay per-repo: gate specifici della verticalizzazione (TS tsc, brand voice, design token...)
if [ -x "scripts/playbook-gates.sh" ]; then
  if ! bash scripts/playbook-gates.sh; then FAIL=1; fi
fi

[ "$FAIL" -eq 1 ] && { echo ""; echo "✗ COMMIT BLOCCATO dai gate per-repo qui sopra (scripts/playbook-gates.sh): ogni ✗ dice il file e la regola violata — correggi quelli e ritenta."; exit 1; }
exit 0
