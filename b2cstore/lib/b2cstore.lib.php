<?php
/* Copyright (C) 2025 Henaxis
 * Library functions for B2CStore module (admin UI helpers).
 */

/**
 * Build admin tabs for B2CStore settings pages.
 *
 * @param  Translate $langs   Language object
 * @param  string    $active  Active tab key (setup|appearance|sections|pages)
 * @return array              Array of tab definitions for dol_get_tabs()
 */
function b2cstoreAdminPrepareHead($langs, $active = 'setup')
{
	$langs->load('b2cstore@b2cstore');

	$h   = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/custom/b2cstore/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('B2CStoreSetup');
	$head[$h][2] = 'setup';
	$h++;

	$head[$h][0] = dol_buildpath('/custom/b2cstore/admin/appearance.php', 1);
	$head[$h][1] = $langs->trans('B2CStoreAppearance');
	$head[$h][2] = 'appearance';
	$h++;

	$head[$h][0] = dol_buildpath('/custom/b2cstore/admin/sections.php', 1);
	$head[$h][1] = $langs->trans('B2CStoreSections');
	$head[$h][2] = 'sections';
	$h++;

	$head[$h][0] = dol_buildpath('/custom/b2cstore/admin/pages.php', 1);
	$head[$h][1] = $langs->trans('B2CStorePages');
	$head[$h][2] = 'pages';
	$h++;

	return $head;
}

/**
 * Build public store URL.
 *
 * @return string URL to the storefront index
 */
function b2cstore_build_url()
{
	return dol_buildpath('/custom/b2cstore/public/index.php', 1);
}

/**
 * Format a price for display in the storefront (wrapper).
 *
 * @param  float  $amount  Amount
 * @param  string $currency Currency symbol (defaults to $conf->currency)
 * @return string Formatted string
 */
function b2cstore_format_price($amount)
{
	return price($amount);
}
