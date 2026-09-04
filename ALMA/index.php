<?php
require_once __DIR__ . '/../config/version.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/alma_helpers.php';
require_once __DIR__ . '/../conexaoMain.php';

if (empty($_SESSION['logado'])) {
    header('Location: ../index.html');
    exit;
}

$imageId = (int) ($_GET['imagem_id'] ?? 0);
$conn = conectarBanco();
$permissions = alma_permissions($conn);
$conn->close();
$assetVersion = max((int) @filemtime(__DIR__ . '/alma.css'), (int) @filemtime(__DIR__ . '/alma.js'));
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ALMA - Direção Visual</title>
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="alma.css?v=<?php echo $assetVersion; ?>">
</head>

<body class="alma-page">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <main id="almaApp" class="alma-app" data-image-id="<?php echo $imageId; ?>"
        data-can-edit="<?php echo !empty($permissions[ALMA_CAP_EDIT]) ? '1' : '0'; ?>"
        data-can-activate="<?php echo !empty($permissions[ALMA_CAP_ACTIVATE]) ? '1' : '0'; ?>">
        <div class="alma-loading" id="almaLoading"><span></span>
            <p>Carregando Direção Visual...</p>
        </div>
    </main>

    <div class="alma-dialog-backdrop" id="almaDialog" hidden>
        <section class="alma-dialog" role="dialog" aria-modal="true" aria-labelledby="almaDialogTitle">
            <button class="alma-dialog-close" type="button" data-close-dialog aria-label="Fechar">×</button>
            <div id="almaDialogBody"></div>
        </section>
    </div>

    <div class="alma-toast" id="almaToast" role="status" aria-live="polite" hidden></div>
    <script src="../script/sidebar.js"></script>
    <script src="alma.js?v=<?php echo $assetVersion; ?>"></script>
</body>

</html>