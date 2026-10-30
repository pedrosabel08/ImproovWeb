# Céu HDRI e iluminação do Prédio 3D

## Fluxo anterior e separação final

O coordenador `solar-environment.js` já isolava a DirectionalLight e a LightProbe.
Entretanto, `procedural-sky.js` compunha gradiente procedural e EXR normalizado por
sua média (`sky.hdriContribution`); em ortográfica, usava `screen.y * 0.75 + 0.10`
para o horizonte. O Hemisphere obtinha suas cores das médias do EXR e escolhia
intensidade pelo preset HDRI, inclusive no override manual.

Agora as responsabilidades são:

```text
SunCalc / Luxon / Norte → SolarLighting → DirectionalLight → luz direta e sombras
Atmosfera procedural → LightProbe + Hemisphere → iluminação ambiente do prédio
HDRI → céu visual + PMREM local → reflexos nos materiais elegíveis
```

- `hdri-sky.js`: fundo exclusivamente EXR, sem gradiente, atmosfera, cor solar ou
  iluminação. Faz amostragem equiretangular mundial, calibração por arquivo e
  crossfade linear antes do tone mapping existente.
- `sky-projection.js`: interseção dos raios reais da câmera com uma esfera fixa na
  origem mundial. Usa `projectionMatrixInverse` e `matrixWorld`, inclusive para
  ortográfica, zoom, pan, roll, aspect e frustum assimétrico. A câmera do modelo
  continua ortográfica; não se inventa um FOV para ela.
- `procedural-atmosphere.js`: campo atmosférico técnico sem samplers HDRI e sem
  disco solar. O shader anterior de iluminação foi preservado. Seu cubemap
  alimenta somente a LightProbe. O Hemisphere integra o mesmo campo em CPU
  (192 direções uniformes em ângulo sólido por atualização solar, nunca por frame).
- `procedural-light-probe.js`: mesma captura half-float 32², SH, debounce de
  200 ms, thresholds de 1°/2°, cache de 32 capturas e leitura GPU assíncrona.
- `solar-environment.js`: coordena cache e carregamento; não escreve no Sol.
  Alterar HDRI manualmente não agenda probe nem altera o fill.
- `material-reflections.js`: PMREM local somente em vidro/água/metal e materiais
  elegíveis pelos critérios já existentes. O hook continua zerando exclusivamente
  `getIBLIrradiance`, preservando o termo especular e o ShaderChunk global. A
  rotação horizontal do panorama também orienta seus reflexos; a orientação
  original do material é restaurada ao sair do estudo solar.
- `solar-lighting.js`: mantém SunCalc, Luxon, latitude, longitude, data, horário,
  Norte, cor solar, intensidade, sombras, marcadores e trajetória existentes.

Compatibilidade do receptor: quando um mesh visível chamado Terreno/Terrain/
Ground/Rua já recebe sombras no GLB, o Shadow Catcher técnico fica oculto. Isso
evita sombras fora do terreno sobre o panorama. O terreno, DirectionalLight,
shadow map e cálculo do frustum são preservados; sem solo visível, o catcher
anterior continua disponível.

O módulo híbrido `procedural-sky.js` foi removido. `hdriContribution`, o horizonte
por coordenada de tela e os presets de Hemisphere vinculados ao EXR foram
removidos. Não foram adicionados controles cromáticos. A dessaturação existente
do fill foi preservada; a configuração cromática da probe, que não era usada,
foi removida. Para visualização noturna, o Hemisphere usa intensidade 0.60 e a probe 0.60;
os valores diurnos continuam 0.06 e 0.20. O reforço esmaece entre -6° e 0° de elevação solar, preservando a curva diurna anterior.

## Horizonte e limite matemático da ortográfica

`worldUp=(0,1,0)` e o equador da esfera em `Y=0` são fixos. A direção usada para
amostrar o panorama é a direção normalizada do ponto de interseção na esfera.
`asin(direction.y)=0` define o equador. O shader de diagnóstico desenha esse
mesmo zero, com espessura antialias em pixels; não posiciona uma linha em CSS.
A visibilidade é calculada analiticamente recortando o círculo do equador pelos
planos laterais e frontal da câmera. Não depende do prédio, de seu pé ou do target.

**Limitação explícita:** uma câmera ortográfica possui raios paralelos; um plano
horizontal infinito não tem linha de fuga finita nessa projeção. Esta implementação
usa uma esfera ambiental de raio finito, não afirma simular um horizonte no
infinito. Seu equador pode apresentar curvatura sob pan/roll ou enquadramentos
muito amplos. O raio técnico `sky.domeRadius=10000` é fixo em unidades mundiais
(metros do GLB), sem recenter/resize baseado em câmera ou pavimento. Ao olhar para
baixo, é esperado visualizar o hemisfério inferior do EXR e perder o equador.
Isso pode deixar o fundo claro e pouco detalhado em enquadramentos ortográficos
próximos: a amostragem cobre uma pequena região angular do panorama.

A navegação não foi limitada para manter a câmera dentro da esfera. Fora dela,
o fundo usa direção mundial como fallback; o painel avisa e desativa a linha do
equador esférico. Modelos muito grandes ou distantes da origem podem exigir
revisão do raio técnico. A câmera e a geometria do GLB não são modificadas.

## Configuração

`environment-config.mjs` mantém os quatro arquivos e a seleção astronômica:
noite até -6°, manhã inicial subindo abaixo de 8°, manhã tardia/dia, e tarde
descendo abaixo de 30°. Nenhum horário civil é usado para escolher o arquivo.

| Parâmetro | Unidade / responsabilidade |
| --- | --- |
| `ENVIRONMENTS.*.horizonOffset` | Graus de latitude do panorama; calibração vertical somente do céu. Todos 0: os arquivos têm o horizonte no equador. |
| `ENVIRONMENTS.*.rotation` | Graus em torno de Y, somente céu e reflexos. Todos 0; não tenta alinhar o hotspot ao Sol. |
| `ENVIRONMENTS.*.exposure` | Multiplicador linear somente do fundo. Dia/manhã/tarde 1; noite 0.15. |
| `sky.domeRadius` | Raio mundial fixo da esfera ambiental, 10000. |
| `sky.transitionMs` | Crossfade de 600 ms; uma troca em curso termina antes de aplicar o último destino pendente. |
| `sky.hotspotThreshold` | Detecção inicial do disco; a região conectada de brilho é reconstruída no worker. |
| `atmosphereLighting` | Intensidade e espalhamento técnicos existentes, separados das opções do céu visual. |
| `hemisphere.intensity / nightScale` | Fill técnico baseado no estado solar, independente do preset HDRI. |

A exposição noturna é necessária porque o arquivo existente tem média linear
superior ~0.39 após reconstrução, semelhante ao diurno (~0.37), apesar de conter estrelas. A correção
é escalar: não altera cores ou iluminação do prédio. PMREM continua usando sua
intensidade especular independente, sem GI global. Os panoramas de manhã/tarde
existentes têm horizontes muito claros; o de tarde não contém o pôr do sol quente
da referência. Mantê-los limita a equivalência estética com a imagem fornecida.
As comparações dos arquivos estão em `output/sky-hdri-qa/source-panoramas.png`.

`processHdri()` detecta o núcleo solar e percorre sua região conectada de
contraste, incluindo raios e halo abaixo do threshold inicial. Reconstrói RGB
linear com a média robusta da mesma latitude (quartis centrais do panorama),
preservando o gradiente do horizonte e a emenda periódica. O residual suave é
limitado a 5% do campo local, sem recorte circular, núcleo ou raios solares.
O antigo raio fixo foi removido: ele deixava anéis ao reconstruir halos largos. EXRs não
são alterados em disco. As médias RGB ainda calculadas pelo decoder são apenas
estatísticas do processamento, sem consumidores na iluminação.

## Carregamento e ciclo de vida

Downloads, decode em worker e PMREM permanecem em cache por renderer. Debounce
180 ms, preload de apenas um vizinho em bytes, retry após 30 s, cancelamento e
proteção contra resultados obsoletos foram preservados. Mover o slider não
recarrega/descarrega EXRs continuamente. Falha preserva o último céu e os últimos
reflexos válidos; antes do primeiro EXR fica o fundo CSS existente.

Por frame são atualizados blends e matrizes. O diagnóstico da câmera atualiza
no máximo a 10 Hz, sem captura SH/PMREM por frame. Descarte libera esfera técnica,
probe, texturas, PMREM, worker, callbacks e timers. Seleção e clones continuam
registrados no gerenciador especular. Durante o estudo, `scene.environment=null`
e intensidade global 0. A navegação sem estudo e o Dashboard preservam o cubemap
técnico grafite anterior, que não é um HDRI.

## Como validar

1. Entre pela URL oficial e abra Ferramentas → Mapa de pavimento → obra com GLB →
   Modelo 3D. Abra Hub de Inspeção → Sol → Ambiente · diagnóstico.
2. Marque **Mostrar horizonte geométrico**. Orbite até uma vista horizontal:
   a linha verde representa o equador mundial. Incline para baixo: ela sobe e
   pode sair. Orbite lateralmente, use ViewCube, pan e zoom: a posição responde
   às matrizes reais. Confira `Horizon visible`, elevação da câmera, offset e
   rotação. Teste Vista superior: horizonte fora da viewport. A linha fica atrás
   da geometria, portanto pode ser ocultada pelo terreno/prédio.
3. Deixe HDRI em Automático e confira quatro estados solares. No CIB_HYP em
   30/10/2026: 06:00 usa Manhã (~6.5°); 12:00 usa Manhã 2; 17:00 usa Tarde;
   20:00 usa Noite. Esses horários são exemplos para essa localização/data.
   Confira Sol baixo/sombras longas, Sol alto/sombras curtas, descida e noite
   com intensidade solar 0. A noite pode deixar o prédio muito escuro.
4. Fixe data/horário e alterne os quatro HDRIs manualmente. Céu/reflexos mudam;
   azimute, elevação, intensidade solar, cores/intensidade do Hemisphere e
   contagem/horário da probe devem continuar idênticos.
5. Mova o slider rapidamente, volte a períodos já carregados e confira cache,
   transição suave e ausência de novas capturas ao orbitar/resize.
6. Valide pavimentos, ambientes, ViewCube, mapa, trajetória, rosa dos ventos e
   navegação sem estudo. Confira desktop, notebook, iPad landscape/portrait e
   mobile. Acessos/metadata continuam sob os helpers centrais existentes.

Testes: `sky_projection_test.mjs`, `solar_environment_cache_test.mjs`,
`solar_environment_test.mjs`, `procedural_light_probe_test.mjs`,
`material_reflections_test.mjs`, `hdri_processing_test.mjs` e
`exr_decoder_test.mjs`, além dos testes solares/pavimentos existentes.
Os testes Three recebem `--three-module=CAMINHO/three.module.js` r180; a probe
aceita `--light-probe-module=CAMINHO/LightProbeGenerator.js` da mesma versão.

Referências técnicas: [câmera ortográfica](https://threejs.org/docs/pages/OrthographicCamera.html)
e [diffuse/specular IBL r180](https://github.com/mrdoob/three.js/blob/r180/src/renderers/shaders/ShaderChunk/envmap_physical_pars_fragment.glsl.js).


## Validação prática em 30/10/2026

Ambiente acessado por `https://improov/ImproovWeb/`, com login de desenvolvimento
real e navegação Ferramentas → Mapa de pavimento → Modelo 3D, no CIB_HYP/P00.
Foram validados 06:00, 12:00, 17:00 e 20:00. À noite a intensidade solar foi 0,
a probe 0.016 e o Hemisphere 0.0048 (0.005 no painel). A comparação dos quatro
HDRIs ao meio-dia manteve azimute 358.4°, elevação 76.7°, Sol 2.76, probe 0.200,
mesma contagem/horário SH e Hemisphere 0.060 com cores idênticas.

O horizonte apareceu em câmera horizontal e saiu ao inclinar para baixo e na
vista superior. Desktop 1920×1080, notebook 1366×768, iPad 1024×768 e 768×1024,
e mobile 390×844 passaram sem overflow horizontal, overflow do diagnóstico ou
sobreposição entre toolbar e ViewCube. Resize e órbita mantiveram a contagem SH.
O checkbox e as leituras também foram conferidos no diagnóstico mobile.

A navegação selecionou Lazer 1, abriu seu mapa com 13 ambientes, selecionou
Piscina Leste e carregou suas duas imagens. O receptor técnico deixou de produzir
sombra além do terreno existente; as sombras reais sobre o GLB continuaram.
Passaram os testes de projeção (187 pontos do equador em poses/zooms distintos),
cache/concorrência/falhas, LightProbe, materiais especulares, processamento dos
quatro EXRs, cálculo solar, 108 combinações de frustum/sombras e navegação/
visibilidade de pavimentos. O teste de metadata passou 830 verificações.

As evidências e os valores lidos da interface estão em `output/sky-hdri-qa/`.
Os horários de captura e contagens desse diretório refletem sessões distintas;
o JSON de comparação ao meio-dia é uma única sessão e horário solar fixo.


## Arquivos desta refatoração

Novos módulos: `hdri-sky.js`, `sky-projection.js`, `procedural-atmosphere.js`
(todos em `MapaPavimento/predio-3d/`). Removido o módulo híbrido
`procedural-sky.js`.

No mesmo diretório, alterados: `environment-config.mjs`, `solar-environment.js`,
`solar-lighting.js`, `material-reflections.js`, `hdri-processing.mjs`,
`exr-decoder.js`, `exr-decoder-worker.js`, `procedural-light-probe.js`
(import da configuração), `viewer-environment.js`,
`visualizador-glb-navegacao.js` (imports), `solar-premium.css` e
`shadow-catcher.js`.

Teste novo: `tests/sky_projection_test.mjs`. Atualizados:
`tests/solar_environment_cache_test.mjs`, `tests/material_reflections_test.mjs`,
`tests/hdri_processing_test.mjs`, `tests/exr_decoder_test.mjs` e
`tests/solar_shadow_test.mjs`. Documentação: este arquivo. Evidências:
`output/sky-hdri-qa/validation.json`, screenshots e panoramas de inspeção.
Os demais arquivos previamente modificados no workspace foram preservados.


Em ajuste posterior, o preenchimento noturno foi reforçado para manter fachadas visíveis: Hemisphere 0.60 e intensidade da probe 0.60, com transição suave entre -6° e 0°, preservando a curva diurna anterior. A exposição visual do HDRI noturno passou a 0.15. O Sol continua desligado abaixo do horizonte; valores históricos das capturas anteriores permanecem como registro.


Na calibração seguinte, a probe noturna passou a reduzir sua saturação gradualmente, preservando a luminância e os coeficientes originais no cache. O Hemisphere noturno passou a 0.60, para melhorar a leitura sem alterar o dia. Foi experimentada a oclusão de ambiente do Three.js r180: acrescentava outra renderização do modelo e apresentou granulação/manchas na câmera ortográfica, por isso o experimento foi retirado. As reentrâncias continuam limitadas pela ausência de sombras produzidas pelo preenchimento ambiente.
