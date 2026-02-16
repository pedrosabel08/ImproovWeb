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

session_start();

include '../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

$idusuario = $_SESSION['idusuario'] ?? null;
$tela_atual = basename($_SERVER['PHP_SELF']);

if ($idusuario) {
    $sql2 = "UPDATE logs_usuarios SET tela_atual = ?, ultima_atividade = NOW() WHERE usuario_id = ?";
    $stmt2 = $conn->prepare($sql2);
    if ($stmt2) {
        $stmt2->bind_param("si", $tela_atual, $idusuario);
        $stmt2->execute();
        $stmt2->close();
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

include '../conexaoMain.php';
$connMain = conectarBanco();
$obras = obterObras($connMain);
$obras_inativas = obterObras($connMain, 1);
$connMain->close();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset_url('../css/styleSidebar.css'); ?>" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" />
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">

    <title>Flow Referências</title>
    <link rel="stylesheet" href="<?php echo asset_url('../css/modalSessao.css'); ?>">
</head>

<body>

    <?php include '../sidebar.php'; ?>

    <div class="container">
        <div class="header">
            <h1>📌 Flow Referências</h1>
            <button class="btn-upload" id="btnUpload">+ Novo Upload</button>
        </div>

        <div class="filters">
            <select id="filter_axis">
                <option value="">Todos os eixos</option>
            </select>

            <select id="filter_category">
                <option value="">Todas as categorias</option>
            </select>

            <select id="filter_subcategory">
                <option value="">Todas as subcategorias</option>
            </select>

            <select id="filter_ext">
                <option value="">Todos os tipos</option>
            </select>
        </div>

        <div class="tabela">
            <table class="tabelaRefs">
                <thead>
                    <tr>
                        <th>Arquivo</th>
                        <th>Eixo</th>
                        <th>Categoria</th>
                        <th>Subcategoria</th>
                        <th>Tipo</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <h2>Novo Upload</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <label>Eixo</label>
                <select name="axis_id" id="axisSelect" required>
                    <option value="">-- Selecione --</option>
                </select>

                <label>Categoria</label>
                <select name="category_id" id="categorySelect" required>
                    <option value="">-- Selecione --</option>
                </select>

                <label>Subcategoria</label>
                <select name="subcategory_id" id="subcategorySelect" required>
                    <option value="">-- Selecione --</option>
                </select>

                <div class="hint" id="tipoHint" style="display:none"></div>

                <label>Arquivo</label>
                <input type="file" name="arquivos[]" id="arquivosInput" multiple required />

                <label>Descrição</label>
                <textarea name="descricao" rows="3"></textarea>

                <div class="buttons">
                    <button type="button" class="btn-close" id="closeModal">Cancelar</button>
                    <button type="submit" class="btn-submit">Enviar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script src="<?php echo asset_url('../script/sidebar.js'); ?>"></script>

    <script src="<?php echo asset_url('../script/controleSessao.js'); ?>"></script>
</body>

</html>