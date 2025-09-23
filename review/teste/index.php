<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Aprovação de Imagens</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
</head>

<body>

    <div class="main">
        <!-- Swiper Principal -->
        <div class="swiper mySwiper">
            <div class="swiper-wrapper">
                <div class="swiper-slide"><img src="uploads/1.jpg" alt=""></div>
                <div class="swiper-slide"><img src="uploads/2.jpg" alt=""></div>
                <div class="swiper-slide"><img src="uploads/3.jpg" alt=""></div>
            </div>
            <div class="swiper-pagination"></div>
        </div>

        <!-- Thumbs -->
        <div class="swiper myThumbs">
            <div class="swiper-wrapper">
                <div class="swiper-slide"><img src="uploads/1.jpg" alt=""><span>23.RDO_VAL-FIN-2-1.jpg</span></div>
                <div class="swiper-slide"><img src="uploads/2.jpg" alt=""></div>
                <div class="swiper-slide"><img src="uploads/3.jpg" alt=""></div>
                <div class="swiper-slide"><img src="uploads/4.jpg" alt=""></div>
                <div class="swiper-slide"><img src="uploads/5.jpg" alt=""></div>
                <div class="swiper-slide"><img src="uploads/6.jpg" alt=""></div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4 id="imagem_nome">23.RDO_VAL Living</h4>
        <button id="btnAprovar">✅ Aprovar</button>
        <label for="comentarios">Comentários</label>
        <textarea id="comentarios" placeholder="Escreva seu comentário..."></textarea>
        <div class="historico">
            <p><strong>Você:</strong> Ajustar iluminação.</p>
            <p><strong>Cliente:</strong> Está ótimo!</p>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast">✔ Imagem aprovada com sucesso!</div>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
</body>

</html>