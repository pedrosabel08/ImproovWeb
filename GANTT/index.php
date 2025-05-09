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
    <title>Tabela de Imagens e Etapas</title>
    <script defer src="script.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">

</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <div class="container">

        <h2>GANTT - <span id="nomenclatura"></span></h2>
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
</body>

</html>