# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ProfeBot is a voice-interactive AI tutoring app for 1st-grade Cuban curriculum students (ages 6-7). It generates multiple-choice questions using AI (Groq/Gemini with automatic fallback), reads them aloud via Web Speech API, and accepts voice/touch answers. Subjects: Matemática (7 units, 44 objectives) and Lengua Española (4 units, 35 objectives).

## Running Locally

```bash
# Option 1: API keys via environment variables
export GROQ_API_KEY=gsk_...
export GEMINI_API_KEY=AIza...
php -S localhost:8080

# Option 2: Enter API keys in the UI provider config at runtime
php -S localhost:8080
```

Open `http://localhost:8080/profebot.php` in browser.

Requirements: PHP 7.0+ with cURL extension, modern browser with Web Speech API support.

## Architecture

**Single-file monolith** — `profebot.php` contains everything:

- **PHP backend (top):** Multi-provider API proxy with automatic fallback. Supports Groq and Gemini. Reads provider keys from POST body or env vars (`GROQ_API_KEY`, `GEMINI_API_KEY`). Tries providers in client-specified order; falls back on 429/402/403/500/502/503 or cURL errors; stops on 400/401.
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

## External Dependencies

- Groq API (`llama-3.3-70b-versatile` model) — primary provider
- Gemini API (`gemini-2.5-flash` model) — fallback provider
- pdf.js v3.11.174 (CDN) — PDF text extraction, limited to 12,000 chars
- All Origins API — web content proxy for URL materials, limited to 12,000 chars
- Google Fonts (CDN)

## Language

All UI text, prompts, and curriculum content are in **Spanish**. Code comments and variable names are in English/Spanish mix.
