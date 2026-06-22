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
 *	\file       htdocs/efattita/controlloFatture.php
 *	\ingroup    efattita
 *	\brief      Received invoice list
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php using relative path
if (! $res && file_exists("../main.inc.php")) $res=@include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once ('/arubasdi/class/arubasdi.class.php');
dol_include_once('/efattita/class/efattita.class.php');
dol_include_once('/efattita/lib/efattita.lib.php');
// Load translation files required by the page
$langs->load("efattita@efattita");

$action=GETPOST('action', 'alpha');
$importSelected = GETPOST('importSelected', 'array');
$month=GETPOST("month","int")?GETPOST("month","int"):date("m");
$year=GETPOST("year","int")?GETPOST("year","int"):date("Y");

$prev = dol_get_prev_month($month, $year);
$prev_year  = $prev['year'];
$prev_month = $prev['month'];
$next = dol_get_next_month($month, $year);
$next_year  = $next['year'];
$next_month = $next['month'];
if(((float) DOL_VERSION >= 6)){
	$previous = '<i class="fa fa-chevron-left"></i>';
	$next = '<i class="fa fa-chevron-right"></i>';
}else{
	$previous = '&lt;';
	$next = '&gt;';
}
$nav ="<a href=\"?year=".$prev_year."&amp;month=".$prev_month."\">$previous</a> &nbsp;\n";
$nav.=" <span id=\"month_name\">".dol_print_date(dol_mktime(0,0,0,$month,1,$year),"%b %Y");
$nav.=" </span>\n";
$nav.=" &nbsp; <a href=\"?year=".$next_year."&amp;month=".$next_month."\">$next</a>\n";
$picto='calendar';

// Securite acces client
if (! $user->rights->efattita->read) accessforbidden();

$now=dol_now();


/*
 * Actions
 */

// UTENTICAZIONE
	$token = ArubaSDI::getAuthToken();
    if(!$token){
        setEventMessages($langs->trans('UnableToObtainToken'), null, 'errors' );
        dol_syslog(__METHOD__ . ' UnableToObtainToken', LOG_ERR);
    }

// importa fatture selezionate
if (!empty($importSelected)) {
	ArubaSDI::loadByFileNames($token, $importSelected);
}

// GET FATTURE
    $startTime  = date('c', dol_mktime(0,0,0,$month,1,$year));
	$endDate = date('t', dol_mktime(0,0,0,$month,1,$year));
    $endTime    = date('c', dol_mktime(24,0,0,$month, $endDate, $year));
	$page = GETPOST('page', 'int');
	$response = ArubaSDI::getInvoices($token, $startTime, $endTime, $page);


/*
 * View
 */

llxHeader("",$langs->trans("controlloFatture"));
print load_fiche_titre($langs->trans("controlloFatture"), null.' &nbsp; &nbsp; '.$nav,'efattita.png@efattita');
// print load_fiche_titre($s, $link.' &nbsp; &nbsp; '.$nav, '', 0, 0, 'tablelistofcalendars');
print ($langs->trans("controlloFattureDescription"));
print '<div class="fichecenter"><div class="fichethirdleft">';
print'<br><br>';
// visualizza errori/informazioni
if(isset($response->error_code) && $response->error_code != '0000'){
	setEventMessages($response->error_code . $response->error_description, $errors, 'errors');
}
if(empty($response->content)){
	print 'Nessuna fattura';
}else{
	print '</div><div class="fichetwothirdright"><div class="ficheaddleft">';
	
	print '</div></div></div>';
	print'<form method="POST">';

	if(!$response->first){
		echo "<a href='?year=" . $year . "&month=" . $month . "&page=" . ($response->number - 1) . "'><i class='fa fa-chevron-left'></i></a> ";
	}
	echo 'Pag. ' . $response->number . ' di ' . $response->totalPages;
	if(!$response->last){
		echo " <a href='?year=" . $year . "&month=" . $month . "&page=" . ($response->number + 1) . "'><i class='fa fa-chevron-right'></i></a>";
	}

	
	print '<table class="tagtable liste listwithfilterbefore">';
	print '<tr class="liste_titre">
	<th class="liste_titre">Ragione sociale</th>
	<th class="liste_titre">Numero</th>
	<th class="liste_titre">XML</th>
	<th class="liste_titre">PDF</th>
	<th class="liste_titre">Data</th>
	<th class="liste_titre">Tipo</th>
	<th class="liste_titre">P.IVA Fornitore</th>
	<th class="liste_titre">Registrata</th>
	<th class="liste_titre"></th>
	</tr>';

	foreach($response->content as $received){
		foreach($received->invoices as $invoice){
			$var=true;
			$localInvoice = ArubaSDI::localSearch($received->sender->vatCode, $invoice->number, $invoice->invoiceDate);
			$var=!$var;
			echo "<tr {$bc[$var]}>
			<td>{$received->sender->description}</td>
			<td><img src='" . DOL_URL_ROOT . "/theme/eldy/img/object_bill.png' alt='' class='paddingright'>{$invoice->number} </td>
			<td><a href='" . dol_buildpath('/arubasdi/fattura.php?filename='.  $received->filename, 1 ) ."'>{$received->filename}</a></td>
			<td><a href='" . dol_buildpath('/arubasdi/fattura.php?filename='.  $received->filename, 1 ) ."&format=pdf'>" . str_replace(['.xml', '.p7m'],'', $received->filename) . ".pdf</a></td>
			<td>".dol_print_date($invoice->invoiceDate)."</td>
			<td>{$received->invoiceType}</td>
			<td>" . $received->sender->countryCode .$received->sender->vatCode . "</td>
			<td>".($localInvoice ? '<a href=\''.DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$localInvoice->rowid.'\' class=\'classfortooltip\'><img src=\''.DOL_URL_ROOT.'/theme/eldy/img/object_bill.png\' class=\'paddingright\'>'. $localInvoice->ref .'</a>':'No')."</td>
			<td><input type='checkbox' name='importSelected[]' value='$received->filename' /></td>

			</tr>
			";
		}
	}
	echo '</table>';
	echo '
	<div class="tabsAction">
		<input class="butAction" type ="submit" value="Importa" />
	</div>';
	echo '<input type="hidden" name="token" value="'.newToken().'">';
	echo '</form>';
}


llxFooter();
