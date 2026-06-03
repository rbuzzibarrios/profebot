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

  var API = {
    makeScene: makeScene,
    renderSceneSVG: renderSceneSVG,
    // (generateVisualItem, templatesFor added in later tasks)
    _internals: { count: count, randInt: randInt, pick: pick, shuffle: shuffle, capit: capit, maxTotalFor: maxTotalFor,
      COLORS: COLORS, COLOR_ADJ_FEM: COLOR_ADJ_FEM, SHAPES: SHAPES, SHAPE_SINGULAR: SHAPE_SINGULAR, SHAPE_PLURAL: SHAPE_PLURAL }
  };
  if (typeof module !== 'undefined' && module.exports) { module.exports = API; }
  root.VisualItems = API;
})(typeof window !== 'undefined' ? window : this);
