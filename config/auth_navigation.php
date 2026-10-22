<?php
/** Post-login navigation only. This never authenticates or grants permissions. */
require_once __DIR__ . '/page_metadata.php';

function flow_auth_page_files(): array
{
    // Only document entry points: never actions, APIs, downloads or public token flows.
    return [
        'inicio.php', 'inicio2.php', 'main.php', 'infos.php',
        'PaginaPrincipal/Home/index.php', 'PaginaPrincipal/Overview/index.php',
        'ALMA/index.php', 'ALMA/admin.php', 'AdminMetas/index.php',
        'Alteracao/index.php', 'Atividade/index.php', 'Animacao/index.php',
        'Arquitetura/index.php', 'Briefing/index.php', 'Colaborador/index.php',
        'Contratos/index.php', 'ControleComercial/index.php', 'Custos/index.php',
        'Dashboard/index.php', 'Dashboard/obra.php', 'Dashboard/obra2.php',
        'Dashboard/projetos.php', 'Dashboard/Operacional/index.php',
        'Entregas/index.php', 'FlowBlock/index.php', 'FlowBlock/issue.php',
        'FlowDrive/index.php', 'FlowReferencias/index.php', 'FlowReview/index.php',
        'FlowTrack/index.php', 'FlowTrack/Radar/index.php', 'FlowTrack/Report/index.php',
        'Fotografico/index.php', 'GANTT/index.php', 'GANTT/gantt.php', 'GANTT/teste.php',
        'Gestao/index.php', 'Imagens/index.php', 'MapaCompatibilizacao/index.php',
        'MapaPavimento/index.php', 'MapaPavimento/predio-3d/visualizador-glb-navegacao.php',
        'Metas/index.php', 'notificacoes/index.php', 'Obras/index.php',
        'Pagamento/index.php', 'Pagamento/fechamento.php', 'PlanejamentoCapacidade/index.php',
        'PlanejamentoProducao/index.php', 'Planner/index.php', 'Pos-Producao/index.php',
        'Pos-Producao/index2.php', 'PreAlteracao/index.php', 'PreAlteracao/planejamento.php',
        'Prioridade/index.php', 'Projetos/index.php', 'Quadro/index.php',
        'Relatorio/index.php', 'Render/index.php',
        'review/index.php', 'ScriptArquivos/index.php', 'SIRE/index.php',
        'TelaGerencial/index.php', 'TimelineObraTeste/index.php', 'TvDashboard/admin.php',
    ];
}

function flow_auth_routes(): array
{
    static $routes;
    if ($routes !== null) return $routes;
    $routes = [];
    foreach (flow_auth_page_files() as $file) {
        $routes[$file] = $file;
        if (str_ends_with($file, '/index.php')) {
            $directory = substr($file, 0, -strlen('index.php'));
            $routes[$directory] = $file;
            $routes[rtrim($directory, '/')] = $file;
        }
    }
    // Existing Apache/Nginx alias, not arbitrary extensionless PHP resolution.
    $routes['Dashboard/obra'] = 'Dashboard/obra.php';
    return $routes;
}

function flow_auth_navigation_script(): string
{
    $url = flow_metadata_base_path() . '/script/authNavigation.js?v=' . filemtime(__DIR__ . '/../script/authNavigation.js');
    return '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>';
}

function flow_auth_validate_return($url): ?string
{
    if (!is_string($url) || $url === '' || strlen($url) > 8192 || preg_match('/[\x00-\x20\x7f]/', $url)) return null;
    // Decode repeatedly to detect encoded control characters without rewriting the query.
    $decoded = $url;
    for ($i = 0; $i < 8; $i++) {
        if (preg_match('/[\x00-\x1f\x7f]/', $decoded)) return null;
        $next = rawurldecode($decoded);
        if ($next === $decoded) break;
        $decoded = $next;
    }
    if ($i === 8 || preg_match('/[\x00-\x1f\x7f]/', $decoded)) return null;
    // Permit encoded spaces in query values, but no fragments or authority/scheme syntax.
    $parts = parse_url($url);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
        || isset($parts['fragment'])) return null;
    $path = $parts['path'] ?? '';
    if (!preg_match('~^/[A-Za-z0-9_./-]+$~D', $path)
        || str_contains($path, '//') || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)) return null;
    $prefix = flow_metadata_base_path() . '/';
    if (!str_starts_with($path, $prefix)) return null;
    $relative = substr($path, strlen($prefix));
    $file = flow_auth_routes()[$relative] ?? null;
    if ($file === null) return null;
    $root = realpath(dirname(__DIR__));
    $resolved = realpath($root . '/' . $file);
    if ($resolved === false || !is_file($resolved)
        || !str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', $root) . '/')) return null;
    return $url;
}

function flow_auth_current_page(): bool
{
    $root = str_replace('\\', '/', (string)realpath(dirname(__DIR__))) . '/';
    $script = str_replace('\\', '/', (string)realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')));
    return str_starts_with($script, $root)
        && in_array(substr($script, strlen($root)), flow_auth_page_files(), true);
}

function flow_auth_request_return_url(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $parts = explode('?', $uri, 2);
    if (!isset($parts[1])) return $uri;
    $query = array_filter(explode('&', $parts[1]), static fn ($part) => !str_starts_with($part, '_flow_auth_resume='));
    $query = implode('&', $query);
    return $parts[0] . ($query !== '' ? '?' . $query : '');
}

function flow_auth_pending_returns(): array
{
    $pending = $_SESSION['_flow_login_returns'] ?? [];
    if (!is_array($pending)) $pending = [];
    $now = time();
    foreach ($pending as $id => $entry) {
        if (!is_array($entry) || !is_int($entry['expires'] ?? null) || $entry['expires'] < $now
            || !preg_match('/^[a-f0-9]{32}$/D', (string)$id)) unset($pending[$id]);
    }
    return $pending;
}

function flow_auth_store_return(string $url): ?string
{
    $url = flow_auth_validate_return($url);
    if ($url === null) return null;
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_id('');
        if (!session_start()) return null;
    }
    $pending = flow_auth_pending_returns();
    while (count($pending) >= 8) array_shift($pending);
    $id = bin2hex(random_bytes(16));
    $pending[$id] = ['url' => $url, 'expires' => time() + 1800];
    $_SESSION['_flow_login_returns'] = $pending;
    return $id;
}

function flow_auth_consume_return($id): ?string
{
    $pending = flow_auth_pending_returns();
    $entry = is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) ? ($pending[$id] ?? null) : null;
    if (is_string($id)) unset($pending[$id]);
    $_SESSION['_flow_login_returns'] = $pending;
    return $entry ? flow_auth_validate_return($entry['url'] ?? null) : null;
}

function flow_auth_unauthorized(string $message = 'Sessão inexistente ou expirada.'): void
{
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['ok' => false, 'code' => 'AUTH_REQUIRED', 'message' => $message]);
    exit;
}

/** Call only from an existing authentication denial. Keep all authorization checks. */
function flow_auth_redirect_to_login(): void
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true) || improov_is_json_request() || !flow_auth_current_page()) {
        flow_auth_unauthorized();
    }
    $context = flow_auth_store_return(flow_auth_request_return_url());
    $url = flow_metadata_login_url();
    if ($context !== null) $url .= (str_contains($url, '?') ? '&' : '?') . 'login_context=' . $context;
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Location: ' . $url, true, 302);
}
