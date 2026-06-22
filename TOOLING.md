# TOOLING — knowledge base degli strumenti di sviluppo

> **Principio: portabilità.** Tutto qui è una *ricetta riproducibile*, non uno stato di una
> macchina. I path assoluti di questa VM (`/opt/p2g_dev/...`) sono indicati come variabili
> dove conta; su un'altra macchina cambiano i path, non la procedura. Versioni pinnate dove
> rilevante. Niente passo "magico" non documentato.

Scopo: dare visione **e verifica** piena del codice in un workspace di soli sorgenti
(editing/analisi/refactor). La navigazione è coperta (Serena + ripgrep); la lacuna vera è
**eseguire e verificare** — questo documento copre entrambe.

---

## 0. Inventario toolchain VM (da CLAUDE.md + verificato)
`php-cli 8.5` (+ estensioni) · `composer 2.9.5` · `docker` (daemon attivo) · `ripgrep` ·
`uv` · `serena` · `gh` (identità GYBeccaria) · `node` · `tmux`.
Assenti di default (installabili via composer, vedi §4): phpstan, deptrac, psalm, php-cs-fixer.

---

## 1. Serena — navigazione simbolica (già attiva)
Strumenti symbol-aware (find_symbol, find_referencing_symbols, get_symbols_overview,
replace_symbol_body, ecc.). **Primario** per leggere/editare codice: non serve null'altro per
*navigare*. Vedi le istruzioni in testa al prompt di progetto.

---

## 2. Stack Dolibarr in esecuzione — `henaxis-mba-*` (CLIENTE VIVO)
Container attivi (docker):

| container | immagine | ruolo |
|---|---|---|
| `henaxis-mba-web` | `henaxis-mba/dolibarr:21-mba` | Dolibarr 21 (vertical Mutua MBA) |
| `henaxis-mba-mdb` | `mariadb:10.11` | DB `dolibarr` |
| `henaxis-mba-synapse` | `matrixdotorg/synapse:v1.123.0` | Matrix (henax-chat) |
| `henaxis-mba-redis` | `redis:7.4-alpine` | cache |
| `henaxis-mba-minio` | `minio/...` | object storage |
| `henaxis-mba-clamav` | `clamav/clamav:1.4` | antivirus |

> ⚠️ **È l'istanza di un cliente vivo (Mutua MBA), NON una sandbox.** Solo **SELECT**, mai
> scritture/DDL. Moduli installati qui: `henaxmba`, `henaxmbafe`, `henaxauth` — **non**
> skyllam/architect/domicare. Quindi serve a: (a) verificare lo schema core reale e i range
> ID, (b) NON a verificare i moduli che stiamo razionalizzando → per quelli vedi §6.

---

## 3. Accesso DB in sola lettura (ricetta portabile)
Le credenziali stanno nell'env del container DB; **non vanno mai stampate**. Si interroga
da dentro il container, usando `$MARIADB_PASSWORD` già presente nell'ambiente:

```bash
# Pattern read-only: la password è letta dall'env del container, mai esposta.
docker exec <db_container> sh -c 'mariadb -u dolibarr -p"$MARIADB_PASSWORD" dolibarr -N -e "
  <QUERY SELECT>
"' | grep -v "Using a password"
```

Per scoprire i parametri DB di una nuova istanza:
```bash
docker inspect <db_container> --format '{{range .Config.Env}}{{println .}}{{end}}' \
  | grep -iE "MARIADB|MYSQL"   # NB: redarre PASSWORD prima di condividere
```

Query ad alto valore per la razionalizzazione (sempre SELECT):
```sql
-- owner reale tabelle (prefisso llx_<mod>_)
SELECT SUBSTRING_INDEX(SUBSTRING(TABLE_NAME,5),'_',1) modpfx, COUNT(*) n
FROM information_schema.TABLES
WHERE TABLE_SCHEMA='dolibarr' AND TABLE_NAME LIKE 'llx_%'
GROUP BY modpfx ORDER BY n DESC;

-- moduli nostri attivi
SELECT name,value FROM llx_const WHERE name LIKE 'MAIN_MODULE_%';

-- range ID reali (conferma blocchi REGISTRY) — verificato su mba:
--   henaxauth 580601-2 · henaxmba 580701-3 · henaxmbafe 580731
SELECT module, MIN(id) idmin, MAX(id) idmax, COUNT(*) n
FROM llx_rights_def GROUP BY module ORDER BY idmin;

-- conflitti ID / FK cross-modulo: interrogare information_schema.KEY_COLUMN_USAGE
```

**Esito verificato**: read-only funziona; 424 tabelle nella mba. Conferma che gli ID
580601/580701/580731 occupano i blocchi 5806xx/5807xx (coerente con REGISTRY).

---

## 4. Analizzatori statici — PHPStan + Deptrac
Installati in una cartella tooling **fuori dai repo dei moduli** (non inquina i sorgenti):
`/opt/p2g_dev/.tooling/`. Ricetta di install (portabile):

```bash
mkdir -p <WORKSPACE>/.tooling && cd <WORKSPACE>/.tooling
# composer.json:
#   "require-dev": { "phpstan/phpstan": "^2.0", "deptrac/deptrac": "^3.0" }
composer install --no-interaction
# -> vendor/bin/phpstan (2.2.2), vendor/bin/deptrac (2.0.5)
```

### 4.1 PHPStan — integrità simboli whole-program
Dolibarr non è autoloadato/PSR-4: per risolvere i simboli core (`getDolGlobalString`,
`dol_now`, `MAIN_DB_PREFIX`, classe base `DolibarrModules`, ecc.) si fa **scan** del core su
disco (vedi §5), senza analizzarlo. Config che FUNZIONA (baseline pulito su henax-ai):

```yaml
# phpstan-henaxai.neon
parameters:
    level: 0
    phpVersion: 80300
    paths:
        - <MODULE>/core
        - <MODULE>/lib/henaxai_client.lib.php
        - <MODULE>/lib/henaxai_service.lib.php
        - <MODULE>/lib/henaxai_manifest.lib.php
        - <MODULE>/lib/henaxai_manifest_builder.lib.php
        - <MODULE>/lib/henaxai_discovery.lib.php
        - <MODULE>/bin
    scanDirectories:
        - <DOL_CORE>/core/lib      # definizioni funzioni Dolibarr
        - <DOL_CORE>/core/class
        - <DOL_CORE>/core/modules  # classe base DolibarrModules
        - <MODULE>/lib/vendor      # Spyc, toml parser (simboli, non analisi)
    scanFiles:
        - <DOL_CORE>/main.inc.php
```
Lancio:
```bash
./vendor/bin/phpstan analyse -c phpstan-henaxai.neon --no-progress
```
**A cosa serve** (oltre `php -l`): simboli non definiti dopo refactor, dead code, chiamate
cross-file invalide. *Esempio reale*: avrebbe beccato a colpo sicuro la trappola di rename
`henax-architect` ↔ `architect_`. **Esito**: livello 0 pulito su tutti i 7 file di henax-ai
(bin inclusi) → il codice portato risolve tutti i simboli.

> Nota: gli "errori" iniziali (`$rights undefined`, `_load_tables not found`,
> `spyc_load not found`) erano **gap di scan**, non bug: aggiunti `core/modules` e
> `lib/vendor` allo scan → 0 errori. Lezione: prima di credere a un errore phpstan su
> Dolibarr, verificare che il simbolo sia nello scan.

### 4.2 Deptrac — confini architetturali L0/L1/L2 (INTEROP)
Codifica le regole INTEROP come check meccanico. Config `deptrac.yaml` (layer per directory):

```yaml
parameters:
  paths: [ <MODULES_DIR>, <module repos coinvolti> ]
  layers:
    - { name: L0, collectors: [{ type: directory, value: '.*/henax-ai/.*' }] }
    - { name: L1, collectors: [{ type: directory, value: '.*/(henax-architect|matrixchat)/.*' }] }
    - { name: L2, collectors: [{ type: directory, value: '.*/(Domicare)/.*' }] }
  ruleset:
    L2: [ L1, L0 ]
    L1: [ L0 ]
    L0: ~        # L0 non dipende da nessuno
```
```bash
./vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
```
> ⚠️ **Limite onesto**: deptrac segue `use`/`new`/`extends` (codice class-based). Dolibarr è
> prevalentemente **procedurale** (`require_once` + chiamate a funzione) → deptrac
> sotto-rileva l'accoppiamento procedurale. Per quel pezzo la **fonte di verità è
> `henaxai_discovery`** (il grafo da manifest/descriptor che abbiamo portato in henax-ai):
> deptrac è complementare, non sostitutivo. Tenere lo scope leggero (no `Domicare` intero:
> è enorme e rallenta). `exclude_files` toglie il rumore vendor (es. `lib/phpqrcode`).

**Esito verificato** (henax-ai + henax-architect + matrixchat): **0 violazioni** di layering.
Con `--report-uncovered` deptrac ha però segnalato un dato reale: `architect_ai_call() →
SkyllamLlm` (fallback legacy in `architect_ai.lib.php:330`) — un accoppiamento **L1→L1 per
classe** vietato da INTEROP, residuo del percorso pre-henax-ai. Da rimuovere nel cutover
(rimozione codice AI legacy). Esempio di deptrac che fa emergere uno smell architetturale reale.

---

## 5. Core Dolibarr su disco (per symbol resolution)
Il core completo è sotto `/opt/p2g_dev/dolibarr/var/www/html/` (Dolibarr **20.0.4**, ~15.6k
file PHP). `dolibarr/htdocs/` è solo `custom/` + `modulebuilder`. Usato come `<DOL_CORE>` negli
scan phpstan. (Mismatch minore: runtime container = v21, core su disco = v20.4; per i simboli
stabili è irrilevante.)

---

## 6. Istanza Dolibarr usa-e-getta (DA ALLESTIRE — ricetta)
Il pezzo mancante per la verifica end-to-end dei moduli che razionalizziamo
(skyllam/architect/domicare/industria40): un'istanza **separata e sacrificabile** dove
installarli e romperli liberamente. Procedura target:

1. `docker compose` minimale: `dolibarr:21` + `mariadb:10.11` su porte dedicate (≠ mba).
2. Montare i repo moduli in `htdocs/custom/` (bind mount dei repo del workspace).
3. Install + enable moduli target; eseguire `bin/build_manifests.php` / `validate_manifests.php`
   (bootstrap parametrico via env `DOL_DOCUMENT_ROOT`, già implementato in henax-ai).
4. Verificare: install tabelle, migrazione nomi (`llx_henax-architect_*`→`llx_henaxai_*`),
   `EXPLAIN` delle `skyllam.stats[].sql`, client AI contro endpoint stub.

Finché non c'è, le verifiche su quei moduli sono "smoke-test con stub" (vedi §7), non runtime.

---

## 7. Pattern operativi (lezioni di campo)
- **Comandi Bash lunghi / output grande possono perdersi** (deptrac su alberi enormi,
  pipe docker complesse): scrivere l'output su file e rileggerlo con lo strumento di
  lettura, oppure lanciare in **background**. Non incatenare retry sullo stesso comando pesante.
- **Smoke-test con stub**: per testare lib che dipendono da Dolibarr senza runtime, definire
  i pochi simboli minimi (`define('DOL_DOCUMENT_ROOT',...)`, `function dol_syslog(){}`) e
  invocare la funzione su dati reali del repo. *Esempi riusciti*: manifest engine su
  `architect.json` reale; builder che genera README/json dal `manifest.yaml`.
- **Segreti**: mai stampare password; leggerle dall'env del container e redarle negli output.
- **Cliente vivo**: su `henaxis-mba-*` solo SELECT.

---

## 8. Mappa: strumento → scopo
| scopo | strumento |
|---|---|
| Navigare/editare simboli | Serena |
| Cercare testo/regex cross-repo | ripgrep |
| Integrità simboli / dead code / rotture refactor | PHPStan (§4.1) |
| Confini di layer L0/L1/L2 (class-based) | Deptrac (§4.2) |
| Accoppiamento procedurale + grafo architettura | `henaxai_discovery` (in henax-ai) |
| Schema/FK/ID reali (read-only) | DB mba via docker exec (§3) |
| Verifica runtime end-to-end moduli | istanza usa-e-getta (§6, da allestire) |
| Test E2E browser della UI | Playwright + Chromium (§9) |
| PR / cross-repo GitHub | `gh` |
| Doc Dolibarr API/wiki | WebFetch/WebSearch |

---

## 9. Playwright + Chromium — test E2E della UI (parte stabile dei tools)
Test browser end-to-end delle pagine UI (admin/setup) contro l'istanza dev usa-e-getta (§6).
Installato in `<WORKSPACE>/.tooling/e2e/` (template versionati in `tooling/e2e/`).

Install (portabile):
```bash
cd <WORKSPACE>/.tooling/e2e
npm install            # @playwright/test
npx playwright install chromium   # scarica il browser (~114MB) in ~/.cache/ms-playwright
# se il launch fallisce per librerie di sistema mancanti: npx playwright install --with-deps chromium (richiede root)
```
Lancio (l'istanza dev deve essere su, §6):
```bash
npx playwright test --reporter=list
# override target: HENAXAI_BASE_URL=http://host:port  credenziali: HENAXAI_ADMIN / HENAXAI_PASS
```
Struttura: `playwright.config.ts` (baseURL `localhost:9199`, headless, screenshot on-failure),
`tests/henaxai-setup.spec.ts`.

**Esito verificato**: entrambi i test passano in Chromium reale, deterministici (login admin →
`setup.php` → render con tutti i provider; poi salva una key → valida → matrice "Esito validazione").
Il form POST porta il token `newToken()` che Playwright invia con la submit: **funziona senza
rilassare nulla**.

> 🐛 **Lezione (caso reale).** All'inizio i POST del form non eseguivano l'`action` e l'avevo
> erroneamente attribuito al CSRF (stavo per disattivarlo sul dev — fermato, giustamente, dal
> classificatore di sicurezza). La causa vera era un **filtro `GETPOST` invalido**:
> `GETPOST('action','az')` — `'az'` NON è un filtro Dolibarr valido (esistono `aZ`, `aZ09`,
> `alphanohtml`, …), quindi l'action veniva scartata silenziosamente (rompeva sia *save* sia
> *validate*). Fix sistemico: `GETPOST('action','aZ09')`. Morale: indagare fino alla causa
> radice, **mai** workaround che indeboliscono la sicurezza; e diffidare delle diagnosi comode.
> Verifica empirica della causa: `MAIN_SECURITY_CSRF_WITH_TOKEN` non era nemmeno settata.
