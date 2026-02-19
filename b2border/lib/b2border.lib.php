<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Prepare admin pages header
 *
 * @return array Array of tabs
 */
function b2borderAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("b2border@b2border");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/custom/b2border/admin/setup.php', 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/custom/b2border/admin/appearance.php', 1);
	$head[$h][1] = $langs->trans("B2BOrderAppearance");
	$head[$h][2] = 'appearance';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'b2border@b2border');

	return $head;
}

/**
 * Build portal URL
 *
 * @param	string	$controller		Controller name
 * @param	array	$params			URL parameters
 * @return	string					URL
 */
function b2border_build_url($controller = '', $params = array())
{
	$url = dol_buildpath('/custom/b2border/public/index.php', 2);
	$queryParts = array();
	if ($controller) {
		$queryParts[] = 'controller='.urlencode($controller);
	}
	foreach ($params as $k => $v) {
		$queryParts[] = urlencode($k).'='.urlencode($v);
	}
	if (!empty($queryParts)) {
		$url .= '?'.implode('&', $queryParts);
	}
	return $url;
}

/**
 * Format price for display in portal
 *
 * @param	float	$amount		Amount
 * @param	string	$currency	Currency code
 * @return	string				Formatted price
 */
function b2border_format_price($amount, $currency = '')
{
	global $conf, $langs;
	return price($amount, 0, $langs, 1, -1, -1, ($currency ? $currency : $conf->currency));
}
