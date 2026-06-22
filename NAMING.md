# Naming standard — moduli Henax / Domicare

Obiettivo: nomi prevedibili che dichiarano **a quale livello** appartiene un modulo e **chi lo possiede**.
Oggi i nomi sono incoerenti (`henax-admin`, `henaxfse`, `henax-fse`, `henaxisanalytics`, `domihcp-be`, nomi liberi). Questo standard è il target verso cui migrare.

## Namespace

| prefisso | significato | esempi |
|---|---|---|
| `henax-*` | **orizzontale / riusabile** — portabile su qualunque cliente, dipende solo da librerie piattaforma + Dolibarr core | `henax-ai`, `henax-docflow`, `henax-chat`, `henax-fse`, `henax-export`, `henax-analytics`, `henax-help`, `henax-admin`, `henax-geo`, `henax-ops` |
| `domicare-*` | **verticale Domicare** — specifico del cliente Consorzio Domicare | `domicare-core`, `domicare-rendicontazione`, `domicare-compliance`, `domicare-woundcare`, `domicare-hcp` (la logica AI di documento NON è qui: sta in `henax-docflow`, domicare ne è un *profilo*) |
| `henaxis-*` | **verticale altri clienti** | `henaxis-mba` (Mutua MBA) |
| `vendor/*` | **moduli di terzi** vendorizzati, non sviluppati da noi | `arubasdi`, `efattita`, `tawkto`, `dolibarrassistant` |

## Regole

1. **kebab-case**, separatore `-`. Niente nomi attaccati: `henaxfse`→`henax-fse`, `henaxisanalytics`→`henax-analytics`.
2. Il **prefisso = livello architetturale** (vedi `INTEROP.md`). Un modulo riusabile è SEMPRE `henax-`; uno legato a un cliente è `domicare-`/`henaxis-<cliente>`.
3. La **classe descrittore Dolibarr** segue: `mod`+CamelCase senza trattini → `henax-ai` ⇒ `modHenaxAi`.
4. **Prefisso tabelle** = nome modulo **senza trattini**, con `_`: `henax-chat` ⇒ `llx_henaxchat_*`, `henax-ai` ⇒ `llx_henaxai_*`. Una tabella vive in UN solo modulo. **Mai il trattino nel nome tabella** (`llx_henax-architect_*` è un bug da sanare: rompe quoting/portabilità SQL).
5. **ID modulo (`$this->numero`)** assegnati per blocco namespace (vedi `REGISTRY.md`). Mai riusare un ID.
6. Nome repo == nome modulo. Un modulo = un repo (o una cartella in `modules/<namespace>/`).
