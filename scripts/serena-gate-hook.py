#!/usr/bin/env python3
"""serena-gate-hook.py — PreToolUse (Bash): blocca grep/git grep su codice sorgente quando
Serena è configurata per il repo, a meno di un override esplicito e motivato.

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


def find_serena_yml(start_dir):
    d = os.path.abspath(start_dir)
    for _ in range(6):
        candidate = os.path.join(d, ".serena", "project.yml")
        if os.path.isfile(candidate):
            return candidate
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

    cwd = payload.get("cwd") or os.getcwd()
    serena_yml = find_serena_yml(cwd)
    if not serena_yml:
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
