<?php
/**
 * henax-ai — SERVICE layer (L0): cache + log + rate-limit attorno al client.
 * Portato da henax-architect/lib/architect_ai.lib.php (rename architect_* -> henaxai_*,
 * tabelle llx_henax-architect_* -> llx_henaxai_*).
 *
 * henaxai_call() è l'orchestratore generico: validate -> rate-limit -> cache -> context
 * builder iniettabile -> henaxai_chat() -> save cache -> log. Il "context builder" (callback)
 * permette il riuso sia per il grafo architect sia per la pipeline docflow.
 */
require_once __DIR__.'/henaxai_client.lib.php';

function henaxai_cache_key(string $question, string $scope, string $provider, string $model): string
{
    return hash('sha256', $question.'|'.$scope.'|'.$provider.'|'.$model);
}

function henaxai_get_cached_response($db, string $cacheKey)
{
    $ttl = (int) getDolGlobalString('HENAXAI_CACHE_TTL_MIN', '30');
    $sql = "SELECT response, tokens_input, tokens_output FROM ".MAIN_DB_PREFIX."henaxai_cache"
        ." WHERE cache_key = '".$db->escape($cacheKey)."'"
        ." AND created_at > '".$db->idate(dol_now() - $ttl * 60)."'";
    $res = $db->query($sql);
    if (!$res || !$db->num_rows($res)) return null;
    $o = $db->fetch_object($res);
    $db->query("UPDATE ".MAIN_DB_PREFIX."henaxai_cache SET hit_count = hit_count + 1, last_hit_at = '".$db->idate(dol_now())."' WHERE cache_key = '".$db->escape($cacheKey)."'");
    return array('response' => $o->response, 'tokens_input' => (int) $o->tokens_input, 'tokens_output' => (int) $o->tokens_output);
}

function henaxai_save_cache($db, string $cacheKey, string $response, int $tin, int $tout, string $provider, string $model): void
{
    $now = $db->idate(dol_now());
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."henaxai_cache (cache_key, response, created_at, last_hit_at, hit_count, provider, model, tokens_input, tokens_output)"
        ." VALUES ('".$db->escape($cacheKey)."', '".$db->escape($response)."', '".$now."', '".$now."', 0, '".$db->escape($provider)."', '".$db->escape($model)."', ".$tin.", ".$tout.")"
        ." ON DUPLICATE KEY UPDATE response = VALUES(response), created_at = VALUES(created_at), tokens_input = VALUES(tokens_input), tokens_output = VALUES(tokens_output)";
    $db->query($sql);
}

function henaxai_log($db, array $p): void
{
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."henaxai_log (fk_user, datec, question, provider, model, tokens_input, tokens_output, latency_ms, response_truncated, cache_hit, status, error_msg)"
        ." VALUES (".((int) ($p['fk_user'] ?? 0)).", '".$db->idate(dol_now())."', '".$db->escape(dol_trunc($p['question'] ?? '', 5000))."',"
        ." '".$db->escape($p['provider'] ?? '')."', '".$db->escape($p['model'] ?? '')."', ".((int) ($p['tokens_input'] ?? 0)).", ".((int) ($p['tokens_output'] ?? 0)).","
        ." ".((int) ($p['latency_ms'] ?? 0)).", '".$db->escape(dol_trunc($p['response'] ?? '', 500))."', ".((int) ($p['cache_hit'] ?? 0)).","
        ." '".$db->escape($p['status'] ?? 'ok')."', '".$db->escape(dol_trunc($p['error_msg'] ?? '', 500))."')";
    $db->query($sql);
}

function henaxai_check_rate_limit($db, int $fkUser): array
{
    $max = (int) getDolGlobalString('HENAXAI_RATE_LIMIT', '20');
    if ($max <= 0) return array('ok' => true, 'current' => 0, 'max' => 0);
    $sql = "SELECT COUNT(*) AS n, MIN(datec) AS oldest FROM ".MAIN_DB_PREFIX."henaxai_log"
        ." WHERE fk_user = ".$fkUser." AND cache_hit = 0 AND datec > '".$db->idate(dol_now() - 3600)."'";
    $res = $db->query($sql);
    $o = $res ? $db->fetch_object($res) : null;
    $cur = $o ? (int) $o->n : 0;
    if ($cur < $max) return array('ok' => true, 'current' => $cur, 'max' => $max);
    $retry = $o && $o->oldest ? max(0, 3600 - (dol_now() - $db->jdate($o->oldest))) : 3600;
    return array('ok' => false, 'current' => $cur, 'max' => $max, 'retry_after' => $retry);
}

/**
 * Orchestratore generico.
 * @param callable|null $contextBuilder fn(): string  — costruisce il contesto (es. grafo compresso, documento)
 */
function henaxai_call($db, string $question, array $opts = array(), ?callable $contextBuilder = null): array
{
    if (!getDolGlobalString('HENAXAI_ENABLED', '1')) return array('ok' => false, 'error' => 'henax-ai disabilitato');
    $fkUser = (int) ($opts['fk_user'] ?? 0);
    $scope = $opts['scope'] ?? 'default';

    $rl = henaxai_check_rate_limit($db, $fkUser);
    if (!$rl['ok']) {
        henaxai_log($db, array('fk_user' => $fkUser, 'question' => $question, 'status' => 'rate_limited'));
        return array('ok' => false, 'error' => 'rate limit', 'retry_after' => $rl['retry_after']);
    }

    $cfg = henaxai_resolve_config($opts);
    $key = henaxai_cache_key($question, $scope, $cfg['provider'], $cfg['model']);
    $cached = henaxai_get_cached_response($db, $key);
    if ($cached) {
        henaxai_log($db, array('fk_user' => $fkUser, 'question' => $question, 'provider' => $cfg['provider'], 'model' => $cfg['model'], 'cache_hit' => 1, 'tokens_input' => $cached['tokens_input'], 'tokens_output' => $cached['tokens_output']));
        return array('ok' => true, 'response' => $cached['response'], 'cache_hit' => true);
    }

    $context = $contextBuilder ? (string) $contextBuilder() : '';
    $messages = array();
    if (!empty($opts['system'])) $messages[] = array('role' => 'system', 'content' => $opts['system']);
    $messages[] = array('role' => 'user', 'content' => ($context !== '' ? $context."\n\n" : '').$question);

    $t0 = microtime(true);
    $r = henaxai_chat($messages, $opts);
    $latency = (int) round((microtime(true) - $t0) * 1000);

    if ($r === false) {
        henaxai_log($db, array('fk_user' => $fkUser, 'question' => $question, 'provider' => $cfg['provider'], 'model' => $cfg['model'], 'status' => 'error', 'error_msg' => $GLOBALS['henaxai_last_error'] ?? '', 'latency_ms' => $latency));
        return array('ok' => false, 'error' => $GLOBALS['henaxai_last_error'] ?? 'errore LLM');
    }

    henaxai_save_cache($db, $key, $r['content'], $r['tokens_input'], $r['tokens_output'], $cfg['provider'], $cfg['model']);
    henaxai_log($db, array('fk_user' => $fkUser, 'question' => $question, 'provider' => $cfg['provider'], 'model' => $cfg['model'], 'tokens_input' => $r['tokens_input'], 'tokens_output' => $r['tokens_output'], 'latency_ms' => $latency, 'response' => $r['content']));
    return array('ok' => true, 'response' => $r['content'], 'tool_calls' => $r['tool_calls'], 'cache_hit' => false, 'tokens_input' => $r['tokens_input'], 'tokens_output' => $r['tokens_output'], 'latency_ms' => $latency);
}
