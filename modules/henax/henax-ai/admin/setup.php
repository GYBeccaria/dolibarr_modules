<?php
/* Copyright (C) 2026 P2G Connecto — GPL v3+ */

/**
 * \file    custom/henax-ai/admin/setup.php
 * \brief   Setup + validazione provider/API key di henax-ai.
 *          L'operatore inserisce le key per provider, valida (autentica? modelli? latenza?)
 *          e seleziona il provider attivo. Le key sono salvate in const HENAXAI_KEY_<P>.
 */

// Bootstrap Dolibarr (cascade standard moduli custom: admin -> henax-ai -> custom -> root)
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/henax-ai/lib/henaxai_probe.lib.php';

global $db, $conf, $user, $langs;
if (empty($user->admin)) accessforbidden();

$action = GETPOST('action', 'az');
$registry = henaxai_provider_registry();

/* ---------------- Azioni ---------------- */
if ($action === 'save') {
    foreach ($registry as $p => $def) {
        $P = henaxai_provider_const_suffix($p);
        // API key: aggiorna solo se valorizzata; azzera se richiesto
        if (GETPOST('clear_'.$P, 'int')) {
            dolibarr_del_const($db, 'HENAXAI_KEY_'.$P, $conf->entity);
        } else {
            $k = GETPOST('key_'.$P, 'restricthtml');
            if ($k !== '') dolibarr_set_const($db, 'HENAXAI_KEY_'.$P, trim($k), 'chaine', 0, '', $conf->entity);
        }
        // Base URL override (vuoto = default registry)
        $b = GETPOST('base_'.$P, 'restricthtml');
        if ($b !== '') dolibarr_set_const($db, 'HENAXAI_BASE_'.$P, trim($b), 'chaine', 0, '', $conf->entity);
        else dolibarr_del_const($db, 'HENAXAI_BASE_'.$P, $conf->entity);
    }
    // Provider attivo + modello
    $active = GETPOST('active_provider', 'az');
    if ($active !== '' && isset($registry[$active])) dolibarr_set_const($db, 'HENAXAI_PROVIDER', $active, 'chaine', 0, '', $conf->entity);
    $model = GETPOST('active_model', 'restricthtml');
    if ($model !== '') dolibarr_set_const($db, 'HENAXAI_MODEL', trim($model), 'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

$validation = array();
if ($action === 'validate') {
    $cands = array();
    foreach ($registry as $p => $def) {
        $P = henaxai_provider_const_suffix($p);
        $key = getDolGlobalString('HENAXAI_KEY_'.$P, '');
        $base = getDolGlobalString('HENAXAI_BASE_'.$P, '');
        // valida solo i provider con key (o ollama, senza auth)
        if ($key === '' && $def['auth'] !== 'none') continue;
        $opts = array('provider' => $p, 'api_key' => $key);
        if ($base !== '') $opts['endpoint'] = $base;
        $cands[$p] = $opts;
    }
    $validation = henaxai_validate_candidates($cands);
}

/* ---------------- Render ---------------- */
$activeProvider = getDolGlobalString('HENAXAI_PROVIDER', 'openai');
$activeModel = getDolGlobalString('HENAXAI_MODEL', 'gpt-4o-mini');

llxHeader('', 'henax-ai — Setup provider AI');
print load_fiche_titre('henax-ai — Provider AI &amp; validazione API key', '', 'fa-brain');
print '<p class="opacitymedium">Il sistema non sa quale LLM usa il cliente: inserisci le API key dei provider che vuoi usare, '
    .'poi <b>Valida</b> per vedere quali autenticano e quali modelli espongono. Infine seleziona il provider attivo.</p>';

// --- Esito validazione (se richiesta) ---
if ($action === 'validate') {
    print load_fiche_titre('Esito validazione', '', '');
    print '<table class="noborder centpercent"><tr class="liste_titre">';
    print '<td>Provider</td><td>Autentica</td><td>HTTP</td><td>Latenza</td><td>Modelli</td><td>Note</td></tr>';
    foreach ($validation as $p => $r) {
        $color = $r['authenticated'] ? '#1a7f37' : ($r['http_code'] === 401 || $r['http_code'] === 403 ? '#cf222e' : '#9a6700');
        $badge = $r['authenticated'] ? 'OK' : (!$r['ok'] ? 'RETE' : 'NO');
        print '<tr class="oddeven">';
        print '<td><b>'.dol_escape_htmltag($r['label']).'</b> <span class="opacitymedium">('.$p.')</span></td>';
        print '<td style="color:'.$color.';font-weight:bold;">'.$badge.'</td>';
        print '<td>'.($r['http_code'] ?: '-').'</td>';
        print '<td>'.$r['latency_ms'].' ms</td>';
        print '<td>'.(count($r['models']) ? count($r['models']).' <span class="opacitymedium">('.dol_escape_htmltag(implode(', ', array_slice($r['models'], 0, 4))).(count($r['models']) > 4 ? '…' : '').')</span>' : '-').'</td>';
        print '<td class="opacitymedium">'.dol_escape_htmltag($r['error'] ?? '').'</td>';
        print '</tr>';
    }
    if (empty($validation)) print '<tr class="oddeven"><td colspan="6">Nessun provider con API key impostata.</td></tr>';
    print '</table><br>';
}

// --- Form configurazione ---
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="noborder centpercent"><tr class="liste_titre">';
print '<td>Provider</td><td>Famiglia</td><td>API key</td><td>Base URL (override)</td><td title="Provider usato a runtime">Attivo</td></tr>';
foreach ($registry as $p => $def) {
    $P = henaxai_provider_const_suffix($p);
    $hasKey = getDolGlobalString('HENAXAI_KEY_'.$P, '') !== '';
    $base = getDolGlobalString('HENAXAI_BASE_'.$P, '');
    print '<tr class="oddeven">';
    print '<td><b>'.dol_escape_htmltag($def['label']).'</b><br><span class="opacitymedium">'.$p.'</span></td>';
    print '<td>'.$def['family'].($def['family'] === 'anthropic' ? ' <span class="opacitymedium">(nativo)</span>' : '').'</td>';
    print '<td>';
    if ($def['auth'] === 'none') {
        print '<span class="opacitymedium">nessuna auth</span>';
    } else {
        print '<input type="password" name="key_'.$P.'" autocomplete="off" placeholder="'.($hasKey ? '•••• impostata' : 'incolla la key').'" style="width:220px;">';
        if ($hasKey) print ' <label class="opacitymedium"><input type="checkbox" name="clear_'.$P.'" value="1"> azzera</label>';
    }
    print '</td>';
    print '<td><input type="text" name="base_'.$P.'" value="'.dol_escape_htmltag($base).'" placeholder="'.dol_escape_htmltag($def['base'] ?: '(richiesta)').'" style="width:260px;"></td>';
    print '<td style="text-align:center;"><input type="radio" name="active_provider" value="'.$p.'"'.($activeProvider === $p ? ' checked' : '').'></td>';
    print '</tr>';
}
print '</table>';

print '<br><div>Modello del provider attivo: <input type="text" name="active_model" value="'.dol_escape_htmltag($activeModel).'" style="width:240px;"> ';
print '<span class="opacitymedium">(es. gpt-4o-mini, claude-opus-4-8, gemini-2.0-flash, …)</span></div>';

print '<br><div class="center">';
print '<input type="submit" class="button" value="Salva">';
print '</div></form>';

// --- Pulsante valida ---
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'"><div class="center">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="validate">';
print '<input type="submit" class="button button-cancel" value="Valida i provider con API key">';
print '</div></form>';

print '<br><p class="opacitymedium"><b>Nota</b>: le base URL sono default best-effort, sovrascrivibili per provider. '
    .'Se un provider torna HTTP 404 in validazione, correggi la base URL. Le key sono salvate in chiaro nelle costanti Dolibarr (come SKYLLAM_*/HENAXARCHITECT_*).</p>';

llxFooter();
$db->close();
