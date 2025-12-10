<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório Diário</title>
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="https://unpkg.com/intro.js/minified/introjs.min.css">
    <script src="https://unpkg.com/intro.js/minified/intro.min.js"></script>
    <link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="../css/modalSessao.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>

    <div class="container">

        <h1>Relatório Diário</h1>
        <button onclick="exportarPDF()">Exportar para PDF</button>

        <div id="listaHold" class="bloco">
            <h2>🔴 Imagens HOLD</h2>
            <ul id="holdObras"></ul>
        </div>

        <div id="painelDetalhes" class="painel-lateral">
            <button id="fecharPainel">Fechar</button>
            <div id="conteudoPainel"></div>
        </div>
        <div class="bloco">
            <h2>🟡 Funções TO-DO</h2>
            <ul id="listaTodo"></ul>
        </div>

        <div class="bloco">
            <h2>📝 Últimos Acompanhamentos</h2>
            <ul id="listaAcomp"></ul>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="../script/sidebar.js"></script>
    <script src="../script/controleSessao.js"></script>
    <script src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>
</body>

</html>