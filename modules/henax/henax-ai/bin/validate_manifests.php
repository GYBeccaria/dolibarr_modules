<?php
/* Copyright (C) 2026 Henaxis srl — GPL v3+
 *
 * Validation script per architect.{json,toml} di tutti i moduli custom/.
 * Cross-check ogni campo dichiarato vs realtà DB+FS:
 *   - tables: information_schema.TABLES
 *   - endpoints: file_exists()
 *   - drill_links: file_exists()
 *   - rights_range: SELECT MIN/MAX FROM llx_rights_def WHERE module=X
 *   - depends_on: directory custom/<dep>/ esiste
 *   - cron: script esiste in bin/, cron/, root
 *
 * Usage:
 *   php custom/henax-ai/bin/validate_manifests.php
 *
 * Exit code: 0 se tutto OK, 1 se warnings, 2 se errori parser.
 *
 * Da eseguire SEMPRE prima del commit di un manifest (regola: validate, don't invent).
 */

if (php_sapi_name() !== 'cli') die("CLI only\n");

// Bootstrap Dolibarr parametrico: env DOL_DOCUMENT_ROOT o autodetect (master.inc.php risalendo).
$dolRoot = getenv('DOL_DOCUMENT_ROOT') ?: '';
if ($dolRoot === '') { $d = __DIR__; for ($i=0;$i<8;$i++){ if (file_exists($d.'/master.inc.php')){ $dolRoot=$d; break; } $d=dirname($d);} }
if ($dolRoot === '' || !file_exists($dolRoot.'/master.inc.php')) { echo "ERROR: imposta DOL_DOCUMENT_ROOT o esegui da dentro l'albero Dolibarr\n"; exit(2); }
$res = @include $dolRoot.'/master.inc.php';
if (!$res) { echo "ERROR: cannot include master.inc.php\n"; exit(2); }

require_once DOL_DOCUMENT_ROOT."/custom/henax-ai/lib/henaxai_manifest.lib.php";
global $db;

$customDir = DOL_DOCUMENT_ROOT."/custom";
$mods = scandir($customDir);
$total_warn = 0;
$total_modules = 0;

foreach ($mods as $mod) {
    if ($mod === '.' || $mod === '..') continue;
    $base = $customDir.'/'.$mod;
    if (!is_dir($base)) continue;
    if (in_array(strtolower($mod), array('documents', 'install', 'theme', 'cache'), true)) continue;

    $manifest = henaxai_load_manifest($base);
    if (!$manifest) continue;
    $total_modules++;
    $warns = array();

    // 1. Tables
    if (!empty($manifest["tables"]) && is_array($manifest["tables"])) {
        foreach ($manifest["tables"] as $t) {
            $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$db->escape($t)."'";
            $r = $db->query($sql);
            if (!$r || !$db->fetch_object($r)) $warns[] = "table NOT EXISTS: $t";
        }
    }
    // 2. Endpoints
    if (!empty($manifest["endpoints"]) && is_array($manifest["endpoints"])) {
        foreach ($manifest["endpoints"] as $ep) {
            $p = $ep["path"] ?? "";
            if ($p === "" || strpos($p, "/custom/") !== 0) continue;
            if (!file_exists(DOL_DOCUMENT_ROOT.$p)) $warns[] = "endpoint NOT EXISTS: $p";
        }
    }
    // 3. drill_links
    if (!empty($manifest["drill_links"]) && is_array($manifest["drill_links"])) {
        foreach ($manifest["drill_links"] as $key => $url) {
            if (!is_string($url) || strpos($url, "/custom/") !== 0) continue;
            if (!file_exists(DOL_DOCUMENT_ROOT.$url)) $warns[] = "drill_link[$key] NOT EXISTS: $url";
        }
    }
    // 4. rights_range
    if (!empty($manifest["rights_range"]) && is_array($manifest["rights_range"]) && count($manifest["rights_range"]) >= 2) {
        $min = (int)$manifest["rights_range"][0];
        $max = (int)$manifest["rights_range"][1];
        $sql = "SELECT MIN(id) AS rmin, MAX(id) AS rmax, COUNT(*) AS rn FROM ".MAIN_DB_PREFIX."rights_def WHERE module='".$db->escape($mod)."'";
        $r = $db->query($sql);
        if ($r && $o = $db->fetch_object($r)) {
            if ($o->rn == 0) $warns[] = "rights_range $min-$max but DB has 0 rights for module $mod";
            elseif ($o->rmin != $min || $o->rmax != $max) $warns[] = "rights_range mismatch: declared $min-$max, DB actual ".$o->rmin."-".$o->rmax;
        }
    }
    // 5. depends_on
    if (!empty($manifest["depends_on"]) && is_array($manifest["depends_on"])) {
        foreach ($manifest["depends_on"] as $dep) {
            $found = false;
            foreach (array(DOL_DOCUMENT_ROOT."/custom/".strtolower($dep), DOL_DOCUMENT_ROOT."/".$dep) as $c) {
                if (is_dir($c)) { $found = true; break; }
            }
            if (!$found) $warns[] = "depends_on[$dep] modulo NOT FOUND";
        }
    }
    // 6. cron
    if (!empty($manifest["cron"]) && is_array($manifest["cron"])) {
        foreach ($manifest["cron"] as $cron_str) {
            if (preg_match("#^([\w/.-]+\.(?:php|py))#", $cron_str, $m)) {
                $script = $m[1];
                $found = false;
                foreach (array("$base/$script", "$base/bin/$script", "$base/cron/$script") as $c) {
                    if (file_exists($c)) { $found = true; break; }
                }
                if (!$found) $warns[] = "cron script NOT FOUND: $script";
            }
        }
    }

    // === Block Skyllam validation (entities + stats SQL + navigation) ===
    $sk = null;
    if (!empty($manifest["skyllam"]) && is_array($manifest["skyllam"])) {
        $sk = $manifest["skyllam"];
    } elseif (!empty($manifest["skyllam_legacy_file"])) {
        $legacy = $base.'/'.basename($manifest["skyllam_legacy_file"]);
        if (file_exists($legacy)) $sk = json_decode(@file_get_contents($legacy), true);
    }
    if (is_array($sk)) {
        // 7. skyllam.entities[].table
        if (!empty($sk["entities"]) && is_array($sk["entities"])) {
            foreach ($sk["entities"] as $i => $ent) {
                $t = $ent["table"] ?? "";
                if ($t === "") continue;
                $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$db->escape($t)."'";
                $r = $db->query($sql);
                if (!$r || !$db->fetch_object($r)) $warns[] = "skyllam.entities[$i].table NOT EXISTS: $t";
            }
        }
        // 8. skyllam.stats[].sql — execute via EXPLAIN per validare sintassi
        // Placeholders runtime: {entity}, {today}, {now}, {fk_user} (sostituiti da Skyllam loader)
        if (!empty($sk["stats"]) && is_array($sk["stats"])) {
            // Placeholders che possono apparire quoted ('{today}') o unquoted ({entity}).
            // Sostituiamo prima i quoted con valori già quotati (per non duplicare le quote),
            // poi gli unquoted con valori bare.
            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            $sub_quoted = array(
                "'{today}'" => "'$today'",
                "'{now}'"   => "'$now'",
            );
            $sub_unquoted = array(
                '{entity}'  => '1',
                '{today}'   => "'$today'",
                '{now}'     => "'$now'",
                '{fk_user}' => '1',
                '{user_id}' => '1',
                '{id}'      => '1',
            );
            foreach ($sk["stats"] as $i => $st) {
                $sql = $st["sql"] ?? "";
                if ($sql === "") continue;
                $sql_resolved = strtr($sql, $sub_quoted);
                $sql_resolved = strtr($sql_resolved, $sub_unquoted);
                $explain = "EXPLAIN ".$sql_resolved;
                $r = @$db->query($explain);
                if (!$r) {
                    $key = $st["key"] ?? $st["type"] ?? $i;
                    $warns[] = "skyllam.stats[$key].sql ERROR: ".substr($db->lasterror(), 0, 120);
                }
            }
        }
        // 9. skyllam.navigation.pages[*] — verifica path file (strip query string)
        if (!empty($sk["navigation"]["pages"]) && is_array($sk["navigation"]["pages"])) {
            foreach ($sk["navigation"]["pages"] as $key => $url) {
                if (!is_string($url) || strpos($url, "/custom/") !== 0) continue;
                $path_only = preg_replace('/\?.*$/', '', $url); // strip ?query
                if (!file_exists(DOL_DOCUMENT_ROOT.$path_only)) $warns[] = "skyllam.navigation.pages[$key] NOT EXISTS: $url";
            }
        }
        // 10. skyllam.navigation.keywords[*] — verifica path file (strip query string)
        if (!empty($sk["navigation"]["keywords"]) && is_array($sk["navigation"]["keywords"])) {
            foreach ($sk["navigation"]["keywords"] as $key => $url) {
                if (!is_string($url) || strpos($url, "/custom/") !== 0) continue;
                $path_only = preg_replace('/\?.*$/', '', $url);
                if (!file_exists(DOL_DOCUMENT_ROOT.$path_only)) $warns[] = "skyllam.navigation.keywords[$key] NOT EXISTS: $url";
            }
        }
        // 11. skyllam.tables — può essere array piatto o {list: [...]}
        $sk_tables = array();
        if (!empty($sk["tables"])) {
            if (is_array($sk["tables"]) && isset($sk["tables"][0])) $sk_tables = $sk["tables"]; // piatto
            elseif (is_array($sk["tables"]) && isset($sk["tables"]["list"])) $sk_tables = $sk["tables"]["list"]; // wrapped (TOML)
        }
        foreach ($sk_tables as $t) {
            if (!is_string($t)) continue;
            $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$db->escape($t)."'";
            $r = $db->query($sql);
            if (!$r || !$db->fetch_object($r)) $warns[] = "skyllam.tables NOT EXISTS: $t";
        }
    }

    if (empty($warns)) {
        echo "[$mod] OK\n";
    } else {
        echo "[$mod] " . count($warns) . " issues:\n";
        foreach ($warns as $w) echo "    - $w\n";
        $total_warn += count($warns);
    }
}
echo "\n=== Validated $total_modules modules with manifest ===\n";
echo "TOTAL warnings: $total_warn\n";
exit($total_warn === 0 ? 0 : 1);
