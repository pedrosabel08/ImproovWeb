<?php
require_once __DIR__ . '/../../config/session_bootstrap.php';
require_once __DIR__ . '/../../config/version.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../../index.html');
    exit();
}

if ((int) ($_SESSION['nivel_acesso'] ?? 0) !== 1) {
    http_response_code(403);
    echo 'Acesso restrito ao perfil gerencial.';
    exit();
}

$obras = [];
$obras_inativas = [];
$currentMonth = (int) date('m');
$currentYear = (int) date('Y');

require_once __DIR__ . '/../../conexao.php';
include '../../conexaoMain.php';

$idusuario = $_SESSION['idusuario'];
$nome_usuario = $_SESSION['nome_usuario'];
$idcolaborador = $_SESSION['idcolaborador'];
$nivel_acesso = $_SESSION['nivel_acesso'];

// Consulta para contar as tarefas pendentes
$sql_pendentes = "SELECT COUNT(*) as count_pendentes FROM funcao_imagem WHERE status <> 'Finalizado' AND colaborador_id = ?";
$stmt_pendentes = $conn->prepare($sql_pendentes);
$stmt_pendentes->bind_param("i", $idcolaborador);
$stmt_pendentes->execute();
$result_pendentes = $stmt_pendentes->get_result();
$row_pendentes = $result_pendentes->fetch_assoc();
$count_pendentes = $row_pendentes['count_pendentes'];

// Consulta para imagens pendentes
$sql_imagens = "SELECT 
        i.imagem_nome, 
        o.nome_obra ,
i.prazo
    FROM 
        imagens_cliente_obra i 
    JOIN 
        funcao_imagem f ON i.idimagens_cliente_obra = f.imagem_id 
    JOIN 
        obra o ON o.idobra = i.obra_id  -- Supondo que o nome da tabela da obra seja 'obra'
    WHERE 
        f.colaborador_id = ?
        AND f.status <> 'Finalizado'
		AND i.prazo IS NOT NULL
        LIMIT 10";
$stmt_imagens = $conn->prepare($sql_imagens);
$stmt_imagens->bind_param("i", $idcolaborador);
$stmt_imagens->execute();
$result_imagens = $stmt_imagens->get_result();

$sql_grafico = "SELECT COUNT(*) as imagens, SUM(valor) as total, MONTH(prazo) as mes from funcao_imagem WHERE colaborador_id = ? GROUP BY MONTH(prazo)";
$stmt_grafico = $conn->prepare($sql_grafico);
$stmt_grafico->bind_param("i", $idcolaborador);
$stmt_grafico->execute();
$result_grafico = $stmt_grafico->get_result();

// Consulta para o total de produção
$sql_total = "SELECT ROUND(SUM(fi.valor)) AS total_producao FROM funcao_imagem fi WHERE fi.colaborador_id = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("i", $idcolaborador);
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$row_total = $result_total->fetch_assoc();
$count_total = $row_total['total_producao'];

$stmt_pendentes->close();
$stmt_imagens->close();
$stmt_total->close();

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
    <title>Dashboard Operacional</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset_url('../../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
</head>

<body>
    <?php include __DIR__ . '/../../sidebar.php'; ?>

    <main class="op-main" data-current-month="<?php echo $currentMonth; ?>" data-current-year="<?php echo $currentYear; ?>">
        <header class="op-header">
            <div class="op-title-block">
                <h1>Dashboard Operacional</h1>
                <p>Visão de abastecimento e capacidade por função</p>
            </div>

            <form class="op-filters" id="opFilters">
                <label class="op-field">
                    <span>Mês</span>
                    <select id="filterMonth" name="mes" aria-label="Mês"></select>
                </label>
                <label class="op-field">
                    <span>Ano</span>
                    <input id="filterYear" name="ano" type="number" min="2020" max="2100" value="<?php echo $currentYear; ?>" aria-label="Ano">
                </label>
                <label class="op-field">
                    <span>Função</span>
                    <select id="filterFunction" name="funcao_id" aria-label="Função">
                        <option value="0">Todas</option>
                    </select>
                </label>
                <label class="op-field">
                    <span>Tipo</span>
                    <select id="filterType" name="tipo_imagem" aria-label="Tipo de imagem">
                        <option value="">Todos</option>
                    </select>
                </label>
                <button class="op-refresh" type="submit">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Atualizar</span>
                </button>
            </form>
        </header>

        <section class="op-kpi-grid" aria-label="Indicadores principais">
            <article class="op-kpi" data-tone="danger">
                <div class="op-kpi-head"><span>Abaixo do mínimo</span><i class="fa-solid fa-triangle-exclamation"></i></div>
                <strong id="kpiCritical">0</strong>
                <small>Menos de 40%</small>
            </article>
            <article class="op-kpi" data-tone="warning">
                <div class="op-kpi-head"><span>Funções em atenção</span><i class="fa-solid fa-circle-exclamation"></i></div>
                <strong id="kpiAttention">0</strong>
                <small>Entre 40% e 80%</small>
            </article>
            <article class="op-kpi" data-tone="success">
                <div class="op-kpi-head"><span>Funções saudáveis</span><i class="fa-solid fa-arrow-trend-up"></i></div>
                <strong id="kpiHealthy">0</strong>
                <small>Entre 80% e 130%</small>
            </article>
            <article class="op-kpi" data-tone="blue">
                <div class="op-kpi-head"><span>Funções com excesso</span><i class="fa-solid fa-layer-group"></i></div>
                <strong id="kpiExcess">0</strong>
                <small>Acima de 130%</small>
            </article>
            <article class="op-kpi" data-tone="purple">
                <div class="op-kpi-head"><span>Total na fila</span><i class="fa-solid fa-layer-group"></i></div>
                <strong id="kpiQueue">0</strong>
                <small>Planejadas + não iniciadas</small>
            </article>
            <article class="op-kpi" data-tone="orange">
                <div class="op-kpi-head"><span>Abastecimento médio</span><i class="fa-solid fa-chart-pie"></i></div>
                <strong id="kpiSupply">-</strong>
                <small>Fila atual / meta mensal</small>
            </article>
        </section>

        <section class="op-grid">
            <article class="op-panel op-table-panel">
                <div class="op-panel-head">
                    <h2>Abastecimento por função</h2>
                    <i class="fa-regular fa-circle-question" title="Abastecimento = fila total dividida pela meta mensal. A cobertura em dias aparece no detalhe."></i>
                </div>
                <div class="op-table-wrap">
                    <table class="op-table">
                        <colgroup>
                            <col class="op-col-function">
                            <col class="op-col-planned">
                            <col class="op-col-p00">
                            <col class="op-col-not-started">
                            <col class="op-col-queue">
                            <col class="op-col-goal">
                            <col class="op-col-production">
                            <col class="op-col-supply">
                            <col class="op-col-classification">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Função</th>
                                <th>Planejada</th>
                                <th>P00</th>
                                <th>Não iniciado</th>
                                <th>Total da fila operacional</th>
                                <th>Meta mensal</th>
                                <th>Em produção</th>
                                <th>Abastecimento</th>
                                <th>Classificação</th>
                            </tr>
                        </thead>
                        <tbody id="functionsBody">
                            <tr>
                                <td colspan="9" class="op-empty">Carregando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="op-side">
                <article class="op-panel op-alert-panel">
                    <div class="op-panel-head">
                        <h2>Alertas operacionais</h2>
                        <span id="alertCount">0</span>
                    </div>
                    <div id="alertsList" class="op-alert-list"></div>
                </article>

                <article class="op-panel">
                    <div class="op-panel-head">
                    <h2>Tendência de consumo</h2>
                        <span>mês selecionado</span>
                    </div>
                    <div class="op-chart-wrap">
                        <canvas id="trendChart" aria-label="Tendência de consumo"></canvas>
                    </div>
                </article>
            </aside>
        </section>

        <section class="op-bottom-grid" hidden aria-hidden="true">
            <article class="op-panel">
                <div class="op-panel-head">
                    <h2>Menor abastecimento</h2>
                </div>
                <div id="coverageRanking" class="op-ranking"></div>
            </article>

            <article class="op-panel">
                <div class="op-panel-head">
                    <h2>Funções com maior fila</h2>
                </div>
                <div id="queueRanking" class="op-ranking"></div>
            </article>

            <article class="op-panel">
                <div class="op-panel-head">
                    <h2>Recomendações inteligentes</h2>
                    <span>por abastecimento</span>
                </div>
                <div id="recommendationsList" class="op-recommendations"></div>
            </article>
        </section>

        <footer class="op-footer">
            <i class="fa-regular fa-clock"></i>
            <span id="lastUpdate">Dados ainda não carregados</span>
        </footer>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?php echo asset_url('script.js'); ?>" defer></script>
    <script src="<?php echo asset_url('../../script/sidebar.js'); ?>"></script>
</body>

</html>
