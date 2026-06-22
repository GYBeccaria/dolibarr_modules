# henax-ai (L0) — scaffold

Libreria di piattaforma Henaxis. **UN client LLM multi-provider + UN manifest engine + discovery.**
Consolida `skyllam` (client) e `henax-architect` (service AI / manifest / discovery).

> ⚠️ **Scaffold, non production.** Niente Dolibarr runtime in questa VM: il codice è strutturato e
> verificato per coerenza, non eseguito. Vedi `design/henax-ai.md` per design completo e ordine di migrazione.

## Stato dei pezzi
| File | Stato |
|---|---|
| `core/modules/modHenaxAi.class.php` | descrittore (ID 580420, const HENAXAI_*, tabelle cache/log) — completo |
| `lib/henaxai_client.lib.php` | client multi-provider, **incluso ramo Anthropic-nativo nuovo** — funzionale, base |
| `lib/henaxai_service.lib.php` | cache + log + rate-limit + orchestratore — portato da architect_ai — funzionale |
| `lib/henaxai_manifest.lib.php` | loader + validator + `extract_skyllam_block` (superficie AI-queryable) — **portato da architect, smoke-test ok su architect.json reale** |
| `lib/vendor/{toml_parser,Spyc}.php` | vendor del manifest engine (toml entry rinominata) |
| `lib/henaxai_manifest_builder.lib.php` | builder `manifest.yaml`→README/architect.json/skyllam.json + autodiscovery — **portato, smoke-test ok** (genera i 3 file dal manifest di henax-ai) |
| `lib/henaxai_discovery.lib.php` | grafo architettura `{nodes,edges}` — **portato** (owner-map estesa a henax-ai/henax-docflow) |
| `bin/build_manifests.php` `bin/validate_manifests.php` | CLI con **bootstrap parametrico** (env `DOL_DOCUMENT_ROOT` o autodetect) — portati, lint ok |
| `sql/llx_henaxai_cache.sql` `sql/llx_henaxai_log.sql` | tabelle tecniche, nomi sanati (no trattino) |
| `manifest.yaml` | source-of-truth (genera README/architect.json/skyllam.json) |

## API principale
```php
require_once DOL_DOCUMENT_ROOT.'/custom/henax-ai/lib/henaxai_service.lib.php';

// chiamata diretta al client
$r = henaxai_chat([['role'=>'user','content'=>'...']], ['provider'=>'anthropic','model'=>'claude-opus-4-8']);

// chiamata con cache/log/rate-limit + context builder
$r = henaxai_call($db, $question, ['fk_user'=>$user->id, 'scope'=>'docflow'], function() { return $contextoDocumento; });
```

## Provider supportati (`henaxai_provider_registry()`)
14 provider, fonte unica condivisa client+probe. Quasi tutti OpenAI-compatible (cambia la base URL):
`openai`, `gemini`, `perplexity`, `qwen` (DashScope), `glm` (z.ai/Zhipu), `mistral`, `groq`,
`deepseek`, `xai`, `openrouter`, `openai-compatible` (generico). `anthropic` = nativo. `ollama`/`anythingllm` = famiglie proprie.
Config per-provider: `HENAXAI_KEY_<P>` / `HENAXAI_BASE_<P>` / `HENAXAI_MODEL_<P>` (base URL = default sovrascrivibile).

## Stadio di validazione provider+API key (`henaxai_probe.lib.php` + UI)
Il sistema non sa quale LLM usa il cliente → `henaxai_validate($opts)` testa una coppia
provider+key in modo non distruttivo (endpoint list-models/auth): distingue
**autenticato / key rifiutata (401) / errore di rete / config da verificare**, ritorna i modelli esposti e la latenza.
`henaxai_validate_candidates([...])` valida N candidati (stage di selezione).
**UI**: `admin/setup.php` (link "configura" del modulo) — l'operatore inserisce le key, preme **Valida**
e vede la matrice provider→{autentica?, modelli, latenza}, poi seleziona il provider attivo.
Verificato live (14 provider; openrouter ha tornato 340 modelli con `/models` pubblico; UI resa nell'istanza dev).

## TODO migrazione (sintesi — dettaglio in design/henax-ai.md)
1. ~~Completare il mapping tool_use/tool_result per Anthropic~~ — **fatto** (assistant.tool_calls→tool_use, role tool→tool_result; verificato).
2. ~~Portare manifest engine + discovery da henax-architect~~ — **fatto** (manifest/builder/discovery + 2 bin CLI, smoke-test ok).
3. Shim config `HENAXAI_* <- SKYLLAM_* <- HENAXARCHITECT_AI_*` (già nel client) + script migrazione dati cache/log.
4. Refactor consumer per usare `henaxai_chat()` — **avviato (additivo, con guard, reversibile)**, verificato nell'istanza dev:
   - `henax-architect` `architect_ai_call` → henax-ai (branch `refactor/consume-henax-ai`).
   - `skyllam` `SkyllamLlm::chat()` → henax-ai, tool_calls ri-mappati a formato OpenAI (branch `refactor/consume-henax-ai`).
   - Resta: rimuovere il codice AI legacy duplicato dopo cutover; agganciare i consumer manifest (architect UI, `skyllam_manifest`).
