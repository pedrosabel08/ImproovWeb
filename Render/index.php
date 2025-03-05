<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: index.html");
    exit();
}

include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">

    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Adicione os links para DataTables no seu HTML -->

    <title>Renders</title>
</head>

<body>
    <?php

    include '../sidebar.php';

    ?>
    <div class="container">
        <header>
            <img src="../gif/assinatura_preto.gif" alt="" style="width: 200px;">
        </header>
        <table id="renderTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome imagem</th>
                    <th>Status</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody id="renderList">
                <!-- Os renders serão carregados aqui via AJAX -->
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <form id="editForm">
                <input type="hidden" id="render_id">
                <p id="imagem_nome"></p>
                <div style="padding: 1rem 0;">
                    <label for="render_status">Status:</label>
                    <select id="render_status" name="render_status">
                        <option value="Não iniciado">Não iniciado</option>
                        <option value="Em andamento">Em andamento</option>
                        <option value="Finalizado">Finalizado</option>
                    </select>
                </div>
                <div class="buttons">
                    <button type="submit" id="salvar">Salvar</button>
                    <button id="deleteRender">Excluir Render</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>

</body>

</html>