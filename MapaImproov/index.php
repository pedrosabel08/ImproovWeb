<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (($_SESSION['logado'] ?? false) !== true) { flow_auth_redirect_to_login(); exit; }
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../config/mapa_improov.php';
$conn = conectarBanco();
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$conn->close();
$mapConfig = mi_map_config();
$version = max(filemtime(__DIR__ . '/mapa.js'), filemtime(__DIR__ . '/mapa.css'), filemtime(__DIR__ . '/mapa-cartography.mjs'));
?>
<!doctype html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php require __DIR__ . '/../includes/page-head.php'; ?>
    <?= flow_auth_navigation_script() ?>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('flow-mapa-theme') === 'light' ? 'light' : 'dark'; } catch {}</script>
    <link rel="stylesheet" href="../css/master.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="../assets/libs/maplibre-gl/5.13.0/maplibre-gl.css">
    <link rel="stylesheet" href="mapa.css?v=<?= $version ?>">
</head>
<body class="mi-page">
<?php include __DIR__ . '/../sidebar.php'; ?>
<main class="mi-app" id="mapaImproov" data-user="<?= (int) ($_SESSION['idusuario'] ?? 0) ?>">
    <header class="mi-header">
        <div class="mi-brand"><span class="mi-brand-icon" aria-hidden="true">◎</span><div><h1>Mapa Improov</h1><p>Empreendimentos conectados ao mundo</p></div></div>
        <span class="mi-internal">Uso interno · Flow</span>
        <button type="button" id="themeToggle" class="mi-icon" aria-label="Alternar tema" title="Alternar tema">◐</button>
    </header>
    <section class="mi-workspace" aria-label="Exploração geográfica dos empreendimentos">
        <div id="map" aria-label="Mapa interativo de empreendimentos"></div>
        <nav class="mi-map-toolbar" aria-label="Controles geográficos">
            <div class="mi-projections"><button type="button" id="globeMode" aria-pressed="true">◎ Globo 3D</button><button type="button" id="flatMode" aria-pressed="false">▤ Mapa</button></div>
            <button type="button" id="resetBrazil" title="Voltar ao enquadramento do Brasil">Brasil ↗</button>
            <button type="button" id="terrainToggle" aria-pressed="false" hidden>Relevo 3D</button>
        </nav>
        <button type="button" id="filtersToggle" class="mi-mobile-filters" aria-label="Buscar e filtrar empreendimentos" aria-controls="filtersPanel" aria-expanded="false">⌕ Buscar e filtrar</button>
        <aside class="mi-panel mi-filters" id="filtersPanel" aria-label="Pesquisa e filtros">
            <div class="mi-panel-heading"><h2>Empreendimentos</h2><button type="button" id="closeFilters" class="mi-icon" aria-label="Fechar filtros">×</button></div>
            <div class="mi-stats" aria-label="Indicadores dos resultados">
                <div><strong id="totalCount">—</strong><span>Acessíveis</span></div><div><strong id="locatedCount">—</strong><span>No mapa</span></div>
                <div><strong id="glbCount">—</strong><span>Com modelo 3D</span></div><div><strong id="missingCount">—</strong><span>Sem localização</span></div>
            </div>
            <label class="mi-field mi-search">Buscar empreendimento<input type="search" id="search" placeholder="Código, nome ou localização" autocomplete="off"></label>
            <div class="mi-filter-heading"><h3>Filtros</h3><button type="button" id="clearFilters" class="mi-text-button">Limpar filtros</button></div>
            <label class="mi-field">Status<select id="status"><option value="">Todos os status</option></select></label>
            <label class="mi-field">Modelo 3D<select id="model"><option value="">Todas as obras</option><option value="yes">Com modelo GLB</option><option value="no">Sem modelo GLB</option></select></label>
            <label class="mi-field" id="locationField" hidden>Localização cadastrada<select id="location"><option value="">Todas as localizações</option></select></label>
            <div class="mi-results-heading"><h3>Resultados</h3><span id="resultCount" aria-live="polite">Carregando</span></div>
            <div class="mi-results" id="results" aria-label="Resultados da pesquisa"></div>
        </aside>
        <aside class="mi-panel mi-details" id="detailsPanel" aria-labelledby="detailName" hidden tabindex="-1">
            <div class="mi-panel-heading"><span id="detailCode" class="mi-code"></span><button type="button" id="closeDetails" class="mi-icon" aria-label="Fechar detalhes">×</button></div>
            <h2 id="detailName"></h2><p id="detailLocation" class="mi-muted"></p><span id="detailStatus" class="mi-status"></span>
            <div class="mi-project-cover" aria-label="Empreendimento sem imagem de capa"><span aria-hidden="true">▥</span><strong id="coverCode"></strong><small>Imagem de capa não cadastrada</small></div>
            <dl class="mi-facts"><div><dt>Modelo 3D</dt><dd id="detailModel"></dd></div><div><dt>Localização no mapa</dt><dd id="detailCoordinates"></dd></div></dl>
            <a id="openViewer" class="mi-primary">Abrir visualização 3D ↗</a><p id="viewerReason" class="mi-muted" hidden></p>
            <a id="openProject" class="mi-secondary">Ver detalhes da obra</a>
        </aside>
        <div class="mi-loading" id="loading" role="status"><canvas data-thinking-orb data-orb-state="composing" data-orb-size="48" aria-label="Carregando empreendimentos"></canvas><span>Carregando empreendimentos</span></div>
        <div class="mi-notice" id="notice" role="status" hidden><span id="noticeText"></span><button type="button" id="retry" hidden>Tentar novamente</button></div>
        <div class="mi-map-notice" id="mapNotice" role="status" hidden><span id="mapNoticeText"></span><button type="button" id="retryMap">Tentar novamente</button></div>
        <div class="mi-map-caption"><span class="mi-dot"></span><span>Selecione um empreendimento no mapa ou na lista</span></div>
    </section>
</main>
<script type="application/json" id="mapConfig"><?= json_encode($mapConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="../script/sidebar.js"></script>
<script src="../script/controleSessao.js?authReturn=1"></script>
<script src="../assets/js/thinking-orbs.js"></script>
<script src="../assets/libs/maplibre-gl/5.13.0/maplibre-gl.js"></script>
<script type="module" src="mapa.js?v=<?= $version ?>"></script>
</body>
</html>
