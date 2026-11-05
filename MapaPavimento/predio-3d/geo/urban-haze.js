import * as THREE from "three";
import { SKY_PROJECTION_GLSL } from "../sky-projection.js";

// Fog e fade radial temporariamente desligados para comparação visual.
// Para restaurar a implementação abaixo: export const URBAN_FOG_ENABLED = true;
export const URBAN_FOG_ENABLED = false;

/** Only the last 60 m INSIDE the existing square is feathered with screen-door
 * coverage. No global fog, sky sampling, changed colours or expanded footprint. */
export function softenEnvironmentEdge(material, bounds) {
  const north=bounds.toEN({x:0,z:1}),east=bounds.toEN({x:1,z:0});
  const previous=material.onBeforeCompile;
  material.onBeforeCompile=shader=>{
    previous(shader);
    shader.uniforms.edgeBasis={value:new THREE.Vector4(east.x,north.x,east.z,north.z)};
    shader.uniforms.edgeRadius={value:bounds.radius};
    shader.vertexShader="varying vec2 contextXZ;\n"+shader.vertexShader;
    shader.vertexShader=shader.vertexShader.replace("#include <begin_vertex>","#include <begin_vertex>\n contextXZ=position.xz;");
    shader.fragmentShader="varying vec2 contextXZ; uniform vec4 edgeBasis; uniform float edgeRadius;\n"+shader.fragmentShader;
    shader.fragmentShader=shader.fragmentShader.replace("#include <clipping_planes_fragment>",`#include <clipping_planes_fragment>
      vec2 en=vec2(dot(contextXZ,edgeBasis.xy),dot(contextXZ,edgeBasis.zw));
      float coverage=smoothstep(0.0,60.0,edgeRadius-max(abs(en.x),abs(en.y)));
      float threshold=fract(52.9829189*fract(dot(gl_FragCoord.xy,vec2(0.06711056,0.00583715))));
      if(coverage<0.001 || coverage<threshold)discard;`);
  };
  material.customProgramCacheKey=()=>"flow-context-edge-v1";
}

/** Radial world-space fog: works with parallel orthographic rays as well as perspective.
 * Samples the EXISTING sky, including its exposure/crossfade, at the same screen ray.
 * Local materials only; no global fog, light, sky or GLB shader changes. */
export function urbanMaterial(color, { sky, radius, ground = false } = {}) {
  const material = new THREE.MeshStandardMaterial({
    color,
    roughness: 0.94,
    metalness: 0,
    transparent: true,
    side: ground ? THREE.DoubleSide : THREE.FrontSide,
  });
  const uniforms = {
    urbanSkyEnabled: { value: false },
    urbanFade: {
      value: new THREE.Vector2(radius * 0.7, ground ? radius * 1.8 : radius),
    },
    urbanFog: { value: new THREE.Vector2(radius * 0.3, radius * 0.98) },
  };
  material.userData.hazeUniforms = uniforms;
  // Mantém o shader original disponível, sem aplicá-lo enquanto a fog estiver desligada.
  if (!URBAN_FOG_ENABLED) return material;
  material.onBeforeCompile = (shader) => {
    Object.assign(shader.uniforms, sky?.uniforms, uniforms);
    shader.vertexShader =
      "varying vec2 urbanXZ; varying vec4 urbanClip;\n" + shader.vertexShader;
    shader.vertexShader = shader.vertexShader.replace(
      "#include <project_vertex>",
      "#include <project_vertex>\n urbanXZ = position.xz; urbanClip = gl_Position;",
    );
    shader.fragmentShader =
      `varying vec2 urbanXZ; varying vec4 urbanClip;
      uniform vec2 urbanFade, urbanFog; uniform bool urbanSkyEnabled;
      uniform sampler2D hdriCurrent, hdriPrevious;
      uniform vec2 currentCalibration, previousCalibration;
      uniform float hdriBlend, currentExposure, previousExposure;
      ${SKY_PROJECTION_GLSL}
      vec2 urbanUv(vec3 ray, vec2 calibration) {
        return vec2(fract(atan(ray.z,ray.x)/6.28318530718+0.5+calibration.x/6.28318530718),
          clamp(0.5+(asin(clamp(ray.y,-1.0,1.0))+calibration.y)/3.14159265359,0.0,1.0));
      }
      ` + shader.fragmentShader;
    shader.fragmentShader = shader.fragmentShader.replace(
      "#include <fog_fragment>",
      `
      #include <fog_fragment>
      float urbanDistance = length(urbanXZ);
      float urbanVisibility = 1.0-smoothstep(urbanFade.x,urbanFade.y,urbanDistance);
      if (urbanVisibility < 0.001) discard;
      if (urbanSkyEnabled) {
        vec3 ray = worldSkyDirection(urbanClip.xy / urbanClip.w);
        vec3 haze = texture2D(hdriCurrent,urbanUv(ray,currentCalibration)).rgb*currentExposure;
        if (hdriBlend < 1.0) haze = mix(texture2D(hdriPrevious,urbanUv(ray,previousCalibration)).rgb*previousExposure,haze,hdriBlend);
        #if defined(TONE_MAPPING)
          haze = toneMapping(haze);
        #endif
        haze = linearToOutputTexel(vec4(haze,1.0)).rgb;
        gl_FragColor.rgb = mix(gl_FragColor.rgb,haze,smoothstep(urbanFog.x,urbanFog.y,urbanDistance));
      }
      gl_FragColor.a *= urbanVisibility;
    `,
    );
  };
  material.customProgramCacheKey = () => "flow-osm-haze-v1";
  return material;
}
