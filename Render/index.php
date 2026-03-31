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

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

include_once __DIR__ . '/../conexao.php';

include '../conexaoMain.php';

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);
// Use DB server time for ultima_atividade to avoid clock/timezone mismatches
// $ultima_atividade = date('Y-m-d H:i:s');

// We already extracted needed session values; close the session to release the lock
// before performing heavier DB work below.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Use MySQL NOW() so the database records its own current timestamp
$sql2 = "UPDATE logs_usuarios 
         SET tela_atual = ?, ultima_atividade = NOW()
         WHERE usuario_id = ?";
$stmt2 = $conn->prepare($sql2);

if (!$stmt2) {
    die("Erro no prepare: " . $conn->error);
}

// 'si' indica os tipos: string, integer
$stmt2->bind_param("si", $tela_atual, $idusuario);

if (!$stmt2->execute()) {
    die("Erro no execute: " . $stmt2->error);
}
$stmt2->close();

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Renders</title>
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" alt="Improov" class="page-header-logo" id="gif">
                <h1 class="page-title">Renders</h1>
            </div>
            <div class="results-summary">
                <span class="results-badge" id="resultsBadge">
                    <i class="fa-solid fa-layer-group"></i>
                    <span id="resultsCount">0</span><span id="resultsTotal" class="results-total"></span> renders
                    <span class="filter-active-dot" id="filterDot"></span>
                </span>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filters" id="filters">

            <div class="filter-search">
                <label for="filterSearch"> <i class="fa-solid fa-magnifying-glass"></i> Buscar</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="filterSearch" placeholder="Nome da imagem, obra, responsável…">
                </div>
            </div>

            <div class="filter-item">
                <label for="filterStatus"><i class="fa-solid fa-circle-dot"></i> Status</label>
                <select id="filterStatus"></select>
            </div>

            <div class="filter-item">
                <label for="filterStatusImagem"><i class="fa-solid fa-image"></i> Status Imagem</label>
                <select id="filterStatusImagem"></select>
            </div>

            <div class="filter-item">
                <label for="filterObra"><i class="fa-solid fa-building"></i> Obra</label>
                <select id="filterObra"></select>
            </div>

            <div class="filter-item">
                <label for="filterColaborador"><i class="fa-solid fa-user"></i> Responsável</label>
                <select id="filterColaborador"></select>
            </div>

            <div class="filter-date-group">
                <label><i class="fa-solid fa-calendar"></i> Período</label>
                <div class="filter-date-range">
                    <input type="date" id="filterDateFrom" title="Data inicial">
                    <span>até</span>
                    <input type="date" id="filterDateTo" title="Data final">
                </div>
            </div>

            <div class="filter-actions">
                <button type="button" class="btn-apply" id="btnAplicar">Aplicar</button>
                <button type="button" class="btn-clear" id="btnLimpar">Limpar</button>
            </div>

        </div>

        <!-- Mobile: FAB to toggle filters -->
        <button id="filter-toggle-btn" aria-expanded="false" aria-controls="filters" class="fab-filter" title="Filtros" style="display:none;">
            <i class="fa-solid fa-filter"></i>
        </button>

        <!-- Render Grid + Load More (scrollable area) -->
        <div class="grid-scroll-area">
            <div id="renderGrid" class="render-grid">
                <!-- Skeleton placeholders shown while loading -->
                <?php for ($i = 0; $i < 8; $i++): ?>
                    <div class="skeleton-card">
                        <div class="skeleton-thumb"></div>
                        <div class="skeleton-body">
                            <div class="skeleton-line medium"></div>
                            <div class="skeleton-line short"></div>
                            <div class="skeleton-line medium"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Load more -->
            <div class="load-more-wrap" id="loadMoreWrap" style="display:none;">
                <button type="button" class="btn-load-more" id="btnLoadMore">
                    <i class="fa-solid fa-rotate"></i>
                    Carregar mais
                    <span class="load-more-counter" id="loadMoreCounter"></span>
                </button>
            </div>
        </div>

    </div><!-- /.container -->

    <!-- ===== CONTEXT MENU: TROCAR RESPONSÁVEL ===== -->
    <div id="ctxMenu" class="ctx-menu">
        <div class="ctx-menu-title">
            <i class="fa-solid fa-user-pen"></i> Trocar Responsável
        </div>
        <select id="ctxResponsavelSelect"></select>
        <button id="ctxSalvar" class="ctx-btn-salvar">
            <i class="fa-solid fa-check"></i> Salvar
        </button>
    </div>

    <!-- ===== JOB DETAILS MODAL ===== -->
    <div id="myModal" class="modal">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header">
                <div class="modal-header-left">
                    <span class="modal-title" id="modal_imagem_id">—</span>
                    <span class="modal-subtitle">
                        <span id="modal_obra_nome"></span>
                        <span id="modal_status_badge"></span>
                    </span>
                </div>
                <div class="modal-header-right">
                    <button class="modal-close" id="closeModal" title="Fechar">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <!-- Body: preview + details -->
            <div class="modal-body">

                <!-- Left: image preview + gallery -->
                <div class="modal-preview-panel">
                    <img id="modalPreviewImg" class="modal-main-img" src="" alt="Preview">
                    <div class="modal-gallery" id="modalGallery"></div>
                </div>

                <!-- Right: info panels -->
                <div class="modal-details-panel">

                    <!-- Section: Identificação -->
                    <div class="detail-section">
                        <div class="detail-section-title">
                            <i class="fa-solid fa-fingerprint"></i> Identificação
                        </div>
                        <div class="detail-grid">
                            <div class="detail-row">
                                <span class="detail-label">ID</span>
                                <span class="detail-value" id="modal_idrender">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Número BG</span>
                                <span class="detail-value" id="modal_numero_bg">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Processo -->
                    <div class="detail-section">
                        <div class="detail-section-title">
                            <i class="fa-solid fa-gears"></i> Processo
                        </div>
                        <div class="detail-grid">
                            <div class="detail-row">
                                <span class="detail-label">Status</span>
                                <span class="detail-value" id="modal_status">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status detalhado</span>
                                <span class="detail-value" id="modal_status_id">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Responsável</span>
                                <span class="detail-value" id="modal_responsavel_id">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Computador</span>
                                <span class="detail-value" id="modal_computer">—</span>
                            </div>
                            <div class="detail-row" id="errorsContainer" style="display:none;">
                                <span class="detail-label">Erros</span>
                                <button class="errors-toggle" id="toggleErrors">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    Ver erros
                                    <i class="fa-solid fa-chevron-down chevron" style="margin-left:auto;"></i>
                                </button>
                                <div class="errors-body" id="modal_errors"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Linha do tempo -->
                    <div class="detail-section">
                        <div class="detail-section-title">
                            <i class="fa-solid fa-clock-rotate-left"></i> Linha do Tempo
                        </div>
                        <div class="detail-grid">
                            <div class="detail-row">
                                <span class="detail-label">Enviado</span>
                                <span class="detail-value" id="modal_submitted">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Última atualização</span>
                                <span class="detail-value" id="modal_last_updated">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Técnico -->
                    <div class="detail-section">
                        <div class="detail-section-title">
                            <i class="fa-solid fa-folder-open"></i> Arquivos
                        </div>
                        <div class="detail-grid">
                            <div class="detail-row">
                                <span class="detail-label">Pasta Render</span>
                                <span class="detail-value path" id="modal_job_folder">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Prévia JPG</span>
                                <span class="detail-value path" id="modal_previa_jpg">—</span>
                            </div>
                        </div>
                    </div>

                </div><!-- /.modal-details-panel -->
            </div><!-- /.modal-body -->

            <!-- Footer: actions -->
            <div class="modal-footer">
                <button type="button" class="btn-action btn-excluir" id="deleteRender">
                    <i class="fa-solid fa-trash"></i> Excluir
                </button>
                <button type="button" class="btn-action btn-reprovar" id="reprovarRender">
                    <i class="fa-solid fa-rotate-right"></i> Reprovar
                </button>
                <button type="button" class="btn-action btn-aprovar" id="aprovarRender">
                    <i class="fa-solid fa-check"></i> Aprovar
                </button>
            </div>

        </div><!-- /.modal-content -->
    </div><!-- /#myModal -->


    <!-- ===== POS MODAL ===== -->
    <div id="modalPOS" class="modal">
        <div class="modal-pos-content">
            <h2><i class="fa-solid fa-wand-magic-sparkles" style="color:var(--accent);margin-right:8px;"></i>Referências de Pós-Produção</h2>
            <input type="hidden" id="pos_render_id">
            <div class="modal-pos-field">
                <label for="pos_caminho">Caminho / Referências</label>
                <textarea id="pos_caminho" placeholder="Cole o caminho ou link das referências…"></textarea>
            </div>
            <div class="modal-pos-field">
                <label for="pos_referencias">Observações</label>
                <textarea id="pos_referencias" placeholder="Observações adicionais…"></textarea>
            </div>
            <div class="modal-pos-actions">
                <button class="btn-pos-fechar" id="fecharPOS">Fechar</button>
                <button class="btn-pos-enviar" id="enviarPOS">
                    <i class="fa-solid fa-paper-plane"></i> Enviar
                </button>
            </div>
        </div>
    </div>


    <!-- ===== SESSION MODAL ===== -->
    <div id="modalSessao" class="modal-sessao">
        <div class="modal-conteudo">
            <h2>Sessão Expirada</h2>
            <p>Sua sessão expirou. Deseja continuar?</p>
            <button onclick="renovarSessao()">Continuar Sessão</button>
            <button onclick="sair()">Sair</button>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>

</body>

</html>