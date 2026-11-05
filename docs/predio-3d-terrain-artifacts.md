# Artefatos geométricos do entorno topográfico

Diagnóstico e correção em 04/11/2026. Complementa [a arquitetura do terreno](predio-3d-terrain.md).

## Causa e evidência

O defeito tinha causas geométricas combinadas. Os offsets, a iluminação e a
transparência não foram usados como correção.

1. **Salto na amostragem das margens.** `TerrainSurface.sample()` retornava a cota
   do canal até 1 cm da margem e, logo depois, voltava à interpolação da grade.
   Um teste em encosta mostrou uma diferença de **0,276789 m em apenas 2 mm** de
   deslocamento. Pontos quase coincidentes recebiam cotas distintas; sua ligação
   produzia faces muito íngremes e pontas. O mesmo mecanismo de ajuste pontual
   existia na costa e na borda do GLB.
2. **Mesma grade não significava mesma superfície.** Terra e vegetação eram
   trianguladas separadamente e ganhavam vértices diferentes nos recortes. Uma
   função com essas restrições locais não é afim em cada triângulo da grade.
   Conferir somente as alturas dos vértices escondia o problema: no caso de teste,
   o interior da terra diferia do amostrador em até **0,84616 m**. As faces de outra
   camada podiam atravessá-la mesmo com todos os vértices corretamente amostrados.
3. **Definições diferentes de margem.** A abertura do rio usava a faixa triangular
   com junções miter; o ajuste altimétrico usava distância ao eixo menos meia
   largura. Esses limites divergem nas curvas e nas extremidades. Vegetação também
   não era subtraída do canal, podendo cobri-lo novamente.
4. **Água sobreposta e cotas independentes.** Ribbons e polígonos eram emitidos por
   feature, embora a abertura de terra utilizasse sua união. Em confluências isso
   permitia faces sobrepostas e níveis diferentes. A aplicação sucessiva de
   controles de água também tornava a deformação dependente da ordem de entrada.
5. **Faces sem resolução numérica.** Recortes sobre arestas/diagonais emitiam
   vértices repetidos e faces de área nula. O primeiro teste encontrou **220**.
   Além disso, o último recorte pelo quadrado do entorno podia colapsar ou inverter
   pequenas faces ao convertê-las para `Float32`, mesmo após o builder validá-las.
6. **Triângulos válidos com geometria ruim.** A auditoria inicial de validade não
   bastava: diagonais auxiliares da triangulação do polígono atravessavam células
   e deixavam triângulos quase colineares nas margens. Atribuir a função não linear
   de cota a esses pontos amplificava pequenas diferenças em planos com inclinação
   extrema. Um caso mínimo extraído do diagnóstico tem variação de apenas 16 cm,
   mas inclinação superior a 20:1 por causa da diagonal escolhida. Esse é o motivo
   de faces ainda poderem parecer agulhas mesmo sem degeneração ou inversão.
7. **Referências diferentes no terreno GLB.** O amostrador escolhia a maior cota
   entre faces superiores sobrepostas, mas a cópia renderizada e a projeção de
   camadas ainda usavam todas elas. A união do footprint também eliminava pontos
   colineares que marcavam mudanças de cota. Isso produzia projeções duplicadas e
   agulhas locais na junção do lote.

As camadas diretamente envolvidas eram DEM, vegetação e água. Ruas utilizavam o
mesmo caminho de projeção e também precisavam acompanhar a superfície corrigida.
A cópia de visualização das faces superiores do GLB integra a superfície de apoio;
o arquivo, os buffers e as transformações do GLB original não são escritos.

## Correção

- A restrição de canal é contínua na faixa de margem existente de 10 m, aplicada
  ao DEM interpolado. Costa e junção GLB também chegam continuamente à cota da
  borda, utilizando a faixa existente de 45 m. O salto de 27,7 cm do teste passou
  a aproximadamente **0,000000507 m** para o mesmo deslocamento.
- A distância da margem vem dos polígonos reais da água, incluindo curvas,
  extremidades e furos; o perfil longitudinal continua derivado das amostras DEM.
- Terra e leitos são construídos primeiro. `SurfaceMesh` indexa os planos efetivos
  já convertidos e recortados. Vegetação e ruas são intersectadas com esses
  triângulos e recebem cotas baricêntricas. Edifícios consultam a mesma superfície.
  O leito fica no índice CPU para conservar o comportamento das ruas que cruzam
  canais; não é uma superfície extra sobre a água.
- Antes de atribuir cotas, as partes de terra são reunidas por metade de célula,
  removendo as diagonais auxiliares da triangulação original. Contornos são
  normalizados na precisão `Float32`, com orientação e início determinísticos.
  Trocas de diagonais internas melhoram a qualidade dos triângulos, mantendo
  arestas de contorno, furos e limites da grade como restrições. O caso mínimo
  acima passa a inclinação inferior a 1:1 **sem mover vértices nem suas cotas**.
  O teste também exige que mudar só a triangulação de entrada não mude o relevo.
- A união dos footprints serve tanto à abertura de terra quanto à única superfície
  de água. Um campo de cotas linear por triângulo da grade é compartilhado nas
  confluências. Lagos desconectados mantêm seu próprio nível horizontal.
- A vegetação é subtraída da mesma união de água antes da triangulação; furos e
  partes desconectadas são preservados pelo recorte de polígonos existente.
- Builders e recorte final validam finitude, área e orientação **após** a conversão
  para `Float32`. Somente faces cuja espessura está abaixo da precisão das próprias
  coordenadas são descartadas. Paredes verticais válidas são preservadas. Isso não
  altera a resolução adaptativa de 6–36 m.
- A recuperação numérica do amostrador usa o ponto mais próximo de uma aresta,
  limitado a 0,2 mm; não extrapola planos quase degenerados. O índice cobre as
  células vizinhas nessa tolerância.
- A inspeção do GLB constrói a envoltória superior das projeções: recorta regiões
  cobertas por uma face mais alta e elimina duplicatas coplanares. Contornos são
  subdivididos nos vértices reais das faces e cada trecho usa o seu próprio plano
  para calcular as duas cotas de borda. Um teste com planos sobrepostos e cruzados
  verifica cobertura única, cota superior e imutabilidade do buffer original.

Mapterhorn, cache, escala vertical 1:1, mar horizontal, materiais, cores, offsets
centimétricos e `polygonOffset` existentes são preservados. A câmera, ViewCube,
seleção, estudo solar e arquivo GLB não receberam alterações funcionais. A mudança
no entrypoint do visualizador apenas atualiza a versão do import.

## Diagnóstico opcional

Acrescentar `geo_debug=1` à query da página autenticada atual. O painel permite
isolar DEM, cópia do terreno GLB, ruas, água unificada, mar e vegetação, e ativar
wireframe. O modo não está habilitado na experiência normal, não muda permissões
e não usa globais com referências privadas da cena.

`#viewport.dataset.geoGeometryAudit` contém os resultados. A validade de buffers,
índices, área, orientação e normais é examinada em todas as faces das superfícies.
A continuidade usa amostragem distribuída de até 2.000 triângulos por mesh, com
baricentro e pontos médios das arestas; `sampledTriangles` informa a cobertura.
Os testes automatizados executam essa verificação sem limite de amostras. O custo
é de construção/diagnóstico, sem auditoria por frame. `geoBuildStage` identifica
a etapa corrente; recursos pendentes são descartados em cancelamentos.
`maximumSlope` e `steepestFace` permitem examinar agulhas que passam nas verificações
de validade. Não há limite artificial de inclinação aplicado à topografia.

## Arquivos

- `geo/terrain-surface.js`: campo contínuo, footprints, água compartilhada e drape.
- `geo/surface-mesh.js` (novo): índice dos planos e auditorias geométricas.
- `geo/geometry-clip.js`: validação depois do último recorte e conversão numérica.
- `geo/environment-clip.js`: interseção de polígonos para identificar lagos isolados.
- `geo/geo-environment.js`: construção na ordem correta, união da água e lifecycle.
- `geo/vegetation-builder.js`: exclusão dos canais antes da triangulação.
- `geo/water-builder.js`: mantém fallback plano; evita reemitir água por feature
  quando o controlador topográfico já construiu a união.
- `geo/terrain-debug.js` (novo): isolamento de camadas e relatório opcional.
- `geo/model-ground.js`: envoltória superior e conservação das divisões altimétricas
  da borda do terreno GLB.
- `geo/feature-builder.js` e `visualizador-glb-navegacao.js`: versões dos imports
  para carregar o recorte e controlador atualizados.
- `tests/geo_surface_continuity_test.mjs` (novo): regressões descritas abaixo.

Os caminhos `geo/` são relativos a `MapaPavimento/predio-3d/`.

## Testes reproduzíveis

```text
node tests/geo_surface_continuity_test.mjs --three-module=output/osm-qa/three.module.js
node tests/geo_terrain_test.mjs --three-module=output/osm-qa/three.module.js
node tests/geo_environment_test.mjs --three-module=output/osm-qa/three.module.js
node tests/geo_clip_coast_test.mjs --three-module=output/osm-qa/three.module.js
php tests/mapa_dem_test.php
node tests/solar_shadow_test.mjs --three-module=output/osm-qa/three.module.js
node tests/solar_position_test.mjs
node tests/floor_navigation_test.mjs --three-module=output/osm-qa/three.module.js
node tests/floor_visibility_test.mjs --three-module=output/osm-qa/three.module.js
node tests/model_measurement_test.mjs --three-module=output/osm-qa/three.module.js
node tests/north_direction_test.mjs
php tools/test_page_metadata.php
```

A nova suíte cobre salto nas margens, curvas/miter/caps, confluências e ordem das
features, lagos próximos desconectados, áreas côncavas e com furos, exclusão dos
canais, áreas antes/depois do drape, continuidade no interior e nas arestas,
normais, orientação, entradas indexadas, coordenadas inválidas, recorte de limites
rotacionados, colapso em `Float32`, preservação de paredes verticais, independência
da triangulação de entrada e remoção de agulhas por troca de diagonais.

## Limitações

O perfil é uma aproximação visual baseada no DEM e OSM, sem solução hidráulica,
batimetria ou cadastro de pontes. Sistemas mistos de polígonos e linhas conectados
usam o campo comum de água. Precisão de datum, cobertura e resolução do DEM seguem
as limitações anteriores. A auditoria numérica não substitui a inspeção visual e
não comprova que todo dado OSM futuro terá topologia adequada.

Os resultados da validação desta correção devem ser lidos em
`output/terrain-artifacts-qa/validation.json`, separados das evidências históricas
de `output/terrain-qa/`.

## Resultado da validação desta correção

- Login e navegação autenticada no navegador interno pela origem oficial
  `https://improov/ImproovWeb/`, inicialmente por Ferramentas → Mapa de pavimento →
  Modelo 3D. Inspecionada a região de curvas, confluência, mata e edifício da captura,
  com vistas superior/perspectiva, zoom, luz diurna e luz baixa.
- Duas obras reais, com superfícies GLB diferentes e DEM pronto. A primeira mantém
  aproximadamente 197 m de variação de altitude no recorte e grade com 119 pontos
  por eixo. O mar permaneceu horizontal nas duas cenas.
- Zero índices inválidos, coordenadas não finitas, faces degeneradas, faces
  invertidas ou normais inconsistentes nas superfícies auditadas. Nenhuma amostra
  de apoio ausente. Na primeira cena, o maior desvio medido da mata foi
  **0,067 mm**, e de ruas **0,012 mm**; na segunda, todos ficaram abaixo de
  **0,09 mm**. São verificações distribuídas da auditoria do navegador, não uma
  prova exaustiva de continuidade de qualquer cena futura.
- Desktop 1920×1080, notebook 1366×768, iPad landscape 1024×768, iPad portrait
  768×1024 e mobile 390×844: sem overflow horizontal, controles e créditos legíveis.
- Doze suítes passaram, incluindo a nova regressão, as 32 verificações de terreno,
  88 de entorno, 95 de recorte/costa, 8 de DEM, 108 cenários de sombra, 27 de
  enquadramento e 850 de metadata. Posição solar, Norte, visibilidade de pavimentos
  e medição também passaram. O fallback DEM foi exercitado nos testes automatizados.
- Os entornos finais tiveram cerca de 112 mil e 84 mil triângulos, com 12 meshes
  cada. A redução decorre da remoção de subdivisões artificiais: a grade e o DEM
  conservam sua resolução. Construção medida com auditoria e GLB renderizando:
  cerca de 26 s e 6 s, respectivamente; não foi realizado benchmark de FPS.
- A experiência normal foi reaberta sem `geo_debug`; o painel e as auditorias
  adicionais ficam restritos ao modo opcional.
- Conferidos também foco em pavimento, abertura do mapa arquitetônico, seleção de
  um ambiente com duas imagens e retorno ao visualizador.

As imagens `desktop.png`, `top.png`, `river-detail.png`, `low-sun.png`,
`second-work.png` e as cinco resoluções estão na mesma pasta de evidências. O
sombreamento facetado natural do DEM sob luz rasante é preservado; não foi usado
um ajuste de iluminação ou de material para fazê-lo desaparecer.
