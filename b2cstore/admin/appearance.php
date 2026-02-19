<?php
/* Copyright (C) 2025 Henaxis
 * Admin: Appearance (logo, favicon, colors, fonts, custom CSS).
 */

$res = 0;
if (!$res && file_exists('../../../main.inc.php'))             $res = @include '../../../main.inc.php';
if (!$res && file_exists('../../../../main.inc.php'))          $res = @include '../../../../main.inc.php';
if (!$res && file_exists('../../../../../main.inc.php'))       $res = @include '../../../../../main.inc.php';
if (!$res) die('Cannot find main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/../lib/b2cstore.lib.php';

if (!$user->admin) accessforbidden();

$langs->loadLangs(array('admin', 'b2cstore@b2cstore'));

$action = GETPOST('action', 'aZ09');
$uploadDir = $conf->b2cstore->dir_output ?? (DOL_DATA_ROOT.'/b2cstore');
dol_mkdir($uploadDir);

// Handle file uploads
if ($action === 'uploadfile') {
	$filekey = GETPOST('filekey', 'alpha');
	$allowed = array('logo' => 'B2CSTORE_LOGO', 'favicon' => 'B2CSTORE_FAVICON');
	if (isset($allowed[$filekey]) && !empty($_FILES[$filekey]['name'])) {
		$ext = strtolower(pathinfo($_FILES[$filekey]['name'], PATHINFO_EXTENSION));
		$extsOk = array('png','jpg','jpeg','gif','svg','ico','webp');
		if (in_array($ext, $extsOk)) {
			$dest = $uploadDir.'/'.$filekey.'.'.$ext;
			if (move_uploaded_file($_FILES[$filekey]['tmp_name'], $dest)) {
				dolibarr_set_const($db, $allowed[$filekey], $filekey.'.'.$ext, 'chaine', 0, '', $conf->entity);
				setEventMessages($langs->trans('FileUploaded'), null, 'mesgs');
			} else {
				setEventMessages($langs->trans('ErrorUpload'), null, 'errors');
			}
		} else {
			setEventMessages($langs->trans('ErrorBadFileFormat'), null, 'errors');
		}
	}
	$action = '';
}

// Save text/color settings
if ($action === 'update') {
	$params = array(
		'B2CSTORE_COLOR_PRIMARY'   => 'color',
		'B2CSTORE_COLOR_SECONDARY' => 'color',
		'B2CSTORE_COLOR_ACCENT'    => 'color',
		'B2CSTORE_COLOR_BG'        => 'color',
		'B2CSTORE_COLOR_TEXT'      => 'color',
		'B2CSTORE_FONT_FAMILY'     => 'alphanohtml',
		'B2CSTORE_FOOTER_TEXT'     => 'alphanohtml',
		'B2CSTORE_POWERED_BY'      => 'chk',
		'B2CSTORE_CUSTOM_CSS'      => 'nohtml',
	);
	foreach ($params as $key => $type) {
		if ($type === 'chk') {
			$val = GETPOST($key, 'alpha') ? '1' : '0';
		} elseif ($type === 'color') {
			$raw = GETPOST($key, 'alpha');
			$val = preg_match('/^#[0-9a-fA-F]{3,8}$/', $raw) ? $raw : '';
		} else {
			$val = GETPOST($key, $type);
		}
		dolibarr_set_const($db, $key, $val, 'chaine', 0, '', $conf->entity);
	}
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	$action = '';
}

// ----- OUTPUT -----
llxHeader('', $langs->trans('B2CStoreAppearance'));

$head = b2cstoreAdminPrepareHead($langs, 'appearance');
print dol_get_fiche_head($head, 'appearance', $langs->trans('ModuleB2CStoreName'), -1, 'setup');

// Color preview block
$primaryColor   = dol_escape_htmltag(getDolGlobalString('B2CSTORE_COLOR_PRIMARY',   '#2563eb'));
$secondaryColor = dol_escape_htmltag(getDolGlobalString('B2CSTORE_COLOR_SECONDARY', '#64748b'));
$accentColor    = dol_escape_htmltag(getDolGlobalString('B2CSTORE_COLOR_ACCENT',    '#f97316'));
$bgColor        = dol_escape_htmltag(getDolGlobalString('B2CSTORE_COLOR_BG',        '#ffffff'));
$textColor      = dol_escape_htmltag(getDolGlobalString('B2CSTORE_COLOR_TEXT',      '#1a1a2e'));

print '<style>
.b2cs-preview { display:flex; gap:1rem; flex-wrap:wrap; margin:1rem 0 1.5rem; }
.b2cs-preview__swatch { width:60px; height:60px; border-radius:8px; border:1px solid #ccc; display:flex; align-items:flex-end; justify-content:center; padding-bottom:4px; font-size:.65rem; font-weight:700; color:#333; }
</style>';
print '<div class="b2cs-preview">';
foreach (array('Primary'=>$primaryColor,'Secondary'=>$secondaryColor,'Accent'=>$accentColor,'Bg'=>$bgColor,'Text'=>$textColor) as $lbl=>$col) {
	print '<div class="b2cs-preview__swatch" style="background:'.$col.'">'.$lbl.'</div>';
}
print '</div>';

// Upload form
print '<form method="post" action="'.$_SERVER['PHP_SELF'].'" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="uploadfile">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';
foreach (array('logo'=>$langs->trans('B2CStoreLogo'),'favicon'=>$langs->trans('B2CStoreFavicon')) as $fk=>$lbl) {
	$current = getDolGlobalString('B2CSTORE_'.strtoupper($fk));
	print '<tr class="oddeven"><td>'.$lbl.'</td><td>';
	if ($current) print dol_escape_htmltag($current).' &nbsp; ';
	print '<input type="file" name="'.$fk.'" accept="image/*">';
	print '<input type="hidden" name="filekey" value="'.$fk.'">';
	print '<button type="submit" class="button smallpadding">'.$langs->trans('Upload').'</button>';
	print '</td></tr>';
}
print '</table></form>';

// Main form
print '<br><form method="post" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';

$colorFields = array(
	'B2CSTORE_COLOR_PRIMARY'   => array($langs->trans('B2CStoreColorPrimary'),   '#2563eb'),
	'B2CSTORE_COLOR_SECONDARY' => array($langs->trans('B2CStoreColorSecondary'), '#64748b'),
	'B2CSTORE_COLOR_ACCENT'    => array($langs->trans('B2CStoreColorAccent'),    '#f97316'),
	'B2CSTORE_COLOR_BG'        => array($langs->trans('B2CStoreColorBg'),        '#ffffff'),
	'B2CSTORE_COLOR_TEXT'      => array($langs->trans('B2CStoreColorText'),       '#1a1a2e'),
);
foreach ($colorFields as $key => list($label, $default)) {
	$val = dol_escape_htmltag(getDolGlobalString($key, $default));
	print '<tr class="oddeven"><td>'.$label.'</td>';
	print '<td><input type="color" name="'.$key.'" value="'.$val.'"> <input type="text" name="'.$key.'" id="'.$key.'_txt" value="'.$val.'" size="10"></td></tr>';
}

$txtFields = array(
	'B2CSTORE_FONT_FAMILY' => array($langs->trans('B2CStoreFontFamily'), 'alphanohtml', ''),
	'B2CSTORE_FOOTER_TEXT' => array($langs->trans('B2CStoreFooterText'), 'alphanohtml', ''),
);
foreach ($txtFields as $key => list($label, $filter, $default)) {
	$val = dol_escape_htmltag(getDolGlobalString($key, $default));
	print '<tr class="oddeven"><td>'.$label.'</td>';
	print '<td><input type="text" name="'.$key.'" value="'.$val.'" class="minwidth300"></td></tr>';
}

$poweredBy = getDolGlobalString('B2CSTORE_POWERED_BY', '1');
print '<tr class="oddeven"><td>'.$langs->trans('B2CStorePoweredBy').'</td>';
print '<td><input type="checkbox" name="B2CSTORE_POWERED_BY" value="1"'.($poweredBy ? ' checked' : '').'></td></tr>';

$customCss = getDolGlobalString('B2CSTORE_CUSTOM_CSS');
print '<tr class="oddeven"><td>'.$langs->trans('B2CStoreCustomCss').'</td>';
print '<td><textarea name="B2CSTORE_CUSTOM_CSS" rows="8" class="minwidth500" style="font-family:monospace">'.dol_escape_htmltag($customCss).'</textarea></td></tr>';

print '</table>';
print '<br><div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div></form>';

print '<br><p><a href="'.dol_buildpath('/custom/b2cstore/admin/downloadcss.php', 1).'" class="button">'.$langs->trans('B2CStoreDownloadCssExample').'</a></p>';

print dol_get_fiche_end();
llxFooter();
$db->close();
