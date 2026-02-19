<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/controllers/login.controller.php
 * \brief   Login controller for B2B Order Portal
 */

/** @var B2BOrderContext $context */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var DoliDB $db */

/*
 * Action
 */
$action = GETPOST('action', 'alphanohtml');

if ($action == 'login') {
	$token = GETPOST('token', 'alphanohtml');
	if (!$context->verifyToken($token)) {
		$context->addError($langs->trans("B2BOrderErrorInvalidToken"));
	} else {
		$login = GETPOST('login', 'alphanohtml');
		$password = GETPOST('password', 'none');

		$error = 0;

		if (empty($login)) {
			$context->addError($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Login")));
			$error++;
		}
		if (empty($password)) {
			$context->addError($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Password")));
			$error++;
		}

		if (!$error) {
			$account_id = $context->getThirdPartyAccountFromLogin($login, $password);
			if ($account_id > 0) {
				$context->doLogin($account_id);
				// Redirect to catalog
				header('Location: index.php?controller=catalog');
				exit;
			} else {
				$context->addError($langs->trans("B2BOrderErrorAuthentication"));
			}
		}
	}
}

/*
 * View
 */
$title = getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'login.tpl.php';
include $tplDir.'footer.tpl.php';
