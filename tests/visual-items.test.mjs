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
