<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/admin/appearance.php
 * \brief   B2B Order Portal appearance/branding settings
 */

// Load Dolibarr environment
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once dirname(__DIR__).'/lib/b2border.lib.php';

// Security check
if (!$user->admin) {
	accessforbidden();
}

$langs->loadLangs(array('admin', 'companies'));
$langs->load('b2border@b2border');

$action = GETPOST('action', 'alphanohtml');

// Upload directory
$uploadDir = DOL_DATA_ROOT.'/b2border/';

/*
 * Actions
 */
if ($action == 'update') {
	$error = 0;

	// Custom CSS
	$res = dolibarr_set_const($db, 'B2BORDER_CUSTOM_CSS', GETPOST('B2BORDER_CUSTOM_CSS', 'restricthtml'), 'chaine', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }

	// Primary color
	$res = dolibarr_set_const($db, 'B2BORDER_PRIMARY_COLOR', GETPOST('B2BORDER_PRIMARY_COLOR', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }

	// Primary dark color
	$res = dolibarr_set_const($db, 'B2BORDER_PRIMARY_DARK_COLOR', GETPOST('B2BORDER_PRIMARY_DARK_COLOR', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }

	// Footer text
	$res = dolibarr_set_const($db, 'B2BORDER_FOOTER_TEXT', GETPOST('B2BORDER_FOOTER_TEXT', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }

	// Hide powered by
	$res = dolibarr_set_const($db, 'B2BORDER_HIDE_POWERED_BY', GETPOST('B2BORDER_HIDE_POWERED_BY', 'int'), 'int', 0, '', $conf->entity);
	if ($res <= 0) { $error++; }

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

if ($action == 'upload_logo') {
	dol_mkdir($uploadDir);
	if (!empty($_FILES['logo']['tmp_name'])) {
		$filename = 'logo_b2border'.'.'.strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
		$result = dol_move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir.$filename, 1, 0, $_FILES['logo']['error']);
		if ($result > 0) {
			if (image_format_supported($uploadDir.$filename) >= 0) {
				// Generate thumbnail
				$imgThumbSmall = vignette($uploadDir.$filename, 200, 0, '_small', 80);
				dolibarr_set_const($db, 'B2BORDER_LOGO', $filename, 'chaine', 0, '', $conf->entity);
				setEventMessages($langs->trans("B2BOrderLogoUploaded"), null, 'mesgs');
			} else {
				dol_delete_file($uploadDir.$filename);
				setEventMessages($langs->trans("ErrorBadImageFormat"), null, 'errors');
			}
		} else {
			setEventMessages($langs->trans("ErrorFailedToSaveFile"), null, 'errors');
		}
	} else {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesaliases("File")), null, 'errors');
	}
}

if ($action == 'upload_favicon') {
	dol_mkdir($uploadDir);
	if (!empty($_FILES['favicon']['tmp_name'])) {
		$ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
		if (!in_array($ext, array('ico', 'png', 'svg'))) {
			setEventMessages($langs->trans("B2BOrderFaviconBadFormat"), null, 'errors');
		} else {
			$filename = 'favicon_b2border.'.$ext;
			$result = dol_move_uploaded_file($_FILES['favicon']['tmp_name'], $uploadDir.$filename, 1, 0, $_FILES['favicon']['error']);
			if ($result > 0) {
				dolibarr_set_const($db, 'B2BORDER_FAVICON', $filename, 'chaine', 0, '', $conf->entity);
				setEventMessages($langs->trans("B2BOrderFaviconUploaded"), null, 'mesgs');
			} else {
				setEventMessages($langs->trans("ErrorFailedToSaveFile"), null, 'errors');
			}
		}
	} else {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesaliases("File")), null, 'errors');
	}
}

if ($action == 'remove_logo') {
	$logo = getDolGlobalString('B2BORDER_LOGO');
	if ($logo) {
		dol_delete_file($uploadDir.$logo);
		// Delete thumbnail too
		$thumbName = getImageFileNameForSize($uploadDir.$logo, '_small');
		if ($thumbName) {
			dol_delete_file($thumbName);
		}
		dolibarr_del_const($db, 'B2BORDER_LOGO', $conf->entity);
		setEventMessages($langs->trans("B2BOrderLogoDeleted"), null, 'mesgs');
	}
}

if ($action == 'remove_favicon') {
	$favicon = getDolGlobalString('B2BORDER_FAVICON');
	if ($favicon) {
		dol_delete_file($uploadDir.$favicon);
		dolibarr_del_const($db, 'B2BORDER_FAVICON', $conf->entity);
		setEventMessages($langs->trans("B2BOrderFaviconDeleted"), null, 'mesgs');
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
print dol_get_fiche_head($head, 'appearance', $langs->trans($page_name), -1, 'fa-shopping-cart');

// --- Logo upload ---
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("B2BOrderLogo").'</td></tr>';
print '<tr class="oddeven"><td style="width:50%">'.$langs->trans("B2BOrderLogoDesc").'</td><td>';

$currentLogo = getDolGlobalString('B2BORDER_LOGO');
if ($currentLogo && file_exists($uploadDir.$currentLogo)) {
	$logoUrl = dol_buildpath('/custom/b2border/public/getfile.php', 1).'?f=logo&t='.time();
	print '<div style="margin-bottom:10px">';
	print '<img src="'.$logoUrl.'" alt="Logo" style="max-height:80px; max-width:300px; border:1px solid #ddd; padding:4px; border-radius:4px;">';
	print '<br><br>';
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=remove_logo&token='.newToken().'" class="button button-cancel" onclick="return confirm(\''.$langs->trans("B2BOrderConfirmDeleteFile").'\');">'.$langs->trans("Delete").'</a>';
	print '</div>';
}

print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="upload_logo">';
print '<input type="file" name="logo" accept="image/*"> ';
print '<input type="submit" class="button" value="'.$langs->trans("Upload").'">';
print '</form>';
print '</td></tr>';
print '</table>';
print '</div><br>';

// --- Favicon upload ---
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("B2BOrderFavicon").'</td></tr>';
print '<tr class="oddeven"><td style="width:50%">'.$langs->trans("B2BOrderFaviconDesc").'</td><td>';

$currentFavicon = getDolGlobalString('B2BORDER_FAVICON');
if ($currentFavicon && file_exists($uploadDir.$currentFavicon)) {
	$favUrl = dol_buildpath('/custom/b2border/public/getfile.php', 1).'?f=favicon&t='.time();
	print '<div style="margin-bottom:10px">';
	print '<img src="'.$favUrl.'" alt="Favicon" style="max-height:32px; border:1px solid #ddd; padding:2px; border-radius:2px;">';
	print '<br><br>';
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=remove_favicon&token='.newToken().'" class="button button-cancel" onclick="return confirm(\''.$langs->trans("B2BOrderConfirmDeleteFile").'\');">'.$langs->trans("Delete").'</a>';
	print '</div>';
}

print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="upload_favicon">';
print '<input type="file" name="favicon" accept=".ico,.png,.svg"> ';
print '<input type="submit" class="button" value="'.$langs->trans("Upload").'">';
print '</form>';
print '</td></tr>';
print '</table>';
print '</div><br>';

// --- Colors, CSS, Footer ---
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("B2BOrderColors").'</td></tr>';

// Primary color
print '<tr class="oddeven"><td>'.$langs->trans("B2BOrderPrimaryColor").'<br><small>'.$langs->trans("B2BOrderPrimaryColorDesc").'</small></td>';
$primaryColor = getDolGlobalString('B2BORDER_PRIMARY_COLOR');
print '<td><input type="color" name="B2BORDER_PRIMARY_COLOR" value="'.dol_escape_htmltag($primaryColor ? $primaryColor : '#2e86de').'">';
print ' <input type="text" name="" value="'.dol_escape_htmltag($primaryColor).'" size="8" disabled placeholder="#2e86de">';
if ($primaryColor) {
	print ' <a href="#" onclick="$(\'input[name=B2BORDER_PRIMARY_COLOR]\').val(\'#2e86de\'); return false;" class="small">'.$langs->trans("B2BOrderResetDefault").'</a>';
}
print '</td></tr>';

// Primary dark color
print '<tr class="oddeven"><td>'.$langs->trans("B2BOrderPrimaryDarkColor").'<br><small>'.$langs->trans("B2BOrderPrimaryDarkColorDesc").'</small></td>';
$primaryDarkColor = getDolGlobalString('B2BORDER_PRIMARY_DARK_COLOR');
print '<td><input type="color" name="B2BORDER_PRIMARY_DARK_COLOR" value="'.dol_escape_htmltag($primaryDarkColor ? $primaryDarkColor : '#1a5fa8').'">';
print ' <input type="text" name="" value="'.dol_escape_htmltag($primaryDarkColor).'" size="8" disabled placeholder="#1a5fa8">';
if ($primaryDarkColor) {
	print ' <a href="#" onclick="$(\'input[name=B2BORDER_PRIMARY_DARK_COLOR]\').val(\'#1a5fa8\'); return false;" class="small">'.$langs->trans("B2BOrderResetDefault").'</a>';
}
print '</td></tr>';
print '</table>';
print '</div><br>';

// Custom CSS
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">';
print $langs->trans("B2BOrderCustomCSS");
print ' &nbsp; <a href="'.$_SERVER["PHP_SELF"].'/../downloadcss.php?token='.newToken().'" class="button smallpaddingimp" download>';
print '<span class="fa fa-download"></span> '.$langs->trans("B2BOrderDownloadCSSExample");
print '</a>';
print '</td></tr>';
print '<tr class="oddeven"><td style="width:30%">';
print $langs->trans("B2BOrderCustomCSSDesc");
print '<br><br><small>'.$langs->trans("B2BOrderCustomCSSHint").'</small>';
print '</td>';
print '<td><textarea name="B2BORDER_CUSTOM_CSS" rows="12" style="width:100%; font-family:monospace; font-size:12px; line-height:1.5;">'.dol_escape_htmltag(getDolGlobalString('B2BORDER_CUSTOM_CSS')).'</textarea></td></tr>';
print '</table>';
print '</div><br>';

// Footer settings
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("B2BOrderFooterSettings").'</td></tr>';

// Footer text
print '<tr class="oddeven"><td>'.$langs->trans("B2BOrderFooterText").'<br><small>'.$langs->trans("B2BOrderFooterTextDesc").'</small></td>';
print '<td><input type="text" name="B2BORDER_FOOTER_TEXT" value="'.dol_escape_htmltag(getDolGlobalString('B2BORDER_FOOTER_TEXT')).'" size="60"></td></tr>';

// Hide powered by
print '<tr class="oddeven"><td>'.$langs->trans("B2BOrderHidePoweredBy").'</td>';
print '<td><input type="checkbox" name="B2BORDER_HIDE_POWERED_BY" value="1"'.(getDolGlobalInt('B2BORDER_HIDE_POWERED_BY') ? ' checked' : '').'></td></tr>';

print '</table>';
print '</div>';

print '<br><div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
