"""
Extract images from preesc PDFs into assets/img/{slug}/p{page}_{i}.{ext}
plus a per-PDF _pages.json with page text snippets for context.

Run from repo root: python extract_preesc_images.py
"""

import hashlib
import io
import json
import re
from pathlib import Path

import fitz  # PyMuPDF

REPO = Path(__file__).resolve().parent
OUT_ROOT = REPO / "assets" / "img"

# (pdf relative path, slug, subj, units)
PDFS = [
    ("cubaeduca_primera_infancia/5toanno de vida/Educ y desarrollo estético/LITERATURA INFANTIL/1211_Cuentos para el 5to año de vida.pdf",
     "cuentos_5to", "len", [1]),
    ("cubaeduca_primera_infancia/5toanno de vida/Educ y desarrollo estético/LITERATURA INFANTIL/1212_Poesías para el 5to año de vida.pdf",
     "poesias_5to", "len", [3]),
    ("cubaeduca_primera_infancia/5toanno de vida/Educ y desarrollo estético/LITERATURA INFANTIL/1213_Adivinanzas para 5to  y 6to año de vida.pdf",
     "adivinanzas", "len", [4]),
    ("cubaeduca_primera_infancia/5toanno de vida/Educ y desarrollo estético/LITERATURA INFANTIL/1214_Fábulas para el 5to año de vida.pdf",
     "fabulas_5to", "len", [2]),
    ("cubaeduca_primera_infancia/5toanno de vida/Educ y desarrollo de la relación con el entorno/NOCIONES ELEMENTALES DE MATEMÁTICA/2455_Las Nociones Elementales de Matemática. Trabajo con conjuntos.pdf",
     "mat_conjuntos", "mat", [0, 1, 2]),
    ("cubaeduca_primera_infancia_sexto/Educ y desarrollo estético/LITERATURA INFANTIL/1244_Cuentos para el 6to año de vida.pdf",
     "cuentos_6to", "len", [1]),
    ("cubaeduca_primera_infancia_sexto/Educ y desarrollo estético/LITERATURA INFANTIL/1245_Poesías para el 6to año de vida.pdf",
     "poesias_6to", "len", [3]),
    ("cubaeduca_primera_infancia_sexto/Educ y desarrollo estético/LITERATURA INFANTIL/1246_Fábulas para el 6to año de vida.pdf",
     "fabulas_6to", "len", [2]),
    ("cubaeduca_primera_infancia_sexto/Educ y desarrollo estético/LITERATURA INFANTIL/1248_Trabalenguas para el 6to año de vida.pdf",
     "trabalenguas", "len", [4]),
    ("cubaeduca_primera_infancia_sexto/Educ y desarrollo estético/LITERATURA INFANTIL/1249_Adivinanzas para 5to y 6to año de vida.pdf",
     "adivinanzas_5y6", "len", [4]),
    ("cubaeduca_primera_infancia_sexto/Educ y desarrollo de la comunicación/ANÁLISIS FÓNICO/1251_Cuaderno de Análisis Fónico.pdf",
     "fonico", "len", [0]),
    ("cubaeduca_primera_infancia_sexto/Educ y desarrollo de la relación con el entorno/NOCIONES ELEMENTALES DE MATEMÁTICA/1252_La solución de problemas sencillos en edades preescolares.pdf",
     "mat_problemas", "mat", [4]),
    ("cubaeduca_primera_infancia_sexto/Educ y desarrollo de la relación con el entorno/NOCIONES ELEMENTALES DE MATEMÁTICA/1410_Operaciones combinadas de conjuntos. Una alternativa para elevar el nivel de  co.pdf",
     "mat_operaciones", "mat", [4]),
    ("cubaeduca_primera_infancia_sexto/Educ y desarrollo de la relación con el entorno/NOCIONES ELEMENTALES DE MATEMÁTICA/1660_Aspectos metodológicos para la planificación de actividades del trabajo con long.pdf",
     "mat_longitudes", "mat", [3]),
]

# Reject tiny images (logos, decorative dots) and giant ones (page backgrounds).
MIN_W, MIN_H = 80, 80
MAX_W, MAX_H = 2000, 2400

# Truncate near-page text to keep _pages.json small.
NEAR_TEXT_LIMIT = 400


def slugify(s: str) -> str:
    s = s.lower()
    s = re.sub(r"[^a-z0-9]+", "-", s).strip("-")
    return s[:60]


def extract_pdf(pdf_path: Path, slug: str, subj: str, units: list) -> dict:
    doc = fitz.open(pdf_path)
    out_dir = OUT_ROOT / slug
    out_dir.mkdir(parents=True, exist_ok=True)

    seen_hashes: dict[str, str] = {}  # hash → first filename (dedupe)
    images: list[dict] = []
    raster_count = 0

    for page_idx in range(len(doc)):
        page = doc[page_idx]
        page_text = page.get_text("text") or ""
        near_text = re.sub(r"\s+", " ", page_text).strip()[:NEAR_TEXT_LIMIT]

        for img_i, info in enumerate(page.get_images(full=True)):
            xref = info[0]
            try:
                pix = fitz.Pixmap(doc, xref)
            except Exception:
                continue

            w, h = pix.width, pix.height
            if w < MIN_W or h < MIN_H or w > MAX_W or h > MAX_H:
                pix = None
                continue

            # CMYK / alpha → convert to RGB
            if pix.n - pix.alpha >= 4:
                pix = fitz.Pixmap(fitz.csRGB, pix)

            png_bytes = pix.tobytes("png")
            digest = hashlib.sha1(png_bytes).hexdigest()
            if digest in seen_hashes:
                pix = None
                continue
            seen_hashes[digest] = ""

            fname = f"p{page_idx + 1:03d}_{img_i + 1}.png"
            fpath = out_dir / fname
            fpath.write_bytes(png_bytes)
            pix = None
            raster_count += 1

            images.append({
                "file": f"assets/img/{slug}/{fname}",
                "page": page_idx + 1,
                "width": w,
                "height": h,
                "near_text": near_text,
                "subj": subj,
                "units": units,
                "source_pdf": pdf_path.name,
                "kind": "raster",
            })

    # Fallback: PDF has no embedded rasters (pure-text + vector illustrations).
    # Render each page as PNG so the tagger can decide if it has illustration.
    if raster_count == 0:
        for page_idx in range(len(doc)):
            page = doc[page_idx]
            page_text = page.get_text("text") or ""
            near_text = re.sub(r"\s+", " ", page_text).strip()[:NEAR_TEXT_LIMIT]
            pix = page.get_pixmap(dpi=120)
            png_bytes = pix.tobytes("png")
            digest = hashlib.sha1(png_bytes).hexdigest()
            if digest in seen_hashes:
                continue
            seen_hashes[digest] = ""
            fname = f"page{page_idx + 1:03d}.png"
            (out_dir / fname).write_bytes(png_bytes)
            images.append({
                "file": f"assets/img/{slug}/{fname}",
                "page": page_idx + 1,
                "width": pix.width,
                "height": pix.height,
                "near_text": near_text,
                "subj": subj,
                "units": units,
                "source_pdf": pdf_path.name,
                "kind": "page_render",
            })

    doc.close()

    pages_json = out_dir / "_pages.json"
    pages_json.write_text(json.dumps(images, ensure_ascii=False, indent=2), encoding="utf-8")

    return {"slug": slug, "count": len(images), "out_dir": str(out_dir)}


def main():
    OUT_ROOT.mkdir(parents=True, exist_ok=True)
    summary = []
    for rel, slug, subj, units in PDFS:
        pdf = REPO / rel
        if not pdf.exists():
            print(f"MISSING: {rel}")
            continue
        try:
            r = extract_pdf(pdf, slug, subj, units)
            print(f"OK  {slug:20s} -> {r['count']:4d} imgs")
            summary.append(r)
        except Exception as e:
            print(f"FAIL {slug}: {e}")
    total = sum(s["count"] for s in summary)
    print(f"\nTotal: {total} images across {len(summary)} PDFs")
    print(f"Output root: {OUT_ROOT}")


if __name__ == "__main__":
    main()
