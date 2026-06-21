# REGISTRY — fonte unica di verità dei moduli

Mappa **stato-attuale → target** della razionalizzazione. Stati: `attivo` (in produzione), `da-fondere` (assorbire in un altro), `da-estrarre` (nasce da spezzare un monolite), `legacy` (archiviare), `vendor` (terzi), `da-decidere`.

Range ID per namespace (no collisioni): **henax** 580400–580499 · **domicare** 580500–580599 · **verticali clienti** 580600+ · vendor: lasciare l'ID upstream.

## L0 — librerie piattaforma (target da consolidamento)

| target | nasce da | stato | note |
|---|---|---|---|
| `henax-ai` (lib) | `skyllam` (skyllam_llm/rag/manifest) + `henax-architect` (architect_ai/manifest) | da-fondere | UN client LLM + UN manifest engine. Risolve la doppia implementazione AI. |
| `henax-chat` (trasporto) | `matrixchat` | attivo→promuovere | trasporto Matrix unico (room/user/message). |
| `henax-export` (engine) | `henaxexport` | attivo→promuovere | motore export CSV/JSON riusabile. |

## L1 — moduli orizzontali `henax-*`

| target | repo attuale | ID oggi | stato | azione |
|---|---|---|---|---|
| `henax-fse` | `henaxfse` (modulo) + `henax-fse` (microservizio) + `it-fse-accreditamento` (fork) | 580750 | attivo | rinomina modulo→`henax-fse`; tenere distinti modulo/microservizio/fork; verificare no-duplicazione logica CDA2/PADES/SOGEI |
| `henax-analytics` | `henaxisanalytics` | 580600 | attivo | rinomina (no `henaxis`-attaccato) |
| `henax-help` | `henax-inner-help` | 580720 | attivo | dipende da `henax-chat` |
| `henax-admin` | `henax-admin` | 104858 | attivo | ID fuori range → riassegnare in 5804xx |
| `henax-export` | `henaxexport` | 580730 | attivo | vedi L0 |
| `henax-chat` | `matrixchat` | 580410 | attivo | vedi L0 |
| `henax-architect` | `henax-architect` | 580800 | da-fondere | il motore manifest confluisce in `henax-ai`; resta la UI grafo |
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
| `domicare-docai` | `ocr_queue` + visionai/pythonrunner/templatefiller | da-estrarre |
| `domicare-woundcare` | `lesione_coord_z` + lesione/calibration (usa docai) | da-estrarre (delicato: vision condivisa) |
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
1. Estrarre `domicare-rendicontazione`, `domicare-compliance`, `henax-ops` (basso accoppiamento).
2. Consolidare `henax-ai` da skyllam+architect.
3. Archiviare `domihcp-be`, `domihcp-fe`, `Dolibarr-toolkit`.
4. Rinomini di naming (henaxfse→henax-fse, henaxisanalytics→henax-analytics, addresscorrector→henax-geo).
5. Riassegnare gli ID in conflitto.
