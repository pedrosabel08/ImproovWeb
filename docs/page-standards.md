# Padrão obrigatório de páginas do Flow

Toda página nova ou alterada do Flow deve usar a metadata central para título, favicon, Open Graph e Twitter/X Card. Esta regra complementa o AGENTS.md e se aplica também a telas internas, variantes externas e templates renderizados pelo servidor.

## Componentes

- `config/page_metadata.php`: catálogo institucional, resolução por arquivo, origem/base, fallbacks, geração de tags e URL do login contextual. Não depende de sessão ou banco.
- `includes/page-head.php`: inclusão idempotente das tags no head PHP.
- `login-preview.php`: reutiliza o HTML estático de `index.html`, substituindo somente title/favicon/metadata no servidor. Não executa páginas protegidas nem acessa banco ou sessão.
- `.htaccess`: encaminha a raiz e `index.html` ao renderer, preservando o formulário e o endpoint `login.php`.
- `assets/og/flow-default.png`: imagem institucional existente do Flow, reutilizada sem alteração (550×275).
- `assets/icons/flow-favicon.png`: cópia da mesma imagem oficial já utilizada no projeto; PNG 225×225. Não criar outra identidade visual para favicons de módulos.

## Como criar uma página

1. Adicione uma entrada **literal e pública** em `flow_metadata_catalog()`, com chave estável, título apropriado, descrição curta, rota canônica e arquivos de entrada. Reutilize uma chave quando forem variantes da mesma página. Não use valores do banco ou da requisição para preencher o catálogo.
2. Preserve o guard e as regras de autorização da página. Em um branch HTML que já bloqueia acesso anônimo, use `flow_auth_redirect_to_login();` e encerre com `exit`. Registre o documento e integre o script conforme [o padrão de retorno pós-login](authentication-navigation.md). A função não autentica nem autoriza ninguém.
3. No `<head>`, inclua `includes/page-head.php` com caminho relativo a `__DIR__`. Remova title, favicon e metatags equivalentes antigos para evitar duplicação.
4. Para templates HTML gerados por PHP, use `flow_metadata_apply_to_html($html)` antes de enviar o documento. Não execute JS para montar metadata.
5. Execute os testes e atualize a auditoria se adicionar um destino à sidebar.

Exemplo a partir de um diretório de módulo:

```php
<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (($_SESSION['logado'] ?? false) !== true) {
    flow_auth_redirect_to_login();
    exit;
}
// Preservar aqui as verificações adicionais de permissão existentes.
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= flow_auth_navigation_script() ?>
    <?php require __DIR__ . '/../includes/page-head.php'; ?>
</head>
```

Exemplo de definição no catálogo:

```php
'novo-modulo' => [
    'Novo Módulo | Flow',
    'Descrição institucional curta da funcionalidade.',
    'NovoModulo/',
    ['NovoModulo/index.php'],
],
```

Sem configuração específica, o helper usa `Flow | Improov`, `Plataforma de gestão de projetos da Improov.`, imagem OG padrão e favicon oficial. O fallback não dispensa incluir o helper na página. Não adicionar tags a `sidebar.php`: a sidebar não é dona do head.

## Metadata pública e autenticação

Uma página protegida continua protegida. Sem sessão, o guard envia HTTP 302 para `login-preview.php?preview=chave-publica`, com um contexto opaco de navegação quando aplicável. O servidor retorna o mesmo login, com metadata institucional no HTML inicial. `preview` só seleciona uma entrada fixa do catálogo. Não aceita URLs, caminhos de arquivo ou destinos de navegação. Chaves inválidas, desconhecidas ou arrays usam o fallback. O destino pós-login fica na sessão e não entra nas tags.

Não usar User-Agent para conceder acesso a crawlers. Nunca remover guards, liberar APIs ou renderizar o corpo de uma página privada para montar preview. O mesmo login público é entregue a navegadores e crawlers. Não há meta refresh ou execução de JS para produzir OG.

Não alterar respostas de APIs por causa da metadata. JSON/XHR deve manter 401/403 e as regras de autorização/CSRF existentes. No módulo Custos, somente a entrada HTML GET/HEAD anônima redireciona ao login; `custos_auth()` e APIs permanecem iguais. Na expiração de sessão, somente páginas HTML cadastradas recebem a chave contextual.

O retorno pós-login é uma funcionalidade de autenticação separada da metadata, documentada em [authentication-navigation.md](authentication-navigation.md). Sem contexto válido, o login abre `inicio.php`. Usuários já autenticados continuam abrindo diretamente a página solicitada, sujeitos às permissões atuais. `FlowReviewExt` mantém seu mecanismo próprio de cookie/token.

## Privacidade e URLs

- Metadata pública só pode conter marca, nome do módulo e descrição institucional da função.
- Nunca incluir obra, nomenclatura, cliente, colaborador, imagem, comentário, status interno, valores financeiros, IDs, tokens ou informações protegidas, inclusive no título e no texto alternativo das imagens.
- Nunca copiar `REQUEST_URI`, query, fragmento, cookies ou dados do banco para `og:url`. O helper usa a rota canônica fixa do módulo. Variantes com parâmetros ou tokens continuam genéricas.
- Imagens OG e favicon devem estar disponíveis anonimamente com HTTP 200 e MIME correto. Nenhum asset privado pode ser utilizado como fallback.
- URLs de imagem, favicon e `og:url` são absolutas. Os hosts locais reconhecidos são `https://improov/ImproovWeb/` e `http://localhost:8066/ImproovWeb/`; a publicação também pode usar `/flow/ImproovWeb`.
- Para outro domínio de publicação, configure `FLOW_PUBLIC_BASE_URL` no ambiente do servidor com a origem e base completas. Sem configuração, hosts desconhecidos não são refletidos e usam o domínio institucional já adotado no projeto. Não confiar em `Host` ou `X-Forwarded-Host` arbitrários.
- Publicar somente o endpoint de login/metadata e os assets públicos não autoriza tornar conteúdo de módulos público. Páginas já públicas, como TV Dashboard, mantêm seu comportamento existente.

## Checklist obrigatório de criação e revisão

- [ ] Catálogo atualizado com título, descrição institucional e rota sem parâmetros privados.
- [ ] Helper central no head, ou aplicado ao template pelo servidor.
- [ ] Um único title e favicon oficial; sem tags OG/X duplicadas.
- [ ] `og:title`, `og:description`, `og:image`, `og:url`, `og:type`, `og:site_name` e Twitter Card completos.
- [ ] Fallback validado para uma página sem configuração específica.
- [ ] Metadata presente no HTML inicial e imagens acessíveis sem cookies.
- [ ] Nenhum dado privado em tags, URLs, descrições ou assets.
- [ ] Página autenticada e navegação pela sidebar validadas no navegador interno.
- [ ] Acesso anônimo bloqueado para conteúdo privado; login contextual correto.
- [ ] APIs e permissões mantidas; nenhum bypass por User-Agent.
- [ ] Desktop, notebook, iPad landscape, iPad portrait e mobile verificados conforme AGENTS.md.
- [ ] Testes de metadata executados; auditoria atualizada.

## Verificação reproduzível

`php tools/test_page_metadata.php` testa catálogo, cobertura da sidebar real, fallback, privacidade, origem/Host, aplicação a templates, tags e assets sem banco ou credenciais.

`node tools/audit_page_metadata.cjs` executa HTTP sem cookies, cenários de User-Agent, rejeição de acesso em APIs, navegação autenticada pela sidebar e captura do login em cinco resoluções. Requer Playwright como ferramenta externa de teste, não como dependência da aplicação. `FLOW_PLAYWRIGHT_MODULE` pode apontar para o Playwright do runtime fornecido pelo Codex; `FLOW_AUDIT_PHP` seleciona o PHP. As credenciais vêm de `FLOW_AUDIT_LOGIN`/`FLOW_AUDIT_PASSWORD` ou do AGENTS.md autorizado. O script aceita somente as duas origens locais oficiais e grava resultados/screenshot em `output/link-preview-*`, sem gravar credenciais ou HTML privado.

Os scripts em `tools/` de teste não devem ser executáveis pela web; o teste PHP recusa requisições HTTP. Detalhes da matriz inicial e dos resultados estão em `docs/link-preview-audit.md`.

Um crawler externo precisa alcançar o domínio publicado e seus assets. O ambiente local não permite confirmar o cache de WhatsApp, Slack, Discord ou X. Não enviar links privados a validadores externos; após publicação, usar uma URL institucional de módulo sem parâmetros sensíveis para validação de integração.
