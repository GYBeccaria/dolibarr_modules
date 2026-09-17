#!/usr/bin/env python3
"""serena-gate-hook.py — PreToolUse (Bash), il gate condiviso del tool Bash. Due regole:
1. blocca grep/git grep su codice sorgente quando Serena è configurata per il repo,
   a meno di un override esplicito e motivato (SERENA_GREP_OK);
2. blocca mqtt-monitor-arm.sh lanciato via Bash: va nel campo `command` del tool Monitor
   (persistent), MAI come processo bash — lanciato a mano si abbona al bus ma NON notifica
   la sessione (coordinamento silenziosamente rotto mentre sembra armato) e alla morte del
   processo padre resta orfano con la LWT retained "online" che mente (visto 2 volte:
   sess-e8634288 orfani il 14/07, riarmo p2g-dev lo stesso giorno — "non deve ripetersi
   mai più", ordine umano). Override motivato: MONITOR_ARM_BASH_OK (solo debug script).

Riscrittura del v1 bash (serena-gate-hook.sh, regex su TUTTA la stringa comando) dopo aver
trovato in sessione tre classi di falso positivo che una regex-su-stringa non può risolvere
per costruzione:
  1. Il comando CITA "grep"/un'estensione come TESTO (es. un commit message) — la regex non
     distingue "grep" come parola di comando da "grep" dentro un argomento tra virgolette.
  2. Un comando multi-statement contiene un grep innocuo (es. `lsof | grep LISTEN`) MA anche,
     altrove nella stessa riga, un file con estensione coperta (es. `node server/index.js`) —
     la regex-su-stringa-intera non sa quale grep sta guardando quale file.
  3. `.json` fa match su `.js` come sottostringa (risolto nel v1 con \b, ma il problema di
     fondo — matching su file, non su token — resta).

Qui si TOKENIZZA il comando con shlex (rispetta le virgolette: un commit message tra
virgolette diventa UN token, non viene scansionato parola per parola) e si SPEZZA sugli
operatori di shell (;, &&, ||, |, &) per isolare ogni singolo comando semplice. Il controllo
sull'estensione si applica SOLO agli argomenti dell'invocazione grep individuata, non a
tutta la riga di comando.

Limiti noti (accettati, non "perfezione shell-parsing" — quella è compito di bash stesso):
subshell annidate `(...)`/`{...}` non vengono ri-analizzate in profondità (il contenuto resta
nel segmento del comando esterno); gli heredoc non sono riconosciuti come sintassi speciale
da shlex (il corpo diventa token normali — raro che assomigli a un'invocazione grep reale).

Contratto hook Claude Code: stdin = payload JSON. Exit 2 = blocca (stderr = contesto). Exit 0
= via libera. Fail-open ovunque manchi un prerequisito.
"""
import json
import shutil
import os
import re
import shlex
import sys
from datetime import datetime, timezone

SOURCE_EXT_BY_LANG = {
    "typescript": (".mjs", ".js", ".jsx", ".ts", ".tsx"),
    "python": (".py",),
    "php": (".php",),
    "bash": (".sh",),
    "html": (".html",),
    "scss": (".css", ".scss"),
}
OPERATORS = {";", "&&", "||", "|", "&", "\n"}


def read_payload():
    try:
        return json.load(sys.stdin)
    except Exception:
        return {}


def serena_usabile():
    """Serena esiste su questa macchina? L'ASSENZA del binario prova che non puo' rispondere.

    E' il minimo verificabile a costo zero. La presenza non prova che risponda — quello lo
    proverebbe solo una chiamata vera — ma bloccare `grep` rimandando a strumenti che NON ESISTONO
    e' un danno certo, e su questa distinzione il gate deve stare dalla parte prudente.
    """
    if shutil.which("serena"):
        return True
    return any(os.path.isfile(c) and os.access(c, os.X_OK) for c in (
        os.path.expanduser("~/.local/bin/serena"), "/usr/local/bin/serena"))


def find_serena_yml(start_dir):
    d = os.path.abspath(start_dir)
    for _ in range(6):
        # UN FILE DI CONFIGURAZIONE NON PROVA CHE LO STRUMENTO FUNZIONI (AP-095). Questo gate
        # bloccava `grep` perche' `.serena/project.yml` ESISTE, e rimandava a `find_symbol` su una
        # macchina dove serena non e' nemmeno installata: due override a verbale in venti minuti
        # (designer-hwlab, 2026-09-01). La presenza del binario non prova che serena risponda, ma
        # la sua ASSENZA prova che non puo': e' il minimo verificabile a costo zero, e va fatto.
        candidate = os.path.join(d, ".serena", "project.yml")
        if os.path.isfile(candidate):
            return candidate
        parent = os.path.dirname(d)
        if parent == d:
            break
        d = parent
    return None


# Estensioni-sorgente usate SOLO nel ramo "repo non indicizzato", dove non esiste un
# project.yml da cui leggerle. Volutamente conservativa: un falso negativo qui costa un
# avviso mancato, un falso positivo costerebbe rumore su ogni grep di log o di prosa.
DEFAULT_SOURCE_EXTS = (
    ".py", ".js", ".mjs", ".cjs", ".ts", ".tsx", ".jsx", ".php", ".go", ".rs", ".java",
    ".rb", ".c", ".h", ".cpp", ".hpp", ".cs", ".swift", ".kt", ".scala", ".sh", ".bash",
)


def find_git_root(start_dir):
    d = os.path.abspath(start_dir)
    for _ in range(8):
        if os.path.isdir(os.path.join(d, ".git")):
            return d
        parent = os.path.dirname(d)
        if parent == d:
            break
        d = parent
    return None


def configured_extensions(serena_yml_path):
    try:
        with open(serena_yml_path, "r", encoding="utf-8") as f:
            text = f.read()
    except OSError:
        return set()
    m = re.search(r"^languages:\n((?:-\s*.+\n?)+)", text, re.MULTILINE)
    if not m:
        return set()
    langs = {line.strip("- ").strip().lower() for line in m.group(1).splitlines() if line.strip()}
    exts = set()
    for lang, ext_tuple in SOURCE_EXT_BY_LANG.items():
        if lang in langs or (lang == "bash" and any(l.startswith("bash") for l in langs)):
            exts.update(ext_tuple)
    return exts


def split_into_segments(command):
    """Tokenizza rispettando le virgolette (shlex) e spezza sugli operatori di shell di primo
    livello. Ritorna una lista di segmenti, ognuno una lista di token (stringhe già "sfilate"
    dalle virgolette da shlex — un commit message tra virgolette è UN token)."""
    lexer = shlex.shlex(command, posix=True, punctuation_chars=True)
    lexer.whitespace_split = True
    try:
        tokens = list(lexer)
    except ValueError:
        return []  # quoting non bilanciato: non tocca a noi parsarlo, non blocchiamo
    segments, current = [], []
    for tok in tokens:
        if tok in OPERATORS or all(c in "|&;" for c in tok):
            if current:
                segments.append(current)
            current = []
        else:
            current.append(tok)
    if current:
        segments.append(current)
    return segments


def grep_arguments(segment):
    """Se il segmento è un'invocazione grep/git-grep, ritorna i suoi argomenti (esclusi i nomi
    del comando stesso); altrimenti None."""
    if not segment:
        return None
    if segment[0] == "grep":
        return segment[1:]
    if segment[0] == "git" and len(segment) > 1 and segment[1] == "grep":
        return segment[2:]
    return None


def has_source_ext(token, exts):
    # solo il "path" del token, ignora eventuali flag tipo -n attaccate o pattern con slash
    base = token.rsplit("/", 1)[-1] if "/" in token else token
    return any(base.endswith(ext) for ext in exts)


def log_override(reason, command):
    logdir = os.path.expanduser("~/.claude/serena-gate")
    try:
        os.makedirs(logdir, exist_ok=True)
        with open(os.path.join(logdir, "overrides.log"), "a", encoding="utf-8") as f:
            ts = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
            f.write(f"{ts}\t{reason or '<senza motivo>'}\t{command}\n")
    except OSError:
        pass


def monitor_arm_block_message():
    return """✗ Gate Monitor — mqtt-monitor-arm.sh lanciato via Bash invece che nel tool Monitor.
  Perché: via Bash il processo si abbona al bus ma NON notifica la sessione (i diretti/escalation
  arrivano al processo, non a te: coordinamento silenziosamente rotto mentre sembra armato) e alla
  chiusura del processo padre resta ORFANO con la presenza LWT retained "online" che mente
  (successo davvero: orfani sess-e8634288, 14/07). In più la password resta negli argv di un
  processo fuori dal lifecycle tracciato.
  Fix (copiabile): usa il tool Monitor con command ESATTAMENTE:
    bash /opt/henaxis-playbook/tools/mqtt-monitor-arm.sh <hex8> [repo]
  (persistent; prima TaskList per non duplicarlo — SoT: MQTT.md, sezione abbonamento sessione).
  Se stai facendo DEBUG dello script stesso (unico caso legittimo via Bash), ripeti il comando
  preceduto da: MONITOR_ARM_BASH_OK="<motivo>" — resta a verbale in
  ~/.claude/serena-gate/overrides.log.
"""


def block_message(serena_yml):
    return f"""✗ Gate Serena — grep su codice sorgente in un repo dove Serena è configurata ({serena_yml}).
  Perché: grep trova STRINGHE, Serena/GitNexus (hm-impact.sh) trovano SIMBOLI — referenze,
  implementazioni, impatto reale, anche cross-repo. Costo misurato (2026-07-08, sessione
  henaxmetrics): un retrofit fatto a grep ha perso 2 call-site su 14 che
  find_referencing_symbols ha trovato subito.
  Fix: usa find_symbol / find_referencing_symbols / get_symbols_overview (Serena MCP) o
  tools/hm-impact.sh <repo> <simbolo> (codegraph) PRIMA di grep.
  Se questo grep è legittimo (log/dati/prosa/output di comandi, non navigazione di simboli),
  ripeti il comando preceduto da: SERENA_GREP_OK="<motivo>" — resta a verbale in
  ~/.claude/serena-gate/overrides.log (dato reale su quando grep serve davvero).
"""


def uncovered_message(repo_root):
    return f"""⚠ Gate Serena — repo NON indicizzato: {repo_root}
  Stai navigando codice sorgente con grep in un repo che NON ha .serena/project.yml, quindi
  il gate non ti ha fermato — e questo e il difetto: il guardiano taceva proprio dove il
  problema e peggiore. Un repo non indicizzato non e un repo esente: e un repo scoperto.
  Perche conta: grep trova STRINGHE, Serena trova SIMBOLI (referenze, implementazioni,
  impatto reale, anche cross-repo). Costo misurato (2026-07-08): un retrofit fatto a grep ha
  perso 2 call-site su 14 che find_referencing_symbols ha trovato subito. La playbook
  (SERENA.md, P2G-P-08) esige che OGNI repo sia Serena-ready: questo non lo e.
  Fix: indicizza il repo (indice Serena + .mcp.json per-progetto, cfr. SERENA.md), poi usa
  find_symbol / find_referencing_symbols / get_symbols_overview.
  Questo e un AVVISO, non un blocco (AP-071: un cancello si certifica prima di renderlo
  bloccante). Il tuo comando prosegue.
"""


def main():
    payload = read_payload()
    if payload.get("tool_name") != "Bash":
        return 0
    command = (payload.get("tool_input") or {}).get("command") or ""
    if not command.strip():
        return 0

    m = re.match(r'^\s*SERENA_GREP_OK="([^"]*)"', command)
    if m:
        log_override(m.group(1), command)
        return 0

    # Regola 2 — Monitor via Bash (indipendente da Serena: vale in OGNI repo agganciato).
    # Match sul nome file in un token di comando (tokenizzato: una citazione tra virgolette
    # in un commit message NON scatta — stessa lezione dei falsi positivi grep).
    if "mqtt-monitor-arm" in command:
        m = re.match(r'^\s*MONITOR_ARM_BASH_OK="([^"]*)"', command)
        if m:
            log_override("MONITOR_ARM: " + m.group(1), command)
            return 0
        # Blocca solo in POSIZIONE DI ESECUZIONE (primo token, o preceduto solo da
        # interpreti/wrapper/flag): leggere lo script (head/cat/diff) non è armarlo.
        EXEC_WRAPPERS = {"bash", "sh", "zsh", "dash", "nohup", "timeout", "setsid",
                         "env", "exec", "command", "eval", "sudo"}
        for segment in split_into_segments(command):
            for i, tok in enumerate(segment):
                if tok.rsplit("/", 1)[-1] != "mqtt-monitor-arm.sh":
                    continue
                before = segment[:i]
                if all(t in EXEC_WRAPPERS or t.startswith("-") or t.isdigit() for t in before):
                    sys.stderr.write(monitor_arm_block_message())
                    return 2

    cwd = payload.get("cwd") or os.getcwd()

    # SE SERENA NON ESISTE QUI, QUESTO GATE NON HA NULLA VERSO CUI RIMANDARE. Bloccava `grep`
    # perche' `.serena/project.yml` ESISTE nel repo — un file di configurazione, che non prova che
    # lo strumento funzioni (AP-095). Su una macchina senza serena il risultato e' un blocco che
    # rimanda a `find_symbol`, che li' non esiste: designer-hwlab ha dovuto mettere a verbale due
    # override in venti minuti. Un gate che costringe a scavalcarlo insegna a scavalcarlo.
    if not serena_usabile():
        sys.stderr.write(
            "· gate Serena INATTIVO: serena non e' installata su questa macchina (cercata nel PATH, "
            "in ~/.local/bin e /usr/local/bin). Il file .serena/project.yml del repo non prova che lo "
            "strumento funzioni. Qui grep e read sono la via corretta, non una scorciatoia.\n")
        return 0

    serena_yml = find_serena_yml(cwd)
    if not serena_yml:
        # INVERSIONE CORRETTA (2026-07-26): "nessuna Serena configurata" NON puo significare
        # "via libera silenziosa" — era un guardiano condizionato allo stato che deve garantire,
        # quindi muto esattamente dove il problema e peggiore (codemeris-core, il core del
        # prodotto, e senza indice: ci si greppa senza alcun attrito). Se siamo dentro un repo
        # git e il grep tocca sorgente, si AVVISA (mai si blocca: AP-071) e si prosegue.
        repo_root = find_git_root(cwd)
        if repo_root:
            for segment in split_into_segments(command):
                args = grep_arguments(segment)
                if args is None:
                    continue
                if any(has_source_ext(tok, DEFAULT_SOURCE_EXTS) for tok in args if not tok.startswith("-")):
                    sys.stderr.write(uncovered_message(repo_root))
                    break
        return 0
    exts = configured_extensions(serena_yml)
    if not exts:
        return 0

    for segment in split_into_segments(command):
        args = grep_arguments(segment)
        if args is None:
            continue
        if any(has_source_ext(tok, exts) for tok in args if not tok.startswith("-")):
            sys.stderr.write(block_message(serena_yml))
            return 2
    return 0


if __name__ == "__main__":
    sys.exit(main())
