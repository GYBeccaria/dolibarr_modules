#!/usr/bin/env bash
# news-hook.sh — UserPromptSubmit: inietta news dal branch 'news' (git show → temp file → python).
set -uo pipefail
PB=""
for d in "${HENAXIS_PLAYBOOK:-}" /opt/p2g_dev/henaxis-playbook "$HOME/henaxis-playbook"; do
 [ -n "$d" ] && [ -d "$d/.git" ] && { PB="$d"; break; }
done
[ -z "$PB" ] && exit 0
git -C "$PB" fetch -q origin news 2>/dev/null || true
T=$(mktemp); git -C "$PB" show origin/news:news.json > "$T" 2>/dev/null
python3 - "$T" <<'PY' 2>/dev/null
import json,sys
try: d=json.load(open(sys.argv[1]))
except: sys.exit(0)
if not d: sys.exit(0)
# Summary troncati (costo-contesto: iniettati a OGNI prompt): il dettaglio si legge con news.sh recent.
def cut(s,n=220): return s if len(s)<=n else s[:n].rstrip()+"…"
lines=[f"[{e.get('ts','')[:16]}] {e.get('machine','?')}/{e.get('session','?')} {cut(e.get('type',''),12)}: {cut(e.get('summary',''))}" for e in d[-8:]]
print(json.dumps({"hookSpecificOutput":{"hookEventName":"UserPromptSubmit","additionalContext":"NEWS cross-sessione (branch news, troncate a 220 char — dettaglio: tools/news.sh recent):\n"+"\n".join(lines)}}))
PY
rm -f "$T"
