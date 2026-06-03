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

  var TEMPLATES = {
    countAll: tplCountAll, countByColor: tplCountByColor,
    countByShape: tplCountByShape, countNotX: tplCountNotX,
    compareQty: tplCompareQty, compareSize: tplCompareSize, commonAttr: tplCommonAttr
  };

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

  var API = {
    makeScene: makeScene,
    renderSceneSVG: renderSceneSVG,
    TEMPLATES: TEMPLATES,
    templatesFor: templatesFor,
    generateVisualItem: generateVisualItem,
    _internals: { count: count, randInt: randInt, pick: pick, shuffle: shuffle, capit: capit, maxTotalFor: maxTotalFor,
      COLORS: COLORS, COLOR_ADJ_FEM: COLOR_ADJ_FEM, SHAPES: SHAPES, SHAPE_SINGULAR: SHAPE_SINGULAR, SHAPE_PLURAL: SHAPE_PLURAL }
  };
  if (typeof module !== 'undefined' && module.exports) { module.exports = API; }
  root.VisualItems = API;
})(typeof window !== 'undefined' ? window : this);
