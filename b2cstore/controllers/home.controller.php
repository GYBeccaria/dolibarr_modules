<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2cstore/controllers/home.controller.php
 * \brief   Frontpage controller — loads configurable sections and featured products
 */

/** @var B2CStoreContext $context */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var DoliDB $db */

// All section types
$sectionTypes = array('HERO', 'ABOUT', 'SERVICES', 'HISTORY', 'PRODUCTS_PREVIEW', 'CONTACT', 'B2B_LINK');

$sections = array();
foreach ($sectionTypes as $type) {
	$enabled = getDolGlobalInt('B2CSTORE_SECTION_'.$type.'_ENABLED', 1);
	if (!$enabled) continue;

	// B2B_LINK: only show if b2border module is active
	if ($type === 'B2B_LINK' && !isModEnabled('b2border')) continue;

	$title   = getDolGlobalString('B2CSTORE_SECTION_'.$type.'_TITLE', '');
	$content = getDolGlobalString('B2CSTORE_SECTION_'.$type.'_CONTENT', '');
	$image   = getDolGlobalString('B2CSTORE_SECTION_'.$type.'_IMAGE', '');
	$order   = getDolGlobalInt('B2CSTORE_SECTION_'.$type.'_ORDER', 50);
	$bg      = getDolGlobalString('B2CSTORE_SECTION_'.$type.'_BG_COLOR', '');
	$css     = getDolGlobalString('B2CSTORE_SECTION_'.$type.'_CSS_CLASS', '');

	$sections[$type] = array(
		'type'      => $type,
		'title'     => $title,
		'content'   => $content,
		'image'     => $image,
		'order'     => $order,
		'bg_color'  => $bg,
		'css_class' => $css,
	);
}

// Sort sections by order
usort($sections, function ($a, $b) { return $a['order'] - $b['order']; });

// Featured products for PRODUCTS_PREVIEW section
$featuredProducts = array();
if (isset($sections['PRODUCTS_PREVIEW']) || array_search('PRODUCTS_PREVIEW', array_column($sections, 'type')) !== false) {
	$count = getDolGlobalInt('B2CSTORE_PRODUCTS_PREVIEW_COUNT', 6);
	$price_level = $context->isAuthenticated() ? $context->getPriceLevel() : getDolGlobalInt('B2CSTORE_DEFAULT_PRICE_LEVEL', 1);
	$productHelper = new B2CStoreProduct($db);
	$featuredProducts = $productHelper->getFeaturedProducts($count, $price_level);
}

// B2B link URL (auto-detect b2border if no override)
$b2bLinkUrl = getDolGlobalString('B2CSTORE_B2B_LINK_URL', '');
if (empty($b2bLinkUrl) && isModEnabled('b2border')) {
	$b2bLinkUrl = dol_buildpath('/custom/b2border/public/index.php', 1);
}
$b2bLinkText = getDolGlobalString('B2CSTORE_B2B_LINK_TEXT', "Sei un'azienda? Accedi al portale B2B");

$title = getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'navbar.tpl.php';
include $tplDir.'home.tpl.php';
include $tplDir.'footer.tpl.php';
