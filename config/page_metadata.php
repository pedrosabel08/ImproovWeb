<?php
/**
 * Public, institutional metadata only. No database, session or request-query data.
 * Add literal page definitions here; see docs/page-standards.md.
 */
function flow_metadata_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }
    // key => [title, description, canonical route, PHP entry points]
    $definitions = [
        'flow' => ['Flow | Improov', 'Plataforma de gestão de projetos da Improov.', 'inicio.php', ['inicio.php', 'PaginaPrincipal/Home/index.php', 'PaginaPrincipal/Overview/index.php']],
        'flow-review' => ['Flow Review | Improov', 'Revisão e aprovação de imagens.', 'FlowReview/', ['FlowReview/index.php', 'review/index.php', 'review/obra.php']],
        'flow-review-externo' => ['Flow Review | Improov', 'Revisão e aprovação de imagens.', 'FlowReviewExt/', ['FlowReviewExt/index.php', 'FlowReviewExt/login.php', 'FlowReviewExt/register.php']],
        'flow-block' => ['Flow Block | Improov', 'Organização de impedimentos e acompanhamento de soluções.', 'FlowBlock/', ['FlowBlock/index.php']],
        'flow-render' => ['Flow Render | Improov', 'Acompanhamento da produção e revisão de renders.', 'Render/', ['Render/index.php']],
        'pos-producao' => ['Pós-Produção | Flow', 'Organização e acompanhamento da pós-produção de imagens.', 'Pos-Producao/', ['Pos-Producao/index.php']],
        'fotografico' => ['Fotográfico | Flow', 'Planejamento e acompanhamento da produção fotográfica.', 'Fotografico/', ['Fotografico/index.php']],
        'flow-drive' => ['Flow Drive | Improov', 'Organização e compartilhamento de arquivos de projetos.', 'FlowDrive/', ['FlowDrive/index.php']],
        'pre-alteracao' => ['Pré-Alteração | Flow', 'Análise e planejamento de solicitações de alteração.', 'PreAlteracao/', ['PreAlteracao/index.php']],
        'alteracao' => ['Alterações | Flow', 'Organização e acompanhamento de alterações de imagens.', 'Alteracao/', ['Alteracao/index.php']],
        'tv-dashboard' => ['TV Dashboard | Flow', 'Visão da produção e acompanhamento de metas da equipe.', 'TvDashboard/', ['TvDashboard/index.php']],
        'entregas' => ['Entregas | Flow', 'Planejamento e acompanhamento das entregas de projetos.', 'Entregas/', ['Entregas/index.php']],
        'gestao' => ['Gestão de Projetos | Flow', 'Visão integrada do planejamento e da gestão de projetos.', 'Gestao/', ['Gestao/index.php']],
        'tela-gerencial' => ['Tela Gerencial | Flow', 'Indicadores para acompanhamento da operação e da produção.', 'TelaGerencial/', ['TelaGerencial/index.php']],
        'metas-tv' => ['Metas TV | Flow', 'Organização de metas para acompanhamento da produção.', 'AdminMetas/', ['AdminMetas/index.php']],
        'flow-track' => ['Flow Track | Improov', 'Acompanhamento das etapas e da evolução da produção.', 'FlowTrack/', ['FlowTrack/index.php']],
        'colaboradores' => ['Colaboradores | Flow', 'Organização da equipe e acompanhamento de atividades.', 'Colaborador/', ['Colaborador/index.php']],
        'atividade' => ['Atividade do Sistema | Flow', 'Acompanhamento da utilização e das atividades do Flow.', 'Atividade/', ['Atividade/index.php']],
        'dashboard' => ['Dashboard | Flow', 'Indicadores e visão geral da gestão de projetos.', 'Dashboard/', ['Dashboard/index.php']],
        'dashboard-operacional' => ['Dashboard Operacional | Flow', 'Visão operacional para acompanhamento da produção.', 'Dashboard/Operacional/', ['Dashboard/Operacional/index.php']],
        'capacidade' => ['Capacidade da Produção | Flow', 'Planejamento da capacidade e da distribuição de trabalho.', 'PlanejamentoCapacidade/', ['PlanejamentoCapacidade/index.php']],
        'projetos' => ['Projetos | Flow', 'Organização e acompanhamento dos projetos da Improov.', 'Projetos/', ['Projetos/index.php']],
        'quadro' => ['Quadro de Produção | Flow', 'Organização visual das tarefas e etapas da produção.', 'Quadro/', ['Quadro/index.php']],
        'alma-biblioteca' => ['Biblioteca ALMA | Flow', 'Biblioteca institucional de referências e direção visual.', 'ALMA/admin.php', ['ALMA/admin.php']],
        'alma' => ['ALMA — Direção Visual | Flow', 'Referências e ferramentas para direção visual de projetos.', 'ALMA/', ['ALMA/index.php']],
        'pagamento' => ['Pagamentos | Flow', 'Organização e acompanhamento de pagamentos.', 'Pagamento/', ['Pagamento/index.php']],
        'pagamento-fechamento' => ['Fechamento mensal | Flow', 'Organização e revisão do fechamento mensal de pagamentos.', 'Pagamento/fechamento.php', ['Pagamento/fechamento.php']],
        'contratos' => ['Contratos | Flow', 'Organização e acompanhamento dos contratos de projetos.', 'Contratos/', ['Contratos/index.php']],
        'custos' => ['Custos | Flow', 'Planejamento e acompanhamento dos custos de projetos.', 'Custos/', ['Custos/index.php']],
        'mapa-pavimento' => ['Mapa de Pavimento | Flow', 'Visualização e gestão de ambientes do projeto.', 'MapaPavimento/', ['MapaPavimento/index.php']],
        'sire' => ['SIRE — Biblioteca Visual | Flow', 'Biblioteca de referências visuais para projetos.', 'SIRE/', ['SIRE/index.php']],
        'mapa-compatibilizacao' => ['Mapa de Compatibilização | Flow', 'Visualização e organização da compatibilização de projetos.', 'MapaCompatibilizacao/', ['MapaCompatibilizacao/index.php']],
        'notificacoes' => ['Notificações | Flow', 'Comunicação e acompanhamento de avisos do sistema.', 'notificacoes/', ['notificacoes/index.php']],
        'flow-referencias' => ['Flow Referências | Improov', 'Organização de referências e materiais de apoio aos projetos.', 'FlowReferencias/', ['FlowReferencias/index.php']],
        'informacoes' => ['Informações do Usuário | Flow', 'Gerenciamento das informações e preferências da conta.', 'infos.php', ['infos.php']],
        'projeto' => ['Projeto | Flow', 'Visão integrada das etapas e dos ambientes do projeto.', 'Dashboard/obra', ['Dashboard/obra.php']],
        'planejamento-producao' => ['Planejamento de Produção | Flow', 'Planejamento das etapas e da execução da produção.', 'PlanejamentoProducao/', ['PlanejamentoProducao/index.php']],
        'briefing' => ['Briefings | Flow', 'Organização das orientações e dos requisitos de projetos.', 'Briefing/', ['Briefing/index.php']],
        'visualizador-3d' => ['Visualizador 3D | Flow', 'Visualização espacial e navegação pelos ambientes do projeto.', 'MapaPavimento/predio-3d/visualizador-glb-navegacao.php', ['MapaPavimento/predio-3d/visualizador-glb-navegacao.php']],
    ];
    $catalog = [];
    foreach ($definitions as $key => [$title, $description, $route, $files]) {
        $catalog[$key] = compact('title', 'description', 'route', 'files');
    }
    return $catalog;
}

function flow_metadata_base_path(): string
{
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    return preg_match('~^/flow/ImproovWeb(?:/|$)~', $path) ? '/flow/ImproovWeb' : '/ImproovWeb';
}

function flow_metadata_base_url(): string
{
    // Deployment configuration is trusted; request Host/Forwarded headers are not.
    $configured = rtrim(trim((string) getenv('FLOW_PUBLIC_BASE_URL')), '/');
    $parts = $configured !== '' ? parse_url($configured) : false;
    if ($parts && in_array($parts['scheme'] ?? '', ['https', 'http'], true)
        && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])
        && !isset($parts['query']) && !isset($parts['fragment'])
        && !preg_match('/[\x00-\x20\x7f]/', $configured)) {
        return $configured;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === 'improov') {
        return 'https://improov' . flow_metadata_base_path();
    }
    if ($host === 'localhost:8066') {
        return 'http://localhost:8066' . flow_metadata_base_path();
    }
    return 'https://improov.com.br/flow/ImproovWeb';
}

function flow_metadata_key_for_script(?string $filename = null): ?string
{
    $root = str_replace('\\', '/', dirname(__DIR__)) . '/';
    $file = str_replace('\\', '/', $filename ?? (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if (strncasecmp($file, $root, strlen($root)) !== 0) {
        return null;
    }
    $relative = substr($file, strlen($root));
    foreach (flow_metadata_catalog() as $key => $meta) {
        foreach ($meta['files'] as $entry) {
            if (strcasecmp($relative, $entry) === 0) {
                return $key;
            }
        }
    }
    return null;
}

function flow_metadata_public_key($key): string
{
    return is_string($key) && isset(flow_metadata_catalog()[$key]) ? $key : 'flow';
}

function flow_metadata_values(?string $key = null): array
{
    $key = flow_metadata_public_key($key ?? flow_metadata_key_for_script());
    $meta = flow_metadata_catalog()[$key];
    $base = flow_metadata_base_url();
    return [
        'key' => $key,
        'title' => $meta['title'],
        'description' => $meta['description'],
        'url' => $base . '/' . $meta['route'],
        'image' => $base . '/assets/og/flow-default.png',
        'favicon' => $base . '/assets/icons/flow-favicon.png',
    ];
}

function flow_metadata_html(?string $key = null): string
{
    $meta = array_map(static fn ($value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), flow_metadata_values($key));
    return <<<HTML
<title>{$meta['title']}</title>
    <meta name="description" content="{$meta['description']}">
    <link rel="icon" href="{$meta['favicon']}" type="image/png">
    <meta property="og:title" content="{$meta['title']}">
    <meta property="og:description" content="{$meta['description']}">
    <meta property="og:image" content="{$meta['image']}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="550">
    <meta property="og:image:height" content="275">
    <meta property="og:image:alt" content="Improov Flow — plataforma de gestão de projetos">
    <meta property="og:url" content="{$meta['url']}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Improov Flow">
    <meta property="og:locale" content="pt_BR">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{$meta['title']}">
    <meta name="twitter:description" content="{$meta['description']}">
    <meta name="twitter:image" content="{$meta['image']}">
    <meta name="twitter:image:alt" content="Improov Flow — plataforma de gestão de projetos">
HTML;
}

/** Apply to existing static HTML templates without executing their body or scripts. */
function flow_metadata_apply_to_html(string $html, ?string $key = null): string
{
    $html = preg_replace('~<title\b[^>]*>.*?</title\s*>~is', '', $html);
    $html = preg_replace('~<link\b(?=[^>]*\brel\s*=\s*[\'"][^\'"]*\bicon\b[^\'"]*[\'"])[^>]*>~is', '', $html);
    $html = preg_replace('~<meta\b(?=[^>]*\b(?:property|name)\s*=\s*[\'"](?:og:[^\'"]*|twitter:[^\'"]*|description)[\'"])[^>]*>~is', '', $html);
    return preg_replace_callback('~<head\b[^>]*>~i', static fn ($match): string => $match[0] . "\n    " . flow_metadata_html($key), $html, 1);
}

function flow_metadata_login_url(): string
{
    $key = flow_metadata_key_for_script();
    return flow_metadata_base_path() . '/login-preview.php' . ($key !== null ? '?preview=' . rawurlencode($key) : '');
}

/** Only call from an existing HTML access-denied branch; this grants no access. */
function flow_redirect_to_login(): void
{
    header('Location: ' . flow_metadata_login_url(), true, 302);
}
