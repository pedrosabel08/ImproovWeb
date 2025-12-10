<?php
session_start();
// $nome_usuario = $_SESSION['nome_usuario'];

include '../conexaoMain.php';
include '../conexao.php';

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
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Flow | Track</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="../css/styleSidebar.css" />
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <div class="container">

        <header class="ft-header">
            <h1>Imagens em Finalização (P00 / R00)</h1>
            <div class="ft-filters">
                <select id="filterObra">
                    <option value="">Todas as obras</option>
                </select>
                <select id="filterTipoImagem">
                    <option value="">Todos os tipos</option>
                </select>
                <select id="filterFinalizador">
                    <option value="">Todos os finalizadores</option>
                </select>
                <select id="filterEtapa">
                    <option value="">Todas etapas</option>
                    <option value="P00">P00</option>
                    <option value="R00">R00</option>
                </select>
                <select id="filterStatusFuncao">
                    <option value="">Todos status</option>
                </select>
                <button id="btnLimpar">Limpar</button>
            </div>
            <div id="ft-kpis" class="ft-kpis" aria-live="polite">
                <div class="kpi total"><strong>Total:</strong> <span id="kpiTotal">0</span></div>
                <div class="kpi p00"><strong>P00:</strong> <span id="kpiP00">0</span></div>
                <div class="kpi r00"><strong>R00:</strong> <span id="kpiR00">0</span></div>
                <div class="kpi statusSummary"><strong>Por status:</strong> <span id="kpiStatus">-</span></div>
            </div>
        </header>

        <main class="ft-main">
            <section class="ft-col">
                <h2>P00</h2>
                <div id="colP00" class="ft-cards"></div>
            </section>
            <section class="ft-col">
                <h2>R00</h2>
                <div id="colR00" class="ft-cards"></div>
            </section>
        </main>

        <template id="cardTemplate">
            <article class="ft-card">
                <div class="ft-card-title">
                    <span class="ft-imagem-nome"></span>
                </div>
                <div class="ft-card-meta">
                    <div><strong>Finalizador:</strong> <span class="ft-finalizador"></span></div>
                    <div><strong>Prazo:</strong> <span class="ft-prazo"></span></div>
                    <div><strong>Status:</strong> <span class="ft-status"></span></div>
                    <div><strong>Obs.:</strong> <span class="ft-observacao"></span></div>
                </div>
            </article>
        </template>
    </div>
    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
</body>

</html>