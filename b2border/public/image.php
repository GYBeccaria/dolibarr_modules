<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/public/image.php
 * \brief   Product image proxy for B2B portal
 */

include 'main.inc.php';

/** @var B2BOrderContext $context */
/** @var Conf $conf */
/** @var DoliDB $db */

// Require authentication
if (!$context->isAuthenticated()) {
	http_response_code(403);
	exit;
}

$product_id = GETPOST('id', 'int');
$thumb = GETPOST('thumb', 'int'); // 1 for thumbnail

if ($product_id <= 0) {
	http_response_code(404);
	exit;
}

$product = new Product($db);
$result = $product->fetch($product_id);
if ($result <= 0 || $product->status != 1) {
	http_response_code(404);
	exit;
}

// Find image directory
$dir = $conf->product->multidir_output[$product->entity].'/'.$product->ref;
if (!is_dir($dir)) {
	$dir = $conf->product->multidir_output[$product->entity].'/'.get_exdir(0, 0, 0, 1, $product, 'product');
}

if (!is_dir($dir)) {
	http_response_code(404);
	exit;
}

// Find first image
$photo = '';
$handle = opendir($dir);
if ($handle) {
	$photos = array();
	while (($file = readdir($handle)) !== false) {
		if ($file == '.' || $file == '..' || $file == 'thumbs') {
			continue;
		}
		if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
			$photos[] = $file;
		}
	}
	closedir($handle);
	sort($photos);
	if (!empty($photos)) {
		$photo = $photos[0];
	}
}

if (empty($photo)) {
	http_response_code(404);
	exit;
}

// Use thumbnail if available and requested
$filepath = $dir.'/'.$photo;
if ($thumb) {
	$thumbdir = $dir.'/thumbs';
	// Look for mini or small thumbnail
	$thumbname = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '_small.\\1', $photo);
	if (file_exists($thumbdir.'/'.$thumbname)) {
		$filepath = $thumbdir.'/'.$thumbname;
	} else {
		$thumbname = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '_mini.\\1', $photo);
		if (file_exists($thumbdir.'/'.$thumbname)) {
			$filepath = $thumbdir.'/'.$thumbname;
		}
	}
}

if (!file_exists($filepath)) {
	http_response_code(404);
	exit;
}

// Detect content type
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
$mimeTypes = array(
	'jpg' => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'png' => 'image/png',
	'gif' => 'image/gif',
	'webp' => 'image/webp',
);
$contentType = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';

// Cache for 1 hour
header('Content-Type: '.$contentType);
header('Cache-Control: public, max-age=3600');
header('Content-Length: '.filesize($filepath));
readfile($filepath);
exit;
