<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2cstore/controllers/login.controller.php
 * \brief   Login controller for B2C Store portal
 */

/** @var B2CStoreContext $context */
/** @var Translate $langs */
/** @var DoliDB $db */

$action = GETPOST('action', 'alphanohtml');

if ($action === 'login') {
	$token = GETPOST('token', 'alphanohtml');
	if (!$context->verifyToken($token)) {
		$context->addError($langs->trans('B2CStoreErrorInvalidToken'));
	} else {
		$login    = GETPOST('login', 'alphanohtml');
		$password = GETPOST('password', 'none');

		if (empty($login) || empty($password)) {
			$context->addError($langs->trans('B2CStoreErrorLoginEmpty'));
		} else {
			$acc_id = $context->authenticate($login, $password);
			if ($acc_id > 0) {
				$context->doLogin($acc_id);
				// Redirect to previously requested page or home
				$redirect = !empty($_SESSION['b2cstore_redirect_after_login']) ? $_SESSION['b2cstore_redirect_after_login'] : 'home';
				unset($_SESSION['b2cstore_redirect_after_login']);
				header('Location: index.php?controller='.$redirect);
				exit;
			} elseif ($acc_id === -2) {
				$context->addError($langs->trans('B2CStoreErrorAccountPending'));
			} else {
				$context->addError($langs->trans('B2CStoreErrorLoginFailed'));
			}
		}
	}
}

$title  = $langs->trans('B2CStoreLogin').' - '.getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'navbar.tpl.php';
include $tplDir.'login.tpl.php';
include $tplDir.'footer.tpl.php';
