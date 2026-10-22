# Autenticação e retorno à navegação original

## Investigação do comportamento anterior

- `config/session_bootstrap.php` configura o cookie PHP, inicia a sessão e verifica os limites de inatividade (30 minutos) e absoluto (4 horas). Não era um guard universal: cada página ou helper verificava `logado`, usuário e permissões. Os prazos e a renovação explícita foram preservados.
- `login.php` recebe o formulário via fetch, consulta o usuário ativo, limpa a sessão, regenera seu ID, registra o acesso e responde JSON. `script/scriptLogin.js` sempre navegava para `inicio.php`. Não havia um return URL comum.
- Os guards HTML estavam distribuídos pelos módulos. As páginas da implementação de metadata chamavam `flow_redirect_to_login()`; telas antigas usavam `header('Location: ../index.html')`. O endpoint de apresentação é `login-preview.php`, que conserva a metadata institucional.
- Na expiração, o bootstrap destruía a sessão e distinguia JSON por Accept, Content-Type ou X-Requested-With. Algumas chamadas fetch sem esses cabeçalhos podiam acabar recebendo HTML de login. Exemplos de respostas diferentes: Custos, Pagamento e notificações já têm helpers JSON 401/403; `FlowReview/atualizar.php` redirecionava; `FlowReview/revisarTarefa.php` tinha um erro JSON com status 200 para sessão ausente.
- `script/controleSessao.js` já centralizava os avisos, keepalive e renovação. O botão “Fazer login” do aviso de expiração chamava `logout.php`, eliminando qualquer contexto. Não havia interceptor global de autenticação para fetch/XHR; jQuery e fetch aparecem nos módulos. Pagamento possui um wrapper local para CSRF e erros, que continua encadeado com o interceptor central sem perder seu token.
- `logout.php` chama `improov_end_session()`, que esvazia os dados, expira o cookie e destrói a sessão. Isso já limpa naturalmente os destinos pendentes.
- `verifica_acesso.php`, os helpers financeiros, os helpers dos módulos e as próprias páginas fazem verificações adicionais de perfil/capacidade/obra. Essas verificações continuam executando no destino.
- **Fluxo separado:** `FlowReviewExt` usa `flow_auth`, `login_tokens` e `auth_login.php`. Seu login AJAX já mantém a página/token atual. Esse fluxo, cadastro e logout próprios permanecem separados. Rotas públicas e links por token não foram incorporados ao retorno do login interno.
- **Hashes:** `script/script.js` utiliza `#add-cliente`, `#filtro-colab`, `#follow-up` e `#filtro-obra`. Fragmentos não chegam ao PHP; são preservados no navegador, inclusive em retomadas após AJAX. Estado de formulários não salvo e filtros somente em memória não são serializados.

## Regra obrigatória

Toda página protegida deve utilizar o mecanismo central de autenticação e retorno pós-login. Quando uma sessão expirar, a navegação original deve ser preservada sempre que tecnicamente possível, sem contornar autenticação ou autorização.

O retorno determina apenas uma navegação GET. Nunca concede acesso, renova uma sessão vencida ou repete uma operação interrompida.

## Componentes e integração

- `config/auth_navigation.php`: registro de documentos, validação, armazenamento/consumo e redirect de autenticação. Reutiliza a URL institucional de login existente; não altera o catálogo, as tags, a imagem ou a canonical Open Graph.
- `config/session_bootstrap.php`: integra o helper à expiração e responde 401 JSON às requisições da interface cuja sessão desapareceu. Um POST de login com um cookie anterior vencido ainda pode validar as novas credenciais normalmente.
- `script/authNavigation.js`: interceptor único de fetch e XMLHttpRequest/jQuery, carregado por `flow_auth_navigation_script()` nas telas internas protegidas. Requisições de outra origem, autenticação e FlowReviewExt ficam fora do interceptor.
- `script/controleSessao.js`: mantém os prazos e o modal existentes; “Fazer login” usa a recuperação de navegação. “Sair” continua sendo logout explícito. A inicialização é idempotente para páginas com inclusão duplicada.
- `login.php` e `script/scriptLogin.js`: o servidor consome o contexto após o sucesso e responde `redirect_url` e `return_restored`; o cliente navega para o destino validado. A referência ao script no template `index.html` foi versionada para atualização do cache.
- Os guards existentes passaram a chamar o helper central. Não foram removidas condições de autenticação ou autorização. A sidebar não foi alterada por esta funcionalidade.
- Guards de telas legadas foram antecipados ao carregamento de versões/conexões e à saída HTML; chamadas duplicadas de `session_start()` foram protegidas e leituras anteriores ao guard foram ajustadas. A conferência anônima revelou que esses diagnósticos/interrupções preexistentes podiam impedir o redirect. As condições de acesso permaneceram iguais. As referências ao controlador de sessão também foram versionadas para atualização do cache.

## Destinos na sessão e múltiplas abas

Uma tentativa de abrir um documento protegido guarda no servidor:

```php
$_SESSION['_flow_login_returns'][$context] = [
    'url' => '/ImproovWeb/FlowReview/?imagem_id=583&modo=revisao',
    'expires' => time() + 1800,
];
```

`$context` é uma chave aleatória de 16 bytes, em hexadecimal. O endereço do login recebe apenas `login_context=<chave>` junto à chave institucional `preview`. O caminho e a query privados não são copiados para esse endereço nem para metadata.

Cada tentativa tem seu próprio contexto. Até oito destinos podem aguardar por sessão, durante 30 minutos; esse prazo é apenas da intenção de navegação e não altera a duração da autenticação. O login conserva os outros contextos durante a regeneração do ID de sessão e remove somente o contexto consumido. Falha de credenciais não o consome. Uma chave de outra sessão, vencida, inexistente ou já consumida resulta no destino padrão.

Abrir o login sem contexto válido continua levando a `inicio.php`. Não há seleção implícita do destino mais recente. Logout explícito elimina todos os destinos. Logins literalmente simultâneos continuam sujeitos à concorrência normal da rotação do cookie PHP; se uma tentativa perder a sessão, o resultado seguro é repetir o login ou usar o fallback, sem reutilizar um destino de outra aba.

## Validação e prevenção de Open Redirect

O destino é validado tanto ao salvar quanto ao consumir. Aceitam-se apenas caminhos absolutos internos de um registro explícito de **documentos** do Flow, sob a base atual `/ImproovWeb/` ou `/flow/ImproovWeb/`. O arquivo correspondente precisa existir dentro do repositório, inclusive após resolver links simbólicos. O alias existente `Dashboard/obra` tem um mapeamento explícito.

São recusados esquemas/domínios, caminhos `//`, barras invertidas, segmentos `.`/`..`, caminhos codificados, fragmentos recebidos pelo servidor, controles/CRLF inclusive codificados repetidamente, bases diferentes e destinos fora do registro. Login, logout, erro, APIs, downloads/assets e autenticação externa não fazem parte do registro. Parâmetros como `return`, `next` ou `redirect` do formulário/login não são utilizados.

A query original é preservada, sem reordenar nem decodificar os valores. Espaços codificados em valores de query são aceitos. Um ID de entidade inválido ou uma permissão ausente continua sendo tratado pela página de destino; o helper não consulta dados de negócio para antecipar essas decisões. Uma rota inexistente ou fora do registro usa `inicio.php`.

## AJAX, hashes e operações mutáveis

O interceptor marca somente requisições internas do Flow com `X-Flow-Auth-Required: 1`. O bootstrap devolve **401 JSON** antes de executar a ação quando essa requisição chega sem sessão. Fetch Metadata e os cabeçalhos JSON/XHR também identificam requisições não documentais durante a expiração. APIs não são guardadas como destinos.

Ao receber 401, o interceptor faz uma nova navegação **GET** da URL atual da interface. O guard desse documento salva o caminho e a query e envia ao login. Requisições paralelas na mesma tela disparam uma única navegação. Um redirect legado de fetch para um documento de login é convertido em falha 401, evitando entregar o HTML como sucesso. XHR/jQuery usa a mesma sinalização do bootstrap.

Para garantir que o navegador realmente faça o GET quando a URL contém hash, essa navegação usa o parâmetro reservado e transitório `_flow_auth_resume`. O servidor o remove antes de guardar o destino; se outra aba já tiver autenticado, o cliente também o remove da barra de endereço. Os demais parâmetros mantêm seus bytes e sua ordem. O hash acompanha os redirects HTTP no navegador e é reaplicado pelo script de login somente quando o servidor confirma um retorno válido; não é enviado ao PHP ou às tags públicas.

Nenhum corpo, método, upload ou callback da ação é salvo. **POST/PUT/PATCH/DELETE não são repetidos.** Depois do login, o usuário decide executar novamente a operação. Um POST documental anônimo sem interceptor recebe 401, sem replay; para retomar esse caso é necessário reabrir o documento por GET. Erros de rede e 403 não provocam um novo login automático.

APIs legadas fora do bootstrap/interceptor conservam seus contratos próprios; esta tarefa não refatora a autenticação de centenas de endpoints independentes. O caminho coberto é a navegação protegida e as requisições da interface interna integrada, incluindo o Flow Review.

## Como criar uma página protegida

1. Inclua `config/session_bootstrap.php` antes de qualquer saída.
2. Registre o documento em `flow_auth_page_files()`. Não registre APIs, ações ou arquivos públicos. Adicione aliases explicitamente quando houver uma regra real do servidor.
3. No branch de autenticação ausente, chame `flow_auth_redirect_to_login(); exit;`.
4. Preserve todas as verificações de perfil, permissão e obra depois do guard.
5. No head, carregue `<?= flow_auth_navigation_script() ?>` antes dos scripts do módulo. Continue seguindo os padrões de metadata em `page-standards.md`.
6. APIs devem incluir o bootstrap e usar 401 JSON para autenticação ausente, 403 para autorização negada e os mecanismos CSRF existentes para escrita. Não devolva HTML de login em ações.

```php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (($_SESSION['logado'] ?? false) !== true) {
    flow_auth_redirect_to_login();
    exit;
}
// Autorizações adicionais permanecem aqui.
```

## Verificação e ferramentas locais

- `php tools/test_auth_navigation.php`: validação de caminhos, codificações maliciosas, query preservada, consumo único, isolamento de contextos, expiração e limite de armazenamento; usa arquivos de sessão temporários isolados dentro de `output/`.
- `node tools/audit_auth_navigation.cjs`: testes reais com navegador e HTTP somente nas duas origens locais oficiais. Reutiliza o Playwright do runtime via `FLOW_PLAYWRIGHT_MODULE`; `FLOW_AUDIT_PHP` seleciona o PHP. Credenciais são lidas do AGENTS.md autorizado ou de variáveis de ambiente e não entram no relatório.
- `tools/auth_navigation_session_fixture.php`: somente CLI, também exige `FLOW_AUTH_TEST_LOCAL=1`. O teste o utiliza para vencer timestamps, reduzir o perfil ou adulterar um destino em sessões isoladas criadas pelo próprio teste. Não altera usuários, permissões no banco ou a política real. Pela web responde 404.
- `php tools/test_page_metadata.php`: regressão independente dos padrões Open Graph.

Os relatórios em `output/auth-navigation-*.json` registram resultados sem senhas, cookies ou HTML privado. O cenário AJAX conta os POSTs ao endpoint de revisão antes/depois do login para comprovar ausência de replay. A validação de autorização reduz apenas o perfil da sessão de teste e confirma o 403 do guard existente; não usa credenciais de outro colaborador.

## Publicação

Publicar o helper e o JavaScript novos, o bootstrap/login, o template/script de login, o controlador de sessão e os entry points integrados como um conjunto. Não é necessária alteração de banco ou de Nginx/Apache. A rota PHP de login contextual já existente permanece em uso. Invalidar o cache do template e dos scripts modificados conforme o processo de deploy do Flow.

O código, renderer e assets Open Graph da implementação anterior não precisam ser republicados por causa desta funcionalidade. O login só recebe mais uma chave de navegação opaca, sem alterar a canonical institucional.

## Arquivos desta funcionalidade

Novos arquivos de execução:

```text
config/auth_navigation.php
script/authNavigation.js
```

Arquivos compartilhados e APIs alterados:

```text
config/session_bootstrap.php
login.php
index.html
script/scriptLogin.js
script/controleSessao.js
verifica_acesso.php
notificacoes/_common.php
FlowReview/atualizar.php
Pos-Producao/obter_notificacoes.php
Quadro/colaboradores.php
```

Documentos integrados (guard, carregamento do interceptor e/ou versão do controlador):

```text
inicio.php
inicio2.php
main.php
infos.php
PaginaPrincipal/Home/index.php
PaginaPrincipal/Overview/index.php
ALMA/index.php
ALMA/admin.php
AdminMetas/index.php
Alteracao/index.php
Atividade/index.php
Animacao/index.php
Arquitetura/index.php
Briefing/index.php
Colaborador/index.php
Contratos/index.php
ControleComercial/index.php
Custos/index.php
Dashboard/index.php
Dashboard/obra.php
Dashboard/obra2.php
Dashboard/projetos.php
Dashboard/Operacional/index.php
Entregas/index.php
FlowBlock/index.php
FlowBlock/issue.php
FlowDrive/index.php
FlowReferencias/index.php
FlowReview/index.php
FlowTrack/index.php
FlowTrack/Radar/index.php
FlowTrack/Report/index.php
Fotografico/index.php
GANTT/index.php
GANTT/gantt.php
GANTT/teste.php
Gestao/index.php
Imagens/index.php
MapaCompatibilizacao/index.php
MapaPavimento/index.php
MapaPavimento/predio-3d/visualizador-glb-navegacao.php
Metas/index.php
notificacoes/index.php
Obras/index.php
Pagamento/index.php
Pagamento/fechamento.php
PlanejamentoCapacidade/index.php
PlanejamentoProducao/index.php
Planner/index.php
Pos-Producao/index.php
Pos-Producao/index2.php
PreAlteracao/index.php
PreAlteracao/planejamento.php
Prioridade/index.php
Projetos/index.php
Quadro/index.php
Relatorio/index.php
Render/index.php
review/index.php
ScriptArquivos/index.php
SIRE/index.php
TelaGerencial/index.php
TimelineObraTeste/index.php
TvDashboard/admin.php
```

Documentação e ferramentas de desenvolvimento:

```text
docs/authentication-navigation.md
docs/page-standards.md
AGENTS.md (local, já ignorado pelo Git)
tools/test_auth_navigation.php
tools/auth_navigation_session_fixture.php
tools/audit_auth_navigation.cjs
```

Arquivos que já estavam modificados pela tarefa anterior de Open Graph ou por backups não pertencem automaticamente a este deploy. `config/page_metadata.php`, `includes/page-head.php`, `login-preview.php`, assets de metadata e `sidebar.php` não foram alterados por esta funcionalidade. A regra nova no AGENTS.md permanece local porque o arquivo contém credenciais e já é ignorado pelo projeto.

## Resultado da validação local

Relatório final: [output/auth-navigation-1792635979265.json](../output/auth-navigation-1792635979265.json), com **173 verificações integradas sem falhas**. Inclui 64 documentos protegidos conferidos por HTTP sem sessão e sem diagnósticos PHP, os dez cenários solicitados e cinco resoluções do formulário de login.

| Cenário | Resultado |
| --- | --- |
| Link direto sem sessão | Login contextual → documento original |
| Query e fragmento | Caminho, query e hash restaurados exatamente |
| Login direto | `inicio.php`, inclusive com um cookie anterior vencido |
| URL externa ou codificada maliciosa na sessão | Rejeitada e consumida, fallback para `inicio.php` |
| Logout com contexto pendente | Contexto antigo não reutilizado |
| Expiração durante navegação | Retorno ao documento que se tentou abrir |
| Expiração em fetch/POST e sessão ausente em XHR | 401, login, retomada da interface; POST contado uma única vez |
| Permissão insuficiente | Guard existente respondeu 403 com perfil de sessão de teste reduzido |
| Página inexistente | Fallback, sem loop |
| Duas abas | Contextos distintos e retorno correto de ambas após os logins |

Também passaram **188 verificações do helper**, **811 verificações de regressão de metadata**, lint PHP dos arquivos integrados e verificação de sintaxe dos scripts JavaScript. Os cenários não exigem mudar a política real: a simulação de expiração altera somente timestamps de sessões isoladas e a de autorização reduz somente o perfil de uma sessão de teste.

No navegador interno foi validado o link HTTPS do Flow Review com query/hash → formulário real → autenticação → endereço original; depois, a navegação autenticada pelo menu Gestão → Flow Track. O HTTP oficial `http://localhost:8066/ImproovWeb/` foi usado na matriz automatizada. Desktop, notebook, iPad landscape/portrait e mobile foram capturados; as imagens do relatório mostram o login sem mudança de layout. Esta não é uma auditoria visual de todas as telas internas.

Limitações: estado não salvo apenas em memória não é restaurado; o login externo conserva seu fluxo separado; sessões literalmente concorrentes continuam sujeitas à rotação normal do cookie PHP; APIs legadas fora do bootstrap/interceptor não foram refatoradas. A funcionalidade foi validada localmente, sem deploy nesta tarefa.

## Retomada do projeto após expiração — correção de 22/10/2026

A página `Dashboard/obra` dependia de `localStorage.obraId`, embora alguns links já contivessem `obra_id`. O retorno pós-login preservava a rota, mas a interface ignorava o ID da URL. Outra dependência era a passagem pela Home para preencher `idusuario` e `idcolaborador`; o retorno direto pula essa etapa. Além disso, outra aba podia sobrescrever a obra global usada em consultas e ações.

A abertura agora mantém o ID na URL, por exemplo `Dashboard/obra?obra_id=...`. O servidor interpreta os aliases existentes (`obra_id`, `idobra`, `id`), confirma a autorização com o helper existente e verifica a existência do projeto antes de renderizar. Os scripts recebem `FlowProjectContext` com o ID dessa página e os IDs do usuário/colaborador vindos da sessão atual. O contexto fica fixo no documento; os widgets de projeto, contatos, onboarding, ALMA, 3D e Entregas não consultam o cache compartilhado para escolher a obra dessa aba.

Links antigos da sidebar continuam funcionando sem alterar a sidebar: uma entrada autenticada leve recupera o ID legado do cache e faz um GET com `obra_id`, antes de carregar o projeto. Esse novo GET passa normalmente pela autorização. Sem ID/cache, o usuário vai à lista de projetos. Um ID explicitamente inválido nunca recupera silenciosamente outro projeto do cache; projetos inexistentes também levam à lista. O cache só é atualizado por compatibilidade, e a página com ID explícito recebe seu contexto mesmo sem cache prévio.

O retorno à página após login não depende mais de visitar a Home. Query/hash continuam sob o mecanismo central; os metadados públicos mantêm a canonical institucional sem ID. Nenhuma operação interrompida é repetida.

### Arquivos para publicar esta correção

```text
Dashboard/obra-context.php (novo)
Dashboard/obra-context.js (novo)
Dashboard/obra.php
Dashboard/scriptObra.js
Dashboard/obra-3d.js
Dashboard/obraContacts.js
Dashboard/onboardingObra.js
Dashboard/almaObra.js
Entregas/script.js
```

Publicar os dois arquivos novos primeiro, depois a entrada PHP e os scripts atualizados, preferencialmente no mesmo deploy. Os scripts carregados pelo projeto usam versão baseada na data do arquivo. Os arquivos centrais de login/retorno, sidebar e implementação de metadata não foram modificados por esta correção.

### Validação específica

`php tools/test_project_context.php`: **22 verificações sem falhas**, incluindo os aliases, entradas inválidas, arrays, overflow e inicialização com a sessão atual.

`node tools/audit_project_return.cjs`: **15 verificações integradas sem falhas**, em [output/project-return-1792680058139.json](../output/project-return-1792680058139.json). Cobre abertura pelo menu real, expiração em AJAX, limpeza do cache no login, retomada exata da URL, dados carregados, usuário inicializado sem Home, duas abas com projetos diferentes, atualização sem cache, link direto num navegador sem histórico, alias, ID inválido e projeto inexistente. Só consulta dados; a expiração usa a fixture CLI em uma sessão isolada de teste.

No navegador interno, a abertura pelo menu de projetos produziu a URL com `obra_id`, carregou o nome do projeto e as linhas reais da tabela sem erro. Também passaram novamente as 188 verificações de autenticação, 811 de metadata e os checks de sintaxe dos arquivos alterados. A correção foi feita localmente; não houve deploy.
