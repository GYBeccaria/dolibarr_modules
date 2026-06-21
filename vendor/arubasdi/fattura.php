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
// Try main.inc.php using relative path
if (! $res && file_exists("../main.inc.php")) $res=@include "../main.inc.php";
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
dol_include_once ('/arubasdi/class/arubasdi.class.php');
dol_include_once('/efattita/lib/efattita.lib.php');
// Load translation files required by the page
$langs->load("efattita@efattita");


// Securite acces client
if (! $user->rights->efattita->read) accessforbidden();

$now=dol_now();
$filename = GETPOST('filename');
$format = GETPOST('format');
/*
 * Actions
 */

// UTENTICAZIONE
	$token = ArubaSDI::getAuthToken();
    if(!$token){
        setEventMessages($langs->trans('UnableToObtainToken'), null, 'errors' );
        dol_syslog(__METHOD__ . ' UnableToObtainToken', LOG_ERR);
    }

// GET FATTURA
	if ($format == 'pdf') {
		$includePdf = true;
		$includeFile = false;
		$downloadFilename = str_replace(['.xml', '.p7m'],'', $filename) . '.pdf';
		$file = 'pdfFile';
	}else{
		$downloadFilename = $filename;
		$file = 'file';
	}
	$response = ArubaSDI::getByFilename($token, $filename, $includePdf, $includeFile);
	// header('Content-Type: application/json; charset=utf-8');
	// header("Content-type: text/xml");
	
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="'. $downloadFilename .'"');
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

	
	echo(base64_decode($response->$file));
