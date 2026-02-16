<?php
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

session_start();

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../index.html');
    exit();
}

include '../conexaoMain.php';

$conn = conectarBanco();
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Timeline de Obras</title>
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <main>
        <section class="page-header">
            <div>
                <h1>Timeline da Obra (Teste)</h1>
                <p>Visualização horizontal com arraste, por obra, tipo de imagem e função.</p>
            </div>
        </section>

        <section class="card filters-card">
            <div class="filters-grid">
                <div class="form-group">
                    <label for="obraSelect">Obra</label>
                    <select id="obraSelect">
                        <option value="">Selecione...</option>
                        <?php foreach ($obras as $obra): ?>
                            <option value="<?= (int) $obra['idobra']; ?>">
                                <?= htmlspecialchars($obra['nomenclatura'] ?: $obra['nome_obra']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="etapaSelect">Etapa</label>
                    <select id="etapaSelect" disabled>
                        <option value="">Todas</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tipoImagemSelect">Tipo de imagem</label>
                    <select id="tipoImagemSelect" disabled>
                        <option value="">Todos</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="funcaoSelect">Função</label>
                    <select id="funcaoSelect" disabled>
                        <option value="">Todas</option>
                    </select>
                </div>
            </div>

            <div class="meta-row">
                <span id="metaResumo">Selecione uma obra para carregar a timeline.</span>
            </div>
        </section>

        <section class="card timeline-card">
            <div id="emptyState" class="empty-state">Sem dados para exibir.</div>

            <div id="timelineContainer" class="timeline-container hidden">
                <div class="timeline-head">
                    <div class="row-label head-label">Item</div>
                    <div class="axis-scroll" id="axisScroll">
                        <div class="axis-track" id="axisTrack"></div>
                    </div>
                </div>

                <div class="timeline-body" id="timelineBody"></div>
            </div>
        </section>
    </main>

    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>
</body>

</html>