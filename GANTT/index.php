<?php
session_start();
$nome_usuario = $_SESSION['nome_usuario'];

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
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GANTT</title>
    <script defer src="script.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <div class="container">

        <h2>GANTT - <span id="nomenclatura"></span></h2>
        <select name="opcao" id="opcao_obra">
            <?php foreach ($obras as $obra): ?>
                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div id="tabela-container">
            <table id="gantt"></table>
        </div>
    </div>

    <div id="colaboradorModal" class="modal" style="display:none;">
        <div class="modal-content">
            <label for="colaboradorInput">ID do Colaborador:</label>
            <select name="colaborador_id" id="colaborador_id">
                <?php foreach ($colaboradores as $colab): ?>
                    <option value="<?= htmlspecialchars($colab['idcolaborador']); ?>">
                        <?= htmlspecialchars($colab['nome_colaborador']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button id="confirmarBtn">Atribuir</button>
        </div>
    </div>

    <script src="../script/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>

</html>