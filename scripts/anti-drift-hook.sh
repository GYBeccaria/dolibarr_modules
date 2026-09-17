#!/usr/bin/env bash
# anti-drift-hook.sh — pre-commit + commit-msg, modulare.
#   $1 vuoto       -> pre-commit (Gate 0 sync + gate universali + overlay per-repo)
#   $1 valorizzato -> commit-msg (Gate 3: sezione ANTI-DRIFT CHECK)
# CORE universale qui; i gate PROGETTO/verticale (anglismi, design-token, tsc TS, ...)
# vivono in scripts/playbook-gates.sh del repo (overlay opzionale) — vedi REFINEMENT.md.
set -e
FAIL=0
COMMIT_MSG_FILE="${1:-}"

# --- commit-msg context: Gate 3 ---
if [ -n "$COMMIT_MSG_FILE" ] && [ -f "$COMMIT_MSG_FILE" ]; then
  if ! grep -q "^ANTI-DRIFT CHECK:" "$COMMIT_MSG_FILE"; then
    cat >&2 <<'MSG'
✗ COMMIT RIFIUTATO — manca la sezione ANTI-DRIFT CHECK (obbligatoria, COMMIT-CONVENTION.md).
  Incolla e compila questo blocco IN FONDO al messaggio (prima dei trailer):

ANTI-DRIFT CHECK:
- Valore durevole oltre lo scope minimo: <test/astrazione/cleanup/decisione — o "nessuno", onesto>
- Anti-pattern catalogati passati per veto: <AP-NNN, ... | nessuno>
- Catch onesti presenti in questo commit: <1 riga ciascuno | nessuno>
- Debito tracciato (se scorciatoia presa): <descrizione + dove | nessuno>
MSG
    exit 1
  fi
  # Gate 3d — PERIMETRO (AP-101, 13/09). Un cambio a COSA gli hook eseguono, DA DOVE risolvono una
  # sorgente fidata, o CHI e' fidato, e' un CAMBIO DI PERIMETRO: si porta PRIMA all'umano, nominato,
  # e lo si dichiara nel messaggio con 'PERIMETRO-OK: <autorizzazione umana, data/ora>'. Senza: BLOCCA.
  # Nato da un glob $HOME/*/henaxis-playbook infilato in un «fix» e propagato a 32 repo senza chiedere.
  PERIM_FILES="$(git diff --cached --name-only 2>/dev/null | grep -E '^(tools|scripts)/.*\.(sh|mjs|py)$|^\.claude/settings\.json$|githooks' || true)"
  if [ -n "$PERIM_FILES" ]; then
    PERIM_HITS="$(git diff --cached -U0 -- $PERIM_FILES 2>/dev/null | grep -E '^\+' | grep -vE '^\+\+\+' | grep -E 'for d in .*(playbook|PLAYBOOK)|\*/henaxis-playbook|HENAXIS_PLAYBOOK=|CANON_REMOTE=|^\+[[:space:]]*PB=|(python3|bash|node|exec) +"?\$\{?(PB|PLAYBOOK)[}/]|hooksPath|curl [^|]*\| *(ba)?sh' || true)"
    # IL SISTEMA IMMUNITARIO DEVE RICONOSCERE SE STESSO (17/09/2026, parole dell'umano: «un sistema
    # immunitario che attacca se stesso non sta funzionando bene; scambiare azioni legittime per
    # infrazioni non e' un buon funzionamento, non saper riconoscere nemmeno se stessi non e' un buon
    # funzionamento»). In un giorno solo questo script ha bloccato QUATTRO commit che non violavano
    # niente: CLAUDE.md rigenerato da se', il commit di aggancio, .gitignore a cui appende lui, e la
    # propagazione dei propri script in 32 repo. Ogni volta la toppa era un caso speciale in piu'.
    #
    # La distinzione vera non e' «quali righe» ma CHI LE HA SCRITTE, e si puo' misurare invece di
    # dichiarare: se il file messo in staging e' BYTE PER BYTE identico alla copia canonica nella
    # playbook, allora non e' qualcuno che cambia il perimetro — e' la propagazione che porta il
    # perimetro gia' deciso. Se anche un solo byte differisce, quello e' un cambio locale e il gate
    # blocca come prima: e' li' che sta il rischio (un glob infilato in un «fix», AP-101).
    # Nessuna autorizzazione da chiedere per ricopiare cio' che e' gia' approvato nella fonte.
    # La playbook si cerca qui e non piu' in basso, perche' questo confronto viene prima. Se non la
    # si trova, NON si conclude «propagazione»: si lascia decidere al gate, che e' il lato prudente.
    PB_DIR=""
    for _d in "${HENAXIS_PLAYBOOK:-}" /opt/henaxis-playbook "$HOME/opt/henaxis-playbook"; do
      [ -n "$_d" ] && [ -d "$_d/tools" ] && { PB_DIR="$_d"; break; }
    done
    PERIM_PROPAGAZIONE=1
    [ -z "$PB_DIR" ] && PERIM_PROPAGAZIONE=0
    for _pf in $PERIM_FILES; do
      [ "$PERIM_PROPAGAZIONE" = 1 ] || break
      case "$_pf" in scripts/*) _canonico="$PB_DIR/tools/${_pf#scripts/}" ;; *) PERIM_PROPAGAZIONE=0; break ;; esac
      if [ ! -f "$_canonico" ] || ! git show ":$_pf" 2>/dev/null | cmp -s - "$_canonico"; then
        PERIM_PROPAGAZIONE=0; break
      fi
    done
    if [ -n "$PERIM_HITS" ] && [ "$PERIM_PROPAGAZIONE" = 1 ]; then
      echo "○ PERIMETRO: i file in staging sono identici byte per byte alla playbook ($PB_DIR) — e' propagazione, non un cambio locale. Nessuna autorizzazione richiesta."
      PERIM_HITS=""
    fi
    if [ -n "$PERIM_HITS" ] && ! grep -qE '^PERIMETRO-OK:' "$COMMIT_MSG_FILE"; then
      { echo "✗ COMMIT RIFIUTATO — CAMBIO DI PERIMETRO senza autorizzazione dichiarata (AP-101)."
        echo "  Righe aggiunte che cambiano cosa/da dove gli hook eseguono o chi e' fidato:"
        echo "$PERIM_HITS" | head -8 | sed 's/^/    /'
        echo "  Un cambio di perimetro si porta PRIMA all'umano, nominato (da X a Y, perche', rischio), e si"
        echo "  dichiara nel messaggio con:  PERIMETRO-OK: <autorizzazione umana, data/ora, parole>"
        echo "  Riparare o STRINGERE non e' allargare: se e' il caso, scrivilo nel PERIMETRO-OK stesso."; } >&2
      exit 1
    fi
  fi
  # Gate 3c — LA GUARDIA ANTIPIGRIZIA. Debito dichiarato su un file che il commit STA TOCCANDO.
  #
  # AVVISA, non blocca, ed è deliberato: esistono ragioni vere per non chiudere qui (un cambio che
  # richiede una decisione umana, un file altrui, uno scope che si spaccherebbe in due — AP-003).
  # Bloccare le renderebbe indistinguibili dalla pigrizia, e la risposta razionale a un blocco
  # ingiusto è smettere di dichiarare il debito: si otterrebbero meno DICHIARAZIONI, non meno debiti.
  # È la stessa inversione per cui `measure green:false` non viene addebitata.
  # Quindi l'avviso porta l'argomento e lascia la scelta, ma la lascia DOPO averla resa visibile.
  # La guardia VIAGGIA COL HOOK: `setup-repo.sh` la copia in `scripts/` accanto a questo file, quindi
  # `dirname $0` la trova ovunque il playbook sia agganciato. Nessun percorso di macchina, nessuna
  # variabile che potrebbe non esistere — la prima stesura usava un `$PLAYBOOK_DIR` mai definito, cioè
  # un ripiego morto: sembrava una difesa in profondità e non lo era.
  DEBITO_GUARD="$(dirname "$0")/debito-in-scope.mjs"
  if [ -f "$DEBITO_GUARD" ] && command -v node >/dev/null 2>&1; then
    node "$DEBITO_GUARD" "$COMMIT_MSG_FILE" || true   # exit 1 = avviso già stampato · 2 = non ha potuto leggere
  fi

  # Gate 3b (AP-055): nome sessione obbligatorio — nessun commit anonimo. Trailer 'Session: <nome> (<hex8>)'.
  if ! grep -qiE "^Session:[[:space:]]*[^[:space:]]" "$COMMIT_MSG_FILE"; then
    HEX="$(printf '%s' "${CLAUDE_CODE_SESSION_ID:-}" | tr -cd '0-9a-f' | cut -c1-8)"  # hex8 canonico (come henaxis_ses)
    cat >&2 <<MSG
✗ COMMIT RIFIUTATO — manca il trailer 'Session:' = QUALE sessione ha eseguito il commit (AP-055).
  Formato: nome human-friendly ASSEGNATO DALL'UMANO + hex8 tra parentesi. Aggiungi in fondo:

Session: <nome> (${HEX:-<hex8>})

  Il tuo hex8 è: ${HEX:-"sconosciuto (CLAUDE_CODE_SESSION_ID assente)"} — è lo stesso id di news/tasks/presence.
  Se la sessione NON ha ancora un nome: FERMATI e chiedi all'umano di assegnarne uno (mai committare anonimo).
MSG
    exit 1
  fi
  exit 0
fi

# --- pre-commit context ---
# Gate 0: sync CLAUDE.md da docs/CLAUDE-core.md (+ auto-stage se cambiato)
if [ -x "scripts/sync-claude-shared.sh" ] && [ -e "docs/CLAUDE-core.md" ]; then
  if bash scripts/sync-claude-shared.sh; then
    git diff --quiet CLAUDE.md 2>/dev/null || { git add CLAUDE.md; echo "→ Gate 0: CLAUDE.md rigenerato (auto-staged)"; }
  else
    echo "✗ COMMIT BLOCCATO — Gate 0: sync di CLAUDE.md da docs/CLAUDE-core.md fallito." >&2
    echo "  Diagnosi: esegui 'bash scripts/sync-claude-shared.sh' a mano per vedere l'errore" >&2
    echo "  (cause tipiche: marker SHARED mancanti/duplicati in CLAUDE.md, docs/CLAUDE-core.md assente)." >&2
    exit 1
  fi
fi

# Gate 0b (SOLO playbook — no-op altrove): densifica AUTOMATICAMENTE i wikilink AP-0xx nudi nei
# .md staged, PRIMA che il commit si chiuda. Nato da: la densificazione era un passaggio MANUALE
# separato (obsidian-linkify.sh check/apply) — è rimasta pending, non committata, per giorni,
# esattamente il tipo di "disciplina che deve ricordarsi da sola" che l'ecosistema rifiuta (AP-006).
# Qui è nativo del commit stesso: scrivi "AP-006" in prosa, il gate lo converte in wikilink prima
# che il commit si chiuda — non c'è più uno stato "pending di conversione" da tenere a mente.
# Best-effort (non hard-block): un fallimento qui è quasi sempre un problema d'ambiente (python3
# assente), non un problema di contenuto — il tool stesso protegge i block-id di definizione
# (^AP-0xx), quindi un successo è per costruzione sicuro da auto-stagare.
if [ -x "tools/obsidian-linkify.sh" ] && [ -e "ANTIPATTERNS.md" ]; then
  MD_STAGED=$(git diff --cached --name-only --diff-filter=ACM 2>/dev/null | grep -E '\.md$' || true)
  if [ -n "$MD_STAGED" ]; then
    if bash tools/obsidian-linkify.sh apply $MD_STAGED 2>/tmp/obsidian-linkify-gate.err; then
      CHANGED=0
      for f in $MD_STAGED; do
        [ -f "$f" ] && { git diff --quiet -- "$f" 2>/dev/null || { git add "$f"; CHANGED=1; }; }
      done
      [ "$CHANGED" -eq 1 ] && echo "→ Gate 0b: wikilink AP-0xx densificati automaticamente nei .md staged (auto-staged)."
    else
      echo "⚠ Gate 0b: obsidian-linkify.sh apply non riuscito (non blocca — verifica a mano se serve):" >&2
      sed 's/^/  /' /tmp/obsidian-linkify-gate.err >&2 2>/dev/null || true
    fi
    rm -f /tmp/obsidian-linkify-gate.err
  fi
fi

# Gate U1 (universale, warning): tic verbale di riempimento nei .md (AP-007)
for f in $(git diff --cached --name-only --diff-filter=ACMR 2>/dev/null | grep -E '\.md$' || true); do
  [ -f "$f" ] || continue
  n=$(grep -ciE '\barticol(at[oaie]|ar[ei]|iamo|ando)\b' "$f" 2>/dev/null || true)
  [ -n "$n" ] && [ "$n" -gt 3 ] && echo "⚠ AP-007: 'articolato' >3 in $f ($n) — verifica retorica."
done

# Gate U2 (universale): impact-check dei simboli cross-repo (AP-059). DOPPIO REGIME dal 2026-07-08 —
# avvisa nel caso comune, BLOCCA sull'alto rischio (≥2 call-site cross-repo a confidenza alta), con
# eccezione auditata via IMPACT_GATE_OVERRIDE. Lo script sta nel clone playbook; ogni repo agganciato
# lo vede via scripts/.
#
# L'esito si PROPAGA. Fino al 2026-07-27 c'era `|| true`, che risaliva alla versione precedente del
# gate — quella sola-warning — e non fu tolto quando il gate acquisì l'hard-block: per tre settimane
# lo script ha stampato «COMMIT BLOCCATO» mentre il commit passava. Un gate che annuncia un blocco e
# lascia passare è peggio di nessun gate: addestra a ignorare i blocchi.
# Propagare è sicuro perché il gate è best-effort per costruzione: senza token, con HenaxMetrics
# irraggiungibile o sui casi a basso rischio esce 0 in silenzio. Blocca SOLO dove ha deciso di farlo.
IMPACT_GATE=""
for cand in "scripts/impact-gate.sh" "$(dirname "$0")/impact-gate.sh"; do [ -r "$cand" ] && { IMPACT_GATE="$cand"; break; }; done
if [ -n "$IMPACT_GATE" ]; then bash "$IMPACT_GATE" || FAIL=1; fi

# Gate NOMI (AP-086, concilia 2026-07-30): un simbolo di modulo < 3 caratteri è invisibile a
# codemappa/hm-impact — quindi a OGNI analisi d'impatto, U2 compreso. BLOCCA sui nuovi; il
# legacy si bonifica con Serena (rename_symbol propaga). L'esito si propaga (lezione del
# `|| true` di U2 qui sopra: un blocco annunciato che lascia passare addestra a ignorarlo).
NOMI_GATE=""
for cand in "scripts/nomi-simboli-gate.sh" "$(dirname "$0")/nomi-simboli-gate.sh"; do [ -r "$cand" ] && { NOMI_GATE="$cand"; break; }; done
if [ -n "$NOMI_GATE" ]; then bash "$NOMI_GATE" || FAIL=1; fi

# Avviso impatto INTERNO (complemento di U2, concilia 2026-07-30): U2 guarda chi rompi negli
# ALTRI repo; questo elenca i call-site di QUESTO repo quando il diff tocca shared/. Solo
# informativo per costruzione (exit 0 sempre): il giudizio resta a chi committa.
IMPATTO_INT=""
for cand in "scripts/impatto-interno.sh" "$(dirname "$0")/impatto-interno.sh"; do [ -r "$cand" ] && { IMPATTO_INT="$cand"; break; }; done
if [ -n "$IMPATTO_INT" ]; then bash "$IMPATTO_INT" || true; fi

# ── LA COPIA CHE DIVERGE DALLA SUA ORIGINE, e lo dice invece di tacerlo ──────────────────────────
# Questo file e' COPIATO in ogni repo agganciato: aggiornare la playbook non aggiorna i 35 cloni, e
# una guardia nuova resta inattiva ovunque tranne dove qualcuno la installa a mano. E' la stessa
# forma dei cloni disallineati misurata il 2026-08-05 — due copie che sembrano la stessa cosa e
# nessuna misura che le distingua — applicata allo strumento che dovrebbe scovarla.
# NON si auto-aggiorna: sostituire un hook sotto i piedi di chi committa e' esattamente il genere
# di cosa che questa playbook non fa. Si DICHIARA, una riga, e chi legge decide.
_PB=""
for c in "${HENAXIS_PLAYBOOK:-}" /opt/henaxis-playbook "$HOME/henaxis-playbook"; do
  [ -n "$c" ] && [ -r "$c/tools/anti-drift-hook.sh" ] && { _PB="$c"; break; }
done
if [ -n "$_PB" ] && [ -r "scripts/anti-drift-hook.sh" ]; then
  _MIO="$(md5sum < scripts/anti-drift-hook.sh 2>/dev/null | cut -d' ' -f1)"
  _ORIG="$(md5sum < "$_PB/tools/anti-drift-hook.sh" 2>/dev/null | cut -d' ' -f1)"
  if [ -n "$_MIO" ] && [ -n "$_ORIG" ] && [ "$_MIO" != "$_ORIG" ]; then
    echo "· il tuo scripts/anti-drift-hook.sh DIVERGE da quello della playbook: gate nuovi o corretti"
    echo "  potrebbero non girare qui. GUARDA la differenza prima di decidere:"
    echo "      diff scripts/anti-drift-hook.sh $_PB/tools/anti-drift-hook.sh"
    echo "  Se la divergenza e' DELIBERATA (dichiarata nel file), riportala nella copia nuova invece di"
    echo "  perderla: un cp sopra una divergenza voluta e' AP-087 con le istruzioni allegate — misurato"
    echo "  il 2026-09-03, quando un cp ha cancellato la restrizione DD-001 di henax-hwlab."
  fi
fi

# ── SCOPI MISTI: l'infrastruttura della playbook non entra nel commit di qualcun altro (AP-003) ──
# `setup-repo.sh` mette in STAGING docs/ e scripts/ e non committa mai — di proposito, perche' nessuno
# deve committare al posto tuo. Ma il seguito non arriva: MISURATO il 2026-08-07, 26 repo su 35 avevano
# quegli 8 file fermi nell'indice da settimane, e il prossimo `git commit` se li porta dentro senza dire
# nulla. Non e' teorico ed e' gia' successo almeno tre volte: henaxmetrics 50cbcf1 «fix(sicurezza):
# secret-debole = placeholder» contiene 63 righe di scripts/news-hook.sh e una modifica a
# docs/CLAUDE-core.md. Chi revertisse quel fix revertirebbe anche l'hook; chi cerca «quando e' cambiato
# news-hook» finisce su un commit di sicurezza. E dev-domicare il 03/08 se n'e' accorta solo perche' un
# ALTRO gate si e' lamentato di un file non suo — cioe' per fortuna.
#
# La guardia NON committa e NON toglie nulla dall'indice: separa. Un commit di sola infrastruttura
# passa (e' un `chore(playbook)` legittimo); un commit di solo lavoro passa; il MISCUGLIO no.
# L'ELENCO E' QUELLO CHE `setup-repo.sh` SCRIVE DAVVERO, non quello che ricordavo. La prima stesura
# copriva solo docs/ e scripts/, e al PRIMO uso vero — l'aggancio di alamatris, 2026-08-14 — ha
# chiamato «il tuo lavoro» .mcp.json, .claude/settings.json e .serena/, che l'aggancio produce
# esattamente come gli altri. Una guardia che sbaglia a dire di chi e' un file insegna a ignorarla,
# ed e' il modo piu' rapido per rendere inutile un gate corretto nel merito.
# INFRASTRUTTURA E' CIO' CHE setup-repo SCRIVE, NON docs/ INTERO.
# Misurato da manager-hwlab il 2026-09-03, due volte nello stesso giorno:
#  1. il rito di chiusura PRETENDE che un commit CAD aggiorni docs/handoff/NEXT-SESSION.md, e questa
#     guardia — classificando docs/ intero come infrastruttura — VIETAVA quello stesso commit per
#     scopi misti. Due gate in contraddizione: obbedire all'uno significava violare l'altro.
#  2. nel monorepo hardware docs/tech/ELETTRONICA-CLAUDE.md E' IL LAVORO (1950 righe di metodo), e la
#     risincronizzazione di questa copia ha CANCELLATO la restrizione locale che lo diceva (DD-001),
#     perche' il rimedio suggerito era un `cp` sopra una divergenza deliberata.
# La distinzione giusta non e' la cartella: e' «file CREATO da setup-repo» contro «file il cui
# CONTENUTO e' lavoro». NEXT-SESSION.md e decisions.md li CREA setup-repo, ma da vuoti: cio' che
# contengono e' lavoro di chi li riempie, e un gate che li chiama infrastruttura giudica il
# contenitore al posto del contenuto.
# L'ELENCO DEGLI SCRIPT VENDORIZZATI NON SI TIENE A MANO (AP-084). La riga qui sotto la RISCRIVE
# `setup-repo.sh` al momento della copia, dai propri `cp`: e' la stessa fonte che li installa, quindi
# non puo' divergere. Tenuta a mano era gia' divergente — elencava `playbook-gates`, che setup-repo
# non installa piu', e ogni script nuovo entrava nell'elenco solo se qualcuno si ricordava di
# aggiungerlo qui. Il valore scritto qui e' il fallback per una copia mai passata da setup-repo.
# <<VENDORIZZATI-DERIVATI-DA-SETUP-REPO>> — non modificare a mano: la riga seguente e' generata
VENDORIZZATI='anti-drift-hook|debito-in-scope|impact-gate|impatto-interno|news-hook|nomi-simboli-gate|riavvio-gate|serena-gate-hook|session-bootstrap|sync-claude-shared|test-evidence-hook'
INFRA_RE="^(docs/CLAUDE-core\.md\$|docs/README\.md\$|CLAUDE\.md\$|\.mcp\.json\$|\.claude/|\.serena/|scripts/($VENDORIZZATI)\.)"
# UN MERGE NON SI PUO' SPEZZARE, E QUESTA GUARDIA CHIEDEVA DI SPEZZARLO.
# Segnalato il 2026-09-03 da allenamento-d-hwlab (9b55a30f) via manager-hwlab, sul primo uso vero
# della catena hwlab: un merge di `main` che tocca gli hook faceva scattare SCOPI MISTI. Ma in un
# merge l'elenco dei file NON e' una scelta di chi committa — e' il risultato della fusione, e un
# merge commit non si divide in due. La via pulita restava «copiare gli hook da main PRIMA del
# merge» (fatto davvero, 42f62e1): funziona, ed e' un rito.
#
# La guardia esiste per una cosa sola: impedire che file altrui SEDIMENTATI nell'indice — quelli
# che `setup-repo.sh` mette in staging e non committa mai — finiscano dentro il commit di qualcun
# altro. In un merge, in un cherry-pick e in un revert nessuno ha messo in staging niente: non c'e'
# accumulo da prevenire, e chiedere di separare significa chiedere l'impossibile.
#
# E una guardia che chiede l'impossibile insegna a usare `--no-verify`, cioe' spegne SE' STESSA e
# tutte le altre. E' il danno peggiore che possa fare un gate corretto nel merito.
#
# SI SALTA DICHIARANDOLO, mai in silenzio: un gate che tace quando non guarda e' indistinguibile da
# uno che ha guardato e approvato.
#
# COSA NON COPRE, misurato su banco e non dedotto: `git cherry-pick -n` (e `revert -n`) NON scrivono
# CHERRY_PICK_HEAD, quindi li' il gate scatta ancora. Non lo considero un buco: con `-n` chi lavora
# ha chiesto esplicitamente di fermarsi in staging, e da quel momento l'indice E' una sua scelta —
# puo' separare. Il caso che non si puo' separare e' quello in cui git ti mette davanti un commit
# gia' formato, e quello e' coperto.
GITDIR="$(git rev-parse --git-dir 2>/dev/null || echo .git)"
FUSIONE=""
for m in MERGE_HEAD CHERRY_PICK_HEAD REVERT_HEAD; do
  [ -f "$GITDIR/$m" ] && { FUSIONE="$m"; break; }
done

STAGED="$(git diff --cached --name-only 2>/dev/null)"
if [ -n "$FUSIONE" ]; then
  case "$FUSIONE" in
    MERGE_HEAD)       _op="un MERGE" ;;
    CHERRY_PICK_HEAD) _op="un CHERRY-PICK" ;;
    *)                _op="un REVERT" ;;
  esac
  echo "○ SCOPI MISTI: gate SALTATO — siamo dentro $_op ($FUSIONE), e l'elenco dei file non e' una tua scelta."
  echo "  Un commit di fusione non si puo' spezzare in due, quindi qui la guardia chiederebbe l'impossibile."
  echo "  Resta vero il motivo per cui esiste: dopo il merge, controlla che l'indice non abbia file di"
  echo "  infrastruttura SEDIMENTATI da un setup — 'git diff --cached --name-only' te li mostra."
elif [ -n "$STAGED" ]; then
  INFRA="$(printf '%s\n' "$STAGED" | grep -E "$INFRA_RE" || true)"
  ALTRO="$(printf '%s\n' "$STAGED" | grep -vE "$INFRA_RE" || true)"
  # IL COMMIT DI AGGANCIO E' UNO SCOPO SOLO. decisions.md, NEXT-SESSION.md e tech/INDEX.md sono
  # «contenuto di chi li riempie» (e6c6152) — ma al momento dell'aggancio NESSUNO li ha ancora
  # riempiti: sono i modelli vuoti che setup-repo ha appena scritto, e insieme agli altri file di
  # setup formano UN atto, non due. Senza questo caso, la correzione di oggi bocciava ogni aggancio:
  # terza occorrenza dell'exit 1 che inseguivo da due (alamatris 14/08, henax-hwlab 01/09) — quelle
  # due erano il gate NOMI su debito-in-scope.mjs, questa l'avevo appena creata io.
  # Il criterio e' stretto: SOLO quel trio, e SOLO quando c'e' anche l'infrastruttura di setup in
  # staging. Un decisions.md modificato in un commit di lavoro qualunque resta lavoro.
  if [ -n "$INFRA" ] && [ -n "$ALTRO" ]; then
    _fuori_trio="$(printf '%s\n' "$ALTRO" | grep -vE '^docs/(decisions\.md|handoff/NEXT-SESSION\.md|tech/INDEX\.md)$' || true)"
    if [ -z "$_fuori_trio" ] && printf '%s\n' "$INFRA" | grep '^docs/CLAUDE-core\.md$' >/dev/null; then
      echo "○ SCOPI MISTI: i soli file non-infrastruttura sono i modelli vuoti dell'aggancio (decisions/NEXT-SESSION/INDEX) — e' il commit di aggancio, uno scopo solo."
      ALTRO=""
    fi
  fi
  # CLAUDE.md DA SOLO NON E' «INFRASTRUTTURA IN STAGING»: e' un artefatto che questo stesso script
  # ha appena rigenerato e messo in staging (Gate 0), non un file che qualcuno ha scelto di
  # committare. Contarlo come scopo misto significa che DUE GATE DELLO STESSO SCRIPT si annullano:
  # il primo aggiunge il file, il secondo rifiuta il commit per quel file — e in un repo dove
  # docs/CLAUDE-core.md e' rimasto modificato dalla propagazione, questo accade a OGNI commit.
  # Misurato il 16/09 in /opt/codemeris: nessun commit passava, qualunque cosa si mettesse in
  # staging, nemmeno un indice temporaneo con un solo file di lavoro. Una sessione ha lavorato,
  # non ha potuto committare il proprio handoff, e ha fatto la cosa giusta: lo ha detto invece di
  # aggirare il gate. Una guardia che chiede l'impossibile insegna --no-verify, cioe' spegne se'
  # stessa e tutte le altre — e' scritto trenta righe piu' su, a proposito dei merge, e vale qui.
  # `.gitignore` ACCANTO ALL'INFRASTRUTTURA E' INFRASTRUTTURA (17/09, misurato su 32 repo).
  # setup-repo non lo copia: ci APPENDE (`.serena/cache/`, `.mcp.json`), quindi non compariva
  # nell'elenco dei vendorizzati e il gate lo contava come «il tuo lavoro». Risultato: un commit di
  # sola propagazione veniva rifiutato per scopi misti in 32 repo su 34, e la via d'uscita suggerita
  # — togliere l'infrastruttura dall'indice — avrebbe lasciato proprio i file da committare.
  # Terza volta in due giorni che questo gate blocca un commit che non ha nulla di misto: prima
  # CLAUDE.md rigenerato, poi il commit di aggancio, ora questo. La forma e' sempre la stessa —
  # un file che la playbook tocca da se' e che il gate non riconosce come proprio.
  # Resta stretto: solo QUANDO c'e' anche infrastruttura vera. Un `.gitignore` modificato da solo,
  # o insieme a codice, resta lavoro e il gate non lo tocca.
  if [ -n "$INFRA" ] && [ "$ALTRO" = ".gitignore" ]; then
    echo "○ SCOPI MISTI: l'unico file non-infrastruttura e' .gitignore, a cui setup-repo APPENDE durante l'aggancio — parte della propagazione, non lavoro."
    ALTRO=""
  fi
  if [ -n "$INFRA" ] && [ -n "$ALTRO" ] && [ "$INFRA" = "CLAUDE.md" ]; then
    echo "○ SCOPI MISTI: l'unico file di infrastruttura e' CLAUDE.md, rigenerato da questo stesso script poco fa (Gate 0) — artefatto, non una scelta di chi committa."
    INFRA=""
  fi
  if [ -n "$INFRA" ] && [ -n "$ALTRO" ]; then
    echo ""
    echo "✗ SCOPI MISTI (AP-003): stai per committare INSIEME l'infrastruttura della playbook e il tuo lavoro."
    echo "  infrastruttura in staging ($(printf '%s\n' "$INFRA" | wc -l) file):"
    printf '%s\n' "$INFRA" | sed 's/^/      /'
    echo "  il tuo lavoro ($(printf '%s\n' "$ALTRO" | wc -l) file): $(printf '%s\n' "$ALTRO" | head -3 | tr '\n' ' ')..."
    echo ""
    echo "  Perche' e' bloccato: fusi insieme, un revert del tuo fix revertirebbe anche gli hook, e"
    echo "  l'archeologia su «quando e' cambiato quello script» finirebbe su un commit che non lo riguarda."
    echo ""
    echo "  Due modi, scegli tu — nessuno dei due committa al posto tuo:"
    echo "      git restore --staged $(printf '%s\n' "$INFRA" | tr '\n' ' ')   # i file restano sul disco, escono dall'indice"
    echo "      oppure committa PRIMA l'infrastruttura da sola, con scope  chore(playbook):"
    FAIL=1
  fi
fi

# Overlay per-repo: gate specifici della verticalizzazione (TS tsc, brand voice, design token...)
if [ -x "scripts/playbook-gates.sh" ]; then
  if ! bash scripts/playbook-gates.sh; then FAIL=1; fi
fi

[ "$FAIL" -eq 1 ] && { echo ""; echo "✗ COMMIT BLOCCATO dai gate qui sopra (scripts/playbook-gates.sh): ogni ✗ dice il file e la regola violata — correggi quelli e ritenta."; exit 1; }
exit 0
