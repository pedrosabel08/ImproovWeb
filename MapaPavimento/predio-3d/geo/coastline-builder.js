import {
  clipSegment,
  signedArea,
  polygonArea,
  unionPolygons,
  differencePolygons,
  mapPolygons,
} from "./environment-clip.js?v=20261103-boundary-v4";

/** Polygonise coastline + square perimeter in geographic E/N (not mirrored X/Z).
 * A directed OSM coast has LAND on its left and SEA on its right. Each half-edge
 * walks its left face; reverse coastal half-edges therefore identify sea faces.
 * No nearest-edge guesses or large water planes. Broken/branching coasts fail closed. */
export function buildCoastalAreas(features, project, bounds) {
  const nodes = new Map(),
    edges = [],
    coastLines = [],
    r = bounds.radius;
  const key = (p) => `${Math.round(p.x * 1000)}:${Math.round(p.z * 1000)}`;
  const node = (p) => {
    const k = key(p);
    if (!nodes.has(k)) nodes.set(k, { ...p, id: k, out: [], coastDegree: 0 });
    return nodes.get(k);
  };
  const edgeKeys = new Set();
  function add(a, b, label = null) {
    const u = node(a),
      v = node(b);
    if (u === v) return;
    const id = [u.id, v.id].sort().join("/");
    if (edgeKeys.has(id)) return;
    edgeKeys.add(id);
    const forward = { from: u, to: v, label, visited: false },
      reverse = { from: v, to: u, label: label ? "SEA" : null, visited: false };
    forward.twin = reverse;
    reverse.twin = forward;
    u.out.push(forward);
    v.out.push(reverse);
    edges.push(forward, reverse);
    if (label) {
      u.coastDegree++;
      v.coastDegree++;
    }
  }
  for (const feature of features) {
    const points = feature.line.map(project).map(bounds.toEN);
    for (let i = 1; i < points.length; i++) {
      const segment = clipSegment(points[i - 1], points[i], r);
      if (segment) {
        add(segment[0], segment[1], "LAND");
        coastLines.push(segment.map(bounds.toLocal));
      }
    }
  }
  const boundary = (p) =>
    Math.abs(Math.abs(p.x) - r) < 0.002 || Math.abs(Math.abs(p.z) - r) < 0.002;
  if (!edges.length)
    return { sea: [], land: [bounds.polygon], coastLines, state: "no-coast" };
  const issues = [...nodes.values()]
    .filter((p) => p.coastDegree > 2 || (p.coastDegree === 1 && !boundary(p)))
    .map((p) => ({ x: p.x, z: p.z, degree: p.coastDegree }));
  if (nodes.size > 20000 || issues.length)
    return {
      sea: [],
      land: [bounds.polygon],
      coastLines,
      state: "incomplete",
      issues,
    };
  const corners = [
    { x: -r, z: -r },
    { x: r, z: -r },
    { x: r, z: r },
    { x: -r, z: r },
  ];
  corners.forEach(node);
  // Perimeter split at EVERY clipped coastal endpoint, so each interior face closes exactly.
  for (let side = 0; side < 4; side++) {
    const onSide = (p) =>
      side === 0
        ? Math.abs(p.z + r) < 0.002
        : side === 1
          ? Math.abs(p.x - r) < 0.002
          : side === 2
            ? Math.abs(p.z - r) < 0.002
            : Math.abs(p.x + r) < 0.002;
    const value = (p) =>
      side === 0 ? p.x : side === 1 ? p.z : side === 2 ? -p.x : -p.z;
    const points = [...nodes.values()]
      .filter(onSide)
      .sort((a, b) => value(a) - value(b));
    for (let i = 1; i < points.length; i++) add(points[i - 1], points[i]);
  }
  for (const n of nodes.values())
    n.out.sort(
      (a, b) =>
        Math.atan2(a.to.z - n.z, a.to.x - n.x) -
        Math.atan2(b.to.z - n.z, b.to.x - n.x),
    );
  const faces = [];
  for (const first of edges) {
    if (first.visited) continue;
    let current = first;
    const ring = [],
      labels = new Set();
    let steps = 0;
    do {
      if (current.visited || ++steps > edges.length) break;
      current.visited = true;
      ring.push({ x: current.from.x, z: current.from.z });
      if (current.label) labels.add(current.label);
      const choices = current.to.out,
        index = choices.indexOf(current.twin);
      current = choices[(index + choices.length - 1) % choices.length];
    } while (current !== first);
    if (current !== first)
      return {
        sea: [],
        land: [bounds.polygon],
        coastLines,
        state: "invalid-graph",
      };
    if (signedArea(ring) > 0.001) {
      if (labels.size > 1)
        return {
          sea: [],
          land: [bounds.polygon],
          coastLines,
          state: "inconsistent-direction",
        };
      ring.push({ ...ring[0] });
      faces.push({
        polygon: { outer: ring, holes: [] },
        label: [...labels][0],
      });
    }
  }
  // A closed island leaves an unlabeled perimeter face. Its exterior is sea.
  const unknown = faces.filter((f) => !f.label);
  if (unknown.length) {
    const labels = new Set(faces.filter((f) => f.label).map((f) => f.label));
    if (labels.size !== 1)
      return {
        sea: [],
        land: [bounds.polygon],
        coastLines,
        state: "ambiguous-closed-coast",
      };
    unknown.forEach((f) => {
      f.label = labels.has("LAND") ? "SEA" : "LAND";
    });
  }
  let sea = [];
  for (const face of faces.sort(
    (a, b) => polygonArea(b.polygon) - polygonArea(a.polygon),
  )) {
    sea =
      face.label === "SEA"
        ? unionPolygons(sea, [face.polygon])
        : differencePolygons(sea, [face.polygon]);
  }
  const land = differencePolygons(
    mapPolygons([bounds.polygon], bounds.toEN),
    sea,
  );
  return {
    sea: mapPolygons(sea, bounds.toLocal),
    land: mapPolygons(land, bounds.toLocal),
    coastLines,
    state: "ready",
  };
}
