#!/usr/bin/env bash
# session-bootstrap.sh — hook SessionStart di Claude Code. A OGNI sessione (start/resume/clear):
#  1) ri-sincronizza CLAUDE.md dal playbook (chiude la finestra di divergenza; idempotente)
#  2) inietta un orientamento conciso come additionalContext (così l'AI è orientata a prescindere)
# Output: JSON SessionStart costruito con printf (nessuna dipendenza da jq — AP-028).
set -uo pipefail
[ -x scripts/sync-claude-shared.sh ] && [ -e docs/CLAUDE-core.md ] && bash scripts/sync-claude-shared.sh >/dev/null 2>&1 || true
ver=$(grep -oE 'version: [0-9.]+' docs/CLAUDE-core.md 2>/dev/null | head -1)
repo=$(basename "$(pwd)")
# AUTO-HEARTBEAT presidio (strumento > disciplina, AP-006/046): rinfresca la presence della sessione
# in background, best-effort (mai blocca l'avvio). Non ruba presidi altrui attivi (semantica heartbeat).
# Playbook config-driven (portabilità): env > ~/.config/henaxis/config.env > path convenzionali.
[ -z "${HENAXIS_PLAYBOOK:-}" ] && [ -r "$HOME/.config/henaxis/config.env" ] && \
  HENAXIS_PLAYBOOK=$(sed -n -E 's/^(export[[:space:]]+)?HENAXIS_PLAYBOOK=["'"'"']?([^"'"'"' ]+).*/\2/p' "$HOME/.config/henaxis/config.env" | tail -1)
if [ -n "${CLAUDE_CODE_SESSION_ID:-}" ]; then
  for d in "${HENAXIS_PLAYBOOK:-}" /opt/p2g_dev/henaxis-playbook "$HOME/henaxis-playbook"; do
    [ -n "$d" ] && [ -x "$d/tools/presence.sh" ] && { ( bash "$d/tools/presence.sh" heartbeat "$repo" >/dev/null 2>&1 & ) ; break; }
  done
fi
ctx="Repo '$repo' agganciato a henaxis-playbook ($ver). Leggi l'orientamento in cima a CLAUDE.md: usa PLAYBOOK (regole+strumenti) E docs/ (modulo, Serena, finalita) — ENTRAMBI, non sottovalutarne nessuno. Serena MCP attivo per i simboli del codice (usa i tool serena invece di leggere a tappeto). Deleghe agentiche solo con TASK-CONTRACT; PROD e' fonte di verita (AP-040, gate umano). PRESIDIO (AP-046): heartbeat AUTO eseguito per '$repo'; prima di IMPLEMENTARE in un ALTRO progetto verifica il presidio (tools/presence.sh check <repo>); se e' di un'altra sessione, INSTRADA il lavoro li (news/handoff). Nomina la sessione: tools/presence.sh claim <repo> <nome> (il nome appare sulla board)."
# ctx privo di doppi apici/newline -> JSON sicuro senza escaping
printf '{"continue":true,"hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"%s"}}\n' "$ctx"
