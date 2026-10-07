<?php
/**
 * SURYAMITHRA - provider-agnostic LLM client.
 *
 *   $r = ai_generate($systemPrompt, $messages, ['json' => false, 'max_tokens' => 2048]);
 *   // $r = ['ok' => bool, 'text' => ?string, 'provider' => ?string, 'errors' => string[]]
 *
 * $messages = [['role' => 'user'|'assistant', 'content' => '...'], ...]
 *
 * Providers are tried in AI_PROVIDER_ORDER (config.php). A provider with no key is
 * skipped. Every failure is recorded in 'errors' (never silently swallowed).
 */

// ---------------------------------------------------------------- HTTP helpers
function ai_http(string $method, string $url, array $headers, ?array $payload, int $timeout): array
{
    if (!function_exists('curl_init')) {
        return [0, '', 'PHP cURL extension is not enabled (enable extension=curl in php.ini)'];
    }
    $isLocal = php_sapi_name() === 'cli'
        || (isset($_SERVER['HTTP_HOST']) && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_SERVER['HTTP_HOST']));

    $run = function (bool $verifySsl) use ($method, $url, $headers, $payload, $timeout) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => max(5, $timeout),
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $res = [(int)curl_getinfo($ch, CURLINFO_HTTP_CODE), is_string($body) ? $body : '', curl_error($ch), curl_errno($ch)];
        curl_close($ch);
        return $res;
    };

    [$status, $body, $err, $errno] = $run(true);

    // XAMPP on Windows usually has no CA bundle -> cURL error 60. Retry unverified on localhost ONLY.
    if ($errno === 60 && $isLocal) {
        error_log('[ai] cURL SSL CA bundle missing on localhost; retrying without verification (dev only). '
            . 'Fix: set curl.cainfo in php.ini to a cacert.pem file.');
        [$status, $body, $err, $errno] = $run(false);
    }
    return [$status, $body, $err];
}

function ai_api_error_text(int $status, string $body, string $curlErr): string
{
    if ($status === 0) {
        return 'network error: ' . ($curlErr ?: 'no response (outbound HTTPS may be blocked by the host)');
    }
    $j = json_decode($body, true);
    $msg = $j['error']['message'] ?? ($j['error'] ?? null);
    if (is_array($msg)) $msg = json_encode($msg);
    $msg = $msg ?: substr(trim(strip_tags($body)), 0, 160);
    return "HTTP $status: " . substr((string)$msg, 0, 220);
}

// ------------------------------------------------------------------ Gemini
function ai_gemini_models(string $key): array
{
    $pinned = defined('GEMINI_MODEL') ? trim((string)GEMINI_MODEL) : '';
    if ($pinned !== '') return [$pinned];

    $cache = sys_get_temp_dir() . '/suryamithra_gemini_models_' . substr(md5($key), 0, 10) . '.json';
    if (is_file($cache) && filemtime($cache) > time() - 6 * 3600) {
        $c = json_decode((string)@file_get_contents($cache), true);
        if (is_array($c) && $c) return $c;
    }

    $found = [];
    [$st, $body] = ai_http('GET', 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=200',
        ['x-goog-api-key: ' . $key], null, 12);
    if ($st === 200) {
        $j = json_decode($body, true);
        foreach (($j['models'] ?? []) as $m) {
            $name = preg_replace('#^models/#', '', $m['name'] ?? '');
            if (!in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) continue;
            if (!preg_match('/^gemini-.*flash/', $name)) continue;
            if (preg_match('/image|tts|live|audio|embed|exp|vision|thinking|customtools|robotics|computer/i', $name)) continue;
            $ver = preg_match('/gemini-(\d+(?:\.\d+)?)/', $name, $mm) ? (float)$mm[1] : 0.0;
            $score = $ver * 10
                + (strpos($name, 'preview') === false ? 5 : 0)   // prefer stable
                + (strpos($name, 'lite') === false ? 2 : 0)      // prefer full flash over lite
                + ($name === 'gemini-flash-latest' ? 100 : 0);   // alias always tracks newest
            $found[$name] = $score;
        }
        arsort($found);
        $found = array_slice(array_keys($found), 0, 4);
    }
    if (!$found) $found = ['gemini-flash-latest', 'gemini-2.5-flash', 'gemini-2.0-flash'];
    @file_put_contents($cache, json_encode($found));
    return $found;
}

function ai_call_gemini(string $system, array $messages, array $o, int $timeout, array &$errors): ?string
{
    $key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if (!$key) return null;

    $contents = [];
    foreach ($messages as $m) {
        $contents[] = ['role' => $m['role'] === 'user' ? 'user' : 'model', 'parts' => [['text' => $m['content']]]];
    }
    $gen = ['temperature' => $o['temperature'], 'maxOutputTokens' => max(4096, $o['max_tokens'] * 2)];
    if ($o['json']) $gen['responseMimeType'] = 'application/json';
    $payload = [
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents' => $contents,
        'generationConfig' => $gen,
    ];

    foreach (ai_gemini_models($key) as $model) {
        if ($o['deadline'] - time() < 5) { $errors[] = 'Gemini: out of time'; return null; }
        [$st, $body, $cerr] = ai_http('POST',
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
            ['x-goog-api-key: ' . $key], $payload, min($timeout, $o['deadline'] - time()));

        if ($st === 200) {
            $j = json_decode($body, true);
            $text = '';
            foreach (($j['candidates'][0]['content']['parts'] ?? []) as $p) {
                if (!empty($p['thought'])) continue;
                $text .= $p['text'] ?? '';
            }
            if (trim($text) !== '') return $text;
            $why = $j['promptFeedback']['blockReason'] ?? ($j['candidates'][0]['finishReason'] ?? 'empty');
            $errors[] = "Gemini($model): empty reply ($why)";
            continue;
        }
        $errors[] = "Gemini($model): " . ai_api_error_text($st, $body, $cerr);
        // bad key / permission -> no point trying other models
        if (in_array($st, [401, 403], true) || ($st === 400 && stripos($body, 'API key') !== false)) return null;
    }
    return null;
}

// ------------------------------------------------------------------ OpenAI
function ai_call_openai(string $system, array $messages, array $o, int $timeout, array &$errors): ?string
{
    $key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    if (!$key) return null;
    $model = defined('OPENAI_MODEL') && OPENAI_MODEL ? OPENAI_MODEL : 'gpt-4o-mini';

    $msgs = array_merge([['role' => 'system', 'content' => $system]], $messages);
    $payload = ['model' => $model, 'messages' => $msgs];
    if (preg_match('/^(gpt-5|o\d)/', $model)) {
        $payload['max_completion_tokens'] = $o['max_tokens'] * 4; // reasoning models spend tokens thinking
    } else {
        $payload['max_tokens'] = $o['max_tokens'];
        $payload['temperature'] = $o['temperature'];
    }
    if ($o['json']) $payload['response_format'] = ['type' => 'json_object'];

    [$st, $body, $cerr] = ai_http('POST', 'https://api.openai.com/v1/chat/completions',
        ['Authorization: Bearer ' . $key], $payload, min($timeout, max(5, $o['deadline'] - time())));
    if ($st === 200) {
        $j = json_decode($body, true);
        $t = $j['choices'][0]['message']['content'] ?? '';
        if (trim($t) !== '') return $t;
        $errors[] = 'OpenAI: empty reply';
        return null;
    }
    $errors[] = 'OpenAI: ' . ai_api_error_text($st, $body, $cerr);
    return null;
}

// --------------------------------------------------------------- Anthropic
function ai_call_anthropic(string $system, array $messages, array $o, int $timeout, array &$errors): ?string
{
    $key = defined('LLM_API_KEY') ? LLM_API_KEY : '';
    if (!$key) return null;
    $model = defined('LLM_MODEL') && LLM_MODEL ? LLM_MODEL : 'claude-sonnet-5-5';

    $payload = ['model' => $model, 'max_tokens' => $o['max_tokens'], 'system' => $system, 'messages' => $messages];
    [$st, $body, $cerr] = ai_http('POST', 'https://api.anthropic.com/v1/messages',
        ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01'], $payload, min($timeout, max(5, $o['deadline'] - time())));
    if ($st === 200) {
        $j = json_decode($body, true);
        $t = '';
        foreach (($j['content'] ?? []) as $blk) if (($blk['type'] ?? '') === 'text') $t .= $blk['text'];
        if (trim($t) !== '') return $t;
        $errors[] = 'Anthropic: empty reply';
        return null;
    }
    $errors[] = 'Anthropic: ' . ai_api_error_text($st, $body, $cerr);
    return null;
}

// ------------------------------------------------------------ Orchestrator
function ai_configured_providers(): array
{
    $order = defined('AI_PROVIDER_ORDER') ? AI_PROVIDER_ORDER : 'openai,gemini,anthropic';
    $have = [
        'openai'    => defined('OPENAI_API_KEY') && OPENAI_API_KEY,
        'gemini'    => defined('GEMINI_API_KEY') && GEMINI_API_KEY,
        'anthropic' => defined('LLM_API_KEY') && LLM_API_KEY,
    ];
    $out = [];
    foreach (array_map('trim', explode(',', strtolower($order))) as $p) {
        if (!empty($have[$p]) && !in_array($p, $out, true)) $out[] = $p;
    }
    return $out;
}

/**
 * Normalise chat history so every provider accepts it: valid roles only, trimmed,
 * consecutive same-role turns merged, starts with 'user', ends with the current prompt.
 */
function ai_normalize_messages(array $history, string $prompt): array
{
    $out = [];
    $push = function (string $role, string $text) use (&$out) {
        $text = trim(mb_substr($text, 0, 6000));
        if ($text === '') return;
        if ($out && end($out)['role'] === $role) {
            $out[count($out) - 1]['content'] .= "\n\n" . $text;
        } else {
            $out[] = ['role' => $role, 'content' => $text];
        }
    };
    foreach (array_slice($history, -12) as $h) {
        if (!is_array($h) || empty($h['content']) || !is_string($h['content'])) continue;
        $push(($h['role'] ?? '') === 'user' ? 'user' : 'assistant', $h['content']);
    }
    // The browser already includes the current question as the last user turn; add it only if missing.
    $last = $out ? end($out) : null;
    if (!$last || $last['role'] !== 'user' || trim($last['content']) !== trim(mb_substr($prompt, 0, 6000))) {
        if ($last && $last['role'] === 'user') array_pop($out);
        $push('user', $prompt);
    }
    while ($out && $out[0]['role'] !== 'user') array_shift($out);
    return $out;
}

function ai_generate(string $system, array $messages, array $opts = []): array
{
    $o = $opts + ['json' => false, 'max_tokens' => 2048, 'temperature' => 0.7, 'budget' => 70];
    $o['deadline'] = time() + (int)$o['budget'];
    $errors = [];
    $providers = ai_configured_providers();

    if (!$providers) {
        return ['ok' => false, 'text' => null, 'provider' => null,
            'errors' => ['No AI API key configured. Add one in config/gemini_key.txt, config/openai_key.txt, or set the GEMINI_API_KEY / OPENAI_API_KEY / LLM_API_KEY environment variable.']];
    }

    foreach ($providers as $p) {
        if ($o['deadline'] - time() < 5) { $errors[] = 'Out of time before trying ' . $p; break; }
        $text = null;
        if ($p === 'gemini')    $text = ai_call_gemini($system, $messages, $o, 40, $errors);
        if ($p === 'openai')    $text = ai_call_openai($system, $messages, $o, 40, $errors);
        if ($p === 'anthropic') $text = ai_call_anthropic($system, $messages, $o, 40, $errors);
        if ($text !== null && trim($text) !== '') {
            return ['ok' => true, 'text' => $text, 'provider' => $p, 'errors' => $errors];
        }
    }
    foreach ($errors as $e) error_log('[ai_tutor] ' . $e);
    return ['ok' => false, 'text' => null, 'provider' => null, 'errors' => $errors];
}
