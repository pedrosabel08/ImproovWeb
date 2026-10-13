<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
if (($_SESSION['logado'] ?? false) !== true) {
    header('Location: ../index.html');
    exit;
}
$_SESSION['mapa_pavimento_csrf'] ??= bin2hex(random_bytes(32));
$canEdit = in_array((int) ($_SESSION['nivel_acesso'] ?? 0), [1, 2], true);
$embedded = ($_GET['embed'] ?? '') === '3d';
$conn = conectarBanco();
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$conn->close();
$version = max(filemtime(__DIR__ . '/script.js'), filemtime(__DIR__ . '/style.css'), filemtime(__DIR__ . '/../assets/js/components/planta-viewer.js'), filemtime(__DIR__ . '/../assets/js/components/planta-polygon-editor.js'), filemtime(__DIR__ . '/../assets/js/components/planta-layer-inspector.js'));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mapa arquitetônico · Flow</title>
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="../assets/css/components/planta-viewer.css?v=<?= $version ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="style.css?v=<?= $version ?>">
    <?php if ($embedded): ?><link rel="stylesheet" href="embedded.css?v=<?= filemtime(__DIR__ . '/embedded.css') ?>"><link rel="stylesheet" href="../assets/css/components/flow-alert.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"><?php endif; ?>
    <link rel="icon" href="../assets/logo.jpg">
</head>
<body class="mp-page<?= $embedded ? ' mp-embedded' : '' ?>">
<?php if (!$embedded) {
    include __DIR__ . '/../sidebar.php';
} ?>
<main id="mapaPavimento" class="mp-app" data-can-edit="<?= $canEdit ? '1' : '0' ?>"
    data-embedded="<?= $embedded ? '1' : '0' ?>"
    data-csrf="<?= htmlspecialchars($_SESSION['mapa_pavimento_csrf'], ENT_QUOTES, 'UTF-8') ?>"
    data-initial-subtipo="<?= (int) ($_GET['subtipo_id'] ?? 0) ?>" data-initial-legado="<?= (int) ($_GET['legado_id'] ?? 0) ?>" data-initial-obra="<?= (int) ($_GET['obra_id'] ?? 0) ?>" data-initial-pavimento="<?= (int) ($_GET['pavimento_id'] ?? 0) ?>">
    <header class="mp-header">
        <div><h1>Mapa arquitetônico</h1><p>Conecte os ambientes da planta às imagens da obra.</p></div>
        <button id="themeToggle" class="mp-icon-button" type="button" aria-label="Alternar tema" title="Alternar tema">◐</button>
    </header>
    <section class="mp-context" aria-label="Obra e pavimento">
        <label class="mp-field">Obra
            <select id="obraSelect"><option value="">Selecione a obra</option>
                <?php foreach ($obras as $obra): ?>
                    <option value="<?= (int) $obra['idobra'] ?>"><?= htmlspecialchars($obra['nomenclatura'] . ' — ' . $obra['nome_obra'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="mp-field mp-floor-field"><span id="pavimentoLabel">Pavimento / subtipo</span>
            <select id="pavimentoSelect" disabled><option value="">Selecione o subtipo</option></select>
        </label>
        <label class="mp-field mp-document-field">Documento arquitetônico
            <select id="documentSelect" disabled><option value="">Selecionar PDF…</option></select>
        </label>
        <?php if ($canEdit): ?><label id="uploadLabel" class="mp-button mp-secondary mp-upload is-disabled" aria-disabled="true"><span>+ Enviar PDF</span><input id="pdfInput" type="file" accept="application/pdf,.pdf" disabled></label><?php endif; ?>
        <div class="mp-page-choice"><span>Página</span><button id="choosePage" type="button" class="mp-button mp-secondary" disabled>Selecionar página</button></div>
    </section>
    <details id="legacySection" class="mp-legacy" hidden><summary>Configurações anteriores aguardando associação</summary><p>As plantas, demarcações e imagens foram preservadas. Selecione um subtipo acima e associe a configuração correspondente.</p><div id="legacyList"></div></details>
    <div id="pageMessage" class="mp-message" role="alert" hidden></div>
    <section id="referencePreparation" class="mp-preparation" aria-labelledby="preparationTitle" hidden>
        <div class="mp-preparation-heading"><h2 id="preparationTitle">Preparação da referência</h2><p id="layersStatus" aria-live="polite"></p><p id="cropStatus" aria-live="polite"></p></div>
        <?php if ($canEdit): ?><div class="mp-preparation-actions"><button id="configureLayers" type="button" class="mp-button mp-secondary">Configurar camadas</button><button id="defineCrop" type="button" class="mp-button mp-secondary">Definir área útil</button></div><?php endif; ?>
        <button id="inspectLayers" type="button" class="mp-button mp-secondary" aria-pressed="false">Inspecionar camada</button>
    </section>
    <section id="cropBar" class="mp-crop-bar" aria-labelledby="cropTitle" hidden>
        <div><h2 id="cropTitle">Definir área útil</h2><p>Clique nos vértices e feche no primeiro ponto. Arraste os pontos para editar; clique numa borda para inserir um vértice.</p></div>
        <div class="mp-crop-actions"><button id="cropUndo" type="button" class="mp-button mp-secondary">Desfazer ponto</button><button id="cropAddVertex" type="button" class="mp-button mp-secondary">Adicionar vértice</button><button id="cropRemoveVertex" type="button" class="mp-button mp-secondary">Excluir vértice</button><button id="cropRedraw" type="button" class="mp-button mp-secondary">Redesenhar</button><button id="fullPageCrop" type="button" class="mp-button mp-secondary">Limpar área</button><button id="cancelCrop" type="button" class="mp-button mp-secondary">Cancelar</button><button id="saveCrop" type="button" class="mp-button">Salvar área útil</button></div>
        <p id="cropPointCount" aria-live="polite"></p><p id="cropError" class="mp-form-error" role="alert" hidden></p>
    </section>
    <section class="mp-workbench">
        <div class="mp-plan">
            <header class="mp-plan-header"><div><h2 id="planTitle">Referência arquitetônica</h2><p id="planSubtitle">Documento e página por subtipo.</p></div><span id="environmentCount" class="mp-count" hidden></span></header>
 
                <div id="drawingBar" class="mp-drawing-bar" hidden><span id="drawingHint">Marque os vértices do ambiente.</span><div><button id="addVertex" type="button" class="mp-button mp-secondary">Adicionar vértice</button><button id="removeVertex" type="button" class="mp-button mp-secondary">Excluir vértice</button><button id="undoPoint" type="button" class="mp-button mp-secondary">Desfazer ponto</button><button id="finishPolygon" type="button" class="mp-button" disabled>Concluir contorno</button><button id="cancelDrawing" type="button" class="mp-button mp-secondary">Cancelar</button></div></div>
            <div class="mp-viewer-wrap">
                <div id="plantaViewport" class="pv-viewport" role="region" tabindex="0" aria-label="Planta do pavimento. Arraste para mover e use os botões para ampliar.">
                    <div class="pv-surface" hidden><canvas aria-label="Planta PDF"></canvas><svg xmlns="http://www.w3.org/2000/svg" aria-label="Ambientes demarcados"></svg></div>
                </div>
                <div id="emptyState" class="mp-empty"><svg viewBox="0 0 104 88" fill="none" aria-hidden="true"><path d="M14 70V16h75v54H14Z M14 44h29V16 M43 44v26 M43 44h46 M64 44V16" stroke="currentColor" stroke-width="2"/><path d="M43 55a11 11 0 0 1 11-11 M64 27a10 10 0 0 1 10-10" stroke="currentColor" stroke-width="1" stroke-dasharray="3 3"/><path d="M8 78h87 M8 75v6 M95 75v6" stroke="currentColor"/></svg><h3 id="emptyTitle">Comece pela obra</h3><p id="emptyDescription">Selecione a obra e o pavimento para visualizar ou enviar sua planta.</p></div>
                <div id="viewTools" class="mp-view-tools" role="group" aria-label="Visualização da planta" hidden>
                    <button id="zoomOut" type="button" aria-label="Diminuir zoom" title="Diminuir zoom">−</button><output id="zoomValue" aria-live="off">100%</output><button id="zoomIn" type="button" aria-label="Aumentar zoom" title="Aumentar zoom">+</button><span></span><button id="fitView" type="button" title="Ajustar à tela" aria-label="Ajustar à tela">⛶</button><button id="resetView" type="button" title="Restaurar visualização" aria-label="Restaurar visualização">↺</button><span></span><button id="rotatePage" type="button" title="Girar documento 90° no sentido horário" aria-label="Girar documento 90 graus no sentido horário">⟳</button>
                </div>
                <div id="busyOverlay" class="mp-busy" role="status" aria-live="polite" hidden><canvas data-thinking-orb data-orb-state="composing" data-orb-size="64" aria-label="Carregando"></canvas><p id="busyText">Carregando</p></div>
            </div>
            <footer class="mp-plan-footer"><span id="navigationHint">Arraste para mover · Role para ampliar</span><span>Referência visual</span></footer>
        </div>
        <aside class="mp-panel" aria-label="Ambientes do pavimento">
            <header class="mp-panel-header"><h2>Ambientes</h2><?php if ($canEdit): ?><button id="addEnvironment" type="button" class="mp-button" disabled>+ Adicionar ambiente</button><?php endif; ?></header>
            <div id="environmentList" class="mp-environment-list"><p class="mp-panel-empty">Os ambientes demarcados aparecerão aqui.</p></div>
            <section id="environmentDetails" class="mp-details" hidden aria-label="Informações do ambiente"></section>
        </aside>
    </section>
    <div id="tooltip" class="mp-tooltip" hidden role="tooltip"></div>
    <div id="toast" class="mp-toast" hidden role="status" aria-live="polite"></div>
</main>
<dialog id="pagesDialog" class="mp-dialog mp-pages-dialog" aria-labelledby="pagesDialogTitle"><header><div><h2 id="pagesDialogTitle">Selecionar página</h2><p id="pagesDocumentName" class="mp-help"></p></div><button type="button" data-close="pagesDialog" class="mp-icon-button" aria-label="Fechar">×</button></header><div class="mp-dialog-body"><p>Páginas em uso também podem ser compartilhadas por outros subtipos.</p><div id="pagesList" class="mp-pages-grid"></div><p id="pagesError" class="mp-form-error" role="alert" hidden></p></div></dialog>
<dialog id="reuseDialog" class="mp-dialog" aria-labelledby="reuseTitle"><form method="dialog"><header><h2 id="reuseTitle">Compartilhar página?</h2></header><div class="mp-dialog-body"><p id="reuseMessage"></p></div><footer><button value="cancel" class="mp-button mp-secondary">Cancelar</button><button value="confirm" class="mp-button">Utilizar também</button></footer></form></dialog>
<dialog id="environmentDialog" class="mp-dialog mp-environment-dialog" aria-labelledby="environmentDialogTitle"><form id="environmentForm">
    <header><h2 id="environmentDialogTitle">Novo ambiente</h2><button type="button" data-close="environmentDialog" class="mp-icon-button" aria-label="Fechar">×</button></header>
    <div class="mp-dialog-body"><label class="mp-field">Nome do ambiente<input id="environmentName" required maxlength="100" placeholder="Ex.: Brinquedoteca" autocomplete="off"></label>
        <div class="mp-image-heading"><h3>Imagens relacionadas</h3><span id="selectedImageCount">0 selecionadas</span></div>
        <label id="environmentImagesLabel" for="environmentImages" class="mp-sr-only">Buscar e selecionar imagens da obra</label>
        <div id="environmentImagesPicker" class="mp-image-picker"><select id="environmentImages" multiple aria-describedby="environmentImagesHelp"></select></div>
        <p id="imageOptionsEmpty" class="mp-panel-empty" hidden>Esta obra ainda não possui imagens cadastradas.</p>
        <p id="environmentImagesHelp" class="mp-help">Todas as imagens da obra estão disponíveis. Uma imagem pode ser vinculada a mais de um ambiente. Você também pode salvar sem imagens e relacioná-las depois.</p><p id="environmentError" class="mp-form-error" role="alert" hidden></p>
    </div><footer><button type="button" data-close="environmentDialog" class="mp-button mp-secondary">Cancelar</button><button id="saveEnvironment" type="submit" class="mp-button">Salvar ambiente</button></footer>
</form></dialog>
<dialog id="layersDialog" class="mp-dialog" aria-labelledby="layersDialogTitle"><form id="layersForm">
    <header><h2 id="layersDialogTitle">Camadas do PDF</h2><button type="button" data-close="layersDialog" class="mp-icon-button" aria-label="Fechar">×</button></header>
    <div class="mp-dialog-body"><p class="mp-help">Passe o mouse ou foque uma camada para isolá-la temporariamente na planta. Salvar aplica os checkboxes escolhidos.</p>
        <div class="mp-layer-actions"><button id="allLayers" type="button" class="mp-button mp-secondary">Marcar todos</button><button id="noLayers" type="button" class="mp-button mp-secondary">Desmarcar todos</button><button id="defaultLayers" type="button" class="mp-button mp-secondary">Restaurar padrão</button></div>
        <p class="mp-help">Padrão do mapa: todas as camadas visíveis.</p><div id="layerOptions" class="mp-layer-options"></div><p id="layersError" class="mp-form-error" role="alert" hidden></p>
    </div><footer><button type="button" data-close="layersDialog" class="mp-button mp-secondary">Cancelar</button><button id="saveLayers" type="submit" class="mp-button">Salvar camadas</button></footer>
</form></dialog>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="../assets/pdfjs/pdf.min.js"></script>
<script>pdfjsLib.GlobalWorkerOptions.workerSrc = '../assets/pdfjs/pdf.worker.min.js';</script>
<script src="../assets/js/thinking-orbs.js"></script>
<?php if ($embedded): ?><script src="../assets/js/components/flow-alert.js"></script><?php endif; ?>
<script src="../assets/js/components/planta-viewer.js?v=<?= $version ?>"></script>
<script src="../assets/js/components/planta-polygon-editor.js?v=<?= $version ?>"></script>
<script src="../assets/js/components/planta-layer-inspector.js?v=<?= $version ?>"></script>
<script src="script.js?v=<?= $version ?>"></script>
<?php if (!$embedded): ?><script src="../script/sidebar.js"></script><?php endif; ?>
<script src="../script/controleSessao.js"></script>
</body>
</html>
