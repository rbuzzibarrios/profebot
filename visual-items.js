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
