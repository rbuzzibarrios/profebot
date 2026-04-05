<?php
// ═══════════════════════════════════════════════════════
//  ProfeBot — Servidor local PHP
//  Uso: php -S localhost:8080
//  Abrir: http://localhost:8080/profebot.php
// ═══════════════════════════════════════════════════════

// ── CACHE DE PREGUNTAS ──
define('CACHE_FILE', __DIR__.'/question_cache.json');
define('CACHE_MAX_PER_KEY', 50);

// Cargar .env si existe (local con php -S)
$CONFIG_KEYS = [];
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}
// Cargar config.php si existe (InfinityFree u otro hosting sin env vars)
@include __DIR__ . '/config.php';

function cache_read()
{
    if (!file_exists(CACHE_FILE)) {
        return [];
    }
    $raw = file_get_contents(CACHE_FILE);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function cache_write($data)
{
    $f = fopen(CACHE_FILE, 'c+');
    if (!$f) {
        return false;
    }
    flock($f, LOCK_EX);
    ftruncate($f, 0);
    rewind($f);
    fwrite($f, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($f);
    flock($f, LOCK_UN);
    fclose($f);
    return true;
}

// ── PROXY API: multi-proveedor con fallback automático ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    // ── Cache actions ──
    if (isset($data['action'])) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        if ($data['action'] === 'cache_get') {
            $key = isset($data['cache_key']) ? $data['cache_key'] : '';
            $exclude = isset($data['exclude']) ? $data['exclude'] : [];
            $cache = cache_read();
            $items = isset($cache[$key]) ? $cache[$key] : [];
            // Filter out already-asked questions
            $available = array_values(array_filter($items, function ($q) use ($exclude) {
                return !in_array($q['question'], $exclude);
            }));
            if (empty($available)) {
                echo json_encode(['cached' => false]);
            } else {
                // If fewer than 5 cached, 50% chance to skip cache and go to AI to grow variety
                if (count($items) < 5 && mt_rand(1, 100) <= 50) {
                    echo json_encode(['cached' => false]);
                } else {
                    $pick = $available[array_rand($available)];
                    echo json_encode(['cached' => true, 'question' => $pick]);
                }
            }
            exit;
        }

        if ($data['action'] === 'cache_save') {
            $key = isset($data['cache_key']) ? $data['cache_key'] : '';
            $question = isset($data['question']) ? $data['question'] : null;
            if (!$key || !$question) {
                echo json_encode(['saved' => false, 'error' => 'missing data']);
                exit;
            }
            $cache = cache_read();
            if (!isset($cache[$key])) {
                $cache[$key] = [];
            }
            $cache[$key][] = $question;
            // FIFO: keep max per key
            if (count($cache[$key]) > CACHE_MAX_PER_KEY) {
                $cache[$key] = array_slice($cache[$key], -CACHE_MAX_PER_KEY);
            }
            cache_write($cache);
            echo json_encode(['saved' => true]);
            exit;
        }
    }

    // Registro de proveedores
    $PROVIDERS = [
            'groq'   => [
                    'env'   => 'GROQ_API_KEY',
                    'build' => function ($data, $key) {
                        $body = ['model' => 'llama-3.3-70b-versatile', 'max_tokens' => 2048, 'messages' => []];
                        if (!empty($data['system'])) {
                            $body['messages'][] = ['role' => 'system', 'content' => $data['system']];
                        }
                        if (!empty($data['messages'])) {
                            foreach ($data['messages'] as $m) {
                                $body['messages'][] = ['role' => $m['role'], 'content' => $m['content']];
                            }
                        }
                        return [
                                'url'     => 'https://api.groq.com/openai/v1/chat/completions',
                                'headers' => ['Content-Type: application/json', 'Authorization: Bearer '.$key],
                                'body'    => json_encode($body),
                        ];
                    },
                    'parse' => function ($resp) {
                        $d = json_decode($resp, true);
                        $text = $d['choices'][0]['message']['content'] ?? null;
                        $err = $d['error']['message'] ?? null;
                        $usage = $d['usage'] ?? null;
                        return ['text' => $text, 'error' => $err, 'usage' => $usage];
                    },
            ],
            'claude' => [
                    'env'   => 'ANTHROPIC_API_KEY',
                    'build' => function ($data, $key) {
                        $body = ['model' => 'claude-sonnet-4-20250514', 'max_tokens' => 1024];
                        if (!empty($data['system'])) {
                            $body['system'] = $data['system'];
                        }
                        $body['messages'] = [];
                        if (!empty($data['messages'])) {
                            foreach ($data['messages'] as $m) {
                                $body['messages'][] = ['role' => $m['role'], 'content' => $m['content']];
                            }
                        }
                        return [
                                'url'     => 'https://api.anthropic.com/v1/messages',
                                'headers' => [
                                        'Content-Type: application/json',
                                        'x-api-key: '.$key,
                                        'anthropic-version: 2023-06-01',
                                ],
                                'body'    => json_encode($body),
                        ];
                    },
                    'parse' => function ($resp) {
                        $d = json_decode($resp, true);
                        $text = $d['content'][0]['text'] ?? null;
                        $err = $d['error']['message'] ?? null;
                        return ['text' => $text, 'error' => $err];
                    },
            ],
            'gemini' => [
                    'env'   => 'GEMINI_API_KEY',
                    'build' => function ($data, $key) {
                        $parts = [];
                        if (!empty($data['system'])) {
                            $parts[] = ['text' => $data['system']];
                        }
                        if (!empty($data['messages'])) {
                            foreach ($data['messages'] as $m) {
                                $parts[] = ['text' => $m['content']];
                            }
                        }
                        $body = [
                                'contents'         => [['parts' => $parts]],
                                'generationConfig' => ['maxOutputTokens' => 2048],
                        ];
                        if (!empty($data['system'])) {
                            $body['systemInstruction'] = ['parts' => [['text' => $data['system']]]];
                            $body['contents'] = [
                                    [
                                            'parts' => array_values(array_filter($parts, function ($p) use ($data) {
                                                return $p['text'] !== $data['system'];
                                            })),
                                    ],
                            ];
                        }
                        return [
                                'url'     => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key='.$key,
                                'headers' => ['Content-Type: application/json'],
                                'body'    => json_encode($body),
                        ];
                    },
                    'parse' => function ($resp) {
                        $d = json_decode($resp, true);
                        $text = $d['candidates'][0]['content']['parts'][0]['text'] ?? null;
                        $err = $d['error']['message'] ?? null;
                        $usage = $d['usageMetadata'] ?? null;
                        return ['text' => $text, 'error' => $err, 'usage' => $usage];
                    },
            ],
    ];

    // Leer keys y orden de prioridad del body JSON
    $clientKeys = isset($data['providers']) ? $data['providers'] : [];
    $order = isset($data['provider_order']) ? $data['provider_order'] : array_keys($PROVIDERS);

    // Resolver keys: client-sent > env var; filtrar proveedores sin key
    $resolved = [];
    foreach ($order as $pid) {
        if (!isset($PROVIDERS[$pid])) {
            continue;
        }
        $k = '';
        if (!empty($clientKeys[$pid])) {
            $k = $clientKeys[$pid];
        } elseif (!empty($CONFIG_KEYS[$pid])) {
            $k = $CONFIG_KEYS[$pid];
        } elseif (getenv($PROVIDERS[$pid]['env'])) {
            $k = getenv($PROVIDERS[$pid]['env']);
        }
        if ($k !== '') {
            $resolved[$pid] = $k;
        }
    }

    if (empty($resolved)) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'No hay proveedores configurados. Agregá al menos una API key.']);
        exit;
    }

    // Limpiar campos de control del body antes de enviar al proveedor
    unset($data['providers'], $data['provider_order']);

    // Fallback loop
    $errors = [];
    $STOP_CODES = [400, 401]; // problemas de config, no reintentar
    $FALLBACK_CODES = [429, 402, 403, 500, 502, 503];

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    foreach ($resolved as $pid => $key) {
        $prov = $PROVIDERS[$pid];
        $req = $prov['build']($data, $key);

        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $req['body'],
                CURLOPT_HTTPHEADER     => $req['headers'],
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        // Error cURL → fallback
        if ($curlErr) {
            $errors[] = "$pid: red – $curlErr";
            continue;
        }

        // HTTP error → decidir si parar o fallback
        if ($httpCode >= 400) {
            $parsed = $prov['parse']($response);
            $msg = $parsed['error'] ?: "HTTP $httpCode";
            if (in_array($httpCode, $STOP_CODES)) {
                http_response_code($httpCode);
                echo json_encode(['error' => "$pid: $msg", 'provider' => $pid]);
                exit;
            }
            $errors[] = "$pid: $msg";
            continue; // fallback
        }

        // Éxito → parsear y devolver
        $parsed = $prov['parse']($response);
        if ($parsed['text']) {
            $out = ['content' => [['text' => $parsed['text']]], 'provider' => $pid];
            if (!empty($parsed['usage'])) {
                $out['usage'] = $parsed['usage'];
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $errors[] = "$pid: respuesta vacía";
    }

    // Todos fallaron
    http_response_code(502);
    echo json_encode(['error' => 'Todos los proveedores fallaron: '.implode(' | ', $errors)]);
    exit;
}

// ── OPTIONS (preflight) ──
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// ── GET → servir el HTML ──
include __DIR__.'/profebot.html';
