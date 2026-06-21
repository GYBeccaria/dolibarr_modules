# Contratto di interoperabilità

Le tre regole che rendono il set **portabile** (riusabile su altri clienti) e **interoperabile** (i moduli si compongono senza accoppiarsi).

## 1. Architettura a livelli

```
L0  librerie piattaforma   client AI/LLM unico · manifest engine · trasporto Matrix · export engine
L1  moduli henax-*          orizzontali, riusabili — dipendono SOLO da L0 + Dolibarr core
L2  verticali               domicare-* / henaxis-<cliente> — dipendono da L1
```

Una dipendenza punta **solo verso il basso** (L2→L1→L0). Mai L1→L2. Mai L1→L1 su classi interne: si passa per hook/manifest.

## 2. Accoppiamento via hook + manifest, non per classe

- I moduli **non** istanziano classi interne di altri moduli. Comunicano via **hook/trigger Dolibarr** e via il **manifest dichiarativo**.
- Ogni modulo espone un **`manifest.json`** che dichiara: `name, id, namespace, layer, version, depends[], provides[] (capabilities/hook esposti), tables[], ai_queryable[]`.
- `henax-ai` + `henax-architect` consumano i manifest per discovery e per la superficie AI-queryable: un modulo nuovo diventa interrogabile **solo** dichiarandosi nel manifest, senza modificare l'AI.

## 3. Confini dati

- Prefisso tabelle = nome modulo (vedi `NAMING.md` §4). Nessuna tabella condivisa fra moduli; l'accesso cross-modulo passa da API/hook del modulo proprietario.
- Le librerie L0 NON hanno tabelle proprie di dominio (solo cache/log tecnici, es. `llx_henaxai_cache`).

## Definition of Done per un modulo conforme
- [ ] nome conforme a `NAMING.md` · [ ] `manifest.json` presente e valido · [ ] dipendenze solo verso il basso · [ ] tabelle col proprio prefisso · [ ] nessuna classe L0 duplicata internamente · [ ] riga aggiornata in `REGISTRY.md`.
