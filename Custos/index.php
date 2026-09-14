<?php
require_once __DIR__ . '/custos_auth.php';
custos_auth();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$todas = obterObras($conn, 'all');
if (empty($_SESSION['custos_csrf'])) $_SESSION['custos_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['custos_csrf'];
$conn->close();
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Custos do Projeto · Flow</title>
    <script src="theme.js"></script>
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="style.css?v=2">
</head>

<body class="custos-page">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <?php include __DIR__ . '/view.php'; ?>
    <script src="script.js?v=2" defer></script>
    <script src="../script/sidebar.js" defer></script>
    <script src="../script/controleSessao.js" defer></script>
</body>

</html>