# Design — `henax-docflow` (L1)

Capability orizzontale: **ingest documenti → estrazione AI → classificazione → output (DB + documento strutturato)**. Modello **core L1 + adapter L2**. Gira su `henax-ai` per ogni chiamata LLM.

## Perché orizzontale
Lo stesso flusso è oggi implementato **due volte**, ai due estremi:

| | Domicare (omogeneo) | Industria40 / perizie (eterogeneo) |
|---|---|---|
| Ingest | `ocr/upload.php` → coda DB `llx_domicare_ocr_queue` | `file_manager_upload_handler.php` → filesystem `{socid}/{perizia}/` |
| Prep | `document_prep/` ricco (compress/split/edit/scatta PDF) | solo thumbnail |
| Estrazione | PDF→img (`pdftoppm`/PyMuPDF) → OpenAI gpt-4o **Vision** (`runOCR()`) | immagine → OpenAI gpt-4o Vision (`async_ai_processor.php`); PDF=solo thumbnail |
| Schema | **fisso** (verbale UVM/PAI) | **scelto dall'AI** (fattura/preventivo/scheda/targhetta/…) |
| Classify | assente (mono-tipo) | esplicita: `detected_type` |
| Output-DB | `consolidate()` → entità Dolibarr (societe, PAI, ente/mmg) | **nessun DB**: risposte come file `.txt` in `ai_responses/` |
| Output-doc | **FSE CDA XML + PDF/A firmato** via microservizio `henax-fse` (`llx_domicare_fse_documents`) | **DOCX** via PhpWord `TemplateProcessor` su `perizia_template.docx` |

(Una terza variante, l'app standalone `ind40`, riporta lo stato in colonne DB `analisi_tecniche`/`documenti`: conferma che lo store-su-file di industria40-PHP è un anti-pattern da non generalizzare.)

## Stadi standard (lifecycle esplicito, su DB)

```
INGEST → PREP → EXTRACT → CLASSIFY(opz.) → OUTPUT_DB → OUTPUT_DOC
```

Ogni job ha uno stato persistito (no file piatti). Tabelle del **core** (uniche di docflow):
- `llx_henaxdocflow_job` — `rowid, entity, fk_profile, source_path, status, extracted_json (longtext), detected_type, error_message, fk_soc, fk_object, datec, tms`. Stati: `0 queued · 1 prep · 2 extracting · 3 extracted · 4 classified · 5 output_db_done · 6 doc_built · 7 error · 8 archived`.
- `llx_henaxdocflow_artifact` — `rowid, fk_job, renderer, path, mime, sha256, sign_status, datec`.

> **Confine dati (INTEROP §3+§4):** docflow possiede SOLO lo stato di pipeline. I dati di dominio estratti li scrive il **verticale proprietario** via la sua API/hook (domicare consolida in `societe`/PAI; industria40 nelle sue tabelle), MAI docflow direttamente. Così la pipeline resta riusabile e non conosce il dominio.

## Profilo documento (dichiarativo)
Un **profilo** descrive cosa estrarre e come. Vive nel verticale (config/adapter L2), non nel core:
```yaml
profile: domicare-verbale-uvm
extract:
  mode: fixed_schema          # fixed_schema | ai_classify
  fields: [paziente, mmg, frequenze, forniture, obiettivi, clinical_alerts]
  prompt_ref: domicare/prompts/uvm.txt
output_db:
  handler_hook: domicare::consolidateOcrJob   # hook del verticale
output_doc:
  renderer: fse-cda
```
```yaml
profile: industria40-perizia
extract:
  mode: ai_classify           # l'AI sceglie il tipo
  types: [fattura, preventivo, scheda, schermata, targhetta, foto]
output_doc:
  renderer: docx-template
  template: industria40/templates/perizia_template.docx
```

- `mode: fixed_schema` → CLASSIFY saltato (domicare).
- `mode: ai_classify` → CLASSIFY attivo, popola `detected_type` (industria40).

## EXTRACT — sempre via `henax-ai`
Mai un client LLM proprio. Il core chiama `henaxai_chat($messages, $opts)` con immagine in base64 (Vision) e `response_format` JSON. Il prompt arriva dal profilo. Questo elimina le due chiamate OpenAI dirette duplicate (Domicare `runOCR()` e Industria40 `async_ai_processor.php`).

## OUTPUT_DOC — interfaccia renderer (adapter intercambiabili)
Unica firma, backend a plugin:
```php
interface DocflowRenderer {
    // $extracted = JSON estratto; $profile = config; ritorna artifact (path+meta)
    public function build(array $extracted, array $profile): array;
}
```
Renderer previsti:
| renderer | backend | affidabilità | dove |
|---|---|---|---|
| `fse-cda` | microservizio `henax-fse` (CDA XML + PDF/A firmato SOGEI) | workflow validato (`draft→validating→signed→published`) | adapter che chiama L1 `henax-fse` |
| `docx-template` | PhpOffice/PhpWord `TemplateProcessor` su `.docx` | best-effort in-process | core (riusabile) |
| `pdf` | (futuro) | — | core |

I renderer molto specifici del cliente (mapping campo→fonte della perizia, layout) sono **adapter L2**; l'interfaccia e i renderer generici (`docx-template`) stanno nel core L1.

## PREP — normalizzazione ingest
Generalizzare il `document_prep/` di domicare (compress/split/edit PDF, scatto da camera) come step PREP riusabile del core. Industria40 oggi ne è privo (solo thumbnail) → ne beneficia gratis.

## Hook/trigger esposti (provides[] nel manifest)
- `henaxdocflow::onJobExtracted` — il verticale aggancia qui il consolidamento DB.
- `henaxdocflow::registerRenderer` — un adapter L2 registra un renderer custom.
- `henaxdocflow::registerProfile` — un verticale registra un profilo documento.

## Pilota (da decidere — vedi domanda aperta)
- **Domicare** (omogeneo): valida ingest→extract→classify(off)→output-DB. Più veloce, non stressa OUTPUT_DOC complesso.
- **Industria40** (eterogeneo): valida anche CLASSIFY + OUTPUT_DOC strutturato (perizia DOCX). De-risca di più, più lento.
Prerequisito comune: `henax-ai` consolidato (EXTRACT ci gira sopra).

## Rischi
- **Vision-coupling**: domicare-woundcare condivide la vision lesioni con docflow; tenere la vision come capability di docflow e woundcare come consumer.
- **FSE è un microservizio esterno**: il renderer `fse-cda` è async (poll stato firma) — il lifecycle artifact deve modellare `validating/signed/sent/published/failed`.
- **Migrazione store industria40**: passare da `.txt` su disco a `llx_henaxdocflow_job.extracted_json` richiede uno script di import dei `ai_responses/*.txt` esistenti.
