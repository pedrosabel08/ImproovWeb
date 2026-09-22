<?php
require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/version.php';
require_once dirname(__DIR__, 2) . '/includes/flow-motion-assets.php';

if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../../index.html');
    exit;
}

$backgrounds = static function (string $theme): array {
    $directory = dirname(__DIR__, 2) . '/assets/bg/' . $theme;
    $files = glob($directory . '/*.{jpg,jpeg,png,webp,avif}', GLOB_BRACE) ?: [];
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return array_map(static fn (string $file): string => '../../assets/bg/' . $theme . '/' . rawurlencode(basename($file)), $files);
};

$userName = trim((string) ($_SESSION['nome_usuario'] ?? ''));
$userPhoto = trim((string) ($_SESSION['foto_colaborador'] ?? ''));
$initial = $userName !== '' ? mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8')) : 'F';
$backgroundPayload = json_encode(['dark' => $backgrounds('dark'), 'light' => $backgrounds('light')], JSON_UNESCAPED_SLASHES);
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101114">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('../../css/master.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('../../css/styleSidebar.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('../../PaginaPrincipal/Home/home.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <title>Home · Improov Flow</title>
</head>

<body class="flow-home" data-user-name="<?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>" data-backgrounds="<?php echo htmlspecialchars((string) $backgroundPayload, ENT_QUOTES, 'UTF-8'); ?>">
    <?php include dirname(__DIR__, 2) . '/sidebar.php'; ?>
    <div class="home-background" data-home-animate="background" aria-hidden="true"></div>
    <div class="home-scrim" aria-hidden="true"></div>

    <main class="home-shell">
        <header class="home-header">
            <div>
                <h1 id="homeSalutation" data-home-animate="greeting">Olá, <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>.</h1>
                <p id="homeDate" data-home-animate="date">Carregando data</p>
            </div>
            <div class="home-header__right">
                <p class="home-quote" data-home-animate="quote">Disciplina hoje,<br>resultados amanhã.</p><button class="home-theme-toggle" id="themeToggle" data-home-animate="control" type="button" aria-label="Alternar tema"><i class="ri-sun-line"></i></button><a class="home-avatar" data-home-animate="control" href="../../infos.php" aria-label="Abrir perfil"><?php if ($userPhoto !== ''): ?><img src="<?php echo htmlspecialchars($userPhoto, ENT_QUOTES, 'UTF-8'); ?>" alt=""><?php else: ?><span><?php echo htmlspecialchars($initial); ?></span><?php endif; ?></a>
            </div>
        </header>

        <div class="home-grid" id="homeContent" data-has-current="pending" aria-busy="true">
            <section class="home-card home-attention" data-home-card="attention" aria-labelledby="attentionTitle">
                <header class="card-heading">
                    <h2 id="attentionTitle">Precisa de atenção <span id="attentionCount" data-home-attention-count>–</span></h2><a href="../../inicio.php" aria-label="Ver todas as tarefas"><i class="ri-arrow-right-s-line"></i></a>
                </header>
                <div class="attention-list home-skeleton-list" id="attentionList"></div>
                <a class="card-footer-link" href="../../inicio.php">Ver todas <i class="ri-arrow-right-s-line"></i></a>
            </section>
            <section class="home-card home-resume" data-home-card="resume" aria-labelledby="workTitle">
                <header class="card-heading">
                    <h2 id="workTitle">Continue de onde parou</h2><i class="ri-more-2-fill" aria-hidden="true"></i>
                </header>
                <div id="currentWork" class="home-skeleton-current"></div>
            </section>
            <section class="home-card home-shortcuts" data-home-card="shortcuts" aria-labelledby="shortcutsTitle">
                <header class="card-heading">
                    <h2 id="shortcutsTitle">Acessos rápidos</h2><i class="ri-arrow-right-s-line" aria-hidden="true"></i>
                </header>
                <div class="shortcut-grid home-skeleton-list" id="shortcutGrid" aria-live="polite"></div>
            </section>
            <section class="home-card home-next" data-home-card="next" aria-labelledby="nextTitle">
                <header class="card-heading">
                    <h2 id="nextTitle">A seguir</h2><a href="../../Calendario/">Ver agenda</a>
                </header>
                <div class="next-list" id="nextList"></div>
            </section>
            <section class="home-card home-progress" data-home-card="progress" aria-labelledby="progressTitle">
                <header class="card-heading">
                    <h2 id="progressTitle">Este mês</h2><i class="ri-bar-chart-line" aria-hidden="true"></i>
                </header>
                <div id="performancePanel" class="performance-panel home-skeleton-current"></div>
            </section>
        </div>
        <p class="home-footnote"><strong>Flow</strong><span></span>Pessoas, projetos e resultados em movimento.</p>
    </main>
    <?php flow_motion_assets('../../'); ?>
    <script src="<?php echo htmlspecialchars(asset_url('../../PaginaPrincipal/Home/home.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(asset_url('../../script/sidebar.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>

</html>
