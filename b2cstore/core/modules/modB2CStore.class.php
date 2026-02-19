<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor for B2C Storefront (b2cstore)
 * ID: 580400
 */
class modB2CStore extends DolibarrModules
{
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 580400;
		$this->rights_class = 'b2cstore';
		$this->family = 'portal';
		$this->module_position = '92';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Portale e-commerce B2C: frontpage configurabile, registrazione clienti online, catalogo e checkout";
		$this->descriptionlong = "Modulo b2cstore (ID 580400). Entita gestite: clienti (llx_societe_account site=b2cstore_portal, llx_societe), prodotti (llx_product tosell=1), carrello (sessione PHP B2CSTORE_SESSID_), ordini bozza (llx_commande fk_statut=0 module_source=b2cstore), messaggi contatto (llx_b2cstore_contact). Routing portale: parametro controller= (valori: home,register,login,catalog,product,cart,checkout,confirmation,contact,page). Registrazione: crea llx_societe (tipo configurabile) + llx_societe_account con site=b2cstore_portal, password bcrypt. Sezioni frontpage configurabili: HERO,ABOUT,SERVICES,HISTORY,PRODUCTS_PREVIEW,CONTACT,B2B_LINK (ordine, titolo, contenuto, immagine da llx_const). Prezzi: multilivello PRODUIT_MULTIPRICES, livello da B2CSTORE_DEFAULT_PRICE_LEVEL. Navigazione guest: configurabile via B2CSTORE_ALLOW_GUEST_BROWSING. Dipendenze: modProduct, modCommande, modSociete.";
		$this->editor_name = 'Henaxis';
		$this->editor_url = 'https://henaxis.it';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-store';

		$this->depends = array('modCommande', 'modProduct', 'modSociete');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, 0, 0);
		$this->hidden = false;

		$this->config_page_url = array('setup.php@b2cstore');

		$this->dirs = array('/b2cstore/', '/b2cstore/temp', '/b2cstore/sections');

		$this->tables = array('b2cstore_contact');
		$this->tables_pk = array('b2cstore_contact' => 'rowid');

		// Configuration constants
		$this->const = array(
			// General
			0  => array('B2CSTORE_PORTAL_TITLE',           'chaine', 'Il Nostro Negozio',                  'Portal site title',                           1, 'current', 1),
			1  => array('B2CSTORE_META_DESCRIPTION',        'chaine', '',                                    'SEO meta description',                        1, 'current', 1),
			2  => array('B2CSTORE_DEFAULT_PRICE_LEVEL',     'int',    '1',                                   'Default price level for B2C customers (1-6)', 1, 'current', 1),
			3  => array('B2CSTORE_PRODUCTS_PER_PAGE',       'int',    '12',                                  'Products per page in catalog',                1, 'current', 1),
			4  => array('B2CSTORE_SHOW_STOCK',              'int',    '0',                                   'Show stock availability (1=yes)',             1, 'current', 1),
			5  => array('B2CSTORE_ALLOW_GUEST_BROWSING',    'int',    '1',                                   'Allow catalog without login (1=yes)',         1, 'current', 1),
			6  => array('B2CSTORE_REQUIRE_LOGIN_FOR_PRICES','int',    '0',                                   'Show prices only after login',                1, 'current', 1),
			7  => array('B2CSTORE_REQUIRE_LOGIN_FOR_CART',  'int',    '1',                                   'Cart requires login',                         1, 'current', 1),
			8  => array('B2CSTORE_ALLOWED_CATEGORIES',      'chaine', '',                                    'CSV category IDs visible in portal',          1, 'current', 1),
			9  => array('B2CSTORE_ALLOWED_TAGS',            'chaine', '',                                    'CSV tag IDs visible in portal',               1, 'current', 1),
			// Registration
			10 => array('B2CSTORE_ENABLE_REGISTRATION',     'int',    '1',                                   'Enable online registration',                  1, 'current', 1),
			11 => array('B2CSTORE_REGISTRATION_REQUIRES_APPROVAL', 'int', '0',                               'Require admin approval for new accounts',     1, 'current', 1),
			12 => array('B2CSTORE_CUSTOMER_TYPENT_ID',      'int',    '8',                                   'Thirdparty type for B2C customers (8=private)',1, 'current', 1),
			13 => array('B2CSTORE_DEFAULT_CUSTOMER_CATEGORY','int',   '0',                                   'Auto-assign thirdparty category (0=none)',    1, 'current', 1),
			14 => array('B2CSTORE_REGISTRATION_FIELDS',     'chaine', 'name,email,phone,address',            'Required registration fields (CSV)',           1, 'current', 1),
			// Appearance
			15 => array('B2CSTORE_PRIMARY_COLOR',           'chaine', '#2563eb',                             'Primary color (CSS --b2cs-primary)',           1, 'current', 1),
			16 => array('B2CSTORE_SECONDARY_COLOR',         'chaine', '#1e40af',                             'Secondary color (CSS --b2cs-secondary)',       1, 'current', 1),
			17 => array('B2CSTORE_ACCENT_COLOR',            'chaine', '#f59e0b',                             'Accent/CTA color (CSS --b2cs-accent)',         1, 'current', 1),
			18 => array('B2CSTORE_BG_COLOR',                'chaine', '#ffffff',                             'Background color (CSS --b2cs-bg)',             1, 'current', 1),
			19 => array('B2CSTORE_TEXT_COLOR',              'chaine', '#1f2937',                             'Text color (CSS --b2cs-text)',                 1, 'current', 1),
			20 => array('B2CSTORE_FONT_FAMILY',             'chaine', 'Inter, system-ui, sans-serif',        'Font family',                                 1, 'current', 1),
			21 => array('B2CSTORE_CUSTOM_CSS',              'chaine', '',                                    'Custom CSS injected in portal head',           1, 'current', 1),
			22 => array('B2CSTORE_FOOTER_TEXT',             'chaine', '',                                    'Footer text (overrides default copyright)',    1, 'current', 1),
			23 => array('B2CSTORE_HIDE_POWERED_BY',         'int',    '0',                                   'Hide "Powered by" in footer (1=hide)',        1, 'current', 1),
			// Sections
			24 => array('B2CSTORE_SECTION_HERO_ENABLED',    'int',    '1',    'Hero section enabled',         1, 'current', 1),
			25 => array('B2CSTORE_SECTION_HERO_TITLE',      'chaine', '',     'Hero section title',           1, 'current', 1),
			26 => array('B2CSTORE_SECTION_HERO_CONTENT',    'chaine', '',     'Hero section HTML content',    1, 'current', 1),
			27 => array('B2CSTORE_SECTION_HERO_IMAGE',      'chaine', '',     'Hero section image file',      1, 'current', 1),
			28 => array('B2CSTORE_SECTION_HERO_ORDER',      'int',    '10',   'Hero section display order',   1, 'current', 1),
			29 => array('B2CSTORE_SECTION_HERO_BG_COLOR',   'chaine', '',     'Hero section background color',1, 'current', 1),
			30 => array('B2CSTORE_SECTION_HERO_CSS_CLASS',  'chaine', '',     'Hero section extra CSS class', 1, 'current', 1),

			31 => array('B2CSTORE_SECTION_ABOUT_ENABLED',   'int',    '1',    'About section enabled',        1, 'current', 1),
			32 => array('B2CSTORE_SECTION_ABOUT_TITLE',     'chaine', '',     'About section title',          1, 'current', 1),
			33 => array('B2CSTORE_SECTION_ABOUT_CONTENT',   'chaine', '',     'About section HTML content',   1, 'current', 1),
			34 => array('B2CSTORE_SECTION_ABOUT_IMAGE',     'chaine', '',     'About section image file',     1, 'current', 1),
			35 => array('B2CSTORE_SECTION_ABOUT_ORDER',     'int',    '20',   'About section display order',  1, 'current', 1),
			36 => array('B2CSTORE_SECTION_ABOUT_BG_COLOR',  'chaine', '',     'About section background',     1, 'current', 1),
			37 => array('B2CSTORE_SECTION_ABOUT_CSS_CLASS', 'chaine', '',     'About section extra CSS class',1, 'current', 1),

			38 => array('B2CSTORE_SECTION_SERVICES_ENABLED','int',    '1',    'Services section enabled',     1, 'current', 1),
			39 => array('B2CSTORE_SECTION_SERVICES_TITLE',  'chaine', '',     'Services section title',       1, 'current', 1),
			40 => array('B2CSTORE_SECTION_SERVICES_CONTENT','chaine', '',     'Services section HTML content',1, 'current', 1),
			41 => array('B2CSTORE_SECTION_SERVICES_IMAGE',  'chaine', '',     'Services section image file',  1, 'current', 1),
			42 => array('B2CSTORE_SECTION_SERVICES_ORDER',  'int',    '30',   'Services section order',       1, 'current', 1),
			43 => array('B2CSTORE_SECTION_SERVICES_BG_COLOR','chaine','',     'Services background color',    1, 'current', 1),
			44 => array('B2CSTORE_SECTION_SERVICES_CSS_CLASS','chaine','',    'Services extra CSS class',     1, 'current', 1),

			45 => array('B2CSTORE_SECTION_HISTORY_ENABLED', 'int',    '1',    'History section enabled',      1, 'current', 1),
			46 => array('B2CSTORE_SECTION_HISTORY_TITLE',   'chaine', '',     'History section title',        1, 'current', 1),
			47 => array('B2CSTORE_SECTION_HISTORY_CONTENT', 'chaine', '',     'History section HTML content', 1, 'current', 1),
			48 => array('B2CSTORE_SECTION_HISTORY_IMAGE',   'chaine', '',     'History section image file',   1, 'current', 1),
			49 => array('B2CSTORE_SECTION_HISTORY_ORDER',   'int',    '40',   'History section order',        1, 'current', 1),
			50 => array('B2CSTORE_SECTION_HISTORY_BG_COLOR','chaine', '',     'History background color',     1, 'current', 1),
			51 => array('B2CSTORE_SECTION_HISTORY_CSS_CLASS','chaine','',     'History extra CSS class',      1, 'current', 1),

			52 => array('B2CSTORE_SECTION_PRODUCTS_PREVIEW_ENABLED', 'int',    '1',  'Products preview enabled',    1, 'current', 1),
			53 => array('B2CSTORE_SECTION_PRODUCTS_PREVIEW_TITLE',   'chaine', '',   'Products preview title',      1, 'current', 1),
			54 => array('B2CSTORE_SECTION_PRODUCTS_PREVIEW_CONTENT', 'chaine', '',   'Products preview sub-text',   1, 'current', 1),
			55 => array('B2CSTORE_SECTION_PRODUCTS_PREVIEW_IMAGE',   'chaine', '',   'Products preview image',      1, 'current', 1),
			56 => array('B2CSTORE_SECTION_PRODUCTS_PREVIEW_ORDER',   'int',    '50', 'Products preview order',      1, 'current', 1),
			57 => array('B2CSTORE_SECTION_PRODUCTS_PREVIEW_BG_COLOR','chaine', '',   'Products preview background', 1, 'current', 1),
			58 => array('B2CSTORE_SECTION_PRODUCTS_PREVIEW_CSS_CLASS','chaine','',   'Products preview CSS class',  1, 'current', 1),
			59 => array('B2CSTORE_PRODUCTS_PREVIEW_COUNT',            'int',    '6',  'Number of products in preview',1, 'current', 1),

			60 => array('B2CSTORE_SECTION_CONTACT_ENABLED', 'int',    '1',    'Contact form section enabled', 1, 'current', 1),
			61 => array('B2CSTORE_SECTION_CONTACT_TITLE',   'chaine', '',     'Contact section title',        1, 'current', 1),
			62 => array('B2CSTORE_SECTION_CONTACT_CONTENT', 'chaine', '',     'Contact section intro text',   1, 'current', 1),
			63 => array('B2CSTORE_SECTION_CONTACT_IMAGE',   'chaine', '',     'Contact section image',        1, 'current', 1),
			64 => array('B2CSTORE_SECTION_CONTACT_ORDER',   'int',    '60',   'Contact section order',        1, 'current', 1),
			65 => array('B2CSTORE_SECTION_CONTACT_BG_COLOR','chaine', '',     'Contact section background',   1, 'current', 1),
			66 => array('B2CSTORE_SECTION_CONTACT_CSS_CLASS','chaine','',     'Contact section CSS class',    1, 'current', 1),

			67 => array('B2CSTORE_SECTION_B2B_LINK_ENABLED','int',    '1',    'B2B link section enabled',     1, 'current', 1),
			68 => array('B2CSTORE_SECTION_B2B_LINK_TITLE',  'chaine', '',     'B2B link section title',       1, 'current', 1),
			69 => array('B2CSTORE_SECTION_B2B_LINK_CONTENT','chaine', '',     'B2B link section text',        1, 'current', 1),
			70 => array('B2CSTORE_SECTION_B2B_LINK_IMAGE',  'chaine', '',     'B2B link section image',       1, 'current', 1),
			71 => array('B2CSTORE_SECTION_B2B_LINK_ORDER',  'int',    '70',   'B2B link section order',       1, 'current', 1),
			72 => array('B2CSTORE_SECTION_B2B_LINK_BG_COLOR','chaine','',     'B2B link section background',  1, 'current', 1),
			73 => array('B2CSTORE_SECTION_B2B_LINK_CSS_CLASS','chaine','',    'B2B link section CSS class',   1, 'current', 1),
			// B2B link config
			74 => array('B2CSTORE_B2B_LINK_TEXT',           'chaine', "Sei un'azienda? Accedi al portale B2B", 'B2B link button text',   1, 'current', 1),
			75 => array('B2CSTORE_B2B_LINK_URL',            'chaine', '',     'B2B portal URL override (auto-detect if empty)',1, 'current', 1),
		);

		// Permissions
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero + 1;
		$this->rights[$r][1] = 'Read B2C Store portal and configuration';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'read';
		$this->rights[$r][5] = '';
		$r++;

		$this->rights[$r][0] = $this->numero + 2;
		$this->rights[$r][1] = 'Configure B2C Store module';
		$this->rights[$r][2] = 'a';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'setup';
		$this->rights[$r][5] = '';
		$r++;

		// Menus
		$this->menu = array();
		$r = 0;

		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=home',
			'type'     => 'left',
			'titre'    => 'B2C Store',
			'prefix'   => '',
			'mainmenu' => 'home',
			'leftmenu' => 'b2cstore_setup',
			'url'      => '/custom/b2cstore/admin/setup.php',
			'langs'    => 'b2cstore@b2cstore',
			'position' => 1000 + $r,
			'enabled'  => 'isModEnabled("b2cstore")',
			'perms'    => '$user->admin',
			'target'   => '',
			'user'     => 2,
		);
		$r++;

		$this->module_parts = array(
			'hooks'    => array(),
			'triggers' => 0,
		);
	}

	public function init($options = '')
	{
		$sql = array();
		$result = $this->loadTables();
		return $this->_init($sql, $options);
	}

	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
