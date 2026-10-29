// Fixed observation point: drag to look, without translating through the model.
export function setupPersonCamera({ THREE, renderer, scene, camera, controls, viewport, getMeshes, available }) {
  const style = document.createElement('style');
  style.textContent = `.person-camera{position:absolute;bottom:55px;left:12px;z-index:8;display:flex;gap:8px;align-items:center;flex-wrap:wrap;max-width:calc(100% - 24px);padding:8px;border-radius:10px;background:var(--panel,#18212b);color:var(--text,#eee);border:1px solid var(--line,#52606b);font:12px system-ui}.person-camera button,.person-camera input{font:inherit;color:inherit;background:transparent;border:1px solid #78838b;border-radius:6px;padding:6px}.person-camera button{cursor:pointer}.person-camera button[aria-pressed=true]{background:#276b58}.person-camera input{width:65px}.person-camera span{max-width:240px}.person-camera svg{vertical-align:middle;width:22px;height:22px}.person-camera-active canvas{touch-action:none;cursor:crosshair}`;
  document.head.append(style);
  const panel = document.createElement('div');
  panel.className = 'person-camera';
  panel.innerHTML = `<button type="button" aria-label="Posicionar câmera" title="Posicionar câmera" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><circle cx="12" cy="4" r="3"/><path d="M8 8h8l2 7-2 .6-1-4V22h-2v-7h-2v7H9V11.6l-1 4L6 15z"/></svg></button><label hidden>Altura (m) <input type="number" min="0.1" max="20" step="0.1" value="1.6" aria-label="Altura da câmera em metros"></label><span hidden role="status"></span>`;
  viewport.append(panel);
  const button = panel.querySelector('button'), label = panel.querySelector('label'), input = panel.querySelector('input'), status = panel.querySelector('span');
  const eye = new THREE.PerspectiveCamera(60, 1, .01, camera.far);
  const ray = new THREE.Raycaster(), pointer = new THREE.Vector2();
  let enabled = false, placed = false, previousEnabled = true, ground = null, yaw = 0, pitch = 0, drag = null;
  const originalRender = renderer.render.bind(renderer);
  function orient() { eye.quaternion.setFromEuler(new THREE.Euler(pitch, yaw, 0, 'YXZ')); }
  function stop() {
    enabled = placed = false; drag = null;
    controls.enabled = previousEnabled;
    button.setAttribute('aria-pressed', 'false'); button.title = 'Posicionar câmera';
    label.hidden = status.hidden = true;
    viewport.classList.remove('person-camera-active');
  }
  renderer.render = (renderScene, renderCamera) => {
    if (enabled && !available()) stop();
    button.disabled = !enabled && !available();
    if (enabled && placed && renderScene === scene) {
      eye.aspect = renderer.domElement.clientWidth / Math.max(renderer.domElement.clientHeight, 1);
      eye.far = camera.far; eye.updateProjectionMatrix();
      originalRender(renderScene, eye);
    } else originalRender(renderScene, renderCamera);
  };
  button.addEventListener('click', () => {
    if (enabled) return stop();
    if (!available()) return;
    enabled = true; previousEnabled = controls.enabled; controls.enabled = false;
    button.setAttribute('aria-pressed', 'true'); button.title = 'Sair da câmera (Esc)';
    label.hidden = status.hidden = false;
    status.textContent = 'Clique no piso para posicionar. Esc para sair.';
    viewport.classList.add('person-camera-active');
  });
  input.addEventListener('input', () => {
    if (ground && input.validity.valid && input.value !== '') eye.position.copy(ground).add(new THREE.Vector3(0, Number(input.value), 0));
  });
  const canvas = renderer.domElement;
  canvas.addEventListener('pointerdown', event => {
    if (!enabled) return;
    event.stopImmediatePropagation(); event.preventDefault();
    canvas.setPointerCapture(event.pointerId);
    drag = { x: event.clientX, y: event.clientY, id: event.pointerId };
    if (placed) return;
    const rect = canvas.getBoundingClientRect();
    pointer.set((event.clientX - rect.left) / rect.width * 2 - 1, -(event.clientY - rect.top) / rect.height * 2 + 1);
    ray.setFromCamera(pointer, camera);
    const hit = ray.intersectObjects(getMeshes(), false).find(hit => {
      let object = hit.object;
      while (object) { if (!object.visible) return false; object = object.parent; }
      return !hit.object.userData.isShadowCatcher && hit.face && hit.face.normal.clone().transformDirection(hit.object.matrixWorld).y > .65;
    });
    if (!hit) { status.textContent = 'Escolha uma superfície horizontal do piso.'; return; }
    ground = hit.point.clone(); eye.position.copy(ground).add(new THREE.Vector3(0, Number(input.value) || 1.6, 0));
    const direction = camera.getWorldDirection(new THREE.Vector3());
    yaw = Math.atan2(-direction.x, -direction.z); pitch = 0; orient(); placed = true;
    status.textContent = 'Arraste para olhar ao redor. Esc ou pessoa para sair.';
  }, true);
  canvas.addEventListener('pointermove', event => {
    if (!enabled) return;
    event.stopImmediatePropagation();
    if (!placed || !drag || drag.id !== event.pointerId) return;
    yaw -= (event.clientX - drag.x) * .004;
    pitch = THREE.MathUtils.clamp(pitch - (event.clientY - drag.y) * .004, -Math.PI / 2 + .02, Math.PI / 2 - .02);
    drag.x = event.clientX; drag.y = event.clientY; orient();
  }, true);
  for (const name of ['pointerup', 'pointercancel']) canvas.addEventListener(name, event => {
    if (!enabled) return;
    event.stopImmediatePropagation(); drag = null;
    if (canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
  }, true);
  canvas.addEventListener('wheel', event => { if (enabled) { event.preventDefault(); event.stopImmediatePropagation(); } }, { capture: true, passive: false });
  window.addEventListener('keydown', event => { if (enabled && event.key === 'Escape') { event.stopImmediatePropagation(); stop(); } }, true);
}
