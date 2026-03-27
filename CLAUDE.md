# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ProfeBot is a voice-interactive AI tutoring app for 1st-grade Cuban curriculum students (ages 6-7). It generates multiple-choice questions using Claude API, reads them aloud via Web Speech API, and accepts voice/touch answers. Subjects: Matemática (7 units, 44 objectives) and Lengua Española (4 units, 35 objectives).

## Running Locally

```bash
# Option 1: API key via environment variable
export ANTHROPIC_API_KEY=sk-ant-...
php -S localhost:8080

# Option 2: Enter API key in the UI field at runtime
php -S localhost:8080
```

Open `http://localhost:8080/profebot.php` in browser.

Requirements: PHP 7.0+ with cURL extension, modern browser with Web Speech API support.

## Architecture

**Single-file monolith** — `profebot.php` (~790 lines) contains everything:

- **PHP backend (top):** API proxy that forwards requests to `https://api.anthropic.com/v1/messages`. Handles CORS headers, validates API key from `X-API-KEY` header or `ANTHROPIC_API_KEY` env var.
- **HTML (middle):** 5 screen states — s0 (setup), sMat (materials), sVoice (learning), sRep (report), sHist (history).
- **CSS:** Custom variables, mobile-first (max-width 640px), child-friendly design with Google Fonts (Nunito, Baloo 2).
- **JavaScript (bottom):** All app logic including API calls, question parsing, speech synthesis/recognition, session management.

`profebot.html` is an earlier/alternative version.

## Key JS Functions

| Function | Purpose |
|----------|---------|
| `callAPI(sys, userMsg)` | POST to PHP proxy → Claude API |
| `parseQ(txt)` | Parse AI response into `{question, opts, correct, explanation}` |
| `loadQ()` / `renderQ(q)` | Generate and display questions |
| `chooseAns(letter)` | Handle A/B/C/D selection |
| `speak(txt, onEnd)` | TTS with Spanish voice fallback chain |
| `startListening()` | Speech recognition for letter answers |
| `buildCtx()` | Concatenate uploaded material context |
| `getSys(obj)` / `getUMsg(...)` | Build Claude system/user prompts |

## AI Question Format

Claude must respond in this exact format (parsed by regex in `parseQ`):

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

- `localStorage.profebot_apikey` — Anthropic API key
- `localStorage.profebot_hist_v3` — Session history (max 60 sessions)

## External Dependencies

- Claude API (`claude-sonnet-4-20250514` model)
- pdf.js v3.11.174 (CDN) — PDF text extraction, limited to 12,000 chars
- All Origins API — web content proxy for URL materials, limited to 12,000 chars
- Google Fonts (CDN)

## Language

All UI text, prompts, and curriculum content are in **Spanish**. Code comments and variable names are in English/Spanish mix.
