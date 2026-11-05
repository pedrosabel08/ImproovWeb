# Mapa Improov — primeira versão integrada

**Atualização visual de 04/11/2026:** ver [cartografia e terreno](mapa-improov-cartografia.md)
para as fontes, comportamento por zoom, compatibilidade, fallbacks e testes atuais.
As seções abaixo registram a implantação inicial; o DEM passou a ser integrado
por padrão nessa evolução.

Entrada: **Ferramentas → Mapa Improov**, em `MapaImproov/`. Implementação local no Flow; nenhuma publicação foi executada. A importação explícita de localizações do Google Earth descrita abaixo preencheu nove configurações espaciais, sem alteração de esquema.

## Auditoria da arquitetura

O Flow usa páginas PHP por módulo, JavaScript vanilla, CSS e bibliotecas distribuídas em `assets/libs/`. O `package.json` existente atende outros serviços; não há build frontend obrigatório para estes módulos. A página utiliza a sidebar compartilhada, Nunito, o tema `data-theme` e a preferência existente `flow-mapa-theme`. A referência visual orientou a paleta azul profunda, os controles turquesa e a disposição dos painéis; os números e empreendimentos da imagem não foram copiados.

Autenticação: `config/session_bootstrap.php`, destinos registrados em `config/auth_navigation.php`, interceptor `flow_auth_navigation_script()`. Metadata institucional: catálogo `config/page_metadata.php` e `includes/page-head.php`. Loading: `assets/js/thinking-orbs.js`, Vanilla global, estado `composing`, canvas transparente e label em português.

### Tabelas e campos confirmados no banco local

| Fonte | Campos utilizados | Finalidade |
| --- | --- | --- |
| `obra` | `idobra`, `nomenclatura`, `nome_obra`, `nome_real`, `status_obra`, `local` | Identificação, nome, status, busca e localização descritiva |
| `mapa_obra_configuracoes_espaciais` | `obra_id`, `latitude`, `longitude` | Única fonte de coordenadas do mapa |
| `mapa_modelos_3d` | `obra_id`, `id`, `nome` | Disponibilidade e seleção inicial do modelo por `nome,id` |
| `obra_pacote` e contexto de usuário | mecanismo de `conexaoMain.php` | Obras acessíveis conforme perfil e pacotes |

A tabela espacial também contém `norte_graus`, `timezone`, versão e autoria. Esses campos continuam gerenciados por `mpe_get()`/`mpe_save()` no visualizador. O mapa não os altera nem replica. Não existe no cadastro auditado uma capa dedicada autorizada ou campos separados de país, estado e cidade. `local` mistura endereços, localidades e links; não foi dividido por heurísticas nem geocodificado. A página pesquisa esse texto e oferece um filtro pelos valores realmente cadastrados. Ausência de código, localização ou imagem recebe um estado vazio explícito.

Os status encontrados são **Ativo (0)** e **Não ativo (1)**. **Onboarding (2)** também é previsto nos helpers existentes e só aparece no filtro se estiver presente nos registros acessíveis. Não foram criados estados como “Concluído” ou “Em produção”.

### Permissões reaproveitadas

O conjunto consultável vem de `obterObras($conn, 'all')`: gestores recebem o cadastro geral; produção/animação permanecem limitadas a obras ativas com o pacote autorizado. Os dados só são consultados após essa resolução no servidor.

O visualizador tem um conjunto mais restrito, definido por `obterObras($conn)`/`mp_require_obra()`. O mapa calcula `can_open_3d` separadamente, sem ampliar essas permissões. Um GLB cadastrado não concede acesso ao visualizador. Não foi necessária uma permissão nova. A sidebar mantém seus grupos e comportamento responsivo existentes.

## Organização

| Arquivo | Responsabilidade |
| --- | --- |
| `MapaImproov/index.php` | Documento protegido, metadata, sidebar e estrutura acessível |
| `MapaImproov/api.php` | GET interno autenticado, GeoJSON e lista autorizada |
| `helpers/mapa_improov_helper.php` | Consulta em lote, validação espacial e contrato mínimo |
| `config/mapa_improov.php` | Configuração pública de estilo e DEM pelo ambiente |
| `MapaImproov/mapa.js` | MapLibre, camadas, agrupamentos, seleção, câmera e recuperação de falhas |
| `MapaImproov/mapa-data.mjs` | Busca sem acentos, filtros, GeoJSON e validação do estado salvo |
| `MapaImproov/mapa.css` | Temas, painéis e cinco faixas de tela validadas |
| `MapaImproov/viewer-return.css` | Link fixo de retorno no visualizador existente |

Não há novo visualizador Three.js, carregamento de GLB no mapa ou infraestrutura de tiles própria.

## MapLibre, cartografia e configuração

**MapLibre GL JS 5.13.0**, versão fixa, UMD/CSS locais, com licença e hashes em `assets/libs/maplibre-gl/5.13.0/`. Não depende de CDN em runtime para carregar a biblioteca. A projeção usa `setProjection({type:'globe'|'mercator'})`; atmosfera usa `setSky()`. A API de suporte a WebGL de outras bibliotecas não é utilizada: falhas do construtor são capturadas e a lista continua utilizável.

Provedor escolhido: [OpenFreeMap](https://openfreemap.org/), estilo vetorial [Dark](https://tiles.openfreemap.org/styles/dark), com ajustes de cores e rótulos em português. A [documentação oficial](https://openfreemap.org/quick_start/) permite personalizar os estilos. Fontes, sprites, glyphs e atribuições do provedor são preservados. O OpenFreeMap declara uso comercial permitido, sem chave e sem limite de visualizações/requisições, mas sem garantia de SLA. Não se utilizam `demotiles.maplibre.org`, os servidores de tiles padrão do OSM ou Overpass como mapa-base.

Alternativas consideradas: MapTiler oferece estilos e elevação integrados, mas seus [termos da camada gratuita](https://www.maptiler.com/terms/cloud/) limitam uso comercial a pesquisa e desenvolvimento. Uma hospedagem própria de PMTiles/tiles exigiria operação e armazenamento adicionais sem necessidade nesta versão. OSM/Overpass existente fornece entorno vetorial do visualizador, não um serviço mundial de tiles.

Nenhum serviço pago foi contratado. O mapa-base padrão não requer credenciais. Valores opcionais estão em `.env.example`:

```dotenv
FLOW_MAP_STYLE_URL=https://tiles.openfreemap.org/styles/dark
FLOW_MAP_DEM_URL=
FLOW_MAP_DEM_ENCODING=mapbox
FLOW_MAP_DEM_ATTRIBUTION=
```

As URLs devem ser públicas, HTTPS, sem usuário/senha embutidos. Se outro provedor usar chave pública de navegador, restringi-la por domínio/permissões no painel do provedor. Nunca colocar segredo de servidor nessas variáveis: são enviadas ao navegador autenticado. Não gravar chaves no repositório.

**Relevo:** não havia provedor DEM configurado no projeto, e o OpenFreeMap selecionado não fornece elevação nessa integração. A primeira versão funciona sem terreno. `FLOW_MAP_DEM_URL` aceita um TileJSON `raster-dem` com tiles licenciados e CORS apropriados; encoding `mapbox` ou `terrarium`. Quando configurado, aparece o controle “Relevo 3D”, usando `setTerrain()` com exagero 1. Atribuição deve ser fornecida conforme a licença. Falha do DEM desativa o terreno sem interromper obras/mapa-base. Nenhum relevo artificial foi gerado. O caminho DEM permanece **não validado com um provedor real**, por ausência dessa configuração.

Referências de API: [globo](https://maplibre.org/maplibre-gl-js/docs/examples/display-a-globe-with-a-vector-map/), [sky/atmosfera](https://maplibre.org/maplibre-style-spec/sky/), [terreno](https://maplibre.org/maplibre-gl-js/docs/examples/3d-terrain/).

## Backend

`GET MapaImproov/api.php` retorna `sucesso`, `type: FeatureCollection`, `features` válidas e `obras` autorizadas, incluindo as sem coordenadas para busca e indicadores. Um `obra_id` opcional é validado estritamente e autorizado antes de consultar seus detalhes. Outros parâmetros são recusados.

Há uma consulta de obras/configuração espacial e uma de modelos em lote, com placeholders para os IDs autorizados. A seleção do primeiro modelo respeita a mesma ordenação `nome,id` do visualizador. Não há consulta por obra, download de GLB, leitura de geometria ou resolução de arquivos remotos. `has_glb` indica cadastro; a existência efetiva do arquivo continua sendo verificada pela API 3D existente ao abrir.

As coordenadas usam `mpe_coordinate()`, com latitude ±90, longitude ±180, ausência tratada como `null` e zero preservado. GeoJSON usa **[longitude, latitude]**. Registros inválidos não recebem marcador. A resposta não contém caminhos de armazenamento, nomes de arquivos GLB, tokens ou dados comerciais adicionais.

Respostas: 400 para parâmetros inválidos, 401 para sessão ausente/expirada, 403 para obra não autorizada, 405 para escrita e 500 com mensagem genérica para falhas internas. Cache privado `no-store`; nenhum cache compartilhado de dados de obras. O navegador carrega a lista uma vez e filtra em memória. O volume atual não exige paginação ou consulta por região; as features podem futuramente alimentar essa evolução.

## Interface e navegação

- Globo abre com Brasil em destaque; zoom/padding consideram a área disponível e a resolução.
- Globo ↔ mapa preserva fontes, filtros, seleção e câmera; botão Brasil restaura o enquadramento inicial.
- Camadas GeoJSON e clustering nativo, sem um marcador DOM para cada obra. Um segundo source mínimo mantém o destaque da seleção mesmo dentro de clusters.
- Clique em cluster aproxima e divide os grupos; coordenadas coincidentes oferecem lista contextual de seleção individual.
- Busca por código/nome/localização ignora caixa e acentos. Filtros por status real, GLB e localização cadastrada; indicadores acompanham os filtros.
- Painel com dados reais, placeholder sem imagem, ação 3D autorizada e link ao projeto. Miniaturas e modelos não são baixados no mapa.
- Tablet recolhe a pesquisa; mobile usa pesquisa expansível e detalhes inferiores. Controles de zoom não ficam atrás do painel de detalhes.
- `sessionStorage` por usuário e aba guarda apenas câmera, projeção, filtros e ID selecionado. Dados/permissões são consultados novamente na volta. Não aceita URL de retorno arbitrária.
- Falhas de consulta e mapa têm mensagens e nova tentativa; consulta/estilo têm timeout. Sem WebGL, a lista permanece disponível. Foco, Escape, nomes de controles em português e preferência por movimento reduzido são respeitados.

O link 3D reutiliza `MapaPavimento/predio-3d/visualizador-glb-navegacao.php?obra_id=…&modelo_id=…&origem=mapa-improov`. O PHP do visualizador passou a validar o contexto explícito antes de renderizar, mantendo também os guards das APIs. O parâmetro fixo `origem` adiciona somente o link de volta a `../../MapaImproov/`. Não altera o carregamento dos modelos, cálculo solar, HDRIs, OSM, pavimentos ou edição espacial.

## Validação local

Comandos reproduzíveis:

```text
php tests/mapa_improov_test.php
node tests/mapa_improov_test.mjs
php tools/audit_mapa_improov.php
php tools/test_page_metadata.php
php tools/test_auth_navigation.php
```

A auditoria HTTP utiliza apenas `http://localhost:8066/ImproovWeb/`, lê as credenciais autorizadas do AGENTS local e não grava senha, cookie, HTML privado ou arquivos de modelo. Redução de perfil e expiração afetam somente sua própria sessão isolada; não há mudança de usuário ou permissão no banco.

No navegador interno, login em `https://improov/ImproovWeb/`, abertura pelo menu Ferramentas, tiles/atmosfera/globo, busca, seleção, grupos reais 4 → 2 + 2, lista de coincidentes, filtros de status/GLB/localização, pesquisa “essencia”, tema claro e escuro, obra sem GLB e troca de projeção foram exercitados. A navegação por Tab avançou da busca para “Limpar filtros”; Escape fechou o painel e devolveu o foco ao botão de busca. As obras **102/modelo 4** e **133/modelo 8** carregaram os GLBs existentes; o retorno restaurou busca e seleção. O visualizador mostrou respectivamente **5 pavimentos/375 objetos** e **28 pavimentos/1407 objetos**.

As capturas em `output/mapa-improov/` cobrem desktop 1920×1080, notebook 1366×768, iPad landscape 1024×768, iPad portrait 768×1024 e mobile 390×844. São testes de viewport no navegador, sem validação em aparelhos físicos.

O relatório HTTP fica em `output/mapa-improov-http.json`. A verificação inclui acesso anônimo, assets públicos/MIME, metadata inicial, APIs existentes dos modelos, inputs inválidos, tentativa de modelo de outra obra, perfil reduzido e sessão expirada.

Resultado da implantação inicial: **21 verificações PHP de dados, 14 asserções JavaScript, 41 verificações HTTP, 850 verificações centrais de metadata e 190 de autenticação**, todas sem falhas. Sintaxe PHP/JavaScript e `git diff --check` também passaram. Antes da importação Google Earth, o gestor validado tinha **113 obras**, **4 geolocalizadas**, **4 com GLB** e **109 sem localização**. Os filtros de status reais mostraram **24 ativas** e **89 não ativas**. O conjunto de produção no teste de perfil reduzido permaneceu limitado aos pacotes autorizados.

Limites de validação: falha real de provedor/DEM, WebGL indisponível e gestos em hardware físico não foram simulados. Os tratamentos foram implementados, mas não são apresentados como testes executados. Não houve auditoria funcional completa de estudo solar/OSM/HDRIs; seus módulos de implementação não foram alterados por esta tarefa. As alterações locais preexistentes do visualizador e de outros módulos foram preservadas.

## Importação das localizações do Google Earth

O campo `obra.google_earth` foi auditado para complementar apenas obras sem latitude **e** longitude. Foram encontrados 11 links: dois já tinham configurações e foram preservados; oito continham coordenadas explícitas em `/@latitude,longitude,...`; um era um link de projeto sem coordenadas na URL. Esse último foi aberto no navegador: o terreno identificado como GES_CHA foi conferido, e um ponto no interior do polígono foi lido nas coordenadas exibidas pelo Google Earth. Nenhuma localização foi inferida a partir do nome da obra ou do ID do projeto.

Foram adicionadas **9 localizações**: oito por extração da URL e uma por ponto conferido no projeto. O mapa agora retorna **13 obras geolocalizadas**, **100 sem localização**, mantendo **113 acessíveis** e **4 com GLB** no perfil de validação. Todas as coordenadas foram gravadas por `mpe_save()` em `mapa_obra_configuracoes_espaciais`, com autoria do administrador espacial ativo; não existe fallback paralelo no mapa. Norte e timezone existentes são preservados. As configurações completas das obras 102 e 133 foram comparadas por hash antes/depois e ficaram idênticas.

Arquivos: `helpers/google_earth_location_helper.php`, `tools/import_google_earth_locations.php` e `tests/google_earth_location_test.php`. O importador é **somente CLI**, sem execução HTTP. O padrão é simulação:

```text
php tools/import_google_earth_locations.php --user=LOGIN_ADMINISTRADOR
php tools/import_google_earth_locations.php --user=LOGIN_ADMINISTRADOR --apply
```

O login serve para atribuição e escopo em uma execução administrativa local, não cria sessão web nem autentica por URL. O usuário deve estar ativo e pertencer aos administradores espaciais já definidos pelo Flow. `--obra=ID` limita a execução a uma obra autorizada. Somente URLs HTTPS de `earth.google.com/web/` são reconhecidas; o helper não faz requisições HTTP, resolve redirecionamentos ou decodifica payloads de projeto para tentar adivinhar localizações. Links sem coordenadas exigem conferência no Google Earth. Após essa conferência, `--confirmed-point=ID:LATITUDE:LONGITUDE` permite informar explicitamente o ponto; coordenadas reais não são fixadas no código ou na documentação. O comando não transmite links a outros serviços.

Qualquer coordenada já preenchida, inclusive zero ou um par parcial/inválido, é preservada para revisão manual. A versão espacial protege contra sobrescrever edição concorrente. Reexecutar a simulação após a carga encontrou **zero candidatas** e **11 registros preservados**. O mapa continua sendo somente leitura: novos links podem ser processados por uma nova execução administrativa explícita, sem gravar dados durante um GET da API.

Os pontos extraídos representam a visualização gravada no link, e o ponto conferido representa uma posição dentro do terreno. São referências de localização para o mapa; não constituem levantamento topográfico. Ajustes precisos continuam disponíveis nas configurações espaciais existentes.

Validação da importação: **35 testes do parser/proteção de coordenadas** e **51 verificações HTTP** passaram, incluindo o bloqueio HTTP do importador, a contagem de 13 features e as APIs existentes dos modelos 102/133. A simulação posterior confirmou idempotência. No navegador interno, foram confirmados os indicadores 113/13/4/100, a pesquisa/seleção da GES_CHA (ponto conferido no projeto) e da ABF_MAT (coordenadas extraídas da URL). Nenhum arquivo de interface foi alterado nesta importação.

## Publicação futura

Publicar `MapaImproov/`, os assets MapLibre, helper/config novos, catálogo de metadata, registro de autenticação, sidebar e o PHP de entrada do visualizador no mesmo conjunto. Não requer SQL ou npm install. OpenFreeMap precisa estar acessível pelo navegador (incluindo seus sprites/glyphs/tiles). DEM é opcional; configurar e validar um provedor licenciado antes de anunciá-lo como disponível.
