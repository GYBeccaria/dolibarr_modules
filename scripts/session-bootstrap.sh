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
HEX=""
if [ -n "${CLAUDE_CODE_SESSION_ID:-}" ]; then
  HEX="$(printf '%s' "$CLAUDE_CODE_SESSION_ID" | tr -cd '0-9a-f' | cut -c1-8)"
  for d in "${HENAXIS_PLAYBOOK:-}" /opt/p2g_dev/henaxis-playbook "$HOME/henaxis-playbook"; do
    [ -n "$d" ] && [ -x "$d/tools/presence.sh" ] && { ( bash "$d/tools/presence.sh" heartbeat "$repo" >/dev/null 2>&1 & ) ; break; }
  done
  # AUTO-ARM del coordinamento event-driven (AP-062, strumento non disciplina): il listener parte
  # da solo — nessuna sessione deve "ricordarsi" di armarlo. Un pidfile evita doppioni al resume/clear;
  # best-effort totale (senza node/token il listener semplicemente non parte, mai blocca l'avvio).
  if [ "${#HEX}" -eq 8 ] && command -v node >/dev/null 2>&1; then
    COORD_DIR="${HENAXIS_COORD_DIR:-$HOME/.coord}/$HEX"
    mkdir -p "$COORD_DIR" 2>/dev/null
    PIDFILE="$COORD_DIR/listener.pid"
    if ! { [ -s "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE" 2>/dev/null)" 2>/dev/null; }; then
      for d in "${HENAXIS_PLAYBOOK:-}" /opt/p2g_dev/henaxis-playbook "$HOME/henaxis-playbook"; do
        if [ -n "$d" ] && [ -r "$d/tools/coord-listen.mjs" ]; then
          ( nohup node "$d/tools/coord-listen.mjs" "$HEX" >"$COORD_DIR/listener.log" 2>&1 & echo $! > "$PIDFILE" )
          break
        fi
      done
    fi
  fi
fi
ctx="Repo '$repo' agganciato a henaxis-playbook ($ver). Leggi l'orientamento in cima a CLAUDE.md: usa PLAYBOOK (regole+strumenti) E docs/ (modulo, Serena, finalita) — ENTRAMBI, non sottovalutarne nessuno. Serena MCP attivo per i simboli del codice (usa i tool serena invece di leggere a tappeto). Deleghe agentiche solo con TASK-CONTRACT; PROD e' fonte di verita (AP-040, gate umano). PRESIDIO (AP-046): heartbeat AUTO eseguito per '$repo'; prima di IMPLEMENTARE in un ALTRO progetto verifica il presidio (tools/presence.sh check <repo>); se e' di un'altra sessione, INSTRADA il lavoro li (news/handoff). Nomina la sessione: tools/presence.sh claim <repo> <nome> (il nome appare sulla board). COORDINAMENTO (AP-062): il listener event-driven si e' auto-armato in background (coord-listen.mjs, nessuna azione richiesta); diretti/escalation per te appaiono gia' ad ogni prompt (news-hook), niente da fare per riceverli."
# ctx privo di doppi apici/newline -> JSON sicuro senza escaping
printf '{"continue":true,"hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"%s"}}\n' "$ctx"
