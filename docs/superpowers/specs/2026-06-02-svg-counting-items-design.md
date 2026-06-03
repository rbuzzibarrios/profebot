# Feature B — Local SVG counting/attribute items (Prescolar Matemática)

**Date:** 2026-06-02
**Branch:** `feature/svg-counting-items` (from `develop`)
**Status:** approved design

## Problem

The AI generates questions that are impossible to answer by ear, e.g. *"¿Cuántos
objetos son redondos?"*, *"¿Cuántos globos son más grandes que la cometa?"*,
*"¿Cuántos objetos son rojos?"*. Children 5–7 **listen** (they don't read) and no
image is shown, so any "how many / which is bigger" question about unseen objects
is unanswerable. The existing image feature only offers vague decorative PDF
page-renders with no exact countable ground truth, so showing them doesn't help.

## Goal

For the visual Prescolar Matemática objectives, generate the question **and** a
matching picture **100% locally and deterministically**. The generator knows the
exact scene, so the correct answer is computed, never guessed — the child sees a
simple shape picture and hears an answerable question.

Scope of v1: **Prescolar → Nociones Matemáticas**, units *Conjuntos cualitativos*
and *Cantidades y comparación*. Other grades/subjects keep the existing AI flow.

## Key decisions

- **Generation:** 100% local, no AI, no cache (cheap to regenerate every load).
- **Item types:** counting + quantity comparison + attribute.
- **Visuals:** geometric SVG shapes (circle/square/triangle) in controlled
  colors and sizes — every attribute is parametric.
- **Answers:** text/number only. No "point at this shape" answers (unusable by
  audio + single image).

## Architecture

No backend / PHP / `parseQ` / cache changes.

The pure generator lives in a **new DOM-free file `visual-items.js`** rather than
inside `profebot.js`. Reason: `profebot.js` runs browser-only top-level code
(pdf.js worker config, DOM access), so it cannot be imported by Node's test
runner; a separate file with no DOM access keeps the generators importable and
independently testable, and gives a clean unit boundary. `visual-items.js` is
included **before** `profebot.js` in `profebot.html` (so its globals exist when
`loadQ`/`renderQ` call them) and is served as a static file by `router.php`
(`is_file` → served; same mechanism in production).

`visual-items.js` exports its functions to the browser global scope and, when
present, to CommonJS so tests can import them:
```js
if (typeof module !== 'undefined') module.exports = { makeScene, renderSceneSVG, generateVisualItem, OBJECTIVE_VISUAL_MAP /* …templates */ };
```

### Integration points (3 small edits to existing code)

0. **`profebot.html`** — add `<script src="visual-items.js"></script>` immediately
   before the existing `<script src="profebot.js"></script>` (line ~259).

1. **`loadQ()`** — at the top, after `pickObj()`/`getRealDiff()`:
   if the objective key is in `OBJECTIVE_VISUAL_MAP`, build the item locally and
   skip cache + AI entirely:
   ```
   if (OBJECTIVE_VISUAL_MAP[obj.k]) {
       const q = generateVisualItem(obj.k, realDiff);
       lastProvider = 'local';
       // …attach objText/subjLabel/color like the AI path, then renderQ + readQuestion
       return;  // (after the shared tail that pushes to sessQs/sessAsked)
   }
   ```
   The existing cache/AI flow is untouched for every other objective.

2. **`renderQ(q)`** (in `profebot.js`) — if `q.svg` is present, inject it inline
   instead of the `<img>` tag:
   ```
   const visual = q.svg ? `<div class="qsvg">${q.svg}</div>` : imgHtml;
   ```
   `q.svg` is a string we built ourselves (no external/user input), so it is safe
   to inline. Options, feedback, TTS, and the rest of `renderQ` are unchanged.

`lastProvider = 'local'` shows a "vía Actividad" badge (add `local` to `PROV_META`
with label "Actividad").

## Data model

```js
// A generated scene: a flat list of shapes.
Scene = {
  shapes: [ { kind: 'circle'|'square'|'triangle',
              color: 'rojo'|'azul'|'verde'|'amarillo',
              size: 'grande'|'pequeño' } ]
}
```

## Components (pure, independently testable)

| Function | Input → Output | Responsibility |
|----------|----------------|----------------|
| `makeScene(spec)` | spec → `Scene` | Build a random scene honoring the template's constraints (counts, allowed colors/shapes/sizes). |
| `renderSceneSVG(scene)` | `Scene` → SVG string | Lay shapes out in a wrapped grid with a fixed hex palette and two pixel sizes. Purely presentational. |
| template fns | `Scene` → `{question, opts, correct, explanation}` | One per item type. Compute the truth from the scene. |
| `OBJECTIVE_VISUAL_MAP` | — | `obj.k` → array of eligible template ids. |
| `generateVisualItem(objKey, diff)` | → full question object incl. `svg` | Pick a template for the objective + difficulty, make a scene, render SVG, assemble options. |

### Templates (v1) — text/number answers only

- `countAll` — "¿Cuántas figuras hay?" (numeric)
- `countByColor` — "¿Cuántos círculos rojos hay?" / "¿Cuántas figuras rojas hay?" (numeric)
- `countByShape` — "¿Cuántos cuadrados hay?" (numeric)
- `countNotX` — "¿Cuántas figuras NO son círculos?" (numeric; covers *elemento que sobra*)
- `compareQty` — "¿Hay más rojos o más azules?" → opts: *más rojos / más azules / iguales*
- `commonAttr` — monochrome or mono-shape scene → "¿De qué color son todas?" /
  "¿Qué forma son todas?" (covers *característica común*)
- `compareSize` — "¿Hay más figuras grandes o pequeñas?" → opts: *grandes / pequeñas / iguales*

### Objective → template map (v1)

- *Agrupar por color / forma / tamaño*, *característica común* → `commonAttr`,
  `compareSize`, `countByColor`, `countByShape`
- *Elemento que sobra* → `countNotX`
- *Contar hasta 5 / hasta 10* → `countAll`, `countByColor`
- *Más que / menos que*, *igual cantidad* → `compareQty`

(Exact `obj.k` keys are resolved from the `GRADES.preesc.subjects.mat` units during
implementation.)

## Options & answer

- **Counting:** 3 numeric options = correct + 2 plausible neighbors, mapped to
  A/B/C. Distractors are distinct, in range, and never negative.
- **Comparison / attribute:** 2–3 text options.
- The correct letter is computed from the scene → **always correct**. These items
  bypass `pb_valid_question` (they are not cached and not AI-sourced).

## Difficulty (reuses existing `battDiff` / `getRealDiff()`)

- **fácil:** total ≤ 5 shapes, far distractors.
- **media:** total ≤ 8.
- **difícil:** total ≤ 10, near distractors (±1).

## TTS

The question text contains no slashes or visual-dependent phrasing; the child sees
the SVG and hears e.g. "¿Cuántos círculos rojos hay?". Fully answerable. Reuses the
existing `speak()` / `readOptions()` path (incl. the `ttsClean()` sanitizer once
that bugfix lands).

## Testing

The load-bearing invariant is **answer == truth**. There is no JS test runner in
the repo, so add tests with Node's built-in `node --test`:

- `tests/visual-items.test.mjs` — for each template, generate N random scenes
  (e.g. 200) and assert `opts[correct]` equals the value recomputed independently
  from the scene; assert option counts/letters are well-formed; assert
  `compareQty`/`compareSize` pick the right relation incl. the "iguales" tie case;
  assert `commonAttr` only fires on uniform scenes.
- The test imports `visual-items.js` directly via its CommonJS export (no browser
  globals needed, since that file has no DOM access).
- Add a `node` job to `.github/workflows/ci.yml` (alongside `phpunit`) running
  `node --test`.

## Out of scope (YAGNI)

Longitudes unit, 1er grado, emoji/pictogram rendering, "point at the shape"
answers, persistence/caching of generated items, difficulty beyond count ranges.

## Risk / notes

- **Load order:** `visual-items.js` must be included before `profebot.js` in
  `profebot.html`, so its globals exist when `loadQ`/`renderQ` run.
- **Production static serving:** `router.php` serves `is_file` paths, so the new
  `visual-items.js` is served with no extra wiring (same mechanism on Render).
- **No DOM in the pure module:** keep `visual-items.js` free of any DOM/browser
  access (DOM only in the `renderQ` integration inside `profebot.js`), so the Node
  test can import it.
