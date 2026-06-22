# CLAUDE.md — dolibarr_modules


<!-- BEGIN SHARED (auto-generato da docs/CLAUDE-core.md, NON editare a mano) -->
<!-- henaxis-playbook · shared core · version: 1.7 · last_review: 2026-06-22 -->
# Henaxis — convenzioni condivise (auto-caricate)

> Blocco condiviso da **henaxis-playbook** (`shared/CLAUDE-core.md`), inserito automaticamente nel
> `CLAUDE.md` locale tra i marker SHARED. NON editare a mano qui: si modifica nella playbook e si
> propaga (vedi REFINEMENT.md). SoT completa: principi e anti-pattern per esteso nella playbook.

## Come usare questo CLAUDE.md (orientamento — vale a OGNI sessione, leggi prima)
Hai **due corpi di conoscenza complementari**: usali **entrambi**, non sottovalutarne nessuno.
- **PLAYBOOK (orizzontale = *come lavoriamo*)**: regole, anti-pattern, strumenti. Questo blocco SHARED ne è
  il nucleo auto-caricato; per esteso → repo **henaxis-playbook**: `PRINCIPLES`, `ANTIPATTERNS`,
  `COMMIT-CONVENTION`, `BRANCHING`, `TASK-CONTRACT`, `MANIFEST`, `SERENA`, `REFERENCES`.
- **DOCS/ del repo (verticale = *cos'è questo modulo*)**: `docs/README` (mappa), `docs/decisions.md` (DD),
  `docs/handoff/`, `docs/tech/` (note tecniche, PROD, integrazioni), `docs/CLAUDE-core.md` (copia vendorizzata del playbook).
- **Codice reale → indice Serena** (`.serena/`, vedi `SERENA.md`): naviga simboli/riferimenti invece di leggere a tappeto.

**Ordine a inizio sessione**: (1) questo blocco SHARED (regole) → (2) intro/scopo locale + sezioni tecniche del
repo → (3) `docs/` per lo specifico del modulo → (4) la pagina giusta del playbook quando serve (`REFERENCES`
per le API, `TASK-CONTRACT` per delegare, `MANIFEST`/`SERENA` per capabilities e codice).

> **Regola di non-sottovalutazione**: *playbook senza `docs/` = regole senza contesto; `docs/` senza playbook =
> contesto senza regole né strumenti.* Servono **entrambi**, sempre.

## Principi (sintesi)
- **Qualità a 15 anni**: scegli ciò che semplifica il futuro, non ciò che risparmia oggi.
- **Fondamenta solide**: stratifica sopra i framework, non li forki dove basta estendere.
- **Onestà operativa**: distingui "gira oggi (test verde empirico)" da "deciso in roadmap".
- **Strumento > disciplina**: se uno script/symlink/tipo risolve, usalo (non un pattern da ricordare).
- **Commit atomici**: 1 scope coeso = 1 commit, frequenti.
- **1 sessione per repo**: parallelismo solo su repo distinti.
- **Decisione all'umano**: **commit**, push, deploy, transfer, delete si propongono e si attende l'OK esplicito (no commit/push unprompted, no `--force`).
- **Plan first**: prima il piano, poi l'esecuzione step-by-step. **Reasoning over patterns**: segui il ragionamento, non il pattern di default, e dichiaralo.

## Regole operative sempre attive
- Ogni "verde" ha un **comando shell** dietro (AP-001). Niente self-grading.
- Smoke test sul **path completo** (HTTP→auth→DB), mai bypass di layer (AP-034).
- Verifica i **tool shell** prima di usarli (`jq`, `rg`…) (AP-028); `gh api --jq`, non pipe a python (AP-033).
- Niente `git stash pop`/`merge`/`rebase` su path **bind-montati live** in produzione (AP-027).
- Nei comandi shell usa `$VAR`, mai placeholder `<X>` (AP-031).
- Fedeltà alla **fonte autoritativa**, bidirezionale (niente in più né in meno) (AP-038).
- Per API/framework consulta le **fonti tecniche canoniche** (REFERENCES.md della playbook), non la memoria: versioni e API cambiano (AP-001/AP-028). Core Dolibarr pinnato **22.0.3**.
- **PROD/runtime è la fonte di verità** (`main` ≠ deployed): mai auto-sync/overwrite di PROD da un branch; deploy solo via wrapper autorizzato, per-intervento; il cleanup git non propaga a PROD (AP-040).
- **Delega agentica solo con contratto** (TASK-CONTRACT.md): scope + tool whitelist + gate di verifica macchina + checkpoint. Autonomia per raggio d'impatto (reversibile→alta, PROD/dati→gate umano). L'agente non auto-dichiara "fatto" (AP-001/AP-041).

## Precedenza
In caso di conflitto, **queste convenzioni condivise prevalgono** sul contenuto locale del
`CLAUDE.md` di questo repo (salvo un blocco `<!-- OVERRIDE shared: … -->` esplicito). Così un
doppione locale che concorda è innocuo e una contraddizione è risolta per regola, senza review manuale.

## Commit
Sezione `ANTI-DRIFT CHECK` obbligatoria in fondo a ogni commit (vedi COMMIT-CONVENTION.md).
Identità: **Giuliano Yurij Beccaria** (nome completo nei documenti formali).

Catalogo completo anti-pattern: `ANTIPATTERNS.md` della playbook.
<!-- END SHARED -->
