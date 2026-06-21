<?php
/**
 * henax-ai (L0) — descrittore modulo.
 * Libreria di piattaforma: client LLM multi-provider + manifest engine + discovery.
 * Consolida skyllam (client) + henax-architect (service AI / manifest / discovery).
 *
 * ID modulo: 580420 (blocco henax piattaforma/AI 580400-580449, vedi REGISTRY.md).
 * Tabelle: SOLO tecniche (cache/log). Nessuna tabella di dominio (INTEROP §3).
 */
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modHenaxAi extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;

        $this->numero = 580420;
        $this->rights_class = 'henaxai';
        $this->family = 'technic';
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "henax-ai — client LLM multi-provider, manifest engine e discovery (L0 piattaforma)";
        $this->descriptionlong = "Libreria di piattaforma riusabile. UN client LLM (openai/openai-compatible/ollama/anythingllm/anthropic-nativo), UN manifest engine (manifest.yaml -> README/architect.json/skyllam.json), discovery dell'architettura. I moduli AI (chat, docflow, architect-UI) ci girano sopra.";
        $this->version = '1.0.0-alpha';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'fa-brain';
        $this->need_dolibarr_version = array(15, 0);

        // L0: nessuna dipendenza verso l'alto. Soft-dep opzionali risolte a runtime.
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();

        // Solo hook (no trigger). Il consumo avviene via lib, non via classi interne.
        $this->module_parts = array('hooks' => array());

        // Costanti config unificate HENAXAI_* (vedi design/henax-ai.md).
        $this->const = array(
            array('HENAXAI_PROVIDER',      'chaine', 'openai',       'Provider LLM di default', 1, 'current'),
            array('HENAXAI_MODEL',         'chaine', 'gpt-4o-mini',  'Modello di default (anthropic: claude-opus-4-8)', 1, 'current'),
            array('HENAXAI_API_KEY',       'chaine', '',             'API key', 1, 'current'),
            array('HENAXAI_ENDPOINT_URL',  'chaine', '',             'Endpoint custom (openai-compatible/ollama)', 1, 'current'),
            array('HENAXAI_AUTH_TYPE',     'chaine', 'bearer',       'bearer|basic (provider openai-compatible)', 1, 'current'),
            array('HENAXAI_CACHE_TTL_MIN', 'chaine', '30',           'TTL cache risposte (minuti)', 1, 'current'),
            array('HENAXAI_RATE_LIMIT',    'chaine', '20',           'Max query AI per utente/ora (0=off)', 1, 'current'),
        );

        // Tabelle tecniche (create da sql/). Nomi SENZA trattino (NAMING §4).
        $this->tabs = array();
        $this->dictionaries = array();
        $this->boxes = array();
        $this->cronjobs = array();

        // Permessi
        $this->rights = array();
        $r = 0;
        $this->rights[$r][0] = 580421;
        $this->rights[$r][1] = 'Interrogare l\'AI (henax-ai)';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'query';
        $r++;
        $this->rights[$r][0] = 580422;
        $this->rights[$r][1] = 'Gestire manifest/discovery (henax-ai)';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'manage';

        $this->menu = array();
    }

    public function init($options = '')
    {
        $sql = array();
        $this->_load_tables('/henax-ai/sql/');
        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
