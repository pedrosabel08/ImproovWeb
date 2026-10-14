import assert from "node:assert/strict";
import { pathToFileURL } from "node:url";
import { resolve } from "node:path";
import { loadViewerModule } from "./viewer_module_loader.mjs";
const modulePath = process.argv.find(arg => arg.startsWith("--three-module="))?.slice("--three-module=".length);
if (!modulePath) throw new Error("Use --three-module=CAMINHO/three.module.js (Three.js r180).");
const threeURL = pathToFileURL(resolve(modulePath)).href;
const THREE = await import(threeURL);
const { fitSolarShadow, boxCorners } = await loadViewerModule("solar-shadow.js", threeURL);
const { SolarLighting } = await loadViewerModule("solar-lighting.js", threeURL);
const { solarDirection } = await import("../MapaPavimento/predio-3d/solar-position.mjs");
let cases=0;
for (const scale of [.1,1,100]) for (const north of [0,83.9,270]) for (const azimuth of [0,74,277]) for (const altitude of [.01,30,70,90]) {
 const bounds = new THREE.Box3(new THREE.Vector3(123*scale,30*scale,-40*scale),new THREE.Vector3(153*scale,140*scale,10*scale));
 const center = bounds.getCenter(new THREE.Vector3()), radius=bounds.getSize(new THREE.Vector3()).length()/2;
 const direction=solarDirection(azimuth,altitude*Math.PI/180,north);
 const receiver=new THREE.Mesh(new THREE.PlaneGeometry(1,1));
 const state={bounds,center,radius,receiver};
 SolarLighting.prototype.fitReceiver.call(state,direction);
 const light = new THREE.DirectionalLight();
 light.position.copy(center).addScaledVector(new THREE.Vector3(direction.x,direction.y,direction.z),radius*8);
 light.target.position.copy(center);
 light.shadow.mapSize.set(2048,2048);
 fitSolarShadow(light,bounds,state.receiverBounds);
 // Reproduz a atualização pública executada pelo renderizador de sombras de r180.
 light.shadow.updateMatrices(light);
 const camera = light.shadow.camera;
 const matrix = new THREE.Matrix4().multiplyMatrices(camera.projectionMatrix,camera.matrixWorldInverse);
 for(const point of [...boxCorners(bounds),...boxCorners(state.receiverBounds)]) {
   const projected=point.clone().applyMatrix4(matrix);
   assert.ok(Math.abs(projected.x)<=1.000001 && Math.abs(projected.y)<=1.000001 && Math.abs(projected.z)<=1.000001, 'Frustum cortou prédio ou receptor.');
 }
 assert.ok(camera.near>0 && camera.far>camera.near);
 if(altitude>=30) for(const corner of boxCorners(bounds)) {
   const travel=(corner.y-state.receiverBounds.min.y)/direction.y;
   const foot=corner.clone().addScaledVector(new THREE.Vector3(direction.x,direction.y,direction.z),-travel);
   foot.y=state.receiverBounds.min.y;
   assert.ok(state.receiverBounds.containsPoint(foot),'Receptor cortou sombra nos horários de teste.');
 }
 receiver.geometry.dispose(); light.dispose(); cases++;
}
console.log('OK: '+cases+' combinações de escala, Norte, azimute e elevação; frustum cobre modelo/receptor e sombras diurnas cabem no chão.');
