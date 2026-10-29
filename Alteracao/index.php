<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    flow_auth_redirect_to_login();
    exit();
}

require_once __DIR__ . '/../includes/flow-motion-assets.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

// session_start();
$nome_usuario = $_SESSION['nome_usuario'] ?? '';

require_once __DIR__ . '/../conexaoMain.php';

$conn = conectarBanco();

$obras = obterObras($conn); // Also supplies the existing sidebar's permitted projects/context.
$colaboradores = obterColaboradores($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <?= flow_auth_navigation_script() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        // Share the preference already used by Planejamento; otherwise follow the OS.
        try {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light' || savedTheme === 'dark') document.documentElement.dataset.theme = savedTheme;
        } catch (_) {}
    </script>
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css') . '&altconf=20260629-approval-upload'; ?>">
    <link rel="stylesheet" href="<?php echo asset_url('planner.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <?php require __DIR__ . '/../includes/page-head.php'; ?>
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">

        <header class="page-header">
            <div class="page-header-left">
                <span class="planner-title-icon" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span>
                <div><h1 class="page-title">Alterações</h1><p class="planner-subtitle" id="planner-subtitle">Agenda operacional da semana</p></div>
            </div>
            <span class="results-badge"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span id="resultsCount">0</span> itens</span>
        </header>

        <section class="filters planner-filters" id="filtros-alteracao" aria-label="Filtros das alterações">
            <div class="planner-filter-fields">
                <div class="filter-group"><label class="filter-label" for="filtro-obra">Obra</label><select class="filter-select" id="filtro-obra"><option value="">Todas as obras</option></select></div>
                <div class="filter-group"><label class="filter-label" for="filtro-colaborador">Colaborador</label><select class="filter-select" id="filtro-colaborador"><option value="">Todos</option><option value="sem">Sem responsável</option></select></div>
                <div class="filter-group"><label class="filter-label" for="filtro-status-imagem">Etapa</label><select class="filter-select" id="filtro-status-imagem"><option value="">Todas</option></select></div>
                <div class="filter-group"><label class="filter-label" for="filtro-nivel">Nível</label><select class="filter-select" id="filtro-nivel"><option value="">Todos</option><option value="1">N1</option><option value="2">N2</option><option value="3">N3</option><option value="4">N4</option><option value="5">N5</option><option value="sem">Sem nível</option></select></div>
                <div class="filter-group"><label class="filter-label" for="filtro-status">Status</label><select class="filter-select" id="filtro-status"><option value="">Todos</option><option>Não iniciado</option><option>HOLD</option><option>Em andamento</option><option>Em aprovação</option><option>Ajuste</option><option>Aprovado com ajustes</option><option value="concluidas">Concluídas</option><option>Finalizado</option><option>Aprovado</option></select></div>
                <div class="filter-search"><label class="filter-label" for="filtro-busca">Buscar</label><div class="input-wrap"><i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i><input type="search" class="filter-input" id="filtro-busca" placeholder="Imagem, obra, etapa ou colaborador..."></div></div>
            </div>
            <div class="planner-filter-footer">
                <div class="planner-week-nav" aria-label="Navegação por semana">
                    <button type="button" class="planner-icon-btn" id="semana-anterior" aria-label="Semana anterior"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    <label class="planner-week-picker"><i class="fa-regular fa-calendar" aria-hidden="true"></i><span id="semana-label">Semana atual</span><input type="date" id="filtro-semana" aria-label="Escolher semana"></label>
                    <button type="button" class="planner-icon-btn" id="semana-proxima" aria-label="Próxima semana"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                    <button type="button" class="planner-text-btn" id="semana-atual">Semana atual</button>
                </div>
                <div class="planner-filter-actions"><label class="planner-check"><input type="checkbox" id="ocultar-concluidas"> Ocultar concluídas</label><button type="button" class="planner-text-btn" id="btn-limpar-filtros">Limpar filtros</button><a class="planner-edit" id="editar-planejamento" href="../Entregas/"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar planejamento</a></div>
            </div>
        </section>

        <div class="planner-view-bar">
            <div class="planner-tabs" role="tablist" aria-label="Fila operacional">
                <button type="button" role="tab" id="tab-alteracoes" aria-selected="true" aria-controls="planner-content">Alterações <span id="planned-count">0</span></button>
                <button type="button" role="tab" id="tab-render" aria-selected="false" aria-controls="render-content">Render em Alta <span id="render-count">0</span></button>
            </div>
            <button type="button" class="planner-backlog-toggle" id="toggle-unplanned" aria-expanded="false" aria-controls="unplanned-panel"><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i> Sem planejamento <span id="unplanned-count">0</span></button>
        </div>
        <div id="planner-feedback" role="status" aria-live="polite" hidden></div>
        <div id="planner-loading" role="status" hidden><canvas data-thinking-orb data-orb-state="composing" data-orb-size="42" data-orb-theme="auto" aria-label="Carregando alterações"></canvas><span>Carregando alterações…</span></div>
        <section id="planner-content" class="planner-content" role="tabpanel" aria-labelledby="tab-alteracoes">
            <aside id="unplanned-panel" class="planner-unplanned" aria-labelledby="unplanned-title" hidden>
                <div class="planner-panel-heading"><h2 id="unplanned-title">Sem planejamento</h2><button type="button" class="planner-icon-btn" id="close-unplanned" aria-label="Fechar sem planejamento">×</button></div>
                <p class="planner-panel-help">Itens sem dia confirmado. O prazo legado não define o dia de execução.</p>
                <div id="unplanned-cards" class="planner-card-list"></div>
            </aside>
            <div class="planner-week-space">
                <div class="planner-mobile-nav"><button type="button" class="planner-icon-btn" id="dia-anterior" aria-label="Dia anterior">‹</button><span id="dia-label"></span><button type="button" class="planner-icon-btn" id="dia-proximo" aria-label="Próximo dia">›</button></div>
                <div class="planner-board" id="planner-board" aria-label="Agenda semanal de alterações" tabindex="0"></div>
                <div id="planner-exceptional" hidden></div>
            </div>
        </section>
        <section id="render-content" class="planner-render" role="tabpanel" aria-labelledby="tab-render" hidden><p class="planner-panel-help">Render em Alta mantém seu fluxo operacional e não utiliza os dias do planner de alterações.</p><div id="render-cards" class="planner-render-grid"></div></section>
    </div><!-- /.container -->

    <!-- ===== EDIT MODAL ===== -->
    <div id="myModal" class="modal">
        <div class="modal-content">

            <div class="modal-header">
                <div class="modal-header-left">
                    <span class="modal-title" id="campoNomeImagem">—</span>
                    <span class="modal-subtitle">Alteração</span>
                </div>
                <button class="modal-close" id="closeModal" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="imagem_id">

                <div class="modal-field-group">
                    <label class="filter-label">Colaborador</label>
                    <select class="filter-select modal-select" id="opcao_alteracao">
                        <option value="">Ninguém</option>
                        <?php foreach ($colaboradores as $colab): ?>
                            <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                                <?= htmlspecialchars($colab['nome_colaborador']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-field-group">
                    <label class="filter-label">Status da Alteração</label>
                    <select class="filter-select modal-select" id="status_alteracao">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Finalizado">Finalizado</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Não se aplica">Não se aplica</option>
                        <option value="Em aprovação">Em aprovação</option>
                        <option value="Aprovado">Aprovado</option>
                        <option value="Ajuste">Ajuste</option>
                        <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                    </select>
                </div>

                <div class="modal-field-group">
                    <label class="filter-label">Prazo</label>
                    <input type="date" class="filter-select modal-select" id="prazo_alteracao">
                </div>

                <div class="modal-field-group">
                    <label class="filter-label">Caminho do Arquivo</label>
                    <input type="text" class="filter-input modal-input" id="obs_alteracao" placeholder="Caminho arquivo">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-action btn-secundario" id="closeModalBtn">Fechar</button>
                <button type="button" class="btn-action btn-primario" id="salvar_funcoes">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>

        </div>
    </div>

    <!-- Sino de notificações -->
    <div id="notificacao-sino" class="notificacao-sino">
        <i class="fas fa-bell sino" id="icone-sino"></i>
        <span id="contador-tarefas" class="contador-tarefas">0</span>
    </div>

    <!-- Popover unificado -->
    <div id="popover-tarefas" class="popover oculto">
        <div class="secao">
            <div class="secao-titulo secao-tarefas" onclick="toggleSecao('tarefas')">
                <strong>Tarefas</strong>
                <span id="badge-tarefas" class="badge-interna"></span>
            </div>
            <div id="conteudo-tarefas" class="secao-conteudo"></div>
        </div>
        <div class="secao">
            <div class="secao-titulo secao-notificacoes">
                <strong>Notificações</strong>
                <span id="badge-notificacoes" class="badge-interna"></span>
            </div>
            <div id="conteudo-notificacoes" class="secao-conteudo"></div>
        </div>
        <button id="btn-ir-revisao">Ir para Revisão</button>
    </div>

    <!-- Sessão Expirada -->
    <div id="modalSessao" class="modal-sessao">
        <div class="modal-conteudo">
            <h2>Sessão Expirada</h2>
            <p>Sua sessão expirou. Deseja continuar?</p>
            <button onclick="renovarSessao()">Continuar Sessão</button>
            <button onclick="sair()">Sair</button>
        </div>
    </div>

    <?php flow_motion_assets('../'); ?>
    <script src="<?php echo asset_url('../assets/js/thinking-orbs.js'); ?>"></script>
    <script src="<?php echo asset_url('planner.js'); ?>"></script>
    <script src="<?php echo asset_url('script.js') . '&altconf=20261029-planner'; ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/notificacoes.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>&authReturn=1"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>window.FlowMotion && window.FlowMotion.init();</script>
    <script>
        window.ALTERACAO_LOGGED_COLAB_ID = <?= json_encode($_SESSION['idcolaborador'] ?? null); ?>;
    </script>

</body>

</html>
