import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { pathToFileURL } from "node:url";
import { resolve } from "node:path";

const modulePath = process.argv
  .find((arg) => arg.startsWith("--three-module="))
  ?.slice(15);
if (!modulePath)
  throw new Error("Informe --three-module=CAMINHO/three.module.js");
const THREE = await import(pathToFileURL(resolve(modulePath)).href);
// Exercita a função do visualizador sem inicializar WebGL nem a interface.
const source = readFileSync(
  new URL(
    "../MapaPavimento/predio-3d/visualizador-glb-navegacao.js",
    import.meta.url,
  ),
  "utf8",
);
const pattern = source.match(/const floorObjectPattern = .+;/)[0];
const functions = source.slice(
  source.indexOf("function parseFloorObjectName("),
  source.indexOf("function floorLabel("),
);
const buildIndex = new Function(
  "THREE",
  `${pattern}\n${functions}\nreturn buildIndex;`,
)(THREE);

function group(parent, name) {
  const node = new THREE.Group();
  node.name = name;
  parent.add(node);
  return node;
}
function mesh(parent, name) {
  const node = new THREE.Mesh(new THREE.BoxGeometry(1, 1, 1));
  node.name = name;
  parent.add(node);
  return node;
}
const root = new THREE.Group();
for (const floor of [
  "PAVTO_TERREO_01",
  "PAVTO_LAZER_01",
  "PAVTO_LAZER_02",
  "PAVTO_PENTHOUSE_01",
]) {
  const parent = group(root, floor);
  mesh(parent, `${floor}_001`);
}
// Uma peça com dois materiais vira um Group com duas meshes no GLTFLoader.
for (const name of [
  "PAVTO_LAZER_01_013",
  "PAVTO_LAZER_02_016",
  "PAVTO_LAZER_02_040",
]) {
  const floor = root.children.find((node) => name.startsWith(node.name + "_"));
  const piece = group(floor, name);
  mesh(piece, name + "_0");
  mesh(piece, name + "_1");
}
const context = mesh(root, "TERRENO");
root.updateMatrixWorld(true);
let indexed = buildIndex(root, "modelo.glb");
assert.equal(
  indexed.floors.length,
  4,
  "Submalhas não criam os três pavimentos extras da regressão.",
);
assert.equal(
  indexed.records.length,
  10,
  "Todas as peças continuam representadas uma única vez.",
);
assert.equal(new Set(indexed.records.map((record) => record.object)).size, 10);
assert.ok(!indexed.records.some((record) => record.object === context));
assert.equal(
  indexed.floors.find((floor) => floor.id === "PAVTO_LAZER_01").records.length,
  3,
);
assert.equal(
  indexed.floors.find((floor) => floor.id === "PAVTO_LAZER_02").records.length,
  5,
);

const standalone = new THREE.Group();
const piece = group(standalone, "PAVTO_LAZER_01_013");
mesh(piece, "Malha_0");
mesh(piece, "Malha_1");
standalone.updateMatrixWorld(true);
assert.deepEqual(
  buildIndex(standalone).floors.map((floor) => floor.id),
  ["PAVTO_LAZER_01"],
);

const fullNames = new THREE.Group();
for (const name of [
  "PAVTO_LAZER1_001",
  "PAVTO_TIPO1_003",
  "PAV_TERREO_001",
  "PAVTO_LAZER2_001",
]) {
  mesh(group(fullNames, name), "Geometria");
}
fullNames.updateMatrixWorld(true);
assert.equal(
  buildIndex(fullNames).floors.length,
  4,
  "Nomes completos e prefixo PAV_ preservados.",
);
const flat = new THREE.Group();
mesh(flat, "PAVTO_TIPO_01_001");
mesh(flat, "PAVTO_TIPO_01_002");
flat.updateMatrixWorld(true);
assert.deepEqual(
  buildIndex(flat).floors.map((floor) => floor.id),
  ["PAVTO_TIPO_01"],
);
const site = new THREE.Group();
const road = mesh(site, "RUA_01_001");
road.position.y = -0.5; // superfície superior em 0
const raised = mesh(site, "PAVTO_TERREO_01_001");
raised.position.y = 3.5;
const upper = mesh(site, "PAVTO_TIPO_01_001");
upper.position.y = 9.5;
site.updateMatrixWorld(true);
const levels = buildIndex(site).floors;
assert.equal(levels.find((floor) => floor.id === "PAVTO_TERREO_01").bounds.min.y - levels[0].groundElevation, 3);
assert.equal(levels.find((floor) => floor.id === "PAVTO_TIPO_01").bounds.min.y - levels[0].groundElevation, 9);
road.visible = false;
assert.equal(buildIndex(site).floors[0].groundElevation, undefined);
console.log(
  "OK: quatro pavimentos, submalhas agrupadas, contexto excluído e nomes completos preservados.",
);
