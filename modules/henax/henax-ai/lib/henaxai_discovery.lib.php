<?php
/* Copyright (C) 2026 Henaxis srl — GPL v3+ */

/**
 * \file    custom/henax-architect/lib/henaxai_discovery.lib.php
 * \brief   Autodiscovery completa dell'architettura del progetto Dolibarr.
 *
 * Funzioni esposte:
 *   henaxai_discover_modules($db)        → array di nodi 'module'
 *   henaxai_discover_tables($db)         → array di nodi 'table' + edge owns_table
 *   henaxai_discover_integrations($db)   → array di nodi 'service' (Matrix/Jitsi/SMTP/GitHub/OpenAI/...)
 *   henaxai_discover_cron($db)           → array di nodi 'cron' + edge triggered_by
 *   henaxai_discover_roles($db)          → array di nodi 'role' + edge accesses
 *   henaxai_discover_repos($db)          → mapping module → repo (riusa github_sync.lib.php se disponibile)
 *   henaxai_build_edges($nodes, $db)     → derive edge: depends_on, fk_to, lives_in, uses_service
 *   henaxai_build_full_graph($db, $opts) → entry point: ritorna {nodes, edges, meta}
 *
 * Pattern:
 *   - Cache via hub_compute_kpi_cached() esistente (soft dep custom/domicare/lib/admin_hub.lib.php)
 *     Fallback: cache file inline /tmp se admin_hub.lib non disponibile (portabilità).
 *   - Manifest enrichment: se architect.json/architect.toml esiste in custom/<mod>/,
 *     i campi del manifest fanno OVERRIDE sui valori autodiscovery.
 *   - Resilient: ogni scan è in try/catch, fallimento di una sorgente non blocca le altre.
 */

if (!defined('DOL_DOCUMENT_ROOT')) die('Forbidden');

require_once __DIR__.'/henaxai_manifest.lib.php';

// ============================================================================
// MAPPING SERVIZI — keyword nel codice o in const → service node
// ============================================================================

/**
 * Mapping pattern → service node. Estendibile.
 * Usato da scan_integrations() per dedurre dal codice quali servizi usa un modulo.
 */
function henaxai_known_services() {
    // icon_path è relativo a /custom/henax-architect/public/img/services/
    // (URL assoluto costruito da henaxai_build_full_graph via dol_buildpath)
    return array(
        'matrix' => array(
            'label' => 'Matrix Synapse',
            'icon'  => 'fa-comments',
            'icon_path' => 'matrix.svg',
            'color' => '#0DBD8B',
            'detect_const' => array('MATRIXCHAT_HOMESERVER_URL', 'MATRIXCHAT_BOT_LOGIN'),
            'detect_keywords' => array('synapse', 'matrix.org', '_matrix/client'),
        ),
        'jitsi' => array(
            'label' => 'Jitsi Meet',
            'icon'  => 'fa-video',
            'icon_path' => 'jitsi.svg',
            'color' => '#1976d2',
            'detect_const' => array('MATRIXCHAT_JITSI_DOMAIN'),
            'detect_keywords' => array('jitsi.henaxis.net', 'jitsi-meet'),
        ),
        'smtp' => array(
            'label' => 'SMTP Gmail',
            'icon'  => 'fa-envelope',
            'icon_path' => 'smtp.svg',
            'color' => '#ea4335',
            'detect_const' => array('MAIN_MAIL_SMTP_SERVER', 'MAIN_MAIL_EMAIL_FROM'),
            'detect_keywords' => array('smtp.gmail.com', 'CMailFile'),
        ),
        'github' => array(
            'label' => 'GitHub API',
            'icon'  => 'fa-code-branch',
            'icon_path' => 'github.svg',
            'color' => '#24292e',
            'detect_const' => array('HENAXINNERHELP_GITHUB_TOKEN'),
            'detect_keywords' => array('api.github.com', 'github_sync'),
        ),
        'openai' => array(
            'label' => 'OpenAI API',
            'icon'  => 'fa-robot',
            'icon_path' => 'openai.svg',
            'color' => '#10a37f',
            'detect_const' => array('OPENAI_API_KEY', 'SKYLLAM_API_KEY', 'HENAXARCHITECT_AI_API_KEY'),
            'detect_keywords' => array('api.openai.com', 'gpt-4', 'gpt-3.5'),
        ),
        'whisper' => array(
            'label' => 'Whisper STT',
            'icon'  => 'fa-microphone',
            'icon_path' => 'whisper.svg',
            'color' => '#9c27b0',
            'detect_const' => array('DOMICARE_VOICE_WHISPER_URL'),
            'detect_keywords' => array('whisper', 'transcribe'),
        ),
        'piper' => array(
            'label' => 'Piper TTS',
            'icon'  => 'fa-volume-up',
            'icon_path' => 'piper.svg',
            'color' => '#ff9800',
            'detect_const' => array('DOMICARE_VOICE_PIPER_URL'),
            'detect_keywords' => array('piper-tts', '/api/tts'),
        ),
        'rrweb' => array(
            'label' => 'rrweb session-replay',
            'icon'  => 'fa-play-circle',
            'icon_path' => 'rrweb.svg',
            'color' => '#ec407a',
            'detect_const' => array('HENAXISANALYTICS_ENABLE'),
            'detect_keywords' => array('rrweb', 'sessionRecorder'),
        ),
    );
}

// ============================================================================
// MODULI — discovery
// ============================================================================

/**
 * Scopre tutti i moduli custom presenti su filesystem + stato enabled da DB.
 * Combinato con manifest se presente.
 *
 * @param object $db
 * @return array di nodi 'module'
 */
function henaxai_discover_modules($db) {
    $base = defined('DOL_DOCUMENT_ROOT') ? DOL_DOCUMENT_ROOT.'/custom' : '/var/www/html/custom';
    $modules = array();
    if (!is_dir($base)) return $modules;

    $palette = array('#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16','#f97316','#a855f7','#14b8a6','#6366f1');

    // Cartelle filesystem in custom/ che NON sono moduli Dolibarr (uploads, asset, ecc.)
    $non_module_dirs = array('documents', 'install', 'theme', 'cache');

    $dh = opendir($base);
    if (!$dh) return $modules;
    while (($entry = readdir($dh)) !== false) {
        if ($entry === '.' || $entry === '..' || !is_dir($base.'/'.$entry)) continue;
        if (substr($entry, 0, 1) === '.') continue;
        if (in_array(strtolower($entry), $non_module_dirs, true)) continue;
        // Un modulo "vero" deve avere almeno core/modules/mod*.class.php
        if (!henaxai_find_descriptor($base.'/'.$entry)) {
            // Eccezione: se ha admin/ o lib/ è probabile un modulo non-DolibarrModules-style (es. repo annidati Domicare-HCPPortal)
            $is_module_like = is_dir($base.'/'.$entry.'/admin') || is_dir($base.'/'.$entry.'/core') || is_dir($base.'/'.$entry.'/lib');
            if (!$is_module_like) continue;
        }

        $mod_id = strtolower($entry);
        $node = array(
            'id'    => 'module:'.$mod_id,
            'type'  => 'module',
            'group' => 'module',
            'label' => $mod_id,
            'icon'  => '📦',
            'color' => $palette[crc32($mod_id) % count($palette)],
            'data'  => array(
                'name'        => $mod_id,
                'path'        => $base.'/'.$entry,
                'enabled'     => false,
                'depends_on'  => array(),
                'tables'      => array(),
                'services'    => array(),
                'rights_range'=> null,
                'cron'        => array(),
                'endpoints'   => array(),
                'drill_links' => array(),
                'manifest_source' => null, // 'json' | 'toml' | null (autodiscovery)
                'description' => '',
            ),
        );

        // 1. Cerca const enabled (MAIN_MODULE_<UPPERNAME>)
        $const = 'MAIN_MODULE_'.strtoupper($mod_id);
        $node['data']['enabled'] = !empty(getDolGlobalString($const));

        // 2. Cerca descriptor PHP via PHP reflection (non instantiate per evitare side effects)
        $descriptor = henaxai_find_descriptor($base.'/'.$entry);
        if ($descriptor) {
            $node['data']['descriptor'] = $descriptor;
            // Parse semplice (regex) di alcuni campi
            $code = @file_get_contents($descriptor);
            if ($code) {
                if (preg_match('/\$this->name\s*=\s*[\'"]([^\'"]+)[\'"]/', $code, $m)) {
                    $node['label'] = $m[1];
                }
                if (preg_match('/\$this->numero\s*=\s*([0-9]+)/', $code, $m)) {
                    $node['data']['module_id'] = (int)$m[1];
                }
                if (preg_match('/\$this->description\s*=\s*[\'"]([^\'"]+)[\'"]/', $code, $m)) {
                    $node['data']['description'] = $m[1];
                }
                if (preg_match('/\$this->depends\s*=\s*array\(([^\)]*)\)/', $code, $m)) {
                    preg_match_all('/[\'"]mod([A-Za-z0-9_]+)[\'"]/', $m[1], $deps);
                    if (!empty($deps[1])) {
                        $node['data']['depends_on'] = array_map('strtolower', $deps[1]);
                    }
                }
                if (preg_match('/\$this->picto\s*=\s*[\'"]([^\'"]+)[\'"]/', $code, $m)) {
                    // Picto formato: "fa-X_color_#XXX" o "X@modulo"
                    $picto_raw = $m[1];
                    if (preg_match('/^(fa-[a-z0-9-]+)(?:_color_(#[0-9a-fA-F]+))?/', $picto_raw, $pm)) {
                        $node['data']['picto_class'] = $pm[1];
                        if (!empty($pm[2])) $node['color'] = $pm[2];
                    }
                }
            }
        }

        // 3. Manifest enrichment (architect.json o architect.toml)
        $manifest = henaxai_load_manifest($base.'/'.$entry);
        if ($manifest) {
            $node['data']['manifest_source'] = $manifest['_source'];
            unset($manifest['_source']);
            // Override solo i campi presenti nel manifest
            if (isset($manifest['label']))        $node['label']         = $manifest['label'];
            if (isset($manifest['color']))        $node['color']         = $manifest['color'];
            if (isset($manifest['icon']))         $node['data']['picto_class'] = $manifest['icon'];
            if (isset($manifest['repo']))         $node['data']['repo']  = $manifest['repo'];
            if (isset($manifest['depends_on']))   $node['data']['depends_on'] = (array)$manifest['depends_on'];
            if (isset($manifest['tables']))       $node['data']['tables_declared'] = (array)$manifest['tables'];
            if (isset($manifest['services']))     $node['data']['services_declared'] = (array)$manifest['services'];
            if (isset($manifest['endpoints']))    $node['data']['endpoints_declared'] = (array)$manifest['endpoints'];
            if (isset($manifest['rights_range']))$node['data']['rights_range'] = $manifest['rights_range'];
            if (isset($manifest['cron']))         $node['data']['cron_declared'] = (array)$manifest['cron'];
            if (isset($manifest['drill_links'])) $node['data']['drill_links'] = (array)$manifest['drill_links'];
            if (isset($manifest['description']))  $node['data']['description'] = $manifest['description'];
        }

        // 4. Drill-links autodiscovery se non già da manifest
        if (empty($node['data']['drill_links'])) {
            $auto_links = array();
            foreach (array('setup','inbox','list','index','dashboard') as $page) {
                $p = $base.'/'.$entry.'/admin/'.$page.'.php';
                if (file_exists($p)) {
                    $auto_links[$page] = '/custom/'.$entry.'/admin/'.$page.'.php';
                }
            }
            $node['data']['drill_links'] = $auto_links;
        }

        // 5. Repo from existing map (riusa henaxinnerhelp_resolve_target_repo se disponibile)
        if (empty($node['data']['repo']) && function_exists('henaxinnerhelp_default_repo_map')) {
            $map = henaxinnerhelp_default_repo_map();
            if (isset($map[$mod_id])) $node['data']['repo'] = $map[$mod_id];
        }

        // 6. Rights range da DB
        $node['data']['rights_range'] = henaxai_query_rights_range($db, $mod_id);

        $modules[$node['id']] = $node;
    }
    closedir($dh);

    return $modules;
}

/**
 * Trova il descriptor PHP del modulo (cartella core/modules/mod*.class.php).
 */
function henaxai_find_descriptor($module_path) {
    $dir = $module_path.'/core/modules';
    if (!is_dir($dir)) return null;
    $files = @glob($dir.'/mod*.class.php');
    return $files ? $files[0] : null;
}

/**
 * Range rights del modulo da llx_rights_def.
 */
function henaxai_query_rights_range($db, $module_name) {
    $sql = "SELECT MIN(id) AS min_id, MAX(id) AS max_id, COUNT(*) AS n_rights
            FROM ".MAIN_DB_PREFIX."rights_def
            WHERE module='".$db->escape($module_name)."'";
    $r = $db->query($sql);
    if (!$r) return null;
    $o = $db->fetch_object($r);
    if (!$o || $o->n_rights == 0) return null;
    return array('min' => (int)$o->min_id, 'max' => (int)$o->max_id, 'count' => (int)$o->n_rights);
}

// ============================================================================
// TABELLE — discovery via information_schema
// ============================================================================

/**
 * Scopre tutte le tabelle DB del progetto + ownership inferita dal prefix.
 * @return array di nodi 'table' (id "table:llx_xxx")
 */
function henaxai_discover_tables($db) {
    $tables = array();
    $sql = "SELECT TABLE_NAME, TABLE_ROWS, ROUND((DATA_LENGTH+INDEX_LENGTH)/1024) AS size_kb
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME LIKE 'llx_%'
            ORDER BY TABLE_NAME";
    $r = $db->query($sql);
    if (!$r) {
        dol_syslog("henaxai_discover_tables: SQL error: ".$db->lasterror(), LOG_ERR);
        return $tables;
    }
    while ($o = $db->fetch_object($r)) {
        $tname = $o->TABLE_NAME;
        $node = array(
            'id'    => 'table:'.$tname,
            'type'  => 'table',
            'group' => 'table',
            'label' => $tname,
            'icon'  => '🗄️',
            'color' => '#64748b',
            'data'  => array(
                'name'      => $tname,
                'rows'      => (int)$o->TABLE_ROWS,
                'size_kb'   => (int)$o->size_kb,
                'owner_module' => henaxai_infer_table_owner($tname),
            ),
        );
        $tables[$node['id']] = $node;
    }
    return $tables;
}

/**
 * Inferisce il modulo "owner" di una tabella dal prefix:
 *   llx_domicare_*           → 'domicare'
 *   llx_henaxinnerhelp_*     → 'henaxinnerhelp'
 *   llx_henaxisanalytics_*   → 'henaxisanalytics'
 *   llx_hrmformazione_*      → 'hrmformazione'
 *   llx_matrixchat_*         → 'matrixchat'
 *   llx_<core_dolibarr>      → '__core__'
 */
function henaxai_infer_table_owner($tname) {
    $known_prefixes = array(
        'llx_domicare_'           => 'domicare',
        'llx_henaxinnerhelp_'     => 'henaxinnerhelp',
        'llx_henaxisanalytics_'   => 'henaxisanalytics',
        'llx_hrmformazione_'      => 'hrmformazione',
        'llx_matrixchat_'         => 'matrixchat',
        'llx_addresscorrector_'   => 'addresscorrector',
        'llx_skyllam_'            => 'skyllam',
        'llx_henaxai_'            => 'henax-ai',
        'llx_henaxdocflow_'       => 'henax-docflow',
        'llx_customtable_'        => 'customtable',
        'llx_henaxfse_'           => 'henaxfse',
        'llx_henaxarchitect_'     => 'henax-architect',
        'llx_henax-architect_'    => 'henax-architect',
        'llx_henaxis_'            => 'henaxis',
        'llx_henax_'              => 'henax',
    );
    foreach ($known_prefixes as $prefix => $owner) {
        if (strpos($tname, $prefix) === 0) return $owner;
    }
    return '__core__';
}

// ============================================================================
// INTEGRAZIONI — service nodes
// ============================================================================

/**
 * Scopre i service esterni in uso nel progetto (Matrix, Jitsi, SMTP, GitHub, ecc.)
 * @return array di nodi 'service'
 */
function henaxai_discover_integrations($db) {
    $services = array();
    $known = henaxai_known_services();
    foreach ($known as $key => $info) {
        $detected = false;
        foreach ($info['detect_const'] as $const) {
            if (!empty(getDolGlobalString($const))) { $detected = true; break; }
        }
        if (!$detected) continue;
        $services['service:'.$key] = array(
            'id'    => 'service:'.$key,
            'type'  => 'service',
            'group' => 'service',
            'label' => $info['label'],
            'icon'  => '🔌',
            'color' => $info['color'],
            'icon_path' => $info['icon_path'] ?? null,
            'data'  => array(
                'service_key' => $key,
                'detected_via' => $info['detect_const'],
            ),
        );
    }
    return $services;
}

// ============================================================================
// CRON — scopri job registrati
// ============================================================================

function henaxai_discover_cron($db) {
    $crons = array();
    $sql = "SELECT rowid, label, jobtype, command, classesname, methodename, status, datelastrun, lastresult
            FROM ".MAIN_DB_PREFIX."cronjob
            ORDER BY label";
    $r = $db->query($sql);
    if (!$r) return $crons;
    while ($o = $db->fetch_object($r)) {
        $node = array(
            'id'    => 'cron:'.(int)$o->rowid,
            'type'  => 'cron',
            'group' => 'cron',
            'label' => $o->label,
            'icon'  => '⏰',
            'color' => $o->status == 1 ? '#16a34a' : '#94a3b8',
            'data'  => array(
                'rowid'        => (int)$o->rowid,
                'jobtype'      => $o->jobtype,
                'command'      => $o->command,
                'classesname'  => $o->classesname,
                'methodename'  => $o->methodename,
                'status'       => (int)$o->status,
                'datelastrun'  => $o->datelastrun,
                'lastresult'   => (int)$o->lastresult,
                'owner_module' => henaxai_infer_cron_owner($o->command, $o->classesname),
            ),
        );
        $crons[$node['id']] = $node;
    }
    return $crons;
}

function henaxai_infer_cron_owner($command, $classes) {
    $haystack = $command.' '.$classes;
    if (preg_match('#/custom/([a-z0-9_-]+)/#i', $haystack, $m)) {
        return strtolower($m[1]);
    }
    return null;
}

// ============================================================================
// ROLES — gruppi utente Dolibarr
// ============================================================================

function henaxai_discover_roles($db) {
    $roles = array();
    $sql = "SELECT g.rowid, g.nom,
                   (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."usergroup_user ug WHERE ug.fk_usergroup=g.rowid) AS n_members
            FROM ".MAIN_DB_PREFIX."usergroup g
            ORDER BY n_members DESC";
    $r = $db->query($sql);
    if (!$r) return $roles;
    while ($o = $db->fetch_object($r)) {
        if ($o->n_members == 0) continue; // skip vuoti
        $roles['role:'.(int)$o->rowid] = array(
            'id'    => 'role:'.(int)$o->rowid,
            'type'  => 'role',
            'group' => 'role',
            'label' => $o->nom,
            'icon'  => '👥',
            'color' => '#a855f7',
            'data'  => array(
                'rowid'     => (int)$o->rowid,
                'name'      => $o->nom,
                'n_members' => (int)$o->n_members,
            ),
        );
    }
    return $roles;
}

// ============================================================================
// REPO — riusa map github_sync se presente
// ============================================================================

function henaxai_discover_repos($db) {
    $repos = array();
    if (!function_exists('henaxinnerhelp_default_repo_map')) {
        // Soft dep: se HenaxInnerHelp non installato, salta (no repo nodes)
        return $repos;
    }
    $map = henaxinnerhelp_default_repo_map();
    foreach ($map as $key => $repo_full) {
        if (in_array($key, array('__hcp__','__backoffice__'), true)) continue;
        $repos['repo:'.$repo_full] = array(
            'id'    => 'repo:'.$repo_full,
            'type'  => 'repo',
            'group' => 'repo',
            'label' => $repo_full,
            'icon'  => '🐙',
            'icon_path' => 'github.svg',
            'color' => '#24292e',
            'data'  => array(
                'full_name' => $repo_full,
                'module_key' => $key,
                'url' => 'https://github.com/'.$repo_full,
            ),
        );
    }
    return $repos;
}

// ============================================================================
// EDGES — derive dipendenze
// ============================================================================

/**
 * Costruisce gli edge tra i nodi raccolti dai vari scan.
 *
 * Tipi edge:
 *  - depends_on   : module → module (da $this->depends)
 *  - owns_table   : module → table   (da prefix match)
 *  - lives_in     : module → repo    (da map github)
 *  - uses_service : module → service (da const detection a livello modulo via filesystem grep)
 *  - triggered_by : cron → module    (da inferred owner)
 *  - accesses     : role → module    (da llx_usergroup_rights + rights_def.module)
 *  - fk_to        : table → table    (da information_schema.KEY_COLUMN_USAGE) — opt
 */
function henaxai_build_edges($nodes, $db, $opts = array()) {
    $edges = array();

    // 1. depends_on (module → module)
    foreach ($nodes as $n) {
        if ($n['type'] !== 'module') continue;
        if (empty($n['data']['depends_on'])) continue;
        foreach ($n['data']['depends_on'] as $dep) {
            $target_id = 'module:'.strtolower($dep);
            if (isset($nodes[$target_id])) {
                $edges[] = array('from' => $n['id'], 'to' => $target_id, 'type' => 'depends_on', 'arrows' => 'to', 'color' => '#3b82f6', 'dashes' => false);
            }
        }
    }

    // 2. owns_table (module → table)
    // Edge color = colore del modulo owner così l'occhio raggruppa visivamente
    // tabelle e modulo proprietario (issue #4 Giuliano 2026-05-11).
    foreach ($nodes as $n) {
        if ($n['type'] !== 'table') continue;
        $owner = $n['data']['owner_module'] ?? null;
        if (!$owner || $owner === '__core__') continue;
        $owner_id = 'module:'.$owner;
        if (isset($nodes[$owner_id])) {
            $owner_color = $nodes[$owner_id]['color'] ?? '#94a3b8';
            $edges[] = array('from' => $owner_id, 'to' => $n['id'], 'type' => 'owns_table', 'arrows' => 'to', 'color' => $owner_color, 'dashes' => true);
        }
    }

    // 3. lives_in (module → repo)
    foreach ($nodes as $n) {
        if ($n['type'] !== 'module' || empty($n['data']['repo'])) continue;
        $repo_id = 'repo:'.$n['data']['repo'];
        if (isset($nodes[$repo_id])) {
            $edges[] = array('from' => $n['id'], 'to' => $repo_id, 'type' => 'lives_in', 'arrows' => 'to', 'color' => '#24292e', 'dashes' => true);
        }
    }

    // 4. triggered_by (cron → module)
    foreach ($nodes as $n) {
        if ($n['type'] !== 'cron') continue;
        $owner = $n['data']['owner_module'] ?? null;
        if (!$owner) continue;
        $owner_id = 'module:'.$owner;
        if (isset($nodes[$owner_id])) {
            $edges[] = array('from' => $n['id'], 'to' => $owner_id, 'type' => 'triggered_by', 'arrows' => 'to', 'color' => '#16a34a');
        }
    }

    // 5. accesses (role → module via llx_usergroup_rights + llx_rights_def.module)
    $sql = "SELECT DISTINCT g.rowid AS group_id, rd.module
            FROM ".MAIN_DB_PREFIX."usergroup g
            JOIN ".MAIN_DB_PREFIX."usergroup_rights gr ON gr.fk_usergroup = g.rowid
            JOIN ".MAIN_DB_PREFIX."rights_def rd ON rd.id = gr.fk_id
            WHERE rd.module IS NOT NULL AND rd.module != ''";
    $r = $db->query($sql);
    if ($r) {
        while ($o = $db->fetch_object($r)) {
            $role_id = 'role:'.(int)$o->group_id;
            $mod_id  = 'module:'.strtolower($o->module);
            if (isset($nodes[$role_id]) && isset($nodes[$mod_id])) {
                $edges[] = array('from' => $role_id, 'to' => $mod_id, 'type' => 'accesses', 'arrows' => 'to', 'color' => '#a855f7', 'dashes' => true);
            }
        }
    }

    // 6. uses_service (module → service) via filesystem grep
    if (!empty($opts['scan_services_in_code']) && $opts['scan_services_in_code']) {
        $known = henaxai_known_services();
        foreach ($nodes as $n) {
            if ($n['type'] !== 'module') continue;
            $services_used = henaxai_grep_services_in_module($n['data']['path'] ?? '', $known);
            // Manifest può anche dichiarare servizi
            if (!empty($n['data']['services_declared'])) {
                foreach ($n['data']['services_declared'] as $sk) $services_used[$sk] = true;
            }
            foreach ($services_used as $sk => $_) {
                $service_id = 'service:'.$sk;
                if (isset($nodes[$service_id])) {
                    $edges[] = array('from' => $n['id'], 'to' => $service_id, 'type' => 'uses_service', 'arrows' => 'to', 'color' => '#f59e0b');
                }
            }
        }
    }

    // 7. fk_to (table → table) via information_schema.KEY_COLUMN_USAGE — solo se richiesto (può essere lento)
    if (!empty($opts['include_fk_edges']) && $opts['include_fk_edges']) {
        $sql = "SELECT TABLE_NAME, REFERENCED_TABLE_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                  AND TABLE_NAME LIKE 'llx_%'
                  AND REFERENCED_TABLE_NAME LIKE 'llx_%'";
        $r = $db->query($sql);
        if ($r) {
            while ($o = $db->fetch_object($r)) {
                $from_id = 'table:'.$o->TABLE_NAME;
                $to_id   = 'table:'.$o->REFERENCED_TABLE_NAME;
                if (isset($nodes[$from_id]) && isset($nodes[$to_id])) {
                    $edges[] = array('from' => $from_id, 'to' => $to_id, 'type' => 'fk_to', 'arrows' => 'to', 'color' => '#cbd5e1', 'dashes' => true);
                }
            }
        }
    }

    return $edges;
}

/**
 * Grep keyword servizi nel codice di un modulo (resource intensive — usato solo on-demand).
 * Limita scan a max 50 file PHP.
 */
function henaxai_grep_services_in_module($module_path, $known_services) {
    $detected = array();
    if (!is_dir($module_path)) return $detected;
    $files = @glob($module_path.'/{*.php,**/*.php,**/**/*.php}', GLOB_BRACE);
    if (!$files) return $detected;
    $files = array_slice($files, 0, 50); // limit
    foreach ($files as $f) {
        $content = @file_get_contents($f, false, null, 0, 65536); // 64KB per file
        if (!$content) continue;
        foreach ($known_services as $sk => $info) {
            if (isset($detected[$sk])) continue;
            foreach (($info['detect_keywords'] ?? array()) as $kw) {
                if (stripos($content, $kw) !== false) { $detected[$sk] = true; break; }
            }
        }
    }
    return $detected;
}

// ============================================================================
// ENTRY POINT — full graph build (cached)
// ============================================================================

/**
 * Costruisce l'intero grafo (nodes + edges + meta).
 *
 * @param object $db
 * @param array  $opts ['cache_ttl' => 60, 'include_fk_edges' => false, 'scan_services_in_code' => true, 'force_refresh' => false]
 * @return array ['nodes' => [], 'edges' => [], 'meta' => [...]]
 */
function henaxai_build_full_graph($db, $opts = array()) {
    $cache_ttl = (int)($opts['cache_ttl'] ?? getDolGlobalInt('HENAXARCHITECT_GRAPH_CACHE_TTL', 60));
    $cache_key = 'henax-architect:full_graph:v1';

    $compute = function() use ($db, $opts) {
        $t0 = microtime(true);

        $modules = henaxai_discover_modules($db);
        $tables  = henaxai_discover_tables($db);
        $services = henaxai_discover_integrations($db);
        $crons   = henaxai_discover_cron($db);
        $roles   = henaxai_discover_roles($db);
        $repos   = henaxai_discover_repos($db);

        $nodes = array();
        foreach (array($modules, $tables, $services, $crons, $roles, $repos) as $group) {
            foreach ($group as $id => $n) $nodes[$id] = $n;
        }

        // Post-process: popola data.owner_color sui nodi table/cron (per render border
        // colorato del modulo owner). Issue #4 Giuliano 2026-05-11.
        foreach ($nodes as $id => &$n) {
            if (!in_array($n['type'], array('table', 'cron'), true)) continue;
            $owner = $n['data']['owner_module'] ?? null;
            if (!$owner || $owner === '__core__') continue;
            $owner_id = 'module:'.$owner;
            if (isset($nodes[$owner_id])) {
                $n['data']['owner_color'] = $nodes[$owner_id]['color'];
            }
        }
        unset($n);

        // Post-process: popola repo.data.owner_color dal modulo linker (via module_key
        // o filesystem fallback). Issue #4 step extra 2026-05-11.
        foreach ($nodes as $id => &$n) {
            if ($n['type'] !== 'repo') continue;
            $mod_key = $n['data']['module_key'] ?? null;
            if (!$mod_key) continue;
            $owner_id = 'module:'.$mod_key;
            if (isset($nodes[$owner_id])) {
                $n['data']['owner_color'] = $nodes[$owner_id]['color'];
            }
        }
        unset($n);

        // Build assoluto URL per icon_path dei nodi service+repo.
        // Si calcola una volta sola qui per non ripeterlo nel JS.
        $base_url = function_exists('dol_buildpath')
            ? dol_buildpath('/henax-architect/public/img/services/', 1)
            : '/custom/henax-architect/public/img/services/';
        foreach ($nodes as $id => &$n) {
            if (empty($n['icon_path'])) continue;
            $n['data']['icon_url'] = $base_url.$n['icon_path'];
        }
        unset($n);

        $edges = henaxai_build_edges($nodes, $db, array(
            'scan_services_in_code' => !empty($opts['scan_services_in_code']),
            'include_fk_edges'      => !empty($opts['include_fk_edges']),
        ));

        $meta = array(
            'computed_at'    => date('c'),
            'compute_ms'     => (int)((microtime(true) - $t0) * 1000),
            'n_nodes'        => count($nodes),
            'n_edges'        => count($edges),
            'breakdown'      => array(
                'modules'  => count($modules),
                'tables'   => count($tables),
                'services' => count($services),
                'crons'    => count($crons),
                'roles'    => count($roles),
                'repos'    => count($repos),
            ),
        );

        return array('nodes' => array_values($nodes), 'edges' => $edges, 'meta' => $meta);
    };

    // Force refresh bypass cache
    if (!empty($opts['force_refresh'])) {
        $result = $compute();
        @file_put_contents(sys_get_temp_dir().'/'.$cache_key.'.json', json_encode(array('expires' => time() + $cache_ttl, 'data' => $result)));
        return $result;
    }

    // Use hub_compute_kpi_cached if available (riusa cache pattern Domicare)
    if (function_exists('hub_compute_kpi_cached')) {
        return hub_compute_kpi_cached($cache_key, $cache_ttl, $compute);
    }

    // Fallback inline cache (portabilità)
    return henaxai_simple_cache($cache_key, $cache_ttl, $compute);
}

/**
 * Cache file inline /tmp con TTL. Fallback se admin_hub.lib.php non disponibile.
 */
function henaxai_simple_cache($key, $ttl_seconds, $compute_fn) {
    $cache_file = sys_get_temp_dir().'/'.preg_replace('/[^a-z0-9_-]/i','_', $key).'.json';
    if (file_exists($cache_file)) {
        $cached = @json_decode(@file_get_contents($cache_file), true);
        if (is_array($cached) && !empty($cached['expires']) && $cached['expires'] > time()) {
            return $cached['data'];
        }
    }
    $data = $compute_fn();
    @file_put_contents($cache_file, json_encode(array('expires' => time() + (int)$ttl_seconds, 'data' => $data)));
    return $data;
}

/**
 * Invalida la cache del grafo (forza re-discovery al prossimo accesso).
 */
function henaxai_invalidate_cache() {
    $cache_key = 'henax-architect:full_graph:v1';
    $cache_file = sys_get_temp_dir().'/'.preg_replace('/[^a-z0-9_-]/i','_', $cache_key).'.json';
    if (file_exists($cache_file)) @unlink($cache_file);
    return true;
}
