# CLAUDE.md — dolibarr_modules


<!-- BEGIN SHARED (auto-generato da docs/CLAUDE-core.md, NON editare a mano) -->
<!-- henaxis-playbook · shared core · version: 1.15 · last_review: 2026-07-02 -->
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
- **News**: canale unico `origin/news`; `post` con esito **"PUSHATA"** verificato; type da **enum** (deploy/decision/rules/tool/progress/incident/handoff/info), sintesi ≤500 char, refs per deploy/decision — gate applicato da `news.sh` — protocollo **NEWS.md** (AP-043/047).
- **Kanban & presidio**: task su `tasks.json` via `task.sh`; DoD = **LIVE & verificato**, non solo merged; presidio TTL 24h, auto-heartbeat — **KANBAN.md** (AP-046/048/054).
- **Bump versione a ogni modifica frontend** nello stesso commit, visibile in UI (AP-045).
- **Delega agentica solo con contratto** (TASK-CONTRACT.md): scope + tool whitelist + gate macchina + checkpoint; l'agente non auto-dichiara "fatto" (AP-041).
- **Tool**: prima di scrivere uno script consulta **TOOLS.md** (+ `docs/tech/TOOLS.md` del repo); tool nuovo/esteso → registro con provenienza + news (AP-056).

## Precedenza
In caso di conflitto, **queste convenzioni condivise prevalgono** sul contenuto locale del
`CLAUDE.md` di questo repo (salvo un blocco `<!-- OVERRIDE shared: … -->` esplicito). Così un
doppione locale che concorda è innocuo e una contraddizione è risolta per regola, senza review manuale.

## Commit
Sezione `ANTI-DRIFT CHECK` + trailer `Session: <nome> (<hex8>)` obbligatori (hook-enforced) — COMMIT-CONVENTION.md (AP-055).
Identità: **Giuliano Yurij Beccaria** (nome completo nei documenti formali).
<!-- END SHARED -->
