<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2cstore/controllers/catalog.controller.php
 * \brief   Product catalog controller
 */

/** @var B2CStoreContext $context */
/** @var Translate $langs */
/** @var DoliDB $db */

$action = GETPOST('action', 'alphanohtml');

// Add to cart
if ($action === 'addtocart') {
	if ($context->cartRequiresLogin()) {
		$context->addError($langs->trans('B2CStoreLoginRequired'));
	} else {
		$token = GETPOST('token', 'alphanohtml');
		if (!$context->verifyToken($token)) {
			$context->addError($langs->trans('B2CStoreErrorInvalidToken'));
		} else {
			$fk_product = GETPOST('fk_product', 'int');
			$qty = max(1, (int) GETPOST('qty', 'int'));
			$price_level = $context->isAuthenticated() ? $context->getPriceLevel() : getDolGlobalInt('B2CSTORE_DEFAULT_PRICE_LEVEL', 1);

			$cart = new B2CStoreCart($db);
			if ($cart->addItem($fk_product, $qty, $price_level) > 0) {
				$context->addMessage($langs->trans('B2CStoreProductAdded'));
			} else {
				$context->addError($langs->trans('B2CStoreProductNotAvailable'));
			}
		}
	}
}

// Fetch catalog data
$search      = GETPOST('search', 'alphanohtml');
$category_id = GETPOST('category', 'int');
$page        = max(1, (int) GETPOST('page', 'int'));
$sortfield   = GETPOST('sortfield', 'alphanohtml') ?: 'p.label';
$sortorder   = GETPOST('sortorder', 'alphanohtml');

$limit  = getDolGlobalInt('B2CSTORE_PRODUCTS_PER_PAGE', 12);
$offset = ($page - 1) * $limit;
$price_level = $context->isAuthenticated() ? $context->getPriceLevel() : getDolGlobalInt('B2CSTORE_DEFAULT_PRICE_LEVEL', 1);

$productHelper  = new B2CStoreProduct($db);
$result         = $productHelper->getCatalogProducts($price_level, $limit, $offset, $search, $category_id, $sortfield, $sortorder);
$products       = $result['products'];
$totalProducts  = $result['total'];
$totalPages     = max(1, (int) ceil($totalProducts / $limit));
$categories     = $productHelper->getAvailableCategories();

$pricesHidden = $context->arePricesHidden();
$cartRequiresLogin = $context->cartRequiresLogin();

$title  = $langs->trans('B2CStoreCatalog').' - '.getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'navbar.tpl.php';
include $tplDir.'catalog.tpl.php';
include $tplDir.'footer.tpl.php';
