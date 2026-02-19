<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2cstore/controllers/page.controller.php
 * \brief   Static pages controller (chi siamo, storia, servizi)
 */

/** @var B2CStoreContext $context */
/** @var Translate $langs */
/** @var DoliDB $db */

$slug = GETPOST('slug', 'alphanohtml');

// Map slug to section constant prefix
$validSlugs = array(
	'about'    => 'ABOUT',
	'services' => 'SERVICES',
	'history'  => 'HISTORY',
	'contact'  => 'CONTACT',
);

if (!$slug || !isset($validSlugs[$slug])) {
	header('Location: index.php?controller=home');
	exit;
}

$sectionKey = $validSlugs[$slug];
$pageTitle  = getDolGlobalString('B2CSTORE_SECTION_'.$sectionKey.'_TITLE', ucfirst($slug));
$pageContent = getDolGlobalString('B2CSTORE_SECTION_'.$sectionKey.'_CONTENT', '');
$pageImage  = getDolGlobalString('B2CSTORE_SECTION_'.$sectionKey.'_IMAGE', '');

$title  = dol_escape_htmltag($pageTitle).' - '.getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'navbar.tpl.php';
include $tplDir.'page_static.tpl.php';
include $tplDir.'footer.tpl.php';
