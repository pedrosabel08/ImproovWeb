<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redirecionar para a página de login
    header("Location: ../index.html");
    exit();
}

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
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../css/styleSidebar.css">
    <link rel="stylesheet" href="../css/modalSessao.css">


    <link rel="icon" href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTm1Xb7btbNV33nmxv08I1X4u9QTDNIKwrMyw&s"
        type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Adicione os links para DataTables no seu HTML -->
    <!-- ...existing code... -->
    <link rel="stylesheet" href="https://unpkg.com/intro.js/minified/introjs.min.css">
    <script src="https://unpkg.com/intro.js/minified/intro.min.js"></script>
    <!-- ...existing code... -->
    <title>Renders</title>
</head>

<body>
    <?php

    include '../sidebar.php';

    ?>
    <div class="container">
        <header style="position: relative;">
            <img src="../gif/assinatura_preto.gif" alt="" style="width: 200px;">
        </header>
        <button id="startTutorial"
            style="position: fixed;top: 1rem;right: 1rem;font-size: 24px;color: darkblue;"
            onmouseover="document.getElementById('tooltipTutorial').style.display='block'; this.style.color='#007bff'; this.style.cursor='pointer';"
            onmouseout="document.getElementById('tooltipTutorial').style.display='none'; this.style.color='darkblue';">
            <i class="fa-solid fa-circle-info"></i>
        </button>
        <span id="tooltipTutorial"
            style="display:none;position:fixed;top:3.5rem;right:1rem;background:#222;color:#fff;padding:6px 12px;border-radius:4px;font-size:14px;z-index:100;">
            Suporte
        </span>
        <div id="filters">
            <select id="filterStatus"></select>
            <select id="filterColaborador"></select>
        </div>
        <div id="renderGrid" class="render-grid">
            <!-- Os cards serão carregados aqui via AJAX -->
        </div>
    </div>


    <!-- Modal -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <form id="editForm">
                <!-- Coluna da esquerda: imagem -->
                <div class="imagem-preview">
                    <img id="modalPreviewImg" src="" alt="Preview">
                </div>

                <!-- Coluna da direita: detalhes -->
                <div id="job-details">
                    <h3>Job Details</h3>

                    <div class="infos-render">
                        <div class="modal-item">
                            <strong>ID:</strong> <span id="modal_idrender"></span>
                        </div>

                        <div class="modal-item">
                            <strong>Imagem:</strong> <span id="modal_imagem_id"></span>
                        </div>

                        <div class="modal-item">
                            <strong>Status:</strong> <span id="modal_status"></span>
                        </div>

                        <div class="modal-item">
                            <strong>Responsável:</strong> <span id="modal_responsavel_id"></span>
                        </div>

                        <div class="modal-item">
                            <strong>Status:</strong> <span id="modal_status_id"></span>
                        </div>

                        <div class="modal-item">
                            <strong>Computador:</strong> <span id="modal_computer"></span>
                        </div>

                        <div class="modal-item">
                            <strong>Enviado:</strong> <span id="modal_submitted"></span>
                        </div>

                        <div class="modal-item">
                            <strong>Última atualização:</strong> <span id="modal_last_updated"></span>
                        </div>

                        <div class="modal-item">
                            <strong>Tem erro?</strong> <span id="modal_has_error"></span>
                        </div>

                        <div class="modal-item" id="errorsContainer" style="display: none;">
                            <button id="toggleErrors">Mostrar erros ▼</button>
                            <div id="modal_errors" style="display: none; border: 1px solid #ccc; padding: 10px; margin-top: 5px; max-height: 200px; overflow-y: auto;"></div>
                        </div>

                        <div class="modal-item">
                            <strong>Pasta Render:</strong> <span id="modal_job_folder"></span>
                        </div>

                        <div class="modal-item">
                            <strong>Prévia JPG:</strong> <span id="modal_previa_jpg"></span>
                        </div>

                        <div class="modal-item">
                            <strong>Número BG:</strong> <span id="modal_numero_bg"></span>
                        </div>

                        <div class="buttons">
                            <button type="button" id="aprovarRender">Aprovar</button>
                            <button type="button" id="reprovarRender">Reprovar</button>
                            <button id="deleteRender">Excluir</button>
                        </div>
                    </div>
            </form>
        </div>
    </div>

    <!-- Modal POS -->
    <div id="modalPOS" class="modal">
        <div style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:500px;">
            <h2>Referências de Pós-Produção</h2>
            <input type="hidden" id="pos_render_id">
            <div style="margin-bottom:10px;">
                <label for="pos_caminho"><strong>Caminho/Referências:</strong></label>
                <textarea id="pos_caminho" rows="3" style="width:100%;"></textarea>
            </div>
            <div style="margin-bottom:10px;">
                <label for="pos_referencias"><strong>Observações:</strong></label>
                <textarea id="pos_referencias" rows="3" style="width:100%;"></textarea>
            </div>
            <div class="buttons">
                <button id="enviarPOS">Enviar</button>
                <button id="fecharPOS" style="background-color: red;">Fechar</button>
            </div>
        </div>
    </div>


    <div id="modalSessao" class="modal-sessao">
        <div class="modal-conteudo">
            <h2>Sessão Expirada</h2>
            <p>Sua sessão expirou. Deseja continuar?</p>
            <button onclick="renovarSessao()">Continuar Sessão</button>
            <button onclick="sair()">Sair</button>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <script src="script.js"></script>
    <script src="../script/sidebar.js"></script>
    <script src="../script/controleSessao.js"></script>

</body>

</html>