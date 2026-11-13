import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { ExplorationCollision } from "./exploration-collision.js?v=4";

const JOSH = new URL("../../assets/person/Josh.glb?v=2", import.meta.url).href;
const WALK = 1.45,
  RUN = 3.65;
const EYE_HEIGHT = 1.65;

export async function loadJosh() {
  const { scene, animations } = await new GLTFLoader().loadAsync(JOSH);
  const clips = ["Idle", "Walking", "Running"].map((name) =>
    THREE.AnimationClip.findByName(animations, name),
  );
  if (clips.some((clip) => !clip))
    throw new Error("Animações do Josh incompletas.");
  const materials = new Set();
  scene.traverse((object) => {
    if (!object.isSkinnedMesh) return;
    object.castShadow = object.receiveShadow = true;
    // A bind-pose bounding sphere does not enclose every animated pose.
    object.frustumCulled = false;
    materials.add(object.material);
  });
  const mixer = new THREE.AnimationMixer(scene);
  const actions = clips.map((clip) => mixer.clipAction(clip));
  return { scene, mixer, actions, materials };
}

export class ExplorationCharacter {
  constructor(josh, collision, orbitCamera, placement) {
    Object.assign(this, { josh, collision, orbitCamera });
    this.camera = new THREE.PerspectiveCamera(62, 1, 0.045, 6500);
    this.target = new THREE.Vector3();
    this.desired = new THREE.Vector3();
    this.offset = new THREE.Vector3();
    this.movement = new THREE.Vector3();
    this.firstPerson = true;
    this.firstPersonFov = 62;
    const view = placement.towardCamera;
    this.yaw = Math.atan2(view.x, view.z);
    this.pitch = 0;
    this.distance = 4.6;
    this.actualDistance = this.distance;
    this.position = placement.position;
    josh.scene.position.copy(this.position);
    josh.scene.rotation.y = this.yaw + Math.PI; // Mixamo faces +Z.
    this.weights = [1, 0, 0];
    josh.actions.forEach((action, i) =>
      action.reset().setEffectiveWeight(this.weights[i]).play(),
    );
    this.updateCamera(0, true);
  }

  static async prepare(source, signal) {
    source.group?.updateWorldMatrix(true, true);
    const collision = new ExplorationCollision(source);
    await collision.prepare(signal);
    return collision;
  }

  look(dx, dy) {
    this.yaw -= dx * 0.005;
    this.pitch = THREE.MathUtils.clamp(
      this.pitch + dy * 0.005,
      this.firstPerson ? -1.5 : -0.22,
      this.firstPerson ? 1.5 : 1.18,
    );
  }

  zoom(delta) {
    if (this.firstPerson)
      this.firstPersonFov = THREE.MathUtils.clamp(
        this.firstPersonFov + Math.sign(delta) * 3,
        32,
        75,
      );
    else
      this.distance = THREE.MathUtils.clamp(
        this.distance + Math.sign(delta) * 0.45,
        2.4,
        8,
      );
  }

  togglePerspective() {
    this.firstPerson = !this.firstPerson;
    if (!this.firstPerson)
      this.pitch = THREE.MathUtils.clamp(this.pitch, -0.22, 1.18);
    this.updateCamera(0, true);
  }

  update(dt, x, z, sprint) {
    const { josh, collision, position, movement } = this;
    let state = 0;
    if (x || z) {
      const sin = Math.sin(this.yaw),
        cos = Math.cos(this.yaw);
      movement.set(x * cos - z * sin, 0, -x * sin - z * cos).normalize();
      const distance = (sprint ? RUN : WALK) * dt;
      // Small substeps protect against tunneling, even on a slow frame.
      const steps = Math.max(1, Math.ceil(distance / 0.08));
      const dx = (movement.x * distance) / steps,
        dz = (movement.z * distance) / steps;
      const beforeX = position.x,
        beforeZ = position.z;
      for (let i = 0; i < steps && distance > 0; i++) {
        if (collision.tryMove(position, dx, dz)) continue;
        if (dx) collision.tryMove(position, dx, 0);
        if (dz) collision.tryMove(position, 0, dz);
      }
      const movedX = position.x - beforeX,
        movedZ = position.z - beforeZ;
      if (Math.hypot(movedX, movedZ) > 1e-5) {
        state = sprint ? 2 : 1;
        const direction = Math.atan2(movedX, movedZ);
        const turn =
          THREE.MathUtils.euclideanModulo(
            direction - josh.scene.rotation.y + Math.PI,
            Math.PI * 2,
          ) - Math.PI;
        josh.scene.rotation.y += turn * (1 - Math.exp(-14 * dt));
        josh.scene.position.copy(position);
      }
    }
    const blend = 1 - Math.exp(-15 * dt);
    josh.actions.forEach((action, i) => {
      this.weights[i] = THREE.MathUtils.lerp(
        this.weights[i],
        Number(i === state),
        blend,
      );
      action.setEffectiveWeight(this.weights[i]);
    });
    josh.mixer.update(dt);
    this.updateCamera(dt);
    return state;
  }

  updateCamera(dt, snap = false) {
    const { camera, position, target, desired, offset } = this;
    const aspect = this.orbitCamera.aspect || 1;
    const fov = this.firstPerson ? this.firstPersonFov : 58;
    if (camera.aspect !== aspect || camera.fov !== fov) {
      camera.aspect = aspect;
      camera.fov = fov;
      camera.updateProjectionMatrix();
    }
    const flat = Math.cos(this.pitch);
    offset.set(
      Math.sin(this.yaw) * flat,
      Math.sin(this.pitch),
      Math.cos(this.yaw) * flat,
    );
    if (this.firstPerson) {
      camera.position.copy(position);
      camera.position.y += EYE_HEIGHT;
      camera.lookAt(target.copy(camera.position).sub(offset));
      this.showAvatar(false);
      camera.updateMatrixWorld();
      return;
    }
    target.copy(position);
    target.y += 1.34;
    desired.copy(target).addScaledVector(offset, this.distance);
    const safe = this.collision.cameraDistance(target, desired);
    this.actualDistance =
      snap || safe < this.actualDistance
        ? safe
        : THREE.MathUtils.damp(this.actualDistance, safe, 12, dt);
    camera.position.copy(target).addScaledVector(offset, this.actualDistance);
    camera.lookAt(target);
    camera.updateMatrixWorld();
    this.showAvatar(this.actualDistance > 0.52);
  }

  showAvatar(visible) {
    // Keep the skinned meshes in the directional shadow pass while viewing through Josh's eyes.
    for (const material of this.josh.materials)
      material.colorWrite = material.depthWrite = visible;
  }

  dispose() {
    this.josh.scene.removeFromParent();
    this.josh.mixer.stopAllAction();
  }
}
