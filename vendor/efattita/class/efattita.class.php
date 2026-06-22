<?php
/* Copyright (C) 2002-2007 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2013 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Sebastien Di Cintio   <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier        <benoit.mortier@opensides.be>
 * Copyright (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2014 Regis Houssin         <regis.houssin@capnetworks.com>
 * Copyright (C) 2006      Andre Cianfarani      <acianfa@free.fr>
 * Copyright (C) 2007      Franky Van Liedekerke <franky.van.liedekerke@telenet.be>
 * Copyright (C) 2010-2016 Juanjo Menent         <jmenent@2byte.es>
 * Copyright (C) 2012-2014 Christophe Battarel   <christophe.battarel@altairis.fr>
 * Copyright (C) 2012-2015 Marcos García         <marcosgdf@gmail.com>
 * Copyright (C) 2012      Cédric Salvador       <csalvador@gpcsolutions.fr>
 * Copyright (C) 2012-2014 Raphaël Doursenaud    <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2013      Cedric Gross          <c.gross@kreiz-it.fr>
 * Copyright (C) 2013      Florian Henry         <florian.henry@open-concept.pro>
 * Copyright (C) 2016      Ferran Marcet         <fmarcet@2byte.es>
 * Copyright (C) 2018      Alexandre Spangaro    <aspangaro@zendsi.com>
 * Copyright (C) 2018      Nicolas ZABOURI        <info@inovea-conseil.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/compta/facture/class/facture.class.php
 *	\ingroup    facture
 *	\brief      File of class to manage invoices
 */
include_once DOL_DOCUMENT_ROOT.'/core/class/commoninvoice.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/client.class.php';
require_once DOL_DOCUMENT_ROOT.'/margin/lib/margins.lib.php';
include_once DOL_DOCUMENT_ROOT.'/multicurrency/class/multicurrency.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
dol_include_once('/efattita/lib/efattita.lib.php');
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';

/**
 *	Class to manage invoices
 */
class ElectronicFacture extends Facture
{
	public $type='xml';

	/**
	 *  Create a document onto disk according to template module.
	 *
	 *	@param	string		$modele			Generator to use. Caller must set it to obj->modelpdf or GETPOST('modelpdf') for example.
	 *	@param	Translate	$outputlangs	objet lang a utiliser pour traduction
	 *  @param  int			$hidedetails    Hide details of lines
	 *  @param  int			$hidedesc       Hide description
	 *  @param  int			$hideref        Hide ref
	 * @param   null|array  $moreparams     Array to provide more information
	 *	@return int        					<0 if KO, >0 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails=0, $hidedesc=0, $hideref=0, $moreparams=null)
	{
		global $conf, $langs;

		$langs->load("bills");

		if (! dol_strlen($modele)) {

			if ($this->modelpdf) {
				$modele = $this->modelpdf;
			} elseif (! empty($conf->global->FACTURE_ADDON_PDF)) {
				$modele = $conf->global->FACTURE_ADDON_PDF;
			}
		}

		$modelpath = "/efattita/core/modules/efattita/doc/";
		return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
	}

	public function setDocModel($user, $modelpdf){
		$this->modelpdf = $modelpdf;
	}

	/**
	 * 
	 * deprecata?
	 */
	public function load_invoice_xml($xml_fattura){

		$xmlobject = simplexml_load_string(base64_decode($xml_fattura->DocumentoXML));

		dol_syslog(get_class($this)."::load_invoice_xml");

		$socid = $this->createUpdateThirdParty($xmlobject);

		# più fatture
		if($xmlobject->FatturaElettronicaBody[0]){
			foreach ($xmlobject->FatturaElettronicaBody as $xmlobjectelm){
				if ($this->carica_fattura($xmlobjectelm,$socid, $xmlobject->FatturaElettronicaHeader->DatiTrasmissione->FormatoTrasmissione)) {
					$result ++;
				}
			}
		}else{
		# una sola
			$result = $this->carica_fattura($xmlobject->FatturaElettronicaBody,$socid, $xmlobject->FatturaElettronicaHeader->DatiTrasmissione->FormatoTrasmissione);
		}
		return $result;
	}



	/*
	* crea o aggiorna il fornitore in base ai dati presenti in Fattura
	* $type 1 = supplier, 0 = client
	*/
	public function createUpdateThirdParty($fattura, $type = 1){

		global $conf, $langs, $user, $mysoc;

		$FormatoTrasmissione = $fattura->FatturaElettronicaHeader->DatiTrasmissione->FormatoTrasmissione;

		if ($type == 1) {
			$nodoTerzaParte = 'CedentePrestatore';
		}else {
			$nodoTerzaParte = 'CessionarioCommittente';
		}

		if ($FormatoTrasmissione == 'FSM10') {
			$partita_iva = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->IdFiscaleIVA->IdCodice;
			$codice_fiscale = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->CodiceFiscale;
		}else {
			$partita_iva = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->DatiAnagrafici->IdFiscaleIVA->IdCodice;
			$codice_fiscale = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->DatiAnagrafici->CodiceFiscale;
		}

		$sql = strtr("SELECT rowid FROM llx_societe
			WHERE 
			(tva_intra like '%:partita_iva' and ':partita_iva' != '')
			OR (idprof4 = ':codice_fiscale' and ':codice_fiscale' != '')",[
			'llx_'				=> MAIN_DB_PREFIX,
			':partita_iva'		=> $partita_iva,
			':codice_fiscale'	=> $codice_fiscale
		]);
		
		$res = $this->db->query($sql);

		$thirdParty = new Societe($this->db);

		if($res->num_rows > 0){
		// carica la terza parte
			$thirdParty_res = $this->db->fetch_object($res);
			$thirdParty->fetch($thirdParty_res->rowid);
		}else{
			$thirdParty->tva_intra = $partita_iva;
		}

		# comunque aggiorna i dati
		if ($FormatoTrasmissione == 'FSM10') {
			$thirdParty->tvaintra = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->IdFiscaleIVA->IdCodice;
			$thirdParty->idprof4 = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->CodiceFiscale;
			$thirdParty->name = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->Denominazione ?
				$fattura->FatturaElettronicaHeader->$nodoTerzaParte->Denominazione :
				$fattura->FatturaElettronicaHeader->$nodoTerzaParte->Nome .' '.
				$fattura->FatturaElettronicaHeader->$nodoTerzaParte->Cognome;

		}else {
			$thirdParty->tvaintra = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->DatiAnagrafici->IdFiscaleIVA->IdCodice;
			$thirdParty->idprof4 = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->DatiAnagrafici->CodiceFiscale;
			$thirdParty->name = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->DatiAnagrafici->Anagrafica->Denominazione ?
				$fattura->FatturaElettronicaHeader->$nodoTerzaParte->DatiAnagrafici->Anagrafica->Denominazione :
				$fattura->FatturaElettronicaHeader->$nodoTerzaParte->DatiAnagrafici->Anagrafica->Nome .' '.
				$fattura->FatturaElettronicaHeader->$nodoTerzaParte->DatiAnagrafici->Anagrafica->Cognome;
			
		}

		// Sede: se presente StabileOrganizzazione prende i dati da lì sennò da Sede
			if($fattura->FatturaElettronicaHeader->$nodoTerzaParte->StabileOrganizzazione)
			{
				$Sede = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->StabileOrganizzazione;
			}
			else
			{
				$Sede = $fattura->FatturaElettronicaHeader->$nodoTerzaParte->Sede;
			}

		$thirdParty->address = $Sede->Indirizzo.' '.$Sede->NumeroCivico;
		$thirdParty->zip = $Sede->CAP;
		$thirdParty->town = $Sede->Comune;
		$thirdParty->state_code = $Sede->Provincia;
		$thirdParty->state_id = fetchDepartmentId($thirdParty->state_code);
		$thirdParty->country_code = $Sede->Nazione;
		$thirdParty->country_id = fetchCountryId($thirdParty->country_code);
		if ($type == 0) {
			$thirdParty->client = 1;
			$thirdParty->code_client = ((float) DOL_VERSION >= 6) ? 'auto' : -1;
		}
		if ($type == 1) {
			$thirdParty->fournisseur = 1;
			$thirdParty->code_fournisseur = ((float) DOL_VERSION >= 6) ? 'auto' : -1;
	
		// RA e CP
			$thirdParty->localtax1_assuj = $fattura->FatturaElettronicaBody->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta ? 1 : 0;
			$thirdParty->localtax2_assuj = $fattura->FatturaElettronicaBody->DatiGenerali->DatiGeneraliDocumento->DatiCassaPrevidenziale ? 1 : 0;
			$thirdParty->array_options['options_iva_su_cp'] = $fattura->FatturaElettronicaBody->DatiGenerali->DatiGeneraliDocumento->DatiCassaPrevidenziale->AliquotaIVA ? 1 : 0;
			$thirdParty->array_options['options_ra_su_cp'] = $fattura->FatturaElettronicaBody->DatiGenerali->DatiGeneraliDocumento->DatiCassaPrevidenziale->Ritenuta ? 1 : 0;
		}


		// jet exixts
		if($thirdParty->id)
			$result = $thirdParty->update($thirdParty->id, $user);
		else
			$result = $thirdParty->create($user);
		return $thirdParty->id;
	}


	// fattura passiva
	function carica_fattura($fattura,$socid, $FormatoTrasmissione, $origin_xml_file = null, $invoiceFileName = null){
		global $conf, $user, $IvaSuCassaPrevidenziale, $langs, $RitenutaSuCassaPrevidenziale, $hookmanager;

		$hookmanager->initHooks(array('efattitaimport'));

		$sql = strtr("SELECT rowid FROM llx_facture_fourn WHERE ref_supplier = ':ref_supplier' and fk_soc = :fk_soc AND datef = ':datef' and  type = :type",[
			'llx_' => MAIN_DB_PREFIX,
			':ref_supplier' => $this->db->escape($fattura->DatiGenerali->DatiGeneraliDocumento->Numero),
			':datef' => $fattura->DatiGenerali->DatiGeneraliDocumento->Data,
			':fk_soc' => $socid,
			':type' => $fattura->DatiGenerali->DatiGeneraliDocumento->TipoDocumento == 'TD04' ? 2 : 0
			]);

		$res = $this->db->query($sql);
		if($res->num_rows > 0) $known = true;

		if($known){
				setEventMessage('La fattura '.$fattura->DatiGenerali->DatiGeneraliDocumento->Numero.' era già registrata', 'warnings');
				return 0;
		}else{
			$FactureFournisseur = new FactureFournisseur($this->db);
			$FactureFournisseur->ref_supplier = $fattura->DatiGenerali->DatiGeneraliDocumento->Numero;
			$FactureFournisseur->socid = $socid;
			$FactureFournisseur->type = $fattura->DatiGenerali->DatiGeneraliDocumento->TipoDocumento == 'TD04' ? 2 : 0;
			$FactureFournisseur->date = strtotime($fattura->DatiGenerali->DatiGeneraliDocumento->Data);
			$FactureFournisseur->multicurrency_code = $fattura->DatiGenerali->DatiGeneraliDocumento->Divisa;
			$FactureFournisseur->date_lim_reglement = $fattura->DatiPagamento->DettaglioPagamento->DataScadenzaPagamento;
			$FactureFournisseur->date_echeance = $fattura->DatiPagamento->DettaglioPagamento->DataScadenzaPagamento;

			$FactureFournisseur->fetch_thirdparty(); // Serve per il calcolo delle tasse
			$FactureFournisseur->author = $user->id;
			// workaround: in caso di valore nullo dolibarr mostrerebbe un link alla prima fattura nel database
				$FactureFournisseur->fk_facture_source = -1;
			$id = $FactureFournisseur->create($user);
			if($id > 0){
			
			// imposta iva e ritenuta sulla cassa previdenziale

				// questo controllo non è sufficiente a quanto pare.
				// ci sono fatture che non hanno impostato questo blocco ma la ritenuta sulla cassa previdenziale viene aggiunta in
				// $fattura->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta->ImportoRitenuta
				$RitenutaSuCassaPrevidenziale = $fattura->DatiGenerali->DatiGeneraliDocumento->DatiCassaPrevidenziale->Ritenuta ? 1 : 0;

				$IvaSuCassaPrevidenziale = $fattura->DatiGenerali->DatiGeneraliDocumento->DatiCassaPrevidenziale->AliquotaIVA > 0 ? 1 : 0;

				if ($FormatoTrasmissione == 'FSM10') {

					foreach($fattura->DatiBeniServizi as $DettaglioLinee){
						$DettaglioLinee->AliquotaIVA = $DettaglioLinee->DatiIVA->Aliquota ? $DettaglioLinee->DatiIVA->Aliquota : round($DettaglioLinee->DatiIVA->Imposta / ($DettaglioLinee->Importo - $DettaglioLinee->DatiIVA->Imposta) * 100);
						$DettaglioLinee->PrezzoUnitario = $DettaglioLinee->Importo;
						
						$this->carica_linea($DettaglioLinee,$socid,$FactureFournisseur,0,0,'TTC');
					}

				}else {
					if($fattura->DatiBeniServizi->DettaglioLinee[0]){
	
					// ricontrolla se non sia applicata la ritenuta sulla cassa previdenziale anche se non dichiarata
						if($RitenutaSuCassaPrevidenziale == 0){
							foreach($fattura->DatiBeniServizi->DettaglioLinee as $DettaglioLinee) {
								if($DettaglioLinee->Ritenuta){
									$totRitenutaLinee += ($DettaglioLinee->PrezzoTotale * $fattura->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta->AliquotaRitenuta / 100);
								}
							}
							if(nformat($fattura->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta->ImportoRitenuta) != nformat($totRitenutaLinee)) {
								$RitenutaSuCassaPrevidenziale = 1;
							}
						}
	
	
						foreach($fattura->DatiBeniServizi->DettaglioLinee as $DettaglioLinee){
							$this->carica_linea($DettaglioLinee,$socid,$FactureFournisseur,$fattura->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta->AliquotaRitenuta,$fattura->DatiGenerali->DatiGeneraliDocumento->DatiCassaPrevidenziale);
						}
					}
	
					/* Arrotondamenti */
					if($fattura->DatiBeniServizi->DatiRiepilogo[0]){
						foreach($fattura->DatiBeniServizi->DatiRiepilogo as $DatiRiepilogo){
							$this->applica_arrotondamento($DatiRiepilogo, $FactureFournisseur);
						}
					}else{
						$this->applica_arrotondamento($fattura->DatiBeniServizi->DatiRiepilogo, $FactureFournisseur);
					}
				}


				// registra i pagamenti schedulati se abilitato accountingscheduler
                if (!empty($conf->accountingscheduler->enabled)) {
					if (!empty($fattura->DatiPagamento->DettaglioPagamento[1])) {
						foreach ($fattura->DatiPagamento->DettaglioPagamento as $pagamento) {
							// Creation of payment line
							$paiement 				= new PaiementFourn($this->db);
							$paiement->datepaye     = date_create($pagamento->DataScadenzaPagamento)->getTimestamp();
							$paiement->amounts      = [$FactureFournisseur->id => price((float)$pagamento->ImportoPagamento)]; // Array with all payments dispatching with invoice id
							// $paiement->multicurrency_amounts = $multicurrency_amounts; // Array with all payments dispatching
							$paiement->paiementid   = 0;

							if (!$error) {
								// Create payment and update this->multicurrency_amounts if this->amounts filled or
								// this->amounts if this->multicurrency_amounts filled.
								$paiement_id = $paiement->create($user, 0); // This include closing invoices and regenerating documents
								if ($paiement_id < 0) {
									setEventMessages($paiement->error, $paiement->errors, 'errors');
									$error++;
								}else {
									$sql = strtr('insert into llx_installment_facturefourn select * from llx_paiementfourn_facturefourn pf where pf.fk_paiementfourn = :paiement_id',[
										'llx_' => MAIN_DB_PREFIX,
										':paiement_id' => $paiement_id
									]);
									$this->db->query($sql);
									$sql = strtr('delete from llx_paiementfourn_facturefourn where fk_paiementfourn = :paiement_id',[
										'llx_' => MAIN_DB_PREFIX,
										':paiement_id' => $paiement_id
									]);
									$this->db->query($sql);
								}
							}
						}
					}
				}

				// salva il file di origine
				if ($origin_xml_file && $invoiceFileName) {


					require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
					$ref = dol_sanitizeFileName($FactureFournisseur->ref);
					$upload_dir = $conf->fournisseur->facture->dir_output.'/'.get_exdir($FactureFournisseur->id, 2, 0, 0, $FactureFournisseur, 'invoice_supplier') . $ref;

					if (!file_exists($upload_dir)) {
						if (dol_mkdir($upload_dir) < 0) {
							setEventMessages($langs->transnoentities("ErrorCanNotCreateDir", $upload_dir),[], 'errors');
							return 0;
						}
					}

					$result =  dol_copy($origin_xml_file, $upload_dir . '/' . $invoiceFileName);
				}

				setEventMessage('La fattura '. $fattura->DatiGenerali->DatiGeneraliDocumento->Numero . ' è stata registrata', 'mesgs');
				return $FactureFournisseur;
			}
		}
	}

	function carica_fattura_attiva($fattura, $socid){
		global $user, $IvaSuCassaPrevidenziale, $RitenutaSuCassaPrevidenziale, $hookmanager;

		$hookmanager->initHooks(array('efattitaimport'));

		$numero = $this->db->escape($fattura->DatiGenerali->DatiGeneraliDocumento->Numero);
		$data = $fattura->DatiGenerali->DatiGeneraliDocumento->Data;
		$sql = "SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."facture";
		$sql.= " WHERE (ref = '$numero' OR ref_client = '$numero') and fk_soc = $socid AND datef = '$data'";
		$res = $this->db->query($sql);
		if($res->num_rows > 0) $known = true;

		if($known){
				setEventMessage('La fattura attiva '.$fattura->DatiGenerali->DatiGeneraliDocumento->Numero.' era già registrata', 'warnings');
				return 0;
		}else{
			$facture = new Facture($this->db);
			$facture->ref_client = $fattura->DatiGenerali->DatiGeneraliDocumento->Numero;
			$facture->socid = $socid;
			switch ($fattura->DatiGenerali->DatiGeneraliDocumento->TipoDocumento) {
				case 'TD04':
					$facture->type = 2;
					break;

				case 'TD02':
					$facture->type = 3;
					break;

				default:
					$facture->type = 0;
					break;
			}
			$facture->array_options['options_tipo_documento']			= $fattura->DatiGenerali->DatiGeneraliDocumento->TipoDocumento;
			$facture->array_options['options_condizioni_pagamento']		= $fattura->DatiPagamento->CondizioniPagamento;
			$facture->array_options['options_modalita_pagamento'] 		= $fattura->DatiPagamento->DettaglioPagamento->ModalitaPagamento;
			$facture->array_options['options_esigibilita_iva']			= $fattura->DatiBeniServizi->DatiRiepilogo->EsigibilitaIVA;
			$facture->array_options['options_riferimento_normativo']	= $fattura->DatiBeniServizi->DatiRiepilogo->RiferimentoNormativo;
			$facture->array_options['options_natura'] 					= $fattura->DatiBeniServizi->DatiRiepilogo->Natura;

			// $facture->array_options['bollo'] = ;

			$facture->date = strtotime($fattura->DatiGenerali->DatiGeneraliDocumento->Data);
			$facture->multicurrency_code = $fattura->DatiGenerali->DatiGeneraliDocumento->Divisa;

			$facture->fetch_thirdparty(); // Serve per il calcolo delle tasse
			$facture->author = $user->id;
			// workaround: in caso di valore nullo dolibarr mostrerebbe un link alla prima fattura nel database
				// $facture->fk_facture_source = -1;
			if ($fattura->DatiPagamento->DettaglioPagamento->DataScadenzaPagamento) {
				$facture->date_lim_reglement = strtotime($fattura->DatiPagamento->DettaglioPagamento->DataScadenzaPagamento);
				$forceduedate = $facture->date_lim_reglement;
			}
			$id = $facture->create($user, 0, $forceduedate);
			if($id > 0){

			// imposta iva e ritenuta sulla cassa previdenziale

				// questo controllo non è sufficiente
				$RitenutaSuCassaPrevidenziale = $fattura->DatiGenerali->DatiGeneraliDocumento->DatiCassaPrevidenziale->Ritenuta ? 1 : 0;


				$IvaSuCassaPrevidenziale = $fattura->DatiGenerali->DatiGeneraliDocumento->DatiCassaPrevidenziale->AliquotaIVA > 0 ? 1 : 0;

				if($fattura->DatiBeniServizi->DettaglioLinee[0]){

					// cerca se c'è almeno una linea con indicazione della Ritenuta
					foreach($fattura->DatiBeniServizi->DettaglioLinee as $DettaglioLinee){
						if($DettaglioLinee->Ritenuta){
							$ritenutaPerLinea = 1;
							break;
						}
					}

					if($RitenutaSuCassaPrevidenziale == 0){

					// ricontrolla se non sia applicata la ritenuta sulla cassa anche se non dichiarata
						foreach($fattura->DatiBeniServizi->DettaglioLinee as $DettaglioLinee)
						{
							if(!$ritenutaPerLinea || $DettaglioLinee->Ritenuta){
								$totRitenutaLinee += ($DettaglioLinee->PrezzoTotale * $fattura->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta->AliquotaRitenuta / 100);
							}
						}
						if($fattura->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta->ImportoRitenuta != nformat($totRitenutaLinee))
						{
							$RitenutaSuCassaPrevidenziale = 1;
						}
					}


					foreach($fattura->DatiBeniServizi->DettaglioLinee as $DettaglioLinee){

						// se la ritenuta non è indicata su nessuna linea è applicata su tutte
						if(!$ritenutaPerLinea){
							$DettaglioLinee->Ritenuta = 'SI';
						}

						$this->carica_linea_fattura_attiva($DettaglioLinee,$socid,$facture,$fattura->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta->AliquotaRitenuta,$fattura->DatiGenerali->DatiGeneraliDocumento->DatiCassaPrevidenziale);
					}
				}else{ // forse questo blocco non serve
                    if($fattura->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta->ImportoRitenuta != ($fattura->DatiBeniServizi->DettaglioLinee->PrezzoTotale * $fattura->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta->AliquotaRitenuta / 100)){
                        $RitenutaSuCassaPrevidenziale = 1;
                    }
					$this->carica_linea_fattura_attiva($fattura->DatiBeniServizi->DettaglioLinee,$socid,$facture,$fattura->DatiGenerali->DatiGeneraliDocumento->DatiRitenuta->AliquotaRitenuta,$fattura->DatiGenerali->DatiGeneraliDocumento->DatiCassaPrevidenziale);
				}
				/* Arrotondamenti */
				if($fattura->DatiBeniServizi->DatiRiepilogo[0]){
					foreach($fattura->DatiBeniServizi->DatiRiepilogo as $DatiRiepilogo){
						$this->applica_arrotondamento($DatiRiepilogo, $facture);
					}
				}else{
					$this->applica_arrotondamento($fattura->DatiBeniServizi->DatiRiepilogo, $FactureFournisseur);
				}
				setEventMessage('La fattura attiva'. $fattura->DatiGenerali->DatiGeneraliDocumento->Numero . ' è stata registrata', 'mesgs');
				return 1;
			}
		}
	}

	// Arrotondamento
	function applica_arrotondamento($DatiRiepilogo, $FactureFournisseur){
		if($DatiRiepilogo->Arrotondamento > 0){
			// L'arrotondamento viene gestito con l'aggiunta una linea di fattura
			$result = $FactureFournisseur->addline(
				'Arrotondamento',
				$DatiRiepilogo->Arrotondamento,
				$DatiRiepilogo->AliquotaIVA,
				0,
				0,
				1
			);
		}
	}

/*
*  carica linea per la fattura attiva o passiva
*/
	function carica_linea_fattura_attiva($linea, $socid, $facture, $ritenuta, $cap){
        global $negativeLine;

        if($linea->CodiceArticolo){
			foreach ($linea->CodiceArticolo as $CodiceArticolo) {
				$sql = strtr("select p.rowid, p.fk_product_type
					from llx_product p
					where p.barcode = :bcode
					and p.fk_barcode_type IN (select rowid from llx_c_barcode_type bt where instr(':btype', bt.code) > 0  )",[
						'llx_' => MAIN_DB_PREFIX,
						':btype' => $CodiceArticolo->CodiceTipo,
						':bcode' => $CodiceArticolo->CodiceValore
					]);
				$resql = $this->db->query($sql);
				if ($resql->num_rows) {
					$product = $this->db->fetch_object($res);
				}
			}
        }


		if(!empty($linea->ScontoMaggiorazione)){
			// dolibarr gestisce sconti solo in percentuale quinti lo convertiamo
			if(empty($linea->ScontoMaggiorazione->Percentuale))
				$linea->ScontoMaggiorazione->Percentuale = floatval($linea->ScontoMaggiorazione->Importo) * 100 / floatval($linea->PrezzoUnitario);
			if($linea->ScontoMaggiorazione->Tipo=='MG')
                $linea->ScontoMaggiorazione->Percentuale = - $linea->ScontoMaggiorazione->Percentuale;
		}
		// mettiamo ritenuta e cassa previdenziale a tutte le linee che hanno il valore Ritenuta = SI
		$localtax1_tx = $linea->Ritenuta == 'SI' ? - abs(floatval($ritenuta)) : null;
		$localtax2_tx = floatval($cap->AlCassa);

		$vatLabel = getTaxLabel(floatval($linea->AliquotaIVA), $localtax1_tx, $localtax2_tx, 5);

		if($facture->type == $facture::TYPE_CREDIT_NOTE){
				$facture->type = $facture::TYPE_STANDARD;
				$linea->PrezzoUnitario *= -1;
				$wasCreditNote = true;
		}
		$idLine = $facture->addline(
		        $linea->Descrizione,
		        floatval($linea->PrezzoUnitario),
		        floatval($linea->Quantita) > 0 ? floatval($linea->Quantita) : 1,
		        (float) DOL_VERSION >= 6 ? $vatLabel : floatval($linea->AliquotaIVA),
		        $localtax1_tx,
		        $localtax2_tx,
		        $product->rowid,
		        $linea->ScontoMaggiorazione->Percentuale,
		        $linea->DataInizioPeriodo,
		        $linea->DataFinePeriodo,
		        0,
		        null,
		        '',
		        'HT',
		        0,
		        $type
		);
		if($wasCreditNote){
			$facture->type = $facture::TYPE_CREDIT_NOTE;
		}
	}

	function carica_linea($linea,$socid,$FactureFournisseur,$ritenuta,$cap, $price_base = 'HT'){
        global $negativeLine;

	// SKU
        if($linea->CodiceArticolo){
            foreach($linea->CodiceArticolo as $CodiceArticolo){
				if (strtoupper($CodiceArticolo->CodiceTipo) == 'SKU') {
					$ref_fourn = $CodiceArticolo->CodiceValore;
					break;
				}
            }
        }
		$sql = strtr("select p.rowid, p.fk_product_type from llx_product p
		left join llx_product_fournisseur_price pp on pp.fk_product=p.rowid
		where fk_soc=:socid and pp.ref_fourn = ':ref'",[
			'llx_'	=>	MAIN_DB_PREFIX,
			':socid' =>	$socid,
			':ref'	=>	$this->db->escape($ref_fourn)

		]);
        $res = $this->db->query($sql);
        $product = $this->db->fetch_object($res);
		$type = $product->type === 0 ? 0 : 1;

		if(!empty($linea->ScontoMaggiorazione)){
			// dolibarr gestisce sconti solo in percentuale quinti lo convertiamo
			if(empty($linea->ScontoMaggiorazione->Percentuale))
				$linea->ScontoMaggiorazione->Percentuale = floatval($linea->ScontoMaggiorazione->Importo) * 100 / floatval($linea->PrezzoUnitario);
			if($linea->ScontoMaggiorazione->Tipo=='MG')
                $linea->ScontoMaggiorazione->Percentuale = - $linea->ScontoMaggiorazione->Percentuale;
		}
		// mettiamo ritenuta e cassa previdenziale a tutte le linee che hanno il valore Ritenuta = SI
		$localtax1_tx = $linea->Ritenuta == 'SI' ? - abs(floatval($ritenuta)) : 0;
		$localtax2_tx = floatval($cap->AlCassa);

		$vatLabel = getTaxLabel(floatval($linea->AliquotaIVA), $localtax1_tx, $localtax2_tx, 5);

		if($FactureFournisseur->type == $FactureFournisseur::TYPE_CREDIT_NOTE){
				$FactureFournisseur->type = $FactureFournisseur::TYPE_STANDARD;
				$linea->PrezzoUnitario *= -1;
				$wasCreditNote = true;
		}
        $idLine = $FactureFournisseur->addline(
                $linea->Descrizione,
                floatval($linea->PrezzoUnitario),
                (float) DOL_VERSION >= 6 ? $vatLabel : floatval($linea->AliquotaIVA),
                $localtax1_tx,
                $localtax2_tx,
                floatval($linea->Quantita) > 0 ? floatval($linea->Quantita) : 1,
                $product->rowid,
                $linea->ScontoMaggiorazione->Percentuale,
                $linea->DataInizioPeriodo,
                $linea->DataFinePeriodo,
                0,
                null,
                $price_base,
				$type
        );
		if($wasCreditNote){
			$FactureFournisseur->type = $FactureFournisseur::TYPE_CREDIT_NOTE;
		}
	}
}
