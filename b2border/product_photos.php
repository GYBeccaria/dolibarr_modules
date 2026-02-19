<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       b2border/product_photos.php
 * \brief      Tab for managing B2B/B2C product images
 */

// product_photos.php is at: <dolibarr_root>/custom/b2border/product_photos.php
// main.inc.php is at:       <dolibarr_root>/main.inc.php  (2 levels up from __DIR__)
$res = 0;
foreach (array(
	dirname(dirname(__DIR__)).'/main.inc.php',
	dirname(dirname(dirname(__DIR__))).'/main.inc.php',
	dirname(dirname(__DIR__)).'/htdocs/main.inc.php',
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('products', 'b2border@b2border'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

// Fetch product
$object = new Product($db);
if ($id > 0 || !empty($ref)) {
	$object->fetch($id, $ref);
}

// Security check
if ($object->id > 0) {
	if ($object->type == $object::TYPE_PRODUCT) {
		restrictedArea($user, 'produit', $object->id, 'product&product', '', '');
	}
	if ($object->type == $object::TYPE_SERVICE) {
		restrictedArea($user, 'service', $object->id, 'product&product', '', '');
	}
} else {
	$fieldvalue = (!empty($id) ? $id : (!empty($ref) ? $ref : ''));
	$fieldtype = (!empty($ref) ? 'ref' : 'rowid');
	restrictedArea($user, 'produit|service', $fieldvalue, 'product&product', '', '', $fieldtype);
}

$permissiontoadd = (($object->type == Product::TYPE_PRODUCT && $user->hasRight('produit', 'creer')) || ($object->type == Product::TYPE_SERVICE && $user->hasRight('service', 'creer')));

// Upload directory: standard Dolibarr product path
$upload_dir = $conf->product->multidir_output[$object->entity].'/'.get_exdir(0, 0, 0, 1, $object, 'product');


/*
 * Actions
 */

// Upload images
if ($action == 'upload' && $permissiontoadd) {
	if (!empty($_FILES['userfile']['name'][0])) {
		// Ensure upload dir exists
		dol_mkdir($upload_dir);

		// Sanitize ref for filename: lowercase, spaces to underscores, remove special chars
		$safe_ref = strtolower(trim($object->ref));
		$safe_ref = preg_replace('/\s+/', '_', $safe_ref);
		$safe_ref = preg_replace('/[^a-z0-9_\-]/', '', $safe_ref);

		// Find the next available number by scanning existing files
		$existing_photos = $object->liste_photos($upload_dir);
		$max_num = 0;
		foreach ($existing_photos as $photo_info) {
			if (preg_match('/^'.preg_quote($safe_ref, '/').'_(\d+)\./i', $photo_info['photo'], $m)) {
				$max_num = max($max_num, (int) $m[1]);
			}
		}

		$nb_uploaded = 0;
		$nb_files = count($_FILES['userfile']['name']);

		for ($i = 0; $i < $nb_files; $i++) {
			if ($_FILES['userfile']['error'][$i] == UPLOAD_ERR_OK) {
				$original_name = $_FILES['userfile']['name'][$i];
				$tmp_name = $_FILES['userfile']['tmp_name'][$i];

				// Check image format
				if (image_format_supported($original_name) < 0) {
					setEventMessages($langs->trans('B2BOrderPhotosErrorUpload').' ('.$original_name.'): format not supported', null, 'errors');
					continue;
				}

				// Get extension from original filename
				$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
				if ($ext == 'jpeg') {
					$ext = 'jpg';
				}

				// Build new filename: [REF]_[N].[ext]
				$max_num++;
				$new_filename = $safe_ref.'_'.$max_num.'.'.$ext;
				$dest_file = $upload_dir.'/'.$new_filename;

				// Move uploaded file
				$result = dol_move_uploaded_file($tmp_name, $dest_file, 1, 0, $_FILES['userfile']['error'][$i], 0, 'userfile', $upload_dir);

				if ($result > 0) {
					// Generate thumbnails (_small and _mini)
					vignette($dest_file, 160, 120, '_small', 80, 'thumbs');
					vignette($dest_file, 80, 60, '_mini', 80, 'thumbs');
					$nb_uploaded++;
				} else {
					setEventMessages($langs->trans('B2BOrderPhotosErrorUpload').' ('.$original_name.')', null, 'errors');
				}
			} elseif ($_FILES['userfile']['error'][$i] != UPLOAD_ERR_NO_FILE) {
				setEventMessages($langs->trans('B2BOrderPhotosErrorUpload').' ('.$_FILES['userfile']['name'][$i].')', null, 'errors');
			}
		}

		if ($nb_uploaded > 0) {
			setEventMessages($langs->trans('B2BOrderPhotosUploaded', $nb_uploaded), null, 'mesgs');
		}
	}

	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Delete image
if ($action == 'confirm_delete' && $confirm == 'yes' && $permissiontoadd) {
	$filetodelete = GETPOST('filetodelete', 'alpha');
	if ($filetodelete) {
		$filepath = $upload_dir.'/'.$filetodelete;
		if (dol_is_file($filepath)) {
			$object->delete_photo($filepath);
			setEventMessages($langs->trans('B2BOrderPhotosDeleted'), null, 'mesgs');
		}
	}

	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}


/*
 * View
 */

$title = $langs->trans('ProductServiceCard');
$shortlabel = dol_trunc($object->label, 16);
if ($object->type == Product::TYPE_PRODUCT) {
	$title = $langs->trans('Product')." ".$shortlabel." - ".$langs->trans('B2BOrderPhotosTab');
} else {
	$title = $langs->trans('Service')." ".$shortlabel." - ".$langs->trans('B2BOrderPhotosTab');
}

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-product page-b2bphotos');


if ($object->id > 0) {
	$head = product_prepare_head($object);
	$titre = $langs->trans("CardProduct".$object->type);
	$picto = ($object->type == Product::TYPE_SERVICE ? 'service' : 'product');

	print dol_get_fiche_head($head, 'b2bphotos', $titre, -1, $picto);

	$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1&type='.$object->type.'">'.$langs->trans("BackToList").'</a>';
	$object->next_prev_filter = "(te.fk_product_type:=:".((int) $object->type).")";

	$shownav = 1;
	if ($user->socid && !in_array('product', explode(',', getDolGlobalString('MAIN_MODULES_FOR_EXTERNAL')))) {
		$shownav = 0;
	}

	dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	$form = new Form($db);

	// Confirm delete dialog
	if ($action == 'delete') {
		$filetodelete = GETPOST('filetodelete', 'alpha');
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id.'&filetodelete='.urlencode($filetodelete),
			$langs->trans('Delete'),
			$langs->trans('B2BOrderPhotosConfirmDelete'),
			'confirm_delete',
			'',
			0,
			1
		);
	}

	// Section title
	print load_fiche_titre($langs->trans('B2BOrderPhotosTitle'), '', '');

	// Upload form
	if ($permissiontoadd) {
		print '<form enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" method="POST">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="upload">';

		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('B2BOrderPhotosUpload').'</td>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td>';
		print '<span class="opacitymedium small">'.$langs->trans('B2BOrderPhotosUploadHelp').'</span><br>';
		print '<input type="file" name="userfile[]" multiple accept="image/jpeg,image/png,image/webp" class="flat">';
		print ' &nbsp; <input type="submit" value="'.$langs->trans('Upload').'" class="button small">';
		print '</td>';
		print '</tr>';
		print '</table>';
		print '</div>';

		print '</form>';
		print '<br>';
	}

	// List existing photos
	$photos = $object->liste_photos($upload_dir);

	if (count($photos) > 0) {
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('Photo').'</td>';
		print '<td>'.$langs->trans('Name').'</td>';
		print '<td class="center">'.$langs->trans('Size').'</td>';
		if ($permissiontoadd) {
			print '<td class="center">'.$langs->trans('Action').'</td>';
		}
		print '</tr>';

		foreach ($photos as $photo_info) {
			$photo = $photo_info['photo'];
			$filepath = $upload_dir.'/'.$photo;
			$filesize = dol_filesize($filepath);

			print '<tr class="oddeven">';

			// Thumbnail
			print '<td style="width: 170px;">';
			if (!empty($photo_info['photo_vignette'])) {
				$thumb_url = DOL_URL_ROOT.'/viewimage.php?modulepart=product&entity='.$object->entity.'&file='.urlencode(get_exdir(0, 0, 0, 1, $object, 'product').$photo_info['photo_vignette']);
			} else {
				$thumb_url = DOL_URL_ROOT.'/viewimage.php?modulepart=product&entity='.$object->entity.'&file='.urlencode(get_exdir(0, 0, 0, 1, $object, 'product').$photo);
			}
			print '<a href="'.DOL_URL_ROOT.'/viewimage.php?modulepart=product&entity='.$object->entity.'&file='.urlencode(get_exdir(0, 0, 0, 1, $object, 'product').$photo).'" target="_blank" rel="noopener">';
			print '<img src="'.$thumb_url.'" alt="'.dol_escape_htmltag($photo).'" style="max-width:160px; max-height:120px; border:1px solid #ccc; border-radius:4px;">';
			print '</a>';
			print '</td>';

			// Filename
			print '<td>'.$photo.'</td>';

			// Size
			print '<td class="center">'.dol_print_size($filesize, 1, 1).'</td>';

			// Delete button
			if ($permissiontoadd) {
				print '<td class="center">';
				print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken().'&filetodelete='.urlencode($photo).'">';
				print img_picto($langs->trans('Delete'), 'delete');
				print '</a>';
				print '</td>';
			}

			print '</tr>';
		}

		print '</table>';
		print '</div>';
	} else {
		print '<div class="opacitymedium">'.$langs->trans('B2BOrderPhotosNone').'</div>';
	}

	print '</div>';

	print dol_get_fiche_end();
}

llxFooter();
$db->close();
