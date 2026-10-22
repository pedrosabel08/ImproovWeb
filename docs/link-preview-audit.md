# Link Preview do Flow — levantamento e auditoria

Data do levantamento: 21/10/2026 (America/Sao_Paulo).

## Situação antes da implementação

A fonte de verdade é `sidebar.php`: navegação ativa a partir de `<aside class="sidebar">`, marca, perfil, grupos e projetos dinâmicos. O `<template id="sidebar-legacy-template">` contém navegação antiga e não deve ser contado novamente. Há 32 destinos estáticos distintos, mais `Dashboard/obra`, usado pelos projetos e favoritos em `assets/js/flow-shell.js`. Onboarding aponta para uma âncora do Dashboard; não é uma página adicional. Visibilidade por permissão continua definida na sidebar.

Todas as páginas abaixo têm head próprio, nenhum Open Graph e nenhum Twitter Card. `E` significa favicon externo comum, hospedado em `encrypted-tbn0.gstatic.com`; `L` significa `assets/logo.jpg`; `—` significa ausente. A coluna HTTP foi verificada sem cookies na URL oficial HTTPS. Rotas são relativas a `/ImproovWeb/`, sem pressupor publicação na raiz.

| Página/módulo | Rota | Title anterior | OG/X anterior | Ícone anterior | HTTP anônimo anterior | Título final |
|---|---|---|---|---|---|---|
| Página Principal | `inicio.php` | Improov+Flow | ausente | E | 302 | Flow \| Improov |
| Flow Review | `FlowReview/` | Flow Review | ausente | E | 302 | Flow Review \| Improov |
| Flow Block | `FlowBlock/` | Flow Block | ausente | E | 302 | Flow Block \| Improov |
| Flow Render | `Render/` | Renders | ausente | E | 302 | Flow Render \| Improov |
| Pós-Produção | `Pos-Producao/` | Lista Pós-Produção | ausente | E | 302 | Pós-Produção \| Flow |
| Fotográfico | `Fotografico/` | Fotográfico | ausente | E | 302 | Fotográfico \| Flow |
| Flow Drive | `FlowDrive/` | Flow Drive | ausente | E | 302 | Flow Drive \| Improov |
| Pré-Alteração | `PreAlteracao/` | Pré-Alteração | ausente | E | 302 | Pré-Alteração \| Flow |
| Alterações | `Alteracao/` | Tela de Alterações | ausente | E | 302 | Alterações \| Flow |
| TV Dashboard | `TvDashboard/` | TV — Produção por Função | ausente | E | 200 | TV Dashboard \| Flow |
| Entregas | `Entregas/` | Flow \| Entregas | ausente | E | 302 | Entregas \| Flow |
| Gestão | `Gestao/` | Gestão de Projetos | ausente | E | 302 | Gestão de Projetos \| Flow |
| Tela Gerencial | `TelaGerencial/` | Tela Gerencial | ausente | E | 302 | Tela Gerencial \| Flow |
| Metas TV | `AdminMetas/` | Admin — Metas por Colaborador | ausente | E | 302 | Metas TV \| Flow |
| Flow Track | `FlowTrack/` | Flow \| Track | ausente | — | 302 | Flow Track \| Improov |
| Colaboradores | `Colaborador/` | Colaboradores | ausente | E | 302 | Colaboradores \| Flow |
| Atividade | `Atividade/` | Atividade — Flow | ausente | E | 302 | Atividade do Sistema \| Flow |
| Dashboard | `Dashboard/` | Dashboard | ausente | E | 302 | Dashboard \| Flow |
| Dashboard Operacional | `Dashboard/Operacional/` | Dashboard Operacional | ausente | E | 302 | Dashboard Operacional \| Flow |
| Capacidade da Produção | `PlanejamentoCapacidade/` | Planejamento Global de Capacidade · Flow | ausente | E | 302 | Capacidade da Produção \| Flow |
| Projetos | `Projetos/` | Gerenciamento de projetos | ausente | E | 302 | Projetos \| Flow |
| Quadro TEA | `Quadro/` | Quadro de Produção | ausente | E | 302 | Quadro de Produção \| Flow |
| Biblioteca ALMA | `ALMA/admin.php` | ALMA - Biblioteca Oficial | ausente | — | 302 | Biblioteca ALMA \| Flow |
| Pagamento | `Pagamento/` | Tela de pagamento | ausente | E | 302 | Pagamentos \| Flow |
| Contratos | `Contratos/` | Contratos | ausente | E | 302 | Contratos \| Flow |
| Custos | `Custos/` | Custos do Projeto · Flow | ausente | — | 401 JSON | Custos \| Flow |
| Mapa de Pavimento | `MapaPavimento/` | Mapa arquitetônico · Flow | ausente | L | 302 | Mapa de Pavimento \| Flow |
| SIRE | `SIRE/` | SIRE — Biblioteca Visual | ausente | E | 302 | SIRE — Biblioteca Visual \| Flow |
| Mapa de Compatibilização | `MapaCompatibilizacao/` | Mapa de Compatibilização | ausente | — | 302 | Mapa de Compatibilização \| Flow |
| Notificações | `notificacoes/` | Notificações | ausente | E | 302 | Notificações \| Flow |
| Flow Referências | `FlowReferencias/` | Flow Referências | ausente | E | 302 | Flow Referências \| Improov |
| Informações do Usuário | `infos.php` | Informações do Usuário | ausente | E | 302 para login.php | Informações do Usuário \| Flow |
| Projetos/favoritos da sidebar | `Dashboard/obra` e `Dashboard/obra.php` | Detalhes da Obra | ausente | E | 302 | Projeto \| Flow |

## Arquitetura encontrada

- PHP sem layout global de head. Os includes em `includes/` são pontuais; `includes/flow-motion-assets.php` é um exemplo de helper de emissão de assets, mas não cuida de metadata.
- `config/version.php` centraliza `asset_url()`, mas consulta o banco para versionamento. Não deve ser carregado pelo endpoint de metadata pública.
- `config/session_bootstrap.php` inicia sessão, define cookies e controla expiração. Não é um guard global de acesso: cada página decide se exige login. Na expiração, retorna 401 para JSON/XHR ou redireciona HTML para `index.html`.
- A maioria das páginas verifica `$_SESSION['logado'] === true`, emite `Location: ../index.html` e encerra antes do head. Não adianta adicionar OG depois do guard.
- `infos.php` usa `idusuario` como condição e redireciona para `login.php`, que é endpoint JSON, não a tela HTML.
- `notificacoes/_common.php` compartilha autorização entre página e actions, com 401/403 JSON para actions. `Custos/custos_auth.php` compartilha autenticação/autorização/CSRF entre página e APIs; a entrada HTML também retorna JSON sem sessão.
- `TvDashboard/index.php` já entrega HTML sem iniciar sessão. Não se deve introduzir ou remover autorização nesta tarefa.
- `FlowReviewExt/index.php` tem autenticação própria por cookie `flow_auth`, consultando `login_tokens`. Não é equivalente à sessão interna do Flow.
- `sidebar.php` emite um documento HTML aninhado, inclusive `<title>Sidebar</title>`. Remover apenas esse título evita duplicação de metadata sem refatorar o layout inteiro.
- `script/scriptLogin.js` sempre encaminha o login bem-sucedido para `inicio.php`. Retomada automática do deep link não existe atualmente e não será introduzida nesta tarefa.
- `.htaccess` tem o alias `Dashboard/obra` para `Dashboard/obra.php`. O viewer 3D PHP renderiza um template HTML; precisa aplicar o helper ao HTML gerado no servidor.

## Páginas relacionadas

| Página | Origem/justificativa | Situação anterior | Título final |
|---|---|---|---|
| `PaginaPrincipal/Home/index.php` | Redirecionamento de primeiro acesso de `inicio.php` | title e ícone E, sem OG/X | Flow \| Improov |
| `PaginaPrincipal/Overview/index.php` | Alias legado redireciona para início | sem head; manter redirect | Flow \| Improov |
| `FlowReview/index.php` com parâmetros | Links de Quadro, Alteração, timeline e Dashboard | mesmo módulo; parâmetros podem conter nomes/IDs | Flow Review \| Improov |
| `FlowReviewExt/index.php` | Review externo e rotas de token | head próprio, ícone E, autenticação separada | Flow Review \| Improov |
| `FlowReviewExt/login.php` | Login do review externo | sem ícone e sem OG/X | Flow Review \| Improov |
| `FlowReviewExt/register.php` | Cadastro do review externo | sem ícone e sem OG/X | Flow Review \| Improov |
| `review/index.php` | Variante legada de Review presente no repositório | ícone E, sem OG/X | Flow Review \| Improov |
| `review/obra.php` | Detalhe legado de Review; sem guard no arquivo | sem ícone e sem OG/X | Flow Review \| Improov |
| `ALMA/index.php` | Atalho ALMA em `Dashboard/obra.php` | favicon L declarado com MIME de ICO | ALMA — Direção Visual \| Flow |
| `PlanejamentoProducao/index.php` | Atalho Plano R00 de `Dashboard/scriptObra.js` | favicon E duplicado, sem OG/X | Planejamento de Produção \| Flow |
| `Briefing/index.php` | Fluxo de briefing do projeto | ícone E, sem OG/X | Briefings \| Flow |
| `MapaPavimento/predio-3d/visualizador-glb-navegacao.php` | Viewer 3D do módulo espacial | template HTML com ícone injetado pelo PHP | Visualizador 3D \| Flow |
| `index.html` e raiz | Login interno, destino dos guards | título Login, ícone E, sem OG/X | fallback ou módulo solicitado |

As variantes legadas e externas são incluídas por coerência de metadata, sem afirmar que integram diretamente a sidebar atual. Links de Review configuráveis no banco podem apontar para outros sistemas; não cabe alterá-los nem consultá-los para gerar metadata pública.

## Assets existentes

- Favicon comum: mesma imagem externa Google em 27 dos 32 destinos estáticos. Reutilizar os mesmos pixels, disponibilizando cópia local; não desenhar um ícone novo.
- `assets/logo.jpg`: marca Improov, JPEG 636×628, já público (200).
- `assets/ImproovFlow - logo.png`: marca Improov Flow, PNG 550×275, já público (200), adequado como ponto de partida institucional. Reutilizar sem manipular conteúdo de projetos.
- Não foi localizado diretório OG dedicado ou conjunto de metatags existente nas páginas auditadas.
- Uma única imagem institucional padrão atende todos os módulos inicialmente, inclusive Flow Review; não é necessário multiplicar imagens.

## Estratégia aprovada

O usuário autorizou expressamente o login com metadata por módulo em 21/10/2026.

1. Catálogo PHP estático: chave pública, título, descrição curta e rota canônica do módulo. Nenhum acesso a banco, sessão ou parâmetros privados para compor valores.
2. Helper central gera title, description, favicon, Open Graph e Twitter Card com escape de HTML e URLs absolutas.
3. Cada head usa o helper em lugar das tags repetidas. Resolução pelo arquivo da página; fallback institucional quando não houver configuração específica.
4. Guards HTML mantêm a decisão de bloquear e o HTTP 302, mas encaminham para o mesmo login com uma chave pública, por exemplo `index.html?preview=flow-review`. O parâmetro aceita somente chaves do catálogo. Nunca transportar a query original, token, IDs ou um destino arbitrário.
5. `login-preview.php`, na raiz, reaproveita o HTML de `index.html`, substituindo somente metadata no servidor. Regras pontuais em `.htaccess` encaminham a raiz e `index.html` ao renderer. Formulário, CSS, JS e endpoint de autenticação permanecem os mesmos.
6. O crawler que segue o 302 encontra o login com metadata institucional no HTML inicial. O usuário encontra o formulário normal. Não há detecção de User-Agent, bypass, meta refresh ou dependência de JS para OG.
7. Expiração de sessão só acrescenta o contexto público para páginas HTML cadastradas; respostas de APIs continuam iguais.
8. Custos: apenas a entrada HTML GET/HEAD anônima passa a redirecionar ao login contextual. `custos_auth()` e APIs continuam com 401/403/CSRF existentes. A diferença de transporte HTML fica documentada.
9. `og:url` é a rota canônica do módulo, sem query, fragmento, IDs ou token. `FlowReviewExt` usa a raiz do módulo inclusive em URLs de token.
10. Origem das URLs obtida de configuração confiável ou dos hosts oficiais conhecidos; nunca refletir qualquer Host/X-Forwarded-Host recebido.

Um HTML público 200 na URL protegida, seguido de navegação JS/meta refresh, seria outra abordagem, mas mudaria o transporte anônimo e o comportamento sem JS. A abordagem aprovada mantém o redirecionamento HTTP das páginas que já redirecionavam.

## Evidências iniciais e limitações

- Navegador interno: `https://improov/ImproovWeb/` abriu o login; autenticação local bem-sucedida; navegação pela sidebar até Flow Review bem-sucedida.
- `http://localhost:8066/ImproovWeb/` respondeu 200 em requisição HTTP. O sandbox do terminal inicialmente bloqueava acesso de rede; a repetição autorizada fora dele confirmou ambas as URLs oficiais.
- HTTP sem cookies nos 33 destinos: 31 respostas 302, uma 200 (TV) e uma 401 JSON (Custos). O alias de obra foi incluído na contagem.
- User-Agent `facebookexternalhit/1.1` também recebeu 302 no Flow Review, sem tratamento privilegiado.
- Os dois arquivos de marca locais responderam 200 anonimamente.
- Estes são testes de situação inicial; não substituem a auditoria após implementação. Não foi feita nesta fase uma auditoria completa de segurança das APIs ou de conteúdos já públicos.
- Crawlers externos não alcançam os nomes/portas locais. Validação real em WhatsApp/Slack/Discord depende de publicação em domínio público e de atualização do cache do provedor. Não enviar URLs privadas a depuradores externos.

Referências de implementação: [Open Graph Protocol](https://ogp.me/) e [HTTP Semantics — 302](https://www.rfc-editor.org/rfc/rfc9110.html#name-302-found).

## Resultado após implementação

Implementação concluída e validada localmente em 21/10/2026. O catálogo possui **38 configurações institucionais e 44 entradas PHP**, cobrindo os **33 destinos da sidebar** (32 estáticos e o projeto dinâmico), além das páginas relacionadas da matriz acima. A última coluna das tabelas registra os títulos implementados.

### Resultado da auditoria

| Verificação | Resultado |
|---|---|
| Catálogo, segurança, templates, fallback e cobertura da sidebar real | 811 verificações, nenhuma falha |
| Sintaxe PHP | 51 arquivos verificados, nenhum erro |
| Sintaxe JavaScript | auditor e três scripts de título verificados, nenhum erro |
| HTTP sem cookies | 78 combinações de rota/arquivo aprovadas; metadata validada no HTML inicial |
| Destinos estáticos autenticados | 32/32, descobertos da sidebar ativa após login; HTML inicial e DOM conferidos |
| Projeto dinâmico da sidebar | seleção real pelo grupo de projetos, acesso autenticado e metadata genérica aprovados |
| Página adicional autenticada | Briefing aprovado; total de 34 verificações autenticadas |
| Acesso anônimo aos 33 destinos | 32 exigem login com HTTP 302; TV Dashboard mantém HTML público preexistente |
| Favicon | único e padronizado em todos os heads incluídos; corrigidos quatro ausentes e a variante do Mapa |
| Duplicações preexistentes | retirado title da sidebar; retirados os dois favicons de Planejamento de Produção e substituídos por um do helper |
| Open Graph / X | title, description, image, URL canônica, type, site_name e Twitter Card presentes uma única vez em cada resposta auditada |
| Privacidade da metadata | valores iguais ao catálogo estático; query com nomes/IDs fictícios não refletida; nenhum destino arbitrário aceito |
| User-Agent | navegador, Facebook, WhatsApp, Slack e Discord recebem o mesmo bloqueio/login; sem bypass |
| APIs sem sessão | quatro contratos JSON preservados com 401: Custos/index com Accept JSON, Custos/getCustosObra, MapaPavimento/api e notificacoes/actions/preview |
| Assets OG/favicon | ambos HTTP 200 anônimos e Content-Type image/png |
| Responsividade do login contextual | desktop 1920×1080, notebook 1366×768, iPad landscape 1024×768, iPad portrait 768×1024 e mobile 390×844; formulário legível, sem corte horizontal; capturas inspecionadas |
| Navegador interno | login nas duas origens, Flow Review e Mapa via sidebar, projeto dinâmico; link anônimo abriu login contextual sem sidebar privada; login contextual terminou no início como antes |

As páginas não tiveram seus layouts/CSS refatorados. A verificação de cinco tamanhos foi feita no login reaproveitado pelo novo renderer. As páginas autenticadas foram verificadas em desktop; isso não é uma auditoria geral de responsividade de todos os módulos preexistentes.

O relatório automatizado de aprovação é `output/link-preview-1792632516976-audit.json`, acompanhado de cinco PNGs com o mesmo prefixo. Não contém credenciais, nomes ou IDs do projeto selecionado nem HTML privado.

### Ajustes necessários identificados nos testes

- Dashboard/obra, Alteracao, Colaborador e Projetos liam nome_usuario antes do guard; aplicado fallback vazio para não produzir Warning em acesso anônimo. O conteúdo autenticado e as condições de acesso continuam iguais.
- FlowTrack e review/index tentavam iniciar novamente uma sessão já iniciada pelo bootstrap; removida somente a chamada redundante, evitando Notice no redirecionamento.
- Dashboard/scriptObra e os dois scripts do viewer 3D substituíam document.title por nomes de obra/modelo. Essas atribuições foram removidas para preservar o título institucional do head; os nomes continuam no corpo autenticado. Nenhum OG é produzido por JavaScript.
- Custos HTML GET/HEAD anônimo agora redireciona para o login contextual, conforme estratégia autorizada; respostas de APIs/JSON e autorização financeira permanecem iguais.
- infos.php agora encaminha ao formulário HTML contextual em vez do endpoint JSON login.php.

### Documentação permanente

Criado `docs/page-standards.md` com arquitetura, exemplos, fallbacks, regras de privacidade, assets públicos, instruções de teste e checklist obrigatório. A seção 9 de `AGENTS.md` determina seguir esse documento.

**Observação de versionamento:** AGENTS.md já é ignorado por `.gitignore` (regra AGENTS.MD). A seção foi atualizada no arquivo local, mas não aparecerá no diff versionado nem será transportada automaticamente em um commit. O documento de padrões está entre os novos arquivos versionáveis. A regra de ignore foi preservada, inclusive porque o AGENTS local contém credenciais do ambiente.

### Limitações restantes

- Não foi publicado nem enviado link a serviços externos. A integração final e o cache de WhatsApp/Slack/Discord/X dependem de domínio público acessível e publicação das regras Apache, PHP e assets.
- FlowReviewExt mantém autenticação separada; sua metadata pública foi auditada, mas não foi criada uma conta externa nem exercitado login com cookie flow_auth. Nenhuma regra desse mecanismo foi alterada.
- Rotas externas/legadas de token, endpoints de negócio e telas que já eram públicas não foram transformados em novos sistemas de autorização. A auditoria de segurança completa desses fluxos está fora da mudança de metadata.
- O login mantém o redirecionamento pós-autenticação para inicio.php; não foi criada retomada de URL interna.
- Para outro host de publicação, configurar FLOW_PUBLIC_BASE_URL, conforme o documento de padrões.

### Arquivos criados

- `config/page_metadata.php`
- `includes/page-head.php`
- `login-preview.php`
- `assets/og/flow-default.png`
- `assets/icons/flow-favicon.png`
- `docs/link-preview-audit.md`
- `docs/page-standards.md`
- `tools/test_page_metadata.php`
- `tools/audit_page_metadata.cjs`

### Arquivos existentes alterados

A lista a seguir inclui os pontos de integração dos heads, os redirects HTML e os ajustes de título/diagnósticos necessários à padronização. Não inclui alterações preexistentes de Backup.

- `.htaccess`
- `ALMA/admin.php`
- `ALMA/index.php`
- `AdminMetas/index.php`
- `Alteracao/index.php`
- `Atividade/index.php`
- `Briefing/index.php`
- `Colaborador/index.php`
- `Contratos/index.php`
- `Custos/index.php`
- `Dashboard/Operacional/index.php`
- `Dashboard/index.php`
- `Dashboard/obra.php`
- `Dashboard/scriptObra.js`
- `Entregas/index.php`
- `FlowBlock/index.php`
- `FlowDrive/index.php`
- `FlowReferencias/index.php`
- `FlowReview/index.php`
- `FlowReviewExt/index.php`
- `FlowReviewExt/login.php`
- `FlowReviewExt/register.php`
- `FlowTrack/index.php`
- `Fotografico/index.php`
- `Gestao/index.php`
- `MapaCompatibilizacao/index.php`
- `MapaPavimento/index.php`
- `MapaPavimento/predio-3d/empreendimento-map-bridge.js`
- `MapaPavimento/predio-3d/visualizador-glb-navegacao.js`
- `MapaPavimento/predio-3d/visualizador-glb-navegacao.php`
- `Pagamento/index.php`
- `PaginaPrincipal/Home/index.php`
- `PaginaPrincipal/Overview/index.php`
- `PlanejamentoCapacidade/index.php`
- `PlanejamentoProducao/index.php`
- `Pos-Producao/index.php`
- `PreAlteracao/index.php`
- `Projetos/index.php`
- `Quadro/index.php`
- `Render/index.php`
- `SIRE/index.php`
- `TelaGerencial/index.php`
- `TvDashboard/index.php`
- `config/session_bootstrap.php`
- `infos.php`
- `inicio.php`
- `notificacoes/_common.php`
- `notificacoes/index.php`
- `review/index.php`
- `review/obra.php`
- `sidebar.php`
- `AGENTS.md (local, já ignorado pelo Git)`
