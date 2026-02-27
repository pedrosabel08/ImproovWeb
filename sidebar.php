<?php
require_once __DIR__ . '/config/version.php';
// require_once __DIR__ . '/Contratos/access_gate.php';

// Expose session policy to frontend (no headers needed; assumes session already started in the page)
$__idleSeconds = defined('IMPROOV_SESSION_IDLE_SECONDS') ? (int)IMPROOV_SESSION_IDLE_SECONDS : 30 * 60;
$__idleWarnSeconds = defined('IMPROOV_SESSION_IDLE_WARN_SECONDS') ? (int)IMPROOV_SESSION_IDLE_WARN_SECONDS : 25 * 60;
$__absSeconds = defined('IMPROOV_SESSION_ABSOLUTE_SECONDS') ? (int)IMPROOV_SESSION_ABSOLUTE_SECONDS : 60 * 60;
$__absWarnSeconds = defined('IMPROOV_SESSION_ABSOLUTE_WARN_SECONDS') ? (int)IMPROOV_SESSION_ABSOLUTE_WARN_SECONDS : 55 * 60;
$__loginTs = isset($_SESSION['login_ts']) ? (int)$_SESSION['login_ts'] : null;

$__reqUri = $_SERVER['REQUEST_URI'] ?? '';
$__basePath = (strpos($__reqUri, '/flow/ImproovWeb/') !== false || preg_match('~^/flow/ImproovWeb(?:/|$)~', $__reqUri))
    ? '/flow/ImproovWeb/'
    : '/ImproovWeb/';
?>
<script>
    // Session policy provided by PHP
    window.IMPROOV_SESSION_IDLE_MS = <?php echo json_encode($__idleSeconds * 1000); ?>;
    window.IMPROOV_SESSION_IDLE_WARN_MS = <?php echo json_encode($__idleWarnSeconds * 1000); ?>;
    window.IMPROOV_SESSION_ABSOLUTE_MS = <?php echo json_encode($__absSeconds * 1000); ?>;
    window.IMPROOV_SESSION_ABSOLUTE_WARN_MS = <?php echo json_encode($__absWarnSeconds * 1000); ?>;
    window.IMPROOV_LOGIN_TS = <?php echo json_encode($__loginTs); ?>; // seconds since epoch
    window.IMPROOV_APP_BASE = <?php echo json_encode(rtrim($__basePath, '/')); ?>;
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

    <title>Sidebar</title>
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
                <li><a title="Página Principal" href="https://improov.com.br/flow/ImproovWeb/inicio.php"><i class="fas fa-home"></i><span> Página Principal</span></a></li>
                <li><a title="Lista Pós-Produção" href="https://improov.com.br/flow/ImproovWeb/Pos-Producao"><i class="fas fa-list"></i><span> Lista Pós-Produção</span></a></li>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                    <li><a title="Recebimento de arquivos" href="https://improov.com.br/flow/ImproovWeb/Arquivos"><i class="fas fa-file"></i><span> Arquivos</span></a></li>
                    <!-- <li><a title="Infos Cliente" href="https://improov.com.br/flow/ImproovWeb/infoCliente"><i class="fas fa-chart-line"></i><span> Infos Cliente</span></a></li> -->
                <?php endif; ?>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                    <!-- <li><a title="Lista Animação" href="https://improov.com.br/flow/ImproovWeb/Animacao"><i class="fas fa-film"></i><span> Lista Animação</span></a></li> -->
                <?php endif; ?>

                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                    <li><a title="Lista Alteração" href="https://improov.com.br/flow/ImproovWeb/Alteracao"><i class="fa-solid fa-user-pen"></i><span> Lista Alteração</span></a></li>
                    <!-- <li><a title="Obras" href="https://improov.com.br/flow/ImproovWeb/Obras"><i class="fas fa-building"></i><span> Obras</span></a></li> -->
                    <li><a title="Dashboard" href="https://improov.com.br/flow/ImproovWeb/Dashboard"><i class="fa-solid fa-chart-line"></i><span> Dashboard</span></a></li>
                    <li><a title="Projetos" href="https://improov.com.br/flow/ImproovWeb/Projetos"><i class="fa-solid fa-diagram-project"></i><span> Projetos</span></a></li>
                    <li><a title="Entregas" href="https://improov.com.br/flow/ImproovWeb/Entregas"><i class="fa-solid fa-diagram-project"></i><span> Entregas</span></a></li>
                    <li><a title="Gestão" href="https://improov.com.br/flow/ImproovWeb/Gestao"><i class="fa-solid fa-diagram-project"></i><span> Gestão</span></a></li>
                    <li><a title="Flow Referências" href="https://improov.com.br/flow/ImproovWeb/FlowReferencias"><i class="fas fa-paperclip"></i><span> Flow Referências</span></a></li>
                <?php endif; ?>
            </ul>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 5)): ?>

                <ul class="division">
                    <label for="">Gerencial</label>
                    <li><a title="Tela Gerencial" href="https://improov.com.br/flow/ImproovWeb/TelaGerencial"><i class="fa-solid fa-desktop"></i><span> Tela Gerencial</span></a></li>
                    <li><a title="Flow Track" href="https://improov.com.br/flow/ImproovWeb/FlowTrack"><i class="fa-solid fa-desktop"></i><span> Flow Track</span></a></li>
                    <li><a title="Flow Track" href="https://improov.com.br/flow/ImproovWeb/Colaborador"><i class="fa-solid fa-user"></i><span> Colaboradores</span></a></li>
                </ul>
            <?php endif; ?>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 5)): ?>

                <ul class="division">
                    <label for="">Financeiro</label>
                    <li><a title="Tela de custos" href="https://improov.com.br/flow/ImproovWeb/Custos"><i class="fa-solid fa-desktop"></i><span> Tela Custos</span></a></li>
                    <li><a title="Pagamento" href="https://improov.com.br/flow/ImproovWeb/Pagamento"><i class="fas fa-money-bill-wave"></i><span> Pagamento</span></a></li>
                    <li><a title="Contratos" href="https://improov.com.br/flow/ImproovWeb/Contratos"><i class="fa-solid fa-file-contract"></i><span> Contratos</span></a></li>

                </ul>
            <?php endif; ?>



            <ul class="division" id="favoritos">
                <label for="">Favoritos</label>
            </ul>

            <ul class="division">
                <label for="">Ferramentas</label>
                <!-- <li><a title="Filtro Colaborador" href="https://improov.com.br/flow/ImproovWeb/main.php#filtro-colab"><i class="fa-solid fa-user"></i><span>Filtro Colaborador</span></a></li> -->
                <li><a title="Lista Render" href="https://improov.com.br/flow/ImproovWeb/Render"><i class="fas fa-list"></i><span> Lista Render</span></a></li>
                <li><a title="Flow Review" href="https://improov.com.br/flow/ImproovWeb/FlowReview"><i class="fas fa-check"></i><span> Flow Review</span></a></li>
                <?php if (isset($_SESSION['nivel_acesso']) && in_array($_SESSION['nivel_acesso'], [1, 2])): ?>
                    <li><a title="Mapa de Compatibilização" href="https://improov.com.br/flow/ImproovWeb/MapaCompatibilizacao"><i class="fa-solid fa-map"></i><span> Mapa Compatib.</span></a></li>
                <?php endif; ?>
                <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                    <li><a title="Adicionar cliente ou obra" href="https://improov.com.br/flow/ImproovWeb/main.php#add-cliente"><i class="fa-solid fa-person"></i><span>Adicionar cliente ou obra</span></a></li>
                    <li><a title="Gerenciar prioridades" href="https://improov.com.br/flow/ImproovWeb/Prioridade"><i class="fa-solid fa-user-plus"></i><span>Gerenciar prioridades</span></a></li>
                    <li><a title="Quadro TEA" href="https://improov.com.br/flow/ImproovWeb/Quadro"><i class="fa-solid fa-columns"></i><span>Quadro TEA</span></a></li>
                    <li><a title="Notificações" href="https://improov.com.br/flow/ImproovWeb/notificacoes"><i class="fa-solid fa-bell"></i><span>Notificações</span></a></li>

                <?php endif; ?>

            </ul>
            <ul id="obras-list" class="division">
                <label for="">Obras</label>
                <?php foreach ($obras as $obra): ?>
                    <li class="obra">
                        <i class="fa fa-star favorite-icon" data-id="<?= $obra['idobra']; ?>" title="<?= htmlspecialchars($obra['nomenclatura']); ?>"></i>
                        <a title="<?= htmlspecialchars($obra['nomenclatura']); ?>" href="#" class="obra-item" data-id="<?= $obra['idobra']; ?>" data-name="<?= htmlspecialchars($obra['nomenclatura']); ?>">
                            <span><?= htmlspecialchars($obra['nomenclatura']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>

                <?php if (!empty($obras_inativas)): ?>
                    <ul id="obras-inativas" class="division">
                        <label for="">Obras inativas</label>
                        <?php foreach ($obras_inativas as $obra): ?>
                            <li class="obra inativa">
                                <i class="fa fa-star favorite-icon" data-id="<?= $obra['idobra']; ?>" title="<?= htmlspecialchars($obra['nomenclatura']); ?>"></i>
                                <a title="<?= htmlspecialchars($obra['nomenclatura']); ?>" href="#" class="obra-item" data-id="<?= $obra['idobra']; ?>" data-name="<?= htmlspecialchars($obra['nomenclatura']); ?>">
                                    <span><?= htmlspecialchars($obra['nomenclatura']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
            <ul id="obras-list" class="division">
                <label for="">Usuário</label>
                <li><a title="Informações do Usuário" href="https://improov.com.br/flow/ImproovWeb/infos.php"><i class="fa-solid fa-user"></i><span>Informações</span></a></li>
            </ul>
        </ul>
    </div>
</body>

</html>

<script src="<?php echo asset_url($__basePath . 'assets/js/upload-ws.js'); ?>"></script>

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
            var basePath = (window.location.pathname.includes('/flow/ImproovWeb/') || window.location.pathname.includes('/flow/ImproovWeb')) ?
                '/flow/ImproovWeb/' :
                '/ImproovWeb/';

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
            const basePath = (window.location.pathname.includes('/flow/ImproovWeb/') || window.location.pathname.includes('/flow/ImproovWeb')) ?
                '/flow/ImproovWeb/' :
                '/ImproovWeb/';
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