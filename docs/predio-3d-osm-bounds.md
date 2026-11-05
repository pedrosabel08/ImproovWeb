# Ambiente OSM: recorte e costa sem fog

**Atualização:** o recorte e a costa descritos aqui agora recebem a camada DEM
documentada em [predio-3d-terrain.md](predio-3d-terrain.md). Os offsets planos
abaixo continuam sendo o fallback; a fog permanece desligada.

A fog e o fade permanecem desligados (`URBAN_FOG_ENABLED=false`). Não há
clipping de shader novo, DEM, tiles, satélite ou árvores. Os módulos de seleção,
GLB, Norte, SunCalc, autorização, metadata e shadow frustum foram preservados.

## Diagnóstico confirmado

Na resposta real da obra validada, `way/308832472`, `natural=wood`, tinha
45.271.982 m² e vértices a mais de 10 km da origem. O builder anterior triangulava
o polígono inteiro e usava `wood` (#798b70). `leisure=park` usava `green`
(#8b9c7e). A iluminação solar aquecia esses materiais. Outra mata,
`way/1442128808`, tinha 280.311 m². A base media 8 × 8 km e não compartilhava
um recorte com as áreas. Não havia processamento de coastline neste checkout.

Agora as áreas dessas duas matas dentro do ambiente têm, respectivamente,
362.849 m² e 161.662 m². São polígonos de floresta, sem árvores individuais.

## Um limite para todas as camadas

`createEnvironmentBounds(1000, norte_graus)` define um quadrado de 2 × 2 km em
E/N geográfico. A transformação para X/Z reutiliza `northDirection()`, assim como
o cálculo solar. Não depende da câmera. A consulta cobre esse quadrado, inclusive
os cantos; o cache v2 evita reutilizar uma resposta antiga sem coastline/tags.

`prepare-environment.js` projeta as áreas, intersecta os polígonos com esse limite
e só então os entrega aos builders para triangulação/extrusão. Preserva furos,
recortes em vários componentes e interseções vazias. Vegetação e água interna
também são subtraídas do mar classificado.

As operações booleanas ocorrem em E/N, em grade de precisão micrométrica, para
evitar resíduos numéricos nas bordas compartilhadas depois de girar o Norte.
É usada uma cópia local de polygon-clipping 0.15.7, MIT, com licença no diretório
`geo/vendor/`. O UMD original foi envolvido por um módulo local; não há pacote npm,
script externo, global novo ou rede para carregar essa biblioteca em produção.

`geometry-clip.js` faz ainda um recorte CPU final dos triângulos gerados, incluindo
a largura de vias e rios. O recorte das linhas centrais sozinho não conteria suas
bordas. Não há trabalho de clipping por frame.

## Costa e mar

O parser preserva a direção de `natural=coastline`. Os trechos são recortados no
quadrado, conectados a um grafo com o perímetro e poligonizados. A classificação
segue a convenção OSM: terra à esquerda da via dirigida, mar à direita. O grafo
trabalha em E/N para não inverter essa convenção ao passar para X/Z.

Faces de mar/terra são combinadas preservando ilhas e regiões desconectadas.
O terreno é a diferença entre o quadrado e o mar; não existe um plano marítimo
grande cobrindo terra. Falta de conexão no interior, bifurcações, orientação
inconsistente ou falha topológica resultam em ausência de mar e log de diagnóstico.
Sem costa retornada, não se presume que a área seja oceânica. Uma janela totalmente
offshore, sem coastline disponível, exige uma fonte/classificação adicional futura.

A deduplicação de membros de relações é por categoria: uma via que compõe um
multipolígono de água continua disponível como coastline. Na resposta validada,
isso preservou três trechos anteriormente descartados. Os 11 trechos completos
classificaram 833.460 m² de mar, dentro de 4.000.000 m² totais.

## Materiais e superfícies

Os materiais estão em `geo/environment-config.js`, foscos e sem texturas:

| Categoria | Tags | Cor base |
| --- | --- | --- |
| FOREST | natural=wood, landuse=forest | #284b36 |
| GRASS | landuse=grass, natural=grassland | #89977b |
| PARK | leisure=park | #58785a |
| WATER_INLAND | natural=water, water=*, polígonos waterway | #638c99 |
| SEA | natural=coastline processada | #2b617e |
| URBAN_BASE | complemento terrestre da base | #39453f |
| ROADS | highway | #454b49 |
| BUILDINGS | building | cinzas #c0c2bd / #b5b9b5 / #acb2af |

Todas as alturas relativas estão em `SURFACE_Y`, em metros: mar −0,02; base 0;
vegetação +0,01; vias +0,025; água interna +0,04. A origem vertical segue o terreno
original capturado antes de ocultá-lo. São afastamentos técnicos de centímetros,
sem relevo. A costa compartilha as coordenadas exatas com a borda terrestre.

As ruas mantêm `renderOrder=-1`, depth test/write e recebem sombras; a base e
vegetação também as recebem. Nenhuma dessas superfícies projeta sombras. Edifícios
distantes continuam sem sombra. Não houve ampliação do shadow frustum.

O olho da câmera ortográfica pode ser afastado ao longo do próprio raio visual
para o near plane não cortar a frente da área ao dar zoom out. Isso mantém as
coordenadas projetadas, alvo, orientação, zoom e enquadramento. O ajuste ocorre ao
instalar o ambiente ou mudar a pose/órbita, sem percorrer geometrias por frame.

## Debug e árvores

`GEO_OPTIONS` mantém `geoDebug=false` e `enableForestTrees=false`. Árvores ainda
não têm implementação: a flag reserva a opção futura e não adiciona instâncias.
Para diagnóstico temporário, altere `geoDebug` para `true` e recarregue:

- ciano: perímetro comum;
- magenta: geometrias originais (podem ultrapassar o limite intencionalmente);
- verdes: categorias recortadas;
- amarelo: coastline recortada;
- azul: polígonos de mar;
- `console.table`: ID OSM, tags visuais, categoria, área original e área recortada.

Os objetos têm nomes por categoria na árvore Three. Não se adiciona painel ou
labels à interface final. Debug, geometrias e materiais são descartados na troca.
`group.userData.categories` guarda o diagnóstico normalizado sem nomes comerciais.
`#viewport.dataset.geoMetrics` informa meshes, triângulos, tempos e estado da costa.

## Validação

```sh
node tests/geo_clip_coast_test.mjs --three-module=CAMINHO/three.module.js
node tests/geo_environment_test.mjs --three-module=CAMINHO/three.module.js
php tests/mapa_osm_cache_test.php
node tests/solar_position_test.mjs
node tests/solar_shadow_test.mjs --three-module=CAMINHO/three.module.js
node tests/floor_navigation_test.mjs --three-module=CAMINHO/three.module.js
node tests/floor_visibility_test.mjs --three-module=CAMINHO/three.module.js
```

Os testes cobrem polígonos grandes, furos na borda, recorte côncavo em partes
separadas, largura das vias, Norte rotacionado, ilhas, canais, regiões de mar
separadas, costa quebrada, deduplicação por categoria e invariância da projeção
ortográfica após ajuste de profundidade. Fog permanece sem shader aplicado.

Auditoria da resposta real: 10 meshes e aproximadamente 8,1 mil triângulos.
Todos os vértices finais foram conferidos contra o mesmo limite, com erro máximo
inferior a 0,1 mm, decorrente do Float32. Evidências locais ficam em
`output/osm-qa/clipping-real-validation.json` e screenshots no mesmo diretório.

## Aquisição autenticada e cache compartilhado

### Atualização da política de aquisição

O navegador usa somente a API autenticada por obra; a consulta direta ao
Overpass foi removida. O servidor informa `acquired_at` e `expires_at` em
segundos Unix, e o cache v3 do navegador conserva esses prazos. Entradas v2
do navegador são descartadas por segurança, pois não guardavam a idade da
origem; o servidor continua lendo entradas v2 existentes. Dados de contingência
não são gravados no navegador e são identificados na interface.

Travas por localização e vagas globais são não bloqueantes: se não houver vaga,
retorna-se o cache de contingência ou erro 503, sem fila PHP. Configure
`FLOW_OSM_MAX_CONCURRENT` (padrão 2, intervalo 1–16) e
`FLOW_OSM_CACHE_MAX_BYTES` (padrão 256 MiB, mínimo 12 MB). A cota cobre os
arquivos de dados; arquivos pequenos de trava e estado não entram no limite.
O serviço remove entradas com pelo menos 30 dias quando não estão em uso.
Entradas ainda elegíveis para contingência nunca são removidas para abrir espaço:
se elas preencherem a cota, novas gravações são recusadas até haver espaço.
Arquivos de trava são mantidos para impedir corridas na troca de inode.

Falhas externas acionam cooldown global com backoff de 60 s até 30 min;
`Retry-After` pode aumentá-lo até 24 h. Um intervalo maior solicitado pelo
servidor é limitado a 24 h. Não há consulta recorrente automática. Eventos
estruturados no log PHP incluem cache hit/miss, ocupação, falhas, duração e
HTTP/cURL da aquisição. A coordenação exige que todos os processos PHP usem o
mesmo diretório de cache e um sistema de arquivos com `flock` funcional.

A consulta externa direta no navegador apresentou timeout/504. A aquisição por
obra agora passa por `MapaPavimento/api.php?action=osm_contexto&obra_id=...`.
A API mantém o guard de sessão, método GET e `mp_require_obra`; recebe somente
o ID e lê as coordenadas salvas, sem aceitar URL, bbox ou query do cliente.
Solta a sessão e a conexão de banco antes da requisição externa.

`helpers/mapa_osm_helper.php` consulta apenas o endpoint público fixo já utilizado,
via cURL com TLS verificado, timeout de 28 s e limite de 12 MB. A resposta mantém
somente geometrias e tags visuais; nomes e metadados pessoais OSM são removidos.
As respostas da API são privadas/no-store. O cache em `cache/flow-osm` (ignorado
no Git) usa chave por localização/raio, validade de 7 dias e trava por chave.
Todos os arquivos possuem extensão `.php` e guard 404 antes do conteúdo JSON.
Falhas têm cooldown global com backoff; se houver dados com menos de 30 dias, podem ser
servidos como contingência, sem renovar o cache do navegador. Fora disso o GLB
e o estudo solar continuam disponíveis sem o entorno. Não há retry automático.

O cache normalizado do navegador continua com limite de quatro áreas/7 dias.
A origem retornada pela API é conferida antes de usar/cachear os dados; mudanças
de localização durante o carregamento não instalam um contexto incorreto.
`geoMetrics` registra `acquisitionSource`, `serverCacheHit` e `stale`.

A abertura real sem nenhum cache OSM retornou pela API em cerca de 24,3 s,
com `cacheHit=false`, `serverCacheHit=false` e `stale=false`: 82 edifícios,
523 vias, costa válida e 8.143 triângulos. Essa validação substitui a evidência
anterior preparada com dados reais no cache: não depende mais de pré-carga QA.
A reabertura sem cache local confirmou `serverCacheHit=true`, aquisição em
cerca de 407 ms, mantendo as mesmas geometrias. Sem sessão a API retorna 401;
o arquivo de cache solicitado diretamente retorna 404.
