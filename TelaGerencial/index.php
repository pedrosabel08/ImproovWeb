<?php
session_start();
include '../conexao.php'; // Inclui o arquivo de conexão com mysqli
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

// Initialize DB connection before using it (was causing undefined $conn)
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
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>



<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <title>Tela Gerencial</title>
</head>

<body>
    <?php

    include '../sidebar.php';

    ?>
    <div class="container">
        <header>
            <img src="../gif/assinatura_preto.gif" alt="" style="width: 200px;">
        </header>

        <div class="select">

            <h2>Total Produção por colaborador</h2>

            <div class="filtros-linha">
                <label for="mes">Mês:</label>
                <select id="mes" onchange="buscarDados(); buscarEntregasMes();">
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
                <select id="ano" onchange="buscarDados(); buscarEntregasMes();">
                    <?php
                    $anoAtual = (int) date('Y');
                    for ($a = $anoAtual; $a >= $anoAtual - 10; $a--) {
                        echo '<option value="' . $a . '">' . $a . '</option>';
                    }
                    ?>
                </select>

                <button id="gerar-relatorio" style="margin-left:12px;">Gerar relatório</button>
            </div>
        </div>

        <table id="tabelaProducao">
            <thead>
                <tr>
                    <th>Colaborador</th>
                    <th>Função</th>
                    <th>Quantidade</th>
                    <th>Mês anterior</th>
                    <th>Recorde de produção</th>
                </tr>
            </thead>
            <tbody>
                <!-- Dados virão via AJAX -->
            </tbody>
        </table>

        <div class="select">

            <!-- <select name="tipo" id="tipo" onchange="filtrarPorTipo()">
                <option value="mes_tipo" selected>Mês</option>
            
            <!-- Tabela de entregas por mês -->
            <h3 style="margin-top:18px;">Imagens entregues por mês <span id="mes_atual"></span></h3>
            <table id="tabelaEntregas">
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th>Quantidade de imagens entregues</th>
                        <th>Quantidade de plantas entregues</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>

            <h2 id="total_producao">Total de produção por função <span id="mesSelecionadoFuncao">---</span></h2>

            <div class="filtros-linha">
                <label id="labelMesFuncao" for="mesFuncao">Mês:</label>
                <select id="mesFuncao" onchange="buscarDadosFuncao()">
                    <option value="1">Janeiro</option>
                    <option value="2">Fevereiro</option>
                    <option value="3">Março</option>
                    <option value="4">Abril</option>
                    <option value="5">Maio</option>
                    <option value="6">Junho</option>
                    <option value="7">Julho</option>
                    <option value="8">Agosto</option>
                    <option value="9">Setembro</option>
                    <option value="10">Outubro</option>
                    <option value="11">Novembro</option>
                    <option value="12">Dezembro</option>
                </select>

                <label id="labelAnoFuncao" for="anoFuncao">Ano:</label>
                <select id="anoFuncao" onchange="buscarDadosFuncao()">
                    <?php
                    $anoAtual = (int) date('Y');
                    for ($a = $anoAtual; $a >= $anoAtual - 10; $a--) {
                        echo '<option value="' . $a . '">' . $a . '</option>';
                    }
                    ?>
                </select>
            </div>

            <table id="tabelaFuncao">
                <thead>
                    <tr>
                        <th>Processo</th>
                        <th>Quantidade</th>
                        <th>Mês anterior</th>
                        <th>Recorde de produção</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>


    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
</body>

</html>