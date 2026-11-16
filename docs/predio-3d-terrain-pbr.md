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

A água compartilha um material entre mar e água interior. Os parâmetros em `water-material.js` controlam a paleta, a escala em metros, as duas amostras do mapa normal, suas velocidades em ciclos por segundo e a força dos reflexos solares e do HDRI. A preferência de movimento reduzido congela o deslocamento.

O mar recebe uma faixa clara de até 32 m quando o entorno tem costa OSM validada. A textura de distância é calculada a partir da malha existente, inclusive quando vem do cache, e exclui o perímetro do recorte geográfico e as arestas internas. Ela representa proximidade da costa, sem estimar profundidade. Rios, canais e lagos usam a mesma água sem essa faixa.

Execute `node tests/geo_terrain_pbr_test.mjs --three-module=tools/geo-cache/node_modules/three/build/three.module.js` para validar os materiais, seus recortes, o alinhamento ao DEM e o botão Texturas com dados controlados. O cache disponível na obra 133 durante a validação tem asfalto, grama e água. Ele não traz etiquetas de calçada nem polígonos de rocha ou areia.
