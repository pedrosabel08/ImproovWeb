<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/sire_helpers.php';
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

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);

if (session_status() === PHP_SESSION_ACTIVE)
    session_write_close();

$conn2 = conectarBanco();
$sireCanManage = sire_is_admin($conn2, (int) $idusuario);
$sql_log = "UPDATE logs_usuarios SET tela_atual = ?, ultima_atividade = NOW() WHERE usuario_id = ?";
$stmt_log = $conn2->prepare($sql_log);
if ($stmt_log) {
    $stmt_log->bind_param("si", $tela_atual, $idusuario);
    $stmt_log->execute();
    $stmt_log->close();
}

/* ── Filtros dinâmicos: obras e ambientes distintos ── */
$obras_list = [];
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
    while ($row = $res_obras->fetch_assoc())
        $obras_list[] = $row;
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
    while ($row = $res_amb->fetch_assoc())
        $ambientes_list[] = $row['ambiente'];
    $res_amb->free();
}

$conn2->close();

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$conn->close();

// MantÃ©m os assets locais do SIRE sincronizados durante evoluÃ§Ãµes visuais,
// sem invalidar o cache dos demais mÃ³dulos do Flow.
$sireAssetVersion = max(
    (int) @filemtime(__DIR__ . '/catalogo.css'),
    (int) @filemtime(__DIR__ . '/catalogo.js')
);

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIRE — Biblioteca Visual</title>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    <!-- Global -->
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">

    <!-- Módulo -->
    <link rel="stylesheet" href="<?php echo asset_url('catalogo.css') . '&sire=' . $sireAssetVersion; ?>">
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">

        <!-- ── Page Header ── -->
        <header class="page-header sire-library-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" alt="Improov" class="page-header-logo" id="gif">
                <div class="page-title-wrap">
                    <h1 class="page-title">SIRE</h1>
                    <span class="page-subtitle">Biblioteca visual da IMPROOV</span>
                </div>
            </div>
            <!-- ── Search Bar ── -->
            <div class="search-bar-wrap">
                <div class="search-bar-inner">
                    <i class="fa-solid fa-magnifying-glass search-bar-icon"></i>
                    <input type="text" id="searchInput" class="search-bar-input"
                        placeholder="Buscar referências..." autocomplete="off" aria-label="Buscar referências">
                    <button type="button" class="search-bar-clear" id="searchClear" title="Limpar busca">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="results-summary">
                <?php if ($sireCanManage): ?>
                    <button type="button" class="btn-manage-sire" id="btnManageSire">
                        <i class="fa-solid fa-sliders"></i> <span>Administrar SIRE</span>
                    </button>
                    <button type="button" class="btn-add-reference" id="btnAddReference">
                        <i class="fa-solid fa-plus"></i> <span>Adicionar referência</span>
                    </button>
                <?php endif; ?>
                <span class="results-badge" id="resultsBadge">
                    <i class="fa-solid fa-images"></i>
                    <span id="resultsCount">0</span><span id="resultsTotal" class="results-total"></span> referências
                    <span class="filter-active-dot" id="filterDot"></span>
                </span>
            </div>
        </header>


        <!-- ── Filter Bar ── -->
        <section class="sire-library-context" aria-label="Contexto da biblioteca">
            <div class="sire-active-filters">
                <span class="sire-context-label"><i class="fa-solid fa-filter"></i> Filtros ativos</span>
                <div id="activeFilterChips" class="sire-filter-chips" aria-live="polite"></div>
                <button type="button" class="sire-context-clear" id="btnContextClear" hidden>Limpar todos</button>
            </div>
            <div class="sire-library-stats" aria-live="polite">
                <span><b id="contextRefsCount">0</b> refer&ecirc;ncias</span>
                <span>
                    <b id="contextInteriorsCount">0</b>
                    <span id="contextInteriorsLabel">interna</span>
                </span>

                <span>
                    <b id="contextExteriorsCount">0</b>
                    <span id="contextExteriorsLabel">externa</span>
                </span>
                <span><b id="contextNewCount">0</b> novas esta semana</span>
            </div>
            <div class="sire-library-view-controls">
                <label for="sortReferences"><i class="fa-solid fa-arrow-down-wide-short"></i> Ordenar</label>
                <select id="sortReferences" aria-label="Ordenar refer&ecirc;ncias">
                    <option value="relevant">Mais relevantes</option>
                </select>
                <div class="sire-view-toggle" role="group" aria-label="Modo de visualiza&ccedil;&atilde;o">
                    <button type="button" class="is-active" aria-label="Visualiza&ccedil;&atilde;o em grade" aria-pressed="true"><i class="fa-solid fa-grip"></i></button>
                    <button type="button" disabled aria-label="Visualiza&ccedil;&atilde;o em lista em breve" title="Visualiza&ccedil;&atilde;o em lista em breve"><i class="fa-solid fa-list"></i></button>
                </div>
            </div>
        </section>

        <div class="filters sire-filter-sidebar" id="filters" aria-label="Filtros da biblioteca">
            <div class="sire-filter-header">
                <h2>Filtros</h2>
                <div class="sire-filter-header-actions">
                    <button type="button" class="btn-clear" id="btnLimpar">Limpar todos</button>
                    <button type="button" class="sire-filter-close" id="btnCloseFilters" aria-label="Fechar filtros">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="pilar-filters" id="pilarFilters"></div>

            <details class="sire-filter-extra">
                <summary>Mais filtros</summary>
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

                <div class="filter-item filter-item--golden">
                    <label class="filter-golden-label" for="filterGolden">
                        <input type="checkbox" id="filterGolden">
                        <i class="fa-solid fa-star"></i>
                        Apenas Golden Samples
                    </label>
                </div>
            </details>

            <div class="filter-actions">
                <button type="button" class="btn-apply" id="btnAplicar">
                    <i class="fa-solid fa-magnifying-glass"></i> Aplicar
                </button>
            </div>

        </div>

        <!-- ── Reference Grid (scrollable) ── -->
        <section class="sire-event-queue" id="sireEventQueue" aria-labelledby="sireEventQueueTitle" hidden>
            <div class="sire-event-queue-head">
                <div>
                    <h2 id="sireEventQueueTitle">Referências de Eventos</h2>
                    <span id="sireEventQueueCount">Fila pendente</span>
                </div>
                <button type="button" class="sire-event-refresh" id="btnReloadEventRefs" title="Atualizar fila">
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </div>
            <div class="sire-event-refs-list" id="sireEventRefsList">
                <div class="sire-event-empty">Carregando referências...</div>
            </div>
        </section>

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
    <div class="sire-filter-backdrop" id="filterBackdrop" hidden></div>
    <button type="button" id="filter-toggle-btn" class="sire-filter-toggle" aria-expanded="false" aria-controls="filters">
        <i class="fa-solid fa-sliders"></i><span>Filtros</span><b id="mobileFilterCount" hidden>0</b>
    </button>

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
                    <?php if ($sireCanManage): ?>
                        <button type="button" class="btn-action btn-secundario modal-classification-toggle"
                            id="btnToggleClassification" aria-expanded="false" aria-controls="lbClassificationTab">
                            <i class="fa-solid fa-tags"></i><span>Classificar</span>
                        </button>
                        <button type="button" class="btn-golden-modal" id="lbBtnGolden" title="Marcar como Golden Sample">
                            <i class="fa-regular fa-star" id="lbGoldenIcon"></i>
                            <span id="lbGoldenLabel">Golden Sample</span>
                        </button>
                    <?php endif; ?>

                    <button class="modal-close" id="closeLightbox" title="Fechar (Esc)">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="modal-body" id="lbDetailsTab">

                <!-- Preview -->
                <div class="modal-preview-panel">
                    <img id="lbMainImg" class="modal-main-img" src="" alt="Referência">
                    <div class="lb-zoom-hint"><i class="fa-solid fa-magnifying-glass-plus"></i> Ctrl + scroll para zoom
                    </div>
                </div>

                <!-- Detalhes -->
                <div class="modal-details-panel">

                    <section class="modal-classification reference-classification-panel" id="lbClassificationTab" hidden>
                        <div class="classification-heading">
                            <div>
                                <h2>Classificação visual</h2>
                                <p>Selecione quantos valores forem necessários em cada pilar.</p>
                            </div>
                            <span class="origin-badge" id="lbOrigin">—</span>
                        </div>
                        <div id="classificationFields" class="classification-fields"></div>
                        <label class="classification-description" for="lbDescription">Descrição</label>
                        <textarea id="lbDescription" rows="4" placeholder="Contexto livre para esta referência..."></textarea>
                    </section>

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
                <button type="button" class="btn-action btn-primario" id="btnSaveClassification" hidden>
                    <i class="fa-solid fa-floppy-disk"></i> Salvar classificação
                </button>
            </div>

        </div>
    </div><!-- /#refLightbox -->

    <div id="addReferenceModal" class="modal">
        <div class="modal-content add-reference-content">
            <div class="modal-header">
                <div class="modal-header-left"><span class="modal-title">Adicionar referência</span></div>
                <button class="modal-close" id="closeAddReference" title="Fechar"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="addReferenceForm" class="add-reference-form" enctype="multipart/form-data">
                <label for="addReferenceType">Origem</label>
                <select id="addReferenceType" name="tipo">
                    <option value="Upload">Upload de imagem</option>
                    <option value="URL">Adicionar por URL</option>
                </select>
                <label for="addReferenceTitle">Título</label>
                <input id="addReferenceTitle" name="titulo" maxlength="255" placeholder="Opcional">
                <div id="addReferenceUploadGroup">
                    <label for="addReferenceFile">Imagem</label>
                    <input id="addReferenceFile" type="file" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
                </div>
                <div id="addReferenceUrlGroup" hidden>
                    <label for="addReferenceUrl">URL da imagem</label>
                    <input id="addReferenceUrl" type="url" name="url" placeholder="https://...">
                </div>
                <label for="addReferenceDescription">Descrição</label>
                <textarea id="addReferenceDescription" name="descricao" rows="4" placeholder="Opcional"></textarea>
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secundario" id="cancelAddReference">Cancelar</button>
                    <button type="submit" class="btn-action btn-primario"><i class="fa-solid fa-plus"></i> Adicionar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($sireCanManage): ?>
        <div id="sireAdminModal" class="modal" aria-hidden="true">
            <div class="modal-content sire-admin-content" role="dialog" aria-modal="true" aria-labelledby="sireAdminTitle">
                <div class="modal-header">
                    <div class="modal-header-left">
                        <span class="modal-title" id="sireAdminTitle">Administrar SIRE</span>
                        <span class="modal-subtitle">Vocabulário de classificação da biblioteca visual</span>
                    </div>
                    <button type="button" class="modal-close" id="closeSireAdmin" aria-label="Fechar administração" title="Fechar"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="sire-admin-body">
                    <aside class="sire-admin-pillars" aria-label="Pilares de classificação">
                        <div class="sire-admin-pillars-heading">Pilares</div>
                        <div id="adminPillarsNav" class="sire-admin-pillars-list"></div>
                    </aside>
                    <section class="sire-admin-main" aria-live="polite">
                        <div id="adminValuesView">
                            <div class="sire-admin-main-header">
                                <div>
                                    <h2 id="adminPillarTitle">Pilar</h2>
                                    <p id="adminPillarDescription">Gerencie os valores disponíveis para este pilar.</p>
                                </div>
                                <button type="button" class="btn-action btn-primario" id="btnNewSireValue"><i class="fa-solid fa-plus"></i> Novo valor</button>
                            </div>
                            <label class="sr-only" for="adminValueSearch">Buscar valores</label>
                            <div class="sire-admin-search">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input id="adminValueSearch" type="search" placeholder="Buscar valores..." autocomplete="off">
                            </div>
                            <div class="sire-admin-values-wrap">
                                <table class="sire-admin-values-table">
                                    <thead>
                                        <tr>
                                            <th>Valor</th>
                                            <th>Descrição</th>
                                            <th>Características</th>
                                            <th>Uso</th>
                                            <th>Status</th>
                                            <th><span class="sr-only">Ações</span></th>
                                        </tr>
                                    </thead>
                                    <tbody id="adminValuesList"></tbody>
                                </table>
                                <div id="adminValuesEmpty" class="sire-admin-empty" hidden>Nenhum valor encontrado para este pilar.</div>
                            </div>
                        </div>
                        <form id="adminValueForm" class="sire-admin-form" hidden novalidate>
                            <div class="sire-admin-form-header">
                                <div>
                                    <h2 id="adminFormTitle">Novo valor</h2>
                                    <p>Defina o conceito para que a classificação permaneça consistente.</p>
                                </div>
                                <button type="button" class="btn-icon-text" id="btnCancelSireValue"><i class="fa-solid fa-arrow-left"></i> Voltar</button>
                            </div>
                            <input id="adminValueId" type="hidden">
                            <div class="sire-admin-form-grid">
                                <div class="sire-admin-field">
                                    <label for="adminValuePillar">Pilar</label>
                                    <select id="adminValuePillar" required></select>
                                    <small id="adminPillarChangeHelp" hidden>Valores já utilizados não podem ser movidos de pilar.</small>
                                </div>
                                <div class="sire-admin-field">
                                    <label for="adminValueName">Nome</label>
                                    <input id="adminValueName" maxlength="160" required placeholder="Ex.: Contemplativa">
                                </div>
                            </div>
                            <div class="sire-admin-field">
                                <label for="adminValueDescription">Descrição</label>
                                <textarea id="adminValueDescription" rows="4" maxlength="4000" required placeholder="Explique o conceito visual, sem repetir apenas o nome."></textarea>
                            </div>
                            <fieldset class="sire-admin-status-field">
                                <legend>Status</legend>
                                <label><input type="radio" name="adminValueStatus" value="1" checked> Ativo</label>
                                <label><input type="radio" name="adminValueStatus" value="0"> Inativo</label>
                            </fieldset>
                            <div class="sire-admin-features">
                                <div class="sire-admin-features-header">
                                    <div>
                                        <h3>Características</h3>
                                        <p>Textos curtos que ajudam a explicar o valor.</p>
                                    </div><button type="button" class="btn-icon-text" id="btnAddSireFeature"><i class="fa-solid fa-plus"></i> Adicionar característica</button>
                                </div>
                                <div id="adminFeaturesList" class="sire-admin-features-list"></div>
                            </div>
                            <div class="modal-footer sire-admin-form-footer">
                                <button type="button" class="btn-action btn-secundario" id="btnCancelSireValueFooter">Cancelar</button>
                                <button type="submit" class="btn-action btn-primario" id="btnSaveSireValue">Salvar valor</button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="../script/sidebar.js"></script>
    <script src="../script/controleSessao.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?php echo asset_url('catalogo.js') . '&sire=' . $sireAssetVersion; ?>"></script>
</body>

</html>