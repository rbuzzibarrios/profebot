<?php
// ═══════════════════════════════════════════════════════
//  ProfeBot — Servidor local PHP
//  Uso: php -S localhost:8080
//  Abrir: http://localhost:8080/profebot.php
// ═══════════════════════════════════════════════════════

// ── CACHE DE PREGUNTAS ──
define('CACHE_FILE', is_dir('/var/data') ? '/var/data/question_cache.json' : __DIR__ . '/question_cache.json');
define('CACHE_MAX_PER_KEY', 50);

function cache_read() {
    if (!file_exists(CACHE_FILE)) return [];
    $f = fopen(CACHE_FILE, 'r');
    if (!$f) return [];
    flock($f, LOCK_SH);
    $data = json_decode(fread($f, max(1, filesize(CACHE_FILE))), true);
    flock($f, LOCK_UN);
    fclose($f);
    return is_array($data) ? $data : [];
}

function cache_write($data) {
    $f = fopen(CACHE_FILE, 'c+');
    if (!$f) return false;
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
            $available = array_values(array_filter($items, function($q) use ($exclude) {
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
            if (!isset($cache[$key])) $cache[$key] = [];
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
        'groq' => [
            'env' => 'GROQ_API_KEY',
            'build' => function($data, $key) {
                $body = ['model' => 'llama-3.3-70b-versatile', 'max_tokens' => 2048, 'messages' => []];
                if (!empty($data['system'])) $body['messages'][] = ['role' => 'system', 'content' => $data['system']];
                if (!empty($data['messages'])) foreach ($data['messages'] as $m) $body['messages'][] = ['role' => $m['role'], 'content' => $m['content']];
                return [
                    'url' => 'https://api.groq.com/openai/v1/chat/completions',
                    'headers' => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
                    'body' => json_encode($body),
                ];
            },
            'parse' => function($resp) {
                $d = json_decode($resp, true);
                $text = $d['choices'][0]['message']['content'] ?? null;
                $err = $d['error']['message'] ?? null;
                $usage = $d['usage'] ?? null;
                return ['text' => $text, 'error' => $err, 'usage' => $usage];
            },
        ],
        'claude' => [
            'env' => 'ANTHROPIC_API_KEY',
            'build' => function($data, $key) {
                $body = ['model' => 'claude-sonnet-4-20250514', 'max_tokens' => 1024];
                if (!empty($data['system'])) $body['system'] = $data['system'];
                $body['messages'] = [];
                if (!empty($data['messages'])) foreach ($data['messages'] as $m) $body['messages'][] = ['role' => $m['role'], 'content' => $m['content']];
                return [
                    'url' => 'https://api.anthropic.com/v1/messages',
                    'headers' => ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'],
                    'body' => json_encode($body),
                ];
            },
            'parse' => function($resp) {
                $d = json_decode($resp, true);
                $text = $d['content'][0]['text'] ?? null;
                $err = $d['error']['message'] ?? null;
                return ['text' => $text, 'error' => $err];
            },
        ],
        'gemini' => [
            'env' => 'GEMINI_API_KEY',
            'build' => function($data, $key) {
                $parts = [];
                if (!empty($data['system'])) $parts[] = ['text' => $data['system']];
                if (!empty($data['messages'])) foreach ($data['messages'] as $m) $parts[] = ['text' => $m['content']];
                $body = [
                    'contents' => [['parts' => $parts]],
                    'generationConfig' => ['maxOutputTokens' => 2048],
                ];
                if (!empty($data['system'])) {
                    $body['systemInstruction'] = ['parts' => [['text' => $data['system']]]];
                    $body['contents'] = [['parts' => array_values(array_filter($parts, function($p) use ($data) { return $p['text'] !== $data['system']; }))]];
                }
                return [
                    'url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $key,
                    'headers' => ['Content-Type: application/json'],
                    'body' => json_encode($body),
                ];
            },
            'parse' => function($resp) {
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
        if (!isset($PROVIDERS[$pid])) continue;
        $k = '';
        if (!empty($clientKeys[$pid])) $k = $clientKeys[$pid];
        elseif (getenv($PROVIDERS[$pid]['env'])) $k = getenv($PROVIDERS[$pid]['env']);
        if ($k !== '') $resolved[$pid] = $k;
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
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $req['body'],
            CURLOPT_HTTPHEADER => $req['headers'],
            CURLOPT_TIMEOUT => 60,
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
            if (!empty($parsed['usage'])) $out['usage'] = $parsed['usage'];
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $errors[] = "$pid: respuesta vacía";
    }

    // Todos fallaron
    http_response_code(502);
    echo json_encode(['error' => 'Todos los proveedores fallaron: ' . implode(' | ', $errors)]);
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no"/>
<title>ProfeBot Voz 🎙️</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800;900&family=Baloo+2:wght@700;800&display=swap" rel="stylesheet"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<style>
:root{
  --orange:#FF6B35;--green:#4CAF7D;--blue:#4A90D9;--purple:#9B59B6;
  --red:#E53935;--amber:#F59E0B;--bg:#FFF9F0;--card:#fff;--text:#2D2D2D;--muted:#999;--border:#EAEAEA;
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
body{font-family:'Nunito',sans-serif;background:var(--bg);min-height:100vh;display:flex;flex-direction:column;align-items:center;
  background-image:radial-gradient(circle at 8% 12%,rgba(255,217,61,.13) 0%,transparent 38%),radial-gradient(circle at 92% 88%,rgba(74,144,217,.13) 0%,transparent 38%);}
.screen{display:none;width:100%;max-width:640px;min-height:100vh;flex-direction:column;padding:14px 13px 32px;}
.screen.active{display:flex;}

/* PROVIDER CONFIG */
.prov-section{margin-bottom:12px;}
.prov-title{font-family:'Baloo 2',cursive;font-size:.95rem;font-weight:800;color:var(--orange);margin-bottom:8px;}
.prov-row{border:2px solid var(--border);border-radius:12px;margin-bottom:8px;overflow:hidden;transition:border-color .2s;}
.prov-row.ok{border-color:#86EFAC;}
.prov-hdr{display:flex;align-items:center;gap:8px;padding:10px 13px;cursor:pointer;user-select:none;
  font-family:'Nunito',sans-serif;font-weight:700;font-size:.85rem;color:var(--text);background:var(--card);}
.prov-hdr:hover{background:#FAFAFA;}
.prov-status{margin-left:auto;font-size:.75rem;}
.prov-body{display:none;padding:8px 13px 12px;background:linear-gradient(135deg,#FFF3E0,#FFE0CC);
  border-top:1px solid var(--border);}
.prov-body.open{display:block;}
.prov-body p{font-size:.72rem;font-weight:700;color:#795548;margin-bottom:8px;line-height:1.45;}
.prov-body .api-row{display:flex;gap:7px;}
.api-inp{flex:1;padding:9px 12px;border:2px solid var(--border);border-radius:10px;
  font-family:'Nunito',sans-serif;font-size:.82rem;font-weight:600;outline:none;color:var(--text);}
.api-inp:focus{border-color:var(--orange);}
.api-inp::placeholder{color:#C0C0C0;}
.api-save{padding:9px 14px;background:var(--orange);color:white;border:none;border-radius:10px;
  cursor:pointer;font-family:'Nunito',sans-serif;font-weight:800;font-size:.82rem;white-space:nowrap;}
.api-ok{background:#E8F8EF;border:1.5px solid #86EFAC;border-radius:10px;padding:9px 13px;
  font-size:.78rem;font-weight:700;color:#166534;margin-bottom:12px;display:flex;align-items:center;gap:7px;}
.prov-badge{display:inline-block;font-size:.62rem;font-weight:700;color:var(--muted);margin-top:2px;}

/* SETUP */
.setup-hdr{text-align:center;padding:20px 0 14px;}
.mascot{font-size:64px;display:block;animation:flt 3s ease-in-out infinite;}
@keyframes flt{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
.setup-hdr h1{font-family:'Baloo 2',cursive;font-size:2rem;font-weight:800;color:var(--orange);}
.setup-hdr p{font-size:.84rem;color:var(--muted);font-weight:700;margin-top:3px;}
.card{background:var(--card);border-radius:16px;padding:15px;box-shadow:0 4px 18px rgba(0,0,0,.06);margin-bottom:10px;border:1.5px solid rgba(0,0,0,.04);}
.sec{font-size:.68rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:9px;}
.subj-tabs{display:flex;gap:8px;margin-bottom:11px;}
.stab{flex:1;padding:10px 7px;border-radius:11px;border:2.5px solid var(--border);background:white;
  cursor:pointer;font-family:'Nunito',sans-serif;font-size:.85rem;font-weight:800;color:var(--muted);transition:all .2s;text-align:center;}
.stab.am{border-color:var(--blue);background:#EEF6FF;color:var(--blue);}
.stab.al{border-color:var(--green);background:#E8F8EF;color:var(--green);}
.selrow{display:flex;justify-content:space-between;align-items:center;margin-bottom:9px;}
.selcount{font-size:.72rem;font-weight:800;color:var(--muted);}
.lnk{font-size:.7rem;font-weight:800;cursor:pointer;color:var(--blue);text-decoration:underline;background:none;border:none;font-family:'Nunito',sans-serif;}
.unit-list{max-height:300px;overflow-y:auto;display:flex;flex-direction:column;gap:5px;}
.unit-blk{border:1.5px solid var(--border);border-radius:10px;overflow:hidden;}
.unit-hdr{display:flex;align-items:center;gap:7px;padding:8px 11px;cursor:pointer;font-size:.8rem;font-weight:800;color:var(--text);background:#F9F9F9;}
.unit-hdr .utog{margin-left:auto;font-size:.7rem;color:var(--muted);transition:transform .2s;}
.unit-hdr.open .utog{transform:rotate(180deg);}
.unit-body{display:none;padding:6px 8px 8px;flex-direction:column;gap:4px;}
.unit-body.open{display:flex;}
.obj-row{display:flex;align-items:flex-start;gap:7px;padding:6px 8px;border-radius:7px;background:white;border:1.5px solid var(--border);cursor:pointer;transition:all .13s;}
.obj-row:hover{border-color:#aaa;}
.obj-row.chk{background:#F0FDF6;border-color:#86EFAC;}
.obj-row input{margin-top:2px;accent-color:var(--green);width:14px;height:14px;flex-shrink:0;}
.obj-row span{font-size:.77rem;font-weight:700;color:var(--text);line-height:1.4;}
.ubadge{padding:2px 6px;border-radius:20px;font-size:.62rem;font-weight:800;background:#E8F8EF;color:var(--green);margin-left:auto;white-space:nowrap;}
.row2{display:flex;gap:7px;margin-bottom:5px;}
.chip{flex:1;padding:9px 5px;border-radius:10px;border:2.5px solid var(--border);background:white;cursor:pointer;
  font-family:'Nunito',sans-serif;font-size:.78rem;font-weight:800;color:var(--muted);transition:all .18s;text-align:center;}
.chip.cg.on{background:var(--green);border-color:var(--green);color:white;}
.chip.cb.on{background:var(--blue);border-color:var(--blue);color:white;}
.vtog{flex:1;padding:9px 6px;border-radius:10px;border:2.5px solid var(--border);background:white;
  cursor:pointer;font-family:'Nunito',sans-serif;font-size:.78rem;font-weight:800;color:var(--muted);transition:all .2s;text-align:center;}
.vtog.on{border-color:var(--purple);background:#F5F0FF;color:var(--purple);}
.novoice{background:rgba(229,57,53,.08);border:1.5px solid rgba(229,57,53,.25);border-radius:9px;
  padding:8px 11px;font-size:.72rem;font-weight:700;color:var(--red);margin-top:8px;text-align:center;}
.abtn{width:100%;padding:14px;border:none;border-radius:14px;font-family:'Baloo 2',cursive;
  font-size:1.05rem;font-weight:800;cursor:pointer;transition:all .2s;
  display:flex;align-items:center;justify-content:center;gap:7px;margin-bottom:8px;}
.abtn.g{background:linear-gradient(135deg,var(--green),#3d9968);color:white;box-shadow:0 4px 14px rgba(76,175,125,.4);}
.abtn.g:hover{transform:translateY(-2px);}
.abtn.g:disabled{opacity:.5;cursor:not-allowed;transform:none;}
.abtn.o{background:linear-gradient(135deg,var(--orange),#e55a25);color:white;box-shadow:0 4px 14px rgba(255,107,53,.4);}
.abtn.o:hover{transform:translateY(-2px);}
.abtn.out{background:white;color:var(--muted);border:2px solid var(--border);font-size:.88rem;}
.abtn.out:hover{background:#F5F5F5;}
/* materials */
.drop{border:2.5px dashed #D0D0D0;border-radius:11px;padding:13px;text-align:center;cursor:pointer;background:#FAFAFA;margin-bottom:7px;position:relative;transition:all .18s;}
.drop:hover,.drop.dg{border-color:var(--purple);background:#F5F0FF;}
.drop input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.slist{display:flex;flex-direction:column;gap:5px;}
.sit{display:flex;align-items:center;gap:6px;padding:6px 9px;border-radius:7px;}
.sit.pdf{background:#F5F0FF;border:1.5px solid #E0D8F8;}
.sit.url{background:#EEF6FF;border:1.5px solid #C8DFF8;}
.sn{flex:1;font-size:.72rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.sit.pdf .sn{color:var(--purple);}.sit.url .sn{color:var(--blue);}
.sstat{font-size:.65rem;font-weight:800;padding:2px 6px;border-radius:20px;}
.sok{background:#E8F8EF;color:var(--green);}.sld{background:#FFF3E0;color:var(--amber);}.ser{background:#FFE8E8;color:var(--red);}
.xbtn{border:none;background:none;cursor:pointer;font-size:.78rem;color:var(--muted);}
.urow{display:flex;gap:6px;margin-bottom:6px;}
.uinp{flex:1;padding:7px 10px;border:2px solid var(--border);border-radius:8px;font-family:'Nunito',sans-serif;font-size:.78rem;font-weight:600;outline:none;color:var(--text);}
.uinp:focus{border-color:var(--blue);}
.uinp::placeholder{color:#C0C0C0;}
.smbtn{padding:7px 12px;border:none;border-radius:8px;font-family:'Nunito',sans-serif;font-weight:800;font-size:.78rem;cursor:pointer;}
.smbtn.sb{background:var(--blue);color:white;}
.warn{background:#FFF8E1;border:1.5px solid #FFE082;border-radius:8px;padding:6px 10px;font-size:.68rem;font-weight:700;color:#795548;margin-bottom:6px;}

/* VOICE SCREEN */
.voice-screen{background:#1A1A2E;min-height:100vh;}
.vbar{display:flex;align-items:center;gap:9px;padding:13px 15px;border-bottom:1px solid rgba(255,255,255,.08);}
.vbar-back{background:rgba(255,255,255,.1);border:none;color:white;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center;}
.vbar-info{flex:1;}
.vbar-info h3{font-family:'Baloo 2',cursive;font-size:.88rem;font-weight:800;color:white;}
.vbar-info span{font-size:.67rem;color:rgba(255,255,255,.45);font-weight:600;}
.vscore{text-align:right;}
.vsc{font-size:.85rem;font-weight:900;color:#4ade80;}
.vsw{font-size:.85rem;font-weight:900;color:#f87171;}
.vscl{font-size:.58rem;font-weight:700;color:rgba(255,255,255,.35);display:block;}
.vprog{padding:0 15px;}
.vbar2{height:5px;background:rgba(255,255,255,.1);border-radius:20px;overflow:hidden;margin:9px 0 3px;}
.vfill{height:100%;border-radius:20px;background:linear-gradient(90deg,var(--orange),var(--amber));transition:width .4s;}
.vplbl{display:flex;justify-content:space-between;font-size:.63rem;font-weight:700;color:rgba(255,255,255,.35);margin-bottom:9px;}
.owl-zone{display:flex;flex-direction:column;align-items:center;padding:12px 15px 0;}
.owl-wrap{position:relative;width:90px;height:90px;margin-bottom:10px;}
.owl-emoji{font-size:66px;display:block;transition:transform .3s;}
.owl-wrap.talking .owl-emoji{animation:owlTalk .3s ease-in-out infinite alternate;}
.owl-wrap.listening .owl-emoji{animation:owlListen 1s ease-in-out infinite;}
@keyframes owlTalk{0%{transform:scale(1) rotate(-3deg)}100%{transform:scale(1.05) rotate(3deg)}}
@keyframes owlListen{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
.waves{position:absolute;bottom:-6px;left:50%;transform:translateX(-50%);display:flex;gap:3px;align-items:flex-end;opacity:0;transition:opacity .3s;}
.waves.show{opacity:1;}
.wave{width:4px;border-radius:3px;background:var(--orange);}
.wave:nth-child(1){height:7px;animation:wv .5s ease-in-out infinite alternate;}
.wave:nth-child(2){height:13px;animation:wv .5s ease-in-out .1s infinite alternate;}
.wave:nth-child(3){height:9px;animation:wv .5s ease-in-out .2s infinite alternate;}
.wave:nth-child(4){height:15px;animation:wv .5s ease-in-out .05s infinite alternate;}
.wave:nth-child(5){height:7px;animation:wv .5s ease-in-out .15s infinite alternate;}
@keyframes wv{0%{transform:scaleY(.6)}100%{transform:scaleY(1.4)}}
.vstatus{font-family:'Baloo 2',cursive;font-size:.95rem;font-weight:800;color:rgba(255,255,255,.55);text-align:center;margin-bottom:3px;min-height:24px;transition:all .3s;}
.vstatus.talking{color:var(--amber);}
.vstatus.listening{color:#4ade80;}
.qbubble{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:15px;padding:13px 15px;margin:9px 14px 5px;font-size:.95rem;font-weight:800;color:white;line-height:1.5;text-align:center;min-height:56px;}
.qobj{display:inline-block;padding:3px 9px;border-radius:20px;font-size:.65rem;font-weight:800;margin-bottom:7px;background:rgba(255,107,53,.2);color:var(--amber);}
.opts{display:grid;grid-template-columns:1fr 1fr;gap:9px;padding:7px 13px 9px;}
.opt{padding:16px 10px;border-radius:14px;border:3px solid rgba(255,255,255,.14);background:rgba(255,255,255,.07);
  cursor:pointer;font-family:'Nunito',sans-serif;font-size:.92rem;font-weight:800;color:white;
  transition:all .22s;display:flex;flex-direction:column;align-items:center;gap:5px;min-height:75px;justify-content:center;text-align:center;line-height:1.3;}
.opt:active{transform:scale(.96);}
.oltr{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:900;flex-shrink:0;}
.opt[data-l=A] .oltr{background:rgba(74,144,217,.4);color:#93c5fd;}
.opt[data-l=B] .oltr{background:rgba(76,175,125,.4);color:#86efac;}
.opt[data-l=C] .oltr{background:rgba(155,89,182,.4);color:#d8b4fe;}
.opt[data-l=D] .oltr{background:rgba(255,107,53,.4);color:#fdba74;}
.opt[data-l=A]:hover:not(:disabled){border-color:var(--blue);background:rgba(74,144,217,.2);}
.opt[data-l=B]:hover:not(:disabled){border-color:var(--green);background:rgba(76,175,125,.2);}
.opt[data-l=C]:hover:not(:disabled){border-color:var(--purple);background:rgba(155,89,182,.2);}
.opt[data-l=D]:hover:not(:disabled){border-color:var(--orange);background:rgba(255,107,53,.2);}
.opt.correct{border-color:#4ade80;background:rgba(74,222,128,.18);}
.opt.correct .oltr{background:rgba(74,222,128,.4);color:#4ade80;}
.opt.wrong{border-color:#f87171;background:rgba(248,113,113,.18);}
.opt.wrong .oltr{background:rgba(248,113,113,.4);color:#f87171;}
.opt.reveal{border-color:#4ade80;background:rgba(74,222,128,.18);}
.opt.reveal .oltr{background:rgba(74,222,128,.4);color:#4ade80;}
.opt.echosen{border-color:var(--blue);background:rgba(74,144,217,.2);}
.opt:disabled{cursor:default;}
.vfeedback{margin:0 13px 4px;border-radius:12px;padding:10px 13px;font-size:.88rem;font-weight:800;color:white;text-align:center;display:none;line-height:1.5;}
.vfeedback.show{display:block;}
.vfeedback.fc{background:rgba(74,222,128,.2);border:1.5px solid rgba(74,222,128,.3);}
.vfeedback.fw{background:rgba(248,113,113,.2);border:1.5px solid rgba(248,113,113,.3);}
.vexpl{margin:0 13px 5px;background:rgba(245,158,11,.12);border:1.5px solid rgba(245,158,11,.28);border-radius:10px;padding:8px 12px;font-size:.8rem;font-weight:700;color:rgba(255,255,255,.8);text-align:center;display:none;line-height:1.5;}
.vexpl.show{display:block;}
.vnext{width:calc(100% - 26px);margin:4px 13px;padding:13px;border:none;border-radius:13px;font-family:'Baloo 2',cursive;font-size:1rem;font-weight:800;cursor:pointer;transition:all .2s;display:none;align-items:center;justify-content:center;gap:6px;background:linear-gradient(135deg,var(--orange),#e55a25);color:white;box-shadow:0 4px 14px rgba(255,107,53,.38);}
.vnext.show{display:flex;}
.vnext:hover{transform:translateY(-2px);}
.vcontrols{display:flex;align-items:center;justify-content:center;gap:13px;padding:7px 15px 5px;}
.mic-btn{width:66px;height:66px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.7rem;transition:all .22s;position:relative;flex-shrink:0;}
.mic-btn.idle{background:rgba(255,255,255,.1);}
.mic-btn.listening{background:linear-gradient(135deg,#4ade80,#22c55e);box-shadow:0 0 0 7px rgba(74,222,128,.2),0 0 0 14px rgba(74,222,128,.1);}
.mic-btn.listening::after{content:'';position:absolute;inset:-5px;border-radius:50%;border:2px solid rgba(74,222,128,.4);animation:rpl 1.2s linear infinite;}
@keyframes rpl{0%{transform:scale(1);opacity:1}100%{transform:scale(1.5);opacity:0}}
.replay-btn{width:46px;height:46px;border-radius:50%;border:2px solid rgba(255,255,255,.14);background:rgba(255,255,255,.07);cursor:pointer;font-size:1.15rem;display:flex;align-items:center;justify-content:center;color:white;transition:all .17s;}
.replay-btn:hover{background:rgba(255,255,255,.12);}
.mic-lbl{font-size:.67rem;font-weight:800;color:rgba(255,255,255,.35);margin-top:3px;text-align:center;}
.vc-wrap{display:flex;flex-direction:column;align-items:center;}
.vloading{display:flex;flex-direction:column;align-items:center;padding:26px;gap:9px;}
.vloading p{color:rgba(255,255,255,.45);font-size:.8rem;font-weight:700;}
.ldots{display:flex;gap:5px;}
.ldot{width:8px;height:8px;border-radius:50%;background:var(--orange);animation:lsp 1s ease-in-out infinite;}
.ldot:nth-child(2){animation-delay:.2s}.ldot:nth-child(3){animation-delay:.4s}
@keyframes lsp{0%,80%,100%{transform:scale(.6);opacity:.4}40%{transform:scale(1);opacity:1}}

/* REPORT */
.rep-screen{background:#1A1A2E;}
.rep-hero{text-align:center;padding:26px 13px 18px;}
.rep-big{font-family:'Baloo 2',cursive;font-size:3rem;font-weight:800;color:var(--amber);}
.rep-pct{font-size:1rem;font-weight:800;color:var(--amber);margin-top:2px;}
.rep-stars{font-size:1.8rem;letter-spacing:3px;margin:9px 0;}
.rep-msg{font-size:.9rem;font-weight:700;color:rgba(255,255,255,.55);}
.rep-list{padding:0 13px;display:flex;flex-direction:column;gap:7px;margin-bottom:13px;}
.ri{border-radius:11px;padding:11px 13px;border-left:4px solid;}
.ri.ok{background:rgba(74,222,128,.1);border-color:#4ade80;}
.ri.ko{background:rgba(248,113,113,.1);border-color:#f87171;}
.ri-top{display:flex;gap:5px;align-items:flex-start;margin-bottom:3px;}
.ri-n{font-size:.66rem;font-weight:800;color:rgba(255,255,255,.3);white-space:nowrap;}
.ri-q{font-size:.8rem;font-weight:700;color:white;flex:1;line-height:1.4;}
.ri-a{font-size:.73rem;font-weight:700;margin-top:2px;}
.ri-aok{color:#4ade80;}.ri-ako{color:#f87171;}
.ri-ex{font-size:.7rem;font-weight:600;color:rgba(245,158,11,.75);background:rgba(245,158,11,.1);border-radius:6px;padding:5px 8px;margin-top:5px;border:1px solid rgba(245,158,11,.18);}
.rep-btns{padding:0 13px 24px;display:flex;flex-direction:column;gap:8px;}

/* HISTORY */
.hist-screen{background:#1A1A2E;}
.hist-top{display:flex;align-items:center;justify-content:space-between;padding:15px;border-bottom:1px solid rgba(255,255,255,.08);}
.hist-top h2{font-family:'Baloo 2',cursive;font-size:1.05rem;font-weight:800;color:white;}
.del-btn{font-size:.7rem;font-weight:800;color:#f87171;cursor:pointer;border:none;background:none;font-family:'Nunito',sans-serif;}
.hlist{padding:11px 13px;display:flex;flex-direction:column;gap:7px;}
.hitem{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);border-radius:11px;padding:11px 13px;}
.hitem-top{display:flex;align-items:center;gap:8px;margin-bottom:5px;}
.hico{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.hinfo h4{font-size:.8rem;font-weight:800;color:white;line-height:1.3;}
.hinfo span{font-size:.66rem;color:rgba(255,255,255,.35);font-weight:600;}
.hsc{margin-left:auto;text-align:right;}
.hpct{font-family:'Baloo 2',cursive;font-size:1.05rem;font-weight:800;}
.hbar{height:4px;background:rgba(255,255,255,.1);border-radius:20px;overflow:hidden;}
.hfill{height:100%;border-radius:20px;}
.hempty{text-align:center;padding:36px 18px;color:rgba(255,255,255,.28);font-size:.88rem;font-weight:700;}
@media(max-width:380px){.opts{grid-template-columns:1fr;}.opt{min-height:58px;padding:13px 10px;}}
</style>
</head>
<body>

<!-- ══ SETUP SCREEN ══ -->
<div class="screen active" id="s0">
  <div class="setup-hdr">
    <span class="mascot">🦉</span>
    <h1>ProfeBot Voz</h1>
    <p>🎙️ ¡Yo te leo todo en voz alta!</p>
  </div>

  <!-- PROVEEDORES -->
  <div id="apiSection">
    <div class="prov-section" id="provConfig">
      <div class="prov-title">🔑 Configurar Proveedores de IA</div>

      <div class="prov-row" id="provRow_claude">
        <div class="prov-hdr" onclick="toggleProv('claude')">
          <span>🟠 Claude</span>
          <span class="prov-status" id="provSt_claude">❌</span>
        </div>
        <div class="prov-body" id="provBody_claude">
          <p>Obtené tu key en <a href="https://console.anthropic.com/settings/keys" target="_blank"><strong>console.anthropic.com</strong></a> → API Keys.</p>
          <div class="api-row">
            <input class="api-inp" type="password" id="provInp_claude" placeholder="sk-ant-..."/>
            <button class="api-save" onclick="saveProv('claude')">Guardar</button>
          </div>
        </div>
      </div>

      <div class="prov-row" id="provRow_groq">
        <div class="prov-hdr" onclick="toggleProv('groq')">
          <span>⚡ Groq</span>
          <span class="prov-status" id="provSt_groq">❌</span>
        </div>
        <div class="prov-body" id="provBody_groq">
          <p>Obtené tu key en <a href="https://console.groq.com/keys" target="_blank"><strong>console.groq.com/keys</strong></a> → Create API Key.</p>
          <div class="api-row">
            <input class="api-inp" type="password" id="provInp_groq" placeholder="gsk_..."/>
            <button class="api-save" onclick="saveProv('groq')">Guardar</button>
          </div>
        </div>
      </div>

      <div class="prov-row" id="provRow_gemini">
        <div class="prov-hdr" onclick="toggleProv('gemini')">
          <span>💎 Gemini</span>
          <span class="prov-status" id="provSt_gemini">❌</span>
        </div>
        <div class="prov-body" id="provBody_gemini">
          <p>Obtené tu key en <a href="https://aistudio.google.com/apikey" target="_blank"><strong>aistudio.google.com/apikey</strong></a> → Create API Key.</p>
          <div class="api-row">
            <input class="api-inp" type="password" id="provInp_gemini" placeholder="AIza..."/>
            <button class="api-save" onclick="saveProv('gemini')">Guardar</button>
          </div>
        </div>
      </div>
    </div>
    <div class="api-ok" id="apiOk" style="display:none"><span id="apiOkTxt">✅ 1 proveedor configurado.</span> <span style="margin-left:auto;cursor:pointer;text-decoration:underline;font-size:.7rem" onclick="showProvConfig()">Cambiar</span></div>
  </div>

  <!-- OBJECTIVES -->
  <div class="card">
    <div class="subj-tabs">
      <button class="stab am" onclick="switchSubj('mat',this)">🔢 Matemática</button>
      <button class="stab" onclick="switchSubj('len',this)">🗣️ Lengua</button>
    </div>
    <div class="selrow">
      <span class="selcount" id="selCount">0 objetivos</span>
      <div style="display:flex;gap:7px">
        <button class="lnk" onclick="selAll(true)">✅ Todos</button>
        <button class="lnk" onclick="selAll(false)">◻ Ninguno</button>
      </div>
    </div>
    <div class="unit-list" id="unitList"></div>
  </div>

  <!-- CONFIG -->
  <div class="card">
    <div class="sec">🎮 Modo</div>
    <div class="row2" id="modeRow">
      <button class="chip cg on" data-v="study" onclick="pickChip(this,'modeRow','cg')">💪 Practicar</button>
      <button class="chip cb" data-v="eval" onclick="pickChip(this,'modeRow','cb')">📋 Evaluar</button>
    </div>
    <div class="sec" style="margin-top:10px">🔢 Preguntas</div>
    <div class="row2" id="cntRow">
      <button class="chip cg" data-v="5" onclick="pickChip(this,'cntRow','cg')">5</button>
      <button class="chip cg on" data-v="10" onclick="pickChip(this,'cntRow','cg')">10</button>
      <button class="chip cg" data-v="15" onclick="pickChip(this,'cntRow','cg')">15</button>
      <button class="chip cg" data-v="20" onclick="pickChip(this,'cntRow','cg')">20</button>
    </div>
    <div class="sec" style="margin-top:10px">⚡ Dificultad</div>
    <div class="row2" id="diffRow">
      <button class="chip cb" data-v="fácil" onclick="pickChip(this,'diffRow','cb')">😊 Fácil</button>
      <button class="chip cb on" data-v="mixto" onclick="pickChip(this,'diffRow','cb')">🎯 Mixta</button>
      <button class="chip cb" data-v="difícil" onclick="pickChip(this,'diffRow','cb')">🔥 Difícil</button>
    </div>
    <div class="sec" style="margin-top:10px">🎙️ Voz</div>
    <div class="row2">
      <button class="vtog on" id="ttsToggle" onclick="toggleTTS()">🔊 Leer preguntas</button>
      <button class="vtog on" id="srToggle" onclick="toggleSR()">🎤 Escuchar respuesta</button>
    </div>
    <div id="noVoiceWarn" class="novoice" style="display:none">⚠️ Sin reconocimiento de voz — usá Chrome/Edge. Podés tocar los botones.</div>
  </div>

  <!-- MATERIALS -->
  <div class="card">
    <div class="sec" style="cursor:pointer" onclick="toggleMat()">📁 Materiales propios (opcional) <span id="matArr">▼</span></div>
    <div id="matBody" style="display:none">
      <div class="drop" id="dropZ" ondragover="event.preventDefault();this.classList.add('dg')" ondragleave="this.classList.remove('dg')" ondrop="onDrop(event)">
        <input type="file" accept=".pdf" multiple onchange="handleFiles(this.files)"/>
        <span style="font-size:1.5rem">📄</span>
        <p style="font-size:.75rem;font-weight:700;color:var(--muted);margin-top:2px">PDF o <strong style="color:var(--purple)">clic para elegir</strong></p>
      </div>
      <div class="slist" id="srcList"></div>
      <div class="warn" style="margin-top:6px">⚠️ Algunos sitios bloquean el acceso externo.</div>
      <div class="urow">
        <input class="uinp" id="urlInp" type="url" placeholder="https://..."/>
        <button class="smbtn sb" onclick="addUrl()">+ URL</button>
      </div>
      <div class="slist" id="urlList"></div>
    </div>
  </div>

  <button class="abtn o" onclick="startAuto()">🎲 ¡Batería aleatoria!</button>
  <button class="abtn g" onclick="startManual()">💪 ¡Comenzar práctica!</button>
  <button class="abtn out" onclick="showS('sHist')">📊 Ver historial</button>
</div>

<!-- ══ VOICE SCREEN ══ -->
<div class="screen voice-screen" id="sVoice">
  <div class="vbar">
    <button class="vbar-back" onclick="if(confirm('¿Salir?')){stopAll();showS('s0')}">←</button>
    <div class="vbar-info"><h3 id="vTitle">Práctica</h3><span id="vSub">1° Grado</span></div>
    <div class="vscore"><span class="vscl">✅/❌</span><span class="vsc" id="vsc">0</span>/<span class="vsw" id="vsw">0</span></div>
  </div>
  <div class="vprog">
    <div class="vbar2"><div class="vfill" id="vfill" style="width:0%"></div></div>
    <div class="vplbl"><span id="vptxt">Pregunta 1</span><span id="vppct">0%</span></div>
  </div>
  <div class="owl-zone">
    <div class="owl-wrap" id="owlWrap">
      <span class="owl-emoji">🦉</span>
      <div class="waves" id="wavesEl"><div class="wave"></div><div class="wave"></div><div class="wave"></div><div class="wave"></div><div class="wave"></div></div>
    </div>
    <div class="vstatus" id="vstatus">Cargando...</div>
  </div>
  <div id="vContent"><div class="vloading"><div class="ldots"><div class="ldot"></div><div class="ldot"></div><div class="ldot"></div></div><p>Generando pregunta...</p></div></div>
</div>

<!-- ══ REPORT ══ -->
<div class="screen rep-screen" id="sRep">
  <div class="rep-hero" id="repHero"></div>
  <div style="padding:0 13px;font-size:.68rem;font-weight:800;color:rgba(255,255,255,.28);text-transform:uppercase;letter-spacing:1px;margin-bottom:7px">Detalle</div>
  <div class="rep-list" id="repList"></div>
  <div class="rep-btns">
    <button class="abtn o" onclick="retrySession()">🔄 Repetir</button>
    <button class="abtn g" onclick="showS('sHist')">📊 Historial</button>
    <button class="abtn out" onclick="showS('s0')">🏠 Inicio</button>
  </div>
</div>

<!-- ══ HISTORY ══ -->
<div class="screen hist-screen" id="sHist">
  <div class="hist-top"><h2>📊 Historial</h2><button class="del-btn" onclick="clearHist()">🗑 Borrar todo</button></div>
  <div id="histContent"></div>
  <div style="padding:0 13px 8px"><button class="abtn out" onclick="showS('s0')">🏠 Inicio</button></div>
</div>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

// ── CURRICULUM ──
const CUR={
  mat:{label:'Matemática',icon:'🔢',color:'#4A90D9',units:[
    {name:'Articulación espacial y conjuntos',objs:['Distinguir izquierda y derecha','Comparar longitudes: largo, corto, igual','Descomponer conjuntos en partes','Comparar conjuntos por cantidad']},
    {name:'Números naturales hasta 10',objs:['Reconocer el número natural tres','Contar números del 1 al 5','Comparar números del 1 al 5','Ordenar números hasta 5','Identificar el rayo numérico','Reconocer los números ordinales']},
    {name:'Adición y sustracción hasta 10',objs:['Resolver la adición 3 + 2','Resolver la adición 3 + 4','Sumar varios sumandos hasta 10','Restar con resultado hasta 10']},
    {name:'Números naturales hasta 20',objs:['Contar los números del 11 al 20','Reconocer los números del 18 al 19']},
    {name:'Adición y sustracción hasta 20',objs:['Sumar hasta 20','Restar hasta 20']},
    {name:'Números naturales hasta 100',objs:['Contar los números del 21 al 100']},
    {name:'Geometría',objs:['Medir con el centímetro','Identificar puntos y rectas','Reconocer y comparar segmentos','Trazar rectas con regla','Reconocer el triángulo','Reconocer el rectángulo','Reconocer el cuadrado','Reconocer el círculo']}
  ]},
  len:{label:'Lengua Española',icon:'🗣️',color:'#4CAF7D',units:[
    {name:'Dígrafos CH y LL',objs:['Reconocer el dígrafo CH (leche, chico)','Usar CH en palabras','Reconocer el dígrafo LL (llave, lluvia)','Usar LL en palabras']},
    {name:'Fonemas vocálicos y M→S,Z,C',objs:['Pronunciar las vocales','Fonema M (mamá, mesa)','Fonema P (papá, pato)','Fonema S (sapo, sol)','Fonema Z/C (zapato, cera)','Fonema N (nene, nota)','Fonema T (toro, tela)','Fonema D (dedo, dado)','Fonema L (luna, lana)','Fonema F (foca, feo)','Fonema B/V (barco, vaca)','Leer sílabas directas']},
    {name:'Fonemas H→W y orden alfabético',objs:['La H muda (huevo, hotel)','R suave y fuerte (loro, rosa)','RR vs R intervocálica','Fonema G/GU (gato, guerra)','Diéresis GÜ (pingüino)','Fonema J (jirafa, caja)','Fonema K/QU (queso)','Fonema X (examen)','Fonema W (wafle)','El orden alfabético','Ordenar palabras alfabéticamente']},
    {name:'Grafemas y escritura',objs:['Escribir letras minúsculas','Escribir letras mayúsculas','Palabras monosílabas (sol, mar)','Palabras bisílabas (casa, mesa)','Completar oraciones simples']}
  ]}
};

// ── STATE ──
let _subj='mat', selObjs=new Set(), sources=[];
let battMode='study', battCnt=10, battDiff='mixto';
let useTTS=true, useSR=true;
let sessQs=[], sessIdx=0, sessCorr=0, sessWrong=0, sessAsked=[];
let sessMode='study', sessIsAuto=false;
let currentQ=null, srActive=false;

// ── PROVEEDORES ──
const PK='profebot_providers';
const PROV_META={
  claude:{label:'Claude',prefix:'sk-ant-',order:0},
  gemini:{label:'Gemini',prefix:'AIza',order:1},
  groq:{label:'Groq',prefix:'gsk_',order:2},
  cache:{label:'Cache',prefix:'',order:99},
};
const PROV_ORDER=Object.keys(PROV_META);

function getProviderKeys(){try{return JSON.parse(localStorage.getItem(PK))||{};}catch(e){return {};}}
function setProviderKeys(obj){localStorage.setItem(PK,JSON.stringify(obj));}

// Migrar key vieja (profebot_apikey) → estructura nueva
function migrateOldKey(){
  const old=localStorage.getItem('profebot_apikey');
  if(!old)return;
  const cur=getProviderKeys();
  if(!cur.groq){cur.groq=old;setProviderKeys(cur);}
  localStorage.removeItem('profebot_apikey');
}

function saveProv(pid){
  const inp=document.getElementById('provInp_'+pid);
  const v=inp.value.trim();
  const meta=PROV_META[pid];
  if(meta.prefix&&!v.startsWith(meta.prefix)){alert('La key de '+meta.label+' debe empezar con '+meta.prefix+'...');return;}
  const keys=getProviderKeys();keys[pid]=v;setProviderKeys(keys);
  inp.value='';
  refreshProvUI();
}

function toggleProv(pid){
  const body=document.getElementById('provBody_'+pid);
  body.classList.toggle('open');
}

function refreshProvUI(){
  const keys=getProviderKeys();
  let count=0;
  for(const pid of PROV_ORDER){
    const has=!!keys[pid];
    if(has)count++;
    document.getElementById('provSt_'+pid).textContent=has?'✅':'❌';
    document.getElementById('provRow_'+pid).classList.toggle('ok',has);
  }
  const isServer=!location.hostname.match(/^(localhost|127\.)/);
  const hasLocal=count>0;
  if(hasLocal||isServer){
    document.getElementById('provConfig').style.display='none';
    document.getElementById('apiOk').style.display='flex';
    const txt=isServer&&!hasLocal?'✅ Servidor configurado.':'✅ '+count+' proveedor'+(count>1?'es':'')+' configurado'+(count>1?'s':'')+'.';
    document.getElementById('apiOkTxt').textContent=txt;
  }else{
    document.getElementById('provConfig').style.display='block';
    document.getElementById('apiOk').style.display='none';
  }
}

function showProvConfig(){
  document.getElementById('provConfig').style.display='block';
  document.getElementById('apiOk').style.display='none';
}

function initApiUI(){
  migrateOldKey();
  refreshProvUI();
}

// ── SPEECH ──
const synth=window.speechSynthesis;
let voices=[];
synth.addEventListener('voiceschanged',()=>{voices=synth.getVoices();});
voices=synth.getVoices();
function getBestVoice(){
  const prefs=['es-CU','es-419','es-MX','es-AR','es-UY','es'];
  for(const p of prefs){const v=voices.find(v=>v.lang.startsWith(p.replace(/-\d+/,'').split('-')[0])&&(p.includes('-')?v.lang===p||v.lang.startsWith(p):true));if(v)return v;}
  return voices.find(v=>v.lang.startsWith('es'))||null;
}
function speak(txt,onEnd){
  if(!useTTS||!txt){if(onEnd)onEnd();return;}
  synth.cancel();
  const u=new SpeechSynthesisUtterance(txt);
  const v=getBestVoice();if(v)u.voice=v;
  u.lang='es-ES';u.rate=0.88;u.pitch=1.1;u.volume=1;
  setOwl('talking');setStatus('Leyendo...','talking');
  u.onend=()=>{setOwl('idle');setStatus('','');if(onEnd)onEnd();};
  u.onerror=()=>{setOwl('idle');if(onEnd)onEnd();};
  synth.speak(u);
}
function stopSpeak(){synth.cancel();setOwl('idle');setStatus('','');}
function setOwl(s){
  const w=document.getElementById('owlWrap'),wv=document.getElementById('wavesEl');
  w.className='owl-wrap';
  if(s==='talking'){w.classList.add('talking');wv.classList.add('show');}
  else if(s==='listening'){w.classList.add('listening');wv.classList.remove('show');}
  else wv.classList.remove('show');
}
function setStatus(t,c){const el=document.getElementById('vstatus');if(el){el.textContent=t;el.className='vstatus '+(c||'');}}

// SR
let SR=window.SpeechRecognition||window.webkitSpeechRecognition;
let recognition=null;
function startListening(){
  if(!SR||!useSR||srActive||currentQ?.chosen)return;
  recognition=new SR();recognition.lang='es-ES';recognition.continuous=false;recognition.interimResults=false;recognition.maxAlternatives=3;
  setOwl('listening');setStatus('¡Hablá! A, B, C o D...','listening');
  const mb=document.getElementById('micBtn'),ml=document.getElementById('micLbl');
  if(mb)mb.className='mic-btn listening';if(ml)ml.textContent='Escuchando...';
  srActive=true;
  recognition.onresult=(e)=>{
    const rs=Array.from(e.results[0]).map(r=>r.transcript.trim().toUpperCase());
    const l=rs.map(r=>{
      if(/^[ABCD]$/.test(r))return r;
      const m=r.match(/\b([ABCD])\b/);if(m)return m[1];
      return null;
    }).find(Boolean);
    stopSRUI();
    if(l&&currentQ&&!currentQ.chosen)chooseAns(l);
    else{setStatus('No entendí. Tocá una opción.','');if(useTTS)speak('No entendí, tocá una opción.');}
  };
  recognition.onerror=()=>{stopSRUI();};
  recognition.onend=()=>{stopSRUI();};
  try{recognition.start();}catch{stopSRUI();}
}
function stopSR(){if(recognition){try{recognition.abort();}catch{}}stopSRUI();}
function stopSRUI(){
  srActive=false;
  const mb=document.getElementById('micBtn'),ml=document.getElementById('micLbl');
  if(mb)mb.className='mic-btn idle';
  if(ml)ml.textContent=useSR&&SR?'Decí A, B, C o D':'Toca una opción';
  setOwl('idle');setStatus('','');
}
function toggleListen(){if(srActive)stopSR();else{stopSpeak();startListening();}}
function stopAll(){stopSpeak();stopSR();}

// ── NAV ──
function showS(id){document.querySelectorAll('.screen').forEach(s=>s.classList.remove('active'));document.getElementById(id).classList.add('active');if(id==='sHist')renderHist();}

// ── SETUP ──
function switchSubj(s,btn){
  _subj=s;
  document.querySelectorAll('.stab').forEach(b=>b.className='stab');
  btn.classList.add(s==='mat'?'am':'al');
  // Deselect other subject, select all of current
  selObjs.clear();
  CUR[s].units.forEach((u,ui)=>u.objs.forEach((_,oi)=>selObjs.add(`${s}::${ui}::${oi}`)));
  renderUnits();
}
function pickChip(btn,rid,cls){document.querySelectorAll('#'+rid+' .chip').forEach(b=>b.classList.remove('on'));btn.classList.add('on');}
function getChip(rid){return document.querySelector('#'+rid+' .chip.on')?.dataset.v;}
function toggleTTS(){useTTS=!useTTS;document.getElementById('ttsToggle').classList.toggle('on',useTTS);}
function toggleSR(){useSR=!useSR;document.getElementById('srToggle').classList.toggle('on',useSR);checkSR();}
function checkSR(){document.getElementById('noVoiceWarn').style.display=(!SR||!useSR)?'block':'none';}
function toggleMat(){const b=document.getElementById('matBody'),a=document.getElementById('matArr');const o=b.style.display!=='none';b.style.display=o?'none':'block';a.textContent=o?'▼':'▲';}

// ── OBJECTIVES ──
function renderUnits(){
  const data=CUR[_subj];
  document.getElementById('unitList').innerHTML=data.units.map((u,ui)=>{
    const rows=u.objs.map((o,oi)=>{
      const k=`${_subj}::${ui}::${oi}`,chk=selObjs.has(k);
      return`<label class="obj-row${chk?' chk':''}" id="or-${k.replace(/::/g,'_')}"><input type="checkbox" ${chk?'checked':''} onchange="toggleObj('${k}',this.checked)"/><span>${esc(o)}</span></label>`;
    }).join('');
    const cnt=u.objs.filter((_,oi)=>selObjs.has(`${_subj}::${ui}::${oi}`)).length;
    return`<div class="unit-blk"><div class="unit-hdr" onclick="toggleUnit(this)"><span>${data.icon}</span><span>${esc(u.name)}</span><span class="ubadge">${cnt}/${u.objs.length}</span><span class="utog">▼</span></div><div class="unit-body">${rows}</div></div>`;
  }).join('');
  updSel();
}
function toggleUnit(h){h.classList.toggle('open');h.nextElementSibling.classList.toggle('open');}
function toggleObj(k,v){v?selObjs.add(k):selObjs.delete(k);const el=document.getElementById('or-'+k.replace(/::/g,'_'));if(el)el.classList.toggle('chk',v);updSel();}
function selAll(v){Object.entries(CUR).forEach(([s,subj])=>subj.units.forEach((u,ui)=>u.objs.forEach((_,oi)=>{const k=`${s}::${ui}::${oi}`;v?selObjs.add(k):selObjs.delete(k);})));renderUnits();}
function updSel(){document.getElementById('selCount').textContent=`${selObjs.size} objetivo${selObjs.size!==1?'s':''}`;}

// ── MATERIALS ──
function onDrop(e){e.preventDefault();document.getElementById('dropZ').classList.remove('dg');handleFiles(e.dataTransfer.files);}
function handleFiles(files){Array.from(files).filter(f=>f.type==='application/pdf').forEach(procPDF);}
async function procPDF(file){
  const id='f'+Date.now().toString(36);const src={type:'pdf',id,name:file.name,content:'',status:'loading'};sources.push(src);rSrc(src);
  try{
    const pdf=await pdfjsLib.getDocument({data:await file.arrayBuffer()}).promise;
    let txt='';for(let i=1;i<=pdf.numPages;i++){const pg=await pdf.getPage(i);txt+=(await pg.getTextContent()).items.map(it=>it.str).join(' ')+'\n';}
    src.content=txt.trim().slice(0,12000);src.status=src.content.length>10?'ok':'err';
  }catch{src.status='err';}rSrc(src);
}
async function addUrl(){
  const inp=document.getElementById('urlInp');let raw=inp.value.trim();if(!raw)return;
  if(!/^https?:\/\//i.test(raw))raw='https://'+raw;inp.value='';
  const id='u'+Date.now().toString(36);const src={type:'url',id,name:raw,content:'',status:'loading'};sources.push(src);rSrc(src);
  try{
    const r=await fetch(`https://api.allorigins.win/raw?url=${encodeURIComponent(raw)}`);if(!r.ok)throw 0;
    const html=await r.text();const doc=new DOMParser().parseFromString(html,'text/html');
    ['script','style','nav','footer','header','aside','noscript'].forEach(t=>doc.querySelectorAll(t).forEach(e=>e.remove()));
    src.content=(doc.body?.innerText||'').replace(/\s+/g,' ').trim().slice(0,12000);src.status=src.content.length>50?'ok':'err';
  }catch{src.status='err';}rSrc(src);
}
function rSrc(src){
  const sm={ok:'✅',loading:'⏳',err:'❌'},st={ok:'sok',loading:'sld',err:'ser'};
  const h=`<div class="sit ${src.type}" id="${src.id}"><span>${src.type==='pdf'?'📄':'🔗'}</span><span class="sn">${esc(src.name.replace(/^https?:\/\/(www\.)?/,'').slice(0,40))}</span><span class="sstat ${st[src.status]}">${sm[src.status]}</span><button class="xbtn" onclick="rmSrc('${src.id}')">✕</button></div>`;
  const el=document.getElementById(src.id);
  const list=document.getElementById(src.type==='pdf'?'srcList':'urlList');
  if(el)el.outerHTML=h;else list.insertAdjacentHTML('beforeend',h);
}
function rmSrc(id){sources=sources.filter(s=>s.id!==id);const el=document.getElementById(id);if(el)el.remove();}

// ── AUTO-LOAD DEFAULT MATERIALS (.txt) ──
const DEFAULT_MATERIALS=[
  {file:'materiales/leng_libro_antiguo.txt',name:'Libro Lengua (antiguo)',subj:'leng'},
  {file:'materiales/leng_libro.txt',name:'¡A leer! 1er. Grado',subj:'leng'},
  {file:'materiales/leng_cuaderno.txt',name:'Cuaderno Escritura',subj:'leng'},
  {file:'materiales/mat_libro_antiguo.txt',name:'Libro Matemática (antiguo)',subj:'mat'},
  {file:'materiales/mat_libro.txt',name:'Matemática 1er. Grado',subj:'mat'},
  {file:'materiales/mat_cuaderno.txt',name:'Cuaderno Matemática',subj:'mat'}
];
async function loadDefaultMaterials(){
  for(const {file,name,subj} of DEFAULT_MATERIALS){
    const id='d'+Date.now().toString(36)+Math.random().toString(36).slice(2,5);
    const src={type:'pdf',id,name,subj,content:'',status:'loading'};sources.push(src);rSrc(src);
    try{
      const r=await fetch(file);if(!r.ok)throw 0;
      const txt=await r.text();
      src.content=txt.trim().slice(0,3000);src.status=src.content.length>10?'ok':'err';
    }catch{src.status='err';}
    rSrc(src);
  }
}

// ── SESSION ──
function getActiveObjs(){
  const list=[];
  selObjs.forEach(k=>{const[s,ui,oi]=k.split('::');const subj=CUR[s];if(!subj)return;const unit=subj.units[+ui];if(!unit)return;list.push({k,subjKey:s,subj:subj.label,unit:unit.name,obj:unit.objs[+oi],color:subj.color});});
  return list;
}
function startAuto(){
  if(!getActiveObjs().length){alert('Seleccioná objetivos.');return;}
  sessIsAuto=true;sessMode=['study','eval'][Math.round(Math.random())];battMode=sessMode;battCnt=[5,10,15][Math.floor(Math.random()*3)];battDiff='mixto';_init();
}
function startManual(){
  if(!getActiveObjs().length){alert('Seleccioná objetivos.');return;}
  sessIsAuto=false;sessMode=getChip('modeRow')||'study';battMode=sessMode;battCnt=+(getChip('cntRow')||10);battDiff=getChip('diffRow')||'mixto';_init();
}
function _init(){
  sessQs=[];sessIdx=0;sessCorr=0;sessWrong=0;sessAsked=[];
  showS('sVoice');
  document.getElementById('vTitle').textContent=sessIsAuto?'🎲 Batería aleatoria':sessMode==='eval'?'📋 Evaluación':'💪 Práctica';
  document.getElementById('vSub').textContent=`1° Grado · ${battCnt} preguntas`;
  updVProg();loadQ();
}
function retrySession(){sessQs=[];sessIdx=0;sessCorr=0;sessWrong=0;sessAsked=[];showS('sVoice');updVProg();loadQ();}

// ── API CALL (via local PHP proxy) ──
function buildCtx(subjKey){
  const ok=sources.filter(s=>s.status==='ok'&&(!s.subj||s.subj===subjKey));
  if(!ok.length)return '';
  let c='\n\n=== MATERIALES DE REFERENCIA ===\nUsá este contenido como guía para el nivel y estilo de las preguntas. Si el tema no aparece en el material, generá la pregunta igualmente basándote en el objetivo.\n\n';
  ok.forEach((s,i)=>{c+=`--- ${i+1}: ${s.name.slice(0,50)} ---\n${s.content}\n\n`;});
  return c+'=== FIN ===\n';
}
function getSys(obj){
  return`Eres un generador de preguntas de múltiple opción para 1° grado (niños 6-7 años), currículo cubano.
Materia: ${obj.subj}. Unidad: "${obj.unit}".

INSTRUCCIONES ESTRICTAS:
1. Primero decide cuál es la respuesta correcta.
2. Luego coloca esa respuesta en una de las 4 opciones (A, B, C o D) al azar.
3. Las otras 3 opciones deben ser INCORRECTAS pero creíbles.
4. En CORRECTA: pon la LETRA (A, B, C o D) donde pusiste la respuesta correcta.

FORMATO OBLIGATORIO (exactamente 7 líneas, sin texto extra, sin markdown, sin asteriscos):
PREGUNTA: [máx 18 palabras]
A) [opción]
B) [opción]
C) [opción]
D) [opción]
CORRECTA: [la letra A, B, C o D que tiene la respuesta correcta]
EXPLICACION: [1 oración corta explicando por qué esa es la correcta]

Ejemplo:
PREGUNTA: ¿Cuánto es 2 + 3?
A) 4
B) 5
C) 6
D) 7
CORRECTA: B
EXPLICACION: 2 más 3 es 5.

VERIFICA antes de responder: la letra en CORRECTA debe coincidir con la opción que tiene la respuesta verdadera.

PROHIBIDO: preguntas que necesiten ver una imagen, dibujo, ilustración, figura, lámina o tabla. Todo debe entenderse SOLO con texto. Lenguaje muy simple, español.${buildCtx(obj.subjKey)}`;
}
function getUMsg(obj,n,tot,prev){
  const dm={
    fácil:'FÁCIL: pregunta muy sencilla, directa, con opciones obviamente diferentes. Solo requiere reconocer o recordar algo básico.',
    media:'MEDIA: pregunta que requiere pensar un poco, las opciones son parecidas entre sí y el niño debe razonar.',
    difícil:'DIFÍCIL: pregunta con trampa o que requiere varios pasos de razonamiento. Las opciones incorrectas son muy creíbles.',
    mixto:`VARIADA (pregunta ${n} de ${tot}): alterna entre fácil, media y difícil.`
  };
  let m=`Pregunta ${n} de ${tot}. Objetivo: "${obj.obj}".\nDificultad: ${dm[battDiff]||dm.mixto}`;
  if(prev.length)m+=`\nNo repetir: ${prev.slice(-5).join(' / ')}`;
  return m;
}
let lastProvider='';
async function callAPI(sys,userMsg){
  const keys=getProviderKeys();
  const order=PROV_ORDER.filter(p=>!!keys[p]);
  // Si no hay keys locales, enviar orden default (backend usará env vars)
  const sendOrder=order.length?order:PROV_ORDER;
  const r=await fetch(window.location.pathname,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({system:sys,messages:[{role:'user',content:userMsg}],providers:keys,provider_order:sendOrder})
  });
  if(!r.ok){const d=await r.json().catch(()=>({}));throw new Error(d.error||'HTTP '+r.status);}
  const d=await r.json();
  if(d.provider)lastProvider=d.provider;
  return d;
}

// ── PARSE ──
function parseQ(txt){
  console.log('[parseQ] raw:',txt);
  // El modelo a veces envuelve la respuesta en bloques markdown o añade asteriscos
  txt=txt.replace(/```[a-z]*\n?/g,'').replace(/\*\*/g,'').trim();
  const qm=txt.match(/PREGUNTA:\s*(.+?)(?=\n[A-D]\))/is);
  const am=txt.match(/^A\)\s*(.+)/im),bm=txt.match(/^B\)\s*(.+)/im);
  const cm=txt.match(/^C\)\s*(.+)/im),dm=txt.match(/^D\)\s*(.+)/im);
  const cr=txt.match(/CORRECTA:\s*([ABCD])/i),ex=txt.match(/EXPLICACI[OÓ]N:\s*(.+)/i);
  if(!qm||!am||!bm||!cr)return null;
  return{question:qm[1].trim(),opts:{A:am[1].trim(),B:bm[1].trim(),C:cm?cm[1].trim():'',D:dm?dm[1].trim():''},correct:cr[1].toUpperCase(),explanation:ex?ex[1].trim():''};
}
function pickObj(){
  const all=getActiveObjs();if(!all.length)return null;
  return sessIsAuto?all[Math.floor(Math.random()*all.length)]:all[sessIdx%all.length];
}

function getRealDiff(){
  if(battDiff!=='mixto')return battDiff;
  return['fácil','media','difícil'][sessIdx%3];
}

function saveToCacheBackground(cacheKey,q){
  const body={action:'cache_save',cache_key:cacheKey,question:{question:q.question,opts:q.opts,correct:q.correct,explanation:q.explanation}};
  fetch(window.location.pathname,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).catch(()=>{});
}

async function loadQ(){
  stopAll();setStatus('Generando pregunta...','');setOwl('idle');
  document.getElementById('vContent').innerHTML=`<div class="vloading"><div class="ldots"><div class="ldot"></div><div class="ldot"></div><div class="ldot"></div></div><p>Pregunta ${sessIdx+1} de ${battCnt}...</p></div>`;
  const obj=pickObj();if(!obj)return;
  const realDiff=getRealDiff();
  const cacheKey=obj.k+'::'+realDiff;
  try{
    // Try cache first
    let q=null;
    try{
      const cr=await fetch(window.location.pathname,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'cache_get',cache_key:cacheKey,exclude:sessAsked})});
      const cd=await cr.json();
      if(cd.cached&&cd.question){
        q=cd.question;
        lastProvider='cache';
      }
    }catch(e){}

    // Cache miss → call AI
    if(!q){
      for(let attempt=0;attempt<3&&!q;attempt++){
        const d=await callAPI(getSys(obj),getUMsg(obj,sessIdx+1,battCnt,sessAsked));
        const txt=d.content?.[0]?.text||'';
        q=parseQ(txt);
        if(!q)console.warn('parseQ retry',attempt+1,'on:',txt);
      }
      if(!q)throw new Error('parse');
      // Save to cache in background
      saveToCacheBackground(cacheKey,q);
    }
    q.objText=obj.obj;q.subjLabel=obj.subj;q.chosen=null;q.color=obj.color;
    sessQs.push(q);sessAsked.push(q.question);currentQ=q;
    renderQ(q);
    readQuestion(q);
  }catch(e){
    const msg=e.message==='NO_KEY'?'⚠️ Configurá tu API Key arriba antes de empezar.':'❌ Error: '+e.message;
    document.getElementById('vContent').innerHTML=`<div style="padding:18px;text-align:center;color:#f87171;font-weight:700;line-height:1.6">${msg}<br><button onclick="${e.message==='NO_KEY'?'stopAll();showS(\'s0\')':'loadQ()'}" style="background:var(--orange);color:white;border:none;padding:9px 18px;border-radius:10px;cursor:pointer;font-family:'Nunito',sans-serif;font-weight:800;margin-top:10px;display:block;width:100%">${e.message==='NO_KEY'?'Ir a configuración':'🔄 Reintentar'}</button></div>`;
  }
}

function renderQ(q){
  const letters=['A','B','C','D'].filter(l=>q.opts[l]);
  document.getElementById('vContent').innerHTML=`
    <div class="qbubble"><div class="qobj">${esc(q.objText)}</div><br><span>${esc(q.question)}</span>${lastProvider?'<div class="prov-badge">vía '+esc(PROV_META[lastProvider]?.label||lastProvider)+'</div>':''}</div>
    <div class="opts">${letters.map(l=>`<button class="opt" data-l="${l}" onclick="chooseAns('${l}')" id="opt${l}"><span class="oltr">${l}</span><span>${esc(q.opts[l])}</span></button>`).join('')}</div>
    <div class="vfeedback" id="vfb"></div>
    <div class="vexpl" id="vex"></div>
    <button class="vnext" id="vnext" onclick="goNext()">${sessIdx+1<battCnt?'Siguiente ➜':'Ver resultados 🏁'}</button>
    <div class="vcontrols">
      <div class="vc-wrap"><button class="replay-btn" onclick="readQuestion(currentQ)">🔊</button></div>
      <div class="vc-wrap"><button class="mic-btn idle" id="micBtn" onclick="toggleListen()">🎤</button><div class="mic-lbl" id="micLbl">${useSR&&SR?'Decí A, B, C o D':'Toca una opción'}</div></div>
      <div class="vc-wrap"><button class="replay-btn" onclick="readOptions(currentQ)">📋</button></div>
    </div>`;
  updVProg();
}

function readQuestion(q){if(!q)return;speak(`Pregunta ${sessIdx+1}. ${q.question}`,()=>readOptions(q));}
function readOptions(q){
  if(!q)return;
  const ls=['A','B','C','D'].filter(l=>q.opts[l]);
  speak(ls.map(l=>`Opción ${l}: ${q.opts[l]}`).join('. ')+'. ¿Cuál elegís?',()=>{if(useSR&&SR&&!currentQ?.chosen)startListening();});
}

function chooseAns(letter){
  const q=sessQs[sessIdx];if(!q||q.chosen)return;
  stopAll();q.chosen=letter;
  const isEval=sessMode==='eval',isOk=letter===q.correct;
  document.querySelectorAll('.opt').forEach(b=>b.disabled=true);
  if(isEval){document.getElementById('opt'+letter)?.classList.add('echosen');}
  else{
    document.getElementById('opt'+q.correct)?.classList.add('correct');
    if(!isOk)document.getElementById('opt'+letter)?.classList.add('wrong');
    const fb=document.getElementById('vfb');
    if(fb){fb.textContent=isOk?'✅ ¡Muy bien! ¡Correcto!':'❌ ¡Casi! Mirá la respuesta correcta.';fb.className='vfeedback '+(isOk?'fc':'fw')+' show';}
    if(q.explanation){const ex=document.getElementById('vex');if(ex){ex.textContent='💡 '+q.explanation;ex.className='vexpl show';}}
  }
  if(isOk)sessCorr++;else sessWrong++;
  updVProg();
  if(useTTS)speak(isOk?'¡Muy bien! ¡Correcto!':'Esa no era. '+(isEval?'':''+( q.explanation||'')));
  document.getElementById('vnext')?.classList.add('show');
}
function goNext(){sessIdx++;if(sessIdx>=battCnt){showReport();return;}updVProg();loadQ();}
function updVProg(){
  const pct=Math.round((sessIdx/battCnt)*100);
  const f=document.getElementById('vfill');if(f)f.style.width=pct+'%';
  const pt=document.getElementById('vptxt');if(pt)pt.textContent=`Pregunta ${Math.min(sessIdx+1,battCnt)} de ${battCnt}`;
  const pp=document.getElementById('vppct');if(pp)pp.textContent=pct+'%';
  document.getElementById('vsc').textContent=sessCorr;document.getElementById('vsw').textContent=sessWrong;
}

// ── REPORT ──
function showReport(){
  stopAll();showS('sRep');
  const tot=sessQs.filter(q=>q.chosen).length,corr=sessQs.filter(q=>q.chosen&&q.chosen===q.correct).length;
  const pct=tot?Math.round((corr/tot)*100):0;
  const stars=pct>=90?'⭐⭐⭐⭐⭐':pct>=75?'⭐⭐⭐⭐':pct>=60?'⭐⭐⭐':pct>=40?'⭐⭐':'⭐';
  const msgs=[[90,'¡Excelente! 🎉 ¡Campeón!'],[75,'¡Muy bien! 💪'],[60,'¡Bien! 😊'],[40,'¡Buen intento! Repasá.'],[0,'¡No te rindas! 💡']];
  const msg=msgs.find(([t])=>pct>=t)[1];
  document.getElementById('repHero').innerHTML=`<div style="font-size:.66rem;font-weight:800;color:rgba(255,255,255,.28);text-transform:uppercase;letter-spacing:1px;margin-bottom:9px">${sessIsAuto?'🎲 Batería aleatoria':sessMode==='eval'?'📋 Evaluación':'💪 Práctica'}</div><div class="rep-big">${corr}<span style="font-size:1.4rem;color:rgba(255,255,255,.3)"> / ${tot}</span></div><div class="rep-pct">${pct}% correctas</div><div class="rep-stars">${stars}</div><div class="rep-msg">${msg}</div>`;
  document.getElementById('repList').innerHTML=sessQs.map((q,i)=>{const ok=q.chosen===q.correct;return`<div class="ri ${ok?'ok':'ko'}"><div class="ri-top"><span class="ri-n">P${i+1}</span><span class="ri-q">${esc(q.question)}</span><span>${ok?'✅':'❌'}</span></div><div class="ri-a ${ok?'ri-aok':'ri-ako'}">${q.chosen?esc(q.chosen+') '+q.opts[q.chosen]):'—'}</div>${!ok&&q.correct?`<div class="ri-a ri-aok">Correcta: ${esc(q.correct+') '+q.opts[q.correct])}</div>`:''} ${q.explanation?`<div class="ri-ex">💡 ${esc(q.explanation)}</div>`:''}</div>`;}).join('');
  saveResult({date:new Date().toISOString(),mode:sessIsAuto?'auto':sessMode,isAuto:sessIsAuto,total:tot,correct:corr,pct,stars,battCnt,battDiff,subjsUsed:[...new Set(sessQs.map(q=>q.subjLabel))],questions:sessQs.map(q=>({obj:q.objText,q:q.question,chosen:q.chosen,correct:q.correct,ok:q.chosen===q.correct}))});
  if(useTTS)speak(pct>=60?'¡Muy bien! Terminaste.':'Terminaste. ¡Seguí practicando!');
}

// ── HISTORY ──
const HK='profebot_hist_v3';
function loadH(){try{return JSON.parse(localStorage.getItem(HK)||'[]');}catch{return[];}}
function saveResult(r){const h=loadH();h.unshift(r);if(h.length>60)h.splice(60);localStorage.setItem(HK,JSON.stringify(h));}
function clearHist(){if(confirm('¿Borrar historial?')){localStorage.removeItem(HK);renderHist();}}
function renderHist(){
  const h=loadH();const el=document.getElementById('histContent');
  if(!h.length){el.innerHTML='<div class="hempty">📭 Sin sesiones aún.</div>';return;}
  const mi={study:'💪',eval:'📋',auto:'🎲'},mc={study:'var(--green)',eval:'var(--blue)',auto:'var(--orange)'};
  el.innerHTML=`<div class="hlist">${h.map(s=>{const d=new Date(s.date);const ds=d.toLocaleDateString('es',{day:'2-digit',month:'2-digit',year:'2-digit'})+' '+d.toLocaleTimeString('es',{hour:'2-digit',minute:'2-digit'});const col=mc[s.mode]||'var(--blue)';return`<div class="hitem"><div class="hitem-top"><div class="hico" style="background:${col}33;color:${col}">${mi[s.mode]||'📋'}</div><div class="hinfo"><h4>${ds}</h4><span>${(s.subjsUsed||[]).join(' + ')||'—'} · ${s.battCnt||s.total} pregs</span></div><div class="hsc"><div class="hpct" style="color:${col}">${s.pct}%</div><div>${s.stars}</div></div></div><div class="hbar"><div class="hfill" style="width:${s.pct}%;background:${col}"></div></div></div>`;}).join('')}</div>`;
}

function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// ── INIT ──
(function(){
  CUR[_subj].units.forEach((u,ui)=>u.objs.forEach((_,oi)=>selObjs.add(`${_subj}::${ui}::${oi}`)));
  renderUnits();updSel();initApiUI();loadDefaultMaterials();
  if(!SR){useSR=false;document.getElementById('srToggle').classList.remove('on');checkSR();}
})();
</script>
</body>
</html>
<?php
// ── FIN DEL ARCHIVO ──
