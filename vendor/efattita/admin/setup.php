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
 * \file    efattita/admin/setup.php
 * \ingroup efattita
 * \brief   eFattITA setup page.
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/main.inc.php");
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=@include(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php");
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/efattita.lib.php';

// Translations
$langs->load("errors");
$langs->load("admin");
// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$arrayofparameters=array(
	'EFATTITA_CHECK_UPDATES'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'bool'),

	'Cedente prestatore'=>array('enabled'=>1,'type'=>'separator'),
		'RegimeFiscale'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'select','options'=>'RegimeFiscale'),
		'NaturaBollo'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'select','options'=>'NaturaBollo'),
		'RiferimentoAmministrazione'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'bool'),
	'Ritenuta'=>array('enabled'=>1,'type'=>'separator'),
		'TipoRitenuta'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'select','options'=>'TipoRitenuta'),
		'CausalePagamento'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'select','options'=>'CausalePagamento'),
	'Cassa previdenziale'=>array('enabled'=>1,'type'=>'separator'),
		'TipoCassa'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'select','options'=>'TipoCassa'),
		'RitenutaSuCassaPrevidenziale'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'bool'),
		'IvaSuCassaPrevidenziale'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'bool'),
		'NaturaCassa'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'select','options'=>'NaturaCassa'),
	'Parametri generali'=>array('enabled'=>1,'type'=>'separator'),
		'ESPORTATORI_ABITUALI'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'bool'),
		'NATURA_PER_RIGA'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'bool'),
		'CAUSALE_FATTURA'=>array('css'=>'minwidth200','enabled'=>1,'type'=>'bool'),
	'Compilazione automatica della fattura'=>array('enabled'=>1,'type'=>'separator'),
		'EFATTITA_DEFAULT_LAST_FIELDS'=>array('css'=>'width100p','enabled'=>1),
		'EFATTITA_DEFAULT_LAST_EXTRAFIELDS'=>array('css'=>'width100p','enabled'=>1),
);
$CausalePagamento = $db->query('select * from '.MAIN_DB_PREFIX.'efattita_causali_2');
$TipoCassa = $db->query('select * from '.MAIN_DB_PREFIX.'efattita_tipo_cassa');
$RegimeFiscale = $db->query('select * from '.MAIN_DB_PREFIX.'efattita_regime_fiscale_2');
$TipoRitenuta = $db->query('select * from '.MAIN_DB_PREFIX.'efattita_tipo_ritenuta');
$NaturaCassa = $db->query('select * from '.MAIN_DB_PREFIX.'efattita_natura_2');
$NaturaBollo = $db->query('select * from '.MAIN_DB_PREFIX.'efattita_natura_2');

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

$page_name = "eFattITASetup";
llxHeader('', $langs->trans($page_name));

if(!isset($form))
	$form=new Form($db);

// Subheader
$linkback = '<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_efattita@efattita');

// Configuration header
$head = efattitaAdminPrepareHead();
dol_fiche_head($head, 'settings', '', -1, "efattita@efattita");
if ($action != 'edit') {
	$form_status = 'disabled';
}
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<fieldset style="border:none !important;" ' . $form_status . '>';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder" width="100%">';

foreach($arrayofparameters as $key => $val)
{
	if (isset($val['enabled']) && empty($val['enabled'])) continue;

	switch ($val['type']) {
		case 'select':
			print '<tr class="oddeven"><td>';
			print $form->textwithpicto($langs->trans($key),$langs->trans($key.'Tooltip'));
			print '</td><td><select style="width: 100% !important;" name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth200':$val['css']).'" >';
			while($c=$db->fetch_object(${$key})){
				$selected = $c->code == $conf->global->$key ? 'selected' : null;
				echo "<option title='$c->code - $c->description' value='$c->code' $selected>$c->code - $c->description</option>";
			}
			echo '</select></td></tr>';
			break;
		
		case 'bool':
			print '<tr class="oddeven"><td>';
			print $form->textwithpicto($langs->trans($key),$langs->trans($key.'Tooltip'));
			echo '</td><td>
			<input name="'.$key.'" id="'.$key.'0" type="radio" value="0" '. ($conf->global->$key == 0 ? 'checked' : null) .'><label for="'.$key.'0">No</label>
			<input name="'.$key.'" id="'.$key.'1" type="radio" value="1" '. ($conf->global->$key == 1 ? 'checked' : null) .'><label for="'.$key.'1">Si</label>';
			echo '</td></tr>';
			break;
		
		case 'separator':
			print '<tr class="liste_titre"><td>' . $key . '</td><td></td></tr>';
			break;
		
		default:
			print '<tr class="oddeven"><td>';
			print $form->textwithpicto($langs->trans($key),$langs->trans($key.'Tooltip'));
			print '</td><td><input name="'.$key.'"  class="flat '.(empty($val['css'])?'minwidth200':$val['css']).'" value="' . $conf->global->$key . '"></td></tr>';
			break;
	}
}

print '</table>';

if ($action == 'edit') {
	print '<div class="tabsAction">';
	print '<input class="button" type="submit" value="'.$langs->trans("Save").'">';
	print '</div>';

}else {
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit">'.$langs->trans("Modify").'</a>';
	print '</div>';
}
print '</fieldset>';

// Page end
dol_fiche_end();

llxFooter();
$db->close();
