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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">


    <title>GANTT por Colaborador</title>
</head>

<body>


    <?php

    include '../sidebar.php';

    ?>
    <div class="container">


        <h2 id="tituloGantt">GANTT - <span id="colabNome"></span></h2>

        <table id="gantt">
            <thead></thead>
            <tbody></tbody>
        </table>


        <script src="scriptColab.js"></script>
        <script src="../script/sidebar.js"></script>

</body>

</html>