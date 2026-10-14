import * as THREE from "three";
import { northDirection } from "./north-direction.js";

/** Direção no espaço do GLB. A câmera só projeta a etiqueta. */
export class NorthIndicator {
  constructor(scene, camera, viewport, getModel) {
    Object.assign(this, { scene, camera, viewport, getModel });
    this.group = new THREE.Group();
    this.arrow = new THREE.ArrowHelper(
      new THREE.Vector3(0, 0, -1),
      new THREE.Vector3(),
      1,
      0xa1edca,
    );
    this.group.add(this.arrow);
    const points = Array.from(
      { length: 96 },
      (_, i) =>
        new THREE.Vector3(
          Math.sin((i * Math.PI) / 48),
          0,
          -Math.cos((i * Math.PI) / 48),
        ),
    );
    this.ring = new THREE.LineLoop(
      new THREE.BufferGeometry().setFromPoints(points),
      new THREE.LineBasicMaterial({
        color: 0xa1edca,
        transparent: true,
        opacity: 0.45,
        depthTest: false,
        depthWrite: false,
      }),
    );
    this.group.add(this.ring);
    this.group.traverse((object) => {
      if (object.material) {
        object.material.depthTest = false;
        object.material.depthWrite = false;
      }
      object.renderOrder = 900;
    });
    this.group.visible = false;
    scene.add(this.group);
    this.label = document.createElement("span");
    this.label.className = "north-model-label";
    this.label.textContent = "N";
    this.label.hidden = true;
    viewport.append(this.label);
    this.tip = new THREE.Vector3();
    this.compass = document.createElement("div");
    this.compass.className = "model-compass";
    this.compass.setAttribute("role", "img");
    this.compass.hidden = true;
    this.cardinals = [
      ["N", 0],
      ["L", 90],
      ["S", 180],
      ["O", 270],
    ].map(([name, angle]) => {
      const label = document.createElement("span");
      label.className =
        "compass-letter" + (name === "N" ? " compass-north" : "");
      label.textContent = name;
      this.compass.append(label);
      return { label, angle };
    });
    const center = document.createElement("span");
    center.className = "compass-center";
    center.textContent = "+";
    this.compass.append(center);
    viewport.append(this.compass);
    this.compassUp = new THREE.Vector3();
    this.compassRight = new THREE.Vector3();
    this.compassNorth = new THREE.Vector3();
  }
  set(degrees, defining = false) {
    this.degrees = degrees;
    this.defining = defining;
    this.refresh();
  }
  refresh() {
    const model = this.getModel();
    this.group.visible = this.degrees != null && !!model;
    this.label.hidden = !this.group.visible;
    this.compass.hidden = !this.group.visible;
    if (!this.group.visible) return;
    const box = new THREE.Box3().setFromObject(model, true);
    const size = box.getSize(new THREE.Vector3()),
      center = box.getCenter(new THREE.Vector3());
    const length = Math.max(size.x, size.z, 1) * 0.36;
    this.group.position.set(
      center.x,
      box.max.y + Math.max(size.y, 1) * 0.06,
      center.z,
    );
    const direction = northDirection(this.degrees);
    this.arrow.setDirection(new THREE.Vector3(direction.x, 0, direction.z));
    this.arrow.setLength(length, length * 0.18, length * 0.09);
    this.ring.scale.setScalar(length);
    this.ring.visible = this.defining;
    this.tip
      .set(direction.x * length, 0, direction.z * length)
      .add(this.group.position);
    this.label.title =
      "Norte verdadeiro do empreendimento: " + this.degrees.toFixed(1) + "°";
    this.compass.setAttribute(
      "aria-label",
      "Bússola do empreendimento. Norte salvo: " +
        this.degrees.toFixed(1) +
        " graus.",
    );
  }
  update() {
    if (!this.group.visible) return;
    // Bússola em planta: orientação horizontal do topo da tela, respondendo ao giro da câmera.
    // A direção geográfica continua vindo de northDirection, compartilhada com o Sol.
    this.compassUp.set(0, 1, 0).applyQuaternion(this.camera.quaternion);
    this.compassUp.y = 0;
    if (this.compassUp.lengthSq() < 1e-10) {
      this.camera.getWorldDirection(this.compassUp);
      this.compassUp.y = 0;
    }
    this.compassUp.normalize();
    this.compassRight.set(-this.compassUp.z, 0, this.compassUp.x);
    const north = northDirection(this.degrees);
    this.compassNorth.set(north.x, 0, north.z);
    const heading = Math.atan2(
      this.compassNorth.dot(this.compassRight),
      this.compassNorth.dot(this.compassUp),
    );
    for (const { label, angle } of this.cardinals) {
      const direction = heading + (angle * Math.PI) / 180;
      label.style.left = 50 + Math.sin(direction) * 35 + "%";
      label.style.top = 50 - Math.cos(direction) * 35 + "%";
    }
    const point = this.tip.clone().project(this.camera);
    this.label.hidden =
      point.z < -1 ||
      point.z > 1 ||
      Math.abs(point.x) > 0.96 ||
      Math.abs(point.y) > 0.96;
    this.label.style.left =
      ((point.x + 1) / 2) * this.viewport.clientWidth + "px";
    this.label.style.top =
      ((1 - point.y) / 2) * this.viewport.clientHeight + "px";
  }
}
