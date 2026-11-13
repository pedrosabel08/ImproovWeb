// Offline only. Usage: node tools/convert-flow-josh.mjs /path/to/three
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const three = pathToFileURL(resolve(process.argv[2] || 'node_modules/three') + '/');
const load = path => import(new URL(path, three));
const T = await load('build/three.module.js');
assert.equal(T.REVISION, '180', 'Use the same Three.js release as the viewer.');
const { FBXLoader } = await load('examples/jsm/loaders/FBXLoader.js');
const { GLTFExporter } = await load('examples/jsm/exporters/GLTFExporter.js');
const { mergeGeometries, mergeVertices } = await load('examples/jsm/utils/BufferGeometryUtils.js');
const assets = fileURLToPath(new URL('../assets/person/', import.meta.url));
const temp = mkdtempSync(join(tmpdir(), 'flow-josh-'));
const images = new Map();
globalThis.window = { URL: { createObjectURL(blob) {
  const id = `blob:mixamo/${images.size}`;
  images.set(id, blob);
  return id;
} } };
globalThis.document = { createElementNS(_, tag) {
  assert.equal(tag, 'img');
  const handlers = new Map();
  return {
    addEventListener(name, callback) { handlers.set(name, callback); },
    removeEventListener(name) { handlers.delete(name); },
    set src(url) { this.url = url; handlers.get('load').call(this); },
    get src() { return this.url; },
  };
} };
globalThis.FileReader = class {
  async readAsArrayBuffer(blob) {
    this.result = await blob.arrayBuffer();
    // GLTFExporter installs its completion handler after calling readAsArrayBuffer.
    this.onloadend();
  }
};

const readFBX = name => new FBXLoader().parse(Uint8Array.from(readFileSync(join(assets, `${name}.fbx`))).buffer, '');
const bonesOf = root => {
  const bones = new Map();
  root.traverse(bone => {
    // FBXLoader adds identity children with the same name for additional skin clusters.
    if (!bone.isBone || bone.parent?.name === bone.name) return;
    assert(!bones.has(bone.name), `Duplicate bone: ${bone.name}`);
    bones.set(bone.name, bone);
  });
  return bones;
};

try {
  const josh = readFBX('Josh');
  const rig = bonesOf(josh), bones = [...rig.values()];
  assert.equal(bones.length, 65);
  const hips = rig.get('mixamorigHips');
  const avatar = new T.Group();
  avatar.name = 'Josh';
  josh.updateMatrixWorld(true);
  const skeleton = new T.Skeleton(bones);
  const meshGroups = new Map();
  josh.traverse(o => {
    if (!o.isSkinnedMesh) return;
    assert(o.bindMatrix.equals(new T.Matrix4()) && o.matrixWorld.equals(new T.Matrix4()));
    const geometry = o.geometry.clone(), indices = geometry.attributes.skinIndex;
    const mapping = o.skeleton.bones.map((bone, i) => {
      const index = bones.indexOf(rig.get(bone.name));
      assert(index >= 0);
      const inverse = skeleton.boneInverses[index];
      assert(inverse.elements.every((value, k) => Math.abs(value - o.skeleton.boneInverses[i].elements[k]) < .001), `Bind pose differs: ${o.name}/${bone.name}`);
      return index;
    });
    for (let i = 0; i < indices.array.length; i++) indices.array[i] = mapping[indices.array[i]];
    if (!meshGroups.has(o.material)) meshGroups.set(o.material, []);
    meshGroups.get(o.material).push(geometry);
  });
  // Remove cluster copies only after all joint indices and inverse binds have been verified.
  const duplicates = [];
  hips.traverse(b => { if (b.isBone && b.parent?.name === b.name) duplicates.push(b); });
  duplicates.forEach(b => b.removeFromParent());
  avatar.add(hips);
  const clips = ['Idle', 'Walking', 'Running'].map(name => {
    const source = readFBX(name), sourceRig = bonesOf(source);
    assert.equal(sourceRig.size, rig.size);
    for (const [key, target] of rig) {
      const bone = sourceRig.get(key);
      assert(bone && (target.parent.isBone ? target.parent.name : null) === (bone.parent.isBone ? bone.parent.name : null));
      assert(target === hips || target.position.distanceTo(bone.position) < .01, `Bone lengths differ: ${key}`);
    }
    const [clip] = source.animations;
    assert(clip?.duration > 0 && clip.tracks.length === 53);
    clip.name = name;
    const q = new T.Quaternion();
    for (const track of clip.tracks) {
      const { nodeName, propertyName } = T.PropertyBinding.parseTrackName(track.name);
      const target = rig.get(nodeName), bone = sourceRig.get(nodeName);
      assert(target && track.validate());
      if (propertyName === 'quaternion') {
        const correction = target.quaternion.clone().multiply(bone.quaternion.clone().invert());
        for (let i = 0; i < track.values.length; i += 4)
          q.fromArray(track.values, i).premultiply(correction).normalize().toArray(track.values, i);
      } else {
        assert(target === hips && propertyName === 'position');
        // Navigation owns horizontal motion. Retain the animation's vertical hip motion.
        for (let i = 0; i < track.values.length; i += 3) {
          track.values[i] = hips.position.x;
          track.values[i + 1] += hips.position.y - bone.position.y;
          track.values[i + 2] = hips.position.z;
        }
      }
    }
    return clip.optimize();
  });

  const textureFiles = new Map();
  for (const [id, blob] of images) {
    assert.equal(blob.type, 'image/png');
    const file = join(temp, `${textureFiles.size}.png`);
    writeFileSync(file, Buffer.from(await blob.arrayBuffer()));
    textureFiles.set(id, file);
  }
  const used = new Set([...meshGroups.keys()].flatMap(m => Object.values(m).filter(v => v?.isTexture).map(t => t.image.src)));
  const unused = [...textureFiles.keys()].filter(key => !used.has(key));
  // The fifth embedded PNG is Ch23_1001_Glossiness, skipped by FBXLoader.
  assert.equal(unused.length, 1);
  assert(readFileSync(join(assets, 'Josh.fbx')).includes(Buffer.from('Ch23_1001_Glossiness.png')));
  const encoded = new Map();
  function texture(source, type, transform = [], resolution = 2048) {
    const output = join(temp, `${encoded.size}.${type}`);
    execFileSync('magick', [source, '-resize', `${resolution}x${resolution}>`, ...transform,
      ...(type === 'jpg' ? ['-sampling-factor', '4:4:4', '-quality', '91'] : []), '-strip', output]);
    const tex = new T.Texture({ src: output });
    tex.flipY = false;
    tex.userData.mimeType = type === 'jpg' ? 'image/jpeg' : 'image/png';
    encoded.set(tex.image, output);
    return tex;
  }
  const roughnessMaps = new Map();
  for (const [source, geometries] of meshGroups) {
    const hair = !!source.alphaMap;
    assert(!hair || source.map.image.src === source.alphaMap.image.src, 'Hair must carry its alpha in the diffuse PNG.');
    const material = new T.MeshPhysicalMaterial({
      name: source.name, metalness: 0, roughness: hair ? .8 : 1,
      side: hair ? T.DoubleSide : source.side, alphaTest: hair ? .42 : 0,
      map: texture(textureFiles.get(source.map.image.src), hair ? 'png' : 'jpg', ['-flip'], hair ? 1024 : 2048),
    });
    if (!hair) {
      material.normalMap = texture(textureFiles.get(source.normalMap.image.src), 'jpg', ['-flip']);
      material.normalScale.copy(source.normalScale);
      material.specularColorMap = texture(textureFiles.get(source.specularMap.image.src), 'jpg', ['-flip']);
      roughnessMaps.set(material, texture(textureFiles.get(unused[0]), 'jpg', ['-flip', '-colorspace', 'gray', '-negate', '-type', 'TrueColor']));
    }
    material.map.colorSpace = T.SRGBColorSpace;
    if (material.specularColorMap) material.specularColorMap.colorSpace = T.SRGBColorSpace;
    const mesh = new T.SkinnedMesh(mergeVertices(mergeGeometries(geometries)), material);
    mesh.name = hair ? 'Josh_Hair' : 'Josh_Body';
    mesh.bind(skeleton, new T.Matrix4());
    mesh.normalizeSkinWeights();
    avatar.add(mesh);
  }
  const bounds = new T.Box3().setFromObject(avatar, true);
  assert(Math.abs(bounds.max.y - bounds.min.y - 179.275) < .1);
  const mixer = new T.AnimationMixer(avatar);
  for (const clip of clips) {
    const action = mixer.clipAction(clip).play();
    let low = Infinity, high = -Infinity;
    for (let i = 0; i < 12; i++) {
      mixer.setTime(clip.duration * i / 12);
      avatar.updateMatrixWorld(true);
      const pose = new T.Box3().setFromObject(avatar, true);
      low = Math.min(low, pose.min.y); high = Math.max(high, pose.min.y);
      assert(pose.max.y > 145 && pose.max.y < 190 && pose.min.y > -15, `${clip.name}: implausible skin pose`);
    }
    mixer.stopAllAction();
    console.log(`${clip.name}: ${clip.duration.toFixed(3)} s; lowest sole ${low.toFixed(2)}–${high.toFixed(2)} cm`);
    // Calibrate the floor contact without removing airborne phases in the running clip.
    const translation = clip.tracks.find(t => t.name === `${hips.name}.position`);
    for (let i = 1; i < translation.values.length; i += 3) translation.values[i] -= low;
  }
  const scene = new T.Scene();
  const metres = new T.Group();
  metres.name = 'Josh_Metres';
  metres.scale.setScalar(.01); // FBX positions and bind matrices use centimetres.
  metres.add(avatar); scene.add(metres);
  const exporter = new GLTFExporter().register(writer => {
    // Embed the prepared texture bytes directly, without a DOM or a canvas dependency.
    const indices = new Map();
    writer.processImage = (image, _format, _flipY, mimeType) => {
      if (indices.has(image)) return indices.get(image);
      const definition = { mimeType };
      const index = (writer.json.images ||= []).push(definition) - 1;
      indices.set(image, index);
      writer.pending.push(writer.processBufferViewImage(new Blob([readFileSync(encoded.get(image))], { type: mimeType }))
        .then(view => { definition.bufferView = view; }));
      return index;
    };
    return {
      async writeMaterialAsync(material, definition) {
        const roughness = roughnessMaps.get(material);
        if (roughness) definition.pbrMetallicRoughness.metallicRoughnessTexture = { index: await writer.processTextureAsync(roughness) };
      },
      afterParse() {
        // The exporter writes one skin record per mesh, even with a shared Skeleton.
        const { nodes, skins } = writer.json;
        assert(skins.every(skin => skin.joints.join() === skins[0].joints.join()));
        for (const node of nodes) if (node.skin != null) node.skin = 0;
        skins.length = 1;
      },
    };
  });
  const glb = await exporter.parseAsync(scene, { binary: true, animations: clips });
  writeFileSync(join(assets, 'Josh.glb'), Buffer.from(glb));
  console.log(`Josh.glb: ${glb.byteLength} bytes; ${bones.length} bones; ${meshGroups.size} meshes`);
} finally {
  rmSync(temp, { recursive: true, force: true });
}
