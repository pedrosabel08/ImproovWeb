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
// $nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}


$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);
$imagens = obterImagens($conn);
$status_etapa = obterStatus($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow | Entregas</title>
    <!-- Fonts & icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Project CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('styleCard.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" id="gif" style="height:34px;opacity:0.85;"
                    onerror="this.style.display='none'">
                <h1 class="page-title">Quadro de Entregas</h1>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <button class="btn-relatorio" id="btnRelatorioProducao" title="Relatório de Produção">
                    <i class="fa-solid fa-chart-bar"></i> Relatório
                </button>
                <button class="btn-add" id="adicionar_entrega">
                    <i class="fa-solid fa-plus"></i> Nova Entrega
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filters">
            <div class="filter-group">
                <label class="filter-label">Obra</label>
                <select id="filterObra" class="filter-select">
                    <option value="">Todas as obras</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="filterStatus" class="filter-select">
                    <option value="">Todos os status</option>
                </select>
            </div>
            <div class="filter-actions">
                <button class="btn-clear" id="btnLimparFiltros">
                    <i class="fa-solid fa-xmark"></i> Limpar
                </button>
            </div>
            <div class="toggle-planejamento" id="togglePlanejamento"
                title="Ativar modo planejamento para selecionar entregas e gerar cronograma">
                <div class="toggle-track"></div>
                <span class="toggle-label">Planejamento</span>
            </div>
            <button class="btn-arquivadas" id="btnVerArquivadas" title="Ver entregas arquivadas e de obras inativas">
                <i class="fa-solid fa-box-archive"></i> Ver arquivadas
            </button>
        </div>

        <section id="pendenciasEntregaPanel" class="delivery-pending-panel" hidden>
            <div class="delivery-pending-header">
                <div>
                    <h2>Pendências de entrega</h2>
                    <p>Imagens aprovadas que ainda não têm entrega registrada na etapa atual.</p>
                </div>
                <button type="button" class="btn-clear" id="btnFecharPendenciasEntrega" title="Ocultar pendências">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="pendenciasEntregaList" class="delivery-pending-list"></div>
        </section>

        <section class="kpi-panel" aria-label="KPIs de entregas">
            <div class="kpi-grid" id="entregasKpiGrid">
                <div class="kpi-card kpi-blue" data-kpi-card="total">
                    <div class="kpi-card-top">
                        <span class="kpi-icon"><i class="fa-solid fa-clipboard-check"></i></span>
                        <span class="kpi-label">Entregas feitas</span>
                    </div>
                    <strong class="kpi-value" id="entregasKpiTotal">0</strong>
                    <div class="kpi-delta" id="entregasKpiTotalDelta">-</div>
                    <svg class="kpi-sparkline" id="entregasKpiTotalSpark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true"></svg>
                </div>
                <div class="kpi-card kpi-green" data-kpi-card="no_prazo">
                    <div class="kpi-card-top">
                        <span class="kpi-icon"><i class="fa-solid fa-circle-check"></i></span>
                        <span class="kpi-label">No prazo</span>
                    </div>
                    <strong class="kpi-value" id="entregasKpiNoPrazo">0</strong>
                    <div class="kpi-delta" id="entregasKpiNoPrazoDelta">-</div>
                    <svg class="kpi-sparkline" id="entregasKpiNoPrazoSpark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true"></svg>
                </div>
                <div class="kpi-card kpi-red" data-kpi-card="com_atraso">
                    <div class="kpi-card-top">
                        <span class="kpi-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        <span class="kpi-label">Com atraso</span>
                    </div>
                    <strong class="kpi-value" id="entregasKpiAtraso">0</strong>
                    <div class="kpi-delta" id="entregasKpiAtrasoDelta">-</div>
                    <svg class="kpi-sparkline" id="entregasKpiAtrasoSpark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true"></svg>
                </div>
                <div class="kpi-card kpi-blue" data-kpi-card="antecipadas">
                    <div class="kpi-card-top">
                        <span class="kpi-icon"><i class="fa-regular fa-paper-plane"></i></span>
                        <span class="kpi-label">Antecipadas</span>
                    </div>
                    <strong class="kpi-value" id="entregasKpiAntecipadas">0</strong>
                    <div class="kpi-delta" id="entregasKpiAntecipadasDelta">-</div>
                    <svg class="kpi-sparkline" id="entregasKpiAntecipadasSpark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true"></svg>
                </div>
                <div class="kpi-card kpi-purple" data-kpi-card="pontualidade">
                    <div class="kpi-card-top">
                        <span class="kpi-icon"><i class="fa-solid fa-bullseye"></i></span>
                        <span class="kpi-label">Pontualidade (SLA)</span>
                    </div>
                    <strong class="kpi-value" id="entregasKpiPontualidade">0%</strong>
                    <div class="kpi-delta" id="entregasKpiPontualidadeDelta">-</div>
                    <svg class="kpi-sparkline" id="entregasKpiPontualidadeSpark" viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true"></svg>
                </div>
            </div>
        </section>

        <!-- Kanban Board -->
        <div class="kanban-scroll-area">
            <div class="kanban-board" id="kanban">
                <div class="column" data-status="atrasada">
                    <div class="column-header">
                        <span class="column-title">
                            <i class="fa-solid fa-triangle-exclamation"
                                style="color:var(--status-reprovado);margin-right:6px;"></i>
                            Atrasadas
                        </span>
                        <span class="column-count" id="count-atrasada">0</span>
                    </div>
                        <div class="column-cards"></div>
                </div>
                <div class="column" data-status="pendente,parcial">
                    <div class="column-header">
                        <span class="column-title">
                            <i class="fa-solid fa-clock" style="color:var(--status-andamento);margin-right:6px;"></i>
                            A entregar
                        </span>
                        <span class="column-count" id="count-pendente">0</span>
                    </div>
                        <div class="column-cards"></div>
                </div>
                <div class="column column-hold" data-status="hold">
                    <div class="column-header">
                        <span class="column-title">
                            <i class="fa-solid fa-pause" style="color:#9e9e9e;margin-right:6px;"></i>
                            HOLD
                        </span>
                        <span class="column-count" id="count-hold">0</span>
                    </div>
                        <div class="column-cards"></div>
                </div>
                <div class="column" data-status="concluida">
                    <div class="column-header">
                        <span class="column-title">
                            <i class="fa-solid fa-paper-plane" style="color:var(--accent);margin-right:6px;"></i>
                            Enviado / Aguardando
                        </span>
                        <span class="column-count" id="count-concluida">0</span>
                    </div>
                        <div class="column-cards"></div>
                </div>
            </div>
        </div>

    </div><!-- /.container -->

    <!-- ====== Modal: Adicionar Entrega ====== -->
    <div id="modalAdicionarEntrega" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fa-solid fa-box" style="color:var(--accent);margin-right:8px;"></i>Nova Entrega
                </h2>
                <button class="modal-close fecharModal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="formAdicionarEntrega" style="display:contents;">
                    <div>
                        <label>Obra</label>
                        <select name="obra_id" id="obra_id" required>
                            <option value="">Selecione a obra</option>
                            <?php foreach ($obras as $obra): ?>
                                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Etapa</label>
                        <select name="status_id" id="status_id" required>
                            <option value="">Selecione a etapa</option>
                            <?php foreach ($status_imagens as $status): ?>
                                <option value="<?= htmlspecialchars($status['idstatus']); ?>">
                                    <?= htmlspecialchars($status['nome_status']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Imagens</label>
                        <div id="imagens_container" class="imagens-container">
                            <p style="margin:0;color:var(--text-muted);">Selecione uma obra e etapa para listar as
                                imagens.</p>
                        </div>
                    </div>
                    <div>
                        <label>Recebimento</label>
                        <input type="date" name="data_recebimento" id="data_recebimento" required>
                    </div>
                    <div>
                        <label>Prazo previsto</label>
                        <input type="date" name="prazo" id="prazo" required>
                    </div>
                    <div>
                        <label>Observações</label>
                        <textarea name="observacoes" id="observacoes" placeholder="Observações opcionais..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary fecharModal">Cancelar</button>
                <button type="submit" form="formAdicionarEntrega" class="btn-action btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar Entrega
                </button>
            </div>
        </div>
    </div>

    <!-- ====== Modal: Detalhe da Entrega ====== -->
    <div class="modal" id="entregaModal">
        <div class="modal-content modal-wide">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitulo">Entrega</h2>
                <button class="modal-close fecharModal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div style="display:flex;gap:24px;flex-wrap:wrap;">
                    <div>
                        <span
                            style="font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.4px;color:var(--text-muted);display:block;margin-bottom:2px;">Prazo</span>
                        <span id="modalPrazo" style="font-size:13px;font-weight:500;">—</span>
                    </div>
                    <div>
                        <span
                            style="font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.4px;color:var(--text-muted);display:block;margin-bottom:2px;">Conclusão
                            geral</span>
                        <span id="modalProgresso" style="font-size:13px;font-weight:500;">—</span>
                    </div>
                </div>
                <div id="modalCaminhoRow" class="modal-caminho-row">
                    <span class="caminho-label"><i class="fa-solid fa-folder"></i>&ensp;Caminho</span>
                    <div class="caminho-field">
                        <code id="modalCaminhoTexto" class="caminho-text">—</code>
                        <button id="btnCopiarCaminho" class="btn-copiar-caminho" title="Copiar caminho">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div id="modalReviewBatches" class="review-batches-panel" style="display:none;"></div>
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <span
                            style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-muted);">
                            <i class="fa-solid fa-images" style="margin-right:5px;"></i>Imagens
                        </span>
                        <button class="btn-action btn-primary" id="btnAdicionarImagem"
                            style="height:30px;font-size:12px;padding:0 12px;">
                            <i class="fa-solid fa-plus"></i> Adicionar
                        </button>
                    </div>
                    <div id="modalImagens"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary fecharModal">Fechar</button>
            </div>
        </div>
    </div>

    <!-- ====== Modal: Selecionar Imagens ====== -->
    <div id="modalSelecionarImagens" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fa-solid fa-images" style="color:var(--accent);margin-right:8px;"></i>Selecionar Imagens
                </h2>
                <button class="modal-close fecharModal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div id="selecionar_imagens_container" class="imagens-container">
                    <p style="margin:0;color:var(--text-muted);">Selecione uma entrega para carregar imagens.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary fecharModal">Cancelar</button>
                <button type="button" class="btn-action btn-primary" id="btnAdicionarSelecionadas">
                    <i class="fa-solid fa-check"></i> Adicionar Selecionadas
                </button>
            </div>
        </div>
    </div>

    <!-- ====== Floating Bar (Planejamento) ====== -->
    <div class="floating-bar hidden" id="floatingBar">
        <span class="count-label"><strong id="floatingCount">0</strong> entrega(s) selecionada(s)</span>
        <button class="btn-action btn-primary" id="btnGerarCronograma">
            <i class="fa-solid fa-calendar-days"></i> Gerar Cronograma
        </button>
    </div>

    <!-- ====== Modal: Prioridade ====== -->
    <div id="modalPrioridade" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fa-solid fa-arrow-down-1-9" style="color:var(--accent);margin-right:8px;"></i>Defina a
                    prioridade de execução
                </h2>
                <button class="modal-close fecharModal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <p style="font-size:12px;color:var(--text-tertiary);margin:0 0 12px;">Arraste para reordenar. A entrega
                    no topo será priorizada.</p>
                <ul class="priority-list" id="priorityList"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary fecharModal">Cancelar</button>
                <button type="button" class="btn-action btn-primary" id="btnConfirmarPrioridade">
                    <i class="fa-solid fa-calendar-days"></i> Gerar Cronograma
                </button>
            </div>
        </div>
    </div>

    <!-- ====== Modal: Cronograma ====== -->
    <div id="cronogramaModal" class="modal">
        <div class="modal-content modal-xl">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fa-solid fa-calendar-days" style="color:var(--accent);margin-right:8px;"></i>Cronograma de
                    Conclusão
                </h2>
                <button class="modal-close fecharModal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" id="cronogramaBody">
                <div class="cronograma-view-toggle" id="cronogramaViewToggle" style="display:none;">
                    <button class="vt-btn active" data-view="table"><i class="fa-solid fa-table-list"></i>
                        Tabela</button>
                    <button class="vt-btn" data-view="gantt"><i class="fa-solid fa-chart-gantt"></i> Gantt</button>
                </div>
                <div class="cronograma-tabs" id="cronogramaTabs"></div>
                <div id="cronogramaContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary fecharModal">Fechar</button>
            </div>
        </div>
    </div>

    <!-- ====== Modal: Relatório de Produção ====== -->
    <div id="modalRelatorioProducao" class="modal">
        <div class="modal-content modal-relatorio">
            <div class="modal-header">
                <div class="relatorio-header-left">
                    <i class="fa-solid fa-chart-bar" style="color:var(--accent);font-size:18px;"></i>
                    <div>
                        <h2 class="modal-title" style="margin:0;">Relatório de Produção</h2>
                        <p class="relatorio-subtitle">Acompanhe o histórico de prazos e entregas do projeto</p>
                    </div>
                </div>
                <button class="modal-close fecharModal"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <!-- Obra selector -->
            <div class="relatorio-obra-bar">
                <div class="filter-group" style="flex:1;">
                    <label class="filter-label">Obra</label>
                    <select id="relatorioObraSelect" class="filter-select">
                        <option value="">Selecione uma obra...</option>
                        <?php foreach ($obras as $o): ?>
                            <option value="<?= intval($o['idobra']); ?>"><?= htmlspecialchars($o['nomenclatura']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn-apply" id="btnCarregarRelatorio">
                    <i class="fa-solid fa-magnifying-glass"></i> Carregar
                </button>
            </div>

            <div class="modal-body relatorio-body" id="relatorioBody">
                <div class="relatorio-empty" id="relatorioEmpty">
                    <i class="fa-solid fa-chart-bar" style="font-size:40px;color:var(--text-muted);margin-bottom:12px;"></i>
                    <p>Selecione uma obra para visualizar o relatório.</p>
                </div>

                <!-- Loaded content rendered by JS -->
                <div id="relatorioContent" style="display:none;">

                    <!-- Info geral -->
                    <div class="relatorio-info-bar" id="relatorioInfoBar"></div>

                    <!-- Summary cards -->
                    <div class="relatorio-summary" id="relatorioSummary"></div>

                    <!-- Etapas accordion -->
                    <div class="relatorio-etapas" id="relatorioEtapas"></div>

                </div>

                <!-- Loading -->
                <div class="relatorio-loading" id="relatorioLoading" style="display:none;">
                    <i class="fa-solid fa-circle-notch fa-spin" style="font-size:32px;color:var(--accent);"></i>
                    <p>Carregando dados…</p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary fecharModal">Fechar</button>
                <button type="button" class="btn-action btn-primary" id="btnExportarRelatorio" style="display:none;">
                    <i class="fa-solid fa-download"></i> Exportar relatório
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        window.STATUS_IMAGENS = <?php
        $statusArr = [];
        foreach ($status_imagens as $s) {
            $statusArr[] = [
                'id' => intval($s['idstatus'] ?? $s['id'] ?? 0),
                'nome' => htmlspecialchars_decode($s['nome_status'] ?? $s['nome'] ?? '')
            ];
        }
        echo json_encode($statusArr);
        ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script src="<?php echo asset_url('script.js') . '&t=' . filemtime(__DIR__ . '/script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>
