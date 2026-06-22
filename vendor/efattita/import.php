<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
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
 *	\file       htdocs/efattita/template/efattitaindex.php
 *	\ingroup    efattita
 *	\brief      Home page of efattita top menu
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include substr($tmp, 0, ($i+1))."/main.inc.php";
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include dirname(substr($tmp, 0, ($i+1)))."/main.inc.php";
// Try main.inc.php using relative path
if (! $res && file_exists("../main.inc.php")) $res=@include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
include('./lib/efattita.lib.php');
include('./class/efattita.class.php');
// Load translation files required by the page
$langs->load("efattita@efattita");

$action=GETPOST('action', 'alpha');


// Securite acces client
if (! $user->rights->efattita->read) accessforbidden();

$now=dol_now();

/*
 * Actions
 */

//definizioni


if ( $action=='load' )
{
	libxml_use_internal_errors(true);
	if (file_exists($_FILES['file']['tmp_name'])){
		if(mime_content_type($_FILES['file']['tmp_name'])=='application/octet-stream' && pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)=='p7m'){

			// Metodo 1: estrae previamente il certificato dallo stesso file per estrarre l'xml
			// Metodo 2: estrae l'xml con i certificati scaricati dai siti delle autorità


			$xmlfatt = stream_get_meta_data(tmpfile())['uri'];
			$output = stream_get_meta_data(tmpfile())['uri'];
			$signer = stream_get_meta_data(tmpfile())['uri'];																							// metodo 1:

			der2smime($_FILES['file']['tmp_name'], $xmlfatt);
			
			
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
				$xmlContent = stripP7MData(file_get_contents($_FILES['file']['tmp_name']));
				$xmlContent = sanitizeXML($xmlContent);
			}
		}else{
			$xmlContent = file_get_contents($_FILES['file']['tmp_name']);
		}

		if($xmlobject = simplexml_load_string($xmlContent)) {
			$efatt = new ElectronicFacture($db);
			// check se fattura attiva o passiva
			if (is_fattura_attiva($xmlobject)) {
				$socid = $efatt->createUpdateThirdParty($xmlobject, 0);
				if($xmlobject->FatturaElettronicaBody[0]){
					foreach ($xmlobject->FatturaElettronicaBody as $xmlobjectelm){
						$efatt->carica_fattura_attiva($xmlobjectelm, $socid);
					}
				}
			}else {
				$socid = $efatt->createUpdateThirdParty($xmlobject, 1);
				if($xmlobject->FatturaElettronicaBody[0]){
					foreach ($xmlobject->FatturaElettronicaBody as $xmlobjectelm)
					$efatt->carica_fattura($xmlobjectelm, $socid, $xmlobject->FatturaElettronicaHeader->DatiTrasmissione->FormatoTrasmissione, $_FILES['file']['tmp_name']);
				}
			}

		}else{
			setEventMessages("Non è stato possibile elaborare il file", null, 'errors');
		}
	}else {
		setEventMessages($langs->trans("fileNotLoaded"), null, 'errors');
	}
}

/*
 * View
 */

llxHeader("",$langs->trans("loadInvoice"));
print load_fiche_titre($langs->trans("eFattITAImport"),'','efattita.png@efattita');
print ($langs->trans("eFattITAImportDescription"));
print '<div class="fichecenter"><div class="fichethirdleft">';
print '<form name="load" enctype="multipart/form-data" action="' . $_SERVER["PHP_SELF"] . '" method="post">';
print 'File selezionato:</td><input type="file" name="file" />';
print ' <input type="submit" class="button small reposition" value="Upload">
<input type="hidden" name="action" value="load"></input>';
echo '<input type="hidden" name="token" value="'.newToken().'">';

print '</form><br><br>';
// visualizza errori/informazioni

print '</div><div class="fichetwothirdright"><div class="ficheaddleft">';



print '</div></div></div>';

llxFooter();

$db->close();
