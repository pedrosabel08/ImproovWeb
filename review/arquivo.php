<?php
$obraId = $_GET['obraId'] ?? 1; // ou pegue via query string
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Visualização Pública da Obra</title>
    <link rel="stylesheet" href="styleImagens.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body>

    <div class="container">
        <div class="imagens com-wrapper">
            <!-- WRAPPER COM BOTÃO -->
    <button id="wrapper_btn" onclick="showWrapper()" class="toggle-btn btn-left">
                <i class="fas fa-chevron-right"></i>
            </button>
            <div class="wrapper-container" id="wrapper_container">
                <div id="wrapper"></div>
            </div>

            <!-- IMAGEM COM CONTROLES -->
            <div id="imagem_completa">
                <div id="image_wrapper" class="image_wrapper"></div>
            </div>

            <button id="comment_btn" onclick="showComment()" class="toggle-btn btn-right">
                <i class="fas fa-chevron-left"></i>
            </button>
            <!-- SIDEBAR COM BOTÃO -->
            <div class="sidebar-direita hidden" id="sidebar_direita">
                <h3>Comentários</h3>
                <div class="comentarios"></div>
            </div>
        </div>
    </div>

    <ul id="menuContexto">
        <li onclick="excluirImagem()">Excluir <span>🗑️</span></li>
    </ul>
    <div id="comentarioModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Novo Comentário</h3>
            <textarea id="comentarioTexto" rows="5" placeholder="Digite um comentário..." style="width: calc(100% - 10px); padding: 5px;"></textarea>
            <input type="file" id="imagemComentario" accept="image/*" />
            <div class="modal-actions">
                <button id="enviarComentario" style="background-color: green;">Enviar</button>
                <button id="fecharComentarioModal" style="background-color: red;">Cancelar</button>
            </div>
        </div>
    </div>


    <div id="modal-imagem" class="modal-imagem" onclick="fecharImagemModal()">
        <img id="imagem-ampliada" src="" alt="Imagem ampliada">
    </div>

    <!-- Modal -->
    <div id="imagem-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Enviar Imagens</h2>
            <input type="file" id="input-imagens" multiple accept="image/*">
            <div id="preview" class="preview-container"></div>
            <button id="btn-enviar-imagens">Enviar</button>
        </div>
    </div>
    <script src="scriptImagens.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

</body>

</html>