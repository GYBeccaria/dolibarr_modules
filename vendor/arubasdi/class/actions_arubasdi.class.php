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
 *	\file       class/actions_arubasdi.class.php
 *	\ingroup    facture
 *	\brief      File of class to manage invoices operations
 */

dol_include_once ('/arubasdi/class/arubasdi.class.php');

class ActionsArubaSDI
{ 
	/**
	 * Overriding the doActions function : replacing the parent function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function printFieldListValue(&$parameters, $object, $action, $hookmanager)
	{
        global $db, $conf, $i, $langs, $totalarray;

		$html = '';

		if (in_array('invoicelist', explode(':', $parameters['context'])))
		{
			if($parameters['obj']->options_statoFattura){
				// $esiti=str_split($parameters['obj']->options_statoFattura,1);
				$link = dol_buildpath('/compta/fature.php?rowid=1',1);

				$ArubaSDI = new ArubaSDI($db);
					$stati_color = array(0,2,8,2,8,4,4,4,4,8,8); // 0 bianco, 2 giallo, 8 rosso, 4 verde
					$html .= dolGetStatus($ArubaSDI->statiFatture[$parameters['obj']->options_statoFattura], '', '', 'status'.($stati_color[$parameters['obj']->options_statoFattura]), 5);
			}
			// aggiunge un td vuoto sulla riga del totale
			if (!$i) $totalarray['nbfield']++;
            $this->resprints = '<td>' . $html . '</td>';
		}
		return 0; 
	}

	// Titolo / funzione ordinamento Stato Trasmissione
	function printFieldListTitle($parameters, $object, $action, $hookmanager)
	{
        global $db,$conf, $sortfield, $sortorder;
		if (in_array('invoicelist', explode(':', $parameters['context'])))
		{
				$this->resprints = getTitleFieldOfList('Trasmissione',0,$_SERVER["PHP_SELF"],"statoFattura","",$param,'align="center"',$sortfield,$sortorder);
		}
		return 0; 
	}

	// Filtro ricerca fattura per Stato Trasmissione
	function printFieldListOption($parameters, $object, $action, $hookmanager)
	{
		global $db, $langs;
		$filter = GETPOST('search_options_statoFattura', 'alpha');
		if (in_array('invoicelist', explode(':', $parameters['context'])))
		{
			$this->resprints = '<td class="liste_titre">
			<select class="flat maxwidth200 maxwidthonsmartphone" name="search_options_statoFattura" id="search_options_statoFattura">
				<option value=""></option>';
			$ArubaSDI = new ArubaSDI($db);
			foreach($ArubaSDI->statiFatture as $numero => $stato){
				$this->resprints .= "<option value=$numero " . ($filter === (string)$numero ? 'selected' : null) . '>'. $stato.'</option>';
			}
				$this->resprints .= '</select>
			</td>';
		}
		return 0; 
	}

	function doActions($parameters, $object, $action, $hookmanager)
	{
		global $db, $conf, $extrafields, $langs, $mysoc, $user;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

		$filter = GETPOST('search_options_statoFattura', 'int');
		$confirm = GETPOST('confirm', 'alpha');


		if (in_array('invoicecard', explode(':', $parameters['context'])))
		{

			if($action == 'confirm_sendToSDI' && $confirm == 'yes'){

				try {
					ArubaSDI::sendInvoice($object);
				} catch (\Throwable $th) {
					setEventMessage($th->getMessage(), 'warnings');
				}

			}elseif ($action == 'confirm_clone') {
				// impedisce la clonazione di alcuni campi
				unset($extrafields->attributes['facture']['label']['fattura_generata']);
				unset($extrafields->attributes['facture']['label']['statoFattura']);
			}
		}
		
	}



	function addMoreActionsButtons($parameters, $object, $action, $hookmanager){
		global $conf, $langs, $user, $mysoc;

		if (in_array('invoicecard', explode(':', $parameters['context'])))
		{

	// Stato Tramissione	
			$html ='<div class="titre inline-block">Trasmissione:</div>';
			if($object->array_options['options_fattura_generata']){

            // UTENTICAZIONE
	            $token = ArubaSDI::getAuthToken();
				if (!$token) {
					return 0;
				}
            // GET FATTURE
	            $url = empty($conf->global->AURUBASDI_DEMOMODE) ? $conf->global->ARUBASDI_WS : $conf->global->ARUBASDI_DEMOWS;
	            $url .= '/services/invoice/out/getByFilename';
	            $addheaders = array(
	                'Accept: application/json',
	                'Authorization: Bearer '.$token,
	            );
	            $data = array(
	                'filename'     => $object->array_options['options_fattura_generata'],
	            );
	            $url .= '?'. http_build_query($data);
	            // chiamata
	            $res = getURLContent($url, 'GET', '' , 1, $addheaders);
	            $response = json_decode($res['content']);

			// blocco html inserito con javascript poco sotto
			// non deve contenere apici o accapi 
				$html ='<div class="titre inline-block">Trasmissione:</div>';

				foreach($response->invoices as $invoice){
					$html .= '<pre><table class="terminale">';
					$html .= "<tr><td>Data: </td><td>". htmlentities(date(DATE_COOKIE, strtotime($invoice->invoiceDate)), ENT_QUOTES) . "</td></tr>";
					$html .= "<tr><td>Number: </td><td>" . $invoice->number ."</td></tr>";
					$html .= "<tr><td>Stato: </td><td>" . $invoice->status ."</td></tr>";
					$html .= "<tr><td>Descrizione: </td><td>" . htmlentities($invoice->statusDescription, ENT_QUOTES) ."</td></tr>";
					$html .= "</table></pre>";
				}

				}else{
					$html .= '<pre>Fattura non inviata</pre>';
				}
			
				echo "<script>
					$(document).ready(function(){
						$('#addproduct').prepend('". $html ."')
					});
				</script>";

		// Button "Invia a SDI"
			// usiamo, per compatibilità con i dolibarr precedenti, il numero, non la definizione
			/**
			* const STATUS_DRAFT = 0;
			* const STATUS_VALIDATED = 1;
			* const STATUS_CLOSED = 2;
			*/
			if (empty($conf->global->MAIN_USE_ADVANCED_PERMS) || $user->rights->facture->invoice_advance->send) {
				print '<div class="inline-block divButAction">';
				if ($object->statut == 1 || $object->statut == 2 ) {
					if ($object->array_options['options_fattura_generata']) {
						if (in_array($object->array_options['options_statoFattura'], [2,4])) {
							print '<a id="sendToSDIbtn" title="Reinvia" class="butAction" href="' . $_SERVER['PHP_SELF'] . '?facid=' . $object->id . '&action=sendToSDI">' . $langs->trans('Trasmetti') . '</a>';
						}else{
							print '<a id="sendToSDIbtn" title="Già trasmessa" class="butActionRefused" href="">' . $langs->trans('Trasmetti') . '</a>';
						}
					}else {
						print '<a id="sendToSDIbtn" title="Trasmetti fattura elettronica" class="butAction" href="' . $_SERVER['PHP_SELF'] . '?facid=' . $object->id . '&action=sendToSDI">' . $langs->trans('Trasmetti') . '</a>';
					}
				}else{
					print '<a id="sendToSDIbtn" title="Da convalidare" class="butActionRefused" href="#">' . $langs->trans('SendToSdi') . '</a>';
				}
				print '</div>';
			}
		}
	}
	function formConfirm($parameters, $object, $action, $hookmanager){
		global $db, $langs;
		$form = new Form($db);
		// Confirmation
		if ($action == 'sendToSDI'){
			$this->resprints = $form->formconfirm($_SERVER['PHP_SELF'] . '?facid=' . $object->id, $langs->trans('SendToSDI'), $langs->trans('confirmSendToSDI'), 'confirm_sendToSDI', '', "yes", 2);
		}
		return 0; 
	}
}
