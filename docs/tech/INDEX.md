# Note tecniche — indice

Runbook, design e gotcha specifici di questo verticale (registro moduli henax/domicare).

- `henax-ai.md` — design della libreria L0 `henax-ai`: client LLM multi-provider (+ Anthropic-nativo), stadio validazione API key, manifest engine, service. Superficie API, mappa config legacy→`HENAXAI_*`, ordine di migrazione.
- `henax-docflow.md` — design della capability orizzontale L1 `henax-docflow` (pipeline documentale ingest→estrazione→classify→output): stadi, profilo documento, interfaccia renderer (FSE-CDA / DOCX-template), confini dati.
- `TOOLING.md` — knowledge base degli strumenti di sviluppo/verifica (portabile): istanza Dolibarr usa-e-getta, accesso DB read-only, PHPStan, Deptrac, Playwright/E2E. Ricette riproducibili + lezioni di campo.
