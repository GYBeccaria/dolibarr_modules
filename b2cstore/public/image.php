<?php
/* Copyright (C) 2025 Henaxis
 * Secure proxy for product images. Accessible to guests if guest browsing is enabled.
 */

include 'main.inc.php';

/** @var B2CStoreContext $context */
/** @var Conf $conf */

// If guest browsing is disabled, require login
if (!$context->isGuestAllowed() && !$context->isAuthenticated()) {
	http_response_code(403);
	exit;
}

$product_id = GETPOST('id', 'int');
$thumb      = GETPOST('thumb', 'int');

if ($product_id <= 0) { http_response_code(404); exit; }

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

$product = new Product($db);
if ($product->fetch($product_id) <= 0 || $product->status != 1) {
	http_response_code(404);
	exit;
}

// Verify product is in allowed catalog
$productHelper = new B2CStoreProduct($db);
$detail = $productHelper->getProductDetail($product_id, 1);
if (!$detail) { http_response_code(404); exit; }

$dir = $conf->product->multidir_output[$product->entity].'/'.$product->ref;
if (!is_dir($dir)) {
	$dir = $conf->product->multidir_output[$product->entity].'/'.get_exdir(0, 0, 0, 1, $product, 'product');
}

// Find the first image
$found = '';
if (is_dir($dir)) {
	$handle = opendir($dir);
	while ($handle && ($file = readdir($handle)) !== false) {
		if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
			$found = $file;
			break;
		}
	}
	if ($handle) closedir($handle);
}

if (empty($found)) { http_response_code(404); exit; }

// Serve thumbnail if requested
if ($thumb) {
	$thumbDir = $dir.'/thumbs/';
	$thumbFile = $thumbDir.preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '_small.jpg', $found);
	if (!file_exists($thumbFile)) {
		$thumbFile = $thumbDir.preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '_mini.jpg', $found);
	}
	if (file_exists($thumbFile)) {
		$found = basename($thumbFile);
		$dir = $thumbDir;
	}
}

$path = rtrim($dir, '/').'/'.$found;
if (!file_exists($path)) { http_response_code(404); exit; }

$ext = strtolower(pathinfo($found, PATHINFO_EXTENSION));
$mimes = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp');
$mime = isset($mimes[$ext]) ? $mimes[$ext] : 'image/jpeg';

header('Content-Type: '.$mime);
header('Cache-Control: max-age=3600, public');
header('Content-Length: '.filesize($path));
readfile($path);
exit;
