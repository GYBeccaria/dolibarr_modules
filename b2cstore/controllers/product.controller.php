<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2cstore/controllers/product.controller.php
 * \brief   Single product detail controller
 */

/** @var B2CStoreContext $context */
/** @var Translate $langs */
/** @var DoliDB $db */

$product_id = GETPOST('id', 'int');
if ($product_id <= 0) {
	header('Location: index.php?controller=catalog');
	exit;
}

$price_level = $context->isAuthenticated() ? $context->getPriceLevel() : getDolGlobalInt('B2CSTORE_DEFAULT_PRICE_LEVEL', 1);
$productHelper = new B2CStoreProduct($db);
$product = $productHelper->getProductDetail($product_id, $price_level);

if (!$product) {
	header('Location: index.php?controller=catalog');
	exit;
}

$action = GETPOST('action', 'alphanohtml');
if ($action === 'addtocart') {
	if ($context->cartRequiresLogin()) {
		$context->addError($langs->trans('B2CStoreLoginRequired'));
	} else {
		$token = GETPOST('token', 'alphanohtml');
		if (!$context->verifyToken($token)) {
			$context->addError($langs->trans('B2CStoreErrorInvalidToken'));
		} else {
			$qty = max(1, (int) GETPOST('qty', 'int'));
			$cart = new B2CStoreCart($db);
			if ($cart->addItem($product_id, $qty, $price_level) > 0) {
				$context->addMessage($langs->trans('B2CStoreProductAdded'));
			} else {
				$context->addError($langs->trans('B2CStoreProductNotAvailable'));
			}
		}
	}
}

$pricesHidden    = $context->arePricesHidden();
$cartRequiresLogin = $context->cartRequiresLogin();

$title  = dol_escape_htmltag($product['label']).' - '.getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'navbar.tpl.php';
include $tplDir.'product.tpl.php';
include $tplDir.'footer.tpl.php';
