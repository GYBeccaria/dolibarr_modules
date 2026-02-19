<?php
/* Copyright (C) 2025 Henaxis
 * Serves branding files (logo, favicon, section images) without authentication.
 */

define('NOLOGIN', 1); define('NOREQUIREUSER', 1); define('NOREQUIREMENU', 1);
define('NOREQUIRESOC', 1); define('NOIPCHECK', 1); define('NOCSRFCHECK', 1);
define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);

if (!function_exists('dol_getprefix')) {
	function dol_getprefix($m = '') { global $dolibarr_main_instance_unique_id, $dolibarr_main_cookie_cryptkey;
		$u = empty($dolibarr_main_instance_unique_id) ? ($dolibarr_main_cookie_cryptkey ?? '') : $dolibarr_main_instance_unique_id;
		return $u ? sha1('b2cstore'.$u) : sha1('b2cstore'.$_SERVER['SERVER_NAME'].$_SERVER['DOCUMENT_ROOT']); }
}

$res = 0;
foreach (array(dirname(dirname(dirname(__DIR__))).'/main.inc.php', dirname(dirname(dirname(dirname(__DIR__)))).'/main.inc.php') as $p) {
	if (file_exists($p)) { $res = @include $p; if ($res) break; }
}
if (!$res) { http_response_code(500); exit; }

$f = GETPOST('f', 'alphanohtml');
if (empty($f)) { http_response_code(404); exit; }

// Map aliases
$aliases = array('logo' => getDolGlobalString('B2CSTORE_LOGO'), 'favicon' => getDolGlobalString('B2CSTORE_FAVICON'));
$filename = isset($aliases[$f]) ? $aliases[$f] : $f;

// Safety: strip to basename only
$filename = basename($filename);
if (empty($filename)) { http_response_code(404); exit; }

$dir = $conf->global->DOL_DATA_ROOT.'/b2cstore/';
$path = $dir.$filename;

if (!file_exists($path)) { http_response_code(404); exit; }

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimes = array(
	'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
	'gif' => 'image/gif',  'webp' => 'image/webp', 'svg' => 'image/svg+xml',
	'ico' => 'image/x-icon',
);
$mime = isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';

header('Content-Type: '.$mime);
header('Cache-Control: max-age=3600, public');
header('Content-Length: '.filesize($path));
readfile($path);
exit;
