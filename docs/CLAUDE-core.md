<!-- henaxis-playbook · shared core · version: 1.19 · last_review: 2026-10-18 -->
# Henaxis — convenzioni condivise (auto-caricate)

> Blocco condiviso da **henaxis-playbook** (`shared/CLAUDE-core.md`), inserito automaticamente nel
> `CLAUDE.md` locale tra i marker SHARED. NON editare a mano qui: si modifica nella playbook e si
> propaga (vedi REFINEMENT.md). Ogni riga è una **sintesi**: il dettaglio vive nel file-casa citato.

## Come usare questo CLAUDE.md (orientamento — vale a OGNI sessione, leggi prima)
Hai **due corpi di conoscenza complementari**: usali **entrambi**, non sottovalutarne nessuno.
- **PLAYBOOK (orizzontale = *come lavoriamo*)**: regole, anti-pattern, strumenti. Questo blocco SHARED ne è
  il nucleo auto-caricato; per esteso → repo **henaxis-playbook**: `PRINCIPLES`, `ANTIPATTERNS`, `TOOLS`,
  `COMMIT-CONVENTION`, `DEPLOY`, `NEWS`, `KANBAN`, `BRANCHING`, `TASK-CONTRACT`, `MANIFEST`, `SERENA`, `REFERENCES`, `DESIGN`.
- **DOCS/ del repo (verticale = *cos'è questo modulo*)**: `docs/README` (mappa), `docs/decisions.md` (DD),
  `docs/handoff/`, `docs/tech/` (note tecniche, PROD, integrazioni, `TOOLS.md` del repo).
- **Codice reale → indice Serena** (`.serena/`, vedi `SERENA.md`): naviga simboli/riferimenti invece di leggere a tappeto.
- **Anti-pattern → a richiesta, non a tappeto.** L'indice è `ANTIPATTERNS-INDEX.md` (un rigo per AP, basta a
  decidere *se* guardare); la voce intera si tira con `node <playbook>/tools/ap.mjs AP-042` (o una parola, o il
  numero nudo). Funziona con o senza skill: chi le supporta ha `skills/antipattern`, che chiama lo **stesso**
  strumento e non contiene una copia del catalogo. Gli AP che non devono dipendere dall'essere letti sono
  imposti da gate e hook, che scattano comunque.

> **Regola di non-sottovalutazione**: *playbook senza `docs/` = regole senza contesto; `docs/` senza playbook =
> contesto senza regole né strumenti.* Servono **entrambi**, sempre.

## Principi (sintesi — per esteso: PRINCIPLES.md)
- **Qualità a 15 anni**: scegli ciò che semplifica il futuro, non ciò che risparmia oggi.
- **Fondamenta solide**: stratifica sopra i framework, non li forki dove basta estendere.
- **Onestà operativa**: distingui "gira oggi (test verde empirico)" da "deciso in roadmap".
- **Strumento > disciplina** (AP-006): se uno script/hook/tipo risolve, usalo — non un pattern da ricordare.
- **Commit atomici**: 1 scope coeso = 1 commit, frequenti (COMMIT-CONVENTION.md).
- **1 sessione per repo / presidio** (AP-046, KANBAN.md r.8): l'heartbeat è AUTO a inizio sessione; prima di
  implementare in un ALTRO progetto `tools/presence.sh check <repo>` — se presidiato, INSTRADA il lavoro lì.
  Regole/strumenti trasversali (playbook, deploy wrapper, news, design) restano leciti da ogni sessione.
- **Decisione all'umano**: commit, push, deploy, transfer, delete si propongono e si attende l'OK esplicito (no `--force`).
- **Plan first / Reasoning over patterns**: prima il piano; segui il ragionamento, non il pattern di default, e dichiaralo.
- **Perimetri & finalità** (P11/AP-044): dichiara il modo — *verticale-cliente* (perimetri rigidi, mai sconfinare,
  nemmeno leggere) vs *orizzontale-piattaforma*. Fine: sistemi omogenei, meno sovrapposizioni, nuove funzioni.

## Regole operative sempre attive
- Ogni "verde" ha un **comando shell** dietro (AP-001); smoke sul **path completo** HTTP→auth→DB (AP-034); fedeltà **bidirezionale** alla fonte autoritativa (AP-038).
- Shell: verifica i tool prima di usarli (AP-028); `gh api --jq`, non pipe fragili (AP-033); `$VAR`, mai `<X>` (AP-031); niente git sporco su bind-mount live (AP-027).
- API/framework: consulta le **fonti canoniche** (REFERENCES.md), non la memoria. Core Dolibarr pinnato **22.0.3**.
- **PROD/runtime è la fonte di verità** (`main` ≠ deployed): deploy SOLO via wrapper per-intervento, mai overwrite/`--force` — contratto **DEPLOY.md** (AP-040). Riferimenti PROD sempre **espliciti**: host + IP + ruolo + container/DB (AP-042).
- **News**: canale unico `origin/news`; `post` con esito **"PUSHATA"** verificato; type da **enum** (deploy/decision/rules/tool/progress/incident/handoff/info), sintesi ~500 char (mai rifiutata: >1200 auto-spill in nota), refs per deploy/decision — gate applicato da `news.sh` — protocollo **NEWS.md** (AP-043/047/074).
- **Kanban & presidio**: task su `tasks.json` via `task.sh`; DoD = **LIVE & verificato**, non solo merged; presidio TTL 24h, auto-heartbeat — **KANBAN.md** (AP-046/048/054).
- **Bump versione a ogni modifica frontend** nello stesso commit, visibile in UI (AP-045).
- **Delega agentica solo con contratto** (TASK-CONTRACT.md): scope + tool whitelist + gate macchina + checkpoint; l'agente non auto-dichiara "fatto" (AP-041).
- **Delega che non spreca** (umano, 10/10; misurato: i sotto-agenti erano il 65% della spesa): un agente = **un pezzo**. Tipi con tetto di giri: `esecutore` (lavoro meccanico, Sonnet), `pezzo`, `verificatore`; mai `general-purpose` né `fork` per lavoro lungo (il fork nasce col contesto della madre). Nessun comando in primo piano oltre 4 minuti, stato su file, risposta corta — TASK-CONTRACT.md.
- **Tool**: prima di scrivere uno script consulta **TOOLS.md** (+ `docs/tech/TOOLS.md` del repo); tool nuovo/esteso → registro con provenienza + news (AP-056). **Mai errori/codici nudi, sempre spiegazioni**: ogni rifiuto di gate/tool/UI dice cosa è fallito, perché (AP) e il fix copiabile; e **ogni uscita**, anche il verde, dice cosa ha controllato, quanto e con quale esito (AP-057; umano, 18/10). **Lo strumento è superiore alla regola** ogni volta che è possibile (AP-006).
- **Lessico** (AP-127; umano, 18/10: «a definizione uguale deve corrispondere un comportamento uguale»): il vocabolario del repository, acronimi compresi con spiegazione e link, sta in `docs/lessico.json`; `tools/lessico.py spiega <termine>` lo consulta, `valida` lo controlla, e a ogni commit avvisa degli usi incoerenti (oggi **avvisa, non blocca**).
- **Prompt minimi di risveglio** («aggiornati», «news», «fai quello che devi»…): sono un **ordine di eseguire**, non una domanda — l'hook inietta l'orientamento completo (twin, presidio, diretti, handoff, **e i TUOI task**: in-corso di tua proprietà + disponibili nel tuo perimetro; SESSION-AUTOMATION.md). Da lì **GUIDA i tuoi task — falne avanzare almeno UNO ORA**, non solo orientarti. Mai rispondere chiedendo "cosa vuoi che faccia". **Un task in-corso bloccato si DICHIARA sul canale e si scavalca con un disponibile — non giustifica l'attesa**; ci si ferma SOLO ai gate umani (PROD/deploy/transfer/delete/decisioni irreversibili). L'inerzia in attesa del via umano è un anti-pattern, non prudenza (AP-076).

## Avvio e dialogo tra sessioni (sempre attive — umano, 30/09: «Correggiamo subito»)
- **Niente riarmo a vuoto del Monitor** (umano, 09/10): una sessione NON tiene acceso un Monitor sul
  bus e non lo riarma a ogni scadenza. Misurato: il riarmo ogni 30 minuti valeva il 23% del consumo
  della flotta (724 $ su 3174 in sette giorni), turni che rileggevano tutto il contesto per nulla.
  L'**urgente** si manda con un messaggio diretto fra sessioni (sveglia da solo); il resto si legge
  al prompt successivo, con l'hook della posta. Un Monitor si accende solo per un'attesa precisa e
  breve, dichiarata, e si lascia scadere.
- **Posta**: leggi i diretti (`news.sh mine <hex8>`, che emette la ricevuta) a ogni notifica e prima
  di chiudere un turno lungo. **Affidi**: prendili in carico subito (`cmr.sh assign --ack <id>`) e
  **rileggi il registro** prima di dire in che stato è un affido: non dedurlo.
- **Codice → solo Serena** (regola rigida, gate in arrivo): lettura, ricerca e modifica dei file di
  codice passano da Serena; `grep`/`sed`/`cat`/`Read`/`Edit` restano per non-codice e output di comandi.
- **L'umano approva, non fa il postino**: mai chiudere un turno chiedendogli di digitare un comando
  (`! …`, «lancialo tu»). Ciò che spetta a lui gli arriva come **richiesta di approvazione** (regole
  permesso `ask`); se un'azione sua viene negata senza richiesta, manca la regola `ask`: dillo. Unica
  eccezione i comandi davvero interattivi (login, passphrase), dichiarati tali.

## Precedenza
In caso di conflitto, **queste convenzioni condivise prevalgono** sul contenuto locale del
`CLAUDE.md` di questo repo (salvo un blocco `<!-- OVERRIDE shared: … -->` esplicito). Così un
doppione locale che concorda è innocuo e una contraddizione è risolta per regola, senza review manuale.

## Commit
Sezione `ANTI-DRIFT CHECK` + trailer `Session: <nome> (<hex8>)` obbligatori (hook-enforced) — COMMIT-CONVENTION.md (AP-055).
Identità: **Giuliano Yurij Beccaria** (nome completo nei documenti formali).
