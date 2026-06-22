<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2018 SuperAdmin
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
 * \file    arubasdi/admin/setup.php
 * \ingroup arubasdi
 * \brief   ArubaSDI setup page.
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
dol_include_once ('/arubasdi/class/arubasdi.class.php');

// Translations
$langs->load("errors");
$langs->load("admin");

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$arrayofparameters=array(
	'AURUBASDI_LOGIN'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'text'),
	'AURUBASDI_PASSWORD'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'password'),
);

/*
 * Actions
 */
if ((float) DOL_VERSION >= 6)
{
	include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';
}else{

 if ($action == 'update')
 {
 	$db->begin();

	foreach($arrayofparameters as $parameter=>$option){
		$res=dolibarr_set_const($db, "$parameter",GETPOST($parameter, 'alpha'),'chaine',0,'',$conf->entity);
		if (! $res > 0) $error++;
	}
     if (! $error)
     {
     	$db->commit();
     	setEventMessage($langs->trans("SetupSaved"));
     }
     else
     {
     	$db->rollback();
     	setEventMessage($langs->trans("Error"),'errors');
     }
 }

}


/*
 * View
 */

$page_name = "ArubaSDISetup";
llxHeader('', $langs->trans($page_name));

if(!isset($form))
	$form=new Form($db);

// Subheader
$linkback = '<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_arubasdi@arubasdi');

// Configuration header
$head = ArubaSDI::AdminPrepareHead();
dol_fiche_head($head, 'settings', '', -1, "arubasdi@arubasdi");

if ($action == 'edit')
{
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="update">';

	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

	foreach($arrayofparameters as $key => $val)
	{
		if (isset($val['enabled']) && empty($val['enabled'])) continue;
		switch ( $val['type']) {
			case 'text':
				print '<tr class="oddeven"><td>';
				print $form->textwithpicto($langs->trans($key),$langs->trans($key.'Tooltip'));
				print '</td><td><input name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth200':$val['css']).'" value="' . $conf->global->$key . '"></td></tr>';
				break;
			case 'password':
				print '<tr class="oddeven"><td>';
				print $form->textwithpicto($langs->trans($key),$langs->trans($key.'Tooltip'));
				print '</td><td><input type="password" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth200':$val['css']).'" value="' . $conf->global->$key . '"></td></tr>';
				break;
				break;
			case 'select':
				print '<tr class="oddeven"><td>';
				print $form->textwithpicto($langs->trans($key),$langs->trans($key.'Tooltip'));
				print '</td><td><select style="width: 100% !important;" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth200':$val['css']).'" value="' . $conf->global->$key . '">';
				while($c=$db->fetch_object(${$key}))
					echo "<option title='$c->code - $c->description' value='$c->code'>$c->code - $c->description</option>";
				echo '</select></td></tr>';
				break;
			case 'yesno':
				print '<tr class="oddeven"><td>';
				print $form->textwithpicto($langs->trans($key),$langs->trans($key.'Tooltip'));
				print '</td><td><select style="width: 100% !important;" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth200':$val['css']).'" value="' . $conf->global->$key . '">';
				echo '<option value="0">No</option><option value="1">Si</option>';
				echo '</select></td></tr>';
				break;
			
		}
	}

	print '</table>';

	print '<br><div class="center">';
	print '<input class="button" type="submit" value="'.$langs->trans("Save").'">';
	print '</div>';

	print '</form>';
	print '<br>';
}
else
{
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

	foreach($arrayofparameters as $key => $val)
	{
		print '<tr class="oddeven"><td>';
		print $form->textwithpicto($langs->trans($key),$langs->trans($key.'Tooltip'));
		print '</td><td>' . ($val['type']=='password' ? '****' :$conf->global->$key) . '</td></tr>';
	}

	print '</table>';

	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit">'.$langs->trans("Modify").'</a>';
	print '</div>';
}


// Page end
dol_fiche_end();

llxFooter();
$db->close();
