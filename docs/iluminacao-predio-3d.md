# Iluminação do Prédio 3D

Relatório técnico do visualizador integrado do Mapa de Pavimento · 19/11/2026

## Resumo

O **Sol** é uma luz direcional calculada com localização, Norte, data e fuso do empreendimento. Ele produz a iluminação direta e as sombras. Os **HDRIs** são panoramas OpenEXR usados no fundo da cena e nos reflexos de materiais específicos. A **atmosfera procedural** fornece iluminação difusa por meio de uma `LightProbe` e de uma `HemisphereLight`. Cada componente tem uma calibração própria.

O entorno geográfico usa a mesma luz direta e o mesmo preenchimento difuso do edifício. O terreno e as vias recebem a sombra onde o mapa de sombras tem cobertura. A água PBR recebe tratamento especular próprio e alguns edifícios vizinhos podem projetar sombras. A iluminação noturna de janelas implementada pelo visualizador é um efeito emissivo limitado à obra `102`.

## 1. Onde a iluminação é aplicada

A entrada integrada é `visualizador-glb-navegacao.php`, que monta a interface do Flow e carrega `visualizador-glb-navegacao.js`. A configuração espacial chega pela ação `espacial_configuracao` e é encaminhada ao controlador solar e ao entorno. `visualizador-glb.html` e `visualizador-glb-logico.js` contêm caminhos de inspeção com luzes fixas próprias. Os números das próximas seções descrevem o visualizador integrado.

| Estado | Luz direta | Preenchimento e ambiente | Fundo |
| --- | --- | --- | --- |
| Navegação, Sol desligado ou configuração indisponível | Direcional `#FFF6E8`, intensidade `2,25`, posicionada em relação ao volume visível do modelo | Hemisférica `1,65`, céu `#E9F1FF` e solo `#7B827B`. Cubemap grafite procedural de `512` com PMREM, em `scene.environment` na escala `1` | Gradiente grafite do CSS, visível pelo canvas transparente |
| Estudo solar ativo | A mesma direcional passa a seguir o Sol calculado | `LightProbe` e hemisférica da atmosfera procedural. `scene.environment = null` e mapas HDRI nos materiais reflexivos cadastrados | Panorama HDRI quando disponível |
| Data ou hora inválida durante o estudo | Direcional `0` | Ambiente de navegação com hemisférica em `0,45` | Fundo de navegação |

O renderer usa Three.js r180, WebGL com antialias, saída sRGB, tone mapping ACES Filmic e recorte local. A câmera de navegação é ortográfica. A câmera em perspectiva do modo Explorar usa o mesmo renderer, as mesmas luzes e o mesmo céu. Os valores de intensidade neste relatório descrevem parâmetros do renderer, sem conversão para lux ou irradiância medida.

Fontes no código: [entrada PHP](../MapaPavimento/predio-3d/visualizador-glb-navegacao.php#L39), [renderer e luzes](../MapaPavimento/predio-3d/visualizador-glb-navegacao.js#L100), [ambiente de navegação](../MapaPavimento/predio-3d/viewer-environment.js#L8), [mudança de estado](../MapaPavimento/predio-3d/solar-lighting.js#L477).

## 2. Cálculo e comportamento do Sol

- **Ativação.** O estudo vem habilitado por padrão assim que há um modelo e configuração válida. O código valida latitude, longitude, Norte salvo em `[0°, 360°)` e fuso IANA. A definição do Norte pausa o estudo. A interface permite ligar ou desligar o Sol.
- **Data e hora.** Luxon `3.7.2` interpreta o relógio local da obra. Se uma hora civil for repetida, a primeira ocorrência é usada. Horas inexistentes são rejeitadas. O campo aceita minutos e o slider vai de `00:00` a `23:55` em passos de cinco minutos. **Agora** preenche a data e o horário do empreendimento naquele momento. O cálculo é refeito em eventos da interface e alterações de configuração.
- **Direção.** SunCalc `1.9.0` calcula elevação e azimute. O azimute é convertido para graus a partir do Norte verdadeiro, crescente em direção ao Leste. O Norte salvo orienta o eixo horizontal no plano XZ do modelo. `+Y` aponta para cima. A diferença de elevação entre um minuto antes e um minuto depois indica se o Sol está subindo ou descendo.
- **Iluminação direta.** A luz é colocada a `8 ×` o raio dos limites visíveis, na direção empreendimento → Sol, com alvo no centro. Os raios da direcional são paralelos. A intensidade para elevação `α > 0°` é `2,8 × √sen(α)` e vale `0` em `α ≤ 0°`. A cor vem de uma curva com pontos em `−18°, −6°, 0°, 8°, 30° e 90°`. A calibração chega a `#FFD4A0` em `8°`, a `#FFAB65` em `30°` e a `#FFFAF2` em `90°`.
- **Disco e halo.** O marcador é um objeto 3D com núcleo e halo próprio. Aparece quando o Sol está acima do horizonte. O raio visual usa metade da dimensão do entorno, ou o raio do modelo, limitado a `50–10.000 m`. Num entorno padrão de `2 km` de lado, o raio visual é `1 km`. A distância do marcador afeta apenas sua representação.
- **Trajetória, projeção e bússola.** Os controles começam desmarcados. A trajetória usa amostras a cada dez minutos do dia local. A projeção lança uma malha nominal de `5 × 5` raios paralelos e mostra o primeiro bloqueio no GLB ou a interseção com o receptor técnico quando aplicável. A bússola segue o Norte salvo. Na planta 2D, o cálculo de posição é reutilizado para uma indicação direcional esquemática.

Fontes no código: [cálculo astronômico e fuso](../MapaPavimento/predio-3d/solar-position.mjs#L25), [luz e controles](../MapaPavimento/predio-3d/solar-lighting.js#L493), [trajetória e raios](../MapaPavimento/predio-3d/solar-projection.js#L157), [raio visual](../MapaPavimento/predio-3d/solar-visuals.js#L3), [controles 2D](../MapaPavimento/map-spatial-controls.js#L1).

## 3. Sombras do edifício e da vizinhança

- **Shadow map.** A direcional usa `PCFSoftShadowMap` de `2048 × 2048`. A câmera de sombra é ajustada no espaço da luz aos limites visíveis do GLB e aos limites do receptor técnico calculados pelo controlador solar. O ajuste usa margem de `3%` do raio, `bias` de `−0,00005` e `normalBias` de `raio × 0,00025`. O mapa é marcado para atualização quando necessário, como em mudanças do Sol, dos limites visíveis ou do entorno.
- **Geometria do GLB.** Cada mesh projeta e recebe sombra se pelo menos um material passar no filtro de opacidade. O filtro exclui vidro marcado pelo viewer, materiais invisíveis, opacidade de até `0,01` e transmissão de `0,95` ou mais. Lajes, pisos e paredes visíveis participam. O isolamento de pavimento recalcula os limites e usa `clipShadows` nos materiais temporários das peças recortadas.
- **Chão.** Há um plano técnico para exibir sombras quando o Sol está acima do horizonte e faltam tanto um receptor de piso visível no GLB quanto o entorno carregado. O plano é ocultado quando existe terreno do entorno, que já recebe a sombra direcional. O controlador ainda calcula seus limites para ajuste e projeção.
- **Vizinhança.** São candidatos a projetar sombra apenas os volumes da faixa mais próxima cujo contorno inteiro fica a menos de `120 m` da origem. A caixa dos vizinhos fica fora do cálculo de enquadramento da sombra. A participação efetiva depende também do volume coberto pela câmera da luz. A projeção de raios do painel usa o GLB como índice de obstáculos, enquanto o shadow map pode incorporar esses vizinhos.
- **Exploração.** A luz e seu alvo são deslocados juntos, preservando a direção. O enquadramento passa a uma caixa local de `±16 m` em torno do personagem, expandida por um ponto `100 m` na direção da luz. A caixa é reajustada após deslocamento superior a `4 m`, e a atualização da silhueta é limitada a `30 Hz`. Ao sair, volta o ajuste da vista aérea.

Fontes no código: [câmera de sombra](../MapaPavimento/predio-3d/solar-shadow.js#L15), [receptor técnico](../MapaPavimento/predio-3d/shadow-catcher.js), [sombra no modo Explorar](../MapaPavimento/predio-3d/solar-lighting.js#L328), [filtro de materiais](../MapaPavimento/predio-3d/solar-ray-tracer.js#L4), [prédios vizinhos](../MapaPavimento/predio-3d/geo/building-builder.js#L29).

## 4. HDRI, do arquivo ao céu e aos reflexos

### Seleção automática

Os quatro arquivos ativos ficam em `assets/hdri`. São panoramas `1024 × 512`, com arquivos entre `4,93` e `5,14 MB`. Os presets têm rotação horizontal e ajuste de horizonte em `0°` no mundo. A seleção depende da elevação e do sentido do movimento solar.

| Elevação | Movimento | Arquivo | Multiplicador de exposição do fundo |
| --- | --- | --- | ---: |
| `≤ −6°` | Qualquer | `HDRI_noite.exr` | `0,15` |
| `> −6°` e `< 8°` | Subindo | `HDRI_manha.exr` | `1,00` |
| `≥ 8°` | Subindo | `HDRI_manha2.exr` | `1,00` |
| `≥ 30°` | Descendo | `HDRI_manha2.exr` | `1,00` |
| `> −6°` e `< 30°` | Descendo | `HDRI_tarde.exr` | `1,00` |

Ao amanhecer, portanto, o fundo pode mostrar **Manhã** com o Sol ainda abaixo do horizonte. À tarde, o céu passa para **Tarde** quando a elevação cai abaixo de `30°`. A lista ativa usa os quatro `.exr`. Os arquivos `HDRI_Sunrise_*.hdr` e `HDRI_CloudSky_*.hdr` presentes na pasta ficam fora dessa seleção. [Seleção e exposição dos presets](../MapaPavimento/predio-3d/environment-config.mjs#L1).

### Processamento

1. O viewer solicita o EXR do preset, aguarda `180 ms` de estabilidade para processar uma entrada ainda sem textura pronta, e usa um Web Worker para decodificar e tratar os pixels.
2. Em RGB linear, o processamento corrige valores inválidos, marca pixels com luminância acima de `12` e amplia a máscara pela vizinhança mais clara que o fundo da faixa. Usa a cor estimada para aquela latitude da imagem para suprimir os picos, como disco e halo nas capturas que os contêm acima do limiar. A etapa seguinte limita a luminância a `8` e produz pixels RGBA half-float. O céu e os reflexos recebem o produto desse mesmo processamento.
3. O panorama tratado alimenta o fundo. O renderer também gera um PMREM para os reflexos, isto é, um mapa pré-filtrado que permite amostrar diferentes níveis de nitidez conforme a rugosidade do material. A exposição `0,15` da noite é aplicada pelo shader do céu depois do processamento, exclusivamente ao fundo.
4. As texturas processadas ficam em cache durante a instância do visualizador. No automático, um único vizinho elegível pode ser antecipado após `900 ms`, apenas como download. Respostas de pedidos superados são descartadas para fins de ativação. Falhas são exibidas no diagnóstico e o código pode tentar novamente em uma solicitação posterior, após a janela de `30 s`.

Fontes no código: [carregamento, PMREM e cache](../MapaPavimento/predio-3d/solar-environment.js#L259), [worker](../MapaPavimento/predio-3d/exr-decoder-worker.js#L9), [tratamento dos pixels](../MapaPavimento/predio-3d/hdri-processing.mjs#L1), [pré-carregamento](../MapaPavimento/predio-3d/environment-config.mjs#L176).

### Fundo e horizonte

O shader desenha o céu atrás da geometria. Para amostrar a textura, ele intersecta os raios da câmera com uma esfera de raio `10.000 m`, fixa na origem mundial. O equador dessa esfera é `Y = 0`. O horizonte de diagnóstico marca esse equador. Conforme a câmera ou o enquadramento, a linha pode ficar fora da tela. O shader usa amostragem direcional quando o raio não tem interseção válida com a esfera.

Na troca entre panoramas, o fundo faz uma transição de `600 ms`. O primeiro HDRI aparece quando fica pronto. Enquanto isso, o canvas deixa o fundo CSS aparecer. Em uma troca posterior, o céu anterior e seu PMREM continuam disponíveis até chegar um substituto. O novo PMREM é aplicado de imediato quando fica pronto, enquanto o fundo ainda pode estar misturando os dois panoramas. Uma diferença breve entre céu e reflexo é, portanto, possível.

Fontes no código: [shader e transição do céu](../MapaPavimento/predio-3d/hdri-sky.js#L27), [interseção com a esfera](../MapaPavimento/predio-3d/sky-projection.js#L4), [aplicação do mapa](../MapaPavimento/predio-3d/solar-environment.js#L364).

### Materiais reflexivos

O PMREM do HDRI é aplicado por material PBR. São elegíveis vidro, materiais com nomes que indicam água, piscina, metal, inox ou espelho, ou materiais que atendam a pelo menos um destes critérios: `metalness ≥ 0,30`, `roughness ≤ 0,25`, `transmission > 0,05` ou `clearcoat > 0,10` com `clearcoatRoughness ≤ 0,25`. Com o HDRI ativo, o shader desses materiais zera a parcela difusa do mapa de ambiente. A iluminação difusa continua vindo da probe e da hemisférica.

A escala especular comum vai de `0,18` a `0,40` conforme a elevação sobe de `−6°` a `30°`. Em materiais de vidro usa-se a base `0,80` multiplicada por essa escala, resultando em `0,144` na noite e `0,320` com o Sol a `30°` ou mais. Nos demais materiais elegíveis, usa-se a intensidade original do material multiplicada pela escala. Forçar outro HDRI troca a textura e sua calibração, e a elevação continua determinando a escala.

Fontes no código: [critérios e shader especular](../MapaPavimento/predio-3d/material-reflections.js#L4), [cálculo da escala](../MapaPavimento/predio-3d/solar-environment.js#L152).

## 5. Iluminação difusa e leitura noturna

A atmosfera procedural mistura cores de zênite, horizonte, solo e Sol com base na elevação e usa o vetor solar para orientar a contribuição próxima ao horizonte. A captura é feita em uma cena auxiliar com apenas essa atmosfera. A `LightProbe` usa um cubemap half-float de `32 × 32` por face, convertido em harmônicos esféricos. A aplicação agrupa direções em intervalos de `1°` de elevação e `2°` de azimute mundial, aguarda `200 ms` e usa cache de até `32` capturas. A `HemisphereLight` fornece preenchimento complementar a partir de médias da mesma atmosfera, normalizadas para luminância `0,5` com `20%` da cromaticidade. Sua transição leva `600 ms` após a entrada inicial.

As intensidades-base são `0,20` na probe e `0,06` na hemisférica. O multiplicador diurno sobe suavemente de `0,08` a `1` entre `−6°` e `30°`. Abaixo de `0°` entra uma compensação noturna que atinge o valor total em `−6°`, elevando o multiplicador da probe a `3` e o da hemisférica a `10` na noite plena. À noite, até `90%` da cromaticidade dos coeficientes da probe é neutralizada. São recursos de visualização para conservar leitura das fachadas, com a intensidade solar direta em zero.

| Elevação | Direcional | LightProbe, intensidade após captura | Hemisférica, alvo | Escala especular HDRI |
| ---: | ---: | ---: | ---: | ---: |
| `−6°` ou menos | `0` | `0,600` | `0,600` | `0,180` |
| `−3°` | `0` | `0,312` | `0,304` | `0,184` |
| `0°` | `0` | `0,030` | `0,009` | `0,196` |
| `8°` | `1,045` | `0,078` | `0,023` | `0,254` |
| `30°` | `1,980` | `0,200` | `0,060` | `0,400` |
| `60°` | `2,606` | `0,200` | `0,060` | `0,400` |
| `90°` | `2,800` | `0,200` | `0,060` | `0,400` |

Os números acima usam os ajustes de fábrica. A intensidade é um multiplicador da fonte correspondente. O brilho final também depende da cor e dos coeficientes dessa fonte, da orientação, do material, da sombra e do tone mapping. Em particular, a intensidade da probe noturna ser maior que a diurna reflete a compensação de uma atmosfera mais escura.

Fontes no código: [atmosfera procedural](../MapaPavimento/predio-3d/procedural-atmosphere.js#L23), [probe e curva noturna](../MapaPavimento/predio-3d/procedural-light-probe.js#L10), [preenchimento hemisférico](../MapaPavimento/predio-3d/solar-environment.js#L185), [parâmetros](../MapaPavimento/predio-3d/environment-config.mjs#L45).

## 6. Entorno geográfico, materiais e água

| Elemento | Como reage à iluminação |
| --- | --- |
| Terreno e relevo | O entorno usa dados do OpenStreetMap e tenta obter elevação de um DEM. A área padrão é um quadrado de `2.000 × 2.000 m` orientado pelo Norte da obra. Com DEM, as superfícies seguem a malha do relevo. Se o DEM falha, há caminho de terreno plano. Se o entorno inteiro falha, o GLB continua navegável. |
| Solo, areia, rocha, vias, calçadas e vegetação | Materiais da cena recebem Sol, luz difusa e sombra onde o shadow map cobre a região. Os materiais PBR aplicam mapas de cor, normais e rugosidade, com oclusão de ambiente nos pacotes configurados. Texturas começam habilitadas, respeitando a preferência local. Há materiais de cor simples enquanto os pacotes carregam ou como alternativa quando são desabilitados. |
| Edifícios OSM | Volumes usam altura mapeada, número de pavimentos × `3 m` ou valores padrão por tipo. As fachadas de contexto têm rugosidade `0,94` e metalicidade `0`. A iluminação direta e difusa fornece o volume visual. Os edifícios que entram no lote de sombras da vizinhança podem projetar e receber sombra. |
| Água e mar | A água texturizada registra seu material para reflexos HDRI e usa resposta especular própria. Rugosidade base `0,49`. O shader escala a parcela especular direta por `0,13` e a indireta por `0,72`, além da escala do mapa quando há HDRI ativo. Duas camadas de normais animadas alteram os brilhos e a normal é atenuada quando vista de longe. A água continua reagindo à luz da cena, embora seus lotes estejam fora da lista de receptores do shadow map. O fallback é o `MeshStandardMaterial` colorido da camada, sem o shader especializado de água. |
| Bordas e névoa | `URBAN_FOG_ENABLED = false`. A mistura radial com o céu está desativada. Os materiais do entorno continuam com suavização por descarte de pixels nos `60 m` internos da borda quadrada. O efeito reduz a cobertura visual na borda. |

O entorno é instalado como um grupo separado do GLB. A configuração solar é compartilhada, mas o entorno fica fora do índice de pavimentos, do cálculo dos limites do GLB e dos bloqueios exibidos pela projeção solar. A geometria restaurada do cache carrega os mesmos indicadores de projeção e recepção de sombras. Alturas aproximadas do OSM e o alcance local da sombra limitam a leitura de obstruções urbanas.

Fontes no código: [implantação do entorno](../MapaPavimento/predio-3d/geo/geo-environment.js#L173), [raio e dados OSM](../MapaPavimento/predio-3d/geo/osm-service.js#L3), [lotes e sombras](../MapaPavimento/predio-3d/geo/feature-builder.js#L119), [materiais e texturas](../MapaPavimento/predio-3d/geo/terrain-materials.js#L191), [shader da água](../MapaPavimento/predio-3d/water-material.js#L117), [borda e névoa](../MapaPavimento/predio-3d/geo/urban-haze.js#L4).

## 7. Vidros, transparência e janelas acesas

O viewer clona materiais físicos do GLB com `vidro` ou `glass` no nome. Usa transparência alpha, `transmission = 0`, opacidade limitada a `0,16` no tratamento diurno, metalicidade `0`, rugosidade entre `0,16` e `0,30` e IOR `1,45`. Com isso, a geometria que está atrás do vidro continua visível e o HDRI fornece o reflexo do material. Esses vidros são excluídos como bloqueadores opacos.

O efeito noturno é habilitado apenas para a obra `102` e para material PBR cujo nome seja exatamente `vidro`, sem distinção de maiúsculas e minúsculas. A opacidade caminha para pelo menos `0,72` e soma-se emissão `#FFA34D` até intensidade `1,00`. O fator começa a crescer abaixo de `0°`, atinge `1` em `−6°` e transiciona em `600 ms`. É a própria superfície que aparece acesa por emissão do material. O efeito não cria fontes de luz que iluminem pisos, fachadas vizinhas ou ruas.

Fontes no código: [tratamento do GLB](../MapaPavimento/predio-3d/visualizador-glb-navegacao.js#L1385), [material de vidro](../MapaPavimento/predio-3d/viewer-glass.js#L2), [emissão noturna](../MapaPavimento/predio-3d/night-glass.js#L18), [diagnóstico histórico da obra 102](night-glass-diagnostic.md).

## 8. Controles e efeitos observáveis

| Controle | Efeito no visualizador integrado |
| --- | --- |
| **Ativar Sol** | Alterna entre o ambiente fixo de navegação e o estudo da data e hora selecionadas. |
| **Data**, **Horário**, slider e **Agora** | Alteram o instante usado na posição solar, nas sombras e nas curvas de ambiente. Em **Automático**, podem selecionar outro panorama. |
| **HDRI para comparação** | Força Manhã, Manhã 2, Tarde ou Noite para o céu e para o mapa de reflexos. Por exemplo, forçar Noite ao meio-dia mostra o panorama escuro com a luz direta correspondente ao meio-dia. |
| **Mostrar horizonte geométrico** | Exibe a linha de `Y = 0` quando o equador da esfera está no enquadramento. |
| **LightProbe intensity** | Ajusta a intensidade-base da iluminação difusa da atmosfera. Faixa `0–2`, passo `0,01`, padrão `0,20`. |
| **Hemisphere intensity** | Ajusta o preenchimento complementar. Faixa `0–0,3`, passo `0,005`, padrão `0,06`. |
| **Glass reflection intensity** | Ajusta a base do reflexo HDRI no vidro. Faixa `0–2`, passo `0,01`, padrão `0,80`. |
| **Restaurar ajustes** | Restaura os três controles de intensidade. São ajustes de sessão. |
| **Texturas do terreno** | Alterna entre os materiais PBR e as cores básicas do entorno. O shader animado e os reflexos HDRI dedicados aparecem na água quando o material texturizado está aplicado. |

O painel **Ambiente · diagnóstico** informa HDRI atual, rotação, horizonte, elevação da câmera, intensidade solar, escala de reflexos, estado e intensidade da probe, cores e intensidade da hemisférica, materiais cadastrados e falhas de carregamento. A leitura **GI global por HDRI** indica que `scene.environment` está desativado no modo solar, mesmo com preenchimento difuso e reflexos por material em funcionamento.

Fontes no código: [painel de diagnóstico](../MapaPavimento/predio-3d/solar-lighting.js#L70), [ajustes de sessão](../MapaPavimento/predio-3d/solar-environment.js#L99), [preferência de texturas](../MapaPavimento/predio-3d/visualizador-glb-navegacao.js#L140).

## 9. Validação e alcance

Foram executadas **19 suítes existentes** com Node.js e a cópia local do Three.js r180 onde exigida. A cobertura incluiu posição e fuso, Norte, escolha e cache dos ambientes, sombras, trajetória e raios, processamento e projeção do céu, reflexos, probe, vidro noturno, entorno, litoral e materiais PBR. A suíte de sombras passou por `108` combinações de escala, Norte, azimute e elevação.

Os quatro EXRs do workspace foram decodificados pelo mesmo handler usado no worker. Para executar o teste offline, os imports de `three` e `fflate` foram resolvidos para as cópias locais no processo do Node. As medições abaixo são da textura tratada, antes da exposição do fundo e do tone mapping.

| Arquivo | Luminância máxima medida | Luminância média do hemisfério superior |
| --- | ---: | ---: |
| `HDRI_manha.exr` | `0,32` | `0,1395` |
| `HDRI_manha2.exr` | `0,99` | `0,3749` |
| `HDRI_tarde.exr` | `4,03` | `1,2256` |
| `HDRI_noite.exr` | `4,97` | `0,3852` |

O levantamento descreve o código e os arquivos disponíveis no workspace, com validação automatizada. A renderização no navegador, os valores cadastrados de uma obra específica e a versão atualmente publicada ficaram fora da verificação. A direção é astronômica e as sombras são geométricas. A intensidade, a cor da luz e o preenchimento são calibrações visuais do viewer, sem um modelo de clima, cobertura de nuvens ou inter-reflexões entre superfícies do edifício e da cidade.

Fontes no código: [teste dos EXRs reais](../tests/exr_decoder_test.mjs#L26), [teste de sombras](../tests/solar_shadow_test.mjs), [teste de cache de ambiente](../tests/solar_environment_cache_test.mjs), [teste de materiais do terreno](../tests/geo_terrain_pbr_test.mjs).
