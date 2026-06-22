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
    echo "✗ AP-FORMAT: manca la sezione ANTI-DRIFT CHECK nel commit message."
    echo "  Aggiungila in fondo (vedi COMMIT-CONVENTION.md della playbook)."
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
    echo "✗ Gate 0 sync fallito" >&2; exit 1
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

[ "$FAIL" -eq 1 ] && { echo ""; echo "Pre-commit anti-drift bloccante. Correggi e ritenta."; exit 1; }
exit 0
