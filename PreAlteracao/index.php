<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

include '../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';

// session_start();
// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
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
    <title>Pré-Alteração — ImproovWeb</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="../css/modalSessao.css">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <h1 class="page-title"><i class="fa-solid fa-magnifying-glass-chart"></i> Pré-Alteração</h1>
                <span class="results-badge" id="badgeCount">—</span>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filters">
            <div class="filter-group">
                <span class="filter-label">Obra</span>
                <select id="filtroObra" class="filter-select">
                    <option value="">Todas as obras</option>
                </select>
            </div>
        </div>

        <!-- Empty state -->
        <div class="empty-state" id="emptyState" style="display:none">
            <i class="fa-solid fa-search-minus"></i>
            <p>Nenhuma imagem em pré-análise encontrada.</p>
        </div>

        <!-- Two-column layout -->
        <div class="columns-layout" id="columnsLayout" style="display:none">
            <!-- Left: Pré-Análise -->
            <div class="col-panel col-pre-analise">
                <div class="col-panel-header">
                    <div class="col-panel-title">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Pré-Análise</span>
                    </div>
                    <span class="col-panel-count" id="countPreAnalise">0</span>
                </div>
                <div class="col-panel-body" id="colPreAnalise">
                    <!-- Cards: substatus 10 (RVW_DONE) e 11 (PRE_ALT) -->
                </div>
            </div>

            <!-- Right: Para Planejamento -->
            <div class="col-panel col-planejamento">
                <div class="col-panel-header">
                    <div class="col-panel-title">
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>Para Planejamento</span>
                    </div>
                    <span class="col-panel-count col-panel-count--green" id="countPlanejamento">0</span>
                </div>
                <div class="col-panel-body" id="colPlanejamento">
                    <!-- Cards: substatus 12 (READY_FOR_PLANNING) -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pré-Análise -->
    <div class="modal-pa" id="paModal">
        <div class="modal-pa-content">
            <div class="modal-pa-header">
                <div class="modal-pa-header-info">
                    <h2 class="modal-pa-title" id="paModalTitle">—</h2>
                    <div class="modal-pa-badges" id="paModalBadges"></div>
                </div>
                <button class="modal-pa-close" id="paModalClose" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-pa-body" id="paModalBody"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="../script/sidebar.js"></script>
    <script src="../script/controleSessao.js"></script>
    <script src="script.js"></script>
</body>

</html>