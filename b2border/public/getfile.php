<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/public/getfile.php
 * \brief   Serve b2border branding files (logo, favicon) without authentication
 */

define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOREQUIREUSER', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIRESOC', 1);
define('NOIPCHECK', 1);
define('NOBROWSERNOTIF', 1);
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);

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
	http_response_code(500);
	exit;
}

// Only allow 'logo' or 'favicon'
$which = GETPOST('f', 'alphanohtml');
if (!in_array($which, array('logo', 'favicon'))) {
	http_response_code(400);
	exit;
}

if ($which == 'logo') {
	$filename = getDolGlobalString('B2BORDER_LOGO');
	$constName = 'B2BORDER_LOGO';
} else {
	$filename = getDolGlobalString('B2BORDER_FAVICON');
	$constName = 'B2BORDER_FAVICON';
}

if (empty($filename)) {
	http_response_code(404);
	exit;
}

// Validate filename (no path traversal)
$filename = basename($filename);
$uploadDir = DOL_DATA_ROOT.'/b2border/';
$filepath = $uploadDir.$filename;

if (!file_exists($filepath) || !is_file($filepath)) {
	http_response_code(404);
	exit;
}

// Determine MIME type from extension
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeTypes = array(
	'jpg'  => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'png'  => 'image/png',
	'gif'  => 'image/gif',
	'webp' => 'image/webp',
	'svg'  => 'image/svg+xml',
	'ico'  => 'image/x-icon',
);
$mime = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';

// Cache for 1 hour
header('Content-Type: '.$mime);
header('Cache-Control: public, max-age=3600');
header('Content-Length: '.filesize($filepath));
readfile($filepath);
exit;
