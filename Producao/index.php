<?php

session_start();

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produção - MVP</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script defer src="script.js"></script>
</head>

<body>
    <header>
        <button id="menuButton"><i class="fa-solid fa-bars"></i></button>
        <div id="menu" class="hidden">
            <a href="../inicio.php" id="tab-imagens">Página Principal</a>
            <a href="../main.php" id="tab-imagens">Visualizar tabela com imagens</a>
            <a href="../Pos-Producao/index.php">Lista Pós-Produção</a>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 3)): ?>
                <a href="../infoCliente/index.php">Informações clientes</a>
                <a href="../Acompanhamento/index.php">Acompanhamentos</a>
            <?php endif; ?>

            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 4)): ?>
                <a href="../Animacao/index.php">Lista Animação</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['nivel_acesso']) && ($_SESSION['nivel_acesso'] == 1)): ?>
                <a href="../Imagens/index.php">Lista Imagens</a>
                <a href="../Pagamento/index.php">Pagamento</a>
                <a href="../Obras/index.php">Obras</a>
            <?php endif; ?>

            <a href="../Metas/index.php">Metas e progresso</a>
            <a href="./index.php" style="font-weight:bold;">Produção (MVP)</a>
            <a id="calendar" class="calendar-btn" href="../Calendario/index.php">
                <i class="fa-solid fa-calendar-days"></i>
            </a>
        </div>
        <img src="../gif/assinatura_branco.gif" alt="Improov" style="width: 200px;">
    </header>

    <main>
        <section class="kpis">
            <div class="card kpi">
                <div class="kpi-header">
                    <h3>Imagens entregues (mês)</h3>
                    <span class="meta">Meta: <strong id="meta-mensal">100</strong></span>
                </div>
                <div class="kpi-value">
                    <span id="entregues-mes" class="value">0</span>
                    <span class="sep">/</span>
                    <span id="meta-mes" class="target">100</span>
                </div>
                <div class="progress">
                    <div id="progress-entregues" class="progress-bar" style="width: 0%"></div>
                </div>
                <div class="kpi-foot">
                    <span id="percentual-entregues">0%</span>
                    <span class="sub">Somente imagens ENTREGUES contam como produção real.</span>
                </div>
            </div>

            <div class="card kpi secondary">
                <div class="kpi-header">
                    <h3>Tarefas finalizadas (mês)</h3>
                    <span class="meta">Visão do todo</span>
                </div>
                <div class="kpi-value">
                    <span id="finalizadas-mes" class="value">0</span>
                </div>
                <div class="hint">Finalizadas != Entregues</div>
            </div>

            <div class="card kpi warning">
                <div class="kpi-header">
                    <h3>Próximas entregas</h3>
                    <span class="meta">Até 14 dias</span>
                </div>
                <div class="kpi-value">
                    <span id="prox-entregas" class="value">0</span>
                </div>
                <div class="hint"><i class="fa-regular fa-clock"></i> Inclui itens da tabela entregas_itens</div>
            </div>
        </section>

        <section class="grid-2">
            <div class="card">
                <div class="card-title">Produção do mês vs meta</div>
                <canvas id="chartMes"></canvas>
            </div>
            <div class="card">
                <div class="card-title">Status geral (itens)</div>
                <canvas id="chartStatus"></canvas>
            </div>
        </section>

        <section class="card">
            <div class="card-title">Progresso por função (metas ilusórias)</div>
            <canvas id="chartFuncoes"></canvas>
            <div class="legend-inline">
                <span class="dot delivered"></span> Entregues (produção real)
                <span class="dot done"></span> Finalizadas (visão do todo)
                <span class="dot goal"></span> Meta
            </div>
        </section>

        <section class="card">
            <div class="card-title">Produção dos últimos meses (entregues)</div>
            <canvas id="chartHistorico"></canvas>
        </section>

        <section class="card">
            <div class="card-title">Próximas entregas</div>
            <div class="table-wrap">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>Entrega</th>
                            <th>Item</th>
                            <th>Data prevista</th>
                            <th>Status</th>
                            <th>Dias p/ entrega</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-entregas"></tbody>
                </table>
            </div>
        </section>
    </main>

    <footer>
        <small>MVP com dados fictícios. Integração posterior: tabelas entrega, entregas_itens e funcao_imagem.</small>
    </footer>
</body>

</html>
