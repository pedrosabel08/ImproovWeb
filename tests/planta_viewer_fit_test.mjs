import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import vm from "node:vm";

const browser = { window: {}, EventTarget };
vm.runInNewContext(readFileSync(new URL("../assets/js/components/planta-viewer.js", import.meta.url), "utf8"), browser);
const Viewer = browser.window.PlantaViewer;
let cases = 0;
const close = (a, b) => assert.ok(Math.abs(a - b) < 1e-7, `${a} != ${b}`);
for (const [viewportWidth, viewportHeight] of [[1528, 684], [1000, 600], [464, 720], [326, 300]]) {
  for (const [pageWidth, pageHeight] of [[600, 1800], [1800, 600], [900, 900]]) {
    for (const rotation of [0, 90, 180, 270]) {
      for (const crop of [null, { x: .1, y: .2, width: .7, height: .6 }]) {
        const viewer = Object.create(Viewer.prototype);
        Object.assign(viewer, {
          page: {}, pageWidth, pageHeight, rotation, crop, area: null, fitPadding: 40,
          container: { clientWidth: viewportWidth, clientHeight: viewportHeight,
            getBoundingClientRect: () => ({ left: 20, top: 60, width: viewportWidth, height: viewportHeight }) },
          surface: { style: {}, getBoundingClientRect: () => ({ left: 20 + viewer.x, top: 60 + viewer.y, width: viewer.width * viewer.scale, height: viewer.height * viewer.scale }) },
          svg: { setAttribute() {} }, transform() {},
        });
        viewer.updateDisplayGeometry();
        viewer.fit();
        const margin = Math.min(40, Math.max(12, Math.min(viewportWidth, viewportHeight) * .08));
        assert.ok(viewer.x >= margin - 1e-7 && viewer.y >= margin - 1e-7, "O Fit contém a página com margem confortável.");
        close(viewer.width * viewer.scale + viewer.x * 2, viewportWidth);
        close(viewer.height * viewer.scale + viewer.y * 2, viewportHeight);
        assert.ok(Math.min(viewer.x, viewer.y) <= margin + 1e-7, "O Fit aproveita ao máximo pelo menos um eixo.");
        assert.equal(viewer.rotation, rotation, "Enquadrar preserva a orientação escolhida.");
        const point = [.25, .75], screen = viewer.toScreen(point);
        viewer.toPoint(...screen).forEach((value, i) => close(value, point[i]));
        viewer.zoomAt(...screen, 1.25);
        viewer.toScreen(point).forEach((value, i) => close(value, screen[i]));
        viewer.toPoint(...screen).forEach((value, i) => close(value, point[i]));
        viewer.reset();
        assert.equal(viewer.fitted, true);
        cases++;
      }
    }
  }
}
console.log(`OK: ${cases} combinações de viewport, orientação, rotação e recorte; Fit, reset, zoom ancorado e coordenadas preservados.`);
