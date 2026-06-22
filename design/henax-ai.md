# Design — `henax-ai` (L0)

Libreria di piattaforma: **un client LLM multi-provider + un manifest engine + discovery**. Consolida `skyllam` e `henax-architect`, oggi duplicati e accoppiati in modo nominale.

## Stato attuale (verificato sul codice)

| Pezzo | Dove vive oggi | Note critiche |
|---|---|---|
| Client LLM | `skyllam/class/skyllam_llm.class.php` (`SkyllamLlm`) | **Solo OpenAI-protocol**: rami `openai` / openai-compatible (Groq/Mistral via `SKYLLAM_ENDPOINT_URL`) / `ollama` / `anythingllm`. **Nessun ramo Anthropic-nativo.** |
| Service AI (cache/log/rate-limit) | `henax-architect/lib/architect_ai.lib.php` | Motore maturo. `architect_ai_call_via_skyllam()` è **pass-through inerte** verso curl: la "convergenza" è solo nominale. |
| Manifest engine | `henax-architect/lib/architect_manifest*.lib.php` + `bin/build_manifests.php` + `bin/validate_manifests.php` | Pipeline `manifest.yaml → README + architect.json + skyllam.json`. Builder + validator + autodiscovery. È il più maturo. |
| Manifest consumer | `skyllam/class/skyllam_manifest.class.php` (`SkyllamManifest`) | Legge il block `skyllam` per entità/stat/route/prompt. **Consumer, non engine** → resta tale (consuma henax-ai). |
| Discovery/grafo | `henax-architect/lib/architect_discovery.lib.php` | Costruisce il grafo `{nodes,edges}` dell'architettura da descriptor+manifest+DB. |
| Agent loop (tool-calling) | `skyllam/ajax/chat.php` (non in classe) + `skyllam/class/skyllam_agent.class.php` | Il loop LLM↔tool↔conferma write vive in `chat.php`. `SkyllamAgent` = definizioni tool OpenAI + dispatcher. |
| RAG | `skyllam/class/skyllam_rag.class.php` | Indice **keyword BM25-like, NO embedding/vector**. |

### Conflitti di config da unificare
Due set di global con default incoerenti:
- `SKYLLAM_LLM_PROVIDER='openai'`, `SKYLLAM_API_KEY`, `SKYLLAM_ENDPOINT_URL`, `SKYLLAM_MODEL='gpt-4o-mini'`, `SKYLLAM_AUTH_TYPE='bearer'`.
- `HENAXARCHITECT_AI_PROVIDER='openai'`, `HENAXARCHITECT_AI_MODEL='gpt-4o-mini'`, `HENAXARCHITECT_AI_API_KEY`, `HENAXARCHITECT_AI_CACHE_TTL_MIN=30`, `HENAXARCHITECT_AI_RATE_LIMIT=20`.
- Key resolution centralizzata in `domicare_resolve_openai_key()` (soft-dep da disaccoppiare).

## Target — struttura `henax-ai`

```
henax-ai/                                    (ID modulo 580420, namespace henax)
  core/modules/modHenaxAi.class.php          descrittore (no domain tables, solo cache/log)
  lib/henaxai_client.lib.php                 CLIENT multi-provider — UN solo entry point
  lib/henaxai_service.lib.php                cache + log + rate-limit  (da architect_ai)
  lib/henaxai_manifest.lib.php               schema + loader + validator  (da architect_manifest)
  lib/henaxai_manifest_builder.lib.php       manifest.yaml → README/architect.json/skyllam.json
  lib/henaxai_discovery.lib.php              grafo architettura  (da architect_discovery)
  bin/build_manifests.php  bin/validate_manifests.php
  sql/llx_henaxai_cache.sql  sql/llx_henaxai_log.sql
  manifest.yaml  +  generati (README.md, architect.json, skyllam.json)
```

### 1. Client — `henaxai_client.lib.php`
Un solo entry point, provider-agnostic. Estende `skyllam_llm` con il **path Anthropic-nativo nuovo**.

```php
henaxai_chat(array $messages, array $opts = []): array|false
//  $opts: provider, model, tools[], tool_choice, temperature, max_tokens, system
//  ritorno: ['content','finish_reason','tool_calls','tokens_input','tokens_output']
```

Provider supportati e differenze di protocollo:

| provider | endpoint | auth | schema richiesta/risposta |
|---|---|---|---|
| `openai` / openai-compatible (groq/mistral/custom) | `…/v1/chat/completions` | `Authorization: Bearer` | chat-completions: `choices[].message`, tool_calls OpenAI |
| `ollama` | `…/v1/chat/completions` | nessuna | come openai |
| `anythingllm` | `…/api/v1/workspace/{ws}/chat` | Bearer | `textResponse`, no tool-calling |
| **`anthropic` (NUOVO)** | `https://api.anthropic.com/v1/messages` | header `x-api-key` + `anthropic-version: 2023-06-01` | **content blocks** + tool-use con schema diverso; `thinking:{type:"adaptive"}`, **niente `temperature`/`budget_tokens`** sui modelli opus-4.x; default **`claude-opus-4-8`** (o `claude-sonnet-4-6` per volumi/batch). Vedi skill `claude-api`. |

> Il path Anthropic è **codice nuovo**: traduce `$messages` (formato interno stile OpenAI) ↔ Messages API (system separato, `content[]`, `tool_use`/`tool_result` blocks). Valutare l'SDK PHP ufficiale (`composer require anthropic-ai/sdk`) vs curl diretto (coerente con l'attuale `_via_curl`, zero dipendenze). Raccomandazione: **curl diretto** in v1 per non introdurre composer dependency in un modulo Dolibarr; SDK opzionale dietro feature-flag.

Config unificata (un solo set `HENAXAI_*`, con shim di lettura dei vecchi global per migrazione):
```
HENAXAI_PROVIDER  HENAXAI_MODEL  HENAXAI_API_KEY  HENAXAI_ENDPOINT_URL  HENAXAI_AUTH_TYPE
HENAXAI_CACHE_TTL_MIN(=30)  HENAXAI_RATE_LIMIT(=20)
```
Mappa di migrazione (shim, da rimuovere dopo cutover): `HENAXAI_* ← SKYLLAM_* ← HENAXARCHITECT_AI_*` (prima il nuovo, poi i legacy come fallback). Key resolution: rimpiazzare la dipendenza hard a `domicare_resolve_openai_key()` con un resolver interno che *opzionalmente* consulta domicare se presente (soft-dep via `function_exists`).

### 2. Service — `henaxai_service.lib.php` (da `architect_ai.lib.php`)
- `henaxai_cache_key($q,$scope,$provider,$model)` = `sha256(q|scope|provider|model)`.
- `henaxai_get_cached_response($db,$key)` / `henaxai_save_cache(...)` su `llx_henaxai_cache` (PK cache_key, response, created_at, last_hit_at, hit_count, provider, model, tokens). TTL `HENAXAI_CACHE_TTL_MIN`.
- `henaxai_log($db,$params)` su `llx_henaxai_log` (fk_user, datec, question, provider, model, tokens, latency_ms, cache_hit, status, error_msg).
- `henaxai_check_rate_limit($db,$fk_user)` — conta log ultima ora con `cache_hit=0`; `HENAXAI_RATE_LIMIT`.
- `henaxai_call($db,$question,$opts)` = orchestratore (validate → rate-limit → cache → build context → `henaxai_chat` → save → log). Generalizzato: il "context builder" (oggi il grafo compresso) diventa un callback iniettabile, così serve sia ad architect (grafo) sia a docflow (documento).

### 3. Manifest engine — `henaxai_manifest*.lib.php` (da architect)
Promuove tale e quale il motore di architect (è quello maturo):
- Schema `manifest.yaml` (meta/business/dependencies/skyllam/architect/doc_links) + `architect.json` dual-schema + block `skyllam`.
- `henaxai_load_manifest($path)`, `henaxai_validate_manifest($m,$db)`, `henaxai_manifest_build_all/diff/apply($path)`, autodiscovery (tabelle da `sql/`, rights da descriptor, cron, endpoints, repo).
- `henaxai_extract_skyllam_block($path)` — usato dai consumer (es. `SkyllamManifest`).
- CLI `bin/build_manifests.php` / `bin/validate_manifests.php` con bootstrap Dolibarr parametrico (non hardcodare `/var/www/html`).

> **Questa è la peculiarità centrale per l'interoperabilità**: il manifest è la source-of-truth per documentazione (README), struttura (architect.json) e superficie AI-queryable (skyllam block). Un modulo conforme si auto-documenta e si rende interrogabile senza toccare il motore.

### 4. Discovery — `henaxai_discovery.lib.php`
`henaxai_build_full_graph($db,$opts)` invariato; disaccoppiare i soft-coupling: `hub_compute_kpi_cached` (domicare) → fallback cache file già presente; `henaxinnerhelp_default_repo_map` → opzionale.

## Cosa NON entra in L0 (restano L1 che consumano henax-ai)
- UI grafo di architect: `admin/graph.php`, `public/js/architect_graph.js`, `ajax/*` → modulo L1 `henax-architect` (solo presentazione).
- Agent loop + tool di scrittura ORM + RAG + persistenza messaggi: restano in `skyllam` (che diventa "chat assistant" L1, consumando `henax-ai` per il client e `henax-chat` per lo storage messaggi — vedi REGISTRY sovrapposizione #2).

## Tabelle
Solo tecniche (conforme INTEROP §3): `llx_henaxai_cache`, `llx_henaxai_log`. **Nomi senza trattino** (sanare l'attuale `llx_henax-architect_*`). Nessuna tabella di dominio.

## Ordine di migrazione
1. Scaffold modulo + descrittore + sql + manifest engine (copia da architect, rename simboli `architect_*`→`henaxai_*`, rename tabelle).
2. Client: porta `skyllam_llm` come ramo openai/ollama/anythingllm; aggiungi ramo `anthropic` nuovo.
3. Service: porta `architect_ai` cache/log/ratelimit su `henaxai_*` + tabelle nuove.
4. Shim config `HENAXAI_* ← legacy`.
5. Refactor `henax-architect` UI per consumare henax-ai (rimuove le sue lib AI/manifest).
6. Refactor `skyllam` per usare `henaxai_chat()` al posto di `SkyllamLlm` interno.
7. Rimozione shim + dismissione tabelle `llx_henax-architect_*` dopo cutover.

## Punti di rottura / rischi
- **Anthropic tool-use**: lo schema tool/tool_result differisce da OpenAI; il loop in `chat.php` assume formato OpenAI. Il client deve normalizzare i `tool_calls` a un formato interno unico.
- **Key resolution**: oggi 3 moduli leggono key da posti diversi; il resolver unico deve preservare le precedenze esistenti per non rompere installazioni live.
- **Nomi tabella col trattino**: la migrazione dati da `llx_henax-architect_ai_cache/_log` a `llx_henaxai_*` va fatta con uno script (le tabelle vecchie hanno il trattino, attenzione al quoting backtick).
- **Bootstrap CLI hardcoded** (`/var/www/html/master.inc.php`): parametrizzare.
