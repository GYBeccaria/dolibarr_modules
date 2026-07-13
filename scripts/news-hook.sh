#!/usr/bin/env bash
# news-hook.sh — UserPromptSubmit: inietta news dal branch 'news' (git show → temp file → python).
# Il coordinamento event-driven (diretti/escalation) è sul bus MQTT (MQTT.md) — la sessione si
# abbona con un Monitor agentico (armato al SessionStart, orientamento in session-bootstrap.sh);
# questo hook non lo gestisce più (F5, 2026-07-10: rimossi self-heal/session.pid/inbox legacy,
# cutover confermato da tutte le sessioni attive). Resta un guardiano leggero: se la presenza
# retained della sessione risulta offline, lo ricorda qui — a costo quasi zero (query locale,
# timeout breve, mai blocca il prompt).
set -uo pipefail
# Playbook config-driven (portabilità): env > ~/.config/henaxis/config.env > path convenzionali.
[ -z "${HENAXIS_PLAYBOOK:-}" ] && [ -r "$HOME/.config/henaxis/config.env" ] && \
  HENAXIS_PLAYBOOK=$(sed -n -E 's/^(export[[:space:]]+)?HENAXIS_PLAYBOOK=["'"'"']?([^"'"'"' ]+).*/\2/p' "$HOME/.config/henaxis/config.env" | tail -1)
PB=""
for d in "${HENAXIS_PLAYBOOK:-}" /opt/henaxis-playbook "$HOME/henaxis-playbook"; do
 [ -n "$d" ] && [ -d "$d/.git" ] && { PB="$d"; break; }
done
[ -z "$PB" ] && exit 0
git -C "$PB" fetch -q origin news 2>/dev/null || true
T=$(mktemp); git -C "$PB" show origin/news:news.json > "$T" 2>/dev/null
P=$(mktemp); git -C "$PB" show origin/news:presence.json > "$P" 2>/dev/null

# Guardiano presenza MQTT (best-effort, timeout breve): la sessione DEVE avere un Monitor armato
# sul bus (session-bootstrap.sh la istruisce a SessionStart) — se per qualunque motivo è morto a
# metà sessione (visto succedere: compattazione contesto, kill esterno), qui lo si scopre e lo si
# ricorda SENZA riarmarlo da soli (armare un Monitor è un'azione agentica, un hook bash non può
# farlo — stesso limite di sempre, MQTT.md).
MQTT_WARN=""
HEX="$(printf '%s' "${CLAUDE_CODE_SESSION_ID:-}" | tr -cd '0-9a-f' | cut -c1-8)"
if [ "${#HEX}" -eq 8 ] && command -v mosquitto_sub >/dev/null 2>&1 && [ -r "$HOME/.config/henaxis/mqtt.env" ]; then
  # shellcheck disable=SC1091
  . "$HOME/.config/henaxis/mqtt.env"
  if [ -n "${MQTT_USER:-}" ] && [ -n "${MQTT_PASS:-}" ]; then
    LHOST="${MQTT_LOCAL%%:*}"; LPORT="${MQTT_LOCAL##*:}"
    STATE=$(timeout 2 mosquitto_sub -h "${LHOST:-localhost}" -p "${LPORT:-1883}" -u "$MQTT_USER" -P "$MQTT_PASS" -t "henaxis/presence/$HEX" -C 1 -W 1 2>/dev/null)
    case "$STATE" in
      *online*) : ;;  # tutto ok, niente da dire
      *) MQTT_WARN="Il tuo Monitor MQTT risulta OFFLINE (henaxis/presence/$HEX) — riarmalo (MQTT.md, sezione abbonamento sessione): TaskList prima per non duplicarlo." ;;
    esac
  fi
fi

# Cursore per-sessione (locale, MAI su git): "ultima news vista" — senza, l'hook mostrava sempre
# e solo le ultime 8, punto. Con 200 entry cap condivise fra TUTTE le sessioni/repo, una sessione
# ferma qualche ora in un giorno intenso tornava e perdeva tutto ciò che era scorso in mezzo,
# senza saperlo (nessun modo di accorgersi del buco). Locale perché è stato di lettura PER
# SESSIONE PER MACCHINA, non conoscenza condivisa: scriverlo su origin/news aggiungerebbe lock/push
# a ogni singolo prompt sullo stesso file conteso dalle scritture reali (AP-006: lo strumento
# giusto, non il primo che gira). Richiesto dall'umano 2026-07-12 dopo "8 non sono poche?".
CURDIR="$HOME/.cache/henaxis/news-cursor"
CURFILE="$CURDIR/${HEX:-nohex}"
mkdir -p "$CURDIR" 2>/dev/null

python3 - "$T" "$MQTT_WARN" "$P" "$CURFILE" "$HEX" <<'PY' 2>/dev/null
import json,os,sys
from datetime import datetime,timezone
try: from zoneinfo import ZoneInfo; TZ=ZoneInfo("Europe/Rome")
except Exception: TZ=None
try: d=json.load(open(sys.argv[1]))
except: d=[]
curfile=sys.argv[4]
ME=sys.argv[5]
# Rilevanza (suggerita dall'umano 2026-07-12): un diretto ad UN'ALTRA sessione non è rumore da
# filtrare col tempo, è proprio NON per questa sessione — mostrarlo consuma il tetto-30 di
# recupero senza motivo. Stesso criterio di `news.sh mine`: broadcast (senza `to`) sempre
# visibili a tutti, diretti/escalation SOLO se `to` combacia con questo hex8.
def relevant(e):
    to = e.get('to')
    return (not to) or (to == ME)
d = [e for e in d if relevant(e)]
# ts salvato in UTC (canonico, cross-macchina) → mostrato in ora ITALIANA (Europe/Rome): niente "T",
# così combacia con l'orologio a muro e non si legge l'UTC come locale (AP-036).
def loc(ts):
 try:
  dt=datetime.strptime(str(ts)[:19],"%Y-%m-%dT%H:%M:%S").replace(tzinfo=timezone.utc)
  return (dt.astimezone(TZ) if TZ else dt).strftime("%Y-%m-%d %H:%M")
 except Exception: return str(ts)[:16]
def cut(s,n=220): return s if len(s)<=n else s[:n].rstrip()+"…"
def load_names(path):
  # hex8 → nome umano (label del claim presence più recente, AP-058: l'hex8 non è mai il
  # display primario, solo la chiave tecnica). Fallback: hex8 stesso se ignoto.
  try: pj=json.load(open(path))
  except Exception: return {}
  best={}
  for proj,c in pj.items():
    sid=c.get('session'); lbl=c.get('label') or proj
    if not sid: continue
    ls=c.get('last_seen') or c.get('claimed_at') or ''
    if sid not in best or ls>best[sid][1]: best[sid]=(lbl,ls)
  return {k:v[0] for k,v in best.items()}
NAMES=load_names(sys.argv[3])
def who(sid):
  n=NAMES.get(sid)
  return f"{n} ({sid})" if n and n!=sid else (sid or '?')

MAX_BURST=30  # tetto al recupero dopo un'assenza lunga — mai un dump silenzioso (no silent caps)
try: last_seen=open(curfile,encoding='utf-8').read().strip()
except Exception: last_seen=None

if last_seen is None:
    # prima volta per questa sessione (o cache locale persa): stesso comportamento di sempre,
    # niente replay retroattivo — il cursore parte da ORA (dall'ultima entry vista), non da zero.
    shown=d[-8:]
    header="NEWS cross-sessione (branch news, troncate a 220 char — dettaglio: tools/news.sh recent):"
    omitted=0
else:
    new=[e for e in d if str(e.get('ts',''))>last_seen]
    omitted=max(0,len(new)-MAX_BURST)
    shown=new[-MAX_BURST:]
    header="NEWS nuove da quando ti sei fermata/o (cursore per-sessione):" if shown else None

parts=[]
if shown:
    lines=[f"[{loc(e.get('ts',''))}] {e.get('machine','?')}/{who(e.get('session','?'))} {cut(e.get('type',''),12)}: {cut(e.get('summary',''))}" for e in shown]
    text=header+"\n"+"\n".join(lines)
    if omitted:
        text+=f"\n… +{omitted} altre non mostrate (tetto {MAX_BURST}): tools/news.sh recent {omitted+MAX_BURST}"
    parts.append(text)
    parts.append("Per scrivere: news.sh post|to <tipo> \"<sintesi>\" [refs] — sintesi ≤500 char DAVVERO (il gate RIFIUTA, non tronca: scegli tu cosa conta, dettaglio esteso in refs/docs/handoff).")

# Avanza il cursore alla entry più recente VISTA (non a "adesso" — evita di saltare entry pushate
# nella finestra fetch→scrittura cursore, anche se la finestra è di pochi ms).
if d:
    newest=max(str(e.get('ts','')) for e in d)
    try:
        os.makedirs(os.path.dirname(curfile), exist_ok=True)
        open(curfile,'w',encoding='utf-8').write(newest)
    except Exception: pass

mqtt_warn = sys.argv[2] if len(sys.argv) > 2 else ""
if mqtt_warn:
    parts.append("⚠ COORDINAMENTO (AP-062): " + mqtt_warn)
if not parts: sys.exit(0)
print(json.dumps({"hookSpecificOutput":{"hookEventName":"UserPromptSubmit","additionalContext":"\n\n".join(parts)}}))
PY
rm -f "$T" "$P"
