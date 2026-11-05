# Entorno urbano OSM — prova de conceito

**Evolução topográfica:** a implementação atual de DEM, alinhamento GLB e
validação está em [predio-3d-terrain.md](predio-3d-terrain.md). A descrição plana
abaixo registra a arquitetura anterior e seu fallback.

**Revisão atual:** o limite passou a ser um quadrado geográfico de 2 × 2 km,
com clipping CPU comum, classificação de vegetação e mar pela coastline.
O detalhamento e os critérios atuais estão em [predio-3d-osm-bounds.md](predio-3d-osm-bounds.md).
As referências a base de 8 km e aquisições anteriores abaixo são o histórico da PoC.
A aquisição atual usa `api.php?action=osm_contexto`, com cache PHP compartilhado;
o navegador não consulta diretamente o Overpass para obras cadastradas.

O visualizador de navegação recebe uma camada adicional de maquete urbana plana,
com dados reais OpenStreetMap/Overpass e Three.js r180. Não usa DEM, satélite,
tiles, Mapbox, Cesium, MapTiler, fotogrametria ou novas dependências.

## Arquitetura e integração

O GLB continua entrando por `model-loader.js` e sendo instalado, sem mudança de
posição, escala ou orientação, em `visualizador-glb-navegacao.js`. Objetos de
terreno e ruas identificados por nome no GLB são ocultados na visualização; o
arquivo original não é editado. Pavimentos, ambientes, ViewCube, medição, câmera,
enquadramento, seleção e cálculo solar mantêm seus módulos e limites anteriores.

Já existem latitude, longitude, Norte e fuso por obra, persistidos pela API de
Configurações espaciais. O hook `spatial.setConfig` compartilha a configuração
salva com `SolarLighting` e `GeoEnvironment`. Não existem coordenadas de uma obra
hardcodadas no renderer. Sem localização/Norte válidos, mantém-se o visualizador.

Fluxo:

```text
Configurações espaciais da obra
  → geo/osm-service.js (aquisição e cache substituível)
  → geo/osm-parser.js (dados geográficos normalizados)
  → geo/geo-coordinates.js (metros e Norte salvo)
  → builders de edifícios, vias, água e vegetação
  → geo/geo-environment.js (grupo Three fora do GLB)
```

A API PHP atende dados autenticados do projeto e a ação `osm_contexto`. Ela
reutiliza a autorização da obra, lê suas coordenadas salvas e adquire/cacheia
somente geometria e tags visuais OSM. Não há tabela ou migração nova. Consulte
o documento de limites para a política atual de cache e contingência.

## Coordenadas e alinhamento

Projeção tangente local para aproximadamente 1 km, raio terrestre médio
6.371.008,8 m:

```text
E = R × Δlongitude(rad) × cos(latitude da obra)
N = R × Δlatitude(rad)
north = northDirection(norte_graus)
east = (-north.z, 0, north.x)
local = E × east + N × north
```

O delta de longitude é normalizado na passagem pelo antimeridiano. Não se usa a
projeção para latitudes polares (|latitude| ≥ 89°); nesse caso há fallback.
Com Norte 0°: Norte = −Z, Sul = +Z, Leste = +X, Oeste = −X; +Y é vertical.
Com Norte 90°: Norte = +X e Leste = +Z. É a mesma base de `solarDirection()`.
Nenhuma rotação é aplicada ao modelo, Sol ou panorama.

A origem geográfica é `(0,0)` nos builders. O grupo adicional a translada para o
centro X/Z dos limites dos pavimentos (ou do modelo quando não há pavimentos).
A altitude plana segue o topo do maior Terreno/Terrain/Ground/Rua identificado antes de ocultá-lo, com
pequeno afastamento para evitar z-fighting. O GLB deve estar em metros.
A latitude/longitude deve representar aproximadamente esse centro do prédio;
uma coordenada de entrada da obra ou de uma esquina exige calibração posterior.

Footprints OSM sobrepostos aos limites horizontais do modelo são descartados,
preservando o entorno já desenhado no GLB. É uma exclusão conservadora por bounds,
não um recorte cadastral de lote. Os dados OSM podem ter cobertura incompleta.

## Aquisição, cache e falhas

Consulta de 1000 m: building, highway, natural=water, waterway, reservatórios,
park, forest, wood, grass e grassland. Usa `out geom`, incluindo multipolígonos
com membros invertidos/divididos e furos; membros são deduplicados. Nomes e POIs
não são propagados para os dados normalizados/cache.

Endpoint público: `FLOW_OSM_ENDPOINT` em `helpers/mapa_osm_helper.php`, atualmente a instância
VK Maps listada na documentação OSM. As instâncias `.de` e Private Coffee
falharam por indisponibilidade/timeout durante a validação. Não há loop de
reconsultas nem alternância automática que multiplique requisições.

Cache normalizado em memória + localStorage: versão de schema, validade de sete
dias, até quatro áreas, chave latitude/longitude com seis casas + raio. Norte
não entra na chave: a transformação é posterior. Cache negado/corrompido não
impede consulta. Sem cookies ou credenciais na requisição externa; somente o
centro/raio e filtros visuais vão ao Overpass.

Timeout de 35 s no navegador, AbortController e geração de carregamento impedem respostas
antigas de entrar em outra obra/modelo. Falha HTTP, JSON inválido, resposta parcial,
ausência de dados ou erro de construção preservam GLB e estudo solar. Mensagens
OSM ficam no console de desenvolvimento. O grupo só entra na cena quando pronto;
troca/descarte libera geometrias, materiais e atribuição OSM.

## Visual e desempenho

- Edifícios: altura informada (m ou ft), níveis × 3 m ou fallback 6/9/8 m.
  Volumes sem fachadas/texturas, materiais neutros compartilhados.
- Vias: faixas conectadas com largura por classe e limite nas junções.
- Água: polígonos ou faixas para cursos lineares, material standard simples.
- Verde: superfícies para parque/gramado/floresta; nenhuma árvore instanciada
  nesta versão, pois as superfícies já permitem avaliar o contexto.
- Distâncias: até 300 m conserva footprints; 300–700 m simplifica detalhes
  menores que 0,6 m; acima de 700 m usa tolerância de 1,5 m. Fog apaga a borda.
- Base: dois triângulos com borda a pelo menos 4 km, usando a cor do terreno GLB.
  Fade completo em 1,8 km. O terreno original fica oculto no visualizador.
- `urban-haze.js`: fog radial em metros nos materiais adicionais. Usa o MESMO
  raio de amostragem, HDRI, exposição e crossfade do céu existente, depois do
  tone mapping. Evita uma cor uniforme artificial e funciona em ortográfica.
  Sem HDRI, o fade transparente encontra o fundo de navegação existente.
  **Comparação atual:** fog e fade radial estão desligados por
  `URBAN_FOG_ENABLED = false`. O shader completo foi preservado; há uma linha
  comentada no início do arquivo para reativá-lo com `true`.
- Sombras: apenas volumes inteiramente a menos de 120 m podem projetar;
  distantes não projetam/recebem. A base recebe sombras. Nenhum limite OSM entra
  no frustum solar, na projeção analítica ou no enquadramento da câmera.
- Geometrias agrupadas por material/faixa/sombra, lotes de cerca de 90 mil
  vértices, construção cede a cada 80 features. Sem material por prédio.
- As vias são desenhadas antes dos demais objetos transparentes, evitando que
  o centro do lote defina incorretamente sua ordem sobre o modelo e o halo solar.
  O núcleo opaco do marcador solar grava profundidade, preservando sua oclusão.
  As ruas também recebem as sombras da DirectionalLight, usando o frustum
  existente, para a sombra do prédio continuar ao cruzar o asfalto.

A vista exatamente horizontal da câmera ortográfica mantém a limitação existente:
um plano horizontal não ocupa área projetada nessa vista. Não se mudou a câmera
para simular um horizonte em perspectiva.

## Diagnóstico e validação

Sem painel adicional: `#viewport.dataset.geoState` informa inactive/loading/
ready/empty/error. `JSON.parse(viewport.dataset.geoMetrics)` e o log `[Flow OSM]`
informam edifícios, vias, água/verde, polígonos, exclusões, meshes, triângulos,
tempos de aquisição/parser/construção e cache hit.

Testes:

```sh
node tests/geo_environment_test.mjs --three-module=CAMINHO/three.module.js
node tests/solar_position_test.mjs
node tests/solar_shadow_test.mjs --three-module=CAMINHO/three.module.js
node tests/floor_navigation_test.mjs --three-module=CAMINHO/three.module.js
node tests/floor_visibility_test.mjs --three-module=CAMINHO/three.module.js
php tools/test_page_metadata.php
```

Em 03/11/2026, a validação no navegador interno entrou por
`https://improov/ImproovWeb/`, fez login e navegou por Ferramentas → Mapa de
pavimento → obra → Modelo 3D. A etapa de prédios isolados mostrou 71 volumes em
3 meshes, excluindo dois volumes sobrepostos ao GLB. A consulta trouxe 73 prédios,
472 vias, 38 elementos de água e 8 áreas verdes. A versão completa agrupou as
camadas em 8 meshes; duas classes de vias sem representação nesta PoC foram
ignoradas. A reabertura confirmou cache hit. Evidências locais: `output/osm-qa/`.

O HTML autenticado, guards, metadados institucionais, canonical sem parâmetros,
favicon/OG e retorno pós-login não foram alterados. O teste central de metadata
passou 830 verificações; o teste OSM passou 69. O teste OSM cobre projeção/Norte solar, extrusão/furos,
deduplicação, cache, falhas, ausência de dados, descarte e respostas obsoletas.

Foram conferidos dia/noite, ViewCube frontal/perspectiva, seleção de pavimento,
abertura do mapa e seleção de ambiente com suas duas imagens, além de desktop
1920×1080, notebook 1366×768, iPad 1024×768 e 768×1024 e mobile 390×844,
sem overflow horizontal. A atribuição ficou legível acima da dica inferior.

Referências: [Overpass QL](https://wiki.openstreetmap.org/wiki/Overpass_API/Overpass_QL),
[instâncias públicas](https://wiki.openstreetmap.org/wiki/Overpass_API),
[ExtrudeGeometry](https://threejs.org/docs/pages/ExtrudeGeometry.html).
