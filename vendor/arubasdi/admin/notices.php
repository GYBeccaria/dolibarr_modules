<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2019 Linx srls
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    arubasdi/admin/notices.php
 * \ingroup arubasdi
 * \brief   NotificheAruba setup page.
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
dol_include_once ('/arubasdi/class/arubasdi.class.php');

// Translations
$langs->load("errors");
$langs->load("admin");

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');


/*
 * Actions
 */

if ($action == 'subscribe')
{

//  attiva il modulo API necessario
    activateModule('modApi');

// Create NotificheAruba user if does not exist
    $NotificheAruba = new User($db);
    $result = $NotificheAruba->fetch('', 'NotificheAruba');
    if($result <= 0){
        
        $NotificheAruba->lastname = 'NotificheAruba';
        $NotificheAruba->login = 'NotificheAruba';
        $NotificheAruba->email = 'notificheAruba@'.$_SERVER['HTTP_HOST'];
        $NotificheAruba->api_key = getRandomPassword(true);
        
        $result = $NotificheAruba->create($user);
        if ($result < 0)
        {
            setEventMessages($NotificheAruba->error, $NotificheAruba->errors, 'errors');
        }else{
            setEventMessage($langs->trans('NotificheArubaUserCreated'));
        }
    }

    if($NotificheAruba->id){
        
        // Entity property cannot be chosen at this time
        $entity = 1;
        $result = $NotificheAruba->addrights(0, 'arubasdi', '', $entity);
        if ($result < 0)
        {
            setEventMessages($NotificheAruba->error, $NotificheAruba->errors, 'errors');
        }
    }else{
        setEventMessages($langs->trans('NotificheArubaUserNotFound') , 'errors');
    }



    $addheaders = array(
        'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
    );
    $param[] = "dolApiURL=".DOL_MAIN_URL_ROOT;
    $param[] = "dolApiKey=".$NotificheAruba->api_key;
    $param[] = "name=".$mysoc->name;
    $param[] = "countryCode=".$mysoc->country_code;
    $param[] = "vatCode=".preg_replace("/\D/", '', $mysoc->tva_intra);

    $params = implode('&',$param);
    $url = "http://notifiche.semplifica.cloud/subscribe";
    $res = getURLContent($url, 'POST', $params, 1, $addheaders);
    $reponse_object = $res['content'];
    setEventMessage($reponse_object);
}



/*
 * View
 */

$page_name = "NotifySubscription";
llxHeader('', $langs->trans($page_name));

if(!isset($form))
	$form=new Form($db);

// Subheader
$linkback = '<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_arubasdi@arubasdi');

// Configuration header
$head = ArubaSDI::AdminPrepareHead();
dol_fiche_head($head, 'notices', '', 0, "arubasdi@arubasdi");
echo '<p>La sottoscrizione al servizio di notifica consente di ricevere in tempo reale ( appena raggiungono il canale ) le fatture dei fornitori e gli esiti sulle fatture inviate.</p>';
echo '<p>Cliccando su sottoscrivi, verrà creato un nuovo utente NotificheAruba con i soli permessi di ricevere queste informazioni e di aggiornarne le fatture relative.</p>';
echo '<p>I dati inviati e salvati sui server di notifica saranno:
	<ul>
	<li>Partita IVA</li>
	<li>Token dell\'utente NotificheAruba</li>
	<li>URL dell\'installazione di Dolibarr</li>
	</ul>
	</p>';
echo '<div class="info hideonsmartphone">
	<span class="fa fa-info-circle" title="Informazioni per gli amministratori"></span> 
	Nota: questo servizio non è attivabile se l\'installazione di Dolibarr è locale o comunque non raggiungibile attraverso internet.
</div>';
// Page end
dol_fiche_end();


print '<div class="tabsAction">';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=subscribe">'.$langs->trans("Subscribe").'</a>';
print '</div>';
llxFooter();
$db->close();
