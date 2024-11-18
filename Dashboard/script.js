document.addEventListener("DOMContentLoaded", function () {
    atualizarValores();
    carregarCarousel();

    // Inicialização do Slick após o carregamento completo da página
    function carregarCarousel() {
        $.ajax({
            url: 'dadosTotais.php', // Substitua pelo caminho real do seu arquivo PHP
            method: 'GET',
            dataType: 'json',
            success: function (data) {
                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(function (item) {
                        $('.carousel').append(
                            `<div class="obra">
                            <a href="detalhesObra.html?id=${item.idobra}" class="obra-info">
                                <p>${item.nomenclatura}</p>
                            </a>
                        </div>`
                        );
                    });

                    // Destrói o Slick anterior se existir
                    if ($('.carousel').hasClass('slick-initialized')) {
                        $('.carousel').slick('unslick');
                    }

                    // Inicializa o Slick Carousel após adicionar os dados
                    $('.carousel').slick({
                        infinite: true,
                        slidesToShow: 3,
                        slidesToScroll: 1,
                        autoplay: true,
                        autoplaySpeed: 5000,
                        dots: true,
                        arrows: true
                    });
                } else {
                    console.error("Dados não encontrados ou formato incorreto");
                }
            },
            error: function (error) {
                console.error("Erro ao buscar dados: ", error);
            }
        });
    }
});

function atualizarValores() {
    fetch('atualizarValores.php')
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {  // Verifica se há dados e se não está vazio
                const valores = data[0];  // Acessa o primeiro elemento do array

                // Define os valores nas tags HTML correspondentes
                document.getElementById('total_orcamentos').textContent = `R$${valores.total_orcamento}`;
                document.getElementById('total_producao').textContent = `R$${valores.total_producao}`;
                document.getElementById('obras_ativas').textContent = valores.obras_ativas;
            } else {
                console.error("Dados não encontrados");
            }
        })
        .catch(error => {
            console.error("Erro ao buscar dados:", error);
        });
}
