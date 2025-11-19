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
<?php
session_start();
include '../conexao.php'; // Inclui o arquivo de conexão com mysqli
include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>

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

            <label for="mes">Selecione o mês:</label>
            <select id="mes" onchange="buscarDados()">
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
        </div>

        <table id="tabelaProducao">
            <thead>
                <tr>
                    <th>Colaborador</th>
                    <th>Função</th>
                    <th>Quantidade</th>
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
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>

            <h2 id="total_producao">Total de produção por função <span id="mesSelecionadoFuncao">---</span></h2>

            <label id="labelMesFuncao" for="mesFuncao">Selecione o mês:</label>
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

            <table id="tabelaFuncao">
                <thead>
                    <tr>
                        <th>Processo</th>
                        <th>Quantidade</th>
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