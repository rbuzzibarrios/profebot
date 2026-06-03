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
