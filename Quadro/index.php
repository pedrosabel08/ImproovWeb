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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <title>Quadro de Produção</title>
</head>

<body>

    <?php include '../sidebar.php'; ?>

    <div class="wrapper">
        <div class="main-area">
            <header class="quadro-header">
                <div class="header-top">
                    <div class="header-left">
                        <h3>Quadro de Produção</h3>
                        <h4>Por função · agrupado por obra</h4>
                    </div>
                    <div class="header-right">
                        <button id="btn-refresh" title="Atualizar">
                            <i class="ri-refresh-line"></i>
                        </button>
                    </div>
                </div>
                <div class="filter-bar">
                    <div class="filter-group">
                        <label for="filtro-colaborador"><i class="ri-user-line"></i></label>
                        <select id="filtro-colaborador">
                            <option value="">Todos os colaboradores</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filtro-status"><i class="ri-checkbox-circle-line"></i></label>
                        <select id="filtro-status">
                            <option value="">Todos os status</option>
                            <option value="em_andamento">Em andamento</option>
                            <option value="em_aprovacao">Em aprovação</option>
                            <option value="ajuste">Ajuste</option>
                            <option value="hold">HOLD</option>
                            <option value="aprovado_ajustes">Aprovado com ajustes</option>
                            <option value="aprovado">Aprovado</option>
                            <option value="nao_iniciado">Não iniciado</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filtro-tipo"><i class="ri-image-line"></i></label>
                        <select id="filtro-tipo">
                            <option value="">Todos os tipos</option>
                        </select>
                    </div>
                    <button id="btn-clear-filters" class="btn-clear" title="Limpar filtros" style="display:none;">
                        <i class="ri-close-circle-line"></i> Limpar
                    </button>
                </div>
            </header>

            <div id="loading" class="loading-bar" style="display:none;">
                <i class="ri-loader-4-line spin"></i> Carregando…
            </div>

            <div class="kanban" id="kanban-container"></div>
        </div>
    </div>

    <!-- Detail modal -->
    <div id="detail-modal" class="detail-modal" role="dialog" aria-modal="true">
        <div class="detail-modal-box">
            <div class="detail-modal-header">
                <div class="detail-modal-title">
                    <i id="detail-icon" class="ri-image-line"></i>
                    <span id="detail-title">—</span>
                </div>
                <button id="detail-close" title="Fechar"><i class="ri-close-line"></i></button>
            </div>
            <!-- Filtros espelhados no modal -->
            <div class="detail-modal-filters filter-bar">
                <div class="filter-group">
                    <label for="modal-filtro-colaborador"><i class="ri-user-line"></i></label>
                    <select id="modal-filtro-colaborador">
                        <option value="">Todos os colaboradores</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="modal-filtro-status"><i class="ri-checkbox-circle-line"></i></label>
                    <select id="modal-filtro-status">
                        <option value="">Todos os status</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Em aprovação">Em aprovação</option>
                        <option value="Ajuste">Ajuste</option>
                        <option value="HOLD">HOLD</option>
                        <option value="Aprovado com ajustes">Aprovado com ajustes</option>
                        <option value="Aprovado">Aprovado</option>
                        <option value="Não iniciado">Não iniciado</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="modal-filtro-tipo"><i class="ri-image-line"></i></label>
                    <select id="modal-filtro-tipo">
                        <option value="">Todos os tipos</option>
                    </select>
                </div>
                <button id="modal-btn-clear" class="btn-clear" title="Limpar filtros do modal" style="display:none;">
                    <i class="ri-close-circle-line"></i> Limpar
                </button>
            </div>
            <div id="detail-content" class="detail-modal-content"></div>
        </div>
    </div>

    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>