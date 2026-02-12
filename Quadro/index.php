<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">

    <title>Quadro</title>
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
</head>

<body>

    <?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

    include '../conexao.php';
    require_once __DIR__ . '/../config/session_bootstrap.php';
    
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

    $conn->close();
    ?>

    <?php

    include '../sidebar.php';

    ?>
    <div id="quadro-container" class="container"></div>

    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>