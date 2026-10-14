import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../MapaPavimento/predio-3d/north-direction.js', import.meta.url), 'utf8');
const { northDirection, normalizeNorth, dialNorth } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
for (const [degrees, x, z] of [[0,0,-1],[90,1,0],[180,0,1],[270,-1,0]]) {
  const direction = northDirection(degrees);
  assert.ok(Math.abs(direction.x-x)<1e-12 && Math.abs(direction.z-z)<1e-12);
  assert.equal(direction.y, 0);
  assert.equal(dialNorth(x,z), degrees);
}
assert.equal(normalizeNorth(-90), 270);
assert.equal(normalizeNorth(360), 0);
assert.equal(normalizeNorth(359.99), 0);
assert.equal(dialNorth(Math.sin(32.5*Math.PI/180), -Math.cos(32.5*Math.PI/180)), 32.5);
console.log('OK: convenção GLB, círculo, normalização e precisão do Norte.');
