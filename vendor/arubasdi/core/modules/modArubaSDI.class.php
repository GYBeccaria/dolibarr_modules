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
 * 	\defgroup   arubasdi     Module ArubaSDI
 *  \brief      ArubaSDI module descriptor.
 *
 *  \file       htdocs/arubasdi/core/modules/modArubaSDI.class.php
 *  \ingroup    arubasdi
 *  \brief      Description and activation file for module ArubaSDI
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


// The class name should start with a lower case mod for Dolibarr to pick it up
// so we ignore the Squiz.Classes.ValidClassName.NotCamelCaps rule.
// @codingStandardsIgnoreStart
/**
 *  Description and activation class for module ArubaSDI
 */
class modArubaSDI extends DolibarrModules
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
		$this->numero = 463050;		// TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve id number for your module
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'arubasdi';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "financial";
		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';
		// Gives the possibility to the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));

		// Module label (no space allowed), used if translation string 'ModuleArubaSDIName' not found (MyModue is name of module).
		$this->name = preg_replace('/^mod/i','',get_class($this));
		$this->description = "ArubaSDIDescription";
		$this->editor_name = 'Linx s.r.l.s.';
		$this->editor_url = 'https://www.linx.ws';
		$this->url_last_version = $this->editor_url.'/last_version.php?module='.strtolower($this->name);

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '1.5.3';
		// Key used in llx_const table to save module status enabled/disabled (where ArubaSDI is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 2;
		// Name of image file used for this module.
		$this->picto='efattita@efattita';

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /arubasdi/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /arubasdi/core/modules/barcode)
		// for specific css file (eg: /arubasdi/css/arubasdi.css.php)
		$this->module_parts = array(
                	//'triggers' => 1,                                 	    // Set this to 1 if module has its own trigger directory (core/triggers)
					// 'login' => 0,                                    	// Set this to 1 if module has its own login method file (core/login)
					// 'substitutions' => 1,                            	// Set this to 1 if module has its own substitution function file (core/substitutions)
					// 'menus' => 0,                                    	// Set this to 1 if module has its own menus handler directory (core/menus)
					// 'theme' => 0,                                    	// Set this to 1 if module has its own theme directory (theme)
                	// 'tpl' => 0,                                      	// Set this to 1 if module overwrite template dir (core/tpl)
					// 'barcode' => 0,                                  	// Set this to 1 if module has its own barcode directory (core/modules/barcode)
					// 'models' => 0,                                   	// Set this to 1 if module has its own models directory (core/modules/xxx)
					//'css' => array('/arubasdi/css/arubasdi.css'),					// Set this to relative path of css file if module has its own css file
					// 'js' => array('/arubasdi/js/arubasdi.js'),          			// Set this to relative path of js file if module must load a js on all pages
					'hooks' => array('data'=>array('invoicelist', 'invoicecard'), 'entity'=>'0'), 	// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context 'all'
					// 'moduleforexternal' => 0							// Set this to 1 if feature of module are opened to external users
                );

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/arubasdi/temp","/arubasdi/subdir");

		// Config pages. Put here list of php page, stored into arubasdi/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@arubasdi");

		// Dependencies
		$this->hidden = false;			// A condition to hide module
		$this->depends = array('modeFattITA', 'modCron');		// List of module class names as string that must be enabled if this module is enabled
		$this->requiredby = array();	// List of module class names to disable if this one is disabled
		$this->conflictwith = array('modESI');	// List of module class names as string this module is in conflict with
		$this->langfiles = array("arubasdi@arubasdi");
		$this->phpmin = array(5,3);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(3,0);	// Minimum version of Dolibarr required by module
		$this->warnings_activation = array();                     // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		$this->warnings_activation_ext = array();                 // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		//$this->automatic_activation = array('FR'=>'ArubaSDIWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		$this->const[] = array('ARUBASDI_DEMOAUTH', 'chaine', 'https://demoauth.fatturazioneelettronica.aruba.it', '', 1, 'current', 0);
		$this->const[] = array('ARUBASDI_AUTH', 'chaine', 'https://auth.fatturazioneelettronica.aruba.it', '', 1, 'current', 0);
		$this->const[] = array('ARUBASDI_DEMOWS', 'chaine', 'https://demows.fatturazioneelettronica.aruba.it', '', 1, 'current', 0);
		$this->const[] = array('ARUBASDI_WS', 'chaine', 'https://ws.fatturazioneelettronica.aruba.it', '', 1, 'current', 0);

		if (! isset($conf->arubasdi) || ! isset($conf->arubasdi->enabled))
		{
			$conf->arubasdi=new stdClass();
			$conf->arubasdi->enabled=0;
		}

		// Array to add new pages in new tabs
        $this->tabs = array();

        // Dictionaries
		$this->dictionaries=array();

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		$this->cronjobs = array(
            0=>array(
                'label'=>'Scarica nuove fatture fornitore',
                'jobtype'=>'method',
                'class'=>'/arubasdi/class/arubasdi.class.php',
                'objectname'=>'ArubaSDI',
                'method'=>'loadSuppliersInvoices',
                'parameters'=>'',
                'comment'=>'I parametri qui sopra possono essere utilizzati per scaricare fatture incluse in una finestra temporale specifica, utilizzando 3 parametri divisi da virgola: entity, inizio, fine.<br>
					Ad esempio:<br>
					0, 2022-01-01T00:00:00-00:00, 2022-01-31T00:00:00-00:00<br>
					si scaricano le fatture di gennaio',
                'frequency'=>1,
                'unitfrequency'=>86400,
                'status'=>1,
                'test'=>'$conf->arubasdi->enabled'
            ),
    		1=>array(
                'label'=>'Controllo esiti',
                'jobtype'=>'method',
                'class'=>'/arubasdi/class/arubasdi.class.php',
                'objectname'=>'ArubaSDI',
                'method'=>'controlloEsiti',
                'parameters'=>'',
                'comment'=>'Comment',
                'frequency'=>1,
                'unitfrequency'=>14400,
                'status'=>1,
                'test'=>'$conf->arubasdi->enabled'
    		)
        );

	// Permissions
		$this->rights = array();		// Permission array used by this module
		$r=0;
		$this->rights[$r][0] = $this->numero + $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'ReadCustomerInvoices';	// Permission label
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'read';				// In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)
		$this->rights[$r][5] = '';				    // In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero + $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/UpdateCustomerInvoices';	// Permission label
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'write';				// In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)
		$this->rights[$r][5] = '';				    // In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)

		$r++;
		$this->rights[$r][0] = $this->numero + $r;	// Permission id (must not be already used)
		$this->rights[$r][1] = 'DeleteCustomerInvoices';	// Permission label
		$this->rights[$r][3] = 0; 					// Permission by default for new user (0/1)
		$this->rights[$r][4] = 'delete';				// In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)
		$this->rights[$r][5] = '';				    // In php code, permission will be checked by test if ($user->rights->efattita->level1->level2)



	// Main menu entries
		$billing = ((float) DOL_VERSION >= 7) ? 'billing' : 'accountancy';
		$this->menu = array();			// List of menus to add

		$r=0;

		$this->menu[$r++]=array('fk_menu'=>"fk_mainmenu=$billing,fk_leftmenu=suppliers_bills",	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'left',			                // This is a Left menu entry
			'titre'=>'Controllo fatture elettroniche',
			'mainmenu'=>$billing,
			'url'=>'/arubasdi/controlloFatture.php',
			'langs'=>'arubasdi@arubasdi',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1001+$r,
			'enabled'=>'$conf->arubasdi->enabled',  // Define condition to show or hide menu entry. Use '$conf->arubasdi->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'=>'1',			                // Use 'perms'=>'$user->rights->arubasdi->level1->level2' if you want your menu with a permission rules
			'target'=>'',
			'user'=>2
		);
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

		// Create extrafields
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);

		# campi extra fattura
		$extrafields->addExtraField('fattura_generata',			"Fattura generata",				'varchar',	1,	40,	'facture',		0, 0, '',	'',	1,	'', 0,	0,	'',	'',	'arubasdi@arubasdi',	'$conf->arubasdi->enabled');
		$extrafields->addExtraField('statoFattura',				'Stato fattura',				'int',		1,	2,	'facture',		0, 0, '0',	'',	1,	'',	2,	'',	'',	1,	'arubasdi@arubasdi',	'$conf->arubasdi->enabled');
		$extrafields->addExtraField('arubasdi_dataStato',		'Data Stato',					'date',		'100',	'',	'facture',	0, 0, '',	'',	1,	'',	0,	'',	'',	1,	'',	'$conf->arubasdi->enabled');

		$result=$this->_load_tables('/arubasdi/sql/');
		if ($result < 0) return -1; // Do not activate module if not allowed errors found on module SQL queries (the _load_table run sql with run_sql with error allowed parameter to 'default')

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
		$sql = array();

		$this->delete_label('fattura_generata', 'facture');
		$this->delete_label('statoFattura',	'facture');
		$this->delete_label('arubasdi_dataStato',	'facture');

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
