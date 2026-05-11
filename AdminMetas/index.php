<?php

/**
 * AdminMetas/index.php
 * Painel de administração de metas por colaborador/função.
 */

require_once __DIR__ . '/../config/session_bootstrap.php';

$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

include '../conexaoMain.php';
include '../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);

$conn->close();

$mesSel = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('m');
$anoSel = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

// Validação
if ($mesSel < 1 || $mesSel > 12)
    $mesSel = (int) date('m');
if ($anoSel < 2020 || $anoSel > 2100)
    $anoSel = (int) date('Y');

$nomeMeses = [
    'Janeiro',
    'Fevereiro',
    'Março',
    'Abril',
    'Maio',
    'Junho',
    'Julho',
    'Agosto',
    'Setembro',
    'Outubro',
    'Novembro',
    'Dezembro',
];

$anoAtual = (int) date('Y');

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Metas por Colaborador</title>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6.6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- Sidebar & Modal de Sessão -->
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <!-- Módulo -->
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css'); ?>">
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">
        <div class="page-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" class="page-header-logo" id="gif" alt="ImproovWeb">

                <div class="page-heading">
                    <h1 class="page-title">Metas por Colaborador</h1>
                    <p class="page-subtitle">Defina e edite metas mensais por função e colaborador com foco em
                        velocidade operacional.</p>
                </div>
            </div>

            <div class="page-header-right">
                <span class="results-badge" id="resultsBadge">
                    <i class="fa-solid fa-users"></i>
                    <span id="resultsCount">…</span> colaboradores
                </span>

                <a href="../TvDashboard/index.php" target="_blank" class="btn-secondary">
                    <i class="fa-solid fa-tv"></i>
                    Ver como TV
                </a>

                <button class="btn-primary" id="btnSalvar" type="button">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span class="btn-label">Salvar alterações</span>
                    <span id="pendingBadge" class="pending-badge" style="display:none">0</span>
                </button>
            </div>
        </div>

        <div class="filters-panel" id="filtersPanel" aria-labelledby="filtersSheetTitle">
            <div class="filters-sheet-header">
                <div class="filters-sheet-copy">
                    <span class="filters-sheet-kicker">Filtros</span>
                    <strong class="filters-sheet-title" id="filtersSheetTitle">Ajuste o período e os filtros</strong>
                </div>

                <button class="filters-sheet-close" id="btnCloseFilters" type="button" aria-label="Fechar filtros">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label" for="selMes">Mês</label>
                    <div class="control-shell">
                        <i class="fa-regular fa-calendar"></i>
                        <select id="selMes" class="filter-select">
                            <?php foreach ($nomeMeses as $i => $nome): ?>
                                <option value="<?= $i + 1 ?>" <?= ($i + 1 === $mesSel) ? 'selected' : '' ?>>
                                    <?= $nome ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="selAno">Ano</label>
                    <div class="control-shell">
                        <i class="fa-regular fa-calendar"></i>
                        <select id="selAno" class="filter-select">
                            <?php for ($a = $anoAtual; $a >= $anoAtual - 4; $a--): ?>
                                <option value="<?= $a ?>" <?= ($a === $anoSel) ? 'selected' : '' ?>>
                                    <?= $a ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label" for="selFuncao">Função</label>
                    <div class="control-shell">
                        <i class="fa-solid fa-briefcase"></i>
                        <select id="selFuncao" class="filter-select">
                            <option value="">Todas</option>
                        </select>
                    </div>
                </div>

                <div class="filter-group search-group">
                    <label class="filter-label" for="searchColaborador">Buscar colaborador</label>
                    <div class="control-shell control-shell-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input id="searchColaborador" class="filter-input" type="search" autocomplete="off"
                            placeholder="Buscar colaborador...">
                        <button class="search-clear" id="btnLimparBusca" type="button" aria-label="Limpar busca">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div class="filter-actions">
                    <button id="btnAplicar" class="btn-apply" type="button">
                        <i class="fa-solid fa-arrow-rotate-right"></i>
                        Atualizar período
                    </button>
                </div>
            </div>

            <div class="pending-panel" id="pendingPanel">
                <span class="pending-panel-label">Alterações pendentes</span>
                <strong class="pending-panel-value" id="pendingSummaryCount">0</strong>
            </div>
        </div>

        <div class="legend-bar">
            <div class="legend-copy">
                <i class="fa-solid fa-circle-info"></i>
                Edite as metas diretamente na tabela. As alterações só são aplicadas ao salvar.
            </div>

            <div class="legend-items" aria-label="Legenda de status">
                <span class="legend-item"><span class="legend-dot legend-none"></span>Sem meta</span>
                <span class="legend-item"><span class="legend-dot legend-below"></span>Abaixo</span>
                <span class="legend-item"><span class="legend-dot legend-hit"></span>Atingida</span>
                <span class="legend-item"><span class="legend-dot legend-over"></span>Superada</span>
                <span class="legend-item"><span class="legend-dot legend-record"></span>Recorde</span>
            </div>
        </div>

        <div class="list-scroll-area" id="listaAcordoes">
            <!-- Preenchido via JS -->
        </div>

        <div class="sticky-footer" id="stickyFooter" style="display:none;">
            <div class="sticky-footer-copy">
                <strong><span id="stickyPendingCount">0</span> alterações pendentes</strong>
                <span>Revise ou salve para aplicar as metas deste período.</span>
            </div>

            <div class="sticky-footer-actions">
                <button id="btnDescartar" class="btn-ghost" type="button">
                    <i class="fa-regular fa-trash-can"></i>
                    Descartar alterações
                </button>

                <button id="btnSalvarFooter" class="btn-primary" type="button">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span class="btn-label">Salvar alterações</span>
                    <span id="pendingBadgeFooter" class="pending-badge" style="display:none">0</span>
                </button>
            </div>
        </div>
    </div>

    <div class="filters-sheet-backdrop" id="filtersBackdrop" aria-hidden="true"></div>
    <button class="filters-sheet-trigger" id="btnOpenFilters" type="button" aria-controls="filtersPanel"
        aria-expanded="false" aria-label="Abrir filtros">
        <i class="fa-solid fa-sliders"></i>
    </button>

    <?php include '../css/modalSessao.php'; ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Toastify -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Sidebar & Controle de sessão -->
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>

    <!-- Passa dados do PHP para o JS -->
    <script>
        window.APP_MES = <?= $mesSel ?>;
        window.APP_ANO = <?= $anoSel ?>;
    </script>

    <!-- Módulo principal -->
    <script src="<?php echo asset_url('js/app.js'); ?>"></script>
</body>

</html>