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

// Chama a função para atualizar os valores ao carregar a página
document.addEventListener("DOMContentLoaded", atualizarValores);

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