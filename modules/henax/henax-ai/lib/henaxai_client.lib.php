<?php
/**
 * henax-ai — CLIENT LLM multi-provider (L0).
 *
 * UN solo entry point: henaxai_chat(). Provider-agnostic.
 * Consolida skyllam_llm (openai/openai-compatible/ollama/anythingllm) e AGGIUNGE
 * il path Anthropic-nativo (codice nuovo: /v1/messages, content blocks, tool-use).
 *
 * Formato interno messaggi = stile OpenAI: [['role'=>'system|user|assistant|tool','content'=>...], ...]
 * I tool_calls vengono SEMPRE normalizzati a un formato interno unico (vedi sotto),
 * indipendentemente dal provider, così l'agent-loop a monte non conosce le differenze.
 *
 * Ritorno henaxai_chat():
 *   ['content'=>string, 'finish_reason'=>string, 'tool_calls'=>array, 'tokens_input'=>int, 'tokens_output'=>int]
 *   oppure false (errore in $GLOBALS['henaxai_last_error']).
 *
 * tool_call normalizzato: ['id'=>string, 'name'=>string, 'arguments'=>array]
 */

/**
 * Registry provider supportati — fonte unica condivisa da client e probe.
 * La maggior parte e' OpenAI-compatible (family 'openai'): cambia solo la base URL.
 * Solo Anthropic e' davvero nativo. Le base URL sono DEFAULT best-effort: sempre
 * sovrascrivibili per-provider (HENAXAI_BASE_<P>) o globale (HENAXAI_ENDPOINT_URL).
 * La validazione (henaxai_validate) e' il modo per confermare empiricamente la config.
 *
 *  family: 'openai' | 'anthropic' | 'ollama' | 'anythingllm'
 *  auth:   'bearer' | 'x-api-key' | 'none'
 *  models: true se l'endpoint /models elenca i modelli
 */
function henaxai_provider_registry(): array
{
    return array(
        'openai'      => array('label' => 'OpenAI',                 'family' => 'openai',      'base' => 'https://api.openai.com/v1',                              'auth' => 'bearer',    'models' => true),
        'anthropic'   => array('label' => 'Anthropic (Claude)',     'family' => 'anthropic',   'base' => 'https://api.anthropic.com',                              'auth' => 'x-api-key', 'models' => true),
        'gemini'      => array('label' => 'Google Gemini',          'family' => 'openai',      'base' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'auth' => 'bearer',  'models' => true),
        'perplexity'  => array('label' => 'Perplexity',             'family' => 'openai',      'base' => 'https://api.perplexity.ai',                              'auth' => 'bearer',    'models' => false),
        'qwen'        => array('label' => 'Qwen (Alibaba DashScope)','family' => 'openai',     'base' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1', 'auth' => 'bearer',   'models' => true),
        'glm'         => array('label' => 'GLM / Zhipu (z.ai)',     'family' => 'openai',      'base' => 'https://api.z.ai/api/paas/v4',                           'auth' => 'bearer',    'models' => false),
        'mistral'     => array('label' => 'Mistral',                'family' => 'openai',      'base' => 'https://api.mistral.ai/v1',                              'auth' => 'bearer',    'models' => true),
        'groq'        => array('label' => 'Groq',                   'family' => 'openai',      'base' => 'https://api.groq.com/openai/v1',                         'auth' => 'bearer',    'models' => true),
        'deepseek'    => array('label' => 'DeepSeek',               'family' => 'openai',      'base' => 'https://api.deepseek.com',                               'auth' => 'bearer',    'models' => true),
        'xai'         => array('label' => 'xAI (Grok)',             'family' => 'openai',      'base' => 'https://api.x.ai/v1',                                    'auth' => 'bearer',    'models' => true),
        'openrouter'  => array('label' => 'OpenRouter (aggregatore)','family' => 'openai',     'base' => 'https://openrouter.ai/api/v1',                           'auth' => 'bearer',    'models' => true),
        'ollama'      => array('label' => 'Ollama (locale/self-host)','family' => 'ollama',    'base' => 'http://localhost:11434',                                 'auth' => 'none',      'models' => true),
        'anythingllm' => array('label' => 'AnythingLLM',            'family' => 'anythingllm', 'base' => '',                                                       'auth' => 'bearer',    'models' => false),
        'openai-compatible' => array('label' => 'Generico OpenAI-compatible', 'family' => 'openai', 'base' => '',                                                 'auth' => 'bearer',    'models' => true),
    );
}

/** Normalizza un nome provider a suffisso costante (HENAXAI_KEY_<P>). */
function henaxai_provider_const_suffix(string $provider): string
{
    return strtoupper(preg_replace('/[^a-z0-9]/i', '', $provider));
}

/**
 * Risolve la configurazione effettiva (provider/family/model/key/endpoint).
 * Per-provider: HENAXAI_KEY_<P> / HENAXAI_BASE_<P> / HENAXAI_MODEL_<P>.
 * Shim legacy: SKYLLAM_* / HENAXARCHITECT_AI_*. Gli $opts espliciti hanno priorita'
 * (anche api_key='' esplicito: serve a validare "nessuna key").
 */
function henaxai_resolve_config(array $opts = array()): array
{
    $provider = $opts['provider'] ?? getDolGlobalString('HENAXAI_PROVIDER',
        getDolGlobalString('SKYLLAM_LLM_PROVIDER',
        getDolGlobalString('HENAXARCHITECT_AI_PROVIDER', 'openai')));

    $reg = henaxai_provider_registry();
    $def = $reg[$provider] ?? array('label' => $provider, 'family' => 'openai', 'base' => '', 'auth' => 'bearer', 'models' => true);
    $P = henaxai_provider_const_suffix($provider);

    // key: opts esplicito (anche '') -> per-provider const -> resolver legacy
    if (array_key_exists('api_key', $opts)) {
        $key = (string) $opts['api_key'];
    } else {
        $key = getDolGlobalString('HENAXAI_KEY_'.$P, '');
        if ($key === '') $key = henaxai_resolve_api_key($provider);
    }

    // base/endpoint: opts -> per-provider const -> generico legacy -> default registry
    if (array_key_exists('endpoint', $opts)) {
        $endpoint = (string) $opts['endpoint'];
    } else {
        $endpoint = getDolGlobalString('HENAXAI_BASE_'.$P, '');
        if ($endpoint === '') $endpoint = getDolGlobalString('HENAXAI_ENDPOINT_URL', getDolGlobalString('SKYLLAM_ENDPOINT_URL', ''));
    }
    if ($endpoint === '') $endpoint = $def['base'];

    $model = $opts['model'] ?? getDolGlobalString('HENAXAI_MODEL_'.$P,
        getDolGlobalString('HENAXAI_MODEL',
        getDolGlobalString('SKYLLAM_MODEL',
        getDolGlobalString('HENAXARCHITECT_AI_MODEL', 'gpt-4o-mini'))));
    $authType = $opts['auth_type'] ?? getDolGlobalString('HENAXAI_AUTH_TYPE', getDolGlobalString('SKYLLAM_AUTH_TYPE', $def['auth']));

    return array(
        'provider' => $provider, 'family' => $def['family'], 'model' => $model,
        'endpoint' => $endpoint, 'auth_type' => $authType, 'api_key' => $key,
        'temperature' => $opts['temperature'] ?? null,
        'max_tokens' => $opts['max_tokens'] ?? 1500,
        'system' => $opts['system'] ?? null,
        'tools' => $opts['tools'] ?? array(),
        'tool_choice' => $opts['tool_choice'] ?? 'auto',
    );
}

/**
 * Resolver key unico. Soft-dep domicare opzionale (function_exists), non hard.
 * Precedenza: HENAXAI_API_KEY -> domicare resolver (se presente) -> legacy globals -> modulo AI Dolibarr.
 */
function henaxai_resolve_api_key(string $provider = 'openai'): string
{
    $k = getDolGlobalString('HENAXAI_API_KEY', '');
    if ($k !== '' && preg_match('/^sk-[A-Za-z0-9_\-]+$/', $k)) return $k;

    // soft-dep: single source of truth storico in domicare (se installato)
    $dlib = DOL_DOCUMENT_ROOT.'/custom/domicare/lib/domicare.lib.php';
    if (is_readable($dlib)) {
        include_once $dlib;
        if (function_exists('domicare_resolve_openai_key')) {
            $r = domicare_resolve_openai_key('henaxai');
            if (is_array($r) && !empty($r['key'])) return $r['key'];
            if (is_string($r) && $r !== '') return $r;
        }
    }
    foreach (array('SKYLLAM_API_KEY', 'HENAXARCHITECT_AI_API_KEY', 'HENAXIS_AI_API_KEY', 'OPENAI_API_KEY') as $g) {
        $v = getDolGlobalString($g, '');
        if ($v !== '' && preg_match('/^sk-[A-Za-z0-9_\-]+$/', $v)) return $v;
    }
    if (function_exists('isModEnabled') && isModEnabled('ai')) {
        $svc = strtoupper(getDolGlobalString('AI_API_SERVICE', 'chatgpt'));
        $v = getDolGlobalString('AI_API_'.$svc.'_KEY', '');
        if ($v !== '') return $v;
    }
    return '';
}

/**
 * Entry point unico.
 */
function henaxai_chat(array $messages, array $opts = array())
{
    $cfg = henaxai_resolve_config($opts);
    $GLOBALS['henaxai_last_error'] = '';

    switch ($cfg['family']) {
        case 'anthropic':
            return _henaxai_chat_anthropic($messages, $cfg);
        case 'anythingllm':
            return _henaxai_chat_anythingllm($messages, $cfg);
        case 'ollama':
        case 'openai':
        default:
            // openai-compatible: openai, gemini, perplexity, qwen, glm, mistral, groq, deepseek, xai, openrouter, ollama
            return _henaxai_chat_openai($messages, $cfg);
    }
}

/* ---------- OpenAI-protocol (openai / openai-compatible / ollama) ---------- */
function _henaxai_chat_openai(array $messages, array $cfg)
{
    $url = _henaxai_openai_endpoint($cfg);
    $payload = array(
        'model' => $cfg['model'],
        'messages' => $messages,
        'temperature' => $cfg['temperature'] ?? 0.3,
        'max_tokens' => $cfg['max_tokens'],
    );
    if (!empty($cfg['tools'])) {
        $payload['tools'] = $cfg['tools'];               // formato OpenAI function-calling
        $payload['tool_choice'] = $cfg['tool_choice'];
    }
    $headers = array('Content-Type: application/json', 'Accept: application/json');
    if (!empty($cfg['api_key']) && $cfg['family'] !== 'ollama') {
        $headers[] = ($cfg['auth_type'] === 'basic')
            ? 'Authorization: Basic '.base64_encode($cfg['api_key'])
            : 'Authorization: Bearer '.$cfg['api_key'];
    }
    $resp = _henaxai_curl($url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $cfg['family'] === 'ollama');
    if ($resp === false) return false;
    $j = json_decode($resp, true);
    if (!isset($j['choices'][0]['message'])) { $GLOBALS['henaxai_last_error'] = 'risposta openai non valida: '.substr($resp, 0, 300); return false; }
    $m = $j['choices'][0]['message'];
    return array(
        'content' => $m['content'] ?? '',
        'finish_reason' => $j['choices'][0]['finish_reason'] ?? '',
        'tool_calls' => _henaxai_norm_openai_toolcalls($m['tool_calls'] ?? array()),
        'tokens_input' => $j['usage']['prompt_tokens'] ?? 0,
        'tokens_output' => $j['usage']['completion_tokens'] ?? 0,
    );
}

function _henaxai_openai_endpoint(array $cfg): string
{
    $base = rtrim($cfg['endpoint'] ?: '', '/');
    if ($cfg['family'] === 'ollama') {
        return ($base ?: 'http://localhost:11434').'/v1/chat/completions';
    }
    // base = base URL del provider (es. https://api.openai.com/v1, gemini openai-compat, ...)
    if ($base !== '') return $base.'/chat/completions';
    return 'https://api.openai.com/v1/chat/completions';
}

function _henaxai_norm_openai_toolcalls(array $tc): array
{
    $out = array();
    foreach ($tc as $c) {
        $args = $c['function']['arguments'] ?? '{}';
        $out[] = array(
            'id' => $c['id'] ?? '',
            'name' => $c['function']['name'] ?? '',
            'arguments' => is_string($args) ? (json_decode($args, true) ?: array()) : (array) $args,
        );
    }
    return $out;
}

/* ---------- Anthropic-nativo (NUOVO) — /v1/messages, content blocks ---------- */
function _henaxai_chat_anthropic(array $messages, array $cfg)
{
    // Traduzione formato interno (stile OpenAI) -> Messages API.
    [$system, $anthMessages] = _henaxai_to_anthropic_messages($messages, $cfg['system']);
    $payload = array(
        'model' => $cfg['model'] ?: 'claude-opus-4-8',
        'max_tokens' => $cfg['max_tokens'],
        'messages' => $anthMessages,
    );
    if ($system !== '') $payload['system'] = $system;
    // NB opus-4.x: NIENTE temperature/budget_tokens (danno 400). thinking adaptive opzionale.
    if (!empty($cfg['tools'])) {
        $payload['tools'] = _henaxai_tools_openai_to_anthropic($cfg['tools']);
    }
    $headers = array(
        'Content-Type: application/json',
        'x-api-key: '.$cfg['api_key'],
        'anthropic-version: 2023-06-01',
    );
    $resp = _henaxai_curl('https://api.anthropic.com/v1/messages', $headers, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), false);
    if ($resp === false) return false;
    $j = json_decode($resp, true);
    if (!isset($j['content'])) { $GLOBALS['henaxai_last_error'] = 'risposta anthropic non valida: '.substr($resp, 0, 300); return false; }

    // Estrai text + normalizza tool_use blocks al formato interno.
    $text = ''; $tool_calls = array();
    foreach ($j['content'] as $block) {
        if (($block['type'] ?? '') === 'text') $text .= $block['text'];
        elseif (($block['type'] ?? '') === 'tool_use') {
            $tool_calls[] = array('id' => $block['id'] ?? '', 'name' => $block['name'] ?? '', 'arguments' => (array) ($block['input'] ?? array()));
        }
    }
    return array(
        'content' => $text,
        'finish_reason' => $j['stop_reason'] ?? '',
        'tool_calls' => $tool_calls,
        'tokens_input' => $j['usage']['input_tokens'] ?? 0,
        'tokens_output' => $j['usage']['output_tokens'] ?? 0,
    );
}

/**
 * Traduce i messaggi interni (stile OpenAI) -> Anthropic Messages API.
 * - system: separato dal flusso messages.
 * - assistant con tool_calls -> blocchi {text} + {tool_use, id, name, input}.
 * - role 'tool' (tool result) -> user message con blocco {tool_result, tool_use_id, content}.
 * Accetta tool_calls sia in formato OpenAI ({function:{name,arguments}}) sia interno ({name,arguments}).
 */
function _henaxai_to_anthropic_messages(array $messages, $explicitSystem): array
{
    $system = is_string($explicitSystem) ? $explicitSystem : '';
    $out = array();
    foreach ($messages as $m) {
        $role = $m['role'] ?? 'user';
        $content = $m['content'] ?? '';

        if ($role === 'system') {
            $system .= ($system ? "\n\n" : '').(is_string($content) ? $content : json_encode($content));
            continue;
        }

        // Tool result -> user / tool_result block
        if ($role === 'tool') {
            $out[] = array('role' => 'user', 'content' => array(array(
                'type'        => 'tool_result',
                'tool_use_id' => $m['tool_call_id'] ?? ($m['id'] ?? ''),
                'content'     => is_string($content) ? $content : json_encode($content),
            )));
            continue;
        }

        // Assistant con tool_calls -> text + tool_use blocks
        if ($role === 'assistant' && !empty($m['tool_calls'])) {
            $blocks = array();
            if (is_string($content) && $content !== '') $blocks[] = array('type' => 'text', 'text' => $content);
            foreach ($m['tool_calls'] as $tc) {
                $name = $tc['function']['name'] ?? ($tc['name'] ?? '');
                $argsRaw = $tc['function']['arguments'] ?? ($tc['arguments'] ?? array());
                $input = is_string($argsRaw) ? (json_decode($argsRaw, true) ?: array()) : (array) $argsRaw;
                $blocks[] = array(
                    'type'  => 'tool_use',
                    'id'    => $tc['id'] ?? '',
                    'name'  => $name,
                    'input' => empty($input) ? new stdClass() : $input,
                );
            }
            $out[] = array('role' => 'assistant', 'content' => $blocks);
            continue;
        }

        // Messaggio testuale semplice
        $out[] = array('role' => ($role === 'assistant' ? 'assistant' : 'user'), 'content' => is_string($content) ? $content : json_encode($content));
    }
    return array($system, $out);
}

/** tool schema OpenAI {type:function,function:{name,description,parameters}} -> anthropic {name,description,input_schema}. */
function _henaxai_tools_openai_to_anthropic(array $tools): array
{
    $out = array();
    foreach ($tools as $t) {
        $f = $t['function'] ?? $t;
        $out[] = array(
            'name' => $f['name'] ?? '',
            'description' => $f['description'] ?? '',
            'input_schema' => $f['parameters'] ?? array('type' => 'object', 'properties' => new stdClass()),
        );
    }
    return $out;
}

/* ---------- AnythingLLM (no tool-calling) ---------- */
function _henaxai_chat_anythingllm(array $messages, array $cfg)
{
    $ws = $cfg['model'];
    $msg = '';
    foreach ($messages as $m) { $msg .= strtoupper($m['role'] ?? 'user').': '.(is_string($m['content']) ? $m['content'] : json_encode($m['content']))."\n"; }
    $url = rtrim($cfg['endpoint'], '/').'/api/v1/workspace/'.rawurlencode($ws).'/chat';
    $headers = array('Content-Type: application/json', 'Authorization: Bearer '.$cfg['api_key']);
    $payload = json_encode(array('message' => $msg, 'mode' => 'chat', 'sessionId' => 'henaxai-'.md5($msg)));
    $resp = _henaxai_curl($url, $headers, $payload, false);
    if ($resp === false) return false;
    $j = json_decode($resp, true);
    return array('content' => $j['textResponse'] ?? '', 'finish_reason' => 'stop', 'tool_calls' => array(), 'tokens_input' => 0, 'tokens_output' => 0);
}

/* ---------- curl comune ---------- */
function _henaxai_curl(string $url, array $headers, string $body, bool $insecure)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => $insecure ? false : true,
    ));
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) { $GLOBALS['henaxai_last_error'] = 'curl: '.$err; return false; }
    if ($code >= 400) { $GLOBALS['henaxai_last_error'] = 'http '.$code.': '.substr($resp, 0, 300); return false; }
    return $resp;
}
