<?php
session_start();
$nome_usuario = $_SESSION['nome_usuario'];

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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">

    <title>Colaborador</title>
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <main>
        <table id="usuarios">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome Usuário</th>
                    <th>Cargos</th>
                    <th>Ativo</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </main>

    <div class="modal" id="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <form id="form">
                <input type="hidden" id="idcolaborador" name="idcolaborador">

                <label for="nome">Nome</label>
                <input type="text" id="nome_usuario" name="nome_usuario">

                <label for="cargos">Cargos</label>
                <input type="text" id="cargo" name="cargo" placeholder="Digite um cargo..." autocomplete="off">
                <div id="suggestions" class="dropdown"></div>

                <button type="submit">Salvar</button>
            </form>
        </div>
    </div>


    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
</body>

</html>