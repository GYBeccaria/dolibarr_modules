# CHANGELOG EFATTITA FOR <a href="https://www.dolibarr.org">DOLIBARR ERP CRM</a>

# 1.0
- Prima release
## 1.0.2
- Cooreggees
## 1.0.3
- Cooreggees
## 1.0.4
- aggiunto iban
## 1.0.5
- correzione virgola migliaia
## 1.0.6
- aggiunti sconti
## 1.0.7
- il numero fattura va ora impostato a mano nelle impostazioni del modulo fattura
## 1.0.8
- correzione errore "file pdf_efattita.php not found"
## 1.0.9
- correzione mancanza tipo cassa
- formattazione importi
## 1.0.10
- menu importazione spostato in fatture fornitori
- icone
- gestione errori in importazione
## 1.0.11
- correzione importazione parziale della fattura
## 1.0.12
- distingue persona fisica (privato) da giuridica
- deduce nome e cognome da denominazione e codice fiscale
## 1.0.13
- aggiunge esigibilità iva
## 1.0.14
- carica i file con firma digitale
## 1.0.15
- correzione mancanza campo natura e riferimento normativo in caso di iva 0
## 1.0.16
- correzione posizione codice fiscale / partita iva
## 1.0.17
- correzione sconto/maggioragione
- correzione fatture passive di cliente diverso ma con stesso numero fattura (la dava per già caricata)
## 1.0.18
- correzione descrizione mancante in caso di prodotto/servizio non registrato
## 1.0.19
- correzione imposta: max 2 decimali
## 1.0.20
- timestamp in progressivo invio
## 1.0.21
- correzione decimali
- pulizia country code dalla partita iva
- caratteri speciali da descrizione articoli
- modificato id modulo
## 1.0.22
- cambia il sistema di numerazione del file: crea un codice alfanumenrico basato sull'id della fattura
## 1.0.23
- data scadenza fattura importata
## 1.0.24
- correzione errore che sovrascriveva
## 1.0.25
- Cooregge caratteri speciali in nome fattura
- aggiunto ImportoTotaleDocumento
## 1.0.26
- aggiunto DatiBollo
## 1.0.27
- correzione importazione fattura nota di credito

# 1.1
- importa ritenuta e cap da fattura passiva
- imposta fornitore con ra e cap (tassa1 e tassa2)
- crea set tassazione in base a fattura ricevuta
## 1.1.1
- controllo partita iva in importazione non tiene più conto del prefisso nazionale
## 1.1.2
- Cooregge invio a PA
## 1.1.3
- gestione bollo: sostituisce il revenuestamp di dolibarr perchè in italia può essere a carico del cliente o del fornitore

# 2.0
- Controllo fattura con xsd ufficiale
- Spostato regimefiscale in impostazioni modulo
- Abilitati i valori di default per i campi extra in fattura
- Aggiunta Provincia in CedentePrestatore
- StabileOrganizzazione viene preso in cosiderazione in importazione fatture
- Miglior filtro di tutti i valori testuali/html
- Indentazione xml per miglior leggibilità
- RiferimentoNormativo lista a scelta
- Natura viene dedotto da RiferimentoNormativo
## 2.0.1
- correzione sconti ad importo (non percentuale) in importazione fattura
## 2.0.2
- Corregge il sezionale nella maschera fattura
- Abilita il check versione dei moduli
## 2.0.3
- Corregge blocco in mancanza 'riferimento normativo' se iva è 0
## 2.0.4
- Corregge errore nei nomi dei campi cassa previdenziale
- Corregge in maiuscolo i campi codice fiscale
## 2.0.5
- Corregge errore formato trasmissione per PA
- Corregge calcolo imponibile su riepilogo in presenza di cassa previdenziale
## 2.0.6
- Corregge errore su prefisso tabella db IVA
- Aggiunge la gestione dell'arrotondamento applicato in fattura
## 2.0.7
- Correzione su sconto maggiorazione
## 2.0.8
- Aggiunge sezionale a nota di credito
- Corregge valori di default dei campi extra di tipo select

# 2.1
- Aggiunge bollo in PDF fattura (solo se a carico del cliente)
## 2.1.1
- Aggiunge bollo a totale in PDF fattura
## 2.1.2
- Compatibilità installazione nella root directory (includes)
## 2.1.3
- Correzione calcolo bollo

# 2.2
- Anteprima fattura elettronica
## 2.2.1
- Mantiente integre le descrizioni del riferimento normativo
## 2.2.2
- Aggiunge valore rif. normativo utilizzabile per covid19
## 2.2.3
- Correzione calcolo bollo a fornitore

# 2.3
- Correzioni campi esigibilità iva, (scadenza | modalità | condizioni) pagamento, iban
- Unisce i tab 'scheda fattura' con 'fattura elettronica' in uno solo
- Files xml in raccolta documenti insieme ai pdf
- Possibilità di scaricare xml, modificarlo e reimportarlo nella scheda documenti
## 2.3.1
- Correzione segno importi su nota di credito
## 2.3.2
- Compatibilità con multicompany

# 2.4
- Aggiunge Codice Articolo nelle linee fattura
## 2.4.1
- Corregge calcolo ritenuta nel caso in cui non è specificata all'inteno delle linee fattura
## 2.4.2
- Cartella output documents/facture
- Cooregge errore su Barcode assente

# 2.5
- Aggiunge Tipo codice EAN
- Aggiunge secondo codice articolo: TARIC, CPV, SSC
- Aggiunge ReferimentoAmministrazione
- Aggiunge DatiFattureCollegate (nota di credito)
- Aggiunge TipoCessionePrestazione
## 2.5.1
- Aggiunge trattamento IVA dei buoni acquisto

# 2.6.0
- Aggiornamento formato come da Agenzia delle Entrate
## 2.6.1
- Correzione campo Natura
## 2.6.2
- Corregge campo Natura N6 fuori limite
## 2.6.3
- Corregge errore in importo sconto
## 2.6.4
- Toglie codici Natura non più accettati dal 1° gennaio
## 2.6.5
- Aggiornati fogli stile
- Correzione RiferimentoNormativo
## 2.6.6
- Correzione RiferimentoNormativo
## 2.6.7
- Correzione riferimento contratto
## 2.6.8
- Accorcia riferimento ordine se troppo lungo
## 2.6.9
- Accetta fatture con lo stesso numero ma data (anno) diversa

# 2.7.0
- Genera e importa fatture con Ritenuta d'acconto e Cassa previdenziale
## 2.7.1
- Corregge ritenuta su cap ove non indicata in importazione
## 2.7.2
- Coregge errore su creazione ordine
## 2.7.3
- Corregge unità di misura
## 2.7.4
- Corregge righe sconto, anticipo, deposito
## 2.7.5
- Corregge errore su calcolo ritenuta e cassa previdenziale
## 2.7.6
- Corregge errori in calcoli tasse
## 2.7.7
- Corregge calcolo bollo
## 2.7.8
- Compatibilità DDT Italia
## 2.7.9
- Corregge errore iva su cassa previdenziale
## 2.7.10
- Corregge importazione fatture uguali
- Corregge ritenuta su cassa previdenziale
## 2.7.11
- Corregge errore importazione file p7m
## 2.7.12
- Corregge indicazione bollo a carico del cliente
- Importa scadenza pagamento
## 2.7.13
- Corregge ImportoPagamento in caso di "scissione dei pagamenti"
## 2.7.14
- Corregge il riferimento normativo del bollo
## 2.7.15
- Correge campo partita iva europee che includono lettere

# 2.8
- Aggiunge gestione per regime forfettario
- Aggiunte gestione per esportatori abituali
## 2.8.1
- Corregge conteggio rimanenza intento
- Rimanenza intento su tab cliente
## 2.8.2
- Corregge errori in importazine alcune fatture (Enel)
- Cooregge CSRF error

# 2.9
- Cooregge CSRF error
- Corregge totale documento
- Compatibilità php 8.1
- Aggiunge RiferimentoAmministrazione in Cedente/Prestatore
- Riordina setup
- Aggiunge check updates

# 2.10
- Aggiunge l'importazione delle fatture dal pannello controllo fatture
- Aggiunge l'importazione di fatture attive
- Corregge etichetta tassazione in importazione fatture
# 2.10.1
- Cooregge bollo ripetuto
- Cooregge check fattura attiva
# 2.10.2
- Aggiunge riferimenti normativi per forfettari
- Corregge pagina impostazioni

# 2.11
- Aggiunge la possibilità di differenzare la Natura per ogni riga
- Corregge le proprietà dei campi extra (etichette, posizione, permessi, etc...) ad ogni disattivazione/riattivazione del modulo
# 2.11.1
- Risolve importazione di alcune fatture firmate (es. Enel)

# 2.12
- Aggiunge e importa i pagamenti scadenzati (modulo Scadenzario)
- Aggiunge Il Codice commessa convenzione
## 2.12.1
- Impedisce pagamenti scadenzati con scissione dei pagamenti
## 2.12.2
- Corregge sparizione del protocollo intento in fattura se viene modificato nel terzo
## 2.12.3
- Corregge riconoscimento CodiceArticolo in importazione fattura
- Corregge conflitto funzione con UltimatePDF
## 2.12.4
- Corregge rilevamento prodotti/servizi in importazione
## 2.12.5
- Agginge riferiemnto normativo 'art. 26 c.3 DPR 633/1972'
- Permette importazione fattura stesso numero con TD diverso
# 2.13
- Generazione xml alla convalida
- Aggiunge art. 8 c.35 Legge 67/1988 nei rif. normativi
- Calcoli R.A. e CAP su tutte le schede
- FIX fattura già importata
# 2.14
- Calcoli R.A. e CAP per linea

# 3.0.0
- Autocompletamento campi
- Invio multiplo fatture
- Calcoli R.A. e CAP per linea

# 3.1
- Importazione fattura semplificata
## 3.1.1
- FIX importazione fatture

# 3.2
- Aggiunge altri riferimenti normativi
- Allega documenti all'xml
- Aggiunge hook writeXml

# 3.3
- Aggiunge causale fattura
- Preview xml
# 3.3.1
- Riporta il rif cliente nelle righe
# 3.3.2
- Corregge natura bollo per i forfettari
# 3.3.3
- Corregge errore in firma proposta

# 3.4
- Aggiunge documenti linkati (proposte, ddt, ecc..) ai file allegabili
## 3.4.1
- Fix scomparsa della ritenuta d'acconto nel pdf con iva 0
## 3.4.2
- Fix riconosce il terzo anche dal codice fiscale
## 3.4.3
- Fix arrotondamento
## 3.4.4
- Fix descrizione linea fattura importata

# 3.5
- Salva il file xml della fattura nei documenti

# 3.6
- Aggiunge i campi extra in modello fattura
- Importa in fattura i campi extra da modello fattura
## 3.6.1
- Fix generazione automatica xml
## 3.6.2
- Fix scomparsa ra/cap su modifica fattura fornitore
- Fix allegati di oggetti linkati multipli in xml