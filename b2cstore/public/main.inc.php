<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/b2cstore/public/main.inc.php
 * \brief   Bootstrap for B2C Store public pages
 */

// Bypass Dolibarr authentication — portal manages its own session
if (!defined('NOLOGIN'))             { define('NOLOGIN', 1); }
if (!defined('NOREQUIREUSER'))       { define('NOREQUIREUSER', 1); }
if (!defined('NOREQUIREMENU'))       { define('NOREQUIREMENU', 1); }
if (!defined('NOREQUIRESOC'))        { define('NOREQUIRESOC', 1); }
if (!defined('EVEN_IF_ONLY_LOGIN_ALLOWED')) { define('EVEN_IF_ONLY_LOGIN_ALLOWED', 1); }
if (!defined('NOIPCHECK'))           { define('NOIPCHECK', 1); }
if (!defined('NOCSRFCHECK'))         { define('NOCSRFCHECK', 1); }

// Override dol_getprefix BEFORE including main.inc.php to get unique session name
if (!function_exists('dol_getprefix')) {
	function dol_getprefix($mode = '')
	{
		global $dolibarr_main_instance_unique_id, $dolibarr_main_cookie_cryptkey;
		$uid = empty($dolibarr_main_instance_unique_id)
			? (empty($dolibarr_main_cookie_cryptkey) ? '' : $dolibarr_main_cookie_cryptkey)
			: $dolibarr_main_instance_unique_id;
		return $uid ? sha1('b2cstore'.$uid) : sha1('b2cstore'.$_SERVER['SERVER_NAME'].$_SERVER['DOCUMENT_ROOT']);
	}
}

// Load Dolibarr environment — try standard paths
$res = 0;
foreach (array(
	dirname(dirname(dirname(__DIR__))).'/main.inc.php',
	dirname(dirname(dirname(dirname(__DIR__)))).'/main.inc.php',
	dirname(dirname(dirname(__DIR__))).'/htdocs/main.inc.php',
) as $_p) {
	if (file_exists($_p)) {
		$res = @include $_p;
		if ($res) { break; }
	}
}
if (!$res) {
	die('Include of Dolibarr main.inc.php failed. Check path.');
}

// Core Dolibarr classes needed by portal
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';

// Module classes
$_customDir = dirname(__DIR__);
require_once $_customDir.'/class/b2cstore_context.class.php';
require_once $_customDir.'/class/b2cstore_cart.class.php';
require_once $_customDir.'/class/b2cstore_product.class.php';
require_once $_customDir.'/class/b2cstore_customer.class.php';
require_once $_customDir.'/lib/b2cstore.lib.php';

// Check module is enabled
if (empty($conf->b2cstore) || empty($conf->b2cstore->enabled)) {
	http_response_code(403);
	die('Module B2C Store is not activated.');
}

// Start session with unique prefix (separate from Dolibarr backend)
$_prefix = dol_getprefix('');
$_sessionName = 'B2CSTORE_SESSID_'.$_prefix;
if (session_status() === PHP_SESSION_NONE) {
	session_name($_sessionName);
	session_start();
}

// Languages
if (!is_object($langs)) {
	include_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
	$langs = new Translate('', $conf);
}
$_langcode = GETPOST('lang', 'aZ09', 1);
if (empty($_langcode)) {
	$_langcode = getDolGlobalString('MAIN_LANG_DEFAULT', 'auto');
}
$langs->setDefaultLang($_langcode);
$langs->loadLangs(array('main', 'products', 'orders', 'companies', 'bills'));
$langs->load('b2cstore@b2cstore');

// Initialize context singleton
$context = B2CStoreContext::getInstance();
$context->setDb($db);
