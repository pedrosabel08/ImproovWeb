# Texturas PBR do terreno 3D

O botão **Texturas** do visualizador liga e desliga os materiais do entorno. A preferência é local ao navegador (`flow-3d-terrain-textures`), funciona sem gravar na obra e não refaz a geometria. O valor inicial está em `geo/environment-config.js`.

| Superfície | Material | Repetição |
| --- | --- | --- |
| Ruas e avenidas de asfalto | Asphalt Track | 22 m |
| Áreas de praia e areia mapeadas | Aerial Beach 01 | 18 m |
| Calçadas e passeios de concreto | Concrete Floor 01 | 3 m |
| Afloramentos, cascalheiras naturais e praias rochosas | Rocky Terrain 02 | 7 m |
| Parques, jardins e gramados mapeados | [Grass 001 — ambientCG](https://ambientcg.com/view?id=Grass001) | 4 m |
| Polígonos de terra exposta e lama | [Dirt Aerial 02 — Poly Haven](https://polyhaven.com/a/dirt_aerial_02) | 20 m |
| Mar, rios e lagos | Water 002 — arquivo local fornecido | 108 m (escala base) |

Os mapas de execução estão em `assets/textures/terrain-pbr/`. Asphalt Track, Aerial Beach 01, Concrete Floor 01 e Rocky Terrain 02 usam os mapas 1K de cor (sRGB), normal OpenGL e rugosidade (canal verde). Grama, solo e água continuam usando o mapa combinado de oclusão e rugosidade. O mapa especular do pacote rochoso não participa do material dielétrico. As normais EXR são servidas como WebP em espaço de dados. Os ZIPs de origem e os mapas de deslocamento não são carregados pelo visualizador.

`geo/surface-classification.js` e o parser definem as classes OSM; `geo/terrain-materials.js` mapeia essas classes para assets. Trocar uma textura ou sua escala não invalida o cache geográfico. As chaves das superfícies já viajam nos nomes dos lotes gravados pelo worker. O primeiro acesso após a atualização da classificação pode regenerar o cache binário a partir dos dados que já estão disponíveis. O cache OSM mantém suas chaves e prazos; novas tags chegam na atualização regular.

Asphalt Track é o único conjunto de asfalto. Se o OSM informa `surface=asphalt`, a via usa asfalto. Sem `surface`, apenas as classes de ruas e avenidas veiculares usam asfalto como aproximação. A largura continua estimada pela classe. Ao desativar Texturas, as vias usam a cor neutra.

Polígonos `natural=beach` e `natural=sand` recebem areia, salvo quando a superfície registrada indica rocha ou seixos. Rocha exige classificação geográfica explícita, como `natural=bare_rock|scree|shingle`. A inclinação do DEM só modula o tom dentro do polígono de rocha. Vegetação mapeada tem prioridade. Dirt Aerial 02 continua reservado à terra exposta e à lama.

Uma calçada com geometria própria precisa de `footway=sidewalk` ou de superfície de concreto em passeio mapeado. Travessias e caminhos sem indicação continuam neutros. Onde a via indica explicitamente o lado, pode receber uma faixa simplificada com largura padrão de 1,8 m nos primeiros 450 m de detalhe. A faixa é recortada por vias, calçadas mapeadas próximas, água e edifícios. Largura, distância e folgas estão em `SIDEWALK_SETTINGS`.

Para configurar uma praia sem polígono OSM, adicione uma entrada em `BEACH_BY_PROJECT` no mesmo arquivo. Use o ID da obra, `ring` com vértices WGS84 verificados no formato `{lat, lon}`, `widthM` entre 2 e 150 e, se necessário, `maxElevationM` acima do nível do mar (padrão 6 m). Delimite somente o trecho confirmado de praia. O recorte usa o lado de terra da costa válida e exclui relevo acima do limite, áreas urbanas e portuárias mapeadas, costões, vias, vegetação, edifícios e água. A configuração não tem efeito sem DEM e costa válida.

A água compartilha os arquivos de textura e o reflexo HDRI entre os perfis de oceano, água corrente, água parada e água interior genérica. Cada perfil em `water-material.js` define cor, rugosidade, escala e velocidade das ondulações e intensidade especular. A preferência de movimento reduzido congela as fases das duas normais.

Na costa OSM validada, os trechos que tocam a areia classificada alimentam uma máscara de distância em metros. Ela escurece a areia e reduz sua rugosidade numa faixa de até 8,32 m, aproxima a cor da água da areia molhada nos primeiros 16 m e modula a cor costeira até 32 m. Essas larguras são parâmetros visuais. A máscara também funciona com malhas restauradas do cache.

Com Texturas desligado, cada superfície usa a cor base da classe. Essa cor também aparece durante o carregamento ou se faltar um dos mapas necessários. Os filtros de visibilidade do diagnóstico continuam independentes do botão.

Execute `node tests/geo_terrain_pbr_test.mjs --three-module=tools/geo-cache/node_modules/three/build/three.module.js` para validar os materiais com dados controlados. As medições com os modelos reais e as opções de reversão estão no [relatório de homologação](homologacao-entorno-3d.md).
