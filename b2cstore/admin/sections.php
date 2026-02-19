<?php
/* Copyright (C) 2025 Henaxis
 * Admin: Homepage sections manager (enable/disable, order, content editor).
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

$action  = GETPOST('action', 'aZ09');
$section = GETPOST('section', 'alpha');

// Section definitions (type => label)
$sectionDefs = array(
	'HERO'             => $langs->trans('B2CStoreSectionHero'),
	'ABOUT'            => $langs->trans('B2CStoreSectionAbout'),
	'SERVICES'         => $langs->trans('B2CStoreSectionServices'),
	'HISTORY'          => $langs->trans('B2CStoreSectionHistory'),
	'PRODUCTS_PREVIEW' => $langs->trans('B2CStoreSectionProductsPreview'),
	'CONTACT'          => $langs->trans('B2CStoreSectionContact'),
	'B2B_LINK'         => $langs->trans('B2CStoreSectionB2BLink'),
);

// Save all sections order/enabled
if ($action === 'updateall') {
	foreach ($sectionDefs as $type => $label) {
		$enabled = GETPOST('enabled_'.$type, 'alpha') ? '1' : '0';
		$order   = (string)(int) GETPOST('order_'.$type, 'int');
		dolibarr_set_const($db, 'B2CSTORE_SECTION_'.$type.'_ENABLED', $enabled, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'B2CSTORE_SECTION_'.$type.'_ORDER',   $order,   'chaine', 0, '', $conf->entity);
	}
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	$action = '';
}

// Save section content
if ($action === 'updatesection' && $section && isset($sectionDefs[$section])) {
	$fields = array('title','content','bg_color','css_class','image');
	foreach ($fields as $f) {
		$raw = GETPOST($f, ($f === 'content') ? 'nohtml' : 'alphanohtml');
		dolibarr_set_const($db, 'B2CSTORE_SECTION_'.$section.'_'.strtoupper($f), $raw, 'chaine', 0, '', $conf->entity);
	}
	// Special fields per section type
	if ($section === 'HERO') {
		$cta = GETPOST('cta_label', 'alphanohtml');
		dolibarr_set_const($db, 'B2CSTORE_SECTION_HERO_CTA_LABEL', $cta, 'chaine', 0, '', $conf->entity);
	}
	if ($section === 'PRODUCTS_PREVIEW') {
		$cnt = (string)(int) GETPOST('count', 'int');
		dolibarr_set_const($db, 'B2CSTORE_SECTION_PRODUCTS_PREVIEW_COUNT', $cnt, 'chaine', 0, '', $conf->entity);
	}
	if ($section === 'B2B_LINK') {
		$url  = GETPOST('url', 'alpha');
		$text = GETPOST('btn_text', 'alphanohtml');
		dolibarr_set_const($db, 'B2CSTORE_B2B_LINK_URL',      $url,  'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'B2CSTORE_B2B_LINK_BTN_TEXT', $text, 'chaine', 0, '', $conf->entity);
	}
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	$action = '';
}

// ----- OUTPUT -----
llxHeader('', $langs->trans('B2CStoreSections'));

$head = b2cstoreAdminPrepareHead($langs, 'sections');
print dol_get_fiche_head($head, 'sections', $langs->trans('ModuleB2CStoreName'), -1, 'setup');

// Order / Enable table
print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="updateall">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('B2CStoreSectionType').'</th><th>'.$langs->trans('Enable').'</th><th>'.$langs->trans('Order').'</th><th>'.$langs->trans('Edit').'</th></tr>';
foreach ($sectionDefs as $type => $label) {
	$enabled = getDolGlobalString('B2CSTORE_SECTION_'.$type.'_ENABLED', '0');
	$order   = getDolGlobalString('B2CSTORE_SECTION_'.$type.'_ORDER', '50');
	print '<tr class="oddeven"><td>'.$label.' <small style="color:#999">('.$type.')</small></td>';
	print '<td><input type="checkbox" name="enabled_'.$type.'" value="1"'.($enabled ? ' checked' : '').'></td>';
	print '<td><input type="number" name="order_'.$type.'" value="'.dol_escape_htmltag($order).'" size="4" min="1" max="99"></td>';
	print '<td><a href="'.$_SERVER['PHP_SELF'].'?section='.urlencode($type).'">'.$langs->trans('Edit').'</a></td>';
	print '</tr>';
}
print '</table>';
print '<br><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('SaveOrder').'"></div>';
print '</form>';

// Section content editor
if ($section && isset($sectionDefs[$section])) {
	print '<br><h3>'.$langs->trans('B2CStoreEditSection').': '.$sectionDefs[$section].'</h3>';
	print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="updatesection">';
	print '<input type="hidden" name="section" value="'.dol_escape_htmltag($section).'">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Field').'</th><th>'.$langs->trans('Value').'</th></tr>';

	$commonFields = array('title'=>$langs->trans('Title'),'content'=>$langs->trans('Content'),'bg_color'=>$langs->trans('B2CStoreBgColor'),'css_class'=>$langs->trans('B2CStoreCssClass'),'image'=>$langs->trans('B2CStoreImage'));
	foreach ($commonFields as $f => $lbl) {
		$val = dol_escape_htmltag(getDolGlobalString('B2CSTORE_SECTION_'.$section.'_'.strtoupper($f)));
		print '<tr class="oddeven"><td>'.$lbl.'</td><td>';
		if ($f === 'content') {
			print '<textarea name="content" rows="6" class="minwidth500">'.getDolGlobalString('B2CSTORE_SECTION_'.$section.'_CONTENT').'</textarea>';
		} else {
			print '<input type="text" name="'.$f.'" value="'.$val.'" class="minwidth300">';
		}
		print '</td></tr>';
	}

	// Extra section-specific fields
	if ($section === 'HERO') {
		$cta = dol_escape_htmltag(getDolGlobalString('B2CSTORE_SECTION_HERO_CTA_LABEL'));
		print '<tr class="oddeven"><td>'.$langs->trans('B2CStoreHeroCtaLabel').'</td><td><input type="text" name="cta_label" value="'.$cta.'" class="minwidth300"></td></tr>';
	}
	if ($section === 'PRODUCTS_PREVIEW') {
		$cnt = dol_escape_htmltag(getDolGlobalString('B2CSTORE_SECTION_PRODUCTS_PREVIEW_COUNT', '4'));
		print '<tr class="oddeven"><td>'.$langs->trans('B2CStoreFeaturedCount').'</td><td><input type="number" name="count" value="'.$cnt.'" min="1" max="20"></td></tr>';
	}
	if ($section === 'B2B_LINK') {
		$url  = dol_escape_htmltag(getDolGlobalString('B2CSTORE_B2B_LINK_URL'));
		$text = dol_escape_htmltag(getDolGlobalString('B2CSTORE_B2B_LINK_BTN_TEXT'));
		print '<tr class="oddeven"><td>'.$langs->trans('B2CStoreB2BLinkUrl').'</td><td><input type="text" name="url" value="'.$url.'" class="minwidth300"></td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans('B2CStoreB2BLinkBtnText').'</td><td><input type="text" name="btn_text" value="'.$text.'" class="minwidth300"></td></tr>';
	}

	print '</table>';
	print '<br><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
	print '</form>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
