# Mapa Improov — cartografia e terreno (04/11/2026)

Evolução da apresentação existente, mantendo MapLibre GL JS **5.13.0**, PHP e
JavaScript vanilla. Não há nova dependência, alteração de API, banco, coordenadas,
permissões, filtros, seleção, clusters ou implementação do visualizador Three.js.

## Composição e escalas

- Globo: Blue Marble Next Generation, oceanos e continentes reais, saturação
  reduzida em 25%, brilho máximo 0,78 e atmosfera nativa azul discreta.
- Os países principais começam em z1,5; países menores em z3,5, estados em z4,5,
  cidades principais em z5, demais cidades em z7, bairros em z12 e ruas em z12.
  Os limites e filtros de relevância originais do OpenFreeMap são preservados.
  Rótulos entram com opacidade interpolada e colisões habilitadas.
- Imagens globais ficam opacas até z6 e desaparecem progressivamente até z8.
  O mapa vetorial permanece abaixo delas. Não há fotografia urbana: ruas,
  bairros, rios e edifícios são os dados vetoriais do OpenFreeMap/OSM.
- Hillshade entra a partir de z5, com intensidade moderada dependente do zoom.
  A geometria fica desligada na escala mundial, cresce de z6,5 a z9,5 e chega
  ao exagero **1**, sem ultrapassar a escala vertical real.

## Compatibilidade e desempenho

O globo nativo dessa versão normalmente passa a Mercator entre z11–12. A
renderização de terreno chama internamente `calculateFogMatrix`, que não é
suportada pelo caminho esférico. Por isso o modo Globo usa uma expressão nativa
de projeção que faz a transição entre **z5 e z6,5**. A forma esférica permanece
na visão mundial, o hillshade funciona durante a aproximação e a geometria só
entra após a transição. O botão Mapa continua selecionando Mercator diretamente.
Não é necessário atualizar nem modificar a biblioteca vendorizada.

O exagero do terreno aceita número nessa versão. Atualizações são quantizadas
em 0,1 e feitas em `zoomend`, evitando reconstruir alvos de renderização a cada
quadro. Durante o gesto, a escala da própria câmera fornece a evolução contínua.
Ao afastar, o terreno é removido antes de reentrar no caminho esférico.
Antes de substituir o exagero, a instância anterior é liberada por `setTerrain(null)`:
essa versão recria os alvos de renderização sem liberar a instância anterior
automaticamente. Isso evita texturas antigas de clusters e acúmulo de recursos GPU.

MapLibre recomenda fontes DEM separadas para terreno e hillshade, para preservar
a qualidade do sombreamento. Usamos os mesmos URLs com caches HTTP compartilhados,
limitando hillshade a z11 e terreno a z12; não há duplicação de camadas visuais.
Não se carregam tiles DEM antes da escala regional. A versão do módulo entra no
cache busting da página e seu import também é versionado.

## Fontes, condições e verificação

Verificação em 04/11/2026, em documentação oficial e requisições reais:

| Fonte | Uso e formato | Condições e limites |
| --- | --- | --- |
| [NASA GIBS](https://nasa-gibs.github.io/gibs-api-docs/) / Blue Marble Next Generation | WMTS EPSG:3857, JPEG 256px, matriz GoogleMapsCompatible_Level8, ordem z/y/x | Dados abertos, sem conta/chave; crédito NASA GIBS. Não implica endosso. Nenhuma cota numérica/SLA identificada na documentação consultada. |
| [Mapterhorn](https://mapterhorn.com/data-access/) | TileJSON + XYZ Terrarium WebP **512px**, não Mapbox RGB; [fontes/atribuições](https://mapterhorn.com/attribution/) | Projeto aberto; cobertura global de 30m, com dados de maior resolução em algumas regiões. Sem conta/chave. Nenhuma cota numérica/SLA publicada nas páginas consultadas; não equivale a garantia de uso ilimitado. |
| [Copernicus GLO-30](https://dataspace.copernicus.eu/explore-data/data-collections/copernicus-contributing-missions/collections-description/COP-DEM) | Fonte de elevação global do Mapterhorn, incluindo Brasil | Licença COPERNICUS free and open, com uso comercial e atribuição. É modelo de superfície, podendo incluir vegetação/edificações; não é levantamento do terreno de uma obra. |
| [OpenFreeMap Dark](https://openfreemap.org/) | Base vetorial urbana existente, OpenMapTiles/OSM | Uso comercial permitido, sem chave e sem limite de visualizações/requisições declarado. Sem SLA. Atribuição original preservada. Licenças do estilo copiadas junto ao fallback. |
| [Natural Earth](https://www.naturalearthdata.com/about/terms-of-use/) | Países 1:110m, GeoJSON local ~197KB, somente em falhas | Domínio público, incluindo modificação e uso comercial. Sem ruas ou detalhes locais. Propriedades removidas e coordenadas arredondadas a 3 casas. |

Endpoints integrados:

```text
https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/BlueMarble_NextGeneration/default/GoogleMapsCompatible_Level8/{z}/{y}/{x}.jpeg
https://tiles.mapterhorn.com/tilejson.json
https://tiles.mapterhorn.com/{z}/{x}/{y}.webp
https://tiles.openfreemap.org/styles/dark
```

Os tiles JPEG globais e regionais da NASA responderam **200 image/jpeg**, com
CORS `*`. Seu cabeçalho observado foi `no-store`: respeitamos essa política,
usando o cache de tiles da instância MapLibre, sem cache HTTP forçado. Uma
consulta ao catálogo WMTS completo respondeu 504; tiles voltaram a funcionar.
Foi observada uma falha inicial de imagem no navegador, recuperada pela nova
tentativa. Isso motivou validar os fallbacks efetivamente.

O TileJSON do Mapterhorn respondeu 200, JSON válido e CORS `*`; o MIME observado
é `text/plain`, aceito pelo parser JSON do MapLibre. Tile real de Santa Catarina
respondeu **200 image/webp**, CORS `*`, `Cache-Control: public, max-age=604800`.
Encoding Terrarium e tamanho 512 foram confirmados na documentação e TileJSON.

Créditos resumidos aparecem no mapa. O link **Fontes e licenças** abre
`MapaImproov/styles/NOTICE.md`, público e sem dados privados, com o reconhecimento
NASA e os créditos completos Copernicus, Mapterhorn e do estilo. Os recursos são
solicitados sem cookies; nenhuma informação de obra é enviada no conteúdo das
requisições cartográficas. Como em qualquer tile remoto, os índices pedidos
indicam a região visualizada. Não há prefetch mundial, exportação de áreas ou
serviço comercial contratado.

## Recuperação de falhas

- Falha de imagens: esconde a camada raster e mantém o mapa vetorial.
- Falha de DEM: desliga terreno e hillshade, desabilita o botão de relevo e
  mantém imagens, geografia, lista e seleção. O retry recria os recursos.
- Estilo remoto indisponível ou inválido: usa `styles/dark.json`, cópia local
  do estilo público com os fontes, glyphs, sprites e créditos originais.
- Tiles/fontes vetoriais indisponíveis: carrega países Natural Earth locais,
  abaixo das camadas existentes, e informa a ausência de ruas. Os marcadores
  e a lista de obras continuam funcionais. Esse fallback não substitui um
  mapa urbano offline, nem apresenta detalhes inventados.

Configuração pública em `config/mapa_improov.php` e `.env.example`. O provedor
Mapterhorn é reconhecido para preservar seu encoding/tamanho mesmo se houver
`FLOW_MAP_DEM_ENCODING=mapbox` sobrando do exemplo anterior. Outros provedores
mantêm os padrões anteriores Mapbox/256, com atribuição e formato configuráveis.
Não são aceitos HTTP, credenciais embutidas nem protocolos executáveis.

## Arquivos desta evolução

```text
MapaImproov/mapa.js
MapaImproov/mapa-cartography.mjs (novo)
MapaImproov/mapa.css
MapaImproov/index.php (versão de assets)
MapaImproov/styles/dark.json (novo)
MapaImproov/styles/countries.geojson (novo)
MapaImproov/styles/LICENSE.md (novo)
MapaImproov/styles/LICENSE-Dark.md (novo)
MapaImproov/styles/NOTICE.md (novo)
MapaImproov/styles/README.md (novo)
config/mapa_improov.php
.env.example
tests/mapa_cartography_test.mjs (novo)
tests/mapa_cartography_config_test.php (novo)
docs/mapa-improov.md
docs/mapa-improov-cartografia.md (novo)
```

Metadata, autenticação e guards da página continuam no mecanismo central
existente. Os arquivos preexistentes de outros módulos não pertencem a esta
alteração. Não houve deploy.

## Validação

Comandos:

```text
node tests/mapa_cartography_test.mjs
php tests/mapa_cartography_config_test.php
node tests/mapa_improov_test.mjs
php tests/mapa_improov_test.php
php tools/audit_mapa_improov.php
php tools/test_page_metadata.php
php tools/test_auth_navigation.php
```

Passaram os cenários de compatibilidade, limites por zoom, ciclo do terreno,
reutilização/idempotência e fallback; **7 verificações de configuração**, **14
asserções JS de dados**, **21 verificações PHP de dados**, **51 verificações HTTP**,
**850 de metadata** e **190 de autenticação**, além de sintaxe e whitespace.

No navegador interno: entrada HTTPS oficial, login real e menu Ferramentas →
Mapa Improov; globo, rotação, aproximação de Santa Catarina por clusters,
cartografia urbana, busca, seleção, abertura real do GLB e retorno. Conferidos
desktop 1920×1080, notebook 1366×768, iPad landscape 1024×768, portrait 768×1024
e mobile 390×844. A visibilidade do controle de relevo exigiu corrigir sua
sobreposição com o zoom no mobile; a legenda foi afastada dos créditos.

A simulação de falha conjunta usou respostas locais incompatíveis no lugar dos
provedores, incluindo o estilo e o TileJSON vetorial da cópia de contingência.
O navegador mostrou países locais, as 13 obras em cluster e a lista de 113
registros; o relevo foi desabilitado. As configurações e o estilo foram
restaurados após o cenário. Não se desligou o servidor nem se alterou o banco.
Capturas ficam em `output/mapa-visual/`; a auditoria HTTP continua em
`output/mapa-improov-http.json`.

Limites: testes de viewport, sem aparelhos físicos; Blue Marble não fornece
fotografia urbana nem imagem atual; no Brasil o DEM tem resolução global de
30m, limitada ao detalhe disponível, e Web Mercator não cobre adequadamente os
polos além de aproximadamente ±85°. Não há iluminação astronômica dia/noite.
O mapa local de contingência não fornece ruas nem elevação.
