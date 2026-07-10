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
  # Coordinamento event-driven: bus MQTT (MQTT.md). L'auto-arm di un processo listener bash-side
  # è STATO RIMOSSO in F5 (2026-07-10) — l'unico meccanismo che funzionava davvero era il Monitor
  # agentico (armato più sotto via istruzione esplicita), non lo script coord-listen.mjs in sé, che
  # comunque richiedeva un'azione agentica per essere osservato (AP-062, "un hook non può armare un
  # Monitor per conto della sessione"). Il bus sostituisce anche l'auto-morte/pidfile/session.pid.
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

# COORDINAMENTO (AP-062, bus MQTT — SoT playbook MQTT.md, cutover F5 completato 2026-07-10): nessun
# hook bash può armare un Monitor per conto della sessione (è un'azione agentica) — SENZA, i diretti
# restano invisibili finché l'umano non lo dice a voce (lo stesso polling-mascherato-da-evento che
# AP-062 vieta). Istruzione diretta alla sessione, non solo un promemoria: verificalo ORA. Un
# guardiano leggero in news-hook.sh (ogni prompt) segnala se la presenza retained risulta offline.
if [ "${#HEX}" -eq 8 ]; then
  if command -v mosquitto_sub >/dev/null 2>&1 && [ -r "$HOME/.config/henaxis/mqtt.env" ]; then
    # Il Monitor si abbona DIRETTAMENTE al broker: ogni messaggio è una notifica nativa, QoS1+
    # sessione persistente = recapito anche di ciò che è arrivato mentre eri giù, Last-Will =
    # presenza gestita dal protocollo (niente lifecycle da curare).
    coord_note="COORDINAMENTO (AP-062, bus MQTT — SoT playbook MQTT.md): DEVI armare tu ora un Monitor persistente abbonato al bus (azione agentica, nessun hook puo' farlo per te). Prima TaskList (se gia' attivo non riarmarlo); poi Monitor con lo script d'abbonamento documentato in MQTT.md sezione 'Come una SESSIONE si abbona' (mosquitto_sub sui topic henaxis/news/directed/$HEX, henaxis/coord/escalation/$HEX e henaxis/impact/$repo — quest'ultimo ti avvisa se un ALTRO repo tocca un simbolo condiviso che impatta $repo, Atlante/impact-gate.sh — credenziali da ~/.config/henaxis/mqtt.env, presenza LWT su henaxis/presence/$HEX, failover locale→remoto). Senza, i diretti delle altre sessioni e gli impatti cross-repo sul tuo restano invisibili finche' l'umano non te lo dice a voce."
  else
    coord_note="COORDINAMENTO (AP-062): mosquitto/credenziali MQTT non disponibili su questa macchina (~/.config/henaxis/mqtt.env mancante o mosquitto-clients non installato) — il coordinamento event-driven resta inattivo qui; i diretti restano visibili comunque nella news injection ad ogni prompt (record git, mai perso)."
  fi
else
  coord_note="COORDINAMENTO (AP-062): listener event-driven disponibile ma CLAUDE_CODE_SESSION_ID assente in questa esecuzione, niente hex8 su cui armare il Monitor."
fi
ctx="Repo '$repo' agganciato a henaxis-playbook ($ver). Leggi l'orientamento in cima a CLAUDE.md: usa PLAYBOOK (regole+strumenti) E docs/ (modulo, Serena, finalita) — ENTRAMBI, non sottovalutarne nessuno. Serena MCP attivo per i simboli del codice (usa i tool serena invece di leggere a tappeto). Deleghe agentiche solo con TASK-CONTRACT; PROD e' fonte di verita (AP-040, gate umano). PRESIDIO (AP-046): heartbeat AUTO eseguito per '$repo'; prima di IMPLEMENTARE in un ALTRO progetto verifica il presidio (tools/presence.sh check <repo>); se e' di un'altra sessione, INSTRADA il lavoro li (news/handoff). Nomina la sessione: tools/presence.sh claim <repo> <nome> (il nome appare sulla board). $coord_note"
# ctx privo di doppi apici/newline -> JSON sicuro senza escaping
printf '{"continue":true,"hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"%s"}}\n' "$ctx"
