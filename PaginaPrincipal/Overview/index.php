<?php
require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

session_start();

$nivelAcesso   = intval($_SESSION['nivel_acesso']   ?? 0);
$colaboradorId = intval($_SESSION['idcolaborador']  ?? 0);
$nomeUsuario   = htmlspecialchars($_SESSION['nome_usuario'] ?? 'Colaborador', ENT_QUOTES);
$isGestor      = in_array($nivelAcesso, [1, 5]);

$mesAtual = (int) date('m');
$anoAtual = (int) date('Y');
$mesesPt  = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$mesLabel = $mesesPt[$mesAtual] . ' ' . $anoAtual;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Painel · <?php echo $mesLabel; ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../../css/stylePadrao.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../../css/modalSessao.css'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
</head>

<body>

    <?php include '../../sidebar.php'; ?>

    <!-- Dados da sessão para o JS -->
    <script>
        window.PAINEL = {
            colaboradorId: <?php echo $colaboradorId; ?>,
            nivelAcesso: <?php echo $nivelAcesso; ?>,
            nomeUsuario: <?php echo json_encode($nomeUsuario, JSON_UNESCAPED_UNICODE); ?>,
            isGestor: <?php echo $isGestor ? 'true' : 'false'; ?>,
            mesAtual: <?php echo $mesAtual; ?>,
            anoAtual: <?php echo $anoAtual; ?>
        };
    </script>

    <?php if ($isGestor): ?>
        <!-- ======================================================
     Visão do GESTOR  (nivel_acesso 1 ou 5)
     Este painel é para colaboradores. Redirecione para a
     sua visão gerencial.
====================================================== -->
        <div class="container" id="gestor-view">
            <header>
                <div class="brand">
                    <div class="logo">OV</div>
                    <div>
                        <h1>Visão Geral</h1>
                        <p class="sub">Esta página é o painel individual dos colaboradores</p>
                    </div>
                </div>
            </header>
            <div class="card" style="text-align:center;padding:40px 20px">
                <div style="font-size:40px;margin-bottom:12px">📊</div>
                <div style="font-size:18px;font-weight:700;margin-bottom:8px">Você é um gestor</div>
                <p style="color:var(--muted);margin-bottom:20px">Este painel é destinado aos colaboradores para acompanhar a produção pessoal.<br>Acesse a visão gerencial pela barra lateral.</p>
                <a href="../" class="btn primary-link">Voltar ao início</a>
            </div>
        </div>

    <?php else: ?>
        <!-- ======================================================
     Visão do COLABORADOR
====================================================== -->
        <div class="container" id="colab-dashboard">

            <!-- Header -->
            <header>
                <div class="brand">
                    <div class="logo">MP</div>
                    <div>
                        <h1>Meu Painel de Produção</h1>
                        <p class="sub">Sua produção mensal, valor a receber e desempenho por etapa</p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="pill user-pill">Olá, <?php echo $nomeUsuario; ?></div>
                    <select id="mes-seletor" class="pill mes-select" aria-label="Selecionar mês">
                        <option value="<?php echo $anoAtual . '-' . str_pad($mesAtual, 2, '0', STR_PAD_LEFT); ?>">
                            <?php echo $mesLabel; ?>
                        </option>
                    </select>
                </div>
            </header>

            <!-- KPI Cards -->
            <section class="kpi-grid" aria-label="Indicadores do mês">
                <div class="kpi-card kpi-novas">
                    <div class="kpi-icon"><i class="ri-file-add-line"></i></div>
                    <div class="kpi-body">
                        <div class="kpi-value" id="kpi-novas">–</div>
                        <div class="kpi-label">Novas no mês</div>
                    </div>
                </div>
                <div class="kpi-card kpi-valor">
                    <div class="kpi-icon"><i class="ri-money-dollar-circle-line"></i></div>
                    <div class="kpi-body">
                        <div class="kpi-value" id="kpi-valor">–</div>
                        <div class="kpi-label">Valor a receber</div>
                    </div>
                </div>
                <div class="kpi-card kpi-ajustes">
                    <div class="kpi-icon"><i class="ri-loop-left-line"></i></div>
                    <div class="kpi-body">
                        <div class="kpi-value" id="kpi-ajustes">–</div>
                        <div class="kpi-label">Média de ajustes / tarefa</div>
                    </div>
                </div>
            </section>

            <!-- Etapas -->
            <section class="dashboard-section">
                <div class="section-header">
                    <h2><i class="ri-bar-chart-2-line"></i> Desempenho por etapa</h2>
                </div>
                <div class="etapas-grid" id="etapas-grid">
                    <div class="etapa-skeleton"></div>
                    <div class="etapa-skeleton"></div>
                    <div class="etapa-skeleton"></div>
                </div>
            </section>

            <!-- Tabela de tarefas -->
            <section class="dashboard-section">
                <div class="section-header">
                    <h2><i class="ri-task-line"></i> Tarefas do mês</h2>
                    <span class="count-badge" id="tasks-count">–</span>
                </div>
                <div class="tasks-wrap">
                    <table class="tasks-table">
                        <thead>
                            <tr>
                                <th>Imagem</th>
                                <th>Obra</th>
                                <th>Etapa</th>
                                <th>Status</th>
                                <th class="col-right">Valor</th>
                                <th class="col-center">Pago?</th>
                                <th class="col-center">Ajustes</th>
                            </tr>
                        </thead>
                        <tbody id="tasks-body">
                            <tr>
                                <td colspan="7" class="empty-row">Carregando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Feedbacks / ajustes pendentes -->
            <section class="dashboard-section">
                <div class="section-header">
                    <h2><i class="ri-feedback-line"></i> Feedbacks pendentes de ajuste</h2>
                    <span class="count-badge count-danger" id="feedbacks-count">–</span>
                </div>
                <div class="feedback-grid" id="feedback-list">
                    <div class="empty-row" style="padding:16px;color:var(--muted)">Carregando...</div>
                </div>
            </section>

        </div><!-- /#colab-dashboard -->
    <?php endif; ?>

    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../../script/controleSessao.js'); ?>"></script>
</body>

</html>