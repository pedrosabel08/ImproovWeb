<?php
require_once __DIR__ . '/config/version.php';
// require_once __DIR__ . '/Contratos/access_gate.php';

// Expose session policy to frontend (no headers needed; assumes session already started in the page)
$__idleSeconds = defined('IMPROOV_SESSION_IDLE_SECONDS') ? (int) IMPROOV_SESSION_IDLE_SECONDS : 30 * 60;
$__idleWarnSeconds = defined('IMPROOV_SESSION_IDLE_WARN_SECONDS') ? (int) IMPROOV_SESSION_IDLE_WARN_SECONDS : 25 * 60;
$__absSeconds = defined('IMPROOV_SESSION_ABSOLUTE_SECONDS') ? (int) IMPROOV_SESSION_ABSOLUTE_SECONDS : 60 * 60;
$__absWarnSeconds = defined('IMPROOV_SESSION_ABSOLUTE_WARN_SECONDS') ? (int) IMPROOV_SESSION_ABSOLUTE_WARN_SECONDS : 55 * 60;
$__loginTs = isset($_SESSION['login_ts']) ? (int) $_SESSION['login_ts'] : null;

$__reqUri = $_SERVER['REQUEST_URI'] ?? '';
$__reqPath = parse_url($__reqUri, PHP_URL_PATH) ?: '';
if (preg_match('~^(/flow/ImproovWeb)(?:/|$)~i', $__reqPath, $__baseMatch)) {
    $__basePath = rtrim($__baseMatch[1], '/') . '/';
} elseif (preg_match('~^(/ImproovWeb)(?:/|$)~i', $__reqPath, $__baseMatch)) {
    $__basePath = rtrim($__baseMatch[1], '/') . '/';
} else {
    $__basePath = '/ImproovWeb/';
}
unset($__baseMatch);

if (!function_exists('improov_sidebar_url')) {
    function improov_sidebar_url(string $path = ''): string
    {
        $base = $GLOBALS['__basePath'] ?? '/ImproovWeb/';
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!isset($obras) || !is_array($obras)) {
    $obras = [];
}

if (!isset($obras_inativas) || !is_array($obras_inativas)) {
    $obras_inativas = [];
}

$__sidebarProjectLabel = $GLOBALS['improov_sidebar_project_label'] ?? 'Obras';
$__sidebarInactiveLabel = $GLOBALS['improov_sidebar_inactive_label'] ?? 'Obras inativas';
$__sidebarProjectMode = $GLOBALS['improov_sidebar_project_mode'] ?? 'producao';
$__sidebarShowInactive = isset($GLOBALS['improov_sidebar_show_inactive'])
    ? (bool) $GLOBALS['improov_sidebar_show_inactive']
    : (isset($_SESSION['nivel_acesso']) && (int) $_SESSION['nivel_acesso'] === 1);
$__sidebarAllowedObraIds = [];
foreach ($obras as $__obraPermitida) {
    if (isset($__obraPermitida['idobra'])) {
        $__sidebarAllowedObraIds[] = (int) $__obraPermitida['idobra'];
    }
}
if ($__sidebarShowInactive) {
    foreach ($obras_inativas as $__obraPermitida) {
        if (isset($__obraPermitida['idobra'])) {
            $__sidebarAllowedObraIds[] = (int) $__obraPermitida['idobra'];
        }
    }
}
$__sidebarAllowedObraIds = array_values(array_unique($__sidebarAllowedObraIds));

if (!function_exists('improov_sidebar_render_obra_item')) {
    function improov_sidebar_render_obra_item(array $obra, string $extraClass = ''): void
    {
        $id = (int) ($obra['idobra'] ?? 0);
        $nome = htmlspecialchars((string) ($obra['nomenclatura'] ?? ''), ENT_QUOTES, 'UTF-8');
        $classe = trim('obra ' . $extraClass);

        echo '<li class="' . htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') . '">';
        echo '<i class="fa fa-star favorite-icon" data-id="' . $id . '" title="' . $nome . '"></i>';
        echo '<a title="' . $nome . '" href="#" class="obra-item" data-id="' . $id . '" data-name="' . $nome . '">';
        echo '<span>' . $nome . '</span>';
        echo '<span class="sidebar-badge" data-obra-id="' . $id . '" aria-hidden="true"></span>';
        echo '</a>';
        echo '</li>';
    }
}

if (!function_exists('improov_sidebar_obras_por_pacote')) {
    function improov_sidebar_obras_por_pacote(array $obras): array
    {
        $grupos = [
            'STILL' => ['label' => 'Pacote Imagens', 'obras' => []],
            'ANIMACAO' => ['label' => 'Pacote Animação', 'obras' => []],
            'FILME' => ['label' => 'Pacote Filme', 'obras' => []],
            'SEM_PACOTE' => ['label' => 'Sem pacote ativo', 'obras' => []],
        ];

        foreach ($obras as $obra) {
            $pacotesAtivos = $obra['pacotes_ativos'] ?? '';
            $pacotes = array_filter(array_map('trim', explode(',', strtoupper((string) $pacotesAtivos))));
            $pacotesConhecidos = array_values(array_intersect($pacotes, ['STILL', 'ANIMACAO', 'FILME']));

            if (empty($pacotesConhecidos)) {
                $grupos['SEM_PACOTE']['obras'][] = $obra;
                continue;
            }

            foreach ($pacotesConhecidos as $pacote) {
                $grupos[$pacote]['obras'][] = $obra;
            }
        }

        return array_filter($grupos, function ($grupo) {
            return !empty($grupo['obras']);
        });
    }
}
?>
<script>
    // Session policy provided by PHP
    window.IMPROOV_SESSION_IDLE_MS = <?php echo json_encode($__idleSeconds * 1000); ?>;
    window.IMPROOV_SESSION_IDLE_WARN_MS = <?php echo json_encode($__idleWarnSeconds * 1000); ?>;
    window.IMPROOV_SESSION_ABSOLUTE_MS = <?php echo json_encode($__absSeconds * 1000); ?>;
    window.IMPROOV_SESSION_ABSOLUTE_WARN_MS = <?php echo json_encode($__absWarnSeconds * 1000); ?>;
    window.IMPROOV_LOGIN_TS = <?php echo json_encode($__loginTs); ?>; // seconds since epoch
    window.IMPROOV_APP_BASE = <?php echo json_encode(rtrim($__basePath, '/')); ?>;
    window.IMPROOV_ALLOWED_OBRA_IDS = <?php echo json_encode($__sidebarAllowedObraIds); ?>;
    window.IMPROOV_WS_URL = <?php
                            // Produção → WSS via reverse-proxy
                            // Local HTTP → WS direto na porta 8082
                            // Local HTTPS → WSS de produção (evita SecurityError do browser)
                            $__wsHost = $_SERVER['HTTP_HOST'] ?? 'improov.com.br';
                            $__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                                || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;
                            $__isProd  = strpos($__wsHost, 'improov.com.br') !== false;
                            if ($__isProd) {
                                echo json_encode('wss://improov.com.br/ws/');
                            } elseif ($__isHttps) {
                                // Ambiente local em HTTPS: aponta para o servidor WS de produção
                                echo json_encode('wss://improov.com.br/ws/');
                            } else {
                                echo json_encode('ws://' . $__wsHost . ':8082');
                            }
                            ?>;
</script>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="<?php echo asset_url($__basePath . 'assets/css/upload-badge.css'); ?>" />

    <title>Sidebar</title>
    <!-- Sidebar badge styles moved to css/styleSidebar.css -->
</head>

<body>
    <div class="sidebar mini">
        <!-- <button id="menuButton">
            <i class="fa-solid fa-bars"></i>
        </button> -->
        <img id="gif" src="https://improov.com.br/flow/ImproovWeb/gif/assinatura_preto.gif" alt="">
        <ul>
            <ul class="division">
                <label for="">Insights</label>
                <li><a title="Página Principal" href="https://improov.com.br/flow/ImproovWeb/inicio.php"><i
                            class="fas fa-home"></i><span> Página Principal</span></a></li>
                <li><a title="Flow Review" href="https://improov.com.br/flow/ImproovWeb/FlowReview"><i
                            class="fas fa-check"></i><span> Flow Review</span><span class="sidebar-badge" data-module="flow_review" aria-hidden="true"></span></a></li>
                <li><a title="Flow Render" href="https://improov.com.br/flow/ImproovWeb/Render"><i
                            class="fas fa-cube"></i><span> Flow Render</span><span class="sidebar-badge" data-module="render" aria-hidden="true"></span></a></li>
                <li><a title="Lista Pós-Produção" href="https://improov.com.br/flow/ImproovWeb/Pos-Producao"><i
                            class="fas fa-film"></i><span> Lista Pós-Produção</span><span class="sidebar-badge" data-module="pos_producao" aria-hidden="true"></span></a></li>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                    <li><a title="Flow Drive" href="https://improov.com.br/flow/ImproovWeb/FlowDrive"><i
                                class="fas fa-file"></i><span> Flow Drive</span></a></li>
                    <li><a title="Pré-Alteração" href="https://improov.com.br/flow/ImproovWeb/PreAlteracao"><i
                                class="fa-solid fa-magnifying-glass-chart"></i><span> Pré-Alteração</span>
                            <span class="sidebar-badge" data-module="pre_alt_analise" aria-hidden="true"></span>
                        </a></li>
                    <!-- <li><a title="Infos Cliente" href="https://improov.com.br/flow/ImproovWeb/infoCliente"><i class="fas fa-chart-line"></i><span> Infos Cliente</span></a></li> -->
                <?php endif; ?>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                    <!-- <li><a title="Lista Animação" href="https://improov.com.br/flow/ImproovWeb/Animacao"><i class="fas fa-film"></i><span> Lista Animação</span></a></li> -->
                <?php endif; ?>

                <?php if (
                    isset($_SESSION['nivel_acesso']) && (
                        $_SESSION['nivel_acesso'] == 1 ||
                        $_SESSION['nivel_acesso'] == 2 ||
                        in_array($_SESSION['idcolaborador'] ?? null, [7, 34, 37])
                    )
                ): ?>
                    <li><a title="Lista Alteração" href="https://improov.com.br/flow/ImproovWeb/Alteracao"><i
                                class="fa-solid fa-user-pen"></i><span> Lista Alteração</span></a></li>
                <?php endif; ?>
                <li><a title="TV - Produção por Função" href="https://improov.com.br/flow/ImproovWeb/TvDashboard" target="_blank"><i
                            class="fa-solid fa-tv"></i><span> TV Dashboard</span></a></li>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                    <!-- <li><a title="Obras" href="https://improov.com.br/flow/ImproovWeb/Obras"><i class="fas fa-building"></i><span> Obras</span></a></li> -->
                    <li><a title="Entregas" href="<?php echo htmlspecialchars(improov_sidebar_url('Entregas'), ENT_QUOTES, 'UTF-8'); ?>"
                            data-module-link="entregas"
                            data-default-href="<?php echo htmlspecialchars(improov_sidebar_url('Entregas'), ENT_QUOTES, 'UTF-8'); ?>"
                            data-pending-href="<?php echo htmlspecialchars(improov_sidebar_url('Entregas?pendencias=1'), ENT_QUOTES, 'UTF-8'); ?>"><i
                                class="fa-solid fa-truck-fast"></i><span> Entregas</span><span class="sidebar-badge"
                                data-module="entregas" aria-hidden="true"></span><span class="sidebar-badge sidebar-badge--warning sidebar-badge--offset"
                                data-module="entregas_pendencias" aria-hidden="true"></span></a></li>
                    <li><a title="Gestão" href="https://improov.com.br/flow/ImproovWeb/Gestao"><i class="fa-solid fa-diagram-project"></i><span> Gestão</span></a></li>
                <?php endif; ?>
            </ul>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>

                <ul class="division">
                    <label for="">Gerencial</label>
                    <li><a title="Tela Gerencial" href="https://improov.com.br/flow/ImproovWeb/TelaGerencial"><i
                                class="fa-solid fa-gauge-high"></i><span> Tela Gerencial</span></a></li>
                    <li><a title="Configurar Metas - TV" href="https://improov.com.br/flow/ImproovWeb/AdminMetas"><i
                                class="fa-solid fa-bullseye"></i><span> Metas TV</span></a></li>
                    <li><a title="Flow Track" href="https://improov.com.br/flow/ImproovWeb/FlowTrack"><i
                                class="fa-solid fa-route"></i><span> Flow Track</span></a></li>
                    <li><a title="Flow Track" href="https://improov.com.br/flow/ImproovWeb/Colaborador"><i
                                class="fa-solid fa-users"></i><span> Colaboradores</span></a></li>
                    <li><a title="Atividade do Sistema" href="https://improov.com.br/flow/ImproovWeb/Atividade"><i
                                class="fa-solid fa-chart-simple"></i><span> Atividade</span></a></li>
                    <li><a title="Dashboard" href="https://improov.com.br/flow/ImproovWeb/Dashboard"><i
                                class="fa-solid fa-chart-line"></i><span> Dashboard</span></a></li>
                    <li><a title="Dashboard Operacional" href="https://improov.com.br/flow/ImproovWeb/Dashboard/Operacional"><i
                                class="fa-solid fa-chart-area"></i><span> Dashboard Operacional</span></a></li>
                    <!-- <li><a title="Onboarding Pendente" href="https://improov.com.br/flow/ImproovWeb/Dashboard#onboarding-box"><i
                                class="fa-solid fa-clipboard-check"></i><span> Onboarding</span><span class="sidebar-badge"
                                data-module="onboarding" aria-hidden="true"></span></a></li> -->
                    <li><a title="Projetos" href="https://improov.com.br/flow/ImproovWeb/Projetos"><i
                                class="fa-solid fa-sitemap"></i><span> Projetos</span></a></li>
                    <li><a title="Quadro Produção" href="https://improov.com.br/flow/ImproovWeb/Quadro"><i
                                class="fa-solid fa-columns"></i><span>Quadro TEA</span></a></li>
                </ul>
            <?php endif; ?>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 5)): ?>

                <ul class="division">
                    <label for="">Financeiro</label>
                    <li><a title="Dashboard" href="https://improov.com.br/flow/ImproovWeb/Dashboard"><i
                                class="fa-solid fa-chart-line"></i><span> Dashboard</span></a></li>
                    <!-- <li><a title="Tela de custos" href="https://improov.com.br/flow/ImproovWeb/Custos"><i class="fa-solid fa-desktop"></i><span> Tela Custos</span></a></li> -->
                    <li><a title="Pagamento" href="https://improov.com.br/flow/ImproovWeb/Pagamento"><i
                                class="fas fa-money-bill-wave"></i><span> Pagamento</span></a></li>
                    <li><a title="Contratos" href="https://improov.com.br/flow/ImproovWeb/Contratos"><i
                                class="fa-solid fa-file-contract"></i><span> Contratos</span></a></li>

                </ul>
            <?php endif; ?>



            <ul class="division" id="favoritos">
                <label for="">Favoritos</label>
            </ul>

            <ul class="division">
                <label for="">Ferramentas</label>
                <!-- <li><a title="Filtro Colaborador" href="https://improov.com.br/flow/ImproovWeb/main.php#filtro-colab"><i class="fa-solid fa-user"></i><span>Filtro Colaborador</span></a></li> -->
                <?php if (isset($_SESSION['nivel_acesso']) && in_array($_SESSION['nivel_acesso'], [1, 2])): ?>
                    <li><a title="Mapa de Compatibilização"
                            href="https://improov.com.br/flow/ImproovWeb/MapaCompatibilizacao"><i
                                class="fa-solid fa-map"></i><span> Mapa Compatib.</span></a></li>
                    <li><a title="SIRE" href="https://improov.com.br/flow/ImproovWeb/SIRE"><i class="fa-solid fa-link"></i><span> SIRE</span></a></li>

                <?php endif; ?>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                    <!-- <li><a title="Adicionar cliente ou obra" href="https://improov.com.br/flow/ImproovWeb/main.php#add-cliente"><i class="fa-solid fa-person"></i><span>Adicionar cliente ou obra</span></a></li> -->
                    <!-- <li><a title="Gerenciar prioridades" href="https://improov.com.br/flow/ImproovWeb/Prioridade"><i class="fa-solid fa-user-plus"></i><span>Gerenciar prioridades</span></a></li> -->
                    <li><a title="Notificações" href="https://improov.com.br/flow/ImproovWeb/notificacoes"><i
                                class="fa-solid fa-bell"></i><span>Notificações</span></a></li>
                    <li><a title="Flow Referências" href="https://improov.com.br/flow/ImproovWeb/FlowReferencias"><i
                                class="fas fa-paperclip"></i><span> Flow Referências</span></a></li>

                <?php endif; ?>

            </ul>
            <ul id="obras-list" class="division">
                <label for=""><?= htmlspecialchars($__sidebarProjectLabel); ?></label>
                <?php if ($__sidebarProjectMode === 'gestao'): ?>
                    <?php foreach (improov_sidebar_obras_por_pacote($obras) as $grupoPacote): ?>
                        <li class="sidebar-package-label"><span><?= htmlspecialchars($grupoPacote['label']); ?></span></li>
                        <?php foreach ($grupoPacote['obras'] as $obra): ?>
                            <?php improov_sidebar_render_obra_item($obra); ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($obras as $obra): ?>
                        <?php improov_sidebar_render_obra_item($obra); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>

            <?php if ($__sidebarShowInactive): ?>

                <?php if (!empty($obras_inativas)): ?>
                    <ul id="obras-inativas" class="division">
                        <button class="drawer-toggle" id="obras-inativas-toggle" aria-expanded="false">
                            <span class="drawer-label"><?= htmlspecialchars($__sidebarInactiveLabel); ?></span>
                            <i class="fa-solid fa-chevron-right drawer-chevron"></i>
                        </button>
                        <div class="drawer-body" id="obras-inativas-body">
                            <?php if ($__sidebarProjectMode === 'gestao'): ?>
                                <?php foreach (improov_sidebar_obras_por_pacote($obras_inativas) as $grupoPacote): ?>
                                    <li class="sidebar-package-label"><span><?= htmlspecialchars($grupoPacote['label']); ?></span></li>
                                    <?php foreach ($grupoPacote['obras'] as $obra): ?>
                                        <?php improov_sidebar_render_obra_item($obra, 'inativa'); ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($obras_inativas as $obra): ?>
                                    <?php improov_sidebar_render_obra_item($obra, 'inativa'); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
            <ul id="obras-list" class="division">
                <label for="">Usuário</label>
                <li><a title="Informações do Usuário" href="https://improov.com.br/flow/ImproovWeb/infos.php"><i
                            class="fa-solid fa-id-card"></i><span>Informações</span></a></li>
            </ul>
        </ul>
    </div>

    <!-- Upload Badge — barra inferior fixa de progresso de upload -->
    <div id="upload-badge-bar" class="upload-badge-bar">
        <div class="upload-badge-header">
            <span class="upload-badge-header-title">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span id="upload-badge-count">0</span>&nbsp;arquivo(s) em envio
            </span>
            <button class="upload-badge-toggle" title="Minimizar / Expandir" aria-label="Minimizar">
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>
        <div class="upload-badge-list"></div>
    </div>

</body>

</html>

<script src="<?php echo asset_url($__basePath . 'assets/js/upload-ws.js'); ?>"></script>
<script src="<?php echo asset_url($__basePath . 'assets/js/upload-badge.js'); ?>"></script>
<script src="<?php echo asset_url($__basePath . 'assets/js/sidebar-counts.js'); ?>"></script>

<script>
    // Cache-busting didático:
    // - No deploy, o servidor muda APP_VERSION (cache/deploy_version.txt)
    // - Toda página (via sidebar) compara a versão do servidor com a do navegador
    // - Se mudou, força um reload “limpo” alterando a URL com um param _v
    (function() {
        try {
            if (!window.fetch || !window.localStorage || !window.sessionStorage) return;

            var storageKey = 'improov_app_version';
            var reloadGuardKey = 'improov_version_reload_guard';

            // Evita loop infinito se algo der errado
            var guard = sessionStorage.getItem(reloadGuardKey);
            if (guard === '1') return;

            // Do mesmo jeito que seu script/sidebar.js:
            // se estiver em /flow/ImproovWeb/ usa essa base, senão usa /ImproovWeb/
            var pathLower = window.location.pathname.toLowerCase();
            var flowIdx = pathLower.indexOf('/flow/improovweb');
            var rootIdx = pathLower.indexOf('/improovweb');
            var basePath = '/ImproovWeb/';
            if (flowIdx === 0) {
                basePath = window.location.pathname.slice(0, '/flow/improovweb'.length) + '/';
            } else if (rootIdx === 0) {
                basePath = window.location.pathname.slice(0, '/improovweb'.length) + '/';
            }

            var endpoint = window.location.origin + basePath + 'system_version.php';

            fetch(endpoint, {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(resp) {
                    if (!resp.ok) throw new Error('bad');
                    return resp.json();
                })
                .then(function(data) {
                    if (!data || !data.ok || !data.version) return;

                    var current = String(data.version);
                    var saved = localStorage.getItem(storageKey);

                    // Primeira vez no navegador: salva e segue
                    if (!saved) {
                        localStorage.setItem(storageKey, current);
                        return;
                    }

                    // Se mudou: atualiza e força reload
                    if (saved !== current) {
                        localStorage.setItem(storageKey, current);
                        sessionStorage.setItem(reloadGuardKey, '1');

                        var url = new URL(window.location.href);
                        url.searchParams.set('_v', current);
                        window.location.replace(url.toString());
                    }
                })
                .catch(function() {
                    // Se falhar, não quebra a navegação
                });
        } catch (e) {
            // noop
        }
    })();
</script>

<script>
    // Envia o title e url ao servidor para registrar histórico com título amigável
    (function() {
        try {
            if (!window.fetch) return;
            const payload = new FormData();
            payload.append('title', document.title || 'Página');
            payload.append('url', window.location.href);

            // envia de forma assíncrona; o endpoint ignora se usuário não autenticado
            // usa o caminho absoluto para evitar requests relativos incorretos
            const pathLower = window.location.pathname.toLowerCase();
            const flowIdx = pathLower.indexOf('/flow/improovweb');
            const rootIdx = pathLower.indexOf('/improovweb');
            let basePath = '/ImproovWeb/';
            if (flowIdx === 0) {
                basePath = window.location.pathname.slice(0, '/flow/improovweb'.length) + '/';
            } else if (rootIdx === 0) {
                basePath = window.location.pathname.slice(0, '/improovweb'.length) + '/';
            }
            const endpoint = window.location.origin + basePath + 'atualiza_log_tela.php';
            fetch(endpoint, {
                method: 'POST',
                body: payload,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(resp => resp.json().then(js => {
                if (!js.ok) console.debug('atualiza_log_tela response:', js);
            })).catch(err => {
                console.debug('atualiza_log_tela fetch error', err);
            });
        } catch (e) {
            // noop
        }
    })();
</script>
