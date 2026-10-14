# Mapa arquitetônico — Fase 2

## Sol astronômico na navegação 3D

No visualizador integrado, ative **☀ Sol → Ativar Sol** no painel lateral. Escolha a data e o horário local da obra, ou use o slider de 00:00 a 23:55 em passos de cinco minutos. O campo de horário também aceita minutos individuais e permite testar a madrugada. **Agora** usa a hora atual no fuso da obra. Essas mudanças atualizam a mesma luz direcional e suas sombras, sem recarregar o GLB. Data, horário e ativação pertencem à visualização atual; coordenadas, Norte e fuso permanecem na configuração persistida por obra.

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

Com o Sol ativado, **Mostrar projeção solar** desenha até 25 raios paralelos, com setas no sentido Sol → empreendimento e pontos no primeiro bloqueio. **Mostrar trajetória do dia** acrescenta a curva acima do horizonte e marcações de 06h/08h/12h/16h/18h, quando visíveis. Os dois controles são opcionais, independentes e pertencem à visualização atual. Nenhum recurso foi acrescentado ao PDF/mapa 2D. Ao entrar no mapa os auxiliares são ocultados e os cálculos pendentes são cancelados; ao voltar, as opções são restauradas. Os raios retomam somente depois que a animação de retorno restaurar a visibilidade e os materiais completos do prédio.

Os raios reutilizam `solarAt()` e seu vetor de direção já orientado pelo Norte salvo. O campo 5×5 amostra um plano perpendicular ao Sol, projetado sobre os limites do modelo. O [Raycaster do Three.js](https://threejs.org/docs/pages/Raycaster.html) consulta primeiro caixas delimitadoras e depois triângulos dos candidatos, interrompendo no obstáculo opaco mais próximo ou no receptor local do chão. Modelos sem interseção em uma amostra não recebem um ponto artificial. O critério de material opaco é compartilhado com as sombras: vidro com transmissão ≥0.95 e materiais praticamente invisíveis deixam o raio passar. Os materiais de teste enxergam ambos os lados de paredes/lajes e o material original do mesh é restaurado no mesmo bloco síncrono, inclusive em erro. Geometria, layers e materiais compartilhados do GLB não são alterados permanentemente.

A representação é uma amostra geométrica de luz direta, sem cálculo de energia, reflexão, refração ou relatório de insolação. Interseções seguem a geometria dos meshes; transparências recortadas por textura não são uma simulação óptica. Os auxiliares não entram na seleção de pavimentos e não projetam sombras. As caixas são indexadas por carregamento do modelo estático; mover a câmera não refaz raycasts. Alterar o horário processa os raios em lotes com orçamento de tempo por frame e descarta resultados antigos durante arrastes rápidos. Buffers de desenho são reutilizados.

`solarDayPositions()` amostra o dia local em instantes UTC a cada dez minutos, usando o mesmo cálculo astronômico da luz. Dias de 23/25 horas respeitam o fuso; a trajetória é guardada por data/coordenadas/Norte/fuso e não é recalculada em cada mudança de horário. À noite os raios desaparecem; a trajetória diurna pode continuar visível para consulta.

Testes adicionais:

```powershell
node tests/solar_projection_test.mjs --three-module=CAMINHO/three.module.js
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

Disponível somente em `predio-3d/visualizador-glb-navegacao.html?obra_id=74`. Nenhum controle foi acrescentado à tela da obra. O teste usa o modelo `Projeto-TES_TES.glb`, cadastrado para TES_TES. O subtipo Lazer existente é `id=3`.

`mapa_modelos_3d` registra a identidade do arquivo por obra. `mapa_modelo_pavimentos` relaciona `(modelo_id, object_name)` ao `subtipo_id`, com autor, datas e versão para evitar sobrescritas concorrentes. A obra vem do modelo; não há nova entidade de pavimento. Objetos `PAVTO_LAZER_01_001` etc. formam a identidade `PAVTO_LAZER_01`; um grupo ou mesh com esse nome também é aceito. A identificação do GLB não faz correspondência textual com nomes de subtipos.

O primeiro clique abre o vínculo e lista somente subtipos pertencentes à obra por imagens, referências ou vínculos existentes. Depois de salvar, Entrar no mapa destaca o pavimento, desvanece o restante do modelo, aproxima a câmera em vista superior e faz crossfade para a própria tela `MapaPavimento/index.php?embed=3d&obra_id=...&subtipo_id=...`. O iframe usa os componentes e a API existentes; o modo incorporado fixa o contexto e remove somente a navegação externa. Mensagens entre as telas validam origem, janela, identificador de abertura e contexto; a transição aguarda o PDF e as demarcações carregarem. O render 3D fica pausado enquanto o mapa está visível.

Voltar ao empreendimento valida se há edição aberta, desfaz o crossfade e restaura câmera (inclusive zoom), visibilidade e controles. Alterar vínculo está disponível no painel e no mapa. Um subtipo sem referência mostra o estado vazio com link para Configurar mapa no módulo existente, com obra/subtipo selecionados.

Para cadastrar o GLB de outra obra, colocar o arquivo no diretório `predio-3d` e executar a migração idempotente abaixo. Ela registra o modelo, sem inventar subtipos ou criar vínculos automaticamente. A interface permite selecionar as obras autorizadas e os respectivos modelos cadastrados. `?src=arquivo.glb` sem `obra_id` mantém o visualizador de diagnóstico separado da integração e não persiste vínculos de arquivos arbitrários.

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

PDF.js local **3.11.174**, com `getOptionalContentConfig()` e `optionalContentConfigPromise` em todas as renderizações. Os nomes e IDs OCG vêm do PDF. O padrão do mapa é todas as camadas visíveis. Checkboxes são persistidos por configuração; hover/foco isola temporariamente uma camada e restaura o estado anterior sem modificar a seleção.

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
