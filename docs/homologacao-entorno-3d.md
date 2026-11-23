# Homologação do entorno 3D do Flow

**19/11/2026 · CIB_HYP, obra 102, modelo 4 · RKS_DCF, obra 133, modelo 8**

O asfalto recuperou a escala do padrão, a praia ganhou uma faixa úmida, e a água passou a responder ao HDRI conforme o tipo de corpo d’água. A diferença mediana de 16 cm entre água e terreno caiu para zero nas margens amostradas das duas obras. O botão **Texturas** preservou todas as superfícies classificadas, inclusive com falha de carregamento. As cinco alterações estão no workspace local. A homologação usou os modelos reais e 16 testes automatizados. [Comparação visual](../output/homologacao-entorno/comparativo.png) · [Capturas, medições e testes em ZIP](../output/homologacao-entorno.zip)

## Referência e método

A referência foi o commit `0fc8dc600011148f06691114d839d558e8d25e2c` com as alterações locais de iluminação e navegação já presentes no início. Elas foram preservadas nas comparações. O relatório original, seu ZIP e os JSONs de medição continuam no local original. Os hashes de GLB, OSM, DEM, geometria e alinhamento permitem identificar os dados usados. [Diagnóstico de 19/11](diagnostico-entorno-predio-3d-obra102.md) · [Referências verificadas](../output/homologacao-entorno/referencias.json)

Antes da implementação e após cada etapa, um ensaio WebGL em Edge carregou os dois GLBs, decodificou os tiles DEM e as respostas OSM locais, gerou as malhas diretamente no navegador e restaurou o binário produzido pelo worker. O ensaio comparou origem, nomes, número de vértices, posições e normais. Na verificação final, conferiu também os subtipos de água. A maior diferença numérica entre atributos foi de 3,73 × 10⁻⁹ na obra 102 e 4,66 × 10⁻¹⁰ na 133. As assinaturas do apoio do GLB permaneceram iguais. Os Nortes usados foram 84,7° e 64,0°, respectivamente.

As pastas `s0` a `s5` guardam as capturas com Texturas ligado e desligado e as medições de cada etapa, em 924 × 396. As câmeras próximas da obra 102 vieram do diagnóstico. As pastas `r1` e `r2` repetem a comparação com 36 quadros de aquecimento e 90 quadros medidos por condição, em ordem alternada. As pastas `viewer` e `viewer2` usam o renderizador completo do Flow, o material do GLB, o estudo solar ao meio-dia, sombras e PMREM em 1.848 × 792. As rotas locais autenticadas de ambas as obras também carregaram os modelos e as atribuições de OSM e DEM. O botão Texturas foi alternado e voltou ao estado inicial em ambas.

## Implementação e reversão independente

| Etapa | Implementação e verificação | Como reverter só esta frente |
| --- | --- | --- |
| 1. Asfalto | O material amostra cor, normal e rugosidade pelas mesmas UVs X/−Z, com 22 m por repetição. A variação de tonalidade acontece após a leitura da textura. Testes cobrem reta, curva, cruzamento e rampa. A projeção é horizontal, inclusive em declive. | Em `geo/terrain-materials.js`, definir `PACKS.asphalt.metricUV` como `false`. |
| 2. Praia e oceano | A máscara R8 usa os segmentos comuns à costa real e à malha de areia já recortada por rochas, vias, água e estruturas. O perímetro do recorte não gera praia. Num recorte de 2 km, a resolução nominal passou de 5,21 para 2 m por texel, com redução automática em costas muito complexas. A areia umedece até 8,32 m. A cor rasa responde até 32 m. A geometria da praia foi preservada. | Definir `SANDY_SHORE = false` em `geo/terrain-materials.js` para restaurar o gradiente costeiro anterior. |
| 3. Água | Oceano, corrente, parada e genérica compartilham os mapas e o PMREM. Cada perfil tem rugosidade, intensidade especular, escala e velocidade de normais próprias. O Fresnel vem do material PBR do Three.js. A preferência por movimento reduzido congela as duas fases. Em confluências, os perfis compartilham a superfície hidráulica. Um resíduo subpixel da obra 133 revelou um lote vazio durante o teste de cache. O gerador passou a descartá-lo e a restauração foi repetida. | Definir `WATER_PROFILES_ENABLED = false` em `geo/water-profile.js`. A geração volta a agrupar a água interior e a apresentação usa o material anterior. |
| 4. Margens | O terreno converge para a superfície visível da água. O leito de projeção se aprofunda para o interior e encontra a mesma cota na borda. A faixa natural pode atingir 48 m. Canais artificiais e áreas com construções mantêm a faixa anterior de 10 m. O encontro com o mar usa a cota oceânica somente em componentes que tocam a costa. Há testes para ilhas, canais estreitos, confluências, desembocaduras e proximidade do apoio do GLB. | Definir `CONTINUOUS_BANKS = false` em `geo/terrain-surface.js` para restaurar o condicionamento anterior. |
| 5. Texturas | Desligar o PBR restaura o material base sem mudar a geometria nem a visibilidade. Solo e calçadas ganharam cores base próprias. A ausência de cor, normal ou rugosidade deixa a classe visível com o material base. O filtro de visibilidade do diagnóstico continua independente. | Definir `CLASSIFIED_BASE_MATERIALS = false` em `geo/terrain-materials.js` para restaurar o comportamento anterior. |

Os cinco retornos individuais foram exercitados em processos isolados. Em cada execução, uma opção retornou à configuração anterior e as outras quatro mantiveram o comportamento novo na cena sintética. [Resultado das reversões](../output/homologacao-entorno/reversao.json)

Ao distribuir uma reversão, atualizar a versão da URL do módulo correspondente. O servidor já inclui o código de geração e o worker na assinatura do pacote geométrico e exclui os módulos de apresentação. O subtipo de água é um campo opcional no cabeçalho. Pacotes anteriores continuam legíveis com água interior genérica, enquanto a chave do gerador seleciona o pacote atual no fluxo normal. As chaves de aquisição OSM e DEM foram preservadas.

## Perfis medidos das margens

| Medida nas malhas visíveis | Obra 102 | Obra 133 |
| --- | ---: | ---: |
| Pontos com comparação direta terreno/água na versão final | 2.447 | 948 |
| Desnível absoluto mediano, antes → depois | 0,160 m → 0 m | 0,160 m → 0 m |
| Maior desnível absoluto, antes → depois | 0,160007 m → 0,0000072 m | 0,160001 m → 0,00000081 m |
| Maior inclinação das faces do terreno, em m/m | 7,73 → 2,83 | 3,58 → 1,86 |
| Maior diferença água interior/mar amostrada | 2,54 m → < 0,000001 m | 0,04 m → 0 m |

No canal da captura da obra 102, a 25 cm terra adentro, a diferença em relação ao nível da água caiu de 1,69 m para 0,13 m. A 1 m caiu de 4,39 m para 0,60 m. Nas arestas sem acerto direto no terreno, foram amostrados os dois lados a 1 cm e 25 cm. Todos os pontos interiores ao recorte tiveram cobertura de terra, água ou mar. A amostragem tem resolução finita. [Perfis da obra 102](../output/homologacao-entorno/s4/102/margens.json) · [Perfis da obra 133](../output/homologacao-entorno/s4/133/margens.json)

## Desempenho

Última comparação no renderizador completo, Edge, Radeon RX 570 via ANGLE/D3D11, DPR 1, mesma câmera por par. Cada valor de tempo resume 32 intervalos de `requestAnimationFrame` após oito quadros de aquecimento. Os contadores são do renderizador, incluindo os passes que o Flow já utiliza. [Obra 102 antes](../output/homologacao-entorno/viewer2/s0/102/metrics.json) · [Obra 102 depois](../output/homologacao-entorno/viewer2/s5/102/metrics.json) · [Obra 133 antes](../output/homologacao-entorno/viewer2/s0/133/metrics.json) · [Obra 133 depois](../output/homologacao-entorno/viewer2/s5/133/metrics.json)

| Vista e condição | FPS antes → depois | p50 ms | p95 ms | Draw calls | Triângulos |
| --- | ---: | ---: | ---: | ---: | ---: |
| 102 · prédio · ligadas | 19,5 → 18,9 | 50,0 → 50,0 | 66,8 → 66,7 | 6.254 → 6.254 | 4.067.042 → 4.066.945 |
| 102 · prédio · desligadas | 18,5 → 19,9 | 50,1 → 50,0 | 75,0 → 58,3 | 6.252 → 6.254 | 4.059.310 → 4.066.945 |
| 102 · prédio · entorno oculto | 22,7 → 18,6 | 41,7 → 50,0 | 50,1 → 58,5 | 6.237 → 6.237 | 3.919.852 → 3.919.852 |
| 102 · praia · ligadas | 81,7 → 75,3 | 8,4 → 16,6 | 16,8 → 16,7 | 13 → 14 | 141.057 → 141.373 |
| 133 · prédio · ligadas | 87,3 → 89,3 | 8,4 → 8,4 | 16,8 → 16,8 | 1.475 → 1.475 | 1.413.334 → 1.411.653 |
| 133 · prédio · desligadas | 89,3 → 101,0 | 8,4 → 8,3 | 16,8 → 16,7 | 1.473 → 1.475 | 1.404.226 → 1.411.653 |
| 133 · lagoa · ligadas | 120,0 → 120,0 | 8,3 → 8,3 | 8,4 → 8,5 | 17 → 18 | 112.080 → 111.852 |

A praia teve oscilação entre rodadas. Na primeira comparação do renderizador completo, o FPS passou de 93,6 para 120,0. Na última, o p50 subiu, enquanto o p95 ficou praticamente igual. Com 90 quadros no ensaio de 924 × 396, os dois pares deram 103,9 → 100,0 FPS e 97,3 → 101,9 FPS. Uma chamada adicional apareceu nas vistas que incluem os dois perfis de água interior. Com Texturas desligado, os lotes de areia e calçada agora também entram nos contadores. A alteração não adiciona passes de reflexão ou refração.

**Gargalo da cena completa.** O ensaio reproduziu as 6.254 chamadas e os 4.067.042 triângulos de referência da obra 102. Ocultar o entorno ainda deixou 6.237 chamadas e 3.919.852 triângulos. A maior parte da carga de desenho já está presente com o prédio visível. O valor histórico de aproximadamente 1 FPS não se repetiu no ensaio atual. As amostras históricas da cena completa continham quatro a sete quadros, e os dados disponíveis não identificam a causa daquela queda específica.

### Geração e memória

Duas gerações por versão e por obra, em ordem alternada e com os mesmos arquivos. Na obra 102, o tempo médio de geração do worker caiu de **5,56 para 4,03 s**. Na 133, de **3,84 para 2,51 s**. A busca pelo segmento mais próximo passou a comparar distâncias ao quadrado e calcula a raiz somente no segmento vencedor. Os atributos binários resultantes foram idênticos aos usados para as capturas finais. [Tempos de leitura e geração](../output/homologacao-entorno/geracao.json)

| Recurso do entorno | Obra 102, antes → depois | Obra 133, antes → depois |
| --- | ---: | ---: |
| Lotes | 16 → 17 | 15 → 16 |
| Triângulos gerados | 122.419 → 122.735 | 92.431 → 92.203 |
| Pacote serializado, aproximadamente | 8,41 → 8,43 MiB | 6,35 → 6,34 MiB |
| Atributos de vértices com PBR, estimativa | 9,96 → 9,99 MiB | 7,42 → 7,40 MiB |
| Texturas da cena completa após aquecimento, na vista do prédio | 24 → 24 | 23 → 23 |

A máscara costeira R8 nominal passou de 0,14 para 0,95 MiB nos recortes de 2 km. O conjunto de mapas PBR e o PMREM são compartilhados entre os perfis. O teste mediu contadores do renderizador e tamanho dos atributos. Ficam fora da medição a VRAM total do driver e o tempo de GPU por passe.

## Testes, cobertura e arquivos

Os oito testes de recorte, controlador, terreno, continuidade, PBR, posição solar, sombras e projeção passaram nas verificações incrementais. A rodada final passou em **16 de 16**, incluindo cache PHP de geometria, integridade e publicação, cache OSM no servidor e navegador, DEM, visibilidade de pavimentos e ambiente solar. Casos sintéticos cobrem rocha, solo, falha de mapas, ilhas e bordas artificiais. As duas obras foram homologadas com respostas OSM previamente armazenadas e validadas por hash. A disponibilidade dos provedores externos naquele instante ficou fora do ensaio. A homologação de desempenho cobre a GPU e as câmeras registradas. [Resultados dos testes](../output/homologacao-entorno/testes.json)

| Arquivos relativos ao repositório | Mudança |
| --- | --- |
| `MapaPavimento/predio-3d/geo/terrain-materials.js`, `MapaPavimento/predio-3d/water-material.js` | UV métrica, máscara costeira, shader da água, fallback e alternância. |
| `MapaPavimento/predio-3d/geo/water-profile.js`, `geo/water-builder.js`, `geo/feature-builder.js` no mesmo diretório do visualizador | Classificação, partição da superfície e agrupamento por perfil. |
| `MapaPavimento/predio-3d/geo/terrain-surface.js`, `geo/generate-environment.js` | Cotas, condicionamento de margens, leito, foz e geração. |
| `MapaPavimento/predio-3d/geo/server-geometry-cache.js`, `tools/geo-cache/worker.mjs` | Registro e restauração do subtipo a partir do pacote. |
| `MapaPavimento/predio-3d/geo/geo-environment.js`, `MapaPavimento/predio-3d/visualizador-glb-navegacao.js` | Materiais base e atualização dos imports usados pelo visualizador. |
| `tests/geo_terrain_pbr_test.mjs`, `tests/geo_surface_continuity_test.mjs` | Regressões de UV, classificação, falha de mapas, visibilidade, água e geometria. |
| `docs/predio-3d-terrain-pbr.md`, `docs/homologacao-entorno-3d.md`, `output/homologacao-entorno/` | Documentação, capturas e medições da homologação. |

