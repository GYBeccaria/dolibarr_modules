#!/usr/bin/env bash
# news-hook.sh — hook UserPromptSubmit: inietta le news cross-sessione recenti.
set -uo pipefail
PB=""
for d in "${HENAXIS_PLAYBOOK:-}" /opt/p2g_dev/henaxis-playbook "$HOME/henaxis-playbook" /tmp/henaxis-playbook; do
 [ -n "$d" ] && [ -f "$d/news.json" ] && { PB="$d"; break; }
done
[ -z "$PB" ] && exit 0
python3 - "$PB/news.json" <<'PY' 2>/dev/null
import json,sys
try: d=json.load(open(sys.argv[1]))
except: d=[]
if not d: sys.exit(0)
lines=[f"[{e.get('ts','')[:16]}] {e.get('machine','?')}/{e.get('session','?')}: {e.get('summary','')}" for e in d[-8:]]
print(json.dumps({"hookSpecificOutput":{"hookEventName":"UserPromptSubmit","additionalContext":"NEWS cross-sessione (playbook/news.json, leggi prima di agire):\n"+"\n".join(lines)}}))
PY
