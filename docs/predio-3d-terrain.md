# Entorno topográfico no visualizador Three.js

A correção posterior de continuidade, margens e precisão numérica está detalhada
em [Artefatos geométricos do entorno topográfico](predio-3d-terrain-artifacts.md).
Ela substitui a projeção independente por um índice dos planos efetivamente
construídos e mantém a fonte DEM e a grade adaptativa descritas abaixo.

## Arquitetura identificada

O entorno plano era construído em `geo/geo-environment.js`: `polygonGeometry`
triangulava os polígonos complementares de terra/mar, com pequenos offsets em
`SURFACE_Y`. `road-builder.js` usava faixas horizontais, `building-builder.js`
extrusões a partir de Y=0 e os builders de vegetação/água polígonos horizontais.
`prepare-environment.js`, coastline e clipping CPU já compartilhavam um quadrado
geográfico de 2 × 2 km. Esses limites e a classificação OSM continuam vigentes.

`geoProjector` converte latitude/longitude para metros no plano tangente e usa
exatamente o Norte salvo do cálculo solar. O grupo OSM é transladado ao centro
X/Z dos pavimentos. O modelo GLB permanece na transformação instalada pelo loader;
o entorno é irmão do modelo na cena, fora de picking, bounds de pavimentos,
enquadramento, frustum solar e medição. O enquadramento e o ajuste de profundidade
ortográfico existentes foram preservados.

Antes de ocultar os objetos Terreno/Terrain/Ground/Rua, o viewer já identificava o
maior por área X/Z. Agora conserva também uma referência a esse objeto para leitura
da geometria; nenhum buffer ou material GLB é escrito. Continua valendo o estado de
visibilidade original do visualizador, que ocultava o terreno exportado.

## Fonte e aquisição

Fonte: [Mapterhorn](https://mapterhorn.com/), endpoint público
`https://tiles.mapterhorn.com/12/{x}/{y}.webp`. Foram verificados em 04/11/2026 o
TileJSON real e o catálogo de fontes/licenças. São tiles XYZ/Web Mercator, 512 px,
codificação Terrarium: `R*256 + G + B/256 - 32768`, em metros. Não há MapLibre,
serviço pago, token, credencial nova ou dependência de produção adicionada.

A [documentação de acesso](https://mapterhorn.com/data-access/) informa o formato;
a [cobertura](https://mapterhorn.com/) informa resolução global de 30 m. O catálogo
`https://download.mapterhorn.com/attribution.json` identifica `glo30` como
COPERNICUS GLO-30 e licença **COPERNICUS full, free and open**. A
[licença oficial](https://documentation.dataspace.copernicus.eu/APIs/SentinelHub/Data/DEM/resources/license/License-COPDEM-30.pdf)
permite reprodução, distribuição e adaptação sem cobrança. As fontes regionais e
seus créditos estão na [atribuição Mapterhorn](https://mapterhorn.com/attribution/).
A interface apresenta o crédito Mapterhorn/Copernicus e o aviso do produtor no
atributo de título, com link para a lista completa. Não se confunde a licença BSD-3
do código Mapterhorn com a licença dos dados.

`api.php?action=dem_tile` reutiliza sessão e autorização da obra. O servidor lê as
coordenadas salvas e aceita apenas X/Y pertencentes ao recorte, com margem de um
pixel para interpolar bordas. Zoom fixo 12; até 16 tiles (normalmente 1–4); sem URL
arbitrária, redirects ou aquisição por vértice. Latitudes |lat| ≥ 84° usam fallback.
O navegador recebe apenas imagens pela API autenticada e não envia cookies ao
Mapterhorn. Requisições upstream têm TLS verificado, timeout de 12 s e limite de
2 MB; dimensões e MIME são conferidos.

Cache compartilhado `cache/dem/`, com arquivos protegidos por guard PHP, gravação
atômica e lock não bloqueante. Validade de 30 dias, cache antigo utilizável quando
a fonte falha e cooldown de 5 minutos para falhas. O cliente mantém até 16 tiles
decodificados em memória por um dia e usa cache HTTP privado. A amostragem local é
bilinear entre centros de pixels, inclusive entre tiles e no antimeridiano.
AbortController, timeout e geração do ambiente impedem instalação de respostas de
outra obra. Falha DEM mantém a maquete plana anterior; falha OSM preserva o GLB.

## Malha e alinhamento vertical

`terrain-surface.js` usa uma única grade não uniforme: passos de 6 m perto da
origem, crescendo continuamente até 36 m. Não há anéis desconectados ou costuras
entre níveis de resolução. As superfícies são cortadas pelas mesmas células e
diagonais; alturas/normais são calculadas em CPU no carregamento, não por frame.
O detalhe adicional interpola o DEM: não transforma dados de 30 m em levantamento
de 6 m. Escala horizontal/vertical em metros, fator vertical **1**.
Na execução real, a grade é preenchida uma única vez, após estimar a água. Tanto
essa etapa quanto a tesselação principal cedem execução aproximadamente a cada
12 ms de trabalho; cancelamento entre etapas impede finalizar uma obra anterior.

`model-ground.js` lê faces superiores em coordenadas mundiais, indexa os triângulos
espacialmente e faz a união das projeções, preservando furos e superfícies
inclinadas. A referência DEM é a altitude na latitude/longitude salva da obra.
A referência do modelo é a altura amostrada nesse ponto sobre a superfície GLB;
se a origem estiver fora dela, usa a altura da borda mais próxima.

```text
Y_entorno = Y_referencia_GLB + altitude_DEM(local) - altitude_DEM(obra)
Y_mar = Y_referencia_GLB - altitude_DEM(obra)
```

A terra DEM é subtraída da projeção do terreno original. As faces superiores
desse terreno são representadas no grupo OSM com a paleta da maquete. Fora da
borda, uma interpolação smoothstep de 45 m conecta sua altura real ao DEM. As
camadas sobre o lote são cortadas também pelos triângulos reais do GLB: uma grade
regular sozinha poderia atravessar mudanças de inclinação e meios-fios.
Sem terreno identificável, o footprint conservador existente protege a edificação
e conecta uma referência de base ao DEM; esse caso é diagnosticado como
`protected-footprint`, sem alegar medição da superfície do GLB.

## Camadas, costa e sombras

- Ruas e vegetação seguem a mesma malha; mantêm materiais, cores e offsets de
  centímetros. Polygon offset reduz conflitos de profundidade entre superfícies.
- Prédios OSM mantêm coberturas rígidas/horizontais. Contornos são subdivididos a
  6 m; a cobertura parte da maior cota de base amostrada, e paredes/fundações
  alcançam o terreno ao longo do contorno. Os volumes sobrepostos ao GLB seguem
  excluídos pelo critério anterior.
- Lagos/polígonos de água usam nível horizontal estimado por percentil baixo das
  margens, separado do DEM bruto. Cursos lineares usam perfil amostrado a 12 m,
  filtro mediano e declive monotônico segundo os extremos. A terra é recortada no
  canal e sua margem ajustada, impedindo que rios estreitos desapareçam entre
  vértices mais espaçados. Não é um modelo hidráulico ou batimétrico.
- O mar conserva os polígonos da coastline e fica horizontal no datum relativo
  acima. Uma faixa costeira de 45 m conecta a borda terrestre ao nível do mar.
- A borda externa recebe cobertura pontilhada gradual nos últimos 60 m **dentro**
  do quadrado existente. Não há fog global, amostragem de céu, geometria externa
  adicional ou mudança da extensão consultada.
- A luz e o mapa de sombras existentes continuam ativos. Quando o entorno está
  presente, o receptor técnico plano deixa de aparecer: as malhas reais já recebem
  essas sombras. Sem entorno, o receptor anterior volta a funcionar.

## Limitações e diagnóstico

O Copernicus é um modelo de superfície: vegetação/edifícios podem influenciar suas
cotas. Não substitui levantamento topográfico do lote, datum geodésico conhecido,
projeto de terraplenagem ou nível de maré. O alinhamento absoluto depende de a
coordenada salva representar o centro geográfico usado pelo visualizador e de o
GLB estar em metros. Não há altitude de levantamento cadastrada atualmente; por
isso a referência geográfica é inferida do DEM e alinhada ao GLB, sem movê-lo.

Água OSM pode ser incompleta; relações/confluências separadas podem ter níveis
estimados distintos. Polígonos de rio extensos são tratados como espelhos de água;
não há hidrologia completa, pontes ou túneis derivados do DEM. Janela oceânica sem
coastline mantém a limitação de classificação anterior. A transição é visual e
local; topologias GLB inválidas geram fallback em vez de deformar o arquivo.

`#viewport.dataset.demState`: `ready` ou `fallback`. `geoMetrics.dem` informa fonte,
tiles/cache, altitude de referência, Y do mar/modelo, resolução da grade e geometria
GLB examinada. `terrainTimes` separa inspeção, grade e água; `buildMs`, triângulos e
meshes continuam disponíveis. Troca/descarte libera geometrias, materiais e créditos.

## Testes reproduzíveis

```text
node tests/geo_terrain_test.mjs --three-module=output/osm-qa/three.module.js
node tests/geo_environment_test.mjs --three-module=output/osm-qa/three.module.js
node tests/geo_clip_coast_test.mjs --three-module=output/osm-qa/three.module.js
php tests/mapa_dem_test.php
node tests/solar_shadow_test.mjs --three-module=output/osm-qa/three.module.js
node tests/solar_position_test.mjs
node tests/floor_navigation_test.mjs --three-module=output/osm-qa/three.module.js
node tests/floor_visibility_test.mjs --three-module=output/osm-qa/three.module.js
php tools/test_page_metadata.php
```

Os testes cobrem relevo acentuado analítico, GLB não coplanar, junção/footprint,
quatro tiles compartilhando uma amostra, Norte, resolução adaptativa, ruas sobre
mudanças de inclinação GLB, fundamentos de prédios, canais estreitos, mar constante,
falha DEM com fallback instalado, autorização regional, cache/cooldown e descarte.
O loader de testes agora conserva `import.meta.url` ao transformar módulos para
data URLs; o mock DOM também implementa o método usado pela mensagem de status.

Resultados da validação prática e arquivos de evidência ficam em
`output/terrain-qa/validation.json`. Nenhuma configuração espacial ou dado de obra
foi alterado para simular relevo.

Validação autenticada em 04/11/2026 pelas duas URLs oficiais, seguindo
Ferramentas → Mapa de pavimento → Modelo 3D:

- Duas obras reais: uma com litoral/encostas e cerca de 197 m de variação DEM no
  recorte; outra costeira, com geometria GLB diferente. Troca e retorno confirmaram
  reuso do tile em memória. A primeira superfície GLB apresentou 561 triângulos
  superiores e desnível de 2,35 m; a segunda, 365 e superfície praticamente plana.
- Entornos com aproximadamente 166 mil triângulos/13 meshes e 117 mil/12 meshes.
  A construção variou com a cena e cessões de execução; o diagnóstico registra
  tempo total de parede, incluindo renderização concorrente, não apenas CPU. Não
  foi realizado benchmark de FPS em hardware intermediário físico.
- Conferidos mar horizontal, encostas, rios, ruas, bases, transição e zoom/ViewCube.
  Seleção de pavimento, ida/volta ao mapa, ambiente com duas imagens, dia/noite e
  entrada/saída da ferramenta de medição continuaram funcionando.
- Desktop 1920×1080, notebook 1366×768, iPad 1024×768 e 768×1024 e mobile 390×844,
  todos sem overflow horizontal; créditos legíveis e separados da dica inferior.
- Falha HTTP 503 real simulada de forma reversível no cache DEM local: navegador
  instalou o entorno plano (`demState=fallback`). Tile original restaurado, cooldown
  removido e relevo recuperado (`ready`) na recarga. Nenhuma configuração de obra
  foi alterada. Acesso anônimo ao novo endpoint respondeu 401 JSON.
- Testes: 32 verificações de terreno, 88 de entorno, 95 de recorte/costa, 8 de DEM
  PHP, 108 cenários de sombra solar, 27 de enquadramento e 850 de metadata, além
  das suítes de posição solar e visibilidade/medição. Sintaxe e whitespace passaram.

## Arquivos de implementação

Novos: `helpers/mapa_dem_helper.php`, `geo/elevation-service.js`,
`geo/model-ground.js`, `geo/terrain-surface.js`, `tests/mapa_dem_test.php`,
`tests/geo_terrain_test.mjs` e este documento.

Alterados: `MapaPavimento/api.php`, `visualizador-glb-navegacao.js`,
`solar-lighting.js`, `geo/geo-environment.js`, `geo/building-builder.js`,
`geo/road-builder.js`, `geo/vegetation-builder.js`, `geo/water-builder.js`,
`geo/urban-haze.js`, `tests/geo_environment_test.mjs`,
`tests/viewer_module_loader.mjs` e os links de atualização nos documentos OSM.
Os caminhos `geo/` referem-se a `MapaPavimento/predio-3d/geo/`.
