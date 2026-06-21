<?php
/* Copyright (C) 2004-2014	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2008		Raphael Bertrand	<raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2014	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2012      	Christophe Battarel <christophe.battarel@altairis.fr>
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
 * or see http://www.gnu.org/
 */

/**
 *	\file       efattita/core/modules/efattita/doc/doc_efattita.modules.php
 *	\ingroup    facture
 *	\brief      File of class to generate customers invoices from crabe model
 */

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once(DOL_DOCUMENT_ROOT."/core/lib/product.lib.php");
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
dol_include_once('/efattita/lib/efattita.lib.php');


/**
 *	Class to manage fattura elettronica xml
 */
class doc_efattita
{
  var $db;
  var $name;
  var $description;
  var $type;


	var $emetteur;	// Objet societe qui emet


	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
		global $conf,$langs,$mysoc;

		$langs->load("main");
		$langs->load("bills");

		$this->db = $db;
		$this->name = "Fattura elettronica";
		$this->description = $langs->trans('Fattura elettronica');

		// Get source company
		$this->emetteur=$mysoc;
		if (empty($this->emetteur->country_code)) $this->emetteur->country_code=substr($langs->defaultlang,-2);    // By default, if was not defined

		$this->tva=array();
		$this->localtax1=array();
		$this->localtax2=array();
		$this->atleastoneratenotnull=0;
		$this->atleastonediscount=0;

	}


	/**
     *  Function to build pdf onto disk
     *
     *  @param		Object		$object				Object to generate
     *  @param		Translate	$outputlangs		Lang output object
     *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
     *  @param		int			$hidedetails		Do not show line details
     *  @param		int			$hidedesc			Do not show desc
     *  @param		int			$hideref			Do not show ref
     *  @return     int         	    			1=OK, 0=KO
	 */
	function write_file($object,$outputlangs,$srctemplatepath='',$hidedetails=0,$hidedesc=0,$hideref=0)
	{
		global $user,$langs,$conf,$mysoc,$db,$hookmanager;

        // Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
        $hookmanager->initHooks(array('efattitaXmlGeneration'));


        # recupera ordini e contratti collegati(origine)
        $object->fetchObjectLinked();


    	if (! is_object($outputlangs)) $outputlangs=$langs;

    	$outputlangs->load("main");
    	$outputlangs->load("dict");
    	$outputlangs->load("companies");
    	$outputlangs->load("bills");
    	$outputlangs->load("products");
        $outputlangs->load("other");




    	if ($conf->efattita->dir_output)
    	{
    		$object->fetch_thirdparty();
            $this->FormatoTrasmissione = $object->thirdparty->typent_code=='TE_ADMIN'?'FPA12':'FPR12';

    		$deja_regle = $object->getSommePaiement();
    		$amount_credit_notes_included = $object->getSumCreditNotesUsed();
    		$amount_deposits_included = $object->getSumDepositsUsed();

        # conto bancario
			if ($object->fk_account > 0 || $object->fk_bank > 0 || !empty($conf->global->FACTURE_RIB_NUMBER)) {
				$bankid = ($object->fk_account <= 0 ? $conf->global->FACTURE_RIB_NUMBER : $object->fk_account);
				if ($object->fk_bank > 0) $bankid = $object->fk_bank; // For backward compatibility when object->fk_account is forced with object->fk_bank
				$account = new Account($this->db);
				$account->fetch($bankid);
            }

            // Definition of $dir and $file
    		$objectref = dol_sanitizeFileName($object->ref);
    		$dir = $conf->facture->dir_output . "/" . $objectref;
            if (! file_exists($dir))
            {
                if (dol_mkdir($dir) < 0)
                {
                    $this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
                    return 0;
                }
            }
    		$filename = $dir . "/" . getPartitaIva( $mysoc->tva_intra, $mysoc->country_code, 1) .'_'.  strtoupper(base_convert($object->id,10,36)) . ".xml";
    		$file = fopen($filename,'w+');


    		if (file_exists($dir))
    		{
                $total_ttc = ($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;

            // linea bollo a carico del cliente
                if ($object->array_options['options_bollo'] == 2) {
                    $object->lines[] =  (object) [
                        'total_tva' => 0,
                        'tva_tx'    => 0,
                        'desc'      => 'Bollo',
                        'qty'       => 1,
                        'subprice'  => $object->revenuestamp,
                        'total_ht'  => $object->revenuestamp
                      ];

                }

            $nblignes = count($object->lines);

            # Calcolo imposte
                for ($i = 0; $i < $nblignes; $i++){
                // Collecte des totaux par valeur de tva dans $this->tva["taux"]=total_tva
                    $tvaligne           = $object->lines[$i]->total_tva;
                    $localtax1ligne     = $object->lines[$i]->total_localtax1;
                    $localtax2ligne     = $object->lines[$i]->total_localtax2;
                    $localtax1_rate     = $object->lines[$i]->localtax1_tx;
                    $localtax2_rate     = $object->lines[$i]->localtax2_tx;
                    $localtax1_type     = $object->lines[$i]->localtax1_type;
                    $localtax2_type     = $object->lines[$i]->localtax2_type;
                    // $localtax1_over=$object->lines[$i]->total_ht; // non serve
                    $localtax2_over     = $object->lines[$i]->total_ht;

                    if ($object->remise_percent) $tvaligne -=($tvaligne*$object->remise_percent)/100;
                    if ($object->remise_percent) $localtax1ligne -=($localtax1ligne*$object->remise_percent)/100;
                    if ($object->remise_percent) $localtax2ligne -=($localtax2ligne*$object->remise_percent)/100;

                    $vatrate=(string) $object->lines[$i]->tva_tx;

                // Retrieve type from database for backward compatibility with old records
                    if ((! isset($localtax1_type) || $localtax1_type=='' || ! isset($localtax2_type) || $localtax2_type=='') // if tax type not defined
                    && (! empty($localtax1_rate) || ! empty($localtax2_rate))) // and there is local tax
                    {
                        $localtaxtmp_array=getLocalTaxesFromRate($vatrate,0, $object->thirdparty, $mysoc);
                        $localtax1_type = $localtaxtmp_array[0];
                        $localtax2_type = $localtaxtmp_array[2];
                    }

                    // retrieve global local tax
                    if ($localtax1_type && $localtax1ligne != 0)
                        $localtax1 += $localtax1ligne;
                    if ($localtax2_type && $localtax2ligne != 0){
                        $localtax2 += $localtax2ligne;
                        $ImponibileCassa += $localtax2_over;
                    }
                    if($vatrate == 0){
                        if($object->lines[$i]->desc == 'Bollo'){
                            $natura = $conf->global->NaturaBollo;
                            $riferimentoNormativo = $natura == 'N1' ? 'art. 15 DPR 633/72' : 'artt. da 7 a 7-septies del DPR 633/72';
                        }else {
                            if ($conf->global->NATURA_PER_RIGA) {
                                $natura = $object->lines[$i]->array_options['options_natura'];
                                $riferimentoNormativo = $object->lines[$i]->array_options['options_riferimento_normativo'];
                            }else {
                                $natura = $object->array_options['options_natura'];
                                $riferimentoNormativo = $object->array_options['options_riferimento_normativo'];
                            }
                        }
                        $DatiRiepilogo[$vatrate][$natura]['RiferimentoNormativo'] = $riferimentoNormativo;
                    }else{
                        $natura = 0;
                    }

                    # somma DatiRiepilogo
                    $DatiRiepilogo[$vatrate][$natura]['ImponibileImporto'] += $object->lines[$i]->total_ht;

                    // questo sarebbe il modo di default di dolibarr di calcolare l'iva, linea per linea, mentre in italia va calcolato dopo aver sommato gli imponibili
                    // $DatiRiepilogo[$vatrate]['Imposta']             += $object->lines[$i]->total_tva;

                    if(!$AliquotaRitenuta && $localtax1_rate <> 0)
                        $AliquotaRitenuta=abs($localtax1_rate);
                } // fine calcolo imposte

            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>
            <?xml-stylesheet href="' . dol_buildpath('/efattita/xsl/fatturaordinaria_v1.2.1.xsl.php',true) . '" type="text/xsl" ?>
            <p:FatturaElettronica versione="'.$this->FormatoTrasmissione.'" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
                xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xsi:schemaLocation="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2 http://www.fatturapa.gov.it/export/fatturazione/sdi/fatturapa/v1.2/Schema_del_file_xml_FatturaPA_versione_1.2.xsd">
            <FatturaElettronicaHeader></FatturaElettronicaHeader>
            <FatturaElettronicaBody></FatturaElettronicaBody>
            </p:FatturaElettronica>');
            $_FatturaElettronicaHeader = $xml->FatturaElettronicaHeader;
                $_DatiTrasmissione = $_FatturaElettronicaHeader->addChild('DatiTrasmissione');
                    $_IdTrasmittente = $_DatiTrasmissione->addChild('IdTrasmittente');
                        $_IdTrasmittente->addChild('IdPaese',$mysoc->country_code);
                        $_IdTrasmittente->addChild('IdCodice',strtoupper($mysoc->idprof4));
                    $_DatiTrasmissione->addChild('ProgressivoInvio',time());
                    $_DatiTrasmissione->addChild('FormatoTrasmissione',$this->FormatoTrasmissione);
                    if($object->thirdparty->array_options['options_codice_destinatario']){
                        $_DatiTrasmissione->addChild('CodiceDestinatario', strtoupper($object->thirdparty->array_options['options_codice_destinatario']));
                        $_ContattiTrasmittente = $_DatiTrasmissione->addChild('ContattiTrasmittente');
                            $_Email = $_ContattiTrasmittente->addChild('Email',$mysoc->email);
                    }else{
                        $_DatiTrasmissione->addChild('CodiceDestinatario', '0000000');
                        $_ContattiTrasmittente = $_DatiTrasmissione->addChild('ContattiTrasmittente');
                            $_Email = $_ContattiTrasmittente->addChild('Email',$mysoc->email);
                        $_DatiTrasmissione->addChild('PECDestinatario', $object->thirdparty->array_options['options_pec']);
                    }
                $_CedentePrestatore = $xml->FatturaElettronicaHeader->addChild('CedentePrestatore');

                if(in_array($object->array_options['options_tipo_documento'],array('TD16','TD17','TD18','TD19','TD20'))){
            // autofattura
                    setEventMessage('Ricordiamo che in caso di autofattura per società terza, il regime fiscale va impostato in accordo alla società terza','warnings');

                    $_DatiAnagrafici = $_CedentePrestatore->addChild('DatiAnagrafici');
                        $_IdFiscaleIVA = $_DatiAnagrafici->addChild('IdFiscaleIVA');
                            $_IdPaese = $_IdFiscaleIVA->addChild('IdPaese',$object->thirdparty->country_code);
                            $_IdFiscaleIVA->addChild('IdCodice', getPartitaIva($object->thirdparty->tva_intra, $_IdPaese));
                        $_DatiAnagrafici->addChild('CodiceFiscale', strtoupper($object->thirdparty->idprof4));
                        $_Anagrafica = $_DatiAnagrafici->addChild('Anagrafica');
                        if($object->thirdparty->typent_code == 'TE_PRIVATE'){
                            $NomeCognomeCommittente = $this->deduciNomeCognome($object->thirdparty->name,strtoupper($object->thirdparty->idprof4));
                            $_Nome = $_Anagrafica->addChild('Nome', efattita_transliterate($NomeCognomeCommittente[0]));
                            $_Cognome = $_Anagrafica->addChild('Cognome', efattita_transliterate($NomeCognomeCommittente[1]));
                        }else{
                            $_Anagrafica->addChild('Denominazione', efattita_transliterate($object->thirdparty->name));
                        }
                        $_DatiAnagrafici->addChild('RegimeFiscale',$conf->global->RegimeFiscale);
                    $_Sede = $_CedentePrestatore->addChild('Sede');
                        $_Sede->addChild('Indirizzo', $object->thirdparty->address);
                        $_Sede->addChild('CAP', $object->thirdparty->zip);
                        $_Sede->addChild('Comune', $object->thirdparty->town);
                        $_Sede->addChild('Provincia', $object->thirdparty->state_code);
                        $_Sede->addChild('Nazione', $object->thirdparty->country_code);
            //
                }else{
                    $_DatiAnagrafici = $_CedentePrestatore->addChild('DatiAnagrafici');
                        $_IdFiscaleIVA = $_DatiAnagrafici->addChild('IdFiscaleIVA');
                            $_IdPaese = $_IdFiscaleIVA->addChild('IdPaese',$mysoc->country_code);
                            $_IdFiscaleIVA->addChild('IdCodice', getPartitaIva( $mysoc->tva_intra, $_IdPaese));
                        $_DatiAnagrafici->addChild('CodiceFiscale', strtoupper($mysoc->idprof4));
                        $_Anagrafica = $_DatiAnagrafici->addChild('Anagrafica');
                            $_Anagrafica->addChild('Denominazione', efattita_transliterate($mysoc->name));
                        $_DatiAnagrafici->addChild('RegimeFiscale',$conf->global->RegimeFiscale);
                    $_Sede = $_CedentePrestatore->addChild('Sede');
                        $_Sede->addChild('Indirizzo',$mysoc->address);
                        $_Sede->addChild('CAP',$mysoc->zip);
                        $_Sede->addChild('Comune',$mysoc->town);
                        $_Sede->addChild('Provincia',fetchStateCode($mysoc->state_id));
                        $_Sede->addChild('Nazione',$mysoc->country_code);
                }
                $_CedentePrestatore->addChild('RiferimentoAmministrazione', $object->array_options['options_RiferimentoAmministrazione']);

                if(in_array($object->array_options['options_tipo_documento'],array('TD16','TD17','TD18','TD19','TD20','TD26'))){
            // autofattura
                    $_CessionarioCommittente = $xml->FatturaElettronicaHeader->addChild('CessionarioCommittente');
                        $_DatiAnagrafici = $_CessionarioCommittente->addChild('DatiAnagrafici');
                            $_IdFiscaleIVA = $_DatiAnagrafici->addChild('IdFiscaleIVA');
                                $_IdPaese = $_IdFiscaleIVA->addChild('IdPaese',$mysoc->country_code);
                                $_IdCodice = $_IdFiscaleIVA->addChild('IdCodice', getPartitaIva( $mysoc->tva_intra, $_IdPaese));
                            $_DatiAnagrafici->addChild('CodiceFiscale', strtoupper($mysoc->idprof4));
                            $_Anagrafica = $_DatiAnagrafici->addChild('Anagrafica');
                                $_Anagrafica->addChild('Denominazione', efattita_transliterate($mysoc->name));
                    $_Sede = $_CessionarioCommittente->addChild('Sede');
                        $_Sede->addChild('Indirizzo', $mysoc->address);
                        $_Sede->addChild('CAP', $mysoc->zip);
                        $_Sede->addChild('Comune', $mysoc->town);
                        $_Sede->addChild('Provincia', fetchStateCode($mysoc->state_id));
                        $_Sede->addChild('Nazione', $mysoc->country_code);
            //
                }else{
                $_CessionarioCommittente = $xml->FatturaElettronicaHeader->addChild('CessionarioCommittente');
                    $_DatiAnagrafici = $_CessionarioCommittente->addChild('DatiAnagrafici');
                    if($object->thirdparty->tva_intra){
                        $_IdFiscaleIVA = $_DatiAnagrafici->addChild('IdFiscaleIVA');
                        $_IdPaese = $_IdFiscaleIVA->addChild('IdPaese',$object->thirdparty->country_code);
                        $_IdCodice = $_IdFiscaleIVA->addChild('IdCodice', getPartitaIva($object->thirdparty->tva_intra, $_IdPaese));
                    }
                        $_DatiAnagrafici->addChild('CodiceFiscale', strtoupper($object->thirdparty->idprof4));
                        $_Anagrafica = $_DatiAnagrafici->addChild('Anagrafica');
                        if($object->thirdparty->typent_code == 'TE_PRIVATE'){
                            $NomeCognomeCommittente = $this->deduciNomeCognome($object->thirdparty->name,strtoupper($object->thirdparty->idprof4));
                            $_Nome = $_Anagrafica->addChild('Nome', efattita_transliterate($NomeCognomeCommittente[0]));
                            $_Cognome = $_Anagrafica->addChild('Cognome', efattita_transliterate($NomeCognomeCommittente[1]));
                        }else{
                            $_Anagrafica->addChild('Denominazione', efattita_transliterate($object->thirdparty->name));
                        }
                $_Sede = $_CessionarioCommittente->addChild('Sede');
                    $_Sede->addChild('Indirizzo', $object->thirdparty->address);
                    $_Sede->addChild('CAP', $object->thirdparty->zip);
                    $_Sede->addChild('Comune', $object->thirdparty->town);
                    $_Sede->addChild('Provincia', $object->thirdparty->state_code);
                    $_Sede->addChild('Nazione', $object->thirdparty->country_code);
                }

            // moltiplicatore per invertire il segno in caso di nota di credito
            $positivizer = $object->array_options['options_tipo_documento'] == 'TD04' ? -1 : 1;

            $_FatturaElettronicaBody = $xml->FatturaElettronicaBody;
                $_DatiGenerali = $_FatturaElettronicaBody->addChild('DatiGenerali');
                    $_DatiGeneraliDocumento = $_DatiGenerali->addChild('DatiGeneraliDocumento');
                        $_TipoDocumento = $_DatiGeneraliDocumento->addChild('TipoDocumento', $object->array_options['options_tipo_documento']);
                        $currency = ((float) DOL_VERSION >= 4) ? $object->multicurrency_code : $conf->currency;
                        $_Divisa = $_DatiGeneraliDocumento->addChild('Divisa', $currency);
                        $_Data = $_DatiGeneraliDocumento->addChild('Data', dol_print_date($object->date,'%Y-%m-%d'));
                        $_Numero = $_DatiGeneraliDocumento->addChild('Numero', $object->ref);
                        if($object->total_localtax1 <> 0){
                            $_DatiRitenuta = $_DatiGeneraliDocumento->addChild('DatiRitenuta');
                                $_DatiRitenuta->addChild('TipoRitenuta', $conf->global->TipoRitenuta);
                                $_DatiRitenuta->addChild('ImportoRitenuta', nformat($localtax1));
                                $_DatiRitenuta->addChild('AliquotaRitenuta', nformat($AliquotaRitenuta));
                                $_DatiRitenuta->addChild('CausalePagamento', $conf->global->CausalePagamento);
                        }
                        if($object->array_options['options_bollo'] > 0){
                            $_DatiBollo = $_DatiGeneraliDocumento->addChild('DatiBollo');
                                $_DatiBollo->addChild('BolloVirtuale', 'SI');
                                $_DatiBollo->addChild('ImportoBollo', nformat($object->revenuestamp));
                        }
                    # DatiCassaPrevidenziale
                        if($localtax2 <> 0){
                            $_DatiCassaPrevidenziale = $_DatiGeneraliDocumento->addChild('DatiCassaPrevidenziale');
                                $_DatiCassaPrevidenziale->addChild('TipoCassa', $conf->global->TipoCassa);
                                $_DatiCassaPrevidenziale->addChild('AlCassa', nformat($localtax2_rate));
                                $_ImportoContributoCassa = $_DatiCassaPrevidenziale->addChild('ImportoContributoCassa', nformat($localtax2));
                                $_DatiCassaPrevidenziale->addChild('ImponibileCassa', nformat($ImponibileCassa));
                                if($conf->global->IvaSuCassaPrevidenziale){
                                    $_AliquotaIVA = $_DatiCassaPrevidenziale->addChild('AliquotaIVA', nformat($vatrate));
                                    $DatiRiepilogo[$vatrate][]['ImponibileImporto']   += (float) $_ImportoContributoCassa;
                                }else{
                                    $_AliquotaIVA = $_DatiCassaPrevidenziale->addChild('AliquotaIVA', '0.00');
                                    $DatiRiepilogo['0.00'][$conf->global->NaturaCassa]['ImponibileImporto']   += (float) $_ImportoContributoCassa;
                                }
                                if($conf->global->RitenutaSuCassaPrevidenziale){
                                    $_Ritenuta = $_DatiCassaPrevidenziale->addChild('Ritenuta', 'SI');
                                }
                                if(!$conf->global->IvaSuCassaPrevidenziale){
                                    $_Natura = $_DatiCassaPrevidenziale->addChild('Natura', $conf->global->NaturaCassa);
                                }

                        }


                if(!empty($object->linkedObjects['commande']))
                foreach($object->linkedObjects['commande'] as $commande){
                    $_DatiOrdineAcquisto = $_DatiGenerali->addChild('DatiOrdineAcquisto');
                        if($commande->ref_client){
                            $ref = $commande->ref_client;
                            if(strlen($ref) > 20){
                                $ref = str_replace(' ','', $ref);
                            }
                            if(strlen($ref) > 20){
                                $ref = str_replace('www.','', $ref);
                            }
                            $ref_number = preg_replace("/\D/", '', $ref);
                            if(strlen($ref) > 20){
                                $result = preg_split('/(?=\.[^.]+$)/', $ref);
                                $ref = $result[0] . '#'. $ref_number;
                            }
                            if(strlen($ref) > 20){
                                $ref = $ref_number;
                            }
                        }
                        $_IdDocumento = $_DatiOrdineAcquisto->addChild('IdDocumento', empty($conf->global->EFATTITA_INT_REF_COMMANDE) && $ref ? $ref : $commande->ref);
                        $_DatiOrdineAcquisto->addChild('Data', dol_print_date($commande->date_commande,'%Y-%m-%d'));
                        $_DatiOrdineAcquisto->addChild('CodiceCUP', $commande->array_options['options_codice_cup']);
                        $_DatiOrdineAcquisto->addChild('CodiceCIG', $commande->array_options['options_codice_cig']);
                        $_DatiOrdineAcquisto->addChild('CodiceCommessaConvenzione', $commande->array_options['options_codice_commessa_convenzione']);
                }
                if(!empty($object->linkedObjects['contrat'])){
                    foreach($object->linkedObjects['contrat'] as $contrat){
                        $_DatiContratto = $_DatiGenerali->addChild('DatiContratto');
                            $_IdDocumento = $_DatiContratto->addChild('IdDocumento', $contrat->ref_customer?$contrat->ref_customer:$contrat->ref);
                            $_Data = $_DatiContratto->addChild('Data', dol_print_date($contrat->date_contrat,'%Y-%m-%d'));
                            $_CodiceCUP = $_DatiContratto->addChild('CodiceCUP', $contrat->array_options['options_codice_cup']);
                            $_CodiceCIG = $_DatiContratto->addChild('CodiceCIG', $contrat->array_options['options_codice_cig']);
                    }
                }
                // autofattura nella maggio parte dei casi
                if(!empty($object->linkedObjects['invoice_supplier']))
                    foreach($object->linkedObjects['invoice_supplier'] as $invoice_supplier){
                        $_DatiContratto = $_DatiGenerali->addChild('DatiFattureCollegate');
                            $_IdDocumento = $_DatiContratto->addChild('IdDocumento', $invoice_supplier->ref_supplier?$invoice_supplier->ref_supplier:$invoice_supplier->ref);
                            $_Data = $_DatiContratto->addChild('Data', dol_print_date($invoice_supplier->datec,'%Y-%m-%d'));
                    }

                if($object->fk_facture_source){
                    $facture_source = new Facture($db);
                    $facture_source->fetch($object->fk_facture_source);
                    $_DatiFattureCollegate = $_DatiGenerali->addChild('DatiFattureCollegate');
                    $_IdDocumento = $_DatiFattureCollegate->addChild('IdDocumento', $facture_source->ref);
                }
                if(!empty($object->linkedObjects['shipping'])){
                    foreach($object->linkedObjects['shipping'] as $shipping){
                        $_DatiDDT = $_DatiGenerali->addChild('DatiDDT');
                            $_NumeroDDT = $_DatiDDT->addChild('NumeroDDT', $shipping->ref);
                            $_DataDDT = $_DatiDDT->addChild('DataDDT', dol_print_date($shipping->array_options['options_ddti_data_documento']?$shipping->array_options['options_ddti_data_documento']:$shipping->date_delivery,'%Y-%m-%d'));
                    }
                }
                    // $_DatiTrasporto = $_DatiGeneraliDocumento->addChild('DatiTrasporto');
                    //     $_DatiAnagraficiVettore = $_DatiTrasporto->addChild('DatiAnagraficiVettore');
                    //         $_IdFiscaleIVA = $_DatiAnagraficiVettore->addChild('IdFiscaleIVA');
                    //         $_IdPaese = $_DatiAnagraficiVettore->addChild('IdPaese');
                    //         $_IdCodice = $_DatiAnagraficiVettore->addChild('IdCodice');
                    //         $_Anagrafica = $_DatiAnagraficiVettore->addChild('Anagrafica');
                    //             $_Anagrafica->addChild('Denominazione');
                    //     $_DataOraConsegna = $_DatiTrasporto->addChild('DataOraConsegna');



                // linee di fattura
                $_DatiBeniServizi = $_FatturaElettronicaBody->addChild('DatiBeniServizi');
                for ($i = 0, $l = 1; $i < $nblignes; $i++, $l++){
                    // Lo sconto o maggiorazione globale non viene spostato in ScontoMaggiorazione perchè perderebbe la Descrizione. Rimane comunque quello riferito alla linea singola
                    // if($object->lines[$i]->fk_remise_except)
                    //     continue;

                    $product = new Product($db);
                    if ($object->lines[$i]->fk_product) {
                        $product->fetch($object->lines[$i]->fk_product);
                    }
                    // workaround per dolibarr che usa 0 come valore di default per le select dei campi extra
                    foreach(array('options_condizioni_pagamento', 'options_modalita_pagamento', 'options_esigibilita_iva', 'options_tipo_cessione_prestazione') as $field){
                        if(empty($object->array_options[$field])){
                            $object->array_options[$field] = null;
                        }
                    }

                    $_DettaglioLinee = $_DatiBeniServizi->addChild('DettaglioLinee');
                        $_NumeroLinea = $_DettaglioLinee->addChild('NumeroLinea', $l);
                        if($object->lines[$i]->array_options['options_tipo_cessione_prestazione']){
                            $_TipoCessionePrestazione = $_DettaglioLinee->addChild('TipoCessionePrestazione', $object->lines[$i]->array_options['options_tipo_cessione_prestazione']);
                        }

                        $barcode = efattita_get_barcode($object->lines[$i]->fk_product);
                        if(!empty($barcode)){
                            if(substr($barcode->libelle, 0, 3) == 'EAN'){
                                $barcode->libelle = 'EAN';
                            }
                            $_CodiceArticolo = $_DettaglioLinee->addChild('CodiceArticolo');
                            $_CodiceTipo = $_CodiceArticolo->addChild('CodiceTipo', $barcode->libelle);
                            $_CodiceValore = $_CodiceArticolo->addChild('CodiceValore', $barcode->barcode);
                        }
                        if(!empty($product->array_options['options_codice_articolo']) && !empty($product->array_options['options_codice_articolo_tipo'])){
                            $_CodiceArticolo = $_DettaglioLinee->addChild('CodiceArticolo');
                                $_CodiceTipo = $_CodiceArticolo->addChild('CodiceTipo', $product->array_options['options_codice_articolo_tipo']);
                                $_CodiceValore = $_CodiceArticolo->addChild('CodiceValore', $product->array_options['options_codice_articolo']);
                        }

                        // riferimento cliente
                        $ref_customer = '';
                        if($object->lines[$i]->fk_product){
                            $sql = strtr('select ref_customer from llx_product_customer_price
                            where fk_product = :idprod
                            and fk_soc = :idsoc', [
                                'llx_'	=>	MAIN_DB_PREFIX,
                                ':idprod'=>	$object->lines[$i]->fk_product,
                                ':idsoc'	=>	$object->socid
                            ]);
                            $resql = $db->query($sql);
                            $row = $db->fetch_object($resql);
                            if($row->ref_customer){
                                $ref_customer .= ' (Rif. cliente ' . $row->ref_customer . ')';
                            }
                        }


                        $descrizione = implode(' - ',array_filter(array($object->lines[$i]->ref . $ref_customer, $object->lines[$i]->product_label, $object->lines[$i]->desc)));

                        // descrizioni sconti tratto da objectline_view.tpl.php
                            if ($descrizione == '(CREDIT_NOTE)' && $object->lines[$i]->fk_remise_except > 0)
                            {
                                $discount = new DiscountAbsolute($this->db);
                                $discount->fetch($object->lines[$i]->fk_remise_except);
                                $descrizione = $langs->transnoentities("DiscountFromCreditNote", $discount->getNomUrl(0));
                            }
                            elseif ($descrizione == '(DEPOSIT)' && $object->lines[$i]->fk_remise_except > 0)
                            {
                                $discount = new DiscountAbsolute($this->db);
                                $discount->fetch($object->lines[$i]->fk_remise_except);
                                $descrizione = $langs->transnoentities("DiscountFromDeposit", $discount->getNomUrl(0));
                                // Add date of deposit
                                if (!empty($conf->global->INVOICE_ADD_DEPOSIT_DATE))
                                print ' ('.dol_print_date($discount->datec).')';
                            }
                            elseif ($descrizione == '(EXCESS RECEIVED)' && $objp->fk_remise_except > 0)
                            {
                                $discount = new DiscountAbsolute($this->db);
                                $discount->fetch($object->lines[$i]->fk_remise_except);
                                $descrizione = $langs->transnoentities("DiscountFromExcessReceived", $discount->getNomUrl(0));
                            }
                            elseif ($descrizione == '(EXCESS PAID)' && $objp->fk_remise_except > 0)
                            {
                                $discount = new DiscountAbsolute($this->db);
                                $discount->fetch($object->lines[$i]->fk_remise_except);
                                $descrizione = $langs->transnoentities("DiscountFromExcessPaid", $discount->getNomUrl(0));
                            }


                        $descrizione = strip_tags($descrizione);
                        $descrizione = efattita_transliterate($descrizione);
                        $_Descrizione = $_DettaglioLinee->addChild('Descrizione', $descrizione);

                        $_Quantita = $_DettaglioLinee->addChild('Quantita', nformat($object->lines[$i]->qty,8));

                        // unita di misura
                        if($conf->global->PRODUCT_USE_UNITS){
                            if($product->getLabelOfUnit()){
                                $_UnitaMisura = $_DettaglioLinee->addChild('UnitaMisura',   efattita_transliterate($langs->trans($product->getLabelOfUnit())));
                            }
                        }else{
                            $measures = ['weight', 'length',  'width', 'height',  'surface',  'volume'];
                            foreach($measures as $measure){
                                if($product->{$measure} == 1 && $object->lines[$i]->qty>0){
                                    $_UnitaMisura = $_DettaglioLinee->addChild('UnitaMisura',  measuring_units_string($product->{$measure . '_units'}, $measure, 0, 1));
                                    break;
                                }
                            }
                        }

                        $_PrezzoUnitario = $_DettaglioLinee->addChild('PrezzoUnitario', nformat($positivizer * $object->lines[$i]->subprice,8));
                        if($object->lines[$i]->remise_percent){
                            $_ScontoMaggiorazione = $_DettaglioLinee->addChild('ScontoMaggiorazione');
                                $_Tipo = $_ScontoMaggiorazione->addChild('Tipo', 'SC');
                                $_Percentuale = $_ScontoMaggiorazione->addChild('Percentuale', nformat($object->lines[$i]->remise_percent));
                        }
                        $_PrezzoTotale = $_DettaglioLinee->addChild('PrezzoTotale', nformat($positivizer * $object->lines[$i]->total_ht,8));
                        $_AliquotaIVA = $_DettaglioLinee->addChild('AliquotaIVA', nformat($object->lines[$i]->tva_tx));
                        if($object->lines[$i]->localtax1_tx<>0)
                            $_Ritenuta = $_DettaglioLinee->addChild('Ritenuta', 'SI');
                        if($_AliquotaIVA == 0){
                            if($object->lines[$i]->desc == 'Bollo'){
                                $_Natura = $_DettaglioLinee->addChild('Natura', $conf->global->NaturaBollo);
                            }else {
                                if ($conf->global->NATURA_PER_RIGA) {
                                    $_Natura = $_DettaglioLinee->addChild('Natura', $object->lines[$i]->array_options['options_natura']);
                                }else {
                                    $_Natura = $_DettaglioLinee->addChild('Natura', $object->array_options['options_natura']);
                                }
                            }

                        }
                        if($object->fk_facture_source){
                            $_DettaglioLinee->addChild('RiferimentoAmministrazione', $facture_source->ref);
                        }
                        if( $object->array_options['options_protocollo_intento']){
                            $_AltriDatiGestionali = $_DettaglioLinee->addChild('AltriDatiGestionali');
                                $_AltriDatiGestionali->addChild('TipoDato', 'INTENTO');
                                $_AltriDatiGestionali->addChild('RiferimentoTesto', $object->array_options['options_protocollo_intento']);
                                $_AltriDatiGestionali->addChild('RiferimentoData',  dol_print_date($object->thirdparty->array_options['options_data_intento'],'%Y-%m-%d'));
                        }

                } // fine linee

                $importo_totale_documento = 0;

                foreach($DatiRiepilogo as $vatrate => $gruppoVatrate){
                    foreach($gruppoVatrate as $natura => $gruppoNatura){
                        $_DatiRiepilogo = $_DatiBeniServizi->addChild('DatiRiepilogo');
                        $_AliquotaIVA = $_DatiRiepilogo->addChild('AliquotaIVA', nformat($vatrate));
                        if($vatrate == 0){
                            $_Natura = $_DatiRiepilogo->addChild('Natura', $natura);
                        }
                        $_ImponibileImporto = $_DatiRiepilogo->addChild('ImponibileImporto', nformat($positivizer * $gruppoNatura['ImponibileImporto']));

                        // questo sarebbe il modo di default di dolibarr di calcolare l'iva, linea per linea, mentre in italia va calcolato dopo aver sommato gli imponibili
                        // $_Imposta = $_DatiRiepilogo->addChild('Imposta', nformat($positivizer * $DatiRiepilogo[$vatrate]['Imposta']));
                        $_Imposta = $_DatiRiepilogo->addChild('Imposta', nformat($positivizer * $gruppoNatura['ImponibileImporto'] * $vatrate/100));

                        $_EsigibilitaIVA = $_DatiRiepilogo->addChild('EsigibilitaIVA',$object->array_options['options_esigibilita_iva'] );
                        if($_AliquotaIVA == 0){
                            $_RiferimentoNormativo = $_DatiRiepilogo->addChild('RiferimentoNormativo', efattita_transliterate($gruppoNatura['RiferimentoNormativo']));
                        }

                        $importo_totale_documento +=  (float) $_ImponibileImporto;
                        $importo_totale_documento +=  (float) $_Imposta;
                    }

                }
                if (!empty($conf->accountingscheduler->enabled)) {
                    $sql = strtr('select p.* FROM llx_installment_facture pf left join llx_paiement p on p.rowid=pf.fk_paiement where pf.fk_facture = :id order by p.datep',[
                        'llx_' => MAIN_DB_PREFIX,
                        ':id' => $object->id,
                    ]);
                    $installments = $db->query($sql);
                }
                if($installments->num_rows > 0 || $object->array_options['options_condizioni_pagamento'] || $object->array_options['options_modalita_pagamento']){
                    $_DatiPagamento = $_FatturaElettronicaBody->addChild('DatiPagamento');
                        $_CondizioniPagamento = $_DatiPagamento->addChild('CondizioniPagamento', $object->array_options['options_condizioni_pagamento']);
                        if ($installments->num_rows > 0 && $_EsigibilitaIVA != 'S') {
                            while ($installment = $this->db->fetch_object($installments)) {
                                $_DettaglioPagamento = $_DatiPagamento->addChild('DettaglioPagamento');
                                    $_DettaglioPagamento->addChild('ModalitaPagamento', $object->array_options['options_modalita_pagamento']);
                                    $_DettaglioPagamento->addChild('DataScadenzaPagamento', dol_print_date($installment->datep,'%Y-%m-%d'));
                                    $_DettaglioPagamento->addChild('ImportoPagamento', nformat($installment->amount));
                                    $_DettaglioPagamento->addChild('IBAN', $account->iban);
                            }

                        }else{
                            $_DettaglioPagamento = $_DatiPagamento->addChild('DettaglioPagamento');
                                $_DettaglioPagamento->addChild('ModalitaPagamento', $object->array_options['options_modalita_pagamento']);
                                $_DettaglioPagamento->addChild('DataScadenzaPagamento', dol_print_date($object->date_lim_reglement,'%Y-%m-%d'));
                                $_DettaglioPagamento->addChild('ImportoPagamento', nformat($positivizer * ($object->total_ht + ($_EsigibilitaIVA == 'S' ? 0 : $object->total_tva) + $object->total_localtax1 + $object->total_localtax2 + ($object->array_options['options_bollo'] == 2 ? $object->revenuestamp : 0))));
                                $_DettaglioPagamento->addChild('IBAN', $account->iban);
                        }
                }

                // in ImportoTotaleDocumento non va tolta la ritenuta
                $_DatiGeneraliDocumento->addChild('ImportoTotaleDocumento', nformat($positivizer * ($importo_totale_documento + $object->total_localtax2 )));
                
                // causale
                if ($conf->global->CAUSALE_FATTURA) {
                    $_DatiGeneraliDocumento->addChild('Causale', $object->array_options['options_causale']);
                }

            // allegati

            // Query per ottenere i file allegati all'oggetto
            // $sql = strtr('SELECT
            //     f.*
            // FROM
            //     llx_element_element ee
            // LEFT JOIN llx_ecm_files f ON
            //     (
            //         f.src_object_type = ee.sourcetype AND f.src_object_id = ee.fk_source
            //     ) OR(
            //         f.src_object_type = \'facture\' AND f.src_object_id = \':facid\'
            //     )
            // LEFT JOIN llx_ecm_files_extrafields fe ON
            //     f.rowid = fe.fk_object
            // WHERE
            //     ee.fk_target = \':facid\' AND fe.efattita_attach = 1',
            //         [
            //             'llx_'	=>		MAIN_DB_PREFIX,
            //             ':facid' =>	GETPOST('facid', 'int')
            //         ]
            //     );


            $sql = strtr('SELECT f.*
                FROM llx_ecm_files f
                LEFT JOIN llx_element_element ee
                    ON f.src_object_type = ee.sourcetype
                    AND f.src_object_id = ee.fk_source
                LEFT JOIN llx_ecm_files_extrafields fe ON
                        f.rowid = fe.fk_object
                WHERE (f.src_object_type = \'facture\' AND f.src_object_id = \':facid\'
                OR ee.fk_target = \':facid\')
                AND fe.efattita_attach = 1',[
                        'llx_'	=>		MAIN_DB_PREFIX,
                        ':facid' =>	GETPOST('facid', 'int')
                ]
            );








                $this->db->query($sql);
                $result = $this->db->query($sql);

                if ($result->num_rows > 0) {
                    while ($row = $this->db->fetch_object($result)) {
                        $filename = $row->filename;
                        $filepath = DOL_DATA_ROOT . '/' . $row->filepath . '/' . $row->filename;

                        $ext = pathinfo($filepath, PATHINFO_EXTENSION);
                        if (strtolower($ext) === 'zip') {
                            $zip_filepath = $filepath;
                        }else {
                            // Creazione del file zip
                            $zip = new ZipArchive();
                            $zip_filepath = $filepath . 'zip';
                            $zip->open($zip_filepath, ZipArchive::CREATE);
                            $zip->addFile($filepath, $filename);
                            $zip->close();
                            $zip_is_temporary = true;
                        }


                        // Conversione del file zip in base64
                        $zip_base64 = base64_encode(file_get_contents($zip_filepath));

                        $_allegato = $_FatturaElettronicaBody->addChild('Allegati');
                            $_allegato->addChild('NomeAttachment', $filename . '.zip');
                            $_allegato->addChild('AlgoritmoCompressione', 'ZIP');
                            $_allegato->addChild('Attachment', $zip_base64);

                        // Eliminazione del file zip temporaneo
                        if($zip_is_temporary){
                            unlink($zip_filepath);
                        }
                    }
                }

                // permette l'eventuale modifica del xml prima della chiusura
                $parameters = array('xml' => $xml);
				$reshook = $hookmanager->executeHooks('writeXml', $parameters, $object);

                $dom = new DOMDocument("1.0");
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = true;
                $dom->loadXML($xml->asXML());

                // filtra nodi vuoti
                $xpath = new DOMXPath($dom);
                foreach ($xpath->query('//*[not(node())]') as $node) {
                        $node->parentNode->removeChild($node);
                }

                // controllo xml
                libxml_use_internal_errors(true);
                if(!$dom->schemaValidate(dol_buildpath('efattita/lib/xsd/Schema_VFPR12_29052020.xsd'))){
                    $errors = libxml_get_errors();
                    setEventMessage($object->ref . "Errori nella fattura elettronica:<ol>", 'errors');
                    foreach ($errors as  $error) {
                        setEventMessage('<li>'.$error->message.'</li>', 'errors');
                    }
                    setEventMessage('</ol>', 'errors');
                }

            	fwrite($file,$dom->saveXML());
            	fclose($file);

            	if (! empty($conf->global->MAIN_UMASK))
            	   @chmod($filename, octdec($conf->global->MAIN_UMASK));

        		return 1;   // Pas d'erreur
    		} else {
    			$this->error=$langs->trans("ErrorCanNotCreateDir",$dir);
    			return 0;
    		}
		}
		else
		{
			$this->error=$langs->trans("ErrorConstantNotDefined","FAC_OUTPUTDIR");
			return 0;
		}
		$this->error=$langs->trans("ErrorUnknown");
		return 0;   // Erreur par defaut
	}

    # non garantisce al 100% ma quasi
    function deduciNomeCognome($denominazione,$cf){
        $partiDenominazione = explode(' ',$denominazione);
        $partiDenominazione = array_reverse($partiDenominazione);
        $parti=array();
        for ($p=0; $p < count($partiDenominazione); $p++) {
            array_unshift($parti,$partiDenominazione[$p]);
            $string = implode(' ',$parti);
            $num_vocali = preg_match_all('/[AEIOU]/i',$string,$vocali);
            $num_consonanti = preg_match_all('/[BCDFGHJKLMNPQRSTVWZXYZ]/i',$string,$consonanti);
            if($num_consonanti>=3){
                $cf_cognome = $consonanti[0][0] . $consonanti[0][1] . $consonanti[0][2];
            }else {
                for($i = 0; $i < $num_consonanti; $i++)
                    $cf_cognome .= $consonanti[0][$i];
                $n = 3 - strlen($cf_cognome);
                for($i = 0; $i < $n; $i++)
                    $cf_cognome .= $vocali[0][$i];
                $n = 3 - strlen($cf_cognome);
                for($i = 0; $i < $n; $i++)
                    $cf_cognome .= 'X';
            }
            if(strtoupper($cf_cognome) == substr($cf,0,3)){
                $nomeCognome[1]=$string;
                for ($d = $p+1; $d <= count($partiDenominazione)-1; $d++)
                    $parti2[] = $partiDenominazione[$d];
                $nomeCognome[0] = implode(' ',array_reverse($parti2));
                return($nomeCognome);
            }else{
                if($num_consonanti>=4)
                    $cf_nome = $consonanti[0][0] . $consonanti[0][2] . $consonanti[0][3];
                else if($num_consonanti==3)
                    $cf_nome = $consonanti[0][0] . $consonanti[0][1] . $consonanti[0][2];
                else {
                    for($i = 0; $i < $num_consonanti; $i++)
                        $cf_nome = $cf_nome . $consonanti[0][$i];
                    $n = 3 - strlen($cf_nome);
                    for($i = 0; $i < $n; $i++)
                        $cf_nome = $cf_nome . $vocali[0][$i];
                    $n = 3 - strlen($cf_nome);
                    for($i = 0; $i < $n; $i++)
                        $cf_nome = $cf_nome . 'X';
                }
                if(strtoupper($cf_nome) == substr($cf,3,3)){
                    $nomeCognome[0]=$string;
                    for ($d = $p+1; $d <= count($partiDenominazione)-1; $d++)
                        $parti3[] = $partiDenominazione[$d];
                    $nomeCognome[1] = implode(' ',array_reverse($parti3));
                    return($nomeCognome);
                }
            }
        }
        setEventMessage('Denominazione o Cod. Fisc. erronei o mancanti. Necessari se il soggetto è privato', 'errors');
    }
}
