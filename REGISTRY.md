# REGISTRY — fonte unica di verità dei moduli

Mappa **stato-attuale → target** della razionalizzazione. Stati: `attivo` (in produzione), `da-fondere` (assorbire in un altro), `da-estrarre` (nasce da spezzare un monolite), `legacy` (archiviare), `vendor` (terzi), `da-decidere`.

Range ID per namespace (no collisioni). Il blocco henax è ampio perché i moduli orizzontali esistenti già lo occupano fino a 5808xx:
- **henax-*** 580400–580899 (L0+L1). Sotto-blocchi: piattaforma/AI 580400–580449 · trasporto/export 580450–580499 · verticali-helper 580600–580799 · architect/grafo 580800–580899.
- **domicare-*** 580500–580599 (L2).
- **altri verticali clienti** (henaxis-*, sad, hrm) negli spazi 5806xx/5807xx già assegnati.
- vendor: lasciare l'ID upstream.

> ⚠️ Regola tabelle (vedi NAMING §4): prefisso tabella = nome modulo SENZA trattini → `henax-ai` ⇒ `llx_henaxai_*`. Il `henax-architect` attuale viola la regola (`llx_henax-architect_*`, trattino nel nome tabella) e va sanato nella migrazione.

## Base platform (core)
| componente | repo | note |
|---|---|---|
| **henaxis-base** | [GYBeccaria/henaxis-base](https://github.com/GYBeccaria/henaxis-base) | Dolibarr ufficiale **pinnato `22.0.3`** + branding + override core + `web-init.sh`. NON c'è fork del core: si stratifica sopra l'immagine ufficiale. I moduli (sotto) si montano su questa base. |

## L0 — librerie piattaforma (target da consolidamento)

| target | ID | nasce da | stato | note |
|---|---|---|---|---|
| `henax-ai` (lib) | 580420 | **client**: `skyllam_llm` (openai-compatible) **+ path Anthropic-nativo NUOVO** · **service**: `architect_ai` (cache/log/rate-limit) · **manifest engine**: `architect_manifest*` (builder/validator/discovery) | da-fondere | UN client LLM multi-provider + UN manifest engine. Vedi `docs/tech/henax-ai.md`. La convergenza odierna è nominale (`architect_ai_call_via_skyllam` è pass-through); il consolidamento è reale. `skyllam_manifest` diventa consumer. Tabelle tecniche `llx_henaxai_cache`/`_log`. |
| `henax-docflow` (engine) | 580430 | core estratto da `domicare/ocr`+`visionai` e `industria40/async_ai_processor`+`export_to_docx` | da-estrarre→L1 | **capability orizzontale** ingest→OCR/estrazione→classify→output(DB+documento). Core L1, renderer/adapter L2 (FSE-CDA, DOCX-template). Gira su `henax-ai`. Vedi `docs/tech/henax-docflow.md`. |
| `henax-chat` (trasporto) | 580410 | `matrixchat` | attivo→promuovere | trasporto Matrix unico (room/user/message). |
| `henax-export` (engine) | 580450 | `henaxexport` | attivo→promuovere | motore export CSV/JSON riusabile. |

## L1 — moduli orizzontali `henax-*`

| target | repo attuale | ID oggi | stato | azione |
|---|---|---|---|---|
| `henax-fse` | `henaxfse` (modulo) + `henax-fse` (microservizio) + `it-fse-accreditamento` (fork) | 580750 | attivo | rinomina modulo→`henax-fse`; tenere distinti modulo/microservizio/fork; verificare no-duplicazione logica CDA2/PADES/SOGEI |
| `henax-analytics` | `henaxisanalytics` | 580600 | attivo | rinomina (no `henaxis`-attaccato) |
| `henax-help` | `henax-inner-help` | 580720 | attivo | dipende da `henax-chat` |
| `henax-admin` | `henax-admin` | 104858 | attivo | ID fuori range → riassegnare in 5804xx |
| `henax-export` | `henaxexport` | 580730 | attivo | vedi L0 |
| `henax-chat` | `matrixchat` | 580410 | attivo | vedi L0 |
| `henax-architect` | `henax-architect` | 580800 | da-fondere | motore AI + manifest engine + discovery confluiscono in `henax-ai` (L0); **resta solo la UI grafo** (`admin/graph.php`, `public/js/architect_graph.js`, `ajax/`) come modulo L1 che consuma `henax-ai`. Sanare nomi tabella col trattino. |
| `henax-geo` | `addresscorrector` | 550000 | attivo | rinomina; ID→range henax |
| `henax-ops` | da `domicare` (`henaxops_*`) + affine a `domicare-infra` | — | da-estrarre | alert/job_run + backup/cron |
| `henax-help-chat`? | `helpchat` | 119500 | da-decidere | NOSTRO — definire se è duplicato di henax-help/henax-chat o modulo a sé |
| `henax-diagnosi` | `diagnosi_digitale` | 500001 | da-decidere | NOSTRO — classificare |
| `henax-industria40` | `industria40` | 100000 | da-decidere | NOSTRO — scaffold ("Your Name/Company"); ID→range |
| `henax-b2b` | `b2border` | 580300 | da-decidere | NOSTRO (Henaxis) |
| `henax-b2c` | `b2cstore` | 580400 | da-decidere | NOSTRO (Henaxis); ID collide col range, riassegnare |

## L2 — verticale Domicare (da spezzare il monolite `domicare`, ID 500000, 32 tabelle)

| target | contenuto estratto da `domicare` | stato |
|---|---|---|
| `domicare-core` | anagrafiche/masterdata, accesso+agenda, PAI/clinico | da-estrarre (resta il nucleo) |
| `domicare-rendicontazione` | `rendiconto_periodi/questionario/snapshots` + dir rendicontazione | da-estrarre (taglio netto, basso rischio) |
| `domicare-compliance` | `gdpr` + audittrail/breachdetection/retentionpolicy | da-estrarre (basso rischio) |
| → `henax-docflow` | `ocr_queue` (lifecycle estrazione) + `domicarevisionai` → la **logica AI di documento è orizzontale**: va al core L1 `henax-docflow`. Resta in domicare solo il **profilo "verbale UVM/PAI"** (schema campi fissi) come config/adapter. | da-estrarre verso L1 |
| `domicare-woundcare` | `lesione`/`lesione_coord_z`/`misurazioni` + calibration | da-estrarre (L2; consuma `henax-docflow` per la vision lesioni) |
| `domicare-hcp` | `Domicare-HCPPortal` (tieni) + `hcp_consent`/`hcp_login_log`/py-portal da domicare | attivo; **`domihcp-be`, `domihcp-fe` → legacy/archivia** |
| → `henax-fse` | `fse_documents` + classe fse_document (oggi dentro domicare) | da-estrarre verso L1 |
| → `henax-chat` | classe `domicare_matrixchat` + `_legacy_matrix_backup` | da-estrarre verso L0 |

## Altri verticali

| modulo | repo | stato |
|---|---|---|
| `henaxis-mba` | `henaxis-frontend`, `henaxis_mbamutua`, `henaxis-mutua-docs` | attivo (cliente Mutua MBA) |
| `sad` | `sad` (ID 580760) | attivo (Comune Reggio Calabria) — autonomo |
| `hrmformazione` | `hrmformazione` (ID 580700, dep modHRM) | attivo — autonomo |

## vendor/ (terzi, spostati)

`arubasdi` (Linx srl, SDI) · `efattita` (Linx srl) · `tawkto` · `dolibarrassistant`

## ⚠️ Conflitti ID rilevati
- **500000** usato sia da `domicare` sia da `lrid` → collisione: riassegnare uno (lrid è L1/`henax-*` o verticale LRID? → da-decidere).
- `henax-admin` 104858, `industria40` 100000, `addresscorrector` 550000: fuori dai range namespace → riassegnare.
- `b2cstore` 580400 entra nel range henax ma coincide col blocco: riassegnare.

## Legenda azioni immediate consigliate (ordine)
1. **Consolidare `henax-ai` (L0)** da architect_ai (service) + skyllam_llm (client) + path Anthropic-nativo nuovo + manifest engine di architect. È il perno comune a tutto: AI, interoperabilità (manifest), e la pipeline documentale ci girano sopra. Vedi `docs/tech/henax-ai.md`.
2. **Estrarre `henax-docflow` (L1)** validando il pattern core+adapter su un pilota (domicare omogeneo vs industria40 eterogeneo — da decidere). Vedi `docs/tech/henax-docflow.md`.
3. Estrarre `domicare-rendicontazione`, `domicare-compliance`, `henax-ops` (basso accoppiamento).
4. Archiviare `domihcp-be`, `domihcp-fe`, `Dolibarr-toolkit`.
5. Rinomini di naming (henaxfse→henax-fse, henaxisanalytics→henax-analytics, addresscorrector→henax-geo) e sanazione nomi tabella col trattino.
6. Riassegnare gli ID in conflitto (500000 domicare↔lrid per primo).
