#!/usr/bin/env bash
# news-hook.sh — UserPromptSubmit: inietta news dal branch 'news' (git show → temp file → python).
# Il coordinamento event-driven (diretti/escalation) è sul bus MQTT (MQTT.md) — la sessione si
# abbona con un Monitor agentico (armato al SessionStart, orientamento in session-bootstrap.sh);
# questo hook non lo gestisce più (F5, 2026-07-10: rimossi self-heal/session.pid/inbox legacy,
# cutover confermato da tutte le sessioni attive). Resta un guardiano leggero: se la presenza
# retained della sessione risulta offline, lo ricorda qui — a costo quasi zero (query locale,
# timeout breve, mai blocca il prompt).
set -uo pipefail
# Payload UserPromptSubmit da stdin (prompt + cwd): serve al PROTOCOLLO DI RISVEGLIO (2026-07-14,
# direttiva umana: "lanciare la sessione e dire semplicemente: aggiornati... tutto il resto
# dev'essere già noto"). Un prompt minimo di risveglio fa iniettare all'hook TUTTO l'orientamento
# (twin, presidio, diretti, handoff) + l'ordine di agire — strumento, non disciplina (AP-006).
PAY=$(mktemp); cat > "$PAY" 2>/dev/null || true
# Playbook config-driven (portabilità): env > ~/.config/henaxis/config.env > path convenzionali.
[ -z "${HENAXIS_PLAYBOOK:-}" ] && [ -r "$HOME/.config/henaxis/config.env" ] && \
  HENAXIS_PLAYBOOK=$(sed -n -E 's/^(export[[:space:]]+)?HENAXIS_PLAYBOOK=["'"'"']?([^"'"'"' ]+).*/\2/p' "$HOME/.config/henaxis/config.env" | tail -1)
PB=""
# SOLO i percorsi di CONVENZIONE (umano, 13/09): la playbook e' UNA e sta in /opt/henaxis-playbook su
# ogni macchina (HENAXIS_PLAYBOOK esplicito = override dichiarato dall'umano). MAI un glob: questo hook
# ESEGUE gli script della cartella trovata a ogni prompt — fidarsi di una directory per il NOME e'
# AP-092 nel punto piu' sensibile della flotta (violazione d'integrita' introdotta e revertita il 13/09).
# Due collocazioni di convenzione, deterministiche: /opt (root) e $HOME/opt (senza root — ordine
# dell'umano 13/09 20:27 per le macchine dove non ha sudo a portata). MAI glob. Identita' verificata sotto.
for d in "${HENAXIS_PLAYBOOK:-}" /opt/henaxis-playbook "$HOME/opt/henaxis-playbook"; do
 [ -n "$d" ] && [ -d "$d/.git" ] && { PB="$d"; break; }
done
# IDENTITA', non nome: prima di eseguire qualunque script, il clone deve essere QUELLO canonico.
CANON_REMOTE="https://github.com/GYBeccaria/henaxis-playbook.git"
if [ -n "$PB" ]; then
  _remote="$(git -C "$PB" remote get-url origin 2>/dev/null || echo '<nessun remote>')"
  if [ "${_remote%.git}" != "${CANON_REMOTE%.git}" ]; then
    printf '{"hookSpecificOutput":{"hookEventName":"UserPromptSubmit","additionalContext":"⛔ PLAYBOOK NON CANONICA in %s: remote origin = %s, atteso %s. NON eseguo nulla da quella cartella: la POSTA NON e'"'"' stata controllata. Sostituisci il clone con quello canonico."}}\n' "$PB" "$_remote" "$CANON_REMOTE"
    exit 0
  fi
fi
if [ -z "$PB" ]; then
  # AP-100 (fobos, 13/09): «non ho trovato la playbook» usciva 0 MUTO, indistinguibile da «nessuna
  # news» — su fobos l'hook non ha MAI consegnato posta. Ora lo DICHIARA nel prompt ed esce 0
  # (un exit !=0 bloccherebbe il prompt: il silenzio va rotto, non la sessione).
  printf '{"hookSpecificOutput":{"hookEventName":"UserPromptSubmit","additionalContext":"⚠ PLAYBOOK NON TROVATA (cercata in: HENAXIS_PLAYBOOK=%s, /opt/henaxis-playbook, ~/opt/henaxis-playbook): la POSTA NON e'"'"' stata controllata. Il clone canonico va in /opt/henaxis-playbook (o ~/opt/henaxis-playbook dove manca root)."}}\n' "${HENAXIS_PLAYBOOK:-<vuota>}"
  exit 0
fi
git -C "$PB" fetch -q origin news 2>/dev/null || true
# IL GIUNTO (news v2): vista fusa da news-vista.py, mai file diretti (stderr nel log del segnale)
# AP-057 (13/09): SEGNO era usato qui ma MAI definito in questo file (solo in news-hook-utente.sh):
# sotto set -u l'hook moriva a questa riga a OGNI prompt, prima della vista = nessuna news, in silenzio.
SEGNO="${SEGNO:-$HOME/.cache/henaxis/news-hook/${CLAUDE_CODE_SESSION_ID:0:8}}"; mkdir -p "$(dirname "$SEGNO")" 2>/dev/null || true
T=$(mktemp); python3 "$PB/tools/news-vista.py" --ref origin/news > "$T" 2>>"$SEGNO.vista-log"
P=$(mktemp); git -C "$PB" show origin/news:presence.json > "$P" 2>/dev/null

# Guardiano presenza MQTT (best-effort, timeout breve): la sessione DEVE avere un Monitor armato
# sul bus (session-bootstrap.sh la istruisce a SessionStart) — se per qualunque motivo è morto a
# metà sessione (visto succedere: compattazione contesto, kill esterno), qui lo si scopre e lo si
# ricorda SENZA riarmarlo da soli (armare un Monitor è un'azione agentica, un hook bash non può
# farlo — stesso limite di sempre, MQTT.md).
MQTT_WARN=""
HEX="$(printf '%s' "${CLAUDE_CODE_SESSION_ID:-}" | tr -cd '0-9a-f' | cut -c1-8)"
# TURN-LIVENESS (fix attrito #2, 2026-07-20): l'hook gira a OGNI prompt → una lease Redis TTL 5min
# marca "turno ATTIVO adesso" (vs l'ultima-attività del canale). Best-effort: Redis giù = nessun rumore.
if [ -n "$HEX" ] && [ -r "$PB/tools/lib-redis.sh" ]; then
  ( . "$PB/tools/lib-redis.sh"; redis_available && redis_cmd set "turn:$HEX" "$(date -u +%s)" EX 300 >/dev/null 2>&1 ) &
fi
# AUTO-REGISTRAZIONE pane tmux (fix 2026-07-21, autorizzata espressamente dall'umano): la
# sessione annota DA SOLA "hex → pane" (dato suo, ambiente suo). Lo legge wake-watchdog.sh per
# iniettare il prompt di risveglio nel pane giusto quando la sessione è viva in tmux ma sorda
# al bus (Monitor morto). File locale, mai su git.
if [ -n "$HEX" ] && command -v tmux >/dev/null 2>&1; then
  # TMUX_PANE NON è propagato nelle sessioni Claude (verificato 2026-07-21: vuoto) → la sessione
  # trova il PROPRIO pane risalendo la PROPRIA catena di processi (ppid) fino a un pane_pid di tmux.
  # Auto-inspection (solo antenati propri), niente TMUX_PANE, niente ispezione di sessioni altrui.
  ( PANE=$(tmux list-panes -a -F '#{pane_id} #{pane_pid}' 2>/dev/null | python3 -c "
import sys,os
panes={}
for line in sys.stdin:
    p=line.split()
    if len(p)==2:
        try: panes[int(p[1])]=p[0]
        except ValueError: pass
pid=os.getppid()  # risale dalla shell dell'hook
seen=set()
while pid and pid>1 and pid not in seen:
    if pid in panes: print(panes[pid]); break
    seen.add(pid)
    try: pid=int(open(f'/proc/{pid}/stat').read().split(') ',1)[1].split()[1])
    except Exception: break
" 2>/dev/null)
    [ -n "$PANE" ] && { mkdir -p "$HOME/.cache/henaxis/session-pane" 2>/dev/null && \
      printf '%s\t%s\n' "$PANE" "$(date -u +%s)" > "$HOME/.cache/henaxis/session-pane/$HEX"; } ) 2>/dev/null &
fi
# AUTO-HEARTBEAT dei presidi (fix 2026-07-21, "perché le sessioni non si aggiornano da sole?"):
# la parte MECCANICA del risveglio non aspetta il modello — se un presidio di QUESTA sessione è
# stantio (>30min), l'hook lo rinfresca da solo in background. Sicuro per costruzione: heartbeat
# è idempotente e NON ruba mai un presidio altrui (rc=4, AP-046); il gate 30min fa sì che scatti
# al più una volta ogni mezz'ora per progetto, non a ogni prompt. Al modello resta solo ciò che
# è davvero agentico: armare il Monitor. Best-effort: mai blocca il prompt, fallimenti silenziosi.
if [ -n "$HEX" ] && [ -s "$P" ]; then
  STALE_PRJ=$(python3 - "$P" "$HEX" <<'PY' 2>/dev/null
import json,sys,datetime
try: d=json.load(open(sys.argv[1]))
except Exception: sys.exit(0)
me=sys.argv[2]; now=datetime.datetime.now(datetime.timezone.utc)
for proj,c in d.items():
    if c.get("session")!=me: continue
    try: ls=datetime.datetime.fromisoformat(str(c.get("last_seen","")).replace("Z","+00:00"))
    except Exception: continue
    if (now-ls).total_seconds()>1800: print(proj)
PY
)
  if [ -n "$STALE_PRJ" ]; then
    ( while IFS= read -r _pr; do [ -n "$_pr" ] && bash "$PB/tools/presence.sh" heartbeat "$_pr" >/dev/null 2>&1; done <<<"$STALE_PRJ" ) &
  fi
fi
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

# Prefetch del twin (solo se il prompt SEMBRA un risveglio — il match esatto lo fa python;
# qui si evita il costo mosquitto sui prompt normali). Il twin è retained: 2s max.
TWIN=""
if grep -qiE '"prompt"[[:space:]]*:[[:space:]]*"[^"]{0,50}(aggiorn|allinea|news|catch|sync|novit|fai quello|fai ci|fai il tuo)' "$PAY" 2>/dev/null \
   && command -v mosquitto_sub >/dev/null 2>&1 && [ -n "${MQTT_USER:-}" ]; then
  # repo dal cwd del PAYLOAD (dove l'umano ha lanciato la sessione), non dal cwd del processo hook
  RCWD=$(python3 -c "import json,sys;print(json.load(open(sys.argv[1])).get('cwd',''))" "$PAY" 2>/dev/null)
  TWIN=$(timeout 3 mosquitto_sub -h "${MQTT_LOCAL%%:*}" -p "${MQTT_LOCAL##*:}" -u "$MQTT_USER" -P "$MQTT_PASS" -t "henaxis/onboarding/$(basename "${RCWD:-$PWD}")" -C 1 -W 2 2>/dev/null | tr '\n' ' ')
fi

python3 - "$T" "$MQTT_WARN" "$P" "$CURFILE" "$HEX" "$PAY" "$TWIN" <<'PY' 2>/dev/null
import json,os,sys
from datetime import datetime,timezone
try: from zoneinfo import ZoneInfo; TZ=ZoneInfo("Europe/Rome")
except Exception: TZ=None
try: d=json.load(open(sys.argv[1]))
except Exception as _e:
    # AP-100/AP-096: un errore qui NON deve diventare «nessuna news». Si dichiara, nel prompt.
    print(f"⚠ POSTA NON LEGGIBILE (giunto/vista: {type(_e).__name__}: {_e}) — NON concludere «nessuna news»: verifica con news.sh mine <hex8>")
    sys.exit(0)
curfile=sys.argv[4]
ME=sys.argv[5]
raw=list(d)  # lista NON filtrata: serve a inferire l'ultima attività della sessione (vedi sotto)
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
    # Nessun cursore. Due casi DIVERSI (gap chiuso 2026-07-14, ordine umano, caso p2g-dev):
    # una sessione DAVVERO nuova non ha nulla da recuperare; una sessione DORMIENTE che
    # precede il meccanismo-cursore (o che ha perso la cache locale) ha invece una storia
    # sul canale — mostrarle solo le ultime 8 le fa perdere in silenzio tutto ciò che è
    # passato dalla sua ultima attività (regole, handoff, decisioni). L'ultima attività si
    # inferisce dal canale stesso: ultima entry POSTATA da lei (solo session==ME, NON i
    # diretti ricevuti — un diretto a una dormiente è proprio ciò che non deve saltare, e
    # se spostasse la watermark si auto-escluderebbe dal recupero). Niente stato nuovo:
    # il record è già la memoria.
    inferred=max((str(e.get('ts','')) for e in raw if e.get('session')==ME), default='')
    new=[e for e in d if str(e.get('ts',''))>inferred] if inferred else []
    if new:
        omitted=max(0,len(new)-MAX_BURST)
        shown=new[-MAX_BURST:]
        header=f"RISVEGLIO dopo assenza (nessun cursore; ultima tua attività sul canale: {loc(inferred)}) — recupero di tutto il rilevante da allora:"
    else:
        # prima volta vera (o dormiente senza nulla di nuovo): comportamento di sempre,
        # niente replay retroattivo — il cursore parte da ORA.
        shown=d[-8:]
        header="NEWS cross-sessione (branch news, troncate a 220 char — dettaglio: tools/news.sh recent):"
        omitted=0
    new_all=new
else:
    new_all=[e for e in d if str(e.get('ts',''))>last_seen]
    omitted=max(0,len(new_all)-MAX_BURST)
    shown=new_all[-MAX_BURST:]
    header="NEWS nuove da quando ti sei fermata/o (cursore per-sessione):" if shown else None

parts=[]
if shown:
    lines=[f"[{loc(e.get('ts',''))}] {e.get('machine','?')}/{who(e.get('session','?'))} {cut(e.get('type',''),12)}: {cut(e.get('summary',''))}" for e in shown]
    text=header+"\n"+"\n".join(lines)
    if omitted:
        text+=f"\n… +{omitted} altre non mostrate (tetto {MAX_BURST}): tools/news.sh recent {omitted+MAX_BURST}"
        # Le news FORTI scartate dal tetto non si perdono in una nota numerica: digest
        # ultra-compresso (una riga, 100 char) — sono proprio le rules/decision/handoff
        # che una sessione assente non può permettersi di saltare (stesso gap p2g-dev).
        strong=[e for e in new_all[:-MAX_BURST] if e.get('type') in ('rules','decision','handoff','incident')]
        if strong:
            over=len(strong)-15
            dig="\n".join(f"  · [{loc(e.get('ts',''))}] {who(e.get('session','?'))} {e.get('type')}: {cut(e.get('summary',''),100)}" for e in strong[-15:])
            text+=f"\nTra le omesse, le FORTI (digest compresso):\n{dig}"
            if over>0: text+=f"\n  … +{over} forti più vecchie: tools/news.sh recent {omitted+MAX_BURST}"
    parts.append(text)
    parts.append("Per scrivere: news.sh post|to <tipo> \"<sintesi>\" [refs] — punta a ~450 char per leggibilità. NIENTE rifiuti sulla lunghezza (≤600 ok · fino a 1200 solo avviso · oltre → auto-spill: il testo pieno va in una nota, inline un capo, nulla perso). Scrivi naturale, non tagliare a mano e non riscrivere per rientrare. THREAD: ogni news ha un id — rispondi con news.sh reply <id> <type> (auto-dirige a chi ha scritto); verifica una dichiarazione con news.sh verify <id> ok|fail — recent mostra verificata/smentita.")

# Avanza il cursore alla entry più recente VISTA (non a "adesso" — evita di saltare entry pushate
# nella finestra fetch→scrittura cursore, anche se la finestra è di pochi ms).
if d:
    newest=max(str(e.get('ts','')) for e in d)
    try:
        os.makedirs(os.path.dirname(curfile), exist_ok=True)
        open(curfile,'w',encoding='utf-8').write(newest)
    except Exception: pass

# ── PROTOCOLLO DI RISVEGLIO (2026-07-14, direttiva umana: "dire semplicemente: aggiornati...
# tutto il resto dev'essere già noto alla sessione"). Un prompt MINIMO (solo trigger, ≤50 char)
# fa iniettare l'orientamento completo: twin, presidio del repo, diretti, handoff, ordine di agire.
# Trigger volutamente ristretti a espressioni di puro risveglio: "vai"/"procedi"/"ok" NON ci sono
# (in mezzo a una conversazione significano "approvo il piano", non "riorientati").
import re as _re
try: _pay=json.load(open(sys.argv[6]))
except Exception: _pay={}
_prompt=str(_pay.get('prompt','')).strip().lower().rstrip('!?. …')
_WAKE=_re.compile(r"(ri)?(aggiornati|aggiornamenti|allineati|allineati alle news|novita|novità"
                  r"|(leggi |controlla |guarda )?(le )?news|che news|che (c'è|ce) di nuovo|che si dice"
                  r"|catch ?-?up|sync|fai (quello|ciò|cio) che (sai|devi)( fare)?|fai il tuo (lavoro|dovere))")
if _prompt and len(_prompt)<=50 and _WAKE.fullmatch(_prompt):
    cwd=_pay.get('cwd') or os.getcwd()
    repo=os.path.basename(cwd)
    L=["🌅 PROMPT DI RISVEGLIO riconosciuto — protocollo (SoT SESSION-AUTOMATION.md): tutto il necessario è qui sotto e nel canale. AGISCI, non chiedere all'umano cosa fare (AP-076)."]
    twin=(sys.argv[7] if len(sys.argv)>7 else "").strip()
    L.append(f"1. IDENTITÀ/TWIN ({repo}): "+(cut(twin,700) if twin else "nessun twin retained — l'identità è nell'header di CLAUDE.md (già caricato)."))
    pres=None
    try:
        for proj,c in json.load(open(sys.argv[3])).items():
            if proj.lower()==repo.lower(): pres=c; break
    except Exception: pass
    if pres and pres.get('session')==ME:
        L.append(f"2. PRESIDIO '{repo}': già tuo (ultimo heartbeat {loc(pres.get('last_seen',''))}). Se non hai un nome sulla board: presence.sh claim {repo} <nome>.")
    elif pres:
        L.append(f"2. PRESIDIO '{repo}': di {who(pres.get('session','?'))}, visto {loc(pres.get('last_seen',''))} — coordinati via news.sh ask, NON implementare qui (AP-046).")
    else:
        L.append(f"2. PRESIDIO '{repo}': LIBERO → presence.sh claim {repo} <nome-umano>.")
    if ME:
        directed=[e for e in raw if e.get('to')==ME][-3:]
        if directed:
            L.append("3. ULTIMI DIRETTI A TE (quadro completo: news.sh mine "+ME+"):")
            L += [f"   · [{loc(e.get('ts',''))}] {who(e.get('session','?'))}: {cut(e.get('summary',''),160)}" for e in directed]
        else:
            L.append(f"3. Nessun diretto a te nel registro. Quadro: news.sh mine {ME}.")
    hf=os.path.join(cwd,'docs','handoff','NEXT-SESSION.md')
    if os.path.isfile(hf):
        try: body="\n".join(open(hf,encoding='utf-8').read().splitlines()[:40])[:1200]
        except Exception: body="(illeggibile)"
        # QUANTO E' VECCHIO IL FOGLIO DI RISVEGLIO (16/09/2026). Queste righe finiscono nel risveglio
        # di OGNI sessione: se il foglio e' fermo, l'orientamento di tutta la flotta e' fermo con lui,
        # e nessuno se ne accorge perche' un testo vecchio si legge esattamente come uno nuovo.
        # Misurato: NEXT-SESSION.md di 11814 e' rimasto fermo tre giorni mentre in linea cambiava
        # tutto; diceva «nessuna superficie e' esposta» mentre il pilota di un cliente era servito su
        # un nome pubblico. Nella notte fra il 15 e il 16 due sessioni hanno dichiarato un incidente
        # di esposizione che non esisteva, orientandosi su questa riga. Il numero non lo si tiene a
        # mente: lo si conta, e lo si dice qui sopra il testo, dove nessuno puo' non vederlo.
        eta=""
        try:
            import subprocess as _sp
            _g=lambda *a: _sp.run(["git","-C",cwd]+list(a),capture_output=True,text=True,timeout=5).stdout.strip()
            ultimo=_g("log","-1","--format=%H","--","docs/handoff/NEXT-SESSION.md")
            if ultimo:
                dietro=_g("rev-list","--count",ultimo+"..HEAD")
                quando=_g("log","-1","--format=%cd","--date=format:%d/%m %H:%M",ultimo)
                if dietro.isdigit() and int(dietro)>=25:
                    eta=(f"   ⚠ QUESTO FOGLIO E' FERMO DA {dietro} COMMIT (ultimo aggiornamento {quando}): "
                         f"trattalo come una TRACCIA, non come lo stato. Misura prima di orientarti — "
                         f"e se contiene numeri, versioni o impronte, sono quasi certamente falsi.\n")
                elif dietro.isdigit():
                    eta=f"   (aggiornato {quando}, {dietro} commit fa)\n"
        except Exception:
            eta="   (eta' del foglio non misurabile qui)\n"
        L.append(f"4. HANDOFF (docs/handoff/NEXT-SESSION.md, prime righe):\n{eta}{body}")
    # QUANTA MEMORIA STANNO MANGIANDO GLI SCARTI (17/09/2026, AP-108). Su queste macchine /tmp e' un
    # tmpfs: non e' un disco, e' RAM. Misurato: swap 11 Gi PIENO con 4,3 GB di /tmp dentro, cinque
    # sessioni uccise dalla pressione di memoria e due push a meta', mentre tutti credevano fosse
    # «la coda dei processi». Erano file: 77.059 voci piu' vecchie di un giorno, quasi tutte
    # cartelle di mktemp dei nostri strumenti, mai rimosse. Una macchina non dice da sola che sta
    # soffocando: lo si scopre quando qualcosa muore. Quindi lo dice il risveglio, con un numero.
    try:
        import shutil as _sh, subprocess as _sp2
        _t = os.environ.get("TMPDIR", "/tmp")
        _tipo = _sp2.run(["findmnt", "-no", "FSTYPE", "--target", _t],
                         capture_output=True, text=True, timeout=5).stdout.strip()
        if _tipo == "tmpfs":
            _u = _sh.disk_usage(_t)
            _mb, _tot = _u.used // 1048576, _u.total // 1048576
            if _tot and _mb * 100 // _tot >= 25:
                L.append(f"⚠ SCARTI IN MEMORIA: {_t} e' un tmpfs e ne occupa {_mb} MB su {_tot} "
                         f"({_mb*100//_tot}%) — quella e' RAM tolta al lavoro, e sotto pressione "
                         f"finisce in swap. Guarda con: bash "
                         f"{os.environ.get('HENAXIS_PLAYBOOK', '/opt/henaxis-playbook')}"
                         f"/tools/scarti-temporanei.sh   (AP-108: chi crea uno scarto lo toglie; "
                         f"gli scarti grossi non vanno in {_t} per niente)")
    except Exception:
        pass
    else:
        L.append("4. Nessun handoff in docs/handoff/NEXT-SESSION.md.")
    # 5. I TUOI TASK (radice dell'inerzia: una sessione che non SA cosa possiede aspetta il via
    #    umano; iniettarli + ordinare di guidarli è il fix sistemico, non il poke del caso singolo).
    my_areas=set()
    try:
        for proj,c in json.load(open(sys.argv[3])).items():
            if c.get('session')==ME: my_areas.add(proj.lower())
    except Exception: pass
    tasks=[]
    try:
        _tj=json.load(open(os.path.join(os.path.dirname(sys.argv[3]),'tasks.json')))
        tasks=_tj if isinstance(_tj,list) else _tj.get('tasks',[])
    except Exception: pass
    mine=[t for t in tasks if t.get('owner')==ME and str(t.get('status','')).replace('_','-') in ('in-corso',)]
    avail=[t for t in tasks if not t.get('owner') and t.get('status')=='backlog'
           and any(a and a in str(t.get('area','')).lower() for a in my_areas)]
    if mine or avail:
        seg=["5. I TUOI TASK — GUIDALI ORA (task.sh; NON aspettare il via, AP-076):"]
        for t in mine[:5]: seg.append(f"   ▶ IN-CORSO (tuo): {t.get('id')} — {cut(t.get('title',''),80)} → falne avanzare un pezzo; se BLOCCATO, dillo nel canale e passa a un disponibile")
        for t in avail[:5]: seg.append(f"   ○ DISPONIBILE (tuo perimetro): {t.get('id')} — {cut(t.get('title',''),80)} → claima con task.sh se l'in-corso è bloccato/assente")
        L.append("\n".join(seg))
    else:
        L.append(f"5. Nessun task tuo in-corso né disponibile nel tuo perimetro ({', '.join(sorted(my_areas)) or 'nessun presidio'}) — verifica: task.sh list. Se la coda è davvero vuota, esegui il deliverable/handoff dichiarato.")
    L.append("6. ORDINE OPERATIVO: (a) arma il Monitor se manca; (b) GUIDA i task sopra o il deliverable dichiarato — falne avanzare almeno UNO ORA. NON rispondere «cosa faccio»: se possiedi o puoi claimare lavoro, AGISCI; ferma SOLO ai gate umani (PROD/deploy/transfer/delete/decisioni irreversibili). Un task bloccato si DICHIARA e si scavalca con un altro, non giustifica l'attesa (AP-076).")
    parts.append("\n".join(L))

mqtt_warn = sys.argv[2] if len(sys.argv) > 2 else ""
if mqtt_warn:
    parts.append("⚠ COORDINAMENTO (AP-062): " + mqtt_warn)
if not parts: sys.exit(0)
print(json.dumps({"hookSpecificOutput":{"hookEventName":"UserPromptSubmit","additionalContext":"\n\n".join(parts)}}))
PY
rm -f "$T" "$P"
