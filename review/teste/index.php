<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Aprovação de Imagens</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="main">
        <!-- Swiper Principal -->
        <div class="swiper mySwiper">
            <div class="swiper-wrapper" id="swiper-wrapper"></div>
            <div class="swiper-pagination"></div>
        </div>

        <!-- Thumbs -->
        <div class="swiper myThumbs">
            <div class="swiper-wrapper" id="thumbs-wrapper"></div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4 id="imagem_nome">Imagem</h4>
        <div id="motivo-sugerida"></div>

        <button id="aprovar-btn">Escolher este ângulo</button>

        <label for="novo-comentario">Comentários</label>
        <textarea id="novo-comentario" placeholder="Escreva seu comentário..."></textarea>
        <button id="enviar-comentario">Enviar</button>

        <div id="lista-comentarios"></div>
    </div>

    <!-- <div class="acoes">
        <button id="aprovar-btn">Escolher este ângulo</button>
    </div> -->

    <!-- Modal Comentário -->
    <div id="modal-comentario" class="modal">
        <div class="modal-content">
            <h3>Deseja adicionar um comentário?</h3>
            <textarea id="comentario-texto" placeholder="Digite aqui..."></textarea>
            <button id="enviar-comentario">Enviar</button>
            <button id="pular-comentario">Pular</button>
        </div>
    </div>

    <!-- Seletor de Mood -->
    <div id="mood-container" class="hidden">
        <h3>Escolha o mood da imagem</h3>
        <select id="mood-select">
            <option value="">Selecione...</option>
            <option value="claro">Claro</option>
            <option value="escuro">Escuro</option>
            <option value="vibrante">Vibrante</option>
        </select>
        <button id="enviar-mood">Confirmar mood</button>
    </div>

    <div id="toast" class="toast">Escolha salva!</div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="script.js"></script>
</body>

</html>