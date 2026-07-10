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
    # session.pid = PID del processo `claude` di QUESTA sessione (risalgo l'ancestry /proc dal hook):
    # è il segnale di AUTO-MORTE distribuita del listener (coord-listen.mjs esce quando questo PID
    # muore) — niente reaper centrale, niente SPOF. Best-effort: se non risolvo il PID, non scrivo
    # il file e il listener resta senza auto-morte (fail-safe, comportamento storico).
    _cpid=""; _p="$PPID"
    for _ in 1 2 3 4 5 6 7 8; do
      [ -r "/proc/$_p/comm" ] || break
      [ "$(cat "/proc/$_p/comm" 2>/dev/null)" = "claude" ] && { _cpid="$_p"; break; }
      _p="$(awk '/^PPid:/{print $2}' "/proc/$_p/status" 2>/dev/null)"
      [ -n "$_p" ] && [ "$_p" -gt 1 ] 2>/dev/null || break
    done
    [ -n "$_cpid" ] && printf '%s\n' "$_cpid" > "$COORD_DIR/session.pid" 2>/dev/null
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
# Refresh idempotente del wiring PreToolUse (gate Serena, AP-066) in .claude/settings.json: lo
# script scripts/serena-gate-hook.py arriva via git (commit-fleet, condiviso), ma l'attivazione
# richiede il path ASSOLUTO di QUESTA macchina/checkout — non propagabile via git perché .claude/
# è gitignored apposta (path machine-specific). Prima serviva rilanciare setup-repo.sh a mano per
# ogni repo/macchina dopo ogni propagazione flotta; qui si rigenera ad ogni SessionStart così
# l'attivazione segue lo script senza sweep manuale (strumento > disciplina, AP-006). Tocca SOLO
# la chiave PreToolUse, preserva il resto; se il file manca o è illeggibile non lo tocca (la
# creazione da zero resta compito esplicito di setup-repo.sh — mai ricostruire alla cieca un file
# che possiede anche SessionStart/UserPromptSubmit). Best-effort, mai blocca l'avvio.
if [ -e .claude/settings.json ] && [ -e scripts/serena-gate-hook.py ] && command -v python3 >/dev/null 2>&1; then
  python3 - "$(pwd)/scripts/serena-gate-hook.py" <<'PYEOF' >/dev/null 2>&1 || true
import json, os, sys
abs_path = sys.argv[1]
p = ".claude/settings.json"
with open(p) as f:
    cfg = json.load(f)
cfg.setdefault("hooks", {})
wanted = [{"matcher": "Bash", "hooks": [{"type": "command", "command": f"python3 {abs_path}", "timeout": 8}]}]
if cfg["hooks"].get("PreToolUse") != wanted:
    cfg["hooks"]["PreToolUse"] = wanted
    tmp = p + ".tmp"
    with open(tmp, "w") as f:
        json.dump(cfg, f, indent=2)
    os.replace(tmp, p)
PYEOF
fi

# COORDINAMENTO (AP-062, gap chiuso 2026-07-10): coord-listen.mjs (auto-armato sopra) scrive
# fedelmente il trigger ~/.coord/<hex8>/wake sugli eventi diretti/escalation — ma nessun bash-hook
# puo' armare un Monitor per conto della sessione (e' un'azione agentica, non automatizzabile da
# shell). SENZA quel Monitor esplicito, il file cambia nel vuoto e l'umano deve chiedere a voce
# ("leggi le news") — lo stesso polling-mascherato-da-evento che AP-062 vieta. Istruzione diretta
# alla sessione, non solo un promemoria: verificalo ORA.
if [ "${#HEX}" -eq 8 ]; then
  coord_note="COORDINAMENTO (AP-062): il listener coord-listen.mjs e' auto-armato in background, MA nessun bash-hook puo' armare per te il Monitor che lo osserva — e' un'azione agentica che DEVI fare tu ora: prima TaskList (se un Monitor sul wake trigger e' gia' attivo, non riarmarlo); altrimenti arma un Monitor persistente che polla/tail ~/.coord/$HEX/wake (o inotifywait -m se disponibile) — senza, diretti/escalation restano invisibili finche' l'umano non te lo dice a voce (gap reale gia' successo)."
else
  coord_note="COORDINAMENTO (AP-062): listener event-driven disponibile ma CLAUDE_CODE_SESSION_ID assente in questa esecuzione, niente hex8 su cui armare il Monitor."
fi
ctx="Repo '$repo' agganciato a henaxis-playbook ($ver). Leggi l'orientamento in cima a CLAUDE.md: usa PLAYBOOK (regole+strumenti) E docs/ (modulo, Serena, finalita) — ENTRAMBI, non sottovalutarne nessuno. Serena MCP attivo per i simboli del codice (usa i tool serena invece di leggere a tappeto). Deleghe agentiche solo con TASK-CONTRACT; PROD e' fonte di verita (AP-040, gate umano). PRESIDIO (AP-046): heartbeat AUTO eseguito per '$repo'; prima di IMPLEMENTARE in un ALTRO progetto verifica il presidio (tools/presence.sh check <repo>); se e' di un'altra sessione, INSTRADA il lavoro li (news/handoff). Nomina la sessione: tools/presence.sh claim <repo> <nome> (il nome appare sulla board). $coord_note"
# ctx privo di doppi apici/newline -> JSON sicuro senza escaping
printf '{"continue":true,"hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"%s"}}\n' "$ctx"
