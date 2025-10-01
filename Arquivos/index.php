<?php
session_start();

include '../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../index.html");
    exit();
}

$nome_usuario = $_SESSION['nome_usuario'];
$idcolaborador = $_SESSION['idcolaborador'];


include '../conexaoMain.php';

$conn = conectarBanco();

$clientes = obterClientes($conn);
$obras = obterObras($conn);
$colaboradores = obterColaboradores($conn);
$status_imagens = obterStatusImagens($conn);
$funcoes = obterFuncoes($conn);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <title>Improov+Flow</title>
</head>

<body>

    <?php

    include '../sidebar.php';

    ?>
    <div class="container">
        <div class="header">
            <h1>📂 Arquivos Recebidos</h1>
            <button class="btn-upload" id="btnUpload">+ Novo Upload</button>
        </div>

        <!-- Filtros -->
        <div class="filters">
            <select>
                <option>Projeto</option>
                <option>Obra 1</option>
                <option>Obra 2</option>
            </select>
            <select>
                <option>Tipo de Imagem</option>
                <option>Fachada</option>
                <option>Interna</option>
            </select>
            <select>
                <option>Tipo de Arquivo</option>
                <option>DWG</option>
                <option>PDF</option>
            </select>
            <select>
                <option>Status</option>
                <option>Atualizado</option>
                <option>Antigo</option>
                <option>Pendente</option>
            </select>
        </div>

        <div class="tabela">
            <!-- Tabela -->
            <table class="tabelaArquivos">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Projeto</th>
                        <th>Tipo Imagem</th>
                        <th class="arquivoTh">Arquivo</th>
                        <th class="statusTh">Status</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Modal Upload -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <h2>Novo Upload</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <label>Projeto</label>
                <select name="obra_id" required>
                    <option value="">-- Selecione --</option>
                    <?php foreach ($obras as $obra): ?>
                        <option value="<?= $obra['idobra']; ?>"><?= htmlspecialchars($obra['nomenclatura']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Categoria</label>
                <select name="tipo_categoria" required>
                    <option value="1">Arquitetônico</option>
                    <option value="2">Referências</option>
                    <option value="3">Paisagismo</option>
                    <option value="4">Luminotécnico</option>
                    <option value="5">Estrutural</option>
                </select>

                <label>Tipo de Imagem</label>
                <select name="tipo_imagem[]" multiple size="5" required>
                    <option value="Fachada">Fachada</option>
                    <option value="Imagem Interna">Interna</option>
                    <option value="Imagem Externa">Externa</option>
                    <option value="Unidade">Unidades</option>
                    <option value="Planta Humanizada">Plantas Humanizadas</option>
                </select>

                <label>Tipo de Arquivo</label>
                <select name="tipo_arquivo" required>
                    <option value="">-- Selecione --</option>
                    <option value="DWG">DWG</option>
                    <option value="PDF">PDF</option>
                    <option value="SKP">SKP</option>
                    <option value="IMG">IMG</option>
                    <option value="Outros">Outros</option>
                </select>

                <div id="referenciasContainer"></div>

                <label>Arquivo</label>
                <input id="arquivoFile" type="file" name="arquivos[]" multiple required>

                <label>Descrição</label>
                <textarea name="descricao" rows="4"></textarea>

                <div class="checkbox-group">
                    <label><input type="checkbox" name="flag_substituicao" value="1"> Substituir existente</label>
                </div>

                <div class="buttons">
                    <button type="button" class="btn-close" id="closeModal">Cancelar</button>
                    <button type="submit" class="btn-submit">Enviar</button>
                </div>
            </form>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="../script/notificacoes.js"></script>
    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>

</body>

</html>