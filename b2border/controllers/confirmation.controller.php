<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/controllers/confirmation.controller.php
 * \brief   Order confirmation controller
 */

/** @var B2BOrderContext $context */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var DoliDB $db */

$order_id = GETPOST('id', 'int');

if ($order_id <= 0) {
	header('Location: index.php?controller=catalog');
	exit;
}

// Load order and verify it belongs to this customer
$order = new Commande($db);
$result = $order->fetch($order_id);

if ($result <= 0 || $order->socid != $context->logged_thirdparty->id) {
	$context->addError($langs->trans("B2BOrderErrorOrderNotFound"));
	header('Location: index.php?controller=catalog');
	exit;
}

/*
 * View
 */
$title = $langs->trans("B2BOrderConfirmation").' - '.getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'menu.tpl.php';
include $tplDir.'confirmation.tpl.php';
include $tplDir.'footer.tpl.php';
