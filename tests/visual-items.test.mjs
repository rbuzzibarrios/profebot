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

test('renderSceneSVG returns an <svg> string with one node per shape', () => {
  const scene = VI.makeScene(6);
  const svg = VI.renderSceneSVG(scene);
  assert.match(svg, /^<svg /);
  assert.match(svg, /<\/svg>$/);
  const nodes = (svg.match(/<(circle|rect|polygon)\b/g) || []).length;
  assert.equal(nodes, 6);
});

// Helper: recompute truth independently and assert the template agrees.
function checkTemplate(tplName, recount) {
  for (const diff of ['fácil', 'media', 'difícil']) {
    for (let i = 0; i < 200; i++) {
      const r = VI.TEMPLATES[tplName](diff);
      assert.equal(r.options.length, 2, tplName + ' has 2 options');
      assert.ok(new Set(r.options).size === 2, tplName + ' options unique');
      assert.ok(r.correctIndex >= 0 && r.correctIndex < 2, tplName + ' correctIndex in range');
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

test('compareQty picks the more numerous color (no tie, 2 options)', () => {
  for (const diff of ['fácil', 'media', 'difícil']) for (let i = 0; i < 300; i++) {
    const r = VI.TEMPLATES.compareQty(diff);
    assert.equal(r.options.length, 2);
    const colorByAdj = { rojas: 'rojo', azules: 'azul', verdes: 'verde', amarillas: 'amarillo' };
    const m = r.question.match(/más figuras (\w+) o más (\w+)\?/);
    const a = colorByAdj[m[1]], b = colorByAdj[m[2]];
    const na = r.scene.shapes.filter(s => s.color === a).length;
    const nb = r.scene.shapes.filter(s => s.color === b).length;
    assert.notEqual(na, nb);
    assert.equal(r.correctIndex, na > nb ? 0 : 1);
  }
});
test('compareSize picks the more numerous size (no tie, 2 options)', () => {
  for (const diff of ['fácil', 'media', 'difícil']) for (let i = 0; i < 300; i++) {
    const r = VI.TEMPLATES.compareSize(diff);
    assert.equal(r.options.length, 2);
    const ng = r.scene.shapes.filter(s => s.size === 'grande').length;
    const np = r.scene.shapes.filter(s => s.size === 'pequeño').length;
    assert.notEqual(ng, np);
    assert.equal(r.correctIndex, ng > np ? 0 : 1);
  }
});
test('commonAttr fires only on uniform scenes and names the shared attribute', () => {
  for (let i = 0; i < 300; i++) {
    const r = VI.TEMPLATES.commonAttr('media');
    assert.equal(r.options.length, 2);
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
    assert.ok(['A', 'B'].includes(q.correct));
    assert.equal(Object.keys(q.opts).length, 2);
    assert.ok(q.opts[q.correct] && q.opts[q.correct].length > 0);
    assert.ok(q.question.length > 0 && q.explanation.length > 0);
  }
  assert.equal(VI.generateVisualItem({ subjKey: 'len', obj: 'x' }, 'media'), null);
});
