<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    flow_auth_redirect_to_login();
    exit();
}

$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Verificar se o usuário está logado


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
    <?= flow_auth_navigation_script() ?>
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
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
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

    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>&authReturn=1"></script>
    <script src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>
</body>

</html>