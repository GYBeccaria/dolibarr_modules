<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/controllers/cart.controller.php
 * \brief   Cart controller
 */

/** @var B2BOrderContext $context */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var DoliDB $db */

$cart = new B2BOrderCart($db);
$price_level = $context->getPriceLevel();

/*
 * Actions
 */
$action = GETPOST('action', 'alphanohtml');

if (!empty($action)) {
	$token = GETPOST('token', 'alphanohtml');
	if (!$context->verifyToken($token)) {
		$context->addError($langs->trans("B2BOrderErrorInvalidToken"));
	} else {
		switch ($action) {
			case 'update':
				$fk_product = GETPOST('fk_product', 'int');
				$qty = GETPOST('qty', 'int');
				$cart->updateItemQty($fk_product, $qty);
				$context->addMessage($langs->trans("B2BOrderCartUpdated"));
				break;

			case 'remove':
				$fk_product = GETPOST('fk_product', 'int');
				$cart->removeItem($fk_product);
				$context->addMessage($langs->trans("B2BOrderItemRemoved"));
				break;

			case 'clear':
				$cart->clear();
				$context->addMessage($langs->trans("B2BOrderCartCleared"));
				break;

			case 'updateall':
				// Bulk update from cart form
				$qtys = GETPOST('qtys', 'array');
				if (is_array($qtys)) {
					foreach ($qtys as $fk_product => $qty) {
						$cart->updateItemQty((int) $fk_product, (int) $qty);
					}
				}
				$context->addMessage($langs->trans("B2BOrderCartUpdated"));
				break;
		}
	}
}

/*
 * Fetch data
 */
$items = $cart->getItems($price_level);
$totals = $cart->getTotals($price_level);

/*
 * View
 */
$title = $langs->trans("B2BOrderCart").' - '.getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'menu.tpl.php';
include $tplDir.'cart.tpl.php';
include $tplDir.'footer.tpl.php';
