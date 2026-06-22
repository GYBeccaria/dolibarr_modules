# CLAUDE.md — dolibarr_modules


<!-- BEGIN SHARED (auto-generato da docs/CLAUDE-core.md, NON editare a mano) -->
<!-- henaxis-playbook · shared core · version: 1.2 · last_review: 2026-06-22 -->
# Henaxis — convenzioni condivise (auto-caricate)

> Blocco condiviso da **henaxis-playbook** (`shared/CLAUDE-core.md`), inserito automaticamente nel
> `CLAUDE.md` locale tra i marker SHARED. NON editare a mano qui: si modifica nella playbook e si
> propaga (vedi REFINEMENT.md). SoT completa: principi e anti-pattern per esteso nella playbook.

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

## Precedenza
In caso di conflitto, **queste convenzioni condivise prevalgono** sul contenuto locale del
`CLAUDE.md` di questo repo (salvo un blocco `<!-- OVERRIDE shared: … -->` esplicito). Così un
doppione locale che concorda è innocuo e una contraddizione è risolta per regola, senza review manuale.

## Commit
Sezione `ANTI-DRIFT CHECK` obbligatoria in fondo a ogni commit (vedi COMMIT-CONVENTION.md).
Identità: **Giuliano Yurij Beccaria** (nome completo nei documenti formali).

Catalogo completo anti-pattern: `ANTIPATTERNS.md` della playbook.
<!-- END SHARED -->
