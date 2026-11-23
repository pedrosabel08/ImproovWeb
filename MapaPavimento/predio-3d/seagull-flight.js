import * as THREE from "three";

const { clamp, damp, euclideanModulo } = THREE.MathUtils;
const radians = (angle) => euclideanModulo(angle + Math.PI, 2 * Math.PI) - Math.PI;
const travel = (current, goal, response, dt) =>
  goal * dt + (current - goal) * -Math.expm1(-response * dt) / response;

export class SeagullFlight {
  constructor({ bounds, group, collision, orbitCamera, orbitTarget, maxSpeed = 24 }) {
    this.bounds = bounds;
    this.collision = collision;
    this.orbitCamera = orbitCamera;
    const size = bounds.getSize(new THREE.Vector3());
    this.unit = clamp(size.length() / 12, 0.04, 1);
    this.radius = 0.32 * this.unit;
    this.clearance = 0.55 * this.unit;
    this.maxSpeed = maxSpeed * this.unit;
    this.climbSpeed = 9 * this.unit;
    this.distance = 5.2 * this.unit;
    this.pitch = this.lookPitch = 0.27;
    this.speed = this.verticalSpeed = this.bank = 0;
    this.altitudeTarget = null;
    this.position = new THREE.Vector3();
    this.candidate = new THREE.Vector3();
    this.eyeOffset = new THREE.Vector3();
    this.desiredCamera = new THREE.Vector3();
    this.camera = new THREE.PerspectiveCamera(58, 1, 0.055 * this.unit, orbitCamera.far);
    this.ceiling = bounds.max.y + Math.max(size.y * 0.35, 25 * this.unit);
    this.fallbackGround = bounds.min.y;
    const perimeter = group?.userData.geoBounds;
    if (perimeter) {
      this.site = perimeter;
      this.fromWorld = group.matrixWorld.clone().invert();
      this.sitePoint = new THREE.Vector3();
    } else {
      const margin = Math.max(20 * this.unit, Math.max(size.x, size.z) * 0.55);
      this.horizontal = bounds.clone().expandByScalar(margin);
    }
    const view = new THREE.Vector3().subVectors(orbitCamera.position, orbitTarget);
    this.entryYaw = view.setY(0).lengthSq() > 1e-6 ? Math.atan2(view.x, view.z) : 0;
    this.entryHeight = orbitTarget.y;
  }

  spawn(saved) {
    if (saved && this.canOccupy(saved.position)) {
      this.position.copy(saved.position);
      this.yaw = this.lookYaw = saved.yaw;
      this.pitch = this.lookPitch = saved.pitch;
      this.distance = saved.distance;
      this.updateCamera(0);
      return true;
    }

    const center = this.bounds.getCenter(new THREE.Vector3());
    const half = this.bounds.getSize(new THREE.Vector3()).multiplyScalar(0.5);
    for (const height of [this.entryHeight, this.bounds.max.y + 4 * this.unit, this.ceiling]) {
      for (const angle of [0, Math.PI / 4, -Math.PI / 4, Math.PI / 2, -Math.PI / 2, Math.PI]) {
        const yaw = this.entryYaw + angle;
        const x = Math.sin(yaw), z = Math.cos(yaw);
        const edge = Math.min(half.x / Math.max(Math.abs(x), 1e-6), half.z / Math.max(Math.abs(z), 1e-6));
        const radius = edge + Math.max(half.x * 0.2, half.z * 0.2, 6 * this.unit);
        const point = this.position.set(center.x + x * radius, height, center.z + z * radius);
        if (!this.inside(point)) continue;
        point.y = Math.max(height, this.ground(point) + 3 * this.unit);
        if (!this.canOccupy(point)) continue;
        this.yaw = this.lookYaw = yaw;
        this.updateCamera(0);
        return true;
      }
    }
    return false;
  }

  snapshot() {
    return {
      position: this.position.clone(),
      yaw: this.yaw,
      pitch: this.pitch,
      distance: this.distance,
    };
  }

  inside(point) {
    if (this.site) {
      const local = this.sitePoint.copy(point).applyMatrix4(this.fromWorld);
      const { x, z } = this.site.toEN(local);
      return Math.max(Math.abs(x), Math.abs(z)) <= this.site.radius - this.radius;
    }
    const { min, max } = this.horizontal;
    return point.x >= min.x && point.x <= max.x && point.z >= min.z && point.z <= max.z;
  }

  ground(point) {
    return this.collision.groundHeight(point.x, point.z, this.fallbackGround);
  }

  canOccupy(point, start = point) {
    return point.y <= this.ceiling &&
      this.inside(point) &&
      point.y >= this.ground(point) + this.clearance &&
      !this.collision.flightBlocked(start, point, this.radius);
  }

  look(dx, dy) {
    this.lookYaw -= dx * 0.004;
    this.lookPitch = clamp(this.lookPitch + dy * 0.004, -0.28, 1.12);
  }

  zoom(delta) {
    this.distance = clamp(this.distance * Math.exp(Math.sign(delta) * 0.11), 2.4 * this.unit, 15 * this.unit);
  }

  goToFloor(floor) {
    const { min, max } = floor.bounds;
    this.altitudeTarget = Math.min(min.y + Math.min(1.5 * this.unit, (max.y - min.y) / 2), this.ceiling);
  }

  move(dx, dy, dz) {
    this.candidate.copy(this.position);
    this.candidate.x += dx;
    this.candidate.y += dy;
    this.candidate.z += dz;
    if (!this.canOccupy(this.candidate, this.position)) return false;
    this.position.copy(this.candidate);
    return true;
  }

  update(dt, throttle, steer, lift) {
    this.lookYaw -= steer * 1.65 * dt;
    const turn = radians(this.lookYaw - this.yaw);
    const yawStep = damp(0, turn, 9, dt);
    const heading = this.yaw + yawStep * 0.5;
    this.yaw = radians(this.yaw + yawStep);
    this.bank = damp(this.bank, clamp(turn * 0.9, -0.38, 0.38), 7, dt);
    const speed = throttle * this.maxSpeed * (throttle < 0 ? 0.35 : 1);
    const response = throttle ? 1.9 : 2.8;
    const distance = travel(this.speed, speed, response, dt);
    this.speed = damp(this.speed, speed, response, dt);
    if (throttle || steer || lift) this.altitudeTarget = null;
    const target = this.altitudeTarget;
    const error = target == null ? 0 : target - this.position.y;
    const climb = target == null
      ? lift * this.climbSpeed
      : clamp(error * 2, -this.climbSpeed, this.climbSpeed);
    let dy = travel(this.verticalSpeed, climb, 5, dt);
    this.verticalSpeed = damp(this.verticalSpeed, climb, 5, dt);
    if (target != null && dy * error > 0 && Math.abs(dy) > Math.abs(error)) dy = error;
    const dx = -Math.sin(heading) * distance;
    const dz = -Math.cos(heading) * distance;
    const steps = Math.max(1, Math.ceil(Math.hypot(dx, dz, dy) / (0.36 * this.unit)));
    let result = null;
    for (let i = 0; i < steps; i++) {
      if (Math.abs(dy) > 1e-7 && !this.move(0, dy / steps, 0)) {
        this.verticalSpeed = dy = 0;
        if (target != null) result = "blocked";
      }
      if (Math.hypot(dx, dz) > 1e-7 && !this.move(dx / steps, 0, dz / steps)) {
        // Continue alongside a façade when only one axis is obstructed.
        const x = dx !== 0 && this.move(dx / steps, 0, 0);
        const z = dz !== 0 && this.move(0, 0, dz / steps);
        if (!x && !z) this.speed = 0;
      }
    }
    if (!result && target != null && Math.abs(this.position.y - target) <= 0.045 * this.unit) {
      this.verticalSpeed = 0;
      result = "arrived";
    }
    if (result) this.altitudeTarget = null;
    this.updateCamera(dt);
    return result;
  }

  updateCamera(dt) {
    const { camera, position, eyeOffset, desiredCamera } = this;
    const aspect = this.orbitCamera.aspect || 1;
    if (camera.aspect !== aspect) {
      camera.aspect = aspect;
      camera.updateProjectionMatrix();
    }
    this.pitch = damp(this.pitch, this.lookPitch, 9, dt);
    const flat = Math.cos(this.pitch);
    eyeOffset.set(Math.sin(this.yaw) * flat, Math.sin(this.pitch), Math.cos(this.yaw) * flat);
    desiredCamera.copy(position).addScaledVector(eyeOffset, this.distance);
    const safe = this.collision.cameraDistance(
      position, desiredCamera, 0.2 * this.unit, 0.26 * this.unit,
      this.fromWorld ? -Infinity : this.fallbackGround,
    );
    this.actualDistance = this.actualDistance == null || safe < this.actualDistance
      ? safe
      : damp(this.actualDistance, safe, 10, dt);
    camera.position.copy(position).addScaledVector(eyeOffset, this.actualDistance);
    camera.lookAt(position);
    camera.updateMatrixWorld();
  }
}
