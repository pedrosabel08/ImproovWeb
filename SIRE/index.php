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

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

include_once __DIR__ . '/../conexao.php';
include '../conexaoMain.php';

$idusuario  = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$conn2 = conectarBanco();
$sql_log = "UPDATE logs_usuarios SET tela_atual = ?, ultima_atividade = NOW() WHERE usuario_id = ?";
$stmt_log = $conn2->prepare($sql_log);
if ($stmt_log) {
    $stmt_log->bind_param("si", $tela_atual, $idusuario);
    $stmt_log->execute();
    $stmt_log->close();
}

/* ── Filtros dinâmicos: obras e ambientes distintos ── */
$obras_list   = [];
$ambientes_list = [];

$res_obras = $conn2->query("
    SELECT DISTINCT o.idobra, o.nomenclatura
    FROM obra o
    INNER JOIN imagens_cliente_obra i ON i.obra_id = o.idobra
    INNER JOIN funcao_imagem fi ON fi.imagem_id = i.idimagens_cliente_obra
    INNER JOIN referencias_imagens ri ON ri.funcao_imagem_id = fi.idfuncao_imagem
    ORDER BY o.nomenclatura
");
if ($res_obras) {
    while ($row = $res_obras->fetch_assoc()) $obras_list[] = $row;
    $res_obras->free();
}

$res_amb = $conn2->query("
    SELECT DISTINCT i.tipo_imagem as ambiente
    FROM imagens_cliente_obra i
    INNER JOIN funcao_imagem fi ON fi.imagem_id = i.idimagens_cliente_obra
    INNER JOIN referencias_imagens ri ON ri.funcao_imagem_id = fi.idfuncao_imagem
    WHERE i.tipo_imagem IS NOT NULL AND i.tipo_imagem <> ''
    ORDER BY i.tipo_imagem
");
if ($res_amb) {
    while ($row = $res_amb->fetch_assoc()) $ambientes_list[] = $row['ambiente'];
    $res_amb->free();
}

$conn2->close();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Referências</title>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <!-- Global -->
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">

    <!-- Módulo -->
    <link rel="stylesheet" href="<?php echo asset_url('catalogo.css'); ?>">
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">

        <!-- ── Page Header ── -->
        <div class="page-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" alt="Improov" class="page-header-logo" id="gif">
                <div class="page-title-wrap">
                    <h1 class="page-title">Catálogo de Referências</h1>
                    <span class="page-subtitle">Explore referências visuais da empresa</span>
                </div>
            </div>
            <div class="results-summary">
                <span class="results-badge" id="resultsBadge">
                    <i class="fa-solid fa-images"></i>
                    <span id="resultsCount">0</span><span id="resultsTotal" class="results-total"></span> referências
                    <span class="filter-active-dot" id="filterDot"></span>
                </span>
            </div>
        </div>

        <!-- ── Search Bar ── -->
        <div class="search-bar-wrap">
            <div class="search-bar-inner">
                <i class="fa-solid fa-magnifying-glass search-bar-icon"></i>
                <input type="text" id="searchInput"
                    class="search-bar-input"
                    placeholder="Buscar por ambiente, obra, estilo ou palavra-chave…"
                    autocomplete="off">
                <button type="button" class="search-bar-clear" id="searchClear" title="Limpar busca">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- ── Filter Bar ── -->
        <div class="filters" id="filters">

            <div class="filter-item">
                <label for="filterObra"><i class="fa-solid fa-building"></i> Obra</label>
                <select id="filterObra">
                    <option value="">Todas as obras</option>
                    <?php foreach ($obras_list as $o): ?>
                        <option value="<?php echo htmlspecialchars($o['idobra']); ?>">
                            <?php echo htmlspecialchars($o['nomenclatura']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-item">
                <label for="filterAmbiente"><i class="fa-solid fa-door-open"></i> Ambiente</label>
                <select id="filterAmbiente">
                    <option value="">Todos os ambientes</option>
                    <?php foreach ($ambientes_list as $a): ?>
                        <option value="<?php echo htmlspecialchars($a); ?>"><?php echo htmlspecialchars($a); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-item">
                <label for="filterEstilo"><i class="fa-solid fa-palette"></i> Estilo</label>
                <select id="filterEstilo">
                    <option value="">Todos os estilos</option>
                </select>
            </div>

            <div class="filter-item">
                <label for="filterTipo"><i class="fa-solid fa-tag"></i> Tipo</label>
                <select id="filterTipo">
                    <option value="">Todos os tipos</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="button" class="btn-apply" id="btnAplicar">
                    <i class="fa-solid fa-magnifying-glass"></i> Aplicar
                </button>
                <button type="button" class="btn-clear" id="btnLimpar">Limpar</button>
            </div>

        </div>

        <!-- ── Reference Grid (scrollable) ── -->
        <div class="grid-scroll-area">
            <div id="refGrid" class="ref-grid">
                <?php for ($i = 0; $i < 12; $i++): ?>
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

            <div class="load-more-wrap" id="loadMoreWrap" style="display:none;">
                <button type="button" class="btn-load-more" id="btnLoadMore">
                    <i class="fa-solid fa-rotate"></i>
                    Carregar mais
                    <span class="load-more-counter" id="loadMoreCounter"></span>
                </button>
            </div>
        </div>

    </div><!-- /.container -->

    <!-- ── Lightbox: visualização expandida ── -->
    <div id="refLightbox" class="modal">
        <div class="modal-content ref-lightbox-content">

            <div class="modal-header">
                <div class="modal-header-left">
                    <span class="modal-title" id="lb_titulo">—</span>
                    <span class="modal-subtitle">
                        <span id="lb_obra"></span>
                        <span id="lb_ambiente" class="lb-badge"></span>
                    </span>
                </div>
                <div class="modal-header-right">
                    <button class="modal-close" id="closeLightbox" title="Fechar (Esc)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="modal-body">

                <!-- Preview -->
                <div class="modal-preview-panel">
                    <img id="lbMainImg" class="modal-main-img" src="" alt="Referência">
                    <div class="lb-zoom-hint"><i class="fa-solid fa-magnifying-glass-plus"></i> Ctrl + scroll para zoom</div>
                </div>

                <!-- Detalhes -->
                <div class="modal-details-panel">

                    <div class="detail-section">
                        <div class="detail-section-title">
                            <i class="fa-solid fa-fingerprint"></i> Identificação
                        </div>
                        <div class="detail-grid">
                            <div class="detail-row">
                                <span class="detail-label">Nomenclatura</span>
                                <span class="detail-value" id="lb_nomenclatura">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Arquivo</span>
                                <span class="detail-value path" id="lb_arquivo">—</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <div class="detail-section-title">
                            <i class="fa-solid fa-building"></i> Obra
                        </div>
                        <div class="detail-grid">
                            <div class="detail-row">
                                <span class="detail-label">Obra</span>
                                <span class="detail-value" id="lb_obra_det">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Ambiente</span>
                                <span class="detail-value" id="lb_ambiente_det">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Estilo</span>
                                <span class="detail-value" id="lb_estilo">—</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <div class="detail-section-title">
                            <i class="fa-solid fa-clock"></i> Registro
                        </div>
                        <div class="detail-grid">
                            <div class="detail-row">
                                <span class="detail-label">Importado em</span>
                                <span class="detail-value" id="lb_data">—</span>
                            </div>
                        </div>
                    </div>

                </div><!-- /.modal-details-panel -->
            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <button type="button" class="btn-action btn-secundario" id="closeLightboxFooter">
                    <i class="fa-solid fa-xmark"></i> Fechar
                </button>
                <button type="button" class="btn-action btn-primario" id="btnVerOriginal">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver original
                </button>
            </div>

        </div>
    </div><!-- /#refLightbox -->

    <?php include '../css/modalSessao.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="../script/sidebar.js"></script>
    <script src="../script/controleSessao.js"></script>
    <script src="<?php echo asset_url('catalogo.js'); ?>"></script>
</body>

</html>