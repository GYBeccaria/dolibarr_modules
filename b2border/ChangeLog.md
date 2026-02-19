# ChangeLog — B2B Order Portal (b2border)

Modulo Dolibarr ID: 580300 | Autore: Henaxis

---

## v1.0.0 — 2026-02-19

**Tipo di rilascio:** Prima versione stabile

### Funzionalità introdotte

**Portale pubblico clienti**
- Autenticazione clienti B2B tramite `llx_societe_account` (compatibile con WebPortal Dolibarr nativo, `site='dolibarr_portal'`)
- Sessione portale isolata dal backend Dolibarr (prefisso cookie `B2BORDER_SESSID_`, nessuna condivisione di sessione)
- Token CSRF su tutti i form del portale

**Catalogo prodotti**
- Elenco prodotti con paginazione configurabile (`B2BORDER_PRODUCTS_PER_PAGE`, default 12)
- Ricerca testuale su `ref`, `label`, `description`
- Filtro per categoria Dolibarr (`llx_categorie_product`)
- Filtro per tag Dolibarr (`llx_element_tag`) con logica OR rispetto alle categorie
- Indicatore disponibilità stock in tempo reale (`llx_product_stock`, attivabile con `B2BORDER_SHOW_STOCK`)
- Supporto prezzi multilivello (`PRODUIT_MULTIPRICES`, campi `multiprices[N]`, `multiprices_ttc[N]`)
- Dettaglio singolo prodotto con foto, descrizione, prezzo e disponibilità

**Carrello e checkout**
- Carrello persistente in sessione PHP, indicizzato per `fk_product`
- Prezzi ricalcolati dal DB a ogni accesso (nessuna fiducia nei dati client)
- Calcolo totali tramite `calcul_price_total()` (compatibile con IVA inclusa/esclusa)
- Checkout con campo note ordine libero
- Creazione automatica ordine bozza in `llx_commande` con `module_source='b2border'`
- Pagina di conferma post-invio con numero ordine

**Amministrazione**
- Configurazione categorie e tag prodotti consentiti (multi-select con select2)
- Selezione livello prezzi predefinito (dropdown, disabilitato se PRODUIT_MULTIPRICES non attivo)
- Personalizzazione grafica: logo, favicon, colori CSS primari, CSS personalizzato, testo footer
- Download esempio CSS con tutti i selettori del portale documentati
- Tab "Foto prodotti" nella scheda prodotto Dolibarr per gestire immagini del portale
- Endpoint pubblico `getfile.php` per logo e favicon (senza autenticazione, con protezione path traversal)

**Architettura e portabilità**
- Nessuna tabella custom: usa esclusivamente tabelle standard Dolibarr
- Nessun path hardcoded: discovery di `main.inc.php` con 3 fallback portabili
- Nessuna dipendenza esterna in produzione
- Traduzioni complete in italiano (`it_IT`) e inglese (`en_US`)
- Test di integrazione PHP: 80 test, 0 fallimenti (method unit, SQL injection, HTTP endpoints)
