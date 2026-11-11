import * as THREE from "three";

const floorObjectPattern = /^(PAV(?:TO)?_.+_[0-9]+)_([0-9]+)$/i;
function parseFloorObjectName(name) {
  const match = floorObjectPattern.exec((name || "").trim());
  return match
    ? { floor: match[1].toUpperCase(), objectNumber: match[2] }
    : null;
}
export function buildIndex(root, sourceName = "") {
  const byFloor = new Map(),
    records = [],
    floorByObject = new Map();
  let hasObjectNames = false;
  root.traverse((object) => {
    const parsed = parseFloorObjectName(object.name);
    if (parsed) {
      floorByObject.set(object, parsed);
      if (object.isMesh) hasObjectNames = true;
    } else if (/^PAV(?:TO)?_.+_[0-9]{1,3}$/i.test(object.name || ""))
      floorByObject.set(object, {
        floor: object.name.trim().toUpperCase(),
        objectNumber: "",
      });
  });
  const addRecord = (object, floorId, objectNumber = "") => {
    if (!byFloor.has(floorId))
      byFloor.set(floorId, {
        id: floorId,
        records: [],
        contextMeshes: [],
        bounds: new THREE.Box3(),
      });
    const record = { object, floor: floorId, objectNumber, name: object.name };
    const floor = byFloor.get(floorId);
    floor.records.push(record);
    floor.bounds.union(new THREE.Box3().setFromObject(object, true));
    records.push(record);
  };
  root.traverse((object) => {
    if (!object.isMesh) return;
    const ownFloor = floorByObject.get(object);
    // O GLTFLoader transforma peças com várias primitives em grupos e cria
    // nomes para suas malhas internas. O grupo original define o pavimento;
    // o sufixo gerado para cada primitive não identifica outro pavimento.
    for (
      let ancestor = object.parent;
      ancestor && ancestor !== root;
      ancestor = ancestor.parent
    ) {
      const ancestorFloor = floorByObject.get(ancestor);
      if (ancestorFloor) {
        addRecord(
          object,
          ancestorFloor.floor,
          ownFloor?.floor === ancestorFloor.floor
            ? ownFloor.objectNumber
            : ancestorFloor.objectNumber,
        );
        return;
      }
    }
    if (ownFloor) addRecord(object, ownFloor.floor, ownFloor.objectNumber);
  });
  // A standalone floor file can use its filename as the identifier while its meshes
  // are being renamed to the shared-prefix convention for a whole-building GLB.
  if (!hasObjectNames) {
    const fileName =
      sourceName
        .split("?")[0]
        .replace(/\\/g, "/")
        .split("/")
        .pop()
        ?.replace(/\.(glb|gltf)$/i, "")
        .trim()
        .toUpperCase() || "";
    if (/^PAV(?:TO)?_[A-Z0-9_]+$/.test(fileName)) {
      byFloor.clear();
      records.length = 0;
      root.traverse((object) => {
        if (object.isMesh) addRecord(object, fileName);
      });
    }
  }
  const floors = [...byFloor.values()].sort(
    (a, b) =>
      a.bounds.min.y - b.bounds.min.y ||
      a.id.localeCompare(b.id, "pt-BR", { numeric: true }),
  );
  // A superfície do entorno é a referência do chão; o térreo pode estar
  // elevado ou rebaixado em relação a ela. Na ausência de terreno identificado
  // não é possível inferir essa altura com segurança.
  let groundElevation,
    groundArea = 0;
  root.traverse((object) => {
    if (
      !object.isMesh ||
      !/^(terreno|terrain|ground|rua)(?:_|\b)/i.test(object.name || "")
    )
      return;
    for (let node = object; node; node = node.parent) if (!node.visible) return;
    const bounds = new THREE.Box3().setFromObject(object, true);
    const size = bounds.getSize(new THREE.Vector3());
    const area = size.x * size.z;
    if (!bounds.isEmpty() && area > groundArea) {
      groundArea = area;
      groundElevation = bounds.max.y;
    }
  });
  floors.forEach((floor) => {
    floor.groundElevation = groundElevation;
  });
  return { floors, records };
}

export function modelGroundSelection(modelRoot) {
  const groundObjects = [];
  let groundInfo = null,
    groundArea = 0;
  modelRoot.traverseVisible((object) => {
    if (!/^(terreno|terrain|ground|rua)(?:_|\b)/i.test(object.name || ""))
      return;
    groundObjects.push(object);
    const bounds = new THREE.Box3().setFromObject(object, true);
    const size = bounds.getSize(new THREE.Vector3()),
      area = size.x * size.z;
    if (!bounds.isEmpty() && area > groundArea) {
      groundArea = area;
      let material;
      object.traverseVisible((child) => {
        if (!material && child.isMesh)
          material = Array.isArray(child.material)
            ? child.material[0]
            : child.material;
      });
      groundInfo = { elevation: bounds.max.y, color: material?.color?.clone(), object };
    }
  });
  return { groundObjects, groundInfo };
}

export async function modelAlignmentSignature(bounds, anchor, groundInfo) {
  const meshes = [];
  groundInfo?.object.updateWorldMatrix(true, true);
  groundInfo?.object.traverse(object => {
    const position = object.geometry?.attributes.position;
    if (!object.isMesh || !position) return;
    const coordinates = [];
    for (let i = 0; i < position.count; i++) {
      coordinates.push(position.getX(i), position.getY(i), position.getZ(i));
    }
    const index = object.geometry.index;
    const indices = index ? Array.from({ length: index.count }, (_, i) => index.getX(i)) : null;
    meshes.push([object.matrixWorld.toArray(), coordinates, indices]);
  });
  const input = [
    bounds.min.toArray(), bounds.max.toArray(), anchor.min.toArray(), anchor.max.toArray(),
    groundInfo?.elevation ?? null, meshes,
  ];
  const digest = await globalThis.crypto.subtle.digest("SHA-256", new TextEncoder().encode(JSON.stringify(input)));
  return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, "0")).join("");
}
