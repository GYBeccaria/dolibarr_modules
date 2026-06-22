# docs/ — verticalizzazioni di questo repo

Questa cartella contiene lo **specifico di questo repo/verticale**: ciò che NON è universale.
L'orizzontale (principi, anti-pattern, convenzioni comuni) arriva dalla **henaxis-playbook** via
`docs/CLAUDE-core.md` (symlink) auto-caricato in `CLAUDE.md`.

| qui (verticale) | nella playbook (orizzontale) |
|---|---|
| decisioni di questo progetto (`decisions.md`) | principi comuni (`PRINCIPLES.md`) |
| handoff tra sessioni (`handoff/`) | catalogo anti-pattern (`ANTIPATTERNS.md`) |
| note tecniche del dominio (`tech/`) | convenzione commit, hook, skills |

## Struttura
- `decisions.md` — Decisioni di progetto (DD-NNN): architetturali/di dominio, con data e motivazione.
- `handoff/NEXT-SESSION.md` — stato corrente + prossima azione, per riprendere senza perdere contesto.
- `tech/INDEX.md` — indice delle note tecniche specifiche (integrazioni, gotcha, runbook).
- `CLAUDE-core.md` — **symlink** al SoT condiviso della playbook (NON editare: vedi REFINEMENT.md).

Regola: se una nota vale per **tutte** le repo, non sta qui — va proposta alla playbook (skill
`capture-antipattern` o PR). Qui sta solo ciò che è proprio di questo verticale.
