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
| `lib/henaxai_manifest_builder.lib.php` | builder `manifest.yaml`→README/json — da portare (architect_manifest_builder, 538 righe) — TODO |
| `lib/henaxai_discovery.lib.php` | grafo architettura — da portare (architect_discovery, 765 righe) — TODO |
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

## TODO migrazione (sintesi — dettaglio in design/henax-ai.md)
1. Completare il mapping tool_use/tool_result per Anthropic (oggi base).
2. Portare manifest engine + discovery da henax-architect (rename simboli + tabelle).
3. Shim config `HENAXAI_* <- SKYLLAM_* <- HENAXARCHITECT_AI_*` (già nel client) + script migrazione dati cache/log.
4. Refactor `henax-architect` (UI grafo) e `skyllam` (chat) per consumare henax-ai.
