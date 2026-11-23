import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";

const MODEL = new URL("../../assets/person/seagull-2.glb", import.meta.url).href;
const CLIPS = ["flap", "planer", "planer 2"];
const { clamp, damp } = THREE.MathUtils;

export async function loadSeagull(disposeResources) {
  const gltf = await new GLTFLoader().loadAsync(MODEL);
  try {
    return new SeagullAvatar(gltf, disposeResources);
  } catch (error) {
    releaseModel(gltf.scene, disposeResources);
    throw error;
  }
}

function releaseModel(model, disposeResources) {
  const skeletons = new Set();
  model.traverse((mesh) => {
    if (mesh.isSkinnedMesh) skeletons.add(mesh.skeleton);
  });
  skeletons.forEach((skeleton) => skeleton.dispose());
  disposeResources(model);
}

export class SeagullAvatar {
  constructor({ scene, animations }, disposeResources) {
    this.clips = CLIPS.map((name) => THREE.AnimationClip.findByName(animations, name));
    if (this.clips.some((clip) => !clip || !Number.isFinite(clip.duration) || clip.duration <= 0))
      throw new Error("O modelo da gaivota tem clipes ausentes ou inválidos.");
    this.anchor = scene.getObjectByName("Bone");
    const span = new THREE.Box3().setFromObject(scene, true).getSize(new THREE.Vector3()).x;
    if (!this.anchor?.isBone || !Number.isFinite(span) || span <= 0)
      throw new Error("O modelo da gaivota tem dimensões ou esqueleto inválidos.");
    this.model = scene;
    this.disposeResources = disposeResources;
    this.root = new THREE.Group();
    this.root.name = "Gaivota";
    this.pose = new THREE.Group();
    this.root.add(this.pose);
    this.pose.add(scene);
    this.spanScale = 1.22 / span;
    this.center = new THREE.Vector3();
    this.mixer = new THREE.AnimationMixer(scene);
    // All three actions keep their times. Blend current weights even when the
    // player changes the clip before a previous transition has finished.
    this.weights = [0, 1, 0];
    this.actions = this.clips.map((clip, index) =>
      this.mixer.clipAction(clip).setEffectiveWeight(this.weights[index]).play(),
    );
    scene.traverse((mesh) => {
      if (!mesh.isSkinnedMesh) return;
      mesh.frustumCulled = false;
      mesh.castShadow = true;
    });
  }

  update(dt, flyer, throttle, preview) {
    const climbing = flyer.verticalSpeed > flyer.climbSpeed * 0.13;
    const accelerating = throttle > 0 && flyer.speed < flyer.maxSpeed * 0.84;
    const glidingDown = flyer.verticalSpeed < -flyer.climbSpeed * 0.13;
    const state = preview >= 0 ? preview : climbing || accelerating ? 0 : glidingDown ? 2 : 1;
    this.actions.forEach((action, index) => {
      this.weights[index] = damp(this.weights[index], Number(index === state), 9, dt);
      action.setEffectiveWeight(this.weights[index]);
    });
    this.mixer.update(dt);
    this.root.visible = flyer.actualDistance > 0.72 * flyer.unit;
    this.root.position.copy(flyer.position);
    this.root.rotation.set(
      -0.16 * clamp(flyer.verticalSpeed / flyer.climbSpeed, -1, 1),
      flyer.yaw + Math.PI, // The beak faces +Z in the supplied GLB.
      -flyer.bank,
      "YXZ",
    );
    this.pose.scale.setScalar(this.spanScale * flyer.unit);
    // Cancel the authored movement of the body anchor while keeping the wing,
    // head and body posing. The controller and camera have no bone dependency.
    this.pose.position.set(0, 0, 0);
    this.anchor.getWorldPosition(this.center);
    this.root.worldToLocal(this.center);
    this.pose.position.copy(this.center).negate();
  }

  detach() {
    this.root.removeFromParent();
  }

  dispose() {
    this.detach();
    this.mixer.stopAllAction();
    this.mixer.uncacheRoot(this.model);
    releaseModel(this.model, this.disposeResources);
  }
}
