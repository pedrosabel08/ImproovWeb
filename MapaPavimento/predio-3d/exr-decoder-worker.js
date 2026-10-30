import { EXRLoader } from "../../assets/libs/three/loaders/EXRLoader.js";
import { processHdri } from "./hdri-processing.mjs?v=20261030-horizon-final";
import {
  DataUtils,
  FloatType,
  HalfFloatType,
} from "https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js";

// EXR é CPU intensivo. Este worker não possui renderer, luzes ou estado da cena.
const loader = new EXRLoader().setDataType(FloatType);
self.onmessage = ({ data: { id, buffer, maxLuminance, sky } }) => {
  try {
    const started = performance.now();
    const data = loader.parse(buffer);
    const { pixels, hemisphere, skyAverage, removedHotspots } = processHdri(
      data,
      { maxLuminance, sky },
      DataUtils,
    );
    // Somente pixels e metadata de textura; o header completo do EXR é dispensado.
    self.postMessage(
      {
        id,
        result: {
          data: pixels,
          width: data.width,
          height: data.height,
          format: data.format,
          type: HalfFloatType,
          colorSpace: data.colorSpace,
          flipY: false,
          hemisphere,
          skyAverage,
          removedHotspots,
          decodeMs: performance.now() - started,
        },
      },
      [pixels.buffer],
    );
  } catch (error) {
    self.postMessage({ id, error: error.message });
  }
};
