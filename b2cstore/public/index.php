<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/b2cstore/public/index.php
 * \brief   Entry point and router for B2C Store portal
 */

include 'main.inc.php';

/** @var B2CStoreContext $context */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var DoliDB $db */

// Allowed controllers
$allowed = array('home', 'register', 'login', 'catalog', 'product', 'cart', 'checkout', 'confirmation', 'contact', 'page');

$controller = GETPOST('controller', 'alphanohtml');
if (empty($controller) || !in_array($controller, $allowed)) {
	$controller = 'home';
}

// Guest browsing rules
$guestAllowed = $context->isGuestAllowed();

// Controllers accessible to guests
$guestControllers = array('home', 'login', 'register', 'contact', 'page');
if ($guestAllowed) {
	$guestControllers[] = 'catalog';
	$guestControllers[] = 'product';
}

// Authenticated redirect: login/register → home
if ($context->isAuthenticated() && in_array($controller, array('login', 'register'))) {
	$controller = 'home';
}

// Unauthenticated redirect for protected controllers
if (!$context->isAuthenticated() && !in_array($controller, $guestControllers)) {
	$_SESSION['b2cstore_redirect_after_login'] = $controller;
	$controller = 'login';
}

// Cart / checkout require login
if (in_array($controller, array('cart', 'checkout', 'confirmation')) && $context->cartRequiresLogin()) {
	$_SESSION['b2cstore_redirect_after_login'] = $controller;
	$controller = 'login';
}

// Load session context for authenticated users
if ($context->isAuthenticated()) {
	$result = $context->loadSessionContext();
	if ($result < 0) {
		$context->destroySession();
		if (session_status() === PHP_SESSION_NONE) {
			$_prefix = dol_getprefix('');
			session_name('B2CSTORE_SESSID_'.$_prefix);
			session_start();
		}
		$controller = 'login';
	} else {
		// Set global $user for Dolibarr core classes
		$user = $context->logged_user;
	}
}

// Dispatch
$controllerFile = dirname(__DIR__).'/controllers/'.$controller.'.controller.php';
if (!file_exists($controllerFile)) {
	http_response_code(404);
	die('Controller not found.');
}

require_once $controllerFile;
