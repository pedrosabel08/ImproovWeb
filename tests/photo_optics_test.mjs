import assert from "node:assert/strict";
import { frameSize, frameProjection, entranceFocal, gate } from "../MapaPavimento/predio-3d/photo-optics.mjs";
for(const viewport of [16/9,21/9,390/720,1]) for(const ratio of [4/5,16/9,9/16,1,2.1]) {
  const lens=entranceFocal(62,1,viewport,ratio);
  assert.ok(Math.abs(frameProjection(lens,ratio,viewport)-62)<1e-10);
  const s=frameSize(viewport*1000,1000,ratio);
  assert.ok(Math.abs(s.width/s.height-ratio)<1e-12);
  assert.ok(Math.abs(gate(ratio).width/gate(ratio).height-ratio)<1e-12);
  const screen=Math.tan(frameProjection(24,ratio,viewport)*Math.PI/360)*s.height/1000;
  assert.ok(Math.abs(screen-Math.tan(frameProjection(24,ratio,ratio)*Math.PI/360))<1e-12);
}
console.log("20 combinações: perspectiva de entrada, máscara e projeção da captura coincidem.");
