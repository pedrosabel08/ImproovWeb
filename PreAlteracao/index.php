<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

include '../conexaoMain.php';
$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css') . '&m=' . rawurlencode((string) filemtime(__DIR__ . '/style.css')); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.2.0/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <title>Pré-Alteração</title>
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <main class="prealt-shell">
        <header class="prealt-header">
            <div class="prealt-title-block">
                <h1>Pré-Alteração</h1>
                <p>Central de triagem das imagens antes do processo de alterações.</p>
            </div>
            <div class="prealt-header-actions">
                <button type="button" class="btn btn-secondary" id="btnAtualizar">
                    <i class="fa-solid fa-rotate-right"></i>
                    <span>Atualizar</span>
                </button>
                <button type="button" class="btn btn-secondary" id="btnBatchMode">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Atualização em lote</span>
                </button>
                <!-- <button type="button" class="btn btn-primary" id="btnNovoLote">
                    <i class="fa-solid fa-plus"></i>
                    <span>Novo lote</span>
                </button> -->
            </div>
        </header>

        <section class="kpi-grid" id="kpiGrid" aria-label="Indicadores de pré-alteração"></section>

        <section class="filter-bar" aria-label="Filtros">
            <label class="filter-control filter-search">
                <span>Busca</span>
                <div class="filter-input-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="filtroBusca" type="search" placeholder="Obra, cliente, responsável ou etapa">
                </div>
            </label>

            <label class="filter-control">
                <span>Obra</span>
                <select id="filtroObra">
                    <option value="">Todas</option>
                </select>
            </label>

            <label class="filter-control">
                <span>Cliente</span>
                <select id="filtroCliente">
                    <option value="">Todos</option>
                </select>
            </label>

            <label class="filter-control">
                <span>Status</span>
                <select id="filtroStatus">
                    <option value="">Todos</option>
                    <option value="EM_TRIAGEM">Em triagem</option>
                    <option value="AGUARDANDO_CLIENTE">Aguardando cliente</option>
                    <option value="PRONTO_PLANEJAMENTO">Para planejamento</option>
                </select>
            </label>

            <label class="filter-control">
                <span>Prioridade</span>
                <select id="filtroPrioridade">
                    <option value="">Todas</option>
                    <option value="BAIXA">Baixa</option>
                    <option value="NORMAL">Normal</option>
                    <option value="ALTA">Alta</option>
                    <option value="CRITICA">Crítica</option>
                </select>
            </label>

            <label class="filter-control filter-advanced">
                <span>Responsável</span>
                <select id="filtroResponsavel">
                    <option value="">Todos</option>
                </select>
            </label>

            <label class="filter-control filter-advanced">
                <span>Prazo</span>
                <select id="filtroPrazo">
                    <option value="">Todos</option>
                    <option value="ATRASADO">Atrasado</option>
                    <option value="HOJE">Hoje</option>
                    <option value="7_DIAS">Próximos 7 dias</option>
                    <option value="SEM_PRAZO">Sem prazo</option>
                </select>
            </label>

            <label class="filter-control filter-advanced">
                <span>Resolvido em</span>
                <input id="filtroData" type="date">
            </label>

            <button type="button" class="btn btn-ghost" id="btnMaisFiltros">
                <i class="fa-solid fa-sliders"></i>
                <span>Mais filtros</span>
            </button>
        </section>

        <section class="batch-action-bar" id="batchActionBar" aria-live="polite">
            <strong><span id="selectedCount">0</span> lotes selecionados</strong>
            <button type="button" data-batch-action="responsavel"><i class="fa-solid fa-user-pen"></i> Alterar responsável</button>
            <button type="button" data-batch-action="prazo"><i class="fa-solid fa-calendar-days"></i> Alterar prazo</button>
            <button type="button" data-batch-action="prioridade"><i class="fa-solid fa-flag"></i> Alterar prioridade</button>
            <button type="button" data-batch-action="status"><i class="fa-solid fa-arrow-right-arrow-left"></i> Mover etapa</button>
            <button type="button" data-batch-action="concluir"><i class="fa-solid fa-circle-check"></i> Concluir triagem</button>
            <button type="button" class="batch-clear" id="btnLimparSelecao"><i class="fa-solid fa-xmark"></i> Remover seleção</button>
        </section>

        <section class="kanban-board" id="kanbanBoard" aria-label="Kanban de pré-alteração">
            <article class="kanban-column" data-column="triagem">
                <header class="kanban-column-header">
                    <div>
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                        <strong>Em triagem</strong>
                    </div>
                    <span class="column-badge" id="countTriagem">0 lotes</span>
                    <small id="imagesTriagem">0 imagens</small>
                </header>
                <div class="kanban-column-body" id="colTriagem"></div>
            </article>

            <article class="kanban-column" data-column="aguardando">
                <header class="kanban-column-header">
                    <div>
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <strong>Aguardando cliente</strong>
                    </div>
                    <span class="column-badge column-badge-client" id="countAguardando">0 lotes</span>
                    <small id="imagesAguardando">0 imagens</small>
                </header>
                <div class="kanban-column-body" id="colAguardando"></div>
            </article>

            <article class="kanban-column" data-column="planejamento">
                <header class="kanban-column-header">
                    <div>
                        <i class="fa-solid fa-calendar-check"></i>
                        <strong>Para planejamento</strong>
                    </div>
                    <span class="column-badge column-badge-plan" id="countPlanejamento">0 lotes</span>
                    <small id="imagesPlanejamento">0 imagens</small>
                </header>
                <div class="kanban-column-body" id="colPlanejamento"></div>
            </article>
        </section>
    </main>

    <div class="modal-pa" id="paModal" aria-hidden="true">
        <div class="modal-pa-content">
            <div class="modal-pa-header">
                <div class="modal-pa-header-info">
                    <h2 class="modal-pa-title" id="paModalTitle">Carregando</h2>
                    <div class="modal-pa-badges" id="paModalBadges"></div>
                </div>
                <div class="modal-header-actions" id="paModalActions"></div>
                <button class="modal-pa-close" id="paModalClose" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-pa-body" id="paModalBody"></div>
            <div class="modal-pa-footer" id="paModalFooter">
                <span id="paPendingCount">0 imagem(ns) com alterações pendentes</span>
                <div>
                    <button type="button" class="btn btn-secondary" id="paFooterClose">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarAlteracoes" disabled>
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Salvar alterações</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="batch-modal" id="batchModal" aria-hidden="true">
        <form class="batch-modal-card" id="batchForm">
            <header>
                <h3 id="batchModalTitle">Atualização em lote</h3>
                <button type="button" id="batchModalClose" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>
            <div class="batch-modal-body" id="batchModalBody"></div>
            <footer>
                <button type="button" class="btn btn-secondary" id="batchCancel">Cancelar</button>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </footer>
        </form>
    </div>

    <div class="conclusao-modal" id="conclusaoModal" aria-hidden="true">
        <form class="conclusao-card" id="conclusaoForm">
            <header>
                <div>
                    <span>Concluir triagem</span>
                    <h3 id="conclusaoTitle">Resumo do lote</h3>
                </div>
                <button type="button" id="conclusaoModalClose" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>
            <div class="conclusao-body" id="conclusaoBody"></div>
            <footer>
                <span id="conclusaoFooterInfo">Revise os prazos antes de liberar.</span>
                <div>
                    <button type="button" class="btn btn-secondary" id="conclusaoCancel">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="conclusaoSubmit">
                        <i class="fa-solid fa-circle-check"></i>
                        Concluir e liberar lote
                    </button>
                </div>
            </footer>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://unpkg.com/tabulator-tables@6.2.0/dist/js/tabulator.min.js"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
    <script src="<?php echo asset_url('script.js') . '&m=' . rawurlencode((string) filemtime(__DIR__ . '/script.js')); ?>"></script>
</body>

</html>
