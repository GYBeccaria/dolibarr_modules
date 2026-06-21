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
 * Copyright (C) 2020      LINX srls              <info@linx.ws>
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
 *	\file       class/arubasdi.class.php
 *	\ingroup    facture
 *	\brief      File of class to manage invoices operations
 */
require_once DOL_DOCUMENT_ROOT.'/societe/class/client.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
require_once dol_buildpath('/efattita/class/efattita.class.php');


class ArubaSDI
{
    public $output;
    public $result;
    public $statiFatture = array();

    /**
	 * 	Constructor
	 *
	 * 	@param	DoliDB		$db			Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
        $this->statiFatture = array(
            0	=> 'Non inviata',
            1	=> 'Presa in carico',
            2	=> 'Errore elaborazione',
            3	=> 'Inviata',
            4	=> 'Scartata',
            5	=> 'Non consegnata',
            6	=> 'Recapito impossibile',
            7	=> 'Consegnata',
            8	=> 'Accettata',
            9	=> 'Rifiutata',
            10	=> 'Decorrenza termini'
        );
	}

    private function getStatusNumber($name){
        return array_search(strtolower($name), array_map('strtolower', $this->statiFatture));
    }

    // CRON scarica fatture fornitore
	public function loadSuppliersInvoices($entity = 0, $startTime = null, $endTime = null){
		global $db, $conf, $langs, $user, $mysoc;

        // permette di definire l'entity se si usa il modulo multicompany
            if($entity){
                $conf->entity = $entity;
                $conf->setValues($db);
            }

        // UTENTICAZIONE
			$token = self::getAuthToken();
            if(!$token){
                setEventMessages($langs->trans('UnableToObtainToken'), null, 'errors' );
                dol_syslog(__METHOD__ . ' UnableToObtainToken', LOG_ERR);
                return 0;
            }
    	// GET FATTURE
			$reponse_object = self::getInvoices($token, $startTime, $endTime);
            if(!empty($reponse_object->content)){
                foreach($reponse_object->content as $invoiceInfo){
                    foreach($invoiceInfo->invoices as $invoice){
                        $sql = "SELECT f.rowid";
                        $sql.= " FROM ".MAIN_DB_PREFIX."facture_fourn f left join ".MAIN_DB_PREFIX."societe s on s.rowid = f.fk_soc";
                        $sql.= " WHERE f.ref_supplier = '".$db->escape($invoice->number)."' AND s.tva_intra LIKE '%{$invoiceInfo->sender->vatCode}' AND f.datef = date('". $invoice->invoiceDate."')";
                        $res = $db->query($sql);
                        if($res->num_rows > 0) {
                            setEventMessage('Già registrata: '.$invoice->number, 'warnings');
                            break;
                        }else {
                            $invoicesFileNames[] = $invoiceInfo->filename;
                        }

                    }
                }
            }

            if(!empty($invoicesFileNames)){
                $n = self::loadByFileNames($token, $invoicesFileNames);
                $this->output = $n . ' ' . $langs->trans('invoicesDownloaded');
            }

		return 0;
	}
/*
* Load invoices from names array 
* Return # loaded
*/
    public static function loadByFileNames($token, $invoicesFileNames){
        global $conf, $db, $langs, $user;
        foreach($invoicesFileNames as $invoiceFileName){
            // TODO: filtrare con una query le fatture già importate o fitrando per socid ref_supplier o per nome fattura (che andrebbe quindi salvato durante l'importazione)
            // $sql = "select 1 from " . MAIN_DB_PREFIX . "facture_fourn_extrafields where arubasdi_filename = '{$invoiceFileName}'";
            // $res = $db->query($sql);
            // if($db->num_rows == 0){
                $invoiceData = self::getByFilename($token, $invoiceFileName); //questo è codificato in BASE64 e puo essere firmato o meno
                if(!$invoiceData){
                    setEventMessages("Fattura non ottenuta: " . $invoiceFileName, null, 'errors');
                    dol_syslog(__METHOD__ . ' Fattura non ottenuta: ' . $invoiceFileName, LOG_ERR);
                    continue;
                }

                $tmpFileP7m = stream_get_meta_data(tmpfile())['uri'];
                $fh = @fopen( $tmpFileP7m, 'w' );
                fwrite($fh, base64_decode($invoiceData->file));

                $fileInfo = pathinfo($invoiceFileName);
                
                if($fileInfo['extension'] == 'p7m') // è fimato, va estratto
                {

                    // Metodo 1: estrae previamente il certificato dallo stesso file per estrarre l'xml
                    // Metodo 2: estrae l'xml con i certificati scaricati dai siti delle autorità
                    
                    $xmlfatt = stream_get_meta_data(tmpfile())['uri'];
                    $output = stream_get_meta_data(tmpfile())['uri'];
                    $signer = stream_get_meta_data(tmpfile())['uri'];																							// metodo 1:

                    der2smime($tmpFileP7m, $xmlfatt);

        			// estrae certificato
        			$result = openssl_pkcs7_verify($xmlfatt, PKCS7_NOVERIFY | PKCS7_NOSIGS, $signer); 															// metodo 1:

        			// non funziona con alcune fatture
        			// $result = @openssl_pkcs7_verify($xmlfatt, PKCS7_NOVERIFY, '' ,array( __DIR__ .'/certs/CA.pem'), __DIR__ .'/certs/CA.pem', $output);		// metodo 2:
        			
        			if($result == 1){
        				// alternativa:
        				// estrae file con certificato estratto previamente
        				$result = openssl_pkcs7_verify($xmlfatt, PKCS7_NOVERIFY | PKCS7_NOSIGS, $signer, [], $signer, $output);									// metodo 1:
        				$xmlContent = file_get_contents($output);
        			}else {
        				// Alcuni file non sono compatibili, non funzionano neanche con openssl
        				$xmlContent = stripP7MData(file_get_contents($tmpFileP7m));
        				$xmlContent = sanitizeXML($xmlContent);
        			}
                }else{
                    $xmlContent = base64_decode($invoiceData->file);
                }

                if($xmlobject = simplexml_load_string($xmlContent)) {
        			$efatt = new ElectronicFacture($db);
        			// check se fattura attiva o passiva
        			if (is_fattura_attiva($xmlobject)) {
        				$socid = $efatt->createUpdateThirdParty($xmlobject, 0);
        				if($xmlobject->FatturaElettronicaBody[0]){
        					foreach ($xmlobject->FatturaElettronicaBody as $xmlobjectelm)
        					$efatt->carica_fattura_attiva($xmlobjectelm, $socid);
        				}else{
        					# una sola
        					$efatt->carica_fattura_attiva($xmlobject->FatturaElettronicaBody, $socid);
        				}
        			}else {
        				$socid = $efatt->createUpdateThirdParty($xmlobject, 1);
        				# più fatture
        				if($xmlobject->FatturaElettronicaBody[0]){
        					foreach ($xmlobject->FatturaElettronicaBody as $xmlobjectelm)
        					$FactureFournisseur = $efatt->carica_fattura($xmlobjectelm, $socid, $xmlobject->FatturaElettronicaHeader->DatiTrasmissione->FormatoTrasmissione, $tmpFileP7m, $invoiceFileName);
        				}
        			}
                    // link pdf fattura
                    if ($FactureFournisseur) {

                        require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
                        $linkObject = new Link($db);
                        $linkObject->entity = $conf->entity;
                        $linkObject->url = dol_buildpath('/arubasdi/fattura.php?filename='.  $invoiceFileName, 1 ) .'&format=pdf';
                        $linkObject->objecttype = 'invoice_supplier';
                        $linkObject->objectid = $FactureFournisseur->id;
                        $linkObject->label = str_replace(['.xml', '.p7m'],'', $invoiceFileName). '.pdf';
                        $res = $linkObject->create($user);
                        $langs->load('link');
                        if ($res > 0) {
                            setEventMessages($langs->trans("LinkComplete"), null, 'mesgs');
                        } else {
                            setEventMessages($langs->trans("ErrorFileNotLinked"), null, 'errors');
                        }
                    }
        		}else{
        			setEventMessages("Non è stato possibile elaborare il file", null, 'errors');
        		}
                $n++;
            // }
        }
        return $n;
    }

    // CRON scarica esiti
    public function controlloEsiti(){
        global $conf,$langs, $mysoc, $user;
        
        // https://fatturazioneelettronica.aruba.it/apidoc/docs.html#multicedenti-status

        // le fatture che ancora non hanno un esito positivo da meno di 15 giorni
        // order dà priorità a fatture appena inviate
        $sql = strtr('SELECT fk_object, fattura_generata
            FROM llx_facture_extrafields
            WHERE statoFattura in (1, 2, 3, 5, 7)
            AND (arubasdi_dataStato >= DATE_SUB(CURDATE(), INTERVAL 15 DAY) OR arubasdi_dataStato IS NULL)
            ORDER BY statoFattura, arubasdi_dataStato
            LIMIT 10',[
            'llx_'  => MAIN_DB_PREFIX,
            ]);

        $resql = $this->db->query($sql);
        $num = $this->db->num_rows($resql);

        $updates = 0;
		$r=0;

        while ($r < $num){
            $invio = $this->db->fetch_object($resql);

            // UTENTICAZIONE
            $token = self::getAuthToken();
            // GET FATTURE
            $url = empty($conf->global->AURUBASDI_DEMOMODE) ? $conf->global->ARUBASDI_WS : $conf->global->ARUBASDI_DEMOWS;
            $url .= '/services/invoice/out/getByFilename';
            $addheaders = array(
                'Accept: application/json',
                'Authorization: Bearer '. $token,
            );
            $data = array(
                'filename'     => $invio->fattura_generata,
            );
            $url .= '?'. http_build_query($data);
            // chiamata
            $res = getURLContent($url, 'GET', '' , 1, $addheaders);
            $response = json_decode($res['content']);
            if(!empty($response->invoices)){
                $status = $this->getStatusNumber($response->invoices[0]->status);
                $sql = strtr('UPDATE llx_facture_extrafields
                    SET statofattura = :status,
                    arubasdi_dataStato = CURDATE()
                    WHERE fk_object = :fk_object
                    AND (arubasdi_dataStato IS NULL OR statofattura != :status)',
                    [
                        ':status'   => $this->getStatusNumber($response->invoices[0]->status),
                        'llx_'      => MAIN_DB_PREFIX,
                        ':fk_object'=> $invio->fk_object,
                    ]);

                $res = $this->db->query($sql);
                $updates++;
            }
            $r++;
        }
        $this->output = $updates.' updates';
        return 0;
    }

    static function AdminPrepareHead()
    {
    	global $langs, $conf;

    	$langs->load("admin");

    	$h = 0;
    	$head = array();

    	$head[$h][0] = dol_buildpath("/arubasdi/admin/setup.php", 1);
    	$head[$h][1] = $langs->trans("Settings");
    	$head[$h][2] = 'settings';
        $h++;
        $head[$h][0] = dol_buildpath("/arubasdi/admin/notices.php", 1);
        $head[$h][1] = $langs->trans("Notices");
        $head[$h][2] = 'notices';
    	$h++;
    	$head[$h][0] = dol_buildpath("/arubasdi/admin/about.php", 1);
    	$head[$h][1] = $langs->trans("About");
    	$head[$h][2] = 'about';
    	$h++;

    	complete_head_from_modules($conf, $langs, $object, $head, $h, 'arubasdi');

    	return $head;
    }

    static function getAuthToken(){
 
        global $conf;
 
        $url = empty($conf->global->AURUBASDI_DEMOMODE) ? $conf->global->ARUBASDI_AUTH : $conf->global->ARUBASDI_DEMOAUTH;
        $url .= '/auth/signin';
        $addheaders = array(
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
        );
        $param = "grant_type=password&username=".$conf->global->AURUBASDI_LOGIN."&password=".$conf->global->AURUBASDI_PASSWORD;

        /**
         * Function to get a content from an URL (use proxy if proxy defined)
         *
         * @param	string	  $url 				    URL to call.
         * @param	string    $postorget		    'POST', 'GET', 'HEAD', 'PUT', 'PUTALREADYFORMATED', 'POSTALREADYFORMATED', 'DELETE'
         * @param	string    $param			    Parameters of URL (x=value1&y=value2) or may be a formated content with PUTALREADYFORMATED
         * @param	integer   $followlocation		1=Follow location, 0=Do not follow
         * @param	string[]  $addheaders			Array of string to add into header. Example: ('Accept: application/xrds+xml', ....)
         * @return	array						    Returns an associative array containing the response from the server array('content'=>response,'curl_error_no'=>errno,'curl_error_msg'=>errmsg...)
         */
        // function getURLContent($url, $postorget = 'GET', $param = '', $followlocation = 1, $addheaders = array())

        $res = getURLContent($url, 'POST', $param, 1, $addheaders);
        $reponse_object = json_decode($res['content']);
        if(!$reponse_object->access_token){
            setEventMessage('ArubaSDI: ' . $res['curl_error_msg'], 'errors');
            return 0;
        }
        return($reponse_object->access_token);
    }

    static function transmit($token, $fattura_xml){
        global $conf;
        $url = empty($conf->global->AURUBASDI_DEMOMODE) ? $conf->global->ARUBASDI_WS : $conf->global->ARUBASDI_DEMOWS;
        $url .= '/services/invoice/upload';
        $addheaders = array(
            'Accept: application/json',
            'Authorization: Bearer '.$token,
            'Content-Type: application/json;charset=UTF-8'
        );
        $data = new stdClass();
        $data->dataFile = base64_encode($fattura_xml->asXML());
        // chiamata
        $res = getURLContent($url, 'POST', json_encode($data), 1, $addheaders);
        return json_decode($res['content']);
    }

    static function getInvoices($token, $startTime = null, $endTime = null, $page = 1){
        global $conf, $langs, $user;

        $url = empty($conf->global->AURUBASDI_DEMOMODE) ? $conf->global->ARUBASDI_WS : $conf->global->ARUBASDI_DEMOWS;
        $url .= '/services/invoice/in/findByUsername';
        $addheaders = array(
            'Accept: application/json',
            'Authorization: Bearer '.$token,
        );
        // dati da passare via GET
        $startDate  = $startTime ? $startTime : date('c', strtotime("-7 days"));
        $endDate    = $endTime   ? $endTime : date('c');
        $data = array(
            'username'          => $conf->global->AURUBASDI_LOGIN,
            'startDate'         => $startTime,
            'endDate'           => $endTime,
            'size'              => $conf->global->MAIN_SIZE_LISTE_LIMIT,
            'page'              => $page,
            'countryReceiver'   => 'IT',
            'vatcodeReceiver'   => preg_replace("/\D/", '', $conf->global->MAIN_INFO_TVAINTRA)
        );
        $url .= '?'. http_build_query($data);
        // chiamata
        $res = getURLContent($url, 'GET', '' , 1, $addheaders);
        return json_decode($res['content']);
    }

    /*
     * Cerca fatture registrate
     * 
    */
    static function localSearch($partitaIva, $numeroFattura, $data){
    	global $db;
    	# fatture già registrate nel mese
    	$sql = "SELECT f.rowid, f.ref, s.tva_intra FROM ".MAIN_DB_PREFIX."facture_fourn f
            LEFT JOIN ".MAIN_DB_PREFIX."societe s on s.rowid=f.fk_soc
            WHERE  s. tva_intra like '%".preg_replace("/\D/", '', $partitaIva)."'
            AND f.ref_supplier = '" . $db->escape($numeroFattura) . "'
            AND f.datef = DATE('$data')";
		$res = $db->query($sql);
		if($db->num_rows($res) > 0){
			$fattura=$res->fetch_object();
            return $fattura;
		}else {
            return 0;
        }
    }

        /**
         * Funzione per ottenere la fattura
         *
         * @param	string	  $token
         * @param	string    $filename
         * @param	bool      $includePdf
         * @param	bool      $includeFile		
         * @return	object    
         */

    static function getByFilename($token, $filename, $includePdf = false, $includeFile = true){
        global $conf, $user;

        $url = empty($conf->global->AURUBASDI_DEMOMODE) ? $conf->global->ARUBASDI_WS : $conf->global->ARUBASDI_DEMOWS;
        $url .= '/services/invoice/in/getByFilename';
        $addheaders = array(
            'Accept: application/json',
            'Authorization: Bearer '.$token,
        );
        // dati da passare via GET
        $data = array(
            'filename'     => $filename,
            'includePdf'   => $includePdf,
            'includeFile'  => $includeFile
        );
        $url .= '?'. http_build_query($data);
        // chiamata
        $res = getURLContent($url, 'GET', '' , 1, $addheaders);
        return json_decode($res['content']);
        
    }

    static function sendInvoice(Facture $object){
        global $db, $conf, $langs, $mysoc;

        if($object->statut == 1 || $object->statut == 2){

            // Definition of $dir and $file
            $objectref = dol_sanitizeFileName($object->ref);
            $dir = $conf->facture->dir_output . "/" . $objectref;
            $filename = $mysoc->country_code . preg_replace("/\D/", '', $mysoc->tva_intra) .'_'.  strtoupper(base_convert($object->id,10,36)) . ".xml";
            $file = $dir .'/'. $filename;
            
            if (! file_exists($file))
            {
                throw new Exception($object->ref . ": Missing xml file", 1);
            }

            $object->fetch_thirdparty();

            $fattura_xml = simplexml_load_file($file);
            $fattura_xml->FatturaElettronicaHeader->DatiTrasmissione->IdTrasmittente->IdPaese='IT';
            $fattura_xml->FatturaElettronicaHeader->DatiTrasmissione->IdTrasmittente->IdCodice='01879020517';

            $_ContattiTrasmittente = $fattura_xml->FatturaElettronicaHeader->DatiTrasmissione->ContattiTrasmittente;
            unset($_ContattiTrasmittente->Email);
            $_Telefono = $_ContattiTrasmittente->addChild('Telefono','05750505');
            $_Email = $_ContattiTrasmittente->addChild('Email','info@arubapec.it');

    // UTENTICAZIONE
            $token = ArubaSDI::getAuthToken();
            if(!$token){
                throw new Exception(self::class . 'Token non ottenuto');
            }
    // INVIO FATTURA
            $reponse = ArubaSDI::transmit($token, $fattura_xml);
            if($reponse->errorCode == '0000'){
                setEventMessages($reponse->uploadFileName.'<br>'.$reponse->errorDescription, null, 'mesgs');
                
                // salva i riferimenti di invio
                $object->array_options['options_fattura_generata'] = $reponse->uploadFileName;
                $object->array_options['options_statoFattura'] = 1;
                $object->array_options['options_arubasdi_dataStato'] = date('Y-m-d');
                $object->insertExtraFields();
            }else{
                throw new Exception('Fatturazione elettronica Aruba: '.$reponse->errorDescription);
            }

            // $fattura_xml->$dir .'/'. $filename;
            // scrive negli eventi calendario
            if (empty($actiontypecode)) $actiontypecode='AC_OTH_AUTO'; // Event insert into agenda automatically

            $object->actiontypecode	= 'AC_OTH_AUTO'; // Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
            $object->actionmsg2		= $langs->trans('invoiceSentToSDI_short');     // Short text
            $object->actionmsg		= $langs->trans('invoiceSentToSDI_long').' - '.$response->returnCode.' '.$response->description.' - '.$response->message;      // Long text
            $object->trackid        = $trackid;
            $object->fk_element		= $object->id;
            $object->elementtype	= $object->element;
            // Trigger
                include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
                $interface = new Interfaces($db);
                $result = $interface->run_triggers('BILL_SENTBYMAIL',$object,$user,$langs,$conf);

                if ($result < 0) {
                    setEventMessage($interface->error,  'errors');
                }
        }else{
            throw new Exception("La fattura non è convalidata", 1);
        }
    }
}
