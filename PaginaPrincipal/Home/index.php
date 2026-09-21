<?php
require_once dirname(__DIR__, 2) . '/config/session_bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/version.php';

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
    <title>Home · Improov Flow</title>
</head>
<body class="flow-home" data-user-name="<?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>" data-backgrounds="<?php echo htmlspecialchars((string) $backgroundPayload, ENT_QUOTES, 'UTF-8'); ?>">
    <?php include dirname(__DIR__, 2) . '/sidebar.php'; ?>
    <div class="home-background" aria-hidden="true"></div><div class="home-scrim" aria-hidden="true"></div>

    <main class="home-shell">
        <header class="home-header">
            <div><h1 id="homeSalutation">Olá, <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>.</h1><p id="homeDate">Carregando data</p></div>
            <div class="home-header__right"><p class="home-quote">Disciplina hoje,<br>resultados amanhã.</p><button class="home-theme-toggle" id="themeToggle" type="button" aria-label="Alternar tema"><i class="ri-sun-line"></i></button><a class="home-avatar" href="../../infos.php" aria-label="Abrir perfil"><?php if ($userPhoto !== ''): ?><img src="<?php echo htmlspecialchars($userPhoto, ENT_QUOTES, 'UTF-8'); ?>" alt=""><?php else: ?><span><?php echo htmlspecialchars($initial); ?></span><?php endif; ?></a></div>
        </header>

        <div class="home-grid" id="homeContent" aria-busy="true">
            <section class="home-card home-attention" aria-labelledby="attentionTitle"><header class="card-heading"><h2 id="attentionTitle">Precisa de atenção <span id="attentionCount">–</span></h2><a href="../../inicio.php" aria-label="Ver todas as tarefas"><i class="ri-arrow-right-s-line"></i></a></header><div class="attention-list home-skeleton-list" id="attentionList"></div><a class="card-footer-link" href="../../inicio.php">Ver todas <i class="ri-arrow-right-s-line"></i></a></section>
            <section class="home-card home-resume" aria-labelledby="workTitle"><header class="card-heading"><h2 id="workTitle">Continue de onde parou</h2><i class="ri-more-2-fill" aria-hidden="true"></i></header><div id="currentWork" class="home-skeleton-current"></div></section>
            <section class="home-card home-shortcuts" aria-labelledby="shortcutsTitle"><header class="card-heading"><h2 id="shortcutsTitle">Acessos rápidos</h2><i class="ri-arrow-right-s-line" aria-hidden="true"></i></header><div class="shortcut-grid"><a href="../../Projetos/"><i class="ri-folder-3-line"></i><strong>Projetos</strong><span>Ver todos</span><b><i class="ri-arrow-right-s-line"></i></b></a><a href="../../FlowReview/"><i class="ri-checkbox-circle-line"></i><strong>Aprovações</strong><span>Pendências</span><b><i class="ri-arrow-right-s-line"></i></b></a><a href="../../ALMA/"><i class="ri-box-3-line"></i><strong>ALMA</strong><span>Biblioteca</span><b><i class="ri-arrow-right-s-line"></i></b></a><a href="../../Render/"><i class="ri-image-line"></i><strong>Render</strong><span>Fila e histórico</span><b><i class="ri-arrow-right-s-line"></i></b></a></div></section>
            <section class="home-card home-next" aria-labelledby="nextTitle"><header class="card-heading"><h2 id="nextTitle">A seguir</h2><a href="../../Calendario/">Ver agenda</a></header><div class="next-list" id="nextList"></div></section>
            <section class="home-card home-progress" aria-labelledby="progressTitle"><header class="card-heading"><h2 id="progressTitle">Este mês</h2><i class="ri-bar-chart-line" aria-hidden="true"></i></header><div id="performancePanel" class="performance-panel home-skeleton-current"></div></section>
        </div>
        <p class="home-footnote"><strong>Flow</strong><span></span>Pessoas, projetos e resultados em movimento.</p><div class="home-mascot-slot" aria-hidden="true"></div>
    </main>
    <script src="<?php echo htmlspecialchars(asset_url('../../PaginaPrincipal/Home/home.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(asset_url('../../script/sidebar.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
