<?php
/* Copyright (C) 2026 P2G Connecto — GPL v3+ */

/**
 * \file    custom/henax-ai/bin/build_manifests.php
 * \brief   CLI generatore 3 file derivati (README/skyllam.json/architect.json) da manifest.yaml.
 *          Portato da henax-architect/bin/build_manifests.php — bootstrap PARAMETRICO.
 *
 * Uso:
 *   php bin/build_manifests.php --module=<name> [--diff|--apply] [--all] [--quiet]
 *   DOL_ROOT bootstrap: variabile d'ambiente DOL_DOCUMENT_ROOT, oppure autodetect
 *   risalendo le cartelle fino a trovare master.inc.php.
 *
 * Exit codes: 0 ok/no-diff · 1 manifest missing/invalid · 2 write error · 3 diff presente (pre-commit hook)
 */

if (php_sapi_name() !== 'cli') die("CLI only\n");

$opts = getopt('', array('module:', 'all', 'diff', 'apply', 'quiet'));
$mode = isset($opts['apply']) ? 'apply' : 'diff';
$quiet = isset($opts['quiet']);

if (!isset($opts['all']) && empty($opts['module'])) {
    fwrite(STDERR, "Usage: php bin/build_manifests.php --module=<name> [--diff|--apply]\n       php bin/build_manifests.php --all [--diff|--apply]\n");
    exit(1);
}

// --- Bootstrap Dolibarr parametrico ---
$dolRoot = getenv('DOL_DOCUMENT_ROOT') ?: '';
if ($dolRoot === '') {
    // autodetect: risali da __DIR__ (custom/henax-ai/bin) cercando master.inc.php
    $d = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        if (file_exists($d.'/master.inc.php')) { $dolRoot = $d; break; }
        $d = dirname($d);
    }
}
if ($dolRoot === '' || !file_exists($dolRoot.'/master.inc.php')) {
    fwrite(STDERR, "Bootstrap fallito: imposta DOL_DOCUMENT_ROOT o esegui da dentro l'albero Dolibarr.\n");
    exit(1);
}
$_SERVER['DOCUMENT_ROOT'] = $dolRoot;
chdir($dolRoot);
$res = @include $dolRoot.'/master.inc.php';
if (!$res) { fwrite(STDERR, "Failed to bootstrap Dolibarr\n"); exit(1); }

require_once DOL_DOCUMENT_ROOT.'/custom/henax-ai/lib/henaxai_manifest_builder.lib.php';

$custom_root = DOL_DOCUMENT_ROOT.'/custom';

$modules = array();
if (isset($opts['all'])) {
    foreach (glob($custom_root.'/*/manifest.yaml') as $mp) {
        $modules[] = basename(dirname($mp));
    }
    if (empty($modules)) {
        fwrite(STDERR, "No modules with manifest.yaml found in $custom_root\n");
        exit(1);
    }
} else {
    $modules[] = $opts['module'];
}

$any_diff = false;
$any_error = false;

foreach ($modules as $module) {
    $mpath = $custom_root.'/'.$module;
    if (!is_dir($mpath)) {
        fwrite(STDERR, "[X] Module dir not found: $mpath\n");
        $any_error = true;
        continue;
    }
    $mfile = $mpath.'/manifest.yaml';
    if (!file_exists($mfile)) {
        if (!$quiet) fwrite(STDOUT, "[skip] $module: manifest.yaml missing\n");
        continue;
    }

    if ($mode === 'diff') {
        $r = henaxai_manifest_build_diff($mpath);
        if (isset($r['error'])) {
            fwrite(STDERR, "[X] $module: ".$r['error']."\n");
            $any_error = true;
            continue;
        }
        $module_diff = false;
        foreach ($r['diff'] as $fname => $d) {
            $marker = $d['same'] ? '[ok]' : '[!!]';
            if (!$d['same']) { $module_diff = true; $any_diff = true; }
            if (!$quiet) {
                $size_str = $d['exists'] ? "{$d['current_size']}->{$d['new_size']}" : "missing->{$d['new_size']}";
                fwrite(STDOUT, sprintf("%s %-30s %-18s %s\n", $marker, $module, $fname, $size_str));
            }
        }
        if (!$module_diff && !$quiet) fwrite(STDOUT, "[ok] $module: 3 files in sync\n");
    } elseif ($mode === 'apply') {
        $r = henaxai_manifest_build_apply($mpath);
        if (isset($r['error'])) {
            fwrite(STDERR, "[X] $module: ".$r['error']."\n");
            $any_error = true;
            continue;
        }
        foreach ($r['written'] as $fname => $ok) {
            $marker = $ok ? '[ok]' : '[X]';
            if (!$quiet) fwrite(STDOUT, "$marker $module/$fname\n");
            if (!$ok) $any_error = true;
        }
    }
}

if ($any_error) exit(2);
if ($mode === 'diff' && $any_diff) exit(3);
exit(0);
