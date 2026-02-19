<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modB2BOrder extends DolibarrModules
{
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 580300;
		$this->family = "portal";
		$this->module_position = '91';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Portale self-service B2B: catalogo prodotti, carrello e invio ordini direttamente su Dolibarr";
		$this->descriptionlong = "Modulo b2border (ID 580300). Entita gestite: clienti (llx_societe_account campi login+pass_crypted, site=dolibarr_portal), prodotti (llx_product tosell=1), carrello (sessione PHP B2BORDER_SESSID_), ordini bozza (llx_commande fk_statut=0 module_source=b2border). Routing portale: parametro controller= (valori: login,catalog,product,cart,checkout,confirmation). Operazioni cliente: login (params: login,password), sfoglia catalogo (params: page,search,category), dettaglio prodotto (param: id), aggiungi/aggiorna/rimuovi carrello, checkout, invio ordine (action=createorder params: note_public,ref_client). Prezzi: multilivello PRODUIT_MULTIPRICES, livello da llx_societe.price_level o B2BORDER_DEFAULT_PRICE_LEVEL fallback 1. Stock: llx_product_stock via Product::load_stock(). Sicurezza: sessione isolata, token CSRF su tutti i POST, prezzi ricalcolati server-side. Dipendenze: modProduct, modCommande. Nessuna tabella custom.";
		$this->editor_name = 'Henaxis';
		$this->editor_url = 'https://henaxis.it';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-shopping-cart';

		$this->depends = array('modCommande', 'modProduct');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, 0, 0);
		$this->hidden = false;

		$this->config_page_url = array("setup.php@b2border");

		$this->dirs = array('/b2border/', '/b2border/temp');

		$this->tables = array();

		$this->const = array(
			0 => array(
				'B2BORDER_ALLOWED_CATEGORIES',
				'chaine',
				'',
				'Comma-separated IDs of product categories to show (empty = all)',
				1,
				'current',
				1
			),
			1 => array(
				'B2BORDER_PRODUCTS_PER_PAGE',
				'int',
				'12',
				'Number of products per page in catalog',
				1,
				'current',
				1
			),
			2 => array(
				'B2BORDER_SHOW_STOCK',
				'int',
				'1',
				'Show stock availability indicator (1=yes, 0=no)',
				1,
				'current',
				1
			),
			3 => array(
				'B2BORDER_PORTAL_TITLE',
				'chaine',
				'B2B Order Portal',
				'Title displayed in portal header',
				1,
				'current',
				1
			),
			4 => array(
				'B2BORDER_CUSTOM_CSS',
				'chaine',
				'',
				'Custom CSS injected in the public portal',
				1,
				'current',
				1
			),
			5 => array(
				'B2BORDER_PRIMARY_COLOR',
				'chaine',
				'',
				'Override --b2b-primary CSS variable (e.g. #2e86de)',
				1,
				'current',
				1
			),
			6 => array(
				'B2BORDER_PRIMARY_DARK_COLOR',
				'chaine',
				'',
				'Override --b2b-primary-dark CSS variable',
				1,
				'current',
				1
			),
			7 => array(
				'B2BORDER_FOOTER_TEXT',
				'chaine',
				'',
				'Custom footer text (overrides default copyright)',
				1,
				'current',
				1
			),
			8 => array(
				'B2BORDER_HIDE_POWERED_BY',
				'int',
				'0',
				'Hide the Powered by Dolibarr footer line (1=hide)',
				1,
				'current',
				1
			),
			9 => array(
				'B2BORDER_ALLOWED_TAGS',
				'chaine',
				'',
				'Comma-separated IDs of product tag-categories to show (empty = no tag restriction)',
				1,
				'current',
				1
			),
			10 => array(
				'B2BORDER_DEFAULT_PRICE_LEVEL',
				'int',
				'1',
				'Default price level for portal customers without an explicit price level (requires PRODUIT_MULTIPRICES)',
				1,
				'current',
				1
			),
		);

		// Permissions
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero + 1;
		$this->rights[$r][1] = 'Access B2B Order Portal admin';
		$this->rights[$r][2] = 'a';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'admin';
		$this->rights[$r][5] = '';
		$r++;

		// Menus
		$this->menu = array();
		$r = 0;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=home',
			'type' => 'left',
			'titre' => 'B2B Order Portal',
			'prefix' => '',
			'mainmenu' => 'home',
			'leftmenu' => 'b2border_setup',
			'url' => '/custom/b2border/admin/setup.php',
			'langs' => 'b2border@b2border',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("b2border")',
			'perms' => '$user->admin',
			'target' => '',
			'user' => 2,
		);
		$r++;

		$this->module_parts = array(
			'hooks' => array(),
			'triggers' => 0,
		);

		$this->tabs = array(
			'product:+b2bphotos:B2BOrderPhotosTab:b2border@b2border:$user->hasRight("produit","creer")||$user->hasRight("service","creer"):/custom/b2border/product_photos.php?id=__ID__'
		);
	}

	public function init($options = '')
	{
		// No custom tables needed — module uses existing Dolibarr tables
		return $this->_init(array(), $options);
	}

	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
