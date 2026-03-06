<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
$__root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
foreach ([$__root . '/flow/ImproovWeb/config/version.php', $__root . '/ImproovWeb/config/version.php'] as $__p) {
    if ($__p && is_file($__p)) {
        require_once $__p;
        break;
    }
}
unset($__root, $__p);

// session_start();

include '../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

$nome_usuario = $_SESSION['nome_usuario'];
$idcolaborador = $_SESSION['idcolaborador'];

$idusuario = $_SESSION['idusuario'];
$tela_atual = basename($_SERVER['PHP_SELF']);
// Use DB server time for ultima_atividade to avoid clock/timezone mismatches
// $ultima_atividade = date('Y-m-d H:i:s');

// We already extracted needed session values; close the session to release the lock
// before performing heavier DB work below.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Use MySQL NOW() so the database records its own current timestamp
$sql2 = "UPDATE logs_usuarios 
         SET tela_atual = ?, ultima_atividade = NOW()
         WHERE usuario_id = ?";
$stmt2 = $conn->prepare($sql2);

if (!$stmt2) {
    die("Erro no prepare: " . $conn->error);
}

// 'si' indica os tipos: string, integer
$stmt2->bind_param("si", $tela_atual, $idusuario);

if (!$stmt2->execute()) {
    die("Erro no execute: " . $stmt2->error);
}
$stmt2->close();

include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$obras_inativas = obterObras($conn, 1);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();

// Pre-fetch tipo_imagem for filter dropdown
$conn3 = conectarBanco();
$tipos = [];
$resT = $conn3->query("SELECT id_tipo_imagem, nome FROM tipo_imagem ORDER BY nome ASC");
if ($resT) {
    while ($r = $resT->fetch_assoc()) $tipos[] = $r;
}
$conn3->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    <title>Flow Drive</title>
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <div class="container">

        <!-- FM Header -->
        <div class="fm-header">
            <div class="fm-header-left">
                <h1 class="fm-title"><i class="ri-folder-5-line"></i> Flow Drive</h1>
                <div class="storage-widget" id="storageWidget">
                    <svg class="storage-donut" viewBox="0 0 36 36" width="52" height="52">
                        <circle class="donut-track" cx="18" cy="18" r="15.9" fill="none" stroke-width="3" />
                        <circle class="donut-fill" id="storageFill" cx="18" cy="18" r="15.9" fill="none" stroke-width="3"
                            stroke-dasharray="0 100" stroke-linecap="round" transform="rotate(-90 18 18)" />
                    </svg>
                    <div class="storage-info">
                        <span class="storage-used" id="storageUsed">—</span>
                        <span class="storage-label">de 200 GB</span>
                    </div>
                </div>
            </div>
            <div class="fm-header-right">
                <div class="fm-search-wrap">
                    <i class="ri-search-line"></i>
                    <input type="text" id="fm-search" placeholder="Buscar arquivo ou projeto...">
                </div>

                <select id="filter_obra" class="fm-select">
                    <option value="">Todos os projetos</option>
                    <?php foreach ($obras as $obra): ?>
                        <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="filter_tipo" class="fm-select">
                    <option value="">Tipo de imagem</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= htmlspecialchars($t['nome']); ?>"><?= htmlspecialchars($t['nome']); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="filter_tipo_arquivo" class="fm-select">
                    <option value="">Tipo de arquivo</option>
                    <option value="DWG">DWG</option>
                    <option value="PDF">PDF</option>
                    <option value="SKP">SKP</option>
                    <option value="IMG">IMG</option>
                    <option value="IFC">IFC</option>
                    <option value="Outros">Outros</option>
                </select>

                <button class="btn-upload" id="btnUpload">
                    <i class="ri-upload-cloud-2-line"></i> Upload
                </button>
            </div>
        </div>

        <!-- Arquivos Recentes -->
        <section class="fm-section">
            <div class="fm-section-header">
                <span class="fm-section-title">Arquivos Recentes</span>
            </div>
            <div class="recent-scroll" id="recentFiles"></div>
        </section>

        <!-- Categorias -->
        <section class="fm-section">
            <div class="fm-section-header">
                <span class="fm-section-title">Categorias</span>
                <button class="fm-clear-filter" id="clearCatFilter" style="display:none;">
                    <i class="ri-close-line"></i> Limpar filtro
                </button>
            </div>
            <div class="category-grid" id="categoryCards"></div>
        </section>

        <!-- Todos os Arquivos -->
        <section class="fm-section">
            <div class="fm-section-header">
                <span class="fm-section-title">Todos os Arquivos</span>
                <span class="fm-count" id="totalCount">&mdash;</span>
            </div>
            <div class="tabela">
                <table id="tabelaArquivos" class="tabelaArquivos">
                    <thead>
                        <tr>
                            <th class="th-icon"></th>
                            <th class="th-sortable" data-col="nome_interno">Nome <i class="ri-arrow-up-down-line sort-icon"></i></th>
                            <th class="th-sortable" data-col="projeto">Projeto <i class="ri-arrow-up-down-line sort-icon"></i></th>
                            <th>Categoria</th>
                            <th class="th-sortable" data-col="tipo">Tipo <i class="ri-arrow-up-down-line sort-icon"></i></th>
                            <th class="th-sortable" data-col="tamanho">Tamanho <i class="ri-arrow-up-down-line sort-icon"></i></th>
                            <th class="th-sortable" data-col="recebido_em">Data <i class="ri-arrow-up-down-line sort-icon"></i></th>
                            <th class="statusTh">Status</th>
                            <th class="th-actions">A&ccedil;&otilde;es</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>

    </div>
    <!-- Modal Upload -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">

            <!-- Modal header -->
            <div class="modal-header">
                <div class="modal-header-left">
                    <span class="modal-icon"><i class="ri-upload-cloud-2-line"></i></span>
                    <div>
                        <h2>Novo Upload</h2>
                        <p class="modal-subtitle">Arraste arquivos ou preencha os campos abaixo</p>
                    </div>
                </div>
                <button type="button" class="modal-close-x" id="closeModal" aria-label="Fechar"><i class="ri-close-line"></i></button>
            </div>

            <form id="uploadForm" enctype="multipart/form-data">

                <!-- Drop zone -->
                <div class="drop-zone" id="dropZone">
                    <i class="ri-inbox-archive-line drop-zone-icon"></i>
                    <span class="drop-zone-text">Arraste arquivos aqui</span>
                    <span class="drop-zone-sub">ou clique para selecionar</span>
                    <input id="arquivoFile" type="file" name="arquivos[]" multiple required class="drop-zone-input">
                    <div class="drop-zone-preview" id="dropZonePreview"></div>
                </div>

                <!-- 2-column grid for form fields -->
                <div class="modal-grid">

                    <div class="modal-field">
                        <label>Projeto</label>
                        <select name="obra_id" required>
                            <option value="">— Selecione —</option>
                            <?php foreach ($obras as $obra): ?>
                                <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="modal-field">
                        <label>Categoria</label>
                        <select name="tipo_categoria" required>
                            <option value="1">Arquitetônico</option>
                            <option value="2">Referências</option>
                            <option value="3">Paisagismo</option>
                            <option value="4">Luminotécnico</option>
                            <option value="5">Estrutural</option>
                            <option value="6">Alterações</option>
                            <option value="7">Ângulo definido</option>
                        </select>
                    </div>

                    <div class="modal-field">
                        <label>Tipo de Arquivo</label>
                        <select name="tipo_arquivo" required>
                            <option value="">— Selecione —</option>
                            <option value="DWG">DWG</option>
                            <option value="PDF">PDF</option>
                            <option value="SKP">SKP</option>
                            <option value="IMG">IMG</option>
                            <option value="IFC">IFC</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>

                    <div class="modal-field" id="fieldSufixo" style="display:none;">
                        <label>Sufixo <span class="field-hint">máx. 2 palavras com _</span></label>
                        <select name="sufixo" id="sufixoSelect" style="width:100%"></select>
                    </div>

                    <div class="modal-field modal-field--full" id="perFileSufixoContainer" style="display:none;"></div>

                    <div class="modal-field modal-field--full" style="margin-bottom: 15px;">
                        <label>Tipo de Imagem <span class="field-hint">Segure Ctrl para selecionar múltiplos</span></label>
                        <select name="tipo_imagem[]" multiple size="4" required style="overflow-y: hidden; min-height: 100%;">
                            <option value="Fachada">Fachada</option>
                            <option value="Imagem Interna">Interna</option>
                            <option value="Imagem Externa">Externa</option>
                            <option value="Unidade">Unidades</option>
                            <option value="Planta Humanizada">Plantas Humanizadas</option>
                        </select>
                    </div>

                    <div class="modal-field modal-field--full" id="refsSkpModo" style="display:none;">
                        <label>Modo de envio</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="refsSkpModo" value="geral" checked>
                                <span>Enviar geral</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="refsSkpModo" value="porImagem">
                                <span>Enviar por imagem</span>
                            </label>
                        </div>
                    </div>

                    <div class="modal-field modal-field--full" id="referenciasContainer" style="max-height:40vh;overflow-y:auto;"></div>

                    <div class="modal-field modal-field--full">
                        <label>Descrição <span class="field-hint">opcional</span></label>
                        <textarea name="descricao" rows="2" placeholder="Observações sobre o arquivo…"></textarea>
                    </div>

                    <div class="modal-field">
                        <label>Data de Recebimento</label>
                        <input type="date" name="data_recebido" value="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <div class="modal-field modal-field--middle">
                        <label class="checkbox-label">
                            <input type="checkbox" name="flag_substituicao" value="1">
                            <span>Substituir existente</span>
                        </label>
                    </div>

                </div><!-- /.modal-grid -->

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="closeModal2">Cancelar</button>
                    <button type="submit" class="btn-primary"><i class="ri-upload-2-line"></i> Enviar arquivos</button>
                </div>

            </form>
        </div>
    </div>

    <!-- Context menu (shared) -->
    <div id="ctxMenu" class="ctx-menu" style="display:none;"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="<?php echo asset_url('../script/notificacoes.js'); ?>"></script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>

    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>