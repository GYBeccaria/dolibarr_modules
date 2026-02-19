<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/controllers/product.controller.php
 * \brief   Product detail controller
 */

/** @var B2BOrderContext $context */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var DoliDB $db */

/*
 * Action - handle add to cart
 */
$action = GETPOST('action', 'alphanohtml');

if ($action == 'addtocart') {
	$token = GETPOST('token', 'alphanohtml');
	if ($context->verifyToken($token)) {
		$fk_product = GETPOST('fk_product', 'int');
		$qty = GETPOST('qty', 'int');
		if ($qty < 1) {
			$qty = 1;
		}

		$cart = new B2BOrderCart($db);
		$result = $cart->addItem($fk_product, $qty, $context->getPriceLevel());
		if ($result > 0) {
			$context->addMessage($langs->trans("B2BOrderProductAdded"));
		} else {
			$context->addError($langs->trans("B2BOrderProductNotAvailable"));
		}
	} else {
		$context->addError($langs->trans("B2BOrderErrorInvalidToken"));
	}
}

/*
 * Fetch product
 */
$product_id = GETPOST('id', 'int');

if ($product_id <= 0) {
	header('Location: index.php?controller=catalog');
	exit;
}

$productHelper = new B2BOrderProduct($db);
$productData = $productHelper->getProductDetail($product_id, $context->getPriceLevel());

if (!$productData) {
	header('Location: index.php?controller=catalog');
	exit;
}

/*
 * View
 */
$title = dol_escape_htmltag($productData['label']).' - '.getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'menu.tpl.php';
include $tplDir.'product.tpl.php';
include $tplDir.'footer.tpl.php';
