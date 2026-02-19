<?php
/* Copyright (C) 2025 Henaxis
 * Admin: Static pages content editor (About, Services, History).
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
$page   = GETPOST('page', 'alpha');

// Pages mapped to section constants
$pageDefs = array(
	'about'    => array('slug' => 'about',    'label' => $langs->trans('B2CStorePageAbout'),    'section' => 'ABOUT'),
	'services' => array('slug' => 'services', 'label' => $langs->trans('B2CStorePageServices'), 'section' => 'SERVICES'),
	'history'  => array('slug' => 'history',  'label' => $langs->trans('B2CStorePageHistory'),  'section' => 'HISTORY'),
	'contact'  => array('slug' => 'contact',  'label' => $langs->trans('B2CStorePageContact'),  'section' => 'CONTACT'),
);

// Save page content
if ($action === 'updatepage' && $page && isset($pageDefs[$page])) {
	$section = $pageDefs[$page]['section'];
	$title   = GETPOST('title', 'alphanohtml');
	$content = GETPOST('content', 'nohtml');
	$image   = GETPOST('image', 'alphanohtml');
	dolibarr_set_const($db, 'B2CSTORE_SECTION_'.$section.'_TITLE',   $title,   'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'B2CSTORE_SECTION_'.$section.'_CONTENT', $content, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'B2CSTORE_SECTION_'.$section.'_IMAGE',   $image,   'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	$action = '';
}

// ----- OUTPUT -----
llxHeader('', $langs->trans('B2CStorePages'));

$head = b2cstoreAdminPrepareHead($langs, 'pages');
print dol_get_fiche_head($head, 'pages', $langs->trans('ModuleB2CStoreName'), -1, 'setup');

$storeBase = b2cstore_build_url();

print '<p>'.$langs->trans('B2CStorePagesIntro').'</p>';
print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans('Page').'</th><th>'.$langs->trans('Preview').'</th><th>'.$langs->trans('Edit').'</th></tr>';
foreach ($pageDefs as $slug => $def) {
	print '<tr class="oddeven"><td>'.$def['label'].'</td>';
	print '<td><a href="'.$storeBase.'?controller=page&slug='.urlencode($slug).'" target="_blank">'.$langs->trans('B2CStoreViewPage').'</a></td>';
	print '<td><a href="'.$_SERVER['PHP_SELF'].'?page='.urlencode($slug).'">'.$langs->trans('Edit').'</a></td>';
	print '</tr>';
}
print '</table>';

// Inline editor
if ($page && isset($pageDefs[$page])) {
	$section = $pageDefs[$page]['section'];
	$title   = dol_escape_htmltag(getDolGlobalString('B2CSTORE_SECTION_'.$section.'_TITLE'));
	$content = getDolGlobalString('B2CSTORE_SECTION_'.$section.'_CONTENT');
	$image   = dol_escape_htmltag(getDolGlobalString('B2CSTORE_SECTION_'.$section.'_IMAGE'));

	print '<br><h3>'.$langs->trans('EditPage').': '.$pageDefs[$page]['label'].'</h3>';
	print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="updatepage">';
	print '<input type="hidden" name="page" value="'.dol_escape_htmltag($page).'">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Field').'</th><th>'.$langs->trans('Value').'</th></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Title').'</td><td><input type="text" name="title" value="'.$title.'" class="minwidth400"></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('B2CStoreImage').' <small>('.$langs->trans('FileName').')</small></td><td><input type="text" name="image" value="'.$image.'" class="minwidth300"></td></tr>';
	print '<tr class="oddeven"><td valign="top">'.$langs->trans('Content').'</td><td>';
	print '<textarea name="content" rows="12" class="minwidth500" style="font-family:inherit">'.dol_escape_htmltag($content).'</textarea>';
	print '<br><small>'.$langs->trans('B2CStoreContentHtmlAllowed').'</small>';
	print '</td></tr>';
	print '</table>';
	print '<br><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
	print '</form>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
