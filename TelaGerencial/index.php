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

            <h2>Total Produção - Mês: <span id="mesSelecionado">Abril</span></h2>

            <label for="mes">Selecione o mês:</label>
            <select id="mes" onchange="buscarDados()">
                <option value="01">Janeiro</option>
                <option value="02">Fevereiro</option>
                <option value="03">Março</option>
                <option value="04" selected>Abril</option>
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
                    <th>Valor Total</th>
                    <th>Data Pagamento</th>
                    <th>Quantidade</th>
                    <th>Mês Anterior</th>
                    <th>Recorde de Produção</th>
                </tr>
            </thead>
            <tbody>
                <!-- Dados virão via AJAX -->
            </tbody>
        </table>

        <div class="select">

            <select name="tipo" id="tipo" onchange="filtrarPorTipo()">
                <option value="mes_tipo" selected>Mês</option>
                <option value="semana_tipo">Semana</option>
                <option value="dia_tipo">Dia Anterior</option>
            </select>

            <h2>Total de produção mês: <span id="mesSelecionadoFuncao">---</span></h2>

            <label for="mesFuncao">Selecione o mês:</label>
            <select id="mesFuncao" onchange="buscarDadosFuncao()">
                <option value="1">Janeiro</option>
                <option value="2">Fevereiro</option>
                <option value="3">Março</option>
                <option value="4" selected>Abril</option>
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
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- conteúdo JS -->
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2"><strong>Valor total:</strong></td>
                        <td id="valorTotal"><strong>R$ 0,00</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>


    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
</body>

</html>