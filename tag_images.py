"""
Auto-tag extracted images via Gemini Vision (gemini-2.5-flash).
Reads each assets/img/{slug}/_pages.json, calls Gemini per image,
writes a merged assets/img/_index.json with description + tags + useful flag.

Run: python tag_images.py [--limit N] [--slug SLUG]
Requires GEMINI_API_KEY env var (or in repo .env).
"""

import argparse
import base64
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

REPO = Path(__file__).resolve().parent
IMG_ROOT = REPO / "assets" / "img"
INDEX_FILE = IMG_ROOT / "_index.json"
ENV_FILE = REPO / ".env"

MODEL = "gemini-2.5-flash"
ENDPOINT = f"https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent"

PROMPT = """Analizá esta imagen extraída de un libro infantil cubano (preescolar 5-6 años).

Devolvé SOLO un JSON válido (sin markdown, sin texto extra) con este shape:
{
  "useful": true|false,
  "description": "una oración corta en español describiendo lo que se ve",
  "tags": ["palabra1", "palabra2", ...]
}

Reglas:
- useful=false si la imagen es solo texto, página en blanco, portada, índice, o no tiene ilustración significativa.
- useful=true si tiene dibujos de personajes, animales, objetos, escenas, figuras geométricas, conjuntos contables.
- description: 1 oración, máx 20 palabras, en español, concreta. Mencionar colores si son visibles. Ej: "Un perro marrón corriendo detrás de una pelota roja en un parque."
- tags: 3-8 palabras clave en español (sustantivos, colores, acciones). Ej: ["perro", "pelota", "marrón", "rojo", "correr"]
"""


def load_env():
    if not ENV_FILE.exists():
        return
    for line in ENV_FILE.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        os.environ.setdefault(k.strip(), v.strip().strip('"').strip("'"))


def gemini_tag(api_key: str, png_bytes: bytes) -> dict:
    b64 = base64.b64encode(png_bytes).decode("ascii")
    body = {
        "contents": [{
            "parts": [
                {"text": PROMPT},
                {"inline_data": {"mime_type": "image/png", "data": b64}},
            ]
        }],
        "generationConfig": {"maxOutputTokens": 400, "temperature": 0.2},
    }
    req = urllib.request.Request(
        f"{ENDPOINT}?key={api_key}",
        data=json.dumps(body).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=60) as resp:
        raw = resp.read().decode("utf-8")
    d = json.loads(raw)
    text = d.get("candidates", [{}])[0].get("content", {}).get("parts", [{}])[0].get("text", "")
    # Strip code fences if Gemini wraps JSON
    text = re.sub(r"^```(?:json)?\s*|\s*```$", "", text.strip(), flags=re.MULTILINE).strip()
    try:
        parsed = json.loads(text)
    except json.JSONDecodeError:
        return {"useful": False, "description": "", "tags": [], "raw": text[:200]}
    return parsed


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--limit", type=int, default=0, help="Max images to tag (0 = all)")
    parser.add_argument("--slug", type=str, default="", help="Only this slug")
    parser.add_argument("--rate", type=float, default=1.1, help="Seconds between requests (free tier ~60/min)")
    args = parser.parse_args()

    load_env()
    api_key = os.environ.get("GEMINI_API_KEY", "").strip()
    if not api_key:
        print("ERROR: GEMINI_API_KEY missing (set env var or .env)")
        sys.exit(1)

    # Load existing index to support resuming.
    existing = {}
    if INDEX_FILE.exists():
        try:
            for entry in json.loads(INDEX_FILE.read_text(encoding="utf-8")):
                existing[entry["file"]] = entry
        except Exception:
            pass

    # Aggregate all _pages.json
    all_imgs: list[dict] = []
    for pages_json in sorted(IMG_ROOT.glob("*/_pages.json")):
        slug = pages_json.parent.name
        if args.slug and slug != args.slug:
            continue
        all_imgs.extend(json.loads(pages_json.read_text(encoding="utf-8")))

    todo = [img for img in all_imgs if img["file"] not in existing]
    print(f"Total imgs: {len(all_imgs)}  already tagged: {len(existing)}  to tag: {len(todo)}")
    if args.limit:
        todo = todo[: args.limit]
        print(f"Limited to: {len(todo)}")

    tagged = list(existing.values())
    for i, img in enumerate(todo, 1):
        img_path = REPO / img["file"]
        if not img_path.exists():
            print(f"[{i}/{len(todo)}] MISSING file {img['file']}")
            continue
        png_bytes = img_path.read_bytes()
        t = None
        for attempt in range(3):
            try:
                t = gemini_tag(api_key, png_bytes)
                break
            except urllib.error.HTTPError as e:
                code = e.code
                snippet = e.read()[:200].decode("utf-8", "replace")
                if code in (429, 500, 502, 503, 504) and attempt < 2:
                    backoff = 5 * (attempt + 1)
                    print(f"[{i}/{len(todo)}] retry HTTP {code}, sleeping {backoff}s")
                    time.sleep(backoff)
                    continue
                print(f"[{i}/{len(todo)}] HTTP {code} {img['file']}: {snippet}")
                break
            except Exception as e:
                print(f"[{i}/{len(todo)}] ERROR {img['file']}: {e}")
                time.sleep(2)
                break
        if t is None:
            continue
        merged = {
            **img,
            "useful": bool(t.get("useful", False)),
            "description": (t.get("description") or "")[:200],
            "tags": [str(x)[:30] for x in (t.get("tags") or [])][:10],
        }
        tagged.append(merged)
        flag = "OK " if merged["useful"] else "skip"
        print(f"[{i}/{len(todo)}] {flag} {img['file']} -> {merged['description'][:80]}")

        # Persist after each call so we never lose work.
        INDEX_FILE.write_text(json.dumps(tagged, ensure_ascii=False, indent=2), encoding="utf-8")
        time.sleep(args.rate)

    useful = sum(1 for x in tagged if x.get("useful"))
    print(f"\nDone. {useful}/{len(tagged)} useful images. Index: {INDEX_FILE}")


if __name__ == "__main__":
    main()
