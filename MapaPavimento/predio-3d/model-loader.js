import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { DRACOLoader } from "three/addons/loaders/DRACOLoader.js";

/** Configuração compartilhada pelos viewers do Flow. Um loader por ciclo de vida. */
export function createModelLoader() {
  const draco = new DRACOLoader();
  draco.setDecoderPath(
    "https://www.gstatic.com/draco/versioned/decoders/1.5.7/",
  );
  const loader = new GLTFLoader().setDRACOLoader(draco);
  return { loader, dispose: () => draco.dispose() };
}
