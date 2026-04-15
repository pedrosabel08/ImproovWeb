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

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);
// Use DB server time for ultima_atividade to avoid clock/timezone mismatches
// $ultima_atividade = date('Y-m-d H:i:s');

// We already extracted needed session values; close the session to release the lock
// before performing heavier DB work below.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Carrega conexão com o banco antes de executar atualizações de logs
include '../conexaoMain.php';
$conn = conectarBanco();

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

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);

$conn->close();
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" />
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <title>Tela Gerencial</title>
</head>

<body>
    <?php

    include '../sidebar.php';

    ?>
    <div class="container">
        <div class="page-header">
            <div class="page-header-left">
                <img src="../gif/assinatura_preto.gif" class="page-header-logo" id="gif" style="height:36px; opacity:0.85" />
                <h1 class="page-title">Tela Gerencial</h1>
            </div>
            <div class="filtros-linha">
                <label for="mes">Mês:</label>
                <select id="mes" onchange="refreshAll()">
                    <option value="01">Janeiro</option>
                    <option value="02">Fevereiro</option>
                    <option value="03">Março</option>
                    <option value="04">Abril</option>
                    <option value="05">Maio</option>
                    <option value="06">Junho</option>
                    <option value="07">Julho</option>
                    <option value="08">Agosto</option>
                    <option value="09">Setembro</option>
                    <option value="10">Outubro</option>
                    <option value="11">Novembro</option>
                    <option value="12">Dezembro</option>
                </select>
                <label for="ano">Ano:</label>
                <select id="ano" onchange="refreshAll()">
                    <?php
                    $anoAtual = (int) date('Y');
                    for ($a = $anoAtual; $a >= $anoAtual - 10; $a--) {
                        echo '<option value="' . $a . '">' . $a . '</option>';
                    }
                    ?>
                </select>
                <button id="gerar-relatorio">Gerar relatório</button>
            </div>
        </div>

        <!-- Cards de resumo -->
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-title">Total produção</div>
                <div id="totalProducao" class="card-value">—</div>
            </div>
            <div class="card card-green">
                <div class="card-title">Pagas</div>
                <div id="totalPagas" class="card-value">—</div>
            </div>
            <div class="card card-orange">
                <div class="card-title">Não pagas</div>
                <div id="totalNaoPagas" class="card-value">—</div>
            </div>
            <div class="card card-blue">
                <div class="card-title">Período</div>
                <div id="cardPeriodo" class="card-value card-value--sm">—</div>
            </div>
        </div>

        <!-- Gráficos -->
        <!-- <div class="dashboard-charts">
            <div class="chart-card">
                <h4>Produção por colaborador</h4>
                <canvas id="chartColaborador" role="img" aria-label="Produção por colaborador"></canvas>
            </div>
            <div class="chart-card">
                <h4>Produção por função</h4>
                <canvas id="chartFuncao" role="img" aria-label="Produção por função"></canvas>
            </div>
            <div class="chart-card">
                <h4>Entregas por status</h4>
                <canvas id="chartEntregas" role="img" aria-label="Entregas por status"></canvas>
            </div>
        </div> -->

        <!-- Grid principal: 2 colunas, 2 linhas -->
        <div class="tables-main-grid">
            <!-- Linha 1: tabela de colaboradores — span 2 colunas -->
            <div class="table-block full-span">
                <h2 class="section-title">Produção por colaborador</h2>
                <div class="table-scroll">
                    <table id="tabelaProducao">
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th id="thFuncaoProducao">Função</th>
                                <th>Quantidade</th>
                                <th>Pagas</th>
                                <th>Não pagas</th>
                                <th>Mês anterior</th>
                                <th>Recorde</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Linha 2, col 1: imagens entregues -->
            <div class="table-block">
                <h3 class="section-title">Imagens entregues <span id="mes_atual"></span></h3>
                <div class="table-scroll">
                    <table id="tabelaEntregas">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Imagens entregues</th>
                                <th>Plantas entregues</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Linha 2, col 2: produção por função -->
            <div class="table-block">
                <h3 class="section-title">Produção por função</h3>
                <div class="table-scroll">
                    <table id="tabelaFuncao">
                        <thead>
                            <tr>
                                <th>Processo</th>
                                <th>Quantidade</th>
                                <th>Pagas</th>
                                <th>Não pagas</th>
                                <th>Mês anterior</th>
                                <th>Recorde</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


    <div id="modalImagensOverlay" class="imagens-overlay" aria-hidden="true">
        <div class="imagens-panel" role="dialog" aria-modal="true" aria-labelledby="imagensTitulo">
            <div class="imagens-header">
                <h3 id="imagensTitulo"></h3>
                <button id="fecharModalImagens" type="button" class="imagens-fechar">Fechar</button>
            </div>
            <div id="imagensBody" class="imagens-body"></div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="<?php echo asset_url('dashboard-utils.js'); ?>"></script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
    <?php include '../css/modalSessao.php'; ?>
</body>

</html>