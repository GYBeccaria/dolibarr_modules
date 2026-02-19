<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2cstore/controllers/confirmation.controller.php
 * \brief   Order confirmation page controller
 */

/** @var B2CStoreContext $context */
/** @var Translate $langs */
/** @var DoliDB $db */

$order_id = GETPOST('id', 'int');
$order = null;

if ($order_id > 0) {
	$order = new Commande($db);
	if ($order->fetch($order_id) <= 0 || $order->module_source !== 'b2cstore') {
		$order = null;
	} elseif ($order->socid != $context->logged_thirdparty->id) {
		$order = null; // Security: must be the customer's own order
	}
}

if (!$order) {
	header('Location: index.php?controller=home');
	exit;
}

$title  = $langs->trans('B2CStoreConfirmation').' - '.getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'navbar.tpl.php';
include $tplDir.'confirmation.tpl.php';
include $tplDir.'footer.tpl.php';
