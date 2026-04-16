<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

// session_start();
$nome_usuario = $_SESSION['nome_usuario'];

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
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../Dashboard/styleObra.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <title>Tela de Alterações</title>
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" alt="Improov" class="page-header-logo" id="gif">
                <h1 class="page-title">Alterações</h1>
            </div>
            <div class="results-summary">
                <span class="results-badge" id="resultsBadge">
                    <i class="fa-solid fa-layer-group"></i>
                    <span id="resultsCount">0</span> itens
                </span>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filters" id="filtros-alteracao">

            <div class="filter-group">
                <label class="filter-label">Status Kanban</label>
                <select class="filter-select" id="filtro-status">
                    <option value="">Todos</option>
                    <option value="Não iniciado">Não iniciado</option>
                    <option value="Em andamento">Em andamento</option>
                    <option value="Em aprovação">Em aprovação</option>
                    <option value="Finalizado">Finalizado</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label"><i class="fa-solid fa-image"></i> Status Imagem</label>
                <select class="filter-select" id="filtro-status-imagem">
                    <option value="">Todos</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Obra</label>
                <select class="filter-select" id="filtro-obra">
                    <option value="">Todas</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Colaborador</label>
                <select class="filter-select" id="filtro-colaborador">
                    <option value="">Todos</option>
                </select>
            </div>

            <div class="filter-search">
                <label class="filter-label"><i class="fa-solid fa-magnifying-glass"></i> Buscar</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" class="filter-input" id="filtro-busca" placeholder="Imagem, obra ou colaborador...">
                </div>
            </div>

            <div class="filter-actions">
                <button type="button" id="btn-toggle-compact" class="btn-toggle-compact">
                    <i class="fa-solid fa-compress"></i> Compactar
                </button>
                <button type="button" class="btn-apply" id="btn-aplicar-filtros">
                    <i class="fa-solid fa-magnifying-glass"></i> Aplicar
                </button>
                <button type="button" class="btn-clear" id="btn-limpar-filtros">Limpar</button>
            </div>

        </div>

        <!-- Board Area: EF Panel + Kanban -->
        <div class="board-area">

            <!-- EF Side Panel -->
            <div class="ef-panel" id="ef-panel">
                <div class="ef-panel-header">
                    <i class="fa-solid fa-bolt"></i>
                    <span>Render em Alta</span>
                    <span class="ef-panel-count" id="ef-panel-count">0</span>
                </div>
                <div class="ef-panel-body" id="ef-panel-body">
                    <div class="ef-panel-empty">
                        <i class="fa-solid fa-check"></i>
                        <span>Nenhum EF</span>
                    </div>
                </div>
            </div>

            <!-- Kanban Board -->
            <div class="kanban-board" id="kanban-board">
                <div class="kanban-column" data-status="Não iniciado">
                    <div class="kanban-title">
                        Não iniciado
                        <div class="kanban-title-right">
                            <span class="ef-count" id="ef-count-nao-iniciado"><i class="fa-solid fa-bolt"></i><span>0</span></span>
                            <span class="count" id="count-nao-iniciado">0</span>
                        </div>
                    </div>
                    <div class="kanban-cards" id="kanban-nao-iniciado"></div>
                </div>
                <div class="kanban-column" data-status="Em andamento">
                    <div class="kanban-title">
                        Em andamento
                        <div class="kanban-title-right">
                            <span class="ef-count" id="ef-count-em-andamento"><i class="fa-solid fa-bolt"></i><span>0</span></span>
                            <span class="count" id="count-em-andamento">0</span>
                        </div>
                    </div>
                    <div class="kanban-cards" id="kanban-em-andamento"></div>
                </div>
                <div class="kanban-column" data-status="Em aprovação">
                    <div class="kanban-title">
                        Em aprovação
                        <div class="kanban-title-right">
                            <span class="ef-count" id="ef-count-em-aprovacao"><i class="fa-solid fa-bolt"></i><span>0</span></span>
                            <span class="count" id="count-em-aprovacao">0</span>
                        </div>
                    </div>
                    <div class="kanban-cards" id="kanban-em-aprovacao"></div>
                </div>
                <div class="kanban-column" data-status="Finalizado">
                    <div class="kanban-title">
                        Finalizado
                        <div class="kanban-title-right">
                            <span class="ef-count" id="ef-count-finalizado"><i class="fa-solid fa-bolt"></i><span>0</span></span>
                            <span class="count" id="count-finalizado">0</span>
                        </div>
                    </div>
                    <div class="kanban-cards" id="kanban-finalizado"></div>
                </div>
            </div>

        </div><!-- /.board-area -->

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

    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/notificacoes.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        window.ALTERACAO_LOGGED_COLAB_ID = <?= json_encode($_SESSION['idcolaborador'] ?? null); ?>;
    </script>

</body>

</html>