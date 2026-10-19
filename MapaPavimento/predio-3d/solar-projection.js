import * as THREE from "three";
import {
  createSolarRayField,
  SolarRayIndex,
} from "./solar-ray-tracer.js?v=20261016-alpha-glass";
import { solarAt, solarDayPositions } from "./solar-position.mjs";
import { solarGlowTexture, SOLAR_ORBIT_SCALE } from "./solar-visuals.js";

const noPicking = (object) => {
  object.raycast = () => {};
  object.frustumCulled = false;
  object.castShadow = object.receiveShadow = false;
  return object;
};
const nextFrame = (callback) =>
  typeof requestAnimationFrame === "function"
    ? requestAnimationFrame(callback)
    : setTimeout(callback, 0);

/** Auxiliares exclusivamente 3D: raios amostrados, pontos de impacto e trajetória diária. */
export class SolarProjection {
  constructor(scene, viewport = null, onStatus = () => {}) {
    Object.assign(this, { viewport, onStatus });
    this.token = 0;
    this.root = new THREE.Group();
    this.root.name = "Projeção solar (auxiliar visual)";
    this.rays = noPicking(
      new THREE.LineSegments(
        this.geometry(25 * 6),
        new THREE.LineBasicMaterial({
          color: 0xf4cc75,
          vertexColors: true,
          transparent: true,
          opacity: 0.3,
          depthWrite: false,
          toneMapped: false,
        }),
      ),
    );
    this.rays.geometry.setAttribute(
      "color",
      new THREE.BufferAttribute(new Float32Array(25 * 6 * 3), 3),
    );
    this.impacts = noPicking(
      new THREE.Points(
        this.geometry(25),
        new THREE.PointsMaterial({
          color: 0xffe2a0,
          map: solarGlowTexture(),
          transparent: true,
          opacity: 0.65,
          size: 6,
          sizeAttenuation: false,
          depthWrite: false,
          toneMapped: false,
        }),
      ),
    );
    this.path = noPicking(
      new THREE.LineSegments(
        this.geometry(320),
        new THREE.LineDashedMaterial({
          color: 0xe9bf68,
          transparent: true,
          opacity: 0.72,
          depthWrite: false,
          toneMapped: false,
          dashSize: 1,
          gapSize: 0.5,
        }),
      ),
    );
    this.hourPoints = noPicking(
      new THREE.Points(
        this.geometry(7),
        new THREE.PointsMaterial({
          color: 0xffe6a2,
          map: solarGlowTexture(),
          transparent: true,
          size: 10,
          sizeAttenuation: false,
          depthWrite: false,
          toneMapped: false,
        }),
      ),
    );
    this.root.add(this.rays, this.impacts, this.path, this.hourPoints);
    scene.add(this.root);
    this.hide();
    this.hourLabels = viewport
      ? [6, 8, 10, 12, 14, 16, 18].map((hour) => {
          const element = document.createElement("span");
          element.className = "solar-path-hour";
          element.textContent = String(hour).padStart(2, "0") + "h";
          element.hidden = true;
          viewport.append(element);
          return { hour, element, position: null };
        })
      : [];
    this.projected = new THREE.Vector3();
  }
  geometry(vertices) {
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute(
      "position",
      new THREE.BufferAttribute(new Float32Array(vertices * 3), 3),
    );
    geometry.setDrawRange(0, 0);
    return geometry;
  }
  fill(object, values) {
    const attribute = object.geometry.getAttribute("position");
    if (values.length > attribute.array.length)
      throw new Error("Limite de amostras solares excedido.");
    attribute.array.set(values);
    attribute.needsUpdate = true;
    object.geometry.setDrawRange(0, values.length / 3);
  }
  setModel(model, bounds) {
    this.index?.dispose();
    this.index = null;
    this.setBounds(bounds);
    if (model && bounds && !bounds.isEmpty())
      this.index = new SolarRayIndex(model);
  }
  setBounds(bounds) {
    this.hide();
    this.bounds = bounds?.clone() ?? null;
    this.pathKey = this.rayKey = null;
    if (bounds && !bounds.isEmpty()) {
      this.center = bounds.getCenter(new THREE.Vector3());
      this.radius = Math.max(
        bounds.getSize(new THREE.Vector3()).length() / 2,
        0.5,
      );
    }
  }
  hideRays() {
    ++this.token;
    this.rays.visible = this.impacts.visible = false;
    this.rayKey = null;
  }
  hide() {
    this.hideRays();
    this.path.visible = false;
    this.hourPoints.visible = false;
    this.hourLabels?.forEach(({ element }) => {
      element.hidden = true;
    });
  }
  requestRays(direction, ground) {
    if (!this.index || direction.y <= 0) {
      this.hideRays();
      return Promise.resolve(false);
    }
    const key = JSON.stringify([
      direction.x,
      direction.y,
      direction.z,
      ground?.min.toArray(),
      ground?.max.toArray(),
    ]);
    if (key === this.rayKey) return this.pending ?? Promise.resolve(true);
    this.hideRays();
    this.rayKey = key;
    const token = this.token;
    const field = createSolarRayField(this.bounds, direction);
    const floor = ground?.clone() ?? null;
    const lines = [],
      points = [],
      colors = [];
    let cursor = 0,
      buildings = 0,
      groundHits = 0;
    this.onStatus({ pending: true });
    this.pending = new Promise((resolve) => {
      const batch = () => {
        if (token !== this.token) {
          resolve(false);
          return;
        }
        const deadline = performance.now() + 6;
        try {
          do {
            const ray = field[cursor++];
            const hit = this.index.trace(
              ray.origin,
              ray.direction,
              ray.far,
              floor,
            );
            if (hit) {
              const add = (point) => lines.push(point.x, point.y, point.z);
              add(ray.origin);
              add(hit.point);
              colors.push(0.12, 0.12, 0.12, 1, 1, 1);
              // Chevron apontando na direção de viagem da luz, nunca em direção ao Sol.
              const tip = ray.origin.clone().lerp(hit.point, 0.72);
              const back = tip
                .clone()
                .addScaledVector(ray.direction, -this.radius * 0.045);
              add(back.clone().addScaledVector(ray.right, this.radius * 0.015));
              add(tip);
              add(tip);
              add(
                back.clone().addScaledVector(ray.right, -this.radius * 0.015),
              );
              colors.push(...Array(12).fill(0.8));
              const impact = hit.point
                .clone()
                .addScaledVector(ray.direction, -this.radius * 0.0002);
              points.push(impact.x, impact.y, impact.z);
              if (hit.kind === "model") buildings++;
              else groundHits++;
            }
          } while (cursor < field.length && performance.now() < deadline);
        } catch (error) {
          this.rayKey = null;
          this.onStatus({
            error: "Não foi possível projetar os raios neste modelo.",
          });
          resolve(false);
          return;
        }
        if (cursor < field.length) {
          nextFrame(batch);
          return;
        }
        this.fill(this.rays, lines);
        this.fill(this.impacts, points);
        this.rays.geometry.getAttribute("color").array.set(colors);
        this.rays.geometry.getAttribute("color").needsUpdate = true;
        this.rays.visible = this.impacts.visible = lines.length > 0;
        this.onStatus({
          pending: false,
          rays: points.length / 3,
          buildings,
          ground: groundHits,
        });
        resolve(true);
      };
      nextFrame(batch);
    });
    return this.pending;
  }
  showPath(config, date) {
    if (!this.bounds) return;
    const key = JSON.stringify([
      config.latitude,
      config.longitude,
      config.norte_graus,
      config.timezone,
      date,
    ]);
    if (key !== this.pathKey) {
      const samples = solarDayPositions(config, date);
      const lines = [];
      const at = (value) =>
        this.center
          .clone()
          .addScaledVector(
            new THREE.Vector3(
              value.direction.x,
              value.direction.y,
              value.direction.z,
            ),
            this.radius * SOLAR_ORBIT_SCALE,
          );
      for (let index = 1; index < samples.length; index++) {
        if (samples[index - 1].altitude <= 0 || samples[index].altitude <= 0)
          continue;
        lines.push(
          ...at(samples[index - 1]).toArray(),
          ...at(samples[index]).toArray(),
        );
      }
      this.fill(this.path, lines);
      this.path.computeLineDistances();
      this.path.material.dashSize = this.radius * 0.025;
      this.path.material.gapSize = this.radius * 0.012;
      const hourPoints = [];
      for (const label of this.hourLabels) {
        try {
          const result = solarAt(
            config,
            date,
            String(label.hour).padStart(2, "0") + ":00",
          );
          label.position = result.altitude > 0 ? at(result) : null;
          if (label.position) hourPoints.push(...label.position.toArray());
        } catch {
          label.position = null;
        }
      }
      this.fill(this.hourPoints, hourPoints);
      this.pathKey = key;
    }
    this.path.visible = this.path.geometry.drawRange.count > 0;
    this.hourPoints.visible = this.path.visible;
  }
  hidePath() {
    this.path.visible = false;
    this.hourPoints.visible = false;
    this.hourLabels.forEach(({ element }) => {
      element.hidden = true;
    });
  }
  update(camera) {
    const occupied = [];
    for (const label of this.hourLabels) {
      if (!this.path.visible || !label.position) {
        label.element.hidden = true;
        continue;
      }
      this.projected.copy(label.position).project(camera);
      label.element.hidden =
        Math.abs(this.projected.x) > 0.93 ||
        Math.abs(this.projected.y) > 0.92 ||
        Math.abs(this.projected.z) > 1;
      const x = ((this.projected.x + 1) / 2) * this.viewport.clientWidth;
      const y = ((1 - this.projected.y) / 2) * this.viewport.clientHeight;
      if (x > this.viewport.clientWidth - 260 && y < 90)
        label.element.hidden = true;
      if (
        !label.element.hidden &&
        occupied.some(
          (point) => Math.abs(point.x - x) < 36 && Math.abs(point.y - y) < 24,
        )
      )
        label.element.hidden = true;
      if (!label.element.hidden) occupied.push({ x, y });
      label.element.style.left = x + "px";
      label.element.style.top = y + "px";
    }
  }
}
