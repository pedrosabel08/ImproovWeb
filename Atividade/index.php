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
    header('Location: ../index.html');
    exit;
}

// Restrito a administradores (nivel_acesso == 1)
if (empty($_SESSION['nivel_acesso']) || (int)$_SESSION['nivel_acesso'] !== 1) {
    header('Location: ../inicio.php');
    exit;
}

include '../conexaoMain.php';
$conn = conectarBanco();
$colaboradores = obterColaboradores($conn);
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Atividade — Flow</title>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon" />

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" />

    <!-- Global styles -->
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>" />

    <!-- Module styles -->
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="container">

        <!-- ══════════════════════════════════════════════
       PAGE HEADER
  ════════════════════════════════════════════════ -->
        <div class="page-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" id="gif" style="height:36px; opacity:0.85" alt="Flow" />
                <div>
                    <h1 class="page-title">Atividade</h1>
                    <p class="page-subtitle">Monitoramento operacional e uso do sistema</p>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span class="results-badge" id="onlineCount">
                    <i class="fa-solid fa-circle" style="color:var(--status-online-color); font-size:8px;"></i>
                    <span id="onlineCountNum">—</span> online
                </span>
                <button class="refresh-btn" id="btnRefresh" title="Atualizar dados">
                    <i class="fa-solid fa-arrows-rotate"></i> Atualizar
                </button>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
       FILTER BAR
  ════════════════════════════════════════════════ -->
        <div class="filters" id="filtersPanel">
            <div class="filters-sheet-header" id="filtersSheetHeader">
                <span><i class="fa-solid fa-sliders"></i> Filtros</span>
                <button class="refresh-btn" id="btnCloseFilters" style="padding:4px 10px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="filter-group">
                <label class="filter-label">Período</label>
                <select class="filter-select" id="filtPeriodo">
                    <option value="today">Hoje</option>
                    <option value="week">Últimos 7 dias</option>
                    <option value="month">Últimos 30 dias</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Colaborador</label>
                <select class="filter-select" id="filtUsuario">
                    <option value="">Todos</option>
                    <?php foreach ($colaboradores as $c): ?>
                        <option value="<?php echo (int)$c['idcolaborador']; ?>">
                            <?php echo htmlspecialchars($c['nome_colaborador']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">Tela</label>
                <div class="input-wrap" style="position:relative;">
                    <input type="text" class="filter-input" id="filtTela" placeholder="Ex: Dashboard..." style="padding-left:10px; width:180px;" />
                </div>
            </div>

            <div class="filter-group" id="grpStatus">
                <label class="filter-label">Status</label>
                <select class="filter-select" id="filtStatus">
                    <option value="">Todos</option>
                    <option value="online">Online</option>
                    <option value="ausente">Ausente</option>
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn-apply" id="btnAplicar">
                    <i class="fa-solid fa-magnifying-glass"></i> Aplicar
                </button>
                <button class="btn-clear" id="btnLimpar">Limpar</button>
            </div>
        </div>

        <!-- Backdrop bottom-sheet (mobile/tablet) -->
        <div class="filters-sheet-backdrop" id="filtersBackdrop"></div>

        <!-- ══════════════════════════════════════════════
       KPI CARDS
  ════════════════════════════════════════════════ -->
        <div class="kpi-row" id="kpiRow">
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-circle-dot"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpiOnline">—</div>
                    <div class="kpi-label">Online agora</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpiSessoes">—</div>
                    <div class="kpi-label">Sessões ativas hoje</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-fire"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value kpi-text" id="kpiTela">—</div>
                    <div class="kpi-label">Tela mais acessada</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-trophy"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value kpi-text" id="kpiUsuario">—</div>
                    <div class="kpi-label">Usuário mais ativo</div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-chart-bar"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpiTotal">—</div>
                    <div class="kpi-label">Acessos hoje</div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
       TABS
  ════════════════════════════════════════════════ -->
        <div class="tabs-container">
            <nav class="tabs-nav" role="tablist">
                <button class="tab-btn active" data-tab="online" role="tab" aria-selected="true">
                    <i class="fa-solid fa-circle-dot"></i> Online
                </button>
                <button class="tab-btn" data-tab="telas" role="tab" aria-selected="false">
                    <i class="fa-solid fa-layer-group"></i> Uso de Telas
                </button>
                <button class="tab-btn" data-tab="historico" role="tab" aria-selected="false">
                    <i class="fa-solid fa-clock-rotate-left"></i> Histórico
                </button>
                <button class="tab-btn" data-tab="ativos" role="tab" aria-selected="false">
                    <i class="fa-solid fa-ranking-star"></i> Mais Ativos
                </button>
            </nav>

            <div class="tabs-content">

                <!-- ─────────────────── TAB: ONLINE ─────────────────── -->
                <div class="tab-panel active" id="panel-online" role="tabpanel">
                    <div class="auto-refresh-bar">
                        <span class="refresh-dot"></span>
                        Atualização automática a cada 30 s
                        <span style="margin-left:auto; color:var(--text-tertiary);" id="lastRefreshOnline"></span>
                    </div>
                    <div class="colabs-scroll-area">
                        <div class="colabs-grid" id="colabsGrid">
                            <div class="colab-card-skeleton"></div>
                            <div class="colab-card-skeleton"></div>
                            <div class="colab-card-skeleton"></div>
                            <div class="colab-card-skeleton"></div>
                            <div class="colab-card-skeleton"></div>
                            <div class="colab-card-skeleton"></div>
                        </div>
                    </div>
                </div>

                <!-- ─────────────────── TAB: TELAS ─────────────────── -->
                <div class="tab-panel" id="panel-telas" role="tabpanel">
                    <div class="chart-panel">
                        <div class="chart-section">
                            <div class="chart-section-title">
                                <i class="fa-solid fa-chart-bar"></i>
                                Top 10 Telas por Acessos
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="chartTelas"></canvas>
                            </div>
                        </div>
                        <div class="table-section">
                            <div class="table-wrap">
                                <table class="data-table" id="tableTelas">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tela</th>
                                            <th class="col-right">Total de Acessos</th>
                                            <th class="col-right">Usuários Únicos</th>
                                            <th>Último Acesso</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyTelas">
                                        <tr class="empty-row">
                                            <td colspan="5">Carregando...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─────────────────── TAB: HISTÓRICO ─────────────────── -->
                <div class="tab-panel" id="panel-historico" role="tabpanel" style="flex-direction:column;">
                    <div class="table-scroll-area" style="flex:1; overflow-y:auto; padding:16px 16px 0;">
                        <div class="table-section">
                            <div class="table-wrap">
                                <table class="data-table" id="tableHistorico">
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Tela</th>
                                            <th>IP</th>
                                            <th>Horário</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyHistorico">
                                        <tr class="empty-row">
                                            <td colspan="4">Carregando...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="pagination" id="paginationHistorico">
                        <span class="pagination-info" id="paginInfoHistorico"></span>
                        <div class="pagination-controls" id="paginCtrlHistorico"></div>
                    </div>
                </div>

                <!-- ─────────────────── TAB: MAIS ATIVOS ─────────────────── -->
                <div class="tab-panel" id="panel-ativos" role="tabpanel">
                    <div class="chart-panel">
                        <div class="chart-section">
                            <div class="chart-section-title">
                                <i class="fa-solid fa-ranking-star"></i>
                                Top 10 Usuários por Acessos
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="chartAtivos"></canvas>
                            </div>
                        </div>
                        <div class="table-section">
                            <div class="table-wrap">
                                <table class="data-table" id="tableAtivos">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Usuário</th>
                                            <th class="col-right">Total Acessos</th>
                                            <th>Última Atividade</th>
                                            <th>Tela Mais Acessada</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyAtivos">
                                        <tr class="empty-row">
                                            <td colspan="5">Carregando...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /tabs-content -->
        </div><!-- /tabs-container -->

    </div><!-- /container -->

    <!-- Bottom-sheet trigger (tablet/mobile) -->
    <button class="filters-sheet-trigger" id="btnOpenFilters" type="button" aria-controls="filtersPanel" aria-expanded="false">
        <i class="fa-solid fa-sliders"></i>
    </button>

    <?php include '../css/modalSessao.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>

    <script src="<?php echo asset_url('script.js'); ?>"></script>
</body>

</html>