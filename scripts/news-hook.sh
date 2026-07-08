#!/usr/bin/env bash
# news-hook.sh — UserPromptSubmit: inietta news dal branch 'news' (git show → temp file → python).
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
python3 - "$T" <<'PY' 2>/dev/null
import json,sys
from datetime import datetime,timezone
try: from zoneinfo import ZoneInfo; TZ=ZoneInfo("Europe/Rome")
except Exception: TZ=None
try: d=json.load(open(sys.argv[1]))
except: sys.exit(0)
if not d: sys.exit(0)
# ts salvato in UTC (canonico, cross-macchina) → mostrato in ora ITALIANA (Europe/Rome): niente "T",
# così combacia con l'orologio a muro e non si legge l'UTC come locale (AP-036).
def loc(ts):
 try:
  dt=datetime.strptime(str(ts)[:19],"%Y-%m-%dT%H:%M:%S").replace(tzinfo=timezone.utc)
  return (dt.astimezone(TZ) if TZ else dt).strftime("%Y-%m-%d %H:%M")
 except Exception: return str(ts)[:16]
# Summary troncati (costo-contesto: iniettati a OGNI prompt): il dettaglio si legge con news.sh recent.
def cut(s,n=220): return s if len(s)<=n else s[:n].rstrip()+"…"
lines=[f"[{loc(e.get('ts',''))}] {e.get('machine','?')}/{e.get('session','?')} {cut(e.get('type',''),12)}: {cut(e.get('summary',''))}" for e in d[-8:]]
print(json.dumps({"hookSpecificOutput":{"hookEventName":"UserPromptSubmit","additionalContext":"NEWS cross-sessione (branch news, troncate a 220 char — dettaglio: tools/news.sh recent):\n"+"\n".join(lines)}}))
PY
rm -f "$T"
