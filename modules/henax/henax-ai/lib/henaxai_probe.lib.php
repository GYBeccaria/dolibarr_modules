<?php
/* Copyright (C) 2026 P2G Connecto — GPL v3+ */

/**
 * \file    custom/henax-ai/lib/henaxai_probe.lib.php
 * \brief   STADIO DI VALIDAZIONE/VALUTAZIONE PROVIDER+API KEY (L0).
 *
 * Il sistema non puo' sapere quale LLM usa il cliente: serve poter TESTARE una
 * coppia provider+key (autentica? quali modelli espone? latenza?) in modo
 * NON distruttivo e indipendente dall'uso reale. Generalizza skyllam::detect()
 * e architect_ai_resolve_api_key(). Usa il registry condiviso del client.
 *
 * API:
 *   henaxai_providers(): array                        // = henaxai_provider_registry()
 *   henaxai_validate(array $opts): array              // valida UNA config provider+key
 *   henaxai_validate_candidates(array $cands): array  // valida N candidati (stage selezione)
 *
 * Esito henaxai_validate():
 *   ['ok'=>bool,            // richiesta completata (no errore di rete)
 *    'provider'=>str, 'label'=>str, 'model'=>str,
 *    'authenticated'=>bool, // key accettata (no 401/403)
 *    'models'=>[str...],    // modelli esposti dal provider (se elencabili)
 *    'http_code'=>int, 'latency_ms'=>int, 'error'=>str|null]
 */

if (!defined('DOL_DOCUMENT_ROOT')) die('Forbidden');
require_once __DIR__.'/henaxai_client.lib.php';   // registry + henaxai_resolve_config()

/** Elenco provider supportati (= registry del client). */
function henaxai_providers(): array
{
    return henaxai_provider_registry();
}

/** Deriva l'URL "list models / auth-check" non distruttivo per una config risolta. */
function _henaxai_probe_url(array $cfg, array $def): string
{
    $base = rtrim($cfg['endpoint'] ?: $def['base'], '/');
    switch ($def['family']) {
        case 'anthropic':   return ($base ?: 'https://api.anthropic.com').'/v1/models';
        case 'ollama':      return ($base ?: 'http://localhost:11434').'/api/tags';
        case 'anythingllm': return $base.'/api/v1/system/check';
        case 'openai':
        default:            return ($base ?: 'https://api.openai.com/v1').'/models';
    }
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

    $url = _henaxai_probe_url($cfg, $def);
    if (strpos($url, '//') === false || preg_match('#^/?api/#', $url)) {
        // base mancante per provider che la richiede (anythingllm/openai-compatible custom)
        $out['error'] = "base URL non configurata per $provider"; return $out;
    }

    // Header auth per famiglia/auth
    $headers = array('Accept: application/json');
    if ($def['auth'] === 'bearer' && $cfg['api_key'] !== '') {
        $headers[] = 'Authorization: Bearer '.$cfg['api_key'];
    } elseif ($def['auth'] === 'x-api-key') {
        $headers[] = 'x-api-key: '.$cfg['api_key'];
        $headers[] = 'anthropic-version: 2023-06-01';
    }

    [$code, $body, $err, $ms] = _henaxai_http_get($url, $headers, $def['family'] === 'ollama');
    $out['http_code'] = $code;
    $out['latency_ms'] = $ms;

    if ($err !== '') { $out['error'] = 'rete: '.$err; return $out; }
    $out['ok'] = true;

    if ($code === 401 || $code === 403) {
        $out['error'] = "key rifiutata (HTTP $code)";
        return $out;
    }
    if ($code >= 200 && $code < 300) {
        $out['authenticated'] = true;
        $out['models'] = _henaxai_extract_models($def['family'], json_decode($body, true));
        return $out;
    }
    // Altro codice: auth non confermata; quasi sempre base URL/endpoint o modello errati.
    $out['error'] = "HTTP $code (verifica base URL/endpoint)";
    return $out;
}

/**
 * Stage di selezione: valida N candidati.
 * @param array $candidates  ['label' => $opts, ...]  (es. ['openai'=>['provider'=>'openai','api_key'=>'sk-..'], ...])
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
        case 'openai':     // {data:[{id},...]}  (vale anche per gemini/qwen/... openai-compat)
        case 'anthropic':  // {data:[{id},...]}
            foreach (($j['data'] ?? array()) as $m) { if (!empty($m['id'])) $models[] = $m['id']; }
            break;
        case 'ollama':     // {models:[{name},...]}
            foreach (($j['models'] ?? array()) as $m) { if (!empty($m['name'])) $models[] = $m['name']; }
            break;
        // anythingllm /system/check non elenca modelli (authenticated=true e' sufficiente)
    }
    return $models;
}

function _henaxai_http_get(string $url, array $headers, bool $insecure): array
{
    $t0 = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => $insecure ? false : true,
    ));
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    return array($code, $body === false ? '' : $body, $body === false ? $err : '', $ms);
}
