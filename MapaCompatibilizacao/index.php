<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';

// --- Auth ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../index.html');
    exit();
}

$nivelAcesso = (int) ($_SESSION['nivel_acesso'] ?? 0);
$idColaborador = (int) ($_SESSION['idcolaborador'] ?? 0);
$podEditar = in_array($nivelAcesso, [1, 2]);

// --- Obras para a sidebar e para o select ---
$conn           = conectarBanco();
$obras          = obterObras($conn)    ?? [];   // status_obra = 0 → ativas
$obras_inativas = obterObras($conn, 1) ?? [];   // status_obra = 1 → inativas (sidebar)
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Compatibilização</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" />
</head>

<body>

    <?php include __DIR__ . '/../sidebar.php'; ?>

    <script>
        // Configurações passadas ao JS
        const NIVEL_ACESSO = <?= json_encode($nivelAcesso) ?>;
        const POD_EDITAR = <?= json_encode($podEditar) ?>;
        const ID_COLABORADOR = <?= json_encode($idColaborador) ?>;
        const BASE_URL = '<?= rtrim((strpos($_SERVER['REQUEST_URI'], '/flow/ImproovWeb/') !== false ? '/flow/ImproovWeb' : '/ImproovWeb'), '/') ?>/MapaCompatibilizacao';
    </script>

    <!-- ================================================================= LAYOUT -->
    <div class="mc-layout">

        <!-- ---- Painel Lateral ---- -->
        <aside class="mc-sidebar">
            <h2 class="mc-title"><i class="fa-solid fa-map"></i> Mapa de Compatibilização</h2>

            <!-- Seleção de obra -->
            <div class="mc-field-group">
                <label for="selectObra">Obra</label>
                <select id="selectObra">
                    <option value="">— Selecione —</option>
                    <?php foreach ($obras as $obra): ?>
                        <option value="<?= (int) $obra['idobra'] ?>">
                            <?= htmlspecialchars($obra['nomenclatura'] . ' – ' . $obra['nome_obra']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Info da planta ativa -->
            <div id="plantaInfo" class="mc-planta-info hidden">
                <span id="plantaVersao"></span>
            </div>

            <!-- Barra de progresso -->
            <div id="progressoWrap" class="mc-progresso-wrap hidden">
                <label>Conclusão</label>
                <div class="mc-progress-bar">
                    <div id="progressoBar" class="mc-progress-fill"></div>
                </div>
                <span id="progressoTexto"></span>
            </div>

            <!-- Aviso de imagens sem marcação -->
            <div id="avisoNaoMarcadas" class="mc-aviso hidden">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span id="avisoNaoMarcadasTexto"></span>
            </div>

            <!-- Ações (apenas gestor/arquiteto) -->
            <?php if ($podEditar): ?>
                <div class="mc-acoes">
                    <button id="btnUploadPlanta" class="mc-btn mc-btn-secondary" title="Enviar nova versão da planta">
                        <i class="fa-solid fa-upload"></i> Nova Planta
                    </button>
                    <!-- Input de arquivo oculto -->
                    <input type="file" id="inputPlanta" accept="image/png,image/jpeg" style="display:none">

                    <button id="btnToggleEdicao" class="mc-btn mc-btn-primary hidden" title="Ativar/desativar modo edição">
                        <i class="fa-solid fa-pen"></i> Modo Edição
                    </button>
                </div>
            <?php endif; ?>

            <!-- Instrução modo edição -->
            <div id="instrucaoEdicao" class="mc-instrucao hidden">
                <p><b>Modo Edição ativo</b></p>
                <p>Clique na planta para adicionar vértices do polígono.<br>
                    Clique perto do <b>primeiro ponto</b> para fechar o ambiente.</p>
                <p>Clique em um polígono existente para <b>editar</b> ou <b>excluir</b>.</p>
                <button id="btnCancelarDesenho" class="mc-btn mc-btn-danger hidden">
                    <i class="fa-solid fa-xmark"></i> Cancelar desenho
                </button>
            </div>

            <!-- Zoom controls -->
            <div id="zoomControls" class="mc-zoom-controls hidden">
                <button id="btnZoomIn" title="Zoom +"><i class="fa-solid fa-plus"></i></button>
                <button id="btnZoomReset" title="Resetar zoom"><i class="fa-solid fa-compress"></i></button>
                <button id="btnZoomOut" title="Zoom -"><i class="fa-solid fa-minus"></i></button>
            </div>

            <!-- Legenda de cores -->
            <div class="mc-legenda">
                <span class="mc-legenda-item"><span class="mc-legenda-cor" style="background:#2ecc71"></span>Finalizado</span>
                <span class="mc-legenda-item"><span class="mc-legenda-cor" style="background:#f1c40f"></span>Em andamento</span>
                <span class="mc-legenda-item"><span class="mc-legenda-cor" style="background:#ecf0f1;border:1px solid #bbb"></span>Sem status</span>
            </div>
        </aside>

        <!-- ---- Área Central ---- -->
        <main class="mc-main">
            <!-- Estado vazio -->
            <div id="emptyState" class="mc-empty">
                <i class="fa-solid fa-map-location-dot"></i>
                <p>Selecione uma obra para visualizar o mapa.</p>
            </div>

            <!-- Container da planta -->
            <div id="plantaOuter" class="mc-planta-outer hidden">
                <!-- Wrapper que recebe transform (zoom/pan) -->
                <div id="plantaWrapper" class="mc-planta-wrapper">
                    <img id="plantaImg" class="mc-planta-img" src="" alt="Planta baixa">
                    <!-- SVG overlay: viewBox em 0–100 para coordenadas percentuais -->
                    <svg id="plantaSvg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"
                        preserveAspectRatio="none" class="mc-planta-svg">
                    </svg>
                </div>
            </div>
        </main>
    </div>

    <!-- ================================================================= TOOLTIP -->
    <div id="mcTooltip" class="mc-tooltip hidden"></div>

    <!-- ================================================================= MODAL VÍNCULO -->
    <div id="modalVinculo" class="mc-modal hidden" role="dialog" aria-modal="true" aria-labelledby="modalVinculoTitulo">
        <div class="mc-modal-content">
            <div class="mc-modal-header">
                <h3 id="modalVinculoTitulo"><i class="fa-solid fa-link"></i> Vincular Ambiente</h3>
                <button id="btnFecharModal" class="mc-modal-close" aria-label="Fechar">&times;</button>
            </div>
            <div class="mc-modal-body">
                <div class="mc-field-group">
                    <label for="inputNomeAmbiente">Nome do Ambiente <span class="required">*</span></label>
                    <input type="text" id="inputNomeAmbiente" placeholder="Ex: Suite Master, Sala de Estar…"
                        maxlength="100">
                </div>
                <div class="mc-field-group">
                    <label for="selectImagem">Imagem vinculada</label>
                    <select id="selectImagem">
                        <option value="">— Nenhuma (apenas marcar) —</option>
                    </select>
                </div>
                <div id="imagemVinculadaInfo" class="mc-imagem-info hidden"></div>
            </div>
            <div class="mc-modal-footer">
                <button id="btnCancelarVinculo" class="mc-btn mc-btn-secondary">Cancelar</button>
                <button id="btnSalvarVinculo" class="mc-btn mc-btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================= MODAL EDITAR MARCAÇÃO -->
    <div id="modalEditar" class="mc-modal hidden" role="dialog" aria-modal="true" aria-labelledby="modalEditarTitulo">
        <div class="mc-modal-content">
            <div class="mc-modal-header">
                <h3 id="modalEditarTitulo"><i class="fa-solid fa-pen-to-square"></i> Editar Marcação</h3>
                <button id="btnFecharModalEditar" class="mc-modal-close">&times;</button>
            </div>
            <div class="mc-modal-body">
                <input type="hidden" id="editarMarcacaoId">
                <div class="mc-field-group">
                    <label for="editarNomeAmbiente">Nome do Ambiente</label>
                    <input type="text" id="editarNomeAmbiente" maxlength="100">
                </div>
                <div class="mc-field-group">
                    <label for="editarSelectImagem">Imagem vinculada</label>
                    <select id="editarSelectImagem">
                        <option value="">— Nenhuma —</option>
                    </select>
                </div>
            </div>
            <div class="mc-modal-footer">
                <button id="btnDeletarMarcacao" class="mc-btn mc-btn-danger">
                    <i class="fa-solid fa-trash"></i> Excluir
                </button>
                <button id="btnCancelarEditar" class="mc-btn mc-btn-secondary">Cancelar</button>
                <button id="btnSalvarEditar" class="mc-btn mc-btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================= SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
</body>

</html>