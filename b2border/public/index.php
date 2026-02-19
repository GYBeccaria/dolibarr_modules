<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/b2border/public/index.php
 * \brief   Entry point and router for B2B Order Portal
 */

include 'main.inc.php';

/** @var B2BOrderContext $context */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var DoliDB $db */

// Allowed controllers
$allowed_controllers = array('login', 'catalog', 'product', 'cart', 'checkout', 'confirmation');

// Determine controller
$controller = GETPOST('controller', 'alphanohtml');
if (empty($controller) || !in_array($controller, $allowed_controllers)) {
	$controller = $context->isAuthenticated() ? 'catalog' : 'login';
}

// If not authenticated and not on login page, redirect to login
if (!$context->isAuthenticated() && $controller != 'login') {
	$controller = 'login';
}

// If authenticated and on login page, redirect to catalog
if ($context->isAuthenticated() && $controller == 'login') {
	$controller = 'catalog';
}

// If authenticated, load session context
if ($context->isAuthenticated()) {
	$result = $context->loadSessionContext();
	if ($result < 0) {
		// Session broken, destroy and go to login
		$context->destroySession();
		if (session_status() === PHP_SESSION_NONE) {
			$prefix = dol_getprefix('');
			session_name('B2BORDER_SESSID_'.$prefix);
			session_start();
		}
		$controller = 'login';
	} else {
		// Set global $user so Dolibarr core classes (Product, Commande) work correctly
		$user = $context->logged_user;
	}
}

// Load controller
$controllerFile = dirname(__DIR__).'/controllers/'.$controller.'.controller.php';
if (!file_exists($controllerFile)) {
	http_response_code(404);
	die('Controller not found.');
}

require_once $controllerFile;
