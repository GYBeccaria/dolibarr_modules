# Decisioni di progetto (DD)

Decisioni specifiche di questo verticale (architettura, dominio, trade-off). Le decisioni che
valgono per tutte le repo NON stanno qui: vanno nella playbook.

Formato: `DD-NNN — Titolo` · data · stato (attiva/superata) · contesto · decisione · conseguenze.
Debito tecnico tracciato come `BL-NNN`.

---

## DD-001 — `henax-ai` come libreria di piattaforma L0 (consolidamento AI)
- **Data**: 2026-06-22
- **Stato**: attiva
- **Contesto**: AI duplicata tra `skyllam` (client LLM) e `henax-architect` (cache/log/rate-limit + manifest engine); convergenza solo nominale (`architect_ai_call_via_skyllam` era pass-through).
- **Decisione**: estrarre una L0 unica `henax-ai` (ID 580420) = UN client LLM + UN service (cache/log/rate-limit) + UN manifest engine (portato da architect). I moduli AI ci girano sopra.
- **Conseguenze**: `skyllam`/`henax-architect` diventano consumer (L1→L0); `skyllam_manifest` diventa consumer del manifest engine. Vedi `docs/tech/henax-ai.md`.

## DD-002 — Capability orizzontali: modello ibrido core L1 + adapter L2
- **Data**: 2026-06-22
- **Stato**: attiva
- **Contesto**: una capability ricorrente (es. pipeline documentale) ha un motore comune ma pezzi molto specifici per cliente.
- **Decisione**: il **core** (motore/stadi/orchestrazione) sta in L1; gli **adapter** cliente-specifici sono moduli L2 che estendono il core **via hook**, non per ereditarietà.
- **Conseguenze**: i verticali diventano *config/profili*, non re-implementazioni. Registrato in INTEROP §1.

## DD-003 — `henax-docflow` come pipeline documentale orizzontale (L1)
- **Data**: 2026-06-22
- **Stato**: attiva
- **Contesto**: il flusso *ingest→estrazione AI→classify→output(DB+documento)* è oggi implementato due volte (domicare omogeneo, industria40 eterogeneo).
- **Decisione**: capability orizzontale L1 `henax-docflow` (ID 580430) con profilo documento dichiarativo + interfaccia renderer (FSE-CDA, DOCX-template). `domicare-docai` declassato a profilo. Gira su `henax-ai`.
- **Conseguenze**: pilota di estrazione (domicare vs industria40) ancora **da decidere**. Vedi `docs/tech/henax-docflow.md`.

## DD-004 — Client AI provider-agnostic + stadio di validazione API key
- **Data**: 2026-06-22
- **Stato**: attiva
- **Contesto**: non sappiamo quale LLM usa il cliente; servono più provider e un modo per validare le key.
- **Decisione**: registry condiviso di 14 provider (quasi tutti OpenAI-compatible via base URL + Anthropic nativo); `henaxai_validate()` non distruttivo (solo 2xx=autenticato, mai falsi positivi) + UI `admin/setup.php`.
- **Conseguenze**: il cliente sceglie provider+key, il consumer usa `henaxai_chat()` senza sapere chi c'è sotto.

## DD-005 — `henax-ai` dipendenza hard dei consumer (cutover)
- **Data**: 2026-06-22
- **Stato**: attiva
- **Contesto**: i refactor erano additivi con fallback `file_exists`; il fallback `architect_ai_call→SkyllamLlm` è accoppiamento **L1→L1** (vietato da INTEROP, segnalato da Deptrac).
- **Decisione**: `modHenaxAi` dichiarato `$this->depends` in `skyllam`/`henax-architect`; rimosso il fallback legacy (cutover). henax-ai diventa requisito.
- **Conseguenze**: i consumer non si abilitano senza henax-ai; layering pulito (Deptrac: arco rimosso). PR henax-architect #6 / skyllam #1.

---

## BL-001 — Nomi tabella col trattino in `henax-architect` (da sanare)
- **Data**: 2026-06-22 · **Stato**: aperto
- **Contesto/Debito**: `llx_henax-architect_ai_cache`/`_log` hanno il **trattino** nel nome tabella → senza backtick MariaDB li interpreta come sottrazione (errore SQL, dimostrato nell'istanza dev). Viola NAMING §4.
- **Azione**: nella migrazione a `henax-ai` rinominare in `llx_henaxai_*` (senza trattino) + script di migrazione dati. Tracciato in REGISTRY/NAMING.
