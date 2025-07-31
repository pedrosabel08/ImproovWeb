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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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

            <div id="submit_decision">
                <h2>Aprovação da imagem</h2>
                <select name="apr_imagem" id="apr_imagem">
                    <option value="aprovada">Aprovada</option>
                    <option value="reprovada">Reprovada</option>
                    <option value="aprovada_ajustes">Aprovada com ajustes</option>
                </select>
                <button type="button" id="confirmar_aprovacao" style="display: none;">✅</button>
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
    <div id="modalLogin" class="modal" style="background-color: black;">
        <div class="modal-content">
            <h2>Identifique-se</h2>
            <form id="formLogin">
                <input type="text" id="nome" placeholder="Nome" required>
                <input type="email" id="email" placeholder="Email" required>
                <button type="submit">Entrar</button>
            </form>
        </div>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="scriptImagens.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</body>

</html>