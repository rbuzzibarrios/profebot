# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ProfeBot is a voice-interactive AI tutoring app for 1st-grade Cuban curriculum students (ages 6-7). It generates multiple-choice questions using AI (Groq/Gemini with automatic fallback), reads them aloud via Web Speech API, and accepts voice/touch answers. Subjects: Matemática (7 units, 44 objectives) and Lengua Española (4 units, 35 objectives).

## Running Locally

Always run with `router.php` as the second arg — it handles `/`, blocks private paths, and serves static assets. Without it, the root URL returns 404.

```bash
# Option 1: API keys via environment variables (bash / git bash)
export GROQ_API_KEY=gsk_...
export GEMINI_API_KEY=AIza...
php -S localhost:8080 router.php

# Option 2: Enter API keys in the UI provider config at runtime
php -S localhost:8080 router.php
```

PowerShell equivalent for env vars:
```powershell
$env:GROQ_API_KEY="gsk_..."; $env:GEMINI_API_KEY="AIza..."; php -S localhost:8080 router.php
```

Open `http://localhost:8080/` in browser.

### Debug simulation (force error UI)

Set `PROFEBOT_DEBUG=1` in the server env, then in the browser DevTools console:

```js
window.__pb_simulate = 'ai_busy_no_cache'   // forces 502 + friendly warning UI
window.__pb_simulate = 'ai_busy_with_cache' // forces cache fallback path
window.__pb_simulate = null                  // disable
```

Bash one-liner: `PROFEBOT_DEBUG=1 php -S localhost:8080 router.php`. The flag is ignored unless that env var is set, so it's safe to leave the JS hook in place.

Requirements: PHP 7.0+ with cURL extension, modern browser with Web Speech API support.

## Architecture

**Single-file monolith** — `profebot.php` contains everything:

- **PHP backend (top):** Multi-provider API proxy with automatic fallback. Supports Ollama Cloud, OpenRouter, Groq, Gemini, and Claude. Reads provider keys from POST body or env vars (`OLLAMA_API_KEY`, `OPENROUTER_API_KEY`, `GROQ_API_KEY`, `GEMINI_API_KEY`, `ANTHROPIC_API_KEY`). Tries providers in client-specified order; falls back on 429/402/403/500/502/503 or cURL errors; stops on 400/401.
- **HTML (middle):** 5 screen states — s0 (setup), sMat (materials), sVoice (learning), sRep (report), sHist (history).
- **CSS:** Custom variables, mobile-first (max-width 640px), child-friendly design with Google Fonts (Nunito, Baloo 2).
- **JavaScript (bottom):** All app logic including API calls, question parsing, speech synthesis/recognition, session management.

`profebot.html` is an earlier/alternative version.

## Multi-Provider System

**Adding a new provider** requires 3 changes:
1. PHP: Add entry to `$PROVIDERS` array (url, build, parse functions)
2. JS: Add entry to `PROV_META` object (label, prefix, order)
3. HTML: Add a `prov-row` block in the setup screen

**Fallback logic** (PHP):
- 429, 402, 403, 500, 502, 503 → try next provider
- 400, 401 → stop (config problem)
- cURL error → try next provider
- All fail → return combined error message

**On Render** (production): env vars are set in the dashboard, UI auto-hides config and shows "Servidor configurado."

## Key JS Functions

| Function | Purpose |
|----------|---------|
| `callAPI(sys, userMsg)` | POST to PHP proxy with `providers` map and `provider_order` |
| `parseQ(txt)` | Parse AI response into `{question, opts, correct, explanation}` |
| `loadQ()` / `renderQ(q)` | Generate and display questions (shows "vía Provider" badge) |
| `chooseAns(letter)` | Handle A/B/C/D selection |
| `speak(txt, onEnd)` | TTS with Spanish voice fallback chain |
| `startListening()` | Speech recognition for letter answers |
| `buildCtx()` | Concatenate uploaded material context |
| `getSys(obj)` / `getUMsg(...)` | Build system/user prompts |
| `getProviderKeys()` / `setProviderKeys()` | Read/write `localStorage.profebot_providers` |
| `saveProv(pid)` / `toggleProv(pid)` / `refreshProvUI()` | Provider config UI management |
| `migrateOldKey()` | Auto-migrates old `profebot_apikey` → new multi-provider format |

## AI Question Format

The AI must respond in this exact format (parsed by regex in `parseQ`):

```
PREGUNTA: ...
A) ...
B) ...
C) ...
D) ...
CORRECTA: A|B|C|D
EXPLICACION: ...
```

## Data Storage

- `localStorage.profebot_providers` — Provider API keys `{groq:"...", gemini:"..."}`
- `localStorage.profebot_hist_v3` — Session history (max 60 sessions)

### Question cache backend

Two backends, picked at runtime in `cache_backend()`:

1. **Upstash Redis** (production) — used when `UPSTASH_REDIS_REST_URL` and `UPSTASH_REDIS_REST_TOKEN` env vars are set. Persistent across deploys/restarts. Schema: `profebot:cache:{cacheKey}` → JSON array of questions (max 50 per key, FIFO). Widening fallback uses `SCAN` + pipelined `GET`.
2. **Local JSON file** (`question_cache.json`) — used otherwise. Render free filesystem is ephemeral, so the file is only useful for dev.

API surface (`profebot.php`):

- `cache_get_key($key)` → `array`
- `cache_set_key($key, $items)` → `bool`
- `cache_scan_prefix($prefix)` → `[key => items[]]` (used by progressive widening when all providers fail)

HTTP client: Guzzle 7 (declared in `composer.json`).

## External Dependencies

- Ollama Cloud (`gpt-oss:20b` model, native `/api/chat`) — primary provider; hosted open models via API key (free tier, light-usage quota). `think:false` keeps reasoning models from breaking the 7-line format. Swap model tag in `$PROVIDERS['ollama']['build']` (catalog at `https://ollama.com/search?c=cloud`, use base tag without `-cloud`).
- OpenRouter API (OpenAI-compatible) — fallback provider; sends a `models` array of free models (qwen3-next-80b, deepseek-v4-flash, llama-3.3-70b) for native cross-model failover when one is rate-limited. Free models bypass Gemini's datacenter-IP geo-block. Edit the list in `$PROVIDERS['openrouter']['build']` (live free list at `https://openrouter.ai/api/v1/models`).
- Groq API (`llama-3.3-70b-versatile` model) — fallback provider
- Gemini API (`gemini-2.5-flash` model) — fallback provider (geo-blocked from datacenter IPs like Render; direct calls fail with HTTP 400 "User location is not supported")
- Upstash Redis (REST API) — persistent question cache (optional, env-gated)
- Guzzle HTTP 7 — HTTP client for Upstash REST calls
- pdf.js v3.11.174 (CDN) — PDF text extraction, limited to 12,000 chars
- Google Fonts (CDN)

## Language

All UI text, prompts, and curriculum content are in **Spanish**. Code comments and variable names are in English/Spanish mix.
