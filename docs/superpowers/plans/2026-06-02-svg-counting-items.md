# Local SVG Counting Items Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate preescolar Matemática counting/attribute questions 100% locally with a matching SVG picture, so the answer is always correct and never depends on an unseen image.

**Architecture:** A new DOM-free module `visual-items.js` holds pure generator functions (scene → SVG + question + computed answer). It is loaded before `profebot.js`, which gains a small branch in `loadQ()` (route eligible objectives to the local generator, skip cache/AI) and a one-line change in `renderQ()` (inline the SVG). No PHP/backend/cache changes. Pure functions are unit-tested with Node's built-in test runner.

**Tech Stack:** Vanilla JS (ES5-compatible IIFE module, matches repo style), inline SVG, `node --test` (Node 18+), GitHub Actions.

**Working directory:** worktree `D:/Descargas/profebot/.claude/worktrees/svg-counting`, branch `feature/svg-counting-items`. All paths below are relative to that worktree root.

---

## File Structure

- **Create `visual-items.js`** (repo root) — pure generator: constants, RNG helpers, `makeScene`, `renderSceneSVG`, 7 template functions, `templatesFor`, `generateVisualItem`, CommonJS + global export. No DOM access.
- **Create `tests/visual-items.test.mjs`** — `node --test` suite asserting *answer == truth* over many random runs.
- **Create `.github/workflows/node.yml`** — CI job running `node --test`.
- **Modify `profebot.html`** — add `<script src="visual-items.js">` before `profebot.js`.
- **Modify `profebot.js`** — `loadQ()` routing branch, `renderQ()` SVG injection, `PROV_META.local`.
- **Modify `profebot.css`** — `.qsvg` layout rule.

Confirmed objective strings (preescolar → `mat`, from `GRADES.preesc.subjects.mat`):
unit 0 *Conjuntos cualitativos*: "Agrupar objetos por su color/forma/tamaño", "Reconocer la característica común de un conjunto", "Identificar el elemento que sobra en un conjunto".
unit 2 *Cantidades y comparación*: "Comparar cantidades: más que / menos que", "Reconocer conjuntos con igual cantidad", "Contar objetos hasta 5", "Contar objetos hasta 10".

---

## Task 1: Module scaffold — constants, RNG helpers, `makeScene`

**Files:**
- Create: `visual-items.js`
- Test: `tests/visual-items.test.mjs`

- [ ] **Step 1: Write the failing test**

```js
// tests/visual-items.test.mjs
import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const VI = require('../visual-items.js');

test('makeScene returns the requested number of valid shapes', () => {
  for (let i = 0; i < 200; i++) {
    const n = 1 + Math.floor(Math.random() * 10);
    const scene = VI.makeScene(n);
    assert.equal(scene.shapes.length, n);
    for (const s of scene.shapes) {
      assert.ok(['circle', 'square', 'triangle'].includes(s.kind));
      assert.ok(['rojo', 'azul', 'verde', 'amarillo'].includes(s.color));
      assert.ok(['grande', 'pequeño'].includes(s.size));
    }
  }
});

test('makeScene honors pool constraints', () => {
  const scene = VI.makeScene(8, { colors: ['rojo'], shapes: ['circle'] });
  assert.ok(scene.shapes.every(s => s.color === 'rojo' && s.kind === 'circle'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node --test tests/visual-items.test.mjs`
Expected: FAIL — `Cannot find module '../visual-items.js'`.

- [ ] **Step 3: Create `visual-items.js` with constants, helpers, `makeScene`, and the export shell**

```js
// visual-items.js — pure, DOM-free generator of local counting/attribute items
// for preescolar Matemática. No browser globals; safe to require() under Node.
(function (root) {
  'use strict';

  var COLOR_HEX = { rojo: '#e74c3c', azul: '#3498db', verde: '#2ecc71', amarillo: '#f1c40f' };
  var COLORS = Object.keys(COLOR_HEX);
  var COLOR_ADJ_FEM = { rojo: 'rojas', azul: 'azules', verde: 'verdes', amarillo: 'amarillas' };
  var SHAPE_SINGULAR = { circle: 'círculo', square: 'cuadrado', triangle: 'triángulo' };
  var SHAPE_PLURAL = { circle: 'círculos', square: 'cuadrados', triangle: 'triángulos' };
  var SHAPES = Object.keys(SHAPE_SINGULAR);
  var SIZE_PX = { grande: 54, 'pequeño': 30 };

  function randInt(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }
  function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
  function shuffle(arr) {
    var a = arr.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = a[i]; a[i] = a[j]; a[j] = t;
    }
    return a;
  }
  function capit(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
  function maxTotalFor(diff) { return diff === 'fácil' ? 5 : diff === 'difícil' ? 10 : 8; }

  // Build `count` shapes, drawing each independently from the allowed pools.
  function makeScene(count, opts) {
    opts = opts || {};
    var colors = opts.colors || COLORS;
    var shapes = opts.shapes || SHAPES;
    var sizes = opts.sizes || ['grande', 'pequeño'];
    var list = [];
    for (var i = 0; i < count; i++) {
      list.push({ kind: pick(shapes), color: pick(colors), size: pick(sizes) });
    }
    return { shapes: list };
  }

  function count(scene, pred) { return scene.shapes.filter(pred).length; }

  var API = {
    makeScene: makeScene,
    // (renderSceneSVG, generateVisualItem, templatesFor added in later tasks)
    _internals: { count: count, randInt: randInt, pick: pick, shuffle: shuffle, capit: capit, maxTotalFor: maxTotalFor,
      COLORS: COLORS, COLOR_ADJ_FEM: COLOR_ADJ_FEM, SHAPES: SHAPES, SHAPE_SINGULAR: SHAPE_SINGULAR, SHAPE_PLURAL: SHAPE_PLURAL }
  };
  if (typeof module !== 'undefined' && module.exports) { module.exports = API; }
  root.VisualItems = API;
})(typeof window !== 'undefined' ? window : this);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `node --test tests/visual-items.test.mjs`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting add visual-items.js tests/visual-items.test.mjs
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting commit -m "feat: scaffold visual-items module with makeScene + RNG helpers

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: `renderSceneSVG`

**Files:**
- Modify: `visual-items.js`
- Test: `tests/visual-items.test.mjs`

- [ ] **Step 1: Write the failing test**

```js
test('renderSceneSVG returns an <svg> string with one node per shape', () => {
  const scene = VI.makeScene(6);
  const svg = VI.renderSceneSVG(scene);
  assert.match(svg, /^<svg /);
  assert.match(svg, /<\/svg>$/);
  const nodes = (svg.match(/<(circle|rect|polygon)\b/g) || []).length;
  assert.equal(nodes, 6);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node --test tests/visual-items.test.mjs`
Expected: FAIL — `VI.renderSceneSVG is not a function`.

- [ ] **Step 3: Add `renderSceneSVG` and export it**

Insert these two functions just above the `var API = {` line:

```js
  function shapeSVG(s, cx, cy) {
    var r = SIZE_PX[s.size] / 2, fill = COLOR_HEX[s.color];
    if (s.kind === 'circle') return '<circle cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="' + fill + '"/>';
    if (s.kind === 'square') return '<rect x="' + (cx - r) + '" y="' + (cy - r) + '" width="' + (2 * r) + '" height="' + (2 * r) + '" fill="' + fill + '"/>';
    var pts = [cx + ',' + (cy - r), (cx - r) + ',' + (cy + r), (cx + r) + ',' + (cy + r)].join(' ');
    return '<polygon points="' + pts + '" fill="' + fill + '"/>';
  }

  // Lay shapes in a wrapped grid. Purely presentational; aria-hidden because the
  // question is spoken and the picture is decorative support for it.
  function renderSceneSVG(scene) {
    var cell = 70, perRow = 5, n = scene.shapes.length;
    var cols = Math.min(n, perRow), rows = Math.ceil(n / perRow);
    var w = cols * cell, h = rows * cell, body = '';
    for (var i = 0; i < n; i++) {
      var cx = (i % perRow) * cell + cell / 2;
      var cy = Math.floor(i / perRow) * cell + cell / 2;
      body += shapeSVG(scene.shapes[i], cx, cy);
    }
    return '<svg viewBox="0 0 ' + w + ' ' + h + '" width="100%" role="img" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">' + body + '</svg>';
  }
```

Add `renderSceneSVG: renderSceneSVG,` to the `API` object (after `makeScene:`).

- [ ] **Step 4: Run test to verify it passes**

Run: `node --test tests/visual-items.test.mjs`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting add visual-items.js tests/visual-items.test.mjs
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting commit -m "feat: renderSceneSVG draws shapes in a grid

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: Counting templates (`countAll`, `countByColor`, `countByShape`, `countNotX`)

Each template returns `{ scene, question, options: string[], correctIndex, explanation }` and computes its answer from the scene it built. `numericOptions` returns 3 unique numeric option strings (shuffled) including the correct one.

**Files:**
- Modify: `visual-items.js`
- Test: `tests/visual-items.test.mjs`

- [ ] **Step 1: Write the failing test**

```js
// Helper: recompute truth independently and assert the template agrees.
function checkTemplate(tplName, recount) {
  for (const diff of ['fácil', 'media', 'difícil']) {
    for (let i = 0; i < 200; i++) {
      const r = VI.TEMPLATES[tplName](diff);
      assert.equal(r.options.length, 3, tplName + ' has 3 options');
      assert.ok(new Set(r.options).size === 3, tplName + ' options unique');
      assert.ok(r.correctIndex >= 0 && r.correctIndex < 3, tplName + ' correctIndex in range');
      const truth = String(recount(r.scene));
      assert.equal(r.options[r.correctIndex], truth, tplName + ' answer == truth');
    }
  }
}

test('countAll answer equals total shapes', () => {
  checkTemplate('countAll', scene => scene.shapes.length);
});
test('countByColor answer equals shapes of asked color', () => {
  for (const diff of ['fácil', 'media', 'difícil']) for (let i = 0; i < 200; i++) {
    const r = VI.TEMPLATES.countByColor(diff);
    const m = r.question.match(/figuras (\w+) hay/);
    const adj = m[1];
    const colorByAdj = { rojas: 'rojo', azules: 'azul', verdes: 'verde', amarillas: 'amarillo' };
    const truth = r.scene.shapes.filter(s => s.color === colorByAdj[adj]).length;
    assert.equal(r.options[r.correctIndex], String(truth));
  }
});
test('countByShape answer equals shapes of asked kind', () => {
  const plural = { 'círculos': 'circle', 'cuadrados': 'square', 'triángulos': 'triangle' };
  for (const diff of ['fácil', 'media', 'difícil']) for (let i = 0; i < 200; i++) {
    const r = VI.TEMPLATES.countByShape(diff);
    const word = r.question.match(/Cuántos (.+?) hay/)[1];
    const truth = r.scene.shapes.filter(s => s.kind === plural[word]).length;
    assert.equal(r.options[r.correctIndex], String(truth));
  }
});
test('countNotX answer equals shapes that are NOT the asked kind', () => {
  const plural = { 'círculos': 'circle', 'cuadrados': 'square', 'triángulos': 'triangle' };
  for (const diff of ['fácil', 'media', 'difícil']) for (let i = 0; i < 200; i++) {
    const r = VI.TEMPLATES.countNotX(diff);
    const word = r.question.match(/NO son (.+?)\?/)[1];
    const truth = r.scene.shapes.filter(s => s.kind !== plural[word]).length;
    assert.equal(r.options[r.correctIndex], String(truth));
  }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node --test tests/visual-items.test.mjs`
Expected: FAIL — `Cannot read properties of undefined (reading 'countAll')` (no `TEMPLATES`).

- [ ] **Step 3: Add `numericOptions`, the four counting templates, and a `TEMPLATES` registry**

Insert above `var API = {`:

```js
  function adjColor(c) { return COLOR_ADJ_FEM[c]; }

  // 3 unique numeric option strings incl. `correct`, plausible within [0, total].
  function numericOptions(correct, total, diff) {
    var spread = diff === 'difícil' ? 1 : 2;
    var seen = {}; seen[correct] = true;
    var opts = [correct], guard = 0;
    while (opts.length < 3 && guard++ < 80) {
      var delta = randInt(1, spread) * (Math.random() < 0.5 ? -1 : 1);
      var v = correct + delta;
      if (v < 0 || v > total + spread || seen[v]) continue;
      seen[v] = true; opts.push(v);
    }
    var fill = 0;
    while (opts.length < 3) { if (!seen[fill]) { seen[fill] = true; opts.push(fill); } fill++; }
    return shuffle(opts.map(String));
  }

  function tplCountAll(diff) {
    var n = randInt(2, maxTotalFor(diff));
    var scene = makeScene(n);
    var options = numericOptions(n, n, diff);
    return { scene: scene, question: '¿Cuántas figuras hay?', options: options,
      correctIndex: options.indexOf(String(n)), explanation: 'Hay ' + n + ' figuras en total.' };
  }

  function tplCountByColor(diff) {
    var n = randInt(3, maxTotalFor(diff));
    var two = shuffle(COLORS).slice(0, 2), target = two[0];
    var scene = makeScene(n, { colors: two });
    var c = count(scene, function (s) { return s.color === target; });
    if (c === 0) { scene.shapes[0].color = target; c = 1; }
    var options = numericOptions(c, n, diff);
    return { scene: scene, question: '¿Cuántas figuras ' + adjColor(target) + ' hay?', options: options,
      correctIndex: options.indexOf(String(c)), explanation: 'Hay ' + c + ' figuras ' + adjColor(target) + '.' };
  }

  function tplCountByShape(diff) {
    var n = randInt(3, maxTotalFor(diff));
    var two = shuffle(SHAPES).slice(0, 2), target = two[0];
    var scene = makeScene(n, { shapes: two });
    var c = count(scene, function (s) { return s.kind === target; });
    if (c === 0) { scene.shapes[0].kind = target; c = 1; }
    var options = numericOptions(c, n, diff);
    return { scene: scene, question: '¿Cuántos ' + SHAPE_PLURAL[target] + ' hay?', options: options,
      correctIndex: options.indexOf(String(c)), explanation: 'Hay ' + c + ' ' + SHAPE_PLURAL[target] + '.' };
  }

  function tplCountNotX(diff) {
    var n = randInt(3, maxTotalFor(diff));
    var two = shuffle(SHAPES).slice(0, 2), target = two[0];
    var scene = makeScene(n, { shapes: two });
    var c = count(scene, function (s) { return s.kind !== target; });
    if (c === 0) { scene.shapes[0].kind = two[1]; c = 1; }
    if (c === n) { scene.shapes[0].kind = target; c = n - 1; }
    var options = numericOptions(c, n, diff);
    return { scene: scene, question: '¿Cuántas figuras NO son ' + SHAPE_PLURAL[target] + '?', options: options,
      correctIndex: options.indexOf(String(c)), explanation: c + ' figuras no son ' + SHAPE_PLURAL[target] + '.' };
  }

  var TEMPLATES = {
    countAll: tplCountAll, countByColor: tplCountByColor,
    countByShape: tplCountByShape, countNotX: tplCountNotX
    // (compareQty, compareSize, commonAttr added in Task 4)
  };
```

Add `TEMPLATES: TEMPLATES,` to the `API` object.

- [ ] **Step 4: Run test to verify it passes**

Run: `node --test tests/visual-items.test.mjs`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting add visual-items.js tests/visual-items.test.mjs
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting commit -m "feat: counting templates (all/by-color/by-shape/not-x) with computed answers

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: Comparison & attribute templates (`compareQty`, `compareSize`, `commonAttr`)

**Files:**
- Modify: `visual-items.js`
- Test: `tests/visual-items.test.mjs`

- [ ] **Step 1: Write the failing test**

```js
test('compareQty picks the more numerous color or "Iguales"', () => {
  for (const diff of ['fácil', 'media', 'difícil']) for (let i = 0; i < 300; i++) {
    const r = VI.TEMPLATES.compareQty(diff);
    const colorByAdj = { rojas: 'rojo', azules: 'azul', verdes: 'verde', amarillas: 'amarillo' };
    const m = r.question.match(/más figuras (\w+) o más (\w+)\?/);
    const a = colorByAdj[m[1]], b = colorByAdj[m[2]];
    const na = r.scene.shapes.filter(s => s.color === a).length;
    const nb = r.scene.shapes.filter(s => s.color === b).length;
    const expect = na > nb ? 0 : nb > na ? 1 : 2;
    assert.equal(r.correctIndex, expect);
  }
});
test('compareSize picks the more numerous size or "Iguales"', () => {
  for (const diff of ['fácil', 'media', 'difícil']) for (let i = 0; i < 300; i++) {
    const r = VI.TEMPLATES.compareSize(diff);
    const ng = r.scene.shapes.filter(s => s.size === 'grande').length;
    const np = r.scene.shapes.filter(s => s.size === 'pequeño').length;
    const expect = ng > np ? 0 : np > ng ? 1 : 2;
    assert.equal(r.correctIndex, expect);
  }
});
test('commonAttr fires only on uniform scenes and names the shared attribute', () => {
  for (let i = 0; i < 300; i++) {
    const r = VI.TEMPLATES.commonAttr('media');
    assert.equal(r.options.length, 3);
    const truth = r.options[r.correctIndex];
    if (/color/.test(r.question)) {
      const colors = new Set(r.scene.shapes.map(s => s.color));
      assert.equal(colors.size, 1);
      const adj = { rojo: 'Rojo', azul: 'Azul', verde: 'Verde', amarillo: 'Amarillo' };
      assert.equal(truth, adj[[...colors][0]]);
    } else {
      const kinds = new Set(r.scene.shapes.map(s => s.kind));
      assert.equal(kinds.size, 1);
      const sing = { circle: 'Círculo', square: 'Cuadrado', triangle: 'Triángulo' };
      assert.equal(truth, sing[[...kinds][0]]);
    }
  }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node --test tests/visual-items.test.mjs`
Expected: FAIL — `VI.TEMPLATES.compareQty is not a function`.

- [ ] **Step 3: Add the three templates and register them**

Insert above `var TEMPLATES = {`:

```js
  function tplCompareQty(diff) {
    var max = maxTotalFor(diff);
    var two = shuffle(COLORS).slice(0, 2), cA = two[0], cB = two[1];
    var nA = randInt(1, Math.max(1, max - 1));
    var nB = randInt(1, Math.max(1, max - nA));
    var shapes = [], i;
    for (i = 0; i < nA; i++) shapes.push({ kind: pick(SHAPES), color: cA, size: pick(['grande', 'pequeño']) });
    for (i = 0; i < nB; i++) shapes.push({ kind: pick(SHAPES), color: cB, size: pick(['grande', 'pequeño']) });
    var scene = { shapes: shuffle(shapes) };
    var options = ['Más ' + adjColor(cA), 'Más ' + adjColor(cB), 'Iguales'];
    var correctIndex = nA > nB ? 0 : nB > nA ? 1 : 2;
    var explanation = nA > nB ? ('Hay más ' + adjColor(cA) + ': ' + nA + ' contra ' + nB + '.')
      : nB > nA ? ('Hay más ' + adjColor(cB) + ': ' + nB + ' contra ' + nA + '.')
      : ('Hay ' + nA + ' de cada color.');
    return { scene: scene, question: '¿Hay más figuras ' + adjColor(cA) + ' o más ' + adjColor(cB) + '?',
      options: options, correctIndex: correctIndex, explanation: explanation };
  }

  function tplCompareSize(diff) {
    var max = maxTotalFor(diff);
    var nG = randInt(1, Math.max(1, max - 1));
    var nP = randInt(1, Math.max(1, max - nG));
    var shapes = [], i;
    for (i = 0; i < nG; i++) shapes.push({ kind: pick(SHAPES), color: pick(COLORS), size: 'grande' });
    for (i = 0; i < nP; i++) shapes.push({ kind: pick(SHAPES), color: pick(COLORS), size: 'pequeño' });
    var scene = { shapes: shuffle(shapes) };
    var options = ['Más grandes', 'Más pequeñas', 'Iguales'];
    var correctIndex = nG > nP ? 0 : nP > nG ? 1 : 2;
    var explanation = nG > nP ? ('Hay más grandes: ' + nG + ' contra ' + nP + '.')
      : nP > nG ? ('Hay más pequeñas: ' + nP + ' contra ' + nG + '.')
      : ('Hay ' + nG + ' de cada tamaño.');
    return { scene: scene, question: '¿Hay más figuras grandes o más pequeñas?',
      options: options, correctIndex: correctIndex, explanation: explanation };
  }

  function tplCommonAttr(diff) {
    var n = randInt(3, maxTotalFor(diff));
    if (Math.random() < 0.5) {
      var color = pick(COLORS);
      var scene = makeScene(n, { colors: [color] });
      var others = shuffle(COLORS.filter(function (c) { return c !== color; })).slice(0, 2);
      var options = shuffle([color, others[0], others[1]].map(capit));
      return { scene: scene, question: '¿De qué color son todas las figuras?', options: options,
        correctIndex: options.indexOf(capit(color)), explanation: 'Todas las figuras son ' + adjColor(color) + '.' };
    }
    var shape = pick(SHAPES);
    var scene2 = makeScene(n, { shapes: [shape] });
    var others2 = shuffle(SHAPES.filter(function (s) { return s !== shape; })).slice(0, 2);
    var options2 = shuffle([shape, others2[0], others2[1]].map(function (s) { return capit(SHAPE_SINGULAR[s]); }));
    return { scene: scene2, question: '¿Qué forma son todas las figuras?', options: options2,
      correctIndex: options2.indexOf(capit(SHAPE_SINGULAR[shape])), explanation: 'Todas las figuras son ' + SHAPE_PLURAL[shape] + '.' };
  }
```

Then extend the registry:

```js
  var TEMPLATES = {
    countAll: tplCountAll, countByColor: tplCountByColor,
    countByShape: tplCountByShape, countNotX: tplCountNotX,
    compareQty: tplCompareQty, compareSize: tplCompareSize, commonAttr: tplCommonAttr
  };
```

- [ ] **Step 4: Run test to verify it passes**

Run: `node --test tests/visual-items.test.mjs`
Expected: PASS (10 tests).

- [ ] **Step 5: Commit**

```bash
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting add visual-items.js tests/visual-items.test.mjs
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting commit -m "feat: comparison + common-attribute templates

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: `templatesFor` (objective routing) + `generateVisualItem`

**Files:**
- Modify: `visual-items.js`
- Test: `tests/visual-items.test.mjs`

- [ ] **Step 1: Write the failing test**

```js
test('templatesFor maps preescolar mat objectives, null otherwise', () => {
  const objs = {
    'Agrupar objetos por su color': ['countByColor', 'commonAttr'],
    'Agrupar objetos por su forma': ['countByShape', 'commonAttr'],
    'Agrupar objetos por su tamaño': ['compareSize'],
    'Reconocer la característica común de un conjunto': ['commonAttr'],
    'Identificar el elemento que sobra en un conjunto': ['countNotX'],
    'Comparar cantidades: más que / menos que': ['compareQty'],
    'Reconocer conjuntos con igual cantidad': ['compareQty'],
    'Contar objetos hasta 5': ['countAll', 'countByColor'],
    'Contar objetos hasta 10': ['countAll', 'countByColor']
  };
  for (const [text, ids] of Object.entries(objs)) {
    assert.deepEqual(VI.templatesFor({ subjKey: 'mat', obj: text }), ids);
  }
  assert.equal(VI.templatesFor({ subjKey: 'len', obj: 'Agrupar objetos por su color' }), null);
  assert.equal(VI.templatesFor({ subjKey: 'mat', obj: 'Identificar el sonido inicial' }), null);
});

test('generateVisualItem returns a renderable, correct item', () => {
  for (let i = 0; i < 300; i++) {
    const q = VI.generateVisualItem({ subjKey: 'mat', obj: 'Agrupar objetos por su forma' }, 'media');
    assert.ok(q.svg.startsWith('<svg'));
    assert.ok(['A', 'B', 'C'].includes(q.correct));
    assert.ok(q.opts[q.correct] && q.opts[q.correct].length > 0);
    assert.ok(q.question.length > 0 && q.explanation.length > 0);
  }
  assert.equal(VI.generateVisualItem({ subjKey: 'len', obj: 'x' }, 'media'), null);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node --test tests/visual-items.test.mjs`
Expected: FAIL — `VI.templatesFor is not a function`.

- [ ] **Step 3: Add `templatesFor` and `generateVisualItem`; export both**

Insert above `var API = {`:

```js
  // Maps an objective (by its Spanish text) to the templates that fit it.
  // Text-based (not index-based) so it survives curriculum reordering.
  function templatesFor(obj) {
    if (!obj || obj.subjKey !== 'mat') return null;
    var t = (obj.obj || '').toLowerCase();
    if (/por su color/.test(t)) return ['countByColor', 'commonAttr'];
    if (/por su forma/.test(t)) return ['countByShape', 'commonAttr'];
    if (/por su tama/.test(t)) return ['compareSize'];
    if (/característica común|caracteristica comun/.test(t)) return ['commonAttr'];
    if (/elemento que sobra/.test(t)) return ['countNotX'];
    if (/igual cantidad|más que|menos que|comparar cantidad/.test(t)) return ['compareQty'];
    if (/contar objetos hasta/.test(t)) return ['countAll', 'countByColor'];
    return null;
  }

  // Pick a template for the objective + difficulty, build the item, map options
  // to A/B/C letters. Returns null if the objective is not locally generable.
  function generateVisualItem(obj, diff) {
    var ids = templatesFor(obj);
    if (!ids) return null;
    var r = TEMPLATES[pick(ids)](diff || 'media');
    var letters = ['A', 'B', 'C', 'D'], opts = {};
    for (var i = 0; i < r.options.length; i++) opts[letters[i]] = r.options[i];
    return {
      question: r.question,
      svg: renderSceneSVG(r.scene),
      opts: opts,
      correct: letters[r.correctIndex],
      explanation: r.explanation
    };
  }
```

Add to the `API` object: `templatesFor: templatesFor,` and `generateVisualItem: generateVisualItem,`.

- [ ] **Step 4: Run test to verify it passes**

Run: `node --test tests/visual-items.test.mjs`
Expected: PASS (12 tests).

- [ ] **Step 5: Commit**

```bash
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting add visual-items.js tests/visual-items.test.mjs
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting commit -m "feat: objective routing + generateVisualItem assembly

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 6: Wire into `profebot.html`, `profebot.js`, `profebot.css`

No unit test (DOM/integration); verify with `node --check` + a manual smoke note.

**Files:**
- Modify: `profebot.html` (script include)
- Modify: `profebot.js` (`loadQ` branch, `renderQ` SVG, `PROV_META`)
- Modify: `profebot.css` (`.qsvg` rule)

- [ ] **Step 1: Add the script include before `profebot.js`**

In `profebot.html`, find `<script src="profebot.js"></script>` and insert immediately before it:

```html
<script src="visual-items.js"></script>
```

- [ ] **Step 2: Add the `local` provider label**

In `profebot.js`, find the `PROV_META` object and add an entry (keep existing entries intact):

```js
    local: { label: 'Actividad' },
```

- [ ] **Step 3: Add the routing branch in `loadQ()`**

In `profebot.js`, locate in `loadQ()` the lines that compute the objective and difficulty:

```js
    const obj = pickObj();
    if (!obj) return;
    const realDiff = getRealDiff();
    const cacheKey = _grade + '::' + obj.k + '::' + realDiff;
```

Immediately AFTER that block, insert:

```js
    // Locally-generated visual items (preescolar Matemática) bypass cache + AI:
    // we render an SVG scene whose answer we compute, so it's always correct and
    // never depends on an unseen image.
    if (_grade === 'preesc' && typeof VisualItems !== 'undefined' && VisualItems.templatesFor(obj)) {
        const vq = VisualItems.generateVisualItem(obj, realDiff);
        if (vq) {
            stopAll();
            lastProvider = 'local';
            vq.objText = obj.obj;
            vq.subjLabel = obj.subj;
            vq.chosen = null;
            vq.color = obj.color;
            sessQs.push(vq);
            sessAsked.push(vq.question);
            currentQ = vq;
            renderQ(vq);
            readQuestion(vq);
            return;
        }
    }
```

(`stopAll()` mirrors the start of the normal `loadQ` path; the early `return` skips the cache/AI flow entirely.)

- [ ] **Step 4: Inject the SVG in `renderQ()`**

In `profebot.js`, inside `renderQ(q)`, find where `imgHtml` is built (the `safeImg` lines). Immediately AFTER the `const imgHtml = ...` line, add:

```js
    const visualHtml = q.svg ? `<div class="qsvg">${q.svg}</div>` : imgHtml;
```

Then in the template string that builds the question bubble, replace the single `${imgHtml}` occurrence with `${visualHtml}`.

- [ ] **Step 5: Add the `.qsvg` CSS rule**

Append to `profebot.css`:

```css
.qsvg { max-width: 360px; margin: 10px auto; }
.qsvg svg { display: block; width: 100%; height: auto; }
```

- [ ] **Step 6: Verify syntax + serve check**

Run: `node --check D:/Descargas/profebot/.claude/worktrees/svg-counting/profebot.js`
Expected: no output (exit 0).

Manual smoke (document in the commit, do not automate here): `php -S localhost:8080 router.php`, open the app, pick **Prescolar → Nociones Matemáticas**, run a battery on a Conjuntos/Cantidades objective, confirm an SVG renders, the spoken question matches the picture, and picking the right option is graded correct. Badge shows "vía Actividad".

- [ ] **Step 7: Commit**

```bash
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting add profebot.html profebot.js profebot.css
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting commit -m "feat: render local SVG items for preescolar mat objectives

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 7: CI — run `node --test`

**Files:**
- Create: `.github/workflows/node.yml`

- [ ] **Step 1: Create the workflow**

```yaml
name: Node tests

on:
  pull_request:
    branches: [master, develop]
  push:
    branches: [master, develop]

jobs:
  node-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - name: Run unit tests
        run: node --test tests/
```

- [ ] **Step 2: Verify the suite locally one more time**

Run: `node --test tests/`
Expected: PASS (12 tests, 0 failures).

- [ ] **Step 3: Commit**

```bash
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting add .github/workflows/node.yml
git -C D:/Descargas/profebot/.claude/worktrees/svg-counting commit -m "ci: run node --test on PRs and pushes

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Notes for the implementer

- **Spanish UI / English comments** (per CLAUDE.md). All question/option/explanation text is Spanish.
- **Do not commit** `vendor/` or edit PHP — this feature is JS-only.
- **`pequeño` key** in `SIZE_PX` is quoted because of the `ñ`.
- The `local` items are not cached and never reach `pb_valid_question`; correctness is guaranteed by the generator + the Task 3/4/5 invariant tests.
- After all tasks: push the branch and open a PR to `develop` (the user merges from the GitHub UI; do not `gh pr merge`).
