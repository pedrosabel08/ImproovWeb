# Mapa arquitetônico — Fase 2

## Medição 3D no empreendimento

Em **Modelo 3D → Hub de inspeção → Medir**, escolha Distância, Horizontal ou Vertical e clique em duas superfícies do GLB. O primeiro clique define A; o movimento do ponteiro mostra a prévia até a interseção atual; o segundo confirma B. **Nova medição** inicia outro par sem remover as cotas concluídas. A lista e as etiquetas da cena permitem selecionar uma medição; alterar seu tipo recalcula o resultado dos mesmos pontos.

Os pontos são `hit.point` do Raycaster existente em **world space**, sem usar centros ou bounding boxes. A escala é fixa: **1 unidade = 1 metro**. Distância usa `pointA.distanceTo(pointB)`; Horizontal usa somente X/Z; Vertical usa `abs(B.y - A.y)`. Resultados sempre em metros, com duas casas decimais e vírgula decimal, inclusive abaixo de 1 m. Não há calibração manual.

`measurement-geometry.js` contém os cálculos, projeções e filtro de superfícies. `model-measurement.js` mantém os pares `{ id, tipo, pointA, pointB, valor }`, prévia, painel e recursos gráficos. O visualizador fornece o Raycaster compartilhado e atualiza as etiquetas no ciclo de render já existente. A medição considera todos os meshes do modelo, inclusive terreno/contexto, descartando ancestrais invisíveis, materiais invisíveis e partes cortadas. Anéis, eixos, Sol e cotas não participam do picking.

Marcadores A/B e etiquetas usam o mesmo mecanismo HTML + `Vector3.project` já empregado no Norte e no Sol; acompanham câmera, zoom, pan e resize, com tamanho legível em pixels. A linha principal representa o tipo escolhido; para Horizontal e Vertical, uma guia discreta liga a projeção ao segundo ponto real. Geometrias de linha são atualizadas sem recriação durante a prévia. O Raycaster da prévia é consultado no máximo uma vez por frame.

Medir é exclusivo: cliques não abrem pavimentos nem selecionam objetos, e a árvore fica inerte. OrbitControls continua permitindo rotação/pan/zoom, mas arrastar ou fazer um gesto com vários ponteiros não confirma pontos. Uma espera temporária não desativa o modo; se o modo mudar entre pressionar e soltar, o gesto é descartado. **Sair do modo**, **Inspecionar** e **Sol** restauram a navegação. **Esc** cancela somente o par incompleto; **Delete** remove a medição selecionada, sem atuar em campos de formulário. **Limpar** remove o par atual/selecionado; **Limpar todas** remove todas as cotas. Entrar no mapa ou definir Norte suspende as sobreposições. Trocar obra/modelo ou recarregar a página descarta as medições e libera geometrias, materiais e etiquetas.

As medições existem somente na sessão do visualizador. Não há gravação no banco, área, ângulo, perímetro, snap, exportação ou histórico persistente.

```text
node tests/model_measurement_test.mjs --three-module=CAMINHO/three.module.js
```

O teste executa os módulos de produção com Three.js r180. Confere fórmulas, formatação, raycast real em mesh com translação/rotação/escala, visibilidade/cortes, prévia, várias medições, seleção/remoção, câmera, resize e descarte na troca de modelo.

QA autenticado em 15/10/2026 pelo Edge conectado, via menu do Flow → Mapa de pavimento → CIB_HYP → Modelo 3D. Conferidos largura entre superfícies (26,48 m), prévia antes de confirmar B, diferença vertical entre os pontos escolhidos (153,51 m) e horizontal (38,82 m) com 135,63 m de diferença de altura ignorada. As três cotas coexistiram na cena e as etiquetas acompanharam a rotação sem alterar os valores. Nova medição manteve o modo exclusivo; Esc descartou somente o par incompleto; Delete removeu somente a cota selecionada. Sair permitiu abrir o mapa do Térreo e retornar com as cotas preservadas; Limpar todas removeu todas as etiquetas e marcadores. Desktop 1912×866, notebook 1366×768, iPad 1024×768/768×1024 e celular 390×844 conferidos sem overflow horizontal. No celular, o modo Medir mantém canvas e Hub juntos na tela, com resultado compacto e rolagem somente nos controles. Nenhum dado de projeto foi gravado por esses testes.

## Envio de modelos 3D e armazenamento no VPS

O botão **Modelo 3D** no Mapa arquitetônico abre o empreendimento da obra selecionada em outra aba. Usuários dos níveis 1/2 podem usar **Enviar modelo 3D** para criar um modelo ou enviar uma nova versão do modelo selecionado. O nome de apresentação é escolhido no formulário; o arquivo físico recebe automaticamente este padrão:

```text
uploads/model-3d/obra-102/obra-102_modelo-4_v0001_20261014T174606Z_583ff4ffd2b5.glb
```

O nome combina IDs de obra/modelo, número sequencial da versão, data UTC e identificador aleatório. Não depende do nome original nem de caminhos enviados pelo usuário. Arquivos binários e caches ficam fora do Git. O banco armazena caminho, nome original, tamanho, SHA-256, responsável e datas em `mapa_modelo_3d_versoes`; `mapa_modelos_3d.arquivo` aponta para a versão ativa.

No VPS Linux, o arquivo é salvo diretamente no diretório `uploads/model-3d` da instalação. No ambiente Windows, o módulo usa a conexão **IMPROOV_VPS_SFTP_*** já configurada em `.env`: o GLB é validado, transferido para um arquivo temporário no VPS e renomeado somente após a conclusão. O cadastro só é confirmado depois dessa transferência. Uma cópia local permite visualizar o modelo no desenvolvimento; caso ela não exista, é recuperada do VPS. `IMPROOV_MODEL3D_STORAGE=local|sftp` permite configurar explicitamente o modo. A chave do servidor SFTP é validada pela fingerprint já usada nos scripts de implantação, com override por `IMPROOV_VPS_SFTP_HOST_KEY_SHA256`.

O upload aceita GLB 2.0 autocontido, com texturas incorporadas e pavimentos identificáveis pela nomenclatura `PAVTO_...`. Valida cabeçalho, comprimento, chunks, JSON, dados binários e ausência de recursos externos. O limite do módulo é 500 MiB, reduzido automaticamente se o PHP permitir menos. `.htaccess` (mod_php) e `.user.ini` (PHP-FPM) configuram `upload_max_filesize=500M` e `post_max_size=512M`. No VPS, alinhar também o limite e o timeout do proxy, por exemplo `client_max_body_size 512m` no Nginx. A interface informa o limite efetivo do PHP e mostra o progresso do envio com Thinking Orbs.

**Versões** lista o histórico e permite **Usar esta versão**. As versões anteriores não são apagadas. Atualizações e restaurações verificam o arquivo ativo para detectar concorrência e recusam versões sem os pavimentos que já possuem vínculos, indicando os nomes ausentes. Conteúdo idêntico no mesmo modelo é reconhecido pelo SHA-256 e não cria outra versão. Ao atualizar um cadastro legado, o arquivo original entra no histórico como versão 1.

Os GLBs são servidos por `modelo_3d_arquivo` na API, com sessão autenticada e autorização da obra. A pasta possui `.htaccess` negando acesso direto; em instalações Nginx sem Apache, configurar também o bloqueio de `/uploads/model-3d/` e de `.user.ini` no servidor. Incluir **arquivos do VPS e banco** no backup. A pasta deve ser persistente entre deploys, com permissão de gravação para o PHP ou para a conta SFTP configurada.

Preparação do banco, idempotente e sem alterar modelos existentes:

```text
php scripts/migrate_mapa_modelos_3d_upload.php
php tests/modelo_3d_upload_test.php --fixtures
```

O teste HTTP exige `IMPROOV_QA_LOGIN` e `IMPROOV_QA_PASSWORD` apenas no ambiente do processo. `php tests/modelo_3d_upload_http_test.php` cria um modelo temporário em TES_TES, testa login/CSRF, isolamento, upload e download, bloqueio do arquivo direto, atualização, duplicidade, conflito, restauração, preservação dos vínculos e cache recuperado do VPS. Remove somente sua própria fixture. `--keep` mantém essa fixture para QA visual e `--cleanup` a remove depois. Não utiliza conteúdo de CIB_HYP.

Para completar a transferência de um upload local feito antes da ativação do SFTP: `php scripts/sync_modelo_3d_upload.php --modelo=ID`. Esse comando não modifica o cadastro nem sobrescreve arquivos remotos. `--verify` confere tamanho e SHA-256 no VPS sem alterar o arquivo. Em 14/10/2026, o modelo 4 enviado pelo usuário foi transferido e sua integridade foi conferida dessa forma; nenhum mapa ou vínculo do CIB_HYP foi modificado pelo agente.

## Workspace espacial: Planta e Modelo

Planta (PDF.js) e Modelo (Three.js) compartilham `assets/css/components/spatial-workspace.css`: tokens `--spatial-*`, tipografia, header, botões, inputs, toolbars, painel de inspeção, foco, estados e dicas de navegação. O acabamento estrutural do 3D foi extraído de `solar-premium.css`; as regras específicas do Sol permanecem nesse arquivo. `workspace-layout.css` organiza apenas o layout da Planta e seus controles específicos. `style.css` mantém formulários, galerias, diálogos e recursos do mapa; `embedded.css` contém somente as diferenças do contexto incorporado.

O mapa independente usa a altura restante abaixo do header, com rolagem dentro da inspeção. No fluxo 3D → Planta, o header 3D permanece e **Alterar vínculo** ocupa a barra contextual do mapa, sem repetir seu título. Camadas, área útil e inspeção ficam na toolbar do viewport; a contagem indica camadas **visíveis**. Ambientes mantêm seleção, imagens e ações existentes. Criação/edição mostram estado explícito e Cancelar. O tema inicial é escuro; a preferência clara salva continua sendo respeitada.

`PlantaViewer(container, { fitPadding: 40 })` contém o documento/recorte com margem de 40 px nos eixos limitantes, reduzida proporcionalmente em viewports pequenos. O padrão do componente reutilizável continua em 20 px. A altura da toolbar e do cabeçalho já está descontada da área medida pelo viewer. Fit não muda orientação, rotação, recorte ou coordenadas das demarcações. O PDF vertical usa a altura disponível; o horizontal usa a largura, conforme a proporção real. Nenhuma API, regra de vínculo ou formato de geometria foi alterado.

Verificação de enquadramento e preservação de coordenadas:

```powershell
node tests/planta_viewer_fit_test.mjs
php tests/mapa_pavimento_rules_test.php
php tests/mapa_pavimento_preparacao_test.php
```

QA em 14/10/2026, TES_TES/Lazer, pela URL oficial HTTPS e login de desenvolvimento no Edge conectado (navegador interno indisponível). Comparadas Planta independente, Planta incorporada e Modelo 3D em desktop 1912×866, notebook 1366×768, iPad 1024×768/768×1024 e mobile 390×844. No desktop, a altura enquadrada do recorte passou de aproximadamente 435 para 604 px. Conferidos zoom, pan, Fit/reset, rotação, camadas 0/todas e cancelamento, inspeção de camadas, recorte/cancelamento, criação/cancelamento, edição de pontos, formulário e imagens vinculadas, confirmação de exclusão com cancelamento, página única automática, bloqueio de saída durante edição e retorno ao 3D. Temas claro/escuro e rolagem interna conferidos; consoles sem erros. Os testes acima passaram, incluindo 96 combinações geométricas no viewer.

## Sol astronômico na navegação 3D

No visualizador integrado, o **Sol** começa ativado quando o empreendimento tem configuração espacial válida, com projeção e trajetória habilitadas. Escolha a data e o horário local da obra, ou use o slider de 00:00 a 23:55 em passos de cinco minutos. O campo de horário também aceita minutos individuais e permite testar a madrugada. **Agora** usa a hora atual no fuso da obra. Essas mudanças atualizam a mesma luz direcional e suas sombras, sem recarregar o GLB. Data, horário e ativação pertencem à visualização atual; coordenadas, Norte e fuso permanecem na configuração persistida por obra.

O painel organiza horário, azimute/elevação, fuso/Norte, status e **Opções visuais**. **Mostrar bússola do entorno** começa desligado: quando ativado, desenha anel e marcas na base, com N/L/S/O orientados pelo Norte salvo. O HUD discreto continua disponível sem esse anel. A seta técnica aparece somente durante a definição visual do Norte. Mapa 2D e edição do Norte ocultam temporariamente o anel, preservando a opção ao retornar.

O astro usa núcleo 3D e halo estático procedural com brilho aditivo, sem pós-processamento ou dependências novas. A distância visual do astro/arco é independente da luz física. Raios têm transparência e atenuação visual na origem; marcações horárias próximas são ocultadas para evitar sobreposição. A folha `solar-premium.css` centraliza o acabamento e a adaptação para desktop, notebook, tablet e mobile. Os cálculos astronômicos, coordenadas e Norte persistidos permanecem inalterados.

QA visual em 14/10/2026, TES_TES/modelo 1, pela URL oficial HTTPS com login de desenvolvimento, no Edge conectado (navegador interno indisponível nesta sessão). Conferidos desktop 1440×900, notebook 1366×768, iPad landscape 1024×768, iPad portrait 768×1024 e mobile 390×844; controles independentes, madrugada às 02h, manhã às 09h, tarde às 16h, ativação/desativação e retorno do mapa Lazer preservando horário/opções. Console sem erros. Norte salvo de 83.5° preservado. Testes de posição solar, projeção, sombras (108 combinações), direção do Norte e ciclo de vida da bússola aprovados com Three.js r180.

O fluxo usa `espacial_configuracao` e o Norte salvo, sem uma segunda configuração de orientação. Sem latitude, longitude, Norte ou fuso válido, o Sol permanece indisponível e o painel oferece a configuração existente. Zero é um Norte válido; `NULL` não vira −Z automaticamente. Durante a edição visual do Norte, o Sol fica pausado; a luz só passa a usar a nova orientação após salvar as configurações espaciais.

Dependências locais e fixas em `assets/libs/solar`: [SunCalc 1.9.0](https://github.com/mourner/suncalc/tree/v1.9.0) e [Luxon 3.7.2](https://moment.github.io/luxon/api-docs/index.html), com licenças e hashes. O cálculo não envia localização a um serviço externo. Não atualizar SunCalc sem rever a conversão: a versão utilizada devolve altitude e azimute em **radianos**, com azimute começando no sul e crescendo para oeste.

**Conversão:** `azimuteVerdadeiro = (azimuth * 180 / PI + 180) mod 360`. A convenção interna fica 0° Norte, 90° Leste, 180° Sul e 270° Oeste. `northDirection(norte_graus)` é a mesma função usada pela seta e pela bússola. A base Leste é `(-north.z, 0, north.x)`; a componente horizontal é `north*cos(azimute) + east*sin(azimute)`. Multiplica-se por `cos(altitude)` e define-se Y como `sin(altitude)`. O resultado é um vetor unitário do empreendimento até o Sol.

**Fuso:** `timezone` IANA é armazenado na tabela espacial existente, validado no PHP e editável no mesmo formulário. O padrão inicial é `America/Sao_Paulo`, incluindo TES_TES. Luxon interpreta a data/hora nessa zona e fornece o instante UTC para SunCalc. O timezone do computador não determina o resultado. Horários inexistentes em transições de horário de verão são recusados; em horários repetidos usa-se explicitamente a primeira ocorrência, indicada na UI. Clientes antigos que omitem o campo preservam o fuso existente.

**Iluminação e sombras:** reutiliza a única `DirectionalLight` preexistente, com raios paralelos. O Sol fica a oito raios do bounding box e mira seu centro. A intensidade é `2.8 * sqrt(sin(altitude))` acima do horizonte e zero quando a elevação é ≤ 0; a HemisphereLight secundária permanece suave (0.45). Desativar Sol restaura a iluminação de navegação. O indicador é um mesh sem sombra e sem picking, acompanhado de uma etiqueta projetada; quando fora do enquadramento, a etiqueta indica a direção na borda.

Sombras usam PCFSoftShadowMap, 2048×2048. Os oito cantos do bounding box e o receptor local do chão são transformados para o espaço da luz, de onde vêm left/right/top/bottom/near/far. Bias e normalBias acompanham a escala do GLB. O receptor é dimensionado pelas projeções dos cantos no chão para conter as sombras diurnas; sua extensão é limitada a dez raios do empreendimento perto do horizonte, evitando um plano infinito e um shadow map impraticável. A câmera de navegação não define a direção da luz nem o frustum. Os limites do modelo são guardados por carregamento; alterar o relógio não percorre novamente todos os meshes. A shadow map é invalidada quando a luz/geometria visível muda.

Lajes, paredes, pisos e demais volumes opacos projetam e recebem sombras. Vidro totalmente transmissivo não é tratado como bloqueador opaco. Os materiais existentes do TES_TES são PBR e foram preservados. A bússola simples N/L/S/O usa o Norte compartilhado e acompanha o giro horizontal da câmera; ela não gira nem reorienta o GLB. A navegação/mapa 2D existente continua com seu comportamento próprio, sem controle solar no PDF.

Aplicar a migração existente em ambientes já instalados acrescenta somente a coluna de fuso, de forma idempotente, sem alterar latitude, longitude, Norte ou versão de edição:

```powershell
C:/xampp/php/php.exe scripts/migrate_mapa_espacial.php
C:/xampp/php/php.exe tests/mapa_espacial_test.php --integration
node tests/solar_position_test.mjs
node tests/north_direction_test.mjs
# Verificação geométrica opcional: indicar um módulo Three.js r180 local, com three.core.js ao lado.
node tests/solar_shadow_test.mjs --three-module=CAMINHO/three.module.js
```

QA em 13/10/2026, TES_TES (74): Norte salvo **83.9°**, latitude **−27.0992256**, longitude **−48.6179817**, America/Sao_Paulo; orientação e versão 4 foram preservadas. Foram conferidos Sol/sombras em 08h, 09h, 12h, 16h e ausência de luz solar/indicador às 02h, Agora, slider de cinco minutos e resposta da bússola ao giro da câmera. O cálculo produz aproximadamente azimute/elevação 74.3°/43.0° às 09h, 0.8°/70.7° às 12h e 277.1°/30.4° às 16h. Testes validam bases Norte/Leste, vetor unitário, intensidade, fusos e transições de horário de verão; processos com TZ=UTC, Asia/Tokyo e America/Los_Angeles retornaram resultados idênticos. O teste geométrico passou em 108 combinações de escala/Norte/azimute/elevação, usando a atualização real de sombras do Three.js r180. Layout conferido em 950×913 e 390×844; abertura/cancelamento da ferramenta de Norte e entrada/retorno do mapa 2D preservaram o Sol da visualização. Console sem erros. Evidências locais em `tmp/solar-qa/`.

### Projeção solar somente no 3D

Com o Sol ativado, **Mostrar projeção solar** desenha até 25 raios paralelos, com setas no sentido Sol → empreendimento e pontos no primeiro bloqueio. **Mostrar trajetória do dia** acrescenta o arco tracejado acima do horizonte e marcações de 06h/08h/10h/12h/14h/16h/18h, quando visíveis e sem sobreposição. Os controles são opcionais, independentes e pertencem à visualização atual. Nenhum recurso foi acrescentado ao PDF/mapa 2D. Ao entrar no mapa os auxiliares são ocultados e os cálculos pendentes são cancelados; ao voltar, as opções são restauradas. Os raios retomam somente depois que a animação de retorno restaurar a visibilidade e os materiais completos do prédio.

Os raios reutilizam `solarAt()` e seu vetor de direção já orientado pelo Norte salvo. O campo 5×5 amostra um plano perpendicular ao Sol, projetado sobre os limites do modelo. O [Raycaster do Three.js](https://threejs.org/docs/pages/Raycaster.html) consulta primeiro caixas delimitadoras e depois triângulos dos candidatos, interrompendo no obstáculo opaco mais próximo ou no receptor local do chão. Modelos sem interseção em uma amostra não recebem um ponto artificial. O critério de material opaco é compartilhado com as sombras: vidro com transmissão ≥0.95 e materiais praticamente invisíveis deixam o raio passar. Os materiais de teste enxergam ambos os lados de paredes/lajes e o material original do mesh é restaurado no mesmo bloco síncrono, inclusive em erro. Geometria, layers e materiais compartilhados do GLB não são alterados permanentemente.

A representação é uma amostra geométrica de luz direta, sem cálculo de energia, reflexão, refração ou relatório de insolação. Interseções seguem a geometria dos meshes; transparências recortadas por textura não são uma simulação óptica. Os auxiliares não entram na seleção de pavimentos e não projetam sombras. As caixas são indexadas por carregamento do modelo estático; mover a câmera não refaz raycasts. Alterar o horário processa os raios em lotes com orçamento de tempo por frame e descarta resultados antigos durante arrastes rápidos. Buffers de desenho são reutilizados.

`solarDayPositions()` amostra o dia local em instantes UTC a cada dez minutos, usando o mesmo cálculo astronômico da luz. Dias de 23/25 horas respeitam o fuso; a trajetória é guardada por data/coordenadas/Norte/fuso e não é recalculada em cada mudança de horário. À noite os raios desaparecem; a trajetória diurna pode continuar visível para consulta.

Testes adicionais:

```powershell
node tests/solar_projection_test.mjs --three-module=CAMINHO/three.module.js
node tests/north_indicator_test.mjs --three-module=CAMINHO/three.module.js
node tests/solar_shadow_test.mjs --three-module=CAMINHO/three.module.js
node tests/solar_position_test.mjs
```

Os testes exercitam paralelismo/sentido, primeiro obstáculo, vidro, grupos de materiais, faces pelo verso, visibilidade, transformações, restauração de materiais em erro, cancelamento de cálculos, cache da trajetória e dias de 23/25 horas.

QA local em TES_TES, modelo 1: projeção e trajetória conferidas às 09h/12h/16h, raios ocultos às 02h, layout de 390×844 e retorno do mapa Lazer preservando horário/opções e os mesmos 23 raios/12 impactos às 09h. Console sem erros. Norte salvo de 83.9° preservado; esta implementação não altera banco, API ou o mapa arquitetônico 2D.

## Configurações espaciais da obra

Na navegação 3D integrada, o botão **Configurações espaciais** usa a obra selecionada, a mesma API autenticada e as mesmas permissões de edição (níveis 1/2 e CSRF). A leitura é independente do carregamento do GLB; ausência de configuração não impede a navegação. Em falhas de consulta, o formulário informa o erro e impede gravação de valores incompletos.

`mapa_obra_configuracoes_espaciais` guarda uma linha por `obra_id`, com latitude, longitude, Norte, versão, autor e datas. As tabelas anteriores de modelos, vínculos e PDFs não representam configurações espaciais por obra, por isso foi criada esta estrutura específica. Valores `NULL` indicam não definido; zero é um valor válido. Coordenadas têm sete casas decimais; Norte tem uma casa. Latitude aceita −90 a 90; longitude, −180 a 180. O backend valida também tipo e limites. Edições concorrentes retornam HTTP 409 sem sobrescrever a configuração.

**Convenção do Norte:** plano horizontal XZ do GLB tal como carregado no Three.js, +Y vertical. 0° aponta para −Z; 90° para +X; 180° para +Z; 270° para −X. O ângulo aumenta no sentido horário visto de +Y em direção à origem e é normalizado em [0°, 360°). A direção é `(sin(θ), 0, −cos(θ))`. A câmera somente projeta a seta e sua etiqueta N; não participa do valor persistido. Modelos da mesma obra devem compartilhar a orientação de seus eixos.

Definir no modelo abre um círculo arrastável e um slider acessível por teclado, com seta horizontal sobre o empreendimento. Câmera e seleção de pavimentos ficam suspensas nesse modo. Confirmar atualiza o rascunho; Cancelar/Escape restaura o Norte anterior do rascunho. Somente **Salvar** persiste os três campos; cancelar o formulário restaura a indicação salva. O indicador simples reutiliza a mesma função de direção, preparada para uma bússola futura. A seção Sol acima usa a mesma orientação persistida.

Ações de API: `espacial_configuracao` (GET, `obra_id`), `espacial_salvar` (POST JSON, `obra_id`, `latitude`, `longitude`, `norte_graus`, `versao`). A obra é validada pela autorização existente, inclusive na leitura.

Migração CLI idempotente, sem DDL em requisições web. A inicialização é parametrizada e não sobrescreve dados existentes:

```powershell
C:/xampp/php/php.exe scripts/migrate_mapa_espacial.php
# Dados iniciais solicitados para TES_TES; Norte permanece NULL:
C:/xampp/php/php.exe scripts/migrate_mapa_espacial.php --obra=74 --latitude=-27.0992256 --longitude=-48.6179817
C:/xampp/php/php.exe tests/mapa_espacial_test.php
C:/xampp/php/php.exe tests/mapa_espacial_test.php --integration
node tests/north_direction_test.mjs
```

O teste de integração usa uma tabela temporária da conexão, com o mesmo esquema e sem alterar registros espaciais reais. Cobre criação, leitura, limites, campos vazios, Norte zero, isolamento e conflito de edição.

QA inicial da configuração espacial em 13/10/2026: migração aplicada e reexecutada; testes espaciais PHP/banco/JS e sintaxe passaram. Na sessão autenticada em localhost:8066, foram conferidos valores iniciais, edição visual, câmera parada, cancelamento, latitude inválida e persistência do Norte de teste após recarga. Layout conferido em desktop e 390×844; arraste do círculo, teclado, entrada no mapa e retorno ao empreendimento também passaram, sem erros de console. O ângulo de teste foi removido ao finalizar, deixando as coordenadas solicitadas e Norte não definido. O teste legado `mapa_modelos_3d_test.php` espera dois pavimentos, mas o GLB atual também contém `PAVTO_LAZER_02`; essa divergência preexistente permanece fora desta alteração.

## Integração experimental com empreendimento 3D

Disponível pelo botão **Modelo 3D** do Mapa arquitetônico, após selecionar a obra. O visualizador recebe o contexto da obra e lista seus modelos cadastrados. A fixture histórica TES_TES usa `Projeto-TES_TES.glb` e o subtipo Lazer `id=3`; não é necessário usar essa fixture para cadastrar uma obra real.

`mapa_modelos_3d` registra a identidade do arquivo por obra. `mapa_modelo_pavimentos` relaciona `(modelo_id, object_name)` ao `subtipo_id`, com autor, datas e versão para evitar sobrescritas concorrentes. A obra vem do modelo; não há nova entidade de pavimento. Objetos `PAVTO_LAZER_01_001` etc. formam a identidade `PAVTO_LAZER_01`; um grupo ou mesh com esse nome também é aceito. A identificação do GLB não faz correspondência textual com nomes de subtipos.

O primeiro clique abre o vínculo e lista somente subtipos pertencentes à obra por imagens, referências ou vínculos existentes. Depois de salvar, Entrar no mapa destaca o pavimento, desvanece o restante do modelo, aproxima a câmera em vista superior e faz crossfade para a própria tela `MapaPavimento/index.php?embed=3d&obra_id=...&subtipo_id=...`. O iframe usa os componentes e a API existentes; o modo incorporado fixa o contexto e remove somente a navegação externa. Mensagens entre as telas validam origem, janela, identificador de abertura e contexto; a transição aguarda o PDF e as demarcações carregarem. O render 3D fica pausado enquanto o mapa está visível.

Voltar ao empreendimento valida se há edição aberta, desfaz o crossfade e restaura câmera (inclusive zoom), visibilidade e controles. Alterar vínculo está disponível no painel e no mapa. Um subtipo sem referência mostra o estado vazio com link para Configurar mapa no módulo existente, com obra/subtipo selecionados.

Para cadastrar o GLB de outra obra, use **Mapa de pavimento → selecionar obra → Modelo 3D → Enviar modelo 3D**. O upload registra o modelo sem inventar subtipos nem criar vínculos automaticamente. O cadastro legado por script continua disponível para os arquivos que já estão em `predio-3d`. A interface permite selecionar as obras autorizadas e os respectivos modelos cadastrados. `?src=arquivo.glb` sem `obra_id` mantém o visualizador de diagnóstico separado da integração e não persiste vínculos de arquivos arbitrários.

```powershell
C:/xampp/php/php.exe scripts/migrate_mapa_modelos_3d.php --obra=74 --arquivo=Projeto-TES_TES.glb --nome=TES_TES
C:/xampp/php/php.exe tests/mapa_modelos_3d_test.php
# Testes de banco criam um modelo temporário e removem somente essa fixture:
C:/xampp/php/php.exe tests/mapa_modelos_3d_test.php --integration
```

API autenticada compartilhada: `modelos_3d` (GET, obra), `modelo_3d_contexto` (GET, obra/modelo), `modelo_3d_vincular` (POST, obra/modelo/object_name/subtipo_id/versao). Escrita preserva a permissão de níveis 1/2 e CSRF do módulo. Valida modelo da obra, presença do pavimento na cena GLB e pertencimento do subtipo à obra.

QA em 13/10/2026 pelo navegador interno, autenticado em `https://improov/ImproovWeb/`: vínculo inicial Lazer, persistência após recarga, clique direto no prédio e pela lista, transição para o PDF com layers/área útil/Salão de festas e suas três imagens, retorno completo, bloqueio de saída durante edição e alteração de vínculo a partir do mapa. Estado sem mapa e gravação de alteração de vínculo foram testados com modelo temporário, posteriormente removido; o vínculo real do modelo 1 permanece `PAVTO_LAZER_01 → subtipo_id=3`. Layouts conferidos em 1920×1080, 1366×768, 1024×768, 768×1024 e 390×844, com correções da barra de visualização e painel de ambientes em telas estreitas. A navegação normal por Ferramentas → Mapa de pavimento → TES_TES → Lazer também foi conferida. Testes PHP existentes, testes de identificação/isolamento/persistência/conflito/ausência de mapa e sintaxe PHP/JS passaram.

Acesso por **Ferramentas → Mapa de pavimento**. O módulo complementa as imagens do Flow e permanece independente de `MapaCompatibilizacao`.

## Fluxo

Obra → pavimento/subtipo existente ou criado pelo Select2 → documento da obra → página → layers → área útil poligonal → ambientes → imagens da obra.

Um documento pode atender vários subtipos. PDFs de uma página são vinculados automaticamente; documentos com várias páginas abrem miniaturas com os nomes dos subtipos que já utilizam cada página. Compartilhar uma página exige um aviso, sem bloqueio ou exclusividade. O seletor mostra total de páginas e quantidade de páginas distintas utilizadas.

Reenviar conteúdo idêntico na mesma obra reutiliza o documento por SHA-256 e informa o usuário. Mesmo nome com conteúdo diferente é aceito. O original não é regravado, dividido ou convertido.

## Estrutura e reaproveitamento

| Estrutura                    | Responsabilidade                                                                                             |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------ |
| `subtipo_imagem`             | Fonte canônica de pavimento/subtipo, compartilhada com a tela da obra.                                       |
| `imagens_cliente_obra`       | Todas as imagens disponíveis por `obra_id`, independentemente do subtipo.                                    |
| `mapa_documentos`            | PDF privado da obra, nome, tamanho, páginas, hash, autor e datas. Único por obra/hash.                       |
| `referencias_arquitetonicas` | Configuração por obra/subtipo: documento, página, layers, área útil e versão. `UNIQUE(obra_id, subtipo_id)`. |
| `ambientes`                  | Identidade, nome, `referencia_id`, autor, datas e versão de edição.                                          |
| `ambiente_demarcacoes`       | Polígono do ambiente e referência correspondente.                                                            |
| `ambiente_imagens`           | Relação N:N com imagens existentes; uma imagem pode estar em vários ambientes.                               |

Não existe `UNIQUE(documento_id, pagina)`. A antiga `obra_pavimentos` deixa de existir após a migração verificada. Configurações sem correspondência inequívoca ficam com `subtipo_id=NULL`, preservadas e disponíveis para visualizar/associar pela interface; nenhum subtipo é inventado automaticamente.

O cadastro de subtipos foi extraído para `helpers/subtipo_imagem_helper.php`, usado tanto pelo endpoint existente `Dashboard/getSubtipos.php` quanto pelo mapa. Preserva os grupos Obra/Demais e respostas do Dashboard, valida nomes e evita duplicação concorrente. O Select2 segue o padrão da tela da obra.

O viewer reaproveita a abordagem PDF.js/canvas/SVG do mapa antigo e os componentes do MVP. Zoom, pan, fit/reset, pinch, teclado, limite de resolução, cancelamento e ResizeObserver continuam encapsulados em `planta-viewer.js`. `planta-polygon-editor.js` é compartilhado por área útil e ambientes, incluindo fechamento, arraste, inserção em bordas, adicionar/remover vértices e redesenho. Os controles ficam fora da planta para não encobrir pontos no mobile.

## Coordenadas

Área útil:

```json
{
  "version": 3,
  "coordinate_space": "page_normalized",
  "points": [
    [0.1, 0.2],
    [0.8, 0.2],
    [0.9, 0.6],
    [0.7, 0.8],
    [0.1, 0.8]
  ]
}
```

Pontos relativos à página exibida pelo PDF.js, considerando rotação/CropBox, origem superior esquerda e x/y de 0 a 1. `NULL` significa página inteira. Recortes retangulares anteriores são convertidos para polígonos compatíveis na leitura.

Ambientes:

```json
{
  "version": 2,
  "coordinate_space": "useful_area_normalized",
  "points": [
    [0.1, 0.2],
    [0.4, 0.2],
    [0.3, 0.5]
  ]
}
```

Os pontos são relativos à **caixa delimitadora** da área útil, não ao interior irregular do polígono. Sem área útil, a caixa é a página inteira. Para converter à página: `xPagina = caixa.x + x * caixa.width`, `yPagina = caixa.y + y * caixa.height`. Essa transformação permite consumo futuro em outros viewers; nenhum alinhamento GLB é implementado aqui.

A máscara CSS `clip-path: polygon(...)` recorta canvas e SVG conjuntamente. O backend valida pontos, limites, área, repetições, interseções e contenção de todas as arestas, inclusive em áreas côncavas. Até 200 vértices. O último ponto não repete o primeiro.

Geometrias legadas v1 (`normalized`, página inteira) continuam legíveis sem mutação na consulta. Salvar, ampliar ou limpar a área útil recalcula os ambientes na mesma transação, mantendo sua posição física na página. Uma alteração que corte um ambiente é recusada integralmente. Trocar documento/página em um mapa com ambientes também é recusado, evitando deslocar demarcações para outra planta; primeiro é necessário remover as demarcações desse mapa.

## Layers e inspeção

PDF.js local **3.11.174**, com `getOptionalContentConfig()` e `optionalContentConfigPromise` em todas as renderizações. Os nomes e IDs OCG vêm do PDF. O padrão do mapa é todas as camadas visíveis. A lista é ordenada pelo nome em português, sem distinguir maiúsculas ou acentos e com ordem numérica natural (2 antes de 10). Cada checkbox atualiza imediatamente o PDF e a contagem de camadas visíveis; Marcar todos, Desmarcar todos e Restaurar padrão também atualizam a prévia. Salvar camadas persiste a seleção; Cancelar, Fechar e Escape restauram a configuração anterior. O hover/foco da lista não modifica a visibilidade da planta.

`planta-layer-inspector.js` compara renders isolados com um render sem OCGs e guarda máscaras binárias limitadas em resolução/memória. O ponteiro consulta essas máscaras nas coordenadas da página e lista todas as camadas detectadas, inclusive sobreposições. É uma **aproximação visual**, não uma extração semântica de objetos PDF; elementos muito finos, efeitos de transparência, expressões de visibilidade e documentos muito densos podem reduzir a precisão. Conteúdo sem OCG não é atribuído a uma camada. PDFs achatados mostram uma mensagem explícita e desabilitam a inspeção.

## Imagens e compatibilidade

Novas seleções vêm de `imagens_cliente_obra WHERE obra_id=?`, incluindo imagens de qualquer subtipo e sem subtipo. O subtipo continua representando o pavimento do mapa; o ambiente possui seu próprio nome. Nenhum nome de imagem é criado pelo mapa e os vínculos existentes são preservados.

O Select2 permite buscar pelo nome e selecionar várias imagens. Separa imagens sem vínculo em outros ambientes das já vinculadas e mostra os nomes dos ambientes/pavimentos relacionados na obra, desconsiderando o próprio ambiente em edição. Essa classificação é informativa: uma imagem pode estar em vários ambientes, inclusive de pavimentos diferentes. Listagem e gravação validam a obra, sem alterar o subtipo original da imagem. A classificação é atualizada após salvar ou excluir um ambiente.

Nome, geometria e vínculos são salvos atomicamente. Alterar/excluir ambientes exige `versao_edicao`; desenhar exige `versao_referencia`; preparar/trocar página exige `versao_config`. Conflitos retornam HTTP 409. A leitura do mapa usa snapshot consistente.

## Instalação e migração

```powershell
C:/xampp/php/php.exe scripts/migrate_mapa_pavimento.php
# Para associar explicitamente IDs antigos a subtipos existentes:
C:/xampp/php/php.exe scripts/migrate_mapa_pavimento.php --associar=ID_LEGADO:ID_SUBTIPO
```

O runner CLI aplica a estrutura e migração retomável da Fase 2. Não há DDL pela interface. Antes de executar em outro ambiente, manter backup do banco e dos arquivos originais. Cada linha antiga de pavimento e metadados da referência fica em `referencias_arquitetonicas.legado_json` para auditoria, antes da remoção da tabela antiga. PDFs passam a `mapa_documentos`; IDs de referências, ambientes, geometrias e links são preservados. Associações automáticas exigem nome correspondente no catálogo existente; colisões ficam pendentes.

No desenvolvimento em 09/10/2026, por orientação do usuário: legado 1 “Homologação Mapa 20261009” → subtipo 1 “Térreo”; legado 2 “Pavimento lazer” → subtipo 3 “Lazer”. Os três ambientes anteriores, seus polígonos, links e hashes dos PDFs foram comparados com o snapshot anterior. Reexecução da migração validada.

Poppler existente: `tools/poppler/poppler-25.12.0/Library/bin/pdfinfo.exe` no Windows ou `pdfinfo` no PATH. `IMPROOV_PDFINFO_BINARY` pode indicar outro executável; FPDI existente é fallback. Select2/jQuery seguem as bibliotecas já usadas no Flow. Limite de upload: 30 MB; configurar `upload_max_filesize` e `post_max_size` compatíveis.

PDFs em `uploads/referencias_arquitetonicas/` são privados. Manter o `.htaccess` que bloqueia acesso direto; em outro servidor, configurar restrição equivalente. Downloads passam pela API autenticada e validam obra. Arquivos originais precisam acompanhar backups do banco.

## API

`MapaPavimento/api.php?action=...`. Sessão Flow, obras permitidas por `obterObras()`, leitura autenticada, escrita para níveis 1/2 com `X-CSRF-Token` emitido na página.

| Ação                  | Método         | Dados principais                                                                                                                      |
| --------------------- | -------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| contexto              | GET            | obra_id                                                                                                                               |
| subtipo_salvar        | POST JSON      | obra_id, nome                                                                                                                         |
| documento_upload      | POST multipart | obra_id, pdf                                                                                                                          |
| documento_pdf         | GET            | obra_id, documento_id                                                                                                                 |
| paginas               | GET            | obra_id, documento_id                                                                                                                 |
| pagina_vincular       | POST JSON      | obra_id, subtipo_id, documento_id, pagina, versao_config, confirmar_reuso                                                             |
| legado_associar       | POST JSON      | obra_id, subtipo_id, referencia_id                                                                                                    |
| mapa                  | GET            | obra_id, subtipo_id; ou legado_id para configuração pendente                                                                          |
| imagens               | GET            | obra_id, subtipo_id, referencia_id opcional; imagens da obra com lista de ambientes vinculados; preservadas vazio por compatibilidade |
| referencia_configurar | POST JSON      | obra_id, subtipo_id, referencia_id, versao_config, layers, area_util (inclusive NULL)                                                 |
| ambiente_salvar       | POST JSON      | obra_id, subtipo_id, referencia_id, versao_referencia, nome, geometria, imagem_ids; id/versao_edicao para editar                      |
| ambiente_excluir      | POST JSON      | obra_id, subtipo_id, referencia_id, id, versao_edicao                                                                                 |

URL de reabertura: `obra_id` + `subtipo_id`. Bookmarks anteriores com `pavimento_id` são resolvidos pelos metadados migrados.

## Navegação 3D por pavimento

No `predio-3d/visualizador-glb-navegacao.html`, selecionar um pavimento pelo modelo ou pela lista aproxima suavemente a c?mera, mant?m o andar selecionado e os inferiores e oculta os superiores pela altura geom?trica. Elementos compartilhados s?o recortados com materiais tempor?rios, incluindo suas sombras. Tetos identificados pelos nomes teto/Tetos/ceiling, inclusive fora do grupo do andar, ficam ocultos durante a inspe??o. Voltar ao empreendimento ou ESC restaura materiais, visibilidade, c?mera e o estado independente das laterais. ESC respeita di?logos, campos e medi??o.

O hover na lista destaca temporariamente o pavimento sem deslocar a c?mera. OrbitControls mantém o zoom centralizado no target (zoomToCursor desativado). As laterais recolhem automaticamente na entrada; o enquadramento aguarda o fim da transi??o do layout. A c?mera permanece limitada acima do terreno.

O menu contextual apresenta nome, contagem real de ambientes da refer?ncia e imagens de imagens_cliente_obra pela obra/subtipo, al?m de Ver mapa arquitet?nico, Alterar v?nculo e Voltar ao empreendimento. Reutiliza os v?nculos e permiss?es existentes, sem novas tabelas. A planta original alterna na mesma pagina com obra/subtipo; veja predio-3d/flow-spatial-shell.md. O fundo grafite permanece no CSS do viewport com clear transparente; o cubemap alimenta somente o PMREM de reflexos. Vidros alpha não projetam sombras opacas. A luz e o shadow frustum usam os limites da geometria visível após o isolamento, incluindo o recorte de geometrias compartilhadas.

`shadow-catcher.js` cria um único quad com ShadowMaterial transparente, fora do GLB, identificado por `isShadowCatcher` e sem raycasting. A geometria original do terreno é preservada. A opacidade diminui suavemente pela distância à projeção XZ dos limites do modelo, entre 0,2 e 1,5 vezes seu raio. A projeção solar dimensiona o receptor somente dentro dessa região; `fitSolarShadow` ajusta o frustum no espaço da luz ao modelo e ao receptor. Mantém shadow map 2048² e atualização sob demanda da luz existente.

Validação em 15/10/2026: login nas duas URLs oficiais, navegação pelos menus até CIB_HYP/P00, seleção pela geometria e lista, troca de pavimento, órbita, retorno geral, abertura do PDF do Térreo na página 1/1, recolhimento/restauração independente, medição no GLB e rejeição da área técnica, Eixos/Grade, Sol às 06h/18h e à noite. Layout conferido em 1920×1080, 1366×768, 1024×768, 768×1024 e 390×844; corrigida sobreposição do cabeçalho mobile. O navegador interno IAB estava indisponível; os testes práticos utilizaram o Edge conectado. Testes Node: 27 enquadramentos e 108 combinações de sombras, além das suítes existentes de posição solar, projeção, Norte e medição.

## Verificação

```powershell
C:/xampp/php/php.exe tests/mapa_pavimento_rules_test.php
C:/xampp/php/php.exe tests/mapa_pavimento_preparacao_test.php
C:/xampp/php/php.exe tests/mapa_pavimento_pdf_fixture.php
```

Testes cobrem polígonos válidos/côncavos, limites, autocruzamento, arestas atravessando vazios, conversão e preservação de posição, layers, nomes, links e PDFs reais de uma/várias páginas. Fixtures sintéticas incluem OCGs sobrepostos, PDF achatado e rotação/CropBox.

QA autenticado na obra TES_TES/teste (74), em 09/10/2026: Select2/criação, documento existente/upload, miniaturas, página compartilhada, hash idêntico e mesmo nome com conteúdo diferente, página única automática, layers/preview/inspeção sobreposta, área útil e vértices, ambientes 0/N imagens, edição, exclusão, zoom/pan, fit/reset e recuperação após recarga. Negativos: contexto incompatível, imagens de outro subtipo, área externa, concorrência e CSRF. Layout conferido em desktop, notebook, iPad landscape/portrait e mobile, claro/escuro; auditoria axe sem violações detectadas na tela e seletor de páginas (checagens manuais de contraste/ARIA ainda aplicáveis).

O navegador interno falhou ao iniciar nas duas URLs oficiais; a validação prática utilizou agent-browser/Chromium no ambiente HTTPS oficial. Evidências ficam ignoradas em `tmp/mapa-pavimento-qa/`. Dados temporários de QA são removidos sem alterar os registros anteriores.

QA das imagens em 13/10/2026, autenticado em `http://localhost:8066/ImproovWeb/`, obra TES_TES/teste (74): todas as 8 imagens disponíveis no Lazer, busca com/sem resultados, seleção múltipla, criação/edição e persistência após recarga. Uma imagem foi compartilhada entre dois ambientes temporários no Lazer e a Brinquedoteca existente no Térreo; remover um dos vínculos preservou os demais. Salvar sem imagens também foi validado. Imagem de outra obra retornou HTTP 422 sem alterar o ambiente. Layout conferido em 1920×1080, 1366×768, 1024×768, 768×1024 e 390×844; temas claro/escuro, Escape e clique em salvar com a lista aberta. Auditoria axe final sem violações detectadas, com verificações de contraste pendentes de análise manual. Testes PHP existentes e verificações de sintaxe PHP/JS passaram. O navegador interno falhou na inicialização em ambas as URLs; a alternativa usada foi agent-browser/Chrome na segunda URL oficial.

Valida??o adicional em 15/10/2026: tetos da vers?o v0002 ocultos no pavimento Tipo; sele??o, retorno por ESC, di?logo de v?nculo, estados das laterais, zoom no cursor e PDF do Tipo (3 ambientes). Modo de pavimento conferido em desktop, notebook, iPad landscape/portrait e mobile, sem overflow horizontal. Console sem erros. Teste floor_visibility_test cobre tetos fora do grupo, restaura??o, recortes, medi??o e ?ndice solar reutilizado.
