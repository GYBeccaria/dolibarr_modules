<?php
/* Copyright (C) 2026 Henaxis srl — GPL v3+ */

/**
 * \file    custom/henax-architect/lib/architect_manifest_builder.lib.php
 * \brief   Manifest YAML pipeline: source-of-truth → 3 file derivati (README, skyllam.json, architect.json).
 *
 * Workflow:
 *   1. henaxai_manifest_yaml_load($path)           → array unified
 *   2. henaxai_manifest_autodiscover($module_path) → array detected (rights/cron/tables/endpoints)
 *   3. henaxai_manifest_merge($yaml, $auto)        → array enriched (yaml override autodiscovery)
 *   4. henaxai_manifest_render_readme($enriched, $existing_readme_path) → markdown string (preserva MANUAL blocks)
 *   5. henaxai_manifest_render_skyllam($enriched)  → JSON string
 *   6. henaxai_manifest_render_architect($enriched) → JSON string
 *
 * Marker README per preservare contenuto manuale:
 *   <!-- AUTO-START:section --> ... <!-- AUTO-END:section -->   (rigenerato)
 *   <!-- MANUAL-START --> ... <!-- MANUAL-END -->                (preservato)
 *
 * Vendored: lib/vendor/Spyc.php (mustangostang/spyc, MIT).
 */

if (!defined('DOL_DOCUMENT_ROOT')) die('Forbidden');

require_once __DIR__.'/vendor/Spyc.php';
require_once __DIR__.'/henaxai_manifest.lib.php';
if (file_exists(__DIR__.'/henaxai_discovery.lib.php')) {
    require_once __DIR__.'/henaxai_discovery.lib.php';
}

// ============================================================================
// LOAD / SAVE manifest.yaml
// ============================================================================

/**
 * Carica manifest.yaml di un modulo.
 * @return array|null manifest parsed o null se non esiste
 */
function henaxai_manifest_yaml_load($manifest_yaml_path) {
    if (!file_exists($manifest_yaml_path)) return null;
    $raw = @file_get_contents($manifest_yaml_path);
    if ($raw === false) return null;
    try {
        $data = spyc_load($raw);
    } catch (Throwable $e) {
        dol_syslog("henaxai_manifest_yaml_load: parse error in $manifest_yaml_path: ".$e->getMessage(), LOG_ERR);
        return null;
    }
    if (!is_array($data)) return null;
    $data['_source_path'] = $manifest_yaml_path;
    return $data;
}

/**
 * Salva manifest.yaml (Spyc dump).
 * @return bool
 */
function henaxai_manifest_yaml_save($manifest_yaml_path, $array) {
    unset($array['_source_path']);
    $yaml = spyc_dump($array);
    return @file_put_contents($manifest_yaml_path, $yaml) !== false;
}

// ============================================================================
// AUTODISCOVERY (riusa architect_discovery.lib se disponibile + scan filesystem)
// ============================================================================

/**
 * Esegue autodiscovery sul modulo: rights, cron, tables, endpoints, git remote.
 * @param string $module_path es. "/var/www/html/custom/henaxinnerhelp"
 * @return array {detected_tables, detected_rights, detected_cron, detected_endpoints, git_remote}
 */
function henaxai_manifest_autodiscover($module_path) {
    $result = array(
        'detected_tables'    => array(),
        'detected_rights'    => array(),
        'detected_cron'      => array(),
        'detected_endpoints' => array(),
        'git_remote'         => '',
    );

    if (!is_dir($module_path)) return $result;

    // 1. Tabelle DB da sql/llx_*.sql
    $sql_dir = $module_path.'/sql';
    if (is_dir($sql_dir)) {
        foreach (glob($sql_dir.'/llx_*.sql') as $sql_file) {
            $tbl = basename($sql_file, '.sql');
            // Estrai solo nome tabella senza prefisso .key.sql ecc.
            $tbl = preg_replace('/\.(key|extrafields)$/', '', $tbl);
            if (!in_array($tbl, $result['detected_tables'], true)) {
                $result['detected_tables'][] = $tbl;
            }
        }
        sort($result['detected_tables']);
    }

    // 2. Rights da modXxx.class.php (estrae IDs dichiarati in $this->rights[])
    $core_modules = glob($module_path.'/core/modules/mod*.class.php');
    foreach ($core_modules as $modfile) {
        $content = @file_get_contents($modfile);
        if ($content === false) continue;
        // Cerca pattern: $this->rights[$r][0] = NUMERIC_ID;
        if (preg_match_all('/\$this->rights\[\$r\]\[0\]\s*=\s*(\d+)\s*;/', $content, $m)) {
            foreach ($m[1] as $rid) {
                $rid = (int)$rid;
                if (!in_array($rid, $result['detected_rights'], true)) {
                    $result['detected_rights'][] = $rid;
                }
            }
        }
        sort($result['detected_rights']);
    }

    // 3. Cron da bin/cron_*.php
    $bin_dir = $module_path.'/bin';
    if (is_dir($bin_dir)) {
        foreach (glob($bin_dir.'/cron_*.php') as $cron_file) {
            $rel = 'bin/'.basename($cron_file);
            $result['detected_cron'][] = $rel;
        }
        sort($result['detected_cron']);
    }

    // 4. Endpoints HTTP: admin/*.php + ajax/*.php + public/*.php (recursive 1 livello)
    foreach (array('admin', 'ajax', 'public', 'admin/ajax') as $sub) {
        $sub_dir = $module_path.'/'.$sub;
        if (!is_dir($sub_dir)) continue;
        foreach (glob($sub_dir.'/*.php') as $f) {
            $name = basename($f, '.php');
            // Skip files non-endpoint (lib_, _helper, ecc.)
            if (preg_match('/^(_|lib|helper|include)/', $name)) continue;
            $module_name = basename($module_path);
            $rel = '/custom/'.$module_name.'/'.$sub.'/'.basename($f);
            $result['detected_endpoints'][] = $rel;
        }
    }
    sort($result['detected_endpoints']);

    // 5. Git remote
    $git_config = $module_path.'/.git/config';
    if (file_exists($git_config)) {
        $cfg = @file_get_contents($git_config);
        if ($cfg && preg_match('#url\s*=\s*(https?://[^\s]+)#', $cfg, $m)) {
            $url = trim($m[1]);
            // Normalizza a "owner/repo"
            if (preg_match('#github\.com[:/]([^/]+)/([^/.\s]+)#', $url, $m2)) {
                $result['git_remote'] = $m2[1].'/'.$m2[2];
            } else {
                $result['git_remote'] = $url;
            }
        }
    }

    return $result;
}

// ============================================================================
// MERGE yaml + autodiscovery
// ============================================================================

/**
 * Merge manifest.yaml (priorità) con autodiscovery (fallback).
 * @return array enriched manifest
 */
function henaxai_manifest_merge($yaml, $auto) {
    if (!is_array($yaml)) $yaml = array();
    $enriched = $yaml;

    // Iniezione autodiscovery sotto chiave dedicata (read-only)
    $enriched['autodiscovery'] = $auto;

    // Se manca repo nel meta, prendi da git_remote
    if (empty($enriched['meta']['repo']) && !empty($auto['git_remote'])) {
        $enriched['meta']['repo'] = $auto['git_remote'];
    }

    return $enriched;
}

// ============================================================================
// RENDER 3 FILE DERIVATI
// ============================================================================

/**
 * Marker AUTO-START/AUTO-END identificano blocchi rigenerati nel README.
 * Tutto fuori dai blocchi AUTO è preservato (incluso MANUAL blocks).
 */
function henaxai_manifest_render_readme($enriched, $existing_readme_path = null) {
    $meta = $enriched['meta'] ?? array();
    $business = $enriched['business'] ?? array();
    $deps = $enriched['dependencies'] ?? array();
    $arch = $enriched['architect'] ?? array();
    $doc_links = $enriched['doc_links'] ?? array();
    $auto = $enriched['autodiscovery'] ?? array();

    // Costruisci blocchi AUTO da manifest
    $blocks = array();

    // META
    $module_id_str = !empty($meta['module_id']) ? "Modulo Dolibarr ID `".$meta['module_id']."` · " : "";
    $blocks['meta'] = "# ".($meta['display_name'] ?? $meta['name'] ?? 'Modulo')."\n\n"
        .$module_id_str."v".($meta['version'] ?? '?')." · status: **".($meta['status'] ?? '?')."**\n\n"
        .($business['short_description'] ?? '');

    // CAPABILITIES
    if (!empty($business['capabilities'])) {
        $cap = "## Caratteristiche\n\n";
        foreach ($business['capabilities'] as $c) {
            $cap .= "- ".$c."\n";
        }
        $blocks['capabilities'] = $cap;
    }

    // DEPENDENCIES
    if (!empty($deps)) {
        $dep_str = "## Dipendenze\n\n";
        if (!empty($deps['hard'])) {
            $dep_str .= "**Hard** (richieste):\n";
            foreach ($deps['hard'] as $d) {
                if (is_array($d)) {
                    foreach ($d as $k => $v) $dep_str .= "- `".$k."`: ".$v."\n";
                } else {
                    $dep_str .= "- `".$d."`\n";
                }
            }
        }
        if (!empty($deps['soft'])) {
            $dep_str .= "\n**Soft** (opzionali):\n";
            foreach ($deps['soft'] as $d) $dep_str .= "- `".$d."`\n";
        }
        $blocks['dependencies'] = $dep_str;
    }

    // RIGHTS
    if (!empty($arch['rights'])) {
        $r_str = "## Module ID + Rights\n\n";
        if (!empty($meta['module_id'])) $r_str .= "`".$meta['module_id']."`\n\n";
        $r_str .= "| ID | Permission | Assignment |\n|---|---|---|\n";
        foreach ($arch['rights'] as $rid => $rdef) {
            $label = is_array($rdef) ? ($rdef['label'] ?? '?') : $rdef;
            $assign = is_array($rdef) ? ($rdef['assignment'] ?? '-') : '-';
            $r_str .= "| `".$rid."` | ".$label." | ".$assign." |\n";
        }
        $blocks['rights'] = $r_str;
    }

    // CRON
    if (!empty($arch['cron'])) {
        $c_str = "## Cron jobs\n\n";
        foreach ($arch['cron'] as $cr) {
            $c_str .= "- `".($cr['file'] ?? '?')."` (`".($cr['schedule'] ?? 'manual')."`): ".($cr['purpose'] ?? '?')."\n";
        }
        $blocks['cron'] = $c_str;
    }

    // ENDPOINTS
    if (!empty($arch['services'])) {
        $e_str = "## Endpoints\n\n";
        foreach ($arch['services'] as $svc) {
            $e_str .= "- `".($svc['method'] ?? 'GET')." ".($svc['path'] ?? '?')."` — ".($svc['purpose'] ?? '?')."  \n  _Auth_: ".($svc['auth'] ?? 'admin')."\n";
        }
        $blocks['endpoints'] = $e_str;
    }

    // INSTALLATION
    if (!empty($business['installation_notes'])) {
        $blocks['installation'] = "## Installazione\n\n".$business['installation_notes'];
    }

    // TROUBLESHOOTING
    if (!empty($business['troubleshooting'])) {
        $tr = "## Troubleshooting\n\n";
        foreach ($business['troubleshooting'] as $item) {
            $tr .= "- **".($item['symptom'] ?? '?')."**: ".($item['fix'] ?? '?')."\n";
        }
        $blocks['troubleshooting'] = $tr;
    }

    // DOC LINKS
    if (!empty($doc_links)) {
        $dl = "## Documentazione dettagliata\n\n";
        foreach ($doc_links as $link) {
            $dl .= "- [".($link['title'] ?? '?')."](".($link['path'] ?? '#').")\n";
        }
        $blocks['doc_links'] = $dl;
    }

    // AUTODISCOVERY footer (read-only info)
    $afoot = "## Autodiscovery (read-only)\n\n";
    $afoot .= "_Generato automaticamente da_ `bin/build_manifests.php` _il_ ".date('Y-m-d H:i')."\n\n";
    $afoot .= "- Tabelle DB: ".(count($auto['detected_tables'] ?? array()))."\n";
    $afoot .= "- Rights: ".(count($auto['detected_rights'] ?? array()))."\n";
    $afoot .= "- Cron: ".(count($auto['detected_cron'] ?? array()))."\n";
    $afoot .= "- Endpoints: ".(count($auto['detected_endpoints'] ?? array()))."\n";
    if (!empty($auto['git_remote'])) $afoot .= "- Git: `".$auto['git_remote']."`\n";
    $blocks['autodiscovery'] = $afoot;

    // Compose: se README esistente, preserva MANUAL blocks. Else: nuovo da zero.
    if ($existing_readme_path && file_exists($existing_readme_path)) {
        $existing = file_get_contents($existing_readme_path);
        return _henaxai_manifest_readme_merge_blocks($existing, $blocks);
    }
    return _henaxai_manifest_readme_compose_new($blocks);
}

/**
 * Compose nuovo README from scratch (nessun MANUAL da preservare).
 */
function _henaxai_manifest_readme_compose_new($blocks) {
    $order = array('meta', 'capabilities', 'installation', 'dependencies', 'rights', 'cron', 'endpoints', 'troubleshooting', 'doc_links', 'autodiscovery');
    $out = "";
    foreach ($order as $key) {
        if (empty($blocks[$key])) continue;
        $out .= "<!-- AUTO-START:".$key." -->\n".$blocks[$key]."\n<!-- AUTO-END:".$key." -->\n\n";
    }
    return rtrim($out)."\n";
}

/**
 * Merge nuovi blocchi in README esistente, preservando MANUAL e fuori-blocchi.
 */
function _henaxai_manifest_readme_merge_blocks($existing, $blocks) {
    $out = $existing;
    foreach ($blocks as $key => $content) {
        $pattern = '/<!-- AUTO-START:'.preg_quote($key, '/').' -->[\s\S]*?<!-- AUTO-END:'.preg_quote($key, '/').' -->/';
        $replacement = "<!-- AUTO-START:".$key." -->\n".$content."\n<!-- AUTO-END:".$key." -->";
        if (preg_match($pattern, $out)) {
            $out = preg_replace($pattern, $replacement, $out);
        } else {
            // Blocco non esistente: append in fondo
            $out = rtrim($out)."\n\n".$replacement."\n";
        }
    }
    return $out;
}

/**
 * Render skyllam.json da manifest.
 */
function henaxai_manifest_render_skyllam($enriched) {
    $meta = $enriched['meta'] ?? array();
    $sky = $enriched['skyllam'] ?? array();
    $business = $enriched['business'] ?? array();

    // SkyllamManifest::loadAll() valida campi 'label' e 'description' come stringhe
    // non vuote (vedi custom/skyllam/class/skyllam_manifest.class.php). Senza questi
    // campi il modulo è scartato in silenzio (dol_syslog WARNING). Fix 2026-05-10:
    // emettiamo entrambi derivati da meta.display_name e business.short_description.
    $label = $meta['display_name'] ?? $meta['name'] ?? '';
    $description = trim((string)($business['short_description'] ?? ''));

    $out = array(
        'module' => $meta['name'] ?? 'unknown',
        'label' => $label,
        'display_name' => $meta['display_name'] ?? '',
        'version' => $meta['version'] ?? '0.0.0',
        'description' => $description,
        '_generated_at' => date('c'),
        '_generated_from' => 'manifest.yaml (build_manifests.php)',
        'entities' => $sky['entities'] ?? array(),
        'stats' => $sky['stats'] ?? array(),
        'patterns' => $sky['patterns'] ?? array(),
    );
    return json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Render architect.json da manifest.
 * Schema dual: emette sia campi NEW (depends/services oggetti) sia LEGACY (depends_on/services stringhe)
 * per compatibilità con henaxai_discovery.lib.php esistente.
 */
function henaxai_manifest_render_architect($enriched) {
    $meta = $enriched['meta'] ?? array();
    $deps = $enriched['dependencies'] ?? array();
    $arch = $enriched['architect'] ?? array();
    $auto = $enriched['autodiscovery'] ?? array();
    $business = $enriched['business'] ?? array();

    // LEGACY: depends_on come array piatto di stringhe (solo nomi moduli)
    $depends_on_legacy = array();
    foreach (array_merge($deps['hard'] ?? array(), $deps['soft'] ?? array()) as $d) {
        if (is_string($d)) $depends_on_legacy[] = $d;
        elseif (is_array($d)) {
            // skip key/value tipo dolibarr_min_version
            continue;
        }
    }
    // LEGACY: services_declared come array stringhe (path endpoint)
    $services_legacy = array();
    foreach ($arch['services'] ?? array() as $svc) {
        if (is_array($svc) && !empty($svc['path'])) $services_legacy[] = $svc['path'];
        elseif (is_string($svc)) $services_legacy[] = $svc;
    }
    // LEGACY: cron_declared come array stringhe (file path)
    $cron_legacy = array();
    foreach ($arch['cron'] ?? array() as $cr) {
        if (is_array($cr) && !empty($cr['file'])) $cron_legacy[] = $cr['file'];
        elseif (is_string($cr)) $cron_legacy[] = $cr;
    }
    // LEGACY: rights_range computato da min/max degli ID rights
    $rights_range_legacy = null;
    if (!empty($arch['rights'])) {
        $ids = array_map('intval', array_keys($arch['rights']));
        if ($ids) $rights_range_legacy = array(min($ids), max($ids));
    }

    $out = array(
        // === Campi LEGACY (consumati da henaxai_discovery.lib.php esistente) ===
        'label'        => $meta['display_name'] ?? $meta['name'] ?? '',
        'description'  => $business['short_description'] ?? '',
        'depends_on'   => $depends_on_legacy,
        'services'     => $services_legacy,
        'endpoints'    => $services_legacy,           // alias storico
        'cron'         => $cron_legacy,
        'rights_range' => $rights_range_legacy,
        'tables'       => $auto['detected_tables'] ?? array(),

        // === Campi NEW (richiesti dal nuovo manifest pipeline) ===
        'module'       => $meta['name'] ?? 'unknown',
        'display_name' => $meta['display_name'] ?? '',
        'module_id'    => $meta['module_id'] ?? null,
        'version'      => $meta['version'] ?? '0.0.0',
        'status'       => $meta['status'] ?? 'unknown',
        'category'     => $meta['category'] ?? 'misc',
        'repo'         => $meta['repo'] ?? '',
        'owner'        => $meta['owner'] ?? '',
        'license'      => $meta['license'] ?? '',
        '_generated_at'   => date('c'),
        '_generated_from' => 'manifest.yaml (build_manifests.php)',
        'depends'      => array(
            'hard' => $deps['hard'] ?? array(),
            'soft' => $deps['soft'] ?? array(),
        ),
        'rights'       => $arch['rights'] ?? array(),
        'cron_detail'  => $arch['cron'] ?? array(),         // versione strutturata
        'services_detail' => $arch['services'] ?? array(),  // versione strutturata
        'integrations' => $arch['integrations'] ?? array(),
        '_autodiscovery' => $auto,
    );
    return json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Build completo: legge manifest.yaml + autodiscovery + render 3 file.
 * @return array {readme, skyllam, architect, manifest_loaded, autodiscovery}
 */
function henaxai_manifest_build_all($module_path) {
    $manifest_path = $module_path.'/manifest.yaml';
    $yaml = henaxai_manifest_yaml_load($manifest_path);
    if (!$yaml) {
        return array('error' => "manifest.yaml non trovato in $module_path");
    }
    $auto = henaxai_manifest_autodiscover($module_path);
    $enriched = henaxai_manifest_merge($yaml, $auto);

    $existing_readme = $module_path.'/README.md';
    return array(
        'manifest_loaded' => true,
        'autodiscovery' => $auto,
        'enriched' => $enriched,
        'readme' => henaxai_manifest_render_readme($enriched, $existing_readme),
        'skyllam' => henaxai_manifest_render_skyllam($enriched),
        'architect' => henaxai_manifest_render_architect($enriched),
    );
}

/**
 * Confronto SEMANTIC: ignora campi volatili (_generated_at, _generated_from, riga timestamp README).
 * Necessario per idempotenza diff/apply (altrimenti ogni apply cambia timestamp e diff sempre ⚠️).
 */
function _henaxai_manifest_normalize_for_diff($content, $is_json) {
    if ($is_json) {
        $data = @json_decode($content, true);
        if (!is_array($data)) return $content;
        unset($data['_generated_at'], $data['_generated_from']);
        // Anche annidati (es. _autodiscovery non ha timestamp ma per safety)
        if (isset($data['_autodiscovery']) && is_array($data['_autodiscovery'])) {
            unset($data['_autodiscovery']['_generated_at']);
        }
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    // Markdown: ignora la riga "_Generato automaticamente ... il YYYY-MM-DD HH:MM_"
    $content = preg_replace('/_Generato automaticamente da_.*_il_\s*\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/', '_Generato automaticamente_', $content);
    return $content;
}

/**
 * Diff: confronta output vs file attuali (semantic, ignora timestamp).
 */
function henaxai_manifest_build_diff($module_path) {
    $build = henaxai_manifest_build_all($module_path);
    if (isset($build['error'])) return $build;

    $files = array(
        'README.md'      => array($build['readme'], false),
        'skyllam.json'   => array($build['skyllam'], true),
        'architect.json' => array($build['architect'], true),
    );

    $diff = array();
    foreach ($files as $fname => $info) {
        list($new_content, $is_json) = $info;
        $path = $module_path.'/'.$fname;
        $current = file_exists($path) ? file_get_contents($path) : '';

        $current_norm = _henaxai_manifest_normalize_for_diff(trim($current), $is_json);
        $new_norm     = _henaxai_manifest_normalize_for_diff(trim($new_content), $is_json);

        $diff[$fname] = array(
            'exists' => file_exists($path),
            'same' => $current_norm === $new_norm,
            'current_size' => strlen($current),
            'new_size' => strlen($new_content),
        );
    }
    return array('build' => $build, 'diff' => $diff);
}

/**
 * Apply: scrive i 3 file. Ritorna conteggi.
 */
function henaxai_manifest_build_apply($module_path) {
    $build = henaxai_manifest_build_all($module_path);
    if (isset($build['error'])) return $build;

    $written = array();
    $files = array(
        'README.md' => $build['readme'],
        'skyllam.json' => $build['skyllam'],
        'architect.json' => $build['architect'],
    );
    foreach ($files as $fname => $content) {
        $path = $module_path.'/'.$fname;
        $ok = @file_put_contents($path, rtrim($content)."\n") !== false;
        $written[$fname] = $ok;
    }
    return array('written' => $written, 'build' => $build);
}