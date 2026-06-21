<?php
/* Copyright (C) 2026 P2G Connecto — GPL v3+ */

/**
 * \file    custom/henax-ai/lib/henaxai_manifest.lib.php
 * \brief   MANIFEST ENGINE (L0) — loader/validator + estrazione superficie AI-queryable.
 *          Portato 1:1 da henax-architect/lib/architect_manifest.lib.php
 *          (rename architect_* -> henaxai_*, vendor toml entry rinominata).
 *
 * Questo e' il CONTRATTO DI INTEROPERABILITA' centrale (INTEROP.md §2):
 *   manifest (architect.json / .toml) = source-of-truth per struttura + superficie AI.
 *   henaxai_extract_skyllam_block() = come un modulo si rende interrogabile dall'AI.
 *
 * Funzioni esposte:
 *   henaxai_manifest_schema(): array
 *   henaxai_load_manifest(string $module_path): ?array        // architect.json (prio) | architect.toml
 *   henaxai_validate_manifest(array $manifest, $db = null): array {ok,errors,warnings}
 *   henaxai_extract_skyllam_block(string $module_path): ?array  // usato dai consumer (es. SkyllamManifest)
 *
 * TODO (porting follow-up): builder (manifest.yaml -> README/architect.json/skyllam.json)
 *   da architect_manifest_builder.lib.php; discovery grafo da architect_discovery.lib.php.
 */

if (!defined('DOL_DOCUMENT_ROOT')) die('Forbidden');

require_once __DIR__.'/vendor/toml_parser.php';

if (!function_exists('henaxai_manifest_schema')) {

/**
 * Schema dei campi attesi nel manifest. Usato per validation + doc.
 */
function henaxai_manifest_schema() {
    return array(
        // === Identita' + UI grafo ===
        'module'       => array('type' => 'string', 'required' => false, 'desc' => 'Nome canonico modulo (default: nome cartella)'),
        'label'        => array('type' => 'string', 'required' => false, 'desc' => 'Label leggibile UI'),
        'icon'         => array('type' => 'string', 'required' => false, 'desc' => 'Font Awesome class (es. "fa-life-ring")'),
        'color'        => array('type' => 'string', 'required' => false, 'desc' => 'Colore hex per nodo grafo (es. "#1565c0")'),
        'description'  => array('type' => 'string', 'required' => false, 'desc' => 'Descrizione breve (1-2 frasi)'),
        'version'      => array('type' => 'string', 'required' => false, 'desc' => 'Versione modulo (semver opzionale)'),
        // === Architettura ===
        'repo'         => array('type' => 'string', 'required' => false, 'desc' => 'GitHub "owner/repo"'),
        'depends_on'   => array('type' => 'array',  'required' => false, 'desc' => 'Lista nomi moduli da cui dipende'),
        'tables'       => array('type' => 'array',  'required' => false, 'desc' => 'Tabelle DB owned (per validation)'),
        'services'     => array('type' => 'array',  'required' => false, 'desc' => 'Servizi esterni usati (es. ["matrix","github","openai"])'),
        'endpoints'    => array('type' => 'array',  'required' => false, 'desc' => 'Lista endpoint con {path, auth, purpose}'),
        'rights_range' => array('type' => 'array',  'required' => false, 'desc' => 'Range ID rights [min, max]'),
        'cron'         => array('type' => 'array',  'required' => false, 'desc' => 'Lista cron jobs ["name (frequency)", ...]'),
        'drill_links'  => array('type' => 'array',  'required' => false, 'desc' => 'Mappa key -> URL ({setup, inbox, list, ...})'),
        // === Block AI-queryable (superficie interrogabile, sostituisce skyllam.json legacy) ===
        'skyllam'      => array('type' => 'array',  'required' => false, 'desc' => 'Block AI: {entities, stats, navigation, prompts, quick_actions, welcome}'),
        'skyllam_legacy_file' => array('type' => 'string', 'required' => false, 'desc' => 'Punta a skyllam.json legacy se il block AI vive separato (caso Domicare)'),
    );
}

/**
 * Cerca + carica manifest da una cartella modulo.
 * Priorita': architect.json > architect.toml.
 *
 * @param string $module_path Path assoluto della cartella modulo
 * @return array|null Manifest parsed (con chiave '_source') o null
 */
function henaxai_load_manifest($module_path) {
    if (!is_dir($module_path)) return null;

    // Priorita' JSON
    $json_path = $module_path.'/architect.json';
    if (file_exists($json_path)) {
        $raw = @file_get_contents($json_path);
        if ($raw === false) return null;
        $data = @json_decode($raw, true);
        if (!is_array($data)) {
            dol_syslog("henaxai_load_manifest: JSON invalid in $json_path", LOG_WARNING);
            return null;
        }
        $data['_source'] = 'json';
        return $data;
    }

    // Fallback TOML
    $toml_path = $module_path.'/architect.toml';
    if (file_exists($toml_path)) {
        $raw = @file_get_contents($toml_path);
        if ($raw === false) return null;
        try {
            $data = henaxai_parse_toml($raw);
        } catch (Throwable $e) {
            dol_syslog("henaxai_load_manifest: TOML parse error in $toml_path: ".$e->getMessage(), LOG_WARNING);
            return null;
        }
        if (!is_array($data)) return null;
        $data['_source'] = 'toml';
        return $data;
    }

    return null;
}

/**
 * Valida il manifest contro lo schema. Ritorna array con ok + errors + warnings.
 *
 * @param array $manifest
 * @param object|null $db (per cross-check tabelle dichiarate vs realta' DB)
 * @return array {ok: bool, errors: [], warnings: []}
 */
function henaxai_validate_manifest($manifest, $db = null) {
    $schema = henaxai_manifest_schema();
    $errors = array();
    $warnings = array();

    foreach ($manifest as $field => $value) {
        if ($field === '_source') continue;
        if (!isset($schema[$field])) {
            $warnings[] = "Campo sconosciuto '$field' (ignorato)";
            continue;
        }
        $expected = $schema[$field]['type'];
        $actual = gettype($value);
        if ($expected === 'string' && $actual !== 'string') {
            $errors[] = "Campo '$field' deve essere string, trovato $actual";
        } elseif ($expected === 'array' && $actual !== 'array') {
            $errors[] = "Campo '$field' deve essere array, trovato $actual";
        }
    }

    // Cross-check tabelle dichiarate vs DB realta' (se DB disponibile)
    if ($db && !empty($manifest['tables']) && is_array($manifest['tables'])) {
        foreach ($manifest['tables'] as $tname) {
            if (!is_string($tname)) continue;
            $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$db->escape($tname)."' LIMIT 1";
            $r = $db->query($sql);
            if ($r && !$db->fetch_object($r)) {
                $warnings[] = "Tabella dichiarata '$tname' non esiste nel DB";
            }
        }
    }

    return array('ok' => empty($errors), 'errors' => $errors, 'warnings' => $warnings);
}

/**
 * Estrae il blocco AI-queryable dal manifest unificato di un modulo.
 * Convergenza skyllam.json <-> architect.json:
 *   - Priority 1: architect.{json,toml} con campo `skyllam: {...}` inline
 *   - Priority 2: architect.{json,toml} con `skyllam_legacy_file: "skyllam.json"`
 *   - Priority 3: skyllam.json legacy nel root del modulo
 *
 * @param string $module_path Path assoluto cartella modulo
 * @return array|null Block {entities?, stats?, navigation?, prompts?, ...} o null
 */
function henaxai_extract_skyllam_block($module_path) {
    if (!is_dir($module_path)) return null;

    $manifest = henaxai_load_manifest($module_path);
    if ($manifest) {
        // Inline block
        if (!empty($manifest['skyllam']) && is_array($manifest['skyllam'])) {
            $sk = $manifest['skyllam'];
            if (!isset($sk['module']) && !empty($manifest['module']))          $sk['module'] = $manifest['module'];
            if (!isset($sk['label']) && !empty($manifest['label']))            $sk['label'] = $manifest['label'];
            if (!isset($sk['description']) && !empty($manifest['description'])) $sk['description'] = $manifest['description'];
            return $sk;
        }
        // Legacy pointer
        if (!empty($manifest['skyllam_legacy_file'])) {
            $legacy_path = $module_path.'/'.basename($manifest['skyllam_legacy_file']);
            if (file_exists($legacy_path)) {
                $raw = @file_get_contents($legacy_path);
                $data = @json_decode($raw, true);
                return is_array($data) ? $data : null;
            }
        }
    }

    // Fallback: skyllam.json legacy diretto
    $legacy = $module_path.'/skyllam.json';
    if (file_exists($legacy)) {
        $raw = @file_get_contents($legacy);
        $data = @json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    return null;
}

} // end if (!function_exists henaxai_manifest_schema)
