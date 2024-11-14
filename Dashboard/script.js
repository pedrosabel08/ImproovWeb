$(document).ready(function () {
    // AJAX para buscar os dados das obras
    $.ajax({
        url: 'dadosTotais.php', // Substitua pelo caminho real do seu arquivo PHP
        method: 'GET',
        dataType: 'json',
        success: function (data) {
            // Itera sobre os dados para adicionar ao carousel
            data.forEach(function (item, index) {
                $('.carousel').append(
                    `<div class="obra">
                        <a href="detalhesObra.html?id=${item.idobra}" class="obra-info">
                        <p>${item.nomenclatura}</p>
                    </div>`
                );
            });

            // Inicializa o Slick Carousel após carregar os dados
            $('.carousel').slick({
                infinite: true,
                slidesToShow: 3,      // Mostra 3 obras por vez
                slidesToScroll: 1,    // Passa um slide por vez
                autoplay: true,
                autoplaySpeed: 5000,
                dots: true,
                arrows: true
            });

            // Fecha o modal ao clicar no "X"
            $('.close').on('click', function () {
                $('#obraModal').hide();
            });
        },
        error: function (error) {
            console.error("Erro ao buscar dados: ", error);
        }
    });
});