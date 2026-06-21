<?php
/* Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modMyModule_MyModuleTriggers.class.php
 * \ingroup mymodule
 * \brief   Example trigger.
 *
 * Put detailed description here.
 *
 * \remarks You can create other triggers by copying this one.
 * - File name should be either:
 *      - interface_99_modMyModule_MyTrigger.class.php
 *      - interface_99_all_MyTrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMytrigger
 * - The constructor method must be named InterfaceMytrigger
 * - The name property name must be MyTrigger
 */
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

// compatibility with dolibarr old versions
if(!class_exists('DolibarrTriggers')){
    class DolibarrTriggers{}
}

/**
 *  Class of triggers for MyModule module
 */
class InterfaceEfattitaTriggers extends DolibarrTriggers
{
    /**
     * @var DoliDB Database handler
     */
    protected $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Efattita triggers.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'efattita@efattita';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }


    /**
     * Trigger version
     * compatibility with dolibarr old versions
     * @return string Description of trigger file
     */
    public function getVersion()
    {
        return $this->version;
    }


    /**
     * compatibility with dolibarr old versions
     *
     * @param string 		$action 	Event action code
     * @param CommonObject 	$object 	Object
     * @param User 			$user 		Object user
     * @param Translate 	$langs 		Object langs
     * @param Conf 			$conf 		Object conf
     * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        $this->runTrigger($action, $object, $user,$langs, $conf);
    }


    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers
     *
     * @param string 		$action 	Event action code
     * @param CommonObject 	$object 	Object
     * @param User 			$user 		Object user
     * @param Translate 	$langs 		Object langs
     * @param Conf 			$conf 		Object conf
     * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        global $mysoc, $langs, $negativeLine, $xml_generated;
        
        dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
        if (empty($conf->efattita->enabled)) return 0;     // If module is not enabled, we do nothing
        
        switch ($action) {
            
            case 'BILL_VALIDATE':
                $object->fetch($object->id);
            case 'BILL_MODIFY':
                if ($object->status) {
                    $outputlangs = $langs;
                    $newlang = '';
        
                    if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
                        $newlang = GETPOST('lang_id', 'aZ09');
                    }
                    if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && isset($object->thirdparty->default_lang)) {
                        $newlang = $object->thirdparty->default_lang; // for proposal, order, invoice, ...
                    }
                    if (getDolGlobalInt('MAIN_MULTILANGS') && empty($newlang) && isset($object->default_lang)) {
                        $newlang = $object->default_lang; // for thirdparty
                    }
                    if ($conf->global->MAIN_MULTILANGS && empty($newlang) && empty($object->thirdparty)) { //load lang from thirdparty
                        $object->fetch_thirdparty();
                        $newlang = $object->thirdparty->default_lang; // for proposal, order, invoice, ...
                    }
                    if (!empty($newlang)) {
                        $outputlangs = new Translate("", $conf);
                        $outputlangs->setDefaultLang($newlang);
                    }
        
                    // To be sure vars is defined
                    if (empty($hidedetails)) {
                        $hidedetails = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
                    }
                    if (empty($hidedesc)) {
                        $hidedesc = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
                    }
                    if (empty($hideref)) {
                        $hideref = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);
                    }
                    if (empty($moreparams)) {
                        $moreparams = null;
                    }
                    
                    if ($result <= 0) {
                        setEventMessages($object->error, $object->errors, 'errors');
                        $error++;
                        break;
                    } else {
                        $nbok++;
                    }
                }
                
                // aggiorna bollo
                if($object->array_options['options_bollo'] < 2){
                    // Remove revenue stamp to total as it is not to be paid by customer
                    $object->revenuestamp = 0;
                }
                $object->update_price(1);
                
                $result = $object->generateDocument('efattita', $langs);
                
                break;
 
        // calcolo ra e cap
            case 'LINEBILL_SUPPLIER_CREATE':
            case 'LINEBILL_SUPPLIER_MODIFY':
                $object->parent_table = 'facture_fourn';
                $object->parent_id = $object->fk_facture_fourn;
                $object->passive = 1;
                $this->addProfTaxes($object, $user, $langs, $conf);
                $object->description = $object->desc; // dolibarr bug
                $object->update(1);
                break;
            case 'LINESUPPLIER_PROPOSAL_INSERT':
            case 'LINESUPPLIER_PROPOSAL_MODIFY':
                $object->parent_table = 'supplier_proposal';
                $object->parent_id = $object->fk_supplier_proposal;
                $object->passive = 1;
                $this->addProfTaxes($object, $user, $langs, $conf);
                $object->update(1);
                break;
            case 'LINEORDER_SUPPLIER_MODIFY':
            case 'LINEORDER_SUPPLIER_CREATE':
                $object->parent_table = 'commande_fournisseur';
                $object->parent_id = $object->element == 'order_supplier' ? $object->id : $object->fk_commande;
                $object->passive = 1;
                $this->addProfTaxes($object, $user, $langs, $conf);
                if (method_exists($object, 'update')) {
                    $object->update(1);
                } else {  // vecchie versioni di dolibarr ~4.0
                    $sql = strtr('update llx_commande_fournisseur set
                        tva         = :total_tva,
                        localtax1   = :localtax1,
                        localtax2   = :localtax2,
                        total_ht    = :total_ht,
                        total_ttc   = :total_ttc',[
                            'llx_'  => MAIN_DB_PREFIX,
                            ':total_tva'    => isset($object->total_tva) ? $object->total_tva : "null",
                            ':localtax1'    => isset($object->total_localtax1) ? $object->total_localtax1 : "null",
                            ':localtax2'    => isset($object->total_localtax2) ? $object->total_localtax2 : "null",
                            ':total_ht'     => isset($object->total_ht) ? $object->total_ht : "null",
                            ':total_ttc'    => isset($object->total_ttc) ? $object->total_ttc : "null"
                        ]
                    );


                    $this->db->begin();

                    dol_syslog(get_class($this)."::update", LOG_DEBUG);
                    $resql = $this->db->query($sql);
                    if (!$resql) {
                        $error++;
                        $this->errors[] = "Error ".$this->db->lasterror();
                    }
            
                    // Commit or rollback
                    if ($error) {
                        foreach ($this->errors as $errmsg) {
                            dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
                            $this->error .= ($this->error ? ', '.$errmsg : $errmsg);
                        }
                        $this->db->rollback();
                        return -1 * $error;
                    } else {
                        $this->db->commit();
                        return 1;
                    }
                }
                break;
               
            case 'LINEBILL_INSERT':
            case 'LINEBILL_MODIFY':
            case 'LINEBILL_UPDATE': // old dolibarr versions
                $object->parent_table = 'facture';
                $object->parent_id = $object->fk_facture;
                $this->addProfTaxes($object, $user, $langs, $conf);
                $object->update($user, 1);
                break;
                    
            case 'LINEORDER_MODIFY':
            case 'LINEORDER_INSERT':
                $object->parent_table = 'commande';
                $object->parent_id = $object->fk_commande;
                $this->addProfTaxes($object, $user, $langs, $conf);
                $object->update($user, 1);
                break;

            case 'LINEPROPAL_MODIFY':
            case 'LINEPROPAL_INSERT':
                $object->parent_table = 'propal';
                $object->parent_id = $object->fk_propal;
                $this->addProfTaxes($object, $user, $langs, $conf);
                $object->update(1);
                break;
            case 'BILL_SUPPLIER_MODIFY':
                $object->update_price();
                break;
            default:
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
                break;
        }
        return 0;
    }

    private function addProfTaxes($object, User $user, Translate $langs, Conf $conf)
    {
        global $db;

        if ($object->passive) {
            $sql = strtr('select s.iva_su_cp, s.ra_su_cp
            from :table t
            left join llx_societe_extrafields s
                on s.fk_object = t.fk_soc
            where t.rowid = :id',[
                'llx_'      =>  MAIN_DB_PREFIX,
                ':table'	=>	MAIN_DB_PREFIX . $object->parent_table,
                ':id'    =>  $object->parent_id
            ]);
            $resql = $db->query($sql);
            $soc = $db->fetch_object($resql);
            $IvaSuCassaPrevidenziale = $soc->iva_su_cp;
            $RitenutaSuCassaPrevidenziale = $soc->ra_su_cp;
        }else {
            $IvaSuCassaPrevidenziale = $conf->global->IvaSuCassaPrevidenziale;
            $RitenutaSuCassaPrevidenziale = $conf->global->RitenutaSuCassaPrevidenziale;
        }


        // ricalcola iva su ritenuta d'acconto
        
        if (!$IvaSuCassaPrevidenziale && !$RitenutaSuCassaPrevidenziale) {
            return 0;
        }

        // line->fetch non sempre prende i dati delle tasse
        $sql = strtr('SELECT * FROM :table where rowid = :id',[
            'llx_'		=>	MAIN_DB_PREFIX,
            ':table'	=>	MAIN_DB_PREFIX . $object->table_element,
            ':id'		=>  $object->id
        ]);
        
        $res = $db->query($sql);
        $obj = $db->fetch_object($res);
        
        if ($obj->product_type == 1)
        {
            // necessario per update fattura fornitore
            $object->pu_ht  = $obj->pu_ht;
            $object->pu_ttc = $obj->pu_ttc;
            // necessario per ordine
            $object->remise = $obj->remise;

            // aggiunge iva sulla cassa previdenziale all'iva già calcolata
            if($IvaSuCassaPrevidenziale){

                $object->total_ttc		                +=	nformat(($object->total_localtax2 * $object->tva_tx / 100), 2);
                $object->total_tva		                +=	nformat(($object->total_localtax2 * $object->tva_tx / 100), 2);
                $object->multicurrency_total_ttc		+=	nformat(($object->total_localtax2 * $object->tva_tx / 100), 2);
                $object->multicurrency_total_tva		+=	nformat(($object->total_localtax2 * $object->tva_tx / 100), 2);
            }
            // aggiunge la ritenuta sulla cassa previdenziale
            if($RitenutaSuCassaPrevidenziale){
                $object->total_ttc		                +=	($object->total_localtax2 * $object->localtax1_tx / 100);
                $object->multicurrency_total_ttc		+=	($object->total_localtax2 * $object->localtax1_tx / 100);
                $object->total_localtax1	            +=	($object->total_localtax2 * $object->localtax1_tx / 100);
            }
        }
    }
}
