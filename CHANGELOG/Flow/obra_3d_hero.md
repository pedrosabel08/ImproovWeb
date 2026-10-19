# Apresentação 3D da obra

## Loading em duas etapas — 19/10/2026

- Sem metadados: Thinking Orbs centralizado no hero inteiro; informações e camada 3D reservadas até os dados chegarem.
- Com informações: nome/cards disponíveis e loading apenas no slot do prédio. Se o GLB já estiver pronto, revela diretamente o modelo.
- Consulta inicial também mantém loading no slot quando os dados chegam antes do cadastro do modelo. Somente uma orb ativa por vez; aria-busy acompanha as duas pendências.
- Validado no navegador autenticado em CIB_HYP/102: estado inicial centralizado, etapa com informações e loading à direita, conclusão. Desktop, notebook 1366x768, iPad 1024x768/768x1024 e mobile 390x844 sem overflow horizontal. PHP e JS sem erros de sintaxe.
- Console registrou erro preexistente de JSON no Kanban (Entregas/script.js), fora deste ajuste.
- Aba antiga estava no domínio de produção e a revisão automática bloqueou seu reload; validação realizada na aba local oficial, sem acessar produção.

## Entrada priorizando a apresentação 3D — 19/10/2026

Corrigido o flash em que os skeletons da tabela apareciam primeiro e depois eram deslocados pelo hero.

- Consulta de cadastro do modelo iniciada no head, antes dos scripts/consultas operacionais. O bootstrap consome a mesma Promise; não repete `modelos_3d`.
- Hero já presente no HTML inicial, com altura reservada, estado checking e preparação contextual. Informações ficam reservadas até chegarem os metadados reais, sem valores fictícios.
- Havendo modelo, substitui a preparação pelo loading existente no mesmo espaço e inicia o GLB independentemente de `infosObra.php`. Tabela e dados continuam carregando em paralelo abaixo do hero.
- Sem modelo, oculta a preparação e libera a tabela normal. Falha na consulta também libera a área operacional; falha no GLB mantém o erro contextual existente. `aria-busy` e estado explícito indicam conclusão.
- Thinking Orbs global antecipado com defer e reutilizado; instância inicial descartada antes do loading do modelo, evitando animações duplicadas. Abort, geração e descarte preservados. Obras sem modelo não carregam Three/GSAP/GLB; o módulo global de Orbs agora atende a preparação inicial também.
- Menu Prédio 3D oculto durante a checagem, aparecendo somente após confirmar o cadastro.

Validação na CIB_HYP/102: primeiro estado observado loading, tabela começando em y=867 no desktop; após ready, tabela ainda em y=867, um canvas e aria-busy=false. Log Apache confirmou uma consulta `modelos_3d` antes de `infosObra.php` no ciclo. Fluxo revisto em notebook, iPad landscape/portrait e mobile; no portrait e mobile, a tabela começou respectivamente em y=1024 e y=844 durante o loading. Sem overflow horizontal.

ABF_ALM/85: estado absent, hero oculto, tabela em y=90, resumo com 78 imagens, zero canvases Three e zero scripts GSAP. Sem falhas novas de console. Sintaxe PHP/JS e diff verificados; não foi injetada falha artificial de rede. Evidência de primeira tela: `obra_loading_inicial.jpg`.

## Aproximação inicial — 19/10/2026

Ajuste pontual solicitado: zoom ortográfico inicial e do botão Enquadrar passou de 1 para 1,35, ampliando o prédio em 35% para aproximar o hero da referência. Nenhuma mudança de iluminação, inclinação, terreno, layout ou controles. Validação visual e de minimap na obra 102; evidência `obra_3d_aproximado.jpg`.

## Configuração do viewer completo na obra — 19/10/2026

Conforme pedido posterior, a apresentação volta aos parâmetros solares do viewer completo e passa a usar sua projeção ortográfica. Esta seção substitui o balanço de luz da etapa de contraste abaixo.

- OrthographicCamera com near 0,01/far 100000; enquadramento pela diagonal dos bounds × 1,3 e proporção da viewport, como a vista completa sem trajetória solar. Azimute inicial 1,15:1,25 da vista completa; componente vertical reduzida para preservar a inclinação baixa do hero.
- Zoom por botões adaptado para camera.zoom, incluindo animação, limites, interrupção e reset no enquadramento. Mudanças de zoom notificam o controlador existente para recalcular o recorte do minimap.
- DPR máximo 2, PCFSoftShadowMap, mapa de sombra 2048² e radius 2. Intensidade solar calculada integral e HemisphereLight em 0,45, sem o multiplicador 0,85 nem o fill diurno adicional da etapa anterior. Tone mapping, exposure e tratamento de vidro já compartilhados permanecem iguais.
- Sol continua no instante real da abertura; não foram adicionados controles de data/hora. Círculos, grid e halo existentes preservados; terreno/entorno continuam ocultos. Bounds e frustum usam a arquitetura visível, sem inserir terreno/receptor adicional.

Modificado apenas `Dashboard/obra-3d-viewer.js` nesta etapa, além desta documentação e evidência `obra_3d_viewer_alinhado.jpg`.

Validação autenticada na CIB_HYP/102: zoom +/−, enquadramento, órbita, minimap com um canvas/apenas prédio e retorno ao topo. Revisão em desktop 1912px, notebook 1366×768, iPad 1024×768/768×1024 e mobile 390×844; sem overflow horizontal. Sintaxe e testes solar_position/solar_shadow passaram. Console sem erros/warnings, viewport restaurado.

## Contraste e legibilidade das sombras — 19/10/2026

Investigação antes da mudança, na obra 102:

- O modo solar tinha luz direta calculada até 2,8 e HemisphereLight em apenas 0,45, contra 1,65 no modo de navegação. O ambiente procedural escuro também fornece pouco preenchimento indireto. A oclusão das sacadas/lajes deixava a fachada dependente desse fill baixo.
- Comparação temporária sem shadow map confirmou a contribuição da oclusão. Teste com bias dez vezes maior e normalBias quatro vezes maior não removeu o bloco escuro; não houve evidência de shadow acne como causa principal. Configurações temporárias foram removidas.
- GLB v0007: parede não metálica (metallicFactor 0, roughness padrão 1). Inspeção dos bounds das geometrias não apontou plano externo acima do prédio; elementos altos correspondem à fachada/ACM, e o grande piso está no térreo. Vidros continuam excluídos dos bloqueadores solares. Isso não constitui auditoria individual de cada triângulo.
- Shadow map 2048² e frustum ajustado aos bounds visíveis. Testes de frustum passaram em 108 combinações. Bias efetivo permanece −0,00005 e normalBias proporcional ao raio × 0,00025.
- No shader Three r180, PCFSoftShadowMap utiliza kernel fixo e não aplica shadow.radius na sombra direcional. Apenas aumentar radius nesse modo não ampliaria a suavização.

Mudança final restrita a `Dashboard/obra-3d-viewer.js`: intensidade direta calculada × 0,85; fill diurno 0,85 (noturno continua 0,45); filtro PCFShadowMap com radius 2. Sombras continuam habilitadas, resolução e biases mantidos. Exposure permanece 1. Posição/cálculo solar, Norte, câmera, materiais, layout e minimap preservados. O viewer completo não foi modificado nesta etapa.

Comparação visual antes/depois com o mesmo instante solar (19/10/2026 16:14:18.816 UTC), azimute 310,7278° e elevação 65,4758°, mesma viewport desktop e enquadramento. Evidências: `obra_3d_sombra_antes.jpg` e `obra_3d_sombra_depois.jpg`. Instante fixado somente durante a comparação; código final voltou a usar o instante real de abertura.

Validação: órbita, responsividade desktop/notebook/iPad landscape/portrait/mobile, minimap e retorno. Console sem erros. Sintaxe dos módulos e testes solar_position/solar_shadow passaram. Viewport restaurado e arquivos temporários removidos.

## Acabamento do hero conforme nova referência — 19/10/2026

Esta referência passa a orientar o acabamento visual. Alterações restritas a `Dashboard/obra-3d-stage.js` e à integração visual em `Dashboard/obra-3d-viewer.js`:

- Três círculos concêntricos feitos com `THREE.LineLoop`, geometria no plano XZ, na base real dos limites do prédio. Linhas de baixa opacidade (0,13/0,22), com profundidade e perspectiva da câmera; não são overlay 2D e não projetam nem recebem sombras.
- Indicador solar substituído por disco e oito raios finos, cinza frio, sem textura, glow, flare ou luz adicional. Orientação visual acompanha a câmera; posição continua no mesmo ponto solar calculado. O cálculo, instante de abertura e iluminação solar não foram alterados.
- Stage separado do modelo e de seus bounds, preservando enquadramento, sombra e recorte do minimap. Stage e Sol ocultos desde o início da transição ao minimap; ao retornar, reaparecem. Geometrias e materiais descartados no cleanup existente.
- Grid CSS sutil e halo frio mantidos. Sem HDRI, novos elementos arquitetônicos ou alterações em cards, filtros, header, Norte, câmera/OrbitControls, GSAP, vidro ou iluminação.

Validação na sessão autenticada de CIB_HYP/102, localhost:8066: órbita, enquadramento, scroll, minimap apenas com prédio e clique de retorno. Revisão visual em desktop 1912px, notebook 1366×768, iPad 1024×768/768×1024 e mobile 390×844; largura mobile sem overflow. Um canvas e console sem erros/warnings. Viewport restaurado ao final. Verificação geométrica com Three r180 confirmou três anéis coplanares, bounds de entrada intactos e ausência de luzes/sombras adicionais. Sintaxe dos dois módulos validada.

Evidência visual: `obra_3d_stage.jpg`.

Implementação aditiva na tela `Dashboard/obra.php`. Header, filtros, cards operacionais, HTML/CSS da tabela e suas regras foram preservados.

## Arquivos

Criados:

- `Dashboard/obra-3d.js`: consulta de cadastro, metadados, espelhamento dos cards, loading e tratamento de erro.
- `Dashboard/obra-3d-viewer.js`: cena transparente, câmera, OrbitControls, enquadramento, zoom, bússola e descarte de recursos.
- `Dashboard/obra-3d-scroll.js`: transformação do mesmo canvas, breakpoints e retorno ao topo.
- `Dashboard/obra-3d.css`: estilos restritos à nova apresentação.
- `MapaPavimento/predio-3d/model-loader.js`: configuração compartilhada de GLTFLoader/DRACOLoader.
- Este documento.

Modificados:

- `Dashboard/obra.php`: seção inicialmente oculta abaixo do header, CSS, import map e bootstrap modular.
- `Dashboard/scriptObra.js`: publicação dos metadados já retornados por `infosObra.php`, após a verificação da requisição atual. Nenhuma regra da tabela foi alterada.
- `MapaPavimento/predio-3d/visualizador-glb-navegacao.js`: passa a usar a configuração de loader extraída para o módulo compartilhado.

## Cadastro e fontes dos dados

O bootstrap consulta `MapaPavimento/api.php?action=modelos_3d&obra_id=...`, que verifica sessão e acesso à obra e lê `mapa_modelos_3d`. A seleção inicial segue a do viewer completo: primeiro modelo em `ORDER BY nome,id`. Sem cadastro, a seção continua oculta e Three.js/GSAP/GLB não são carregados.

| Informação       | Fonte                                                                                                                |
| ---------------- | -------------------------------------------------------------------------------------------------------------------- |
| Nome             | `infosObra.php → obra.nome_real`, com fallback para `nome_obra`                                                      |
| Localização      | `infosObra.php → obra.local`; omitida quando vazia                                                                   |
| Total de imagens | Texto do card original `#imagens-totais .imagens-totais-texto`, produzido por `atualizarResumoImagens`               |
| Pendências       | `#dashboardPendenciasTotal` e `#dashboardPendenciasCaption`, calculados pelo dashboard a partir de `OBRA_PENDENCIAS` |
| Entregas         | `#dashboardEntregasTotal`, consolidado pelo dashboard a partir dos cards de entregas                                 |
| Norte            | `MapaPavimento/api.php?action=espacial_configuracao → configuracao.norte_graus`                                      |

Um MutationObserver restrito ao dashboard espelha seus valores finais; assim, inclusive atualizações da busca permanecem coerentes com os cards existentes. Não há contagens ou nomes de obras fixados no código.

## Reaproveitamento do 3D

- Three.js r180, OrbitControls e a mesma configuração Draco 1.5.7 do viewer existente.
- `viewer-environment.js` para iluminação/reflexos e fundo transparente.
- `floor-visibility.js → visibleModelBounds` para limites das superfícies visíveis.
- `north-direction.js` para a direção Norte e a mesma projeção horizontal do `NorthIndicator` existente.

Foi criado um controlador leve para a apresentação. O viewer completo instala também seleção de pavimentos, mapa, inspeção, medição e iluminação solar; inicializar toda essa interface dentro da obra criaria controles e processamento desnecessários.

O enquadramento usa a esfera dos limites reais, FOV vertical/horizontal e proporção do canvas, sem posições específicas da obra 102. Zoom dos botões é animado com GSAP e limitado por `minDistance`/`maxDistance`. OrbitControls mantém botão esquerdo para órbita, roda para zoom e botão direito para pan. As setas do teclado também permitem pan quando o canvas recebe foco.

Na apresentação, objetos explicitamente nomeados `TERRENO`, `TERRAIN`, `GROUND` ou `ENTORNO` são ocultados para destacar a arquitetura; não são removidos do arquivo e permanecem disponíveis na visualização completa. A identificação não depende de IDs da CIB_HYP.

## GSAP, minimap e ciclo de vida

A timeline usa o scroller real `.container`, `start: 0`, duração de scroll derivada da seção e `scrub: 0.25`. Ela anima `x`, `y` e escala uniforme do mesmo layer; a toolbar perde opacidade no início. Não há pin, espaçador ou alteração das dimensões da tabela.

O destino utiliza a projeção dos limites do edifício para manter o modelo e sua área clicável no respiro lateral à direita, abaixo do header. O recorte remove somente margens transparentes. Ele é recalculado após mudanças de câmera e de viewport. Câmera/controles são desabilitados durante a redução; no minimap somente o botão transparente de retorno recebe interação. O retorno anima `scrollTop` da mesma `.container` e devolve foco ao canvas.

Abaixo de 768px não há minimap fixo: o viewer acompanha sua seção normalmente. O layout empilha informações e viewer em telas de até 900px. `prefers-reduced-motion` elimina suavização, entrada e retorno animados, mantendo o progresso diretamente ligado ao scroll.

Há apenas uma cena, câmera, instância de loader, renderer e canvas por ciclo da obra. Scroll, zoom e resize não invocam novo carregamento do GLB. A renderização é sob demanda, com um único RAF pendente e atualizações adicionais somente durante interação/damping. Aba oculta cancela o frame pendente. ResizeObserver, eventos, timelines, geometrias, materiais, texturas, ambiente e Draco são descartados na troca de obra/saída. O retorno pelo histórico recria corretamente a página.

Loading usa o Thinking Orbs global, estado `composing`, Canvas transparente e label em português; sua instância é destruída ao terminar. Erros ficam restritos à região do viewer e são registrados com o ID da obra no console. Falha na consulta espacial usa a convenção do modelo e não impede o GLB.

## Orientação e rota completa

Convenção compartilhada: +Y vertical; 0° aponta para −Z; 90° para +X; giro horário visto de +Y. Sem Norte cadastrado, a indicação usa −Z e informa no título/label que o Norte geográfico não está definido. Nenhuma latitude/longitude é inferida.

Rota do botão principal:

`MapaPavimento/predio-3d/visualizador-glb-navegacao.php?obra_id=<id>&modelo_id=<id>`

Na validação: `obra_id=102&modelo_id=4`. O clique no minimap retorna à seção principal, sem navegar para essa rota.

## Validação em 19/10/2026

As duas URLs oficiais abriram o login. A validação prática foi realizada autenticada em `http://localhost:8066/ImproovWeb/`, navegando pelo menu Projetos Ativos. O navegador IAB não estava disponível na ferramenta; foi usado o Edge conectado à automação. Ocorreram timeouts transitórios de leitura durante navegações, recuperados por inspeção da aba e screenshots.

Verificado na CIB_HYP / 102:

- Carregamento do modelo cadastrado 4; nome CIBEA Hyperion.
- Dados reais: 42 imagens, 15 pendências, 12 críticas, 1 entrega.
- Órbita com arraste e atualização da indicação de Norte cadastrado, 84,7°.
- Zoom por roda, botões +/−, enquadramento e pan por setas do teclado.
- Abertura da rota completa com os dois IDs, carregamento do viewer existente e volta pelo histórico.
- Scroll intermediário e rápido, minimap sem toolbar/bússola, clique para retorno completo ao topo e repetição do ciclo.
- Um único canvas; log do Apache sem nova requisição de `modelo_3d_arquivo` nos ciclos de scroll/retorno.
- Busca existente por “Coworking”: uma linha; limpeza da busca: 42 linhas. O resumo 3D acompanhou o total do card original.
- Layout em desktop (1912×914), notebook (1366×768), iPad landscape (1024×768), iPad portrait (768×1024) e mobile (390×844); sem overflow horizontal novo. Em mobile, o modelo saiu da viewport com sua seção, sem ficar fixo sobre a tabela.
- ABF_ALM, sem modelo: apresentação oculta, zero canvases/layers/dependências GSAP/Orbs e tabela anterior com 78 linhas.
- Console das páginas verificadas sem erros; sintaxe PHP/JavaScript e diff sem erros de whitespace.

Limites dos testes: o pan foi exercitado pelo teclado; o gesto de arraste com botão direito é o comportamento padrão de OrbitControls e não foi simulado pela ferramenta. Não foi cadastrado um GLB inválido para provocar erro, nem alterada a preferência de movimento do sistema operacional. A limpeza de listeners foi revisada no código; não foi realizado perfil de heap prolongado.

## Segunda etapa — refinamento visual e HDRI

### Comparação com as referências

A versão inicial tinha título de até 58px em uma linha, cards verticais, viewer limitado a 540px de largura/620px de altura, fundo praticamente uniforme e controles separados no meio da cena. O modelo ocupava aproximadamente 430px de altura na viewport de desktop utilizada. A referência pede maior hierarquia do nome, cards horizontais, modelo mais alto, atmosfera técnica discreta e controles próximos ao topo direito.

O refinamento mantém todas as fontes de dados e comportamentos. Não foram copiados a categoria “Residencial”, a localização de São Paulo, a descrição comercial, os controles extras ou a tabela ilustrada: essas informações/funcionalidades não são fornecidas pelo cadastro atual da obra.

### Ajustes pontuais

- Título responsivo até 120px, peso 700, line-height 0,98 e largura de 11ch; o nome completo quebra naturalmente em duas linhas.
- Cards da apresentação com ícone lateral, fundo translúcido, bordas discretas, números maiores e altura mínima de 124px. Os cards operacionais abaixo não foram modificados.
- Hero utiliza a altura disponível abaixo do header; canvas sem moldura, sem limite artificial de 540px, com mais altura. Enquadramento permanece calculado pelos limites reais; margem do framing passou de 1,12 para 1,04 e a inclinação genérica ficou ligeiramente mais baixa.
- Fundo CSS com halo radial, arco e grid em perspectiva de baixa opacidade. Não há novos objetos de cena, assets ou render loops para essa decoração.
- Toolbar unificada, com bússola circular acima; desktop posiciona o grupo em 30% da altura do viewer. Em telas empilhadas permanece em 50% para não sobrepor os cards.
- A timeline existente foi preservada. Somente o destino do minimap foi refinado: teto de altura de 170px em desktop/132px em telas menores, largura limitada ao respiro lateral real do dashboard, máximo de 96px. O tamanho efetivo depende da proporção projetada do modelo e do espaço disponível; não se reproduz o painel de 200px do mockup porque ele encobriria a área operacional existente. Margem superior e direita de 12px; somente o prédio e sua área clicável aparecem.

### HDRI e renderer

Asset existente reutilizado, sem cópia: `assets/hdri/HDRI_Sunrise_004.hdr` (6.186.336 bytes), exatamente o configurado em `viewer-scroll.html`.

O exemplo usa RGBELoader e PMREMGenerator. Na versão r180 já utilizada pelo projeto, RGBELoader é um alias obsoleto que estende HDRLoader e emite um aviso. O hero importa diretamente HDRLoader, a mesma classe base, para evitar esse aviso. Fonte: [código oficial r180](https://raw.githubusercontent.com/mrdoob/three.js/r180/examples/jsm/loaders/RGBELoader.js).

Fluxo: um `loadAsync` por ciclo do viewer → `EquirectangularReflectionMapping` → PMREM → `scene.environment`. O `scene.background` permanece `null`, com clear transparente; o panorama HDRI não aparece na interface. A orientação do environment é fixa, como `followCamera: false` no exemplo; o cálculo Norte não foi alterado.

Configuração final:

| Parâmetro | Valor |
| --- | --- |
| Output color space | `THREE.SRGBColorSpace` |
| Tone mapping | `THREE.ACESFilmicToneMapping` |
| Exposure | `0.82`, igual ao exemplo |
| Environment intensity | `1` |
| Intensidade PBR por material | valor exportado × `0.6`, igual ao exemplo |
| HemisphereLight | `0.35`, céu `0xdce9f3`, chão `0x17211c` |
| DirectionalLight | `1.0`, cor `0xffe8cb` |

As luzes auxiliares foram reduzidas em relação à primeira implementação (1,65 e 2,25) para compensar a iluminação do HDRI. Cores, roughness, metalness, transparência e transmissão dos materiais originais — inclusive vidro — são preservados. Não foram adicionados pós-processamento, sombras dinâmicas ou materiais artificiais.

O HDR bruto é descartado após a conversão, assim como o PMREMGenerator. O WebGLRenderTarget resultante fica no viewer e é descartado no cleanup; o ambiente procedural anterior é liberado ao substituir a textura e permanece como fallback caso o HDRI falhe. Saída durante um carregamento descarta a textura tardia sem utilizar renderer destruído.

### Escopo dos arquivos nesta etapa

Modificados somente `Dashboard/obra-3d.css`, `Dashboard/obra-3d-viewer.js`, `Dashboard/obra-3d-scroll.js` e este documento. Não houve alteração de endpoints, queries, Norte, bootstrap/dados, GLTF/Draco, header, tabela ou dashboard operacional.

### Verificações adicionais

- CIB_HYP/102, modelo 4: environment HDRI pronto, Norte cadastrado 84,7° e dados espelhados sem números fixos. Nesta sessão os dados operacionais passaram a 42 imagens, 12 pendências, 9 críticas e 1 entrega.
- Órbita e resposta da bússola, pan pelo teclado, zoom com roda/botões e enquadramento testados com o HDRI aplicado.
- Filtro “Fachada”: 5 linhas visíveis e ambos os resumos em 5. Busca “Coworking”: 1 linha e ambos em 1. Filtros e busca restaurados ao final.
- Minimap sem toolbar/bússola, mantendo o ambiente, e scroll rápido testados. Nas operações de câmera, filtros e scroll, o log do Apache manteve as contagens de 13 solicitações do GLB e 12 do HDRI registradas antes desses testes; essas contagens incluem ciclos de páginas anteriores, não downloads por viewer.
- A rota completa da obra/modelo continuou acessível; o HDRI desta etapa é exclusivo da apresentação e não altera o viewer completo.
- Sintaxe dos módulos JavaScript verificada. O aviso inicial do RGBELoader motivou a importação direta de HDRLoader; o caminho de importação novo não emite esse aviso.
- Revisão visual adicional em 1912×867, 1366×768, 1024×768, 768×1024 e 390×844, sem novo overflow. Toolbar/bússola não sobrepõem informações; no mobile, o viewer permanece absoluto e sai da viewport junto com a seção.
- No desktop de 1912px, o minimap final ocupou aproximadamente 53×108px, com 12px abaixo do header e 12px de margem direita; não há painel ou controles. Retorno ao topo confirmado com foco no canvas, seguido de novos ciclos.
- A contagem posterior de 15 requisições do GLB/12 do HDRI também permaneceu estável em outro ciclo, após testes de resize; novas navegações de página são ciclos independentes.
- ABF_ALM foi novamente aberta pelo menu: 78 linhas, hero oculto, zero canvases e zero dependências de GSAP/Orbs. Nenhum erro no console nessa verificação.


## Refinamento solar ? 19/10/2026

Esta etapa substitui a configura??o HDRI descrita acima, conforme orienta??o mais recente.

- HDRLoader, download HDR e ajuste artificial de intensidade dos materiais removidos. Ambiente procedural transparente reutilizado do viewer completo.
- Sol calculado com as mesmas fun??es de solar-position.mjs e Norte salvo. solarAtInstant preserva o instante real de abertura, inclusive segundos e repeti??o do rel?gio em hor?rio de ver?o. N?o h? timer, painel, trajet?ria ou controle de data/hor?rio.
- Luz direcional e indicador solar usam o mesmo vetor geogr?fico; Sol abaixo do horizonte fica oculto. Cadastro espacial incompleto mant?m ilumina??o de navega??o, sem inventar coordenadas.
- Enquadrar usa fa-expand. Acesso ao viewer completo usa fa-cube no final da toolbar, com ordem de teclado correspondente.
- C?rculo decorativo do hero removido; b?ssola principal preservada.
- Minimap quadrado de 160px no desktop e 120px no tablet, com borda, fundo e cantos discretos. Apenas pr?dio; b?ssola, toolbar e marcador solar ocultos. Mesmos renderer/modelo/canvas. Clique retorna ao topo com foco no canvas.

### Valida??o desta etapa

Sess?o autenticada existente no Edge, em http://localhost:8066/ImproovWeb/, obra CIB_HYP/102. Mantida alternativa ao navegador interno indispon?vel registrada na etapa inicial.

Verificados: apresenta??o com Sol, ?cones, zoom, enquadramento, transi??o, minimap quadrado (~160 ? 160px), toolbar oculta e retorno ao topo. Um ?nico canvas. Instant?neo solar permaneceu inalterado em scroll, controles e resize. Sem overflow horizontal no mobile (390px).

Revis?o visual em desktop 1912px, notebook 1366?768, iPad 1024?768 e 768?1024, mobile 390?844. Overrides de viewport restaurados ao final. Nenhum erro novo ap?s corrigir cache do m?dulo solar; importa??o versionada evita export antigo no navegador. Contagem Apache do HDRI permaneceu em 12 durante toda esta etapa: nenhuma nova requisi??o HDR.

Sintaxe dos tr?s m?dulos hero validada. solar_position_test.mjs passou, incluindo novos casos de instante preciso, equival?ncia com c?lculo anterior, instante inv?lido e hor?rio repetido. Os testes opcionais solar_shadow_test e solar_projection_test exigem caminho local Three r180 e n?o executaram sem esse pr?-requisito; esses componentes n?o foram alterados.

Evid?ncia: obra_3d_solar.jpg. Dados, filtros, tabela, endpoints e viewer completo preservados.


## Paridade de ilumina??o com o viewer completo ? 19/10/2026

- HemisphereLight alinhada ao viewer completo: c?u 0xe9f1ff, ch?o 0x7b827b; luz direcional de navega??o 0xfff6e8. Modo solar preserva cor 0xffe4b3, intensidade calculada e ambiente 0,45.
- Sombras PCFSoftShadowMap de 2048px, radius 2, frustum e biases pelo mesmo fitSolarShadow. Paredes/lajes projetam e recebem sombra; classifica??o solarOpaqueMaterial exclui vidro adaptado. Sombras recalculadas somente quando preparadas, sem custo recorrente durante ?rbita/scroll.
- Tratamento de vidro extra?do sem mudan?a de valores para viewer-glass.js, reutilizado pelos dois viewers. Clone mant?m material original intacto; transmission 0, opacity at? 0,16, roughness entre 0,16 e 0,3, reflexos limitados a 0,12 e demais par?metros id?nticos ao viewer completo. Materiais e shadow map liberados no cleanup.
- Sol continua fixo na abertura; HDRI ausente. Layout, c?mera de apresenta??o, minimap e dados operacionais preservados.

Valida??o na sess?o autenticada do localhost:8066: obra 102/modelo 4, acesso ao viewer completo pelo cubo e retorno pelo hist?rico. Ambos renderizaram sem erros/warnings. Zoom, enquadramento, scroll, minimap e retorno ao topo funcionais, um canvas. Revis?o visual em desktop, 1366?768, 1024?768, 768?1024 e 390?844; sem overflow mobile. Viewport restaurado.

Sintaxe JS validada. solar_shadow_test (108 combina??es), solar_position_test e solar_projection_test passaram com Three r180 local. Verifica??o adicional com MeshPhysicalMaterial real confirmou preserva??o do material original e exclus?o de vidro como bloqueador solar. Evid?ncia: obra_3d_iluminacao_fiel.jpg.
