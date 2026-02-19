<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/b2border/public/main.inc.php
 * \brief   Bootstrap for B2B Order Portal public pages
 */

// Prevent direct access to Dolibarr login
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', 1);
}
if (!defined('EVEN_IF_ONLY_LOGIN_ALLOWED')) {
	define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1);
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', 1);
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1); // We handle CSRF ourselves
}

// Override dol_getprefix to get a unique session name for B2B portal
if (!function_exists('dol_getprefix')) {
	function dol_getprefix($mode = '')
	{
		global $dolibarr_main_instance_unique_id, $dolibarr_main_cookie_cryptkey;
		$tmp_instance_unique_id = empty($dolibarr_main_instance_unique_id) ?
			(empty($dolibarr_main_cookie_cryptkey) ? '' : $dolibarr_main_cookie_cryptkey) :
			$dolibarr_main_instance_unique_id;
		if (!empty($tmp_instance_unique_id)) {
			return sha1('b2border'.$tmp_instance_unique_id);
		} else {
			return sha1('b2border'.$_SERVER['SERVER_NAME'].$_SERVER['DOCUMENT_ROOT']);
		}
	}
}

// Include Dolibarr main - this gives us $db, $conf, $langs, $mysoc
// public/main.inc.php is at: <dolibarr_root>/custom/b2border/public/main.inc.php
// main.inc.php is at:        <dolibarr_root>/main.inc.php  (3 levels up from __DIR__)
$res = 0;
foreach (array(
	dirname(dirname(dirname(__DIR__))).'/main.inc.php',           // standard layout
	dirname(dirname(dirname(dirname(__DIR__)))).'/main.inc.php',  // custom outside htdocs
	dirname(dirname(dirname(__DIR__))).'/htdocs/main.inc.php',    // old-style htdocs subdir
) as $_mainPath) {
	if (file_exists($_mainPath)) {
		$res = @include $_mainPath;
		if ($res) {
			break;
		}
	}
}

if (!$res) {
	die('Include of Dolibarr main.inc.php failed. Check path.');
}

// Now we have $db, $conf, $langs, $mysoc, $hookmanager
// Include our module classes
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';

// Module files
$customDir = dirname(__DIR__);
require_once $customDir.'/class/b2border_context.class.php';
require_once $customDir.'/class/b2border_cart.class.php';
require_once $customDir.'/class/b2border_product.class.php';
require_once $customDir.'/lib/b2border.lib.php';

// Check module is enabled
if (empty($conf->b2border) || empty($conf->b2border->enabled)) {
	http_response_code(403);
	die('Module B2B Order Portal is not activated.');
}

// Init session with unique prefix
$prefix = dol_getprefix('');
$sessionname = 'B2BORDER_SESSID_'.$prefix;

if (session_status() === PHP_SESSION_NONE) {
	session_name($sessionname);
	session_start();
}

// Setup language
if (!is_object($langs)) {
	include_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
	$langs = new Translate("", $conf);
}
$langcode = GETPOST('lang', 'aZ09', 1);
if (empty($langcode)) {
	$langcode = getDolGlobalString('MAIN_LANG_DEFAULT', 'auto');
}
$langs->setDefaultLang($langcode);
$langs->loadLangs(array('main', 'products', 'orders', 'companies', 'bills'));
$langs->load('b2border@b2border');

// Init context singleton
$context = B2BOrderContext::getInstance();
$context->setDb($db);
