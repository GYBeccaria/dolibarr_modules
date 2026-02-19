# B2B Order Portal — Documentazione tecnica e operativa

> Modulo Dolibarr ID: 580300 | Versione: 1.0.0 | Autore: Henaxis
> URL portale: `/custom/b2border/public/index.php`

---

## 1. Scopo e contesto operativo

Il modulo **B2B Order Portal** (`b2border`) estende Dolibarr con un portale web self-service per clienti business. Consente ai clienti autenticati di sfogliare il catalogo prodotti, comporre un ordine tramite carrello e inviarlo come bozza su Dolibarr, senza accesso al backend amministrativo.

**Attori del sistema:**

| Attore | Ruolo | Accesso |
|---|---|---|
| Cliente B2B | Naviga catalogo, gestisce carrello, invia ordine | Solo portale pubblico (`/public/`) |
| Amministratore Dolibarr | Configura il modulo, gestisce foto prodotti | Backend Dolibarr + admin del modulo |
| Agente AI di sistema | Opera sul backend Dolibarr per conto dell'utente | API Dolibarr + PHP CLI |

---

## 2. Entità gestite

### 2.1 Cliente (Terzo autenticato)

- **Tabella DB:** `llx_societe_account` (campo `site = 'dolibarr_portal'`)
- **Chiavi:** `login`, `pass_crypted` (hash password), `pass_encoding` (algoritmo), `fk_soc` (collegamento a `llx_societe`)
- **Livello prezzi:** `llx_societe.price_level` — determina quale listino (1–6) viene applicato
- **Creazione account:** dalla scheda terzo in Dolibarr → tab "Account portale"
- **Sessione portale:** variabile `$_SESSION['b2border_account_id']`, prefisso cookie `B2BORDER_SESSID_`

### 2.2 Prodotto

- **Tabella DB:** `llx_product` (campo `tosell = 1` per prodotti visibili nel portale)
- **Classe PHP:** `Product` + helper `B2BOrderProduct` (`class/b2border_product.class.php`)
- **Filtri applicati a ogni query:**
  - Solo prodotti con `tosell = 1`
  - Solo prodotti nelle categorie/tag configurati in `B2BORDER_ALLOWED_CATEGORIES` e/o `B2BORDER_ALLOWED_TAGS` (logica OR se entrambi impostati)
- **Prezzi:** `llx_product_price` — multilivello via `PRODUIT_MULTIPRICES`; campo `multiprices[N]`, `multiprices_ttc[N]`, `multiprices_tva_tx[N]`
- **Stock:** `llx_product_stock` — sommato via `Product::load_stock()` → `stock_reel`
- **Foto:** directory standard Dolibarr (`DOL_DATA_ROOT/produit/[REF]/`)

### 2.3 Carrello

- **Storage:** `$_SESSION['b2border_cart']['items']` (array indicizzato per `fk_product`)
- **Classe PHP:** `B2BOrderCart` (`class/b2border_cart.class.php`)
- **Struttura item carrello:**
  ```
  fk_product, ref, label, qty, pu_ht, pu_ttc, tva_tx, price_base_type, product_type
  ```
- **Prezzi:** ricalcolati dal DB a ogni chiamata a `getItems()` — il carrello non si fida dei dati client
- **Metodi principali:** `addItem(fk_product, qty, price_level)`, `updateItemQty(fk_product, qty)`, `removeItem(fk_product)`, `clear()`, `getItems(price_level)`, `getTotals(price_level)`, `getCount()`, `isEmpty()`

### 2.4 Ordine

- **Tabella DB:** `llx_commande` (colonna DB: `fk_statut = 0` per bozza; nella API ORM Dolibarr la proprietà è `$commande->statut`)
- **Classe PHP:** `Commande` (Dolibarr core)
- **Metodo di creazione:** `Commande::create($user)` → `addline()` per ogni item del carrello
- **Marcatori:** `module_source = 'b2border'`, note ordine inserite dal cliente
- **Utente tecnico:** definito in `WEBPORTAL_USER_LOGGED` (impostazione Dolibarr)

### 2.5 Categoria e Tag

- **Categorie prodotto:** `llx_categorie` (type = 0) + `llx_categorie_product` (junction)
- **Tag prodotto:** `llx_element_tag` (join su `fk_element` = `llx_product.rowid`)
- **Configurazione:** `B2BORDER_ALLOWED_CATEGORIES` e `B2BORDER_ALLOWED_TAGS` (CSV di ID interi in `llx_const`)

---

## 3. Operazioni disponibili (action space)

### 3.1 Operazioni del cliente (portale pubblico)

| Operazione | Endpoint / Controller | Parametri |
|---|---|---|
| Login | `POST /public/index.php?controller=login` action=login | `login`, `password`, `token` |
| Logout | `GET /public/logout.php` | — |
| Sfoglia catalogo | `GET /public/index.php?controller=catalog` | `page`, `search`, `category` |
| Dettaglio prodotto | `GET /public/index.php?controller=product` | `id` (fk_product) |
| Aggiungi al carrello | `POST /public/index.php?controller=cart` action=add | `product_id`, `qty` |
| Aggiorna quantità | `POST /public/index.php?controller=cart` action=update | `product_id`, `qty` |
| Rimuovi dal carrello | `POST /public/index.php?controller=cart` action=remove | `product_id` |
| Visualizza carrello | `GET /public/index.php?controller=cart` | — |
| Checkout (anteprima) | `GET /public/index.php?controller=checkout` | — |
| Invia ordine | `POST /public/index.php?controller=checkout` action=createorder | `note_public`, `ref_client`, `token` |
| Conferma ordine | `GET /public/index.php?controller=confirmation` | `id` (order_id) |

### 3.2 Operazioni dell'amministratore

| Operazione | File | Note |
|---|---|---|
| Configura filtri prodotti e prezzi | `admin/setup.php` | Salva in `llx_const` |
| Personalizza aspetto (logo, colori, CSS) | `admin/appearance.php` | File in `DOL_DATA_ROOT/b2border/` |
| Scarica esempio CSS | `admin/downloadcss.php` | Download diretto |
| Gestisci foto prodotto | `product_photos.php` | Tab nella scheda prodotto |
| Attiva/disattiva modulo | Dolibarr Admin → Moduli | ID modulo: 580300 |

### 3.3 Operazioni dell'agente AI di sistema

Un agente AI può eseguire le seguenti operazioni tramite PHP CLI o API Dolibarr:

| Obiettivo | Metodo consigliato |
|---|---|
| Elencare prodotti visibili nel portale | `B2BOrderProduct::getCatalogProducts($price_level, $limit, $offset, $search, $category_id)` |
| Verificare disponibilità prodotto | `B2BOrderProduct::isInStock($product)` |
| Ottenere prezzo per livello cliente | `B2BOrderProduct::getProductPrice($product, $price_level)` |
| Creare/modificare account cliente portale | SQL su `llx_societe_account` con `site='dolibarr_portal'` |
| Consultare ordini B2B inviati | `SELECT * FROM llx_commande WHERE module_source='b2border'` |
| Modificare categorie consentite | `dolibarr_set_const($db, 'B2BORDER_ALLOWED_CATEGORIES', '1,2,3', ...)` |
| Leggere configurazione modulo | `getDolGlobalString('B2BORDER_*')` o `getDolGlobalInt('B2BORDER_*')` |

---

## 4. Costanti di configurazione

Tutte le costanti sono in `llx_const` con `entity = conf->entity`.

| Costante | Tipo | Default | Descrizione |
|---|---|---|---|
| `B2BORDER_ALLOWED_CATEGORIES` | string | `''` | ID categorie prodotto visibili (CSV). Vuoto = tutti i prodotti |
| `B2BORDER_ALLOWED_TAGS` | string | `''` | ID tag prodotto visibili (CSV). OR con le categorie |
| `B2BORDER_DEFAULT_PRICE_LEVEL` | int | `1` | Livello prezzi per clienti senza livello esplicito (1–6) |
| `B2BORDER_PRODUCTS_PER_PAGE` | int | `12` | Prodotti per pagina nel catalogo |
| `B2BORDER_SHOW_STOCK` | int | `1` | Mostra indicatore stock (0=nascosto, 1=visibile) |
| `B2BORDER_PORTAL_TITLE` | string | `'B2B Order Portal'` | Titolo nell'intestazione del portale |
| `B2BORDER_PRIMARY_COLOR` | string | `''` | Colore primario CSS (`--b2b-primary`), es. `#2e86de` |
| `B2BORDER_PRIMARY_DARK_COLOR` | string | `''` | Colore primario scuro CSS (`--b2b-primary-dark`) |
| `B2BORDER_CUSTOM_CSS` | string | `''` | CSS aggiuntivo iniettato nel `<head>` del portale |
| `B2BORDER_FOOTER_TEXT` | string | `''` | Testo footer personalizzato |
| `B2BORDER_HIDE_POWERED_BY` | int | `0` | Nasconde "Powered by Dolibarr" nel footer (1=nascosto) |
| `B2BORDER_LOGO` | string | `''` | Nome file logo (in `DOL_DATA_ROOT/b2border/`). **Non in `$this->const`**: impostata da `appearance.php` al momento dell'upload, non viene resettata al reinit del modulo. |
| `B2BORDER_FAVICON` | string | `''` | Nome file favicon (in `DOL_DATA_ROOT/b2border/`). **Non in `$this->const`**: stessa logica di B2BORDER_LOGO. |

---

## 5. Struttura file e classi PHP

```
b2border/
├── core/modules/modB2BOrder.class.php   # Descriptor modulo (ID 580300)
├── class/
│   ├── b2border_context.class.php       # Singleton sessione: autenticazione, livello prezzi, token CSRF
│   ├── b2border_product.class.php       # Query catalogo, prezzi, stock, filtri categoria/tag
│   └── b2border_cart.class.php          # Gestione carrello in sessione, calcolo totali
├── controllers/
│   ├── login.controller.php             # Autenticazione e logout
│   ├── catalog.controller.php           # Elenco prodotti con filtri e paginazione
│   ├── product.controller.php           # Dettaglio singolo prodotto
│   ├── cart.controller.php              # CRUD carrello
│   ├── checkout.controller.php          # Anteprima ordine e creazione bozza
│   └── confirmation.controller.php      # Pagina di conferma post-ordine
├── public/
│   ├── index.php                        # Entry point: routing per action
│   ├── main.inc.php                     # Bootstrap portale (NOLOGIN, sessione separata)
│   ├── getfile.php                      # Serve logo/favicon senza autenticazione
│   ├── image.php                        # Serve immagini prodotto con resize (proxy sicuro)
│   ├── logout.php                       # Distrugge sessione portale
│   ├── tpl/                             # Template HTML (header, menu, footer, login, catalog, cart, checkout, confirmation, product)
│   ├── css/b2border.css                 # Stili portale
│   └── js/b2border.js                   # JavaScript portale
├── admin/
│   ├── setup.php                        # Configurazione categorie, tag, prezzi, stock
│   ├── appearance.php                   # Logo, favicon, colori, CSS, footer
│   └── downloadcss.php                  # Scarica esempio CSS documentato
├── lib/b2border.lib.php                 # b2borderAdminPrepareHead() — tab admin
├── product_photos.php                   # Tab foto nella scheda prodotto Dolibarr
└── langs/
    ├── it_IT/b2border.lang              # Traduzioni italiano
    └── en_US/b2border.lang              # Traduzioni inglese
```

---

## 6. Vincoli e regole di business

- Un prodotto è visibile nel portale **solo se** `tosell = 1` E rientra nelle categorie/tag configurati (se impostati)
- I prezzi sono **sempre ricalcolati dal DB** al momento del checkout — il carrello non accetta valori lato client
- Gli ordini vengono creati come **bozza** (`statut = 0`) e devono essere confermati manualmente da un operatore Dolibarr
- La sessione portale è **separata** dalla sessione Dolibarr: un cliente non ottiene mai accesso al backend
- Il token CSRF è verificato su **tutti i POST** del portale
- Il livello prezzi viene determinato in quest'ordine: (1) `llx_societe.price_level` del terzo autenticato, (2) `B2BORDER_DEFAULT_PRICE_LEVEL`, (3) fallback a livello 1
- Le foto prodotto sono visibili nel portale solo se presenti nella directory standard Dolibarr (`produit/[REF]/`)

---

## 7. Dipendenze e compatibilità

- **Dolibarr:** ≥ 20.0.0
- **PHP:** ≥ 7.4
- **Moduli Dolibarr richiesti:** `modProduct` (Prodotti), `modCommande` (Ordini clienti)
- **Moduli Dolibarr opzionali:** `modStock` (per indicatore disponibilità), `modCategorie` (per filtri categorie)
- **Nessuna dipendenza esterna** (no Composer, no npm in produzione)
- **Nessuna tabella custom:** tutte le operazioni usano tabelle standard Dolibarr
