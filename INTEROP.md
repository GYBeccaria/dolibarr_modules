# Contratto di interoperabilità

Le tre regole che rendono il set **portabile** (riusabile su altri clienti) e **interoperabile** (i moduli si compongono senza accoppiarsi).

## 1. Architettura a livelli

```
L0  librerie piattaforma   henax-ai (client LLM multi-provider + manifest engine + discovery) · henax-chat (Matrix) · henax-export
L1  capability/moduli       henax-docflow (pipeline documentale) · henax-fse · henax-analytics · henax-help · henax-architect(UI grafo) · …
    henax-*                 orizzontali, riusabili — dipendono SOLO da L0 + Dolibarr core
L2  verticali               domicare-* / henaxis-<cliente> — dipendono da L1; sono CONFIG/profili + adapter
```

Una dipendenza punta **solo verso il basso** (L2→L1→L0). Mai L1→L2. Mai L1→L1 su classi interne: si passa per hook/manifest.

Modello ibrido (deciso): per una capability orizzontale, il **core** (motore, stadi, orchestrazione) sta in L1; gli **adapter molto specifici** del cliente (es. renderer perizia DOCX, profilo schema-fisso domicare, vision lesioni) sono moduli L2 che estendono il core **via hook**, non per ereditarietà.

## 2. Accoppiamento via hook + manifest, non per classe

- I moduli **non** istanziano classi interne di altri moduli. Comunicano via **hook/trigger Dolibarr** e via il **manifest dichiarativo**.
- **Manifest = source-of-truth unica per modulo.** Pattern già implementato in `henax-architect` (da promuovere in `henax-ai`): un `manifest.yaml` per modulo → generati `README.md` + `architect.json` (struttura/dipendenze/tabelle/rights/cron/services) + block `skyllam` (entità/stat/prompt interrogabili). Campi target del manifest: `name, id, namespace, layer, version, depends{hard,soft}, provides[] (capabilities/hook esposti), tables[], services[], ai_queryable` (= block `skyllam`: `entities[]` con `detail_sql`, `stats[]` con `sql`).
- **La superficie AI-queryable è il contratto centrale di interoperabilità.** `henax-ai` (motore discovery + AI) costruisce dal manifest di ogni modulo: (a) il **grafo compresso** dell'architettura e (b) le **entità/stat interrogabili**. Un modulo nuovo diventa interrogabile dall'AI **solo** dichiarandosi nel manifest — senza toccare il motore. Validazione: `validate_manifests` (cross-check tabelle vs `information_schema`, sql vs `EXPLAIN`, rights vs `llx_rights_def`).

## 3. Confini dati

- Prefisso tabelle = nome modulo (vedi `NAMING.md` §4). Nessuna tabella condivisa fra moduli; l'accesso cross-modulo passa da API/hook del modulo proprietario.
- Le librerie L0 NON hanno tabelle proprie di dominio (solo cache/log tecnici, es. `llx_henaxai_cache`).

## 4. Contratto della pipeline documentale (`henax-docflow`)

La capability ricorrente *ingest → estrazione → classify → output* è un orizzontale L1. Stadi standard (lifecycle esplicito su tabella di stato, non file piatti):

```
INGEST → PREP(normalize PDF) → EXTRACT(OCR/Vision via henax-ai) → CLASSIFY(opz.) → OUTPUT_DB → OUTPUT_DOC
```

- **EXTRACT** chiama sempre `henax-ai` (mai un client LLM proprio). Lo schema di estrazione è dato da un **profilo documento** dichiarativo (campi attesi): profilo fisso (domicare) o profilo che lascia all'AI la scelta del tipo (industria40 → CLASSIFY attivo).
- **OUTPUT_DOC** passa per un'**interfaccia renderer** con backend intercambiabili dietro un'unica firma `build(extracted_json, profile) → artifact`: `fse-cda` (XML+PDF/A firmato via microservizio `henax-fse`), `docx-template` (PhpWord TemplateProcessor), `pdf`. I renderer specifici del cliente sono **adapter L2**.
- **Confine dati**: `henax-docflow` possiede solo lo stato di pipeline (`llx_henaxdocflow_job`/`_artifact`). I dati di dominio estratti vengono scritti dal verticale proprietario via la sua API/hook (es. domicare consolida in `societe`/PAI), mai da docflow direttamente.

## Definition of Done per un modulo conforme
- [ ] nome conforme a `NAMING.md` · [ ] `manifest.json` presente e valido · [ ] dipendenze solo verso il basso · [ ] tabelle col proprio prefisso · [ ] nessuna classe L0 duplicata internamente · [ ] riga aggiornata in `REGISTRY.md`.
