<?php
session_start();

include '../conexao.php';
include '../conexaoMain.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Tela de custos</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
    type="image/x-icon">
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>

    <div class="container">
        <h1>Selecionar Imagem/Obra</h1>

        <label for="opcao">Obra:</label>
        <select id="selectImagem">
            <?php foreach ($obras as $obra): ?>
                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <table id="tabelaComercial">
            <thead>
                <tr>
                    <th>Número do Contrato</th>
                    <th>Nome da imagem</th>
                    <th>Valor</th>
                    <th>Imposto</th>
                    <th>Valor com Imposto</th>
                    <th>Comissão Comercial</th>
                    <th>Valor Comissão Comercial</th>
                    <th>Valor Comercial Liquido</th>
                    <th>Valor Produção Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="9" style="text-align: center;">Selecione uma imagem para ver os dados</td>
                </tr>
            </tbody>
        </table>

        <canvas id="graficoGeral" width="400" height="200"></canvas>
        <canvas id="graficoDetalhado" width="400" height="300" style="margin-top: 30px;"></canvas>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</body>

</html>