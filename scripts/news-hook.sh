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
for d in "${HENAXIS_PLAYBOOK:-}" /opt/p2g_dev/henaxis-playbook "$HOME/henaxis-playbook"; do
 [ -n "$d" ] && [ -d "$d/.git" ] && { PB="$d"; break; }
done
[ -z "$PB" ] && exit 0
git -C "$PB" fetch -q origin news 2>/dev/null || true
T=$(mktemp); git -C "$PB" show origin/news:news.json > "$T" 2>/dev/null

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

python3 - "$T" "$MQTT_WARN" <<'PY' 2>/dev/null
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
mqtt_warn = sys.argv[2] if len(sys.argv) > 2 else ""
if mqtt_warn:
    parts.append("⚠ COORDINAMENTO (AP-062): " + mqtt_warn)
if not parts: sys.exit(0)
print(json.dumps({"hookSpecificOutput":{"hookEventName":"UserPromptSubmit","additionalContext":"\n\n".join(parts)}}))
PY
rm -f "$T"
