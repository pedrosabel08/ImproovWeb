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
$obraId = (int) ($_GET['obra_id'] ?? 0);
$embedded = (string) ($_GET['embed'] ?? '') === '1';
$conn = conectarBanco();
$permissions = alma_permissions($conn);
$conn->close();
$assetVersion = max(
    (int) @filemtime(__DIR__ . '/alma.css'),
    (int) @filemtime(__DIR__ . '/alma.js'),
    (int) @filemtime(__DIR__ . '/alma-loader.css'),
    (int) @filemtime(__DIR__ . '/alma-loader.js')
);
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
    <link rel="stylesheet" href="alma-loader.css?v=<?php echo $assetVersion; ?>">
    <link rel="shortcut icon" href="../assets/logo.jpg" type="image/x-icon">
</head>

<body class="alma-page<?php echo $embedded ? ' alma-embedded' : ''; ?>">
    <?php if (!$embedded) {
        include __DIR__ . '/../sidebar.php';
    } ?>
    <main id="almaApp" class="alma-app" data-image-id="<?php echo $imageId; ?>"
        data-obra-id="<?php echo $obraId; ?>"
        data-can-edit="<?php echo !empty($permissions[ALMA_CAP_EDIT]) ? '1' : '0'; ?>"
        data-can-activate="<?php echo !empty($permissions[ALMA_CAP_ACTIVATE]) ? '1' : '0'; ?>">
        <div class="alma-content" id="almaContent" aria-busy="true"></div>

        <section class="alma-build-loader" id="almaLoading" data-state="idle"
            data-min-duration="2150" data-sequence-delay="200" data-pillar-duration="175"
            data-settle-duration="100" data-reveal-duration="380"
            aria-live="polite" aria-label="Carregando Direção Visual">
            <div class="alma-build-loader__noise" aria-hidden="true"></div>
            <div class="alma-build-loader__glow" aria-hidden="true"></div>

            <header class="alma-build-loader__header">
                <div class="alma-build-loader__brand">
                    <span>ALMA</span>
                    <i></i>
                    <small>Direção Visual</small>
                </div>
                <div class="alma-build-loader__live">
                    <span></span>
                    <small>Construindo</small>
                </div>
            </header>

            <div class="alma-build-loader__experience">
                <div class="alma-build-loader__intro">
                    <span class="alma-build-loader__eyebrow">Sistema visual em composição</span>
                    <h1>Carregando<br>Direção Visual<span>...</span></h1>
                    <p>Organizando referências e conectando os pilares do ALMA.</p>
                </div>

                <div class="alma-build-loader__stage" aria-hidden="true">
                    <svg class="alma-build-loader__connections" id="almaLoaderConnections"
                        viewBox="0 0 100 100" preserveAspectRatio="none"></svg>
                    <div class="alma-build-loader__nodes" id="almaLoaderPillars"></div>
                    <div class="alma-build-loader__board">
                        <span class="alma-build-loader__corner is-nw"></span>
                        <span class="alma-build-loader__corner is-ne"></span>
                        <span class="alma-build-loader__corner is-sw"></span>
                        <span class="alma-build-loader__corner is-se"></span>
                        <div class="alma-build-loader__board-grid" id="almaLoaderFragments"></div>
                        <div class="alma-build-loader__board-mark">
                            <i></i><span>ALMA / BUILD</span>
                        </div>
                    </div>
                </div>

                <footer class="alma-build-loader__footer">
                    <div class="alma-build-loader__status">
                        <span id="almaLoaderStatus">Preparando o espaço visual</span>
                        <strong id="almaLoaderPercent" aria-hidden="true">00%</strong>
                    </div>
                    <div class="alma-build-loader__progress" aria-hidden="true"><i></i></div>
                    <div class="alma-build-loader__signals" aria-hidden="true">
                        <span>Referências</span><span>Matéria</span><span>Luz</span><span>Narrativa</span>
                    </div>
                </footer>
            </div>
        </section>
    </main>

    <div class="alma-dialog-backdrop" id="almaDialog" hidden>
        <section class="alma-dialog" role="dialog" aria-modal="true" aria-labelledby="almaDialogTitle">
            <button class="alma-dialog-close" type="button" data-close-dialog aria-label="Fechar">×</button>
            <div id="almaDialogBody"></div>
        </section>
    </div>

    <div class="alma-toast" id="almaToast" role="status" aria-live="polite" hidden></div>
    <?php if (!$embedded): ?><script src="../script/sidebar.js"></script><?php endif; ?>
    <script src="alma-loader.js?v=<?php echo $assetVersion; ?>"></script>
    <script src="alma.js?v=<?php echo $assetVersion; ?>"></script>
</body>

</html>
