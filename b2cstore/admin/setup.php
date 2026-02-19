<?php
/* Copyright (C) 2025 Henaxis
 * Admin: General settings for B2CStore module.
 */

$res = 0;
if (!$res && file_exists('../../../main.inc.php'))             $res = @include '../../../main.inc.php';
if (!$res && file_exists('../../../../main.inc.php'))          $res = @include '../../../../main.inc.php';
if (!$res && file_exists('../../../../../main.inc.php'))       $res = @include '../../../../../main.inc.php';
if (!$res) die('Cannot find main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../lib/b2cstore.lib.php';

if (!$user->admin) accessforbidden();

$langs->loadLangs(array('admin', 'b2cstore@b2cstore'));

$action = GETPOST('action', 'aZ09');

// Save settings
if ($action === 'update' && !GETPOST('cancel', 'alpha')) {
	$errors = array();

	$params = array(
		'B2CSTORE_ENABLE_REGISTRATION'   => array('type' => 'chk'),
		'B2CSTORE_REGISTRATION_APPROVAL' => array('type' => 'chk'),
		'B2CSTORE_ALLOW_GUEST_BROWSING'  => array('type' => 'chk'),
		'B2CSTORE_HIDE_PRICES_GUEST'     => array('type' => 'chk'),
		'B2CSTORE_CART_REQUIRES_LOGIN'   => array('type' => 'chk'),
		'B2CSTORE_REGISTRATION_FIELDS'   => array('type' => 'txt'),
		'B2CSTORE_DEFAULT_PRICE_LEVEL'   => array('type' => 'int'),
		'B2CSTORE_ITEMS_PER_PAGE'        => array('type' => 'int'),
		'B2CSTORE_CUSTOMER_TYPENT_ID'    => array('type' => 'int'),
		'B2CSTORE_NOTIFICATION_EMAIL'    => array('type' => 'txt'),
		'B2CSTORE_META_TITLE'            => array('type' => 'txt'),
		'B2CSTORE_META_DESC'             => array('type' => 'txt'),
	);

	foreach ($params as $key => $cfg) {
		if ($cfg['type'] === 'chk') {
			$val = GETPOST($key, 'alpha') ? '1' : '0';
		} elseif ($cfg['type'] === 'int') {
			$val = (string)(int) GETPOST($key, 'int');
		} else {
			$val = GETPOST($key, 'alphanohtml');
		}
		dolibarr_set_const($db, $key, $val, 'chaine', 0, '', $conf->entity);
	}

	if (empty($errors)) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages(implode('<br>', $errors), null, 'errors');
	}
	$action = '';
}

// ----- OUTPUT -----
llxHeader('', $langs->trans('B2CStoreSetup'));

$head = b2cstoreAdminPrepareHead($langs, 'setup');
print dol_get_fiche_head($head, 'setup', $langs->trans('ModuleB2CStoreName'), -1, 'setup');

print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="page_y" value="">';

print load_fiche_titre($langs->trans('B2CStoreGeneralSettings'), '', 'setup');
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';

$list = array(
	array('B2CSTORE_ENABLE_REGISTRATION',   'chk',  $langs->trans('B2CStoreEnableRegistration')),
	array('B2CSTORE_REGISTRATION_APPROVAL', 'chk',  $langs->trans('B2CStoreRegistrationApproval')),
	array('B2CSTORE_ALLOW_GUEST_BROWSING',  'chk',  $langs->trans('B2CStoreAllowGuestBrowsing')),
	array('B2CSTORE_HIDE_PRICES_GUEST',     'chk',  $langs->trans('B2CStoreHidePricesGuest')),
	array('B2CSTORE_CART_REQUIRES_LOGIN',   'chk',  $langs->trans('B2CStoreCartRequiresLogin')),
);
foreach ($list as $row) {
	$val = getDolGlobalString($row[0]);
	print '<tr class="oddeven"><td>'.$row[2].'</td>';
	print '<td><input type="checkbox" name="'.$row[0].'" value="1"'.($val ? ' checked' : '').'></td></tr>';
}

print '</table><br>';

print load_fiche_titre($langs->trans('B2CStoreAdvancedSettings'), '', 'setup');
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';

$txtList = array(
	array('B2CSTORE_REGISTRATION_FIELDS', $langs->trans('B2CStoreRegistrationFields'),    'alphanohtml', 'firstname,lastname,email,password,phone,address,zip,town'),
	array('B2CSTORE_DEFAULT_PRICE_LEVEL', $langs->trans('B2CStoreDefaultPriceLevel'),     'int',         '1'),
	array('B2CSTORE_ITEMS_PER_PAGE',      $langs->trans('B2CStoreItemsPerPage'),          'int',         '12'),
	array('B2CSTORE_CUSTOMER_TYPENT_ID',  $langs->trans('B2CStoreCustomerTypeEntId'),     'int',         '0'),
	array('B2CSTORE_NOTIFICATION_EMAIL',  $langs->trans('B2CStoreNotificationEmail'),     'email',       ''),
	array('B2CSTORE_META_TITLE',          $langs->trans('B2CStoreMetaTitle'),             'alphanohtml', ''),
	array('B2CSTORE_META_DESC',           $langs->trans('B2CStoreMetaDesc'),              'alphanohtml', ''),
);
foreach ($txtList as $row) {
	$val = getDolGlobalString($row[0], $row[3]);
	print '<tr class="oddeven"><td>'.$row[1].'</td>';
	print '<td><input type="text" name="'.$row[0].'" value="'.dol_escape_htmltag($val).'" class="minwidth300"></td></tr>';
}
print '</table>';

print '<br><div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '&nbsp;<input type="submit" name="cancel" class="button button-cancel" value="'.$langs->trans('Cancel').'">';
print '</div>';

print '</form>';

$storeUrl = b2cstore_build_url();
print '<br><div class="info"><a href="'.dol_escape_htmltag($storeUrl).'" target="_blank">'.$langs->trans('B2CStoreViewStore').'</a></div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
