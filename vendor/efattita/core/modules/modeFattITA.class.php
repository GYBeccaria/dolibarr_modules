<?php
/* Copyright (C) 2004-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2018 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   efattita     Module eFattITA
 *  \brief      eFattITA module descriptor.
 *
 *  \file       htdocs/efattita/core/modules/modeFattITA.class.php
 *  \ingroup    efattita
 *  \brief      Description and activation file for module eFattITA
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';


// The class name should start with a lower case mod for Dolibarr to pick it up
// so we ignore the Squiz.Classes.ValidClassName.NotCamelCaps rule.
// @codingStandardsIgnoreStart
/**
 *  Description and activation class for module eFattITA
 */
class modeFattITA extends DolibarrModules
{
	// @codingStandardsIgnoreEnd
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
        global $langs,$conf;

        $this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 463000;		// TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve id number for your module
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'efattita';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "financial";
		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';
		// Gives the possibility to the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));

		// Module label (no space allowed), used if translation string 'ModuleeFattITAName' not found (MyModue is name of module).
		$this->name = preg_replace('/^mod/i','',get_class($this));
		$this->description = "eFattITADescription";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "eFattITADescription (Long)";

		$this->editor_name = 'Linx s.r.l.s.';
		$this->editor_url = 'https://www.linx.ws';
		$this->url_last_version = $this->editor_url.'/last_version.php?module='.strtolower($this->name);

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '3.6.2';
		// Key used in llx_const table to save module status enabled/disabled (where EFATTITA is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 2;
		// Name of image file used for this module.
		$this->picto='efattita@efattita';

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /efattita/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /efattita/core/modules/barcode)
		// for specific css file (eg: /efattita/css/efattita.css.php)
		$this->module_parts = array(
                	'triggers' => 1,                                 	// Set this to 1 if module has its own trigger directory (core/triggers)
					// 'login' => 0,                                    	// Set this to 1 if module has its own login method file (core/login)
					// 'substitutions' => 1,                            	// Set this to 1 if module has its own substitution function file (core/substitutions)
					// 'menus' => 0,                                    	// Set this to 1 if module has its own menus handler directory (core/menus)
					// 'theme' => 0,                                    	// Set this to 1 if module has its own theme directory (theme)
                	// 'tpl' => 0,                                      	// Set this to 1 if module overwrite template dir (core/tpl)
					// 'barcode' => 0,                                  	// Set this to 1 if module has its own barcode directory (core/modules/barcode)
					'models' => 1,                                   	// Set this to 1 if module has its own models directory (core/modules/xxx)
					'css' => array('/efattita/css/efattita.css'),	// Set this to relative path of css file if module has its own css file
					// 'js' => array('/efattita/js/efattita.js'),          // Set this to relative path of js file if module must load a js on all pages
					'hooks' => array('data'=>array('index', 'supplier_proposalcard', 'invoicelist',  'ordercard', 'ordersuppliercard', 'propalcard', 'invoicesuppliercard', 'invoicecard','pdfgeneration', 'efattitaimport', 'thirdpartycard', 'thirdpartycomm', 'adminmodules', 'fileslib'), 'entity'=>'0'), 	// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context 'all'
					// 'moduleforexternal' => 0							// Set this to 1 if feature of module are opened to external users
                );

		// Config pages. Put here list of php page, stored into efattita/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@efattita");

		// Dependencies
		$this->hidden = false;			// A condition to hide module
		$this->depends = array('modFacture');		// List of module class names as string that must be enabled if this module is enabled
		$this->requiredby = array();	// List of module class names to disable if this one is disabled
		$this->conflictwith = array();	// List of module class names as string this module is in conflict with
		$this->langfiles = array("efattita@efattita");
		$this->phpmin = array(5,3);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(3,0);	// Minimum version of Dolibarr required by module
		$this->warnings_activation = array();                     // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		$this->warnings_activation_ext = array();                 // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		//$this->automatic_activation = array('FR'=>'eFattITAWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(0=>array('EFATTITA_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
		//                             1=>array('EFATTITA_MYNEWCONST2','chaine','myvalue','This is another constant to add',0, 'current', 1)
		// );
		$this->const = array(
            array('EFATTITA_CHECK_UPDATES', 'chaine', 1, 'Enables version checking of installed modules ', 1, 'current', 0),
            array('EFATTITA_DEFAULT_LAST_FIELDS', 'chaine', 'fk_cond_reglement, fk_mode_reglement, fk_account', 'Aggrappa i campi precompilati', 1, 'current', 0),
            array('EFATTITA_DEFAULT_LAST_EXTRAFIELDS', 'chaine', 'bollo, tipo_documento, condizioni_pagamento, modalita_pagamento, esigibilita_iva, natura, riferimento_normativo, protocollo_intento', 'Aggrappa i campi precompilati', 1, 'current', 0),
        );

		// Some keys to add into the overwriting translation tables
		$this->overwrite_translation = array(
			'it_IT:TE_PRIVATE'=>'Persona fisica',
		);

		if (! isset($conf->efattita) || ! isset($conf->efattita->enabled))
		{
			$conf->efattita=new stdClass();
			$conf->efattita->enabled=0;
		}


		// Array to add new pages in new tabs
        $this->tabs = array();

        // Dictionaries
		$this->dictionaries=array();
        /* Example:
        $this->dictionaries=array(
            'langs'=>'mylangfile@efattita',
            'tabname'=>array(MAIN_DB_PREFIX."table1",MAIN_DB_PREFIX."table2",MAIN_DB_PREFIX."table3"),		// List of tables we want to see into dictonnary editor
            'tablib'=>array("Table1","Table2","Table3"),													// Label of tables
            'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),	// Request to select fields
            'tabsqlsort'=>array("label ASC","label ASC","label ASC"),																					// Sort order
            'tabfield'=>array("code,label","code,label","code,label"),																					// List of fields (result of select to show dictionary)
            'tabfieldvalue'=>array("code,label","code,label","code,label"),																				// List of fields (list of fields to edit a record)
            'tabfieldinsert'=>array("code,label","code,label","code,label"),																			// List of fields (list of fields for insert)
            'tabrowid'=>array("rowid","rowid","rowid"),																									// Name of columns with primary key (try to always name it 'rowid')
            'tabcond'=>array($conf->efattita->enabled,$conf->efattita->enabled,$conf->efattita->enabled)												// Condition to show each dictionary
        );
        */

		// Permissions
		$this->rights = array();		// Permission array used by this module

		$r=0;
		$this->rights[$r][0] = $this->numero + $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'Read fattura elettronica';	// Permission label
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'read';				// In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)
		$this->rights[$r][5] = '';				    // In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero + $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update fattura elettronica';	// Permission label
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'write';				// In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)
		$this->rights[$r][5] = '';				    // In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero + $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete fattura elettronica';	// Permission label
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'delete';				// In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)
		$this->rights[$r][5] = '';				    // In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)


		// Main menu entries
		$this->menu = array();			// List of menus to add
		$r=0;

		// Add here entries to declare new menus
		$billing = ((float) DOL_VERSION >= 7) ? 'billing' : 'accountancy';
		$this->menu[$r++]=array(	'fk_menu'=>"fk_mainmenu=$billing",	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>'Importa fattura elettronica',
								'mainmenu'=>$billing,
								'url'=>'/efattita/import.php',
								'langs'=>'efattita@efattita',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>1000+$r,
								'enabled'=>'$conf->efattita->enabled',  // Define condition to show or hide menu entry. Use '$conf->efattita->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'1',			                // Use 'perms'=>'$user->rights->efattita->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>2);


	}

	/**
	 *	Function called when module is enabled.
	 *	The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *	It also creates data directories
	 *
     *	@param      string	$options    Options when enabling module ('', 'noboxes')
	 *	@return     int             	1 if OK, 0 if KO
	 */
	public function init($options='')
	{
		global $conf;

		/* deactivate mysql reporting */
		/* adding indexes and primary keys would results in error if table exists */
		mysqli_report(MYSQLI_REPORT_OFF);

		$result=$this->_load_tables('/efattita/sql/');
		if ($result < 0) return -1; // Do not activate module if not allowed errors found on module SQL queries (the _load_table run sql with run_sql with error allowed parameter to 'default')

		addDocumentModel('efattita', 'invoice', $label = 'Fattura elettronica');
		// Create extrafields
		$extrafields = new ExtraFields($this->db);

		// public function addExtraField($attrname, 				$label, 						$type, $pos, $size, $elementtype, $unique = 0, $required = 0, $default_value = '', $param = '', $alwayseditable = 0, $perms = '', $list = '-1', $help = '', $computed = '', $entity = '', $langfile = '', $enabled = '1')

		# campi extra terzi
			$thirdparty = ((float) DOL_VERSION >= 4) ? 'thirdparty' : 'societe';

			$extrafields->addExtraField('codice_destinatario',		"Codice destinatario",			'varchar',	0,	7,	$thirdparty,	0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('cod_sped',					"Associa metodo di spedizione",	'sellist',	0,	10,	$thirdparty,	0, 0, '',		'a:1:{s:7:"options";a:1:{s:31:"c_shipment_mode:libelle:rowid::";N;}}',																										1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('pec',						"PEC",							'mail',		0,	10,	$thirdparty,	0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');

			$extrafields->addExtraField('esportatore_abituale', 	"Esportatore abituale", 		'separate', 10, 0,	$thirdparty, 	0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->ESPORTATORI_ABITUALI');
			$extrafields->addExtraField('data_intento', 			"Data intento", 				'date', 	11, 0,	$thirdparty, 	0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->ESPORTATORI_ABITUALI');
			$extrafields->addExtraField('protocollo_intento',		"Protocollo intento", 			'varchar',	11,100,	$thirdparty, 	0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->ESPORTATORI_ABITUALI');
			$extrafields->addExtraField('valore_intento', 			"Valore intento", 				'price', 	11, 0,	$thirdparty, 	0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->ESPORTATORI_ABITUALI');
			$extrafields->addExtraField('iva_su_cp',				'IvaSuCassaPrevidenziale',		'boolean',	12, 0,	$thirdparty,	0, 0, '',		'a:1:{s:7:"options";a:1:{s:0:"";N;}}',																																		1, '', 1, 0, '', 1,	 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('ra_su_cp',					'RitenutaSuCassaPrevidenziale','boolean',	12, 0,	$thirdparty,	0, 0, '',		'a:1:{s:7:"options";a:1:{s:0:"";N;}}',																																		1, '', 1, 0, '', 1,	 'efattita@efattita', '$conf->efattita->enabled');
			
		# campi extra fattura
			$extrafields->addExtraField('bollo',					"Bollo",						'select',	0,	10,	'facture',		0, 0, '',		'a:1:{s:7:"options";a:2:{i:1;s:22:"A carico del fornitore";i:2;s:20:"A carico del cliente";}}',																				1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('tipo_documento',			"Tipo documento",				'sellist',	11,	10,	'facture',		0, 1, 'TD01',	array('options' => array('efattita_tipo_documento:description|1:code::' => NULL)),																							1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('condizioni_pagamento',		"Condizioni di pagamento",		'select',	11,	10,	'facture',		0, 0, '',		'a:1:{s:7:"options";a:3:{s:4:"TP01";s:6:"A rate";s:4:"TP02";s:15:"Unica soluzione";s:4:"TP03";s:8:"Anticipo";}}',															1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('modalita_pagamento',		"Modalità di pagamento",		'sellist',	11,	10,	'facture',		0, 0, '',		array('options' => array('efattita_modalita_pagamento:description|1:code::' => NULL)),																						1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('esigibilita_iva',			"Esigibilità IVA",				'select',	11,	10,	'facture',		0, 0, 'I',		'a:1:{s:7:"options";a:3:{s:1:"I";s:33:"I - IVA ad esigibilità immediata";s:1:"D";s:33:"D - IVA ad esigibilità differita";s:1:"S";s:27:"S - scissione dei pagamenti";}}',	1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('natura',					"Natura",						'sellist',	11,	10,	'facture',		0, 0, '',		array('options' => array('efattita_natura_2:description|1:code::' => NULL)),																								1, '', 1, 'Obbligatorio se presente IVA = 0', '', '', 'efattita@efattita', '$conf->efattita->enabled && !$conf->global->NATURA_PER_RIGA');
			$extrafields->addExtraField('riferimento_normativo',	"Riferimento normativo",		'sellist',	11,	100,'facture',		0, 0, '',		array('options' => array('efattita_riferimento_normativo:description|1:description::' => NULL)),																			1, '', 1, '', '', '', 'efattita@efattita', '$conf->efattita->enabled && !$conf->global->NATURA_PER_RIGA');
			$extrafields->addExtraField('protocollo_intento',		"Protocollo intento",			'sellist',	11,	100,'facture',		0, 0, '',		array('options' => array('facture f left join '. MAIN_DB_PREFIX .'societe_extrafields se on se.fk_object = f.fk_soc left join '. MAIN_DB_PREFIX .'facture_extrafields fe on fe.fk_object = f.rowid:ifnull(nullif(fe.protocollo_intento,0), se.protocollo_intento):ifnull(nullif(fe.protocollo_intento,0), se.protocollo_intento)::f.rowid=$ID$' => NULL)),			1, '', 1, '', '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->ESPORTATORI_ABITUALI');
			$extrafields->addExtraField('RiferimentoAmministrazione',"RiferimentoAmministrazione", 	'varchar',	11, 20,	'facture', 		0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->RiferimentoAmministrazione');
			$extrafields->addExtraField('causale',					'Causale',						'varchar',	12, 200,'facture',		0, 0, '',		'',																																											1, '', 1, 0, '', 1,	 'efattita@efattita', '$conf->efattita->enabled && $conf->global->CAUSALE_FATTURA');

		# campi extra fattura ripetuta
			$extrafields->addExtraField('bollo',					"Bollo",						'select',	0,	10,	'facture_rec',		0, 0, '',		'a:1:{s:7:"options";a:2:{i:1;s:22:"A carico del fornitore";i:2;s:20:"A carico del cliente";}}',																				1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('tipo_documento',			"Tipo documento",				'sellist',	11,	10,	'facture_rec',		0, 1, 'TD01',	array('options' => array('efattita_tipo_documento:description|1:code::' => NULL)),																							1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('condizioni_pagamento',		"Condizioni di pagamento",		'select',	11,	10,	'facture_rec',		0, 0, '',		'a:1:{s:7:"options";a:3:{s:4:"TP01";s:6:"A rate";s:4:"TP02";s:15:"Unica soluzione";s:4:"TP03";s:8:"Anticipo";}}',															1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('modalita_pagamento',		"Modalità di pagamento",		'sellist',	11,	10,	'facture_rec',		0, 0, '',		array('options' => array('efattita_modalita_pagamento:description|1:code::' => NULL)),																						1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('esigibilita_iva',			"Esigibilità IVA",				'select',	11,	10,	'facture_rec',		0, 0, 'I',		'a:1:{s:7:"options";a:3:{s:1:"I";s:33:"I - IVA ad esigibilità immediata";s:1:"D";s:33:"D - IVA ad esigibilità differita";s:1:"S";s:27:"S - scissione dei pagamenti";}}',	1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('natura',					"Natura",						'sellist',	11,	10,	'facture_rec',		0, 0, '',		array('options' => array('efattita_natura_2:description|1:code::' => NULL)),																								1, '', 1, 'Obbligatorio se presente IVA = 0', '', '', 'efattita@efattita', '$conf->efattita->enabled && !$conf->global->NATURA_PER_RIGA');
			$extrafields->addExtraField('riferimento_normativo',	"Riferimento normativo",		'sellist',	11,	100,'facture_rec',		0, 0, '',		array('options' => array('efattita_riferimento_normativo:description|1:description::' => NULL)),																			1, '', 1, '', '', '', 'efattita@efattita', '$conf->efattita->enabled && !$conf->global->NATURA_PER_RIGA');
			$extrafields->addExtraField('protocollo_intento',		"Protocollo intento",			'sellist',	11,	100,'facture_rec',		0, 0, '',		array('options' => array('facture f left join '. MAIN_DB_PREFIX .'societe_extrafields se on se.fk_object = f.fk_soc left join '. MAIN_DB_PREFIX .'facture_extrafields fe on fe.fk_object = f.rowid:ifnull(nullif(fe.protocollo_intento,0), se.protocollo_intento):ifnull(nullif(fe.protocollo_intento,0), se.protocollo_intento)::f.rowid=$ID$' => NULL)),			1, '', 1, '', '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->ESPORTATORI_ABITUALI');
			$extrafields->addExtraField('RiferimentoAmministrazione',"RiferimentoAmministrazione", 	'varchar',	11, 20,	'facture_rec', 		0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->RiferimentoAmministrazione');
			$extrafields->addExtraField('causale',					'Causale',						'varchar',	12, 200,'facture_rec',		0, 0, '',		'',																																											1, '', 1, 0, '', 1,	 'efattita@efattita', '$conf->efattita->enabled && $conf->global->CAUSALE_FATTURA');


		# campi extra linee fattura
			$extrafields->addExtraField('tipo_cessione_prestazione', "Tipo Cessione Prestazione",	'select',	1,	10,	'facturedet',	0, 0, '',		'a:1:{s:7:"options";a:4:{s:2:"SC";s:7:" Sconto";s:2:"PR";s:7:" Premio";s:2:"AB";s:8:" Abbuono";s:2:"AC";s:17:" Spesa accessoria";}}',										1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->RiferimentoAmministrazione');
			$extrafields->addExtraField('natura',					"Natura",						'sellist',	11,	10,	'facturedet',	0, 0, '',		array('options' => array('efattita_natura_2:description|1:code::' => NULL)),																								1, '', 1, 'Obbligatorio se presente IVA = 0', '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->NATURA_PER_RIGA');
			$extrafields->addExtraField('riferimento_normativo',	"Riferimento normativo",		'sellist',	11,	100,'facturedet',	0, 0, '',		array('options' => array('efattita_riferimento_normativo:description|1:description::' => NULL)),																			1, '', 1, '', '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->NATURA_PER_RIGA');

			$extrafields->addExtraField('tipo_cessione_prestazione', "Tipo Cessione Prestazione",	'select',	1,	10,	'facturedet_rec',	0, 0, '',		'a:1:{s:7:"options";a:4:{s:2:"SC";s:7:" Sconto";s:2:"PR";s:7:" Premio";s:2:"AB";s:8:" Abbuono";s:2:"AC";s:17:" Spesa accessoria";}}',										1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->RiferimentoAmministrazione');
			$extrafields->addExtraField('natura',					"Natura",						'sellist',	11,	10,	'facturedet_rec',	0, 0, '',		array('options' => array('efattita_natura_2:description|1:code::' => NULL)),																								1, '', 1, 'Obbligatorio se presente IVA = 0', '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->NATURA_PER_RIGA');
			$extrafields->addExtraField('riferimento_normativo',	"Riferimento normativo",		'sellist',	11,	100,'facturedet_rec',	0, 0, '',		array('options' => array('efattita_riferimento_normativo:description|1:description::' => NULL)),																			1, '', 1, '', '', '', 'efattita@efattita', '$conf->efattita->enabled && $conf->global->NATURA_PER_RIGA');

		#campi extra ordini
			$extrafields->addExtraField('codice_cup',				"CUP",							'varchar',	1,	15,	'commande',		0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('codice_cig',				"CIG",							'varchar',	1,	15,	'commande',		0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('codice_commessa_convenzione',"Codice commessa convenzione",'varchar',	1,	100,'commande',		0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
		# campi extra contratti
			$extrafields->addExtraField('codice_cup',				"CUP",							'varchar',	1,	15,	'contrat',		0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('codice_cig',				"CIG",							'varchar',	1,	10,	'contrat',		0, 0, '',		'',																																											1, '', 1, 0, '', '', 'efattita@efattita', '$conf->efattita->enabled');
		# campi extra prodotti
			$extrafields->addExtraField('codice_articolo_tipo',		"Codice Articolo - Tipo",		'select',	1,	1,	'product',		0, 0, '',		'a:1:{s:7:"options";a:3:{s:5:"TARIC";s:5:"TARIC";s:3:"CPV";s:3:"CPV";s:3:"SSC";s:3:"SSC";}}',																				1, '', 1, 'CodiceArticoloTipoTooltip', '', '', 'efattita@efattita', '$conf->efattita->enabled');
			$extrafields->addExtraField('codice_articolo',			"Codice Articolo",				'varchar',	1,	20,	'product',		0, 0, '',		'',																																											1, '', 1, 'CodiceArticoloTooltip', '', '', 'efattita@efattita', '$conf->efattita->enabled');

		# campi extra Documenti
		$extrafields->addExtraField(	'efattita_attach',	'efattita_attach',	'boolean',	'100',	'',	'ecm_files',	0,	0,	'',	'a:1:{s:7:"options";a:1:{s:0:"";N;}}',	1,	'',	1,	'',	'',	1,	'',	'1');

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *	@param      string	$options    Options when enabling module ('', 'noboxes')
	 *	@return     int             	1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{

		# campi extra terzi
			$thirdparty = ((float) DOL_VERSION >= 4) ? 'thirdparty' : 'societe';

			$this->delete_label('codice_destinatario',			$thirdparty);
			$this->delete_label('cod_sped',						$thirdparty);
			$this->delete_label('pec',							$thirdparty);

			$this->delete_label('esportatore_abituale',			$thirdparty);
			$this->delete_label('data_intento',					$thirdparty);
			$this->delete_label('protocollo_intento',			$thirdparty);
			$this->delete_label('valore_intento',				$thirdparty);

		#campiextrafattura
			$this->delete_label('bollo',						'facture');
			$this->delete_label('tipo_documento',				'facture');
			$this->delete_label('condizioni_pagamento',			'facture');
			$this->delete_label('modalita_pagamento',			'facture');
			$this->delete_label('esigibilita_iva',				'facture');
			$this->delete_label('natura',						'facture');
			$this->delete_label('riferimento_normativo',		'facture');
			$this->delete_label('protocollo_intento',			'facture');
			$this->delete_label('RiferimentoAmministrazione',	'facture');
			$this->delete_label('bollo',						'facture_rec');
			$this->delete_label('tipo_documento',				'facture_rec');
			$this->delete_label('condizioni_pagamento',			'facture_rec');
			$this->delete_label('modalita_pagamento',			'facture_rec');
			$this->delete_label('esigibilita_iva',				'facture_rec');
			$this->delete_label('natura',						'facture_rec');
			$this->delete_label('riferimento_normativo',		'facture_rec');
			$this->delete_label('protocollo_intento',			'facture_rec');
			$this->delete_label('RiferimentoAmministrazione',	'facture_rec');
		#campiextralineefattura
			$this->delete_label('tipo_cessione_prestazione',	'facturedet');
			$this->delete_label('natura',						'facturedet');
			$this->delete_label('riferimento_normativo',		'facturedet');

			$this->delete_label('tipo_cessione_prestazione',	'facturedet_rec');
			$this->delete_label('natura',						'facturedet_rec');
			$this->delete_label('riferimento_normativo',		'facturedet_rec');

		#campiextraordini
			$this->delete_label('codice_cup',					'commande');
			$this->delete_label('codice_cig',					'commande');
		#campiextracontratti
			$this->delete_label('codice_cup',					'contrat');
			$this->delete_label('codice_cig',					'contrat');
		#campiextraprodotti
			$this->delete_label('codice_articolo_tipo',			'product');
			$this->delete_label('codice_articolo',				'product');


		delDocumentModel('efattita', 'invoice');
		$sql = array();

		return $this->_remove($sql, $options);
	}

	// from core/class/extrafields.class.php
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Delete description of an optional attribute
	 *
	 *	@param	string	$attrname			Code of attribute to delete
	 *  @param  string	$elementtype        Element type ('member', 'product', 'thirdparty', ...)
	 *  @return int              			< 0 if KO, 0 if nothing is done, 1 if OK
	 */
	public function delete_label($attrname, $elementtype = 'member')
	{
		// phpcs:enable
		global $conf;

		if ($elementtype == 'thirdparty') {
			$elementtype = 'societe';
		}
		if ($elementtype == 'contact') {
			$elementtype = 'socpeople';
		}

		if (isset($attrname) && $attrname != '' && preg_match("/^\w[a-zA-Z0-9-_]*$/", $attrname)) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."extrafields";
			$sql .= " WHERE name = '".$this->db->escape($attrname)."'";
			$sql .= " AND entity IN  (0,".$conf->entity.')';
			$sql .= " AND elementtype = '".$this->db->escape($elementtype)."'";

			dol_syslog(get_class($this)."::delete_label", LOG_DEBUG);
			$resql = $this->db->query($sql);
			if ($resql) {
				return 1;
			} else {
				dol_print_error($this->db);
				return -1;
			}
		} else {
			return 0;
		}
	}


}
