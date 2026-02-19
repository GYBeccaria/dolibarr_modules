<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2cstore/controllers/cart.controller.php
 * \brief   Shopping cart CRUD controller
 */

/** @var B2CStoreContext $context */
/** @var Translate $langs */
/** @var DoliDB $db */

$price_level = $context->isAuthenticated() ? $context->getPriceLevel() : getDolGlobalInt('B2CSTORE_DEFAULT_PRICE_LEVEL', 1);
$cart = new B2CStoreCart($db);
$action = GETPOST('action', 'alphanohtml');

if (in_array($action, array('add', 'update', 'remove', 'clear'))) {
	$token = GETPOST('token', 'alphanohtml');
	if (!$context->verifyToken($token)) {
		$context->addError($langs->trans('B2CStoreErrorInvalidToken'));
	} else {
		$fk_product = GETPOST('product_id', 'int');

		if ($action === 'add') {
			$qty = max(1, (int) GETPOST('qty', 'int'));
			if ($cart->addItem($fk_product, $qty, $price_level) > 0) {
				$context->addMessage($langs->trans('B2CStoreProductAdded'));
			} else {
				$context->addError($langs->trans('B2CStoreProductNotAvailable'));
			}
		} elseif ($action === 'update') {
			$qty = (int) GETPOST('qty', 'int');
			$cart->updateItemQty($fk_product, $qty);
		} elseif ($action === 'remove') {
			$cart->removeItem($fk_product);
		} elseif ($action === 'clear') {
			$cart->clear();
		}
	}
}

$items  = $cart->getItems($price_level);
$totals = $cart->getTotals($price_level);

$title  = $langs->trans('B2CStoreCart').' - '.getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'navbar.tpl.php';
include $tplDir.'cart.tpl.php';
include $tplDir.'footer.tpl.php';
