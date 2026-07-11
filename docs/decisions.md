# Decisioni di progetto (DD)

Decisioni specifiche di questo verticale (architettura, dominio, trade-off). Le decisioni che
valgono per tutte le repo NON stanno qui: vanno nella playbook.

Formato: `DD-NNN — Titolo` · data · stato (attiva/superata) · contesto · decisione · conseguenze.

---

## DD-001 — Solo move a /opt/dolibarr_modules

- **Data**: 2026-07-11
- **Stato**: attiva
- **Contesto**: topologia paritetica piatta (playbook TOPOLOGY.md, AP-075) — un repo = una
  directory top-level `/opt`. Nome già conforme, nessun rename GitHub necessario. Migrazione
  batch (14 repo) eseguita prima dello switch-off pymig del 17/07 (direttiva umana: chiudere il
  debito di topologia, non lasciarlo indietro).
- **Decisione**: clone fresco a `/opt/dolibarr_modules` dallo stesso remote GitHub. Nessun rename, presidio
  libero, tutto già pushato sul mirror di origine.
- **Conseguenze**: nessun impatto su codice/DB. Continuità metrica verificata (batch, via
  `npm run analyze` pre/post su tutta la flotta): 56 commit/€47362 presenti una sola
  volta, zero doppioni malgrado mirror+clone coesistenti (dedup per remote-URL in
  `repoIdentity`). Mirror `/opt/p2g_dev/dolibarr_modules` NON rimosso ora (rollback) — via alla wave
  successiva.
