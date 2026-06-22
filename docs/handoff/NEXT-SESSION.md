# NEXT-SESSION — handoff

> Aggiornare a fine sessione. Serve a riprendere senza ricostruire il contesto.

## Stato attuale
- **`henax-ai` (L0) costruito e su `main`** (mergiato via PR #1, ora chiusa): client multi-provider (14 provider + Anthropic-nativo), stadio validazione API key (`henaxai_probe`) + UI `admin/setup.php`, manifest engine portato da architect (loader/validator/builder/discovery + bin), service cache/log/rate-limit, descrittore `modHenaxAi` 580420. Tutto verificato live nell'istanza dev `doli-dev`. Vedi `docs/tech/henax-ai.md`.
- **Refactor consumer** (PR aperte): `henax-architect` PR #6 e `skyllam` PR #1 (p2gconnecto), branch `refactor/consume-henax-ai` in entrambi. Instradano le chiamate LLM su `henaxai_chat()`, dichiarano `depends modHenaxAi`.
- **Cutover #5 (in corso)**: rimosso il fallback legacy `architect_ai_call→SkyllamLlm` (arco L1→L1) — commit `dfe0e07` sul branch refactor di henax-architect, **non ancora pushato** (in attesa OK). Deptrac conferma arco rimosso, 0 violazioni.
- **Tooling** documentato in `docs/tech/TOOLING.md` + `tooling/`: istanza `doli-dev` (web :9199, db :3399), PHPStan, Deptrac, Playwright/E2E (2 test verdi).

## Prossima azione
- **Push PR #6** (cutover henax-architect) su OK utente.
- Verificare se `skyllam` ha un arco legacy analogo da rimuovere (gemello del cutover).
- Poi piano utente (ordine 1→3→2→4→5): #1 chiuso, #4 fatto → **#3 razionalizzazione registry** (consiglio: conflitto ID 500000 domicare↔lrid, ma cambiare `$this->numero` su modulo di produzione è delicato — investigare read-only prima) e **#2 henax-docflow** (serve scegliere il pilota: domicare omogeneo vs industria40 eterogeneo).

## Aperti / attenzione
- **BL-001**: nomi tabella col trattino `llx_henax-architect_*` (errore SQL reale) — sanare in `llx_henaxai_*` nella migrazione.
- **2 lezioni universali da proporre alla playbook** (NON sepolte qui): (a) `GETPOST('az')` non è un filtro Dolibarr valido (gotcha framework, validi `aZ`/`aZ09`/`alphanohtml`); (b) mai workaround che indeboliscono la sicurezza per comodità di test. Candidate ad anti-pattern playbook (`capture-antipattern`).
- **Dolibarr pinnato 22.0.3**: pin effettivo al prossimo `docker compose up -d` (henaxis.org).
- Regole playbook attive: commit/push solo su OK, no `--force`, sezione `ANTI-DRIFT CHECK` nei commit, AP-040 (PROD≠main).

## Comandi utili
- Istanza dev: `cd .tooling/throwaway && docker compose -p doli-dev up -d` · reset `down -v` · web http://localhost:9199 (admin/admindev).
- PHPStan: `cd .tooling && ./vendor/bin/phpstan analyse -c phpstan-henaxai.neon --no-progress`
- Deptrac: `cd .tooling && ./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress`
- E2E: `cd .tooling/e2e && npx playwright test`
- Validazione provider (smoke): bin temporaneo che chiama `henaxai_validate_candidates()` nel container dev.
