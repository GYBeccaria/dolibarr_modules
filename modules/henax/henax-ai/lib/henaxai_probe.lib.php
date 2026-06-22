<?php
/* Copyright (C) 2026 P2G Connecto — GPL v3+ */

/**
 * \file    custom/henax-ai/lib/henaxai_probe.lib.php
 * \brief   STADIO DI VALIDAZIONE/VALUTAZIONE PROVIDER+API KEY (L0).
 *
 * Il sistema non puo' sapere quale LLM usa il cliente: serve poter TESTARE una
 * coppia provider+key (autentica? quali modelli espone? latenza?) in modo
 * NON distruttivo e indipendente dall'uso reale. Usa il registry condiviso del client.
 *
 * Metodo di probe per provider:
 *  - openai-compatible CON /models  -> GET /models (lista modelli); 404/405 -> fallback chat
 *  - openai-compatible SENZA /models -> POST /chat/completions minimale (solo key-check)
 *  - anthropic -> GET /v1/models ; ollama -> GET /api/tags ; anythingllm -> GET /api/v1/system/check
 *
 * API: henaxai_providers(), henaxai_validate($opts), henaxai_validate_candidates($cands).
 * Esito: ['ok','provider','label','model','authenticated','models'[],'http_code','latency_ms','error'].
 */

if (!defined('DOL_DOCUMENT_ROOT')) die('Forbidden');
require_once __DIR__.'/henaxai_client.lib.php';   // registry + henaxai_resolve_config()

function henaxai_providers(): array
{
    return henaxai_provider_registry();
}

/** URL "list models / auth-check" per famiglie con endpoint dedicato. */
function _henaxai_probe_models_url(string $base, array $def): string
{
    switch ($def['family']) {
        case 'anthropic':   return ($base ?: 'https://api.anthropic.com').'/v1/models';
        case 'ollama':      return ($base ?: 'http://localhost:11434').'/api/tags';
        case 'anythingllm': return $base.'/api/v1/system/check';
        case 'openai':
        default:            return ($base ?: 'https://api.openai.com/v1').'/models';
    }
}

function _henaxai_auth_headers(array $def, string $key): array
{
    $h = array('Accept: application/json');
    if ($def['auth'] === 'bearer' && $key !== '') {
        $h[] = 'Authorization: Bearer '.$key;
    } elseif ($def['auth'] === 'x-api-key') {
        $h[] = 'x-api-key: '.$key;
        $h[] = 'anthropic-version: 2023-06-01';
    }
    return $h;
}

/** Valida UNA coppia provider+key (non distruttiva). */
function henaxai_validate(array $opts): array
{
    $cfg = henaxai_resolve_config($opts);
    $provider = $cfg['provider'];
    $reg = henaxai_provider_registry();
    $def = $reg[$provider] ?? null;

    $out = array('ok' => false, 'provider' => $provider, 'label' => $def['label'] ?? $provider,
                 'model' => $cfg['model'], 'authenticated' => false, 'models' => array(),
                 'http_code' => 0, 'latency_ms' => 0, 'error' => null);

    if ($def === null) { $out['error'] = "provider non supportato: $provider"; return $out; }

    $base = rtrim($cfg['endpoint'] ?: $def['base'], '/');
    $headers = _henaxai_auth_headers($def, $cfg['api_key']);

    // openai-compatible SENZA /models -> valida via chat
    if ($def['family'] === 'openai' && empty($def['models'])) {
        return _henaxai_validate_via_chat($out, $base, $headers, $cfg);
    }

    $url = _henaxai_probe_models_url($base, $def);
    if (strpos($url, '//') === false || preg_match('#^/?api/#', $url)) {
        $out['error'] = "base URL non configurata per $provider"; return $out;
    }

    [$code, $body, $err, $ms] = _henaxai_http($url, 'GET', $headers, null, $def['family'] === 'ollama');
    $out['http_code'] = $code; $out['latency_ms'] = $ms;
    if ($err !== '') { $out['error'] = 'rete: '.$err; return $out; }
    $out['ok'] = true;

    if ($code === 401 || $code === 403) { $out['error'] = "key rifiutata (HTTP $code)"; return $out; }
    if ($code >= 200 && $code < 300) {
        $out['authenticated'] = true;
        $out['models'] = _henaxai_extract_models($def['family'], json_decode($body, true));
        return $out;
    }
    // /models assente (404/405) su famiglia openai -> fallback chat
    if (($code === 404 || $code === 405) && $def['family'] === 'openai') {
        return _henaxai_validate_via_chat($out, $base, $headers, $cfg);
    }
    $out['error'] = "HTTP $code (verifica base URL/endpoint)";
    return $out;
}

/** Key-check via POST /chat/completions minimale. 401/403=rifiutata; 2xx o 4xx-non-404=auth ok. */
function _henaxai_validate_via_chat(array $out, string $base, array $headers, array $cfg): array
{
    if ($base === '') { $out['error'] = "base URL non configurata per ".$out['provider']; return $out; }
    $url = $base.'/chat/completions';
    $hdr = array_merge(array('Content-Type: application/json'), $headers);
    $payload = json_encode(array(
        'model' => $cfg['model'] ?: 'x',
        'messages' => array(array('role' => 'user', 'content' => 'ping')),
        'max_tokens' => 1,
    ));
    [$code, $body, $err, $ms] = _henaxai_http($url, 'POST', $hdr, $payload, false);
    $out['http_code'] = $code; $out['latency_ms'] = $ms;
    if ($err !== '') { $out['error'] = 'rete: '.$err; return $out; }
    $out['ok'] = true;

    // Solo 2xx = autenticato (mai falsi positivi: un 400 puo' significare "key mancante",
    // es. Gemini risponde 400 senza key). 401/403 = rifiutata; resto = indeterminato.
    if ($code === 401 || $code === 403) { $out['error'] = "key rifiutata (HTTP $code)"; return $out; }
    if ($code >= 200 && $code < 300) { $out['authenticated'] = true; return $out; }
    $out['error'] = "HTTP $code (indeterminato: controlla key/modello/base)";
    return $out;
}

/**
 * Stage di selezione: valida N candidati.
 * @param array $candidates  ['label' => $opts, ...]
 * @return array  ['label' => risultato henaxai_validate, ...]
 */
function henaxai_validate_candidates(array $candidates): array
{
    $out = array();
    foreach ($candidates as $label => $opts) {
        $out[$label] = henaxai_validate(is_array($opts) ? $opts : array());
    }
    return $out;
}

/* ---------- helpers ---------- */

function _henaxai_extract_models(string $family, $j): array
{
    if (!is_array($j)) return array();
    $models = array();
    switch ($family) {
        case 'openai':
        case 'anthropic':  // {data:[{id},...]}
            foreach (($j['data'] ?? array()) as $m) { if (!empty($m['id'])) $models[] = $m['id']; }
            break;
        case 'ollama':     // {models:[{name},...]}
            foreach (($j['models'] ?? array()) as $m) { if (!empty($m['name'])) $models[] = $m['name']; }
            break;
    }
    return $models;
}

/** HTTP GET/POST con codice + latenza. */
function _henaxai_http(string $url, string $method, array $headers, ?string $body, bool $insecure): array
{
    $t0 = microtime(true);
    $ch = curl_init($url);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => $insecure ? false : true,
    );
    if ($method === 'POST') { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = $body; }
    else { $opts[CURLOPT_HTTPGET] = true; }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    return array($code, $resp === false ? '' : $resp, $resp === false ? $err : '', $ms);
}
