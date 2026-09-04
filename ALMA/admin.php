<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/alma_helpers.php';
require_once __DIR__ . '/../conexaoMain.php';

if (empty($_SESSION['logado'])) {
    header('Location: ../index.html');
    exit;
}

$conn = conectarBanco();
if (!alma_can($conn, ALMA_CAP_LIBRARY_ADMIN)) {
    http_response_code(403);
    $conn->close();
    echo 'Você não possui a capacidade alma.administrar_biblioteca.';
    exit;
}
$conn->close();
$assetVersion = max((int) @filemtime(__DIR__ . '/alma.css'), (int) @filemtime(__DIR__ . '/admin.js'));
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ALMA - Biblioteca Oficial</title>
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="alma.css?v=<?php echo $assetVersion; ?>">
</head>

<body class="alma-page">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <main id="almaAdmin" class="alma-app alma-admin-app">
        <div class="alma-loading"><span></span>
            <p>Carregando Biblioteca Oficial ALMA...</p>
        </div>
    </main>
    <div class="alma-toast" id="almaToast" role="status" aria-live="polite" hidden></div>
    <script src="../script/sidebar.js"></script>
    <script src="admin.js?v=<?php echo $assetVersion; ?>"></script>
</body>

</html>