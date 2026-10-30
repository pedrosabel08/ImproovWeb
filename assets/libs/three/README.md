# Decoder EXR do Three.js r180

EXRLoader e fflate são cópias da versão **0.180.0** já utilizada pelo import map
do Prédio 3D, para permitir decodificação em module worker (workers não herdam o
import map do documento). Única adaptação em EXRLoader: o import de `three` usa a
mesma URL absoluta do import map. Não atualizar separadamente da versão do viewer.

Origens:

- https://cdn.jsdelivr.net/npm/three@0.180.0/examples/jsm/loaders/EXRLoader.js
- https://cdn.jsdelivr.net/npm/three@0.180.0/examples/jsm/libs/fflate.module.js
- https://github.com/mrdoob/three.js/tree/r180

Os avisos e licenças originais BSD/MIT estão preservados nos arquivos. O worker
reutiliza esta implementação; não existe outro decoder de EXR na aplicação.
