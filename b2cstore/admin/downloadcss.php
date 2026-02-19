<?php
/* Copyright (C) 2025 Henaxis
 * Admin: Download CSS example / starter template.
 */

$res = 0;
if (!$res && file_exists('../../../main.inc.php'))             $res = @include '../../../main.inc.php';
if (!$res && file_exists('../../../../main.inc.php'))          $res = @include '../../../../main.inc.php';
if (!$res && file_exists('../../../../../main.inc.php'))       $res = @include '../../../../../main.inc.php';
if (!$res) die('Cannot find main.inc.php');

if (!$user->admin) accessforbidden();

// Send CSS file as download
$cssFile = __DIR__.'/../public/css/b2cstore.css';
if (!file_exists($cssFile)) {
	http_response_code(404);
	die('CSS file not found');
}

header('Content-Type: text/css');
header('Content-Disposition: attachment; filename="b2cstore-example.css"');
header('Content-Length: '.filesize($cssFile));
header('Cache-Control: no-cache');
readfile($cssFile);
exit;
