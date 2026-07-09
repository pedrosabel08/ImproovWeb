<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php', __DIR__ . '/../config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../index.html');
    exit();
}

$loteId = isset($_GET['lote_id']) && is_numeric($_GET['lote_id']) ? (int) $_GET['lote_id'] : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('planejamento.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <title>Planejamento da Pré-Alteração</title>
</head>

<body data-lote-id="<?php echo htmlspecialchars((string) $loteId, ENT_QUOTES, 'UTF-8'); ?>">
    <?php include '../sidebar.php'; ?>

    <main class="pa-plan-shell">
        <header class="pa-plan-header">
            <div>
                <a class="pa-plan-back" href="index.php"><i class="fa-solid fa-arrow-left"></i> Pré-Alteração</a>
                <span class="pa-plan-eyebrow">Planejamento visual</span>
                <h1 id="planTitle">Carregando lote</h1>
                <p id="planSubtitle">Organize grupos, responsáveis e dependências antes da execução da Alteração.</p>
            </div>
            <div class="pa-plan-actions">
                <button type="button" class="btn btn-secondary" id="btnAutoGenerate"><i class="fa-solid fa-wand-magic-sparkles"></i> Gerar base</button>
                <button type="button" class="btn btn-secondary" id="btnAutoLayout"><i class="fa-solid fa-diagram-project"></i> Organizar</button>
                <button type="button" class="btn btn-secondary" id="btnValidate"><i class="fa-solid fa-circle-check"></i> Validar</button>
                <button type="button" class="btn btn-primary" id="btnSave"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                <button type="button" class="btn btn-success" id="btnPublish"><i class="fa-solid fa-bullhorn"></i> Publicar</button>
            </div>
        </header>

        <section class="pa-plan-status" id="planStatusBar" aria-live="polite"></section>

        <section class="pa-plan-workspace">
            <aside class="pa-plan-sidebar">
                <div class="pa-panel-head">
                    <div>
                        <span>Imagens do lote</span>
                        <strong id="imageCount">0 imagens</strong>
                    </div>
                    <button type="button" id="btnAddGroup" title="Criar grupo"><i class="fa-solid fa-folder-plus"></i></button>
                </div>
                <div class="pa-image-list" id="imageList"></div>
            </aside>

            <section class="pa-canvas-shell">
                <div class="pa-canvas-toolbar">
                    <div>
                        <button type="button" id="btnFit"><i class="fa-solid fa-expand"></i> Enquadrar</button>
                        <button type="button" id="btnAddGate"><i class="fa-solid fa-flag"></i> Novo gate</button>
                    </div>
                    <span id="canvasHint">Arraste elementos para ajustar o layout.</span>
                </div>
                <div id="dependencyCanvas" class="pa-canvas"></div>
            </section>

            <aside class="pa-plan-properties">
                <div class="pa-panel-head">
                    <div>
                        <span>Propriedades</span>
                        <strong id="propertyTitle">Nada selecionado</strong>
                    </div>
                </div>
                <div id="propertyPanel" class="pa-property-panel"></div>

                <form id="dependencyForm" class="pa-dependency-form">
                    <h3>Nova dependência</h3>
                    <label>
                        <span>Origem</span>
                        <select id="depOrigin"></select>
                    </label>
                    <label>
                        <span>Condição</span>
                        <select id="depCondition">
                            <option value="APROVADA">Aprovada</option>
                            <option value="FINALIZADA">Finalizada</option>
                        </select>
                    </label>
                    <label>
                        <span>Destino</span>
                        <select id="depTarget"></select>
                    </label>
                    <label>
                        <span>Observação</span>
                        <textarea id="depNote" rows="2" placeholder="Opcional"></textarea>
                    </label>
                    <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-link"></i> Criar dependência</button>
                </form>

                <div id="validationPanel" class="pa-validation-panel"></div>
            </aside>
        </section>
    </main>

    <script src="<?php echo asset_url('../assets/vendor/dagre/dagre.min.js'); ?>"></script>
    <script src="<?php echo asset_url('../assets/vendor/cytoscape/cytoscape.min.js'); ?>"></script>
    <script src="<?php echo asset_url('../assets/vendor/cytoscape/cytoscape-dagre.js'); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
    <script src="<?php echo asset_url('planejamento.js'); ?>"></script>
</body>

</html>
