<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/admin/setup.php
 * \brief   B2B Order Portal admin setup page
 */

// Load Dolibarr environment
// admin/setup.php is at: <dolibarr_root>/custom/b2border/admin/setup.php
// main.inc.php is at:    <dolibarr_root>/main.inc.php  (3 levels up from __DIR__)
$res = 0;
foreach (array(
	dirname(dirname(dirname(__DIR__))).'/main.inc.php',
	dirname(dirname(dirname(dirname(__DIR__)))).'/main.inc.php',
	dirname(dirname(dirname(__DIR__))).'/htdocs/main.inc.php',
) as $_mainPath) {
	if (file_exists($_mainPath)) {
		$res = @include $_mainPath;
		if ($res) {
			break;
		}
	}
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once dirname(__DIR__).'/lib/b2border.lib.php';

// Security check
if (!$user->admin) {
	accessforbidden();
}

$langs->loadLangs(array('admin', 'companies', 'categories'));
$langs->load('b2border@b2border');

$action = GETPOST('action', 'alphanohtml');

/*
 * Actions
 */
if ($action == 'update') {
	$error = 0;

	$res = dolibarr_set_const($db, 'B2BORDER_PORTAL_TITLE', GETPOST('B2BORDER_PORTAL_TITLE', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }
	$res = dolibarr_set_const($db, 'B2BORDER_PRODUCTS_PER_PAGE', GETPOST('B2BORDER_PRODUCTS_PER_PAGE', 'int'), 'int', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }

	// Categories: get array from multi-select, store as comma-separated
	$selectedCats = GETPOST('B2BORDER_ALLOWED_CATEGORIES', 'array');
	$catString = is_array($selectedCats) ? implode(',', array_map('intval', $selectedCats)) : '';
	$res = dolibarr_set_const($db, 'B2BORDER_ALLOWED_CATEGORIES', $catString, 'chaine', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }

	// Tags: get array from multi-select, store as comma-separated
	$selectedTags = GETPOST('B2BORDER_ALLOWED_TAGS', 'array');
	$tagString = is_array($selectedTags) ? implode(',', array_map('intval', $selectedTags)) : '';
	$res = dolibarr_set_const($db, 'B2BORDER_ALLOWED_TAGS', $tagString, 'chaine', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }

	// Show stock
	$res = dolibarr_set_const($db, 'B2BORDER_SHOW_STOCK', GETPOST('B2BORDER_SHOW_STOCK', 'int'), 'int', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }

	// Default price level
	$priceLevel = GETPOST('B2BORDER_DEFAULT_PRICE_LEVEL', 'int');
	if ($priceLevel < 1) {
		$priceLevel = 1;
	}
	$res = dolibarr_set_const($db, 'B2BORDER_DEFAULT_PRICE_LEVEL', $priceLevel, 'int', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

/*
 * View
 */
$page_name = "B2BOrderSetup";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = b2borderAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'fa-shopping-cart');

// Portal URL
$portalUrl = dol_buildpath('/custom/b2border/public/index.php', 2);
print '<div class="info">';
print $langs->trans("B2BOrderPortalURL").': <a href="'.$portalUrl.'" target="_blank">'.$portalUrl.'</a>';
print '</div><br>';

// Load all product categories for select2
$categStatic = new Categorie($db);
$allCats = $categStatic->get_full_arbo(Categorie::TYPE_PRODUCT);

// Current selected categories
$currentCats = array_filter(array_map('trim', explode(',', getDolGlobalString('B2BORDER_ALLOWED_CATEGORIES'))));

// Load all tag categories (from llx_element_tag -> llx_categorie)
// Tags are type=0 categories linked to products via llx_element_tag
$allTagCats = array();
$sqlTags = "SELECT DISTINCT c.rowid, c.label";
$sqlTags .= " FROM ".$db->prefix()."categorie as c";
$sqlTags .= " INNER JOIN ".$db->prefix()."element_tag et ON et.fk_categorie = c.rowid";
$sqlTags .= " WHERE c.entity IN (".getEntity('category').")";
$sqlTags .= " ORDER BY c.label ASC";
$resqlTags = $db->query($sqlTags);
if ($resqlTags) {
	while ($obj = $db->fetch_object($resqlTags)) {
		$allTagCats[] = array('rowid' => $obj->rowid, 'label' => $obj->label);
	}
}
// Current selected tags
$currentTags = array_filter(array_map('trim', explode(',', getDolGlobalString('B2BORDER_ALLOWED_TAGS'))));

// Multiprices limit
$multipricingEnabled = getDolGlobalString('PRODUIT_MULTIPRICES');
$multipricesLimit = max(1, getDolGlobalInt('PRODUIT_MULTIPRICES_LIMIT', 10));

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

// Portal title
print '<tr class="oddeven"><td>'.$langs->trans("B2BOrderPortalTitle").'</td>';
print '<td><input type="text" name="B2BORDER_PORTAL_TITLE" value="'.dol_escape_htmltag(getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal')).'" size="60"></td></tr>';

// Products per page
print '<tr class="oddeven"><td>'.$langs->trans("B2BOrderProductsPerPage").'</td>';
print '<td><input type="number" name="B2BORDER_PRODUCTS_PER_PAGE" value="'.getDolGlobalInt('B2BORDER_PRODUCTS_PER_PAGE', 12).'" min="4" max="100"></td></tr>';

// --- Allowed categories ---
print '<tr class="oddeven"><td>';
print $langs->trans("B2BOrderAllowedCategories");
print '<br><small>'.$langs->trans("B2BOrderAllowedCategoriesHelp").'</small>';
print '</td><td>';
print '<select name="B2BORDER_ALLOWED_CATEGORIES[]" id="b2border_categories" multiple="multiple" class="minwidth400">';
if (is_array($allCats)) {
	foreach ($allCats as $cat) {
		$catId = $cat['id'];
		$catLabel = !empty($cat['fulllabel']) ? $cat['fulllabel'] : $cat['label'];
		$selected = in_array((string)$catId, $currentCats) ? ' selected' : '';
		print '<option value="'.intval($catId).'"'.$selected.'>'.dol_escape_htmltag($catLabel).'</option>';
	}
}
print '</select>';
print '</td></tr>';

// --- Allowed tags ---
print '<tr class="oddeven"><td>';
print $langs->trans("B2BOrderAllowedTags");
print '<br><small>'.$langs->trans("B2BOrderAllowedTagsHelp").'</small>';
print '</td><td>';
if (empty($allTagCats)) {
	print '<span class="opacitymedium">'.$langs->trans("B2BOrderNoTagsFound").'</span>';
} else {
	print '<select name="B2BORDER_ALLOWED_TAGS[]" id="b2border_tags" multiple="multiple" class="minwidth400">';
	foreach ($allTagCats as $tag) {
		$selected = in_array((string)$tag['rowid'], $currentTags) ? ' selected' : '';
		print '<option value="'.intval($tag['rowid']).'"'.$selected.'>'.dol_escape_htmltag($tag['label']).'</option>';
	}
	print '</select>';
}
print '</td></tr>';

// --- Price level ---
print '<tr class="oddeven"><td>';
print $langs->trans("B2BOrderDefaultPriceLevel");
print '<br><small>'.$langs->trans("B2BOrderDefaultPriceLevelHelp").'</small>';
if (!$multipricingEnabled) {
	print '<br><small class="warning">'.$langs->trans("B2BOrderMultipricesDisabled").'</small>';
}
print '</td><td>';
$currentLevel = getDolGlobalInt('B2BORDER_DEFAULT_PRICE_LEVEL', 1);
print '<select name="B2BORDER_DEFAULT_PRICE_LEVEL"'.(!$multipricingEnabled ? ' disabled' : '').'>';
for ($i = 1; $i <= $multipricesLimit; $i++) {
	print '<option value="'.$i.'"'.($currentLevel == $i ? ' selected' : '').'>'.($langs->trans("B2BOrderPriceLevel").' '.$i).'</option>';
}
print '</select>';
if (!$multipricingEnabled) {
	print '<input type="hidden" name="B2BORDER_DEFAULT_PRICE_LEVEL" value="'.intval($currentLevel).'">';
}
print '</td></tr>';

// Show stock
print '<tr class="oddeven"><td>'.$langs->trans("B2BOrderShowStock").'</td>';
print '<td><input type="checkbox" name="B2BORDER_SHOW_STOCK" value="1"'.(getDolGlobalInt('B2BORDER_SHOW_STOCK', 1) ? ' checked' : '').'></td></tr>';

print '</table>';

print '<br><div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';
print '</form>';

// Include select2 and initialize both selects
print "\n".'<script src="'.DOL_URL_ROOT.'/includes/jquery/plugins/select2/js/select2.full.min.js"></script>'."\n";
print '<link rel="stylesheet" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/select2/css/select2.min.css">'."\n";
print '<script>
$(document).ready(function() {
	$("#b2border_categories").select2({
		placeholder: "'.dol_escape_js($langs->trans("B2BOrderAllCategories")).'",
		allowClear: true,
		width: "80%"
	});
	$("#b2border_tags").select2({
		placeholder: "'.dol_escape_js($langs->trans("B2BOrderAllTags")).'",
		allowClear: true,
		width: "80%"
	});
});
</script>'."\n";

print dol_get_fiche_end();

llxFooter();
$db->close();
