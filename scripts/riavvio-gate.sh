#!/usr/bin/env bash
# riavvio-gate.sh — PreToolUse (Bash): RIFIUTA i comandi di riavvio/spegnimento della macchina.
#
# DIRETTIVA UMANA (2026-09-14, parole sue): «il riavvio non e' mai un'opzione, non va nemmeno
# proposto, e' una mia soluzione, mai una soluzione di una sessione». COORDINATION §3-ter.
# Enforcement, non disciplina (AP-006): il comando non parte, e il rifiuto dice perche' e cosa
# fare invece (l'equivalente senza riavvio: restart/kill del singolo servizio + misura).
#
# Contratto PreToolUse: JSON su stdin {tool_name, tool_input:{command}}; exit 2 = BLOCCA e mostra
# stderr alla sessione; exit 0 = lascia passare. Qualunque errore interno lascia passare (exit 0):
# un gate che si rompe non deve fermare ogni Bash della flotta (stessa lezione del gate Serena).
set -uo pipefail
IN="$(cat 2>/dev/null || true)"
# La decisione e' in python: il verbo di riavvio deve stare in POSIZIONE DI COMANDO (inizio di un
# segmento shell, o dopo sudo/env/nohup/exec/time/doas), non ovunque nella riga — altrimenti
# `grep reboot /var/log/syslog` sarebbe bloccato (falso positivo misurato alla prima falsificazione).
ESITO="$(printf '%s' "$IN" | python3 -c '
import sys, json, re, shlex
try:
    d = json.load(sys.stdin)
except Exception:
    print("passa"); sys.exit()
if d.get("tool_name") != "Bash":
    print("passa"); sys.exit()
cmd = d.get("tool_input", {}).get("command", "") or ""
# CANARINO (henaxMetrics, 14/09): un gate che copre SOLO azioni irreversibili non e collaudabile
# end-to-end senza rischiare l irreversibile. henax-canarino-riavvio non esiste come comando:
# se il gate e davvero invocato dall harness -> bloccato con §3-ter; se non lo e -> command not
# found, nessun danno. Misura l invocazione, non la presenza nel file di configurazione.
# (NIENTE APOSTROFI in questi commenti: siamo dentro un python -c ad apici singoli — il 14/09 un
# apostrofo qui ha rotto il gate e bloccato OGNI Bash della macchina per alcuni minuti.)
VERBI = {"reboot", "shutdown", "poweroff", "halt", "henax-canarino-riavvio"}
SYSCTL = {"reboot", "poweroff", "halt", "kexec", "suspend", "hibernate", "hybrid-sleep", "suspend-then-hibernate"}
PREFISSI = {"sudo", "doas", "env", "nohup", "exec", "time", "command", "builtin", "setsid"}
# FLAG CHE MANGIANO IL TOKEN SEGUENTE (16/09, trovate dalla sessione di collaudo leggendo questo
# file). Il salto dei prefissi trattava ogni flag come booleana: in sudo -u root reboot il token
# root diventava il VERBO, e reboot non veniva mai ispezionato. Tre frasi passavano il gate.
# Qui le flag con argomento si dichiarano, e si salta anche il token che consumano.
ARGOMENTO = {
  "sudo": {"-u", "-g", "-p", "-C", "-h", "-D", "-R", "-t", "-T", "-U", "-r"},
  "doas": {"-u", "-C"},
  "env": {"-u", "-C", "-S"},
  "systemctl": {"-M", "-H", "-t", "-p", "-o", "-n", "--machine", "--host", "--type", "--property"},
}
def salta_argomento(parole, i, comando):
    # vero se parole[i] e una flag del comando che consuma parole[i+1] come argomento separato
    # (la forma --flag=valore non consuma niente e non entra qui)
    return parole[i] in ARGOMENTO.get(comando, set())
if re.search(r"echo\s+[bo]\s*>\s*/proc/sysrq-trigger", cmd):
    print("blocca"); sys.exit()
# segmenti: separatori shell + sostituzioni di comando
# Il separatore & NUDO mancava (16/09): in true & reboot il secondo comando non veniva mai
# guardato, perche lo split conosceva && ma non &. Sta DOPO && nell alternativa, o mangerebbe il suo
# primo carattere.
for seg in re.split(r"(?:\|\||&&|;|&|\||\n|\$\(|`)", cmd):
    try:
        w = shlex.split(seg, posix=True)
    except ValueError:
        w = seg.split()
    # salta prefissi e loro opzioni/assegnazioni (sudo -n, env VAR=x, ...) — e le flag CON
    # ARGOMENTO si portano dietro il token che consumano, o quel token verrebbe scambiato per il
    # verbo (sudo -u root reboot).
    i = 0
    ultimo_prefisso = ""
    while i < len(w):
        if w[i] in PREFISSI:
            ultimo_prefisso = w[i]; i += 1; continue
        if re.match(r"^[A-Za-z_][A-Za-z0-9_]*=", w[i]):
            i += 1; continue
        if ultimo_prefisso and w[i].startswith("-"):
            if salta_argomento(w, i, ultimo_prefisso) and i + 1 < len(w):
                i += 2
            else:
                i += 1
            continue
        break
    if i >= len(w):
        continue
    verbo = w[i].rsplit("/", 1)[-1]
    if verbo in VERBI:
        print("blocca"); sys.exit()
    if verbo in ("init", "telinit") and i + 1 < len(w) and w[i+1] in ("0", "6"):
        print("blocca"); sys.exit()
    if verbo == "systemctl":
        # Anche qui le flag con argomento: systemctl -M altrahost reboot lasciava altrahost come
        # primo token non-flag, e reboot non veniva mai guardato. Riavviare la macchina di qualcun
        # altro e peggio, non meglio, che riavviare la propria.
        rest = []
        j = i + 1
        while j < len(w):
            if w[j].startswith("-"):
                j += 2 if (salta_argomento(w, j, "systemctl") and j + 1 < len(w)) else 1
                continue
            rest.append(w[j]); j += 1
        if rest and rest[0] in SYSCTL:
            print("blocca"); sys.exit()
print("passa")
' 2>/dev/null || echo passa)"
if [ "$ESITO" = blocca ]; then
  cat >&2 <<'MSG'
⛔ RIAVVIO/SPEGNIMENTO RIFIUTATO (COORDINATION §3-ter — direttiva umana 14/09/2026):
  «il riavvio non e' mai un'opzione, non va nemmeno proposto, e' una mia soluzione,
   mai una soluzione di una sessione».
  Nessuna sessione riavvia o spegne una macchina, e non lo propone. Se un servizio deve
  dimostrare di ripartire: systemctl enable --now <unita'> · systemctl kill/restart <unita'>
  · misura che risponde. Se serve DAVVERO un riavvio, lo decide e lo esegue l'umano.
MSG
  exit 2
fi
exit 0
