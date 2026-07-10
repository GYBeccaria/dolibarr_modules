#!/usr/bin/env bash
# news-hook.sh — UserPromptSubmit: inietta news dal branch 'news' (git show → temp file → python)
# + DRENA l'inbox del coordinamento event-driven (AP-062/063): diretti/escalation per QUESTA sessione,
# scritti da coord-listen.mjs in ~/.coord/<hex8>/inbox.jsonl, appaiono qui ad OGNI prompt — zero
# scelta della sessione (livello "automatico per costruzione", non un Monitor da armare a mano).
# L'inbox viene svuotata dopo la lettura (mailbox: se arrivano eventi tra un prompt e l'altro,
# compaiono al prossimo; il RECORD resta comunque la news, questo è solo un promemoria locale).
set -uo pipefail
# Playbook config-driven (portabilità): env > ~/.config/henaxis/config.env > path convenzionali.
[ -z "${HENAXIS_PLAYBOOK:-}" ] && [ -r "$HOME/.config/henaxis/config.env" ] && \
  HENAXIS_PLAYBOOK=$(sed -n -E 's/^(export[[:space:]]+)?HENAXIS_PLAYBOOK=["'"'"']?([^"'"'"' ]+).*/\2/p' "$HOME/.config/henaxis/config.env" | tail -1)
PB=""
for d in "${HENAXIS_PLAYBOOK:-}" /opt/p2g_dev/henaxis-playbook "$HOME/henaxis-playbook"; do
 [ -n "$d" ] && [ -d "$d/.git" ] && { PB="$d"; break; }
done
[ -z "$PB" ] && exit 0
git -C "$PB" fetch -q origin news 2>/dev/null || true
T=$(mktemp); git -C "$PB" show origin/news:news.json > "$T" 2>/dev/null

COORD_DIR="${HENAXIS_COORD_DIR:-$HOME/.coord}"
HEX="$(printf '%s' "${CLAUDE_CODE_SESSION_ID:-}" | tr -cd '0-9a-f' | cut -c1-8)"
INBOX=""
[ "${#HEX}" -eq 8 ] && [ -s "$COORD_DIR/$HEX/inbox.jsonl" ] && INBOX="$COORD_DIR/$HEX/inbox.jsonl"

python3 - "$T" "$INBOX" <<'PY' 2>/dev/null
import json,sys
from datetime import datetime,timezone
try: from zoneinfo import ZoneInfo; TZ=ZoneInfo("Europe/Rome")
except Exception: TZ=None
try: d=json.load(open(sys.argv[1]))
except: d=[]
# ts salvato in UTC (canonico, cross-macchina) → mostrato in ora ITALIANA (Europe/Rome): niente "T",
# così combacia con l'orologio a muro e non si legge l'UTC come locale (AP-036).
def loc(ts):
 try:
  dt=datetime.strptime(str(ts)[:19],"%Y-%m-%dT%H:%M:%S").replace(tzinfo=timezone.utc)
  return (dt.astimezone(TZ) if TZ else dt).strftime("%Y-%m-%d %H:%M")
 except Exception: return str(ts)[:16]
def cut(s,n=220): return s if len(s)<=n else s[:n].rstrip()+"…"
parts=[]
if d:
    lines=[f"[{loc(e.get('ts',''))}] {e.get('machine','?')}/{e.get('session','?')} {cut(e.get('type',''),12)}: {cut(e.get('summary',''))}" for e in d[-8:]]
    parts.append("NEWS cross-sessione (branch news, troncate a 220 char — dettaglio: tools/news.sh recent):\n"+"\n".join(lines))
    # Promemoria della regola PRIMA di scrivere, non dopo un rifiuto: il gate di news.sh respinge
    # (non tronca) una sintesi >500 char — richiesto esplicitamente 2026-07-10, un taglio automatico
    # rischia di perdere info essenziale o creare ambiguità. Fix del pattern "scrivo lungo → rifiutato
    # → riscrivo" ripetuto su OGNI sessione (AP-006/AP-074): la regola va vista qui, a monte, non
    # scoperta dopo aver già composto il testo.
    parts.append("Per scrivere: news.sh post|to <tipo> \"<sintesi>\" [refs] — sintesi ≤500 char DAVVERO (il gate RIFIUTA, non tronca: scegli tu cosa conta, dettaglio esteso in refs/docs/handoff).")
inbox_path = sys.argv[2]
if inbox_path:
    try: raw=[json.loads(l) for l in open(inbox_path) if l.strip()]
    except Exception: raw=[]
    if raw:
        ilines=[f"  {e.get('kind','evento')} da {e.get('session','?')} — {e.get('eid') or e.get('resource') or ''} ({str(e.get('seenAt',''))[:16]})" for e in raw[-10:]]
        parts.append("COORDINAMENTO per te (AP-060/062, drenato dall'inbox — news.sh mine per il dettaglio):\n"+"\n".join(ilines))
if not parts: sys.exit(0)
print(json.dumps({"hookSpecificOutput":{"hookEventName":"UserPromptSubmit","additionalContext":"\n\n".join(parts)}}))
PY
rm -f "$T"
[ -n "$INBOX" ] && : > "$INBOX" 2>/dev/null   # drena: consumato, riparte vuoto (best-effort, mai bloccante)
