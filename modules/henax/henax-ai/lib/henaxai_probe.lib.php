<?php
/* Copyright (C) 2026 P2G Connecto — GPL v3+ */

/**
 * \file    custom/henax-ai/lib/henaxai_probe.lib.php
 * \brief   STADIO DI VALIDAZIONE/VALUTAZIONE PROVIDER+API KEY (L0).
 *
 * Il sistema non puo' sapere quale LLM usa il cliente: serve poter TESTARE una
 * coppia provider+key (autentica? quali modelli espone? latenza?) in modo
 * NON distruttivo e indipendente dall'uso reale. Generalizza skyllam::detect()
 * e architect_ai_resolve_api_key().
 *
 * API:
 *   henaxai_providers(): array                      // registry provider supportati
 *   henaxai_validate(array $opts): array            // valida UNA config provider+key
 *   henaxai_validate_candidates(array $cands): array // valida N candidati (stage selezione)
 *
 * Esito henaxai_validate():
 *   ['ok'=>bool,            // richiesta completata (no errore di rete)
 *    'provider'=>str, 'model'=>str,
 *    'authenticated'=>bool, // key accettata (no 401/403)
 *    'models'=>[str...],    // modelli esposti dal provider (se elencabili)
 *    'http_code'=>int, 'latency_ms'=>int, 'error'=>str|null]
 */

if (!defined('DOL_DOCUMENT_ROOT')) die('Forbidden');
require_once __DIR__.'/henaxai_client.lib.php';   // henaxai_resolve_config()

/**
 * Registry provider supportati + come si validano (endpoint "list models / auth", non distruttivo).
 */
function henaxai_providers(): array
{
    return array(
        'openai' => array(
            'label' => 'OpenAI',
            'auth'  => 'bearer',
            'models_url' => 'https://api.openai.com/v1/models', // override con endpoint custom (openai-compatible)
            'native' => false,
        ),
        'anthropic' => array(
            'label' => 'Anthropic (Claude)',
            'auth'  => 'x-api-key',
            'models_url' => 'https://api.anthropic.com/v1/models',
            'native' => true, // path Messages API nativo
        ),
        'ollama' => array(
            'label' => 'Ollama (locale/self-host)',
            'auth'  => 'none',
            'models_url' => '{endpoint}/api/tags',
            'native' => false,
        ),
        'anythingllm' => array(
            'label' => 'AnythingLLM',
            'auth'  => 'bearer',
            'models_url' => '{endpoint}/api/v1/system/check', // valida il token
            'native' => false,
        ),
        // groq/mistral/altri = openai-compatible: provider 'openai' + HENAXAI_ENDPOINT_URL custom.
    );
}

/**
 * Valida UNA coppia provider+key. Non distruttiva: usa l'endpoint "list models / auth".
 */
function henaxai_validate(array $opts): array
{
    $cfg = henaxai_resolve_config($opts);
    $provider = $cfg['provider'];
    $registry = henaxai_providers();
    $base = array('ok' => false, 'provider' => $provider, 'model' => $cfg['model'],
                  'authenticated' => false, 'models' => array(), 'http_code' => 0, 'latency_ms' => 0, 'error' => null);

    if (!isset($registry[$provider])) {
        $base['error'] = "provider non supportato: $provider";
        return $base;
    }
    $pr = $registry[$provider];

    // Risolvi URL (endpoint custom per openai-compatible / ollama / anythingllm)
    $url = $pr['models_url'];
    if (strpos($url, '{endpoint}') !== false) {
        $ep = rtrim($cfg['endpoint'] ?: ($provider === 'ollama' ? 'http://localhost:11434' : ''), '/');
        if ($ep === '') { $base['error'] = "endpoint non configurato per $provider"; return $base; }
        $url = str_replace('{endpoint}', $ep, $url);
    } elseif ($provider === 'openai' && !empty($cfg['endpoint'])) {
        $url = rtrim($cfg['endpoint'], '/').'/models'; // openai-compatible custom
    }

    // Header auth per provider
    $headers = array('Accept: application/json');
    if ($pr['auth'] === 'bearer' && !empty($cfg['api_key'])) {
        $headers[] = 'Authorization: Bearer '.$cfg['api_key'];
    } elseif ($pr['auth'] === 'x-api-key') {
        $headers[] = 'x-api-key: '.$cfg['api_key'];
        $headers[] = 'anthropic-version: 2023-06-01';
    }

    [$code, $body, $err, $ms] = _henaxai_http_get($url, $headers, $provider === 'ollama');
    $base['http_code'] = $code;
    $base['latency_ms'] = $ms;

    if ($err !== '') { $base['error'] = 'rete: '.$err; return $base; }
    $base['ok'] = true;

    if ($code === 401 || $code === 403) {
        $base['authenticated'] = false;
        $base['error'] = "key rifiutata (HTTP $code)";
        return $base;
    }
    if ($code >= 400) {
        $base['error'] = "HTTP $code";
        return $base;
    }

    // 2xx: autenticato. Estrai modelli per provider.
    $base['authenticated'] = true;
    $j = json_decode($body, true);
    $base['models'] = _henaxai_extract_models($provider, $j);
    return $base;
}

/**
 * Stage di selezione: valida N candidati e ritorna l'esito per ciascuno.
 * @param array $candidates  ['label' => $opts, ...]   (es. ['openai-prod'=>[...], 'claude'=>[...]])
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

function _henaxai_extract_models(string $provider, $j): array
{
    if (!is_array($j)) return array();
    $models = array();
    switch ($provider) {
        case 'openai':     // {data:[{id},...]}
        case 'anthropic':  // {data:[{id},...]}
            foreach (($j['data'] ?? array()) as $m) { if (!empty($m['id'])) $models[] = $m['id']; }
            break;
        case 'ollama':     // {models:[{name},...]}
            foreach (($j['models'] ?? array()) as $m) { if (!empty($m['name'])) $models[] = $m['name']; }
            break;
        // anythingllm /system/check non elenca modelli: lista vuota, ma authenticated=true e' sufficiente.
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
