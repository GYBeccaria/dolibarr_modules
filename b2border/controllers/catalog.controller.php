<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/controllers/catalog.controller.php
 * \brief   Product catalog controller
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
 * Fetch data
 */
$search = GETPOST('search', 'alphanohtml');
$category_id = GETPOST('category', 'int');
$page = GETPOST('page', 'int');
if ($page < 1) {
	$page = 1;
}
$sortfield = GETPOST('sortfield', 'alphanohtml');
$sortorder = GETPOST('sortorder', 'alphanohtml');
if (empty($sortfield)) {
	$sortfield = 'p.label';
}

$limit = getDolGlobalInt('B2BORDER_PRODUCTS_PER_PAGE', 12);
$offset = ($page - 1) * $limit;

$productHelper = new B2BOrderProduct($db);
$result = $productHelper->getCatalogProducts(
	$context->getPriceLevel(),
	$limit,
	$offset,
	$search,
	$category_id,
	$sortfield,
	$sortorder
);

$products = $result['products'];
$totalProducts = $result['total'];
$totalPages = ceil($totalProducts / $limit);

$categories = $productHelper->getAvailableCategories();

/*
 * View
 */
$title = $langs->trans("B2BOrderCatalog").' - '.getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'menu.tpl.php';
include $tplDir.'catalog.tpl.php';
include $tplDir.'footer.tpl.php';
