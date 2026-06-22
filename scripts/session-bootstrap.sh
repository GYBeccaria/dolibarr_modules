#!/usr/bin/env bash
# session-bootstrap.sh — hook SessionStart di Claude Code. A OGNI sessione (start/resume/clear):
#  1) ri-sincronizza CLAUDE.md dal playbook (chiude la finestra di divergenza; idempotente)
#  2) inietta un orientamento conciso come additionalContext (così l'AI è orientata a prescindere)
# Output: JSON SessionStart costruito con printf (nessuna dipendenza da jq — AP-028).
set -uo pipefail
[ -x scripts/sync-claude-shared.sh ] && [ -e docs/CLAUDE-core.md ] && bash scripts/sync-claude-shared.sh >/dev/null 2>&1 || true
ver=$(grep -oE 'version: [0-9.]+' docs/CLAUDE-core.md 2>/dev/null | head -1)
repo=$(basename "$(pwd)")
ctx="Repo '$repo' agganciato a henaxis-playbook ($ver). Leggi l'orientamento in cima a CLAUDE.md: usa PLAYBOOK (regole+strumenti) E docs/ (modulo, Serena, finalita) — ENTRAMBI, non sottovalutarne nessuno. Serena MCP attivo per i simboli del codice (usa i tool serena invece di leggere a tappeto). Deleghe agentiche solo con TASK-CONTRACT; PROD e' fonte di verita (AP-040, gate umano)."
# ctx privo di doppi apici/newline -> JSON sicuro senza escaping
printf '{"continue":true,"hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"%s"}}\n' "$ctx"
